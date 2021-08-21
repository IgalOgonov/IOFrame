<?php

namespace IOFrame\Handlers\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use IOFrame;

/* A simple log handler, meant to be used only with IOFrame.
 * Some classes and constants (such as SettingsHandler, SQLHandler) are meant to be those defined in IOFrame.
 * @author Igal Ogonov <igal1333@hotmail.com>
 * */

class IOFrameHandler extends AbstractProcessingHandler{
    /* @var bool Indicates whether the handler has been initialized (depends on opMode)
     * */
    private $initialized = false;
    /* @var IOFrame\Handlers\SettingsHandler as defined in IOFrame
     * */
    private $settings;
    /* @var IOFrame\Handlers\SQLHandler as defined in IOFrame
     * */
    private $SQLHandler;
    /* @var IOFrame\Handlers\FileHandler as defined in IOFrame
     * */
    private $FileHandler = null;
    /* @var string Possible modes - 'db','local', 'echo'
     * */
    private $opMode;
    /* @var string Identifier (not exact name) for the table to log into in the database
     * */
    private $tableName = '';
    /* @var string Path to the local logs folder, relative to root (defined by 'absPathToRoot')
     * */
    private $filePath = '';
    /* @var string Name of the local logs file, inside $filePath
     * */
    private $fileName = '';
    /* @var bool If set to false, writing to local log file will override existing file.
     * */
    private $appendToLog = true;
    /* @var bool Specify to FileHandler to use native PHP locks rather than IOFrame\LockHandler
     * */
    private $useNativeLock = true;
    /* @var bool If true, will check initialization before
     * */
    private $checkInit = false;
    /* @var bool If true, will append a folder based on the minute of creation to default local file path.
     * */
    private $timeBasedFolders = true;
    /* @var bool If true, will add a prefix to the log file based on the type of log being written
     * */
    private $typePrefix = false;

    /**
     * Construction function
     *
     * @param  IOFrame\Handlers\SettingsHandler  $settings  IOFrame settings handler
     * @param  IOFrame\Handlers\SQLHandler $SQLHandler  IOFrame database handler. Can be left null and built from $settings.
     * @param  string  $opMode  Operation mode. May be 'db', 'local' or 'echo'. Wrong type resolves to 'echo'.
     * @param  string  $target  Signifies $tableName if opMode is 'db', or $filePath.$fileName if opMode is 'local'
     *                          Defaults to 'ACTIVE' and '_logs/logs_active.txt', respectively.
     * @param  bool  $structuredOutput  If set to true, the default folder in 'local' mode will be structured as
     *                                  '_logs/<current minute>/<filename>' and the fileName will always be appended
     *                                  with 'warning_/info_/critical_' etc, depending on its level, before it is saved.
     * @param  bool  $checkInit  Set this to true if you want this handler to check db/file initialization before each
     *                           log operation, and initialize them if they aren't. High performance penalty.
     * @param  int $level  logger param $level
     * @param  bool $bubble logger param $bubble
     */

    public function __construct(IOFrame\Handlers\SettingsHandler $settings, IOFrame\Handlers\SQLHandler $SQLHandler = null, $opMode ='local',
                                $timeBasedFolders = true, $checkInit = false, $target = 'ACTIVE', $level = Logger::DEBUG, $bubble = true){
        //Define EOL_FILE if for some reason coreUtil has not been included before this class is called
        if(!defined('EOL_FILE'))
            define('EOL_FILE',mb_convert_encoding('&#x000A;', 'UTF-8', 'HTML-ENTITIES'));

        $this->settings = $settings;
        $this->timeBasedFolders = $timeBasedFolders;
        $this->checkInit = $checkInit? true : false;
        //Create a new SQLHandler if needed
        $this->SQLHandler = $SQLHandler;

        //Default opMode is to write to the database
        $this->appendToLog = false; //Only relevant if writing to file
        if($opMode == 'db' || $opMode === '' ){
            $this->opMode = 'db';
            //Sanitize tableName, then set it
            $sqlPrefix = $SQLHandler->getSQLPrefix();
            if(preg_match('/\W/',$target) || strlen($target.$sqlPrefix.'LOGS_') > 64)
                $target = 'ACTIVE';
            $this->tableName = $sqlPrefix.'LOGS_'.$target;
        }
        elseif($opMode == 'local'){
            $this->opMode = 'local';
            $this->appendToLog = true;
            //Sanitize tableName, then set it
            if(preg_match_all('/\w|.|\//',$target)<strlen($target) || strlen($target)>100 || $target == 'ACTIVE'){
                $target =  $this->timeBasedFolders? 'localFiles/logs/'.((int)((time()%3600)/60)).'/logs.txt' : 'localFiles/logs.txt';
            }
            $this->fileName = substr(strrchr($target, "/"), 1);
            $this->filePath = $settings->getSetting('absPathToRoot').substr($target, 0, strripos($target,'/')+1);
        }
        else{
            $this->opMode = 'echo';
        }

        parent::__construct($level, $bubble);
    }

    /* Writes to log - depending on the operation @opMode.
     * */
    protected function write(array $record): void{
        if ($this->checkInit) {
            $this->initialize();
        }
        switch($this->opMode){
            case 'db':
                $this->writeToDB($record);
                break;
            case 'local':
                $this->writeToFile($record);
                break;
            case 'echo':
                echo $record['message'].EOL;
                break;
            default:
        }
    }

    /* Writes logs to the database
     * */
    protected function writeToDB(array $record){
        $this->SQLHandler->exeQueryBindParam(
            'INSERT INTO '.$this->tableName.' (channel, level, message, time) VALUES (:channel, :level, :message, :time)',
            [[':channel',$record['channel']],[':level',$record['level']],[':message',$record['formatted']],[':time',$record['datetime']->format('U')]]);
    }

    /* Writes logs to local system
     * */
    protected function writeToFile(array $record){
        if($this->FileHandler == null){
            $this->FileHandler = new IOFrame\Handlers\FileHandler();
        }
        $fileName = $this->fileName;
        if($this->typePrefix){
            $prefix = '';
            switch($record['level']){
                case 100:
                    $prefix = 'debug_';
                    break;
                case 200:
                    $prefix = 'info_';
                    break;
                case 250:
                    $prefix = 'notice_';
                    break;
                case 300:
                    $prefix = 'warning_';
                    break;
                case 400:
                    $prefix = 'error_';
                    break;
                case 500:
                    $prefix = 'critical_';
                    break;
                case 550:
                    $prefix = 'alert_';
                    break;
                case 600:
                    $prefix = 'emergency_';
                    break;
                default:
                    ;
            }
            //Create file if it does not exist!
            $fileName = $prefix.$this->fileName;
            if(!is_file($this->filePath.$fileName))
                if(!fclose(fopen($this->filePath.$fileName,'w')))
                    throw new \Exception('Could not create log file at '.$this->filePath.$fileName);
        }
            $this->FileHandler->writeFileWaitMutex(
                $this->filePath,
                $fileName,
                '"'.$record['channel'].'"#&%#'.$record['level'].'#&%#"'.$record['datetime']->format('U').'"#&%#"'.$record['message'].'"'.EOL_FILE,
                ['append' => $this->appendToLog, 'backUp' => false, 'useNative' => $this->useNativeLock]
                );
    }

    /* Sets local file writing settings
     * @param bool $appendToLog as described in class variables.
     * @param bool $structuredOutput as described in class variables.
     * @param bool $useNativeLock as described in class variables.
     * @param bool $timeBasedFolders as described in class variables.
     * @param bool $typePrefix as described in class variables.
     * */
    public function setLocal($appendToLog = true, $useNativeLock = true, $timeBasedFolders = true, $typePrefix = false){
        $this->appendToLog = ($appendToLog) ? true : false;
        $this->useNativeLock = ($useNativeLock) ? true : false;
        $this->typePrefix = ($typePrefix) ? true : false;
        $this->timeBasedFolders = ($timeBasedFolders) ? true : false;
    }

    /* Initiates local file writing folder(s) and file
     * */
    private function initLocal(){
        if(!is_file($this->filePath.$this->fileName)){
            $pathSoFar = $this->filePath;
            //Make sure the directories up to the specified one exist
            $filePathArr = explode('/',$this->filePath);
            $tempCount = count($filePathArr)-1;
            //See how many directories in our path do not exist
            for($i = $tempCount; $i>=0; $i--){
                $pathSoFar = substr($pathSoFar,0,strlen($pathSoFar)-strlen($filePathArr[$i])-1);
                if(is_dir($pathSoFar))
                    break;
                $tempCount--;
            }
            //Create needed directories
            $tempCount2 = count($filePathArr)-1;
            for($i = $tempCount; $i<$tempCount2; $i++){
                $pathSoFar .= '/'.$filePathArr[$i];
                if(!mkdir($pathSoFar))
                    throw new \Exception('Could not initiate logger. Folder '.$pathSoFar.$filePathArr[$i].' could not be created');
            }

            if(!fclose(fopen($this->filePath.$this->fileName,'w')))
                throw new \Exception('Could not create log file at '.$this->filePath.$this->fileName);
        }
        $this->initialized = true;
    }

    /* Initiates database table
     * */
    protected function initDB(){
        $this->SQLHandler->exeQueryBindParam('CREATE TABLE IF NOT EXISTS '.$this->tableName.'(
                ID INTEGER UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
                channel VARCHAR(255),
                level INTEGER,
                message LONGTEXT,
                time INTEGER UNSIGNED)
                ENGINE=InnoDB DEFAULT CHARSET = utf8;"'
        );
    }

    /* Calls the relevant initiation function depending on @opMode
     * */
    public function initialize(){
        switch($this->opMode){
            case 'db':
                $this->initDB();
                break;
            case 'local':
                $this->initLocal();
                break;
            case 'echo':
                break;
            default:
        }
        $this->initialized = true;
    }
}












