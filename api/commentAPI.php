<?php
/**
 * This API is similar to the Objects API.
 * However, comments are specific objects where the contents of each object are a Markdown comment.
 *
 * -- Create / Update --
 * As such, it will support insertion/update with an extra column ('Trusted_Comment'), which specifies whether the
 * comment is "trusted" user input (has to be specifically requested and authorized).
 * Also, the $params array has an additional attribute:
 *      'trusted' => bool, defaults to 0. Specifies whether the created/updated comment needs to be a trusted comment.
 * Note that a user needs specific authentication for this.
 *
 *
 * -- Read [Group] --
 * As for the read (or read group) operations, comment content (the contents of each object) will be parsed from Markdown -
 * and those who were "trusted" will also allow HTML (untrusted comments are parsed in "safe mode" by default).
 * Also, in addition to groupMap, a second map of dates will be returned - "dateMap" - where each member is of the form:
 *      {
 *          'created' => <Comment creation date>,
 *          'updated' => <Date of the last CONTENT update (through THIS API!)>
 *      }
 * Finally, another type of error is possible - "object isn't a comment" error, aka Error code -1 (per object! Not the input code).
 *
 * Interesting note: You may update objects you own that aren't comments and change Trusted_Comment and Date_Comment_Updated
 * values. However, that object will never be parsed as a comment, because Date_Comment_Created cannot be changed by
 * updating, and that's the field used to determine whether an object is a comment.
 */
if(!defined('coreInit'))
    require __DIR__ . '/../main/coreInit.php';
if(!defined('ObjectHandler'))
    require __DIR__.'/../IOFrame/Handlers/ObjectHandler.php';

//Fix any values that are strings due to softly typed language bullshit
require 'defaultInputChecks.php';
require 'CSRF.php';

//Session Info
$sesInfo = json_decode($_SESSION['details'],true);

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

$objHandler = new IOFrame\Handlers\ObjectHandler($settings,['SQLHandler'=>$SQLHandler,'logger'=>$logger]);

if(!isset($siteSettings))
    $siteSettings = new IOFrame\Handlers\SettingsHandler($settings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/siteSettings/');

switch($action){
    case "r":
        require 'commentAPI_fragments/extra_params_read.php';
        require 'objectAPI_fragments/r_checks.php';
        require 'objectAPI_fragments/r_execution.php';
        require 'commentAPI_fragments/markdown_parse.php';
        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        break;
    case "rg":
        if(!validateThenRefreshCSRFToken($SessionHandler))
            die('-3');
        require 'commentAPI_fragments/extra_params_read.php';
        require 'objectAPI_fragments/rg_checks.php';
        require 'objectAPI_fragments/rg_execution.php';
        require 'commentAPI_fragments/markdown_parse.php';
        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        break;
    case "c":
        if(!validateThenRefreshCSRFToken($SessionHandler))
            die('-3');
        require 'commentAPI_fragments/write_check.php';
        require 'commentAPI_fragments/extra_params_create.php';
        require 'objectAPI_fragments/c_checks.php';
        require 'objectAPI_fragments/c_execution.php';
        echo ($result === 0)?
            '0' : $result;
        break;
    case "u":
        if(!validateThenRefreshCSRFToken($SessionHandler))
            die('-3');
        require 'commentAPI_fragments/write_check.php';
        require 'commentAPI_fragments/extra_params_update.php';
        require 'objectAPI_fragments/u_checks.php';
        require 'objectAPI_fragments/u_execution.php';
        echo ($result === 0)?
            '0' : $result;
        break;
    default:
        echo 'Incorrect operation type!';
}
