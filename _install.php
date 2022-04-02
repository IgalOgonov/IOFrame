<?php

require 'main/definitions.php';
require_once __DIR__.'/vendor/autoload.php';
if(!defined('SettingsHandler'))
    require 'IOFrame/Handlers/SettingsHandler.php';
if(!defined('helperFunctions'))
    require 'IOFrame/Util/helperFunctions.php';
if(!defined('abstractDB'))
    require 'IOFrame/Handlers/abstractDB.php';
if(!defined('UserHandler'))
    require 'IOFrame/Handlers/UserHandler.php';
if(!defined('PluginHandler'))
    require 'IOFrame/Handlers/PluginHandler.php';

//--------------------Initialize Current DIR--------------------
$baseUrl = IOFrame\Util\replaceInString('\\','/',__DIR__).'/';

//--------------------Initialize EOL --------------------
if(!defined("EOL"))
    if (php_sapi_name() == "cli") {
        define("EOL",PHP_EOL);
    } else {
        define("EOL",'<br>');;
    }

//--------------------Initialize local files folder if it does not exist--------------------
if(!is_dir('localFiles')){
    if(!mkdir('localFiles'))
        die('Cannot create files directory for some reason - most likely insufficient user privileges, or it already exists');
}
//--------------------If the installation was complete, exit --------------------
if(file_exists('localFiles/_installComplete'))
    die('It seems the site is already installed! If this is an error, go to the /siteFiles folder and delete _installComplete.');

//--------------------Initialize temp files folder if it does not exist--------------------
if(!is_dir('localFiles/temp')){
    if(!mkdir('localFiles/temp'))
        die('Cannot create temp directory for some reason - most likely insufficient user privileges, or it already exists');
}
if(!is_dir('localFiles/logs')){
    if(!mkdir('localFiles/logs'))
        die('Cannot create logs directory for some reason - most likely insufficient user privileges, or it already exists');
}
//-------------------- Throw in an htaccess (from a place it already exists) to deny access to local files --------------------
if(!file_exists('localFiles/.htaccess'))
    file_put_contents(
        'localFiles/.htaccess',
        file_get_contents('plugins/.htaccess')
    );


//--------------------Create the definitions json file --------------------
if(!is_dir('localFiles/definitions')){
    if(!mkdir('localFiles/definitions'))
        die('Cannot create definitions directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/definitions/definitions.json','w'));
}


//--------------------Initialize plugin "settings" folders--------------
if(!is_dir('localFiles/plugins')){
    if(!mkdir('localFiles/plugins'))
        die('Cannot create plugins directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/plugins/settings','w'));
}


//--------------------Create empty plugin order--------------------
if(!is_dir('localFiles/plugin_order')){
    if(!mkdir('localFiles/plugin_order'))
        die('Cannot create plugin order directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/plugin_order/order','w'));
}

//--------------------Create empty plugin dependency map--------------------
if(!is_dir('localFiles/pluginDependencyMap')){
    if(!mkdir('localFiles/pluginDependencyMap'))
        die('Cannot create plugin dependency map for some reason - most likely insufficient user privileges, or it already exists');
}

//--------------------Initialize local setting folders--------------------
if(!is_dir('localFiles/localSettings')){
    if(!mkdir('localFiles/localSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/localSettings/settings','w'));
}

if(!is_dir('localFiles/sqlSettings')){
    if(!mkdir('localFiles/sqlSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/sqlSettings/settings','w'));
}

if(!is_dir('localFiles/redisSettings')){
    if(!mkdir('localFiles/redisSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/redisSettings/settings','w'));
}

$redisSettings = new IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/redisSettings/',['useCache'=>false]);
$sqlSettings = new IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/sqlSettings/');
$localSettings = new IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/localSettings/');

//--------------------From this point on, if we are in CLI mode, the installation is different--------------------
if(php_sapi_name() == "cli"){
    define('INSTALL_CLI',true);
    require 'cli_install.php';
    die();
}

//Only require this if it is not a local install
require_once 'procedures/SQLdbInit.php';

echo '<head>
    <link rel="stylesheet" type="text/css" href="front/ioframe/css/install.css" media="all">
    <script src="front/ioframe/js/ext/jQuery_3_1_1/jquery.js"></script>
    <script src="front/ioframe/css/ext/bootstrap_3_3_7/js/bootstrap.js"></script>
    </head>';
//--------------------Initialize settings handler--------------------
if(!is_dir('localFiles/userSettings')){
    if(!mkdir('localFiles/userSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/userSettings/settings','w'));
}

if(!is_dir('localFiles/pageSettings')){
    if(!mkdir('localFiles/pageSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/pageSettings/settings','w'));
}

if(!is_dir('localFiles/siteSettings')){
    if(!mkdir('localFiles/siteSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/siteSettings/settings','w'));
}

if(!is_dir('localFiles/mailSettings')){
    if(!mkdir('localFiles/mailSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/mailSettings/settings','w'));
}

if(!is_dir('localFiles/resourceSettings')){
    if(!mkdir('localFiles/resourceSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/resourceSettings/settings','w'));
}

if(!is_dir('localFiles/metaSettings')){
    if(!mkdir('localFiles/metaSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/metaSettings/settings','w'));
}

if(!is_dir('localFiles/apiSettings')){
    if(!mkdir('localFiles/apiSettings'))
        die('Cannot create settings directory for some reason - most likely insufficient user privileges, or it already exists');
    fclose(fopen('localFiles/apiSettings/settings','w'));
}

$userSettings = new IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/userSettings/');
$pageSettings = new IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/pageSettings/');
$mailSettings = new IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/mailSettings/');
$siteSettings = new IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/siteSettings/');
$resourceSettings = new IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/resourceSettings/');
$metaSettings = new IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/metaSettings/');
$apiSettings = new IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/apiSettings/');

//--------------------
if(!file_exists('localFiles/_installSes') && isset($_SERVER['REMOTE_ADDR'])){
    $myFile = fopen('localFiles/_installSes', 'w+');
    fwrite($myFile,$_SERVER['REMOTE_ADDR']);
    install($userSettings,$pageSettings,$mailSettings,$localSettings,$siteSettings,$sqlSettings,$redisSettings,$resourceSettings,$metaSettings,$apiSettings,0,$baseUrl);
}
else{
    $myFile = fopen('localFiles/_installSes', 'r+');
    $temp = fread($myFile,100);

    if($temp!=$_SERVER['REMOTE_ADDR']){
        echo 'Previous install seems to have been made from a different IP.'.EOL.
            'Please, go to the folder /siteFiles on your website, and delete file _installSes, they try again.'.EOL;
        die();
    }
    else{
        $installStage = 0;
        if(isset($_REQUEST['stage']))
            $installStage = $_REQUEST['stage'];
        install($userSettings,$pageSettings,$mailSettings,$localSettings,$siteSettings,$sqlSettings,$redisSettings,$resourceSettings,$metaSettings,$apiSettings,$installStage,$baseUrl);
    }
}

function install(IOFrame\Handlers\SettingsHandler $userSettings,
                 IOFrame\Handlers\SettingsHandler $pageSettings, IOFrame\Handlers\SettingsHandler $mailSettings,
                 IOFrame\Handlers\SettingsHandler $localSettings, IOFrame\Handlers\SettingsHandler $siteSettings,
                 IOFrame\Handlers\SettingsHandler $sqlSettings, IOFrame\Handlers\SettingsHandler $redisSettings,
                 IOFrame\Handlers\SettingsHandler $resourceSettings,
                 IOFrame\Handlers\SettingsHandler $metaSettings,
                 IOFrame\Handlers\SettingsHandler $apiSettings,
                 $stage='0',$baseUrl){
    //Echo the return button
    if($stage!=0)
        echo    '<form method="post" action="">
                <input type="text" name="stage" value="'.($stage-1).'" hidden>
                <input type="submit" value="Previous Stage">
                </form>';

    if($stage>=6){
        $defaultSettingsParams = [];
        $RedisHandler = new IOFrame\Handlers\RedisHandler($redisSettings);
        $defaultSettingsParams['RedisHandler'] = $RedisHandler;
        $defaultSettingsParams['siteSettings'] = $siteSettings;
        $defaultSettingsParams['resourceSettings'] = $resourceSettings;
        if($RedisHandler->isInit){
            $defaultSettingsParams['useCache'] = true;
        }
        $SQLHandler = new IOFrame\Handlers\SQLHandler(
            $localSettings,
            $defaultSettingsParams
        );
        $defaultSettingsParams['SQLHandler'] = $SQLHandler;
        $defaultSettingsParams['opMode'] = 'mixed';
    }

    switch($stage){
        //-------------2nd installation stage
        case 1:
            $bytes = openssl_random_pseudo_bytes(32, $cstrong);
            $privKey = bin2hex($bytes);
            echo '<div id="notice">';
            if(isset($_REQUEST['siteName'])){
                if($siteSettings->setSetting('siteName',$_REQUEST['siteName'],['createNew'=>true]))
                    echo 'Setting siteName set to '.$_REQUEST['siteName'].EOL;
                else{
                    echo 'Failed to set setting siteName set to '.$_REQUEST['siteName'].EOL;
                }
            }
            else{
                if($siteSettings->setSetting('siteName','My Website',['createNew'=>true]))
                    echo 'Setting siteName set to My Website'.EOL;
                else{
                    echo 'Failed to set setting siteName set to My Website'.EOL;
                }
            }

            echo '</div>';
            echo 'Install stage 2:'.EOL.
                'Please choose your additional settings:'.EOL.EOL;
            //Settings to set..

            echo   '<form method="post" action="">
                    <span>Private key:</span>
                    <input type="text" name="privateKey" value="'.$privKey.'"><br>
                     <small>MUST BE 64 digits long, numbers or latters a-f, don\'t change it if you do not know what this is</small><br>
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
                    <small>If this is checked, all pages on yout site will be redirected to SSL by default.</small><br>
                     <small>Can be manually changed later</small><br>

                    <span>Remember Login:</span>
                    <input type="checkbox" name="rememberMe" value="1" checked><br>
                    <small>If this is checked, the framework will allow you to use the "Remember Me" feature,</small><br>

                    <span>Save Relog Info Using Cookies:</span>
                    <input type="checkbox" name="relogWithCookies" value="1" checked><br>
                    <small>If this is checked, and "Remember Me" is checked, will automatically try to relog using cookies instead of local storage</small><br>

                    <span>Remember login for (seconds):</span>
                    <input type="number" name="userTokenExpiresIn" value="0" checked><br>
                    <small>Number of <b>seconds</b> tokens generated for auto-relog are valid for.</small><br>
                    <small> If 0, tokens never expire. While login remembering is not allowed, this has no effect.</small><br>

                    <span>Password Reset Validity:</span>
                    <input type="number" name="passwordResetTime" value="5" checked><br>
                    <small>For how many minutes after a user successfully clicked the mail link he can reset the password.</small><br>

                    <span>Self Registration:</span>
                    <input type="checkbox" name="selfReg" value="1" checked><br>
                    <small>If this is checked, allows everyone to register new accounts.</small><br>

                    <span>Username</span> <br>
                    <input type="radio" name="usernameChoice" value="0" checked>Force explicit username <br>
                    <input type="radio" name="usernameChoice" value="1">Allow random username <br>
                    <input type="radio" name="usernameChoice" value="2">Force random username <br>
                    <small>Focuses on the default user API. Forces the user to explicitly choose a username, OR allows
                            to leave it blank, OR forces it to be blank (both latter choices spawn a random username).</small><br>
                            
                    <span>Allow Testing APIs:</span>
                    <input type="checkbox" name="allowTesting" value="0" checked><br>
                    <small>If this is checked, API testing will be allowed with no restrictions. DONT CHECK IN PRODUCTION (live sites)!!!</small><br>
                            
                    <span>Allow Getting Restricted Articles by Address:</span>
                    <input type="checkbox" name="allowTesting" value="0" checked><br>
                    <small>If this is checked, users will be able to get articles with "restricted" auth with direct links (making them hidden for those without the link, in effect)</small><br>

                    <span>Registration Mail Confirmation:</span>
                    <input type="checkbox" name="regConfirmMail" value="1" checked><br>
                    <small>If this is checked, user will have to confirm his mail upon registration for his account to become active.</small><br>

                    <span>Password Reset Link Expiry (Hours):</span>
                    <input type="number" name="pwdResetExpires" value="72" checked><br>
                    <small>How long until password reset link sent to a client expires, in hours.</small><br>

                    <span>Email Confirmation Link Expiry (Hours):</span>
                    <input type="number" name="mailConfirmExpires" value="72" checked><br>
                    <small>How long until registration confirmation email sent to a client expires, in hours.</small><br>

                    <span>Expected Proxy:</span>
                    <input type="text" name="expectedProxy" value=""><br>
                    <small>Keep empty if you dont know what this is.</small><br>
                    <small>If you are going to use a reverse proxy, put the expected HTTP_X_FORWARDED_FOR + REMOTE_ADDR prefix here. </small><br>
                    <small>For example, if your load balancer IP is 10.10.11.11, and it itself is behind a proxy with IP 210.20.1.10,</small><br>
                    <small>this should be "210.20.1.10,10.10.11.11". Otherwise, leave empty.</small><br>

                    <input type="text" name="stage" value="2" hidden>
                    <input type="submit" value="Next">
                    </form>';
            break;
        /*            array_push($args,["rememberMe",1]);
            array_push($args,["selfReg",0]);
            array_push($args,["regConfirmMail",0]);
            array_push($args,["pwdResetExpires",72]);
            array_push($args,["mailConfirmExpires",72]);
        */
        //-------------3rd installation stage
        case 2:
            echo '<div id="notice">';

            if(isset($_REQUEST['privateKey'])){
                if($siteSettings->setSetting('privateKey',$_REQUEST['privateKey'],['createNew'=>true]))
                    echo 'Setting privateKey set to '.$_REQUEST['privateKey'].EOL;
                else{
                    echo 'Failed to set setting privateKey to '.$_REQUEST['privateKey'].EOL.EOL;
                }
            }

            if(!empty($_REQUEST['captcha-secret'])){
                if($siteSettings->setSetting('captcha_secret_key',$_REQUEST['captcha-secret'],['createNew'=>true]))
                    echo 'Setting captcha_secret_key set to '.$_REQUEST['captcha-secret'].EOL;
                else{
                    echo 'Failed to set setting captcha_secret_key to '.$_REQUEST['captcha-secret'].EOL.EOL;
                }
            }

            if(!empty($_REQUEST['captcha-sitekey'])){
                if($siteSettings->setSetting('captcha_site_key',$_REQUEST['captcha-sitekey'],['createNew'=>true]))
                    echo 'Setting captcha_site_key set to '.$_REQUEST['captcha-sitekey'].EOL;
                else{
                    echo 'Failed to set setting captcha_site_key to '.$_REQUEST['captcha-sitekey'].EOL.EOL;
                }
            }

            if(isset($_REQUEST['sslOn'])){
                if($siteSettings->setSetting('sslOn',1,['createNew'=>true]))
                    echo 'Setting sslOn set to 1'.EOL;
                else{
                    echo 'Failed to set setting sslOn  to 1'.EOL.EOL;
                }
            }

            if(isset($_REQUEST['rememberMe'])){
                if($userSettings->setSetting('rememberMe',1,['createNew'=>true]))
                    echo 'User setting rememberMe set to 1'.EOL;
                else{
                    echo 'Failed to set User setting RememberMe to 1'.EOL.EOL;
                }
            }

            if(isset($_REQUEST['relogWithCookies'])){
                if($userSettings->setSetting('relogWithCookies',1,['createNew'=>true]))
                    echo 'User setting relogWithCookies set to 1'.EOL;
                else{
                    echo 'Failed to set User setting relogWithCookies to 1'.EOL.EOL;
                }
            }

            if(isset($_REQUEST['userTokenExpiresIn'])){
                if($userSettings->setSetting('userTokenExpiresIn',$_REQUEST['userTokenExpiresIn'],['createNew'=>true]))
                    echo 'User setting userTokenExpiresIn set to '.$_REQUEST['userTokenExpiresIn'].EOL;
                else{
                    echo 'Failed to set User setting userTokenExpiresIn to '.$_REQUEST['userTokenExpiresIn'].EOL.EOL;
                }
            }

            if(isset($_REQUEST['selfReg'])){
                if($userSettings->setSetting('selfReg',1,['createNew'=>true]))
                    echo 'User setting selfReg set to 1'.EOL;
                else{
                    echo 'Failed to set User setting selfReg  to 1'.EOL.EOL;
                }
            }

            if(isset($_REQUEST['usernameChoice'])){
                if($userSettings->setSetting('usernameChoice',$_REQUEST['usernameChoice'],['createNew'=>true]))
                    echo 'User setting usernameChoice set to '.$_REQUEST['usernameChoice'].EOL;
                else{
                    echo 'Failed to set User setting usernameChoice  to '.$_REQUEST['usernameChoice'].EOL.EOL;
                }
            }

            if(isset($_REQUEST['allowTesting'])){
                if($apiSettings->setSetting('allowTesting',$_REQUEST['allowTesting'],['createNew'=>true]))
                    echo 'User setting allowTesting set to '.$_REQUEST['allowTesting'].EOL;
                else{
                    echo 'Failed to set User setting allowTesting  to '.$_REQUEST['allowTesting'].EOL.EOL;
                }
            }

            if(isset($_REQUEST['restrictedArticleByAddress'])){
                if($apiSettings->setSetting('restrictedArticleByAddress',$_REQUEST['restrictedArticleByAddress'],['createNew'=>true]))
                    echo 'User setting restrictedArticleByAddress set to '.$_REQUEST['restrictedArticleByAddress'].EOL;
                else{
                    echo 'Failed to set User setting restrictedArticleByAddress  to '.$_REQUEST['restrictedArticleByAddress'].EOL.EOL;
                }
            }

            if(isset($_REQUEST['passwordResetTime'])){
                if($userSettings->setSetting('passwordResetTime',$_REQUEST['passwordResetTime'],['createNew'=>true]))
                    echo 'User setting passwordResetTime set to '.$_REQUEST['passwordResetTime'].EOL;
                else{
                    echo 'Failed to set User setting passwordResetTime to '.$_REQUEST['passwordResetTime'].EOL.EOL;
                }
            }

            if(isset($_REQUEST['regConfirmMail'])){
                if($userSettings->setSetting('regConfirmMail',1,['createNew'=>true]))
                    echo 'User setting regConfirmMail set to 1'.EOL;
                else{
                    echo 'Failed to set User setting regConfirmMail  to 1'.EOL.EOL;
                }
            }

            if(isset($_REQUEST['pwdResetExpires'])){
                if($userSettings->setSetting('pwdResetExpires',$_REQUEST['pwdResetExpires'],['createNew'=>true]))
                    echo 'User setting pwdResetExpires set to '.$_REQUEST['pwdResetExpires'].EOL;
                else{
                    echo 'Failed to set User setting pwdResetExpires  to '.$_REQUEST['pwdResetExpires'].EOL.EOL;
                }
            }

            if(isset($_REQUEST['mailConfirmExpires'])){
                if($userSettings->setSetting('mailConfirmExpires',$_REQUEST['mailConfirmExpires'],['createNew'=>true]))
                    echo 'User setting mailConfirmExpires set to '.$_REQUEST['mailConfirmExpires'].EOL;
                else{
                    echo 'Failed to set User setting mailConfirmExpires  to '.$_REQUEST['mailConfirmExpires'].EOL.EOL;
                }
            }

            if(isset($_REQUEST['expectedProxy'])){
                if($localSettings->setSetting('expectedProxy',$_REQUEST['expectedProxy'],['createNew'=>true]))
                    echo 'Local setting expectedProxy set to '.$_REQUEST['expectedProxy'].EOL;
                else{
                    echo 'Failed to set local setting expectedProxy  to '.$_REQUEST['expectedProxy'].EOL.EOL;
                }
            }

            echo '</div>';
            echo 'Install stage 3:'.EOL.
                'Please input the Redis credentials and settings - may be skipped if you don\'t have any, by leaving the address blank:'.EOL;

            if(php_sapi_name()!='cli')
                echo    '<form method="post" action="">
                        <input type="text" name="stage" value="3" hidden><br>
                        <span>Redis IP address <small><b>Leave blank to skip this part!</b>.</small>:</span><input type="text" name="redis_addr" placeholder="E.g 127.0.0.1"><br>
                        <span>Redis Port:</span><input type="number" name="redis_port" value=6379><br>
                        <span>Redis Password:</span><input type="text" name="redis_password" placeholder="Optional Password"><br>
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

            //Notice everything else is inside this - if the address is not set, everything is skipped
            if(isset($_REQUEST['redis_addr']) && ($_REQUEST['redis_addr']!= '') ){
                if($redisSettings->setSetting('redis_addr',$_REQUEST['redis_addr'],['createNew'=>true]))
                    echo 'Setting redis_addr set to '.$_REQUEST['redis_addr'].EOL;
                else{
                    echo 'Failed to set setting redis_addr to '.$_REQUEST['redis_addr'].EOL.EOL;
                }

                if(isset($_REQUEST['redis_port'])){
                    if($redisSettings->setSetting('redis_port',$_REQUEST['redis_port'],['createNew'=>true]))
                        echo 'Setting redis_port set to '.$_REQUEST['redis_port'].EOL;
                    else{
                        echo 'Failed to set setting redis_port to '.$_REQUEST['redis_port'].EOL.EOL;
                    }
                }

                if(isset($_REQUEST['redis_password'])){
                    //This is optional!
                    if($_REQUEST['redis_password'] != ''){
                        if($redisSettings->setSetting('redis_password',$_REQUEST['redis_password'],['createNew'=>true]))
                            echo 'Setting redis_password set to '.$_REQUEST['redis_password'].EOL;
                        else{
                            echo 'Failed to set setting redis_password to '.$_REQUEST['redis_password'].EOL.EOL;
                        }
                    }
                }

                if(isset($_REQUEST['redis_timeout'])){
                    //This is optional!
                    if($_REQUEST['redis_timeout'] != ''){
                        //Has to be at least 1
                        if($_REQUEST['redis_timeout'] < 1)
                            $_REQUEST['redis_timeout'] = 1;
                        if($redisSettings->setSetting('redis_timeout',$_REQUEST['redis_timeout'],['createNew'=>true]))
                            echo 'Setting redis_timeout set to '.$_REQUEST['redis_timeout'].EOL;
                        else{
                            echo 'Failed to set setting redis_timeout to '.$_REQUEST['redis_timeout'].EOL.EOL;
                        }
                    }
                }

                if(isset($_REQUEST['redis_default_persistent'])){
                    if($redisSettings->setSetting('redis_default_persistent',$_REQUEST['redis_default_persistent'],['createNew'=>true]))
                        echo 'Setting redis_default_persistent set to '.$_REQUEST['redis_default_persistent'].EOL;
                    else{
                        echo 'Failed to set setting redis_default_persistent to '.$_REQUEST['redis_default_persistent'].EOL.EOL;
                    }
                }

            }

            echo '</div>';
            echo 'Install stage 4:'.EOL.
                'Please input the SQL credentials (user must have ALL privileges):'.EOL;

            echo    '<form method="post" action="">
                    <input type="text" name="stage" value="4" hidden><br>
                    <input type="text" name="sql_table_prefix" placeholder="Prefered table Prefix (Max 6 Characters)"><br>
                    <input type="text" name="sql_addr" placeholder="Your SQL server address"><br>
                    <input type="text" name="sql_port" placeholder="Your SQL server port"><br>
                    <input type="text" name="sql_u" placeholder="Your SQL server username"><br>
                    <input type="text" name="sql_p" placeholder="Your SQL server password"><br>
                    <input type="text" name="sql_db" placeholder="Your SQL server database name"><br>
                    <input type="submit" value="Next">
                    </form>';
            break;
        //-------------5th installation stage
        case 4:
            echo 'Install stage 5:'.EOL;
            echo '<div id="notice">';


            if(!isset($_REQUEST['sql_addr'])
                ||!isset($_REQUEST['sql_u'])
                ||!isset($_REQUEST['sql_p'])
                ||!isset($_REQUEST['sql_db'])
            ){
                echo 'Incorrect input! Please try again';
                echo '</div>';
                die();
            }

            if(($_REQUEST['sql_addr'] == '')
                ||($_REQUEST['sql_u'] == '')
                ||($_REQUEST['sql_p'] == '')
                ||($_REQUEST['sql_db'] == '')
            ){
                echo 'Incorrect input! Please try again';
                echo '</div>';
                die();
            }

            $sqlArgs = [
                //["arg","value"]
            ];
            //Enforce table prefix to be 6 characters max
            if(strlen($_REQUEST['sql_table_prefix'])>6)
                $_REQUEST['sql_table_prefix'] = substr($_REQUEST['sql_table_prefix'],0,6);

            array_push($sqlArgs,["sql_table_prefix",$_REQUEST['sql_table_prefix']]);
            array_push($sqlArgs,["sql_server_addr",$_REQUEST['sql_addr']]);
            array_push($sqlArgs,["sql_server_port",$_REQUEST['sql_port']]);
            array_push($sqlArgs,["sql_username",$_REQUEST['sql_u']]);
            array_push($sqlArgs,["sql_password",$_REQUEST['sql_p']]);
            array_push($sqlArgs,["sql_db_name",$_REQUEST['sql_db']]);

            $res = true;
            //Update all settings, and return false if any of the updates failed
            foreach($sqlArgs as $key=>$val){
                if($sqlSettings->setSetting($val[0],$val[1],['createNew'=>true]))
                    echo 'Setting '.$val[0].' set to '.$val[1].EOL;
                else{
                    echo 'Failed to set setting '.$val[0].' to '.$val[1].EOL;
                    $res = false;
                }
            }

            if($res){
                try{
                    $conn = IOFrame\Util\prepareCon($sqlSettings);
                    echo 'All is well.'.EOL.'</div>';
                }
                catch(\Exception $e){
                    echo 'Failed to connect to DB! Error:'.$e;
                }
                echo '<form method="post" action="">
                    <input type="text" name="stage" value="5" hidden>
                    <input type="submit" value="Next">
                    </form>';
            }
            else{
                echo ''.EOL.'</div>';
            }

            break;
        //-------------6th installation stage
        case 5:
            echo 'Install stage 6:<div id="notice"> ';
            if(IOFrame\initDB($localSettings)) {
                echo EOL.'Database initiated! </div>' . EOL;
            }
            else{
                echo EOL.'Database NOT initiated properly.'.EOL.
                    'You might continue, but only if the reason for the error was tables already existing from a previous app.</div>'.EOL;
            }

            echo 'If the database is properly initiated, click next for the default Actions Rulebook (security related rules)
                 to be initiated.<br>
                 If not, clicking next might force you to reinstall, or install improperly - go back and retry the DB initiation.'.EOL;

            echo '<form method="post" action="">
                    <input type="text" name="stage" value="6" hidden>
                    <input type="submit" value="Next">
                     </form>';
            break;
        //-------------7th installation stage
        case 6:
            require_once 'IOFrame/Util/safeSTR.php';
            require_once 'IOFrame/Handlers/RouteHandler.php';
            echo 'Install stage 7:<div id="notice"> ';
            $SQLHandler = new IOFrame\Handlers\SQLHandler($localSettings);

            //Insert security events
            $columns = ['Event_Category','Event_Type','Sequence_Number','Blacklist_For','Add_TTL'];
            $assignments = [
                [0,0,0,0,8640],
                [0,0,1,0,0],
                [0,0,5,60,0],
                [0,0,7,300,3600],
                [0,0,8,1200,43200],
                [0,0,9,3600,86400],
                [0,0,10,86400,604800],
                [0,0,11,31557600,31557600],

                [0,1,0,0,86400],
                [0,1,1,0,0],
                [0,1,2,60,0],
                [0,1,3,3600,0],
                [0,1,4,86400,2678400],
                [0,1,5,86400,0],
                [0,1,10,31557600,31557600],

                [0,2,0,0,86400],
                [0,2,1,0,0],
                [0,2,5,3600,2678400],
                [0,2,6,3600,3600],
                [0,2,7,86400,86400],
                [0,2,8,2678400,2678400],
                [0,2,9,31557600,31557600],

                [1,0,0,0,17280],
                [1,0,1,0,0],
                [1,0,5,0,86400],
                [1,0,6,0,0],
                [1,0,10,0,2678400],
                [1,0,11,0,0],
                [1,0,100,2678400,31557600],

                [1,1,0,60,3600],
                [1,1,2,600,21600],
                [1,1,4,3600,86400],
                [1,1,5,86400,2678400],

                [1,2,0,60,3600],
                [1,2,2,600,21600],
                [1,2,5,1900,86400],
                [1,2,10,3600,2678400]
            ];

            $res = $SQLHandler->insertIntoTable($SQLHandler->getSQLPrefix().'EVENTS_RULEBOOK',$columns,$assignments,['test'=>false]);

            if($res) {
                echo EOL.'Events Rulebook initiated!' . EOL;
            }
            else{
                echo EOL.'Events Rulebook NOT initiated properly! Please initiate the database properly.'.EOL;
                die();
            }

            //Insert auth actions
            $columns = ['Auth_Action','Description'];
            $assignments = [
                ['REGISTER_USER_AUTH',\IOFrame\Util\str2SafeStr('Required to register a user when self-registration is not allowed')],
                ['ADMIN_ACCESS_AUTH',\IOFrame\Util\str2SafeStr('Required to access administrator pages')],
                ['AUTH_MODIFY_RANK',\IOFrame\Util\str2SafeStr('Required to modify user ranks (down to your rank at most)')],
                ['AUTH_VIEW',\IOFrame\Util\str2SafeStr('Required to view auth actions/groups/users')],
                ['AUTH_SET',\IOFrame\Util\str2SafeStr('Required to freely modify auth actions/groups')],
                ['AUTH_SET_ACTIONS',\IOFrame\Util\str2SafeStr('Required to set auth actions')],
                ['AUTH_SET_GROUPS',\IOFrame\Util\str2SafeStr('Required to set auth groups')],
                ['AUTH_DELETE',\IOFrame\Util\str2SafeStr('Required to delete auth actions/groups')],
                ['AUTH_DELETE_ACTIONS',\IOFrame\Util\str2SafeStr('Required to delete auth actions')],
                ['AUTH_DELETE_GROUPS',\IOFrame\Util\str2SafeStr('Required to delete auth groups')],
                ['AUTH_MODIFY',\IOFrame\Util\str2SafeStr('Required to delete auth actions/groups/users')],
                ['AUTH_MODIFY_ACTIONS',\IOFrame\Util\str2SafeStr('Required to delete auth actions')],
                ['AUTH_MODIFY_GROUPS',\IOFrame\Util\str2SafeStr('Required to delete auth groups')],
                ['AUTH_MODIFY_USERS',\IOFrame\Util\str2SafeStr('Required to delete auth users')],
                ['CONTACTS_GET',\IOFrame\Util\str2SafeStr('Required to freely get contacts')],
                ['CONTACTS_MODIFY',\IOFrame\Util\str2SafeStr('Required to freely modify contacts')],
                ['PLUGIN_GET_AVAILABLE_AUTH',\IOFrame\Util\str2SafeStr('Required to get available plugins')],
                ['PLUGIN_GET_INFO_AUTH',\IOFrame\Util\str2SafeStr('Required to get plugin info')],
                ['PLUGIN_GET_ORDER_AUTH',\IOFrame\Util\str2SafeStr('Required to get plugin order')],
                ['PLUGIN_PUSH_TO_ORDER_AUTH',\IOFrame\Util\str2SafeStr('Required to push to plugin order')],
                ['PLUGIN_REMOVE_FROM_ORDER_AUTH',\IOFrame\Util\str2SafeStr('Required to remove from plugin order')],
                ['PLUGIN_MOVE_ORDER_AUTH',\IOFrame\Util\str2SafeStr('Required to move plugin order')],
                ['PLUGIN_SWAP_ORDER_AUTH',\IOFrame\Util\str2SafeStr('Required to swap plugin order')],
                ['PLUGIN_INSTALL_AUTH',\IOFrame\Util\str2SafeStr('Required to install a plugin')],
                ['PLUGIN_UNINSTALL_AUTH',\IOFrame\Util\str2SafeStr('Required to uninstall a plugin')],
                ['PLUGIN_IGNORE_VALIDATION',\IOFrame\Util\str2SafeStr('Required to ignore plugin validation during installation')],
                ['TREE_C_AUTH',\IOFrame\Util\str2SafeStr('Required to create all trees')],
                ['TREE_R_AUTH',\IOFrame\Util\str2SafeStr('Required to read all trees')],
                ['TREE_U_AUTH',\IOFrame\Util\str2SafeStr('Required to update all trees')],
                ['TREE_D_AUTH',\IOFrame\Util\str2SafeStr('Required to delete all trees')],
                ['TREE_MODIFY_ALL',\IOFrame\Util\str2SafeStr('Required to modify all trees')],
                ['BAN_USERS_AUTH',\IOFrame\Util\str2SafeStr('Required to ban users')],
                ['SET_USERS_AUTH',\IOFrame\Util\str2SafeStr('Required to update users')],
                ['GET_USERS_AUTH',\IOFrame\Util\str2SafeStr('Required to get users information')],
                ['MODIFY_USER_ACTIONS_AUTH',\IOFrame\Util\str2SafeStr('Required to modify user actions')],
                ['MODIFY_AUTH',\IOFrame\Util\str2SafeStr('Required to modify all user auth')],
                ['MODIFY_USER_RANK_AUTH',\IOFrame\Util\str2SafeStr('Required to modify user ranks')],
                ['MODIFY_GROUP_AUTH',\IOFrame\Util\str2SafeStr('Required to modify auth groups')],
                ['ASSIGN_OBJECT_AUTH',\IOFrame\Util\str2SafeStr('Action required to assign objects in the object map')],
                ['IMAGE_UPLOAD_AUTH',\IOFrame\Util\str2SafeStr('Allow image upload')],
                ['IMAGE_FILENAME_AUTH',\IOFrame\Util\str2SafeStr('Allow choosing image filename on upload')],
                ['IMAGE_OVERWRITE_AUTH',\IOFrame\Util\str2SafeStr('Allow overwriting existing images')],
                ['IMAGE_GET_ALL_AUTH',\IOFrame\Util\str2SafeStr('Allows getting all images (and each individual one)')],
                ['IMAGE_UPDATE_AUTH',\IOFrame\Util\str2SafeStr('Allow unlimited image updating (both alt tag and name)')],
                ['IMAGE_ALT_AUTH',\IOFrame\Util\str2SafeStr('Allow unlimited image alt tag changing')],
                ['IMAGE_NAME_AUTH',\IOFrame\Util\str2SafeStr('Allow unlimited image name changing')],
                ['IMAGE_MOVE_AUTH',\IOFrame\Util\str2SafeStr('Allow unlimited image moving')],
                ['IMAGE_DELETE_AUTH',\IOFrame\Util\str2SafeStr('Allow unlimited image deletion')],
                ['IMAGE_INCREMENT_AUTH',\IOFrame\Util\str2SafeStr('Allow unlimited image version incrementation')],
                ['GALLERY_GET_ALL_AUTH',\IOFrame\Util\str2SafeStr('Allows getting all galleries (and each individual one)')],
                ['GALLERY_CREATE_AUTH',\IOFrame\Util\str2SafeStr('Allow unlimited gallery creation')],
                ['GALLERY_UPDATE_AUTH',\IOFrame\Util\str2SafeStr('Allow unlimited gallery updating - includes adding/removing media to/from gallery')],
                ['GALLERY_DELETE_AUTH',\IOFrame\Util\str2SafeStr('Allow unlimited gallery deletion')],
                ['MEDIA_FOLDER_CREATE_AUTH',\IOFrame\Util\str2SafeStr('Allows creating media folders')],
                ['ORDERS_VIEW_AUTH',\IOFrame\Util\str2SafeStr('Allows viewing all orders')],
                ['ORDERS_MODIFY_AUTH',\IOFrame\Util\str2SafeStr('Allow modyfing all orders')],
                ['USERS_ORDERS_VIEW_AUTH',\IOFrame\Util\str2SafeStr('Allow viewing all user-order relations')],
                ['USERS_ORDERS_MODIFY_AUTH',\IOFrame\Util\str2SafeStr('Allow modifying all user-order relations')],
                ['OBJECT_AUTH_VIEW',\IOFrame\Util\str2SafeStr('Allows viewing all object action auth')],
                ['OBJECT_AUTH_MODIFY',\IOFrame\Util\str2SafeStr('Allow modyfing all object action auth')],
                ['ARTICLES_MODIFY_AUTH',\IOFrame\Util\str2SafeStr('Allow modyfing all articles')],
                ['ARTICLES_VIEW_AUTH',\IOFrame\Util\str2SafeStr('Allows viewing all articles')],
                ['ARTICLES_CREATE_AUTH',\IOFrame\Util\str2SafeStr('Allows creating new trees')],
                ['ARTICLES_UPDATE_AUTH',\IOFrame\Util\str2SafeStr('Allows updating all articles')],
                ['ARTICLES_DELETE_AUTH',\IOFrame\Util\str2SafeStr('Allows deleting all articles')],
                ['ARTICLES_BLOCKS_ASSUME_SAFE',\IOFrame\Util\str2SafeStr('Allow inserting potentially "unsafe" content into articles.')],
                ['CAN_ACCESS_CP',\IOFrame\Util\str2SafeStr('Allows accessing the control panel even when not an admin')],
                ['CAN_UPDATE_SYSTEM',\IOFrame\Util\str2SafeStr('Allows updating the system even when not an admin')],
                ['INVITE_USERS_AUTH',\IOFrame\Util\str2SafeStr('Allows inviting users - either via mail, or by just creating invites')],
                ['SET_INVITE_MAIL_ARGS',\IOFrame\Util\str2SafeStr('Allows passing invite mail arguments')],
            ];

            foreach($assignments as $k=>$v){
                $assignments[$k][0] = [$v[0],'STRING'];
                $assignments[$k][1] = [$v[1],'STRING'];
            }

            $res = $SQLHandler->insertIntoTable($SQLHandler->getSQLPrefix().'ACTIONS_AUTH',$columns,$assignments,['test'=>false]);

            if($res) {
                echo EOL.'Default Actions initiated!' . EOL;
            }
            else{
                echo EOL.'Default Actions NOT initiated properly! Please initiate the database properly.'.EOL;
                die();
            }
            require __DIR__.'/IOFrame/Handlers/SecurityHandler.php';
            $SecurityHandler = new \IOFrame\Handlers\SecurityHandler($localSettings,$defaultSettingsParams);
            $res = $SecurityHandler->setEventsMeta(
                [
                    [
                        'category'=>0,
                        'type'=>0,
                        'meta'=>json_encode([
                            'name'=>'IP Incorrect Login Limit'
                        ])
                    ],
                    [
                        'category'=>0,
                        'type'=>1,
                        'meta'=>json_encode([
                            'name'=>'IP Request Reset Mail Limit'
                        ])
                    ],
                    [
                        'category'=>0,
                        'type'=>2,
                        'meta'=>json_encode([
                            'name'=>'IP Forbidden Action Repeat Limit'
                        ])
                    ],
                    [
                        'category'=>0,
                        'type'=>3,
                        'meta'=>json_encode([
                            'name'=>'IP Registration Limit'
                        ])
                    ],
                    [
                        'category'=>1,
                        'type'=>0,
                        'meta'=>json_encode([
                            'name'=>'User Incorrect Login Limit'
                        ])
                    ],
                    [
                        'category'=>1,
                        'type'=>1,
                        'meta'=>json_encode([
                            'name'=>'User Registration Confirmation Mail Limit'
                        ])
                    ],
                    [
                        'category'=>1,
                        'type'=>2,
                        'meta'=>json_encode([
                            'name'=>'User Reset Mail Limit'
                        ])
                    ],
                    [
                        'category'=>0,
                        'meta'=>json_encode([
                            'name'=>'IP Related Events'
                        ])
                    ],
                    [
                        'category'=>1,
                        'meta'=>json_encode([
                            'name'=>'User Related Events'
                        ])
                    ],
                ],
                []
            );
            if($res){
                echo EOL.'Default security events meta information initiated!' . EOL;
            }
            else{
                echo EOL.'Default security events meta information NOT initiated!'.EOL;
            }

            //Insert routing rules
            $RouteHandler = new IOFrame\Handlers\RouteHandler($localSettings,$defaultSettingsParams);

            $routesAdded = $RouteHandler->addRoutes(
                [
                    ['GET|POST|PUT|DELETE|PATCH|HEAD','api/[*:trailing]','api',null],
                    ['GET|POST','[*:trailing]','front',null]
                ]
            );

            if($routesAdded>=1){
                echo EOL.'Default Routes initiated!' . EOL;
            }
            else{
                echo EOL.'Default Routes NOT initiated properly!'.EOL;
                die();
            }


            $matches = $RouteHandler->setMatches(
                [
                    'front'=>['front/ioframe/pages/[trailing]', 'php,html,htm'],
                    'api'=>['api/[trailing]','php',true]
                ]
            );

            if($matches['front']===0 && $matches['api']===0 ){
                echo EOL.'Default Matches initiated!' . EOL;
            }
            else{
                echo EOL.'Default Matches NOT initiated properly!'.EOL;
                die();
            }

            //Insert default resources
            require 'IOFrame/Handlers/FrontEndResourceHandler.php';
            $FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($localSettings,$defaultSettingsParams);
            $resourceCreation = $FrontEndResourceHandler->setResources(
                [
                    ['address'=>'sec/aes.js'],
                    ['address'=>'sec/mode-ecb.js'],
                    ['address'=>'sec/mode-ctr.js'],
                    ['address'=>'sec/pad-ansix923-min.js'],
                    ['address'=>'sec/pad-zeropadding.js'],
                    ['address'=>'utils.js'],
                    ['address'=>'initPage.js'],
                    ['address'=>'objects.js'],
                    ['address'=>'fp.js'],
                    ['address'=>'ezAlert.js']
                ],
                'js'
            );
            foreach($resourceCreation as $res)
                if($res === -1){
                    echo EOL.'Resource creation failed, could not connect to db!'.EOL;
                    die();
                }

            $collectionCreation = $FrontEndResourceHandler->setJSCollection('IOFrameCoreJS',null);
            if($collectionCreation === -1){
                echo EOL.'Resource collection creation failed, could not connect to db!'.EOL;
                die();
            }

            $collectionInit = $FrontEndResourceHandler->addJSFilesToCollection(
                ['sec/aes.js','sec/mode-ecb.js','sec/mode-ctr.js','sec/pad-ansix923-min.js','sec/pad-zeropadding.js',
                    'utils.js','initPage.js','objects.js','fp.js','ezAlert.js'],
                'IOFrameCoreJS'
            );
            foreach($collectionInit as $res)
                if($res === -1){
                    echo EOL.'Resource collection population failed, could not connect to db!'.EOL;
                    die();
                }


            echo EOL.'Resources created!'.EOL;

            echo ' </div>';

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
                           In most services, you\'ll need to set them manual, and at worst using an non-existent one will cause your emails not being sent.</small><br>;
                     <input type="submit" value="Next">
                     </form>';

            break;
        //-------------8th installation stage
        case 7:
            echo 'Install stage 8:'.EOL;
            if(isset($_REQUEST['mailHost'])
                &&isset($_REQUEST['mailEncryption'])
                &&isset($_REQUEST['mailUsername'])
                &&isset($_REQUEST['mailPassword'])
                &&isset($_REQUEST['mailPort'])
            ){
                echo '<div id="notice">Setting mail...';
                if($mailSettings->setSetting('mailHost',$_REQUEST['mailHost'],['createNew'=>true]))
                    echo 'Setting mailHost set to '.$_REQUEST['mailHost'].EOL;
                else{
                    echo 'Failed to set setting mailHost  to '.$_REQUEST['mailHost'].EOL.EOL;
                }
                if($mailSettings->setSetting('mailEncryption',$_REQUEST['mailEncryption'],['createNew'=>true]))
                    echo 'Setting mailEncryption set to '.$_REQUEST['mailEncryption'].EOL;
                else{
                    echo 'Failed to set setting mailEncryption  to '.$_REQUEST['mailEncryption'].EOL.EOL;
                }
                if($mailSettings->setSetting('mailUsername',$_REQUEST['mailUsername'],['createNew'=>true]))
                    echo 'Setting mailUsername set to '.$_REQUEST['mailUsername'].EOL;
                else{
                    echo 'Failed to set setting mailUsername  to '.$_REQUEST['mailUsername'].EOL.EOL;
                }
                if($mailSettings->setSetting('mailPassword',$_REQUEST['mailPassword'],['createNew'=>true]))
                    echo 'Setting mailPassword set to '.$_REQUEST['mailPassword'].EOL;
                else{
                    echo 'Failed to set setting mailPassword  to '.$_REQUEST['mailPassword'].EOL.EOL;
                }
                if($mailSettings->setSetting('mailPort',$_REQUEST['mailPort'],['createNew'=>true]))
                    echo 'Setting mailPort set to '.$_REQUEST['mailPort'].EOL;
                else{
                    echo 'Failed to set setting mailPort  to '.$_REQUEST['mailPort'].EOL.EOL;
                }
                if(isset($_REQUEST['defaultAlias'])
                ){
                    if($mailSettings->setSetting('defaultAlias',$_REQUEST['defaultAlias'],['createNew'=>true]))
                        echo 'Setting defaultAlias set to '.$_REQUEST['defaultAlias'].EOL;
                    else{
                        echo 'Failed to set setting defaultAlias  to '.$_REQUEST['defaultAlias'].EOL.EOL;
                    }
                }
            }

            //Initiate all settings handlers that need to by synced

            $userSettings = new IOFrame\Handlers\SettingsHandler(
                IOFrame\Util\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/userSettings/',
                $defaultSettingsParams
            );;
            $userSettings->initDB();
            $pageSettings = new IOFrame\Handlers\SettingsHandler(
                IOFrame\Util\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/pageSettings/',
                $defaultSettingsParams
            );
            $pageSettings->initDB();
            $mailSettings = new IOFrame\Handlers\SettingsHandler(
                IOFrame\Util\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/mailSettings/',
                $defaultSettingsParams
            );
            $mailSettings->initDB();
            $siteSettings = new IOFrame\Handlers\SettingsHandler(
                IOFrame\Util\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/siteSettings/',
                $defaultSettingsParams
            );
            $siteSettings->initDB();
            $resourceSettings = new IOFrame\Handlers\SettingsHandler(
                IOFrame\Util\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/resourceSettings/',
                $defaultSettingsParams
            );
            $resourceSettings->initDB();
            $apiSettings = new IOFrame\Handlers\SettingsHandler(
                IOFrame\Util\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/apiSettings/',
                $defaultSettingsParams
            );
            $apiSettings->initDB();
            $metaSettings = new IOFrame\Handlers\SettingsHandler(
                IOFrame\Util\getAbsPath().'/'.SETTINGS_DIR_FROM_ROOT.'/metaSettings/',
                $defaultSettingsParams
            );
            $metaSettings->initDB();

            echo 'All settings synced to database!'.EOL;
            echo '</div>';
            echo 'Create the admin account. This will be a one-of a kind account with the highest rank, so remember the info!'.EOL;

            echo    '<form method="post" action="">
                        <input type="text" name="stage" value="8" hidden><br>
                        <input type="text" name="u" placeholder="Your username">
                        <a href="#" data-html="true" data-placement="bottom" data-toggle="tooltip-u"
                         title="Must be 6-16 characters long, must contain numbers and latters">?</a><br>
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
            $UserHandler = new IOFrame\Handlers\UserHandler($localSettings);

            function checkInput($inputs,$test = false){

                $res=true;

                if($inputs["u"]==null||$inputs["p"]==null||$inputs["m"]==null)
                    $res = false;
                else{
                    $u=$inputs["u"];
                    $p=$inputs["p"];
                    $m=$inputs["m"];
                    //Validate Username
                    if(strlen($u)>16||strlen($u)<6||preg_match_all('/\W/',$u)>0||preg_match_all('/undefined/',$u)==1){
                        $res=false;
                        if($test) echo 'Username illegal.';
                    }
                    //Validate Password
                    else if(strlen($p)>20||strlen($p)<8||preg_match_all('/(\s|<|>)/',$p)>0
                        ||preg_match_all('/\d/',$p)==0||preg_match_all('/[a-z]|[A-Z]/',$p)==0){
                        $res=false;
                        if($test) echo 'Password illegal.';
                    }
                    //Validate Mail
                    else if(!filter_var($m, FILTER_VALIDATE_EMAIL)){
                        $res=false;
                        if($test) echo 'Email illegal.';
                    }
                }
                return $res;
            }

            if (!checkInput(['u'=>$_REQUEST['u'],'p'=>$_REQUEST['p'],'m'=>$_REQUEST['m'],])){
                echo 'Illegal account info provided. Please read the password/username requirements, and provide a legal email.';
                die();
            }


            try {
                //New connection
                $_SESSION['INSTALLING']=true;

                $inputs = [];
                $arrExpected =["u","m","p"];
                foreach($arrExpected as $expected){
                    if(isset($_REQUEST[$expected]))
                        $inputs[$expected] = $_REQUEST[$expected];
                }
                $inputs['r'] = 0;

                $UserHandler->regUser($inputs,['considerActive'=>true]);
            }
            catch(PDOException $e)
            {
                echo "Error: " . $e->getMessage().'<br/>';
                Die();
            }
            //Finally, copy the version this node was installed in
            $FileHandler = new IOFrame\Handlers\FileHandler();
            $availableVersion = $FileHandler->readFile($localSettings->getSetting('absPathToRoot').'meta/', 'ver');
            $siteSettings->setSetting('ver',$availableVersion,['createNew'=>true]);
            //The private key should stay inside the db, not in a setting file.
            $siteSettings->setSetting('privateKey',null,['createNew'=>true]);
            echo 'Installation complete!'.EOL;
            $_SESSION['INSTALLING']=false;
            //This means installation was complete!
            $myFile = fopen('localFiles/_installComplete', 'w');
            fclose($myFile);
            //Create Install Complete file
            echo '<form method="get" action="cp/login">
                         <input type="submit" value="Go to admin panel">
                         </form>';

            break;
        //-------------First installation stage
        default:

            $_SESSION['INSTALLING']=true;
            echo 'Welcome! Install stage 1:'.EOL
                .'<div id="notice"> Creating default settings...'.EOL.EOL;
            //Settings to set..
            $userArgs = [
                //["arg","value"]
            ];

            $pageArgs = [

            ];

            $localArgs = [

            ];

            $siteArgs = [

            ];

            $resourceArgs = [

            ];

            $apiArgs = [

            ];

            $metaArgs = [

            ];

            array_push($localArgs,["absPathToRoot",$baseUrl]);
            array_push($localArgs,["pathToRoot",
                substr($_SERVER['SCRIPT_NAME'], 0, strlen($_SERVER['SCRIPT_NAME'])- strlen('/install.php'))]);
            array_push($localArgs,["opMode",IOFrame\Handlers\SETTINGS_OP_MODE_MIXED]);
            array_push($localArgs,["dieOnPluginMismatch",true]);

            array_push($siteArgs,["siteName",'My Website']);
            array_push($siteArgs,["maxInacTime",3600]);
            array_push($siteArgs,["privateKey",'0000000000000000000000000000000000000000000000000000000000000000']);
            array_push($siteArgs,["secStatus",IOFrame\SEC_MODE_1]);
            array_push($siteArgs,["logStatus",IOFrame\LOG_MODE_1]);
            array_push($siteArgs,["sslOn",1]);
            array_push($siteArgs,["maxObjectSize",4000000]);
            array_push($siteArgs,["maxCacheSize",65536]);
            array_push($siteArgs,["maxUploadSize",4000000]);
            array_push($siteArgs,["tokenTTL",3600]);
            array_push($siteArgs,["CPMenu",json_encode([],JSON_FORCE_OBJECT)]);
            array_push($siteArgs,["languages",'']);
            array_push($siteArgs,["captcha_site_key",'']);
            array_push($siteArgs,["captcha_secret_key",'']);

            array_push($userArgs,["pwdResetExpires",72]);
            array_push($userArgs,["mailConfirmExpires",72]);
            array_push($userArgs,["regConfirmTemplate",1]);
            array_push($userArgs,["regConfirmTitle",'Registration Confirmation Mail']);
            array_push($userArgs,["pwdResetTemplate",2]);
            array_push($userArgs,["pwdResetTitle",'Password Reset Confirmation Mail']);
            array_push($userArgs,["emailChangeTemplate",3]);
            array_push($userArgs,["emailChangeTitle",'Email Change Confirmation Mail']);
            array_push($userArgs,["passwordResetTime",5]);
            array_push($userArgs,["inviteMailTemplate",4]);
            array_push($userArgs,["inviteMailTitle",'You\\\'ve been invited to IOFrame Test']);
            array_push($userArgs,["inviteExpires",774]);
            array_push($userArgs,["rememberMe",1]);
            array_push($userArgs,["relogWithCookies",1]);
            array_push($userArgs,["userTokenExpiresIn",0]);
            array_push($userArgs,["allowRegularLogin",1]);
            array_push($userArgs,["allowRegularReg",1]);
            array_push($userArgs,["selfReg",0]);
            array_push($userArgs,["regConfirmMail",0]);
            array_push($userArgs,["allowSMS2FA",0]);
            array_push($userArgs,["sms2FAExpires",300]);
            array_push($userArgs,["allowMail2FA",1]);
            array_push($userArgs,["mail2FAExpires",1800]);
            array_push($userArgs,["allowApp2FA",1]);

            array_push($pageArgs,["loginPage",'cp/login']);
            array_push($pageArgs,["registrationPage",'cp/login']);
            array_push($pageArgs,["pwdReset",'cp/account']);
            array_push($pageArgs,["mailReset",'cp/account']);
            array_push($pageArgs,["regConfirm",'cp/account']);
            array_push($pageArgs,["homepage",'front/ioframe/pages/welcome']);
            array_push($pageArgs,["404",'']);

            array_push($resourceArgs,["videoPathLocal",'front/ioframe/vid/']);
            array_push($resourceArgs,["imagePathLocal",'front/ioframe/img/']);
            array_push($resourceArgs,["jsPathLocal",'front/ioframe/js/']);
            array_push($resourceArgs,["cssPathLocal",'front/ioframe/css/']);
            array_push($resourceArgs,["autoMinifyJS",1]);
            array_push($resourceArgs,["autoMinifyCSS",1]);
            array_push($resourceArgs,["imageQualityPercentage",100]);
            array_push($resourceArgs,["allowDBMediaGet",1]);

            array_push($apiArgs,["articles",json_encode(['active'=>1])]);
            array_push($apiArgs,["auth",json_encode(['active'=>1])]);
            array_push($apiArgs,["contacts",json_encode(['active'=>1])]);
            array_push($apiArgs,["mail",json_encode(['active'=>1])]);
            array_push($apiArgs,["media",json_encode(['active'=>1])]);
            array_push($apiArgs,["menu",json_encode(['active'=>1])]);
            array_push($apiArgs,["object-auth",json_encode(['active'=>1])]);
            array_push($apiArgs,["objects",json_encode(['active'=>1])]);
            array_push($apiArgs,["orders",json_encode(['active'=>0])]);
            array_push($apiArgs,["plugins",json_encode(['active'=>1])]);
            array_push($apiArgs,["security",json_encode(['active'=>1])]);
            array_push($apiArgs,["session",json_encode(['active'=>1])]);
            array_push($apiArgs,["settings",json_encode(['active'=>1])]);
            array_push($apiArgs,["tokens",json_encode(['active'=>1])]);
            array_push($apiArgs,["trees",json_encode(['active'=>0])]);
            array_push($apiArgs,["users",json_encode(['active'=>1])]);
            array_push($apiArgs,["allowTesting",0]);
            array_push($apiArgs,["restrictedArticleByAddress",0]);
            array_push($apiArgs,["captchaFile",'validateCaptcha.php']);

            array_push($metaArgs,['localSettings',json_encode(['local'=>1,'db'=>0,'title'=>'Local Node Settings'])]);
            array_push($metaArgs,['redisSettings',json_encode(['local'=>1,'db'=>0,'title'=>'Redis Settings'])]);
            array_push($metaArgs,['sqlSettings',json_encode(['local'=>1,'db'=>0,'title'=>'SQL Connection Settings'])]);
            array_push($metaArgs,['mailSettings',json_encode(['local'=>0,'db'=>1,'title'=>'Mail Settings'])]);
            array_push($metaArgs,['pageSettings',json_encode(['local'=>0,'db'=>1,'title'=>'Page (redirection) Settings'])]);
            array_push($metaArgs,['resourceSettings',json_encode(['local'=>0,'db'=>1,'title'=>'Resource Settings'])]);
            array_push($metaArgs,['siteSettings',json_encode(['local'=>0,'db'=>1,'title'=>'General Site Settings'])]);
            array_push($metaArgs,['userSettings',json_encode(['local'=>0,'db'=>1,'title'=>'Users Settings'])]);
            array_push($metaArgs,['apiSettings',json_encode(['local'=>0,'db'=>1,'title'=>'API Settings'])]);


            $res = true;

            //Update all settings, and return false if any of the updates failed
            foreach($localArgs as $key=>$val){
                if($localSettings->setSetting($val[0],$val[1],['createNew'=>true]))
                    echo 'Setting '.$val[0].' set to '.$val[1].EOL;
                else{
                    echo 'Failed to set setting '.$val[0].' to '.$val[1].EOL;
                    $res = false;
                }
            }

            foreach($siteArgs as $key=>$val){
                if($siteSettings->setSetting($val[0],$val[1],['createNew'=>true]))
                    echo 'Setting '.$val[0].' set to '.$val[1].EOL;
                else{
                    echo 'Failed to set setting '.$val[0].' to '.$val[1].EOL;
                    $res = false;
                }
            }

            foreach($userArgs as $key=>$val){
                if($userSettings->setSetting($val[0],$val[1],['createNew'=>true]))
                    echo 'User setting '.$val[0].' set to '.$val[1].EOL;
                else{
                    echo 'Failed to set mail setting '.$val[0].' to '.$val[1].EOL;
                    $res = false;
                }
            }


            foreach($pageArgs as $key=>$val){
                if($pageSettings->setSetting($val[0],$val[1],['createNew'=>true]))
                    echo 'Page setting '.$val[0].' set to '.$val[1].EOL;
                else{
                    echo 'Failed to set page setting '.$val[0].' to '.$val[1].EOL;
                    $res = false;
                }
            }

            foreach($resourceArgs as $key=>$val){
                if($resourceSettings->setSetting($val[0],$val[1],['createNew'=>true]))
                    echo 'Resource setting '.$val[0].' set to '.$val[1].EOL;
                else{
                    echo 'Failed to set resource setting '.$val[0].' to '.$val[1].EOL;
                    $res = false;
                }
            }

            foreach($apiArgs as $key=>$val){
                if($apiSettings->setSetting($val[0],$val[1],['createNew'=>true]))
                    echo 'Resource setting '.$val[0].' set to '.$val[1].EOL;
                else{
                    echo 'Failed to set api setting '.$val[0].' to '.$val[1].EOL;
                    $res = false;
                }
            }

            foreach($metaArgs as $key=>$val){
                if($metaSettings->setSetting($val[0],$val[1],['createNew'=>true]))
                    echo 'Meta setting '.$val[0].' set to '.$val[1].EOL;
                else{
                    echo 'Failed to set meta setting '.$val[0].' to '.$val[1].EOL;
                    $res = false;
                }
            }

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
    }

}