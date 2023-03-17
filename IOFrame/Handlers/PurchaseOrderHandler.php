<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('PurchaseOrderHandler',true);
    if(!defined('abstractObjectsHandler'))
        require 'abstractObjectsHandler.php';

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

    class PurchaseOrderHandler extends IOFrame\abstractObjectsHandler
    {

        /**
         * @var string The name of the archive table (for when we archive orders)
         */
        protected $archiveTable = null;

        /** Standard constructor
         *
         * Constructs an instance of the class, getting the main settings file and an existing DB connection, or generating
         * such a connection itself.
         *
         * @param object $settings The standard settings object
         * @param array $params - All parameters share the name/type of the class variables
         * */
        function __construct(SettingsHandler $settings, array $params = []){

            $prefix = $params['SQLHandler']->getSQLPrefix();

            if(isset($params['archiveTable']))
                $this->archiveTable = $params['archiveTable'];

            $this->validObjectTypes = ['default-orders','default-orders-users'];

            $this->objectsDetails = [
                'default-orders' => [
                    'tableName' => 'DEFAULT_ORDERS',
                    'cacheName'=> 'default_order_',
                    'keyColumns' => ['ID'],
                    'columnsToGet' => [
                        [
                            'expression' => '
                            (SELECT GROUP_CONCAT(CONCAT(User_ID,"/",Relation_Type))
                             FROM '.$prefix.'DEFAULT_USERS_ORDERS
                             WHERE
                                '.$prefix.'DEFAULT_USERS_ORDERS.Order_ID = '.$prefix.'DEFAULT_ORDERS.ID
                             ) AS "Order_Users"
                             ',
                        ]
                    ],
                    'setColumns' => [
                        'ID' => [
                            'type' => 'int',
                            'autoIncrement' => true
                        ],
                        'Order_Info' => [
                            'type' => 'string',
                            'jsonObject' => true,
                            'default' => null
                        ],
                        'Order_History' => [
                            'type' => 'string',
                            'jsonObject' => true,
                            'function'=>function($context){
                                $currentHistory = json_decode($context['existingArray']['Order_History'] ?? '[]',true);
                                $changes = [
                                    'Last_Updated'=>time()
                                ];
                                foreach($context['typeArray']['setColumns'] as $possibleInput =>$details){
                                    if(isset($context['inputArray'][$possibleInput]))
                                        $changes[$possibleInput] = $context['inputArray'][$possibleInput];
                                }
                                array_push($currentHistory,$changes);
                                return json_encode($currentHistory);
                            }
                        ],
                        'Order_Type' => [
                            'type' => 'string',
                            'default' => null
                        ],
                        'Order_Status' => [
                            'type' => 'string',
                            'default' => null
                        ]
                    ],
                    'lockColumns' => [
                        'lock' => 'Session_Lock',
                        'timestamp' => 'Locked_At',
                    ],
                    'columnFilters' => [
                        'typeIs' => [
                            'column' => 'Order_Type',
                            'filter' => '='
                        ],
                        'typeIsNot' => [
                            'column' => 'Order_Type',
                            'filter' => '!='
                        ],
                        'typeIn' => [
                            'column' => 'Order_Type',
                            'filter' => 'IN'
                        ],
                        'statusIs' => [
                            'column' => 'Order_Status',
                            'filter' => '='
                        ],
                        'statusIsNot' => [
                            'column' => 'Order_Status',
                            'filter' => '!='
                        ],
                        'statusIn' => [
                            'column' => 'Order_Status',
                            'filter' => 'IN'
                        ],
                        'usersIn' => [
                            'function' => function($context){
                                $itemIn = $context['params']['usersIn'] ?? null;
                                if(!$itemIn)
                                    return false;
                                $relationsIn = $context['params']['userRelationsIn'] ?? null;
                                $prefix = $context['SQLHandler']->getSQLPrefix();
                                $baseTableName = $prefix.'DEFAULT_ORDERS';
                                $tableName = $prefix.'DEFAULT_USERS_ORDERS';
                                $itemsArray = $itemIn;
                                array_push($itemsArray,'CSV');
                                $foreignCond = [
                                    ['User_ID'],
                                    $itemsArray,
                                    'IN'
                                ];
                                if($relationsIn){
                                    foreach ($relationsIn as $index=>$item)
                                        $relationsIn[$index] = [$item,'STRING'];
                                    array_push($relationsIn,'CSV');
                                    $foreignCond = [
                                        $foreignCond,
                                        [
                                            ['Relation_Type'],
                                            $relationsIn,
                                            'IN'
                                        ],
                                        'AND'
                                    ];
                                }

                                $cond = $context['SQLHandler']->selectFromTable(
                                    $tableName,
                                    $foreignCond,
                                    [$tableName.'.Order_ID'],
                                    ['justTheQuery'=>true,'useBrackets'=>true]
                                );
                                return [
                                    $baseTableName.'.ID',
                                    [$cond,'ASIS'],
                                    'IN'
                                ];
                            }
                        ],
                        'userRelationsIn' => [
                            'function' => function($context){
                                //This merges with usersIn if it's passed
                                if(!empty($context['params']['usersIn']))
                                    return false;
                                $itemIn = $context['params']['userRelationsIn'] ?? null;
                                if(!$itemIn)
                                    return false;
                                $prefix = $context['SQLHandler']->getSQLPrefix();
                                $baseTableName = $prefix.'DEFAULT_ORDERS';
                                $tableName = $prefix.'DEFAULT_USERS_ORDERS';
                                $itemsArray = $itemIn;
                                foreach ($itemsArray as $index=>$item)
                                    $itemsArray[$index] = [$item,'STRING'];
                                array_push($itemsArray,'CSV');
                                $cond = $context['SQLHandler']->selectFromTable(
                                    $tableName,
                                    [
                                        ['Relation_Type'],
                                        $itemsArray,
                                        'IN'
                                    ],
                                    [$tableName.'.Order_ID'],
                                    ['justTheQuery'=>true,'useBrackets'=>true]
                                );
                                return [
                                    $baseTableName.'.ID',
                                    [$cond,'ASIS'],
                                    'IN'
                                ];
                            }
                        ],
                        'noUserRelationsIn' => [
                            'function' => function($context){
                                $itemIn = $context['params']['noUserRelationsIn'] ?? null;
                                if(!$itemIn)
                                    return false;
                                $prefix = $context['SQLHandler']->getSQLPrefix();
                                $baseTableName = $prefix.'DEFAULT_ORDERS';
                                $tableName = $prefix.'DEFAULT_USERS_ORDERS';
                                $itemsArray = $itemIn;
                                foreach ($itemsArray as $index=>$item)
                                    $itemsArray[$index] = [$item,'STRING'];
                                array_push($itemsArray,'CSV');
                                $cond = $context['SQLHandler']->selectFromTable(
                                    $tableName,
                                    [
                                        ['Relation_Type'],
                                        $itemsArray,
                                        'IN'
                                    ],
                                    [$tableName.'.Order_ID'],
                                    ['justTheQuery'=>true,'useBrackets'=>true]
                                );
                                return [
                                    $baseTableName.'.ID',
                                    [$cond,'ASIS'],
                                    'NOT IN'
                                ];
                            }
                        ],
                    ],
                    'orderColumns' => ['ID','Order_Status','Order_Type'],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                    ],
                    'autoIncrement' => true,
                ],
                'default-orders-users'=>[
                    'tableName' => 'DEFAULT_USERS_ORDERS',
                    'cacheName'=> 'default_users_orders_',
                    'extendTTL'=> false,
                    'fatherDetails'=>[
                        [
                            'tableName' => 'DEFAULT_ORDERS',
                            'cacheName' => 'default_order_'
                        ]
                    ],
                    'keyColumns' => ['Order_ID','User_ID'],
                    'setColumns' => [
                        'Order_ID' => [
                            'type' => 'int',
                            'required' => true
                        ],
                        'User_ID' => [
                            'type' => 'int',
                            'required' => true
                        ],
                        'Relation_Type' => [
                            'type' => 'string'
                        ],
                        'Meta' => [
                            'type' => 'string',
                            'jsonObject' => true,
                            'considerNull' => '@',
                            'default' => null
                        ]
                    ],
                    'moveColumns' => [
                    ],
                    'columnFilters' => [
                        'orderIn' => [
                            'column' => 'Order_ID',
                            'filter' => 'IN'
                        ],
                        'usersIn' => [
                            'column' => 'User_ID',
                            'filter' => 'IN'
                        ],
                        'userRelationsIn' => [
                            'column' => 'Relation_Type',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                    ],
                    'orderColumns' => []
                ],
            ];

            parent::__construct($settings,$params);
        }

        /** Gets a single order by ID.
         * @param int $orderID Orders ID
         * @param array $params
         *          'getUsers' - bool, default true - whether to get specific users (and their relations) to the order.
         * @return int|array:
         *          codes:
         *              -1 - failed to reach the db
         *               1 - order does not exist
         *          array: JSON encoded DB array of the order
         * */
        function getOrder(int $orderID,$params = []){
            $getUsers = isset($params['getUsers'])? $params['getUsers'] : true;

            $res = $this->getOrders([$orderID],$params)[$orderID];

            if(!is_array($res))
                return $res;

            if($getUsers){
                $res['users'] = $this->getItems([],'default-orders-users',array_merge($params,['orderIn'=>[$orderID],'usersIn'=>null,'userRelationsIn'=>null]));
            }

            return $res;
        }

        /** Gets multiple orders by IDs, or just all the orders.
         * */
        function getOrders(array $orderIDs = [],$params = []){
            return $this->getItems($orderIDs,'default-orders',$params);
        }

        /** Creates or updates a single Order
         *
         * @param mixed $id ID of the Order
         * @param array $inputs each object of the form:
         *                      'id' => int, default null, required when not creating new orders.
         *                      'orderInfo' => string, default null - a JSON encoded object to be set. Will be merged with
         *                                     the existing value (if it exists) using array_merge_recursive_distinct, with
         *                                     'deleteOnNull' being true.
         *                      'orderType' => string, default null - Potential identifier of the order type.
         *                      'orderStatus' => string, default null - Potential identifier of the order status.
         * @param array $params same as setItems
         *
         * @returns  array|int as in setItems
         */
        function setOrder(array $inputs, array $params = []){
            $tempInputs = [
                'ID'=>$inputs['id']??null,
                'Order_Info'=>$inputs['orderInfo']??null,
                'Order_Type'=>$inputs['orderType']??null,
                'Order_Status'=>$inputs['orderStatus']??null
            ];
            $res = $this->setOrders([$tempInputs],$params);
            return $inputs['id'] ? $res[$inputs['id']] : $res;
        }

        /** Sets multiple orders, by IDs.
         * @param array $inputs Inputs of the form:
         *              [<id>,<$inputs from setOrder>, <another id>,<$inputs from setOrder>, ...]
         * @param array $params same as setItems
         * @return array|int as in setItems
         */
         function setOrders(array $inputs, $params = []){
             return $this->setItems($inputs,'default-orders',$params);
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
         *           'typeIn'           => string[], default null - filter for order types
         *           'statusIn'         => string[], default null - filter for order statuses
         *           'createdBefore'    => int, default null - only archive orders created before this timestamp
         *           'createdAfter'     => int, default null - only archive orders created before this timestamp
         *           'updatedBefore'    => int, default null - only archive orders updated before this timestamp
         *           'updatedAfter'     => int, default null - only archive orders updated before this timestamp
         *           'IDFrom'           => int, default null - only archive orders with ID larger than this
         *           'lastArchivedID'   => RESERVED - SHOULD NOT be passed by the initial function caller.
         *           'iteration'        => RESERVED - SHOULD NOT be passed by the initial function caller.
         *
         * @return array Object of the form:
         *          [
         *          'lastArchivedID'=> int, ID of the last archived order, can be -1 if nothing was deleted yet
         *          'codeOrigin'   => string, 'backupTable', 'getOrders', 'timeout', 'deleteArchived' or 'noTable'
         *          'code'         => int,  0 if we stopped naturally or reached repeatToLimit,
         *                                  OR -1 with origin 'noTable' if we didn't define an archive table
         *                                  OR -1 if the order deletion function threw the code
         *                                  OR the code from backupTable()/getOrders() if we stopped cause that function threw the error code.
         *          ]
         *
         */
         function archiveOrders(array $orderIDs = [], $params = []){
             $typeArray = $this->objectsDetails['default-orders'];
             if(!$this->archiveTable){
                 return [
                     'codeOrigin'=>'noTable',
                     'code'=>-1
                 ];
             }

             $test = $params['test']?? false;
             $verbose = $params['verbose'] ?? $test;
             $deleteArchived = isset($params['deleteArchived'])? $params['deleteArchived'] : true;
             $repeatToLimit = isset($params['repeatToLimit'])? $params['repeatToLimit'] : true;
             $timeout = isset($params['timeout'])? $params['timeout'] : 20;
             $iteration = isset($params['iteration'])? $params['iteration'] : 1;
             $lastArchivedID = isset($params['lastArchivedID'])? $params['lastArchivedID'] : -1;
             $limit =  isset($params['limit'])? $params['limit'] : 1000;
             $typeIn = isset($params['typeIn'])? $params['typeIn'] : [];
             $statusIn = isset($params['statusIn'])? $params['statusIn'] : [];
             $createdBefore = isset($params['createdBefore'])? $params['createdBefore'] : null;
             $createdAfter = isset($params['createdAfter'])? $params['createdAfter'] : null;
             $updatedBefore = isset($params['updatedBefore'])? $params['updatedBefore'] : null;
             $updatedAfter = isset($params['updatedAfter'])? $params['updatedAfter'] : null;
             $IDFrom = isset($params['IDFrom'])? $params['IDFrom'] : null;
             $startTime = time();
             $useCache = isset($typeArray['useCache']) ? $typeArray['useCache'] : !empty($typeArray['cacheName']);
             $results = [
                 'lastArchivedID'=> $lastArchivedID,
                 'codeOrigin'=> '',
                 'code'=> 0
             ];

             //Handle limit related stuff - also the recursion stop condition
             $offset = ($iteration-1) * 1000;
             $limit -= $offset;
             if($limit<1)
                 return $results;
             $limit = min(1000, $limit);

             //Handle the time limit part
             if($timeout < 0){
                 $results['codeOrigin'] = 'timeout';
                 return $results;
             }

             $filters = [];
             if(!empty($typeIn))
                 array_push($filters,[
                     'Order_Type',
                     array_merge($typeIn,'CSV'),
                     'IN'
                 ]);
             if(!empty($statusIn))
                 array_push($filters,[
                     'Order_Status',
                     array_merge($statusIn,'CSV'),
                     'IN'
                 ]);
             if($createdBefore)
                 array_push($filters,[
                     'Created',
                     $createdBefore,
                     '<'
                 ]);
             if($createdAfter)
                 array_push($filters,[
                     'Created',
                     $createdBefore,
                     '>'
                 ]);
             if($updatedBefore)
                 array_push($filters,[
                     'Last_Updated',
                     $updatedBefore,
                     '<'
                 ]);
             if($updatedAfter)
                 array_push($filters,[
                     'Last_Updated',
                     $updatedBefore,
                     '>'
                 ]);
             if($IDFrom)
                 array_push($filters,[
                     'ID',
                     $updatedBefore,
                     '>'
                 ]);

             //Check whether any orders are left
             $existing = $this->getOrders($orderIDs, array_merge($params,['limit'=>1,'offset'=>$offset],$filters));

             if(isset($existing['@']))
                unset($existing['@']);
             else{
                 if($verbose)
                     echo 'Could not get orders at iteration '.$iteration.EOL;
                 $results['codeOrigin'] = 'getOrders';
                 $results['code'] = -1;
                 return $results;
             }

             //If there are no existing IDs left, maybe because requested ones do not exist, we are done.
             if(count($existing) ===0)
                 return $results;
             //Else, check to see whether there was an error connecting to the DB
             else
                 foreach($existing as $orderArr){
                 if($orderArr === -1){
                     if($verbose)
                         echo 'Could not get orders at iteration '.$iteration.EOL;
                     $results['codeOrigin'] = 'getOrders';
                     $results['code'] = -1;
                     return $results;
                 }
             }

             //Add order IDs to filters
             if(count($orderIDs))
                 array_push($filters,[
                     'ID',
                     array_merge($orderIDs,'CSV'),
                     'IN'
                 ]);

             $query = 'INSERT IGNORE INTO '.$this->SQLHandler->getSQLPrefix().$this->archiveTable.' '.
                 $this->SQLHandler->selectFromTable(
                     $this->SQLHandler->getSQLPrefix().$typeArray['tableName'],
                     $filters,
                     [],
                     ['justTheQuery'=>true,'limit'=>$limit,'offset'=>$offset,'orderBy'=>'ID','orderType'=>0]
                 );
             if($verbose)
                 echo 'Query to send: '.$query.EOL;
             $backUp = $test ? true : $this->SQLHandler->exeQueryBindParam($query);

             if($backUp!== true){
                 $results['codeOrigin'] = 'backupTable';
                 $results['code'] = $backUp;
                 return $results;
             }
             else{
                 $results['code'] = 0;
                 $results['lastArchivedID'] = (int)($this->SQLHandler->selectFromTable(
                     $this->SQLHandler->getSQLPrefix().$this->archiveTable,
                     $filters,
                     [],
                     ['verbose'=>$verbose,'limit'=>1,'offset'=>0,'orderBy'=>'ID','orderType'=>1]
                 )[0]['ID']??-1);
                 $params['lastArchivedID'] = $results['lastArchivedID'];
             }

             //Delete archived orders if needed TODO
             if($deleteArchived){
                 //Get IDs of relevant orders
                 $IDs = $this->SQLHandler->selectFromTable(
                     $this->SQLHandler->getSQLPrefix().$this->archiveTable,
                     $filters,
                     ['ID'],
                     ['verbose'=>$verbose,'limit'=>$limit,'offset'=>0,'orderBy'=>'ID','orderType'=>1]
                 );
                 if(is_array($IDs)){
                     $temp = [];
                     foreach ($IDs as $ID)
                         array_push($temp,$ID['ID']);
                     array_push($temp,'CSV');
                     //Try to delete
                     $deleted = $this->SQLHandler->deleteFromTable(
                         $this->SQLHandler->getSQLPrefix().$typeArray['tableName'],
                         [
                             'ID',
                             $temp,
                             'IN'
                         ],
                         ['test'=>$test,'verbose'=>$verbose]
                     );
                     //If we didn't delete orders properly, return
                     if($deleted !== true){
                         $results['codeOrigin'] = 'deleteArchived';
                         $results['code'] = $deleted;
                         return $results;
                     }
                     //Delete orders from cache
                     elseif($useCache){
                         if(count($temp) >= 2)
                             array_pop($temp);

                         foreach ($temp as $i=>$o){
                             $temp[$i] = $typeArray['cacheName'].$o;
                         }

                         if($verbose)
                             echo 'Deleting orders '.json_encode($temp).' from cache!'.EOL;

                         if(!$test){
                             $this->RedisHandler->call('del',[$temp]);
                         }
                     }
                 }
                 else{
                     $results['codeOrigin'] = 'deleteArchived';
                     $results['code'] = -1;
                     return $results;
                 }
             }

             //Repeat if we got anything left.
             if($repeatToLimit){
                 $params['iteration'] = $iteration+1;
                 $params['timeout'] = $timeout - (time() - $startTime);
                 return $this->archiveOrders($orderIDs,$params);
             }

             return $results;
         }

        /** Binds users to an order
         * @param mixed $id ID of the Order
         * @param array $inputs each object of the form:
         *                      'user' => int, user ID
         *                      'order' => int, order ID
         *                      'relation' => string, default null - relation between user and order.
         *                      'meta' => string, default null - meta information
         * @param array $params same as setItems
         *
         * @returns  array|int as in setItems
         */
        function setOrderUsers(int $order, array $inputs, array $params = []){
            $tempInputs = [];
            $conversion = [
                'user'=>[
                    'col'=>'User_ID'
                ],
                'relation'=>[
                    'col'=>'Relation_Type',
                    'default'=>null
                ],
                'meta'=>[
                    'col'=>'Meta',
                    'default'=>null
                ]
            ];
            foreach ($inputs as $index => $arr){
                $temp = [
                    'Order_ID'=>$order
                ];
                foreach ($conversion as $input=>$inputArr){
                    $temp[$inputArr['col']] = $arr[$input] ?? $inputArr['default'];
                }
                array_push($tempInputs,$temp);
            }
            return $this->setOrdersUsers($tempInputs,$params);
        }

        /** Assigns orders <=> users.
         * @param array $inputs Inputs of the form:
         *              [<id>,<$inputs from setOrder>, <another id>,<$inputs from setOrder>, ...]
         * @param array $params same as setItems
         * @return array|int as in setItems
         * */
        function setOrdersUsers(array $inputs = [],array $params = []){
            return $this->setItems($inputs,'default-orders-users',$params);
        }

        /** Deletes users of an order.
         * @param array $inputs Inputs of the form:
         *              [<id>,<$inputs from setOrder>, <another id>,<$inputs from setOrder>, ...]
         * @param array $params same as setItems
         * @return array|int as in setItems
         * */
        function deleteOrderUsers(int $order, array $userIDs = [],array $params = []){
            $tempInputs = [];
            foreach ($userIDs as $id){
                array_push($tempInputs,[
                    'Order_ID'=>$order,
                    'User_ID'=>$id,
                ]);
            }
            return $this->deleteOrdersUsers($tempInputs,$params);
        }

        /** Deletes orders <=> users.
         * @param array $inputs Inputs of the form:
         *              [<id>,<$inputs from setOrder>, <another id>,<$inputs from setOrder>, ...]
         * @param array $params same as setItems
         * @return array|int as in setItems
         * */
        function deleteOrdersUsers(array $inputs,array $params = []){
            return $this->deleteItems($inputs,'default-orders-users',$params);
        }

    }
}