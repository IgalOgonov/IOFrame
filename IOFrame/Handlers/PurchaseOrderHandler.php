<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('PurchaseOrderHandler',true);
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';

    /* This class handles orders - as in, purchase orders.
     * It isn't meant to be used by itself, as each system has different items that can be purchased, different procedures
     * for how to handle the order process, and so on.
     * This class is meant to be extended by different order handlers for different systems - you may even have multiple
     * types of different purchase orders in the same system.
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */

    class PurchaseOrderHandler extends IOFrame\abstractDBWithCache
    {

        /**
         * @var String Used for default naming of cache names and table names.
         */
        protected $name = 'DEFAULT';

        /**
         * @var string The table name - required to do db operations - used as a prefix for the full db name $tableName.'_ORDERS' and .
         * Defaults to strtoupper($name)
         */
        protected $tableName = null;

        /**
         * @var string The table relation name - required to do db operations - used as a prefix for the full db name $relationTableName.'_USERS_ORDERS'.
         * Defaults to strtoupper($name)
         */
        protected $relationTableName = null;

        /**
         * @var string The cache name - used as a prefix for the full cache name - $cacheName.'_purchase_order_'.
         * Defaults to strtolower($name).
         */
        protected $cacheName = null;

        /**
         * @var string The cache name - used as a prefix for the full cache name - $cacheName.'_purchase_order_users_'.
         * Defaults to strtolower($name).
         */
        protected $relationCacheName = null;

        /**
         * @var string[] As I wrote earlier, this class is meant to be extended. One part of it is adding additional
         * columns, indexed or unindexed, to the DB table, by the system that extends it.
         *
         * For example, a system that handles car orders may add an indexed column called "Car_Mileage", so it is possible to
         * search through the car orders by mileage.
         * Then, every call to the DB this handler makes will assume "Car_Mileage" exists in the DB. Additionally, the
         * setter function will accept the parameter "carMileage" (converts Underscore_Case to camelCase), both getting
         * it from the DB and setting it back to either the provided value, the existing value (if nothing was provided) or NULL (if nothing was provided or existed).
         * A small annoying behaviour is that values that have a default and cant be null would cause the setter to fail
         * if nothing was provided - in that case, there is a way to tell the setter to ignore those values if not provided,
         * rather than try to set them to null.
         *
         * IMPORTANT - All column names MUST have a default value for proper handler operation.
         *
         * -- COLUMN NAMES 'User_Info' and 'Relation_Info' are reserved --
         */
        protected $orderColumnNames = [];

        /**
         * @var string[] Same as $orderColumnNames but in regard to the Users <=> Orders many to many table, and users-orders
         *               related assign/modify functions.
         * -- COLUMN NAMES 'User_Info' and 'Relation_Info' are reserved --
         */
        protected $userOrderColumnNames = [];

        /** Standard constructor
         *
         * Constructs an instance of the class, getting the main settings file and an existing DB connection, or generating
         * such a connection itself.
         *
         * @param object $settings The standard settings object
         * @param array $params - All parameters share the name/type of the class variables
         * */
        function __construct(SettingsHandler $settings, array $params = []){

            parent::__construct($settings,$params);
            if(isset($params['name']))
                $this->name = $params['name'];
            else
                $this->name = 'DEFAULT';

            if(isset($params['cacheName']))
                $this->cacheName = $params['cacheName'].'_purchase_order_';
            else
                $this->cacheName = strtolower($this->name).'_purchase_order_';

            if(isset($params['cacheName']))
                $this->relationCacheName = $params['cacheName'].'_purchase_order_users_';
            else
                $this->relationCacheName = strtolower($this->name).'_purchase_order_users_';

            if(isset($params['tableName']))
                $this->tableName = $params['tableName'].'_ORDERS';
            else
                $this->tableName = strtoupper($this->name).'_ORDERS';

            if(isset($params['relationTableName']))
                $this->relationTableName = $params['relationTableName'].'_USERS_ORDERS';
            else
                $this->relationTableName = strtoupper($this->name).'_USERS_ORDERS';

            if(isset($params['orderColumnNames']))
                $this->orderColumnNames = $params['orderColumnNames'];
            else
                $this->orderColumnNames = [];

            if(isset($params['userOrderColumnNames']))
                $this->userOrderColumnNames = $params['userOrderColumnNames'];
            else
                $this->userOrderColumnNames = [];
        }

        /** Tries to get a lock on orders.
         * @param int[] $orderIDs Array of order IDs
         * @param array $params
         * @return bool|string Lock used, or false if failed to reach DB
         *
         * */
        protected function lockOrders(array $orderIDs, array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            $hex_secure = false;
            $lock = '';
            while(!$hex_secure)
                $lock=bin2hex(openssl_random_pseudo_bytes(128,$hex_secure));

            foreach($orderIDs as $index=>$id){
                $orders[$index] = [
                    $id,
                    'STRING'
                ];
            }

            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->tableName,
                ['Session_Lock = "'.$lock.'", Locked_At = "'.time().'"'],
                [
                    [
                        'ID',
                        array_merge($orderIDs,['CSV']),
                        'IN'
                    ],
                    [
                        'Session_Lock',
                        'ISNULL'
                    ],
                    'AND'
                ],
                ['test'=>$test,'verbose'=>$verbose]
            );
            if(!$res){
                if($verbose)
                    echo 'Failed to lock orders '.json_encode($orderIDs).'!'.EOL;
                return false;
            }
            else{
                if($verbose)
                    echo 'Orders '.json_encode($orderIDs).' locked with lock '.$lock.EOL;
                return $lock;
            }
        }


        /** Unlocks orders locked with a specific session lock.
         * @param string[] $orderIDs Array of order identifiers
         * @param array $params Parameters of the form:
         *              'key' - string, default null - if not NULL, will only try to unlock orders that
         *                      have a specific key. TODO Fix this - does not work properly with key for some reason
         * @return bool true if reached DB, false if didn't reach DB
         *
         * */
         function unlockOrders(array $orderIDs, array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            $key = $test = isset($params['key'])? $params['key'] : null;

            foreach($orderIDs as $index=>$id){
                $orderIDs[$index] = [
                    $id,
                    'STRING'
                ];
            }

            if($key === null)
                $conds = [
                    'ID',
                    array_merge($orderIDs,['CSV']),
                    'IN'
                ];
            else
                $conds = [
                    [
                        'ID',
                        array_merge($orderIDs,['CSV']),
                        'IN'
                    ],
                    [
                        'Session_Lock',
                        [$key,'STRING'],
                        '='
                    ],
                    'AND'
                ];

            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->tableName,
                ['Session_Lock = NULL, Locked_At = NULL'],
                $conds,
                ['test'=>$test,'verbose'=>$verbose]
            );

            if(!$res){
                if($verbose)
                    echo 'Failed to unlock orders '.json_encode($orderIDs).'!'.EOL;
                return false;
            }
            else{
                if($verbose){
                    echo 'Orders '.json_encode($orderIDs).' unlocked';
                    echo ($key)? ' with key '.$key.'!' : '!';
                    echo EOL;
                }
                return true;
            }
        }

        /** Gets a single order by ID.
         * @param int $orderID Orders ID
         * @param array $params
         *          'ignoreLocked' - bool, default false - whether to ignore the order if it's currently locked.
         * @return int|array:
         *          codes:
         *              -1 - failed to reach the db
         *               1 - order does not exist
         *          array: JSON encoded DB array of the order
         * */
        function getOrder(int $orderID,$params = []){
            return $this->getOrders([$orderID],$params)[$orderID];
        }

        /** Gets multiple orders by IDs, or just all the orders.
         * @param int[] $orderIDs Orders IDs. If [], will return all the orders (with limit and offset)
         * @param array $params getFromCacheOrDB() params, as well as:
         *
         *          'keyLocked'         - string, default null - if not null, ignore orders that are locked by a key that
         *                                isn't the value of this param.
         *                                WILL ONLY WORK WITH SPECIFIC IDS!!!
         *          'getLimitedInfo'    - bool, default false - will only get order IDs, Created and Last_Updated
         *          'typeIs'            - string, default null - Only return items which have this order type.
         *          'statusIs'          - string, default null - Only return items which have this order status.
         *          'createdAfter'      - int, default null - Only return items created after this date.
         *          'createdBefore'     - int, default null - Only return items created before this date.
         *          'changedAfter'      - int, default null - Only return items last changed after this date.
         *          'changedBefore'     - int, default null - Only return items last changed  before this date.
         *          'extraDBFilters'    - array, default [] - for each name in $orderColumnNames, there is a good possibility
         *                                you want to be ordering or filtering by them. Maybe even more complex filters
         *                                than the ones provided. This array is for that.
         *                                This array will be merged with $extraDBConditions before the query, and passed
         *                                to getFromCacheOrDB() as the 'extraConditions' param.
         *                                Each condition needs to be a valid PHPQueryBuilder array.
         *          'extraCacheFilters' - array, default [] - Same as extraDBFilters but merged with $extraCacheConditions
         *                                and passed to getFromCacheOrDB() as 'columnConditions'.
         *          ----- The parameters bellow disable caching -----
         *          'orderBy'     - string, defaults to null. Possible values include 'Created' 'Last_Changed',
         *                          and any of the names in $orderColumnNames
         *          'orderType'   - bool, defaults to null.  0 for 'ASC', 1 for 'DESC'
         *          'limit'       - string, SQL LIMIT, defaults to system default
         *          'offset'      - string, SQL OFFSET
         * @return int|array:
         *          codes:
         *              JSON encoded DB array of the form:
         *              -1 - failed to reach the db
         *               1 - order does not exist
         *               2 - order exists but is locked
         *          array:
         *              [
         *                  <id> => <Array of DB info> | <int 1 if specific order doesnt exist or fails the filter checks>
         *              ]
                 *      if $orderIDs is [], also returns the object '@' (stands for 'meta') inside which there is a
         *              single key '#', and the value is the total number of results if there was no limit.
         * */
        function getOrders(array $orderIDs = [],$params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            $extraDBFilters = isset($params['extraDBFilters'])? $params['extraDBFilters'] : [];
            $extraCacheFilters = isset($params['extraCacheFilters'])? $params['extraCacheFilters'] : [];

            $keyLocked = isset($params['keyLocked'])? $params['keyLocked'] : null;
            $getLimitedInfo = isset($params['getLimitedInfo'])? $params['getLimitedInfo'] : false;

            $typeIs = isset($params['typeIs'])? $params['typeIs'] : null;
            $statusIs = isset($params['statusIs'])? $params['statusIs'] : null;
            $createdAfter = isset($params['createdAfter'])? $params['createdAfter'] : null;
            $createdBefore = isset($params['createdBefore'])? $params['createdBefore'] : null;
            $changedAfter = isset($params['changedAfter'])? $params['changedAfter'] : null;
            $changedBefore = isset($params['changedBefore'])? $params['changedBefore'] : null;
            $orderBy = isset($params['orderBy'])? $params['orderBy'] : null;
            $orderType = isset($params['orderType'])? $params['orderType'] : 0;
            $limit = isset($params['limit'])? $params['limit'] : null;
            $offset = isset($params['offset'])? $params['offset'] : null;

            $prefix = $this->SQLHandler->getSQLPrefix();
            $retrieveParams = $params;
            $extraDBConditions = [];
            $extraCacheConditions = [];
            $colPrefix = $this->SQLHandler->getSQLPrefix().$this->tableName.'.';

            //If we are using any of this functionality, we cannot use the cache
            if($offset || $limit ||  $orderBy || $orderType)
                $retrieveParams['useCache'] = false;
            elseif($getLimitedInfo)
                $retrieveParams['updateCache'] = false;

            //Create all the conditions for the db/cache
            if($typeIs!== null){
                $cond = [$colPrefix.'Order_Type',$typeIs,'='];
                $condDB = [$colPrefix.'Order_Type',[$typeIs,'STRING'],'='];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$condDB);
            }

            if($statusIs!== null){
                $cond = [$colPrefix.'Order_Status',$statusIs,'='];
                $condDB = [$colPrefix.'Order_Status',[$statusIs,'STRING'],'='];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$condDB);
            }

            if($createdAfter!== null){
                $cond = [$colPrefix.'Created',$createdAfter,'>'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($createdBefore!== null){
                $cond = [$colPrefix.'Created',$createdBefore,'<'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($changedAfter!== null){
                $cond = [$colPrefix.'Last_Updated',$changedAfter,'>'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($changedBefore!== null){
                $cond = [$colPrefix.'Last_Updated',$changedBefore,'<'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            $extraDBConditions = array_merge($extraDBConditions,$extraDBFilters);
            $extraCacheConditions = array_merge($extraCacheConditions,$extraCacheFilters);

            if($extraCacheConditions!=[]){
                array_push($extraCacheConditions,'AND');
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }
            if($extraDBConditions!=[]){
                array_push($extraDBConditions,'AND');
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            if($getLimitedInfo)
                $columns = ['ID','Created','Last_Updated'];
            else
                $columns = [];

            if($orderIDs == []){
                $results = [];

                $tableQuery = $prefix.$this->tableName;

                $res = $this->SQLHandler->selectFromTable(
                    $tableQuery,
                    $extraDBConditions,
                    $columns,
                    $retrieveParams
                );
                $count = $this->SQLHandler->selectFromTable(
                    $tableQuery,
                    $extraDBConditions,
                    ['COUNT(*)'],
                    array_merge($retrieveParams,['limit'=>0])
                );
                if(is_array($res)){
                    $resCount = isset($res[0]) ? count($res[0]) : 0;
                    foreach($res as $resultArray){
                        for($i = 0; $i<$resCount/2; $i++)
                            unset($resultArray[$i]);
                        $results[$resultArray['ID']] = $resultArray;
                    }
                    $results['@'] = array('#' => $count[0][0]);
                }
                return (is_array($res))? $results : [];
            }
            else{

                $results = $this->getFromCacheOrDB(
                    $orderIDs,
                    'ID',
                    $this->tableName,
                    $this->cacheName,
                    $columns,
                    $retrieveParams
                );

                if($keyLocked)
                    foreach($results as $index => $result)
                        if(is_array($result) && $result['Session_Lock']!==null && $result['Session_Lock']!==$keyLocked)
                            $results[$index] = 2;

                return $results;
            }
        }

        /** Sets a single order, by ID.
         * @param int $orderID ID of the order to change. Ignored if you are creating a new order.
         * @param array $inputs Inputs of the form:
         *                      'orderInfo' => string, default null - a JSON encoded object to be set. Will be merged with
         *                                     the existing value (if it exists) using array_merge_recursive_distinct, with
         *                                     'deleteOnNull' being true.
         *                      'orderType' => string, default null - Potential identifier of the order type.
         *                      'orderStatus' => string, default null - Potential identifier of the order status.
         *                      <Any additional column name in $orderColumnNames converted from Underscore_Case to camelCase> -
         *                      they ALWAYS default to null, which means unchanged.
         *                      To annul DB values, one needs to pass an empty string '' (and DB values can't be an empty string).
         * @param array $params
         *          'createNew' - bool, default false - if true, orderID will be ignore, and a new order will be created.
         *          'existing' - Array default null - the DB object array, like one from getOrders() - if not null,
         *                       must have all the additional columns from $orderColumnNames. Same for setOrder and setOrders.
         *          'key' - string, default null - if 'existing' is not null, the key to unlock them (that was acquired earlier)
         *                  must be provided. Don't forget to lock the orders you get (and modify) in this class extensions!
         * @return int:
         *          codes:
         *              [createNew == false] -3 - trying to set an order which does not exist
         *              [createNew == false] -2 - failed to lock order
         *              -1 - failed to reach the db
         *              [createNew == false] 0 - All good
         *              [createNew == true] ID of the newly created order - starts with 1
         *
         */
         function setOrder( int $orderID, array $inputs, $params = []){
             $createNew = isset($params['createNew'])? $params['createNew'] : false;
             if(!$createNew)
                return $this->setOrders([[$orderID,$inputs]],$params)[$orderID];
             else
                 return $this->setOrders([[$orderID,$inputs]],$params);
        }

        /** Sets multiple orders, by IDs.
         * @param array $inputs Inputs of the form:
         *              [<id>,<$inputs from setOrder>, <another id>,<$inputs from setOrder>, ...]
         * @param array $params from setOrder
         * @return array of the form:
         *              if setting existing orders:
         *              [
         *                  <id> => <code>
         *              ]
         *              if creating new orders:
         *              -1   - fail
         *              <id> - ID of the FIRST newly created order.
         *          where the codes are from setOrder
         */
         function setOrders(array $inputs, $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
             $createNew = isset($params['createNew'])? $params['createNew'] : false;

            $identifiers = [];
            $identifierIndexMap = [];
            $results = [];
            $ordersToSet = [];
            $cacheContactsToUnset = [];
            $contactsToUnlock = [];
            $currentTime = (string)time();

             //If we are setting existing orders, there is quite a bit we need to do
             if(!$createNew){
                 //Create the usual structures, index/identifier maps, initiate results, etc
                 foreach($inputs as $index => $inputArray){
                     $identifier = $inputArray[0];
                     //Add the identifier to the array - indexes are 1..n anyway
                     $identifiers[$index] = $identifier;
                     $results[$identifier] = -1;
                     $identifierIndexMap[$identifier] = $index;
                 }
                 //Lock, then get existing orders
                 if(isset($params['existing']) && isset($params['key'])){
                     $key = $params['key'];
                     $existing = $params['existing'];
                 }
                 else{
                     $key = $this->lockOrders($identifiers,$params);
                     if(!$key){
                         foreach($results as $index => $result)
                             $results[$index] = -2;
                         return $results;
                     }
                     $existing = $this->getOrders($identifiers, array_merge($params,['updateCache'=>false,'keyLocked'=>$key]));
                 }
             }
             else
                 $results = -1;

            //Parse all existing orders.
            foreach($inputs as $index => $inputArray){
                $identifier = $inputArray[0];
                $orderInputs = isset($inputArray[1])? $inputArray[1] : [];
                //All changes history must be stored
                $historyAddition = [];

                //Initiate each input and add it to change history
                $possibleInputs = array_merge(['Order_Info','Order_Status','Order_Type'],$this->orderColumnNames);
                foreach($possibleInputs as $possibleInput){
                    //Converts from Underscore_Separated to camelCase
                    $possibleInput = str_replace('_','',lcfirst($possibleInput));
                    $orderInputs[$possibleInput] = isset($orderInputs[$possibleInput])? $orderInputs[$possibleInput] : null;
                    if($orderInputs[$possibleInput] !== null){
                        $historyAddition[$possibleInput] = $orderInputs[$possibleInput];
                    }
                }
                $historyAddition['lastUpdated'] = $currentTime;


                $orderToSet = [];
                $orderToSet['Last_Updated'] = [$currentTime,'STRING'];

                //If we are setting an existing order
                if(!$createNew){
                    //If a single contact failed to be gotten from the db, it's safe to bet DB connection failed in general.
                    if($existing[$identifier] === -1)
                        return $results;
                    array_push($contactsToUnlock,$identifier);

                    //If the order exists but was locked, we cannot modify it or unlock it
                    if($existing[$identifier] === 2){
                        $results[$identifier] = -2;
                        unset($identifiers[array_search($identifier,$identifiers)]);
                        unset($contactsToUnlock[array_search($identifier,$contactsToUnlock)]);
                        continue;
                    }
                    elseif(gettype($existing[$identifier]) !== 'array'){
                        $results[$identifier] = -3;
                        unset($identifiers[array_search($identifier,$identifiers)]);
                        unset($contactsToUnlock[array_search($identifier,$contactsToUnlock)]);
                        continue;
                    }
                    //If we are updating, get all missing inputs from the existing result and update history
                    else{
                        $orderToSet['ID'] = $identifier;
                        $orderToSet['Created'] = [$existing[$identifier]['Created'],'STRING'];

                        //Get existing or set new
                        $possibleInputs = array_merge(['Order_Status','Order_Type'],$this->orderColumnNames);
                        foreach($possibleInputs as $dbName){
                            $inputName = str_replace('_','',lcfirst($dbName));
                            //Use existing values
                            if($orderInputs[$inputName]===null)
                                $orderToSet[$dbName] = $existing[$identifier][$dbName];
                            //Annul existing values
                            elseif($orderInputs[$inputName]==='')
                                $orderToSet[$dbName] = null;
                            //Use provided values
                            else
                                $orderToSet[$dbName] = $orderInputs[$inputName];
                            //Specify that a string is a string
                            if(gettype($orderToSet[$dbName]) === 'string')
                                $orderToSet[$dbName] = [$orderToSet[$dbName],'STRING'];
                        }
                        //Order_Info gets special treatment
                        if($orderInputs['orderInfo']!==null){
                            if($orderInputs['orderInfo'] === '')
                                $orderToSet['Order_Info'] = null;
                            else{
                                $inputJSON = json_decode($orderInputs['orderInfo'],true);
                                $existingJSON = json_decode($existing[$identifier]['Order_Info'],true);
                                if($inputJSON === null)
                                    $inputJSON = [];
                                if($existingJSON === null)
                                    $existingJSON = [];
                                $orderToSet['Order_Info'] =
                                    json_encode(IOFrame\Util\array_merge_recursive_distinct($existingJSON,$inputJSON,['deleteOnNull'=>true]));
                                if($orderToSet['Order_Info'] == '[]')
                                    $orderToSet['Order_Info'] = null;
                            }
                        }
                        else
                            $orderToSet['Order_Info'] = $existing[$identifier]['Order_Info'];

                        if($orderToSet['Order_Info'] !== null)
                            $orderToSet['Order_Info'] = [$orderToSet['Order_Info'],'STRING'];

                        //Finally, update the history
                        $existingHistory = json_decode($existing[$identifier]['Order_History'],true);
                        if($existingHistory === null)
                            $existingHistory = [];
                        array_push($existingHistory,$historyAddition);
                        $orderToSet['Order_History'] = [json_encode($existingHistory),'STRING'];
                    }
                }
                //If are creating a new order
                else{
                    $orderToSet['Created'] = [$currentTime,'STRING'];
                    //Set new values
                    $possibleInputs = array_merge(['Order_Info','Order_Status','Order_Type'],$this->orderColumnNames);
                    foreach($possibleInputs as $dbName){
                        $inputName = str_replace('_','',lcfirst($dbName));
                        // empty strings are like null to us
                        if($orderInputs[$inputName]==='')
                            $orderToSet[$dbName] = null;
                        //Use provided values
                        else
                            $orderToSet[$dbName] = $orderInputs[$inputName];
                        //Specify that a string is a string
                        if(gettype($orderToSet[$dbName]) === 'string')
                            $orderToSet[$dbName] = [$orderToSet[$dbName],'STRING'];
                    }
                    //Dont forget Order_History
                    $orderToSet['Order_History'] = [json_encode([$historyAddition]),'STRING'];
                }

                if($orderToSet !== []){
                    array_push($ordersToSet,$orderToSet);
                    if(!$createNew)
                        array_push($cacheContactsToUnset,$identifier);
                }
            }

            //Push everything inside the set array
            $columns = (!$createNew)? ['ID'] : [];
            $columns = array_merge($columns,['Order_Info','Order_Status','Order_Type','Order_History','Last_Updated','Created'],$this->orderColumnNames);
            $insertArray = [];
            foreach($ordersToSet as $orderToSet){
                $toSet = [];
                foreach($columns as $colName)
                    array_push($toSet,$orderToSet[$colName]);
                array_push($insertArray,$toSet);

            }

            //In case we cannot set anything new, return,
            if($insertArray === [])
                return $results;

            //Set the contacts
            $res = $this->SQLHandler->insertIntoTable(
                $this->SQLHandler->getSQLPrefix().$this->tableName,
                $columns,
                $insertArray,
                array_merge($params,['onDuplicateKey' => !$createNew,'returnRows' => $createNew])
            );

            //If successful, set results and erase the cache
            if(!$createNew){
                //Unlock orders if we locked them earlier
                $this->unlockOrders($identifiers,$params);

                if($res){
                    //The bellow code will do nothing if createNew is false, as $identifiers is empty
                    foreach($identifiers as $index => $identifier){
                        if($results[$identifiers[$index]] === -1)
                            $results[$identifiers[$index]] = 0;
                        $identifiers[$index] = $this->cacheName.$identifiers[$index];
                    }
                    if(count($identifiers)>0){
                        if($verbose)
                            echo 'Deleting identifiers '.json_encode($identifiers).' from cache!'.EOL;
                        if(!$test)
                            $this->RedisHandler->call('del',[$identifiers]);
                    }
                }
            }
             //If we created new stuff, return the ID for the first inserted new order
             else{
                 $results = $res;
                 if($results == 0)
                     $results = -1;
             }

            return $results;
        }

        /** Moves orders to archive, by IDs.
         * @param int[] $orderIDs Orders IDs - defaults to [], which means all the orders (subject to constraints).
         *                                   The general usage of this function should be to archive orders too old to be
         *                                   relevant, not delete/archive specific orders, so unless you know what you're
         *                                   doing this should stay [].
         * @param array $params of the form:
         *           ALL the params from getOrders. BE CAREFUL not to archive too much at once! By default the limit is 10,000.
         *           'deleteArchived'    => bool, default true - deletes archived orders from the main table on success.
         *           'repeatToLimit'     => bool, default true - The max query limit for this function is 10,000. But maybe you want to
         *                                  archive more than 10,000 orders (and $orderIDs is []). This setting means this
         *                                  function will recursively execute in batches of 10,000 until there are no more
         *                                  orders left to archive that meet the parameters.
         *                                  The speed is capped at 10,000 orders per second.
         *                                  Note that archived tables over at the db_backup_meta will have their iteration
         *                                  saved as meta information in case of more that one (the 'Meta' field will
         *                                  be 'Part 1', 'Part 2', ...)
         *           'timeout'          => int, default 20 - In case repeatToLimit is true and this function is recursively
         *                                                   executing, it will not execute if more time than
         *                                                   limitExecutionTime (in seconds) has passed since the start.
         *                                                   REMEMBER - max_execution_time in php.ini will be the upper limit of this param.
         *           'returnIDMeta'     => bool, default true - Instead of returning all the archived/deleted IDs, which
         *                                 could be millions, returns the minimum,maximum and number of deleted/archived IDs.
         *                                 Their return form becomes:
         *                                 [
         *                                  'smallestID' => <number>,
         *                                  'largestID' => <number>,
         *                                  'total' => <number>,
         *                                 ]
         *           'archivedIDs'      => RESERVED - SHOULD NOT be passed by the initial function caller.
         *           'deletedIDs'       => RESERVED - SHOULD NOT be passed by the initial function caller.
         *           'iteration'        => RESERVED - SHOULD NOT be passed by the initial function caller.
         *
         * @return array Object of the form:
         *          [
         *          'archivedIDs'  => int[]|Object, IDs of the orders that were successfully archived. Changes if 'returnIDMeta' is true.
         *          'deletedIDs'   => int[]|Object, IDs of the orders that were successfully deleted. Changes if 'returnIDMeta' is true.
         *
         *          'codeOrigin'   => string, 'backupTable', 'getOrders', 'timeout', 'deleteArchived' or ''
         *          'code'         => int,  0 if we stopped naturally or reached repeatToLimit,
         *                                  OR -1 if the order deletion function threw the code
         *                                  OR the code from backupTable()/getOrders() if we stopped cause that function threw the error code.
         *          ]
         *
         */
         function archiveOrders(array $orderIDs = [], $params = []){
             $test = isset($params['test'])? $params['test'] : false;
             $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
             $deleteArchived = isset($params['deleteArchived'])? $params['deleteArchived'] : true;
             $repeatToLimit = isset($params['repeatToLimit'])? $params['repeatToLimit'] : true;
             $timeout = isset($params['timeout'])? $params['timeout'] : 20;
             $returnIDMeta = isset($params['returnIDMeta'])? $params['returnIDMeta'] : true;
             $archivedIDs = isset($params['archivedIDs'])? $params['archivedIDs'] : [];
             $deletedIDs = isset($params['deletedIDs'])? $params['deletedIDs'] : [];
             $iteration = isset($params['iteration'])? $params['iteration'] : 1;
             $limit =  isset($params['limit'])? $params['limit'] : 10000;
             $startTime = time();
             $results = [
                 'archivedIDs'=> $archivedIDs,
                 'deletedIDs'=> $deletedIDs,
                 'codeOrigin'=> '',
                 'code'=> 0
             ];

             //Handle limit related stuff - also the recursion stop condition
             $limit -= ($iteration-1) * 10000;
             if($limit<1)
                 return $results;
             $limit = min(10000, $limit);

             //Handle the time limit part
             if($timeout < 0){
                 $results['codeOrigin'] = 'timeout';
                 return $results;
             }

             //Get the orders to be archived
             $existingIDs = [];
             $existing = $this->getOrders($orderIDs, array_merge($params,['limit'=>$limit,'getLimitedInfo'=>true]));

             if(isset($existing['@']))
                unset($existing['@']);

             foreach($existing as $index => $orderArr){
                 //If we got a specific element which isn't an array, it should be -1 or 1
                 if(!is_array($orderArr)){
                     if($orderArr === -1){
                         if($verbose)
                             echo 'Could not get orders at iteration '.$iteration.EOL;
                         $results['codeOrigin'] = 'getOrders';
                         $results['code'] = -1;
                         return $results;
                     }
                     else{
                         unset($existing[$index]);
                         continue;
                     }
                 }
                 array_push($existingIDs,$orderArr['ID']);
             }
             //If there are no existing IDs left, maybe because requested ones do not exist, we are done.
             if(count($existingIDs) ===0)
                 return $results;

             //Build the archiving conditions
             $conds = [
                 'ID',
                 array_merge($existingIDs,['CSV']),
                 'IN'
             ];
             $backUp = $this->SQLHandler->backupTable($this->tableName,[],['test'=>$test,'verbose'=>$verbose,'meta'=>'Part '.$iteration,'cond'=>$conds]);
             if($backUp!== 0){
                 $results['codeOrigin'] = 'backupTable';
                 $results['code'] = $backUp;
                 return $results;
             }
             else{
                 if(!$returnIDMeta)
                    $params['archivedIDs'] = array_merge($archivedIDs,$existingIDs);
                 else{
                     if(isset($params['archivedIDs']['smallestID']))
                         $params['archivedIDs']['smallestID'] = min($params['archivedIDs']['smallestID'],min($existingIDs));
                     else
                         $params['archivedIDs']['smallestID'] = min($existingIDs);

                     if(isset($params['archivedIDs']['largestID']))
                         $params['archivedIDs']['largestID'] = max($params['archivedIDs']['largestID'],max($existingIDs));
                     else
                         $params['archivedIDs']['largestID'] = max($existingIDs);

                     if(isset($params['archivedIDs']['total']))
                         $params['archivedIDs']['total'] = $params['archivedIDs']['total'] + count($existingIDs);
                     else
                         $params['archivedIDs']['total'] = count($existingIDs);
                 }
                 $results['archivedIDs'] = $params['archivedIDs'];
             }

             //Delete archived orders if needed
             if($deleteArchived){
                 $archiveDeletion = $this->SQLHandler->deleteFromTable(
                     $this->SQLHandler->getSQLPrefix().$this->tableName,
                     $conds,
                     ['test'=>$test,'verbose'=>$verbose]
                 );
                 //If we didn't delete orders properly, return
                 if($archiveDeletion === false){
                     $results['codeOrigin'] = 'deleteArchived';
                     $results['code'] = -1;
                     return $results;
                 }
                 //Notify we deleted the IDs

                 if(!$returnIDMeta)
                     $params['deletedIDs'] = array_merge($deletedIDs,$existingIDs);
                 else{
                     if(isset($params['deletedIDs']['smallestID']))
                         $params['deletedIDs']['smallestID'] = min($params['deletedIDs']['smallestID'],min($existingIDs));
                     else
                         $params['deletedIDs']['smallestID'] = min($existingIDs);

                     if(isset($params['deletedIDs']['largestID']))
                         $params['deletedIDs']['largestID'] = max($params['deletedIDs']['largestID'],max($existingIDs));
                     else
                         $params['deletedIDs']['largestID'] = max($existingIDs);

                     if(isset($params['deletedIDs']['total']))
                         $params['deletedIDs']['total'] = $params['deletedIDs']['total'] + count($existingIDs);
                     else
                         $params['deletedIDs']['total'] = count($existingIDs);
                 }
                 $results['deletedIDs'] = $params['deletedIDs'];

                //Delete orders from cache
                 $cacheIDs = [];
                 foreach($existingIDs as $index=>$id)
                     $cacheIDs[$index] = $this->cacheName.$id;
                 if($verbose)
                     echo 'Deleting orders '.json_encode($cacheIDs).' from cache!'.EOL;
                 if(!$test)
                     $this->RedisHandler->call('del',[$cacheIDs]);
             }

             //Repeat if we got anything left.
             if($repeatToLimit){
                 $params['iteration'] = $iteration+1;
                 $params['timeout'] = $timeout - (time() - $startTime);
                 return $this->archiveOrders($orderIDs,$params);
             }

             return $results;
         }

        /** This is a private function that serves as a base for getUserOrders and getOrderUsers.
         *  De facto, the other user/order getter functions are wrappers for this one.
         *  You can understand the parameters and return codes of this function if you read the docs of the other getter
         *  functions.
         */
        private function getOrdersUsers(int $mainID, bool $mainUser, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            $extraDBFilters = isset($params['extraDBFilters'])? $params['extraDBFilters'] : [];
            $extraCacheFilters = (!$mainUser && isset($params['extraCacheFilters']))? $params['extraCacheFilters'] : [];
            $getLimitedInfo = isset($params['getLimitedInfo'])? $params['getLimitedInfo'] : false;

            $secondaryIDs = (!$mainUser && isset($params['orderIDs']))? $params['orderIDs'] : [];

            $returnOrders = ($mainUser && isset($params['returnOrders']))? $params['returnOrders'] : false;
            $relationType = isset($params['relationType'])? $params['relationType'] : null;
            $createdAfter = isset($params['createdAfter'])? $params['createdAfter'] : null;
            $createdBefore = isset($params['createdBefore'])? $params['createdBefore'] : null;
            $changedAfter = isset($params['changedAfter'])? $params['changedAfter'] : null;
            $changedBefore = isset($params['changedBefore'])? $params['changedBefore'] : null;
            $orderBy = isset($params['orderBy'])? $params['orderBy'] : null;
            $orderType = isset($params['orderType'])? $params['orderType'] : 0;
            $limit = isset($params['limit'])? $params['limit'] : null;
            $offset = isset($params['offset'])? $params['offset'] : null;

            $prefix = $this->SQLHandler->getSQLPrefix();
            $retrieveParams = $params;
            $extraDBConditions = [];
            $extraCacheConditions = [];

            //If we are using any of this functionality, we cannot use the cache
            if($offset || $limit ||  $orderBy || $orderType || $mainUser)
                $retrieveParams['useCache'] = false;

            if($relationType!== null){
                $cond = ['Relation_Type',$relationType,'='];
                $condDB = ['Relation_Type',[$relationType,'STRING'],'='];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$condDB);
            }

            if($createdAfter!== null){
                $cond = ['Created',$createdAfter,'>'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($createdBefore!== null){
                $cond = ['Created',$createdBefore,'<'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($changedAfter!== null){
                $cond = ['Last_Updated',$changedAfter,'>'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($changedBefore!== null){
                $cond = ['Last_Updated',$changedBefore,'<'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($secondaryIDs !== []){
                $secondIdentifierColumn = $mainUser? 'Order_ID': 'User_ID';
                $cond = [$secondIdentifierColumn,array_merge($secondaryIDs,'CSV'),'IN'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            $extraDBConditions = array_merge($extraDBConditions,$extraDBFilters);
            if($mainUser)
                $extraDBConditions = array_merge($extraDBConditions, [['User_ID',$mainID,'=']]);

            if(!$mainUser)
                $extraCacheConditions = array_merge($extraCacheConditions,$extraCacheFilters);

            if($extraDBConditions!=[]){
                array_push($extraDBConditions,'AND');
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            if($extraCacheConditions!=[]){
                array_push($extraCacheConditions,'AND');
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }

            if($getLimitedInfo)
                $columns = ['User_ID','Order_ID','Relation_Type','Created','Last_Updated'];
            else
                $columns = [];

            $results = [];
            $tableQuery = $prefix.$this->relationTableName;

            //What to do if a user is the main target
            if($mainUser){

                $res = $this->SQLHandler->selectFromTable(
                    $tableQuery,
                    $extraDBConditions,
                    $columns,
                    $retrieveParams
                );

                if($secondaryIDs == []){
                    $count = $this->SQLHandler->selectFromTable(
                        $tableQuery,
                        $extraDBConditions,
                        ['COUNT(*)'],
                        array_merge($retrieveParams,['limit'=>0])
                    );
                }

                if(is_array($res)){
                    $resCount = isset($res[0]) ? count($res[0]) : 0;
                    foreach($res as $resultArray){
                        for($i = 0; $i<$resCount/2; $i++)
                            unset($resultArray[$i]);
                        $results[$resultArray['Order_ID']] = $resultArray;
                    }
                    if($secondaryIDs == [] && count($res) > 0){
                        $results['@'] = array('#' => $count[0][0]);
                    }

                    //Get extra order information
                    if($returnOrders && count($res) > 0){
                        $existingOrders = [];
                        foreach($results as $ID=> $resArray){
                            if($ID !== '@')
                                array_push($existingOrders,$ID);
                        }
                        $existingOrders = $this->getOrders($existingOrders,['test'=>$test,'verbose'=>$verbose,'getLimitedInfo'=>$getLimitedInfo]);

                        foreach($secondaryIDs as $secondaryID){
                            if(!isset($existingOrders[$secondaryID]))
                                $results[$secondaryID]['Order_Info'] = 1;
                        }
                        foreach($existingOrders as $id => $orderArr)
                            $results[$id]['Order_Info'] = $orderArr;
                    }
                }

                //If we were requesting specific orders, mark those that we didn't get
                if($secondaryIDs !== [])
                    foreach($secondaryIDs as $ID)
                        if(!isset($results[$ID]))
                            $results[$ID] = ($res === false) ? -1 : 1;

                return ($results !== [])? $results : ['@' => ['#' => 0]];
            }
            //What to do if an order is the main target
            else{
                $results = $this->getFromCacheOrDB(
                    [$mainID],
                    ['Order_ID'],
                    $this->relationTableName,
                    $this->relationCacheName,
                    $columns,
                    array_merge($retrieveParams,['groupByFirstNKeys'=>2,'extraKeyColumns'=>['User_ID']])
                );

                return $results;
            }
        }

        /** This is a private function that serves as a base for assignOrdersToUser and assignUsersToOrder.
         *  De facto, the other user/order assignment functions are wrappers for this one.
         *  You can understand the parameters and return codes of this function if you read the docs of the other assignment
         *  functions.
         */
        protected function setUsersOrders(int $mainID, array $targetInputs, bool $mainUser, bool $create, array $params){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            $secondaryIDs = [];
            $inputsMap = [];
            $indexMap = [];
            $results = [];
            $prefix = $this->SQLHandler->getSQLPrefix();
            $currentTime = (string)time();
            $dbArray = [];
            $possibleInputs = array_merge(['Relation_Type','Meta'],$this->orderColumnNames);
            $dbColumns = array_merge(['User_ID','Order_ID','Created','Last_Updated','Relation_Type','Meta'],$this->orderColumnNames);

            //Check whether the main ID exists
            $tableName = $mainUser? 'USERS' : $this->tableName ;
            $cacheName = $mainUser? 'user_' : $this->cacheName ;
            $mainExists = $this->getFromCacheOrDB(
                [$mainID],
                'ID',
                $tableName,
                $cacheName,
                ['ID'],
                ['test'=>$test,'verbose'=>$verbose,'updateCache'=>false]
            );
            $mainExists = is_array($mainExists[$mainID]);

            foreach($targetInputs as $index =>$inputArray){
                array_push($secondaryIDs,$inputArray[0]);
                $indexMap[$inputArray[0]] = $index;
                $inputsMap[$inputArray[0]] = $inputArray[1];
                //If the main ID exists, proceed properly
                if($mainExists)
                    $results[$inputArray[0]] = -1;
                //If the main ID does not exist, set the relevant codes - we'll exit later
                else
                    $results[$inputArray[0]] = $mainUser ? 3 : 2;
            }
            //If the main ID does not exist, return.
            if(!$mainExists)
                return $results;

            //Check whether the secondary IDs exist
            $tableName = $mainUser? $this->tableName :  'USERS' ;
            $cacheName = $mainUser? $this->cacheName :  'user_' ;
            $secondaryItems =  $this->getFromCacheOrDB(
                $secondaryIDs,
                'ID',
                $tableName,
                $cacheName,
                ['ID'],
                ['test'=>$test,'verbose'=>$verbose,'updateCache'=>false]
            );

            //See if we need to get existing items
            if(isset($params['existing']))
                $existing = $params['existing'];
            elseif($mainUser)
                $existing = $this->getUserOrders($mainID,['test'=>$test,'verbose'=>$verbose,'useCache'=>false,'orderIDs'=>$secondaryIDs]);
            else
                $existing = $this->getOrderUsers($mainID,['test'=>$test,'verbose'=>$verbose,'useCache'=>false,'userIDs'=>$secondaryIDs]);

            //If the assignment exists, return 1 for each one that does
            foreach($secondaryIDs as $index => $secondaryID){
                //If we failed to connect to the DB, nothing more to do
                if($secondaryItems[$secondaryID] === -1)
                    return $results;
                //Check which secondary IDs exist, discard those which dont
                elseif($secondaryItems[$secondaryID] === 1){
                    $results[$secondaryID] = $mainUser ? 2 : 3;
                    unset($secondaryIDs[$index]);
                    continue;
                }

                //Whether the ID exists
                $assignmentExists = isset($existing[$secondaryID]);

                //If it does not exist, update the result and unset the secondary ID
                if(($assignmentExists && $create) || (!$assignmentExists && !$create)){
                    $results[$secondaryID] = 1;
                    unset($secondaryIDs[$index]);
                    continue;
                }

                $assignmentArray = [];

                //Handle User ID, Order ID, Time Created and Time Updated
                $userID = $mainUser? $mainID : $secondaryID;
                $orderID = $mainUser? $secondaryID : $mainID;
                if($create){
                    $createdTime = $currentTime;
                }
                else{
                    $createdTime =  $existing[$secondaryID]['Created'];
                }
                array_push($assignmentArray,$userID);
                array_push($assignmentArray,$orderID);
                array_push($assignmentArray,[$createdTime,'STRING']);
                array_push($assignmentArray,[$currentTime,'STRING']);

                //Handle the rest of the inputs
                foreach($possibleInputs as $possibleInput){
                    //For clarity
                    $dbColumnName = $possibleInput;
                    //Converts from Underscore_Separated to camelCase
                    $possibleInputName = str_replace('_','',lcfirst($possibleInput));
                    $input = isset($inputsMap[$secondaryID][$possibleInputName])? $inputsMap[$secondaryID][$possibleInputName] : null;
                    //When the user passes '', it means he wants to annul the input
                    if($input === '')
                        $input = null;
                    elseif($input === null && !$create){
                        $input = $existing[$secondaryID][$dbColumnName];
                    }

                    //Meta gets a special treatment if we are updating an existing relation
                    if(!$create)
                        $existingMeta =  $existing[$secondaryID][$dbColumnName];
                    else
                        $existingMeta = null;
                    if($possibleInput === 'Meta' && $input !== null && IOFrame\Util\is_json($input) && IOFrame\Util\is_json($existingMeta) && !$create){
                        if($existingMeta !== null){
                            $inputJSON = json_decode($input,true);
                            $existingJSON = json_decode($existingMeta,true);
                            if($inputJSON === null)
                                $inputJSON = [];
                            if($existingJSON === null)
                                $existingJSON = [];
                            $input =
                                json_encode(IOFrame\Util\array_merge_recursive_distinct($existingJSON,$inputJSON,['deleteOnNull'=>true]));
                            if($input == '[]')
                                $input = null;
                        }
                    }

                    if(gettype($input) === 'string')
                        $input = [$input,'STRING'];
                    array_push($assignmentArray,$input);
                }

                array_push($dbArray,$assignmentArray);
            }

            //If there is nothing left to set, what can we do.
            if(count($secondaryIDs) == 0)
                return $results;

            //If we were going to set orders of user, then the secondary IDs are affected orders
            $affectedOrders = $mainUser ? $secondaryIDs : [$mainID];

            //SET RELEVANT RELATIONS
            $res = $this->SQLHandler->insertIntoTable(
                $prefix.$this->relationTableName,
                $dbColumns,
                $dbArray,
                array_merge($params,['onDuplicateKey'=>true])
            );

            //If assignment failed, return
            if($res === false)
                return $results;
            else{
                foreach($results as $index => $res)
                    if($res === -1)
                        $results[$index] = 0;
            }

            //REMOVE AFFECTED ORDERS FROM CACHE
            foreach($affectedOrders as $index => $orderID)
                $affectedOrders[$index] = $this->relationCacheName.$orderID;

            if($verbose)
                echo 'Removing orders '.json_encode($affectedOrders).' from cache'.EOL;
            if(!$test)
                $this->RedisHandler->call('del',[$affectedOrders]);

            return $results;
        }

        /** This is a private function that serves as a base for removeOrdersFromUser and removeUsersFromOrder.
         *  De facto, the other user/order removal functions are wrappers for this one.
         *  You can understand the parameters and return codes of this function if you read the docs of the other removal
         *  functions.
         */
        protected function removeUsersOrders(int $mainID, array $targetIDs, bool $mainUser, array $params){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            $prefix = $this->SQLHandler->getSQLPrefix();
            $conds = ($mainUser)?
                [
                    [
                        'User_ID',
                        $mainID,
                        '='
                    ],
                    [
                        'Order_ID',
                        array_merge($targetIDs,['CSV']),
                        'IN'
                    ],
                    'AND'
                ]
                :
                [
                    [
                        'Order_ID',
                        $mainID,
                        '='
                    ],
                    [
                        'User_ID',
                        array_merge($targetIDs,['CSV']),
                        'IN'
                    ],
                    'AND'
                ]
            ;

            //Delete stuff
            $res = $this->SQLHandler->deleteFromTable(
                $prefix.$this->relationTableName,
                $conds,
                array_merge($params,['onDuplicateKey'=>true])
            );

            //If deletion failed, return
            if($res === false)
                return -1;
            //Else remove affected orders from the cache
            else{
                $affectedOrders = [];

                if(!$mainUser)
                    $affectedOrders = [$this->relationCacheName.$mainID];
                else
                    foreach($targetIDs as $targetID)
                        array_push($affectedOrders, $this->relationCacheName.$targetID);

                if($verbose)
                    echo 'Deleting order cache '.json_encode($affectedOrders).EOL;
                if(!$test)
                    $this->RedisHandler->call('del',[$affectedOrders]);
            }

            return 0;
        }

        /** Gets all the orders of a single user. SLOWER than getOrderUsers, as it cannot use caching.
         * @param int $userID ID of the user.
         * @param array $params getFromCacheOrDB() params, as well as:
         *          'orderIDs'            - int[], default [] - if not empty, will only get items with those ordersIDs.
         *          'getLimitedInfo'      - bool, default false -  Will only return Order_ID, Relation_Type, Created and Last_Updated columns
         *          'returnOrders'        - bool, default false - Returns all orders that belong to the user using
         *                                getOrders() with 'getLimitedInfo' param. Dumps each ORDERS result into a
         *                                reserved 'Orders_Info' column in the order array for the relevant order.
         *          'relationType'        - string, default null - if set, will only return results where Relation_Type
         *                                is EXACTLY like this param.
         *          'relationTypeLike'    - string, default null, overridden by relationType - if set and not overridden,
         *                                will only return results where Relation_Type is LIKE (RLIKE, Regex Like) this param.
         *          'createdAfter'        - same as in getOrders() but applies to user<=>order relationships
         *          'createdBefore'       - same as in getOrders() but applies to user<=>order relationships
         *          'changedAfter'        - same as in getOrders() but applies to user<=>order relationships
         *          'changedBefore'       - same as in getOrders() but applies to user<=>order relationships
         *          'extraDBFilters'      - same as in getOrders() but applies to $userOrderColumnNames instead of the order ones.
         *          'orderBy'             - string, defaults to null. Possible values include 'Created' 'Last_Changed',
         *                                  and any of the names in $userOrderColumnNames
         *          'orderType'           - bool, defaults to null.  0 for 'ASC', 1 for 'DESC'
         *          'limit'               - string, SQL LIMIT, defaults to system default
         *          'offset'              - string, SQL OFFSET
         *
         * @return array of the form:
         *          if 'returnOrders' is false:
         *          [
         *              <orderID> => <Array of USERS_ORDERS columns> OR 1 if 'orderIDs' was not empty and some orders didn't exist
         *          ]
         *          if 'returnOrders' is true:
         *          [
         *              <orderID> => <Array of USERS_ORDERS columns merged with ORDERS information (or 1 if the order no
         *                            longer exists) in the column Order_Info>, OR 1 if 'orderIDs' was not empty and some orders didn't exist
         *          ]
         */
        function getUserOrders(int $userID, array $params = []){
            return $this->getOrdersUsers($userID, true, $params);
        }

        /** Gets all the users of a single order.
         * @param int $orderID ID of the order.
         * @param array $params similar to getUserOrders
         *
         * @return int[] | int of the form:
         *          [
         *              <userID> => <Array of USERS_ORDERS columns>
         *          ]
         *         OR
         *          1 if not a single relation exists
         */
        function getOrderUsers(int $orderID, array $params = []){
            return $this->getOrdersUsers($orderID, false, $params)[$orderID]??[];
        }


        /** Assigns an order to a specific user.
         * @param int $userID ID of the user.
         * @param int $orderID ID of the order.
         * @param array $inputs of the form:
         *          'relationType'      - string, default null - if set, will set the Relation_Type to the string.
         *          'meta'              - string, default null - if set, will set the Meta to the string.
         *                                                       SHOULD be a JSON encoded object.
         *          <Any additional column name in $orderColumnNames converted from Underscore_Case to camelCase>
         * @param array $params
         *          'existing' => Array, default null - array of getUserOrders($orderID) in case we got it earlier
         * @return int Code:
         *         -1 - failed to reach DB
         *          0 - All is well
         *          1 - Assignment already exists (update it instead)
         *          2 - Order does not exist
         *          3 - User does not exist
         * */
        function assignOrderToUser(int $userID, int $orderID, array $inputs = [],array $params = []){
            return $this->assignOrdersToUser($userID,[$orderID,$inputs],$params)[$orderID];
        }

        /** Assigns multiple orders to a single user.
         * @param int $userID ID of the user.
         * @param array $inputs of the form:
         *          [<OrderID>,<$inputs like in assignOrderToUser()>]
         * @param array $params
         *          'existing' => Array, default null - array of getUserOrders($userID) in case we got it earlier
         * @return array of the form:
         *              [
         *                  <orderID> => <code>
         *              ]
         *          Where the codes are the same as assignOrderToUser()
         * */
        function assignOrdersToUser(int $userID, array $inputs, array $params = []){
            return $this->setUsersOrders($userID, $inputs, true , true, $params);
        }


        /** Assigns a user to a specific order.
         * @param int $userID ID of the user.
         * @param int $orderID ID of the order.
         * @param array $inputs of the form:
         *          'relationType'      - string, default null - if set, will set the Relation_Type to the string.
         *          'meta'              - string, default null - if set, will set the Meta to the value.
         *                                                       SHOULD be a JSON encoded object.
         *          <Any additional column name in $orderColumnNames converted from Underscore_Case to camelCase>
         * @param array $params
         *          'existing' => Array, default null - array of getOrderUsers($orderID) in case we got it earlier
         * @return int Code:
         *         -1 - failed to reach DB
         *          0 - All is well
         *          1 - Assignment already exists (update it instead)
         *          2 - Order does not exist
         *          3 - User does not exist
         * */
        function assignUserToOrder(int $orderID, int $userID, array $inputs = [],array $params = []){
            return $this->assignUsersToOrder($orderID,[[$userID,$inputs]],$params)[$userID];
        }

        /** Assigns an order to specific users. While generally one-to-many, in some cases you may want to assign an order
         *  to more than one user.
         * @param int $orderID ID of the order.
         * @param array $inputs of the form:
         *          [<UserID>,<$inputs like in assignOrderToUser()>]
         * @param array $params
         *          'existing' => Array, default null - array of getOrderUsers($orderID) in case we got it earlier
         * @return array of the form:
         *              [
         *                  <userID> => <code>
         *              ]
         *          Where the codes are the same as assignUserToOrder()
         * */
        function assignUsersToOrder(int $orderID, array $inputs, array $params = []){
            return $this->setUsersOrders($orderID, $inputs, false , true, $params);
        }

        /** Removes an order from a user. Remember that order information such as order creator (which might be this same user)
         *  is not affected, but there might be important information stored in the relation info.
         *
         * @param int $userID ID of the user.
         * @param int $orderID ID of the order.
         * @param array $params
         *          'existing' => Array, default null - array of getUserOrders($orderID) in case we got it earlier
         * @return int Code:
         *          -1 - failed to reach DB
         *           0 - Relations removed successfully.
         * */
        function removeOrderFromUser(int $userID, int $orderID, array $params = []){
            return $this->removeOrdersFromUser($userID,[$orderID],$params);
        }

        /** Removes multiple orders from a single user.
         *
         * @param int $userID ID of the user.
         * @param int[] $orderIDs IDs of the orders.
         * @param array $params
         *          'existing' => Array, default null - array of getUserOrders($userID) in case we got it earlier
         * @return int Code:
         *          -1 - failed to reach DB
         *           0 - Relations removed successfully.
         * */
        function removeOrdersFromUser(int $userID, array $orderIDs, array $params = []){
            return $this->removeUsersOrders($userID, $orderIDs, true , $params);
        }

        /** Removes a single user from an order .
         *
         * @param int $orderID ID of the order.
         * @param int $userID ID of the user.
         * @param array $params
         *          'existing' => Array, default null - array of getOrderUsers($orderID) in case we got it earlier
         * @return int Code:
         *          -1 - failed to reach DB
         *           0 - Relations removed successfully.
         * */
        function removeUserFromOrder(int $orderID, int $userID, array $params = []){
            return $this->removeUsersFromOrder($orderID,[$userID],$params);
        }

        /** Removes multiple users from a single order .
         *
         * @param int $orderID ID of the order.
         * @param int[] $userIDs IDs of the users.
         * @param array $params
         *          'existing' => Array, default null - array of getOrderUsers($orderID) in case we got it earlier
         * @return int Code:
         *          -1 - failed to reach DB
         *           0 - Relations removed successfully.
         * */
        function removeUsersFromOrder(int $orderID, array $userIDs, array $params = []){
            return $this->removeUsersOrders($orderID, $userIDs, false , $params);
        }

        /** Modifies a user-order assignment meta information
         * @param int $userID User identifier
         * @param int $orderID Orders ID
         * @param array $inputs Inputs of the form:
         *                      'relationType'      - string, default null - Type of relation between the user and the order (e.g 'seller')
         *                      'meta'              - string, default null - if set, will set the Meta to the string.
         *                                            SHOULD be a JSON encoded object.
         *                      <Any additional column name in $userOrderColumnNames converted from Underscore_Case to camelCase>
         * @param array $params same as updateUserOrdersAssignment()
         * @return int code:
         *          -1 could not reach the db (for all the )
         *          0 - All is well
         *          1 - Assignment does not exist (create it instead)
         *          2 - Order does not exist
         *          3 - User does not exist
         *
         * */
        function updateOrderUserAssignment(int $userID, int $orderID, array $inputs, array $params = []){
            return $this->updateOrderUsersAssignment($userID,[[$orderID,$inputs]],$params)[$orderID];
        }

        /** Modifies a user-orders assignment meta information
         * @param int $userID User identifier
         * @param array $inputs
         *          [<OrderID>,<$inputs like in updateUserOrderAssignment()>]
         * @param array $inputs Inputs from updateUserOrdersAssignment - will affect all assignments
         * @param array $params
         *          'existing' - Array default null - the DB object array, like one from getUserOrders() - if not null,
         *                       must have all the additional columns from $userOrderColumnNames.
         * @return int[] Array of the form:
         *          {
         *             <orderID> => <code>
         *          }
         *          where the codes are from updateUserOrderAssignment
         *
         * */
        function updateUserOrdersAssignment(int $userID, array $inputs, array $params = []){
            return $this->setUsersOrders($userID,$inputs,true,false,$params);
        }

        /** Modifies a order-users assignment meta information
         * @param int $orderID Order identifier
         * @param  array $inputs
         *          [<OrderID>,<$inputs like in updateUserOrderAssignment()>]
         * @param array $inputs Inputs from updateUserOrdersAssignment - will affect all assignments
         * @param array $params
         *          'existing' - Array default null - the DB object array, like one from getOrderUsers() - if not null,
         *                       must have all the additional columns from $userOrderColumnNames.
         * @return int[] Array of the form:
         *          {
         *              <userID> => <code>
         *          }
         *          where the codes are from updateUserOrderAssignment
         *
         * */
        function updateOrderUsersAssignment(int $orderID, array $inputs, array $params = []){
            return $this->setUsersOrders($orderID,$inputs,false,false,$params);
        }

    }
}