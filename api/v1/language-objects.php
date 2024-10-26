<?php
/*  Responsible for all  language objects.
 *
 *      See standard return values at defaultInputResults.php
 *_________________________________________________
 * getLanguageObjects
 *      - Gets language objects
 *          names: json encoded array, default [] - if set, gets specific objects. Required if not admin.
 *          language - string, default null - which language to unpack the object with. Required if not admin.
 *          [!names]limit: int, when getting all items, you may limit the number of items you get
 *          [!names]offset: int, default 0 - used for pagination purposes with limit
 *          [!names]includeRegex      - string, get item identifiers including this regex
 *          [!names]excludeRegex      - string, get item identifiers excluding this regex
 *          [!names]createdAfter      - int, default null - Only return items created after this date.
 *          [!names]createdBefore     - int, default null - Only return items created before this date.
 *          [!names]changedAfter      - int, default null - Only return items last changed after this date.
 *          [!names]changedBefore     - int, default null - Only return items last changed  before this date.
 *
 *
 *        Examples: action=getLanguageObjects&limit=5&offset=1
 *                  action=getLanguageObjects&names=["test","test2"]
 *                  action=getLanguageObjects&names=["test","test2"]&language=eng
 *
 *        Returns Array of the form:
 *
 *          [
 *           <object name> => false if object doesnt exist, raw object if getSource is true, or object parsed by LanguageObjectHandler->getLoadedObjects()
 *           ...,
 *          '@' => [
 *                  '#' => int, Number of results you'd see if limit wasn't used
 *                 ]
 *          ]
 *
 *_________________________________________________
 * setLanguageObjects
 *      - Creates or updates language objects
 *        objects : JSON encoded object where each item is an object of the form:
 *          {
 *              name : string, json encoded object of CHANGES
 *          }
 *        overwrite : bool, default false - allows overwriting existing items
 *        update : bool, default false - only allows updating of existing items
 *
 *        Examples: action=setLanguageObjects&objects={"test":{"title":{"eng":"test","de":"test"}}}
 *                  action=setLanguageObjects&objects={"test2":{"title":{"eng":"test2","de":"test2"},"deep":{"garbage":"garbageData","subTitle":{"eng":"subtitle eng","de":"subtitle ger", "garbageString":"garbage"} } } }
 *                  action=setLanguageObjects&objects={"test2":{"title":{"eng":"test3","de":"test3"}}}&update=1
 *                  action=setLanguageObjects&objects={"languageObjectsCPMain":{"operation-confirm":{"eng":"Confirm Eng","de":"Confirm Ger"}}}
 *
 *        Returns JSON encoded array of the form <type>/<identifier> => <code>,
 *          with int codes (for each tag):
 *         -1 db connection error
 *          0 done
 *          1 item exists and override is false OR item does not exist and update is true
 *_________________________________________________
 * deleteLanguageObjects
 *      - Deletes language objects
 *        identifiers : json encoded array of strings, which are item identifiers of the form <name>
 *
 *        Examples: action=deleteLanguageObjects&identifiers=["test","test2"]
 *
 *        Returns Int Codes:
 *         -1 db connection error
 *          0 done
 *          1 nothing sent to delete
 *_________________________________________________
 * renameLanguageObject
 *      - Renames language objects
 *        name : string, object name
 *        newName : string, new object name
 *
 *        Examples: action=renameLanguageObject&name=test&newName=newTest
 *
 *        Returns Int Codes:
 *         -1 db connection error
 *          0 done
 *_________________________________________________
 * setPreferredLanguage
 *      - Sets a new preferred language for the user
 *        lang : string, new language, has to be one of those supported by the system
 *
 *        Examples: action=setPreferredLanguage&lang=de
 *
 *        Returns Int Codes:
 *         -1 db connection error
 *          0 done
 *
 * */

if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../../main/core_init.php';

require __DIR__ .'/../apiSettingsChecks.php';
require 'standardDefinitions.php';
require __DIR__.'/../defaultInputChecks.php';
require __DIR__.'/../defaultInputResults.php';
require __DIR__.'/../CSRF.php';
require 'language-objects_fragments/definitions.php';
require __DIR__ . '/../../IOFrame/Handlers/LanguageObjectHandler.php';
require __DIR__ . '/../../IOFrame/Managers/v1APIManager.php';

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];

if(!checkApiEnabled('language-objects',$apiSettings,$SecurityHandler,$_REQUEST['action']))
    exit(API_DISABLED);

if($test)
    echo 'Testing mode!'.EOL;

$APIManager = new \IOFrame\Managers\v1APIManager($settings,$apiSettings,$defaultSettingsParams);
$LanguageObjectHandler = new \IOFrame\Handlers\LanguageObjectHandler(
    $settings,
    array_merge($defaultSettingsParams, ['siteSettings'=>$siteSettings])
);

//Handle inputs
$inputs = [];

//Standard pagination inputs
$standardPaginationInputs = ['limit','offset','includeRegex','excludeRegex','createdAfter','createdBefore',
    'changedAfter','changedBefore'];

switch($action){

    case 'getLanguageObjects':

        $arrExpected = array_merge(["names","language"],$standardPaginationInputs);

        require __DIR__.'/../setExpectedInputs.php';
        require 'language-objects_fragments/getLanguageObjects_checks.php';
        require 'language-objects_fragments/getLanguageObjects_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'setLanguageObjects':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["objects","overwrite","update"];

        require __DIR__.'/../setExpectedInputs.php';
        require 'language-objects_fragments/setLanguageObjects_checks.php';
        require 'language-objects_fragments/setLanguageObjects_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'deleteLanguageObjects':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["identifiers"];

        require __DIR__.'/../setExpectedInputs.php';
        require 'language-objects_fragments/deleteLanguageObjects_checks.php';
        require 'language-objects_fragments/deleteLanguageObjects_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'renameLanguageObject':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["name","newName"];

        require __DIR__.'/../setExpectedInputs.php';
        require 'language-objects_fragments/renameLanguageObject_checks.php';
        require 'language-objects_fragments/renameLanguageObject_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'setPreferredLanguage':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["lang"];

        require __DIR__.'/../setExpectedInputs.php';
        require 'language-objects_fragments/setPreferredLanguage_checks.php';
        require 'language-objects_fragments/setPreferredLanguage_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}