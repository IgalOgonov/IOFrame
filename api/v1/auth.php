<?php

/* This is the API that handles all the auth functions.
 *
 *      See standard return values at defaultInputResults.php
 *
 * Parameters:
 * "action"     - Requested action - described below
 * "params"     - Parameters, depending on action - described below
 * "limit/offset" - can be passed as explicit parameters, will the override whatever is in "params"
 *_________________________________________________
 * getRank
 *      Returns:
 *          Current users rank (int).
 *
 *      Examples: action=getRank
 *_________________________________________________
 * isLoggedIn
 *      Returns:
 *          true or false ('1' or '0') depending on whether the user is logged in.
 *
 *      Examples: action=isLoggedIn
 *_________________________________________________
 * modifyUserRank [CSRF protected]
 *      Modifies user rank.
 *      params:
 *          'identifier' - Either a user ID (only digits) or a mail. Identifies the target who's rank to change.
 *          'newRank'    - A new rank in digits
 *      Returns:
 *          true or false ('1' or '0') depending on whether the user is logged in.
 *
 *      Examples: action=modifyUserRank&params={"identifier":"test@example.com","newRank":10}
 *_________________________________________________
 * getUsers
 *      Views specific users, and potentially their actions.
 *      params:
 *                      [
 *                      'id'|'action'|'group' => Array of Filters of the form [<filter type>, <parameters>].
 *                      'separator' => string, 'AND' or 'OR', logical relation between filters, defaults to 'AND'
 *                      'includeActions' => bool, whether to return only the IDs or all action/group info. Defaults to false.
 *                      'limit' => int, As defined in MySQL 'SELECT' documentation
 *                      'offset' => int, As defined in MySQL 'SELECT' documentation
 *                      'orderByExp' => string, Expression to order results by. Defaults to 'ID'.
 *                      ]
 *          Supported filter types are: 'NOT IN', 'IN', '<', '>', '<=', '>=', '='.
 *          Filter parameters are arrays or single values of type Strings/IDs, depending on filter type - group and actions are STRINGs,
 *          ids are INTs. For 'NOT IN' and 'IN' it's arrays, for the rest single values.
 *      Returns: Array of the form
 *              If fetching user IDs - JSON array of relevant (in respect to filters) user IDs of the form:[
 *                                                               <UserID> =><empty object>
 *                                                               ...
 *                                                               '@' => {
 *                                                                    '#'=><Number of results without limit/offset>
 *                                                               }
 *                                                              ]
 *              If fetching user Actions - JSON array of the form: [
 *                                                               <UserID> =>[
 *                                                                            "@" => Array of Actions
 *                                                                            <groupName> => Array of Actions
 *                                                                            ...
 *                                                                           ]
 *                                                               ...
 *                                                              ]
 *              where "@" inside each user are actions belonging directly to the user, and '@' inside the main results is meta information.
 *              When fetching actions, limit/offset are ignored, and there is no '@' key in the main array.
 *      Examples:
 *          action=getUsers&params={"action":[["=","PLUGIN_GET_INFO_AUTH"]],"group":["=","Test Group"],"separator":"OR","limit":2,"offset":0}
 *_________________________________________________
 * getUsersWithActions
 *      Views all user actions (can be filtered with $params). Is an alias of getUsers.
 *      Note that if allowed to be viewed without enforcing an ID condition '=' or 'IN' (or at least a similar group
 *      condition), the results could reach insane sizes, and the query would be very slow, as there is no way to limit
 *      this.
 *
 *      Returns:
 * Examples:
 *          action=getUsersWithActions&params={"action":["=","PLUGIN_GET_INFO_AUTH"],"group":["=","Test Group"],"separator":"OR"}
 *_________________________________________________
 * getActions
 *      Returns all the actions in a JSON encoded array of the form
 *                            [
 *                             <Action Name> => {
 *                                  'description'=><Description>
 *                             }
 *                             ...
 *                             '@' => {
 *                                  '#'=><Number of results without limit/offset>
 *                             }
 *                            ]
 *
 * Examples:
 *          action=getActions&params={"limit":10,"offset":10}
 *_________________________________________________
 * setActions [CSRF protected]
 *       Create new actions, or modifies existing ones.
 *      params:
 *              'actions' => An array of the form <Action Name> => <Description>|null
 *      Returns:
 *          true or false ('1' or '0') depending on whether the request succeeded.
 * Examples:
 *      action=setActions&params={"actions":{"TEST_ACTION":"Test Action.","BAN_USERS_AUTH":null}}
 *_________________________________________________
 * deleteActions [CSRF protected]
 *       Deletes actions
 *      params:
 *              'actions' => An array of action names
 *      Returns:
 *          true or false ('1' or '0') depending on whether the request succeeded.
 * Examples:
 *      action=deleteActions&params={"actions":["TEST_ACTION","BAN_USERS_AUTH"]}
 *_________________________________________________
 * getGroups
 *      Views specific groups, and potentially their actions.
 *      params:
 *              Same as for getUsers
 *      Returns:
 *          If fetching Groups - JSON encoded array  of the form: [
 *                                                               <Group Name> => <Empty object>
 *                                                               ...
 *                                                               '@' => {
 *                                                                    '#'=><Number of results without limit/offset>
 *                                                               }
 *                                                              ]
 *          If fetching Groups Actions - JSON object of the form: [
 *                                                               <Group Name> => Array of Actions
 *                                                               ...
 *                                                              ]
 *          When fetching actions, limit/offset are ignored, and there is no '@' key in the main array.
 * Examples:
 *      action=getGroups&params={"action":[["=","BAN_USERS_AUTH"],["=","SOME_ACTION"]],"separator":"OR"}
 *_________________________________________________
 * getGroupActions
 *      Returns all groups and their actions. Is an alias of getGroups.
 *      See getUsersWithActions for similarities to getGroups
 *
 * Examples:
 *      action=getGroupActions&params={"id":[["=","1"]],"action":["=","BAN_USERS_AUTH"],"separator":"AND"}
 *_________________________________________________
 * setGroups [CSRF protected]
 *       Create new groups, or modifies existing ones (description).
 *      params:
 *              'groups' => A JSON object of the form <Group Name> => <Description>|null
 *      Returns:
 *          true or false ('1' or '0') depending on whether the request succeeded.
 * Examples:
 *      action=setGroups&params={"groups":{"Test Group":"Test Description.","Another Test Group":"Test Description II - The Description Strikes Back."}}
 *_________________________________________________
 * deleteGroups [CSRF protected]
 *       Deletes groups
 *      params:
 *              'groups' => An array of group names
 *      Returns:
 *          true or false ('1' or '0') depending on whether the request succeeded.
 * Examples:
 *      action=deleteGroups&params={"groups":["Fake Test Group","Faker Test Group"]}
 *_________________________________________________
 * modifyUserActions [CSRF protected]
 *        Adds/Removes actions to/from a user.
 *        params:
 *              'id' => int, User ID
 *              'actions' => A JSON object of the form:
 *                          <Action Name> => Bool true/false for set/delete.
 * Examples:
 *      action=modifyUserActions&params={"id":1,"actions":{"TEST_1":true,"TEST_2":false,"ASSIGN_OBJECT_AUTH":false}}
 *_________________________________________________
 * modifyUserGroups [CSRF protected]
 *        Adds/Removes groups to/from a user.
 *        params:
 *              'id' => int, User ID
 *              'groups' => A JSON object of the form:
 *                          <Groups Name> => Bool true/false for set/delete.
 * Examples:
 *      action=modifyUserGroups&params={"id":2,"groups":{"TEST_G1":true,"TEST_G2":false,"G6":false}}
 *_________________________________________________
 * modifyGroupActions [CSRF protected]
 *        Adds/Removes actions to/from a group.
 *        params:
 *              'groupName' => string, Name of the group
 *              'actions' => A JSON object of the form:
 *                          <Action Name> => Bool true/false for set/delete.
 * Examples:
 *      action=modifyGroupActions&params={"groupName":"Test Group","actions":{"SOME_ACTION":false,"STRANGE_ACTION":true}}
 * */

if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../../main/core_init.php';
require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';
require 'auth_fragments/definitions.php';

if($test)
    echo 'Testing mode!'.EOL;

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];

if(!checkApiEnabled('auth',$apiSettings,$SecurityHandler,$_REQUEST['action']))
    exit(API_DISABLED);

if(isset($_REQUEST['params']))
    $params = json_decode($_REQUEST['params'],true);
else
    $params = [];

$commons = ['limit','offset'];
foreach($commons as $common){
    if(isset($_REQUEST[$common])){
        $params[$common] = $_REQUEST[$common];
    }
}

switch($action){
    case 'getRank':
        require 'auth_fragments/getRank_execution.php';
        echo  ($result)?: '0';
        break;

    case 'isLoggedIn':
        require 'auth_fragments/isLoggedIn_execution.php';
        echo ($result)? '1' : '0';
        break;

    case 'modifyUserRank':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);
        require 'auth_fragments/modifyUserRank_auth.php';
        require 'auth_fragments/modifyUserRank_checks.php';
        require 'auth_fragments/modifyUserRank_execution.php';
        echo ($result)? '1' : '0';
        break;

    case 'getUsers':
        require 'auth_fragments/get_auth.php';
        require 'auth_fragments/get_checks.php';
        require 'auth_fragments/getUsers_execution.php';
        echo json_encode($result);
        break;

    case 'getUsersWithActions':
        require 'auth_fragments/get_auth.php';
        require 'auth_fragments/get_checks.php';
        require 'auth_fragments/getUsersWithActions_execution.php';
        echo json_encode($result);
        break;

    case 'getActions':
        require 'auth_fragments/getActions_auth.php';
        require 'auth_fragments/getActions_checks.php';
        require 'auth_fragments/getActions_execution.php';
        echo json_encode($result);
        break;

    case 'setActions':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);
        require 'auth_fragments/set_auth.php';
        require 'auth_fragments/set_checks.php';
        require 'auth_fragments/setActions_execution.php';
        echo ($result)? '1' : '0';
        break;

    case 'deleteActions':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);
        require 'auth_fragments/delete_auth.php';
        require 'auth_fragments/delete_checks.php';
        require 'auth_fragments/deleteActions_execution.php';
        echo ($result)? '1' : '0';
        break;


    case 'getGroups':
        require 'auth_fragments/get_auth.php';
        require 'auth_fragments/get_checks.php';
        require 'auth_fragments/getGroups_execution.php';
        echo json_encode($result);
        break;


    case 'getGroupActions':
        require 'auth_fragments/get_auth.php';
        require 'auth_fragments/get_checks.php';
        require 'auth_fragments/getGroupActions_execution.php';
        echo json_encode($result);
        break;


    case 'setGroups':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);
        require 'auth_fragments/set_auth.php';
        require 'auth_fragments/set_checks.php';
        require 'auth_fragments/setGroups_execution.php';
        echo ($result)? '1' : '0';
        break;


    case 'deleteGroups':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);
        require 'auth_fragments/delete_auth.php';
        require 'auth_fragments/delete_checks.php';
        require 'auth_fragments/deleteGroups_execution.php';
        echo ($result)? '1' : '0';
        break;


    case 'modifyUserActions':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);
        require 'auth_fragments/modify_auth.php';
        require 'auth_fragments/modify_checks.php';
        require 'auth_fragments/modifyUserActions_execution.php';
        echo ($result)? '1' : '0';
        break;


    case 'modifyUserGroups':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);
        require 'auth_fragments/modify_auth.php';
        require 'auth_fragments/modify_checks.php';
        require 'auth_fragments/modifyUserGroups_execution.php';
        echo ($result)? '1' : '0';
        break;

    case 'modifyGroupActions':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);
        require 'auth_fragments/modify_auth.php';
        require 'auth_fragments/modify_checks.php';
        require 'auth_fragments/modifyGroupActions_execution.php';
        echo ($result)? '1' : '0';
        break;

    default:
        exit('Specified action is not recognized');
}


