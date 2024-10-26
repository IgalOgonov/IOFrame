<?php

namespace IOFrame\Managers\Integrations\Monolog;

if (!defined('IOFrameUtilFileSystemFunctions'))
    require __DIR__ . '/../../../Util/FileSystemFunctions.php';

/* A simple log handler, meant to be used only with IOFrame.
 * @author Igal Ogonov <igal1333@hotmail.com>
 * */

class IOFrameHandler extends \Monolog\Handler\AbstractProcessingHandler {
    /** @var \IOFrame\Handlers\SettingsHandler as defined in IOFrame
     * */
    public \IOFrame\Handlers\SettingsHandler $settings;
    /** @var array Initiation parameters
     * */
    private array $params;
    /** @var string Path to the local logs folder, relative to root (defined by 'absPathToRoot')
     * */
    private string $filePath = '';
    /** @var string Name of the local logs file, inside $filePath
     * */
    private string $fileName = '';
    /** @var bool If true, will check initialization before
     * */
    private bool $checkInit;
    /** @var string
     */
    private string $opMode;

    /**
     * Construction function
     *
     * @param \IOFrame\Handlers\SettingsHandler $settings IOFrame settings handler
     * @param $params array of the form:
     *          string 'fileName':  default 'logs.txt', signifies $fileName if opMode is 'local'.
     *          string 'filePath':  default $settings->getSetting('absPathToRoot') . 'localFiles/logs/', where to save the logs.
     *          bool 'timeBasedFiles':  If set to true, the default folder in 'local' mode will be structured as
     *                                  '$filePath/$timeframe_$filename'
     *          bool 'checkInit':  Set this to true if you want this handler to check file initialization before each
     *                           log operation, and initialize them if they aren't. High performance penalty.
     *          int 'level':  logger param $level, defaults to \Monolog\Logger::DEBUG
     *          bool 'bubble': logger param $bubble, defaults to false
     */

    public function __construct(\IOFrame\Handlers\SettingsHandler $settings, array $params = []) {
        //Define EOL_FILE if for some reason coreUtil has not been included before this class is called
        if (!defined('EOL_FILE'))
            define('EOL_FILE', mb_convert_encoding('&#x000A;', 'UTF-8', 'HTML-ENTITIES'));

        $this->params = $params;

        $this->settings = $settings;
        $this->checkInit = $params['checkInit'] ?? true;
        $this->params['timeBasedFiles'] = $params['timeBasedFiles'] ?? true;
        $this->params['intervals'] = $params['intervals'] ?? 10;
        $this->opMode = $params['opMode'] ?? 'local';

        //Default opMode is to write to the database
        if ($this->opMode == 'local') {
            $this->fileName = ($this->params['timeBasedFiles'] ? (int)(time() / $this->params['intervals']) . '_' : '') . ($params['fileName'] ?? 'logs.txt');
            $this->filePath = $params['filePath'] ?? $settings->getSetting('absPathToRoot') . 'localFiles/logs/';
        }

        parent::__construct($params['level'] ?? \Monolog\Logger::DEBUG, $params['bubble'] ?? false);
    }

    /** Refreshes log time to current time
     * */
    public function refreshLogTime(array $params = []): void {

        $timeBasedFiles = $params['timeBasedFiles'] ?? $this->params['timeBasedFiles'] ?? true;
        $intervals = $params['intervals'] ?? $this->params['intervals'] ?? 10;

        $this->fileName = ($timeBasedFiles ? (int)(time() / $intervals) . '_' : '') . ($params['fileName'] ?? 'logs.txt');
    }

    /** Writes to log - depending on 'opMode'.
     * @param $params array of the form:
     *               'test' - bool, default false - whether this should actually be written to disk
     *               'verbose' - bool, default 'test' - whether to echo the logging verbose info
     * */
    protected function write(array $record, array $params = []): void {
        if ($this->checkInit) {
            if(!$this->initialize())
                return;
        }
        switch ($this->opMode) {
            case 'local':
                $this->writeToFile($record);
                break;
            case 'echo':
                echo $record['message'] . EOL;
                break;
            default:
        }
    }

    /* Writes logs to local system
     * */
    protected function writeToFile(array $record): void {
        $LockManager = new \IOFrame\Managers\LockManager($this->filePath . $this->fileName);
        //This lock can only exist if the default CLI logging handler (or similar process) is working on the file, and preparing to delete it on success
        if($LockManager->waitForMutex(['sec'=>20,'ignore'=>30])){
            //Create file if it does not exist!
            if (!is_file($this->filePath . $this->fileName))
                if (!fclose(fopen($this->filePath . $this->fileName, 'w')))
                    return;
            try {
                \IOFrame\Util\FileSystemFunctions::writeFileWaitMutex(
                    $this->filePath,
                    $this->fileName,
                    json_encode([
                        'channel' => $record['channel'],
                        'level' => $record['level'],
                        'datetime' => $record['datetime']->format('U.u'),
                        'message' => $record['message'],
                        'context' => $record['context'],
                    ]) . EOL_FILE,
                    ['append' => true, 'useNative' => true, 'backUp' => false, 'verbose' => true]
                );
            }
            catch (\Exception){
                //Who's logs the loggers?
            }
        }
    }

    /** Initiates local file writing folder(s) and file
     * The reason mkdir result isn't checked is that 2 requests may try to create a new directory at the same time.
     * As long as the log location is within the project directory, OR is a folder that the PHP process has write access to,
     * this should never throw an exception.
     * */
    private function initLocal(): bool {
        if (!is_file($this->filePath . $this->fileName)) {
            if (!is_dir($this->filePath))
                if(!@mkdir($this->filePath, 0777, true))
                    return false;
            if (!fclose(fopen($this->filePath . $this->fileName, 'w')))
                return false;
        }
        return true;
    }

    /* Calls the relevant initiation function depending on @opMode
     * */
    public function initialize(): bool {
        switch ($this->opMode) {
            case 'local':
                return $this->initLocal();
            case 'echo':
            default:
                return true;
        }
    }
}












