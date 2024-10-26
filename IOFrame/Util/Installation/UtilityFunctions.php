<?php

namespace IOFrame\Util\Installation{

    use IOFrame\Util\ValidatorFunctions;
    use ScssPhp\ScssPhp\Util;

    define('IOFrameUtilInstallationUtilityFunctions',true);

    class UtilityFunctions{

        /** Creates a new installation session, or validates an existing one
         * @returns bool
         * */
        public static function validateOrCreateInstallSession(array $params = []): bool {
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            $baseUrl = \IOFrame\Util\FrameworkUtilFunctions::getBaseUrl();

            if(!file_exists($baseUrl.'localFiles/_installSes') && isset($_SERVER['REMOTE_ADDR'])){
                if($verbose)
                    echo 'Creating localFiles/_installSes'.EOL;

                if(!$test){
                    $myFile = fopen($baseUrl.'localFiles/_installSes', 'w+');
                    fwrite($myFile,$_SERVER['REMOTE_ADDR']);
                }
                return true;
            }
            else{
                if($verbose)
                    echo 'Validating localFiles/_installSes'.EOL;

                $myFile = fopen($baseUrl.'localFiles/_installSes', 'r+');
                if(!$myFile){
                    if($verbose)
                        echo 'Could not read localFiles/_installSes, or empty file'.EOL;
                    fclose($myFile);
                    return !$test;
                }

                $res = fread($myFile,100) == $_SERVER['REMOTE_ADDR'];
                if($verbose)
                    echo 'localFiles/_installSes '.($res? 'matches' : 'does not match').' installer IP.'.EOL;

                fclose($myFile);

                return $test || $res;
            }
        }

        /** Checks whether the framework is already installed
         * @returns bool
         * */
        public static function alreadyInstalled(): bool {
            return file_exists(\IOFrame\Util\FrameworkUtilFunctions::getBaseUrl().'localFiles/_installComplete');
        }

        /** Set multiple settings in multiple handlers
         * @param array $targets object of objects where the key is the setting identifier, and the value is of the from:
         *      'args' => object of the form <string, setting name> => <mixed, setting value>
         *      'handler' => IOFrame\Handlers\SettingsHandler, relevant settings handler
         * @returns bool|string
         *          true on success, name of the failed setting on failure
         */
        public static function initMultipleSettingHandlers(array $targets, array $params = []){
            foreach ($targets as $identifier => $inputs){
                $success = $inputs['handler']->setSettings(
                    $inputs['args'],
                    array_merge(['createNew'=>true,'initIfNotExists'=>true],$params)
                );
                foreach($success as $key=>$res){
                    if($res !== true)
                        return $identifier;
                }
            }

            return true;
        }

        /** Initiates local files
         * @param array $params
         *              'checkIfAlreadyInstalled' - bool, default true - if true, will check whether installation is already complete.
         * @returns bool
         * */
        public static function initiateLocalFiles($params = []){
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            $baseUrl = \IOFrame\Util\FrameworkUtilFunctions::getBaseUrl();

            $checkIfAlreadyInstalled = $params['checkIfAlreadyInstalled'] ?? true;

            //--------------------If the installation was complete, exit --------------------
            if($checkIfAlreadyInstalled && self::alreadyInstalled()){
                if($verbose)
                    echo 'It seems the site is already installed! If this is an error, go to the /siteFiles folder and delete _installComplete.'.EOL;
                return false;
            }

            //--------------------Initialize local files folder if it does not exist--------------------
            if(!is_dir($baseUrl.'localFiles')){
                if(!$test && !mkdir($baseUrl.'localFiles')){
                    if($verbose)
                        echo 'Cannot create files directory for some reason - most likely insufficient user privileges, or it already exists'.EOL;
                    return false;
                }
            }

            //-------------------- Throw in an htaccess (from a place it already exists) to deny access to local files --------------------
            if(!file_exists($baseUrl.'localFiles/.htaccess')){
                $copy = $test ? 0 : \IOFrame\Util\FileSystemFunctions::createOrPopulateFile($baseUrl.'localFiles/.htaccess',['copyExistingFile'=>$baseUrl.'plugins/.htaccess']);
                if($copy !== 0){
                    if($verbose)
                        echo 'Cannot copy .htaccess for some reason - most likely insufficient user privileges, or plugins/.htaccess was removed, error '.$copy.EOL;
                    return false;
                }
            }

            //--------------------Initialize temp files folder if it does not exist--------------------
            if(!is_dir($baseUrl.'localFiles/temp')){
                if(!$test && !mkdir($baseUrl.'localFiles/temp')){
                    if($verbose)
                        echo 'Cannot create temp directory for some reason - most likely insufficient user privileges'.EOL;
                    return false;
                }
            }

            //--------------------Initialize logs folder if it does not exist--------------------
            if(!is_dir($baseUrl.'localFiles/logs')){
                if(!$test && !mkdir($baseUrl.'localFiles/logs')){
                    if($verbose)
                        echo 'Cannot create logs directory for some reason - most likely insufficient user privileges'.EOL;
                    return false;
                }
            }

            //--------------------Create the definitions json file --------------------
            $create = $test ? 0 : \IOFrame\Util\FileSystemFunctions::createOrPopulateFile($baseUrl.'localFiles/definitions/definitions.json');
            if($create !== 0){
                if($verbose)
                    echo 'Cannot create definitions for some reason - most likely insufficient user privileges, error '.$create.EOL;
                return false;
            }

            //--------------------Initialize plugin "settings" folders--------------
            $create = $test ? 0 : \IOFrame\Util\FileSystemFunctions::createOrPopulateFile($baseUrl.'localFiles/plugins/settings');
            if($create !== 0){
                if($verbose)
                    echo 'Cannot create plugins for some reason - most likely insufficient user privileges, error '.$create.EOL;
                return false;
            }

            //--------------------Create empty plugin order--------------------
            $create = $test ? 0 : \IOFrame\Util\FileSystemFunctions::createOrPopulateFile($baseUrl.'localFiles/plugin_order/order');
            if($create !== 0){
                if($verbose)
                    echo 'Cannot create plugin order for some reason - most likely insufficient user privileges, error '.$create.EOL;
                return false;
            }

            //--------------------Create empty plugin dependency map--------------------
            if(!is_dir($baseUrl.'localFiles/pluginDependencyMap')){
                if(!$test && !mkdir($baseUrl.'localFiles/pluginDependencyMap')){
                    if($verbose)
                        echo 'Cannot create plugin dependency map for some reason - most likely insufficient user privileges'.EOL;
                    return false;
                }
            }

            return true;
        }

        /** Initiates settings to defaults
         * @param array $params
         *                  <settingsName, e.g "localSettings"> - object, default null. Keys correspond to relevant settings, value to ovveride default.
         * @returns bool | string
         *      same as initMultipleSettingHandlers
         * */
        public static function initiateDefaultSettings(
            \IOFrame\Handlers\SettingsHandler $localSettings,
            \IOFrame\Handlers\SettingsHandler $siteSettings,
            \IOFrame\Handlers\SettingsHandler $userSettings,
            \IOFrame\Handlers\SettingsHandler $pageSettings,
            \IOFrame\Handlers\SettingsHandler $resourceSettings,
            \IOFrame\Handlers\SettingsHandler $apiSettings,
            \IOFrame\Handlers\SettingsHandler $tagSettings,
            \IOFrame\Handlers\SettingsHandler $logSettings,
            \IOFrame\Handlers\SettingsHandler $metaSettings,
            array $params = []
        ){
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            $version = \IOFrame\Util\FrameworkUtilFunctions::getLocalFrameworkVersion(['verbose'=>$verbose]);
            $baseUrl = \IOFrame\Util\FrameworkUtilFunctions::getBaseUrl();

            $localArgs = [];
            $siteArgs = [];
            $userArgs = [];
            $pageArgs = [];
            $resourceArgs = [];
            $apiArgs = [];
            $tagArgs = [];
            $logArgs = [];
            $metaArgs = [];

            $localArgs["absPathToRoot"] = $baseUrl;
            $localArgs["pathToRoot"] = '';
            $localArgs["opMode"] = \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_MIXED;
            $localArgs["dieOnPluginMismatch"] = true;
            $localArgs["nodeID"] = \IOFrame\Util\PureUtilFunctions::GeraHash(12);
            $localArgs["highScalability"] = 0;
            $localArgs["_templates_default"] = '';
            $localArgs["_templates_maintenance_local"] = '';
            $localArgs["_templates_maintenance_global"] = '';
            $localArgs["_templates_plugins_mismatch"] = '';
            $localArgs["_templates_page_not_found"] = '';
            $localArgs["_templates_user_banned"] = '';
            $localArgs["_templates_ip_blacklisted"] = '';
            $localArgs["_templates_unauthorized_generic"] = '';

            $siteArgs["siteName"] = 'My Website';
            $siteArgs["maxInacTime"] = 3600;
            $siteArgs["privateKey"] = '0000000000000000000000000000000000000000000000000000000000000000';
            $siteArgs["sslOn"] = 1;
            $siteArgs["maxCacheSize"] = 65536;
            $siteArgs["maxUploadSize"] = 4000000;
            $siteArgs["tokenTTL"] = 3600;
            $siteArgs["CPMenu"] = json_encode([],JSON_FORCE_OBJECT);
            $siteArgs["languages"] = '';
            $siteArgs["languagesMap"] = '{"eng":{"flag":"gb","title":"English"}}';
            $siteArgs["defaultLanguage"] = 'eng';
            $siteArgs["captcha_site_key"] = '';
            $siteArgs["captcha_secret_key"] = '';
            $siteArgs["allowTesting"] = 0;
            $siteArgs["devMode"] = 0;
            $siteArgs["enableStickyCookie"] = 0;
            $siteArgs["stickyCookieDuration"] = 0;
            $siteArgs["ver"] = $version;

            $userArgs["pwdResetExpires"] = 72;
            $userArgs["mailConfirmExpires"] = 72;
            $userArgs["regConfirmTemplate"] = 'default_activation';
            $userArgs["regConfirmTitle"] = 'Registration Confirmation Mail';
            $userArgs["pwdResetTemplate"] = 'default_password_reset';
            $userArgs["pwdResetTitle"] = 'Password Reset Confirmation Mail';
            $userArgs["emailChangeTemplate"] = 'default_mail_reset';
            $userArgs["emailChangeTitle"] = 'Email Change Confirmation Mail';
            $userArgs["email2FATemplate"] = 'default_mail_2FA';
            $userArgs["email2FATitle"] = 'Two Factor Authentication Mail';
            $userArgs["emailSusTemplate"] = 'default_mail_sus';
            $userArgs["emailSusTitle"] = 'Suspicious Account Activity';
            $userArgs["passwordResetTime"] = 5;
            $userArgs["inviteMailTemplate"] = 'default_invite';
            $userArgs["inviteMailTitle"] = 'You\\\'ve been invited to IOFrame Test';
            $userArgs["inviteExpires"] = 774;
            $userArgs["rememberMe"] = 1;
            $userArgs["rememberMeLimit"] = 0;
            $userArgs["relogWithCookies"] = 1;
            $userArgs["userTokenExpiresIn"] = 0;
            $userArgs["allowRegularLogin"] = 1;
            $userArgs["allowRegularReg"] = 1;
            $userArgs["selfReg"] = 0;
            $userArgs["regConfirmMail"] = 0;
            $userArgs["allowSMS2FA"] = 0;
            $userArgs["sms2FAExpires"] = 300;
            $userArgs["allowMail2FA"] = 1;
            $userArgs["mail2FAExpires"] = 1800;
            $userArgs["allowApp2FA"] = 1;

            $pageArgs["loginPage"] = 'cp/login';
            $pageArgs["registrationPage"] = 'cp/login';
            $pageArgs["pwdReset"] = 'cp/account';
            $pageArgs["mailReset"] = 'cp/account';
            $pageArgs["regConfirm"] = 'cp/account';
            $pageArgs["homepage"] = 'front/ioframe/pages/welcome';
            $pageArgs["isSPA"] = '0';

            $resourceArgs["videoPathLocal"] = 'front/ioframe/vid/';
            $resourceArgs["imagePathLocal"] = 'front/ioframe/img/';
            $resourceArgs["jsPathLocal"] = 'front/ioframe/js/';
            $resourceArgs["cssPathLocal"] = 'front/ioframe/css/';
            $resourceArgs["autoMinifyJS"] = 1;
            $resourceArgs["autoMinifyCSS"] = 1;
            $resourceArgs["imageQualityPercentage"] = 100;
            $resourceArgs["allowDBMediaGet"] = 1;

            $apiArgs["articles"] = json_encode(['active'=>1,'allowUserBannedActions'=>['getArticles','getArticle']]);
            $apiArgs["auth"] = json_encode(['active'=>1,'allowUserBannedActions'=>['getRank','isLoggedIn']]);
            $apiArgs["contacts"] = json_encode(['active'=>1,'allowUserBannedActions'=>['getContactTypes']]);
            $apiArgs["language-objects"] = json_encode(['active'=>1,'allowUserBannedActions'=>['getLanguageObjects','setPreferredLanguage']]);
            $apiArgs["logs"] = json_encode(['active'=>1,'allowUserBannedActions'=>[]]);
            $apiArgs["mail"] = json_encode(['active'=>1,'allowUserBannedActions'=>[]]);
            $apiArgs["media"] = json_encode(['active'=>1,'allowUserBannedActions'=>['getDBMedia','getGallery','getVideoGallery']]);
            $apiArgs["menu"] = json_encode(['active'=>1,'allowUserBannedActions'=>['getMenu']]);
            $apiArgs["object-auth"] = json_encode(['active'=>1,'allowUserBannedActions'=>[]]);
            $apiArgs["orders"] = json_encode(['active'=>0,'allowUserBannedActions'=>['getOrder']]);
            $apiArgs["plugins"] = json_encode(['active'=>1,'allowUserBannedActions'=>[]]);
            $apiArgs["security"] = json_encode(['active'=>1,'allowUserBannedActions'=>[]]);
            $apiArgs["session"] = json_encode(['active'=>1,'allowUserBannedActions'=>[]]);
            $apiArgs["settings"] = json_encode(['active'=>1,'allowUserBannedActions'=>[]]);
            $apiArgs["tags"] = json_encode(['active'=>1,'allowUserBannedActions'=>['getBaseTags','getCategoryTags','getManifest']]);
            $apiArgs["tokens"] = json_encode(['active'=>1,'allowUserBannedActions'=>[]]);
            $apiArgs["users"] = json_encode(['active'=>1,'allowUserBannedActions'=>['getMyUser','require2FA','requestApp2FA','confirmApp','logUser','pwdReset','changePassword','regConfirm','mailReset','changeMail']]);
            $apiArgs["restrictedArticleByAddress"] = 0;
            $apiArgs["captchaFile"] = 'validateCaptcha.php';

            $tagArgs["availableTagTypes"] = json_encode(
                ['default-article-tags'=>['title'=>'Default Article Tags','img'=>true,'img_empty_url'=>'ioframe/img/icons/upload.svg','extraMetaParameters'=>['eng'=>['title'=>'Tag Title']]]]
            );
            $tagArgs["availableCategoryTagTypes"] = '';

            $logArgs["secStatus"] = \Monolog\Logger::NOTICE;
            $logArgs["logStatus"] = \Monolog\Logger::NOTICE;
            $logArgs["defaultChannels"] = implode(
                ',',
                [
                    \IOFrame\Definitions::LOG_DEFAULT_CHANNEL,
                    \IOFrame\Definitions::LOG_GENERAL_SECURITY_CHANNEL,
                    \IOFrame\Definitions::LOG_USERS_CHANNEL,
                    \IOFrame\Definitions::LOG_TOKENS_CHANNEL,
                    \IOFrame\Definitions::LOG_TAGS_CHANNEL,
                    \IOFrame\Definitions::LOG_SETTINGS_CHANNEL,
                    \IOFrame\Definitions::LOG_ROUTING_CHANNEL,
                    \IOFrame\Definitions::LOG_RESOURCES_CHANNEL,
                    \IOFrame\Definitions::LOG_ORDERS_CHANNEL,
                    \IOFrame\Definitions::LOG_PLUGINS_CHANNEL,
                    \IOFrame\Definitions::LOG_MAILING_CHANNEL,
                    \IOFrame\Definitions::LOG_CLI_TESTING_CHANNEL,
                    \IOFrame\Definitions::LOG_CLI_JOBS_CHANNEL
                ]
            );
            $logArgs["logs_sql_table_prefix"] = '';
            $logArgs["logs_sql_server_addr"] = '';
            $logArgs["logs_sql_server_port"] = '';
            $logArgs["logs_sql_username"] = '';
            $logArgs["logs_sql_password"] = '';
            $logArgs["logs_sql_db_name"] = '';
            $logArgs["logs_sql_persistent"] = '';
            $logArgs["defaultReportMailTemplate"] = 'default_log_report';
            $logArgs["defaultReportMailTitle"] = 'Default Logs Report';

            $metaArgs["localSettings"] = json_encode(['local'=>1,'db'=>0,'title'=>'Local Node Settings']);
            $metaArgs["redisSettings"] = json_encode(['local'=>1,'db'=>0,'title'=>'Redis Settings']);
            $metaArgs["sqlSettings"] = json_encode(['local'=>1,'db'=>0,'title'=>'SQL Connection Settings']);
            $metaArgs["mailSettings"] = json_encode(['local'=>0,'db'=>1,'title'=>'Mail Settings']);
            $metaArgs["pageSettings"] = json_encode(['local'=>0,'db'=>1,'title'=>'Page (redirection) Settings']);
            $metaArgs["resourceSettings"] = json_encode(['local'=>0,'db'=>1,'title'=>'Resource Settings']);
            $metaArgs["siteSettings"] = json_encode(['local'=>0,'db'=>1,'title'=>'Site (General) Settings']);
            $metaArgs["userSettings"] = json_encode(['local'=>0,'db'=>1,'title'=>'Users Settings']);
            $metaArgs["apiSettings"] = json_encode(['local'=>0,'db'=>1,'title'=>'API Settings']);
            $metaArgs["tagSettings"] = json_encode(['local'=>0,'db'=>1,'base64'=>1,'title'=>'Tag Settings']);
            $metaArgs["logSettings"] = json_encode(['local'=>0,'db'=>1,'title'=>'Logging Settings']);

            $targets = [
                'local'=>[
                    'handler'=>$localSettings,
                    'args'=>array_merge($localArgs,$params['localSettings'] ?? [])
                ],
                'site'=>[
                    'handler'=>$siteSettings,
                    'args'=>array_merge($siteArgs,$params['siteSettings'] ?? [])
                ],
                'user'=>[
                    'handler'=>$userSettings,
                    'args'=>array_merge($userArgs,$params['userSettings'] ?? [])
                ],
                'page'=>[
                    'handler'=>$pageSettings,
                    'args'=>array_merge($pageArgs,$params['pageSettings'] ?? [])
                ],
                'resource'=>[
                    'handler'=>$resourceSettings,
                    'args'=>array_merge($resourceArgs,$params['resourceSettings'] ?? [])
                ],
                'api'=>[
                    'handler'=>$apiSettings,
                    'args'=>array_merge($apiArgs,$params['apiSettings'] ?? [])
                ],
                'tag'=>[
                    'handler'=>$tagSettings,
                    'args'=>array_merge($tagArgs,$params['tagSettings'] ?? [])
                ],
                'log'=>[
                    'handler'=>$logSettings,
                    'args'=>array_merge($logArgs,$params['logSettings'] ?? [])
                ],
                'meta'=>[
                    'handler'=>$metaSettings,
                    'args'=>array_merge($metaArgs,$params['metaSettings'] ?? [])
                ]
            ];

            return self::initMultipleSettingHandlers($targets,$params);
        }

        /** Deletes some optional / unneeded files.
         * @param string $pathToRoot As in localSettings
         * @param array $params
         *        'metaFiles' => bool, default true - deletes license, copyright, install, and other readme files.
         *        'testPlugins' => bool, default true - deletes test plugins
         *        'testFiles' => bool, default true - deletes test files.
         * @returns array
         *       'metaFiles' => bool, whether meta files were deleted
         *       'testPlugins' => bool, whether test plugins were deleted
         *       'testFiles' => bool, whether test files were deleted
         * */
        public static function deleteDevFiles(string $pathToRoot, array $params = []): array {
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            $metaFiles = $params['metaFiles'] ?? true;
            $testPlugins = $params['testPlugins'] ?? true;
            $testFiles = $params['testFiles'] ?? true;

            $res = [
                'metaFiles'=>false,
                'testPlugins'=>false,
                'testFiles'=>false,
            ];

            if($metaFiles){
                if(
                    !$test &&
                    (
                        !@unlink($pathToRoot.'/LICENSE.md') ||
                        !@unlink($pathToRoot.'/INSTALL.md') ||
                        !@unlink($pathToRoot.'/README.md') ||
                        !@unlink($pathToRoot.'/COPYRIGHT.md')
                    )
                ){
                    if($verbose)
                        echo 'Failed to delete meta files, or they did not exist'.EOL;
                }
                else{
                    if($verbose)
                        echo 'Deleted meta files.'.EOL;
                    $res['metaFiles'] = true;
                }
            }

            if($testPlugins){
                try{
                    if(!$test){
                        \IOFrame\Util\FileSystemFunctions::folder_delete($pathToRoot.'/plugins/testPlugin');
                        \IOFrame\Util\FileSystemFunctions::folder_delete($pathToRoot.'/plugins/testPlugin2');
                    }
                    if($verbose)
                        echo 'Deleted test plugins.'.EOL;
                    $res['testPlugins'] = true;
                }
                catch (\Exception $e){
                    if($verbose)
                        echo 'Failed to delete test plugins, or they did not exist, exception '.$e->getMessage().EOL;
                }
            }

            if($testFiles){
                if(
                    !$test &&
                    (
                        !@unlink($pathToRoot.'/test.php') ||
                        !@unlink($pathToRoot.'/apiTest.php')
                    )
                ){
                    if($verbose)
                        echo 'Failed to delete test files, or they did not exist'.EOL;
                }
                else{

                    if($verbose)
                        echo 'Deleted test files'.EOL;
                    $res['metaFiles'] = true;
                }
            }

            return $res;
        }

        /** Syncs all provided settings with the DB.
         *  This assumes they were only local beforehand.
         * @param string[] $targets setting names (e.g 'user', 'page', etc)
         * @param array $params
         *         'defaultSettingsParams' => array, default [] - see parameter by the same name for most Handlers
         * @returns array
         *      <string, target name> => bool, whether the target was synced with db
         * */
        public static function syncSettingsToDB(array $targets, array $params = []){
            $defaultSettingsParams = $params['defaultSettingsParams'] ?? [];

            $results = [];
            $realTargets = [];
            foreach ( $targets as $target){
                $realTargets[$target] = [
                    'handler' => new \IOFrame\Handlers\SettingsHandler(
                        \IOFrame\Util\FrameworkUtilFunctions::getBaseUrl().'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$target.'Settings/',
                        $defaultSettingsParams
                    )
                ];
                $results[$target] = $realTargets[$target]['handler']->initDB($params);
                if(!$realTargets[$target])
                    return $results;
            }

            return $results;

        }

        /** Creates initial super-admin user.
         * @param array $inputs
         *           'u' => string, username, see validation,
         *           'p' => string, password, see validation,
         *           'm' => string, mail
         * @param \IOFrame\Handlers\UsersHandler $UsersHandler
         * @param array $params
         * @returns int See $UsersHandler->regUser
         * */
        public static function createSuperAdmin(array $inputs, \IOFrame\Handlers\UsersHandler $UsersHandler, array $params = []){
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;

            function checkInput($inputs,$verbose = false): bool {

                $res=true;

                if($inputs["u"]==null||$inputs["p"]==null||$inputs["m"]==null)
                    $res = false;
                else{
                    $u=$inputs["u"];
                    $p=$inputs["p"];
                    $m=$inputs["m"];
                    //Validate Username
                    if(!\IOFrame\Util\ValidatorFunctions::validateUsername($u)){
                        $res=false;
                        if($verbose)
                            echo 'Username illegal.'.EOL;
                    }
                    //Validate Password
                    else if(!\IOFrame\Util\ValidatorFunctions::validatePassword($p)){
                        $res=false;
                        if($verbose)
                            echo 'Password illegal.'.EOL;
                    }
                    //Validate Mail
                    else if(!filter_var($m, FILTER_VALIDATE_EMAIL)){
                        $res=false;
                        if($verbose)
                            echo 'Email illegal.'.EOL;
                    }
                }
                return $res;
            }

            if (!checkInput($inputs,$verbose))
                return false;
            $inputs['r'] = 0;
            return $UsersHandler->regUser($inputs,array_merge($params,['considerActive'=>true]));
        }

        /** Finalizes installation
         * @param \IOFrame\Handlers\SettingsHandler $localSettings
         * @param \IOFrame\Handlers\SettingsHandler $siteSettings
         * @returns bool
         * */
        public static function finalizeInstallation($absPathToRoot, \IOFrame\Handlers\SettingsHandler $siteSettings = null, array $params = []){
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            $local = $params['local'] ?? false;

            if(!$local){
                //Read the local version
                $version = \IOFrame\Util\FrameworkUtilFunctions::getLocalFrameworkVersion(['verbose'=>$verbose]);
                if(!$version)
                    return false;
                //Copy the version this node was installed with
                $siteSettings->setSetting('ver',$version,['createNew'=>true]);
                //The private key should stay inside the core db table, not in a setting file.
                $siteSettings->setSetting('privateKey',null,['createNew'=>true]);
            }


            if(!$test)
                \IOFrame\Util\ModificationFunctions::replaceInFile($absPathToRoot.'.htaccess','DirectoryIndex _install.php','DirectoryIndex index.php');
            if($verbose)
                echo '.htaccess - Replacing DirectoryIndex from "_install.php" to "index.php"'.EOL;


            //This means the installation was complete!
            if(!$test)
                touch($absPathToRoot.'localFiles/_installComplete');
            if($verbose)
                echo 'Installation complete!'.EOL;

            return true;
        }
    }
}