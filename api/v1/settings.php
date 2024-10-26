<?php
/* This the the API that handles all the settings functions, like getting/setting settings.

 *      See standard return values at defaultInputResults.php
 *
 * Parameters:
 * "target"     - Identifier  of the setting file/table
 * "action"     - Requested action - described below
 * "params"     - Parameters, depending on action - described below
 *_________________________________________________
 * getSettingsMeta
 *      Gets all available setting collections. Note that this only includes those which were registered -
 *      there might be other setting collections that aren't registered, for one reason or another.
 *      params:
 *          none (no target - this is equivalent of calling "getSettings" with target "metaSettings")
 *      Returns:
 *          JSON encoded array, where each member is also a JSON encoded array, of the form:
 *          {
 *              <settings collection identifier - used as "target" for the other actions>: {
 *                  local:<bool, whether collection is local>
 *                  db:<bool, whether collection is exists in the db>,
 *                  [optional]title: <string, title of the settings collection>
 *              }
 *          }
 *
 *      Examples: target=siteSettings&action=getSettings
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

if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../../main/core_init.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');

if(!isset($_REQUEST["target"]) && $_REQUEST["action"] !== 'getSettingsMeta')
    exit('Target settings not specified!');

if($test)
    echo 'Testing mode!'.EOL;

$target = $_REQUEST["target"] ?? '';

$action = $_REQUEST["action"];

if(!checkApiEnabled('settings',$apiSettings,$SecurityHandler,$_REQUEST['action']))
    exit(API_DISABLED);

if(isset($_REQUEST['params']))
    $params = json_decode($_REQUEST['params'],true);
else
    $params = null;

switch($action){

    case 'getSetting':
        require 'settings_fragments/get_auth.php';
        require 'settings_fragments/get_checks.php';
        require 'settings_fragments/getSetting_execution.php';
        echo ($result === 0)?
            '0' : $result;
        break;

    case 'getSettingsMeta':
    case 'getSettings':
        if($action === 'getSettingsMeta')
            $target = 'metaSettings';
        require 'settings_fragments/get_auth.php';
        require 'settings_fragments/get_checks.php';
        require 'settings_fragments/getSettings_execution.php';
        echo json_encode($result);
        break;

    case 'setSetting':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);
        require 'settings_fragments/set_auth.php';
        require 'settings_fragments/set_checks.php';
        require 'settings_fragments/setSetting_execution.php';
        echo $result === true ?
            '1' : '0';
        break;

    case 'unsetSetting':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);
        require 'settings_fragments/unset_auth.php';
        require 'settings_fragments/unset_checks.php';
        require 'settings_fragments/unsetSetting_execution.php';
        echo ($result === false)?
            '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}
