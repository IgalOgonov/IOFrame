<?php
/* This the the API that handles all the order related functions.
 * Note that this API is disabled by default in siteSettings (to enable it, create siteSetting 'ordersAPI' and set
 * it to true), due to the fact that this API is meant to be extended, not used AS-IS
 *
 *      See standard return values at defaultInputResults.php
 *_________________________________________________
 * getOrder
 *      - Gets an order from the DB
 *          id                  - int, order ID.
 *          includeOrderUsers   - bool, default true - gets the users related to the order, placed in a column 'User_Info'.
 *
 *        Examples:
 *          action=getOrder&id=1&includeOrderUsers=true
 *
 *        Returns:
 *        String - JSON encoded array of the DB fields
 *        OR
 *        Int - Codes which mean:
 *             -1 - failed to reach DB
 *              1 - order does not exist
 *_________________________________________________
 * getOrders
 *      - Gets multiple orders from the database
 *          ids                 - string, default null - JSON encoded array of IDs. If null, gets all orders instead.
 *          typeIs              - string, default null - If not null, only returns orders of this type
 *          statusIs            - string, default null - If not null, only returns orders with this status
 *          The following are ONLY relevant if IDs is null:
 *              limit: int, default 50, max 500, min 1 - limit the number of items you get
 *              offset: int, default 0 - used for pagination purposes with limit
 *              orderBy                - string, defaults to null. Possible values include 'Created' 'Last_Updated',
 *                                     and any of the names in $orderColumnNames
 *              orderType              - bool, defaults to null.  0 for 'ASC', 1 for 'DESC'
 *              createdAfter           - int, default null - Only return items created after this date.
 *              createdBefore          - int, default null - Only return items created before this date.
 *              changedAfter           - int, default null - Only return items last changed after this date.
 *              changedBefore          - int, default null - Only return items last changed  before this date.
 *
 *        Examples:
 *          action=getOrders
 *          action=getOrders&createdBefore=100000000000&createdAfter=0&changedAfter=0&changedBefore=10000000000&typeIs=test2&statusIs=testing&getLimitedInfo=true&orderBy=Created&orderType=0
 *
 *        Returns Array of the form:
 *
 *          [
 *           <id> => Code/Order,
 *           <id> => Code/Order,
 *           ...
 *          ]
 *          if $orderIDs is null, also returns the object '@' (stands for 'meta') inside which there is a
 *              single key '#', and the value is the total number of results if there was no limit.
 *
 *          Orders and codes are the same as the ones from getOrder
 *_________________________________________________
 * createOrder
 *      - Creates an order
 *          orderInfo   -  string, default null - a JSON encoded object to set.
 *          orderType   -  string, default null - Potential identifier of the order type.
 *          orderStatus -  string, default null - Potential identifier of the order status.
 *          [Extension] <Any additional column name in $orderColumnNames converted from Underscore_Case to camelCase>:
 *                      they ALWAYS default to null, which means unchanged.
 *                      To annul DB values, one needs to pass an empty string '' (and DB values can't be an empty string).
 *
 *        Examples:
 *          action=createOrder&orderInfo={"test":"test","test2":"test"}&orderType=test&orderStatus=testing
 *
 *        Returns Int:
 *              -1 - failed to connect to the db
 *              <ID> - The ID of the newly created order.
 *_________________________________________________
 * updateOrder
 *      - Updates an order
 *          id          - int, ID of the order to update
 *          orderInfo   -  string, default null - a JSON encoded object to set.
 *          orderType   -  string, default null - Potential identifier of the order type.
 *          orderStatus -  string, default null - Potential identifier of the order status.
 *          [Extension] <Any additional column name in $orderColumnNames converted from Underscore_Case to camelCase>:
 *                      they ALWAYS default to null, which means unchanged.
 *                      To annul DB values, one needs to pass an empty string '' (and DB values can't be an empty string).
 *
 *        Examples:
 *          action=updateOrder&id=1&orderInfo={"test":"test","test2":"test"}&orderType=test&orderStatus=testing
 *
 *        Returns Int:
 *              -3 - trying to update an order which does not exist
 *              -2 - failed to lock order - you should get the up-to-date information before trying again.
 *              -1 - failed to connect to the db
 *               0 - All good
 *_________________________________________________
 * archiveOrders
 *      - Moves orders to archive, and deletes them from the main table.
 *        This is a very limited version of the actual handler function.
 *        The maximum time limit for this action is 20 seconds. If it stops before that, you might need to run this again
 *        for all the orders you wanted to be archived.
 *        MUST BE AN ADMIN.
 *        PARAMETERS are the same as getOrders, except there is no limit to 'limit', and archiving will be executed
 *        in batches of 10000.
 *        Due to the above fact, the maximum number of archived orders per action is 200,000 (10,000 per batch, 1 batch per second,
 *        max 20 seconds).
 *           'returnIDMeta'     => bool, default true - Instead of returning all the archived/deleted IDs, which
 *                                 could be hundreds of thousands, returns the minimum,maximum and number of deleted/archived IDs.
 *                                 Their return form becomes:
 *                                 [
 *                                  'smallestID' => <number>,
 *                                  'largestID' => <number>,
 *                                  'total' => <number>,
 *                                 ]
 *
 *        Examples:
 *          action=archiveOrders&createdBefore=100000000000&createdAfter=0&changedAfter=0&changedBefore=10000000000&typeIs=test2&statusIs=testing&getLimitedInfo=true&orderBy=Created&orderType=0
 *          action=archiveOrders&returnIDMeta=false
 *
 *        Returns JSON encoded object:
 *          [
 *          'archivedIDs'  => int[]|Object, IDs of the orders that were successfully archived. Changes if 'returnIDMeta' is true.
 *          'deletedIDs'   => int[]|Object, IDs of the orders that were successfully deleted. Changes if 'returnIDMeta' is true.
 *
 *          'codeOrigin'   => string, 'backupTable', 'getOrders', 'timeout', 'deleteArchived' or ''
 *          'code'         => int, 0 if we stopped naturally or reached repeatToLimit,
 *                                  OR -1 if the order deletion function threw the code
 *                                  OR the code from backupTable()/getOrders() if we stopped cause that function threw the error code.
 *          ]
 *
 *_________________________________________________
 * getUserOrders TODO
 *      - Gets all the orders of a single user.
 *          userID - int, ID of the user
 *          getLimitedInfo - bool, default false -  Will only return Order_ID, Relation_Type, Created and Last_Updated columns
 *          returnOrders        - bool, default false - Returns all orders that belong to the user using
 *                                getOrders() with 'getLimitedInfo' param. Dumps each ORDERS result into a
 *                                reserved 'Orders_Info' column in the order array for the relevant order.
 *          relationType        - string, default null - if set, will only return results where Relation_Type
 *                                is EXACTLY like this param.
 *          relationTypeLike    - string, default null, overridden by relationType - if set and not overridden,
 *                                will only return results where Relation_Type is LIKE (RLIKE, Regex Like) this param.
 *          createdAfter        - same as in getOrders() but applies to user<=>order relationships
 *          createdBefore       - same as in getOrders() but applies to user<=>order relationships
 *          changedAfter        - same as in getOrders() but applies to user<=>order relationships
 *          changedBefore       - same as in getOrders() but applies to user<=>order relationships
 *          orderBy             - string, defaults to null. Possible values include 'Created' 'Last_Updated',
 *                                  and any of the names in $userOrderColumnNames
 *          orderType           - bool, defaults to null.  0 for 'ASC', 1 for 'DESC'
 *          limit               - string, SQL LIMIT, defaults to system default
 *          offset              - string, SQL OFFSET
 *          [extension]extraDBFilters - same as in getOrders() but applies to $userOrderColumnNames instead of the order ones.
 *
 *        Examples:
 *          action=getUserOrders&userID=1
 *          action=getUserOrders&userID=1&createdBefore=100000000000&createdAfter=0&changedAfter=0&changedBefore=10000000000&getLimitedInfo=true&returnLimitedOrders=true&orderBy=Created
 *
 *        Returns JSON encoded object:
 *          if 'returnLimitedOrders' is false:
 *          [
 *              <orderID> => <Array of USERS_ORDERS columns> OR 1 if 'orderIDs' was not empty and some orders didn't exist
 *          ]
 *          if 'returnLimitedOrders' is true:
 *          [
 *              <orderID> => <Array of USERS_ORDERS columns merged with ORDERS information (or 1 if the order no
 *                            longer exists) in the column Order_Info>, OR 1 if 'orderIDs' was not empty and some orders didn't exist
 *          ]
 *
 *_________________________________________________
 * assignUserToOrder
 *      - Assigns a user to an order.
 *        MUST BE AN ADMIN.
 *          userID            - int, ID of the user
 *          orderID           - int, ID of the order
 *          relationType      - string, default null - if set, will set the Relation_Type to the value.
 *          meta              - string, default null - if set, will set the Meta to the value.
 *                                                       MUST be a JSON encoded object.
 *          [Extension] <Any additional column name in $orderColumnNames converted from Underscore_Case to camelCase>
 *
 *        Examples:
 *          action=assignUserToOrder&userID=1&orderID=1&relationType=tester&meta={"test":"test"}
 *
 *        Returns int Code:
 *         -1 - failed to reach DB
 *          0 - All is well
 *          1 - Assignment already exists (update it instead)
 *          2 - Order does not exist
 *          3 - User does not exist
 *_________________________________________________
 * updateOrderUserAssignment
 *      - Assigns a user/order assignment.
 *        MUST BE AN ADMIN.
 *          userID            - int, ID of the user
 *          orderID           - int, ID of the order
 *          relationType      - string, default null - if set, will set the Relation_Type to the value.
 *          meta              - string, default null - if set, will set the Meta to the value.
 *                                                       MUST be a JSON encoded object.
 *          [Extension] <Any additional column name in $orderColumnNames converted from Underscore_Case to camelCase>
 *
 *        Examples:
 *          action=updateOrderUserAssignment&userID=1&orderID=1&relationType=stillATester&meta={"test":"test2"}
 *
 *        Returns int Code:
 *          -1 could not reach the db (for all the )
 *          0 - All is well
 *          1 - Assignment does not exist (create it instead)
 *          2 - Order does not exist
 *          3 - User does not exist
 *_________________________________________________
 * removeUserFromOrder
 *      -  Removes a single user from an order.
 *          userID            - int, ID of the user
 *          orderID           - int, ID of the order
 *
 *        Examples:
 *          action=removeUserFromOrder&userID=1&orderID=1
 *
 *        Returns int Code:
 *          -1 could not reach the db (for all the )
 *          0 - All is well
 * */

if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../../main/core_init.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';
require 'orders_fragments/definitions.php';
require __DIR__ . '/../../IOFrame/Handlers/PurchaseOrderHandler.php';

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];

if($test)
    echo 'Testing mode!'.EOL;

if(!checkApiEnabled('orders',$apiSettings,$SecurityHandler,$_REQUEST['action']))
    exit(API_DISABLED);

//Handle inputs
$inputs = [];

//Standard pagination inputs
$standardPaginationInputs = ['limit','offset','orderBy','orderType','createdAfter','createdBefore','changedAfter',
    'changedBefore'];

//Unlike most APIs, we define the handler here because it is needed for authentication
$PurchaseOrderHandler = new \IOFrame\Handlers\PurchaseOrderHandler(
    $settings,
    array_merge($defaultSettingsParams, ['siteSettings'=>$siteSettings])
);

//We also get the session details for that very reason
$loggedIn = $_SESSION['logged_in'] ?? false;
//There is not a single action here you may perform if you are not logged in
if(!$loggedIn){
    if($test)
        echo 'User must be logged in to use the orders API!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}
//Finally, get session details
$details = json_decode($_SESSION['details'],true);

switch($action){

    case 'getOrder':
        $arrExpected =["id","includeOrderUsers"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'orders_fragments/getOrder_validation_checks.php';
        require 'orders_fragments/getOrder_auth_checks.php';
        require 'orders_fragments/getOrder_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'getOrders':
        $arrExpected = array_merge(["ids","getLimitedInfo","typeIs","statusIs"],$standardPaginationInputs);

        require __DIR__ . '/../setExpectedInputs.php';
        require 'orders_fragments/getOrders_validation_checks.php';
        require 'orders_fragments/getOrders_auth_checks.php';
        require 'orders_fragments/getOrders_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'createOrder':
    case 'updateOrder':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["orderInfo","orderType","orderStatus"];
        if($action === 'updateOrder')
            $arrExpected[] = 'id';

        require __DIR__ . '/../setExpectedInputs.php';
        require 'orders_fragments/setOrder_auth_checks.php';
        require 'orders_fragments/setOrder_validation_checks.php';
        require 'orders_fragments/setOrder_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'archiveOrders':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = array_merge(["ids","typeIs","statusIs","returnIDMeta"],$standardPaginationInputs);

        require __DIR__ . '/../setExpectedInputs.php';
        require 'orders_fragments/archiveOrders_auth_checks.php';
        require 'orders_fragments/getOrders_validation_checks.php';
        require 'orders_fragments/archiveOrders_execution.php';

        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        break;

    case 'getUserOrders':
        $arrExpected = array_merge(["userID","returnOrders","getLimitedInfo","relationType"],$standardPaginationInputs);
        /* TODO
        require __DIR__ . '/../setExpectedInputs.php';
        require 'orders_fragments/getUserOrders_validation_checks.php';
        require 'orders_fragments/getUserOrders_auth_checks.php';
        require 'orders_fragments/getUserOrders_execution.php';
         */

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        break;

    case 'assignUserToOrder':
    case 'updateOrderUserAssignment':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["userID","orderID","relationType","meta"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'orders_fragments/setUserOrder_auth_checks.php';
        require 'orders_fragments/setUserOrder_validation_checks.php';
        require 'orders_fragments/setUserOrder_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'removeUserFromOrder':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["userID","orderID"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'orders_fragments/removeUserFromOrder_auth_checks.php';
        require 'orders_fragments/removeUserFromOrder_validation_checks.php';
        require 'orders_fragments/removeUserFromOrder_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}
