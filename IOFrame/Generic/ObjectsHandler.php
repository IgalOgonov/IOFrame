<?php
namespace IOFrame\Generic{

    /** Most Handlers in IOFrame follow a specific pattern.
     * This abstract class comes to provide a common set of actions, that have no reason to be implemented on their own every time.
     * It is meant to be expanded upon by its child classes.
     * Note that in some cases, it'd still be better to write a new class from scratch than to extend this.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class ObjectsHandler extends \IOFrame\Abstract\DBWithCache{

        /** @var array $validObjectTypes Array of valid object types - e.g. ['test','test2',...]
         * To be set by the inheriting child
         * */
        protected array $validObjectTypes = [];

        /** @var array $objectsDetails Table of details for each valid object type details,
         *              where each key is a valid objet type, and each child is an array of the following form:
         *                  [
         *                      'tableName' => string - the name of the objects table
         *                      'joinOnGet' => array, defaults to [] - relevant if the object should be joined with other tables on get.
         *                                             Of the form:
         *                                              [
         *                                                  [
         *                                                      'expression' => <string, allows passing a custom expression ignoring all below>
         *                                                      'leftTableName'=> <string, by default the name of this table (WITHOUT PREFIX)>
         *                                                      'tableName'=> <string|string[2], name of the table to join (WITHOUT PREFIX), or [<string, table name>, <string, alias>]>
         *                                                      'join' => <string, defaults to 'LEFT JOIN' - can be any valid SQL join.>,
         *                                                      'condition' => <string, defaults to '=' - can also be '!=', '>', '<' and similar>,
         *                                                      'on' => <string, array of strings OR pairs the form [[column_a, column_b],...] where:
         *                                                              Each pair represents the column name in the main table (a), and foreign table (b).
         *                                                              Each value inside the pair can also be passed as ["value","STRING"] to indicate it
         *                                                              should not have a table name prefixed to it but just enclosed in ",
         *                                                              or ["value","ASIS"] to not do anything with it.
         *                                                              Each string represents an expression passed as-is.>
         *                                                  ]
         *                                              ]
         *                      'columnsToGet' => array, defaults to [] - in case we are joining other stuff, we usually want to get specific columns.
         *                                               Array of the form:
         *                                              [
         *                                                  [
         *                                                      'expression' => <string, if true simply uses this expression>
         *                                                      'tableName'=> <string, name of the table (WITHOUT PREFIX)>
         *                                                      'alias' => <bool, if true will not prepend the table prefix automatically>
         *                                                      'column' => <string, name of the column ("*" for "all columns of this table")>
         *                                                      'as' => <string, if set returns this column under this name>
         *                                                  ]
         *                                              ]
         *                      '_uniqueLogger' => string, default null. If set, the item uses this as its own log channel
         *                      'useCache'=> bool, default true - if set to false, specific object will not use cache
         *                      'cacheName'=> string - cache prefix
         *                      'allItemsCacheName'=> string - if set, will use this name to to cache all results when searching without filters.
         *                                                     DO NOT set for objects where total amount of results is too big to be cached.
         *                                                     Also, DO NOT set for items that have fathers (as those are cached per father object)
         *                      'extendTTL'=> bool, whether to extend item TTL in cache
         *                      'cacheTTL'=> int, custom cache ttl - defaults to Abstract/DBWithCache value
         *                      'childCache'=> string[] - when deleting an item that has sub items, will delete the cache
         *                                                of all its children - identified by the cache names in this array.
         *                                                only works correctly when an item has a single sub-item layer.
         *                      'fatherDetails'=> associative array of assoc arrays the form:
         *                                              [
         *                                                  [
         *                                                      'tableName'=> <string, name of the father table (WITHOUT PREFIX)>
         *                                                      'cacheName' => <string, name of the father cache prefix >
         *                                                  ],
         *                                                  'updateColumn' => <string, default 'Last_Updated' - name of
         *                                                                    the father table column that represents when it was last updated>
         *                                                  'minKeyNum' =>  <int, number of keys of the top-most father>, defaults to 1;
         *                                              ]
         *                                              Used on sub-item tables (those with extraKeyColumns and groupByFirstNKeys).
         *                                              If cacheName is set, will delete the parent cache upon deletion / update.
         *                                              If tableName and updateColumn are set, will update all parents'
         *                                              "last updated" date to the current one (which changes their state - hense, cache deletion).
         *                                              This structure assumes each subsequent father has 1 additional key
         *                                              compared to the one above it.
         *                      'keyColumns' => string[] - key column names.
         *                      'extraKeyColumns' => Columns which are key, but should not be queried (keys in one-to-many relationships, like one user having many actions).
         *                      'safeStrColumns' => Columns that need to be converted from/to safeStr. Affects both gets and sets.
         *                      'setColumns' => array of objects, where each object is of the form:
         *                                      <column name> => [
         *                                          'type' => 'int'/'string'/'bool'/'double' - type of the column for purposes of the SQL query,
         *                                          'default' =>  any possible value including null - if not set when
         *                                                        creating new objects, will return an error.
         *                                          'forceValue' => if set, this value will always be inserted regardless of
         *                                                          user input
         *                                          'jsonObject' => bool, default false - if set and true, will treat the field
         *                                                         as a JSON
         *                                          'autoIncrement' => bool, if set, indicates that this column auto-increments (doesn't need to be set on creation)
         *                                          'considerNull' => mixed, if passed, this value will be considered "NULL" (since we cannot pass an actual NULL value sometimes, like through the API)
         *                                          'onDuplicateKeyColExp' => string, default null - if set, will set the relevant expression for the column.
         *                                                                    More info in the SQLManager class.
         *                                          'function' => callable, default null - if set, will set the value of the item to the result of this function.
         *                                                                                 The function receives 1 item as input, an associated array of the form:
         *                                                                                 [
         *                                                                                  'typeArray' => The objects details for the relevant item type.
         *                                                                                  'params' => The parameters passed to the main set function
         *                                                                                  'inputArray' => The user inputs for this specific object
         *                                                                                  ['existingArray'] => The exising database object (when updating)
         *                                                                                 ]
         *                                      ]
         *                      'moveColumns' => array of objects, where each object is of the form:
         *                                      <column name> => [
         *                                          'type' => 'int'/'string'/'bool'/'double' - type of the column for purposes of the SQL query,
         *                                          'inputName' => string, name of the input key relevant to this, defaults to column name
         *                                      ]
         *                                      * note that this can also be used to rename objects, not just move them.
         *                      'lockColumns' => Assoc Array, if both "lock" and "timestamp" are sot, will try to lock relevant columns on update - key column names.
         *                                      [
         *                                          'lock' => string, name of columns where the lock should be
         *                                          'timestamp' => string, name of columns where the lock timestamp should be
         *                                          'retryAttempts' => int, default 5. How many times to try to attempt to get ownership of a locked item.
         *                                          'retryInterval' => int, default 1000. Retry interval (in milliseconds) after each attempt to get ownership of a locked item.
         *                                          'lockLength' => int, default 128. Length of auto-generated lock, in bytes.
         *                                          'timeout' => int, default null. Timeout (in seconds) after which, if it was locked before, the locked row may be considered wrongfully locked.
         *                                                       setting this to any number may result in breaking errors.
         *                                      ],
         *                      'defaultTableToFilterColumns' - bool, default false. if set prepends table name to filter columns without an explicit 'tableName'
         *                      'columnFilters' => object of objects, where each object is of the form:
         *                                      <filter name> => [
         *                                          'column' => string|array, name of relevant column, or array of columns to search for a value "IN"
         *                                          'tableName' => string|bool, if set to a string, prepends this table name to the column AS IS.
         *                                                                      if set to true/1, prepends the default table name with the prefix to the column.
         *                                                                      if set to false/0, will override 'defaultTableToFilterColumns' and not prefix anything
         *                                          'filter' => string, one of the filters from the abstract class Abstract/DBWithCache:
         *                                                      '>','<','=', '!=', 'IN', 'RLIKE' and 'NOT RLIKE'
         *                                                      In case of multiple columns, this is ignored.
         *                                          'default' => if set, will be the default value for this filter
         *                                          'considerNull' => mixed, if passed, this value will be considered "NULL" (since we cannot pass an actual NULL value sometimes, like through the API)
         *                                          'alwaysSend' => if set to true, will always send the filter. Has to have 'default'
         *                                          'function' => callable - if set, add this expression to the conditions.
         *                                                        Keep in mind that using even one filter with a function DISABLES CACHE.
         *                                                                                 The function receives 1 item as input, an associated array of the form:
         *                                                                                 [
         *                                                                                  'filterName' => Name of the filter parameter (also how it should appear in "params")
         *                                                                                  'SQLManager' => SQLManager from this class
         *                                                                                  'typeArray' => The objects details for the relevant item type.
         *                                                                                  'params' => The parameters passed to the main set function
         *                                                                                 ]
         *                                      ],
         *                      'extraToGet' => object of objects, extra meta-data to get when getting multiple items,
         *                                      where each object is of the form:
         *                                      <column name> => [
         *                                          'key' => string, key under which the results will be added to '@'
         *                                          'differentColName' => string, different column name
         *                                          'type' => string, either 'min'/'max' (range values), 'sum', 'count' (the key doesn't matter here),
         *                                                    'distinct' (get all distinct values), 'distinct_multiple' (get distinct values from multiple columns),
         *                                                    'count_interval' (counts how many rows exist, grouped by intervals, usually via a time column)
         *                                          'intervals' => int, default 1 - in case of count_interval, column value is divided (floored) by this.
         *                                          'columns' => array of strings, if the type is 'distinct_multiple', here you specify all relevant columns to get values from
         *                                          'function' => callable, default null - if set, will add the result of this function - a string, that is a select query - to the result via UNION.
         *                                                                                 The function receives 1 item as input, an associated array of the form:
         *                                                                                 [
         *                                                                                  'typeArray' => The exising object type array
         *                                                                                  'params' => The parameters passed to the main set function
         *                                                                                  'extraDBConditions' => The extra DB conditions that the main items had
         *                                                                                  'tableQuery' => The string that represents the table/tables to get
         *                                                                                 ]
         *                                      ],
         *                      'orderColumns' => array of column names by which it is possible to order the query.
         *                      'groupByFirstNKeys' => int, default 0 - whether to group results by the first n keys (less than the total number of keys).
         *                                             Relevant for one-to-many tables where the first N keys represent a parent, and the last one - a child.
         *                      'hasTimeColumns' => bool, default true - whether the table contains Created / Last_Updated unix timestamp columns
         *                      'autoIncrement' => bool, default false - whether the main identifier auto-increments
         *                  ]
         * */
        protected array $objectsDetails =[
            /** Example :
             *  'objectUsers' => [
             *      'tableName' => 'OBJECT_AUTH_OBJECT_USERS',
             *      'cacheName'=> 'object_auth_object_user_actions_',
             *      'keyColumns' => ['Object_Auth_Category','Object_Auth_Object','ID'],
             *      'extraKeyColumns' => ['Object_Auth_Action'],
             *      'setColumns' => [
             *          'Object_Auth_Category' => [
             *              'type' => 'int',
             *              'required' => true
             *          ],
             *          'Object_Auth_Object' => [
             *              'type' => 'string',
             *              'required' => true
             *          ],
             *          'ID' => [
             *              'type' => 'int',
             *              'required' => true
             *          ],
             *          'Object_Auth_Action' => [
             *              'type' => 'string',
             *              'required' => true
             *          ]
             *      ],
             *      'moveColumns' => [
             *          'Object_Auth_Object' => [
             *              'type' => 'string',
             *              'inputName' => 'New_Object'
             *          ],
             *      ],
             *      'columnFilters' => [
             *          'categoryIs' => [
             *              'column' => 'Object_Auth_Category',
             *              'filter' => '='
             *          ],
             *          'categoryIn' => [
             *              'column' => 'Object_Auth_Category',
             *              'filter' => 'IN'
             *          ],
             *          'objectLike' => [
             *              'column' => 'Object_Auth_Object',
             *              'filter' => 'RLIKE'
             *          ],
             *          'objectIn' => [
             *              'column' => 'Object_Auth_Object',
             *              'filter' => 'IN'
             *          ],
             *          'userIDIs' => [
             *              'column' => 'ID',
             *              'filter' => '='
             *          ],
             *          'userIDIn' => [
             *              'column' => 'ID',
             *              'filter' => 'IN'
             *          ],
             *          'actionLike' => [
             *              'column' => 'Object_Auth_Action',
             *              'filter' => 'RLIKE'
             *          ],
             *          'actionIn' => [
             *              'column' => 'Object_Auth_Action',
             *              'filter' => 'IN'
             *          ],
             *      ],
             *      'extraToGet' => [
             *          '#' => [
             *              'key' => '#',
             *              'type' => 'count'
             *          ],
             *          'Object_Auth_Category' => [
             *              'key' => 'categories',
             *              'type' => 'distinct'
             *          ],
             *          'Object_Auth_Object' => [
             *              'key' => 'objects',
             *              'type' => 'distinct'
             *          ],
             *          'exampleDistinctAreas' => [
             *              'key' => 'areas',
             *              'type' => 'distinct_multiple',
             *              'columns' => ['Area_1','Area_2','Area_3']
             *          ],
             *      ],
             *      'orderColumns' => ['Object_Auth_Category','Object_Auth_Object','ID','Object_Auth_Action'],
             *      'groupByFirstNKeys'=>3,
             *  ]
             *
             *
             *
             *
             */
        ];

        /* common columns for all tables - generally it's those two, but in some cases not every table has 'created'/'updated' set*/
        protected array $timeColumns=[ 'Created' , 'Last_Updated' ];

        /* common filters for all tables - dependant on timeColumns*/
        protected array $timeFilters=[
            'createdBefore' => [
                'tableName'=> true,
                'column' => 'Created',
                'filter' => '<'
            ],
            'createdAfter' => [
                'tableName'=> true,
                'column' => 'Created',
                'filter' => '>'
            ],
            'changedBefore' => [
                'tableName'=> true,
                'column' => 'Last_Updated',
                'filter' => '<'
            ],
            'changedAfter' => [
                'tableName'=> true,
                'column' => 'Last_Updated',
                'filter' => '>'
            ],
        ];

        /* common order columns for all tables - defaults to $commonColumns*/
        protected array $timeOrderColumns= [ 'Created' , 'Last_Updated' ];

        /**
         * Basic construction function
         * @param \IOFrame\Handlers\SettingsHandler $settings local settings handler.
         * @param array $params Typical default settings array
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, $params = []){

            /* Allows dynamically setting table details at construction.
             * As much as I hate variable variables, this is likely one of the few places where their use is for the best.
             * */
            $dynamicParams = $this->validObjectTypes;
            $additionParams = ['timeFilters','timeColumns','timeOrderColumns'];

            foreach($dynamicParams as $param){
                foreach($this->objectsDetails[$param] as $index => $defaultValue){
                    if(
                        isset($params[$param][$index]) &&
                        (!isset($this->objectsDetails[$param][$index]) || gettype($params[$param][$index]) === gettype($this->objectsDetails[$param][$index]))
                    )
                        $this->objectsDetails[$param][$index] = $params[$param][$index];
                    if(($index === '_uniqueLogger') && is_string($defaultValue) && !empty($defaultValue)){
                        $this->objectsDetails[$param][$index] = new \Monolog\Logger($defaultValue);
                        $handler = $params['logHandler'] ?? new \IOFrame\Managers\Integrations\Monolog\IOFrameHandler($settings);
                        if(!($params['disableLogging']??false))
                            $this->objectsDetails[$param][$index]->pushHandler($handler);
                    }
                }
            }

            foreach($additionParams as $param){
                if(!isset($params[$param]))
                    continue;
                else
                    $this->$param = array_merge($this->$param , $params[$param]);
            }

            parent::__construct($settings,$params);
        }

        /** Gets the current logger that should be used by an item of a specific type
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         *
         * @throws \Exception
         * @throws \Exception
         */
        function getLogger(string $type){
            if(!in_array($type,$this->validObjectTypes))
                throw new \Exception('Invalid object type!');
            if( empty($this->objectsDetails[$type]['_uniqueLogger']) || (get_class($this->objectsDetails[$type]['_uniqueLogger']) !== 'Monolog\Logger') )
                return $this->logger;
            else
                return $this->objectsDetails[$type]['_uniqueLogger'];
        }

        /** Updates father tables of child tables that were changed
         * @param array $childInputsArray Array of the child keys
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         * @param array $params
         *                'disableLogging' - bool, default false. Disables logging for this operation
         * @returns bool success on db update, failure otherwise
         * @throws \Exception If the item type is invalid
         */
        function updateFathers(array $childInputsArray, string $type, array $params){

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $disableLogging = $params['disableLogging'] ?? null;
            $fatherDetails = $typeArray['fatherDetails'] ?? [];

            $updateColumn = !empty($fatherDetails['updateColumn'])? $fatherDetails['updateColumn'] : 'Last_Updated';
            if(isset($fatherDetails['updateColumn']))
                unset($fatherDetails['updateColumn']);

            $minKeyNum  = !empty($fatherDetails['minKeyNum'])? $fatherDetails['minKeyNum'] : 1;

            if(isset($fatherDetails['minKeyNum']))
                unset($fatherDetails['minKeyNum']);

            $keyColumns = $typeArray['keyColumns'];

            if(empty($fatherDetails))
                return true;
            else
                ksort($fatherDetails);

            $keyNumDelta = $minKeyNum-1;
            $affectedCacheIdentifiers = [];
            $updateMap = [];
            $tableQuery = '';
            $updateArray = [];
            $prefix = $this->SQLManager->getSQLPrefix();
            $numberOfFathers = count($fatherDetails);
            //The columns for our condition
            $tableColumns = [];
            for($i = 0; $i<$keyNumDelta; $i++){
                $tableColumns[] = $prefix . $fatherDetails[0]['tableName'] . '.' . $keyColumns[$i];
            }
            for($i = $keyNumDelta; $i<count($fatherDetails) + $keyNumDelta; $i++){
                $tableColumns[] = $prefix . $fatherDetails[$i - $keyNumDelta]['tableName'] . '.' . $keyColumns[$i];
            }
            $tableColumns[] = 'CSV';

            //Set the table columns and get the cache identifiers at the ame time
            $tableKeyConds = [];
            foreach($childInputsArray as $input){
                $oneCond = [];
                $keysSoFar = [];

                for($i = 0; $i<$keyNumDelta; $i++){
                    $oneCond[] = $input[$keyColumns[$i]];
                    $keysSoFar[] = $input[$keyColumns[$i]];
                }

                for($i = $keyNumDelta; $i<$numberOfFathers+$keyNumDelta; $i++){
                    $oneCond[] = $input[$keyColumns[$i]];
                    //Here we take care of the cache identifiers
                    $keysSoFar[] = $input[$keyColumns[$i]];
                    if(!empty($fatherDetails[$i-$keyNumDelta]['cacheName'])){
                        $cacheItem = $fatherDetails[$i-$keyNumDelta]['cacheName'].implode('/',$keysSoFar);
                        //Add any cache item not yet in the cache
                        if(!in_array($cacheItem,$affectedCacheIdentifiers))
                            $affectedCacheIdentifiers[] = $cacheItem;
                    }
                }

                if(!isset($updateMap[implode('/',$oneCond)])){
                    $updateMap[implode('/',$oneCond)] = true;
                    foreach($oneCond as $index => $val){
                        if(gettype($val) === 'string')
                            $oneCond[$index] = [$val,'STRING'];
                    }
                    $oneCond[] = 'CSV';
                    $tableKeyConds[] = $oneCond;
                }
            }
            $tableKeyConds[] = 'CSV';

            //Merge everything
            $tableCond = [
                $tableColumns,
                $tableKeyConds,
                'IN'
            ];

            //Now, for each parent after the first one, we need to do some joins
            foreach($fatherDetails as $index => $fatherDetailsArray){

                $tableQuery .= $prefix.$fatherDetailsArray['tableName'];

                $updateArray[] = $prefix . $fatherDetailsArray['tableName'] . '.' . $updateColumn . ' = ' . time();

                if($index > 0){
                    $tableQuery .=' ON ';
                    for($i = 0; $i<$keyNumDelta; $i++){
                        $tableQuery .= $prefix.$fatherDetails[$i]['tableName'].'.'.$keyColumns[$i].' = '.$prefix.$fatherDetailsArray['tableName'].'.'.$keyColumns[$i].' AND ';
                    }
                    for($i = $keyNumDelta; $i<$index+$keyNumDelta; $i++){
                        $tableQuery .= $prefix.$fatherDetails[$i-$keyNumDelta]['tableName'].'.'.$keyColumns[$i].' = '.$prefix.$fatherDetailsArray['tableName'].'.'.$keyColumns[$i].' AND ';
                    }
                    $tableQuery = substr($tableQuery,0,strlen($tableQuery)-5);
                }
                $tableQuery .= ' INNER JOIN ';
            }
            $tableQuery = substr($tableQuery,0,strlen($tableQuery)-12);

            $res = $this->SQLManager->updateTable(
                $tableQuery,
                $updateArray,
                $tableCond,
                $params
            );

            if($res === true && !empty($affectedCacheIdentifiers)){
                if($verbose)
                    echo 'Deleting cache of '.$type.' parents: '.json_encode($affectedCacheIdentifiers).EOL;
                if(!$test)
                    $this->RedisManager->call( 'del', [$affectedCacheIdentifiers] );
            }
            elseif($res !== true){
                if(!$disableLogging)
                    $this->getLogger($type)->error('Failed to update '.$type.' fathers',['updateArray'=>$updateArray,'cond'=>$tableCond]);
            }

            return $res;
        }

        /** Tries to get a lock on items.
         * @param int[]|int[][] $itemIDs Array of arrays, representing item IDs (can be length of 1, if just 1 column is the ID)
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         * @param array $params
         *                 'disableLogging' - bool, default false. Disables logging for this operation
         *                  'lock' => string, whether you want to use a specific lock - just make sure it is secure
         * @return bool|string Lock used, or false if failed to reach DB
         *
         * @throws \Exception
         * @throws \Exception
         */
        function lockItems(array $itemIDs, string $type, array $params = []): bool|string {

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $disableLogging = $params['disableLogging'] ?? null;
            $lock = $params['lock'] ?? false;
            $keyColumns = $typeArray['keyColumns'];
            $lockColumns = $typeArray['lockColumns'] ?? (
                $test ? [
                        'lock'=>'Test_Lock',
                        'timestamp'=>'Test_Locked_At'
                    ]
                    :
                    []
                );

            if(!($lockColumns['lock'] ?? false) || !($lockColumns['timestamp'] ?? false))
                throw new \Exception('Trying to lock items without');

            if( (count($itemIDs)<1) || count($itemIDs[0])<1)
                throw new \Exception('Invalid IDs');

            $lockColumns['retryInterval'] = $params['retryInterval'] ?? ($lockColumns['retryInterval'] ?? 1000);
            $lockColumns['lockLength'] = $params['lockLength'] ?? ($lockColumns['lockLength'] ?? 128);
            $lockColumns['timeout'] = $params['timeout'] ?? ($lockColumns['timeout'] ?? 30);

            if(!$lock){
                $hex_secure = false;
                while(!$hex_secure)
                    $lock=bin2hex(openssl_random_pseudo_bytes($lockColumns['lockLength'],$hex_secure));
            }

            foreach($itemIDs as $index=>$ids){
                foreach($ids as $index2=>$id){
                    $itemIDs[$index][$index2] = [
                        $id,
                        'STRING'
                    ];
                }
                $itemIDs[$index][] = 'CSV';
            }
            $itemIDs[] = 'CSV';
            $keyColumns[] = 'CSV';
            $replaceConds = [
                $lockColumns['lock'],
                'ISNULL'
            ];
            if($lockColumns['timeout']){
                $replaceConds = [
                    $replaceConds,
                    [
                        $lockColumns['timestamp'],
                        time()+$lockColumns['timeout'],
                        '<'
                    ],
                    'OR'
                ];
            }

            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix() . $typeArray['tableName'],
                ['Session_Lock = "'.$lock.'", Locked_At = "'.time().'"'],
                [
                    [
                        $keyColumns,
                        $itemIDs,
                        'IN'
                    ],
                    $replaceConds,
                    'AND'
                ],
                ['test'=>$test,'verbose'=>$verbose]
            );
            if(!$res){
                if($verbose)
                    echo 'Failed to lock items '.json_encode($itemIDs).'!'.EOL;
                if(!$disableLogging)
                    $this->getLogger($type)->warning('Failed to lock '.$type.' items',['ids'=>$itemIDs]);
                return false;
            }
            else{
                if($verbose)
                    echo 'Items '.json_encode($itemIDs).' locked with lock '.$lock.EOL;
                return $lock;
            }
        }


        /** Unlocks items, either locked with a specific session lock or locked with any lock.
         * @param string[]|string[][] $itemIDs Array of order identifiers
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         * @param array $params Parameters of the form:
         *              'disableLogging' - bool, default false. Disables logging for this operation
         *              'key' - string, default null - if not NULL, will only try to unlock items that
         *                      have a specific key. TODO Fix this - does not work properly with key for some reason
         * @return bool true if reached DB, false if didn't reach DB
         *
         *
         * @throws \Exception
         * @throws \Exception
         */
        function unlockItems(array $itemIDs, string $type, array $params = []): bool {

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $lock = $params['lock'] ?? false;
            $disableLogging = $params['disableLogging'] ?? null;
            $keyColumns = $typeArray['keyColumns'];
            $lockColumns = $typeArray['lockColumns'] ?? (
                $test ? [
                    'lock'=>'Test_Lock',
                    'timestamp'=>'Test_Locked_At'
                ]
                    :
                    []
                );

            if(!($lockColumns['lock'] ?? false) || !($lockColumns['timestamp'] ?? false))
                throw new \Exception('Trying to lock items without');

            if( (count($itemIDs)<1) || count($itemIDs[0])<1)
                throw new \Exception('Invalid IDs');

            foreach($itemIDs as $index=>$ids){
                foreach($ids as $index2=>$id){
                    $itemIDs[$index][$index2] = [
                        $id,
                        'STRING'
                    ];
                }
                if(count($itemIDs[$index]) > 1)
                    $itemIDs[$index][] = 'CSV';
            }
            if(count($itemIDs) > 1)
                $itemIDs[] = 'CSV';
            if(count($keyColumns) > 1)
                $keyColumns[] = 'CSV';
            $conds = [
                $keyColumns,
                $itemIDs,
                'IN'
            ];
            if($lock)
                $conds = [
                    $conds,
                    [
                        $lockColumns['lock'],
                        [$lock,'STRING'],
                        '='
                    ],
                    'AND'
                ];

            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix() . $typeArray['tableName'],
                ['Session_Lock = NULL, Locked_At = NULL'],
                $conds,
                ['test'=>$test,'verbose'=>$verbose]
            );
            if(!$res){
                if($verbose)
                    echo 'Failed to unlock items '.json_encode($itemIDs).'!'.EOL;
                if(!$disableLogging)
                    $this->getLogger($type)->warning('Failed to unlock '.$type.' items',['ids'=>$itemIDs]);
                return false;
            }
            else{
                if($verbose)
                    echo 'Items '.json_encode($itemIDs).' locked with lock '.$lock.EOL;
                return $lock;
            }
        }

        /** Get multiple items
         * @param array $items Array of objects (arrays). Each object needs to contain the keys from they type array's "keyColumns",
         *              each key pointing to the value of the desired item to get.
         *              Defaults to [], which searches through all available items and cannot use the cache.
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         * @param array $params of the form:
         *              <valid filter name> - valid filters are found in the type array's "columnFilters" - the param name
         *                                    is the same as the key.
         *              'getAllSubItems' - bool, default false. If true, will get all sub-items and ignore limit even if
         *                                 we are getting all items ($items is [])
         *              'cacheFullResultsCustomSuffix' - if you are getting a sub-set of all the items on an object with allItemsCacheName, but still
         *                                               want to cache it (despite the filters making $cacheFullResults false),
         *                                               use this to specify that you are in this specific sub-set.
         *              'ignoreSubItemCacheLimit' - bool, default false. If true, will cache any sub-items, otherwise will calculate
         *                                           a new limit of the size $this->maxCacheSize * <int, number of items>
         *              'replaceTypeArray' - object, default [] - If set, recursively merges with $this->objectsDetails[$type],
         *                                   potentially replacing things like primary key, expanding / reducing extraToGet, etc.
         *              'disableExtraToGet' - bool|string|array, default null -Extends 'replaceTypeArray' with specific extraToGet keys to disable.
         *                                    If true, disables all ExtraToGet. If string/array, disables those keys.
         *              --- Usage of the params below disables cache even when searching for specific items ---
         *              'limit' - int, standard SQL parameter - CAN BE ZERO to only get the extra data (e.g. count)
         *              'offset' - int, standard SQL parameter
         *              'orderBy' - string, default null - possible values found in the type array's "orderColumns"
         *              'orderType' - int, default null, possible values 0 and 1 - 0 is 'ASC', 1 is 'DESC'
         *
         * @return array of the form:
         *          [
         *              <identifier - string, all keys separated by "/"> =>
         *                  <DB array. If the type array had the "groupByFirstNKeys" param and we are getting specific items,
         *                   this will be an array of sub-items>,
         *                  OR
         *                  <code int - 1 if specific item that was requested is not found, -1 if there was a DB error>
         *          ]
         *          A DB error when $items is [] will result in an empty array returned, not an error.
         *
         * @throws \Exception If the item type is invalid
         *
         */
        function getItems(array $items, string $type, array $params = []): array {

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');

            $replaceTypeArray = $params['replaceTypeArray'] ?? [];

            $disableExtraToGet = $params['disableExtraToGet']?? false;

            if($disableExtraToGet){
                if($disableExtraToGet === true)
                    $replaceTypeArray['extraToGet'] = null;
                else{
                    $replaceTypeArray['extraToGet'] = $replaceTypeArray['extraToGet']??[];
                    if(!is_array($disableExtraToGet))
                        $disableExtraToGet = [$disableExtraToGet];
                    foreach ($disableExtraToGet as $disableColumn){
                        $replaceTypeArray['extraToGet'][$disableColumn] = null;
                    }
                }
            }

            if(!empty($replaceTypeArray) && is_array($replaceTypeArray))
                $typeArray = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($typeArray,$replaceTypeArray,['deleteOnNull'=>true]);

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $hasTimeColumns = $params['hasTimeColumns'] ?? ($this->objectsDetails[$type]['hasTimeColumns'] ?? true);
            $getAllSubItems = $params['getAllSubItems'] ?? false;
            if($getAllSubItems){
                $params['limit'] = null;
            }
            $limit = $params['limit'] ?? null;
            $offset = $params['offset'] ?? null;
            $orderBy = $params['orderBy'] ?? null;
            $orderType = $params['orderType'] ?? null;
            $keyColumns = $typeArray['keyColumns'];
            $useCache = $this->defaultSettingsParams['useCache'] && ($typeArray['useCache'] ?? !empty($typeArray['cacheName']));
            $extendTTL = $typeArray['extendTTL'] ?? false;
            $cacheTTL = $typeArray['cacheTTL'] ?? 3600;
            $safeStrColumns = $typeArray['safeStrColumns'] ?? [];
            $joinOnGet = $typeArray['joinOnGet'] ?? [];
            $columnsToGet = $typeArray['columnsToGet'] ?? [];
            $extraKeyColumns = $typeArray['extraKeyColumns'] ?? [];
            $groupByFirstNKeys = $typeArray['groupByFirstNKeys'] ?? 0;
            $defaultTableToFilterColumns = $typeArray['defaultTableToFilterColumns'] ?? false;
            $orderColumns = $typeArray['orderColumns'];
            $validFilters = $typeArray['columnFilters'];

            $cacheFullResults = $useCache && !empty($typeArray['allItemsCacheName']) && empty($limit) && empty($offset) && empty($groupByFirstNKeys) &&
                !(isset($params['allItemsCacheName']) && !$params['allItemsCacheName']);
            $cacheFullResultsCustomSuffix = $params['cacheFullResultsCustomSuffix'] ?? null;
            $ignoreSubItemCacheLimit = $params['ignoreSubItemCacheLimit'] ?? false;

            if($hasTimeColumns){
                $orderColumns = array_merge($this->timeOrderColumns,$orderColumns);
                $validFilters = array_merge($this->timeFilters,$validFilters);
            }

            if(!count($orderColumns)){
                $orderInputType = gettype($orderBy);
                switch($orderInputType){
                    case 'string':
                        if(!in_array($orderBy,$orderColumns))
                            $params['orderBy'] = null;
                        break;
                    case 'array':
                        $orderBy = array_intersect($orderBy,$orderColumns);
                        if(!count($orderBy))
                            $params['orderBy'] = null;
                        break;
                    default:
                        $params['orderBy'] = null;
                }
            }

            $retrieveParams = $params;
            $retrieveParams['type'] = $type;
            $retrieveParams['useCache'] = $useCache;
            $retrieveParams['extendTTL'] = $extendTTL;
            $retrieveParams['cacheTTL'] = $cacheTTL;

            //If we are using any of this functionality, we cannot use the cache
            if( $orderBy || $orderType || $offset || $limit){
                $retrieveParams['useCache'] = false;
                $retrieveParams['orderBy'] = $orderBy?: null;
                $retrieveParams['orderType'] = $orderType?: 0;
                $retrieveParams['limit'] =  $limit || ($limit === 0)? $limit : null;
                $retrieveParams['offset'] =  $offset?: null;
            }

            $extraDBConditions = [];
            $extraCacheConditions = [];

            foreach($validFilters as $filterParam => $filterArray){

                $cond = null;
                $cacheCond = null;

                if(!empty($filterArray['column'])){
                    if(is_array($filterArray['column']))
                        $filterArray['filter'] = null;

                    $prefix = isset($filterArray['tableName']) ?
                        (
                            is_string($filterArray['tableName'])?
                                $filterArray['tableName'] :
                                (
                                    !empty($filterArray['tableName']) ?
                                        ( $this->SQLManager->getSQLPrefix() . $typeArray['tableName'] ):
                                        null
                                )
                        ) :
                        (
                            $defaultTableToFilterColumns ?
                                ( $this->SQLManager->getSQLPrefix() . $typeArray['tableName'] ) :
                                null
                        ) ;
                    if($prefix){
                        if(is_array($filterArray['column'])){
                            foreach ($filterArray['column'] as $index => $val)
                                $filterArray['column'][$index] = $prefix.'.'.$filterArray['column'][$index];
                        }
                        else{
                            $filterArray['column'] = $prefix.'.'.$filterArray['column'];
                        }
                    }
                }

                if(!empty($filterArray['function'])){
                    $cond = $filterArray['function']([
                        'filterName'=>$filterParam,
                        'SQLManager'=>$this->SQLManager,
                        'typeArray'=>$typeArray,
                        'params'=>$params
                    ]);
                    if($cond)
                        $retrieveParams['useCache'] = false;
                }
                elseif(isset($params[$filterParam])){
                    if(isset($filterArray['considerNull']) && ($params[$filterParam] === $filterArray['considerNull']) ){
                        $params[$filterParam] = null;
                    }
                    elseif(gettype($params[$filterParam]) === 'array'){
                        foreach($params[$filterParam] as $key => $value){
                            $params[$filterParam][$key] = [$value,'STRING'];
                        }
                    }
                    //Edge cases
                    if($filterArray['filter'] === '=' && $params[$filterParam] === null){
                        $cond = [$filterArray['column'],'ISNULL'];
                    }
                    elseif(is_array($filterArray['column'])){
                        if($params[$filterParam] !== null)
                            $cond = [[$params[$filterParam],'STRING'],$filterArray['column'],'IN'];
                        else{
                            $cond = [];
                            foreach ($filterArray['column'] as $column)
                                $cond[] = [$column, 'ISNULL'];
                            $cond[] = 'OR';
                        }
                        $cacheCond = [$params[$filterParam],$filterArray['column'],'INREV'];
                    }
                    else
                        $cond = [$filterArray['column'],$params[$filterParam],$filterArray['filter']];
                }
                elseif(isset($filterArray['default']) && isset($filterArray['alwaysSend'])){
                    if(is_array($filterArray['column'])){
                        $cond = [[$filterArray['default'],'STRING'],$filterArray['column'],'IN'];
                        $cacheCond = [$filterArray['default'],$filterArray['column'],'INREV'];
                    }
                    else
                        $cond = [$filterArray['column'],[$filterArray['default'],'STRING'],$filterArray['filter']];
                }

                if($cond){
                    $cacheCond = $cacheCond ?? $cond;
                    $extraCacheConditions[] = $cacheCond;
                    $extraDBConditions[] = $cond;
                }

            }

            if($extraCacheConditions!=[]){
                $extraCacheConditions[] = 'AND';
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }
            if($extraDBConditions!=[]){
                $extraDBConditions[] = 'AND';
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            if(!empty($extraCacheConditions) || !empty($extraDBConditions))
                $cacheFullResults = false;

            //This is here because $cacheFullResults can change due to filters
            if(!$cacheFullResults && $cacheFullResultsCustomSuffix)
                $cacheFullResultsCustomSuffix =  $typeArray['allItemsCacheName'].$cacheFullResultsCustomSuffix;
            else
                $cacheFullResultsCustomSuffix = false;

            $joinOtherTable = count($joinOnGet) > 0;

            $tableQuery = $items == [] ? $this->SQLManager->getSQLPrefix() . $typeArray['tableName'] : $typeArray['tableName'];
            $selectionColumns = [];

            if($joinOtherTable){
                foreach($joinOnGet as $joinArr){

                    if(!empty($joinArr['expression'])){
                        $tableQuery .= $joinArr['expression'];
                        continue;
                    }

                    if(empty($joinArr['join']))
                        $joinArr['join'] = ' LEFT JOIN ';
                    if(empty($joinArr['condition']))
                        $joinArr['condition'] = '=';
                    if(empty($joinArr['leftTableName']))
                        $joinArr['leftTableName'] = $typeArray['tableName'];

                    $tableQuery .= $joinArr['join'];
                    //Either we got a table name or a pair of [name,alias]
                    if(gettype($joinArr['tableName']) === 'string'){
                        $joinTableName = $this->SQLManager->getSQLPrefix() . $joinArr['tableName'];
                        $joinAlias = $this->SQLManager->getSQLPrefix() . $joinArr['tableName'];
                    }
                    elseif(gettype($joinArr['tableName']) === 'array'){
                        $joinTableName = $this->SQLManager->getSQLPrefix() . $joinArr['tableName'][0];
                        $joinAlias = $joinArr['tableName'][1];
                    }
                    else{
                        throw new \Exception("Invalid object joinOnGet structure!");
                    }
                    $tableQuery .= $joinTableName.' ';
                    if($joinAlias !== $joinTableName)
                        $tableQuery .= $joinAlias.' ';
                    if(!empty($joinArr['on']))
                        $tableQuery .= ' ON ';
                    if(gettype($joinArr['on']) === 'array'){
                        foreach($joinArr['on'] as $pair){
                            if(gettype($pair[0]) === 'string'){
                                $tableQuery .= $this->SQLManager->getSQLPrefix() . $joinArr['leftTableName'] .'.'.$pair[0];
                            }
                            elseif(gettype($pair[0]) === 'array'){
                                if($pair[0][1] === 'STRING'){
                                    $tableQuery .= '"'.$pair[0][0].'"';
                                }
                                elseif($pair[0][1] === 'ASIS')
                                    $tableQuery .= $pair[0][0];
                            }
                            $tableQuery .= ' '.$joinArr['condition'].' ';
                            if(gettype($pair[1]) === 'string'){
                                $tableQuery .= $joinAlias.'.'.$pair[1];
                            }
                            elseif(gettype($pair[1]) === 'array'){
                                if($pair[1][1] === 'STRING'){
                                    $tableQuery .= '"'.$pair[1][0].'"';
                                }
                                elseif($pair[1][1] === 'ASIS')
                                    $tableQuery .= $pair[1][0];
                            }
                            $tableQuery .= ' AND ';
                        }
                        $tableQuery = substr($tableQuery,0,strlen($tableQuery)-5);
                    }
                    else
                        $tableQuery .= $joinArr['on'];
                }
            }

            if($columnsToGet)
                foreach($columnsToGet as $columnToGet){
                    if(!empty($columnToGet['expression']))
                        $toGet = $columnToGet['expression'];
                    else{
                        $toGet = '';
                        if(empty($columnToGet['alias']))
                            $toGet .= $this->SQLManager->getSQLPrefix();
                        $toGet .= $columnToGet['tableName'].'.'.$columnToGet['column'];
                        if(!empty($columnToGet['as']))
                            $toGet .= ' AS '.$columnToGet['as'];
                    }
                    $selectionColumns[] = $toGet;
                }
            if(count($selectionColumns))
                $selectionColumns[] = $this->SQLManager->getSQLPrefix() . $typeArray['tableName'] . '.*';

            if($items == []){
                $results = [];
                if($groupByFirstNKeys && !$getAllSubItems)
                    $retrieveParams['groupBy'] = $keyColumns;

                //Get all items, handle case when allItemsCacheName is set
                $res = null;
                $cacheTarget = ($cacheFullResults || $cacheFullResultsCustomSuffix) ?
                    ($cacheFullResultsCustomSuffix ?: $typeArray['allItemsCacheName']) :
                    false;
                $cacheTargetMeta = $cacheTarget? $cacheTarget.'_@' : false;
                $gotCacheResults = false;
                if($cacheTarget){
                    if($verbose)
                        echo 'Getting '.$cacheTarget.' from cache'.EOL;
                    $cacheResults = $this->RedisManager->call('get', $cacheTarget);
                    if(!empty($cacheResults) && \IOFrame\Util\PureUtilFunctions::is_json($cacheResults)){
                        $res = json_decode($cacheResults,true);
                        $gotCacheResults = true;
                    }
                    elseif($verbose)
                        echo 'Got nothing from cache!'.EOL;
                    unset($cacheResults);
                }
                if(!$res){
                    $res = $this->SQLManager->selectFromTable(
                        $tableQuery,
                        $extraDBConditions,
                        $selectionColumns,
                        $retrieveParams
                    );
                    if(($cacheFullResults || $cacheFullResultsCustomSuffix) && is_array($res)) {
                        $actualCacheSizeLimit = $this->maxCacheSize * count($res);
                        $resJson = json_encode($res);
                        if($ignoreSubItemCacheLimit || (strlen($resJson) < $actualCacheSizeLimit)){
                            if(!$test)
                                $this->RedisManager->call('set',[$cacheTarget,json_encode($res),$cacheTTL]);
                            if($verbose)
                                echo 'Adding '.$cacheTarget.' to cache for '.
                                    $cacheTTL.' seconds as '.json_encode($res).EOL;
                        }
                        elseif ($verbose){
                            echo 'Not adding '.$cacheTarget.' to cache due to db result being '.strlen($resJson).' long, max being '.$actualCacheSizeLimit.EOL;
                        }
                    }
                }

                if(is_array($res)){

                    $resCount = isset($res[0]) ? count($res[0]) : 0;
                    foreach($res as $resultArray){
                        for($i = 0; $i<$resCount; $i++)
                            unset($resultArray[$i]);
                        $key = '';
                        $i = $groupByFirstNKeys;
                        $keyPrefix = '';
                        foreach(array_merge($keyColumns,$extraKeyColumns) as $keyColumn){
                            if($i-->0){
                                $keyPrefix .= $resultArray[$keyColumn].'/';
                            }
                            else
                                $key .= $resultArray[$keyColumn].'/';
                        }
                        $key = substr($key,0,strlen($key) - 1);
                        if($keyPrefix)
                            $keyPrefix = substr($keyPrefix,0,strlen($keyPrefix) - 1);
                        //Convert safeSTR columns to normal
                        foreach($resultArray as $colName => $colArr){
                            if(in_array($colName,$safeStrColumns))
                                $resultArray[$colName] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($colArr);
                        }
                        if($keyPrefix){
                            if(!isset($results[$keyPrefix]))
                                $results[$keyPrefix] = [];
                            $results[$keyPrefix][$key] = $resultArray;
                        }
                        else
                            $results[$key] = $resultArray;
                    }

                    if(isset($typeArray['extraToGet']) && $typeArray['extraToGet']){
                        //Prepare the meta information
                        $results['@'] = [];
                        $response = false;

                        //Only relevant in case we got regular cache results - otherwise, meta results may be out of date.
                        if($cacheTargetMeta && $gotCacheResults){
                            if($verbose)
                                echo 'Getting '.$cacheTargetMeta.' from cache'.EOL;
                            $cacheResults = $this->RedisManager->call('get', $cacheTargetMeta);
                            if(!empty($cacheResults) && \IOFrame\Util\PureUtilFunctions::is_json($cacheResults))
                                $response = json_decode($cacheResults,true);
                            elseif($verbose)
                                echo 'Got nothing from cache!'.EOL;
                            unset($cacheResults);
                        }

                        if(!$response){
                            //Get all relevant stuff
                            $selectQuery = '';

                            foreach($typeArray['extraToGet'] as $columnName => $arr){
                                $differentColName = $arr['differentColName'] ?? $columnName;
                                $explicitDifferentColName = $this->SQLManager->getSQLPrefix().$typeArray['tableName'].'.'.$differentColName;
                                $explicitKeyColumns = [];
                                foreach ($keyColumns as $col)
                                    $explicitKeyColumns[] = $this->SQLManager->getSQLPrefix() . $typeArray['tableName'] . '.' . $col;
                                switch($arr['type']){
                                    case 'min':
                                    case 'max':
                                        $selectQuery .= $this->SQLManager->selectFromTable(
                                                $tableQuery,
                                                $extraDBConditions,
                                                [($arr['type'] === 'min' ? 'MIN('.$explicitDifferentColName.')': 'MAX('.$explicitDifferentColName.')').' AS Val, "'.$columnName.'" as Type'],
                                                ['justTheQuery'=>true,'test'=>false]
                                            ).' UNION ';
                                        break;
                                    case 'sum':
                                        $selectQuery .= $this->SQLManager->selectFromTable(
                                                $tableQuery,
                                                $extraDBConditions,
                                                ['SUM('.$explicitDifferentColName.') AS Val, "'.$columnName.'" as Type'],
                                                ['justTheQuery'=>true,'test'=>false]
                                            ).' UNION ';
                                        break;
                                    case 'count':
                                        $selectQuery .= $this->SQLManager->selectFromTable(
                                                $tableQuery,
                                                $extraDBConditions,
                                                [(($groupByFirstNKeys && !$getAllSubItems)? 'COUNT(DISTINCT '.implode(',',$explicitKeyColumns).')': 'COUNT(*)').' AS Val, "'.$columnName.'" as Type'],
                                                ['justTheQuery'=>true,'test'=>false]
                                            ).' UNION ';
                                        break;
                                    case 'distinct':
                                        $selectQuery .= $this->SQLManager->selectFromTable(
                                                $tableQuery,
                                                $extraDBConditions,
                                                [$explicitDifferentColName.' AS Val, "'.$columnName.'" as Type'],
                                                ['justTheQuery'=>true,'DISTINCT'=>true,'test'=>false]
                                            ).' UNION ';
                                        break;
                                    case 'distinct_multiple':
                                        $selectQuery .= '(SELECT DISTINCT Temp_Val AS Val, "'.$columnName.'" as Type FROM (';
                                        foreach ($arr['columns'] as $column)
                                            $selectQuery .= $this->SQLManager->selectFromTable(
                                                    $tableQuery,
                                                    $extraDBConditions,
                                                    [$column.' AS Temp_Val'],
                                                    ['justTheQuery'=>true,'DISTINCT'=>true,'test'=>false]
                                                ).' UNION ';
                                        $selectQuery = substr($selectQuery,0,-7);
                                        $selectQuery .= ') as Meaningless_Alias ORDER BY Val) UNION ';
                                        break;
                                    case 'count_interval':
                                        $selectQuery .= '(SELECT CONCAT(Item_Count,"_",Intervals)  AS Val, "'.$columnName.'" as Type FROM (';
                                        $selectQuery .= $this->SQLManager->selectFromTable(
                                            $tableQuery,
                                            $extraDBConditions,
                                            [
                                                (($groupByFirstNKeys && !$getAllSubItems)? 'COUNT(DISTINCT '.implode(',',$explicitKeyColumns).')': 'COUNT(*)').' AS Item_Count',
                                                'FLOOR('.$explicitDifferentColName.' / '.($arr['intervals']??1).') as Intervals',
                                            ],
                                            ['justTheQuery'=>true,'groupBy'=>'Intervals','test'=>false]
                                        );
                                        $selectQuery .= ') as Meaningless_Alias ORDER BY Val) UNION ';
                                        break;
                                    case 'function':
                                        $selectQuery .= '('.$arr['function']([
                                                'tableQuery'=>$tableQuery,
                                                'typeArray'=>$typeArray,
                                                'params'=>$params,
                                                'SQLManager'=>$this->SQLManager,
                                                'extraDBConditions'=>$extraDBConditions
                                            ]).') UNION ';
                                        break;
                                }
                            }
                            $selectQuery = substr($selectQuery,0,strlen($selectQuery) - 7);

                            if($verbose)
                                echo 'Query to send: '.$selectQuery.EOL;

                            $response = $this->SQLManager->exeQueryBindParam($selectQuery,[],['fetchAll'=>true]);

                            if($cacheTargetMeta){
                                if(!$test)
                                    $this->RedisManager->call('set',[$cacheTargetMeta,json_encode($response),$cacheTTL]);
                                if($verbose)
                                    echo 'Adding '.$cacheTargetMeta.' to cache for '.
                                        $cacheTTL.' seconds as '.json_encode($response).EOL;
                            }
                        }

                        if($response){
                            foreach($response as $arr){
                                $columnName = $arr['Type'];
                                $relevantToGetInfo = $typeArray['extraToGet'][$columnName];
                                $key = $relevantToGetInfo['key'];
                                $type = $relevantToGetInfo['type'];
                                if( !in_array($type,['distinct','distinct_multiple','count_interval']) && empty($relevantToGetInfo['aggregate']) ){
                                    $results['@'][$key] = $arr['Val'];
                                }
                                else{
                                    if($arr['Val'] === null)
                                        continue;

                                    if(!isset( $results['@'][$key]))
                                        $results['@'][$key] = [];

                                    if($type !== 'count_interval')
                                        $results['@'][$key][] = $arr['Val'];
                                    else{
                                        $realRes = explode('_',$arr['Val']);
                                        $results['@'][$key][$realRes[1]] = $realRes[0];
                                    }
                                }
                            }
                        }
                    }
                }
                return $results;
            }
            else{
                if($joinOtherTable){
                    $retrieveParams['keyColumnPrefixes'] = [];
                    for($i = 0; $i<count($keyColumns); $i++)
                        $retrieveParams['keyColumnPrefixes'][] = $this->SQLManager->getSQLPrefix() . $typeArray['tableName'] . '.';
                    $retrieveParams['pushKeyToColumns'] = false;
                }

                $results = $this->getFromCacheOrDB(
                    $items,
                    $keyColumns,
                    $tableQuery,
                    $typeArray['cacheName']??null,
                    $selectionColumns,
                    array_merge($retrieveParams,['extraKeyColumns'=>$extraKeyColumns,'groupByFirstNKeys'=>$groupByFirstNKeys,'compareCol'=>!$joinOtherTable,'test'=>$test])
                );

                if(empty($results))
                    $results = [];

                foreach($results as $index => $res){
                    if(!is_array($res))
                        continue;
                    //Convert safeSTR columns to normal
                    if(!$groupByFirstNKeys)
                        foreach($res as $colName => $colArr){
                            if(in_array($colName,$safeStrColumns))
                                $results[$index][$colName] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($results[$index][$colName]);
                        }
                    else{
                        foreach($res as $subIndex => $subItemArray){
                            if(!is_array($subItemArray))
                                continue;
                            foreach($subItemArray as $colName => $colArr){
                                if(in_array($colName,$safeStrColumns))
                                    $results[$index][$subIndex][$colName] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($results[$index][$subIndex][$colName]);
                            }
                        }
                    }
                }

                return $results;
            }
        }

        /** Set multiple items
         * @param array $inputs Array found in the type array's "setColumns". The explanation of the structure is up
         *              at the top of this class,
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         * @param array $params of the form:
         *              'update' - bool, whether to only update existing items. Overrides "override".
         *              'override'/'overwrite' - bool, whether to allow overwriting existing items. Defaults to true.
         *              'checkLock' - bool, default false. If object has lock columns, whether to only unlock columns with the same lock (still needs to lock them first)
         *              'existingKey' - string, default false. If passed, will try to lock items with this key
         *              'unlockWhenDone' - bool, default true. Whether to unlock the items when you're done (always false if not passing an existing key)
         *              'replaceTypeArray' - object, default [] - If set, recursively merges with $this->objectsDetails[$type],
         *                                   potentially replacing things like primary key, expanding / reducing extraToGet, etc.
         *              'disableLogging' - bool, default false. Disables logging for this operation
         *
         * @returns array|int, if not creating new auto-incrementing items, array of the form:
         *          <identifier> => <code>
         *          Where each identifier is the contact identifier, and possible codes are:
         *         -4 - failed to modify items since they could not be locked
         *         -3 - failed to modify items since one of the dependencies is missing
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
         * @throws \Exception If the item type is invalid
         *
         */
        function setItems(array $inputs, string $type, array $params = []): array|bool|int|string|null {

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');

            $replaceTypeArray = $params['replaceTypeArray'] ?? [];
            if(!empty($replaceTypeArray) && is_array($replaceTypeArray))
                $typeArray = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($typeArray,$replaceTypeArray,['deleteOnNull'=>true]);

            if(isset($params['overwrite']) && !isset($params['override']))
                $params['override'] = $params['overwrite'];

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $hasTimeColumns = $params['hasTimeColumns'] ?? ($this->objectsDetails[$type]['hasTimeColumns'] ?? true);
            $update = $params['update'] ?? false;
            $override = $update? true : ($params['override'] ?? true);
            $checkLock = $params['checkLock'] ?? false;
            $autoIncrement = isset($typeArray['autoIncrement']) && $typeArray['autoIncrement'];
            $autoIncrementMainKey = !$override && !$update && $autoIncrement;
            $useCache = $this->defaultSettingsParams['useCache'] && ($typeArray['useCache'] ?? !empty($typeArray['cacheName']));
            $keyColumns = $typeArray['keyColumns'];
            $lockColumns = $typeArray['lockColumns'] ?? [];
            $existingKey = $params['existingKey'] ?? null;
            $unlockWhenDone = !(isset($params['unlockWhenDone']) && $existingKey) || $params['unlockWhenDone'];
            $disableLogging = $params['disableLogging'] ?? null;
            $safeStrColumns = $typeArray['safeStrColumns'] ?? [];
            $extraKeyColumns = $typeArray['extraKeyColumns'] ?? [];
            $groupByFirstNKeys = $typeArray['groupByFirstNKeys'] ?? 0;
            $combinedColumns = isset($typeArray['extraKeyColumns']) ? array_merge($typeArray['keyColumns'],$typeArray['extraKeyColumns']) : $typeArray['keyColumns'];
            $cacheFullResults = $useCache && !empty($typeArray['allItemsCacheName']) && empty($groupByFirstNKeys);
            $cacheFullResultsCustomSuffix = $params['cacheFullResultsCustomSuffix'] ?? null;
            if($cacheFullResults && $cacheFullResultsCustomSuffix)
                $cacheFullResultsCustomSuffix =  $typeArray['allItemsCacheName'].$cacheFullResultsCustomSuffix;

            $identifiers = [];
            $existingIdentifiers = [];
            $indexMap = [];
            $identifierMap = [];

            $results = $autoIncrementMainKey ? -1 : [];
            $itemsToSet = [];
            $itemsToGet = [];
            $setColumns = [];
            $inputsThatPassed = [];
            $onDuplicateKeyColExp = [];
            $timeNow = (string)time();
            $useLocks = ($lockColumns['lock'] ?? false) && ($lockColumns['timestamp'] ?? false) && !$autoIncrementMainKey;
            $lockColumns['retryAttempts'] = $lockColumns['retryAttempts'] ?? 5;
            $lockColumns['lockLength'] = $lockColumns['lockLength'] ?? 128;

            foreach($typeArray['setColumns'] as $colName => $colArr){
                if(
                    $autoIncrementMainKey &&
                    isset($colArr['autoIncrement']) &&
                    $colArr['autoIncrement']
                )
                    continue;
                if(!empty($colArr['onDuplicateKeyColExp']))
                    $onDuplicateKeyColExp[$colName] = $colArr['onDuplicateKeyColExp'];
                $setColumns[] = $colName;
            }

            if($hasTimeColumns)
                $setColumns = array_merge($setColumns,$this->timeColumns);

            if(!$autoIncrementMainKey){
                foreach($inputs as $index=>$inputArr){

                    $identifier = '';
                    $identifierArr = [];

                    foreach($combinedColumns as $keyCol){
                        $identifier .= $inputArr[$keyCol].'/';
                        $identifierArr[] = $inputArr[$keyCol];
                    }

                    $identifier = substr($identifier,0,strlen($identifier)-1);

                    $indexMap[$identifier] = $index;
                    $identifierMap[$index] = $identifier;

                    $identifiers[] = $identifierArr;
                    if(count($extraKeyColumns) === 0)
                        $itemsToGet[] = $identifierArr;
                    else{
                        array_pop($identifierArr);
                        $itemsToGet[] = $identifierArr;
                    }

                    $results[$identifier] = -1;
                }
                if(isset($params['existing']) && !$useLocks)
                    $existing = $params['existing'];
                elseif($useLocks){
                    $tries = 0;
                    $existing = [];
                    if(!$existingKey){
                        $hex_secure = false;
                        while(!$hex_secure)
                            $key=bin2hex(openssl_random_pseudo_bytes($lockColumns['lockLength'],$hex_secure));
                    }
                    else
                        $key = $existingKey;
                    while($tries++ < $lockColumns['retryAttempts']){
                        //Try to lock the columns
                        $lock = $this->lockItems($itemsToGet,$type,array_merge($params,['lock'=>$key]));
                        //See if you got locks
                        $gotLocks = [];
                        $existing = $this->getItems($itemsToGet, $type, array_merge($params,['updateCache'=>false]));
                        foreach($itemsToGet as $keyArr){
                            $itemKey = implode('/',$keyArr);
                            if(!empty($existing[$itemKey][$lockColumns['lock']]) && $existing[$itemKey][$lockColumns['lock']] === $lock || empty($existing[$itemKey][$lockColumns['lock']]) && $test)
                                $gotLocks[] = $itemKey;
                        }
                        if(count($gotLocks) === count($itemsToGet))
                            break;
                        elseif($tries === (int)$lockColumns['retryAttempts']){
                            foreach($itemsToGet as $index=>$keyArr){
                                $itemKey = implode('/',$keyArr);
                                if(!in_array($itemKey,$gotLocks)){
                                    $results[$itemKey] = -4;
                                    unset($itemsToGet[$index]);
                                    unset($inputs[$indexMap[$itemKey]]);
                                }
                            }
                        }
                    }
                    if(empty($itemsToGet))
                        return $results;
                }
                else{
                    $existing = $this->getItems($itemsToGet, $type, array_merge($params,['updateCache'=>false]));
                }
            }
            else
                $existing = [];

            foreach($inputs as $index=>$inputArr){

                $arrayToSet = [];

                $identifier = '';
                foreach($keyColumns as $keyCol){
                    if( !($autoIncrementMainKey && !empty($typeArray['setColumns'][$keyCol]['autoIncrement']) ) )
                        $identifier .= $inputArr[$keyCol].'/';
                }
                $identifier = substr($identifier,0,strlen($identifier)-1);

                if(!$autoIncrementMainKey){
                    if(!$groupByFirstNKeys){
                        $existingArr = $existing[$identifierMap[$index]] ?? 1;
                    }
                    else{
                        $prefix =  explode('/',$identifierMap[$index]);
                        $target = [];
                        for($i=0;$i<$groupByFirstNKeys; $i++)
                            $target[] = array_pop($prefix);
                        $target = array_reverse($target);
                        $prefix = implode('/',$prefix);
                        $target = implode('/',$target);
                        $existingArr = $existing[$prefix][$target] ?? 1;
                    }
                }

                //In this case we are creating an auto-incrementing key, the address does not exist or we couldn't connect to db
                if($autoIncrementMainKey || !is_array($existingArr)){
                    //If we could not connect to the DB, just return because it means we wont be able to connect next
                    if(!$autoIncrementMainKey && (($existingArr??null) == -1) ){
                        if($useLocks && $unlockWhenDone)
                            $this->unlockItems($itemsToGet,$type,array_merge($params,['lock'=>$checkLock ? $key : null]));
                        return $results;
                    }
                    else{
                        //If we are only updating, continue
                        if($update){
                            $results[$identifierMap[$index]] = 1;
                            unset($inputs[$index]);
                            continue;
                        }

                        $missingInputs = false;

                        foreach($typeArray['setColumns'] as $colName => $colArr){

                            if($autoIncrementMainKey && !empty($colArr['autoIncrement']))
                                continue;

                            if(!isset($inputArr[$colName]) &&
                                !array_key_exists('default',$colArr) &&
                                !array_key_exists('forceValue',$colArr) &&
                                !array_key_exists('function',$colArr)
                            )
                            {
                                if($verbose){
                                    echo 'Input '.$index.' is missing the required column '.$colName.EOL;
                                }
                                $missingInputs = true;
                                continue;
                            }

                            if(isset($colArr['forceValue']))
                                $val = $colArr['forceValue'];
                            elseif(isset($colArr['function'])){
                                $tempInputs = [
                                    'typeArray'=>$typeArray,
                                    'inputArray'=>$inputArr,
                                    'params'=>$params
                                ];
                                $val = $colArr['function']($tempInputs);
                            }
                            elseif(isset($inputArr[$colName]))
                                $val = $inputArr[$colName];
                            else
                                $val = $colArr['default'];

                            if(in_array($colName,$safeStrColumns))
                                $val = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($val);

                            if(!empty($colArr['jsonObject']) && is_array($val))
                                $val = json_encode($val,true);

                            if(isset($colArr['considerNull']) && ($val === $colArr['considerNull']) )
                                $val = null;
                            elseif(!isset($colArr['type']) || $colArr['type'] === 'string')
                                $val = [$val,'STRING'];

                            if($val !== null){
                                if($colArr['type'] === 'int')
                                    $val = (int)$val;
                                elseif($colArr['type'] === 'bool')
                                    $val = (bool)$val;
                            }

                            $arrayToSet[] = $val;
                        }
                        //Add creation and update time
                        if($hasTimeColumns){
                            $arrayToSet[] = [$timeNow, 'STRING'];
                            $arrayToSet[] = [$timeNow, 'STRING'];
                        }

                        if($missingInputs){
                            if(!$autoIncrementMainKey){
                                $results[$identifier] = 3;
                                unset($inputs[$index]);
                                continue;
                            }
                            else{
                                $results = -3;
                                if($useLocks && $unlockWhenDone)
                                    $this->unlockItems($itemsToGet,$type,array_merge($params,['lock'=>$checkLock ? $key : null]));
                                return $results;
                            }
                        }

                        //Add the resource to the array to set
                        $itemsToSet[] = $arrayToSet;
                        $inputsThatPassed[] = $inputArr;
                    }
                }
                //This is the case where the item existed
                else{
                    //If we are not allowed to override existing resources, go on
                    if(!$override && !$update){
                        $results[$identifierMap[$index]] = 2;
                        unset($inputs[$index]);
                        continue;
                    }

                    foreach($typeArray['setColumns'] as $colName => $colArr){

                        $existingVal = $existingArr[$colName] ?? null;

                        if(isset($colArr['forceValue']))
                            $val = $colArr['forceValue'];
                        elseif(isset($colArr['function'])){
                            $tempInputs = [
                                'typeArray'=>$typeArray,
                                'inputArray'=>$inputArr,
                                'existingArray'=>$existingArr,
                                'params'=>$params
                            ];
                            $val = $colArr['function']($tempInputs);
                        }
                        elseif(isset($inputArr[$colName])){
                            if(
                                !empty($colArr['jsonObject']) &&
                                \IOFrame\Util\PureUtilFunctions::is_json($existingVal)&&
                                (\IOFrame\Util\PureUtilFunctions::is_json($inputArr[$colName]) || is_array($inputArr[$colName]))
                            ){
                                $inputJSON = is_string($inputArr[$colName])? json_decode($inputArr[$colName],true) : $inputArr[$colName];
                                $existingJSON = json_decode($existingVal,true);
                                if($inputJSON == null)
                                    $inputJSON = [];
                                if($existingJSON == null)
                                    $existingJSON = [];
                                $val =json_encode(\IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($existingJSON,$inputJSON,['deleteOnNull'=>true]));
                                if($val == '[]' || $val == 'null' )
                                    $val = null;
                            }
                            elseif($inputArr[$colName] === '@' || $inputArr[$colName] === ''){
                                $val = null;
                            }
                            else{
                                $val = $inputArr[$colName];
                            }
                        }
                        else{
                            $val = $existingVal;
                        }

                        if(in_array($colName,$safeStrColumns))
                            $val = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($val);

                        if(isset($colArr['considerNull']) && ($val === $colArr['considerNull']) )
                            $val = null;
                        elseif(!isset($colArr['type']) || $colArr['type'] === 'string')
                            $val = [$val,'STRING'];

                        if($val !== null){
                            if($colArr['type'] === 'int')
                                $val = (int)$val;
                            elseif($colArr['type'] === 'bool')
                                $val = (bool)$val;
                        }

                        $arrayToSet[] = $val;

                    }

                    //Add creation and update time
                    if($hasTimeColumns){
                        $created = $existingArr['Created'];

                        $arrayToSet[] = [$created, 'STRING'];
                        $arrayToSet[] = [$timeNow, 'STRING'];
                    }

                    //Add the resource to the array to set
                    $itemsToSet[] = $arrayToSet;
                    $inputsThatPassed[] = $inputArr;
                }

                //Add the identifier to the existing identifiers - differentiates on whether we have extra key columns or not
                if(!empty($useCache)){
                    if(count($extraKeyColumns) == 0 && $identifier)
                        $existingIdentifiers[] = $typeArray['cacheName'] . $identifier;
                    elseif($identifier && !in_array($identifier,$existingIdentifiers))
                        $existingIdentifiers[] = $typeArray['cacheName'] . $identifier;
                }

            }

            //If we got nothing to set, return
            if($itemsToSet==[]){
                if($useLocks && $unlockWhenDone)
                    $this->unlockItems($itemsToGet,$type,array_merge($params,['lock'=>$checkLock ? $key : null]));
                return $results;
            }

            $res = $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix() . $typeArray['tableName'],
                $setColumns,
                $itemsToSet,
                array_merge($params,['returnError'=>true,'onDuplicateKey'=>!$autoIncrementMainKey,'returnRows'=>$autoIncrementMainKey,'onDuplicateKeyColExp'=>$onDuplicateKeyColExp])
            );

            if(!$autoIncrementMainKey){
                //This means we either succeeded or got an error code returned
                if($res === true){

                    $this->updateFathers($inputsThatPassed,$type,$params);

                    foreach($identifiers as $identifier){
                        $identifier = implode('/',$identifier);
                        if($results[$identifier] == -1)
                            $results[$identifier] = 0;
                    }
                    //If we succeeded, set results to success and remove them from cache
                    if($cacheFullResults)
                        $existingIdentifiers[] = $typeArray['allItemsCacheName'];
                    if($cacheFullResultsCustomSuffix)
                        $existingIdentifiers[] = $cacheFullResultsCustomSuffix;
                    if($existingIdentifiers != [] && $useCache){
                        if(count($existingIdentifiers) == 1)
                            $existingIdentifiers = $existingIdentifiers[0];

                        if($verbose)
                            echo 'Deleting objects of type "'.$type.'" '.json_encode($existingIdentifiers).' from cache!'.EOL;

                        if(!$test){
                            $this->RedisManager->call('del',[$existingIdentifiers]);
                        }
                    }
                }
                //This is the code for missing dependencies
                elseif($res === '23000'){
                    foreach($identifiers as $identifier){
                        $identifier = implode('/',$identifier);
                        if($results[$identifier] == -1)
                            $results[$identifier] = -2;
                    }
                }
                else{
                    if(!$disableLogging)
                        $this->getLogger($type)->error('Failed to insert items of '.$type.' into table',['items'=>$itemsToSet]);
                    $results = $res;
                }
            }
            else{
                //This means we either succeeded or got an error code returned
                if($res === '23000'){
                    $results = -2;
                }
                //This is the code for missing dependencies
                elseif($res === true){
                    $results =  -1;
                }
                else{
                    $this->updateFathers($inputsThatPassed,$type,$params);
                    //If we succeeded, set results to success and remove them from cache
                    if($cacheFullResults)
                        $existingIdentifiers[] = $typeArray['allItemsCacheName'];
                    if($cacheFullResultsCustomSuffix)
                        $existingIdentifiers[] = $cacheFullResultsCustomSuffix;
                    if($existingIdentifiers != [] && $useCache){
                        if(count($existingIdentifiers) == 1)
                            $existingIdentifiers = $existingIdentifiers[0];
                        if($verbose)
                            echo 'Deleting objects of type "'.$type.'" '.json_encode($existingIdentifiers).' from cache!'.EOL;

                        if(!$test)
                            $this->RedisManager->call('del',[$existingIdentifiers]);
                    }
                    $results =  $res;
                }
            }

            if($useLocks && $unlockWhenDone)
                $this->unlockItems($itemsToGet,$type,array_merge($params,['lock'=>$checkLock ? $key : null]));
            return $results;
        }

        /** Delete multiple items
         * @param array $items Array of objects (arrays). Each object needs to contain the keys from they type array's "keyColumns",
         *              each key pointing to the value of the desired item to delete.
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         * @param array $params
         *              <valid filter name> - valid filters are found in the type array's "columnFilters" - the param name
         *                                    is the same as the key.
         *              'replaceTypeArray' - object, default [] - If set, recursively merges with $this->objectsDetails[$type],
         *                                   potentially replacing things like primary key, expanding / reducing extraToGet, etc.
         *              'disableLogging' - bool, default false. Disables logging for this operation
         * @return Int codes:
         *          -1 server error (would be the same for all)
         *           0 success (does not check if items do not exist)
         *
         * @throws \Exception If the item type is invalid
         *
         */
        function deleteItems(array $items, string $type, array $params): int {

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');

            $replaceTypeArray = $params['replaceTypeArray'] ?? [];
            if(!empty($replaceTypeArray) && is_array($replaceTypeArray))
                $typeArray = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($typeArray,$replaceTypeArray,['deleteOnNull'=>true]);

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $hasTimeColumns = $params['hasTimeColumns'] ?? ($this->objectsDetails[$type]['hasTimeColumns'] ?? true);
            $useCache = $this->defaultSettingsParams['useCache'] && ($typeArray['useCache'] ?? !empty($typeArray['cacheName']));
            $keyColumns = isset($typeArray['extraKeyColumns']) ? array_merge($typeArray['keyColumns'],$typeArray['extraKeyColumns']) : $typeArray['keyColumns'];
            $childCache = $typeArray['childCache'] ?? [];
            $disableLogging = $params['disableLogging'] ?? null;
            $validFilters = $typeArray['columnFilters'];
            $groupByFirstNKeys = $typeArray['groupByFirstNKeys'] ?? 0;
            $cacheFullResults = $useCache && !empty($typeArray['allItemsCacheName']) && empty($groupByFirstNKeys);
            $cacheFullResultsCustomSuffix = $params['cacheFullResultsCustomSuffix'] ?? null;
            if($cacheFullResults && $cacheFullResultsCustomSuffix)
                $cacheFullResultsCustomSuffix =  $typeArray['allItemsCacheName'].$cacheFullResultsCustomSuffix;

            if($hasTimeColumns){
                $validFilters = array_merge($validFilters,$this->timeFilters);
            }

            $existingIdentifiers = [];
            $identifiers = [];

            foreach($items as $inputArr){
                $identifier = [];
                //Add the identifier to the existing identifiers - differentiates on whether we have extra key columns or not
                $commonIdentifier = '';
                if(!$groupByFirstNKeys){
                    foreach($keyColumns as $keyCol){
                        $commonIdentifier .= $inputArr[$keyCol].'/';
                        $identifier[] = [$inputArr[$keyCol], 'STRING'];
                    }
                    $commonIdentifier = substr($commonIdentifier,0,strlen($commonIdentifier)-1);
                    if($useCache)
                        $existingIdentifiers[] = $typeArray['cacheName'] . $commonIdentifier;
                    if(count($childCache))
                        foreach($childCache as $childCollectionCacheName)
                            $existingIdentifiers[] = $childCollectionCacheName . $commonIdentifier;
                }
                else{
                    for($i=0; $i<$groupByFirstNKeys; $i++)
                        $commonIdentifier .= $inputArr[$typeArray['keyColumns'][$i]].'/';
                    $commonIdentifier = substr($commonIdentifier,0,strlen($commonIdentifier)-1);
                    if($useCache && !in_array($typeArray['cacheName'].$commonIdentifier,$existingIdentifiers)){
                        $existingIdentifiers[] = $typeArray['cacheName'] . $commonIdentifier;
                        if(count($childCache))
                            foreach($childCache as $childCollectionCacheName)
                                $existingIdentifiers[] = $childCollectionCacheName . $commonIdentifier;
                    }

                    foreach($keyColumns as $keyCol){
                        $identifier[] = [$inputArr[$keyCol], 'STRING'];
                    }
                }
                $identifiers[] = $identifier;
            }

            if(count($identifiers) === 0)
                return 1;
            else
                $identifiers[] = 'CSV';

            $DBConditions = [
                [
                    $keyColumns,
                    $identifiers,
                    'IN'
                ]
            ];

            foreach($validFilters as $filterParam => $filterArray){
                if(isset($params[$filterParam])){
                    if(gettype($params[$filterParam]) === 'array'){
                        foreach($params[$filterParam] as $key => $value){
                            $params[$filterParam][$key] = [$value,'STRING'];
                        }
                    }
                    elseif($params[$filterParam] === '')
                        $params[$filterParam] = null;
                    $cond = [$filterArray['column'],$params[$filterParam],$filterArray['filter']];
                    $DBConditions[] = $cond;
                }
                elseif(isset($filterArray['default']) && isset($filterArray['alwaysSend'])){
                    $cond = [$filterArray['column'],[$filterArray['default'],'STRING'],$filterArray['filter']];
                    $DBConditions[] = $cond;
                }
            }

            $DBConditions[] = 'AND';

            $res = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix() . $typeArray['tableName'],
                $DBConditions,
                $params
            );

            if($res){
                $this->updateFathers($items,$type,$params);
                if($useCache) {
                    if($cacheFullResults)
                        $existingIdentifiers[] = $typeArray['allItemsCacheName'];
                    if($cacheFullResultsCustomSuffix)
                        $existingIdentifiers[] = $cacheFullResultsCustomSuffix;
                    if ($verbose)
                        echo 'Deleting  cache of ' . json_encode($existingIdentifiers) . EOL;
                    if (!$test)
                        $this->RedisManager->call('del', [$existingIdentifiers]);
                }
                //Ok we're done
                return 0;
            }
            else{
                if(!$disableLogging)
                    $this->getLogger($type)->error('Failed to delete items of '.$type.' from table',['identifiers'=>$identifiers]);
                return -1;
            }
        }

        /** Move multiple items (to a different category or object)
         * @param array $items Array of objects (arrays). Each object needs to contain the keys from they type array's "keyColumns",
         *         each key pointing to the value of the desired item to move.
         * @param array $inputs Array of objects (arrays). Each object needs to contain the keys from they type array's "moveColumns" -
         *         the values are the new identifiers.
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         * @param array $params of the form:
         *              'replaceTypeArray' - object, default [] - If set, recursively merges with $this->objectsDetails[$type],
         *                                   potentially replacing things like primary key, expanding / reducing extraToGet, etc.
         *               'disableLogging' - bool, default false. Disables logging for this operation
         * @return int
         * @throws \Exception If the item type is invalid
         */
        function moveItems(array $items, array $inputs, string $type, array $params): int {

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');

            $replaceTypeArray = $params['replaceTypeArray'] ?? [];
            if(!empty($replaceTypeArray) && is_array($replaceTypeArray))
                $typeArray = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($typeArray,$replaceTypeArray,['deleteOnNull'=>true]);

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $disableLogging = $params['disableLogging'] ?? null;
            $useCache = $this->defaultSettingsParams['useCache'] && ($typeArray['useCache'] ?? !empty($typeArray['cacheName']));
            $keyColumns = isset($typeArray['extraKeyColumns']) ? array_merge($typeArray['keyColumns'],$typeArray['extraKeyColumns']) : $typeArray['keyColumns'];
            $childCache = $typeArray['childCache'] ?? [];
            $cacheFullResults = $useCache && !empty($typeArray['allItemsCacheName']) && empty($typeArray['groupByFirstNKeys']);
            $cacheFullResultsCustomSuffix = $params['cacheFullResultsCustomSuffix'] ?? null;
            if($cacheFullResults && $cacheFullResultsCustomSuffix)
                $cacheFullResultsCustomSuffix =  $typeArray['allItemsCacheName'].$cacheFullResultsCustomSuffix;

            if(!isset($typeArray['moveColumns']) || count($typeArray['moveColumns']) < 1){
                if($verbose)
                    echo 'Move columns not set for type!'.EOL;
                return 1;
            }

            $identifiers = [];
            $existingIdentifiers = [];
            $timeNow = (string)time();
            $result = -1;
            $assignments = [];


            foreach($typeArray['moveColumns'] as $columnName=>$columnArr){
                if(isset($inputs[$columnName])){
                    $inputName = $columnArr['inputName'] ?? $columnName;
                    $inputValue = $inputs[$inputName] ?? $inputs[$columnName];
                    $assignments[] = $columnName . ' = ' . ($columnArr['type'] === 'string' ? '\'' . $inputValue . '\'' : $inputValue);
                }
            }

            if(!count($assignments))
                return $result;
            else
                $assignments[] = 'Last_Updated = \'' . $timeNow . '\'';


            foreach($items as $index=>$inputArr){
                $identifier = [];
                //Add the identifier to the existing identifiers - differentiates on whether we have extra key columns or not
                $commonIdentifier = '';
                if(!isset($typeArray['extraKeyColumns'])){
                    foreach($keyColumns as $keyCol){
                        if(!isset($inputArr[$keyCol])){
                            if($verbose)
                                echo 'Input '.$index.' missing identifier column!'.EOL;
                            return 1;
                        }
                        $commonIdentifier .= $inputArr[$keyCol].'/';
                        $identifier[] = [$inputArr[$keyCol], 'STRING'];
                    }

                    $commonIdentifier = substr($commonIdentifier,0,strlen($commonIdentifier)-1);
                    if($useCache)
                        $existingIdentifiers[] = $typeArray['cacheName'] . $commonIdentifier;
                    if(count($childCache))
                        foreach($childCache as $childCollectionCacheName)
                            $existingIdentifiers[] = $childCollectionCacheName . $commonIdentifier;
                }
                else{
                    foreach($typeArray['keyColumns'] as $keyCol){
                        if(!isset($inputArr[$keyCol])){
                            if($verbose)
                                echo 'Input '.$index.' missing identifier column!'.EOL;
                            return 1;
                        }
                        $commonIdentifier .= $inputArr[$keyCol].'/';
                    }
                    foreach($keyColumns as $keyCol){
                        if(!isset($inputArr[$keyCol])){
                            if($verbose)
                                echo 'Input '.$index.' missing identifier column!'.EOL;
                            return 1;
                        }
                        $identifier[] = [$inputArr[$keyCol], 'STRING'];
                    }

                    $commonIdentifier = substr($commonIdentifier,0,strlen($commonIdentifier)-1);
                    if($useCache && !in_array($typeArray['cacheName'].$commonIdentifier,$existingIdentifiers)){
                        $existingIdentifiers[] = $typeArray['cacheName'] . $commonIdentifier;
                        if(count($childCache))
                            foreach($childCache as $childCollectionCacheName)
                                $existingIdentifiers[] = $childCollectionCacheName . $commonIdentifier;
                    }

                }

                $identifier[] = 'CSV';
                $identifiers[] = $identifier;
            }
            if(!count($identifiers))
                return $result;
            else
                $identifiers[] = 'CSV';

            $conditions = [
                [
                    array_merge($keyColumns,['CSV']),
                    $identifiers,
                    'IN'
                ]
            ];

            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix() . $typeArray['tableName'],
                $assignments,
                $conditions,
                array_merge($params,['returnError'=>true])
            );

            if($res === true){
                $this->updateFathers($items,$type,$params);
                if($useCache){
                    if($cacheFullResults)
                        $existingIdentifiers[] = $typeArray['allItemsCacheName'];
                    if($cacheFullResultsCustomSuffix)
                        $existingIdentifiers[] = $cacheFullResultsCustomSuffix;
                    if($verbose)
                        echo 'Deleting  cache of '.json_encode($existingIdentifiers).EOL;
                    if(!$test)
                        $this->RedisManager->call( 'del', [$existingIdentifiers] );
                }

                return 0;
            }
            //This is the code for missing dependencies
            elseif($res === '23000'){
                return -2;
            }
            else{
                if(!$disableLogging)
                    $this->getLogger($type)->error('Failed to move items of '.$type,['identifiers'=>$identifiers]);
                return -1;
            }

        }

    }



}