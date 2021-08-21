<?php
namespace IOFrame\Handlers{
    use IOFrame;
    use Monolog\Logger;
    define('ObjectHandler',true);
    if(!defined('abstractDB'))
        require 'abstractDB.php';
    if(!defined('safeSTR'))
        require __DIR__ . '/../Util/safeSTR.php';
    /**
     * Handles adding, deleting, and querying for objects and object inputs. Related Tables are Object_Chache and
     * Object_Cache_Meta.
     */

    //TODO optimize calls to redis - should use mGet/mSet instead of get/set for retrieving cached objects/groups/maps

    class ObjectHandler extends IOFrame\abstractDBWithCache {

        /** @var String This variable indicates the name of the table to query.
         *  The default system-wide table is 'OBJECTS_<CACHE/MAP/CACHE_META>', however different plugins
         *  may create different object tables (for example - 'COMMENT_<CACHE/MAP/CACHE_META>').
         *  This variable indicates the name of the table (group) we are operating on.
         */
        protected $tableName = 'OBJECT';


        /**
         * @var String Just a lowercase tableName for cache usege.
         */
        protected $cacheName = 'object';

        /**
         * Basic construction function, with added connection if it exists
         * @param SettingsHandler $settings regular settings handler.
         * @param SQLHandler $SQLHandler regular SQLHandler handler.
         * @param Logger $logger regular Logger
         * @param \PDO $conn Generic PDO connection
         */
        function __construct(SettingsHandler $settings,  $params = []){

            parent::__construct($settings,$params);

            //If we are operating on a different table than the default one, it must be passed to the Handler.
            if(isset($params['tableName'])){
                $this->tableName = strtoupper($params['tableName']);
                $this->cacheName = strtolower($params['tableName']);
            }
        }


        /** Adds an object to the database, in safeString format.
         * @param string $obj          - the object.
         * @param string $group        - optional group name.
         * @param int $minModifyRank   - Minimal rank required to modify the object.
         * @param int $minViewRank     - Minimal rank required to view the object. Set -1 for "free-for-all".
         * @param array $params        - Object array of the form:
         *                               'safeStr' => bool, default true. Whether to convert the object to safeString upon db insertion.
         *                               'extraColumns' => 1.5D array, default [], of the form:
         *                                                  [[<column name>,<default value>], ...]
         *                                                  where <default value> is null if a value is required. Example:
         *                                                  [
         *                                                  ['Extra_Col_Name',null],
         *                                                  ['Extra_Number',0],
         *                                                  ]
         *                                                 Specifies whether any extra columns are added to the
         *                                                 db query. Notice that if it's set, a matching 'extraContent'
         *                                                 array has to be validated in the API layer, else a critical
         *                                                 error may occur.
         *                               'extraContent' => 2D array, default []. Of dimensions NxE, where E is the number of
         *                                                 (at least the required) extra columns in extraColumns, and N
         *                                                 is the number of objects arrays. Example:
         *                                                 [
         *                                                 ['Extra Content 1', 02101],
         *                                                 ['Extra Content 2', 01],
         *                                                 ['Extra Content 2']
         *                                                 ]
         *                                                 Notice that this has to be validated - invalid input causes an
         *                                                 exception to be thrown. This example, for instance, only works if
         *                                                 the number of objects is 3.
         * @returns mixed Codes:
         *                  -2  Group exists, insufficient authorization to add object to group
         *                  -1 if you are not logged in
         *                  0 failed to set object
         *                  1  Extra Content related error
         *                OR
         *                  Array of the form ['ID' => <new object ID>] on success
         * */
        function addObject(string $obj, string $group = '', int $minModifyRank = 0, int $minViewRank = -1,array $params = []){
            $res = $this->addObjects([[$obj,$group,$minModifyRank,$minViewRank]],$params);
            if(!is_array($res))
                return $res;
            if(isset($res['ID']))
                return $res['ID'];
            else
                return $res[0];
        }


        /** Adds an object to the database, in safeString format.
         * @param array $inputs        - Inputs from adObject in the same order
         * @param array $params        -  Explained in addObject
         * @returns mixed Array of the form:
         *                  "ID":<**CONSTANT** ID of the **FIRST OBJECT OF ALL THOSE INSERTED**> on Success
         *                  <Object number in input array>:<Error code> where the error codes ar from addObject
         *                OR
         *                1 if there is a conflict in the extra content/columns
         * */
        function addObjects(array $inputs, $params = []){
            //It would be better to do it all with 1 query, due to authentication this is not possible until stored-procedure reimplementation.
            $groups = [];       //Groups to retrieve
            $objects = [];      //Objects to create
            $groupsToUpdate = [];
            $groupsToCreate = [];
            $gChange = [];      //Groups to change
            $gCreate = [];      //Groups to create
            $sesInfo = isset($_SESSION['details'])? json_decode($_SESSION['details'], true) : null;
            $updateTime = time();
            $tableName = $this->SQLHandler->getSQLPrefix().$this->tableName.'_CACHE';
            $res = [];
            //set default params

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            if(!isset($params['safeStr']))
                $safeStr = true;
            else
                $safeStr = $params['safeStr'];

            if(!isset($params['extraColumns']))
                $extraColumns = [];
            else
                $extraColumns = $params['extraColumns'];
            //Extract the actual names
            $extraColumnNames = [];
            foreach($extraColumns as $columnPair){
                array_push($extraColumnNames,$columnPair[0]);
            }

            if(!isset($params['extraContent']))
                $extraContent = [];
            else
                $extraContent = $params['extraContent'];

            //You must be logged in to use this handler, as it checks the caller's rank and ID.
            if(!IOFrame\Util\assertLogin() || $sesInfo == null){
                foreach($inputs as $i=>$input){
                    $res[$i] = -1;
                }
                return $res;
            }
            foreach($inputs as $i=>$input){
                //default values
                if(!isset($input[1]))
                    $input[1] = '';
                if(!isset($input[2]))
                    $input[2] = 0;
                if(!isset($input[3]))
                    $input[3] = -1;

                //If $minViewRank is lower than 0, all can view the object.
                if($input[3]<0 || $input[3] == null)
                    $inputs[$i][3] = -1;
                //Having someone able to modify the object but not view it is illogical.
                else if($input[2] > $input[3] && ($input[3] != -1))
                    $inputs[$i][3] = $input[2];
                if($input[2]<0 || $input[2] == null)        //Same as viewRank
                    $inputs[$i][2] = 0;

                //Check if a group for the object was specified
                if($input[1] != ''){
                    array_push($groups,$input[1]);
                }
                $objects[$i] = $input;

                //Convert to safeString if asked
                if($safeStr)
                    $inputs[$i][0] = IOFrame\Util\str2SafeStr($input[0]);
            }

            //Retrieve all marked groups
            if($groups != [])
                $groups = $this->retrieveGroups($groups,$params);

            //Group related
            foreach($objects as $inputOrderID => $obj){
                if($obj != 1){
                    $groupName = $obj[1];
                    $groupInfo = isset($groups[$groupName])? $groups[$groupName] : '';
                    //Check whether group exists
                    if(is_array($groupInfo)){
                        //Check user authorization
                        if(!$this->checkObAddGroupAuth($groupInfo, $sesInfo,true, ['test'=>$test,'verbose'=>$verbose])){
                            if($verbose)
                                echo 'Could not create object because user lacks authorization to add objects to group '.$groupName.'! ';
                            $res[$inputOrderID] = -2;
                        }
                        else {
                            //Update the group
                            if(!isset($gChange[$groupName])){
                                $gChange[$groupName] = true;
                                array_push($groupsToUpdate,$groupName);
                            }
                        }
                    }
                    //If group doesn't exist, then..
                    else{
                        //..if it's a group..
                        if($groupName!='')
                            //.. create the group, if we even connected to the db.
                            if(!isset($gCreate[$groupName]) && $groupInfo !== -1 ){
                                $gCreate[$groupName] = true;
                                array_push($groupsToCreate,$groupName);
                            }
                    }
                }
            }

            //---- At this point, we have all the objects we want to create
            $updateParams =[];
            foreach($inputs as $inputOrderID => $input){
                //Check if the object creation is legal
                if(!isset($res[$inputOrderID])){
                    $objectArray = [
                        [$input[0],'STRING'],
                        $input[1]!=''?[$input[1],'STRING']:null,
                        [(string)$updateTime,'STRING'],
                        $sesInfo['ID'],
                        $input[2],
                        $input[3]
                    ];
                    foreach($extraColumns as $index=>$pair){
                        if(isset($extraContent[$inputOrderID][$index])){
                            $contentToAdd = $extraContent[$inputOrderID][$index];
                        }
                        else{
                            if($pair[1]!==null)
                                $contentToAdd = $pair[1];
                            else
                                return 1;
                        }
                        if(gettype($contentToAdd) == 'string')
                            $contentToAdd = [$contentToAdd,'STRING'];
                        array_push($objectArray,$contentToAdd);
                    }
                    array_push($updateParams,$objectArray);
                }
            }
            //Update relevant objects
            if($updateParams !== []){
                $columns = array_merge(
                    ['Object','Ob_Group','Last_Updated','Owner','Min_Modify_Rank','Min_View_Rank'],
                    $extraColumnNames
                );
                $request = $this->SQLHandler->insertIntoTable(
                    $tableName,
                    $columns,
                    $updateParams,
                    ['test'=>$test,'verbose'=>$verbose]
                );
            }
            else
                $request = false;

            //If we failed, return 5 for all objects that weren't set yet
            if($request !== true){
                foreach($objects as $inputOrderID => $input){
                    if(!isset($res[$inputOrderID]))
                        $res[$inputOrderID] = 0;
                }
                return $res;
            }
            //Get last inserted ID
            $res = $this->SQLHandler->lastInsertId();
            //Create new groups
            $this->createGroups($groupsToCreate,$sesInfo,$params);
            //Update other groups
            $this->updateGroups($groupsToUpdate,$params);

            return $res;

        }

        /* Updates an object if the ID $id.
         * @param int $id ID of object
         * @param string $content Replaces the content with $content
         * @param string $group Optionally changes group to $group
         * @param int $newVRank Optional new View required rank
         * @param int $newMRank Optional new Modify required rank
         * @param int mainOwner ID of the new main owner of the object, should you choose to assign one.
         * @param string $addOwners JSON array of the form {"id":"id"} where the ID's are of the owners you want to add
         * @param string $remOwners JSON array of the form {"id":"id"} where the ID's are of the owners you want to remove
         * @param array $params        - Object array of the form:
         *                               'safeStr' => bool, default true. Whether to convert the object to safeString upon db insertion.
         *                               'extraColumns' => 1.5D array, default [], of the form:
         *                                                  [[<column name>,<default value>], ...]
         *                                                  where <default value> is null if a value is required. Example:
         *                                                  [
         *                                                  ['Extra_Col_Name',null],
         *                                                  ['Extra_Number',0],
         *                                                  ]
         *                                                 Specifies whether any extra columns are added to the
         *                                                 db query. Notice that if it's set, a matching 'extraContent'
         *                                                 array has to be validated in the API layer, else a critical
         *                                                 error may occur.
         * DIFFERENT THAN addObjects ===> 'extraContent' => 2D array, default []. Of dimensions NxE, where E is the number of
         *                                                 (at least the required) extra columns in extraColumns, and N
         *                                                 is the number of objects arrays. Example:
         *                                                 [
         *                                                 9 => ['Extra Content 1', 02101],
         *                                                 7 => ['Extra Content 2', 01],
         *                                                 15 => ['Extra Content 2']
         *                                                 ]
         *                                                 Notice that this has to be validated - invalid input causes an
         *                                                 exception to be thrown. This example, for instance, only works if
         *                                                 the number of objects is 3.
         * @param bool $test Will not perform any DB actions if test isn't false, and instead echo messages.
         * @returns int
         *             -1 failed to connect to db
         *              0 on success.
         *              1 illegal input
         *              2 if insufficient authorization to modify the object.
         *              3 if object can't be moved into the requested group, for group auth reasons
         *              4 object doesn't exist
         *              5 Extra Content related error
         * */
        function updateObject(
            int $id,
            string $content = '',
            $group = null,
            int $newVRank = null,
            int $newMRank = null,
            int $mainOwner = null,
            array $addOwners = [],
            array $remOwners = [],
            array $params = []
        ){
            $res = $this->updateObjects([[$id,$content,$group,$newVRank,$newMRank,$mainOwner,$addOwners,$remOwners]],$params);
            if(is_array($res))
                return $res[$id];
            else
                return $res;
        }


        /* Basically runs updateObject on array $inputs where $inputs[i][0]..[i][7] are $id, $content, $group, $newVRank, $newMRank,
         * $mainOwner, $newOwners and $remOwners respectively, and $params is the same as in updateObject().
         * @param array $inputs
         * @param array $params
         * @returns  0 if no errors occured,
         *          and the array of (<object ID> => <updateObject error code>) otherwise.
         * TODO *SHOULD* be remade using a stored procedure - doesn't support cuncurrent use at current state
         */
        function updateObjects($inputs, $params = []){

            //You must be logged in to use this handler, as it checks the caller's rank and ID.
            if(!IOFrame\Util\assertLogin())
                return -1;

            $res = [];          //Final result
            $groups = [];       //Groups to retrieve
            $objectsToGet = []; //objects to retrieve
            $groupsToUpdate = [];
            $groupsToCreate = [];
            $gChange = [];      //Groups to change
            $gCreate = [];      //Groups to create
            $objectsToSet = []; //array of objects to set, and how to set them
            $sesInfo = json_decode($_SESSION['details'], true);
            $updateTime = time();
            $tableName = $this->SQLHandler->getSQLPrefix().$this->tableName.'_CACHE';

            //set default params

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            if(!isset($params['safeStr']))
                $safeStr = true;
            else
                $safeStr = $params['safeStr'];

            if(!isset($params['extraColumns']))
                $extraColumns = [];
            else
                $extraColumns = $params['extraColumns'];
            //Extract the actual names
            $extraColumnNames = [];
            foreach($extraColumns as $columnPair){
                array_push($extraColumnNames,$columnPair[0]);
            }

            if(!isset($params['extraContent']))
                $extraContent = [];
            else
                $extraContent = $params['extraContent'];

            if(isset($params['cacheTTL']))
                $cacheTTL = $params['cacheTTL'];
            else
                $cacheTTL = $this->cacheTTL;

            //First check all inputs, and
            foreach($inputs as $key=>$input){
                //default values
                if(!isset($input[1]))
                    $input[1] = '';
                if(!isset($input[2]))
                    $input[2] = null;
                if(!isset($input[3]))
                    $input[3] = null;
                if(!isset($input[4]))
                    $input[4] = null;
                if(!isset($input[5]))
                    $input[5] = null;
                if(!isset($input[6]))
                    $input[6] = [];
                if(!isset($input[7]))
                    $input[7] = [];
                // mark it for retrieval from the DB
                array_push($objectsToGet,$input[0]);
                $objectsToSet[$input[0]] = $input;
                //Mark objects new group for retrieval, if it has one and we didn't mark one for retrieval yet
                if(!in_array($input[2],$groups) && $input[2]!=null)
                    array_push($groups,$input[2]);

            }

            //Retrieve db objects
            $columnsToGet = array_merge(
                ['ID','Ob_Group','Last_Updated', 'Owner', 'Owner_Group', 'Min_Modify_Rank','Min_View_Rank','Object','Meta']
            );
            $params['columns'] = $columnsToGet;
            $objectsReceived = $this->retrieveObjects($objectsToGet,$params);

            //For each object, check that it exists and then add its group (if it has one)
            foreach($objectsReceived as $objId => $object){

                //If the object does not exist, set its result to 4 and unset it
                if(!is_array($object)){
                    $res[$objId] = ($object == 1)? 4 : $object;
                    if($verbose){
                        echo 'Cannot update object '.$objId.', does not exist!'.EOL;
                    }
                }
                else{
                    //If we are modifying owners, we got to confirm strict ownership - else, normal ownership
                    $auth = ($objectsToSet[$objId][5] !== null || $objectsToSet[$objId][6] != [] || $objectsToSet[$objId][7] != [])?
                        $this->checkObAuth($object, ['type'=>1,'strict'=>1]) : $this->checkObAuth($object, ['type'=>1,'strict'=>0]);
                    if(!$auth){
                        $res[$objId] = 2;
                        if($verbose){
                            echo 'User with id '.$sesInfo['ID'].' cannot update object '.$objId.EOL;
                        }
                    }
                    else{
                        //If we are changing the main owner:
                        $owner = ($objectsToSet[$objId][5] !== null )? $objectsToSet[$objId][5] : $object['Owner'] ;

                        //If we are changing secondary owners
                        $owners = json_decode($object['Owner_Group'], true);
                        if($owners == null)
                            $owners = array();
                        //Add owners we want to add, if any
                        if($objectsToSet[$objId][6] != []){
                            foreach($objectsToSet[$objId][6] as $newOwner)
                                $owners += array($newOwner => $newOwner);
                        }
                        //Remove owners we want to remove, if any
                        if($objectsToSet[$objId][7] != []){
                            foreach($objectsToSet[$objId][7] as $toRemove)
                                if(isset($owners[$toRemove]))
                                    unset($owners[$toRemove]);
                            if(count($owners) == 0)
                                $owners = null;
                        }

                        //If viewRank is smaller than modifyRank, set them to be equal
                        if($objectsToSet[$objId][3] !== null && $objectsToSet[$objId][4] !== null)
                            if($objectsToSet[$objId][3]<$objectsToSet[$objId][4]  && $objectsToSet[$objId][3] != -1)
                                $objectsToSet[$objId][3] = $objectsToSet[$objId][4];
                        //Set default values
                        if($objectsToSet[$objId][3] === null)
                            $objectsToSet[$objId][3] = $object['Min_View_Rank'];
                        if($objectsToSet[$objId][4] === null)
                            $objectsToSet[$objId][4] = $object['Min_Modify_Rank'];

                        //Remember to convert content to safe string, if specified
                        if($safeStr)
                            $objectsToSet[$objId][1] = IOFrame\Util\str2SafeStr($objectsToSet[$objId][1]);

                        //For each object, check which group it's being added to -
                        //  for a new group, that it's getting +1 member and see if its Minimal Modify/View ranks need updating
                        //  for the old group, mark that it's losing a member
                        //  if the object remains in the same group, if it exists, check whether its Minimal Modify/View ranks need updating

                        //Prepare all object info
                        if($object['Ob_Group'] === null)
                            $object['Ob_Group'] = '';
                        if($objectsToSet[$objId][1] == '')
                            $objectsToSet[$objId][1] = $object['Object'];
                        $objectsToSet[$objId]['Owner_Group'] = ($owners === [])? null:json_encode($owners);
                        $objectsToSet[$objId]['Ob_Group'] = [$object['Ob_Group'],$objectsToSet[$objId][2]]; //Old group, new group
                        $objectsToSet[$objId]['Last_Updated'] = $updateTime;
                        $objectsToSet[$objId]['Owner'] = $owner;
                        $objectsToSet[$objId]['Min_Modify_Rank'] = $objectsToSet[$objId][4];
                        $objectsToSet[$objId]['Min_View_Rank'] = $objectsToSet[$objId][3];
                        $objectsToSet[$objId]['Object'] = $objectsToSet[$objId][1];
                        //Handle the extra columns
                        foreach($extraColumns as $index=>$pair){
                            if(isset($extraContent[$objId][$index]))
                                $objectsToSet[$objId][$pair[0]] = $extraContent[$objId][$index];
                            else{
                                if($pair[1]!==null)
                                    $objectsToSet[$objId][$pair[0]] = $pair[1];
                                else{
                                    $res[$input[0]] = 5;
                                    unset($objectsToSet[$objId]);
                                    continue;
                                }
                            }
                        }
                        //If we unset the object, go on to the next one.
                        if(!isset($objectsToSet[$objId]))
                            continue;
                        //Mark objects old group for retrieval too, if it has one and we didn't mark one for retrieval yet
                        if(!in_array($object['Ob_Group'],$groups)  && ($object['Ob_Group'] != '')){
                            array_push($groups,$object['Ob_Group']);
                        }
                    }
                }
                //Unset all inputs
                for($i = 0; $i<8+count($extraColumnNames); $i++){
                    unset($objectsToSet[$objId][$i]);
                }
                //If it's en empty array, it means we only had inputs - aka we skipped the setting part
                if($objectsToSet[$objId] == [])
                    unset($objectsToSet[$objId]);
            }

            //Retrieve all marked groups
            if($groups != [])
                $groups = $this->retrieveGroups($groups,$params);
            //Group related
            foreach($objectsToSet as $objId => $object){
                $oldGroup = $object['Ob_Group'][0];
                $newGroup = $object['Ob_Group'][1];
                //For each object still left to set, check if user is moving it to a different group
                if($oldGroup !== $newGroup){
                    //If the new group is '', it means we are deleting the old group
                    if($newGroup !== ''){
                        //If the new group is not null, we are changing something, else we are changing nothing
                        if($newGroup !== null){
                            //See if intended group exists
                            if(is_array($groups[$newGroup])){
                                //See if we can add to the intended group
                                if(!$this->checkObAddGroupAuth($groups[$newGroup],$sesInfo,true,['test'=>$test,'verbose'=>$verbose])){
                                    $res[$objId] = 3;
                                    continue;
                                }
                            }
                            //If intended group does not exist, create it
                            else{
                                //See if we already created it for a different object, and that we actually connected to the db
                                if(!isset($gCreate[$newGroup]) && $groups[$newGroup]!== -1){
                                    $gCreate[$newGroup] = true;
                                    array_push($groupsToCreate,$newGroup);
                                }
                            }
                        }
                    }
                }

                //This means we don't want to change the group
                if($newGroup === null){
                    $objectsToSet[$objId]['Ob_Group'][1] = $oldGroup;
                    $newGroup = $objectsToSet[$objId]['Ob_Group'][1];
                }

                //Mark relevant groups to be updated if they exist
                if($newGroup !== '' && !isset($gCreate[$newGroup])){
                    //See if we already marked that group
                    if(!isset($gChange[$newGroup])){
                        $gChange[$newGroup] = true;
                        array_push($groupsToUpdate,$newGroup);
                    }
                }
                if($oldGroup !== ''){
                    //See if we already marked that group
                    if(!isset($gChange[$oldGroup])){
                        $gChange[$oldGroup] = true;
                        array_push($groupsToUpdate,$oldGroup);
                    }
                }

                //Finally, if we are deleting a group, set it to null, not ''
                if($newGroup === ''){
                    $objectsToSet[$objId]['Ob_Group'][1] = null;
                }

            }

            //---- At this point, we have all the objects we want to update, and all the groups we need to create/update/delete
            $updateParams = [];
            foreach($objectsToSet as $id=>$obj){
                $objectsToSet[$id]['Ob_Group'] = $objectsToSet[$id]['Ob_Group'][1];
                $updateArray = [
                    $id,
                    [$objectsToSet[$id]['Ob_Group'],'STRING'],
                    [(string)$updateTime,'STRING'],
                    $obj['Owner'],
                    [$obj['Owner_Group'],'STRING'],
                    $obj['Min_Modify_Rank'],
                    $obj['Min_View_Rank'],
                    [$obj['Object'],'STRING']
                ];
                foreach($extraColumnNames as $colName){
                    if(gettype($obj[$colName] == 'string'))
                        array_push($updateArray,[$obj[$colName],'STRING']);
                    else
                        array_push($updateArray,$obj[$colName]);
                }
                array_push(
                    $updateParams,
                    $updateArray
                );
            }
            //If we got nothing to update, return
            if($updateParams === [])
                return $res;


            //Update relevant objects
            $columnsToSet = array_merge(
                ['ID','Ob_Group','Last_Updated','Owner','Owner_Group','Min_Modify_Rank','Min_View_Rank','Object'],
                $extraColumnNames
            );

            $request = $this->SQLHandler->insertIntoTable(
                $tableName,
                $columnsToSet,
                $updateParams,
                ['onDuplicateKey'=>true,'test'=>$test,'verbose'=>$verbose]
            );

            //If we failed, return 5 for all objects that weren't set yet
            if($request !== true){
                foreach($objectsToSet as $val){
                    if(!isset($res[$val[0]]))
                        $res[$val[0]] = 5;
                }
                return $res;
            }
            //If the request succeeded, add everything to cache
            else{
                //No need to continue if the system does not have a cache. or doesn't use it
                if( isset($this->defaultSettingsParams['useCache']) && $this->defaultSettingsParams['useCache'] ){
                    foreach($objectsToSet as $id=>$obj){
                        if(!$test)
                            $this->RedisHandler->call('set',['ioframe_'.$this->cacheName.'_id_'.$id,json_encode($obj),$cacheTTL]);
                        if($verbose)
                            echo 'Adding object '.$id.' to cache for '.$cacheTTL.' seconds as '.json_encode($obj).EOL;
                    }
                }
            }

            //Create new groups
            $this->createGroups($groupsToCreate,$sesInfo,$params);
            //Update other groups
            $this->updateGroups($groupsToUpdate,$params);

            if($res === [])
                $res = 0;
            return $res;
        }


        /** Retrieves an object
         * @param int $id object ID
         * @param int $updated the time before which you don't need a new object -
         *                     meaning, if you set $updated = 1500000001, and the last time an object was updated is 1500000000,
         *                     you wont get that object. If you want to get an object either way, set $updated = 0.
         * @param array $params of the form:
         *              'safeStr' => bool, Default true. Converts object content to normal string from safeString.
         *              'extraColumns' => array, default []. Gets additional columns of an object.
         *                                  Meant for extensions to this module.
         *              'limit' => SQL parameter LIMIT
         *              'offset'=> SQL parameter OFFSET. Only changes anything if limit is set.
         *
         * @returns mixed
         *             -1 failed to connect to db
         *              Array of the form {<objectID>:"<DB content>"} - note that object['conent'] IS encoded in SafeSTR by default
         *              0 if you can view the object yet $updated is bigger than the object's Last_Updated field.
         *              1 if object of specified ID doesn't exist.
         *              2 if insufficient authorization to view the object.
         *              3 general error
         */
        function getObject(int $id, int $updated = 0, array $params = []){
            //Default params are set in getObjects
            $obj = $this->getObjects([[$id,$updated]],$params);
            if(!isset($obj[$id])){
                $res = $obj['Errors'][$id];
            }
            else{
                $res = [ $id=>$obj[$id]['Object'] , 'group'=>$obj[$id]['Ob_Group'] ];
                foreach($params['extraColumns'] as $colName){
                    $res[$colName] = $obj[$id][$colName];
                }
            }
            return $res;
        }


        /** Retrieve an array of objects, Returns array
         *  Where the
         * @param array $inputs 2D array, where $inputs[i][0-1] are $id and $updated from getObject()
         * @param array $params as in getObject()
         * @returns array
         *      An array of objects with an additional element "Errors" which is an array of error return codes from getObject(),
         *      that looks like:
         *      {
         *      <ObjectID>:<Content>,
         *      <ObjectID>:<Content> ...,
         *      "Errors":{
         *          <ObjectID>:<Error Code>,
         *          ...
         *      }
         *      }
         */
        function getObjects(array $inputs, array $params = []){
            $ids = [];          //Requested IDs
            $res = [];          //Results, of the array form {<ObjectID>=><objectData>}
            $errors = [];
            $times = [];        //Times for each object

            //set default params

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            if(!isset($params['safeStr']))
                $safeStr = true;
            else
                $safeStr = $params['safeStr'];

            if(!isset($params['extraColumns']))
                $extraColumns = [];
            else
                $extraColumns = $params['extraColumns'];

            foreach($inputs as $key => $input){
                if(!isset($inputs[$key][1]))
                    $inputs[$key][1] = 0;
                if(!isset($inputs[$key][2]))
                    $inputs[$key][2] = true;
                array_push($ids,$inputs[$key][0]);
                $times[$inputs[$key][0]] = $inputs[$key][1];
                $errors[$inputs[$key][0]] = 1;
            }
            //Get objects from db
            $columnsToGet = array_merge(
                ['ID','Ob_Group','Last_Updated', 'Owner', 'Owner_Group', 'Min_Modify_Rank','Min_View_Rank','Object','Meta'],
                $extraColumns
            );
            $params['columns'] = $columnsToGet;
            $objs = $this->retrieveObjects(
                $ids,
                $params
            );

            foreach($objs as $objID=>$obj){
                //See if object exists
                if (!is_array($obj)){
                    if($verbose)
                        echo 'object '.$objID.' doesnt exist!'.EOL;
                    $errors[$objID] = $obj;
                }
                else{
                    //Check if the user is authorized to view the object
                    if(!$this->checkObAuth($obj,['type'=>0])){
                        if($verbose)
                            echo 'User with id '.json_decode($_SESSION['details'], true)['ID'].' not authorized to view object '.$objID.EOL;
                        $errors[$objID] = 2;
                    }
                    //Check if the object has not been updated
                    elseif((int)$obj['Last_Updated'] < $times[$objID]){
                        if($verbose)
                            echo 'Object '.$objID.' has been last updated at '.$obj['Last_Updated'].', requested time was '.$times[$objID].EOL;
                        $errors[$objID] = 0;
                    }
                    //Finally, this means you may return the object
                    else{
                        //An object with no group is mapped to "@"
                        if($obj['Ob_Group'] == null)
                            $obj['Ob_Group'] = '@';
                        if($safeStr)
                            $obj['Object'] = IOFrame\Util\safeStr2Str($obj['Object']);
                        //Push to result
                        $res[$objID] = $obj;
                        //Unset the error
                        unset($errors[$objID]);
                    }
                }
            }

            //Append errors
            $res['Errors']= $errors;
            return $res;
        }


        /** Deletes an object with ID $id.
         * For example, if time=100000, and $after = true, only objects last updated after unix time 100000 will be deleted.
         * By default, only objects after time 0 will be deleted - so, any object.
         *
         * @param int $id ID of the object to delete
         * @param int $time Time after/before objects will be deleted.
         * @param bool $after Whether to allow deletion of objects before or after time
         * @param array $params
         * @returns int
         *          -1 failed to connect to db
         *          0 on success.
         *          1 if id doesn't exists.
         *          2 if insufficient authorization to modify the object.
         *          3 object exists, too old/new to delete
         *          4 general error
         * */
        function deleteObject(int $id, int $time = 0, bool $after = true, array $params = []){
            $res = $this->deleteObjects([[$id,$time,$after]],$params);
            $res = $res[$id];
            return $res;
        }


        /** Basically deleteObject $inputs[i][j] is the j-th parameter in deleteObject.
         * @param array $inputs 2D array, where each sub array is $id, $time and $after from deleteObject
         * @param array $params
         * @return array result codes of the form:
         *              <id> => <deleteObject result code>.
         */
        function deleteObjects(array $inputs, $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $tableName = $this->SQLHandler->getSQLPrefix().$this->tableName.'_CACHE';
            //You must be logged in to use this handler, as it checks the caller's rank and ID.
            if(!IOFrame\Util\assertLogin())
                return -1;

            //Requested IDs, and IDs you in fact need to delete, and result
            $ids = [];          //Requested IDs
            $idsToDelete = [];  //IDs you in fact need to delete
            $groupsToUpdate = [];
            $res = [];          //Result
            $times = [];        //Times for each object
            foreach($inputs as $input){
                array_push($ids,$input[0]);
                $res[$input[0]] = 4;
                $times[$input[0]] = [$input[1],$input[2]];
            }
            //Get objects from db
            $params['columns'] = ['ID','Ob_Group','Last_Updated', 'Owner', 'Owner_Group', 'Min_Modify_Rank'];
            $objs = $this->retrieveObjects($ids,array_merge($params,['updateCache'=>false]));

            foreach($objs as $objID=>$obj){
                //See if object exists
                if (!is_array($obj)){
                    if($verbose)
                        echo 'object '.$objID.' doesnt exist!'.EOL;
                    $res[$objID] = $obj;
                }
                else{
                    //Check if the object is in the requested time frame
                    if($times[$objID][1])
                        $objInTime = ((int)$obj['Last_Updated'] >= $times[$objID][0]) ? true : false;
                    else
                        $objInTime = ((int)$obj['Last_Updated'] <= $times[$objID][0]) ? true : false;
                    //Check if the user is authorized to modify the object
                    if(!$this->checkObAuth($obj,['type'=>1])){
                        if($verbose)
                            echo 'User with id '.json_decode($_SESSION['details'], true)['ID'].' not authorized to delete object '.$objID.EOL;
                        $res[$objID] = 2;
                    }
                    //Check if the object is too new/old to delete
                    elseif(!$objInTime){
                        if($verbose)
                            echo 'Object '.$objID.' will not be deleted, as it was last updated at '.$obj['Last_Updated'].' and
                            time constraints are ['.$times[$objID][0].', '.$times[$objID][1].']'.EOL;
                        $res[$objID] = 3;
                    }
                    //Finally, this means you may delete the object
                    else{
                        array_push($idsToDelete,['ID',$objID,'=']);
                        array_push($groupsToUpdate,$obj['Ob_Group']);
                    }
                }
            }
            //As always, check to see the array is not empty - we dont want to delete EVERYTHING (or case an exception in the expression constructor).
            if($idsToDelete != []){
                //On success, update the values of all objects that are of the default error value
                $deletionRequest = $this->SQLHandler->deleteFromTable($tableName, [$idsToDelete,'OR'], ['test'=>$test,'verbose'=>$verbose]);
                if($deletionRequest === true){
                    $shouldUpdateCache =  isset($this->defaultSettingsParams['useCache']) && $this->defaultSettingsParams['useCache'] ;
                    foreach($res as $objID=>$result){
                        if($result == 4)
                            $res[$objID] = 0;
                        //If we are using cache, remove the deleted object from there
                        if($shouldUpdateCache){
                            if(!$test)
                                $this->RedisHandler->call('del','ioframe_'.$this->cacheName.'_id_'.$objID);
                            if($verbose)
                                echo 'Deleting object '.$objID.' from cache!'.EOL;
                        }
                    }
                    //Now, handle updating the group sizes
                    $this->updateGroups($groupsToUpdate,$params);
                }
            }
            return $res;
        }

        /** Returns objects, but this time by groups.
         *  Unlike normal readObjects, CANNOT USE CACHE
         * @param string $groupName Name of the group.
         * @param array $params of the form:
         *              'updated', => number default 0. Will only return objects whose "Last_Updated" is bigger than updated.
         *              'safeStr' => bool, Default true. Converts object content to normal string from safeString.
         *              'protectedView' => bool, default true. Hides unauthorized objects from showing.
         *              'extraColumns' => array, default []. Gets additional columns of an object.
         *                                  Meant for extensions to this module.
         *              'limit' => SQL parameter LIMIT
         *              'offset'=> SQL parameter OFFSET. Only changes anything if limit is set.
         *
         * Will convert from safeString to normal string if $fromSafeStr is true.
         * @return mixed
         *              either an integer, or almost the same array as "getObjects", where:
         *              Integer codes:
         *                  0 - The whole group is up to date
         *                  1 - The group with this name does not exist
         *              Array codes of the form <ID:Code>
         *                  0 if you can view the object yet $updated is bigger than the object's Last_Updated field.
         *                  1 - CANNOT BE RETURNED. If You are missing an object ID, it means that object isn't part of the group anymore or was deleted.
         *                  2 if insufficient authorization to view the object.
         * */
        function getObjectsByGroup(string $groupName, $params = []){
            $errors = [];
            $res = [];

            //set default params

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            if(!isset($params['updated']))
                $updated = 0;
            else
                $updated = $params['updated'];

            if(!isset($params['safeStr']))
                $safeStr = true;
            else
                $safeStr = $params['safeStr'];

            if(!isset($params['protectedView']))
                $protectedView = [];
            else
                $protectedView = $params['protectedView'];

            if(!isset($params['extraColumns']))
                $extraColumns = [];
            else
                $extraColumns = $params['extraColumns'];

            if(isset($params['limit']))
                $limit = min((int)$params['limit'],$this->defaultQueryLimit);
            else
                $limit = $this->defaultQueryLimit;

            if(isset($params['offset']))
                $offset = (int)$params['offset'];
            else
                $offset = null;

            //For objects that are not up to date (thus returned)
            $notUpToDateCol = array_merge(
                ['ID','Owner','Owner_Group','Min_Modify_Rank','Min_View_Rank','Object','false as upToDate'],
                $extraColumns
            );

            //From here on out, objects are either up-to-date and not returned, or they do not exist.
            foreach($extraColumns as $k=>$columnName){
                $extraColumns[$k] = 'null as '.$columnName;
            }

            //For objects that are up to date (thus not returned)
            $upToDateCol = array_merge(
                ['ID','Owner','Owner_Group','Min_Modify_Rank','Min_View_Rank','null as Object','true as upToDate'],
                $extraColumns
            );

            //For groups which are up-to-date
            $upToDateGroupCol = array_merge(
                ['-1 as ID','-1 as Owner','null as Owner_Group','-1 as Min_Modify_Rank','-1 as Min_View_Rank','null as Object','true as upToDate'],
                $extraColumns
            );

            //For groups which do not exist
            $groupDoesNotExistCol = array_merge(
                ['-2 as ID','-2 as Owner','null as Owner_Group','-2 as Min_Modify_Rank','-2 as Min_View_Rank','null as Object','false as upToDate'],
                $extraColumns
            );

            //Get objects from db
            $objectName = $this->SQLHandler->getSQLPrefix().$this->tableName.'_CACHE';
            $objectGroupName = $this->SQLHandler->getSQLPrefix().$this->tableName.'_CACHE_META';
            $query =
                //Select all objects that are not up to date - Unless the whole group is up to date
                $this->SQLHandler->selectFromTable(
                    $objectName,
                    [
                        ['Ob_Group',$groupName,'='],
                        ['Last_Updated',$updated,'>='],
                        [
                            [
                                $objectGroupName,
                                [['Group_Name',$groupName, '='],['Last_Updated',$updated,'>='],'AND'],
                                ['Group_Name'],
                                [],
                                'SELECT'
                            ]
                            , 'EXISTS'
                        ],
                        'AND'
                    ],
                    $notUpToDateCol,
                    ['justTheQuery'=>true,'useBrackets'=>true,'test'=>false]
                ).
                ' UNION '
                .
                //Select all objects that are up to date - Unless the whole group is up to date
                $this->SQLHandler->selectFromTable(
                    $objectName,
                    [
                        ['Ob_Group',$groupName,'='],
                        ['Last_Updated',$updated,'<'],
                        [
                            [
                                $objectGroupName,
                                [['Group_Name',$groupName, '='],['Last_Updated',$updated,'>='],'AND'],
                                ['Group_Name'],
                                [],
                                'SELECT'
                            ]
                            , 'EXISTS'
                        ],
                        'AND'
                    ],
                    $upToDateCol,
                    ['justTheQuery'=>true,'useBrackets'=>true,'test'=>false]
                ).
                ' UNION '
                .
                //Select (-1,-1,NULL,-1,-1,null,true) - IF the whole group is up to date (and exists)
                $this->SQLHandler->selectFromTable(
                    $objectName,
                    [
                        [
                            [
                                [
                                    $objectGroupName,
                                    [['Group_Name',$groupName, '='],['Last_Updated',$updated,'>='],'AND'],
                                    ['Group_Name'],
                                    [],
                                    'SELECT'
                                ]
                                ,'EXISTS'
                            ]
                            ,'NOT'
                        ],
                        [
                            [
                                $objectGroupName,
                                [['Group_Name',$groupName, '='],'AND'],
                                ['Group_Name'],
                                [],
                                'SELECT'
                            ]
                            , 'EXISTS'
                        ],
                        'AND'
                    ],
                    $upToDateGroupCol,
                    ['justTheQuery'=>true,'useBrackets'=>true,'test'=>false]
                ).
                ' UNION '
                .
                //Select (-2,-2,NULL,-2,-2,null,false) - IF the group does not exist
                $this->SQLHandler->selectFromTable(
                    $objectName,
                    [
                        [
                            [
                                $objectGroupName,
                                ['Group_Name',$groupName, '='],
                                ['Group_Name'],
                                [],
                                'SELECT'
                            ]
                            ,'EXISTS'
                        ]
                        ,'NOT'
                    ],
                    $groupDoesNotExistCol,
                    ['justTheQuery'=>true,'useBrackets'=>true,'test'=>false]
                )
            ;

            $query .= ' ORDER BY ID';

            if($limit != null){
                if($offset != null)
                    $query .= ' LIMIT '.$offset.','.$limit;
                else
                    $query .= ' LIMIT '.$limit;
            }

            if($verbose){
                echo 'Query to send: '.$query.EOL;
            }
            $objects = $this->SQLHandler->exeQueryBindParam($query, [], ['fetchAll'=>true]);

            //This means the group does not exist
            if($objects == [] || $objects[0]['ID'] == -2)
                return 1;
            //This means the group is up to date
            if($objects[0]['ID'] == -1)
                return 0;

            foreach($objects as $k=>$v){
                //If we don't have auth to view the object, stick it into the error group.
                if(!$this->checkObAuth($objects[$k],['type'=>1,'strict'=>0])){
                    //If we are not hiding unauthorized objects from showing, do this.
                    if(!$protectedView)
                        $errors[$objects[$k]['ID']] = 2;
                }
                else{
                    if($objects[$k]['Object']=== null)
                        $errors[$objects[$k]['ID']] = 0;
                    else{
                        //If object is not null and $fromSafeStr is true, convert it
                        if($objects[$k]['Object']!== null && $safeStr)
                            $objects[$k]['Object'] = IOFrame\Util\safeStr2Str($objects[$k]['Object']);
                        $res[$objects[$k]['ID']] = $objects[$k];
                    }
                }
            }

            $res['Errors']= $errors;
            return $res;
        }

        /* Checks if the user is allowed to modify/view the object, represented in associative array $obj (same as DB table)
         * $type = 0 for view, 1 for modify. Anything else will simply check for ownership.
         * If $strict isn't false/0/null/etc, will only return true if either the user is the main owner or of rank 0.
         * Returns true or false.
         * */
        protected function checkObAuth($obj, array $params = []){

            isset($params['type'])?
                $type = $params['type'] : $type = 0;
            isset($params['strict'])?
                $strict = $params['strict'] : $strict = 0;
            isset($params['sesInfo'])?
                $sesInfo = $params['sesInfo'] : $sesInfo = null;

            //Different checks, depending on whether the user is logged in or not
            $loggedIn = true;
            if(!isset($_SESSION['logged_in']))
                $loggedIn = false;
            elseif($_SESSION['logged_in']!=true)
                $loggedIn = false;
            //Checks to do if the user is logged in
            if($loggedIn){
                if($sesInfo == null)
                    $sesInfo = json_decode($_SESSION['details'], true);
                //Either the user is the main owner
                $isOwner = ($obj['Owner'] == $sesInfo['ID']);
                if(!$isOwner){

                    //Or the user is part of the owner group
                    $owners = ($obj['Owner_Group'] != null)? json_decode($obj['Owner_Group'], true) : null;
                    if($owners != null && !$strict)
                        foreach($owners as $value){
                            if($value == $sesInfo['ID']){
                                $isOwner = true;
                                break;
                            }
                        }

                    if(!$isOwner)
                        //Or he can modify the object due to his rank
                        if($sesInfo['Auth_Rank'] == 0)
                            $isOwner = true;
                        elseif(!$strict){
                            if($type == 1){
                                if($obj['Min_Modify_Rank']>=$sesInfo['Auth_Rank'])
                                    $isOwner = true;
                            }
                            else if($type == 0){
                                if( $obj['Min_View_Rank']>=$sesInfo['Auth_Rank'] || $obj['Min_View_Rank']== -1)
                                    $isOwner = true;
                            }
                        }
                }
            }
            //Checks to do if not
            else{
                if($type != 0)
                    $isOwner = false;
                else{
                    ( $obj['Min_View_Rank']==-1 )?
                        $isOwner = true : $isOwner = false;
                }
            }
            return $isOwner;
        }



        /**
         * Checks if the object could be legally inserted into / removed from the group.
         */
        protected function checkObAddGroupAuth($groupInfo, $sesInfo, bool $toAdd, array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            //Maybe it's allowed to add objects to this group
            if($toAdd){
                $allowAddition = $groupInfo['Allow_Addition'];
                //Check for the group allowing addition
                if($allowAddition)
                    return true;
            }
            //Maybe you are the owner
            $owner = $groupInfo['Owner'];
            //If the user is the owner, return true.
            if($sesInfo!= null && $owner!=null && $owner == $sesInfo['ID'])
                return true;

            if($verbose)
                echo 'User '.$sesInfo['ID'].' does not have the auth to modify group '.$groupInfo['Group_Name'].EOL;

            return false;
        }

        /** Basically an interface for retrieve<Objects/Maps/Groups>
         *  Gets the requested objects/maps/groups from the db/cache.
         *  $params['type'] is the type of targets.
         *  For the other parameters and/or return format, see the retrieve functions bellow.
         *  Returns either an array of the getFromCacheOrDB() form, or 0 if the type was wrong or not set.
         * */
        protected function getObjectsFromCacheOrDB($targets, array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            if(!isset($params['type'])){
                if($verbose)
                    echo 'Wrong usage of getFromCacheOrDB, lacking type!'.EOL;
                return 0;
            }

            isset($params['columns'])?
                $columns = $params['columns'] : $columns = [] ;

            switch($params['type']){
                case 'object':
                    $keyCol = 'ID';
                    $tableName = $this->tableName.'_CACHE';
                    $cacheName = 'ioframe_'.$this->cacheName.'_id_';
                    break;
                case 'group':
                    $keyCol = 'Group_Name';
                    $tableName = $this->tableName.'_CACHE_META';
                    $cacheName = 'ioframe_'.$this->cacheName.'_group_';
                    $columns = [];
                    break;
                case 'map':
                    $keyCol = 'Map_Name';
                    $tableName = $this->tableName.'_MAP';
                    $cacheName = 'ioframe_'.$this->cacheName.'_map_';
                    $columns = [];
                    break;
                default:
                    if($verbose)
                        echo 'Wrong usage of getFromCacheOrDB, incorrect type!'.EOL;
                    return 0;

            }

            return $this->getFromCacheOrDB($targets,$keyCol,$tableName,$cacheName,$columns,$params);
        }


        /** Retrieves an object from the database.
         * @param int $id Object ID
         * @param array $params of the form:
         *      'columns' => columns to get
         * @returns array
         *          the object as an assoc array, if successful,
         *          1 - object does not exist
         *         -1 - failed to connect to db
         * */
        function retrieveObject(int $id, array $params = []){
            $res = $this->retrieveObjects([$id],$params);
            return is_array($res)?
                $res[$id] : $res;
        }

        /** Retrieves objects from the database.
         * @param array $ids Object IDs
         * @param array $params of the form:
         *      'columns' => columns to get
         * @returns array of the form <object id> => <result as per retrieveObject()>
         * */
        function retrieveObjects(array $ids, array $params = []){
            $params['type'] = 'object';
            return $this->getObjectsFromCacheOrDB($ids,$params);
        }


        /** Retrieves a group from the database.
         * @param string $group group name
         * @param array $params
         * @returns mixed
         *          the group as an assoc array, if successful,
         *          [], if a group by this name doesn't exist.
         * */
        function retrieveGroup(string $group,$params = []){
            $res =  $this->retrieveGroups([$group],$params);
            return is_array($res)?
                $res[$group] : $res;
        }

        /** Retrieves groups from the database.
         * @param array $groups Group names
         * @param array $params
         * @returns array of the form <group name> => <result as per retrieveGroup()>
         * */
        function retrieveGroups(array $groups,$params = []){
            $params['type'] = 'group';
            return $this->getObjectsFromCacheOrDB($groups,$params);
        }

        /** Retrieves an object map from the database.
         * @param string $map Map name
         * @param array $params
         * @returns mixed
         *          the object as an assoc array, if successful,
         *          1, if a map by this name doesn't exist.
         * */
        function retrieveObjectMap(string $map, array $params = []){
            $res = $this->retrieveObjectMaps([$map],$params);
            return is_array($res)?
                $res[$map] : $res;
        }

        /** Retrieves page maps from the database.
         * @param array $maps Map names
         * @param array $params
         * @returns array of the form <map name> => <result as per retrieveObjectMap()>
         * */
        function retrieveObjectMaps(array $maps, array $params = []){
            $params['type'] = 'map';
            return $this->getObjectsFromCacheOrDB($maps,$params);
        }

        /** Creates a group of name $group, and size of $newSize.
         * @param string $group Group name
         * @param array $sesInfo Decoded $_SESSION['details']
         * @param array $params of the form
         *                  'cacheTTL' => int, default set by handler - for how long to cache results gotten during this operation.
         * @returns int 0 on success, 1 on failure
         * */
        function createGroup(string $group, array $sesInfo, array $params = []){
            return $this->createGroups([$group], $sesInfo, $params);
        }

        /** Creates groups  inputs, where each group is an array of inputs like those in createGroup.
         * @param array $groups Array of group names
         * @param array $sesInfo Decoded $_SESSION['details']
         * @param array $params of the form
         *                  'cacheTTL' => int, default set by handler - for how long to cache results gotten during this operation.
         * @returns int 0 on success, 1 on failure
         * */
        function createGroups(array $groups, array $sesInfo, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            if(isset($params['cacheTTL']))
                $cacheTTL = $params['cacheTTL'];
            else
                $cacheTTL = $this->cacheTTL;

            $tableName = $this->SQLHandler->getSQLPrefix().$this->tableName.'_CACHE_META';
            $cols = ['Group_Name','Owner','Last_Updated','Allow_Addition'];
            $values = [];
            $timeUpdated = strval(time());
            //If there is nothing to do..
            if($groups == [])
                return 0;
            //Else create the groups
            foreach($groups as $group){
                array_push($values,[[$group,'STRING'],$sesInfo['ID'],[$timeUpdated,'STRING'],0]);
            }
            //execute
            if($values!=[])
                $res =$this->SQLHandler->insertIntoTable(
                    $tableName,$cols,$values,['onDuplicateKey'=>false, 'returnRows'=>false,'test'=>$test,'verbose'=>$verbose]
                );
            else
                $res = true;

            if($res === true && $values !=[]){
                //Dont forget to update the cache with the DB objects, if we're using cache
                if(
                    isset($this->defaultSettingsParams['useCache']) &&
                    $this->defaultSettingsParams['useCache']
                ){
                    foreach($groups as $group){
                        $toSet = json_encode(
                            [
                                'Group_Name'=>$group,
                                'Last_Updated'=>$timeUpdated,
                                'Owner'=>$sesInfo['ID'],
                                'Allow_Addition'=>0
                            ]
                        );
                        if(!$test)
                            $this->RedisHandler->call(
                                'set',
                                [
                                    'ioframe_'.$this->cacheName.'_group_'.$group,
                                    $toSet,
                                    $cacheTTL
                                ]
                            );
                        if($verbose)
                            echo 'Adding group '.$group.' to cache for '.$cacheTTL.' seconds as '.$toSet.EOL;
                    }
                }
            }
            return ($res === true)?
                0 : 1;
        }

        /** Updates group - usually invoked when an object in the group has changed.
         * $group is a group name
         * @returns int 0 on success, 1 on failure
         * */
        function updateGroup(string $group, array $params = []){
            return $this->updateGroups([$group],$params);
        }

        /** Updates group - usually invoked when an object in the group has changed.
         * @param array $groups Array of the form group names
         * @param array $params of the form
         *                  'cacheTTL' => int, default set by handler - for how long to cache results gotten during this operation.
         * @returns int 0 on success, 1 on failure
         * */
        function updateGroups(array $groups, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            if(isset($params['cacheTTL']))
                $cacheTTL = $params['cacheTTL'];
            else
                $cacheTTL = $this->cacheTTL;

            $tableName = $this->SQLHandler->getSQLPrefix().$this->tableName.'_CACHE_META';
            $cols = ['Group_Name','Last_Updated'];
            $groupMap = [];
            $timeUpdated = strval(time());
            //If there is nothing to do..
            if($groups == [])
                return 0;
            //Else update the groups
            foreach($groups as $group){
                //Update global map
                if(!isset($groupMap[$group])){
                    $groupMap[$group] = [[$group,'STRING'],[$timeUpdated,'STRING']];
                }
            }
            //group values
            $values = [];
            foreach($groupMap as $input){
                array_push($values,$input);
            }
            //execute
            $res =$this->SQLHandler->insertIntoTable(
                $tableName,
                $cols,
                $values,
                ['onDuplicateKey'=>true, 'returnRows'=>false,'test'=>$test,'verbose'=>$verbose]
            );

            //If we are using cache, delete the group. This is because we do not have ownerID to set.
            if($res === true){
                if(
                    isset($this->defaultSettingsParams['useCache']) &&
                    $this->defaultSettingsParams['useCache']
                ){
                    foreach($groups as $group){
                        $currentGroup = $this->RedisHandler->call(
                            'get',
                            'ioframe_'.$this->cacheName.'_group_'.$group
                        );
                        if($currentGroup){
                            $currentGroup = json_decode($currentGroup,true);
                            $toSet = json_encode(
                                [
                                    'Group_Name' =>$group,
                                    'Last_Updated' =>$timeUpdated,
                                    'Owner' =>$currentGroup['Owner'],
                                    'Allow_Addition' =>$currentGroup['Allow_Addition'],
                                ]
                            );
                            if(!$test)
                                $this->RedisHandler->call(
                                    'set',
                                    [
                                        'ioframe_'.$this->cacheName.'_group_'.$group,
                                        $toSet,
                                        $cacheTTL
                                    ]
                                );
                            if($verbose)
                                echo 'Updating group map' . $group . ' to '.$toSet.' with TTL '.$cacheTTL.EOL;
                        }
                        else
                            if($verbose)
                                echo 'Group '.$group.' not in cache, cannot update!'.EOL;
                    }
                }
            }

            return ($res === true)?
                0 : 1;
        }

        /** Checks whether a %time is up-to-date compared to a $group (name).
         * @param string $group Name of the group
         * @param int $time     Time before which the group is considered up-to-date
         * @param array $params
         * @returns int
         *      0 if up to date,
         *      1 if not up to date,
         *      -1 if group doesn't exist
         *      -2 failed to connect to db
         * */

        function checkGroupUpdated(string $group, int $time, array $params = []){
            return $this->checkGroupsUpdated([$group => $time],$params)[$group];
        }

        /** checkGroupUpdated but for more than 1 group.
         * @param array $groups 2D array of the form [<group Name> => <Last updated>]
         * @param array $params
         * @returns array [<groupName>=><result as per checkGroupUpdated>]
         * */

        function checkGroupsUpdated(array $groups, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            if(count($groups) == 0)
                return [];

            $toRetrieve = [];
            foreach($groups as $groupName => $groupTime){
                array_push($toRetrieve,$groupName);
            }
            //Get the inputs' Last_Updated
            $groupRes = $this->retrieveGroups($toRetrieve,$params);
            $resArr = [];
            foreach($groups as  $groupName => $groupTime){
                //Check whether the group actually exists
                if(!is_array($groupRes[$groupName]))
                    $resArr[$groupName] = $groupRes[$groupName] == 1? -1 : -2 ;
                //Check whether group is up to date
                elseif($groupRes[$groupName]['Last_Updated']<=$groupTime)
                    $resArr[$groupName] = 0;
                else
                    $resArr[$groupName] = 1;
            }

            if($verbose)
                echo 'Groups check, result: '.json_encode($resArr).EOL;

            return $resArr;

        }

        /** Assigns, or removes assignment of an object to a page.
         *
         * @param int $id ID of the object to assign
         * @param string $page Name of the page/map to assign the object to
         * @param bool $assign - default true - whether to assign or remove the object to/from the map
         * @param array $params of the form
         * @returns int
         *          0 on success.
         *          1 if object or page id don't exist.
         *          2 if insufficient authorization to modify the object.
         *          3 if insufficient authorization to remove/add object-page assignments.
         *          4 Different error
         * */

        function objectMapModify(int $id, string $page, bool $assign = true, array $params = []){
            $res = $this->objectMapModifyMultiple([[$id, $page, $assign]],$params);
            if($res == -1)
                return 4;
            if($res == 3)
                return 3;
            return $res[$id];
        }


        /** Assigns, or removes assignment of multiple objects to/from multiple pages.
         * @param array $inputArray 2D array where each sub-array is of the same Same order as objectMapModify before $params
         * @param array $params of the form
         *                  'cacheTTL' => int, default set by handler - for how long to cache results gotten during this operation.
         *                  'limit' => int, SQL Limit clause
         *                  'offset' => int, SQL offset clause (only matters if limit is set)
         * @returns mixed
         *          -1 on database failure,
         *          1 No objects of the IDs specified in the inputs exist,
         *          an assoc array [<objectID> => <resultCode>] otherwise
         * TODO *SHOULD* be remade using a stored procedure - doesn't support concurrent use at current state
         * */

        function objectMapModifyMultiple(array $inputArray, array $params = []){

            //You must be logged in to use this handler, as it checks the caller's rank and ID.
            if(!IOFrame\Util\assertLogin())
                return -1;

            if(isset($params['cacheTTL']))
                $cacheTTL = $params['cacheTTL'];
            else
                $cacheTTL = $this->cacheTTL;

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            //Array of the type <pageName> => <objectID> for all objects that need to be assigned
            $assignMaps = [];
            //Array of the type <pageName> => <objectID> for all objects that need to be deleted
            $deleteMaps = [];
            //Relevant Pages, form <pageName>=><pageName>
            $maps = [];
            $mapNames = [];
            //Relevant Object IDs, form <objectID>=><objectID>
            $objectIDs = [];
            $objectIDsMap = [];
            $objectsToCheck = [];

            //Populate the 2 arrays using $inputArray
            foreach($inputArray as $input){
                if(!isset($input[2]) || (isset($input[2]) && $input[2]) ){
                    if(!isset( $assignMaps[(string)$input[1]]) ){
                        $assignMaps[(string)$input[1]] =[(int)$input[0]];
                        $maps[(string)$input[1]] = (string)$input[1];
                    }
                    else{
                        array_push($assignMaps[(string)$input[1]],(int)$input[0]);
                    }
                }
                else{
                    if(!isset( $deleteMaps[(string)$input[1]]) ){
                        $deleteMaps[(string)$input[1]] =[(int)$input[0]];
                        $maps[(string)$input[1]] = (string)$input[1];
                    }
                    else{
                        array_push($deleteMaps[(string)$input[1]],(int)$input[0]);
                    }
                }
                array_push($objectIDs,(int)$input[0]);
                $objectIDsMap[(int)$input[0]] = count($objectIDs)-1;
            }

            //Delete overlapping values (that are marked both for assignment and deletion)
            foreach($deleteMaps as $mapName => $deleteArray){
                if(isset($assignMaps[$mapName])){
                    foreach($deleteArray as $key=>$val){
                        $corresponding = array_search($val,$assignMaps[$mapName]);
                        //If we found matching values in both arrays, delete them!
                        if($corresponding){
                            unset($assignMaps[$mapName][$corresponding]);
                            unset($deleteMaps[$mapName][$key]);
                            unset($objectIDs[$objectIDsMap[$val]]);
                            if($assignMaps[$mapName] = []){
                                unset($assignMaps[$mapName]);
                                unset($maps[$mapName]);
                            }
                            if($deleteMaps[$mapName] = []){
                                unset($deleteMaps[$mapName]);
                                unset($maps[$mapName]);
                            }
                        }
                    }
                }
            }

            //Get session info
            $sesInfo = json_decode($_SESSION['details'],true);

            //get ALL relevant maps, and ALL relevant objects from the database
            //TODO can be done in one query with a bit more work
            foreach($maps as $mapName)
                array_push($mapNames,$mapName);
            $mapNamesExt = $this->retrieveObjectMaps($mapNames,array_merge($params,['updateCache'=>false]));
            $params['columns'] = ['ID','Owner', 'Owner_Group', 'Min_Modify_Rank'];
            $objectIDsExt = $this->retrieveObjects($objectIDs,array_merge($params,['updateCache'=>false]));

            if($objectIDsExt == []){
                if($verbose)
                    echo 'No objects of specified IDs/Names exist!'.EOL;
                return 1;
            }

            //At this point, we log the time as Last_Updated for object_map
            $lastChanged = strval(time());


            //We will need those 2 arrays for our queries
            $deleteArray = [];

            //Check that the objects exist, and their auth
            foreach($objectIDs as $index=>$objectID){

                $objectsToCheck[$objectID] = $objectIDsExt[$objectID];

                //Check object auth
                if(is_array($objectIDsExt[$objectID]))
                    if(!$this->checkObAuth($objectsToCheck[$objectID],['type'=>1,'strict'=>0,'sesInfo'=>$sesInfo])){
                        $objectsToCheck[$objectID] = 2;
                    }
            }

            foreach($maps as $mapName){
                //If the map name does not exist, it wont be an array
                if(is_array($mapNamesExt[$mapName])){
                    $objectArr = ($mapNamesExt[$mapName]['Objects'] != null) ?
                        json_decode($mapNamesExt[$mapName]['Objects'],true) : [] ;
                    $maps[$mapName] = [
                        'Objects' => $objectArr
                    ];
                }
                //If no maps existed, we will still create new ones for assigning objects
                elseif(isset($assignMaps[$mapName]))
                    $maps[$mapName] = [
                        'Objects' => []
                    ];

                //Assign to the relevant maps all the objects THAT EXIST
                if(isset($assignMaps[$mapName]))
                    foreach($assignMaps[$mapName] as $objToAdd){
                        if(is_array($objectsToCheck[$objToAdd]) && count($objectsToCheck[$objToAdd])>0){
                            //Create new page if need be
                            $maps[$mapName]['Objects'][$objToAdd] = $objToAdd;
                            //Mark object as successfully assigned
                            $objectsToCheck[$objToAdd] = 0;
                        }
                    }
                //Delete all objects YOU MAY MODIFY from maps THAT EXIST. Mark empty maps for deletion
                if(isset($deleteMaps[$mapName]))
                    foreach($deleteMaps[$mapName] as $objToDelete){
                        //If you don't have the auth to delete the object, or we didn't get it, do nothing
                        if($objectsToCheck[$objToDelete] !== 2 && $objectsToCheck[$objToDelete] !== -1){
                            //If page does not exist, do nothing
                            if(isset($maps[$mapName]) && is_array($maps[$mapName])){
                                //Unset object if it's assigned to map
                                if(isset($maps[$mapName]['Objects'][$objToDelete]))
                                    unset($maps[$mapName]['Objects'][$objToDelete]);
                                //If a page just became empty, mark it for deletion.
                                if(count($maps[$mapName]['Objects']) == 0){
                                    array_push($deleteArray,$mapName);
                                    $maps[$mapName] = 1;
                                }
                            }
                            //Mark object as successfully deleted - can't be 0, or else $assignMaps might miss them later
                            $objectsToCheck[$objToDelete] = [];
                        }
                    }
            }

            //Every object marked as [] is actually 0
            foreach($objectsToCheck as $key=>$val){
                if($val==[])
                    $objectsToCheck[$key] = 0;
            }

            $tableName = $this->SQLHandler->getSQLPrefix().$this->tableName.'_MAP';

            //Delete all empty pages first
            $conds = [];
            foreach($deleteArray as $mapName){
                array_push($conds,['Map_Name',$mapName,'=']);
            }
            if(count($conds)>0){
                array_push($conds,'OR');
                if($this->SQLHandler->deleteFromTable($tableName,$conds,['test'=>$test,'verbose'=>$verbose]) !== true){
                    if($verbose)
                        echo 'Unexpected error when deleting pages!'.EOL;
                    return -1;
                };
            }
            //If we are using cache, drop the deleted maps from it
            if( isset($this->defaultSettingsParams['useCache']) && $this->defaultSettingsParams['useCache'] ) {
                foreach ($deleteArray as $mapName) {
                    if (!$test)
                        $this->RedisHandler->call('del', 'ioframe_'.$this->cacheName.'_map_' . $mapName);
                    if($verbose)
                        echo 'Deleting object map' . $mapName . ' from cache!' . EOL;
                }
            }
            //Luckily for us, create and update operations may be handled in one query
            $values = [];
            $colArr = ['Map_Name','Objects','Last_Updated'];
            foreach($maps as $mapName=>$details){
                //Obviously, don't do this for empty pages
                if(is_array($maps[$mapName])){
                    array_push($values,[[$mapName,'STRING'],[json_encode($details['Objects']),'STRING'],[$lastChanged,'STRING']]);
                }
            }
            if($values !=[])
                if(
                    $this->SQLHandler->insertIntoTable(
                        $tableName,
                        $colArr,
                        $values,
                        ['onDuplicateKey'=>true, 'returnRows'=>false,'test'=>$test,'verbose'=>$verbose]
                    )
                    !==
                    true
                ){
                    if($verbose)
                        echo 'Unexpected error when updating pages!'.EOL;
                    return -1;
                };
            //If we are using cache, update it with the newly modified maps
            if( isset($this->defaultSettingsParams['useCache']) && $this->defaultSettingsParams['useCache'] ) {
                foreach($maps as $mapName=>$details){
                    if(is_array($details)){
                        $toSet = json_encode(
                            [
                                'Map_Name' => $mapName,
                                'Objects' => json_encode($details['Objects']),
                                'Last_Updated' => $lastChanged,
                            ]
                        );
                        if (!$test)
                            $this->RedisHandler->call(
                                'set',
                                [
                                    'ioframe_'.$this->cacheName.'_map_'.$mapName,
                                    $toSet,
                                    $cacheTTL
                                ]
                            );
                        if($verbose)
                            echo 'Updating object map ' . $mapName . ' to '.$toSet.' with TTL '.$cacheTTL.EOL;
                    }
                }
            }

            return $objectsToCheck;

        }

        /**  Gets the list of objects assigned to a certain page, if the latest change was made not earlier than $params['time'].
         *
         * @param string $page Name of requested page
         * @param array $params of the form:
         *                  'time' -> int, default 0 - Will only return objects assigned to a page that was updated
         *                            later than $params['time']
         *                  'limit' => int, SQL Limit clause
         *                  'offset' => int, SQL offset clause (only matters if limit is set)
         *
         * @return mixed
         *              A JSON array of the objects, of the form {"ID":"ID",...}, if $time < Last_Updated
         *              0 if Last_Updated < $time
         *              1 if the page doesn't exist, or has no objects in it
         * TODO - Add security, allow private or otherwise secure objects, or at least pages
         * */
        function getObjectMap(string $map, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            isset($params['time'])?
                $time = $params['time'] : $time = 0;
            $pageArr = $this->retrieveObjectMap($map, $params);
            if($pageArr == []){
                if($verbose)
                    echo 'Page  '.$map.' does not exist!'.EOL;
                return 1;
            }
            else{
                if($pageArr['Last_Updated'] < $time){
                    if($verbose)
                        echo 'Page '.$map.' is up to date! '.EOL;
                    return 0;
                }
                else{
                    if($verbose)
                        echo 'Retrieving object list from map '.$map.': '.$pageArr['Objects'].EOL;
                    ($pageArr['Objects'] != '')?
                        $res = $pageArr['Objects'] : $res = 1 ;
                    return $res;
                }
            }
        }

        /** Gets the list of objects assigned to certain pages, if the latest change was made not earlier than <Time Updated>.
         *
         ** @param array $pageArray of the form: <Page Name>=><Time Updated>
         ** @param array $params Same as getting one map
         *
         * @return array of the form: [ <Page Name> => <Output of getObjectMap()> ]
         * TODO - Add security, allow private or otherwise secure objects, or at least pages
         * */
        function getObjectMaps(array $pageArray, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $res = array();
            $pagesToGet = [];
            foreach($pageArray as $pageName=>$time){
                $res[$pageName] = 1;
                array_push($pagesToGet,$pageName);
            }
            $pageArr = $this->retrieveObjectMaps($pagesToGet,$params);
            if($pageArr == 1){
                if($verbose)
                    echo 'Page  none of the pages exists!'.EOL;
                return 1;
            }
            else{
                foreach($pageArr as $pageName=>$pageData){
                    if($pageData['Last_Updated'] < $pageArray[$pageName]){
                        if($verbose)
                            echo 'Page '.$pageName.' is up to date! '.EOL;
                        $res[$pageName] = 0;
                    }
                    else{
                        if($verbose)
                            echo 'Retrieving object list from map '.$pageName.': '.$pageData['Objects'].EOL;
                        $res[$pageName] = ($pageData['Objects'] != '') ?
                            $pageData['Objects'] : 1 ;
                    }
                }
            }
            return $res;
        }

        /** Backs up the whole Objects state
         * @param array $params
         * */
        function backupObjects( array $params = []){
            $prefix = $this->SQLHandler->getSQLPrefix().$this->tableName;
            $objArr = [$prefix.'_CACHE_META',$prefix.'_CACHE',$prefix.'_MAP'];
            $this->SQLHandler->backupTables($objArr,[],$params);
        }

        /** Restores latest state - from the latest backup
         * @param array $params
         * */
        function restoreLatestState( array $params = []){
            $prefix = $this->SQLHandler->getSQLPrefix().$this->tableName;
            $objArr = [$prefix.'_CACHE_META',$prefix.'_CACHE',$prefix.'_MAP'];
            $this->SQLHandler->restoreLatestTables($objArr, $params);
        }
    }
}