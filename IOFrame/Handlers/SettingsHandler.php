<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('SettingsHandler',true);
    if(!defined('LockHandler'))
        require 'LockHandler.php';
    if(!defined('FileHandler'))
        require 'FileHandler.php';
    if(!defined('helperFunctions'))
        require __DIR__ . '/../Util/helperFunctions.php';

    /** @const OP_MODE_LOCAL Operation mode where SettingsHandler works on the local node */
    const SETTINGS_OP_MODE_LOCAL = 'local';
    /** @const OP_MODE_DB Operation mode where SettingsHandler works on a database using SQLHandler. Note that SQLHandler uses a local SettingsHandler!*/
    const SETTINGS_OP_MODE_DB = 'db';
    /** @const OP_MODE_MIXED May operate locally or remotely, and sync setting files*/
    const SETTINGS_OP_MODE_MIXED = 'mixed';

    //Default settings directory relative to site root.
    if(!defined('SETTINGS_DIR_FROM_ROOT'))
        define('SETTINGS_DIR_FROM_ROOT','localFiles');
    //Default setting table prefix
    if(!defined('SETTINGS_TABLE_PREFIX'))
        define('SETTINGS_TABLE_PREFIX', 'SETTINGS_');

    /**Handles settings , local and DB based, in IOFrame
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
    */

    class SettingsHandler{
        /** @var string $opMode Mode of operation for SettingsHandler. Can be local or remote (db).*/
        protected $opMode;
        /** @var string[] $settingsArray Array of combined settings*/
        protected $settingsArray= [];
        /** @var array $settingsArrays Array of setting arrays, of the form <name> => <array>. Corresponds to esch nsme */
        protected $settingsArrays= [];
        /** @var string[] $settingsURLs Points to the FOLDERs where 'settings' are located. Settings should be a a file with no exaction,
         * even though it's a json file. */
        protected $settingsURLs = [];
        /** @var array $name The names of the settings. The name is always the last folder in the SettingsURL - for example, userSettings.
                        It is actually an array of the above strings. Also coresponds to table namw in the DB*/
        protected $names = [];
        /** @var string[] $lastUpdateTimes An array of last times this object's settings were updated.
         *              Per setting name.*/
        protected $lastUpdateTimes = [];
        /** @var bool $isInit Used for lazy initiation - might be useful in different framework implementation. */
        public $isInit = false;
        /** @var bool $settingsURL Used to indicate whether the setting handler check for setting updates automatically each time a setting is requested */
        protected $autoUpdate = false;
        /** @var bool[] $useCache Specifies whether we should be using cache */
        protected $useCacheArray = [];
        /** @var LockHandler $myLittleMutex Concurrency Handler in local mode*/
        protected $mutexes = [];
        /** @var FileHandler $FileHandler File Handler in local mode*/
        protected $FileHandler = null;
        /** @var SQLHandler $SQLHandler An SQLHandler in case we may operate remotely. */
        protected $SQLHandler = null;
        /** @var RedisHandler $RedisHandler A RedisHandler so that we may use redis directly as cache. */
        protected $RedisHandler = null;

        /**
         * Sets url to settings file to be the given URL
         * @param mixed $str IF STRING:absolute URL of settings FOLDER, or ALTERNATIVELY the name of the settings remote table
         *                  but of the form "/<tableName>/". In mixed mode, should be the URL, since the name is extracted
         *                  from the URL either way. The local settings folder name *MUST* match the remote table name.
         *                  IF ARRAY: An array of the above strings, to indicate multiple sources.
         * @param array $partams of the form:
         *              'initiate' - default true - if true, will initiate the settings on creation, else this will be a lazy initiation
         *              'SQLHandler' - default null - if provided, will operate either in mixed or remote mode by default.
         *              'opMode' - default OP_MODE_LOCAL/OP_MODE_DB - if provided, will "hint" the handler about mixed mode, or
         *                          straight override the mode (aka "local" will force into local mode even if SQLHandler is provided).
         *                          Default is based on whether SQLHandler was provided.
         *              'useCache' -default true - specifies whether we should be using cache - can be set per setting, too.
         *
         * @throws \Exception If we try to use a DB/Mixed mode without a db handler.
         */
        function __construct($str, $params = []){
            //Initial definitions

            if(!defined('EOL')){
                if (php_sapi_name() == "cli") {
                    define("EOL",PHP_EOL);
                } else {
                    define("EOL",'<br>');;
                }
            }

            //Set defaults
            if(!isset($params['initiate']))
                $params['initiate'] = true;
            if(!isset($params['useCache']))
                $params['useCache'] = true;
            if(!isset($params['SQLHandler']))
                $params['SQLHandler'] = null;
            if(!isset($params['opMode'])){
                $params['opMode'] = ($params['SQLHandler'] != null) ?
                    SETTINGS_OP_MODE_DB : SETTINGS_OP_MODE_LOCAL ;
            }

            //Set redis handler if we got one - and if it is initiated
            if(isset($params['RedisHandler']) && $params['RedisHandler']!==null){
                if(isset($params['RedisHandler']->isInit)){
                    if($params['RedisHandler']->isInit){
                        $this->RedisHandler = $params['RedisHandler'];
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
            if($params['SQLHandler'] == null)
                if($params['opMode'] != SETTINGS_OP_MODE_LOCAL)
                    throw new \Exception('Settings Handler may only run in local mode if a DB Handler is not provided!');

            //Now, lets set variables for local mode
            if($params['opMode'] == SETTINGS_OP_MODE_LOCAL || $params['opMode'] == SETTINGS_OP_MODE_MIXED){
                if(!is_array($str)){
                    $temp = substr($str,0, -1);
                    $name = substr(strrchr($temp, "/"), 1);
                    $this->settingsURLs[$name] = $str;
                    $this->mutexes[$name] = new LockHandler($str, 'mutex');
                }
                else{
                    foreach($str as $settingFileName){
                        $temp = substr($settingFileName,0, -1);
                        $name = substr(strrchr($temp, "/"), 1);
                        $this->settingsURLs[$name] = $settingFileName;
                        $this->mutexes[$name] = new LockHandler($settingFileName, 'mutex');
                    }
                }
                $this->FileHandler = new FileHandler();
            }

            //Now, for remote mode
            if($params['opMode'] == SETTINGS_OP_MODE_DB || $params['opMode'] == SETTINGS_OP_MODE_MIXED){
                $this->SQLHandler = $params['SQLHandler'];
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
                $this->getFromCache($params);
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

        /** @param array $handlers an object that is used to set SQLHandler, RedisHandler, or both.
         */
        function setHandlers($handlers = []){
            if(isset($handlers['SQLHandler']))
                $this->SQLHandler = $handlers['SQLHandler'];
            if(isset($handlers['RedisHandler']))
                $this->RedisHandler = $handlers['RedisHandler'];
        }

        /** Keeps only specific settings in the object (in case of multiple objects in one).
         * Should be used after cloning.
         * @param string|string[] $targets - name(s) of the settings to keep
        */
        function keepSettings($targets){

            if(gettype($targets) == 'string')
                $targets = [$targets];
            //Merge the settings of each target
            $this->settingsArray = [];
            $this->names = [];
            foreach($targets as $target){
                array_push($this->names,$target);
                if(isset($this->settingsArrays[$target])){
                    $this->settingsArray = array_merge($this->settingsArray,$this->settingsArrays[$target]);
                }
            }

            //Remove all setting arrays that are not the targets
            foreach($this->settingsArrays as $name=>$value){
                if(!in_array($name,$targets))
                    unset($this->settingsArrays[$name]);
            }
            //Remove all setting URLs that are not the targets
            foreach($this->settingsURLs as $name=>$value){
                if(!in_array($name,$targets))
                    unset($this->settingsURLs[$name]);
            }
            //Remove all lastUpdated of settings that are not the targets
            foreach($this->lastUpdateTimes as $name=>$value){
                if(!in_array($name,$targets))
                    unset($this->lastUpdateTimes[$name]);
            }
            //Remove all useCacheArray of settings that are not the targets
            foreach($this->useCacheArray as $name=>$value){
                if(!in_array($name,$targets))
                    unset($this->useCacheArray[$name]);
            }
            //Remove all mutexes of settings that are not the targets
            foreach($this->mutexes as $name=>$value){
                if(!in_array($name,$targets))
                    unset($this->mutexes[$name]);
            }
        }

        /** Updates the settings of this object from disk/db. If given an argument, updates the settings on the disk/db with
         * that argument - be careful, it must be an ARRAY of settings!
         *
         * @param array $params of the form:
         *                  'mode' => force operation mode
         *
         * @returns bool true on success
         * */
        function updateSettings(array $params = []){
            $test = isset($params['test'])? $params['test'] : $test = false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            isset($params['mode'])?
                $mode = $params['mode'] : $mode = null;
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
                    $settings = $this->FileHandler->readFileWaitMutex($this->settingsURLs[$name], 'settings', []);
                    $setArray =  json_decode($settings , true ) ;
                    if($setArray === null)
                        $setArray = [];
                    if(is_array($setArray))
                        $combinedSettings = array_merge( $combinedSettings, $setArray);
                    if(!$test){
                        $this->lastUpdateTimes[$name] = $updateTime;
                        $this->settingsArrays[$name] =  $setArray;
                    }
                    if($verbose)
                        echo 'Updating local settings '.$name.' to '.$settings.' at '.$updateTime.EOL;

                    //Update the cache if we're using it - note that this is called BECAUSE in local mode,
                    //updateSettings only gets called if some change was detected in chkInit or after setSetting
                    //This update might happen twice in MIXED mode - this is an acceptable casualty
                    $this->updateCache(['settingsArray'=>$setArray,'name'=>$name,'settingsLastUpdate'=>$updateTime,'test'=>$test,'verbose'=>$verbose]);
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
                    $tname = strtoupper($this->SQLHandler->getSQLPrefix().SETTINGS_TABLE_PREFIX.$name);
                    $testQuery.= $this->SQLHandler->selectFromTable($tname,
                            [ [$tname, [['settingKey', '_Last_Updated', '='],['settingValue',$this->lastUpdateTimes[$name],'>='],'AND'], ['settingKey','settingValue'], [], 'SELECT'], 'EXISTS'],
                            ['settingKey','settingValue', '\''.$name.'\' as Source'],
                            ['justTheQuery'=>true,'test'=>false]
                        ).' UNION ';
                }

                $testQuery =  substr($testQuery,0,-7);

                if($verbose){
                    echo 'Query to send: '.$testQuery.' at '.$updateTime.EOL;
                }
                $temp = $this->SQLHandler->exeQueryBindParam($testQuery, [], ['fetchAll'=>true]);

                //Used to check whether there are duplicate settings - as ell as to remove '_Last_Updated'
                $res = [];

                //Update the settings
                if($temp != 0) {
                    foreach ($temp as $resArray) {
                        if (!array_key_exists($resArray['settingKey'], $res)) {
                            //Indicate the setting exists
                            $res[$resArray['settingKey']] = 1;
                            //If the setting key was not "_last_changed", it was a real setting
                            if ($resArray['settingKey'] != '_Last_Updated') {
                                if (!$test)
                                    $this->settingsArray[$resArray['settingKey']] = $resArray['settingValue'];
                                if($verbose)
                                    echo 'Setting ' . $resArray['settingKey'] . ' set to ' . $resArray['settingValue'] . EOL;
                            }

                        }
                        //If the setting key was "_Last_Updated", set it.
                        if ($resArray['settingKey'] != '_Last_Updated') {
                            if (!$test) {
                                $this->settingsArrays[$resArray['Source']][$resArray['settingKey']] = $resArray['settingValue'];
                                $this->lastUpdateTimes[$resArray['Source']] = $updateTime;
                            }
                            if($verbose) {
                                echo 'Setting ' . $resArray['settingKey'] . ' in ' . $resArray['Source'] . ' set to ' .
                                    $resArray['settingValue'] . ' at ' . $updateTime . EOL;
                            }
                        }
                    }
                    //If we are running in mixed mode and we got new settings, it means the local settings are out of sync.
                    if($mode != SETTINGS_OP_MODE_MIXED)
                        $this->initLocal(['test'=>$test,'verbose'=>$verbose]);
                }

                //Update the cache if we had any new results
                if($res != [])
                    foreach($this->names as $name){
                        //Update the cache if we're using it
                        //This update might happen twice in MIXED mode - this is an acceptable casualty
                        $this->updateCache(['settingsArray'=>$this->settingsArrays[$name],'name'=>$name,'settingsLastUpdate'=>$updateTime,'test'=>$test]);
                    }

                $this->isInit = true;
                $res = true;
            }

            return $res;
        }

        /** Gets the settings from cache, if they exist there, and updates this handler.
         *
         * @param array $params
         * @returns bool true on success
         * */
        function getFromCache($params = []){
            if($this->RedisHandler === null)
                return false;
            //Indicates requested everything was found
            $test = isset($params['test'])? $params['test'] : $test = false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $res = true;
            $combined_array = [];
            foreach($this->names as $name){
                //If we are not using cache, just continue and set result to false
                if(!isset($this->useCacheArray[$name]) || !$this->useCacheArray[$name]){
                    if($verbose)
                        echo 'Tried to get '.$name.' from cache when useCache for it was false'.EOL;
                    $res = false;
                    continue;
                }
                $settingsJSON = $this->RedisHandler->call('get','_settings_'.$name);
                $settingsMeta = $this->RedisHandler->call('get','_settings_meta_'.$name);
                if($settingsJSON && $settingsMeta){
                    $settings = json_decode($settingsJSON,true);
                    $combined_array = array_merge($combined_array, $settings);
                    if(!$test){
                        $this->lastUpdateTimes[$name] = $settingsMeta;
                        $this->settingsArrays[$name] =  $settings;
                    }
                    if($verbose)
                        echo 'Setting array '.$name.' updated from cache to '.$settingsJSON.', freshness: '.$settingsMeta.EOL;

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
                if($verbose)
                    echo 'Setting array updated to '.json_encode($combined_array).EOL;
            }
            return $res;
        }

        /** Updates the settings at the cache.
         *
         * @param array $params of the form:
         *                  'settingsArray' => array of settings to set in the cache
         *                  'settingsLastUpdate' => Last time said settings were updated (from DB)
         * @returns bool true on success
         * */
        function updateCache($params = []){
            if($this->RedisHandler === null)
                return false;

            //Ensure required params
            $test = isset($params['test'])? $params['test'] : $test = false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            if(!isset($params['settingsArray']) || !isset($params['name']) )
                return false;
            $name = $params['name'];
            $settingsJSON = json_encode($params['settingsArray']);

            if(!isset($this->useCacheArray[$name]) || !$this->useCacheArray[$name]){
                if($verbose)
                    echo 'Tried to update cache of '.$name.' when useCache was false'.EOL;
                return false;
            }

            //Set defaults
            if(!isset($params['settingsLastUpdate']))
                $settingsLastUpdate = 0;
            else
                $settingsLastUpdate = $params['settingsLastUpdate'];

            if(!$test){
                $this->RedisHandler->call('set',['_settings_'.$name,$settingsJSON]);
                $this->RedisHandler->call('set',['_settings_meta_'.$name,$settingsLastUpdate]);
            }
            if($verbose)
                echo 'Updating cache settings array '.$name.' to '.$settingsJSON.' at '.$settingsLastUpdate.EOL;

            return true;
        }

        /** Checks if Settings has been initialized, if no initializes it.
         * Also, if they are initialized, checks if they are up to date, if no updates them.
         * @param array $params of the form:
         *                  'mode' => force operation mode
         * @returns bool true on success, false if unable to open the given settings url.
         * */
        function chkInit(array $params = []){

            $test = isset($params['test'])? $params['test'] : $test = false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            isset($params['mode'])?
                $mode = $params['mode'] : $mode = null;

            if($mode == null)
                $mode = $this->opMode;

            if(!$this->isInit){
                return
                    $this->updateSettings(['mode'=>$mode,'test'=>$test,'verbose'=>$verbose]);
            }
            else{
                //TODO initiate ALL of the settings
                //Local mode
                if($mode != SETTINGS_OP_MODE_DB){
                    $shouldUpdate = false;

                    foreach($this->names as $name){
                        //Suppressing the error because if file doesn't exist, we'll create it
                        $lastUpdate = $this->FileHandler->readFileWaitMutex($this->settingsURLs[$name], '_setMeta', []);

                        //This means that for whatever reason the _setMeta file doesn't exist or is wrong, so we gotta create it
                        if( preg_match_all('/[0-9]|\.|\s/',$lastUpdate)!=strlen($lastUpdate) || $lastUpdate = 0)
                            $this->updateMeta($name,['mode'=>SETTINGS_OP_MODE_LOCAL,'test'=>$test,'verbose'=>$verbose]);

                        //If the last time we updated was BEFORE the last time the global settings were updated, we gotta close the gap.
                        if( (int)$lastUpdate > (int)$this->lastUpdateTimes[$name] )
                            $shouldUpdate = true;
                    }

                    if($shouldUpdate)
                        return $this->updateSettings(['mode'=>$mode,'test'=>$test,'verbose'=>$verbose]);
                    else
                        return true;
                }
                //DB mode
                else{
                    //DB mode only updates from tables we are outdated on anyway
                    return $this->updateSettings(['mode'=>$mode,'test'=>$test,'verbose'=>$verbose]);
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
         * @param array $arr array of specific setting names, can be [] to get all settings
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
         * @param array $params of the form:
         *              'createNew' bool, default false - If false, will not allow creating new settings, only updating.
         *              'targetName' string, default null - Specific name of the setting group requested setting belongs to.
         *              'backUp' bool, default true - whether to locally back up setting
         * @returns mixed false if couldn't check/update settings, or -1 if setting requested doesn't exist.
        */
        function setSetting(string $set, $val, array $params = []){

            $test = isset($params['test'])? $params['test'] : $test = false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $backUp = isset($params['backUp'])? $params['backUp'] : $backUp = true;
            isset($params['createNew'])?
                $createNew = $params['createNew'] : $createNew = false;
            isset($params['targetName'])?
                $targetName = $params['targetName'] : $targetName = null;

            //Make sure we are up to date - chkInit() will update us if we're behind and ONLY return false if it failed
            if(!$this->chkInit(['mode'=>null,'test'=>$test,'verbose'=>$verbose]))
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
                    $this->FileHandler->writeFileWaitMutex(
                        $this->settingsURLs[$targetName],
                        'settings',
                        json_encode($newSettings),
                        ['sec' => 2, 'backUp' => $backUp, 'locakHandler' => $this->mutexes[$targetName]]
                    );
                if($verbose)
                    echo 'Writing '.json_encode($newSettings).' to '.$this->settingsURLs[$targetName].' at '.time().EOL;

                $this->updateMeta($targetName,['mode'=>SETTINGS_OP_MODE_LOCAL,'test'=>$test,'verbose'=>$verbose]);
            }
            //Mixed/DB mode
            if($this->opMode == SETTINGS_OP_MODE_DB || $this->opMode == SETTINGS_OP_MODE_MIXED){
                $tname = strtoupper($this->SQLHandler->getSQLPrefix().SETTINGS_TABLE_PREFIX.$targetName);
                //If we deleted a setting
                if(count($newSettings)<count($this->settingsArrays[$targetName])){
                    $this->SQLHandler->deleteFromTable(
                        $tname,
                        [['settingKey',(string)$set,'=']],
                        ['test'=>$test,'verbose'=>$verbose]
                    );
                }
                //If we added or updated a setting
                else{
                    $this->SQLHandler->insertIntoTable(
                        $tname,
                        ['settingKey','settingValue'],
                        [[[$set,"STRING"],[(string)$val,"STRING"]]],
                        ['onDuplicateKey'=>true,'test'=>$test,'verbose'=>$verbose]
                    );
                }
                $this->updateMeta($targetName,['mode'=>SETTINGS_OP_MODE_DB,'test'=>$test,'verbose'=>$verbose]);
            }
            if($verbose)
                echo 'NOW UPDATING SETTINGS OBJECT: '.EOL;
            $this->updateSettings(['mode'=>null,'test'=>$test,'verbose'=>$verbose]);
            return true;
        }


        /** Sets an array of settings, if exist. Pass null as value to unset a setting
         *
         * TODO Implement properly
         */
        function setSettings(){
            return false;
        }

        /** Updates settings meta file _setMeta to <current UNIX time> - with microseconds.
         * @param string $name Name of setting file
         * @param array $params of the form:
         *                  'mode' => force operation mode
         *
        */
        function updateMeta(string $name, array $params = []){

            $test = isset($params['test'])? $params['test'] : $test = false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            isset($params['mode'])?
                $mode = $params['mode'] : $mode = null;

            if($mode == null)
                $mode = $this->opMode;

            if($mode == SETTINGS_OP_MODE_LOCAL){
                if(!$test)
                    $this->FileHandler->writeFileWaitMutex($this->settingsURLs[$name],'_setMeta',time(),['useNative' => true]);
                if($verbose)
                    echo 'Updating settings meta file of '.$name.' at '.time().EOL;
            }
            else{
                    $tname = strtoupper($this->SQLHandler->getSQLPrefix().SETTINGS_TABLE_PREFIX.$name);
                    $this->SQLHandler->insertIntoTable(
                        $tname,
                        ['settingKey','settingValue'],
                        [[["_Last_Updated","STRING"],[(string)time(),"STRING"]]],
                        ['onDuplicateKey'=>true,'test'=>$test,'verbose'=>$verbose]
                    );
            }
        }

        /** Creates the next iteration in the settings changes history, moves all existing changes 1 step back,
         * and delets the $n-th (default = 10) change. This means the last 10 changes are saved by default.
         * Changing the n will resault in a LOSS of all changes earlier than the new n.
         * @param string $name Name of setting file
         * @param array $params
         */
        function backupSettings(string $name, array $params = []){
            $test = isset($params['test'])? $params['test'] : $test = false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            if(!$test)
                $this->FileHandler->backupFile($this->settingsURLs[$name],'settings',['maxBackup'=>10]);
            if($verbose)
                echo 'Backing up settings named '.$name;
        }

        /** Creates the DB tables and copies the settings there.
         * Can only work in mixed Operation Mode.
         * @param array $params
         * @returns bool
         * */
        function initDB(array $params = []){
            $test = isset($params['test'])? $params['test'] : $test = false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            //This can only be done in mixed mode
            if($this->opMode != SETTINGS_OP_MODE_MIXED){
                return false;
            };
            //Obviously, we also need to be initiated
            if(!$test && !$this->isInit){
                return false;
            };
            foreach($this->settingsArrays as $name=>$settings){
                $tname = strtoupper($this->SQLHandler->getSQLPrefix().SETTINGS_TABLE_PREFIX.$name);
                $query = 'CREATE TABLE IF NOT EXISTS '.$tname.' (
                                                              settingKey varchar(255) PRIMARY KEY,
                                                              settingValue varchar(255) NOT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;
                                                              ';
                if(!$test){
                    $this->SQLHandler->exeQueryBindParam($query,[]);
                    $this->SQLHandler->exeQueryBindParam('TRUNCATE TABLE '.$tname,[]);
                }
                if($verbose){
                    echo 'Query to send: '.$query.EOL;
                    echo 'Query to send: TRUNCATE TABLE '.$tname.EOL;
                }

                $toInsert = [[['_Last_Updated',"STRING"],[(string)$this->lastUpdateTimes[$name],"STRING"]]];
                if($settings)
                    foreach($settings as $k=>$v){
                        array_push($toInsert,[[$k,"STRING"],[$v,"STRING"]]);
                    }
                $this->SQLHandler->insertIntoTable($tname,['settingKey','settingValue'],$toInsert,['test'=>$test,'verbose'=>$verbose]);
            }
            return true;
        }
        /** Initiates the local files.
         *  Will assume the URL of each setting is the URL where you *want* the setting file placed, not where it necessarily exists.
         * @param array $params
         * @returns bool
         * */
        function initLocal(array $params = []){
            $test = isset($params['test'])? $params['test'] : $test = false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
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
                    if(!is_dir($urlWithoutSlash))
                        mkdir($urlWithoutSlash);
                }
                catch(\Exception $e){
                    //Hopefully it only fails if directory already existed
                }
                if(!$test){
                    fclose(fopen($url.'settings','w')) or die(false);
                    $this->FileHandler->writeFileWaitMutex($this->settingsURLs[$name], 'settings', json_encode($settings), ['backUp' => true, 'locakHandler' => $this->mutexes[$name]]);
                }
                if($verbose)
                    echo 'Creating and populating settings '.$name.' with '.json_encode($settings).EOL;
                $this->updateMeta($name,['mode'=>SETTINGS_OP_MODE_LOCAL,'test'=>$test,'verbose'=>$verbose]);
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
        function syncWithDB($params = []){
            $test = isset($params['test'])? $params['test'] : $test = false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            //Set defaults
            if(!isset($params['localToDB']))
                $params['localToDB'] = true;
            if(!isset($params['deleteDifferent']))
                $params['deleteDifferent'] = false;

            //Since we can only rewrite local files completely anyway, syncing is the same as initiating from scratch in this case
            if(!$params['localToDB']){
                return $this->updateSettings(['mode'=>SETTINGS_OP_MODE_DB,'test'=>$test,'verbose'=>$verbose]);
            }
            //If we are deleting new settings, we are essentially doing the same thing as recreating the table.
            if($params['deleteDifferent'])
                return $this->initDB(['test'=>$test,'verbose'=>$verbose]);

            //This can only be done in mixed mode
            if($this->opMode != SETTINGS_OP_MODE_MIXED){
                return false;
            }

            //Check that we are up to date - reminder that at this point we are syncing local files to the db
            $this->chkInit(['mode'=>SETTINGS_OP_MODE_LOCAL,'test'=>$test,'verbose'=>$verbose]);

            //In case of syncing local data to the db, we can do actual syncing
            foreach($this->names as $name){
                $tname = strtoupper($this->SQLHandler->getSQLPrefix().SETTINGS_TABLE_PREFIX.$name);
                $values = [];
                foreach($this->settingsArrays[$name] as $k=>$v){
                    array_push($values,[[(string)$k,'STRING'],[(string)$v,'STRING']]);
                }
                array_push($values,[['_Last_Updated','STRING'],[(string)time(),'STRING']]);
                $this->SQLHandler->insertIntoTable(
                    $tname,
                    ['settingKey','settingValue'],
                    $values,
                    ['onDuplicateKey' => true,'test'=>$test,'verbose'=>$verbose]
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
                echo 'SQLHandler:'.($this->SQLHandler != null).EOL;
                echo 'RedisHandler: '.($this->RedisHandler != null).EOL;
                echo '----'.EOL;
                return true;
            }
        }


    }
}
?>