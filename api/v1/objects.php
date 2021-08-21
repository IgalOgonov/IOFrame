<?php
/**
 * Handles operations related to objects, those which are saved in the tables Object_Cache and Object_Cache_Meta.
 * Extension:   It is possible to include this API for the extending API, where you define a function "parseObjectContent($content)"
 *              that parses the contents for each object.
 * Reminder :   Some input checking on here is redundant, yet is still present for security reasons.
 *              On the other hand, some authorization checks are done in ObjectHandler itself.
 *
 * See standard return values at defaultInputResults.php
 *
 * Parameters:
 * "action"   -   The type of action needed. Create, Read (or Read Groups), Update, Delete, Assign, Get Assignments or Remove Assignment for (an) object/s.
 * "params" -   The target collection of objects/groups.
 *_________________________________________________
 *        r (read)
 *              params:
 *              a JSON encoded 2D object array of the form:
 *              {
 *                  "@": {"<objectID1>":"<timeObj1Updated>", ...},
 *                  "<groupName>": {"@":"<timeGroupUpdated>", "<objectID2>":"<timeObj2Updated>", ...},
 *                  ...
 *              }
 *              Where
 *                  "@" as group name is the "group" of all the group-less objects, "@" as group member denotes the last time the whole group was updated.
 *
 *              Returns:
 *              All the objects whose "Last_Updated" is newer than specified by the user, in an array of the form:
 *              {
 *              "<ObjectID1>":"<Contents>",
 *              "<ObjectID2>":"<Contents>",
 *              ...
 *              "Errors":"{"<ObjectID>":"<ErrorID>"}",
 *              "groupMap":{"<ObjectID>":"GroupName",...}
 *              }
 *              Where possible error codes are:
 *                  0 if you can view the object yet $updated is bigger than the object's Last_Updated field.
 *                  1 if object of specified ID doesn't exist.
 *                  2 if insufficient authorization to view the object.
 *                  Will simply ignore groups whose "@" is lower than in the database, aka the user is up to date.
 *
 *          Examples:
 *              action=r&params={"courses":{"15":0,"16":0,"17":0,"18":0,"19":0,"20":0,"21":0,"22":0,"23":0,"24":0,"25":0,"26":0,"27":0,"@":0}}&req=test
 *_________________________________________________
 *        rg (read group)
 *              params:
 *              a JSON array of the form:
 *              {
 *                  "groupName":<Group Name>,
 *                  "updated":<Last time users objects were updated>
 *              }
 *              Notice it is much more wasteful/slow than querying objects directly.
 *
 *              Returns:
 *              either an integer, or almost the same array as R (Read), where:
 *              Integer codes:
 *                  0 - The whole group is up to date
 *                  1 - The group with this name does not exist
 *              Array codes of the form <ID:Code>
 *                  0 if you can view the object yet $updated is bigger than the object's Last_Updated field.
 *                  1 - CANNOT BE RETURNED. If You are "missing" an object ID, it means that object isn't part of the group anymore or was deleted.
 *                  2 if insufficient authorization to view the object.
 *
 *              If an object isn't returned, it means it's not in the group! If no objects are returned, either all the objects
 *              got deleted or changed groups (making it empty either way).
 *          Examples:
 *              action=rg&params={"groupName":"courses","updated":1523379254}&req=test
 *_________________________________________________
 *        c (create) [CSRF protected]
 *              params:
 *              a JSON array of the form:
 *              {
 *                  "obj":"<Contents>",
 *                  ["minViewRank":<Number>],  // Default -1
 *                  ["minModifyRank":<Number>],// Default 0
 *                  ["group":"<Group Name>"],  // Default null
 *              }
 *              where all is self explanatory if you know the structure of the objects class.
 *
 *              Returns:
 *              "ID":<ObjectID> on Success
 *                  1   Illegal input
 *                  2   Group exists, insufficient authorization to add object to group
 *                  3   minViewRank and minModifyRank need to be lower or equal to your own
 *                  4   Other error
 *          Examples:
 *              action=c&params={"obj":"test01_@%23$_(){}[]","minViewRank":12,"minModifyRank":2,"group":"g1"}&req=test
 *_________________________________________________
 *         u (update) [CSRF protected]
 *              params:
 *              a JSON array of the form:
 *              {
 *                  "id":"<ObjectID>",
 *                  ["content":"<Object Contents>"],                   // Default null
 *                  ["group":"<Group Name>"],                          // Default null
 *                  ["newVRank":<Number>],                             // Default null
 *                  ["newMRank":<Number>],                             // Default null
 *                  ["mainOwner":<Number>],                            // Default null
 *                  ["addOwners":JSON Array {"OwnerID1":OwnerID1,...}], // Default null
 *                  ["remOwners":JSON Array {"OwnerID1":OwnerID1,...}], // Default null
 *              }
 *              where all is self explanatory if you know the structure of the objects class.
 *
 *              Returns:
 *                  0 on success.
 *                  1 illegal input
 *                  2 if insufficient authorization to modify the object.
 *                  3 if object can't be moved into the requested group, for group auth reasons
 *                  4 object doesn't exist
 *                  5 different error
 *          Examples:
 *              action=u&params={"id":9,"content":"Test content!**^","group":"g2","newVRank":-1,"newMRank":2,"mainOwner":1,"addOwners":{"2":2,"3":3},"remOwners":{"4":4,"3":3}}&req=test
 *_________________________________________________
 *         d (delete) [CSRF protected]
 *              params:
 *              a JSON array of the form:
 *              {
 *                  "id":"<ObjectID>",
 *                  ["time":<Number>],              // Default 0
 *                  ["after":"false/true"]         // Default true
 *              }
 *              where all is self explanatory if you know the structure of the objects class.
 *
 *              Returns:
 *                  0 on success.
 *                  1 if id doesn't exists.
 *                  2 if insufficient authorization to modify the object.
 *                  3 object exists, too old/new to delete
 *          Examples:
 *              action=d&params={"id":16}&req=test
 *              action=d&params={"id":16,"time":1523379256,"after":0}&req=test
 *_________________________________________________
 *          a (assign) [CSRF protected]
 *              params:
 *              a JSON array of the form:
 *               {
 *                  "id":"<ObjectID>",
 *                  "page":"<path/to/page.php>"
 *              }
 *              where all is self explanatory if you know the structure of the objects class.
 *
 *              Returns:
 *                  0 on success.
 *                  1 if id doesn't exists.
 *                  2 if insufficient authorization to modify the object.
 *                  3 if insufficient authorization to assign objects to pages.
 *          Examples:
 *              action=a&params={"id":16,"page":"testPage.php"}&req=test
 *_________________________________________________
 *          ra (remove assignment) [CSRF protected]
 *              params:
 *              a JSON array of the form:
 *               {
 *                  "id":"<ObjectID>",
 *                  "page":"<path/to/page.php>",
 *              }
 *              where all is self explanatory if you know the structure of the objects class.
 *
 *              Returns:
 *                  0 on success.
 *                  1 if object or page id don't exist.
 *                  2 if insufficient authorization to modify the object.
 *                  3 if insufficient authorization to remove object/page assignments.
 *          Examples:
 *              action=ra&params={"id":16,"page":"testPage.php"}&req=test
 *              action=ra&params={"id":16,"page":"CV/CV.php"}&req=test
 *_________________________________________________
 *           ga (get assignment)
 *              params:
 *              a JSON array of the form:
 *              "pages":{
 *                      "pageName":<Date-up-to-which-you-are-to-date> (defaults to 0),
 *                      ...
 *                  }
 *              where all is self explanatory if you know the structure of the objects class.
 *
 *              Returns:
 *                  A JSON array of the objects, of the form {"ID":"ID",...}, if $time < Last_Updated
 *                  0 if Last_Updated < $time
 *                  1 if the page doesn't exist
 *          Examples:
 *              action=ga&params={"page":"CV/CV.php","date":0}&req=test
 *
 */

if(!defined('coreInit'))
    require __DIR__ . '/../../main/coreInit.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';

require __DIR__ . '/../../IOFrame/Handlers/ObjectHandler.php';
//Session Info
$sesInfo = isset($_SESSION['details'])? json_decode($_SESSION['details'],true) : null;

//You must specify the type of operation
if(!isset($_REQUEST["action"])) {
    if($test)
        echo 'Operation type unset!';
    die('-1');
}
//Parameters are needed for all possible operations
if(!isset($_REQUEST["params"])) {
    if($test)
        echo 'Parameters must be set!';
    die('-1');
}
if(!checkApiEnabled('objects',$apiSettings,$_REQUEST['action']))
    exit(API_DISABLED);

//Parameters are always a JSON array - sometimes, even a 2D one
if(!IOFrame\Util\isJson($_REQUEST["params"])) {
    if($test)
        echo 'Parameters must be a JSON array!';
    die('-1');
}

//type + params
$params = json_decode($_REQUEST["params"], true);      //Store the parameter array in a variable
$action = $_REQUEST["action"];                             //Store operation type in a variable
//Save a whole case with 1 switch
if($action == 'ra'){
    $assign = false;
    $action = 'a';
}
else{
    $assign = true;
}

//In case of an empty param name, it's null
foreach($params as $param)
    if($param == '')
        $param = null;

$objHandler = new IOFrame\Handlers\ObjectHandler($settings,$defaultSettingsParams);

if(!isset($siteSettings))
    $siteSettings = new IOFrame\Handlers\SettingsHandler($settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/siteSettings/');

switch($action){
    case "r":
        require 'objects_fragments/r_checks.php';
        require 'objects_fragments/r_execution.php';
        require 'objects_fragments/read_parse.php';
        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        break;
    case "rg":
        require 'objects_fragments/rg_checks.php';
        require 'objects_fragments/rg_execution.php';
        require 'objects_fragments/read_parse.php';
        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        break;
    case "c":
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);
        require 'objects_fragments/c_auth.php';
        require 'objects_fragments/c_checks.php';
        require 'objects_fragments/c_execution.php';
        echo ($result === 0)?
            '0' : $result;
        break;
    case "u":
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);
        require 'objects_fragments/u_auth.php';
        require 'objects_fragments/u_checks.php';
        require 'objects_fragments/u_execution.php';
        echo ($result === 0)?
            '0' : $result;
        break;
    case "d":
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);
        require 'objects_fragments/d_auth.php';
        require 'objects_fragments/d_checks.php';
        require 'objects_fragments/d_execution.php';
        echo ($result === 0)?
            '0' : $result;
        break;
    case "a":
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);
        require 'objects_fragments/a_auth.php';
        require 'objects_fragments/a_checks.php';
        require 'objects_fragments/a_execution.php';
        echo ($result === 0)?
            '0' : $result;
        break;
    case "ga":
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);
        require 'objects_fragments/ga_checks.php';
        require 'objects_fragments/ga_execution.php';
        echo json_encode($result);
        break;
    default:
        echo 'Incorrect operation type!';
}
