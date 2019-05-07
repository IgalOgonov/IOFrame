<?php
namespace IOFrame{
    define('objectHandler',true);

    if(!defined('abstractDB'))
        require 'abstractDB.php';
    if(!defined('safeSTR'))
        require __DIR__.'/../_util/safeSTR.php';
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;

    /**
     * Handles adding, deleting, and querying for objects and object inputs. Related Tables are Object_Chache and
     * Object_Cache_Meta.
     */

    class objectHandler extends abstractDBWithCache {

        /**
         * Basic construction function, with added connection if it exists
         * @param settingsHandler $settings regular settings handler.
         * @param sqlHandler $sqlHandler regular sqlHandler handler.
         * @param Logger $logger regular Logger
         * @param \PDO $conn Generic PDO connection
         */
        function __construct(settingsHandler $settings,  $params = []){

            parent::__construct($settings,$params);
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
         * @param bool $test           - Will not perform any DB actions if test isn't false, and instead echo messages.
         * @returns mixed Codes:
         *                  -2  Group exists, insufficient authorization to add object to group
         *                  -1 if you are not logged in
         *                  0 failed to set object
         *                  1  Extra Content related error
         *                OR
         *                  Array of the form ['ID' => <new object ID>] on success
         * */
        function addObject(string $obj, string $group = '', int $minModifyRank = 0, int $minViewRank = -1,
                           array $params = [], bool $test = false)
        {
            $res = $this->addObjects([[$obj,$group,$minModifyRank,$minViewRank]],$params,$test);
            if(!is_array($res))
                return $res;
            if(isset($res['ID']))
                return $res['ID'];
            else
                return $res[0];
        }


        /* Adds an object to the database, in safeString format.
         * @param array $inputs        - Inputs from adObject in the same order
         * @param bool $params         -  Explained in addObject
         * @param bool $test           - Will not perform any DB actions if test isn't false, and instead echo messages.
         * @returns mixed Array of the form:
         *                  "ID":<ID of the **FIRST OBJECT OF ALL THOSE INSERTED**> on Success
         *                  <Object number in input array>:<Error code> where the error codes ar from addObject
         *                OR
         *                1 if there is a conflict in the extra content/columns
         * */
        function addObjects(array $inputs, $params = [], bool $test = false){


            //It would be better to do it all with 1 query, due to authentication this is not possible until stored-procedure reimplementation.
            $groups = [];       //Groups to retrieve
            $objects = [];      //Objects to create
            $groupsToUpdate = [];
            $groupsToCreate = [];
            $gChange = [];      //Groups to change
            $gCreate = [];      //Groups to create
            $sesInfo = json_decode($_SESSION['details'], true);
            $updateTime = time();
            $tableName = $this->sqlHandler->getSQLPrefix().'OBJECT_CACHE';
            $res = [];
            //set default params
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
            if(!assertLogin()){
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
                    $inputs[$i][0] = str2SafeStr($input[0]);
            }

            //Retrieve all marked groups
            if($groups != [])
                $groups = $this->retrieveGroups($groups,[],$test);

            //Group related
            foreach($objects as $inputOrderID => $obj){
                if($obj != 1){
                    $groupName = $obj[1];
                    if($groups != [] && isset($groups[$groupName]))
                        $groupInfo = $groups[$groupName];
                    else
                        $groupInfo = 1;
                    //Check whether group exists
                    if($groupInfo != 1){
                        //Check user authorization
                        if(!$this->checkObAddGroupAuth($groupInfo, $sesInfo,true, $test)){
                            if($test)
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
                        //.. create the group.
                            if(!isset($gCreate[$groupName])){
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
                $request = $this->sqlHandler->insertIntoTable(
                    $tableName,
                    $columns,
                    $updateParams,
                    [],
                    $test
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
            $res = $this->sqlHandler->lastInsertId();
            //Create new groups
            $this->createGroups($groupsToCreate,$sesInfo,$test);
            //Update other groups
            $this->updateGroups($groupsToUpdate,$test);

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
            array $params = [],
            bool $test = false
        ){
            $res = $this->updateObjects([[$id,$content,$group,$newVRank,$newMRank,$mainOwner,$addOwners,$remOwners]],$params,$test);
            if(is_array($res))
                return $res[$id];
            else
                return $res;
        }


        /* Basically runs updateObject on array $arr where $arr[i][0]..[i][7] are $id, $content, $group, $newVRank, $newMRank,
         * $mainOwner, $newOwners and $remOwners respectively.
         * Returns  0 if no errors occured,
         *          and the array of errors otherwise.
         * TODO *SHOULD* be remade using a stored procedure - doesn't support cuncurrent use at current state
         */
        function updateObjects($inputs, $params = [], $test = false){

            //You must be logged in to use this handler, as it checks the caller's rank and ID.
            if(!assertLogin())
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
            $tableName = $this->sqlHandler->getSQLPrefix().'OBJECT_CACHE';

            //set default params
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
            $objectsReceived = $this->retrieveObjects($objectsToGet,$columnsToGet,$test);

            if($objectsReceived == 1){
                foreach($objectsToGet as $objId){
                    $res[$objId] = 4;
                }
                return $res;
            }

            //For each object, check that it exists and then add its group (if it has one)
            foreach($objectsReceived as $objId => $object){

                //If the object does not exist, set its result to 4 and unset it
                if($object == 1){
                    $res[$objId] = 4;
                    if($test){
                        echo 'Cannot update object '.$objId.', does not exist!'.EOL;
                    }
                }
                else{
                    //If we are modifying owners, we got to confirm strict ownership - else, normal ownership
                    $auth = ($objectsToSet[$objId][5] !== null || $objectsToSet[$objId][6] != [] || $objectsToSet[$objId][7] != [])?
                        $this->checkObAuth($object, 1, 1) : $this->checkObAuth($object, 1, 0);
                    if(!$auth){
                        $res[$objId] = 2;
                        if($test){
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
                            $objectsToSet[$objId][1] = str2SafeStr($objectsToSet[$objId][1]);

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
                //If it's en empty array, it means we only had inputs
                if($objectsToSet[$objId] == [])
                    unset($objectsToSet[$objId]);
            }
            //Retrieve all marked groups
            $groups = $this->retrieveGroups($groups,[],$test);
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
                            if($groups[$newGroup]!=1){
                                //See if we can add to the intended group
                                if(!$this->checkObAddGroupAuth($groups[$newGroup],$sesInfo,true,$test)){
                                    $res[$objId] = 3;
                                    continue;
                                }
                            }
                            //If intended group does not exist, create it
                            else{
                                //See if we already created it for a different object
                                if(!isset($gCreate[$newGroup])){
                                    $gCreate[$newGroup] = true;
                                    array_push($groupsToCreate,[$newGroup,$sesInfo['ID']]);
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
                $updateArray = [
                    $id,
                    [$obj['Ob_Group'][1],'STRING'],
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
            //Update relevant objects
            $columnsToSet = array_merge(
                ['ID','Ob_Group','Last_Updated','Owner','Owner_Group','Min_Modify_Rank','Min_View_Rank','Object'],
                $extraColumnNames
            );
            $request = $this->sqlHandler->insertIntoTable(
                $tableName,
                $columnsToSet,
                $updateParams,
                ['onDuplicateKey'=>true],
                $test
            );
            if($request !== true){
                //If we failed, return 5 for all objects that weren't set yet
                foreach($objectsToSet as $val){
                    if(!isset($res[$val[0]]))
                        $res[$val[0]] = 5;
                }
                return $res;
            }

            //Create new groups
            $this->createGroups($groupsToCreate,$sesInfo,$test);
            //Update other groups
            $this->updateGroups($groupsToUpdate,$test);

            if($res === [])
                $res = 0;
            return $res;
        }


        /* Retrieves an object , where $id is the object ID and $updated is the time before which you don't need a new object -
         * meaning, if you set $updated = 1500000001, and the last time an object was updated is 1500000000,
         * you wont get that object. If you want to get an object either way, set $updated = 0.
         * @param array $params of the form:
         *              'safeStr' => bool, Defualt true. Converts object content to normal string from safeString.
         *              'extraColumns' => array, default []. Gets additional columns of an object.
         *                                  Meant for extensions to this module.
         *
         * Returns:     Array of the form {<objectID>:"<object>",group:"<groupName>"} - not that the object IS encoded in SafeSTR
         *              0 if you can view the object yet $updated is bigger than the object's Last_Updated field.
         *              1 if object of specified ID doesn't exist.
         *              2 if insufficient authorization to view the object.
         *              3 general error
        */
        function getObject(int $id, int $updated = 0, array $params = [], $test = false){
            //Default params are set in getObjects
            $obj = $this->getObjects([[$id,$updated]],$params,$test);
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


        /* Retrieve an array of objects, Returns array of {"ObjectID": "Content","ObjectID":"Content"..."Errors":"{...}"}
           for
         * Returns  Array of objects with an additional element "Errors" which is an array of error return codes in the format, of
         *          the structure {"ObjectID1":"Contents","ObjectID2":"Contents"..., "Errors":"{"ObjectID":"ErrorID"}" }.
         *          Note that 0 is considered an error in this context.
         *          Note that the combined array length of the main JSON array and the Errors array is the size of $arr.
            */
        function getObjects($inputs, $params = [], $test = false){

            $ids = [];          //Requested IDs
            $res = [];          //Results, of the array form {<ObjectID>=><objectData>}
            $errors = [];
            $times = [];        //Times for each object

            //set default params
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
            $objs = $this->retrieveObjects(
                $ids,
                $columnsToGet,
                $test
            );

            //In case no objects were found, what can we do..
            if($objs == 1){
                if($test)
                    echo 'No objects exist!'.EOL;
            }
            else
                foreach($objs as $objID=>$obj){
                    //See if object exists
                    if ($obj == 1){
                        if($test)
                            echo 'object '.$objID.' doesnt exist!'.EOL;
                        $errors[$objID] = 1;
                    }
                    else{
                        //Check if the user is authorized to view the object
                        if(!$this->checkObAuth($obj,0)){
                            if($test)
                                echo 'User with id '.json_decode($_SESSION['details'], true)['ID'].' not authorized to view object '.$objID.EOL;
                            $errors[$objID] = 2;
                        }
                        //Check if the object has not been updated
                        elseif((int)$obj['Last_Updated'] < $times[$objID]){
                            if($test)
                                echo 'Object '.$objID.' has been last updated at '.$obj['Last_Updated'].', requested time was '.$times[$objID].EOL;
                            $errors[$objID] = 0;
                        }
                        //Finally, this means you may return the object
                        else{
                            //An object with no group is mapped to "@"
                            if($obj['Ob_Group'] == null)
                                $obj['Ob_Group'] = '@';
                            if($safeStr)
                                $obj['Object'] = safeStr2Str($obj['Object']);
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


        /* Deletes an object with ID $id.
         * Returns: 0 on success.
         *          1 if id doesn't exists.
         *          2 if insufficient authorization to modify the object.
         *          3 object exists, too old/new to delete
         *          4 general error
         * */
        function deleteObject(int $id, int $time = 0, bool $after = true, $test = false){
            $res = $this->deleteObjects([[$id,$time,$after]],$test);
            $res = $res[$id];
            return $res;
        }


        /* Basically runs deleteObject on array $arr where $arr[i] is the $id.
         * Returns    array of result codes.
         */
        function deleteObjects($inputs, $test = false){

            $tableName = $this->sqlHandler->getSQLPrefix().'OBJECT_CACHE';
            //You must be logged in to use this handler, as it checks the caller's rank and ID.
            if(!assertLogin())
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
            $objs = $this->retrieveObjects($ids,['ID','Ob_Group','Last_Updated', 'Owner', 'Owner_Group', 'Min_Modify_Rank'],$test);

            //In case no objects were found, what can we do..
            if($objs == 1){
                if($test)
                    echo 'No objects exist!'.EOL;
                foreach($res as $key=>$val){
                    $res[$key] = 1;
                }
                return json_encode($res);
            }

            foreach($objs as $objID=>$obj){
                //See if object exists
                if ($obj == 1){
                    if($test)
                        echo 'object '.$objID.' doesnt exist!'.EOL;
                    $res[$objID] = 1;
                }
                else{
                    //Check if the object is in the requested time frame
                    if($times[$objID][1])
                        $objInTime = ((int)$obj['Last_Updated'] >= $times[$objID][0]) ? true : false;
                    else
                        $objInTime = ((int)$obj['Last_Updated'] <= $times[$objID][0]) ? true : false;
                    //Check if the user is authorized to modify the object
                    if(!$this->checkObAuth($obj,1)){
                        if($test)
                            echo 'User with id '.json_decode($_SESSION['details'], true)['ID'].' not authorized to delete object '.$objID.EOL;
                        $res[$objID] = 2;
                    }
                    //Check if the object is too new/old to delete
                    elseif(!$objInTime){
                        if($test)
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
                if($this->sqlHandler->deleteFromTable($tableName, [$idsToDelete,'OR'], [], $test) === true){
                    foreach($res as $key=>$val){
                        if($val == 4)
                            $res[$key] = 0;
                    }
                    //Now, handle updating the group sizes
                    $this->updateGroups($groupsToUpdate,$test);
                }
            }

            return $res;
        }

        /* Returns objects, but this time by groups.
         * @param array $params of the form:
         *              'updated', => number default 0. Will only return objects whose "Last_Updated" is bigger than updated.
         *              'safeStr' => bool, Default true. Converts object content to normal string from safeString.
         *              'protectedView' => bool, default true. Hides unauthorized objects from showing.
         *              'extraColumns' => array, default []. Gets additional columns of an object.
         *                                  Meant for extensions to this module.
         *
         * Will convert from safeString to normal string if $fromSafeStr is true.
         * Returns either an integer, or almost the same array as "getObjects", where:
         *              Integer codes:
         *                  0 - The whole group is up to date
         *                  1 - The group with this name does not exist
         *              Array codes of the form <ID:Code>
         *                  0 if you can view the object yet $updated is bigger than the object's Last_Updated field.
         *                  1 - CANNOT BE RETURNED. If You are missing an object ID, it means that object isn't part of the group anymore or was deleted.
         *                  2 if insufficient authorization to view the object.
         * */
        function getObjectsByGroup($groupName, $params = [], $test = false){

            $errors = [];
            $res = [];

            //set default params
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
            $objectName = $this->sqlHandler->getSQLPrefix().'OBJECT_CACHE';
            $objectGroupName = $this->sqlHandler->getSQLPrefix().'OBJECT_CACHE_META';
            $query =
                //Select all objects that are not up to date - Unless the whole group is up to date
                $this->sqlHandler->selectFromTable(
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
                    ['justTheQuery'=>true],
                    false
                ).
                ' UNION '
                .
                //Select all objects that are up to date - Unless the whole group is up to date
                $this->sqlHandler->selectFromTable(
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
                    ['justTheQuery'=>true],
                    false).
                ' UNION '
                .
                //Select (-1,-1,NULL,-1,-1,null,true) - IF the whole group is up to date (and exists)
                $this->sqlHandler->selectFromTable(
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
                    ['justTheQuery'=>true],
                    false).
                ' UNION '
                .
                //Select (-2,-2,NULL,-2,-2,null,false) - IF the group does not exist
                $this->sqlHandler->selectFromTable(
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
                    ['justTheQuery'=>true],
                    false)
            ;

            if($test){
                echo 'Query to send: '.$query.EOL;
            }
            $objects = $this->sqlHandler->exeQueryBindParam($query, [], true);

            //This means the group does not exist
            if($objects == [] || $objects[0]['ID'] == -2)
                return 1;
            //This means the group is up to date
            if($objects[0]['ID'] == -1)
                return 0;

            foreach($objects as $k=>$v){
                //If we don't have auth to view the object, stick it into the error group.
                if(!$this->checkObAuth($objects[$k],1,0)){
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
                            $objects[$k]['Object'] = safeStr2Str($objects[$k]['Object']);
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
        function checkObAuth($obj,$type = 0, $strict = 0, $sesInfo = null){
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
                        if($sesInfo['Rank'] == 0)
                            $isOwner = true;
                        elseif(!$strict){
                            if($type == 1){
                                if($obj['Min_Modify_Rank']>=$sesInfo['Rank'])
                                    $isOwner = true;
                            }
                            else if($type == 0){
                                if( $obj['Min_View_Rank']>=$sesInfo['Rank'] || $obj['Min_View_Rank']== -1)
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



        // Checks if the object could be legally inserted into / removed from the group.
        function checkObAddGroupAuth($groupInfo, $sesInfo, $toAdd ,$test = false){
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
            if($owner == $sesInfo['ID'])
                return true;

            if($test){
                echo 'User '.$sesInfo['ID'].' does not have the auth to modify group '.$groupInfo['Group_Name'].EOL;
            }
            return false;
        }

        /* Retrieves from a table, by keyNames, and returns a result in the form
           [<keyName> => <Associated array for row>]
           or returns 1 if nothing exists, or on different error
         * */
        private function getFromTableByKey($keys,$keyCol,$tableName,$columns, $test = false){
            if(!in_array($keyCol,$columns) && $columns!=[])
                array_push($columns,$keyCol);
            $conds = [];
            $tableName = $this->sqlHandler->getSQLPrefix().$tableName;
            $tempRes = [];
            foreach($keys as $key){
                array_push($conds,[$keyCol,$key,'=']);
                $tempRes[$key] = 1;
            }
            if($conds != [])
                array_push($conds,'OR');
            $res = $this->sqlHandler->selectFromTable($tableName,$conds,$columns,[],$test);
            if(is_array($res)){
                $resLength = count($res);
                for($i = 0; $i<$resLength; $i+=1){
                    $resLength2 = count($res[$i]);
                    for($j = 0; $j<$resLength2; $j++){
                        unset($res[$i][$j]);//This is ok because no valid column name will consist solely of digits
                    }
                    $tempRes[$res[$i][$keyCol]] = $res[$i];
                }
                return $tempRes;
            }
            else{
                return 1;
            }
        }

        /* Retrieves an object of a certain ID from the database.
         * Returns  the object as an assoc array, if succesful,
         *          1, if object by this id doesn't exist.
         * */
        function retrieveObject($id, $columns = [], $test = false){
            $res = $this->retrieveObjects([$id],$columns,$test);
            return is_array($res)?
                $res[$id] : $res;
        }

        /* Retrieves an object of a certain ID from the database.
         * Returns  the object as an assoc array of the form <objectID>=><objectAssocArray>, if succesful,
         *          1, if no objects exist, or encountered error.
         * */
        function retrieveObjects(array $ids, $columns = [], $test = false){
            return $this->getFromTableByKey($ids,'ID','OBJECT_CACHE',$columns,$test);
        }


        /* Retrieves a group of a certain name from the database.
         * Returns the group as an assoc array, if succesful,
         *          1, if group by this name doesn't exist.
         * */
        function retrieveGroup($group, $columns = [], $test = false){
            $res =  $this->retrieveGroups([$group],$columns,$test);
            return is_array($res)?
                $res[$group] : $res;
        }

        /* Retrieves a group of a certain name from the database.
         * Returns the group as an assoc array, if succesful,
         *          1, if group by this name doesn't exist.
         * */
        function retrieveGroups(array $groups, $columns = [], $test = false){
            return $this->getFromTableByKey($groups,'Group_Name','OBJECT_CACHE_META',$columns,$test);
        }

        /* Retrieves a page map, identified by $page, from the database.
         * Returns  the object as an assoc array, if succesful,
         *          1, if a page by this name doesn't exist.
         * */
        function retrieveObjectMap($object, $columns = [], $test = false){
            $res = $this->retrieveObjectMaps([$object],$columns,$test);
            return is_array($res)?
                $res[$object] : $res;
        }

        /* Retrieves a page map, identified by $page, from the database.
         * Returns  the object as an assoc array, if succesful,
         *          1, if a page by this name doesn't exist.
         * */
        function retrieveObjectMaps(array $objects, $columns = [], $test = false){
            return $this->getFromTableByKey($objects,'Page_Name','OBJECT_MAP',$columns,$test);
        }

        /* Creates a group of name $group, and size of $newSize.
         * Returns 0 on success,
         *         1 on faliure.
         * */
        function createGroup($group, $sesInfo, $test = false){
            return $this->createGroups([$group], $sesInfo,$test);
        }

        /* Creates inputs, where each group is an array of inputs like those in createGroup.
         * Returns 0 on success,
         *         1 on faliure.
         * */
        function createGroups($groups,$sesInfo, $test = false){
            $tableName = $this->sqlHandler->getSQLPrefix().'OBJECT_CACHE_META';
            $cols = ['Group_Name','Owner','Last_Updated'];
            $values = [];
            $timeUpdated = strval(time());
            //If there is nothing to do..
            if($groups == [])
                return 0;
            //Else create the groups
            foreach($groups as $group){
                array_push($values,[[$group,'STRING'],$sesInfo['ID'],[$timeUpdated,'STRING']]);
            }
            //execute
            if($values!=[])
                $res =$this->sqlHandler->insertIntoTable($tableName,$cols,$values,['onDuplicateKey'=>false, 'returnRows'=>false],$test);
            else
                $res = true;
            return ($res === true)?
                0 : 1;
        }

        /* Updates group - usually invoked when an object in the group has changed.
         * $group is a group name
         * Returns true on success, false on failure
         * */
        function updateGroup(string $group, $test = false){
            return $this->updateGroups([$group],$test);
        }

        /* Updates group - usually invoked when an object in the group has changed.
         * $group is an array of group names
         * Returns true on success, false on failure
         * */
        function updateGroups($groups, $test = false){
            $tableName = $this->sqlHandler->getSQLPrefix().'OBJECT_CACHE_META';
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
            $res =$this->sqlHandler->insertIntoTable($tableName,$cols,$values,['onDuplicateKey'=>true, 'returnRows'=>false],$test);
            return ($res === true)?
                0 : 1;
        }

        /* Checks whether a %time is up-to-date compared to a $group (name).
         * Returns  0 if up to date,
         *          1 if not up to date,
         *          -1 if group doesn't exist
         * */

        function checkGroupUpdated($group, $time, $test = false){
            return $this->checkGroupsUpdated([$group => $time],$test)[$group];
        }

        /* checkGroupUpdated but for more than 1 group.
         * returns [<groupName>=><result as per checkGroupUpdated>]
         * */

        function checkGroupsUpdated($groups,$test = false){
            $toRetrieve = [];
            foreach($groups as $groupName => $groupTime){
                array_push($toRetrieve,$groupName);
            }
            //Get the inputs' Last_Updated
            $groupRes = $this->retrieveGroups($toRetrieve, ['Group_Name','Last_Updated'], $test);
            $resArr = [];
            foreach($groups as  $groupName => $groupTime){
                //Check whether the group actually exists
                if($groupRes[$groupName] == 1)
                    $resArr[$groupName] = -1;
                //Check whether group is up to date
                elseif($groupRes[$groupName]['Last_Updated']<=$groupTime)
                    $resArr[$groupName] = 0;
                else
                    $resArr[$groupName] = 1;
            }

            if($test)
                echo 'Result: '.json_encode($resArr).EOL;

            return $resArr;

        }

        /* Assigns, or removes assignment of an object to a page.
         * $id is the object ID, $page is the path/name of the page, as defined in the OBJECT_MAP docs in SQLdbInit.php
         * $assign is true if you want to assign an object to a page, false if you want to remove an object from a page.
         * Returns  0 on success.
         *          1 if object or page id don't exist.
         *          2 if insufficient authorization to modify the object.
         *          3 if insufficient authorization to remove/add object-page assignments.
         *          4 Different error
         * */

        function objectMapModify($id, $page, $assign = true, $test = false){
            $res = $this->objectMapModifyMultiple([[$id, $page, $assign]],$test);
            if($res == -1)
                return 4;
            if($res == 3)
                return 3;
            return $res[$id];
        }


        /* Assigns, or removes assignment of multiple objects to/from multiple pages.
         * Returns -1 on database failure,
         *          an assoc array [<objectID> => <resultCode>] otherwise
         * TODO *SHOULD* be remade using a stored procedure - doesn't support cuncurrent use at current state
         * */

        function objectMapModifyMultiple(array $inputArray, $test = false){

            //You must be logged in to use this handler, as it checks the caller's rank and ID.
            if(!assertLogin())
                return -1;

            //Array of the type <pageName> => <objectID> for all objects that need to be assigned
            $assignMaps = [];
            //Array of the type <pageName> => <objectID> for all objects that need to be deleted
            $deleteMaps = [];
            //Relevant Pages, form <pageName>=><pageName>
            $mapNames = [];
            //Relevant Object IDs, form <objectID>=><objectID>
            $objectIDs = [];

            //Populate the 2 arrays using $inputArray
            foreach($inputArray as $input){
                if($input[2]){
                    if(!isset( $assignMaps[$input[1]]) ){
                        $assignMaps[$input[1]] =[$input[0]];
                        $mapNames[$input[1]] = $input[1];
                    }
                    else{
                        array_push($assignMaps[$input[1]],$input[0]);
                    }
                }
                else{
                    if(!isset( $deleteMaps[$input[1]]) ){
                        $deleteMaps[$input[1]] =[$input[0]];
                        $mapNames[$input[1]] = $input[1];
                    }
                    else{
                        array_push($deleteMaps[$input[1]],$input[0]);
                    }
                }
                $objectIDs[$input[0]] = $input[0];
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
                            unset($objectIDs[$val]);
                            if($assignMaps[$mapName] = []){
                                unset($assignMaps[$mapName]);
                                unset($mapNames[$mapName]);
                            }
                            if($deleteMaps[$mapName] = []){
                                unset($deleteMaps[$mapName]);
                                unset($mapNames[$mapName]);
                            }
                        }
                    }
                }
            }

            //Get session info
            $sesInfo = json_decode($_SESSION['details'],true);

            //get ALL relevant mapNames, and ALL relevant objects from the database
            //TODO can be done in one query with a bit more work
            $mapNamesExt = $this->retrieveObjectMaps($mapNames,[],$test);
            $objectIDsExt = $this->retrieveObjects($objectIDs,['ID','Owner', 'Owner_Group', 'Min_Modify_Rank'],$test);

            if($objectIDsExt == 1){
                if($test)
                    echo 'No objects of specified IDs/Names exist!'.EOL;
                return 1;
            }

            //At this point, we log the time as Last_Changed for object_map
            $lastChanged = strval(time());

            //If no maps existed, we will still create new ones for assigning objects, or not care when deleting them
            if($mapNamesExt == 1)
                $mapNamesExt = [];

            //We will need those 2 arrays for our queries
            $deleteArray = [];

            //Check that the objects exist, and their auth
            foreach($objectIDs as $objectID){
                //If the map name does not exist, you might want to return 1, or at least specify it does not exist
                if(!isset($objectIDsExt[$objectID])){
                    $objectIDs[$objectID] = 1;
                }
                else{
                    $objectIDs[$objectID] = $objectIDsExt[$objectID];
                }
                //Check object auth
                if(!$this->checkObAuth($objectIDs[$objectID],1,0,$sesInfo)){
                    $objectIDs[$objectID] = 2;
                }
            }

            foreach($mapNames as $mapName){
                //If the map name does not exist, you might want to specify it does not exist
                if(!isset($mapNamesExt[$mapName])){
                    $mapNames[$mapName] = 1;
                }
                else{
                    $objectArr = ($mapNamesExt[$mapName]['Objects'] != null) ?
                        json_decode($mapNamesExt[$mapName]['Objects'],true) : [] ;
                    $mapNames[$mapName] = [
                        'Objects' => $objectArr
                    ];
                }

                //Assign to the relevant maps all the objects THAT EXIST
                if(isset($assignMaps[$mapName]))
                    foreach($assignMaps[$mapName] as $objToAdd){
                        if(is_array($objectIDs[$objToAdd])){
                            //Create new page if need be
                            if($mapNames[$mapName] == 1)
                                $mapNames[$mapName] = [
                                    'Objects' => []
                                ];
                            $mapNames[$mapName]['Objects'][$objToAdd] = $objToAdd;
                            //Mark object as successfully assigned
                            $objectIDs[$objToAdd] = 0;
                        }
                    }
                //Delete all objects YOU MAY MODIFY from maps THAT EXIST. Mark empty maps for deletion
                if(isset($deleteMaps[$mapName]))
                    foreach($deleteMaps[$mapName] as $objToDelete){
                        if($objectIDs[$objToDelete] != 2){
                            //If page does not exist, do nothing
                            if($mapNames[$mapName] != 1){
                                //Unset object if it's assigned to map
                                if(isset($mapNames[$mapName]['Objects'][$objToDelete]))
                                    unset($mapNames[$mapName]['Objects'][$objToDelete]);
                                //If a page just became empty, mark it for deletion.
                                if(count($mapNames[$mapName]['Objects']) == 0){
                                    array_push($deleteArray,$mapName);
                                    $mapNames[$mapName] = 1;
                                }
                            }
                            //Mark object as successfully deleted - can't be 0, or else $assignMaps might miss them later
                            $objectIDs[$objToDelete] = [];
                        }
                    }
            }

            //Every object marked as [] is actually 0
            foreach($objectIDs as $key=>$val){
                if($val==[])
                    $objectIDs[$key] = 0;
            }

            $tableName = $this->sqlHandler->getSQLPrefix().'OBJECT_MAP';

            //Delete all empty pages first
            $conds = [];
            foreach($deleteArray as $key){
                array_push($conds,['Page_Name',$key,'=']);
            }
            if(count($conds)>0){
                array_push($conds,'OR');
                if($this->sqlHandler->deleteFromTable($tableName,$conds,[],$test) !== true){
                    if($test)
                        echo 'Unexpected error when deleting pages!'.EOL;
                    return -1;
                };
            }
            //Luckily for us, create and update operations may be handled in one query
            $values = [];
            $colArr = ['Page_Name','Objects','Last_Changed'];
            foreach($mapNames as $mapName=>$details){
                //Obviously, don't do this for empty pages
                if($mapNames[$mapName] != 1){
                    array_push($values,[[$mapName,'STRING'],[json_encode($details['Objects']),'STRING'],[$lastChanged,'STRING']]);
                }
            }
            if($values !=[])
                if($this->sqlHandler->insertIntoTable($tableName,$colArr,$values,['onDuplicateKey'=>true, 'returnRows'=>false],$test) !== true){
                    if($test)
                        echo 'Unexpected error when updating pages!'.EOL;
                    return -1;
                };

            return $objectIDs;

        }

        /* Gets the list of objects assigned to a certein $page, if the latest change was made after $time.
         * Returns:     A JSON array of the objects, of the form {"ID":"ID",...}, if $time < Last_Changed
         *              0 if Last_Changed < $time
         *              1 if the page doesn't exist, or has no objects in it
         * TODO - Add security, allow private or otherwise secure objects, or at least pages
         * */
        function getObjectMap($page, $time = 0, $test = false){
            $pageArr = $this->retrieveObjectMap($page, [],$test);
            if($pageArr == 1){
                if($test)
                    echo 'Page  '.$page.' does not exist!'.EOL;
                return 1;
            }
            else{
                if($pageArr['Last_Changed'] < $time){
                    if($test)
                        echo 'Page '.$page.' is up to date! ';
                    return 0;
                }
                else{
                    if($test)
                        echo 'Retrieving object list from page '.$page.': ';
                    ($pageArr['Objects'] != '')?
                        $res = $pageArr['Objects'] : $res = 1 ;
                    return $res;
                }
            }
        }

        /* Backs up the whole Objects state
         * */
        function backupObjects($test = false){
            $prefix = $this->sqlHandler->getSQLPrefix();
            $objArr = [$prefix.'OBJECT_CACHE_META',$prefix.'OBJECT_CACHE',$prefix.'OBJECT_MAP'];
            $this->sqlHandler->backupTables($objArr,[],[], $test);
        }

        /* Restores latest state - from the latest backup
         * */
        function restoreLatestState($test = false){
            $prefix = $this->sqlHandler->getSQLPrefix();
            $objArr = [$prefix.'OBJECT_CACHE_META',$prefix.'OBJECT_CACHE',$prefix.'OBJECT_MAP'];
            $this->sqlHandler->restoreLatestTables($objArr, $test);
        }
    }
}