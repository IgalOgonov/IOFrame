<?php
namespace IOFrame\Managers{

    use IOFrame\Handlers\SettingsHandler;

    define('IOFrameManagersCLIManager',true);
    require_once __DIR__ . '/../../main/definitions.php';

    /** A tool to help build CLI apps.
     * It allows gathering variables from various sources (CLI input, files, environment) in a single, shared interface, and operating on them.
     * It also allows defining actions, matching them, validating additional dependencies, and optionally executing them based on their provided function.
     * A reminder - do not do heavy calculations with PHP, rather write scripts that handle the problem in more performant languages, and call them.
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * */
    class CLIManager
    {
        use \IOFrame\Traits\GetterSetter;
        /** @var bool Whether the manager has been initiated
         */
        protected bool $initiated = false;

        /** @var bool Whether the last initiation failed
         */
        protected bool $failedInitiation = false;

        /** @var bool Whether the flags have been initiated - note this can only be done ONCE
         */
        protected bool $flagsInitiated = false;

        /** @var array Filters defined for each variable
         */
        protected array $filters = [];

        /** @var array Automatically constructed from the definitions
         */
        protected array $help = [
            'header'=>'',
            'actions'=>[

            ],
            'variables'=>[

            ]
        ];

        /** @var array Automatically constructed via all acquired variables from all sources,
         */
        protected array $variables = [];

        /** @var array Helps remember the last variable function. Relevant when initiating again with Files
         */
        protected array $variableFunctions = [];

        /** @var string[] Variables which need to be recalculated upon subsequent initiations (require 'func')
         */
        protected array $recalculatedVariables = [];

        /** @var string[] Variables which got initiated automatically
         */
        protected array $reservedVariables = [];

        /** @var string|null The flag responsible for matching actions,
         */
        protected ?string $action = null;

        /** @var array Action definitions
         */
        protected array $actions = [];

        /** @var \IOFrame\Handlers\SettingsHandler local settings
         */
        protected \IOFrame\Handlers\SettingsHandler $settings;

        /** Standard constructor
         * Can be used either for just validating and gathering variables in a comfortable interface, or execution through first-class functions.
         *
         * @param SettingsHandler $settings Local settings
         * @param array|null $definitions Explained in the "initiate" method
         * @param array $params of the form:
         *          'checkCLI' => <bool, default true - automatically checks for cli interface (never set this false in production, only in testing!)>
         *          the rest are explained in initiate
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, array $definitions = null, array $params = [])
        {
            if( ($params['checkCLI']??true) && (php_sapi_name() != "cli") ){
                die('This program must be accessed through the CLI!');
            }

            $this->_addGetSet(
                ['initiated','failedInitiation','flagsInitiated','help','variables','reservedVariables','action','actions']
            );

            $this->settings = $settings;
            //At least some form of help description is suggested, and it must always be first
            $params['helpDesc'] = $params['helpDesc']??'This is the help message for this cli script.'.EOL.'It lists all available parameters and their full information.'.EOL.'It also lists all available actions, if they exist.'.EOL;

            if(!empty($definitions))
                $this->initiate($definitions,$params);
        }

        /** Initiates the CLI manager.
         * Can be run multiple times, but has to be run at least once to access any functionality.
         * Note that this intentionally doesn't support getting arguments without a flag (e.g. $argv[0], etc), due to it being a suboptimal practice when flags exist.
         *
         * @param array $definitions definitions used to populate $variables and generate help messages, each item of the form:
         *          <id, string> => [
         *              'desc' => <string, description of what this parameter is>
         *              'cliFlag' => <string|string[], default null - cli arg to get this parameter from, will try each arg in order until success.
         *                            Note that once the flags were initiated once, they CANNOT be initiated again.
         *                            After first flag initiation, subsequent cliFlag parameters will be ignored.>
         *              'hasInput' => <bool, default false - whether the CLI flag has input (variables from input-less flags always return true or null)>
         *              'required' => <bool, default false - whether the CLI flag is required (can also be expressed through 'validation', but wont appear in automatic messages)>
         *              'reserved' => <bool, default false - marks as reserved. Has certain implications when loading from files>
         *              'fileObj' => <array|array[], defaults to null - objects of the following form, will try each arg in order until success:
         *                          [
         *                              'type' => <string, supported types are:
         *                                         'config' - required file structure specified in parse-ini-file()
         *                                         'json' - a valid JSON file
         *                                        >
         *                              [config]'sections' => bool, default false - 2nd param in parse-ini-file().
         *                                       Passing this once from any definition, will force the specific file to be opened with sections for all other definitions.
         *                              'key' => <string - param key inside file, can be of the form $path.$to.$key in case of JSON files with nexted objects, or the 'sections' option
         *                                        Note that this also automatically handles keys that include dots, but having sub-sections / nexted objects with the same identifier as the
         *                                        dotted key may lead to errors>
         *                              'filePath' => <string - absolute/relative path to file>
         *                              'pathType' => <string, default 'project' - 'project' for IOFrame root dir, 'abs' for absolute>
         *                          ]
         *                          >
         *              'envArg' => <string|string[], defaults to null - env arg to get this parameter from, will try each arg in order until success>
         *              'validation' => <array, default null - same schema as a single object inside v1APIManager::baseValidation() $filters>
         *              'func' => <function|mixed, can mutate the variable value ($value, $context (this), $params), BEFORE validation.
         *                         If you pass anything non-callable (like false), you can unset this function in this and future initiations>
         *              'recalculated' => <bool, default false - if set to true, will recalculate the variable upon subsequent initiations.
         *                                this requires the variable to have a valid 'func' defined>
         *              [creation-at-runtime] 'isAction' => <bool, true - only if this variable is defined as action in params>
         *          ]
         *          * Note that by default, variable '_help' is overwritten, matches cli flags '-h' and '--help', and is used automatically to generate help on failure
         * @param array $params of the form:
         *          'checkCLI' => <bool, default true - automatically checks for cli interface (never set this false in production, only in testing!)>
         *          'overwrite' => <bool, default false - whether to try to overwrite existing variables if initiating more than once with the same variables in definition>
         *          'verbose' => <bool, default false - verbose output (for testing)>
         *          'silent' => <bool, default false - do not print any messages (overrides all printing settings)>
         *          'generateHelp' => <bool, default true - if true, will generate/update automatic -h/--help messages)>
         *          '/' => <bool, default false - if true, will generate automatic file related variables with flags
         *                                  filePath --fp, filePathType --fpt, fileType --ft,
         *                                  fileConfigSections --fcs,fileKeyMap --fkm, keyFileMap --kfm,
         *                                  which can be used by populateFromFiles()
         *                                  >
         *          'helpDesc' => <string, default string as defined below - message to print before variable descriptions on -h>
         *          'printMissingRequired' => <bool, default true - if true,  prints message when missing required variable)>
         *          'printValidationFailure' => <bool, default true - if true, prints message when a variable failed validation)>
         *          'dieOnFailure' => <bool, default true - if true dies on any missing requirement or failed validation, if false only sets variables that failed validation to null>
         *          'baseValidationParams'=> <array, default [] - optional params to pass each validation function>
         *          'absPathToRoot' - <string, default settings->getSetting('absPathToRoot') -
         *                              allows overwriting the local setting of the same name. Should only be used in the rare case when the system is not yet installed>
         *          'action' => <string, id of variable that should be considered action identifier (if cli flag, will require it to have input)>
         *          'actions' => <array[] of the form:
         *                          <actionName, string - matched by the variable which was defined in "action" param> => [
         *                              'desc' => <string - description for this specific action>,
         *                              'required' =>  <string|string[] - variables required for specific action (which might not be required otherwise)>,
         *                              'func' => <function, executed with params $inputs, &$result['functionErrors'], $this (context), $params >
         *                              'logger' => <Monolog\Logger, default null - if provided, will log action if it runs a function.
         *                                          Should NOT have handlers pre-loaded.
         *                                          Keep in mind stuff inside the action function may already have logging>
         *                              'logChannel' => <string, default null - can be passed instead of a logger to create new Monolog\Logger with this channel>
         *                              'logHandlers' => <\Monolog\Handler\AbstractProcessingHandler[], default [] - handlers to push into the logger>
         *                              'shouldLog' => <function, default output !empty($result['error']) -
         *                                                            executed with params $result, $context, if true will log action result >
         *                              'logLevelCalculator' => <function, default output 'error' -
         *                                                             executed with params $result, $context to decide log level, see Monolog\Logger functions for valid levels >
         *                              'logMessageGenerator' => <function, default output 'Error in CLI action '.$action -
         *                                                            executed with params $result, $context to generate log message >
         *                              'logContextGenerator' => <function, default output [ 'result'=>$result ] -
         *                                                            executed with params $result, $context to generate log context >
         *                          ]
         *                       >
         *
         * @return array of the form:
         * [
         *      <string, variable name> => <string, 'initiated' - initiated normally, 'notInitiated' - not initiated,
         *      'failedValidation' - failed validation, 'action-exists' - cannot change action once set>
         * ]
         */
        function initiate(array $definitions, array $params = []): array {

            $overwrite = $params['overwrite']??false;
            $generateHelp = $params['generateHelp']??true;
            $generateFileRelated = $params['generateFileRelated']??false;
            $helpDesc = $params['helpDesc']??null;
            $silent = $params['silent']??false;
            $printMissingRequired = ($params['printMissingRequired']??true) && !$silent;
            $printValidationFailure = ($params['printValidationFailure']??true) && !$silent;
            $verbose = ($params['verbose']??false) && !$silent;
            $dieOnFailure = $params['dieOnFailure']??true;
            $baseValidationParams = $params['baseValidationParams']??[];
            $absPathToRoot = $params['absPathToRoot']??$this->settings->getSetting('absPathToRoot');

            //Action cannot be changed once passed
            if(!empty($params['action']) && !empty($this->action)){
                $res = [];
                foreach ($definitions as $var => $doesntMatter){
                    $res[$var] = 'action-exists';
                }
                return $res;
            }
            //Set new action if passed
            $this->action = $params['action']?? $this->action?? null;
            //If new action definition passed, set defaults
            if($this->action && array_key_exists($this->action,$definitions)){
                $definitions[$this->action]['hasInput'] = true;
                $definitions[$this->action]['isAction'] = true;
                $definitions[$this->action]['reserved'] = true;
            }


            $this->actions = $this->actions??[];
            $params['actions'] = $params['actions']??[];
            $this->actions = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($this->actions,$params['actions']);

            $shortFlagsToGet = [];
            $longFlagsToGet = [];
            $filesToOpen = [];
            $configFileData = [];
            $result = [];

            if($helpDesc)
                $this->help['header'] = $helpDesc.EOL;
            if($generateFileRelated){
                foreach (['filePath', 'filePathType', 'fileType', 'fileConfigSections', 'fileKeyMap', 'keyFileMap'] as $toInitiate){
                    if(empty($definitions[$toInitiate]) && !isset($this->variables[$toInitiate])){
                        $this->reservedVariables[] = $toInitiate;
                        switch ($toInitiate){
                            case 'filePath':
                                $definitions['filePath'] = [
                                    'desc' => 'Path to file',
                                    'cliFlag'=>['--fp'],
                                    'hasInput'=>true,
                                    'reserved'=>true,
                                    'validation'=>['type'=>'string','required'=>false]
                                ];
                                break;
                            case 'filePathType':
                                $definitions['filePathType'] = [
                                    'desc' => 'Type of file path (default "project")',
                                    'cliFlag'=>['--fpt'],
                                    'hasInput'=>true,
                                    'reserved'=>true,
                                    'validation'=>['type'=>'string','required'=>false,'default'=>'project']
                                ];
                                break;
                            case 'fileType':
                                $definitions['fileType'] = [
                                    'desc' => 'Type of file (default "json")',
                                    'cliFlag'=>['--ft'],
                                    'hasInput'=>true,
                                    'reserved'=>true,
                                    'validation'=>['type'=>'string','required'=>false,'default'=>'json']
                                ];
                                break;
                            case 'fileConfigSections':
                                $definitions['fileConfigSections'] = [
                                    'desc' => 'Parse config files with "sections" == true',
                                    'reserved'=>true,
                                    'cliFlag'=>['--fcs']
                                ];
                                break;
                            case 'fileKeyMap':
                                $definitions['fileKeyMap'] = [
                                    'desc' => 'File-Key map - explained in CLIManager->populateFromFiles()',
                                    'cliFlag'=>['--fkm'],
                                    'hasInput'=>true,
                                    'reserved'=>true,
                                    'validation'=>['type'=>'string','required'=>false]
                                ];
                                break;
                            case 'keyFileMap':
                                $definitions['keyFileMap'] = [
                                    'desc' => 'Key-File map - explained in CLIManager->populateFromFiles()',
                                    'cliFlag'=>['--kfm'],
                                    'hasInput'=>true,
                                    'reserved'=>true,
                                    'validation'=>['type'=>'string','required'=>false]
                                ];
                                break;
                            default:
                                break;
                        }
                    }
                }
            }
            if($generateHelp && empty($definitions['_help']) && !isset($this->variables['_help'])){
                $this->reservedVariables[] = '_help';
                $definitions['_help'] = [
                    'desc' => 'Prints this message',
                    'cliFlag'=>['-h','--help'],
                    'hasInput'=>false
                ];
            }

            foreach ($definitions as $varName => $definition){

                //Register variable to result
                $result[$varName] = 'notInitiated';

                //Flags to get from CLI
                if(!$this->flagsInitiated && ($definition['cliFlag']??null)){
                    if(!is_array($definition['cliFlag'])){
                        $definition['cliFlag'] = [$definition['cliFlag']];
                        $definitions[$varName]['cliFlag'] = [$definition['cliFlag']];
                    }
                    $definitions[$varName]['cliFlagsArr'] = [];
                    foreach ($definition['cliFlag'] as $flag){
                        $length = 'short';
                        if(str_starts_with($flag,'--'))
                            $length = 'long';
                        elseif (!str_starts_with($flag,'-')){
                            if($verbose)
                                echo 'Variable '.$varName. 'flag '.$flag.' is invalid.'.EOL;
                            continue;
                        }

                        $flag = substr($flag, $length === 'short' ? 1 : 2);

                        $definitions[$varName]['cliFlagsArr'][] = $flag;

                        if($definition['hasInput']??false)
                            $flag .= ':';

                        $length === 'short' ?
                            array_push($shortFlagsToGet, $flag) :
                            array_push($longFlagsToGet, $flag);
                    }
                }

                //Files to open
                if($definition['fileObj']??false){
                    if(isset($definition['fileObj']['type'])){
                        $definition['fileObj'] = [$definition['fileObj']];
                        $definitions[$varName]['fileObj'] = $definition['fileObj'];
                    }
                    foreach ($definition['fileObj'] as $i => $fileDefinitions){
                        if(!($fileDefinitions['type']??false) || !($fileDefinitions['key']??false) || !($fileDefinitions['filePath']??false)){
                            if($verbose)
                                echo 'Variable '.$varName.' has invalid fileObj'.EOL;
                            continue;
                        }

                        $filePath = $fileDefinitions['filePath'];
                        switch ($fileDefinitions['pathFromRoot']??'relative'){
                            case 'abs':
                                break;
                            case 'project':
                            default:
                                $filePath = $absPathToRoot.$filePath;
                                break;
                        }

                        $definitions[$varName]['fileObj'][$i]['fullFilePath'] = $filePath;

                        if(empty($filesToOpen[$filePath]))
                            $filesToOpen[$filePath] = [
                                'type'=>$fileDefinitions['type'],
                                'sections'=>$fileDefinitions['sections']??false
                            ];
                        elseif ($fileDefinitions['sections']??false)
                            $filesToOpen[$filePath]['sections'] = true;
                    }
                }

                //Normalize env into arrays
                if($definition['envArg']??false){
                    if(!is_array($definition['envArg'])) {
                        $definitions[$varName]['envArg'] = [$definition['envArg']];
                    }
                }

                //Filters
                if($definition['validation']??false){
                    $this->filters[$varName] = $definition['validation'];
                }

                //Functions
                if(isset($definition['func'])){
                    if(is_callable($definition['func']))
                        $this->variableFunctions[$varName] = $definition['func'];
                    elseif(isset($this->variableFunctions[$varName]))
                        unset($this->variableFunctions[$varName]);
                }

                //Handled recalculated variables
                if( ($definition['recalculated']??false) &&
                    isset($this->variableFunctions[$varName]) &&
                    !in_array($varName,$this->recalculatedVariables))
                {
                    $this->recalculatedVariables[] = $varName;
                }

                //Handle reserved variables
                if(($definition['reserved']??false) && !in_array($varName,$this->reservedVariables)){
                    $this->reservedVariables[] = $varName;
                }

                //Help
                if($generateHelp){

                    $toMerge = [];

                    if(in_array($varName,$this->reservedVariables))
                        $toMerge['isReserved'] = 1;
                    if($definition['isAction']??false)
                        $toMerge['isAction'] = 1;
                    if($definition['required']??false)
                        $toMerge['isRequired'] = 1;
                    if($definition['hasInput']??false)
                        $toMerge['hasInput'] = 1;

                    if($definition['desc']??false)
                        $toMerge['desc'] = $definition['desc'];

                    if($definition['cliFlag']??false)
                        $toMerge['flags'] = implode(', ',$definition['cliFlag']);

                    if($definition['envArg']??false)
                        $toMerge['env'] = implode(', ',$definition['envArg']);

                    if(!empty($definitions[$varName]['fileObj'])){
                        $toMerge['files'] = $definitions[$varName]['fileObj'];
                    }

                    if(empty($this->help['variables'][$varName]))
                        $this->help['variables'][$varName] = [];

                    $this->help['variables'][$varName] = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct(
                        $this->help['variables'][$varName],
                        $toMerge
                    );
                }
            }

            //Actions Help
            foreach ($this->actions as $action => $actionArr){
                //Set required to array
                if(empty($actionArr['required'])){
                    $this->actions[$action]['required'] = [];
                }
                elseif(!is_array($actionArr['required'])){
                    $this->actions[$action]['required'] = [$actionArr['required']];
                }

                if($generateHelp){
                    $toMerge = [];

                    if( isset($actionArr['func']) && is_callable($actionArr['func']) )
                        $toMerge['isFunctional'] = 1;

                    if($this->actions[$action]['desc']??false)
                        $toMerge['desc'] = $this->actions[$action]['desc'];

                    if(!empty($this->actions[$action]['required']))
                        $toMerge['required'] = $this->actions[$action]['required'];

                    if(empty($this->help['actions'][$action]))
                        $this->help['actions'][$action] = [];

                    $this->help['actions'][$action] = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct(
                        $this->help['actions'][$action],
                        $toMerge
                    );
                }
            }

            //Get the CLI Flags
            if(count($shortFlagsToGet) || count($longFlagsToGet)){
                $flags = getopt(implode('',$shortFlagsToGet),$longFlagsToGet);
                $this->flagsInitiated = true;
            }
            else
                $flags = [];

            //Get the files
            foreach ($filesToOpen as $filePath => $fileConfig){
                if(!@is_file($filePath)){
                    if($verbose)
                        echo 'File '.$filePath.' does not exist'.EOL;
                    continue;
                }
                $fileContents = null;
                $fileError = false;
                switch ($fileConfig['type']){
                    case 'json':
                        try{
                            $fileContents = \IOFrame\Util\FileSystemFunctions::readFile($filePath);
                            if(!\IOFrame\Util\PureUtilFunctions::is_json($fileContents)){
                                if($verbose)
                                    echo 'File '.$filePath.' not a valid json'.EOL;
                                $fileError = true;
                            }
                            else
                                $fileContents = json_decode($fileContents,true);
                        }
                        catch (\Exception $e){
                            if($verbose)
                                echo 'File '.$filePath.' cannot be opened, exception '.$e->getMessage().EOL;
                            $fileError = true;
                        }
                        break;
                    case 'config':
                        $fileContents = parse_ini_file($filePath, $fileConfig['sections']);
                        if(empty($fileContents)){
                            if($verbose)
                                echo 'File '.$filePath.' cannot be opened or empty'.EOL;
                            $fileError = true;
                        }
                        break;
                    default:
                }
                if($fileError){
                    continue;
                }
                else{
                    $configFileData[$filePath] = $fileContents;
                }
            }

            //This will be checked within the next 2 blocks
            $failure = false;

            //If we are recalculating any variables, add an empty definition (this will only trigger 'func')
            foreach ($this->recalculatedVariables as $var)
                if(empty($definitions[$var]))
                    $definitions[$var] = [];

            //Go over each variable again, and update its value from wherever possible
            foreach ($definitions as $varName => $definition){

                if(isset($this->variables[$varName]) && !$overwrite)
                    continue;

                $value = null;

                //Get from relevant CLI flag
                if(count($definition['cliFlagsArr']??[])){
                    foreach ($definition['cliFlagsArr'] as $flag){
                        if($value!==null)
                            break;
                        if($definition['hasInput']??false)
                            $value = $flags[$flag] ?? null;
                        else
                            $value = isset($flags[$flag]) ? true : null;
                    }
                }

                //If we didn't get value from CLI, try file
                if(($value===null) && !empty($definition['fileObj']??[])){
                    foreach ($definition['fileObj'] as $fileObj){
                        if($value!==null)
                            break;
                        if(empty($fileObj['fullFilePath']) || empty($configFileData[ $fileObj['fullFilePath'] ]))
                            continue;
                        $target = $configFileData[ $fileObj['fullFilePath'] ];
                        $keyArr = explode('.',$fileObj['key']);
                        while(($value===null) && count($keyArr)){

                            if (isset($target[implode('.',$keyArr)]))
                                $value = $target[implode('.',$keyArr)];

                            $topKeyLevel = array_shift($keyArr);
                            if(isset($target[$topKeyLevel])){
                                if(count($keyArr) === 0)
                                    $value = $target[$topKeyLevel];
                                else
                                    $target = $target[$topKeyLevel];
                            }
                            else
                                break;
                        }
                    }
                }
                //If we didn't get value from CLI/File, try env
                if(($value===null) && ($definition['envArg']??false)){
                    foreach ( $definition['envArg'] as $envArg){
                        if($value!==null)
                            break;
                        if(getenv($envArg) !== false)
                            $value = getenv($envArg);
                    }
                }

                //If value was required but is missing
                if(($value === null) && ($definition['required']??false)){
                    if($printMissingRequired && !$silent)
                        echo 'Missing required variable '.$varName.', run -h for help.'.EOL;
                    $failure = true;
                }

                //Populate variable with value
                $newValue = (isset($this->variableFunctions[$varName])) ? $this->variableFunctions[$varName]($value,$this,$params) : $value;

                if(!isset($this->variables[$varName]) || $overwrite){
                    if($newValue)
                        $result[$varName] = 'initiated';
                    $this->variables[$varName] = $newValue;
                }
            }

            //Finally, validate anything that needed validation
            if(count($this->filters)){
                $validationResult = \IOFrame\Managers\v1APIManager::baseValidation($this->variables,$this->filters,array_merge(['test'=>$verbose],$baseValidationParams));
                if(!$validationResult['passed']){

                    if($printValidationFailure && !$silent)
                        echo 'Following variables failed validation: '.implode(', ',$validationResult['failed']).EOL;

                    $failure = true;

                    foreach ($validationResult['failed'] as $varName){
                        $result[$varName] = 'failedValidation';
                        $this->variables[$varName]  = null;
                    }
                }
            }

            if($failure){
                if($dieOnFailure)
                    die(json_encode($result));
                elseif(!$silent){
                    $this->printHelp();
                    echo EOL.'Initiation failed, see above help file'.EOL;
                }
                $this->failedInitiation = true;
            }
            else
                $this->initiated = true;

            return $result;
        }

        /** Prints help either for the whole cli program, or a specific action
         * @param string|null $action
         */
        function printHelp(string $action = null): void {
            if(!$this->initiated){
                echo 'CLI Manager not initiated';
                return;
            }

            if($action){
                if(!empty($this->actions[$action])){
                    echo 'Action '.$action.': '. ($this->actions[$action]['desc']??'No description.') .EOL;
                }
                else
                    echo 'Action '.$action.' does not exist.'.EOL;
            }
            else{
                echo EOL.'---- VARIABLES ----'.EOL.EOL;
                foreach ($this->help['variables'] as $var => $info){
                    $msg = '--- VAR '.$var;
                    if(!empty($info['isReserved']))
                        $msg .= ' [RESERVED]';
                    if(!empty($info['isAction']))
                        $msg .= ' [ACTION]';
                    if(!empty($info['isRequired']))
                        $msg .= ' [REQUIRED]';
                    if(!empty($info['hasInput']))
                        $msg .= ' [HAS INPUT]';
                    $msg .= EOL;
                    if(!empty($info['desc']))
                        $msg .= 'Description: '.$info['desc'].EOL;
                    if(!empty($info['flags']))
                        $msg .= 'Flag(s): '.$info['flags'].EOL;
                    if(!empty($info['env']))
                        $msg .= 'Environment Variable(s): '.$info['env'].EOL;
                    if(!empty($info['files'])){
                        $msg .= 'Files(s): '.EOL;
                        foreach($info['files'] as $fileInfo)
                            $msg .= $fileInfo['type'].' file: '.$fileInfo['fullFilePath'].' - key: '.$fileInfo['key'].EOL;
                    }
                    $msg .= EOL;
                    echo $msg;
                }
                echo EOL.'---- ACTIONS ----'.EOL.EOL;
                foreach ($this->help['actions'] as $action => $info){
                    $msg = '--- ACTION '.$action;
                    if(!empty($info['isFunctional']))
                        $msg .= ' '.'[FUNCTIONAL]';
                    $msg .= EOL;
                    if(!empty($info['desc']))
                        $msg .= 'Description: '.$info['desc'].EOL;
                    if(!empty($info['required'])){
                        $msg .= 'Required variables: ';
                        $msg .= implode(', ',$info['required']).EOL;
                    }
                    $msg .= EOL;
                    echo $msg;
                }
                echo EOL.$this->help['header'].EOL;
            }
        }

        /** Matches an action, and checks if its requirements are met.
         * By default, prints its help message if _help (-h, --help) was passed.
         * May also execute related function if available, and if the inputs parameter is passed (doesn't have to be in actual use).
         * The function takes the following 4 params:
         *  $inputs - Passed via $params
         *  $errors - Created in this function, should be taken by reference if the function might spawn errors.
         *  $this - The context of this specific class, with access to all its variables after initiation.
         *  $params - The parameters passed to the main function, allowing reuse of 'test', 'silent', etc.
         * The structure of the error array is defined by the function, if used.
         * If input is not passed, only used for validation purposes.
         *
         * @param string|null $action Defaults to action matched by the action variable
         * @param array $params - Params of the form:
         *          'test' - bool, default false - Indicates the function should have no side effects
         *          'silent' - bool, default false - indicates the function should only print its return value. Overrides verbose and checkHelp
         *          'verbose' - bool, default false - Verbose mode for testing
         *          'checkHelp' - bool, default true - prints the help message for the function if _help (-h, --help) was passed
         *          'printResult' - bool, default true - prints the actions' result
         *          'inputs' - mixed, default null - if any inputs are passed, will try to execute the matched actions' function, if available.
         *          * Note that if you want to execute a function that doesn't take inputs, you may pass any arbitrary true value to 'inputs' (e.g. true, 1, etc)
         *          * All params are passed to the function. $inputs are passed separately for convenience.
         * @returns array of the form:
         *      {
         *          "result": <mixed, default null - any result from the function, if executed and didn't spawn any errors>,
         *          "error" : <string, default null - one of the following values:
         *                      'initiation' - tried to run uninitiated,
         *                      'config' - action variable isn't defined,
         *                      'match' - action variable value doesn't match any action,
         *                      'required' - missing required variables for action
         *                      'no-func' - passing inputs to action, but it has no execution function
         *                      'action' - function populated the errors array
         *                      'exception' - exception thrown during action execution
         *                      >,
         *          "errorDetails": <string[], default [] - in case of required variables missing, lists hem>
         *          "functionErrors": <array, default [] - array of errors potentially populated by the function.
         *                            Structure of each element is defined by the function. To use this, the function needs
         *                            to take the 2nd param by reference.>
         *      }
         */
        function matchAction(string $action = null, array $params = []): array {
            $test = $params['test'] ?? false;
            $silent = $params['silent'] ?? false;
            $verbose = !$silent && ($params['verbose'] ?? $test);
            $checkHelp = !$silent && ($params['checkHelp'] ?? true);
            $printResult= $params['printResult'] ?? false;
            $inputs= $params['inputs'] ?? null;

            $result = [
                "result" => null,
                "error" => null,
                "errorDetails" => [],
                "functionErrors" => []
            ];

            if(!$this->initiated){
                $result['error'] = 'initiation';
                if($verbose)
                    echo 'Tried to run uninitiated'.EOL;
                if($printResult)
                    echo json_encode($result);
                return $result;
            }

            if(!$action && $this->action)
                $action = $this->variables[$this->action]??null;

            if(!$action){
                if($checkHelp && $this->variables['_help']){
                    $this->printHelp();
                    return $result;
                }
                $result['error'] = 'config';
                if($verbose)
                    echo 'Action variable isn\'t defined'.EOL;
                if($printResult)
                    echo json_encode($result);
                return $result;
            }

            if(empty($this->actions[$action])){
                $result['error'] = 'match';
                if($verbose)
                    echo 'Action variable value doesn\'t match any action'.EOL;
                if($printResult)
                    echo json_encode($result);
                return $result;
            }

            if($checkHelp && ($this->variables['_help']??false)){
                $this->printHelp($action);
                return $result;
            }

            if(!empty($this->actions[$action]['required'])){
                if(!is_array($this->actions[$action]['required']))
                    $this->actions[$action]['required'] = [$this->actions[$action]['required']];
                foreach ($this->actions[$action]['required'] as $requiredVariable){
                    if(!isset($this->variables[$requiredVariable])){
                        $result['error'] = 'required';
                        $result['errorDetails'][] = $requiredVariable;
                        if($printResult)
                            echo json_encode($result);
                    }
                }
            }

            if($result['error'])
                return $result;

            if($inputs){

                if(empty($this->actions[$action]['func'])){
                    $result['error'] = 'no-func';
                }
                else{
                    try {
                        $result['result'] = $this->actions[$action]['func']($inputs, $result['functionErrors'], $this, $params);
                    }
                    catch (\Exception $e){
                        $result['result'] = null;
                        $result['error'] = 'exception';
                        $result['errorDetails'] = [
                            'exception'=>[
                                'code'=>$e->getCode(),
                                'msg'=>$e->getMessage(),
                                'trace'=>$e->getTraceAsString(),
                                'file'=>$e->getFile(),
                                'line'=>$e->getLine()
                            ]
                        ];
                    }
                }

                if(count($result['functionErrors']) && empty($result['error'])){
                    $result['error'] = 'action';
                }

                $logger = null;

                /* Initiate logger from channel or default */
                if(
                    !empty($this->actions[$action]['logger']) &&
                    (get_class($this->actions[$action]['logger']) === 'Monolog\Logger')
                ){
                    $logger = $this->actions[$action]['logger'];
                }
                elseif(!empty($this->actions[$action]['logChannel'])){
                    $logger = new \Monolog\Logger($this->actions[$action]['logChannel']);
                }

                /* Pass handlers.
                   Note that if you pre-defined and passed an existing logger, you can usually still pass handlers
                   this way because by default, each action still only runs once in each process.
                   However, if a CLI app allows chaining actions, you should re-create the logger in each action (using logChannel)
                */
                if(
                    $logger &&
                    !empty($this->actions[$action]['logHandlers']) &&
                    is_array($this->actions[$action]['logHandlers'])
                )
                    foreach ($this->actions[$action]['logHandlers'] as $handler){
                        //Assuming we're using the default handler, we don't want a potentially very long request to log at its start time
                        if(method_exists($handler,'refreshLogTime'))
                            $handler->refreshLogTime();
                        $logger->pushHandler($handler);
                    }

                /* Log if logger exists and we should log */
                if(
                    $logger &&
                    (
                        !empty($this->actions[$action]['shouldLog'])?
                            $this->actions[$action]['shouldLog']($result,$this) :
                            !empty($result['error'])
                    )
                ){
                    $logMessage = !empty($this->actions[$action]['logMessageGenerator']) ?
                        $this->actions[$action]['logMessageGenerator']($result,$this) :
                        'Error in CLI action '.$action;

                    $logContext = !empty($this->actions[$action]['logContextGenerator']) ?
                        $this->actions[$action]['logContextGenerator']($result,$this) :
                        [ 'result'=>$result ];

                    call_user_func_array(
                        [
                            $logger,
                            !empty($this->actions[$action]['logLevelCalculator'])?
                                $this->actions[$action]['logLevelCalculator']($result,$this) :
                                'error'
                        ],
                        [$logMessage,$logContext]
                    );
                }

            }

            if($printResult)
                echo json_encode($result);
            return $result;
        }

        /** Needs to be run after the flags' initiation.
         * Note that filePath, keyFileMap and fileKeyMap are independent of each other, and each add file sources to variable definitions.
         * Also note that reserved variables (such that were generated automatically, or defined as the action variable) are skipped by default.
         * Initiates the CLI manager again, automatically adding definitions based on the following variables:
         *
         * --- filePath --fp
         * String, path to a single file.
         * All existing variables will be added:
         * 'fileObj'=>['filePath'=>$filePath, 'type'=>$fileType,'pathType'=>$filePathType, 'sections'=>$fileConfigSections 'key'=>$variableId]
         *
         * --- filePathType --fpt
         * String, default 'project' - corresponds to $definitions[$id]['fileObj']['pathType']
         *
         * --- fileType --ft
         * String, default 'json' - corresponds to $definitions[$id]['fileObj']['type']
         *
         * --- fileConfigSections --fcs
         * Bool, default false - corresponds to $definitions[$id]['fileObj']['sections']
         *
         * --- keyFileMap --kfm
         * String|json, can either pass a valid json directly, or a path to a json file.
         * If valid json, will try to parse and use directly.
         * If not a valid json, will first try to find path relative to project root, then absolute.
         * The structure of each object in the json is as follows:
         * <string, variableKey>:{
         *     'type':<string, default 'json' - corresponds to $definition['fileObj']['type']>
         *     'sections':<string, default false - corresponds to $definition['fileObj']['sections']>
         *     'key':<string, default $variableKey - corresponds to $definition['fileObj']['key']>
         *     'pathType':<string, default 'project' - corresponds to $definition['fileObj']['pathType']>
         *     'filePath':<string - must be provided for each variable - corresponds to $definition['fileObj']['filePath']>
         * }
         * This will construct a definitions object for each relevant variable.
         * Using this, one can add different files to read variables from, but each variable can only get one file source.
         *
         * --- fileKeyMap --fkm
         * String|json, can either pass a valid json directly, or a path to a json file.
         * If valid json, will try to parse and use directly.
         * If not a valid json, will first try to find path relative to project root, then globally.
         * The structure of each object in the json is as follows:
         * <string, filePath>:{
         *      <string, variableKey>:{
         *          'type':<string, default 'json' - corresponds to $definition['fileObj']['type']>
         *          'sections':<string, default false - corresponds to $definition['fileObj']['sections']>
         *          'key':<string, default $variableKey - corresponds to $definition['fileObj']['key']>
         *          'pathType':<string, default 'project' - corresponds to $definition['fileObj']['pathType']>
         *          'filePath':<string, filled by default, and is overwritten>
         *      },
         *      ...
         * }
         * This will construct a definitions object for each relevant variable, indexed by the files they should be read from.
         * Note that multiple files *can* contain the same variable - they will be added to fileObj of that variable, in
         * the same order they were defined in this object.
         *
         * @param array $params Params passed to initiate(), as well as:
         *              'skipReserved' => <bool, default true - skips reserved variables>
         *              'skipInitiated' => <bool, default true - skips variables which were already initiated>
         *              'generateNewHelp' => <bool, default false - whether to re-generate help based on new difinitions>
         *              'absPathToRoot' - <string, default settings->getSetting('absPathToRoot') -
         *                                allows overwriting the local setting of the same name. Should only be used in the rare case when the system is not yet installed>
         *
         * @returns array Result of the form:
         *              'file' => <bool, default false - whether we successfully built a definition from filePath (--fp)>
         *              'kfm','fkm' => <string, default null -
         *                  null - weren't provided
         *                  'file-invalid' - file/json provided was invalid
         *                  'file-not-json' - file provided was not a valid json
         *                  'file-unavailable' - file provided could not be opened
         *                  'format-invalid' - object provided was of invalid format
         *                  'undefined-variables' - one or more of the variable ids were not previously defined
         *                  'success' - everything went well
         *              >
         *              'init' => <array, default [] - the result of the actual initiate() function with constructed definitions>
         * @throws \Exception
         * @throws \Exception
         */
        function populateFromFiles(array $params = []): bool|array {
            if(!$this->initiated || !$this->flagsInitiated)
                return false;

            $skipReserved = $params['skipReserved'] ?? true;
            $skipInitiated = $params['skipInitiated'] ?? true;
            $generateNewHelp = $params['generateNewHelp'] ?? false;
            $absPathToRoot = $params['absPathToRoot']??$this->settings->getSetting('absPathToRoot');

            $result = [
                'file'=>false,
                'kfm'=>null,
                'fkm'=>null,
                'init'=>[]
            ];

            $newDefinitions = [];

            /* Common loading function for both keyFileMap and fileKeyMap.
               Returns array of the form:
               [
                'result' => array|null, either a valid json file we opened, or null
                'error' => reason for our error, if any - 'file-unavailable', 'file-not-json', 'file-invalid' (also corresponds to invalid json)
               ]
            */
            $loadJsonOrFile = function ($jsonOrFile, $context, $params = []) use ($absPathToRoot){
                $verbose = $params['verbose'] ?? ($params['test'] ?? false);

                if(\IOFrame\Util\PureUtilFunctions::is_json($jsonOrFile))
                    return json_decode($jsonOrFile,true);

                $result = [
                    'result'=>null,
                    'error'=>null
                ];
                $validFile = null;

                if(is_file($absPathToRoot.$jsonOrFile))
                    $validFile = $absPathToRoot.$jsonOrFile;
                elseif(is_file($jsonOrFile))
                    $validFile = $jsonOrFile;

                if($validFile){
                    try{
                        $result['result'] = \IOFrame\Util\FileSystemFunctions::readFile($validFile);
                        if(!\IOFrame\Util\PureUtilFunctions::is_json($result['result'])){
                            if($verbose)
                                echo $validFile.' is not a valid json.'.EOL;
                            $result['result'] = 'file-not-json';
                        }
                        else
                            $result['result'] = json_decode($result['result'],true);
                    }
                    catch (\Exception $e){
                        if($verbose)
                            echo 'Failed to open file '.$validFile.', exception '.$e->getMessage().EOL;
                        $result['result'] = 'file-unavailable';
                    }
                }

                if($result['result'] === null)
                    $result['error'] = 'file-invalid';
                return $result;
            };

            /* Common format validation keyFileMap and fileKeyMap, as well as population of the object with defaults as needed.
               Note that it only validates the format, not the correctness of the values
               Returns bool, true if valid, false if invalid
            */
            $validateAndPopulateMap = function (array &$object, $type = 'kfm', $params = []){
                $verbose = $params['verbose'] ?? ($params['test'] ?? false);

                /* Base validation / population for each object*/
                $baseObjectValidation = function (array &$object,string $fillKey = null,string $fillFilePath = null, string $debugKeySig = '' ,bool $verbose = false){
                    $object['type'] = $object['type']?? 'json';
                    $object['sections'] = $object['sections']?? false;
                    $object['pathType'] = $object['pathType']?? 'project';

                    if(empty($object['key']) && empty($fillKey)){
                        if($verbose)
                            echo $debugKeySig.' has no valid key'.EOL;
                        return false;
                    }
                    else
                        $object['key'] = $object['key'] ?? $fillKey;

                    if(empty($object['filePath']) && empty($fillFilePath)){
                        if($verbose)
                            echo $debugKeySig.' has no valid file path'.EOL;
                        return false;
                    }
                    else
                        $object['filePath'] = $object['filePath'] ?? $fillFilePath;

                    return true;
                };

                if($type === 'kfm'){
                    foreach ($object as $k=>$defObj) {
                        if(!is_array($defObj)){
                            if($verbose)
                                echo $type.' object with key '.$k.' not an array'.EOL;
                            return false;
                        }
                        if(!$baseObjectValidation($object[$k],$k,null,$type.' object with key '.$k,$verbose))
                            return false;
                    }
                }
                else{
                    foreach ($object as $filePath => $keysObj) {
                        if(!is_array($keysObj)){
                            if($verbose)
                                echo $type.' object with key '.$filePath.' not an array'.EOL;
                            return false;
                        }
                        foreach ($keysObj as $k => $defObj) {
                            if(!is_array($defObj)){
                                if($verbose)
                                    echo $type.' object with key '.$filePath.'/'.$k.' not an array'.EOL;
                                return false;
                            }
                            if(!$baseObjectValidation($object[$filePath][$k],$k,$filePath,$type.' object with key '.$filePath.'/'.$k,$verbose))
                                return false;
                        }
                    }
                }

                return true;
            };
            $validateAndPopulateKFM =  function (array &$object, $params = []) use($validateAndPopulateMap){
                return $validateAndPopulateMap($object, 'kfm',$params);
            };
            $validateAndPopulateFKM =  function (array &$object, $params = []) use($validateAndPopulateMap){
                return $validateAndPopulateMap($object, 'fkm',$params);
            };

            //Construct from file
            if(!empty($this->variables['filePath'])){
                $path = $this->variables['filePath'];
                $fileType = $this->variables['fileType'] ?? 'json';
                $pathType = $this->variables['filePathType'] ?? 'project';
                $sections = $this->variables['fileConfigSections'] ?? false;
                foreach ($this->variables as $variable => $value){
                    if($skipReserved && in_array($variable,$this->reservedVariables))
                        continue;
                    if($skipInitiated && $value)
                        continue;
                    if(empty($newDefinitions[$variable]))
                        $newDefinitions[$variable] = [];
                    if(empty($newDefinitions[$variable]['fileObj']))
                        $newDefinitions[$variable]['fileObj'] = [];
                    $newDefinitions[$variable]['fileObj'][] = ['type' => $fileType, 'filePath' => $path, 'pathType' => $pathType, 'sections' => $sections, 'key' => $variable];
                }
                if(!empty($newDefinitions))
                    $result['file'] = true;
            }

            //Construct from keyFileMap
            if(!empty($this->variables['keyFileMap'])){
                $kfm = $loadJsonOrFile($this->variables['keyFileMap'],$this,$params);
                if($kfm['error']){
                    $result['kfm'] = $kfm['error'];
                }
                elseif(!$validateAndPopulateKFM($kfm['result'],$params)){
                    $result['kfm'] = 'format-invalid';
                }
                else{
                    foreach ($kfm['result'] as $variable => $fileObj){
                        if(empty($newDefinitions[$variable]))
                            $newDefinitions[$variable] = [];
                        if(empty($newDefinitions[$variable]['fileObj']))
                            $newDefinitions[$variable]['fileObj'] = [];
                        $newDefinitions[$variable]['fileObj'][] = $fileObj;
                        $result['kfm'] = 'success';
                    }
                }
            }

            //Construct from fileKeyMap
            if(!empty($this->variables['fileKeyMap'])){
                $fkm = $loadJsonOrFile($this->variables['fileKeyMap'],$this,$params);
                if($fkm['error']){
                    $result['fkm'] = $fkm['error'];
                }
                elseif(!$validateAndPopulateFKM($fkm['result'],$params)){
                    $result['fkm'] = 'format-invalid';
                }
                else{
                    foreach ($fkm['result'] as $variables){
                        foreach ($variables as $variable => $fileObj){
                            if(empty($newDefinitions[$variable]))
                                $newDefinitions[$variable] = [];
                            if(empty($newDefinitions[$variable]['fileObj']))
                                $newDefinitions[$variable]['fileObj'] = [];
                            $newDefinitions[$variable]['fileObj'][] = $fileObj;
                            $result['fkm'] = 'success';
                        }
                    }
                }
            }

            //Initiate with new definitions
            $result['init'] = $this->initiate(
                $newDefinitions,
                array_merge($params,['generateHelp'=>$generateNewHelp])
            );

            return $result;
        }
    }

}
