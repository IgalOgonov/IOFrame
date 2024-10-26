<?php

if(php_sapi_name() == "cli"){
    die('Please use the CLI installer, over at cli/install.php');
}
require_once 'vendor/autoload.php';
require 'main/definitions.php';

//--------------------Initialize Current DIR--------------------
$baseUrl = \IOFrame\Util\FrameworkUtilFunctions::getBaseUrl();

//--------------------Initialize local files--------------------
if(!\IOFrame\Util\Installation\UtilityFunctions::initiateLocalFiles(['verbose'=>true]))
    die('Failed to initiate local files');


$redisSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/redisSettings/');
$sqlSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/sqlSettings/');
$localSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/localSettings/');

//TODO Here was the cli include

$userSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/userSettings/');
$pageSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/pageSettings/');
$mailSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/mailSettings/');
$siteSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/siteSettings/');
$resourceSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/resourceSettings/');
$metaSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/metaSettings/');
$apiSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/apiSettings/');
$tagSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/tagSettings/',['base64Storage'=>true]);
$logSettings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/logSettings/');

echo '<head>
    <link rel="stylesheet" type="text/css" href="front/ioframe/css/install.css" media="all">
    <script src="front/ioframe/js/ext/jQuery_3_1_1/jquery.js"></script>
    <script src="front/ioframe/css/ext/bootstrap_3_3_7/js/bootstrap.js"></script>
    </head>';

//--------------------
if(\IOFrame\Util\Installation\UtilityFunctions::validateOrCreateInstallSession()){
    $installStage = $_REQUEST['stage'] ?? 0;
    install($userSettings,$pageSettings,$mailSettings,$localSettings,$siteSettings,$sqlSettings,$redisSettings,$resourceSettings,$metaSettings,$apiSettings,$tagSettings,$logSettings,$baseUrl,$installStage);
}
else
    die('Previous install seems to have been made from a different IP.'.EOL.
        'Please, go to the folder /siteFiles on your website, and delete file _installSes, they try again.'.EOL);

//TODO Rewrite settings as an object rather than this mess (one day)
function install(IOFrame\Handlers\SettingsHandler $userSettings,
                 IOFrame\Handlers\SettingsHandler $pageSettings,
                 IOFrame\Handlers\SettingsHandler $mailSettings,
                 IOFrame\Handlers\SettingsHandler $localSettings,
                 IOFrame\Handlers\SettingsHandler $siteSettings,
                 IOFrame\Handlers\SettingsHandler $sqlSettings,
                 IOFrame\Handlers\SettingsHandler $redisSettings,
                 IOFrame\Handlers\SettingsHandler $resourceSettings,
                 IOFrame\Handlers\SettingsHandler $metaSettings,
                 IOFrame\Handlers\SettingsHandler $apiSettings,
                 IOFrame\Handlers\SettingsHandler $tagSettings,
                 IOFrame\Handlers\SettingsHandler $logSettings,
                 $baseUrl,
                 $stage=0): void {
    //Echo the return button
    if($stage!=0)
        echo    '<form method="post" action="">
                <input type="text" name="stage" value="'.($stage-1).'" hidden>
                <input type="submit" value="Previous Stage">
                </form>';

    if($stage>=6){
        $defaultSettingsParams = [];
        $RedisManager = new \IOFrame\Managers\RedisManager($redisSettings);
        $defaultSettingsParams['RedisManager'] = $RedisManager;
        $defaultSettingsParams['siteSettings'] = $siteSettings;
        $defaultSettingsParams['resourceSettings'] = $resourceSettings;
        if($RedisManager->isInit){
            $defaultSettingsParams['useCache'] = true;
        }
        $SQLManager = new \IOFrame\Managers\SQLManager(
            $localSettings,
            $defaultSettingsParams
        );
        $defaultSettingsParams['SQLManager'] = $SQLManager;
        $defaultSettingsParams['opMode'] = \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_MIXED;
    }

    switch($stage){
        //-------------First installation stage
        default:

            $version = \IOFrame\Util\FrameworkUtilFunctions::getLocalFrameworkVersion(['verbose'=>true]);
            if(!$version)
                die();

            echo 'Welcome! Install stage 1:'.EOL
                .'<div id="notice"> Creating default settings...'.EOL.EOL;

            $res = \IOFrame\Util\Installation\UtilityFunctions::initiateDefaultSettings(
                $localSettings,
                $siteSettings,
                $userSettings,
                $pageSettings,
                $resourceSettings,
                $apiSettings,
                $tagSettings,
                $logSettings,
                $metaSettings,
                [
                    'verbose'=>true,
                    'localSettings'=>[
                        'pathToRoot' => substr($_SERVER['SCRIPT_NAME'], 0, strlen($_SERVER['SCRIPT_NAME'])- strlen('_install.php')),
                    ]
                ]
            );

            echo EOL;

            if(!$res)
                die('Failed to set default settings!</div>');

            echo 'All default settings set!</div>'.EOL;

            echo '<form method="post" action="">
                <span>Please choose the website name:</span><input type="text" name="siteName" value="My Website">'.EOL.'
                <input type="text" name="stage" value="1" hidden>
                <input type="submit" value="Next">
                </form>';

            break;
        //-------------2nd installation stage
        case 1:

            echo '<div id="notice">';

            $_REQUEST['siteName'] = $_REQUEST['siteName'] ?? 'My Website';
            if($siteSettings->setSetting('siteName',$_REQUEST['siteName'],['createNew'=>true]))
                echo 'Setting siteName set to '.$_REQUEST['siteName'].EOL;
            else{
                echo 'Failed to set setting siteName set to '.$_REQUEST['siteName'].EOL;
            }

            echo '</div>';

            echo 'Install stage 2:'.EOL.
                'Please choose your additional settings:'.EOL.EOL;

            $privKey = bin2hex(openssl_random_pseudo_bytes(32));

            //Settings to set..

            echo   '<form method="post" action="">
                    <span>Private key:</span>
                    <input type="text" name="privateKey" value="'.$privKey.'"><br>
                     <small>MUST BE 64 digits long, numbers or letters a-f, don\'t change it if you do not know what this is</small><br>
                     <small style="font-weight:700">It is PARAMOUNT you write this down in a secure place. If you do not, you risk losing ALL your encrypted data in the future.</small><br><br>
                     
                    <h4>Captcha:</h4>
                    <small style="font-weight:700">This is where you may fill out <a href="https://www.hcaptcha.com/">hcaptcha</a> credentials, if planning to use them</small><br>
                    <span>Secret key:</span>
                    <input type="text" name="captcha-secret"  placeholder="secret, used when querying /siteverify api" value=""><br>
                     <small style="font-weight:700">This is your SECRET, not SITE key</small><br>
                    <span>Site key:</span>
                    <input type="text" name="captcha-sitekey" placeholder="site key, generally passed to the client side" value=""><br>
                     <small style="font-weight:700">This is not your SECRET key</small><br><br>

                    <span>SSL Protection:</span>
                    <input type="checkbox" name="sslOn" value="1" checked><br>
                    <small>If this is checked, all pages on your site will be redirected to SSL by default.</small><br>
                     <small>Can be manually changed later</small><br>

                    <span>Sticky Cookie:</span>
                    <input type="checkbox" name="enableStickyCookie" value="0"><br>
                    <small>If checked, will pass a sticky session cookie to the client, based on the node ID</small><br>

                    <span>Sticky Cookie Duration:</span>
                    <input type="number" name="stickyCookieDuration" value="28800" placeholder="28800"><br>
                    <small>How long, in seconds, the sticky cookie should last for (default 8 hours)</small><br>

                    <span>Disable Secure Cookie Overwrite:</span>
                    <input type="checkbox" name="dontSecureCookies" value="0"><br>
                    <small>If this is checked, will DISABLE automatic PHP cookie setting overwrites, which would make cookies secure by default</small><br>
                     <small>Can be disabled if cookie settings were set properly in the PHP ini from setup</small><br>

                    <span>Remember Login:</span>
                    <input type="checkbox" name="rememberMe" value="1" checked><br>
                    <small>If this is checked, users will be able to use the "Remember Me" feature,</small><br>

                    <span>Remember Login Time Limit:</span>
                    <input type="number" name="rememberMeLimit" value="31536000"><br>
                    <small>If Remember Login is active, this is the time limit (in seconds, default 365 days) for it to expire</small><br>

                    <span>Save Relog Info Using Cookies:</span>
                    <input type="checkbox" name="relogWithCookies" value="1" checked><br>
                    <small>If this is checked, and "Remember Me" is checked, will automatically try to relog using cookies instead of local storage</small><br>

                    <span>Remember login for (seconds):</span>
                    <input type="number" name="userTokenExpiresIn" value="0" placeholder="0"><br>
                    <small>Number of <b>seconds</b> tokens generated for auto-relog are valid for.</small><br>
                    <small> If 0, tokens never expire. While login remembering is not allowed, this has no effect.</small><br>

                    <span>Password Reset Validity:</span>
                    <input type="number" name="passwordResetTime" value="5" placeholder="5"><br>
                    <small>For how many minutes, after a user successfully clicked the mail link, he can reset the password.</small><br>

                    <span>Self Registration:</span>
                    <input type="checkbox" name="selfReg" value="1" checked><br>
                    <small>If this is checked, allows everyone to register new accounts.</small><br>
                    <small>If unchecked, users can only be invited, or created by an admin.</small><br>

                    <span>Username</span> <br>
                    <input type="radio" name="usernameChoice" value="0" checked>Force explicit username <br>
                    <input type="radio" name="usernameChoice" value="1">Allow random username <br>
                    <input type="radio" name="usernameChoice" value="2">Force random username <br>
                    <small>Focuses on the default user API. Forces the user to explicitly choose a username, OR allows
                            to leave it blank, OR forces it to be blank (both latter choices spawn a random username).</small><br>
                            
                    <span>Allow Testing (APIs, test.php):</span>
                    <input type="checkbox" name="allowTesting" value="0"><br>
                    <small>If this is checked, testing will be allowed with no restrictions. DONT CHECK IN PRODUCTION (live sites)!</small><br>
                    
                    <span>Delete Meta Files:</span>
                    <input type="checkbox" name="deleteMetaFiles" value="1" checked><br>
                    <small>If this is checked, deletes files like INSTALL.md, LICENSE.md, and others, from the root directory</small><br>
                    
                    <span>Delete Test Plugins:</span>
                    <input type="checkbox" name="deleteTestPlugins" value="1" checked><br>
                    <small>If this is checked, deletes the test plugins</small><br>
                    
                    <span>Delete Tests:</span>
                    <input type="checkbox" name="deleteTestFiles" value="1" checked><br>
                    <small>If this is checked, will delete test.php and apiTest.php from the root folder<br>
                     (even though they are harmless even in production without manually turning on allowTesting / devMode)</small><br>
                            
                    <span>Allow Getting Restricted Articles by Address:</span>
                    <input type="checkbox" name="restrictedArticleByAddress" value="1" checked><br>
                    <small>If this is checked, users will be able to get articles with "restricted" auth with direct links (making them hidden for those without the link, in effect)</small><br>

                    <span>Registration Mail Confirmation:</span>
                    <input type="checkbox" name="regConfirmMail" value="1" checked><br>
                    <small>If this is checked, user will have to confirm his mail upon registration for his account to become active.</small><br>

                    <span>Password Reset Link Expiry (Hours):</span>
                    <input type="number" name="pwdResetExpires" value="72" placeholder="72"><br>
                    <small>How long until password reset email sent to a client expires, in hours.</small><br>

                    <span>Email Confirmation Link Expiry (Hours):</span>
                    <input type="number" name="mailConfirmExpires" value="72" placeholder="72"><br>
                    <small>How long until registration confirmation email sent to a client expires, in hours.</small><br>

                    <span>Expected Proxy:</span>
                    <input type="text" name="expectedProxy" value="" placeholder="1.2.3.4"><br>
                    <small>Keep empty if you dont know what this is.</small><br>
                    <small>If you are going to use a reverse proxy, put the expected HTTP_X_FORWARDED_FOR [+ REMOTE_ADDR] prefix here. </small><br>
                    <small>For example, if your load balancer IP is 10.10.11.11, and it itself is behind a proxy with IP 210.20.1.10,</small><br>
                    <small>this should be "210.20.1.10,10.10.11.11". If the balancer is the only proxy, "10.10.11.11". Otherwise, leave empty.</small><br>

                    <input type="text" name="stage" value="2" hidden>
                    <input type="submit" value="Next">
                    </form>';
            break;
        //-------------3rd installation stage
        case 2:
            echo '<div id="notice">';

            $onlyNotNull = function ($v){
                return $v !== null;
            };
            $targets = [
                'local'=>[
                    'handler'=>$localSettings,
                    'args'=>array_filter(
                        [
                            'expectedProxy'=>$_REQUEST['expectedProxy']??null,
                        ],
                        $onlyNotNull
                    )
                ],
                'site'=>[
                    'handler'=>$siteSettings,
                    'args'=>array_filter(
                        [
                        'privateKey'=>$_REQUEST['privateKey']??null,
                        'captcha_secret_key'=>$_REQUEST['captcha-secret']??null,
                        'captcha_site_key'=>$_REQUEST['captcha-sitekey']??null,
                        'allowTesting'=>$_REQUEST['allowTesting']??null,
                        'sslOn'=>$_REQUEST['sslOn']??null,
                        'dontSecureCookies'=>$_REQUEST['dontSecureCookies']??null,
                        'enableStickyCookie'=>$_REQUEST['enableStickyCookie']??null,
                        'stickyCookieDuration'=>$_REQUEST['stickyCookieDuration']??null
                        ],
                        $onlyNotNull
                    )
                ],
                'user'=>[
                    'handler'=>$userSettings,
                    'args'=>array_filter(
                        [
                            'rememberMe'=>$_REQUEST['rememberMe']??null,
                            'rememberMeLimit'=>$_REQUEST['rememberMeLimit']??null,
                            'relogWithCookies'=>$_REQUEST['relogWithCookies']??null,
                            'userTokenExpiresIn'=>$_REQUEST['userTokenExpiresIn']??null,
                            'selfReg'=>$_REQUEST['selfReg']??null,
                            'usernameChoice'=>$_REQUEST['usernameChoice']??null,
                            'passwordResetTime'=>$_REQUEST['passwordResetTime']??null,
                            'regConfirmMail'=>$_REQUEST['regConfirmMail']??null,
                            'pwdResetExpires'=>$_REQUEST['pwdResetExpires']??null,
                            'mailConfirmExpires'=>$_REQUEST['mailConfirmExpires']??null,
                        ],
                        $onlyNotNull
                    )
                ],
                'api'=>[
                    'handler'=>$apiSettings,
                    'args'=>array_filter(
                        [
                            'restrictedArticleByAddress'=>$_REQUEST['restrictedArticleByAddress']??null,
                        ],
                        $onlyNotNull
                    )
                ]
            ];
            $res = \IOFrame\Util\Installation\UtilityFunctions::initMultipleSettingHandlers($targets,['verbose'=>true,'initIfNotExists'=>false]);
            if($res !== true)
                die('Failed to set '.$res.'settings!</div>');

            \IOFrame\Util\Installation\UtilityFunctions::deleteDevFiles(
                $localSettings->getSetting('absPathToRoot'),
                [
                    'metaFiles'=>isset($_REQUEST['deleteMetaFiles']),
                    'testPlugins'=>isset($_REQUEST['deleteTestPlugins']),
                    'testFiles'=>isset($_REQUEST['deleteTestFiles']),
                    'verbose'=>true
                ]
            );

            echo '</div>';
            echo 'Install stage 3:'.EOL.
                'Please input the Redis credentials and settings - may be skipped if you don\'t have any, by leaving the address blank:'.EOL;


            echo    '<form method="post" action="">
                    <input type="text" name="stage" value="3" hidden><br>
                    <span>Redis IP address <small><b>Leave blank to skip this part!</b>.</small>:</span><input type="text" name="redis_addr" placeholder="E.g 127.0.0.1"><br>
                    <span>Redis Port:</span><input type="number" name="redis_port" value=6379><br>
                    <span>Redis Password:</span><input type="text" name="redis_password" placeholder="Optional Password"><br>
                    <span>Redis Prefix:</span><input type="text" name="redis_prefix" placeholder="Optional Global Cache Prefix"><br>
                    <span>Redis Timeout:</span><input type="number" name="redis_timeout"><br>
                    <small>How many seconds the server will try to connect to Redis before timeout.</small><br>
                    <span>Redis Persistent Connection:</span> <input type="checkbox" name="redis_default_persistent" checked><br>
                    <small>If this is checked, the PHP server will keep a persistent connection to the Redis server when connecting.</small><br>
                    <input type="submit" value="Next">
                    </form>';
            break;
        //-------------4rd installation stage
        case 3:
            echo '<div id="notice">';

            if(isset($_REQUEST['redis_addr']) && ($_REQUEST['redis_addr']!= '') ){
                $onlyNotNullOrEmpty = function ($v){
                    return ($v !== null) && ($v !== '');
                };
                if(
                    ( !empty($_REQUEST['redis_timeout']) || ($_REQUEST['redis_timeout'] === 0) ) &&
                    ($_REQUEST['redis_timeout'] < 1)
                )
                    $_REQUEST['redis_timeout'] = 1;
                $targets = [
                    'redis'=>[
                        'handler'=>$redisSettings,
                        'args'=>array_merge(
                            array_filter(
                                [
                                    'redis_addr'=>$_REQUEST['redis_addr'],
                                    'redis_port'=>$_REQUEST['redis_port']??null,
                                    'redis_prefix'=>$_REQUEST['redis_prefix']??null,
                                    'redis_password'=>$_REQUEST['redis_password']??null,
                                    'redis_timeout'=>$_REQUEST['redis_timeout']??null,
                                    'redis_default_persistent'=>$_REQUEST['redis_default_persistent']??null,
                                ],
                                $onlyNotNullOrEmpty
                            ),
                            [
                                'redis_serializer'=>'',
                                'redis_scan_retry'=>''
                            ]
                        )
                    ],
                ];
                $res = \IOFrame\Util\Installation\UtilityFunctions::initMultipleSettingHandlers($targets,['verbose'=>true,'initIfNotExists'=>true]);
                if($res !== true)
                    die('Failed to set '.$res.'settings!</div>');
            }

            echo '</div>';
            echo 'Install stage 4:'.EOL.
                'Please input the SQL credentials (user must have ALL privileges):'.EOL;

            echo    '<form method="post" action="">
                    <input type="text" name="stage" value="4" hidden><br>
                    <input type="text" name="sql_table_prefix" placeholder="[Optional] Default Table Prefix (Max 6 Characters)"><br>
                    <input type="text" name="sql_server_addr" placeholder="Default SQL server address"><br>
                    <input type="text" name="sql_server_port" placeholder="[Optional] Default SQL server port"><br>
                    <input type="text" name="sql_username" placeholder="Default SQL server username"><br>
                    <input type="text" name="sql_password" placeholder="Default SQL server password"><br>
                    <input type="text" value="1" name="sql_persistent" placeholder="Default SQL persistent connection"><br>
                    <input type="text" name="sql_db_name" placeholder="Default SQL server database name"><br>
                    <br>
                    <input type="text" name="logs_sql_table_prefix" placeholder="[Optional] Logs Table Prefix (Max 6 Characters)"><br>
                    <input type="text" name="logs_sql_server_addr" placeholder="[Optional] Logs SQL server address"><br>
                    <input type="text" name="logs_sql_server_port" placeholder="[Optional] Logs SQL server port"><br>
                    <input type="text" name="logs_sql_username" placeholder="[Optional] Logs SQL server username"><br>
                    <input type="text" name="logs_sql_password" placeholder="[Optional] Logs SQL server password"><br>
                    <input type="text" value="1" name="logs_sql_persistent" placeholder="[Optional] Logs SQL persistent connection"><br>
                    <input type="text" name="logs_sql_db_name" placeholder="[Optional] Logs SQL server database name"><br>
                    <input type="submit" value="Next">
                    </form>';
            break;
        //-------------5th installation stage
        case 4:
            echo 'Install stage 5:'.EOL;
            echo '<div id="notice">';


            if( empty($_REQUEST['sql_server_addr']) || empty($_REQUEST['sql_username']) || empty($_REQUEST['sql_password']) || empty($_REQUEST['sql_db_name']) ){
                die('Incorrect input! Please try again</div>');
            }
            //Enforce table prefix to be 6 characters max
            if(strlen($_REQUEST['sql_table_prefix'])>6)
                $_REQUEST['sql_table_prefix'] = substr($_REQUEST['sql_table_prefix'],0,6);

            $targets = [
                'sql'=>[
                    'handler'=>$sqlSettings,
                    'args'=>array_merge(
                        [
                            'sql_table_prefix'=>$_REQUEST['sql_table_prefix'],
                            'sql_server_addr'=>$_REQUEST['sql_server_addr'],
                            'sql_server_port'=>$_REQUEST['sql_server_port'],
                            'sql_username'=>$_REQUEST['sql_username'],
                            'sql_password'=>$_REQUEST['sql_password'],
                            'sql_persistent'=>$_REQUEST['sql_persistent'],
                            'sql_db_name'=>$_REQUEST['sql_db_name'],
                        ],
                    )
                ],
            ];
            $res = \IOFrame\Util\Installation\UtilityFunctions::initMultipleSettingHandlers($targets,['verbose'=>true,'initIfNotExists'=>true]);

            if($res === true){
                try{
                    \IOFrame\Util\FrameworkUtilFunctions::prepareCon($sqlSettings);
                    echo 'DB Connection Established Successfully';
                }
                catch(\Exception $e){
                    die('Failed to connect to DB! Error: '.$e->getMessage().'</div>');
                }

                if(
                    !empty($_REQUEST['logs_sql_table_prefix']) ||
                    !empty($_REQUEST['logs_sql_server_addr']) ||
                    !empty($_REQUEST['logs_sql_server_port']) ||
                    !empty($_REQUEST['logs_sql_username']) ||
                    !empty($_REQUEST['logs_sql_password']) ||
                    !empty($_REQUEST['logs_sql_persistent']) ||
                    !empty($_REQUEST['logs_sql_db_name'])
                ){
                    $scaleLogs = true;
                    //Enforce table prefix to be 6 characters max
                    if(strlen($_REQUEST['logs_sql_table_prefix'])>6)
                        $_REQUEST['logs_sql_table_prefix'] = substr($_REQUEST['logs_sql_table_prefix'],0,6);

                    $targets = [
                        'logs'=>[
                            'handler'=>$logSettings,
                            'args'=>array_merge(
                                [
                                    'logs_sql_table_prefix'=>$_REQUEST['logs_sql_table_prefix'],
                                    'logs_sql_server_addr'=>$_REQUEST['logs_sql_server_addr'],
                                    'logs_sql_server_port'=>$_REQUEST['logs_sql_server_port'],
                                    'logs_sql_username'=>$_REQUEST['logs_sql_username'],
                                    'logs_sql_password'=>$_REQUEST['logs_sql_password'],
                                    'logs_sql_persistent'=>$_REQUEST['logs_sql_persistent'],
                                    'logs_sql_db_name'=>$_REQUEST['logs_sql_db_name'],
                                ],
                            )
                        ],
                    ];
                    $res = \IOFrame\Util\Installation\UtilityFunctions::initMultipleSettingHandlers($targets,['verbose'=>true,'initIfNotExists'=>false]);
                    if($res !== true)
                        die(EOL.'Failed to set log '.$res.' settings</div>');

                }
                echo '</div>';
                echo '<form method="post" action="">
                    <input type="text" name="stage" value="5" hidden>
                    <input type="text" name="scale-logs" value="'.(!empty($scaleLogs)?'1':'0').'" hidden><br>
                    <input type="submit" value="Next">
                    </form>';
            }
            else{
                die(EOL.'Failed to set '.$res.' Settings</div>') ;
            }

            break;
        //-------------6th installation stage
        case 5:
            echo 'Install stage 6:<div id="notice"> ';
            $initSettings = ['verbose'=>true,'populate'=>false,'tables'=>[]];
            $scaleLogs = (bool)($_REQUEST['scale-logs'] ?? '0');
            if($scaleLogs){
                $initSettings['tables']['logging'] = [
                    'highScalability'=>true
                ];
            }
            $initStructure = \IOFrame\Util\Installation\DBInitiationFunctions::initDB($localSettings,$initSettings);
            if($initStructure === true) {
                echo EOL.'Database initiated! </div>' . EOL;
            }
            else{
                echo EOL.'Database NOT initiated properly, error in '.$initStructure.EOL.
                    'You might continue, but only if the reason for the error was tables already existing from a previous app.</div>'.EOL;
            }

            echo 'If the database is properly initiated, click next for the default values of multiple modules to be initiated.<br>
                 If not, clicking next might force you to reinstall, or the installation will be corrupted - go back and retry the DB initiation.'.EOL;

            echo '<form method="post" action="">
                    <input type="text" name="stage" value="6" hidden>
                    <input type="submit" value="Next">
                     </form>';
            break;
        //-------------7th installation stage
        case 6:

            echo 'Install stage 7:<div id="notice"> ';
            $populateDB = \IOFrame\Util\Installation\DBInitiationFunctions::initDB($localSettings,['verbose'=>true,'defaultSettingsParams'=>$defaultSettingsParams,'init'=>false]);
            if($populateDB === true){
                echo EOL.'Database default values populated! </div>' . EOL;
            }
            else{
                die('Failed to populate some of the default DB values, error in '.$populateDB.'</div>');
            }

            echo 'Please input the mail account settings (Optional but highly recommended!). <br>
                          <small>If you are using cPanel,go into Mail Accounts, create a new account, click "Set Up Mail Client"<br>
                          under Actions of that account, and copy the relevant info.</small>'.EOL;

            echo '<form method="post" action="">
                    <input type="text" name="stage" value="7" hidden>
                    <span>Host Name:</span> <input type="text" name="mailHost" placeholder="yourHostName.com"><br>
                    <span>Encryption (default recommended):</span> <input type="text" name="mailEncryption" value="ssl"><br>
                    <span>Mail Username:</span> <input type="text" name="mailUsername" placeholder="username@yourHostName.com"><br>
                    <span>Mail Password:</span>  <input type="text" name="mailPassword" placeholder="The password for the above user"><br>
                    <span>Mail Server Port:</span> <input type="text" name="mailPort" placeholder="465 - might be different, see host settings"><br>
                    <span>System Alias*:</span> <input type="text" name="defaultAlias" placeholder="customAlias@yourHostName.com"><br>
                    <small>Fill this in if you want to be sending system mails as a different alias (not your username).
                           In most services, you\'ll need to set allowed aliases manually, and at worst using an non-existent one will cause your emails to not be sent.</small><br>;
                     <input type="submit" value="Next">
                     </form>';

            break;
        //-------------8th installation stage
        case 7:
            echo 'Install stage 8:'.EOL;
            echo '<div id="notice">Setting mail...'.EOL;

            $targets = [
                'mail'=>[
                    'handler'=>$mailSettings,
                    'args'=>array_merge(
                        [
                            'mailHost'=>$_REQUEST['mailHost']??'',
                            'mailEncryption'=>$_REQUEST['mailEncryption']??'',
                            'mailUsername'=>$_REQUEST['mailUsername']??'',
                            'mailPassword'=>$_REQUEST['mailPassword']??'',
                            'mailPort'=>$_REQUEST['mailPort']??'',
                            'defaultAlias'=>$_REQUEST['defaultAlias']??'',
                        ],
                    )
                ],
            ];
            $res = \IOFrame\Util\Installation\UtilityFunctions::initMultipleSettingHandlers($targets,['verbose'=>true]);

            if($res !== true)
                die(EOL.'Failed to set log '.$res.' settings</div>');

            //Initiate all settings handlers that need to by synced
            $dbSync = \IOFrame\Util\Installation\UtilityFunctions::syncSettingsToDB(
                ['user','page','mail','site','resource','api','tag','log','meta'],
                ['defaultSettingsParams'=>$defaultSettingsParams,'verbose'=>false]
            );

            if(in_array(false,$dbSync)){
                foreach ($dbSync as $name => $result)
                    if(!$result)
                        echo 'Failed to sync '.$name.' settings to DB!'.EOL;
                die('</div>');
            }
            else{
                echo 'All settings synced to database!'.EOL;
                echo '</div>';
            }
            echo 'Create the admin account. This will be a one-of a kind account with the highest rank, so remember the info!'.EOL;

            echo    '<form method="post" action="">
                        <input type="text" name="stage" value="8" hidden><br>
                        <input type="text" name="u" placeholder="Your username">
                        <a href="#" data-html="true" data-placement="bottom" data-toggle="tooltip-u"
                         title="Must be 3-63 characters long, must contain letters and may contain numbers">?</a><br>
                        <input type="text" name="p" placeholder="Your password (not hidden here)">
                        <a href="#" data-html="true" data-placement="bottom" data-toggle="tooltip-p"
                           title="Must be 8-64 characters long, must include letters and numbers, can include special characters except \'>\' and \'<\'">?</a><br>
                        <input type="text" name="m" placeholder="Your mail"><br>
                        <input type="submit" value="Next">
                        </form>';

            break;
        //-------------9th installation stage
        case 8:
            echo 'Install stage 9:'.EOL;

            echo '<div id="notice">';
            $UsersHandler = new \IOFrame\Handlers\UsersHandler($localSettings);
            $createSuperAdmin = \IOFrame\Util\Installation\UtilityFunctions::createSuperAdmin(['u'=>$_REQUEST['u'],'p'=>$_REQUEST['p'],'m'=>$_REQUEST['m']], $UsersHandler,['verbose'=>true]);

            if($createSuperAdmin !== 0)
                die('Failed to create Super Admin, error '.$createSuperAdmin.'</div>');
            else
                echo 'Super Admin created'.EOL;

            $installationFinished = \IOFrame\Util\Installation\UtilityFunctions::finalizeInstallation($localSettings->getSetting('absPathToRoot'),$siteSettings,['verbose'=>true]);

            if($installationFinished)
                echo '</div>';
            else
                die('</div>');

            echo '<form method="get" action="cp/login">
                         <input type="submit" value="Go to admin panel">
                         </form>';

            break;
    }

}