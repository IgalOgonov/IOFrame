<?php
/**
 * Handles operations related to objects, those which are saved in the tables Object_Cache and Object_Cache_Meta.
 * Extension:   It is possible to include this API for the extending API, where you define a function "parseObjectContent($content)"
 *              that parses the contents for each object.
 * Reminder :   Some input checking on here is redundant, yet is still present for security reasons.
 *              On the other hand, some authorization checks are done in ObjectHandler itself.
 * Parameters:
 * "type"   -   The type of operation needed. Create, Read (or Read Groups), Update, Delete, Assign, Get Assignments or Remove Assignment for (an) object/s.
 * "params" -   The target collection of objects/groups.
 *_________________________________________________
 *        r (read)
 *              params:
 *              a 2D JSON array of the form:
 *              {
 *                  "@": "{"<objectID1>":"<timeObj1Updated>", ...}",
 *                  "<groupName>": "{"@":"<timeGroupUpdated>", "<objectID2>":"<timeObj2Updated>", ...}",
 *                  ... ,
 *                  ["?":"false/true"]          // ? means "test". Default false
 *              }
 *              Where
 *                  "@" as group name is the "group" of all the group-less objects, "@" as group member denotes the last time the whole group was updated.
 *                  "?" is optional, and if set will result in a test query.
 *
 *              Returns:
 *              All the objects whose "Last_Updated" is newer than specified by the user, in an array of the form:
 *              {
 *              "<ObjectID1>":"<Contents>",
 *              "<ObjectID2>":"<Contents>",
 *              ...
 *              "Errors":"{"<ObjectID>":"<ErrorID>"}",
 *              "groupMap":"<ObjectID1>":"GroupName",....},
 *              Where possible error codes are:
 *                  0 if you can view the object yet $updated is bigger than the object's Last_Updated field.
 *                  1 if object of specified ID doesn't exist.
 *                  2 if insufficient authorization to view the object.
 *                  Will simply ignore groups whose "@" is lower than in the database, aka the user is up to date.
 *
 *          Examples:
 *              type=r&params={"?":"true","courses":"{\"15\":0,\"16\":0,\"17\":0,\"18\":0,\"19\":0,\"20\":0,\"21\":0,\"22\":0,\"23\":0,\"24\":0,\"25\":0,\"26\":0,\"27\":0,\"@\":0}"}
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
 *              type=rg&params={"?":"true","groupName":"courses","updated":1523379254}
 *_________________________________________________
 *        c (create)
 *              params:
 *              a JSON array of the form:
 *              {
 *                  "obj":"<Contents>",
 *                  ["minViewRank":<Number>],  // Default -1
 *                  ["minModifyRank":<Number>],// Default 0
 *                  ["group":"<Group Name>"],  // Default null
 *                  ["?":"false/true"]       // Default false
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
 *              type=c&params={"obj":"test01_@%23$_(){}[]","minViewRank":12,"minModifyRank":2,"group":"g1","?":true}
 *_________________________________________________
 *         u (update)
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
 *                  ["?":"false/true"]                                 // Default false
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
 *              type=u&params={"id":9,"content":"Test content!**^","group":"g2","newVRank":-1,"newMRank":2,"mainOwner":1,"addOwners":{"2":2,"3":3},"remOwners":{"4":4,"3":3},"?":"true"}
 *_________________________________________________
 *         d (delete),
 *              params:
 *              a JSON array of the form:
 *              {
 *                  "id":"<ObjectID>",
 *                  ["time":<Number>],              // Default 0
 *                  ["after":"false/true"],         // Default true
 *                  ["?":"false/true"]              // Default false
 *              }
 *              where all is self explanatory if you know the structure of the objects class.
 *
 *              Returns:
 *                  0 on success.
 *                  1 if id doesn't exists.
 *                  2 if insufficient authorization to modify the object.
 *                  3 object exists, too old/new to delete
 *          Examples:
 *              type=d&params={"id":16,"?":true}
 *              type=d&params={"id":16,"time":1523379256,"after":0,"?":true}
 *_________________________________________________
 *          a (assign)
 *              params:
 *              a JSON array of the form:
 *               {
 *                  "id":"<ObjectID>",
 *                  "page":"<path/to/page.php>",
 *                  ["?":"false/true"]              // Default false
 *              }
 *              where all is self explanatory if you know the structure of the objects class.
 *
 *              Returns:
 *                  0 on success.
 *                  1 if id doesn't exists.
 *                  2 if insufficient authorization to modify the object.
 *                  3 if insufficient authorization to assign objects to pages.
 *          Examples:
 *              type=a&params={"id":16,"page":"testPage.php","?":true}
 *_________________________________________________
 *          ra (remove assignment)
 *              params:
 *              a JSON array of the form:
 *               {
 *                  "id":"<ObjectID>",
 *                  "page":"<path/to/page.php>",
 *                  ["?":"false/true"]              // Default false
 *              }
 *              where all is self explanatory if you know the structure of the objects class.
 *
 *              Returns:
 *                  0 on success.
 *                  1 if object or page id don't exist.
 *                  2 if insufficient authorization to modify the object.
 *                  3 if insufficient authorization to remove object/page assignments.
 *          Examples:
 *              type=ra&params={"id":16,"page":"testPage.php","?":true}
 *              type=ra&params={"id":16,"page":"CV/CV.php","?":true}
 *_________________________________________________
 *           ga (get assignment)
 *              params:
 *              a JSON array of the form:
 *              {
 *                  "page":"<path/to/page.php>",
 *                  "date":<Date-up-to-which-you-are-to-date> - defaults to 0
 *                  ["?":"false/true"]              // Default false
 *              }
 *              where all is self explanatory if you know the structure of the objects class.
 *
 *              Returns:
 *                  A JSON array of the objects, of the form {"ID":"ID",...}, if $time < Last_Changed
 *                  0 if Last_Changed < $time
 *                  1 if the page doesn't exist
 *          Examples:
 *              type=ga&params={"page":"CV/CV.php","date":0,"?":true}
 *
 */

require_once __DIR__ . '/../_Core/coreInit.php';
require_once __DIR__.'/../_siteHandlers/objectHandler.php';

//Fix any values that are strings due to softly typed language bullshit
foreach($_REQUEST as $key=>$value){
    if($_REQUEST[$key] == '')
        unset($_REQUEST[$key]);
    else if($_REQUEST[$key] == 'false')
        $_REQUEST[$key] = false;
    else if($_REQUEST[$key] == 'true')
        $_REQUEST[$key] = true;
}
//Session Info
$sesInfo = json_decode($_SESSION['details'],true);
$params = json_decode($_REQUEST["params"], true);      //Store the parameter array in a variable
$type = $_REQUEST["type"];                             //Store operation type in a variable

//You must specify the type of operation
if(!isset($_REQUEST["type"])) {
    echo 'Operation type unset!';
    return false;
}
//Parameters are needed for all possible operations
if(!isset($_REQUEST["params"])) {
    echo 'Parameters must be set!';
    return false;
}

//Parameters are always a JSON array - sometimes, even a 2D one
if(!IOFrame\isJson($_REQUEST["params"])) {
    var_dump($_REQUEST["params"]);
    echo 'Parameters must be a JSON array!';
    return false;
}

//Save a whole case with 1 switch
if($type == 'ra'){
    $assign = false;
    $type = 'a';
}
else{
    $assign = true;
}

//Input policing
//Test is false unless it's true or 'true'
$test = false;
if(isset($params['?'])){
    if($params['?'] === true || $params['?'] === 'true')
        $test = true;
    unset($params['?']);
}
//In case of an empty param name, it's null
foreach($params as $param)
    if($param == '')
        $param = null;

$objHandler = new IOFrame\objectHandler($settings,['sqlHandler'=>$sqlHandler,'logger'=>$logger]);

if(!isset($siteSettings))
    $siteSettings = new IOFrame\settingsHandler($settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/siteSettings/');

switch($type){
    case "r":
        require_once 'objectAPI_fragments/r_checks.php';
        require_once 'objectAPI_fragments/r_execution.php';
        echo json_encode($result);
        break;
    case "rg":
        require_once 'objectAPI_fragments/rg_checks.php';
        require_once 'objectAPI_fragments/rg_execution.php';
        echo json_encode($result);
        break;
    case "c":
        require_once 'objectAPI_fragments/c_checks.php';
        require_once 'objectAPI_fragments/c_execution.php';
        echo $result== 0? '0' : (string)$result;
        break;
    case "u":
        require_once 'objectAPI_fragments/u_checks.php';
        require_once 'objectAPI_fragments/u_execution.php';
        echo $result== 0? '0' : (string)$result;
        break;
    case "d":
        require_once 'objectAPI_fragments/d_checks.php';
        require_once 'objectAPI_fragments/d_execution.php';
        echo $result== 0? '0' : (string)$result;
        break;
    case "a":
        require_once 'objectAPI_fragments/a_checks.php';
        require_once 'objectAPI_fragments/a_execution.php';
        echo $result== 0? '0' : (string)$result;
        break;
    case "ga":
        require_once 'objectAPI_fragments/ga_checks.php';
        require_once 'objectAPI_fragments/ga_execution.php';
        echo json_encode($result);
        break;
    default:
        echo 'Incorrect operation type!';
}
