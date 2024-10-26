<?php

/* This the BASIC API that handles all the object auth functions.
 * The way object auth is actually meant to be used means this API will always be about modifying raw data
 * which operates by business logic unknown to the API itself, thus should only be used by those who can understand it and
 * act accordingly.
 *
 *      See standard return values at defaultInputResults.php
 *
 * Parameters:
 * "action"     - Requested action - described below
 * "type"   - string, each action here has an associated item type. The following types are valid:
 *                'categories','objects','actions','groups','objectUsers','objectGroups','userGroups'
 *_________________________________________________
 * getItems
 *      Gets items. The type of the items depends on the type parameter.
 *      Actions and objects regex is /^[a-zA-Z][a-zA-Z0-9\.\-\_ ]{1,255}$/, while for Titles it's the same but max length is 1024.
 *
 *      params:
 *      keys - array of keys, can be empty to get all items. If not empty, it's an array of arrays, each member
 *            represents the requested identifiers, and is of the following form, depending on the type parameter:
 *          'categories': [<int, category>]
 *          'objects': [<int, category>,<string, object>]
 *          'actions': [<int, category>,<string, action>]
 *          'groups': [<int, category>,<string, object>,<int, group>]
 *          'objectUsers': [<int, category>,<string, object>,<int, user>]
 *          'objectGroups': [<int, category>,<string, object>,<int, group>]
 *          'userGroups': [<int, category>,<string, object>,<int, user>]
 *      -- The following are only relevant when not getting specific keys --
 *      limit - can be passed as explicit parameters when not getting specific items - those are SQL pagination parameters.
 *              Default 50.
 *      offset - related to limit
 *      orderBy - string, default null , when not getting specific items. Possible columns to order by, depending on the type parameter:
 *          'categories': ['category']
 *          'objects': ['category','object']
 *          'actions': ['category','action']
 *          'groups': ['category','object','group']
 *          'objectUsers': ['category','object','userID','action']
 *          'objectGroups': ['category','object','group','action']
 *          'userGroups': ['category','object','userID','group']
 *          * common filters are 'created' and 'updated'
 *      orderType - int, default null, when not getting specific items - possible values 0 and 1 - 0 is 'ASC', 1 is 'DESC'
 *      filters - array of filters, defaults to []. If not empty, it's an associative array, each member
 *            represents a specific filter depending on the type parameter:
 *          'categories': [
 *                      'titleLike' => <string, valid regex>,
 *                      'categoryIs' => <int, specific category>,
 *                      'categoryIn' => <int[], specific categories>,
 *                      ]
 *          'objects': [
 *                      'titleLike' => <string, valid regex>,
 *                      'categoryIs' => <int, specific category>,
 *                      'categoryIn' => <int[], specific categories>,
 *                      'objectLike' => <string, object, valid regex>,
 *                      'objectIn' => <string[], specific objects>,
 *                     ]
 *          'actions': [
 *                      'titleLike' => <string, valid regex>,
 *                      'categoryIs' => <int, specific category>,
 *                      'categoryIn' => <int[], specific categories>,
 *                      'actionLike' => <string, action, valid regex>,
 *                      'actionIn' => <string[], specific actions>,
 *                     ]
 *          'groups': [
 *                      'titleLike' => <string, valid regex>,
 *                      'categoryIs' => <int, specific category>,
 *                      'categoryIn' => <int[], specific categories>,
 *                      'objectLike' => <string, object, valid regex>,
 *                      'objectIn' => <string[], specific objects>,
 *                      'groupIs' => <int, specific group>,
 *                      'groupIn' => <int[], specific groups>,
 *                     ]
 *          'objectUsers': [
 *                      'categoryIs' => <int, specific category>,
 *                      'categoryIn' => <int[], specific categories>,
 *                      'objectLike' => <string, object, valid regex>,
 *                      'objectIn' => <string[], specific objects>,
 *                      'userIDIs' => <int, specific user ID>,
 *                      'userIDIn' => <int[], specific user IDs>,
 *                      'actionLike' => <string, action, valid regex>,
 *                      'actionIn' => <string[], specific actions>,
 *                      ]
 *          'objectGroups': [
 *                      'categoryIs' => <int, specific category>,
 *                      'categoryIn' => <int[], specific categories>,
 *                      'objectLike' => <string, object, valid regex>,
 *                      'objectIn' => <string[], specific objects>,
 *                      'groupIs' => <int, specific group>,
 *                      'groupIn' => <int[], specific groups>,
 *                      'actionLike' => <string, action, valid regex>,
 *                      'actionIn' => <string[], specific actions>,
 *                      ]
 *          'userGroups': [
 *                      'categoryIs' => <int, specific category>,
 *                      'categoryIn' => <int[], specific categories>,
 *                      'objectLike' => <string, object, valid regex>,
 *                      'objectIn' => <string[], specific objects>,
 *                      'userIDIs' => <int, specific user ID>,
 *                      'userIDIn' => <int[], specific user IDs>,
 *                      'groupIs' => <int, specific group>,
 *                      'groupIn' => <int[], specific groups>,
 *                      ]
 *          ALL items also have the following filters available: [
 *                      'createdBefore' => <int, date before the items were created>,
 *                      'createdAfter' => <int, date after the items were created>,
 *                      'changedBefore' => <int, date before the items were changed>,
 *                      'changedAfter' => <int, date after the items were changed>,
 *                      ]
 *
 *      Returns array of the form:
 *          [
 *              <identifier - string, all keys separated by "/"> =>
 *                  <DB array. If the item type is one of 'objectUsers','objectGroups','userGroups', param and we are getting specific items,
 *                   this will be an array of sub-items>,
 *                  OR
 *                  <code int - 1 if specific item that was requested is not found, -1 if there was a DB error>
 *          ]
 *          A DB error when $items is [] will result in an empty array returned, not an error.
 *
 *          The following DB arrays have the following structure, based on the item type:
 *          'categories': [
 *                      'category' => <int, specific category>,
 *                      'title' => <string, could be null - valid title>,
 *                      ]
 *          'objects': [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'title' => <string, could be null - valid title>,
 *                      'public' => <bool, whether the object is considered public by default - specific application logic may ignore this, though>
 *                     ]
 *          'actions': [
 *                      'category' => <int, specific category>,
 *                      'action' => <string, action>,
 *                      'title' => <string, could be null - valid title>,
 *                     ]
 *          'groups': [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'group' => <int, specific group>,
 *                      'title' => <string, could be null - valid title>,
 *                     ]
 *          'objectUsers' (all items): [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'userID' => <int, user ID>
 *                      ]
 *          'objectUsers' (specific items): [
 *                          <string, action> => [
 *                              'category' => <int, specific category>,
 *                              'object' => <string, object>,
 *                              'userID' => <int, user ID>,
 *                              'action' => <string, action>,
 *                          ]
 *                      ]
 *          'objectGroups'  (all items): [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'group' => <int, specific group>,
 *                      ]
 *          'objectGroups' (specific items): [
 *                          <string, action> => [
 *                              'category' => <int, specific category>,
 *                              'object' => <string, object>,
 *                              'group' => <int, specific group>,
 *                              'action' => <string, action>,
 *                          ]
 *                      ]
 *          'userGroups'  (all items): [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'userID' => <int, user ID>,
 *                      ]
 *          'userGroups'  (specific items): [
 *                          <int, group> => [
 *                              'category' => <int, specific category>,
 *                              'object' => <string, object>,
 *                              'userID' => <int, user ID>,
 *                              'group' => <int, specific group>,
 *                          ]
 *                      ]
 *          ALL arrays also have the following keys available: [
 *                      'created' => <int, date created>,
 *                      'updated' => <int, date last changed>,
 *                      ]
 *
 *          All items which do NOT have sub-items have the following meta data under the key '@' WHEN NOT SEARCHING SPECIFIC ITEMS:
 *          'categories': [
 *                      '#' => <int, number of results without limit>
 *                      ]
 *          'objects': [
 *                      '#' => <int, number of results without limit>
 *                      'categories' => <int[], all distinct categories in the current selection given the filters (ignoring limit)>
 *                     ]
 *          'actions': [
 *                      '#' => <int, number of results without limit>
 *                      'categories' => <int[], all distinct categories in the current selection given the filters (ignoring limit)>
 *                     ]
 *          'groups': [
 *                      '#' => <int, number of results without limit>
 *                      'objects' => <int[], all distinct objects in the current selection given the filters (ignoring limit)>
 *                      'categories' => <int[], all distinct categories in the current selection given the filters (ignoring limit)>
 *                     ]
 *          'objectUsers': [
 *                      '#' => <int, number of results without limit>
 *                      'objects' => <int[], all distinct objects in the current selection given the filters (ignoring limit)>
 *                      'categories' => <int[], all distinct categories in the current selection given the filters (ignoring limit)>
 *                      ]
 *          'objectGroups': [
 *                      '#' => <int, number of results without limit>
 *                      'objects' => <int[], all distinct objects in the current selection given the filters (ignoring limit)>
 *                      'categories' => <int[], all distinct categories in the current selection given the filters (ignoring limit)>
 *                      ]
 *          'userGroups': [
 *                      '#' => <int, number of results without limit>
 *                      'objects' => <int[], all distinct objects in the current selection given the filters (ignoring limit)>
 *                      'categories' => <int[], all distinct categories in the current selection given the filters (ignoring limit)>
 *                      ]
 *
 *
 *      Examples:
 *          type=categories&action=getItems
 *          type=categories&action=getItems&limit=5&filters={"titleLike":"t","categoryIs":1,"categoryIn":[1,2]}
 *          type=categories&action=getItems&keys=[[1],[2]]
 *          type=objects&action=getItems
 *          type=objects&action=getItems&limit=5&filters={"titleLike":"t","categoryIs":1,"categoryIn":[1,2],"objectLike":"t","objectIn":["test_1","test_2","test_3"]}
 *          type=objects&action=getItems&keys=[[1,"test_1"],[1,"test_2"],[1,"test_3"]]
 *          type=actions&action=getItems
 *          type=actions&action=getItems&limit=5&filters={"titleLike":"t","categoryIs":1,"categoryIn":[1,2],"actionLike":"t","actionIn":["test_1","test_2","test_3"]}
 *          type=actions&action=getItems&keys=[[1,"test_1"],[1,"test_2"],[1,"test_3"]]
 *          type=groups&action=getItems
 *          type=groups&action=getItems&limit=5&filters={"titleLike":"t","categoryIs":1,"categoryIn":[1,2],"objectLike":"t","objectIn":["test_1","test_2","test_3"],"groupIn":[1,2,3]}
 *          type=groups&action=getItems&keys=[[1,"test_1",1],[1,"test_1",2],[1,"test_2",1]]
 *          type=objectUsers&action=getItems
 *          type=objectUsers&action=getItems&limit=5&filters={"titleLike":"t","categoryIs":1,"categoryIn":[1,2],"objectLike":"t","objectIn":["test_1","test_2","test_3"],"actionLike":"t","userIDIn":[1,2,3],"actionIn":["test_1","test_2","test_3"]}
 *          type=objectUsers&action=getItems&keys=[[1,"test_1",1],[1,"test_1",2],[1,"test_4",5]]
 *          type=objectGroups&action=getItems
 *          type=objectGroups&action=getItems&limit=5&filters={"titleLike":"t","categoryIs":1,"categoryIn":[1,2],"objectLike":"t","objectIn":["test_1","test_2","test_3"],"actionLike":"t","groupIn":[1,2,3],"actionIn":["test_1","test_2","test_3"]}
 *          type=objectGroups&action=getItems&keys=[[1,"test_1",1],[1,"test_1",2],[1,"test_4",5]]
 *          type=userGroups&action=getItems
 *          type=userGroups&action=getItems&limit=5&filters={"titleLike":"t","categoryIs":1,"categoryIn":[1,2],"objectLike":"t","objectIn":["test_1","test_2","test_3"],"actionLike":"t","userIDIn":[1,2,3],"groupIn":[1,2,3]}
 *          type=userGroups&action=getItems&keys=[[1,"test_1",1],[1,"test_1",2],[1,"test_4",5]]
 *_________________________________________________
 * setItems [CSRF protected]
 *       Set items. The type of the items depends on the type parameter.
 *
 *      params:
 *      update - bool, default false - if true, will only update existing items. Overrides "override" in that case.
 *      override - bool, default true - whether to override existing items.
 *      inputs - array of items to be created/changed. Each member's an associative array, of the form (depending on item type):
 *          *Actions and objects regex is /^[a-zA-Z][a-zA-Z0-9\.\-\_ ]{1,255}$/, while for Titles it's the same but max
 *           length is 1024.
 *          'categories' (when updating existing ones - aka 'update' and 'override' are true): [
 *                      'category' => <int, specific category>,
 *                      'title' => <string, could be null - valid title>,
 *                      ]
 *          'categories' (when creating existing ones - aka 'update' and 'override' are false): [
 *                      'title' => <string, could be null - valid title>,
 *                      ]
 *          'objects': [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'title' => <string, could be null - valid title>,
 *                      'public' => <bool, whether the object is considered public by default - specific application logic may ignore this, though>
 *                     ]
 *          'actions': [
 *                      'category' => <int, specific category>,
 *                      'action' => <string, action>,
 *                      'title' => <string, could be null - valid title>,
 *                     ]
 *          'groups' (when updating existing ones - aka 'update' and 'override' are true): [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'group' => <int, specific group>,
 *                      'title' => <string, could be null - valid title>,
 *                     ]
 *          'groups' (when creating existing ones - aka 'update' and 'override' are false): [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'title' => <string, could be null - valid title>,
 *                     ]
 *          'objectUsers': [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'userID' => <int, user ID>,
 *                      'action' => <string, action>
 *                      ]
 *          'objectGroups': [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'group' => <int, specific group>,
 *                      'action' => <string, action>,
 *                      ]
 *          'userGroups': [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'userID' => <int, user ID>,
 *                      'group' => <int, specific group>,
 *                      ]
 *
 *
 *      Returns: Array|Int, if not creating new auto-incrementing items, array of the form:
 *          <identifier> => <code>
 *          Where each identifier is the contact identifier, and possible codes are:
 *         -2 - failed to create items since one of the dependencies is missing
 *         -1 - failed to connect to db
 *          0 - success
 *          1 - item does not exist (and update is true)
 *          2 - item exists (and override is false)
 *          3 - trying to create a new item with missing inputs
 *
 *          Otherwise, one of them codes:
 *         -3 - Missing inputs when creating one of the items
 *         -2 - One of the dependencies missing.
 *         -1 - unknown database error
 *          int, >0 - ID of the FIRST created item. If creating more than one items, they can be assumed
 *                    to be created in the order they were passed.
 *
 * Examples:
 *      type=categories&action=setItems&inputs=[{"title":"test"}]&override=false&update=false
 *      type=categories&action=setItems&inputs=[{"category":1,"title":"test 2"}]&override=true&update=true
 *      type=objects&action=setItems&inputs=[{"category":1,"object":"test_1","title":"test 1","public":false},{"category":1,"object":"test_2","title":"test 2"}]
 *      type=actions&action=setItems&inputs=[{"category":1,"action":"test_1","title":"test 1"},{"category":1,"action":"test_2","title":"test 2"}]
 *      type=groups&action=setItems&inputs=[{"category":1,"object":"test_1","title":"test 1"}]&override=false&update=false
 *      type=groups&action=setItems&inputs=[{"category":1,"object":"test_1","group":1,"title":"test 6246"}]&override=true&update=true
 *      type=objectUsers&action=setItems&inputs=[{"category":1,"object":"test_1","userID":1,"action":"test_1"},{"category":1,"object":"test_1","userID":1,"action":"test_2"},{"category":1,"object":"test_1","userID":2,"action":"test_1"}]
 *      type=objectGroups&action=setItems&inputs=[{"category":1,"object":"test_1","group":1,"action":"test_1"},{"category":1,"object":"test_1","group":2,"action":"test_2"}]
 *      type=userGroups&action=setItems&inputs=[{"category":1,"object":"test_1","userID":1,"group":1},{"category":1,"object":"test_1","userID":1,"group":2},{"category":1,"object":"test_1","userID":2,"group":1}]
 *_________________________________________________
 * deleteItems [CSRF protected]
 *      Deletes items. The type of the items depends on the type parameter.
 *
 *      params:
 *      items - array of keys. Array of arrays, each member represents the requested identifiers, and is of the following form
 *           depending on the type parameter:
 *          'categories' : [
 *                      'category' => <int, specific category>,
 *                      ]
 *          'objects': [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                     ]
 *          'actions': [
 *                      'category' => <int, specific category>,
 *                      'action' => <string, action>,
 *                     ]
 *          'groups' : [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'group' => <int, specific group>
 *                     ]
 *          'objectUsers' : [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'userID' => <int, user ID>,
 *                      'action' => <string, action>
 *                      ]
 *          'objectGroups' : [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'group' => <int, specific group>,
 *                      'action' => <string, action>,
 *                      ]
 *          'userGroups' : [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'userID' => <int, user ID>,
 *                      'group' => <int, specific group>,
 *                      ]
 *
 *      Returns codes:
 *          -1 server error (would be the same for all)
 *           0 success (does not check if items do not exist)
 *
 * Examples:
 *      type=categories&action=deleteItems&items=[{"category":1}]
 *      type=objects&action=deleteItems&items=[{"category":1,"object":"test_1"},{"category":1,"object":"test_2"}]
 *      type=actions&action=deleteItems&items=[{"category":1,"action":"test_1"},{"category":1,"action":"test_2"}]
 *      type=groups&action=deleteItems&items=[{"category":1,"object":"test_1","group":1}]
 *      type=objectUsers&action=deleteItems&items=[{"category":1,"object":"test_1","userID":1,"action":"test_1"},{"category":1,"object":"test_1","userID":1,"action":"test_2"},{"category":1,"object":"test_1","userID":2,"action":"test_1"}]
 *      type=objectGroups&action=deleteItems&items=[{"category":1,"object":"test_1","group":1,"action":"test_1"},{"category":1,"object":"test_1","group":2,"action":"test_2"}]
 *      type=userGroups&action=deleteItems&items=[{"category":1,"object":"test_1","userID":1,"group":1},{"category":1,"object":"test_1","userID":1,"group":2},{"category":1,"object":"test_1","userID":2,"group":1}]
 *_________________________________________________
 * moveItems [CSRF protected]
 *       Moves some types of items. The type of the items depends on the type parameter.
 *
 *      params:
 *      items - Same as deleteItems - array of arrays, each member an identifier, depending on the type parameter:
 *          'categories' : [
 *                      'category' => <int, specific category>,
 *                      ]
 *          'objects': [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                     ]
 *          'actions': [
 *                      'category' => <int, specific category>,
 *                      'action' => <string, action>,
 *                     ]
 *          'groups' : [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'group' => <int, specific group>
 *                     ]
 *          'objectUsers' : [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'userID' => <int, user ID>,
 *                      'action' => <string, action>
 *                      ]
 *          'objectGroups' : [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'group' => <int, specific group>,
 *                      'action' => <string, action>,
 *                      ]
 *          'userGroups' : [
 *                      'category' => <int, specific category>,
 *                      'object' => <string, object>,
 *                      'userID' => <int, user ID>,
 *                      'group' => <int, specific group>,
 *                      ]
 *      inputs - Object. Needs to contain the keys from they type array's "moveColumns" -
 *                  the values are the new identifiers, of the form:
 *          'objects': [
 *                      'category' => <int, specific category>
 *                     ]
 *          'actions': [
 *                      'category' => <int, specific category>
 *                     ]
 *          'groups' : [
 *                      'object' => <string, object>
 *                     ]
 *          'objectUsers' : [
 *                      'object' => <string, object>
 *                      ]
 *          'objectGroups' : [
 *                      'object' => <string, object>
 *                      ]
 *          'userGroups' : [
 *                      'object' => <string, object>
 *                      ]
 *
 *      Returns one of the following codes:
 *      -2 dependency error
 *      -1 db error
 *      0 success
 *      1 input error
 *
 * Examples:
 *      type=objects&action=moveItems&items=[{"category":1,"object":"test_1"},{"category":1,"object":"test_2"}]&inputs={"category":2}
 *      type=actions&action=moveItems&items=[{"category":1,"action":"test_1"},{"category":1,"action":"test_2"}]&inputs={"category":2}
 *      type=groups&action=moveItems&items=[{"category":1,"object":"test_1","group":1}]&inputs={"object":2}
 *      type=objectUsers&action=moveItems&items=[{"category":1,"object":"test_1","userID":1,"action":"test_1"},{"category":1,"object":"test_1","userID":1,"action":"test_2"},{"category":1,"object":"test_1","userID":2,"action":"test_1"}]&inputs={"object":2}
 *      type=objectGroups&action=moveItems&items=[{"category":1,"object":"test_1","group":1,"action":"test_1"},{"category":1,"object":"test_1","group":2,"action":"test_2"}]&inputs={"object":2}
 *      type=userGroups&action=moveItems&items=[{"category":1,"object":"test_1","userID":1,"group":1},{"category":1,"object":"test_1","userID":1,"group":2},{"category":1,"object":"test_1","userID":2,"group":1}]&inputs={"object":2}
 *_________________________________________________
 * */

if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../../main/core_init.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';
require 'object-auth_fragments/definitions.php';
require __DIR__ . '/../../IOFrame/Handlers/ObjectAuthHandler.php';

if($test)
    echo 'Testing mode!'.EOL;

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];
if(!checkApiEnabled('object-auth',$apiSettings,$SecurityHandler,$_REQUEST['action']))
    exit(API_DISABLED);

if(!isset($_REQUEST["type"]))
    exit('Item type not specified!');
elseif(!in_array($_REQUEST["type"],['categories','objects','actions','groups','objectUsers','objectGroups','userGroups'])){
    exit('Invalid item type!');
}
else
    $type = $_REQUEST["type"];

switch($action){
    case 'getItems':

        $arrExpected =["keys","filters","orderBy","orderType","offset","limit"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'object-auth_fragments/getItems_auth.php';
        require 'object-auth_fragments/getItems_checks.php';
        require 'object-auth_fragments/getItems_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'setItems':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["inputs","update","override"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'object-auth_fragments/setItems_auth.php';
        require 'object-auth_fragments/setItems_checks.php';
        require 'object-auth_fragments/setItems_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'deleteItems':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["items"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'object-auth_fragments/deleteItems_auth.php';
        require 'object-auth_fragments/deleteItems_checks.php';
        require 'object-auth_fragments/deleteItems_execution.php';
        echo ($result === 0)?
            '0' : $result;
        break;

    case 'moveItems':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["items","inputs"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'object-auth_fragments/moveItems_auth.php';
        require 'object-auth_fragments/moveItems_checks.php';
        require 'object-auth_fragments/moveItems_execution.php';
        echo ($result === 0)?
            '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}



