<?php
/* This the the API that handles all the user related functions.
 * Many of the procedures here are timing safe, meaning they will return in constant times (well, constant intervals)
 *
 *      See standard return values at defaultInputResults.php
 *_________________________________________________
 * getUsers
 *      Returns all users
 *
 *      'idAtLeast' => int, defaults to 1 - Returns users with ID equal or greater than this
 *      'idAtMost' => int, defaults to null - if set, Returns users with ID equal or smaller than this
 *      'rankAtLeast' => int, defaults to 1 - Returns users with rank equal or greater than this
 *      'rankAtMost' => int, defaults to null - if set, Returns users with rank equal or smaller than this
 *      'usernameLike' => String, default null - returns results where username  matches a regex.
 *      'emailLike' => String, email, default null - returns results where email matches a regex.
 *      'isActive' => bool, defaults to null - if set, Returns users which are either active or inactive (true or false).
 *      'isBanned' => bool, defaults to null - if set, Returns users which are either banned or not banned (true or false).
 *      'isSuspicious' => bool, defaults to null - if set, Returns users which are either suspicious or unsuspicious (true or false).
 *      'createdBefore' => String, Unix timestamp, default null - only returns results created before this date.
 *      'createdAfter' => String, Unix timestamp, default null - only returns results created after this date.
 *      'orderBy'            - string, defaults to null. Possible values include 'Created_On', 'Email', 'Username',
 *                             and 'ID' (default)
 *      'orderType'          - bool, defaults to null.  0 for 'ASC', 1 for 'DESC'
 *      'limit' => typical SQL parameter
 *      'offset' => typical SQL parameter
 *
 *      @returns Array of the form:
 *          [
 *              <identifier *> => {
 *                      'id'=><int, identifier again>,
 *                      'username'=><string>,
 *                      'email'=><string>,
 *                      'phone'=><string>,
 *                      'active'=><bool, whether the account is active>,
 *                      'require2FA'=><bool, whether the account requires two factor authentication>,
 *                      'has2FAApp'=><bool, whether the account is paired with a 2FA app>,
 *                      'rank'=><int, authentication rank>,
 *                      'created'=><int, unix timestamp of when the user was created>,
 *                      'bannedUntil'=><int, shows unix timestamp until when the user is banned>,
 *                      'suspiciousUntil'=><int, shows unix timestamp until when the user is suspicious>
 *                  }
 *          ]
 *
 *
 *       Examples: action=getUsers&idAtLeast=1&idAtMost=3&rankAtLeast=0&rankAtMost=10000&usernameLike=A&emailLike=.com&isActive=true&isBanned=false&isSuspicious=false&createdBefore=999999999999&createdAfter=0&orderBy=Email&orderType=0&limit=5&offset=0&test=true
 *_________________________________________________
 * getMyUser
 *      Gets logged-in user.
 *
 *      @returns Array | Int:
 *          -1 Server error
 *           1 Not logged in
 *          Otherwise, JSON of the form:
 *          {
 *               'id'=><int, identifier again>,
 *               'username'=><string>,
 *               'email'=><string>,
 *               'phone'=><string>,
 *               'active'=><bool, whether the account is active>,
 *               'require2FA'=><bool, whether the account requires two factor authentication>,
 *               'has2FAApp'=><bool, whether the account is paired with a 2FA app>,
 *               'rank'=><int, authentication rank>,
 *               'created'=><int, unix timestamp of when the user was created>
 *          }
 *
 *       Examples: action=updateUser&id=2&username=Test&email=test@test.com&active=0&created=1586370650&bannedDate=1586370650&suspiciousDate=1586370650
 *_________________________________________________
 * updateUser [CSRF protected]
 *      Updates a single user
 *
 *      'id' => int, user id
 *      'username' => String, default null - new username
 *      'email' => String, default null - new Email
 *      'phone' => String, default null - new Phone
 *      'active' => Bool, default null - whether the user is active or not
 *      'created' => Int, default null - Unix timestamp, user creation date.
 *      'bannedDate' => Int, default null - Unix timestamp until which the user is banned (0 to unban the user).
 *      'suspiciousDate' => Int, default null - Unix timestamp until which the user is suspicious (0 to make the user not suspicious).
 *      'require2FA' => bool, default null - can manually set the "Requires 2FA" flag for a user.
 *      'reset2FA' => bool, will set the "Requires 2FA" flag AND 2FA app secret to false for a user.
 *
 *      @returns Codes:
 *          -1 Server error
 *           0 Success
 *           1 Incorrect identifier type
 *           2 Invalid identifier
 *           3 No new assignments
 *
 *       Examples: action=updateUser&id=2&username=Test&email=test@test.com&active=0&created=1586370650&bannedDate=1586370650&suspiciousDate=1586370650
 * _________________________________________________
 * require2FA [CSRF protected]
 *      Requires 2FA usage to log into account. Relevant if at least one auth is enabled for the account
 *
 *      'id' => int, user id. Defaults to your account ID. Only an admin can set it, otherwise.
 *      'require2FA' => bool, default true - whether 2FA should be enabled or not.
 *      TODO add setting that specifies what to do if app already exists - allow refresh, require any type of 2FA, require code from old app
 *      TODO add 'code' => int, code from your current app - for testing, the secret is "JBSWY3DPEHPK3PXP" (only relevant in testing mode)
 *
 *      @returns Codes: Same as updateUser
 *              as well as String: "NO_SUPPORTED_2FA" when the user tries to turn his own 2FA on while he has no supported one.
 *
 *       Examples: action=require2FA
 * _________________________________________________
 * requestApp2FA
 *      Requests app 2FA. If allowed by the system, will return the relevant info to the user, who can then scan a QR code.
 *      To scan a QR for testing, encode the following into QR and scan it: "otpauth://totp/LABEL:test@test.com?secret=JBSWY3DPEHPK3PXP&issuer=Test OTP"
 *      TODO add setting that specifies what to do if app already exists - allow refresh, require any type of 2FA, require code from old app
 *      TODO add 'code' => int, code from your current app - for testing, the secret is "JBSWY3DPEHPK3PXP" (only relevant in testing mode)
 *
 *      @returns: JSON encoded object of the form:
 *          {
 *           'secret': OTP secret
 *           'issuer': OTP Issuer (Site Name)
 *           'mail': User email (actually redundant, but still here)
 *           }
 *
 *       Examples: action=requestApp2FA
 * _________________________________________________
 * confirmApp
 *      Confirms app 2FA. The user needs to input the code on his screen (6 digits).
 *
 *      'code' => int, code - for testing, the secret is "JBSWY3DPEHPK3PXP" (only relevant in testing mode)
 *      'require2FA' => bool, default true - whether 2FA should be required or not (you can set an app without enforcing it).
 *
 *      @returns Codes: Same as updateUser, but also -2 for invalid code
 *
 *       Examples: action=confirmApp&code=749226
 * _________________________________________________
 * requestPhone2FA TODO
 *      Requests phone 2FA
 *
 *      'number' => string, valid phone number (same as in the contacts API)
 *
 *      @returns Codes:
 *
 *       Examples: action=
 * _________________________________________________
 * confirmPhone TODO
 *      Confirms phone number, by inputting the code sent to the user via SMS
 *
 *      'code' => string, code sent via SMS
 *      'require2FA' => bool, default true - whether 2FA should be required or not (you can set an app without enforcing it).
 *
 *      @returns Codes: Same as updateUser, but also -2 for invalid code
 *
 *       Examples: action=
 * _________________________________________________
 * requestSMS2FA TODO
 *      If supported by the system, and the user has a valid phone, will send an SMS to the user
 *
 *      @returns Codes:
 *
 *       Examples: action=
 * _________________________________________________
 * requestMail2FA TODO
 *      If supported by the system, will send an email to the user
 *
 *      @returns Codes:
 *
 *       Examples: action=
 *_________________________________________________
 * addUser [Rate Limited per IP]
 *      - Adds (registers) a user
 *        m: requested mail
 *        p: requested password
 *        u: requested username (optional, depending on a setting)
 *        token: string, default null - if set, will try to register via an invite token (activating the account without confirmation required)
 *        Returns integer code:
 *              0 - success
 *              1 - username already in use
 *              2 - email already in use
 *              3 - server error
 *              4 - registration would succeed, but token does not allow activation
 *
 *        Examples: action=addUser&u=test1&m=test@example.com&p=A5432524gf54
 *_________________________________________________
 * logUser [CSRF protected] [Timing protected] [Incorrect Logins Rate Limited]
 *      - Logs in or out
 *        log: login type ('out','temp' or other) - default 'out'
 *        m: user mail  - required on any login
 *        p: user password  - required on full login
 *        userID: identifier of current device, used for temp login, or for full login using "rememberMe"
 *        sesKey: used to relog using temp login,
 *        2FAType: 2FA Method, if the user chooses to log in via 2FA. Valid methods are:
 *            'mail' - send code via email, always supported on systems with mailSettings set
 *            'app' - works when the user has a 2FA app enabled and configured
 *            'sms' - send code via sms, supported on systems with smsSettings set, AND the user having a valid phone
 *        2FACode: The 2FA code provided by the user. Required when 2FAType is set.
 *        Returns integer code:
 *              -1 Error during some stage of the login, could not complete.
 *              0 all good - without rememberMe
 *              1 username/password combination wrong
 *              2 expired auto login token
 *              3 login type not allowed
 *              4 login would work, but 2FA is required (either user is suspicious or enabled 2FA himself)
 *              5 login would work, but 2FA code is incorrect
 *              6 login would work, but 2FA code expired
 *              7 login would work, but user does not have it set up (no confirmed phone, no registered 2FA app, etc)
 *              8 login would work, but 2FA method is not supported
 *              32-byte hex encoded session ID string - The token for your next automatic relog, if you logged automatically.
 *              JSON encoded array of the form {'iv'=><32-byte hex encoded string>,'sesID'=><32-byte hex encoded string>}
 *
 *        Examples: action=logUser&log=in&m=test@example.com&p=A5432524gf54
 *                  action=logUser&log=out
 *                  action=logUser&log=temp&m=test@example.com&sesKey=8836ac46fbdfa61a2a125991f987079d86903169a9e109557326fec1c71ff5ef&userID=abc6379af765afafa1bad51b084bad48&sesKey=A5432524gf54
 *_________________________________________________
 * pwdReset [Timing protected] [Rate Limited per mail]
 *      - Sends the user a password reset email, or confirms an existing reset code and user session as eligible
 *        to reset the password for a few minutes (time depends on settings)
 *        id: ID of relevant user, used for reset confirmation
 *        code: Confirmation code, used for reset confirmation
 *        mail: user mail, used to request the reset
 *        async: Used to specify you don't want to be redirected, on confirmation.
 *        Returns integer code:
 *              On send request:
 *                  0 - All good
 *                  1 - User mail does not exist
 *              On confirmation:
 *                  0 - All good
 *                  1 - User ID does not exist
 *                  2 - Wrong code
 *                  3 - Code expired
 *        Also, on confirmation, will actually redirect you to a specific page (set in pageSettings) unless "async" is set in the request.
 *
 *        Examples: action=pwdReset&mail=4213@1.so
 *                  action=pwdReset&id=4&code=GtIOsxkfbA92iGp0MsSt70GkfSDTFcUZlyd0I2MJMflz1h6kmI&async
 *_________________________________________________
 * changePassword [CSRF protected]
 *      - Changes user password. Session needs to be authorized via pwdReset first.
 *        newPassword: new password
 *        Returns integer code:
 *                  0 - All good
 *                  1 - User ID does not exist
 *                  2 - Time to change expired!
 *
 *        Examples: action=changePassword&newPassword=Test012345
 *_________________________________________________
 * regConfirm [Rate Limited] [Rate Limited per mail]
 *      - Sends a user a registration email, or confirms an existing registration code and activates user.
 *        --- TO REQUEST A RESET CODE ---
 *        mail: Email of the account
 *        Returns integer code:
 *                -3 activation code creation failed.
 *                -2 user does not exist or already active.
 *                -1 mail failed to send.
 *                 0 all good.
 *                 1 Email activation not required on this system
 *        --- TO APPLY A RESET CODE ---
 *        id: ID of relevant user
 *        code: Confirmation code
 *        async: Used to specify you don't want to be redirected.
 *        Returns integer code:
 *                 0 - All good
 *                 1 - User ID does not exist
 *                 2 - Wrong code
 *                 3 - Code expired
 *        Also, on confirmation, will actually redirect you to a specific page (set in pageSettings) unless "async" is set in the request.
 *
 *        Examples: action=regConfirm&id=4&code=GtIOsxkfbA92iGp0MsSt70GkfSDTFcUZlyd0I2MJMflz1h6kmI
 *                  action=regConfirm&mail=example@example.com
 *_________________________________________________
 * mailReset [Timing protected]  [Rate Limited per mail]
 *      - Sends a reset mail similar to pwdReset, but for the user mail
 *        All codes and inputs similar to pwdReset.
 *
 *        Examples: action=mailReset&id=4&code=GtIOsxkfbA92iGp0MsSt70GkfSDTFcUZlyd0I2MJMflz1h6kmI
 *                  action=mailReset&mail=example@example.com
 *
 *_________________________________________________
 * changeMail [CSRF protected]
 *      - Similar to changePassword, but for the user mail.
 *        newMail: new password
 *        Returns integer code:
 *                 0 - All good
 *                 1 - User ID does not exist
 *                 2 - Time to change expired!
 *
 *        Examples: action=changeMail&newMail=example@example.com
 * _________________________________________________
 * createUserInvite
 *    - Creates an invite token
 *      mail: string, default null - if set, would only allow this mail to use the registration
 *      token: string, if set, this will be the specific token - otherwise, creates a random one
 *      tokenUses: int, default PHP_INT_MAX (9223372036854775807 on basically all systems) - how many uses the token should have
 *      tokenTTL: int, defaults to email activation setting (72 hours by install default) - Token TTL in seconds
 *      overwrite: bool, default true - allows overwriting existing token
 *      update: bool, default false - only updates existing token
 *
 *      Returns string|int -
 *          where possible codes are:
 *         -3 - cannot create mail without $mail set
 *         -2 - token already exists, overwrite is true, but the token is locked
 *         -1 - could not reach db
 *         <string, valid invite token> - on success
 *          1 - token already exists and overwrite is false
 *          2 - token doesn't exist and update is true
 *          3 - "action" was not passed, and token did not previously exist
 *
 *       Examples:
 *          action=createUserInvite
 *          action=createUserInvite&token=test_1&mail=test@test.com&tokenUses=4312&tokenTTL=3600&overwrite=false
 *
 *_________________________________________________
 * sendInviteMail [CSRF protected]
 *      - Sends an invite mail
 *        [required] mail: string, Email to send an invite to
 *        token: string, Specific token to use. Created automatically otherwise
 *        tokenUses: int, default 1 - How many uses a newly created token would have. Cannot be infinite, but can be 64 bit (so basically infinite)
 *        tokenTTL: int, defaults to email activation setting (72 hours by install default) - Token TTL in seconds
 *        extraTemplateArguments: JSON encoded Object, extra arguments for the mail function - REQUIRES SEPARATE AUTH
 *        override: bool, default true, whether to override existing tokens
 *        update: bool, default false, whether to only update existing tokens
 *
 *        Returns integer code:
 *          -3 - Token could not be created
 *          -2 - Mail failed to send
 *          -1 - Server error
 *           <string, valid invite token> - on success
 *           1 - Template does not exist OR setting inviteTemplate not set.
 *
 *        Examples:
 *          action=sendInviteMail&mail=test@test.com
 *          action=createUserInvite&token=test_1&mail=test@test.com&tokenUses=4312&tokenTTL=3600&overwrite=false
 *_________________________________________________
 * checkInvite
 *      - Checks whether a user's invite token is valid. Potentially redirects to registration page with relevant message.
 *        token: Invite token
 *        mail: user mail, can be empty or 0 or "NULL", in which case a token that allows invites by any mail will also be considered valid.
 *        async: Used to specify you don't want to be redirected.
 *
 *        Returns integer code:
 *                  0 - All good
 *                  1 - Token ID does not exist or expired
 *                  2 - Wrong email (and action isn't REGISTER_ANY)
 *        Also, on confirmation, will actually redirect you to a specific page (set in pageSettings) unless "async" is set in the request.
 *
 *        Examples: action=checkInvite&mail=test@test.com&token=test_1
 *_________________________________________________
 * banUser [CSRF protected]
 *      - Bans user for a certain number of minutes
 *        minutes: How many minutes to ban for
 *        id: ID of the user to ban
 *        Returns integer code:
 *                 0 - All good
 *                 1 - User ID does not exist
 *
 *        Examples: action=banUser&id=1&minutes=60000
 *
 * */

if(!defined('coreInit'))
    require __DIR__ . '/../../main/coreInit.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';
require 'user_fragments/definitions.php';
require __DIR__ . '/../../IOFrame/Util/timingManager.php';

if(!checkApiEnabled('users',$apiSettings))
    exit(API_DISABLED);

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];
if($test)
    echo 'Testing mode!'.EOL;

if(!checkApiEnabled('users',$apiSettings,$_REQUEST['action']))
    exit(API_DISABLED);

//Handle inputs
$inputs = [];

//Timing manager
$timingManager = new IOFrame\Util\timingManager();
//Most of the actions need to be timing safe
$timingManager->start();

switch($action){
    case 'getUsers':
        $arrExpected = ['idAtLeast','idAtMost','rankAtLeast','rankAtMost','usernameLike','emailLike','isActive' ,
            'isBanned','isSuspicious','createdBefore','createdAfter','orderBy','orderType', 'limit','offset' ];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/getUsers_auth.php';
        require 'user_fragments/getUsers_checks.php';
        require 'user_fragments/getUsers_execution.php';

        echo json_encode($result);
        break;

    case 'getMyUser':

        require 'user_fragments/getMyUser_auth.php';
        require 'user_fragments/getUsers_execution.php';

        echo json_encode($result);
        break;

    case 'updateUser':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ['id','username','email','phone','active','created','bannedDate','suspiciousDate','require2FA','reset2FA'];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/updateUser_auth.php';
        require 'user_fragments/updateUser_checks.php';
        require 'user_fragments/updateUser_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'require2FA':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ['id','require2FA'];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/require2FA_auth.php';
        require 'user_fragments/require2FA_checks.php';
        require 'user_fragments/updateUser_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'requestApp2FA':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        /*$arrExpected = ['code',];

        require __DIR__ . '/../setExpectedInputs.php';*/
        require 'user_fragments/requestApp2FA_auth.php';
        require 'user_fragments/requestApp2FA_checks.php';
        require 'user_fragments/requestApp2FA_execution.php';

        echo json_encode($result);
        break;

    case 'confirmApp':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ['code','require2FA'];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/confirmApp_auth.php';
        require 'user_fragments/confirmApp_checks.php';
        require 'user_fragments/updateUser_execution.php';

        if(!$test && $result === 0)
            unset($_SESSION['TEMP_2FASecret']);

        if($result === 0)
            $UserHandler->logOut(['test'=>$test,'forgetMe'=>true]);

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'addUser':

        $arrExpected =["u","m","p","token"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/ip_check.php';
        $identifier = $ip;
        require 'user_fragments/addUser_auth.php';
        require 'user_fragments/addUser_checks.php';
        if($apiSettings->getSetting('captchaFile'))
            require __DIR__.'/../'.$apiSettings->getSetting('captchaFile');
        require 'user_fragments/limit_rate_check.php';
        require 'user_fragments/addUser_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'logUser':

        if(isset($_REQUEST["log"]))
            $inputs["log"] = $_REQUEST["log"];
        else
            $inputs["log"] = 'out';

        if($inputs['log']!='out')
            if(!validateThenRefreshCSRFToken($SessionHandler))
                exit(WRONG_CSRF_TOKEN);

        if($test)
            echo 'Log type: '.$inputs["log"].EOL;

        $arrExpected =["userID","m","p","sesKey","2FAType","2FACode"];

        require __DIR__ . '/../setExpectedInputs.php';
        if($inputs['log']!='out'){
            require 'user_fragments/ip_check.php';
        }
        require 'user_fragments/logUser_pre_checks_auth.php';
        require 'user_fragments/logUser_checks.php';
        if($inputs['log']!=='out'){
            if( ($inputs['log']==='temp') && ($inputs['userID']!== null) )
                $userId = $inputs['userID'];
            elseif($inputs['m']){
                $userId = $SQLHandler->selectFromTable($SQLHandler->getSQLPrefix().'USERS',['Email',$inputs['m'],'='],['ID'],[]);
                if(count($userId)>0)
                    $userId = $userId[0]['ID'];
                else
                    $userId = null;
            }
            $identifier = !empty($userId)? $userId : null;
        }
        require 'user_fragments/limit_rate_check.php';
        require 'user_fragments/logUser_post_checks_auth.php';
        require 'user_fragments/logUser_execution.php';

        if($result === 1 || $result === 5){
            $shouldCommitActions = true;
            require 'user_fragments/limit_success_check.php';
        }

        //This procedure can only return after N seconds exactly
        $timingManager->waitUntilIntervalElapsed(1);

        if(is_array($result))
            $result = json_encode($result);

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'pwdReset':

        $arrExpected =["id","code","mail","async"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/ip_check.php';
        require 'user_fragments/reset_checks.php';
        if(isset($inputs['mail'])){
            $identifier = $inputs['mail'];
            require 'user_fragments/reset_mail_limit_rate_check.php';
        }
        require 'user_fragments/pwdReset_execution.php';
        require 'user_fragments/reset_mail_limit_success.php';


        //This procedure can only return after N seconds exactly
        $timingManager->waitUntilIntervalElapsed(1);
        $pageSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/pageSettings/',$defaultSettingsParams);
        if($inputs['mail'] !== null || $inputs['async'] !== null || !$pageSettings->getSetting('pwdReset'))
            echo ($result === 0)?
                '0' : $result;

        break;

    case 'changePassword':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["newPassword"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/changePassword_auth.php';
        require 'user_fragments/changePassword_checks.php';
        require 'user_fragments/changePassword_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'regConfirm':

        $arrExpected =["id","code","mail"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/regConfirm_checks.php';
        if(isset($inputs['mail'])){
            $identifier = $inputs['mail'];
            require 'user_fragments/reset_mail_limit_rate_check.php';
        }
        require 'user_fragments/regConfirm_execution.php';
        require 'user_fragments/reset_mail_limit_success.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'mailReset':

        $arrExpected =["id","code","mail","async"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/ip_check.php';
        require 'user_fragments/reset_checks.php';
        if(isset($inputs['mail'])){
            $identifier = $inputs['mail'];
            require 'user_fragments/reset_mail_limit_rate_check.php';
        }
        require 'user_fragments/mailReset_execution.php';
        require 'user_fragments/reset_mail_limit_success.php';

        //This procedure can only return after N seconds exactly
        $timingManager->waitUntilIntervalElapsed(1);

        $pageSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/pageSettings/',$defaultSettingsParams);
        if($inputs['mail'] !== null || $inputs['async'] !== null || !$pageSettings->getSetting('mailReset'))
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'changeMail':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["newMail"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/changeMail_auth.php';
        require 'user_fragments/changeMail_checks.php';
        require 'user_fragments/changeMail_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'sendInviteMail':
    case 'createUserInvite':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["mail","extraTemplateArguments","tokenUses","token","tokenTTL","overwrite","update"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/invite_auth.php';
        require 'user_fragments/invite_checks.php';
        if($action === 'sendInviteMail')
            require 'user_fragments/sendInviteMail_execution.php';
        else
            require 'user_fragments/createUserInvite_execution.php';

        if(gettype($result) === 'array')
            foreach ($result as $token => $res)
                echo $token;
        else
            echo $result;
        break;

    case 'checkInvite':

        $arrExpected =["mail","token","async"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/checkInvite_checks.php';
        require 'user_fragments/checkInvite_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'banUser':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["minutes","id"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'user_fragments/banUser_pre_checks_auth.php';
        require 'user_fragments/banUser_checks.php';
        require 'user_fragments/banUser_post_checks_auth.php';
        require 'user_fragments/banUser_execution.php';
        echo ($result === 0)?
            '0' : $result;
        break;

    case 'changeUsername':
        echo 'TODO Implement this action - allow configurable restrictions';
        break;

    default:
        exit('Specified action is not recognized');
}

?>