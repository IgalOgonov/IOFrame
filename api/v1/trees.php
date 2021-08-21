<?php
/* This API Is used to retrieve trees from the database.
 * There are 2 representations you may use to store trees, an Euler Tour Tree or an Associated Array tree (in JS - object).
 *
 * Euler tree form:
 *  [
 *   <Node ID> => [
 *      "content" => <Node Content>,
 *      "smallestEdge" => <Smallest Connected Edge>,
 *      "largestEdge" => <Largest Connected Edge>
 *   ],
 *   ...
 * ]
 *
 * Associated tree form:
 * "<content>": {
 *      "<content>":{
 *              ...
 *          },
 *      "<content>":{
 *              ...
 *          },
 *          ...
 *      }
 *
 *      See standard return values at defaultInputResults.php
 *
 * Available operations include:
 *_________________________________________________
 *      addTrees [CSRF protected]
 *      - Add trees to the database.
 *        'inputs' should be a JSON encoded string of the (original) form:
 *          {
 *          "<treeName1>":{ "content":"<json encoded associated (or euler) tree>", "override":"<whether to override exiting tree>" }
 *          "<treeName2>":{ "content":"<json encoded associated (or euler) tree>", "override":"<whether to override exiting tree>" }
 *          ...
 *          }
 *        Returns:
 *          0 On success of all trees
 *          Array of the form {"treeName":<Error Code>} where the error codes are:
 *              1  Cannot override existing tree!
 *              -1 Internal server error!
 *
 *        Examples: action=addTrees&inputs={"test_euler_tree3":{"content":[{"content":"test","smallestEdge":0,"largestEdge":0}],"override":false}}
 *_________________________________________________
 *      removeTrees [CSRF protected]
 *      - Removes trees from the database.
 *        'inputs' should be a JSON encoded string of the (original) form:
 *          {
 *          "<treeName1>":{"onlyEmpty":"<whether to delete only a tree that's empty - default true>" },
 *          ...
 *          }
 *        Returns:
 *          0 On success of all trees
 *          Array of the form {"treeName":<Error Code>} where the error codes are:
 *              1 if trying to delete a non-empty tree when onlyEmpty is false
 *              -1 Internal server error!
 *
 *        Examples: action=removeTrees&inputs={"test_euler_tree1":{"onlyEmpty":true}}
 *_________________________________________________
 *      updateNodes [CSRF protected]
 *      - Updates specific nodes
 *        'treeName' - the name of the tree to update
 *        'content' - json encoded array of the form [ [<nodeID*>,<newNodeContent>], ... ]
 *                    *IDs are the IDs of nodes in an Euler Tour tree.
 *        Returns:
 *          1 On success - also if the tree didn't exist
 *          0 On failure
 *
 *        Examples: action=updateNodes&treeName=someTree&content=[[0,"Root"],[1,"Node 1 - modified"]]
 *_________________________________________________
 *      getSubtree:
 *      - Returns a subtree of a tree from the database
 *        'treeName' should be the name of the tree
 *        'nodeID' should be the ID of the node (0 just returns the whole tree)
 *        'returnType' Default 'numbered' for an associated JSON encoded tree, but may be 'euler' or 'assoc'
 *        'lastUpdated' Will only return trees last updated after this. Default 0 (any tree updated after 0 - any tree ever)
 *        Returns:
 *          0 If the subtree (or tree) do not exist.
 *          A JSON encoded associated (or Euler) tree if the subtree does exist. Note that in case of an Euler tree, the target node becomes the root!
 *
 *        Examples: action=getSubtree&treeName=someTree&nodeID=3&returnType=euler&lastUpdated=0
 *_________________________________________________
 *      getTrees [CSRF protected]
 *      - Returns a tree (or multiple trees)
 *        'inputs' should be a JSON of the form {'treeName':{"returnType":<return Type>,'lastUpdated':<as in getSubTree>}, ...} where the return type is
 *                 is the same as in getSubtree
 *        Returns:
 *          A json array of the form {'treeName':result}
 *              where the result may be a JSON encoded tree of the requested type, or an error code 0 - tree does not exist.
 *
 *        Examples: action=getTrees&inputs={"treeName":{"returnType":"assoc","lastUpdated":0},"treeName2":{"returnType":"assoc","lastUpdated":3525235}}
 *_________________________________________________
 *      linkNodes [CSRF protected]
 *      - Links one tree to be a subtree of an existing node in an existing tree.
 *        'treeName' should be the name of the tree to link to
 *        'nodeID' should be the ID of the node to add the new subtree to
 *        'nodeChildNumber' Which child of the node specified in nodeID the new subtree should be. Default 0 (first child)
 *        'newNodeArray' should be a JSON encoded associated tree (or Euler tree) array
 *        Returns:
 *          0 On success
 *          1 If the tree or specified node do not exist
 *
 *        Examples: action=linkNodes&treeName=someTree&nodeID=4&nodeChildNumber=1&newNodeArray=[{"content":"test","smallestEdge":0,"largestEdge":0}]
 *_________________________________________________
 *      cutNodes [CSRF protected]
 *      - Cuts nodes from an existing tree.
 *        'treeName' the name of the tree to cut from
 *        'nodeID' the node to cut
 *        'returnType' same as in getSubtree - default associated tree
 *        Returns:
 *          JSON encoded cut tree - type depends on returnType
 *          1 If the tree or specified node do not exist
 *
 *        Examples: action=cutNodes&treeName=someTree&nodeID=1&returnType=euler
 *_________________________________________________
 *      moveNodes [CSRF protected]
 *      - Cuts nodes from one tree, and links them to either that or a different tree.
 *        'treeName' the name of the tree to cut from
 *        'nodeID' the node to cut
 *        'targetNodeID' the node to link to in the target tree
 *        'targetChildNumber' Which child of the node specified in targetNodeID the new subtree should be. Default 0 (first child)
 *        'targetTreeName' - default ''. If provided, will link the nodes to a different tree.
 *        Returns:
 *          0 on success
 *          1 If the the tree/node to cut do not exist
 *          2 If the tree/node to link do not exist
 *
 *        Examples: action=moveNodes&treeName=someTree&nodeID=1&targetTreeName=otherTree&targetNodeID=4&targetChildNumber=0
 *_________________________________________________
 *      getTreeMap:
 *      - Gets all available trees.
 *        Examples: action=getTreeMap
 */

if(!defined('coreInit'))
    require __DIR__ . '/../../main/coreInit.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';

require __DIR__ . '/../../IOFrame/Handlers/TreeHandler.php';
require __DIR__ . '/../../IOFrame/Util/validator.php';

if($test){
    echo 'Testing mode!'.EOL;
    foreach($_REQUEST as $key=>$value)
        echo htmlspecialchars($key.': '.$value).EOL;
}

//Make sure there is an action
if(!isset($_REQUEST['action']))
    exit('No action specified');

if(!checkApiEnabled('trees',$apiSettings,$_REQUEST['action']))
    exit(API_DISABLED);

//Recursive function to validate an assoc array.
function validateAssocArray($assocArray){
    if(!is_array($assocArray))
        return false;

    $res = true;
    foreach($assocArray as $content=>$children){
        if(!is_array($children))
            return false;
        if(strlen($content) > TREE_MAX_CONTENT_LENGTH)
            return false;
        if($children !== [])
            $res = validateAssocArray($children);
    }
    return $res;
}

//Recursive function to validate an assoc array.
function validateEulerArray($eulerArray){
    if(!is_array($eulerArray))
        return false;
    foreach($eulerArray as $id => $triplet){
        if(preg_match('/\D/',$id))
            return false;

        if(!isset($triplet['content']) || !isset($triplet['smallestEdge']) || !isset($triplet['largestEdge']))
            return false;

        if(preg_match('/\D/',$triplet['smallestEdge']) || preg_match('/\D/',$triplet['largestEdge']))
            return false;

        if(strlen($triplet['content']) > TREE_MAX_CONTENT_LENGTH)
            return false;
    }
    return true;
}

//Make sure the action is valid, and has all relevant parameters set.
//Also, make sure the user is authorized to perform the action.
switch($_REQUEST['action']){
    /*Auth, and ensure needed inputs are present*/
    case 'addTrees':

        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        //inputs array - existence and validation
        if(!isset($_REQUEST['inputs'])){
            if($test)
                echo 'Inputs array must be specified with addTrees!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!IOFrame\Util\is_json($_REQUEST['inputs'])){
            if($test)
                echo 'Inputs array must be a JSON array!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        $inputs = json_decode($_REQUEST['inputs'],true);

        if(!is_array($inputs)){
            if($test)
                echo 'Inputs array must be an array!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        foreach($inputs as $treeName=>$params){
            if(!\IOFrame\Util\validator::validateSQLTableName($treeName)){
                if($test)
                    echo 'Illegal tree name!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!is_array($params)){
                if($test)
                    echo 'Tree params must be an array!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!isset($params['content'])){
                if($test)
                    echo 'Every new tree has to have content!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!validateAssocArray($params['content']) && !validateEulerArray($params['content'])){
                if($test)
                    echo 'Content of each tree must be a valid Euler or Associated array!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(isset($params['override'])){
                if($params['override']!==0 && $params['override']!==1  && $params['override']!=='1'  && $params['override']!=='0' &&
                    $params['override']!==true && $params['override']!==false  && $params['override']!=='true'  && $params['override']!=='false' ){
                    if($test)
                        echo 'override for tree '.$treeName.' must be a boolean!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
            }
        }

        if( !(
            $auth->isAuthorized(0) ||
            $auth->hasAction(TREE_MODIFY_ALL) ||
            $auth->hasAction(TREE_C_AUTH)
             )
        ){
            if($test)
                echo 'Insufficient auth to add a new tree!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
        break;
    //--------------------
    case 'removeTrees':

        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        //Will be used to check which trees we may remove
        $treesToRemoveAuth = [];

        //inputs array - existence and validation
        if(!isset($_REQUEST['inputs'])){
            if($test)
                echo 'Inputs array must be specified with removeTrees!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!IOFrame\Util\is_json($_REQUEST['inputs'])){
            if($test)
                echo 'Inputs array must be a JSON array!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        $inputs = json_decode($_REQUEST['inputs'],true);

        if(!is_array($inputs)){
            if($test)
                echo 'Inputs array must be an array!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        foreach($inputs as $treeName=>$params){
            //This marks the tree as one we need to check individual auth for
            array_push($treesToRemoveAuth,$treeName);
            if(!\IOFrame\Util\validator::validateSQLTableName($treeName)){
                if($test)
                    echo 'Illegal tree name!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(!is_array($params)){
                if($test)
                    echo 'Tree params must be an array!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(isset($params['onlyEmpty'])){
                if($params['onlyEmpty']!==0 && $params['onlyEmpty']!==1  && $params['onlyEmpty']!=='1'  && $params['onlyEmpty']!=='0' &&
                    $params['onlyEmpty']!==true && $params['onlyEmpty']!==false  && $params['onlyEmpty']!=='true'  && $params['onlyEmpty']!=='false' ){
                    if($test)
                        echo 'onlyEmpty for tree '.$treeName.' must be a boolean!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
            }
            //This removes the tree from the list of trees we are not individually authorized to remove
//            if($AuthHandler->hasAction(TREE_D_ACTION.$_REQUEST['treeName'])){
//                unset($treesToRemoveAuth[count($treesToRemoveAuth)-1]);
//            }
        }

        if( !(
            $auth->isAuthorized(0) ||
            $auth->hasAction(TREE_MODIFY_ALL) ||
            $auth->hasAction(TREE_D_AUTH)
        )
        ){
            //Only relevant if there are trees we are not individually authorized to remove
            if($treesToRemoveAuth !== []){
                if($test)
                    echo 'Insufficient auth to remove trees '.json_encode($treesToRemoveAuth).EOL;
                exit(AUTHENTICATION_FAILURE);
            }
        }
        break;
    //--------------------
    case 'updateNodes':

        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        if(!isset($_REQUEST['treeName'])){
            if($test)
                echo 'Inputs array must be specified with updateNodes!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!\IOFrame\Util\validator::validateSQLTableName($_REQUEST['treeName'])){
            if($test)
                echo 'Illegal tree name!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!isset($_REQUEST['content'])){
            if($test)
                echo 'Need some content to update the nodes with!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!IOFrame\Util\is_json($_REQUEST['content'])){
            if($test)
                echo 'Content must be JSON encoded!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        $contentArray = json_decode($_REQUEST['content'],true);

        if(!is_array($contentArray)){
            if($test)
                echo 'Content must be an array!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        foreach($contentArray as $val){
            if(preg_match('/\D/',$val[0])){
                if($test)
                    echo 'Content ID contains non-digits or is negative!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            if(strlen($val[1]) > TREE_MAX_CONTENT_LENGTH){
                if($test)
                    echo 'Content too long!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }

        if( !(
            $auth->isAuthorized(0) ||
            $auth->hasAction(TREE_MODIFY_ALL) ||
            $auth->hasAction(TREE_C_AUTH) /*||
            $auth->hasAction(TREE_D_ACTION.$_REQUEST['treeName'])*/
        )
        ){
            if($test)
                echo 'Insufficient auth update tree '.$_REQUEST['treeName'].'!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
        break;
    //--------------------
    case 'getSubtree':
        if(!isset($_REQUEST['treeName'])){
            if($test)
                echo 'treeName must be specified with getSubtree!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!\IOFrame\Util\validator::validateSQLTableName($_REQUEST['treeName'])){
            if($test)
                echo 'Illegal tree name!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!isset($_REQUEST['nodeID'])){
            if($test)
                echo 'nodeID must be specified with getSubtree!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(preg_match('/\D/',$_REQUEST['nodeID'])){
            if($test)
                echo 'Node ID contains non-digits or is negative!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(isset($_REQUEST['returnType'])){
            if($_REQUEST['returnType'] !== 'assoc' && $_REQUEST['returnType'] !== 'euler' && $_REQUEST['returnType'] !== 'numbered'){
                if($test)
                    echo 'Invalid return type!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }
        else
            $_REQUEST['returnType'] = 'numbered';

        if(isset($_REQUEST['lastUpdated'])){
            if(preg_match('/\D/',$_REQUEST['lastUpdated'])){
                if($test)
                    echo 'lastUpdated contains non-digits or is negative!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }
        else
            $_REQUEST['lastUpdated'] = 0;

        //In this specific case authentication is done at tree level
        break;
    //--------------------
    case 'getTrees':
        if(!isset($_REQUEST['inputs'])){
            if($test)
                echo 'Inputs array must be specified with getTrees!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!IOFrame\Util\is_json($_REQUEST['inputs'])){
            if($test)
                echo 'Inputs must be a valid JSON!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        $inputs = json_decode($_REQUEST['inputs'],true);

        if(!is_array($inputs)){
            if($test)
                echo 'Inputs must be an array!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        foreach( $inputs as $treeName=>$valArr){
            if(!isset($treeName)){
                if($test)
                    echo 'Each tree in getTrees must have a valid name!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }

            if(!\IOFrame\Util\validator::validateSQLTableName($treeName)){
                if($test)
                    echo 'Illegal tree name!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }

            if($valArr['returnType'] !== 'assoc' && $valArr['returnType'] !== 'euler' && $valArr['returnType'] !== 'numbered'){
                if($test)
                    echo 'Invalid return type for '.$treeName.'!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }

            if(preg_match('/\D/',$valArr['lastUpdated'])){
                if($test)
                    echo 'lastUpdated contains non-digits or is negative for '.$treeName.'!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }

        //In this specific case authentication is done at tree level
        break;
    //--------------------
    case 'linkNodes':

        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        if(!isset($_REQUEST['treeName'])){
            if($test)
                echo 'Missing a valid tree name!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!\IOFrame\Util\validator::validateSQLTableName($_REQUEST['treeName'])){
            if($test)
                echo 'Illegal tree name!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!isset($_REQUEST['nodeID'])){
            if($test)
                echo 'nodeID must be specified with linkNodes!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(preg_match('/\D/',$_REQUEST['nodeID'])){
            if($test)
                echo 'Node ID contains non-digits or is negative!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(isset($_REQUEST['nodeChildNumber'])){
            if(preg_match('/\D/',$_REQUEST['nodeChildNumber'])){
                if($test)
                    echo 'nodeChildNumber contains non-digits or is negative!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }
        else
            $_REQUEST['nodeChildNumber'] = 0;

        if(!IOFrame\Util\is_json($_REQUEST['newNodeArray'])){
            if($test)
                echo 'newNodeArray must be a json!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        $newNodeArray = json_decode($_REQUEST['newNodeArray'],true);

        if(!validateAssocArray($newNodeArray) && !validateEulerArray($newNodeArray)){
            if($test)
                echo 'newNodeArray must be a valid Euler or Associated array!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if( !(
            $auth->isAuthorized(0) ||
            $auth->hasAction(TREE_MODIFY_ALL) ||
            $auth->hasAction(TREE_U_AUTH) /*||
            $auth->hasAction(TREE_U_ACTION.$_REQUEST['treeName'])*/
        )
        ){
            if($test)
                echo 'Insufficient auth to link to a tree!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
        break;
    //--------------------
    case 'cutNodes':

        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        if(!isset($_REQUEST['treeName'])){
            if($test)
                echo 'Missing a valid tree name!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!\IOFrame\Util\validator::validateSQLTableName($_REQUEST['treeName'])){
            if($test)
                echo 'Illegal tree name!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!isset($_REQUEST['nodeID'])){
            if($test)
                echo 'nodeID must be specified with cutNodes!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(preg_match('/\D/',$_REQUEST['nodeID'])){
            if($test)
                echo 'Node ID contains non-digits or is negative!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(isset($_REQUEST['returnType'])){
            if($_REQUEST['returnType'] !== 'assoc' && $_REQUEST['returnType'] !== 'euler'){
                if($test)
                    echo 'Invalid return type!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }
        else
            $_REQUEST['returnType'] = 'assoc';

        if( !(
            $auth->isAuthorized(0) ||
            $auth->hasAction(TREE_MODIFY_ALL) ||
            $auth->hasAction(TREE_U_AUTH) /*||
            $auth->hasAction('TREE_U_ACTION'.$_REQUEST['treeName'])*/
        )
        ){
            if($test)
                echo 'Insufficient auth to cut from a tree!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
        break;
    //--------------------
    case 'moveNodes':

        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        if(!isset($_REQUEST['treeName'])){
            if($test)
                echo 'Missing a valid tree name!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!\IOFrame\Util\validator::validateSQLTableName($_REQUEST['treeName'])){
            if($test)
                echo 'Illegal tree name!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(!isset($_REQUEST['nodeID'])){
            if($test)
                echo 'nodeID must be specified with moveNodes!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(preg_match('/\D/',$_REQUEST['nodeID'])){
            if($test)
                echo 'Node ID contains non-digits or is negative!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(isset($_REQUEST['targetTreeName'])){
            if(!\IOFrame\Util\validator::validateSQLTableName($_REQUEST['targetTreeName'])){
                if($test)
                    echo 'Illegal target tree name!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }
        else
            $_REQUEST['targetTreeName'] = $_REQUEST['treeName'];

        if(isset($_REQUEST['targetChildNumber'])){
            if(preg_match('/\D/',$_REQUEST['targetChildNumber'])){
                if($test)
                    echo 'targetChildNumber contains non-digits or is negative!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }
        else
            $_REQUEST['targetChildNumber'] = 0;

        if(!isset($_REQUEST['targetNodeID'])){
            if($test)
                echo 'targetNodeID must be specified with moveNodes!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if(preg_match('/\D/',$_REQUEST['targetNodeID'])){
            if($test)
                echo 'Node ID contains non-digits or is negative!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }


        if( !(
            $auth->isAuthorized(0) ||
            $auth->hasAction(TREE_MODIFY_ALL) ||
            $auth->hasAction(TREE_U_AUTH)/* ||
            (
                $auth->hasAction('TREE_U_ACTION'.$_REQUEST['treeName'])
                &&
                $auth->hasAction('TREE_U_ACTION'.$_REQUEST['targetTreeName'])
            )*/
        )
        ){
            if($test)
                echo 'Insufficient auth to move nodes in a tree (or between trees)!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
        break;
    //--------------------
    case 'getTreeMap':
        break;
    //--------------------
    default:
        if($test)
            echo 'Specified action is not recognized'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
}

//If the system has no RedisHandler, we cannot use cache
if(!isset($RedisHandler))
    $RedisHandler = null;

//Do what needs to be done
switch($_REQUEST['action']){
    //Assuming we got here, the user is authorized. Now, if his action was "getAvalilable" or "getInfo", he might need
    //to see the plugins' images (icon, thumbnail). So, we make sure to call ensurePublicImages on each.

    case 'addTrees':

        $TreeHandler = new \IOFrame\Handlers\TreeHandler(
            []
            ,$settings,
            ['SQLHandler'=>$SQLHandler,'logger'=>$logger,'RedisHandler'=>$RedisHandler]
        );

        $inputs = json_decode($_REQUEST['inputs'],true);

        foreach($inputs as $k=>$v){
            $inputs[$k]['updateDB'] = true;
            if(isset($inputs[$k]['override']) && $inputs[$k]['override'] === 'false')
                $inputs[$k]['override'] = false;
        }

        $res = $TreeHandler->addTrees($inputs,['test'=>$test]);

        if(gettype($res) == 'string')
            echo $res;
        else
            echo json_encode($res);

        break;

    case 'removeTrees':

        $inputs = json_decode($_REQUEST['inputs'],true);

        $treeNames = [];

        foreach($inputs as $k=>$v){
            $treeNames[$k] = 0;
            $inputs[$k]['updateDB'] = true;
            if(isset($inputs[$k]['onlyEmpty']) && $inputs[$k]['onlyEmpty'] === 'false')
                $inputs[$k]['onlyEmpty'] = false;
        }

        $TreeHandler = new \IOFrame\Handlers\TreeHandler(
            $treeNames
            ,$settings,
            ['SQLHandler'=>$SQLHandler,'logger'=>$logger,'RedisHandler'=>$RedisHandler]
        );

        $res  = $TreeHandler->removeTrees($inputs,['test'=>$test]);

        if(gettype($res) == 'string')
            echo $res;
        else
            echo json_encode($res);

        break;

    case 'updateNodes':

        $contentArray = json_decode($_REQUEST['content'],true);

        $TreeHandler = new \IOFrame\Handlers\TreeHandler(
            $_REQUEST['treeName']
            ,$settings,
            ['SQLHandler'=>$SQLHandler,'logger'=>$logger,'RedisHandler'=>$RedisHandler]
        );

        echo $TreeHandler->updateNodes($contentArray,$_REQUEST['treeName'],['updateDB'=>true,'test'=>$test])? '1':0;

        break;

    case 'getSubtree':

        $TreeHandler = new \IOFrame\Handlers\TreeHandler(
            [$_REQUEST['treeName']=>$_REQUEST['lastUpdated']]
            ,$settings,
            ['SQLHandler'=>$SQLHandler,'logger'=>$logger,'RedisHandler'=>$RedisHandler]
        );

        //Auth that wasn't checked in the earlier stage - if you are authorized, get even trees that are private
        if($auth->isLoggedIn())
            if( (
                $auth->isAuthorized(0) ||
                $auth->hasAction(TREE_R_AUTH)
            )
            ){
                $TreeHandler->getFromDB([$_REQUEST['treeName']=>$_REQUEST['lastUpdated']],['ignorePrivate'=>false, 'test'=>$test]);
            }

        echo json_encode(
            $TreeHandler->getSubtreeByID(
                $_REQUEST['treeName'],['returnType'=>$_REQUEST['returnType'],'nodeID'=>$_REQUEST['nodeID'],'test'=>$test]
            )
        );

        break;

    case 'getTrees':

        $inputs = json_decode($_REQUEST['inputs'],true);

        $eulerTreeNames = [];
        $assocTreeNames = [];
        $numberedTreeNames = [];

        $combinedArray = [];
        $resArray = [];

        foreach( $inputs as $treeName=>$valArr){
            if($valArr['returnType'] == 'euler')
                array_push($eulerTreeNames,$treeName);
            if($valArr['returnType'] == 'assoc')
                array_push($assocTreeNames,$treeName);
            if($valArr['returnType'] == 'numbered')
                array_push($numberedTreeNames,$treeName);
            $combinedArray[$treeName] = $valArr['lastUpdated'];
        }

        $TreeHandler = new \IOFrame\Handlers\TreeHandler(
            $combinedArray
            ,$settings,
            ['SQLHandler'=>$SQLHandler,'logger'=>$logger,'RedisHandler'=>$RedisHandler]
        );


        //Auth that wasn't checked in the earlier stage - if you are authorized, get even trees that are private
        if($auth->isLoggedIn())
            if( (
                $auth->isAuthorized(0) ||
                $auth->hasAction(TREE_R_AUTH)
            )
            ){
                $TreeHandler->getFromDB($combinedArray,['ignorePrivate'=>false, 'test'=>$test]);
            }

        foreach($eulerTreeNames as $eulerName){
            $resArray[$eulerName] = $TreeHandler->getTree($eulerName);
        }

        foreach($assocTreeNames as $assocName){
            $resArray[$assocName] = $TreeHandler->getAssocTree($assocName);
        }

        foreach($numberedTreeNames as $numberedName){
            $resArray[$numberedName] = $TreeHandler->getNumberedTree($numberedName);
        }

        echo json_encode($resArray);

        break;

    case 'linkNodes':


        $TreeHandler = new \IOFrame\Handlers\TreeHandler(
            $_REQUEST['treeName']
            ,$settings,
            ['SQLHandler'=>$SQLHandler,'logger'=>$logger,'RedisHandler'=>$RedisHandler,'ignorePrivate'=>false]
        );

        $newNodeArray = json_decode($_REQUEST['newNodeArray'],true);

        echo $TreeHandler->linkNodesToID(
            $_REQUEST['treeName'],
            $newNodeArray,
            $_REQUEST['nodeID'],
            ['updateDB'=>true, 'childNum'=>$_REQUEST['nodeChildNumber'],'test'=>$test]
        );

        break;

    case 'cutNodes':

        $TreeHandler = new \IOFrame\Handlers\TreeHandler(
            $_REQUEST['treeName']
            ,$settings,
            ['SQLHandler'=>$SQLHandler,'logger'=>$logger,'RedisHandler'=>$RedisHandler,'ignorePrivate'=>false]
        );

        echo json_encode(
            $TreeHandler->cutNodesByID(
            $_REQUEST['treeName'],
            $_REQUEST['nodeID'],
            ['updateDB'=>true, 'returnType'=>$_REQUEST['returnType'],'test'=>$test]
        )
        );

        break;

    case 'moveNodes':

        $treeNames = ($_REQUEST['targetTreeName'] == $_REQUEST['treeName']) ?
            $_REQUEST['treeName'] : [ $_REQUEST['targetTreeName'], $_REQUEST['treeName'] ] ;

        $TreeHandler = new \IOFrame\Handlers\TreeHandler(
            $treeNames
            ,$settings,
            ['SQLHandler'=>$SQLHandler,'logger'=>$logger,'RedisHandler'=>$RedisHandler,'ignorePrivate'=>false]
        );

        echo $TreeHandler->cutNodesByID(
            $_REQUEST['treeName'],
            $_REQUEST['nodeID'],
            [
                'updateDB'=>true,
                'link'=> [
                    $_REQUEST['targetTreeName'],
                    $_REQUEST['targetNodeID'],
                    [ 'childNum' => $_REQUEST['targetChildNumber'] ]
                ],
                'test'=>$test
            ]
        );

        break;

    case 'getTreeMap':
        $TreeHandler = new \IOFrame\Handlers\TreeHandler(
            []
            ,$settings,
            ['SQLHandler'=>$SQLHandler,'logger'=>$logger,'RedisHandler'=>$RedisHandler]
        );
        echo json_encode($TreeHandler->getTreeMap());
        break;

    default:
        exit('Specified action is not recognized');
}




?>