<?php
namespace IOFrame\Handlers{
    define('IOFrameHandlersSettingsHandler',true);

    /**Handles settings , local and DB based, in IOFrame
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
    */

    class SettingsHandler{
        use \IOFrame\Traits\Logger;
        /** @const OP_MODE_LOCAL Default settings directory relative to site root */
        public const SETTINGS_DIR_FROM_ROOT = 'localFiles';
        /** @const OP_MODE_LOCAL Default setting table prefix */
        public const SETTINGS_TABLE_PREFIX = 'SETTINGS_';
        /** @const OP_MODE_LOCAL Operation mode where SettingsHandler works on the local node */
        public const SETTINGS_OP_MODE_LOCAL = 'local';
        /** @public static OP_MODE_DB Operation mode where SettingsHandler works on a database using SQLManager. Note that SQLManager uses a local SettingsHandler!*/
        public const SETTINGS_OP_MODE_DB = 'db';
        /** @public static OP_MODE_MIXED May operate locally or remotely, and sync setting files*/
        public const SETTINGS_OP_MODE_MIXED = 'mixed';
        /** @var ?string $opMode Mode of operation for SettingsHandler. Can be local or remote (db).*/
        protected ?string $opMode;
        /** @var string[] $settingsArray Array of combined settings*/
        protected array $settingsArray= [];
        /** @var array $settingsArrays Array of setting arrays, of the form <name> => <array>. Corresponds to esch nsme */
        protected array $settingsArrays= [];
        /** @var string[] $settingsURLs Points to the FOLDERs where 'settings' are located. Settings should be a a file with no exaction,
         * even though it's a json file. */
        protected array $settingsURLs = [];
        /** @var array $name The names of the settings. The name is always the last folder in the SettingsURL - for example, userSettings.
                        It is actually an array of the above strings. Also coresponds to table namw in the DB*/
        protected array $names = [];
        /** @var string[] $lastUpdateTimes An array of last times this object's settings were updated.
         *              Per setting name.*/
        protected array $lastUpdateTimes = [];
        /** @var string[] $reservedSettingNames An array of reserved setting names.*/
        protected array $reservedSettingNames = ['_Last_Updated'];
        /** @var bool $isInit Used for lazy initiation - might be useful in different framework implementation. */
        public bool $isInit = false;
        /** @var bool $combined Indicates this handler is a merged view of multiple setting objects (that weren't initially initiated),
         *                      and thus should not be used for updating
         */
        public bool $combined = false;
        /** @var bool $autoUpdate Used to indicate whether the setting handler check for setting updates automatically each time a setting is requested */
        protected bool $autoUpdate = false;
        /** @var bool $base64Storage Whether each setting should be stored in a base64 encoded form */
        protected bool $base64Storage = false;
        /** @var bool[] $useCache Specifies whether we should be using cache */
        protected array $useCacheArray = [];
        /** @var \IOFrame\Managers\LockManager[] $mutexes Concurrency Handlers in local mode*/
        protected array $mutexes = [];
        /** @var ?\IOFrame\Managers\SQLManager $SQLManager An SQLManager in case we may operate remotely. */
        public ?\IOFrame\Managers\SQLManager $SQLManager = null;
        /** @var ?\IOFrame\Managers\RedisManager $RedisManager A RedisManager so that we may use redis directly as cache. */
        public ?\IOFrame\Managers\RedisManager $RedisManager = null;

        /**
         * Sets url to settings file to be the given URL
         * @param mixed $str IF STRING:absolute URL of settings FOLDER, or ALTERNATIVELY the name of the settings remote table
         *                  but of the form "/<tableName>/". In mixed mode, should be the URL, since the name is extracted
         *                  from the URL either way. The local settings folder name *MUST* match the remote table name.
         *                  IF ARRAY: An array of the above strings, to indicate multiple sources.
         * @param array $params
         * @throws \Exception If we try to use a DB/Mixed mode without a db handler.
         */
        function __construct(mixed $str, $params = []){

            //Set defaults
            if(!isset($params['initiate']))
                $params['initiate'] = true;
            if(!isset($params['useCache']))
                $params['useCache'] = true;
            if(!isset($params['SQLManager']))
                $params['SQLManager'] = null;
            if(!isset($params['opMode'])){
                $params['opMode'] = ($params['SQLManager'] != null) ?
                    self::SETTINGS_OP_MODE_DB : self::SETTINGS_OP_MODE_LOCAL ;
            }

            //Settings that have "localSettings" may also make local logs
            if(!empty($params['localSettings'])){
                $this->_constructLogger($params['localSettings'],['logChannel'=>\IOFrame\Definitions::LOG_SETTINGS_CHANNEL]);
            }

            //Reserved
            if(!empty($params['reservedSettingNames']))
                $this->reservedSettingNames = array_merge($this->reservedSettingNames,$params['reservedSettingNames']);

            //Base64 mode
            if(isset($params['base64Storage']))
                $this->base64Storage = $params['base64Storage'];


            //Set redis handler if we got one - and if it is initiated
            if(isset($params['RedisManager'])){
                if(isset($params['RedisManager']->isInit)){
                    if($params['RedisManager']->isInit){
                        $this->RedisManager = $params['RedisManager'];
                    }
                }
            }
            else{
                //Might seem unrelated, but there is no cache without redis
                $params['useCache'] = false;
            }

            //Just in case, both table name and url must end in '/'
            if(!is_array($str)){
                if(!str_ends_with($str, '/'))
                    $str .= '/';
            }
            else{
                foreach($str as $k=>$v){
                    if(!str_ends_with($v, '/'))
                        $str[$k] .= '/';
                }
            }

            //Lets see if we are eligible to even run in remote/mixed mode
            if($params['SQLManager'] == null)
                if($params['opMode'] != self::SETTINGS_OP_MODE_LOCAL)
                    throw new \Exception('Settings Handler may only run in local mode if a DB Handler is not provided!');

            //Now, lets set variables for local mode
            if($params['opMode'] == self::SETTINGS_OP_MODE_LOCAL || $params['opMode'] == self::SETTINGS_OP_MODE_MIXED){
                if(!is_array($str)){
                    $temp = substr($str,0, -1);
                    $name = substr(strrchr($temp, "/"), 1);
                    $this->settingsURLs[$name] = $str;
                    $this->mutexes[$name] = new \IOFrame\Managers\LockManager($str);
                }
                else{
                    foreach($str as $settingFileName){
                        $temp = substr($settingFileName,0, -1);
                        $name = substr(strrchr($temp, "/"), 1);
                        $this->settingsURLs[$name] = $settingFileName;
                        $this->mutexes[$name] = new \IOFrame\Managers\LockManager($settingFileName);
                    }
                }
            }

            //Now, for remote mode
            if($params['opMode'] == self::SETTINGS_OP_MODE_DB || $params['opMode'] == self::SETTINGS_OP_MODE_MIXED){
                $this->SQLManager = $params['SQLManager'];
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
                    $this->names[] = $name;
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
                $this->chkInit($params);
            }
        }

        /** @returns string settingsURL
         */
        function getUrl($name): string {
            return $this->settingsURLs[$name];
        }

        /** @returns string name
         */
        function getNames(): array {
            return $this->names;
        }

        /** @returns string opMode
         */
        function getOpMode(){
            return $this->opMode;
        }

        /** @param string $opMode mode of operation to set - must match one of the constants defined in the class
         */
        function setOpMode(string $opMode): void {
            $modes = [self::SETTINGS_OP_MODE_DB,self::SETTINGS_OP_MODE_MIXED,self::SETTINGS_OP_MODE_LOCAL];
            if(in_array($opMode,$modes))
                $this->opMode = $opMode;
        }

        /**
         * @param bool $bool
         */
        function setAutoUpdate(bool $bool = true): void {
            if(!$this->combined)
                $this->autoUpdate = $bool;
        }

        /** @param array $handlers an object that is used to set SQLManager, RedisManager, or both.
         */
        function setHandlers(array $handlers = []): void {
            if(isset($handlers['SQLManager']))
                $this->SQLManager = $handlers['SQLManager'];
            if(isset($handlers['RedisManager']))
                $this->RedisManager = $handlers['RedisManager'];
        }

        /** Allows creating a view by merging either a settings handler or an object with the current settings.
         *  Once this is done, the handler is thereafter only used to view the combined settings, and cannot update (to/from) the source.
         * @param SettingsHandler|array $newSettings Either a settings handler, or a key=>value object representing settings
         * @param array $params of the form:
         *               'ignoreRegex' => string|string[], regex expressions to ignore ($newSettings keys)
         *               'includeRegex' => string|string[], regex expressions to match ($newSettings keys)
         *               'ignoreEmptyStrings' => bool|string[], whether to ignore empty strings (e.g. '') in the $newSettings
         *                                       If array is passed, only applies to keys (BEFORE ALIASES) in this array
         *               'settingAliases' => Object of the form $newSettingsName => $newSettingsNameAlias,
         *                                   which can be used to set different
         */
        function combineWithSettings(array|SettingsHandler $newSettings, array $params = []): bool {
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            $ignoreRegex = $params['ignoreRegex'] ?? [];
            $includeRegex = $params['includeRegex'] ?? [];
            $ignoreEmptyStrings = $params['ignoreEmptyStrings'] ?? true;
            $settingAliases = $params['settingAliases'] ?? [];

            if(is_object($newSettings)){
                if(get_class($newSettings) !== 'IOFrame\Handlers\SettingsHandler'){
                    if($verbose)
                        echo 'Can only combine SettingsHandler with another SettingsHandler, or an object';
                    $this->logger->notice('Tried to combine with invalid settings',['newSettingsClass'=>get_class($newSettings)]);
                    return false;
                }
                $newSettings = $newSettings->getSettings();
            }

            foreach ($newSettings as $newSettingsName => $newSettingsValue){

                if($ignoreEmptyStrings && ($newSettingsValue==='')){
                    if(!is_array($ignoreEmptyStrings))
                        continue;
                    elseif (in_array($newSettingsName,$ignoreEmptyStrings))
                        continue;
                }

                if(!empty($includeRegex) && !\IOFrame\Util\PureUtilFunctions::matchesRegex($newSettingsName,$includeRegex)){
                    if($verbose)
                        echo $newSettingsName.' didnt match required pattern'.EOL;
                    continue;
                }
                if(!empty($ignoreRegex) && !\IOFrame\Util\PureUtilFunctions::matchesRegex($newSettingsName,$ignoreRegex,false)){
                    if($verbose)
                        echo $newSettingsName.' matched excluded pattern'.EOL;
                    continue;
                }
                $newKey = $settingAliases[$newSettingsName] ?? $newSettingsName;
                $this->settingsArray[$newKey] = $newSettingsValue;
            }

            $this->combined = true;
            $this->autoUpdate = false;
            return true;
        }

        /** Keeps only specific settings in the object (in case of multiple objects in one).
         * Should be used after cloning.
         * @param string|string[] $targets - name(s) of the settings to keep
        */
        function keepSettings(array|string $targets): void {

            if(gettype($targets) == 'string')
                $targets = [$targets];
            //Merge the settings of each target
            $this->settingsArray = [];
            $this->names = [];
            foreach($targets as $target){
                $this->names[] = $target;
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
         * @throws \Exception
         * */
        function updateSettings(array $params = []): bool {
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;

            if($this->combined){
                if($verbose)
                    echo 'Cannot update combined settings'.EOL;
                $this->logger->notice('Tried to update combined view',['currentSettingURLs'=>$this->settingsURLs]);
                return false;
            }

            isset($params['mode'])?
                $mode = $params['mode'] : $mode = null;
            //If not specified otherwise, update in default mode
            $res = false;
            if($mode == null)
                $mode = $this->opMode;
            $updateTime = time();
            //Local mode update
            if($mode != self::SETTINGS_OP_MODE_DB){
                //This is how we update the settings for all individual settings in our collection
                $combinedSettings = [];
                //Update settings from settings files
                foreach($this->names as $name){
                    try{
                        $settings = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($this->settingsURLs[$name], 'settings');
                    }
                    catch (\Exception $e){
                        $this->logger->critical('Could not open settings file on disk '.$e->getMessage(),['trace'=>$e->getTrace(),'name'=>$name]);
                        return false;
                    }
                    $setArray =  json_decode($settings , true ) ;
                    if($setArray === null)
                        $setArray = [];
                    if(is_array($setArray)){
                        if($this->base64Storage){
                            foreach ($setArray as $index => $setting)
                                $setArray[$index] = base64_decode($setting);
                        }
                        $combinedSettings = array_merge( $combinedSettings, $setArray);
                    }
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
            if($mode != self::SETTINGS_OP_MODE_LOCAL){

                //Query used to get only the settings that are not up to date
                $testQuery = '';
                foreach($this->names as $name){
                    $tname = strtoupper($this->SQLManager->getSQLPrefix().self::SETTINGS_TABLE_PREFIX.$name);
                    $testQuery.= $this->SQLManager->selectFromTable($tname,
                            [ [$tname, [['settingKey', '_Last_Updated', '='],['settingValue',$this->lastUpdateTimes[$name],'>='],'AND'], ['settingKey','settingValue'], [], 'SELECT'], 'EXISTS'],
                            ['settingKey','settingValue', '\''.$name.'\' as Source'],
                            ['justTheQuery'=>true,'test'=>false]
                        ).' UNION ';
                }

                $testQuery =  substr($testQuery,0,-7);

                if($verbose){
                    echo 'Query to send: '.$testQuery.' at '.$updateTime.EOL;
                }
                try{
                    $temp = $this->SQLManager->exeQueryBindParam($testQuery, [], ['fetchAll'=>true]);
                }
                catch (\Exception $e){
                    $this->logger->critical('Could not get settings from db '.$e->getMessage(),['trace'=>$e->getTrace(),'names'=>$this->names]);
                    $temp = 0;
                }

                //Used to check whether there are duplicate settings - as ell as to remove '_Last_Updated'
                $res = [];

                //Update the settings
                if($temp != 0) {
                    foreach ($temp as $resArray) {
                        if($this->base64Storage)
                            $resArray['settingValue'] = base64_decode($resArray['settingValue']);
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
                    if($mode != self::SETTINGS_OP_MODE_MIXED)
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
        function getFromCache(array $params = []): bool {
            if($this->RedisManager === null)
                return false;
            //Indicates requested everything was found
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;

            if($this->combined){
                if($verbose)
                    echo 'Cannot properly get with combined settings'.EOL;
                $this->logger->notice('Tried to get from cache in combined view',['currentSettingURLs'=>$this->settingsURLs]);
                return false;
            }
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
                $settingsJSON = $this->RedisManager->call('get','_settings_'.$name);
                $settingsMeta = $this->RedisManager->call('get','_settings_meta_'.$name);
                if($settingsJSON && $settingsMeta){
                    $settings = json_decode($settingsJSON,true);
                    if($this->base64Storage){
                        foreach ($settings as $index => $setting)
                            $settings[$index] = base64_decode($setting);
                    }
                    $combined_array = array_merge($combined_array, $settings);
                    if(!$test){
                        $this->lastUpdateTimes[$name] = $settingsMeta;
                        $this->settingsArrays[$name] =  $settings;
                    }
                    if($verbose)
                        echo 'Setting array '.$name.' updated from cache to '.json_encode($settings).', freshness: '.$settingsMeta.EOL;

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
        function updateCache(array $params = []): bool {
            if($this->RedisManager === null)
                return false;

            //Ensure required params
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;

            if($this->combined){
                if($verbose)
                    echo 'Cannot update combined settings'.EOL;
                $this->logger->notice('Tried to update combined view',['currentSettingURLs'=>$this->settingsURLs]);
                return false;
            }

            if(!isset($params['settingsArray']) || !isset($params['name']) )
                return false;
            $name = $params['name'];
            if($this->base64Storage){
                foreach ($params['settingsArray'] as $index => $setting)
                    $params['settingsArray'][$index] = base64_encode($setting);
            }
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
                $this->RedisManager->call('set',['_settings_'.$name,$settingsJSON]);
                $this->RedisManager->call('set',['_settings_meta_'.$name,$settingsLastUpdate]);
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
         *
         * @throws \Exception
         * @throws \Exception
         */
        function chkInit(array $params = []): bool {

            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
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
                if($mode != self::SETTINGS_OP_MODE_DB){
                    $shouldUpdate = false;

                    foreach($this->names as $name){
                        //Suppressing the error because if file doesn't exist, we'll create it
                        try{
                            $lastUpdate = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($this->settingsURLs[$name], '_setMeta');
                        }
                        catch (\Exception $e){
                            $this->logger->critical('Could not get last updated time '.$e->getMessage(),['trace'=>$e->getTrace(),'settingURL'=>$this->settingsURLs[$name]]);
                            return false;
                        }

                        //This means that for whatever reason the _setMeta file doesn't exist or is wrong, so we gotta create it
                        if( preg_match_all('/[0-9]|\.|\s/',$lastUpdate)!=strlen($lastUpdate) || $lastUpdate = 0)
                            $this->updateMeta($name,['mode'=>self::SETTINGS_OP_MODE_LOCAL,'test'=>$test,'verbose'=>$verbose]);

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
        function getSetting(string $str): ?string {
            //If we are automatically updating, use chkInit - else, just check isInit to assure settings are initiated
            ($this->autoUpdate)? $init = $this->chkInit(): $init = $this->isInit;
            //Make sure we are up to date - chkInit() will update us if we're behind and ONLY return false if it failed
            if(!$init)
                return null;
            else
                return $this->settingsArray[$str] ?? null;
        }



        /*** Gets the array of settings
         *
         * @param array $arr array of specific setting names, can be [] to get all settings
         *
         * @returns mixed
         *      false if settings aren't initiated or updated, and we aren't using auto-update
         *      string[] otherwise (could be empty)
         * */
        function getSettings(array $arr = []): ?array {
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
         * @param array $params same as setSettings
         * @returns mixed read setSettings
        */
        function setSetting(string $set, mixed $val, array $params = []){
            return $this->setSettings([$set=>$val],$params)[$set];
        }


        /** Sets an array of settings, if exist. Pass null as value to unset a setting.
         * Note that for better testing clarity, running this in test mode DOES affect the CLASS variables - but does NOT
         * actually set them in the filesystem/db/cache.
         * Running this function in test mode, then running it or other functions without test mode, would potentially result in incorrect operations.
         * @param array $inputs Array of the form [<string key> => <mixed value>]
         * @param array $params of the form:
         *                'createNew' bool, default false - If false, will allow only updating existing settings, not creating.
         *                'initIfNotExists' bool, default false - If true, will check for, and try to create non-existing tables/files
         *                'escapeBackslashes' bool, default false - Similar to SQLManager->insertIntoTable setting
         *                'backUp' bool, default true - whether to locally back up setting
         *                 'targetNames' array, default [] - [<string settingName>=<string settingFile>]
         *                               Specify explicitly to which settings file(s)/table(s) we are writing.
         *                               Otherwise, defaults to the first (potentially only) setting file/table name
         * @returns array Object of the form:
         *               <string key> => <bool|int -
         *                              true if set, false if couldn't check/update settings,
         *                              -1 if couldn't initiate,
         *                              -2 if setting requested doesn't exist and !$createNew>,
         *                              -3 if setting name is reserved
         * @throws \Exception
         * @throws \Exception
         */
        function setSettings(array $inputs, array $params = []): array {

            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            $backUp = $params['backUp'] ?? true;
            $initIfNotExists = $params['initIfNotExists'] ?? false;
            $escapeBackslashes = $params['escapeBackslashes'] ?? false;
            $createNew = $params['createNew'] ?? false;
            $targetNames = $params['targetNames'] ?? [];

            $newSettingFiles = [];
            $results = [];

            foreach ($inputs as $set => $val){
                $results[$set] = -1;
                if(($val !== null) && $this->base64Storage)
                    $inputs[$set] = base64_encode($val);
            }

            if($this->combined){
                if($verbose)
                    echo 'Cannot update combined settings'.EOL;
                return $results;
            }

            //Make sure we are up to date - chkInit() will update us if we're behind and ONLY return false if it failed
            if(!$this->chkInit(['mode'=>null,'test'=>$test,'verbose'=>$verbose]))
                return $results;

            foreach ($inputs as $set => $val){
                if(!isset($this->settingsArray[$set])){
                    if(!$createNew){
                        $results[$set] = -2;
                        continue;
                    }
                }
                elseif (in_array($set,$this->reservedSettingNames)){
                    $results[$set] = -3;
                    continue;
                }

                //Find out which array of settings our setting belongs to.
                if(empty($targetNames[$set])){
                    foreach($this->names as $name){
                        if(isset($this->settingsArrays[$name]) && empty($targetNames[$set]))
                            if(array_key_exists($set,$this->settingsArrays[$name])){
                                $targetNames[$set] = $name;
                            }
                    }
                    if(empty($targetNames[$set]))
                        $targetNames[$set] = $this->names[0];
                }
                $targetFile = $targetNames[$set];

                //Update session settings (which are now up to date) with the new value
                if($val !== null)
                    $this->settingsArrays[$targetFile][$set] = $val;
                elseif(isset($this->settingsArrays[$targetFile][$set]))
                    unset($this->settingsArrays[$targetFile][$set]);

                if(empty($newSettingFiles[$targetFile]))
                    $newSettingFiles[$targetFile] = [];
                if(empty($newSettingFiles[$targetFile][$set]))
                    $newSettingFiles[$targetFile][$set] = $val;
            }

            //This is how we update the specified settings file
            if( ($this->opMode == self::SETTINGS_OP_MODE_LOCAL) || ($this->opMode == self::SETTINGS_OP_MODE_MIXED) ){
                foreach ($newSettingFiles as $file => $newSettings){
                    if($initIfNotExists){
                        $newDir = $this->settingsURLs[$file]??__DIR__.'/../../'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$file;
                        if(!is_dir($newDir)){
                            if(!$test)
                                @mkdir($newDir,0777,true);
                            if($verbose)
                                echo 'Creating settings directory '.$newDir.EOL;
                        }
                        if(!is_file($newDir.'/settings')){
                            if(!$test)
                                @touch($newDir.'/settings');
                            if($verbose)
                                echo 'Creating settings file '.$newDir.'/settings'.EOL;
                        }
                    }

                    //As a reminder, we populated $this->settingsArrays[$file] with the new settings earlier
                    try{
                        $success = $test || \IOFrame\Util\FileSystemFunctions::writeFileWaitMutex(
                                $this->settingsURLs[$file],
                                'settings',
                                json_encode($this->settingsArrays[$file]),
                                ['sec' => 2, 'backUp' => $backUp, 'locakHandler' => $this->mutexes[$file]]
                            );
                    }
                    catch (\Exception $e){
                        $this->logger->critical('Could not write new settings to file '.$e->getMessage(),['trace'=>$e->getTrace(),'settingURL'=>$this->settingsURLs[$file]]);
                        return $results;
                    }

                    if($verbose)
                        echo 'Adding '.json_encode($this->settingsArrays[$file]).' to '.$this->settingsURLs[$file].' at '.time().EOL;

                    if($success)
                        $this->updateMeta($file,['mode'=>self::SETTINGS_OP_MODE_LOCAL,'test'=>$test,'verbose'=>$verbose]);

                    foreach ($newSettings as $setting => $value){
                        $results[$setting] = $success;
                    }
                }
            }

            //Mixed/DB mode
            //Remember that even if we fail here in 'mixed' mode, there is no need to rollback local settings, as DB settings take precedence in this mode
            if( ($this->opMode == self::SETTINGS_OP_MODE_DB) || ($this->opMode == self::SETTINGS_OP_MODE_MIXED) ){

                $toDelete = [];
                $toInsert = [];

                foreach ($newSettingFiles as $file => $newSettings){
                    $tname = strtoupper($this->SQLManager->getSQLPrefix().self::SETTINGS_TABLE_PREFIX.$file);
                    $toDelete[$file]= [];
                    $toInsert[$file]= [];

                    if($initIfNotExists){
                        $existingTable = $this->SQLManager->selectFromTable(
                            'information_schema.TABLES',
                            [
                                ['TABLE_TYPE',['BASE TABLE','STRING'],'LIKE'],
                                ['TABLE_NAME',[$tname,'STRING'],'='],
                                'AND'
                            ],
                            ['TABLE_NAME','TABLE_TYPE'],
                            []
                        );

                        if(count($existingTable) === 0){
                            $query = 'CREATE TABLE IF NOT EXISTS '.$tname.' (
                                                              settingKey varchar(255) PRIMARY KEY,
                                                              settingValue TEXT NOT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;
                                                              ';
                            if(!$test){
                                try{
                                    $success = $this->SQLManager->exeQueryBindParam($query,[]);
                                }catch(\Exception $e){
                                    $this->logger->critical('Could not write new settings to db '.$e->getMessage(),['trace'=>$e->getTrace(),'query'=>$query]);
                                    $success = false;
                                }
                                if($success)
                                    $this->SQLManager->exeQueryBindParam('TRUNCATE TABLE '.$tname,[]);
                            }
                            else
                                $success = true;

                            if($verbose){
                                echo 'Query to send: '.$query.EOL;
                                echo 'Query to send: TRUNCATE TABLE '.$tname.EOL;
                            }

                            if(!$success){
                                foreach ($newSettings as $setting => $value){
                                    $results[$setting] = false;
                                }
                                continue;
                            }

                        }
                    }

                    foreach ($newSettings as $setting => $value){
                        if($value === null)
                            $toDelete[$file][] = [$setting, 'STRING'];
                        else
                            $toInsert[$file][] = [[$setting, 'STRING'], [(string)$value, 'STRING']];
                    }

                    //If we deleted a setting
                    if(count($toDelete[$file])){
                        $success = $this->updateMeta($file,['mode'=>self::SETTINGS_OP_MODE_DB,'test'=>$test,'verbose'=>$verbose]);

                        if($success !== true){
                            foreach ($newSettings as $setting => $value){
                                if($value === null)
                                    $results[$setting] = false;
                            }
                            $this->logger->critical('Could not update meta to delete settings ',['settings'=>$toDelete[$file]]);
                            continue;
                        }

                        $toDelete[$file][] = 'CSV';

                        $success = $this->SQLManager->deleteFromTable(
                            $tname,
                            [['settingKey',$toDelete[$file],'IN']],
                            ['test'=>$test,'verbose'=>$verbose]
                        );

                        if($success !== true){
                            foreach ($newSettings as $setting => $value){
                                if($value === null)
                                    $results[$setting] = false;
                            }
                            $this->logger->critical('Could not update db to delete settings ',['settings'=>$toDelete[$file]]);
                            continue;
                        }

                    }
                    if(count($toInsert[$file])){
                        //In this case, both updating settings and meta is done in a single commit
                        $toInsert[$file][] = [["_Last_Updated", "STRING"], [(string)time(), "STRING"]];
                        $success =
                            $this->SQLManager->insertIntoTable(
                                $tname,
                                ['settingKey','settingValue'],
                                $toInsert[$file],
                                ['onDuplicateKey'=>true,'escapeBackslashes'=>$escapeBackslashes,'test'=>$test,'verbose'=>$verbose]
                            );
                        if($success !== true){
                            foreach ($newSettings as $setting => $value){
                                if($value !== null)
                                    $results[$setting] = false;
                            }
                            $this->logger->critical('Could not insert new settings to db',['settings'=>$toInsert[$file]]);
                        }
                    }

                }

            }

            if($verbose)
                echo 'Updating settings object'.EOL;

            $this->updateSettings(['mode'=>null,'test'=>$test,'verbose'=>$verbose]);

            return $results;
        }

        /** Updates settings meta file _setMeta to <current UNIX time> - with microseconds.
         * @param string $name Name of setting file
         * @param array $params of the form:
         *                  'mode' => force operation mode
         *
         * @throws \Exception
         * @throws \Exception
         */
        function updateMeta(string $name, array $params = []){

            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            if($this->combined){
                if($verbose)
                    echo 'Cannot update combined settings'.EOL;
                $this->logger->notice('Tried to update meta in combined view',['name'=>$name]);
                return false;
            }

            isset($params['mode'])?
                $mode = $params['mode'] : $mode = null;

            if($mode == null)
                $mode = $this->opMode;

            if($mode == self::SETTINGS_OP_MODE_LOCAL){
                $success = $test || \IOFrame\Util\FileSystemFunctions::writeFileWaitMutex($this->settingsURLs[$name],'_setMeta',time(),['useNative' => true]);
                if($verbose)
                    echo 'Updating settings meta file of '.$name.' at '.time().EOL;
                if(!$success)
                    $this->logger->error('Could not update settings file meta',['name'=>$name,'url'=>$this->settingsURLs[$name]]);
            }
            else{
                $tname = strtoupper($this->SQLManager->getSQLPrefix().self::SETTINGS_TABLE_PREFIX.$name);
                $success = $this->SQLManager->insertIntoTable(
                    $tname,
                    ['settingKey','settingValue'],
                    [[["_Last_Updated","STRING"],[(string)time(),"STRING"]]],
                    ['onDuplicateKey'=>true,'test'=>$test,'verbose'=>$verbose]
                );
                if(!$success)
                    $this->logger->error('Could not update settings db meta',['name'=>$name,'table'=>$tname]);
            }

            return $success;
        }

        /** Creates the next iteration in the settings changes history, moves all existing changes 1 step back,
         * and delets the $n-th (default = 10) change. This means the last 10 changes are saved by default.
         * Changing the n will resault in a LOSS of all changes earlier than the new n.
         * @param string $name Name of setting file
         * @param array $params
         * @return bool
         */
        function backupSettings(string $name, array $params = []): bool {
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            if($this->combined){
                if($verbose)
                    echo 'Cannot update combined settings'.EOL;
                return false;
            }
            if(!$test)
                \IOFrame\Util\FileSystemFunctions::backupFile($this->settingsURLs[$name],'settings',['maxBackup'=>10]);
            if($verbose)
                echo 'Backing up settings named '.$name;
            return true;
        }

        /** Creates the DB tables and copies the settings there.
         * Can only work in mixed Operation Mode.
         * @param array $params
         *                 'escapeBackslashes' bool, default false - Similar to SQLManager->insertIntoTable setting
         * @returns bool
         * */
        function initDB(array $params = []): bool {
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            $escapeBackslashes = $params['escapeBackslashes'] ?? false;
            if($this->combined){
                if($verbose)
                    echo 'Cannot update combined settings'.EOL;
                return false;
            }
            //This can only be done in mixed mode
            if($this->opMode != self::SETTINGS_OP_MODE_MIXED){
                return false;
            }
            //Obviously, we also need to be initiated
            if(!$test && !$this->isInit){
                return false;
            }

            foreach($this->settingsArrays as $name=>$settings){
                $tname = strtoupper($this->SQLManager->getSQLPrefix().self::SETTINGS_TABLE_PREFIX.$name);
                $query = 'CREATE TABLE IF NOT EXISTS '.$tname.' (
                                                              settingKey varchar(255) PRIMARY KEY,
                                                              settingValue TEXT NOT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;
                                                              ';
                if($verbose){
                    echo 'Query to send: '.$query.EOL;
                    echo 'Query to send: TRUNCATE TABLE '.$tname.EOL;
                }
                if(!$test){
                    try{
                        $this->SQLManager->exeQueryBindParam($query,[]);
                        $this->SQLManager->exeQueryBindParam('TRUNCATE TABLE '.$tname,[]);
                    }
                    catch (\Exception $e){
                        $this->logger->critical('Could not create settings db table'.$e->getMessage(),['trace'=>$e->getTrace(),'query'=>$query]);
                        return false;
                    }
                }

                $toInsert = [];
                if($settings!==null)
                    foreach($settings as $k=>$v){
                        $toInsert[] = [[$k, "STRING"], [$this->base64Storage ? base64_encode($v) : $v, "STRING"]];
                    }
                $toInsert[] = [['_Last_Updated', "STRING"], [(string)$this->lastUpdateTimes[$name], "STRING"]];
                $success = $this->SQLManager->insertIntoTable($tname,['settingKey','settingValue'],$toInsert,['escapeBackslashes'=>$escapeBackslashes,'test'=>$test,'verbose'=>$verbose]);
                if(!$success){
                    $this->logger->critical('Could not update settings db table',['name'=>$name,'table'=>$tname,'newSettings'=>$settings]);
                    return false;
                }
            }
            return true;
        }

        /** Initiates the local files.
         *  Will assume the URL of each setting is the URL where you *want* the setting file placed, not where it necessarily exists.
         * @param array $params
         * @return bool|void
         * @throws \Exception
         */
        function initLocal(array $params = []){
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            if($this->combined){
                if($verbose)
                    echo 'Cannot update combined settings'.EOL;
                return false;
            }
            //This can only be done in mixed mode
            if($this->opMode != self::SETTINGS_OP_MODE_MIXED){
                return false;
            }
            //Obviously, we also need to be initiated
            if(!$this->isInit){
                return false;
            }
            //Here we do the initiation
            foreach($this->settingsArrays as $name=>$settings){
                $url = $this->settingsURLs[$name];
                $urlWithoutSlash = substr($url,0, -1);
                try{
                    if(!is_dir($urlWithoutSlash))
                        mkdir($urlWithoutSlash);
                }
                catch(\Exception $e){
                    //Hopefully it only fails if directory already existed
                    $this->logger->warning('Could not create settings local directory'.$e->getMessage(),['trace'=>$e->getTrace(),'url'=>$urlWithoutSlash]);
                }
                if($this->base64Storage) {
                    foreach ($settings as $index => $setting)
                        $settings[$index] = base64_encode($setting);
                }
                if(!$test){
                    try{
                        fclose(fopen($url.'settings','w')) or die(false);
                        \IOFrame\Util\FileSystemFunctions::writeFileWaitMutex($this->settingsURLs[$name], 'settings', json_encode($settings), ['backUp' => true, 'locakHandler' => $this->mutexes[$name]]);
                    }
                    catch (\Exception $e){
                        $this->logger->critical('Could not create settings local file'.$e->getMessage(),['trace'=>$e->getTrace(),'url'=>$url]);
                    }
                }
                if($verbose)
                    echo 'Creating and populating settings '.$name.' with '.json_encode($settings).EOL;
                $this->updateMeta($name,['mode'=>self::SETTINGS_OP_MODE_LOCAL,'test'=>$test,'verbose'=>$verbose]);
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
         *                      bool escapeBackslashes - passed to initDB
         * @returns bool Whether we succeeded or not.
         * @throws \Exception
         * @throws \Exception
         */
        function syncWithDB(array $params = []): bool {
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;

            if($this->combined){
                if($verbose)
                    echo 'Cannot update combined settings'.EOL;
                return false;
            }

            //Set defaults
            if(!isset($params['localToDB']))
                $params['localToDB'] = true;
            if(!isset($params['deleteDifferent']))
                $params['deleteDifferent'] = false;

            //Since we can only rewrite local files completely anyway, syncing is the same as initiating from scratch in this case
            if(!$params['localToDB']){
                return $this->updateSettings(['mode'=>self::SETTINGS_OP_MODE_DB,'test'=>$test,'verbose'=>$verbose]);
            }
            //If we are deleting new settings, we are essentially doing the same thing as recreating the table.
            if($params['deleteDifferent'])
                return $this->initDB($params);

            //This can only be done in mixed mode
            if($this->opMode != self::SETTINGS_OP_MODE_MIXED){
                return false;
            }

            //Check that we are up to date - reminder that at this point we are syncing local files to the db
            $this->chkInit(['mode'=>self::SETTINGS_OP_MODE_LOCAL,'test'=>$test,'verbose'=>$verbose]);

            //In case of syncing local data to the db, we can do actual syncing
            foreach($this->names as $name){
                $tname = strtoupper($this->SQLManager->getSQLPrefix().self::SETTINGS_TABLE_PREFIX.$name);
                $values = [];
                foreach($this->settingsArrays[$name] as $k=>$v){
                    $values[] = [[(string)$k, 'STRING'], [(string)($this->base64Storage ? base64_encode($v) : $v), 'STRING']];
                }
                $values[] = [['_Last_Updated', 'STRING'], [(string)time(), 'STRING']];
                $success = $this->SQLManager->insertIntoTable(
                    $tname,
                    ['settingKey','settingValue'],
                    $values,
                    ['onDuplicateKey' => true,'test'=>$test,'verbose'=>$verbose]
                );
                if(!$success)
                    $this->logger->critical('Could not update settings db table',['name'=>$name,'table'=>$tname,'newSettings'=>$this->settingsArrays[$name]]);
            }

            return true;
        }


        /** Prints all the settings, like this:
        Settings{
        <Setting name> : <setting value>
        }
        @returns bool false if settings aren't initiated or updated, and we aren't using auto-update
         */
        function printAll(): bool {
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
                if($this->opMode != self::SETTINGS_OP_MODE_DB)
                    echo 'URLs: '.json_encode($this->settingsURLs).EOL;
                echo 'Update times:'.json_encode($this->lastUpdateTimes).EOL;
                echo 'OP Mode:'.$this->opMode.EOL;
                echo 'Cache: '.json_encode($this->useCacheArray).EOL;
                echo 'AutoUpdate:'.($this->autoUpdate).EOL;
                echo 'SQLManager:'.($this->SQLManager != null).EOL;
                echo 'RedisManager: '.($this->RedisManager != null).EOL;
                echo '----'.EOL;
                return true;
            }
        }

        /** Return update times
         * @return string[]|string
         */
        public function getLastUpdateTimes($setting = null): array|string {
            return $setting ? $this->lastUpdateTimes[$setting]??'-1': $this->lastUpdateTimes;
        }


    }
}