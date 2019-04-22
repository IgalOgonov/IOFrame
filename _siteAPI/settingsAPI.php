<?php
/* This the the API that handles all the settings functions, like getting/setting settings.
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
 * setSetting
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
 * unsetSetting
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

require_once __DIR__ . '/../_Core/coreInit.php';


require_once 'defaultInputChecks.php';

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');

if(!isset($_REQUEST["target"]))
    exit('Target settings not specified!');

if(isset($_REQUEST['params']))
    $params = json_decode($_REQUEST['params'],true);
else
    $params = null;

if($test)
    echo 'Testing mode!'.EOL;

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');

if(isset($_REQUEST['params']))
    $params = json_decode($_REQUEST['params'],true);
else
    $params = null;

switch($_REQUEST["action"]){

    case 'getSetting':
        require_once 'settingsAPI_fragments/get_checks.php';
        require_once 'settingsAPI_fragments/getSetting_execution.php';
        echo $result;
        break;

    case 'getSettings':
        require_once 'settingsAPI_fragments/get_checks.php';
        require_once 'settingsAPI_fragments/getSettings_execution.php';
        echo json_encode($result);
        break;

    case 'setSetting':
        require_once 'settingsAPI_fragments/set_checks.php';
        require_once 'settingsAPI_fragments/setSetting_execution.php';
        echo $result === true ?
            '1' : '0';
        break;

    case 'unsetSetting':
        require_once 'settingsAPI_fragments/unset_checks.php';
        require_once 'settingsAPI_fragments/unsetSetting_execution.php';
        echo ($result === false)?
            '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}


?>