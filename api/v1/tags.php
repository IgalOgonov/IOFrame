<?php
/*  Responsible for all regular and category tags.
 * Current list is:
 *      Base: license, content-type, content-tag, user-icon, user-border, content-report-reason, user-report-reason
 *      Category: content-category-tags, content-category-tags
 *
 *      See standard return values at defaultInputResults.php
 *_________________________________________________
 * getManifest
 *      - Gets the two tag settings - availableTagTypes and availableCategoryTagTypes
 *        updatedAfter - string, unix timestamp, only return settings if updated after this date
 *
 *        Examples: action=getManifest
 *
 *        Returns Array of the form:
 *
 *          [
 *           availableTagTypes => as in setting file
 *           availableCategoryTagTypes => as in setting file
 *          ],
 *
 *          or, if updatedAfter provided, may instead return codes of the form:
 *              -1 - unexpected error
 *              0  - everything is up to date
 *              'INPUT_VALIDATION_FAILURE' - invalid timestamp
 *_________________________________________________
 * getBaseTags
 *      - Gets all available tags of certain type:
 *          type: string, tag type you wish to get
 *          [admin]getMeta: bool, default false - whether to get meta information that's available only to admins
 *          limit: int, when getAll is true, you may limit the number of items you get
 *          offset: int, default 0 - used for pagination purposes with limit
 *          includeRegex      - string, get tag identifiers including this regex
 *          excludeRegex      - string, get tag identifiers excluding this regex
 *          createdAfter      - int, default null - Only return items created after this date.
 *          createdBefore     - int, default null - Only return items created before this date.
 *          changedAfter      - int, default null - Only return items last changed after this date.
 *          changedBefore     - int, default null - Only return items last changed  before this date.
 *          [admin]weightFrom     - int, default null - Only return items with at least this weight.
 *          [admin]weightTo     - int, default null - Only return items with at most this weight.
 *
 *
 *        Examples: action=getBaseTags&type=default-article-tags
 *                  action=getBaseTags&type=default-article-tags&limit=50&offset=10
 *
 *        Returns Array of the form:
 *
 *          [
 *           <tag type>/<tag identifier> => {
 *                               [optional]<defined parameter from tagSettings> => parameter,
 *                               [optional]'img' => [
 *                                                      'address' => string, either relative to local image root or absolute,
 *                                                      'local' => bool, default true - whether to treat address as relative or absolute
 *                                                      'name' => string, default null - image "pretty" name
 *                                                      'alt' => string, default null - image alt
 *                                                      'desc' => string, default null - image description
 *                                                  ]
 *                              },
 *           ...,
 *          '@' => [
 *                  '#' => int, Number of results you'd see if limit wasn't used
 *                 ]
 *          ]
 *
 *_________________________________________________
 * getCategoryTags
 *      - Gets all available tags of certain type, in a certain category:
 *          type: string, tag type you wish to get
 *          category: int, category identifier you wish to get
 *          getMeta: bool, default false - whether to get meta information that's available only to admins
 *          limit: int, when getAll is true, you may limit the number of items you get
 *          offset: int, default 0 - used for pagination purposes with limit
 *          includeRegex      - string, get tag identifiers including this regex
 *          excludeRegex      - string, get tag identifiers excluding this regex
 *          createdAfter      - int, default null - Only return items created after this date.
 *          createdBefore     - int, default null - Only return items created before this date.
 *          changedAfter      - int, default null - Only return items last changed after this date.
 *          changedBefore     - int, default null - Only return items last changed  before this date.
 *
 *        Examples: action=getCategoryTags&type=default-article-tags
 *                  action=getCategoryTags&type=default-article-tags&category=1&test_meta_color_required
 *
 *        Returns Array of the form:
 *
 *          [
 *           <tag type>/<category identifier>/<tag identifier> => {
 *                               [optional]<defined parameter from tagSettings> => parameter,
 *                               [optional]'img' => [
 *                                                      'address' => string, either relative to local image root or absolute,
 *                                                      'local' => bool, default true - whether to treat address as relative or absolute
 *                                                      'name' => string, default null - image "pretty" name
 *                                                      'alt' => string, default null - image alt
 *                                                      'desc' => string, default null - image description
 *                                                  ]
 *                              },
 *           ...,
 *          '@' => [
 *                  '#' => int, Number of results you'd see if limit wasn't used
 *                 ]
 *          ]
 *
 *_________________________________________________
 * setBaseTags
 *      - Creates new base tags
 *        type : string, tags type (1st half of identifier)
 *        tags : JSON encoded array where each item is an object of the form:
 *          {
 *              identifier : string, tags identifier (2nd half of identifier)
 *              [optional]<defined parameter from tagSettings> => parameter
 *              [img] : string - '@' to unset - image address.
 *          }
 *        overwrite : bool, default false - allows overwriting existing items
 *        update : bool, default false - only allows updating of existing items
 *
 *        Examples: action=setBaseTags&type=default-article-tags&tags=[{"identifier":"test","eng":"test"}]
 *
 *        Returns JSON encoded array of the form <type>/<identifier> => <code>,
 *          with int codes (for each tag):
 *         -2 dependency (image) no longer exists
 *         -1 db connection error
 *          0 done
 *          1 item exists and override is false OR item does not exist and update is true
 *
 *_________________________________________________
 * setCategoryTags
 *      - Creates new base tags
 *        type : string, tags type (1st part of identifier)
 *        category : int, category identifier (2st part of identifier)
 *        tags : JSON encoded array where each item is an object of the form:
 *          {
 *              identifier : string, tags identifier (3nd part of identifier)
 *              [optional]<defined parameter from tagSettings> => parameter
 *              [img] : string - '@' to unset - image address.
 *          }
 *        overwrite : bool, default false - allows overwriting existing items
 *        update : bool, default false - only allows updating of existing items
 *
 *        Examples: action=setCategoryTags&type=category-test&category=1&tags=[{"identifier":"test","test_meta_color_required":"ffffff"}]
 *
 *        Returns JSON encoded array of the form <type>/<category>/<identifier> => <code>,
 *          with int codes (for each tag):
 *         -2 dependency (image) no longer exists
 *         -1 db connection error
 *          0 done
 *          1 item exists and override is false OR item does not exist and update is true
 *
 *_________________________________________________
 * deleteBaseTags
 *      - Deletes base tags
 *        type : string, tags type (1st part of identifier)
 *        identifiers : json encoded array of strings, which are item identifiers of the form <name>
 *
 *        Examples: action=deleteBaseTags&type=default-article-tags&identifiers=["test","test2"]
 *
 *        Returns Int Codes:
 *         -1 db connection error
 *          0 done
 *
 *_________________________________________________
 * deleteCategoryTags
 *      - Deletes base tags
 *        type : string, tags type (1st part of identifier)
 *        category : int, category identifier
 *        identifiers : json encoded array of strings, which are item identifiers of the form <name>
 *
 *        Examples: action=deleteCategoryTags&type=category-test&category=1&identifiers=["test","test2"]
 *
 *        Returns Int Codes:
 *         -1 db connection error
 *          0 done
 *_________________________________________________
 * renameBaseTag
 *      - Renames base tags
 *        type : string, tag type
 *        name : string, tag name
 *        newName : string, new tag name
 *
 *        Examples: action=renameBaseTag&type=default-article-tags&name=test&newName=newTest
 *
 *        Returns Int Codes:
 *         -1 db connection error
 *          0 done
 *
 *_________________________________________________
 * renameCategoryTag
 *      - Renames category tags
 *        type : string, tag type
 *        category : int, category identifier
 *        name : string, tag name
 *        newName : string, new tag name
 *
 *        Examples: action=renameCategoryTag&type=category-test&category=1&name=test&newName=newTest
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
require 'tags_fragments/definitions.php';
require __DIR__ . '/../../IOFrame/Handlers/TagHandler.php';
require __DIR__ . '/../../IOFrame/Managers/v1APIManager.php';

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];

if(!checkApiEnabled('tags',$apiSettings,$SecurityHandler,$_REQUEST['action']))
    exit(API_DISABLED);

if($test)
    echo 'Testing mode!'.EOL;

$APIManager = new \IOFrame\Managers\v1APIManager($settings,$apiSettings,$defaultSettingsParams);
//$TagHandlerBaseTagTypes and $TagHandlerCategoryTagTypes defined in definitions
//in a custom API that uses those fragments, and tag settings, this should be defined to handle the relevant tag types
$TagHandler = new \IOFrame\Handlers\TagHandler(
    $settings,
    array_merge($defaultSettingsParams, ['siteSettings'=>$siteSettings,'baseTags'=>$TagHandlerBaseTagTypes,'categoryTags'=>$TagHandlerCategoryTagTypes])
);

//Handle inputs
$inputs = [];

//Standard pagination inputs
$standardPaginationInputs = ['getMeta','limit','offset','includeRegex','excludeRegex','createdAfter','createdBefore',
    'changedAfter','changedBefore','weightFrom','weightTo'];

switch($action){

    case 'getBaseTags':
    case 'getCategoryTags':

        $categories =  $action == 'getCategoryTags';
        $extra = $categories ? ["type","category"] : ["type"];
        $arrExpected = array_merge($extra,$standardPaginationInputs);

        require __DIR__.'/../setExpectedInputs.php';
        require 'tags_fragments/tagTypeDefinitions.php';
        require 'tags_fragments/getTags_checks.php';
        require 'tags_fragments/getTags_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'setBaseTags':
    case 'setCategoryTags':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $categories =  $action == 'setCategoryTags';
        $arrExpected =["type","tags","overwrite","update"];
        if($categories)
            $arrExpected[] = "category";

        require __DIR__.'/../setExpectedInputs.php';
        require 'tags_fragments/tagTypeDefinitions.php';
        require 'tags_fragments/setTags_checks.php';
        require 'tags_fragments/setTags_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'deleteBaseTags':
    case 'deleteCategoryTags':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $categories =  $action == 'deleteCategoryTags';
        $arrExpected =["type","identifiers"];
        if($categories)
            $arrExpected[] = "category";

        require __DIR__.'/../setExpectedInputs.php';
        require 'tags_fragments/tagTypeDefinitions.php';
        require 'tags_fragments/deleteTags_checks.php';
        require 'tags_fragments/deleteTags_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'renameBaseTag':
    case 'renameCategoryTag':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $categories =  $action == 'renameCategoryTag';
        $arrExpected =["type","name","newName"];
        if($categories)
            $arrExpected[] = "category";

        require __DIR__.'/../setExpectedInputs.php';
        require 'tags_fragments/tagTypeDefinitions.php';
        require 'tags_fragments/renameTag_checks.php';
        require 'tags_fragments/renameTag_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'getManifest':
        /*Not even worth its own includes*/

        $arrExpected =["updatedAfter"];
        require __DIR__.'/../setExpectedInputs.php';
        require 'tags_fragments/getManifest_checks.php';
        require 'tags_fragments/getManifest_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}