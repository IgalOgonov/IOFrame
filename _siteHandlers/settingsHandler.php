<?php
namespace IOFrame{
    define('settingsHandler',true);

    /**Handles settings files (and maybe by now - DB shared settings) in IOFrame
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
    */


    if(!defined('lockHandler'))
        require 'lockHandler.php';
    if(!defined('fileHandler'))
        require 'fileHandler.php';
    if(!defined('helperFunctions'))
        require __DIR__.'/../_util/helperFunctions.php';

    /** @const OP_MODE_LOCAL Operation mode where settingsHandler works on the local node */
    const SETTINGS_OP_MODE_LOCAL = 'local';
    /** @const OP_MODE_DB Operation mode where settingsHandler works on a database using sqlHandler. Note that sqlHandler uses a local settingsHandler!*/
    const SETTINGS_OP_MODE_DB = 'db';
    /** @const OP_MODE_MIXED May operate locally or remotely, and sync setting files*/
    const SETTINGS_OP_MODE_MIXED = 'mixed';

    class settingsHandler{
        /** @var string $opMode Mode of operation for settingsHandler. Can be local or remote (db).*/
        protected $opMode;
        /** @var string[] $settingsArray Array of combined settings*/
        protected $settingsArray= [];
        /** @var array $settingsArrays Array of setting arrays, of the form <name> => <array>. Corresponds to esch nsme */
        protected $settingsArrays= [];
        /** @var string $settingsURLs Points to the FOLDERs where 'settings' are located. Settings should be a a file with no exaction,
         * even though it's a json file. */
        protected $settingsURLs = [];
        /** @var array $name The names of the settings. The name is always the last folder in the SettingsURL - for example, userSettings.
                        It is actually an array of the above strings. Also coresponds to table namw in the DB*/
        protected $names = [];
        /** @var string $lastUpdateTimes An array of last times this object's settings were updated.
         *              Per setting name.*/
        protected $lastUpdateTimes = [];
        /** @var bool $isInit Used for lazy initiation - might be useful in different framework implementation. */
        public $isInit = false;
        /** @var bool $settingsURL Used to indicate whether the setting handler check for setting updates automatically each time a setting is requested */
        protected $autoUpdate = false;
        /** @var bool $useCache Specifies whether we should be using cache */
        protected $useCacheArray = [];
        /** @var lockHandler $myLittleMutex Concurrency Handler in local mode*/
        protected $mutexes = [];
        /** @var fileHandler $fileHandler File Handler in local mode*/
        protected $fileHandler = null;
        /** @var sqlHandler $sqlHandler An sqlHandler in case we may operate remotely. */
        protected $sqlHandler = null;
        /** @var redisHandler $redisHandler A redisHandler so that we may use redis directly as cache. */
        protected $redisHandler = null;

        /**
         * Sets url to settings file to be the given URL
         * @param mixed $str IF STRING:absolute URL of settings FOLDER, or ALTERNATIVELY the name of the settings remote table
         *                  but of the form "/<tableName>/". In mixed mode, should be the URL, since the name is extracted
         *                  from the URL either way. The local settings folder name *MUST* match the remote table name.
         *                  IF ARRAY: An array of the above strings, to indicate multiple sources.
         * @param array $partams of the form:
         *              'initiate' - default true - if true, will initiate the settings on creation, else this will be a lazy initiation
         *              'sqlHandler' - default null - if provided, will operate either in mixed or remote mode by default.
         *              'opMode' - default OP_MODE_LOCAL/OP_MODE_DB - if provided, will "hint" the handler about mixed mode, or
         *                          straight override the mode (aka "local" will force into local mode even if sqlHandler is provided).
         *                          Default is based on whether sqlHandler was provided.
         *              'useCache' -default true - specifies whether we should be using cache - can be set per setting, too.
         *
         * @throws \Exception If we try to use a DB/Mixed mode without a db handler.
         */
        function __construct($str, $params = []){
            //Initial definitions
            //Default setting table prefix
            if(!defined('SETTINGS_TABLE_PREFIX'))
                define('SETTINGS_TABLE_PREFIX', 'SETTINGS_');

            //Set defaults
            if(!isset($params['initiate']))
                $params['initiate'] = true;
            if(!isset($params['useCache']))
                $params['useCache'] = true;
            if(!isset($params['sqlHandler']))
                $params['sqlHandler'] = null;
            if(!isset($params['opMode'])){
                $params['opMode'] = ($params['sqlHandler'] != null) ?
                    SETTINGS_OP_MODE_DB : SETTINGS_OP_MODE_LOCAL ;
            }

            //Set redis handler if we got one - and if it is initiated
            if(isset($params['redisHandler']) && $params['redisHandler']!==null){
                if(isset($params['redisHandler']->isInit)){
                    if($params['redisHandler']->isInit){
                        $this->redisHandler = $params['redisHandler'];
                    }
                }
            }
            else{
                //Might seem unrelated, but there is no cache without redis
                $params['useCache'] = false;
            }

            //Just in case, both table name and url must end in '/'
            if(!is_array($str)){
                if(substr($str,-1) != '/')
                    $str .= '/';
            }
            else{
                foreach($str as $k=>$v){
                    if(substr($str[$k],-1) != '/')
                        $str[$k] .= '/';
                }
            }

            //Lets see if we are eligible to even run in remote/mixed mode
            if($params['sqlHandler'] == null)
                if($params['opMode'] != SETTINGS_OP_MODE_LOCAL)
                    throw new \Exception('Settings Handler may only run in local mode if a DB Handler is not provided!');

            //Now, lets set variables for local mode
            if($params['opMode'] == SETTINGS_OP_MODE_LOCAL || $params['opMode'] == SETTINGS_OP_MODE_MIXED){
                if(!is_array($str)){
                    $temp = substr($str,0, -1);
                    $name = substr(strrchr($temp, "/"), 1);
                    $this->settingsURLs[$name] = $str;
                    $this->mutexes[$name] = new lockHandler($str, 'mutex');
                }
                else{
                    foreach($str as $settingFileName){
                        $temp = substr($settingFileName,0, -1);
                        $name = substr(strrchr($temp, "/"), 1);
                        $this->settingsURLs[$name] = $settingFileName;
                        $this->mutexes[$name] = new lockHandler($settingFileName, 'mutex');
                    }
                }
                $this->fileHandler = new fileHandler();
            }

            //Now, for remote mode
            if($params['opMode'] == SETTINGS_OP_MODE_DB || $params['opMode'] == SETTINGS_OP_MODE_MIXED){
                $this->sqlHandler = $params['sqlHandler'];
            }

            //Shared variables
            $this->opMode = $params['opMode'];
            if(!is_array($str)){
                $temp = substr($str,0, -1);
                $name = substr(strrchr($temp, "/"), 1);
                $this->names[0] = $name;
                $this->lastUpdateTimes[$name] = 0;
                if($params['useCache'] === true){
                    $this->useCacheArray[$name] = true;
                }
                else{
                    if(isset($params['useCache'][$name]))
                        $this->useCacheArray[$name] = $params['useCache'][$name];
                    else
                        $this->useCacheArray[$name] = false;
                }
            }
            else{
                foreach($str as $settingFileName){
                    $temp = substr($settingFileName,0, -1);
                    $name = substr(strrchr($temp, "/"), 1);
                    array_push($this->names,$name);
                    $this->lastUpdateTimes[$name] = 0;
                    //Remember - cache can't be used without redis!
                    if($params['useCache'] === true){
                        $this->useCacheArray[$name] = true;
                    }
                    else{
                        if(isset($params['useCache'][$name]))
                            $this->useCacheArray[$name] = $params['useCache'][$name];
                        else
                            $this->useCacheArray[$name] = false;
                    }
                }
            }
            if($params['initiate']){
                $this->getFromCache();
                $this->chkInit();
            }
        }

        /** @returns string settingsURL
         */
        function getUrl($name){
            return $this->settingsURLs[$name];
        }

        /** @returns string name
         */
        function getNames(){
            return $this->names;
        }

        /** @returns string opMode
         */
        function getOpMode(){
            return $this->opMode;
        }

        /** @param string $opMode mode of operation to set - must match one of the constants defined in the class
         */
        function setOpMode(string $opMode){
            $modes = [SETTINGS_OP_MODE_DB,SETTINGS_OP_MODE_MIXED,SETTINGS_OP_MODE_LOCAL];
            if(in_array($opMode,$modes))
                $this->opMode = $opMode;
        }

        /** @param bool $autoUpdate to the specified value
         */
        function setAutoUpdate(bool $bool = true){
            $this->autoUpdate = $bool;
        }

        /** @param array $handlers an object that is used to set sqlHandler, redisHandler, or both.
         */
        function setHandlers($handlers = []){
            if(isset($handlers['sqlHandler']))
                $this->sqlHandler = $handlers['sqlHandler'];
            if(isset($handlers['redisHandler']))
                $this->redisHandler = $handlers['redisHandler'];
        }

        /** Updates the settings of this object from disk/db. If given an argument, updates the settings on the disk/db with
         * that argument - be careful, it must be an ARRAY of settings!
         *
         * @param mixed $arg '' to
         *
         * @returns bool true on success
         * */
        function updateSettings($mode = null,$test = false){
            //If not specified otherwise, update in default mode
            $res = false;
            if($mode == null)
                $mode = $this->opMode;
            $updateTime = time();
            //Local mode update
            if($mode != SETTINGS_OP_MODE_DB){
                //This is how we update the settings for all individual settings in our collection
                $combinedSettings = [];
                //Update settings from settings files
                foreach($this->names as $name){
                    $settings = $this->fileHandler->readFileWaitMutex($this->settingsURLs[$name], 'settings', []);
                    $setArray =  json_decode($settings , true ) ;
                    if(is_array($setArray))
                        $combinedSettings = array_merge( $combinedSettings, $setArray);
                    if(!$test){
                        $this->lastUpdateTimes[$name] = $updateTime;
                        $this->settingsArrays[$name] =  $setArray;
                    }
                    else{
                        echo 'Updating local settings '.$name.' to '.$settings.' at '.$updateTime.EOL;
                    }
                    //Update the cache if we're using it - note that this is called BECAUSE in local mode,
                    //updateSettings only gets called if some change was detected in chkInit or after setSetting
                    //This update might happen twice in MIXED mode - this is an acceptable casualty
                    $this->updateCache(['settingsArray'=>$setArray,'name'=>$name,'settingsLastUpdate'=>$updateTime],$test);
                }
                $this->settingsArray = $combinedSettings;
                $this->isInit = true;
                $res = true;
            }
            //Mixed/DB mode update.
            if($mode != SETTINGS_OP_MODE_LOCAL){

                //Query used to get only the settings that are not up to date
                $testQuery = '';
                foreach($this->names as $name){
                    $tname = strtolower($this->sqlHandler->getSQLPrefix().SETTINGS_TABLE_PREFIX.$name);
                    $testQuery.= $this->sqlHandler->selectFromTable($tname,
                            [ [$tname, [['settingKey', '_Last_Changed', '='],['settingValue',$this->lastUpdateTimes[$name],'>='],'AND'], ['settingKey','settingValue'], [], 'SELECT'], 'EXISTS'],
                            ['settingKey','settingValue', '\''.$name.'\' as Source'],
                            ['justTheQuery'=>true],
                            false
                        ).' UNION ';
                }

                $testQuery =  substr($testQuery,0,-7);

                if($test){
                    echo 'Query to send: '.$testQuery.' at '.$updateTime.EOL;
                }
                $temp = $this->sqlHandler->exeQueryBindParam($testQuery, [], true);

                //Used to check whether there are duplicate settings - as ell as to remove '_Last_Changed'
                $res = [];

                //Update the settings
                if($temp != 0) {
                    foreach ($temp as $resArray) {
                        if (!array_key_exists($resArray['settingKey'], $res)) {
                            //Indicate the setting exists
                            $res[$resArray['settingKey']] = 1;
                            //If the setting key was not "_last_changed", it was a real setting
                            if ($resArray['settingKey'] != '_Last_Changed') {
                                if (!$test)
                                    $this->settingsArray[$resArray['settingKey']] = $resArray['settingValue'];
                                else
                                    echo 'Setting ' . $resArray['settingKey'] . ' set to ' . $resArray['settingValue'] . EOL;
                            }

                        }
                        //If the setting key was "_Last_Changed", set it.
                        if ($resArray['settingKey'] != '_Last_Changed') {
                            if (!$test) {
                                $this->settingsArrays[$resArray['Source']][$resArray['settingKey']] = $resArray['settingValue'];
                                $this->lastUpdateTimes[$resArray['Source']] = $updateTime;
                            } else {
                                echo 'Setting ' . $resArray['settingKey'] . ' in ' . $resArray['Source'] . ' set to ' .
                                    $resArray['settingValue'] . ' at ' . $updateTime . EOL;
                            }
                        }
                    }
                    //If we are running in mixed mode and we got new settings, it means the local settings are out of sync.
                    if($mode != SETTINGS_OP_MODE_MIXED)
                        $this->initLocal($test);
                }

                //Update the cache if we had any new results
                if($res != [])
                    foreach($this->names as $name){
                        //Update the cache if we're using it
                        //This update might happen twice in MIXED mode - this is an acceptable casualty
                        $this->updateCache(['settingsArray'=>$this->settingsArrays[$name],'name'=>$name,'settingsLastUpdate'=>$updateTime],$test);
                    }

                $res = true;
            }

            return $res;
        }

        /** Gets the settings from $_SESSION, if they exist there, and updates this handler.
         * Note that in the future instead of storing a copy of the settings per user, there will be a shared cache
         * for all users, probably using Redis.
         *
         * @returns bool true on success
         * */
        function getFromCache($params = [],$test = false){
            /*
            */
            if($this->redisHandler === null)
                return false;
            //Indicates requested everything was found
            $res = true;
            $combined_array = [];
            foreach($this->names as $name){
                //If we are not using cache, just continue and set result to false
                if(!isset($this->useCacheArray[$name]) || !$this->useCacheArray[$name]){
                    if($test)
                        echo 'Tried to get '.$name.' from cache when useCache for it was false'.EOL;
                    $res = false;
                    continue;
                }
                $settingsJSON = $this->redisHandler->call('get','_settings_'.$name);
                $settingsMeta = $this->redisHandler->call('get','_settings_meta_'.$name);
                if($settingsJSON && $settingsMeta){
                    $settings = json_decode($settingsJSON,true);
                    $combined_array = array_merge($combined_array, $settings);
                    if(!$test){
                        $this->lastUpdateTimes[$name] = $settingsMeta;
                        $this->settingsArrays[$name] =  $settings;
                    }
                    else{
                        echo 'Setting array '.$name.' updated from cache to '.$settingsJSON.', freshness: '.$settingsMeta.EOL;
                    }
                }
                else
                    $res = false;
            }
            //If everything was found in cache, update return true. Else false.
            if($res){
                if(!$test){
                    $this->settingsArray = $combined_array;
                    $this->isInit = true;
                }
                else{
                    echo 'Setting array updated to '.json_encode($combined_array).EOL;
                }
            }
            return $res;
        }

        /** Updates the settings at $_SESSION.
         * Note that in the future instead of storing a copy of the settings per user, there will be a shared cache
         * for all users, probably using Redis.
         *
         * @returns bool true on success
         * */
        function updateCache($params = [],$test = false){
            if($this->redisHandler === null)
                return false;

            //Ensure required params
            if(!isset($params['settingsArray']) || !isset($params['name']) )
                return false;
            $name = $params['name'];
            $settingsJSON = json_encode($params['settingsArray']);

            if(!isset($this->useCacheArray[$name]) || !$this->useCacheArray[$name]){
                if($test)
                    echo 'Tried to update cache of '.$name.' when useCache was false'.EOL;
                return false;
            }

            //Set defaults
            if(!isset($params['settingsLastUpdate']))
                $settingsLastUpdate = 0;
            else
                $settingsLastUpdate = $params['settingsLastUpdate'];

            if(!$test){
                $this->redisHandler->call('set',['_settings_'.$name,$settingsJSON]);
                $this->redisHandler->call('set',['_settings_meta_'.$name,$settingsLastUpdate]);
            }
            else{
                echo 'Updating cache settings array '.$name.' to '.$settingsJSON.' at '.$settingsLastUpdate.EOL;
            }
            return true;
        }

        /** Checks if Settings has been initialized, if no initializes it.
         * Also, if they are initialized, checks if they are up to date, if no updates them.
         *
         * @returns bool true on success, false if unable to open the given settings url.
         * */
        function chkInit($mode = null,$test = false){
            if($mode == null)
                $mode = $this->opMode;

            if(!$this->isInit){
                return
                    $this->updateSettings($mode,$test);
            }
            else{
                //TODO initiate ALL of the settings
                //Local mode
                if($mode != SETTINGS_OP_MODE_DB){
                    $shouldUpdate = false;

                    foreach($this->names as $name){
                        //Suppressing the error because if file doesn't exist, we'll create it
                        $lastUpdate = $this->fileHandler->readFileWaitMutex($this->settingsURLs[$name], '_setMeta', []);

                        //This means that for whatever reason the _setMeta file doesn't exist or is wrong, so we gotta create it
                        if( preg_match_all('/[0-9]|\.|\s/',$lastUpdate)!=strlen($lastUpdate) || $lastUpdate = 0)
                            $this->updateMeta($name,SETTINGS_OP_MODE_LOCAL,$test);

                        //If the last time we updated was BEFORE the last time the global settings were updated, we gotta close the gap.
                        if( (int)$lastUpdate > (int)$this->lastUpdateTimes[$name] )
                            $shouldUpdate = true;
                    }

                    if($shouldUpdate)
                        return $this->updateSettings($mode,$test);
                    else
                        return true;
                }
                //DB mode
                else{
                    //DB mode only updates from tables we are outdated on anyway
                    return $this->updateSettings($mode,$test);
                }
            }
        }


        /** Gets a specific setting -
         *
         * @param string $str setting name
         *
         * @returns mixed
         *      false if settings aren't initiated or updated, and we aren't using auto-update
         *      string setting
         *      null if setting isnt set
         * */
        function getSetting(string $str){
            //If we are automatically updating, use chkInit - else, just check isInit to assure settings are initiated
            ($this->autoUpdate)? $init = $this->chkInit(): $init = $this->isInit;
            //Make sure we are up to date - chkInit() will update us if we're behind and ONLY return false if it failed
            if(!$init)
                return null;
            else
                if(isset($this->settingsArray[$str]))
                    return $this->settingsArray[$str];
                else
                    return null;
        }



        /*** Gets the array of settings
         *
         * @param string $str setting name
         *
         * @returns mixed
         *      false if settings aren't initiated or updated, and we aren't using auto-update
         *      string[] otherwise (could be empty)
         * */
        function getSettings(array $arr = []){
            //If we are automatically updating, use chkInit - else, just check isInit to assure settings are initiated
            ($this->autoUpdate)? $init = $this->chkInit(): $init = $this->isInit;
            //Make sure we are up to date - chkInit() will update us if we're behind and ONLY return false if it failed
            if(!$init)
                return null;
            else{
                if($arr == [] || !is_array($arr))
                    return $this->settingsArray;
                else{
                    $res = [];
                    foreach($arr as $expected){
                        if(isset($this->settingsArray[$expected]))
                            $res[$expected] = $this->settingsArray[$expected];
                    }
                    return $res;
                }
            }
        }
        /**
         * @param string $set Sets a specific setting $set to value $val.
         * @param mixed $val If $val is exact match for null, removes that setting.
         * @param bool $createNew If false, will not allow creating new settings, only updating.
         *
         * @returns mixed false if couldn't check/update settings, or -1 if setting requested doesn't exist.
        */
        function setSetting(string $set, $val, bool $createNew = false, $targetName = null,$test = false){

            //Make sure we are up to date - chkInit() will update us if we're behind and ONLY return false if it failed
            if(!$this->chkInit(null,$test))
                return false;
            else if(!isset($this->settingsArray[$set])){
                if($createNew);
                else
                    return -1;
            }

            //Find out which array of settings our setting belongs to.
            foreach($this->names as $name){
                if($targetName == null){
                    //Remember we might have an empty settings array
                    if(isset($this->settingsArrays[$name]))
                        if(array_key_exists($set,$this->settingsArrays[$name])){
                            $targetName = $name;
                        }
                }
            }
            if($createNew && ($targetName == null))
                $targetName = $this->names[0];
            //Update session settings (which are now up to date) with the new value
            $newSettings = $this->settingsArrays[$targetName];
            if($val !== null)
                $newSettings[$set] = $val;
            else
                unset($newSettings[$set]);

            //This is how we update the specified settings file
            if($this->opMode == SETTINGS_OP_MODE_LOCAL || $this->opMode == SETTINGS_OP_MODE_MIXED){
                if($targetName == null){
                    throw new \Exception('Cannot update settings without knowing the name!');
                }
                if(!$test)
                    $this->fileHandler->writeFileWaitMutex(
                        $this->settingsURLs[$targetName],
                        'settings',
                        json_encode($newSettings),
                        ['sec' => 2, 'backUp' => true, 'locakHandler' => $this->mutexes[$targetName]]
                    );
                else
                    echo 'Writing '.json_encode($newSettings).' to '.$this->settingsURLs[$targetName].' at '.time().EOL;

                $this->updateMeta($targetName,SETTINGS_OP_MODE_LOCAL,$test);
            }
            //Mixed/DB mode
            if($this->opMode == SETTINGS_OP_MODE_DB || $this->opMode == SETTINGS_OP_MODE_MIXED){
                $tname = strtolower($this->sqlHandler->getSQLPrefix().SETTINGS_TABLE_PREFIX.$targetName);
                //If we deleted a setting
                if(count($newSettings)<count($this->settingsArrays[$targetName])){
                    $this->sqlHandler->deleteFromTable(
                        $tname,
                        [['settingKey',(string)$set,'=']],
                        [],
                        $test
                    );
                }
                //If we added or updated a setting
                else{
                    $this->sqlHandler->insertIntoTable(
                        $tname,
                        ['settingKey','settingValue'],
                        [[$set,"STRING"],[(string)$val,"STRING"]],
                        ['onDuplicateKey'=>$createNew],
                        $test
                    );
                }
                $this->updateMeta($targetName,SETTINGS_OP_MODE_DB,$test);
            }
            if($test)
                echo 'NOW UPDATING SETTINGS OBJECT: '.EOL;
            $this->updateSettings(null,$test);
            return true;
        }


        /** Sets an array of settings, if exist. Pass null as value to unset a setting
         *
         * TODO Implement properly
         */
        function setSettings(){
            return false;
        }

        /**Updates settings meta file _setMeta to <current UNIX time> - with microseconds.
        */
        function updateMeta($name, $mode = null,$test = false){
            if($mode == null)
                $mode = $this->opMode;
            if($mode == SETTINGS_OP_MODE_LOCAL){
                if(!$test)
                    $this->fileHandler->writeFileWaitMutex($this->settingsURLs[$name],'_setMeta',time(),['useNative' => true]);
                else
                    echo 'Updating settings meta file of '.$name.' at '.time().EOL;
            }
            else{
                    $tname = strtolower($this->sqlHandler->getSQLPrefix().SETTINGS_TABLE_PREFIX.$name);
                    $this->sqlHandler->insertIntoTable(
                        $tname,
                        ['settingKey','settingValue'],
                        [["_Last_Changed","STRING"],[(string)time(),"STRING"]],
                        ['onDuplicateKey'=>true],
                        $test);
            }
        }

        /** Creates the next iteration in the settings changes history, moves all existing changes 1 step back,
         * and delets the $n-th (default = 10) change. This means the last 10 changes are saved by default.
         * Changing the n will resault in a LOSS of all changes earlier than the new n.
         */
        function backupSettings($name,$test = false){
            if(!$test)
                $this->fileHandler->backupFile($this->settingsURLs[$name],'settings',['maxBackup'=>10]);
            else
                echo 'Backing up settings named '.$name;
        }

        /** Creates the DB tables and copies the settings there.
         * Can only work in mixed Operation Mode.
         *
         * @returns bool
         * */
        function initDB($test = false){
            //This can only be done in mixed mode
            if($this->opMode != SETTINGS_OP_MODE_MIXED){
                return false;
            };
            //Obviously, we also need to be initiated
            if(!$this->isInit){
                return false;
            };
            foreach($this->settingsArrays as $name=>$settings){
                $tname = strtolower($this->sqlHandler->getSQLPrefix().SETTINGS_TABLE_PREFIX.$name);
                $query = 'CREATE TABLE IF NOT EXISTS '.$tname.' (
                                                              settingKey varchar(255) PRIMARY KEY,
                                                              settingValue varchar(255) NOT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;
                                                              ';
                if(!$test){
                    $this->sqlHandler->exeQueryBindParam($query,[],false);
                    $this->sqlHandler->exeQueryBindParam('TRUNCATE TABLE '.$tname,[],false);
                }
                else{
                    echo 'Query to send: '.$query.EOL;
                    echo 'Query to send: TRUNCATE TABLE '.$tname.EOL;
                }

                $toInsert = [[['_Last_Changed',"STRING"],[(string)$this->lastUpdateTimes[$name],"STRING"]]];
                foreach($settings as $k=>$v){
                    array_push($toInsert,[[$k,"STRING"],[$v,"STRING"]]);
                }
                $this->sqlHandler->insertIntoTable($tname,['settingKey','settingValue'],$toInsert,[],$test);
            }
            return true;
        }
        /** Initiates the local files.
         *  Will assume the URL of each setting is the URL where you *want* the setting file placed, not where it necessarily exists.
         * @returns bool
         * */
        function initLocal($test = false){
            //This can only be done in mixed mode
            if($this->opMode != SETTINGS_OP_MODE_MIXED){
                return false;
            };
            //Obviously, we also need to be initiated
            if(!$this->isInit){
                return false;
            };
            //Here we do the initiation
            foreach($this->settingsArrays as $name=>$settings){
                $url = $this->settingsURLs[$name];
                $urlWithoutSlash = substr($url,0, -1);
                try{
                    //Try to create directory
                    mkdir($urlWithoutSlash);
                }
                catch(\Exception $e){
                    //Hopefully it only fails if directory already existed
                }
                if(!$test){
                    fclose(fopen($url.'settings','w')) or die(false);
                    $this->fileHandler->writeFileWaitMutex($this->settingsURLs[$name], 'settings', json_encode($settings), ['backUp' => true, 'locakHandler' => $this->mutexes[$name]]);
                }
                else
                    echo 'Creating and populating settings '.$name.' with '.json_encode($settings).EOL;
                $this->updateMeta($name,SETTINGS_OP_MODE_LOCAL,$test);
            }
            return true;
        }

        /** Syncs the current local setting file with the table in the DB. If a table of the appropriate name does not exist,
         *  creates it.
         * Must be in mixed Operation Mode to run.
         * Much more expensive than initDB would be, but does not truncate the tables.
         *
         * @param array $params Parameter object of the form:
         *                      bool localToDB - Indicates whether to sync local information to the DB, or the other way around.
         *                      bool deleteDifferent - Indicates whether to delete excess settings from the db
         *                                            (just calls initDB as it's the same function)
         * @returns bool Whether we succeeded or not.
         */
        function syncWithDB($params = [],$test = false){
            //Set defaults
            if(!isset($params['localToDB']))
                $params['localToDB'] = true;
            if(!isset($params['deleteDifferent']))
                $params['deleteDifferent'] = false;

            //Since we can only rewrite local files completely anyway, syncing is the same as initiating from scratch in this case
            if(!$params['localToDB'])
                return $this->initLocal($test);
            //If we are deleting new settings, we are essentially doing the same thing as recreating the table.
            if($params['deleteDifferent'])
                return $this->initDB($test);

            //This can only be done in mixed mode
            if($this->opMode != SETTINGS_OP_MODE_MIXED){
                return false;
            }

            //Check that we are up to date - reminder that at this point we are syncing local files to the db
            $this->chkInit(SETTINGS_OP_MODE_LOCAL,$test);

            //In case of syncing local data to the db, we can do actual syncing
            foreach($this->names as $name){
                $tname = strtolower($this->sqlHandler->getSQLPrefix().SETTINGS_TABLE_PREFIX.$name);
                $values = [];
                foreach($this->settingsArrays[$name] as $k=>$v){
                    array_push($values,[[(string)$k,'STRING'],[(string)$v,'STRING']]);
                }
                array_push($values,[['_Last_Changed','STRING'],[(string)time(),'STRING']]);
                $this->sqlHandler->insertIntoTable($tname,
                    ['settingKey','settingValue'],
                    $values,
                    ['onDuplicateKey' => true],
                    $test
                    );
            }

            return true;
        }


        /** Prints all the settings, like this:
        Settings{
        <Setting name> : <setting value>
        }
        @returns bool false if settings aren't initiated or updated, and we aren't using auto-update
         */
        function printAll(){
            ($this->autoUpdate)? $init = $this->chkInit(): $init = $this->isInit;
            if(!$init)
                return false;
            else{
                $last_key = key( array_slice(  $this->settingsArray, -1, 1, TRUE ) );
                if( count($this->names) > 1 ){
                    $name = '['.implode(',',$this->names).']';
                }
                else
                    $name = $this->names[0];
                echo 'Settings <b>'.$name.'</b>: {'.EOL;
                foreach ($this->settingsArray as $key=>$setting){
                    echo $key.': '.$setting;
                    if($key!=$last_key)
                        echo ','.EOL;
                    else
                        echo EOL.'}'.EOL;
                }
                if($this->opMode != SETTINGS_OP_MODE_DB)
                    echo 'URLs: '.json_encode($this->settingsURLs).EOL;
                echo 'Update times:'.json_encode($this->lastUpdateTimes).EOL;
                echo 'OP Mode:'.$this->opMode.EOL;
                echo 'Cache: '.json_encode($this->useCacheArray).EOL;
                echo 'AutoUpdate:'.($this->autoUpdate).EOL;
                echo 'sqlHandler:'.($this->sqlHandler != null).EOL;
                echo 'RedisHandler: '.($this->redisHandler != null).EOL;
                echo '----'.EOL;
                return true;
            }
        }


    }
}
?>