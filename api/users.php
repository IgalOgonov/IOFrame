<?php
/* This the the API that handles all the user related functions.
 * Many of the procedures here are timing safe, meaning they will return in constant times (well, constant intervals)
 *
 *      See standard return values at defaultInputResults.php
 *_________________________________________________
 * addUser
 *      - Adds (registers) a user
 *        m: requested mail
 *        p: requested password
 *        u: requested username (optional, depending on a setting)
 *        Returns integer code:
 *              0 - success
 *              1 - username already in use
 *              2 - email already in use
 *              3 - server error
 *
 *        Examples: action=addUser&u=test1&m=test@example.com&p=A5432524gf54
 *_________________________________________________
 * logUser [CSRF protected] [Timing protected]
 *      - Logs in or out
 *        log: login type ('out','temp' or other) - default 'out'
 *        m: user mail  - required on any login
 *        p: user password  - required on full login
 *        userID: identifier of current device, used for temp login, or for full login using "rememberMe"
 *        sesKey: used to relog using temp login
 *        Returns integer code:
 *              0 all good - without rememberMe
 *              1 username/password combination wrong
 *              2 expired auto login token
 *              3 login type not allowed
 *              32-byte hex encoded session ID string - The token for your next automatic relog, if you logged automatically.
 *              JSON encoded array of the form {'iv'=><32-byte hex encoded string>,'sesID'=><32-byte hex encoded string>}
 *
 *        Examples: action=logUser&log=in&m=test@example.com&p=A5432524gf54
 *                  action=logUser&log=out
 *                  action=logUser&log=temp&m=test@example.com&sesKey=8836ac46fbdfa61a2a125991f987079d86903169a9e109557326fec1c71ff5ef&userID=abc6379af765afafa1bad51b084bad48&sesKey=A5432524gf54
 *_________________________________________________
 * pwdReset [Timing protected]
 *      - Sends the user a password reset email, or confirms an existing reset code and user session as eligible
 *        to reset the password for a few minutes (depends on settings)
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
 *      - Changes user password. Needs to be authorized via pwdReset first.
 *        newPassword: new password
 *        Returns integer code:
 *                 0 - All good
*                  1 - User ID does not exist
*                  2 - Time to change expired!
 *
 *        Examples: action=changePassword&newPassword=Test012345
 *_________________________________________________
 * regConfirm
 *      - Sends a user a registration email, or confirms an existing registration code and user session as eligable
 *        to reset the password for a few minutes (depends on settings)
 *        --- TO REQUEST A RESET CODE ---
 *        mail: Email of the account
 *        --- TO APPLY A RESET CODE ---
 *        id: ID of relevant user
 *        code: Confirmation code
 *        async: Used to specify you don't want to be redirected.
 *        Returns integer code:
 *                 0 - All good
*                  1 - User ID does not exist
*                  2 - Wrong code
*                  3 - Code expired
 *        Also, on confirmation, will actually redirect you to a specific page (set in pageSettings) unless "async" is set in the request.
 *
 *        Examples: action=regConfirm&id=4&code=GtIOsxkfbA92iGp0MsSt70GkfSDTFcUZlyd0I2MJMflz1h6kmI
 *                  action=regConfirm&mail=example@example.com
 *_________________________________________________
 * mailReset [Timing protected]
 *      - Sends a reset mail similar to pwdReset, but for the user mail
 *        All codes and inputs similar to pwdReset.
 *
 *        Examples: action=mailReset&id=4&code=GtIOsxkfbA92iGp0MsSt70GkfSDTFcUZlyd0I2MJMflz1h6kmI
 *                  action=mailReset&mail=example@example.com
 *
 *_________________________________________________
 * changeMail [CSRF protected]
 *      - Similar to changePassword, but for the user mail.
 *        newPassword: new password
 *        Returns integer code:
 *                 0 - All good
*                  1 - User ID does not exist
*                  2 - Time to change expired!
 *
 *        Examples: action=changeMail&newMail=example@example.com
 *
 *_________________________________________________
 * banUser [CSRF protected]
 *      - Bans user for a certain number of minutes
 *        minutes: How many minutes to ban for
 *        id: ID of the user to ban
 *        Returns integer code:
 *                 0 - All good
*                  1 - User ID does not exist
 *
 *        Examples: action=banUser&id=1&minutes=60000
 *
 * */
const BLACKLISTED_IP = 'BLACKLISTED_IP';

if(!defined('coreInit'))
    require __DIR__ . '/../main/coreInit.php';

require 'defaultInputChecks.php';
require 'defaultInputResults.php';
require 'userAPI_fragments/definitions.php';
require 'CSRF.php';
require __DIR__ . '/../IOFrame/Util/timingManager.php';

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];

if($test)
    echo 'Testing mode!'.EOL;

//Handle inputs
$inputs = [];

//Timing manager
$timingManager = new IOFrame\Util\timingManager();
//Most of the actions need to be timing safe
$timingManager->start();

switch($action){
    case 'addUser':

        $arrExpected =["u","m","p"];

        require 'setExpectedInputs.php';
        require 'userAPI_fragments/addUser_checks.php';
        require 'userAPI_fragments/addUser_execution.php';

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

        $arrExpected =["userID","m","p","sesKey"];

        require 'setExpectedInputs.php';
        require 'userAPI_fragments/logUser_checks.php';
        require 'userAPI_fragments/logUser_execution.php';

        if($result === 1){
            if(!isset($SecurityHandler))
                $SecurityHandler = new IOFrame\Handlers\SecurityHandler(
                    $settings,
                    $defaultSettingsParams
                );
            //Mark the IP as one who committed an incorrect login attempt
            $SecurityHandler->commitEventIP(0,[]);
            //Mark the user who had a bad login attempt into
            if($inputs['userID']!== null)
                $id = $inputs['userID'];
            else{
                $id = $SQLHandler->selectFromTable($SQLHandler->getSQLPrefix().'USERS',['Email',$inputs['m'],'='],['ID'],[]);
                if(count($id)>0)
                    $id = $id[0]['ID'];
                else
                    $id = null;
            }
            if($id)
                $SecurityHandler->commitEventUser(0,$id);
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

        require 'setExpectedInputs.php';
        require 'userAPI_fragments/reset_checks.php';
        require 'userAPI_fragments/pwdReset_execution.php';


        //This procedure can only return after N seconds exactly
        $timingManager->waitUntilIntervalElapsed(1);

        if($inputs['mail'] !== null || $inputs['async'] !== null || !$pageSettings->getSetting('pwdReset'))
            echo ($result === 0)?
                '0' : $result;

        break;

    case 'changePassword':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["newPassword"];

        require 'setExpectedInputs.php';
        require 'userAPI_fragments/changePassword_checks.php';
        require 'userAPI_fragments/changePassword_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'regConfirm':

        $arrExpected =["id","code","mail"];

        require 'setExpectedInputs.php';
        require 'userAPI_fragments/regConfirm_checks.php';
        require 'userAPI_fragments/regConfirm_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'mailReset':

        $arrExpected =["id","code","mail","async"];

        require 'setExpectedInputs.php';
        require 'userAPI_fragments/reset_checks.php';
        require 'userAPI_fragments/mailReset_execution.php';

        //This procedure can only return after N seconds exactly
        $timingManager->waitUntilIntervalElapsed(1);

        if($inputs['mail'] !== null || $inputs['async'] !== null || !$pageSettings->getSetting('mailReset'))
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'changeMail':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["newMail"];

        require 'setExpectedInputs.php';
        require 'userAPI_fragments/changeMail_checks.php';
        require 'userAPI_fragments/changeMail_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'banUser':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["minutes","id"];

        require 'setExpectedInputs.php';
        require 'userAPI_fragments/banUser_checks.php';
        require 'userAPI_fragments/banUser_execution.php';
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