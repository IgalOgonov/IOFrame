<?php
/* This the the API that handles all the settings functions, like getting/setting settings.

 *      See standard return values at defaultInputResults.php
 *
 * Parameters:
 * "target"     - Name/URL of the setting file/table
 * "action"     - Requested action - described bellow
 * "params"     - Parameters, depending on action - described bellow
 *_________________________________________________
 * getSetting
 *      Gets one setting.
 *      params:
 *          'settingName' - Name of the setting you want to get
 *      Returns:
 *          The setting, be it string number or bool.
 *
 *      Examples: target=siteSettings&action=getSetting&params={"settingName":"maxInacTime"}
 *_________________________________________________
 * getSettings
 *      Gets all the settings of the target table/file.
 *      params:
 *          none (only a target)
 *      Returns:
 *          json encoded array of settings
 *
 *      Examples: target=siteSettings&action=getSettings
 *_________________________________________________
 * setSetting [CSRF protected]
 *      Modifies or creates a setting.
 *      params:
 *          'settingName' - Name of the setting.
 *          'settingValue' - Value of the setting.
 *          'createNew'    - Defaults to false. Whether to create a new setting if does not exist, or only modify existing ones.
 *      Returns:
 *          true or false ('1' or '0').
 *
 *      Examples:
 *          target=siteSettings&action=setSetting&params={"settingName":"maxInacTime","settingValue":7200}
 *          target=siteSettings&action=setSetting&params={"settingName":"meaninglessNumber","settingValue":43,"createNew":1}
 *_________________________________________________
 * unsetSetting [CSRF protected]
 *      Deletes a setting.
 *      params:
 *          'settingName' - Name of the setting.
 *      Returns:
 *          true or false ('1' or '0') on success or failure
 *          -1 if the setting to unset didn't exist.
 *
 *          target=siteSettings&action=unsetSetting&params={"settingName":"meaninglessNumber"}
 *          target=siteSettings&action=unsetSetting&params={"settingName":"maxInacTime"}
 * */

if(!defined('coreInit'))
    require __DIR__ . '/../main/coreInit.php';


require 'defaultInputChecks.php';
require 'defaultInputResults.php';
require 'CSRF.php';

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');

if(!isset($_REQUEST["target"]))
    exit('Target settings not specified!');

if($test)
    echo 'Testing mode!'.EOL;

$target = $_REQUEST["target"];

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];

if(isset($_REQUEST['params']))
    $params = json_decode($_REQUEST['params'],true);
else
    $params = null;

switch($action){

    case 'getSetting':
        require 'settingsAPI_fragments/get_checks.php';
        require 'settingsAPI_fragments/getSetting_execution.php';
        echo ($result === 0)?
            '0' : $result;
        break;

    case 'getSettings':
        require 'settingsAPI_fragments/get_checks.php';
        require 'settingsAPI_fragments/getSettings_execution.php';
        echo json_encode($result);
        break;

    case 'setSetting':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);
        require 'settingsAPI_fragments/set_checks.php';
        require 'settingsAPI_fragments/setSetting_execution.php';
        echo $result === true ?
            '1' : '0';
        break;

    case 'unsetSetting':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);
        require 'settingsAPI_fragments/unset_checks.php';
        require 'settingsAPI_fragments/unsetSetting_execution.php';
        echo ($result === false)?
            '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}


?>