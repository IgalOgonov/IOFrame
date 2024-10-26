<?php
/* This the the API that handles all the menu related functions.
 *
 *  See standard return values at defaultInputResults.php
 *  Everything except getting a SPECIFIC menu requires admin auth in this API - however, while all menus aren't listed by default,
 *  you mustn't rely on them being truly hidden from attackers.
 *
 * Parameters:
 * "action"     - Requested action - described below
 *_________________________________________________
 * getMenus
 *      - Gets all the menus, including their meta information. CANNOT get specific items this way
 *      params:
 *          limit - can be passed as explicit parameters when not getting specific items - those are SQL pagination parameters.
 *              Default 50.
 *          offset - related to limit
 *
 *      Returns json array of the form:
 *          [
 *              <identifier> =>
 *                  <DB array>,
 *                  OR
 *                  <code>
 *          ]
 *          A DB error when $items is [] will result in an empty array returned, not an error.
 *
 *          Possible codes are:
 *          1 if specific item that was requested is not found,
 *          -1 if there was a DB error
 *          "AUTHENTICATION_FAILURE" - when authentication getting this specific object failed.
 *
 *          The following DB arrays have the following structure:
 *          [
 *              'menuId' => <string, menu id>,
 *              'title' => <string, menu title>,
 *              'menu' => {
 *                  //same structure as getMenu
 *              },
 *              'meta' => {
 *                  //potentially anything that was set by a more specific API
 *              },
 *              'created' => <int, article creation date - unix timestamp>,
 *              'updated' => <int, article last update date - unix timestamp>,
 *          ]
 *
 *        Examples:
 *          action=getMenus
 *_________________________________________________
 * setMenus [CSRF protected]
 *      - Creates new / updates existing menus.
 *      params:
 *          inputs- JSON encoded array of objects, each object of the form:
 *              {
 *                  'menuId' - string, menu id.
 *                  'title' - string, menu title
 *                  'meta' - JSON string, any type of meta
 *              }
 *          override - bool, default true - if false, will not update existing.
 *          update - bool, default false - if true, will only update existing (overrides override).
 *
 *      Returns json array of the form:
 *           <identifier> =>  <code>
 *
 *          Possible codes are:
 *         -1 - failed to connect to db
 *          0 - success
 *          1 - item does not exist (and update is true)
 *          2 - item exists (and override is false)
 *          3 - trying to create a new item with missing inputs - shouldn't happen with the API
 *
 *        Examples:
 *          action=setMenus&inputs=[{"menuId":"test_menu","title":"Text Menu"},{"menuId":"test_menu_2","title":"Text Menu Two"}]
 *_________________________________________________
 * deleteMenus [CSRF protected]
 *      Deletes menus.
 *
 *      params:
 *      menus - string[], array of menu IDs
 *
 *      Returns json array of the form:
 *          <string, id> => <int, code>
 *          where the possible codes are:
 *          -1 server error
 *           0 success (does not check if items do not exist)
 *
 * Examples:
 *      action=deleteMenus&menus=["test_menu_2"]
 *_________________________________________________
 * getMenu
 *      - Gets a specific menu
 *      params:
 *          identifier - string, menu identifier - required.
 *
 *        Returns:
 *        String - JSON encoded array of the form:
 *          {
 *              'children'=>{
 *                  <string, identifier> => {
 *                      [title: string - Title of the menu item]
 *                      [children: array, objects of the same structure as this one]
 *                      //potentially anything that was set by a more specific API
 *                  }
 *              }
 *              '@' => {
 *                  //Potential meta information about the article
 *              }
 *              '@title' => <string/null - menu title>
 *          }
 *        OR
 *        Int: -1, server error
 *
 *        Examples:
 *          action=getMenu&identifier=test_menu
 *_________________________________________________
 * setMenuItems [CSRF protected]
 *      - Sets (or unsets) multiple menu items (children in the menu tree).
 *      params:
 *          identifier - string, menu identifier - required.
 *          inputs- JSON encoded array of objects, each object of the form:
 *          {
 *              'address' - string, array of valid identifiers that represent parents. defaults to [] (menu root).
 *                      If a non-existent parent is referenced, will not add the item.
 *              ['identifier' - string, "identifier"  of the menu item used for stuff like routing.]
 *              ['delete' - bool, if true, deletes the item instead of modifying.]
 *              ['title': string, Title of the menu item]
 *              ['order': string, comma separated list of identifiers - to show the order in which children should be rendered.]
 *          }
 *
 *        Examples:
 *          action=setMenuItems&identifier=test_menu&inputs=[{"address":[],"identifier":"test_1","title":"1"},{"address":[],"identifier":"test_2","title":"2"},{"address":["test_2"],"identifier":"test_3","delete":true}]
 *          action=setMenuItems&identifier=test_menu&inputs=[{"address":[],"order":"test_3,test_2,test_1"}]
 *
 *        Returns:
 *          int Code:
 *              -3 Menu not a valid json somehow
 *              -2 Menu not found for some reason
 *              -1 Database Error
 *               0 All good
 *               1 One of the parents FOR ANY OF THE ITEMS not found
 *               2 Item with similar identifier already exists in address
 *_________________________________________________
 * moveMenuBranch [CSRF protected]
 *      - Moves one branch of the menu to a different root
 *      params:
 *           identifier - string, menu identifier - required.
 *           blockIdentifier -  string Identifier of branch
 *           sourceAddress - string,  Source address, akin to that of setMenuItems
 *           targetAddress - string,  Target address, akin to that of setMenuItems
 *           orderIndex - int, if set, will insert the target into a specific index at the order. Otherwise,
 *                         if updateOrder is set, will insert it into the end.
 *
 *        Examples:
 *          action=moveMenuBranch&identifier=test_menu&blockIdentifier=test_2&sourceAddress=[]&targetAddress=["test_1"]
 *
 *        Returns  int Code of the form:
 *            -3 Menu not a valid json somehow
 *            -2 Menu not found for some reason
 *            -1 Database Error
 *             0 All good
 *             1 One of the parents for the source not found
 *             2 One of the parents for the target not found
 *             3 Source identifier does not exist
 *             4 Address identifier exists and override is false
 *_________________________________________________
 *
 * */

if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../../main/core_init.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';
require 'menu_fragments/definitions.php';

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];

if($test)
    echo 'Testing mode!'.EOL;

if(!checkApiEnabled('menu',$apiSettings,$SecurityHandler,$_REQUEST['action']))
    exit(API_DISABLED);

//Handle inputs
$inputs = [];

switch($action){

    case 'getMenus':

        $arrExpected =["offset","limit"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'menu_fragments/getMenus_checks.php';
        require 'menu_fragments/getMenus_auth.php';
        require 'menu_fragments/getMenus_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'setMenus':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["inputs","override","update"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'menu_fragments/setMenus_checks.php';
        require 'menu_fragments/setMenus_auth.php';
        require 'menu_fragments/setMenus_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'deleteMenus':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["menus"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'menu_fragments/deleteMenus_checks.php';
        require 'menu_fragments/deleteMenus_auth.php';
        require 'menu_fragments/deleteMenus_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'getMenu':

        $arrExpected =["identifier"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'menu_fragments/getMenu_checks.php';
        require 'menu_fragments/getMenu_auth.php';
        require 'menu_fragments/getMenu_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'setMenuItems':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["identifier","inputs"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'menu_fragments/setMenuItems_checks.php';
        require 'menu_fragments/setMenuItems_auth.php';
        require 'menu_fragments/setMenuItems_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'moveMenuBranch':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["identifier","blockIdentifier","sourceAddress","targetAddress","orderIndex"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'menu_fragments/moveMenuBranch_checks.php';
        require 'menu_fragments/moveMenuBranch_auth.php';
        require 'menu_fragments/moveMenuBranch_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}
