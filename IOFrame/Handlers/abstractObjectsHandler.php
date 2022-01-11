<?php
namespace IOFrame{
    use IOFrame;
    define('abstractObjectsHandler',true);
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';

    /** Most Handlers in IOFrame follow a specific pattern.
     * This abstract class comes to provide a common set of actions, that have no reason to be implemented on their own every time.
     * It is meant to be expanded upon by its child classes.
     * Note that in some cases, it'd still be better to write a new class from scratch than to extend this.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class abstractObjectsHandler extends IOFrame\abstractDBWithCache{

        /** @var array $validObjectTypes Array of valid object types - e.g. ['test','test2',...]
         * To be set by the inheriting child
         * */
        protected $validObjectTypes = [];

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
         *                                                      'tableName'=> <string, name of the table to join (WITHOUT PREFIX)>
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
         *                      'useCache'=> bool, default true - if set to false, specific object will not use cache
         *                      'cacheName'=> string - cache prefix
         *                      'extendTTL'=> bool, whether to extend item TTL in cache
         *                      'cacheTTL'=> int, custom cache ttl - defaults to abstractDBWithCache value
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
         *                                                                    More info in the SQLHandler class.
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
         *                      'columnFilters' => object of objects, where each object is of the form:
         *                                      <filter name> => [
         *                                          'column' => string|array, name of relevant column, or array of columns to search for a value "IN"
         *                                          'tableName' => string, if set, prepends this table name to the column
         *                                          'filter' => string, one of the filters from the abstract class abstractDBWithCache:
         *                                                      '>','<','=', '!=', 'IN', 'RLIKE' and 'NOT RLIKE'
         *                                                      In case of multiple columns, this is ignored.
         *                                          'default' => if set, will be the default value for this filter
         *                                          'considerNull' => mixed, if passed, this value will be considered "NULL" (since we cannot pass an actual NULL value sometimes, like through the API)
         *                                          'alwaysSend' => if set to true, will always send the filter. Has to have 'default'
         *                                      ],
         *                      'extraToGet' => object of objects, extra meta-data to get when getting multiple items,
         *                                      where each object is of the form:
         *                                      <column name> => [
         *                                          'key' => string, key under which the results will be added to '@'
         *                                          'type' => string, either 'min'/'max' (range values), 'count' (the key doesn't matter here),
         *                                                    'distinct' (get all distinct values) or 'distinct_multiple' (get distinct values from multiple columns)
         *                                          'columns' => array of strings, if the type is 'distinct_multiple', here you specify all relevant columns to get values from
         *                                      ],
         *                      'orderColumns' => array of column names by which it is possible to order the query.
         *                      'groupByFirstNKeys' => int, default 0 - whether to group results by the first n keys (less than the total number of keys).
         *                                             Relevant for one-to-many tables where the first N keys represent a parent, and the last one - a child.
         *                      'autoIncrement' => bool, default false - whether the main identifier auto-increments
         *                  ]
         * */
        protected $objectsDetails =[
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
        protected $timeColumns=[ 'Created' , 'Last_Updated' ];

        /* common filters for all tables - dependant on timeColumns*/
        protected $timeFilters=[
            'createdBefore' => [
                'column' => 'Created',
                'filter' => '<'
            ],
            'createdAfter' => [
                'column' => 'Created',
                'filter' => '>'
            ],
            'changedBefore' => [
                'column' => 'Last_Updated',
                'filter' => '<'
            ],
            'changedAfter' => [
                'column' => 'Last_Updated',
                'filter' => '>'
            ],
        ];

        /* common order columns for all tables - defaults to $commonColumns*/
        protected $timeOrderColumns= [ 'Created' , 'Last_Updated' ];

        /**
         * Basic construction function
         * @param SettingsHandler $settings local settings handler.
         * @param array $params Typical default settings array
         */
        function __construct(IOFrame\Handlers\SettingsHandler $settings, $params = []){

            /* Allows dynamically setting table details at construction.
             * As much as I hate variable variables, this is likely one of the only places where their use is for the best.
             * */
            $dynamicParams = $this->validObjectTypes;
            $additionParams = ['timeFilters','timeColumns','timeOrderColumns'];

            foreach($dynamicParams as $param){
                if(!isset($params[$param]))
                    continue;
                else foreach($this->objectsDetails[$param] as $index => $defaultValue){
                    if(
                        isset($params[$param][$index]) &&
                        (!isset($this->objectsDetails[$param][$index]) || gettype($params[$param][$index]) === gettype($this->objectsDetails[$param][$index]))
                    )
                        $this->objectsDetails[$param][$index] = $params[$param][$index];
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

        /** Updates father tables of child tables that were changed
         * @param array $childInputsArray Array of the child keys
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         * @param array $params
         * @returns bool success on db update, failure otherwise
         * @throws \Exception If the item type is invalid
         */
        function updateFathers(array $childInputsArray,string $type,$params){

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $fatherDetails = isset($typeArray['fatherDetails']) ? $typeArray['fatherDetails'] : [];

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
            $prefix = $this->SQLHandler->getSQLPrefix();
            $numberOfFathers = count($fatherDetails);
            //The columns for our condition
            $tableColumns = [];
            for($i = 0; $i<$keyNumDelta; $i++){
                array_push($tableColumns,$prefix.$fatherDetails[0]['tableName'].'.'.$keyColumns[$i]);
            }
            for($i = $keyNumDelta; $i<count($fatherDetails) + $keyNumDelta; $i++){
                array_push($tableColumns,$prefix.$fatherDetails[$i-$keyNumDelta]['tableName'].'.'.$keyColumns[$i]);
            }
            array_push($tableColumns,'CSV');

            //Set the table columns and get the cache identifiers at the ame time
            $tableKeyConds = [];
            foreach($childInputsArray as $input){
                $oneCond = [];
                $keysSoFar = [];

                for($i = 0; $i<$keyNumDelta; $i++){
                    array_push($oneCond,$input[$keyColumns[$i]]);
                    array_push($keysSoFar,$input[$keyColumns[$i]]);
                }

                for($i = $keyNumDelta; $i<$numberOfFathers+$keyNumDelta; $i++){
                    array_push($oneCond,$input[$keyColumns[$i]]);
                    //Here we take care of the cache identifiers
                    array_push($keysSoFar,$input[$keyColumns[$i]]);
                    if(!empty($fatherDetails[$i-$keyNumDelta]['cacheName'])){
                        $cacheItem = $fatherDetails[$i-$keyNumDelta]['cacheName'].implode('/',$keysSoFar);
                        //Add any cache item not yet in the cache
                        if(!in_array($cacheItem,$affectedCacheIdentifiers))
                            array_push( $affectedCacheIdentifiers, $cacheItem );
                    }
                }

                if(!isset($updateMap[implode('/',$oneCond)])){
                    $updateMap[implode('/',$oneCond)] = true;
                    foreach($oneCond as $index => $val){
                        if(gettype($val) === 'string')
                            $oneCond[$index] = [$val,'STRING'];
                    }
                    array_push($oneCond,'CSV');
                    array_push($tableKeyConds,$oneCond);
                }
            }
            array_push($tableKeyConds,'CSV');

            //Merge everything
            $tableCond = [
                $tableColumns,
                $tableKeyConds,
                'IN'
            ];

            //Now, for each parent after the first one, we need to do some joins
            foreach($fatherDetails as $index => $fatherDetailsArray){

                $tableQuery .= $prefix.$fatherDetailsArray['tableName'];

                array_push($updateArray,$prefix.$fatherDetailsArray['tableName'].'.'.$updateColumn.' = '.time());

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

            $res = $this->SQLHandler->updateTable(
                    $tableQuery,
                    $updateArray,
                    $tableCond,
                    $params
                );

            if($res === true && !empty($affectedCacheIdentifiers)){
                if($verbose)
                    echo 'Deleting cache of '.$type.' parents: '.json_encode($affectedCacheIdentifiers).EOL;
                if(!$test)
                    $this->RedisHandler->call( 'del', [$affectedCacheIdentifiers] );
            }

            return $res;
        }

        /** Get multiple items
         * @param array $items Array of objects (arrays). Each object needs to contain the keys from they type array's "keyColumns",
         *              each key pointing to the value of the desired item to get.
         *              Defaults to [], which searches through all available items and cannot use the cache.
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         * @param array $params of the form:
         *              <valid filter name> - valid filters are found in the type array's "columnFilters" - the param name
         *                                    is the same as the key.
         *              'defaultTableToFilterColumns' - bool, default false. if set prepends table name to filter columns
         *                                              without an explicit 'tableName'
         *              'getAllSubItems' - bool, default false. If true, will get all sub-items and ignore limit even if
         *                                 we are getting all items ($items is [])
         *              --- Usage of the params below disables cache even when searching for specific items ---
         *              'limit' - int, standard SQL parameter
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
        function getItems(array $items, string $type, array $params = []){

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');


            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $hasTimeColumns = isset($params['hasTimeColumns'])? $params['hasTimeColumns'] : ($this->objectsDetails[$type]['hasTimeColumns'] ?? true);
            $getAllSubItems =  isset($params['getAllSubItems']) ? $params['getAllSubItems'] : false;
            $defaultTableToFilterColumns =  isset($params['defaultTableToFilterColumns']) ? $params['defaultTableToFilterColumns'] : false;
            if($getAllSubItems){
                $params['limit'] = null;
            }
            $limit = isset($params['limit'])? $params['limit'] : null;
            $offset = isset($params['offset'])? $params['offset'] : null;
            $orderBy = isset($params['orderBy'])? $params['orderBy'] : null;
            $orderType = isset($params['orderType'])? $params['orderType'] : null;
            $keyColumns = $typeArray['keyColumns'];
            $useCache = isset($typeArray['useCache']) ? $typeArray['useCache'] : !empty($typeArray['cacheName']);
            $extendTTL = isset($typeArray['extendTTL']) ? $typeArray['extendTTL'] : true;
            $cacheTTL = isset($typeArray['cacheTTL']) ? $typeArray['cacheTTL'] : 3600;
            $safeStrColumns = isset($typeArray['safeStrColumns']) ? $typeArray['safeStrColumns'] : [];
            $joinOnGet = isset($typeArray['joinOnGet']) ? $typeArray['joinOnGet'] : [];
            $columnsToGet = isset($typeArray['columnsToGet']) ? $typeArray['columnsToGet'] : [];
            $extraKeyColumns = isset($typeArray['extraKeyColumns']) ? $typeArray['extraKeyColumns'] : [];
            $groupByFirstNKeys = isset($typeArray['groupByFirstNKeys']) ? $typeArray['groupByFirstNKeys'] : 0;
            $orderColumns = $typeArray['orderColumns'];
            $validFilters = $typeArray['columnFilters'];

            if($hasTimeColumns){
                $orderColumns = array_merge($orderColumns,$this->timeOrderColumns);
                $validFilters = array_merge($validFilters,$this->timeFilters);
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
                $retrieveParams['orderBy'] = $orderBy? $orderBy : null;
                $retrieveParams['orderType'] = $orderType? $orderType : 0;
                $retrieveParams['limit'] =  $limit? $limit : null;
                $retrieveParams['offset'] =  $offset? $offset : null;
            }

            $extraDBConditions = [];
            $extraCacheConditions = [];

            foreach($validFilters as $filterParam => $filterArray){

                $cond = null;
                $cacheCond = null;

                if(is_array($filterArray['column']))
                    $filterArray['filter'] = null;

                $prefix = isset($filterArray['tableName'])? $filterArray['tableName'] : ($defaultTableToFilterColumns ? $typeArray['tableName'] : null);
                if($prefix){
                    if(is_array($filterArray['column'])){
                        foreach ($filterArray['column'] as $index => $val)
                            $filterArray['column'][$index] = $prefix.'.'.$filterArray['column'][$index];
                    }
                    else{
                        $filterArray['column'] = $prefix.'.'.$filterArray['column'];
                    }
                }

                if(isset($params[$filterParam])){
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
                                array_push($cond,[$column,'ISNULL']);
                            array_push($cond,'OR');
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
                    array_push($extraCacheConditions,$cacheCond);
                    array_push($extraDBConditions,$cond);
                }

            }

            if($extraCacheConditions!=[]){
                array_push($extraCacheConditions,'AND');
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }
            if($extraDBConditions!=[]){
                array_push($extraDBConditions,'AND');
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            $joinOtherTable = count($joinOnGet) > 0;

            $tableQuery = $items == [] ? $this->SQLHandler->getSQLPrefix().$typeArray['tableName'] : $typeArray['tableName'];
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
                        $joinTableName = $this->SQLHandler->getSQLPrefix().$joinArr['tableName'];
                        $joinAlias = $this->SQLHandler->getSQLPrefix().$joinArr['tableName'];
                    }
                    elseif(gettype($joinArr['tableName']) === 'array'){
                        $joinTableName = $this->SQLHandler->getSQLPrefix().$joinArr['tableName'][0];
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
                                $tableQuery .= $this->SQLHandler->getSQLPrefix().$joinArr['leftTableName'].'.'.$pair[0];
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
                array_push($selectionColumns,$this->SQLHandler->getSQLPrefix().$typeArray['tableName'].'.*');
                foreach($columnsToGet as $columnToGet){
                    if(!empty($columnToGet['expression']))
                        $toGet = $columnToGet['expression'];
                    else{
                        $toGet = '';
                        if(empty($columnToGet['alias']))
                            $toGet .= $this->SQLHandler->getSQLPrefix();
                        $toGet .= $columnToGet['tableName'].'.'.$columnToGet['column'];
                        if(!empty($columnToGet['as']))
                            $toGet .= ' AS '.$columnToGet['as'];
                    }
                    array_push($selectionColumns,$toGet);
                }
            }
            else{
                $selectionColumns = [];
            }

            if($items == []){
                $results = [];
                if($groupByFirstNKeys && !$getAllSubItems)
                    $retrieveParams['groupBy'] = $keyColumns;

                $res = $this->SQLHandler->selectFromTable(
                    $tableQuery,
                    $extraDBConditions,
                    $selectionColumns,
                    $retrieveParams
                );
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
                                $resultArray[$colName] = IOFrame\Util\safeStr2Str($resultArray[$colName]);
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

                        //Get all relevant stuff
                        $selectQuery = '';

                        foreach($typeArray['extraToGet'] as $columnName => $arr){
                            switch($arr['type']){
                                case 'min':
                                case 'max':
                                    $selectQuery .= $this->SQLHandler->selectFromTable(
                                            $tableQuery,
                                            $extraDBConditions,
                                            [($arr['type'] === 'min' ? 'MIN('.$columnName.')': 'MAX('.$columnName.')').' AS Val, "'.$columnName.'" as Type'],
                                            ['justTheQuery'=>true,'test'=>false]
                                        ).' UNION ';
                                    break;
                                case 'count':
                                    $selectQuery .= $this->SQLHandler->selectFromTable(
                                            $tableQuery,
                                            $extraDBConditions,
                                            [(($groupByFirstNKeys && !$getAllSubItems)? 'COUNT(DISTINCT '.implode(',',$keyColumns).')': 'COUNT(*)').' AS Val, "'.$columnName.'" as Type'],
                                            ['justTheQuery'=>true,'test'=>false]
                                        ).' UNION ';
                                    break;
                                case 'distinct':
                                    $selectQuery .= $this->SQLHandler->selectFromTable(
                                            $tableQuery,
                                            $extraDBConditions,
                                            [$columnName.' AS Val, "'.$columnName.'" as Type'],
                                            ['justTheQuery'=>true,'DISTINCT'=>true,'test'=>false]
                                        ).' UNION ';
                                    break;
                                case 'distinct_multiple':
                                    $selectQuery .= '(SELECT DISTINCT Temp_Val AS Val, "'.$columnName.'" as Type FROM (';
                                    foreach ($arr['columns'] as $column)
                                        $selectQuery .= $this->SQLHandler->selectFromTable(
                                                $tableQuery,
                                                $extraDBConditions,
                                                [$column.' AS Temp_Val'],
                                                ['justTheQuery'=>true,'DISTINCT'=>true,'test'=>false]
                                            ).' UNION ';
                                    $selectQuery = substr($selectQuery,0,-7);
                                    $selectQuery .= ') as Meaningless_Alias ORDER BY Val) UNION ';
                                    break;
                            }
                        }
                        $selectQuery = substr($selectQuery,0,strlen($selectQuery) - 7);

                        if($verbose)
                            echo 'Query to send: '.$selectQuery.EOL;

                        $response = $this->SQLHandler->exeQueryBindParam($selectQuery,[],['fetchAll'=>true]);

                        if($response){
                            foreach($response as $arr){
                                $columnName = $arr['Type'];
                                $relevantToGetInfo = $typeArray['extraToGet'][$columnName];
                                $key = $relevantToGetInfo['key'];
                                $type = $relevantToGetInfo['type'];
                                if(!in_array($type,['distinct','distinct_multiple'])){
                                    $results['@'][$key] = $arr['Val'];
                                }
                                else{
                                    if(!isset( $results['@'][$key]))
                                        $results['@'][$key] = [];
                                    array_push( $results['@'][$key],$arr['Val']);
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
                        array_push($retrieveParams['keyColumnPrefixes'],$this->SQLHandler->getSQLPrefix().$typeArray['tableName'].'.');
                    $retrieveParams['pushKeyToColumns'] = false;
                }

                $results = $this->getFromCacheOrDB(
                    $items,
                    $keyColumns,
                    $tableQuery,
                    $typeArray['cacheName'],
                    $selectionColumns,
                    array_merge($retrieveParams,['extraKeyColumns'=>$extraKeyColumns,'groupByFirstNKeys'=>$groupByFirstNKeys,'compareCol'=>!$joinOtherTable,'test'=>$test])
                );

                foreach($results as $index => $res){
                    if(!is_array($res))
                        continue;
                    //Convert safeSTR columns to normal
                    if(!$groupByFirstNKeys)
                        foreach($res as $colName => $colArr){
                            if(in_array($colName,$safeStrColumns))
                                $results[$index][$colName] = IOFrame\Util\safeStr2Str($results[$index][$colName]);
                        }
                    else{
                        foreach($res as $subIndex => $subItemArray){
                            foreach($subItemArray as $colName => $colArr){
                                if(in_array($colName,$safeStrColumns))
                                    $results[$index][$subIndex][$colName] = IOFrame\Util\safeStr2Str($results[$index][$subIndex][$colName]);
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
         *              'override'/'overwrite' - bool, whether to allow overwriting existing items. Defaults to true,.
         *
         * @returns Array|Int, if not creating new auto-incrementing items, array of the form:
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
         * @throws \Exception If the item type is invalid
         *
         *
         */
        function setItems(array $inputs, string $type, array $params = []){

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');

            if(isset($params['overwrite']) && !isset($params['override']))
                $params['override'] = $params['overwrite'];

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $hasTimeColumns = isset($params['hasTimeColumns'])? $params['hasTimeColumns'] : ($this->objectsDetails[$type]['hasTimeColumns'] ?? true);
            $update = isset($params['update'])? $params['update'] : false;
            $override = $update? true : (isset($params['override'])? $params['override'] : true);
            $autoIncrement = isset($typeArray['autoIncrement']) && $typeArray['autoIncrement'];
            $autoIncrementMainKey = !$override && !$update && $autoIncrement;
            $useCache = isset($typeArray['useCache']) ? $typeArray['useCache'] : !empty($typeArray['cacheName']);
            $keyColumns = $typeArray['keyColumns'];
            $safeStrColumns = isset($typeArray['safeStrColumns']) ? $typeArray['safeStrColumns'] : [];
            $extraKeyColumns = isset($typeArray['extraKeyColumns']) ? $typeArray['extraKeyColumns'] : [];
            $groupByFirstNKeys = isset($typeArray['groupByFirstNKeys']) ? $typeArray['groupByFirstNKeys'] : 0;
            $combinedColumns = isset($typeArray['extraKeyColumns']) ? array_merge($typeArray['keyColumns'],$typeArray['extraKeyColumns']) : $typeArray['keyColumns'];

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

            foreach($typeArray['setColumns'] as $colName => $colArr){
                if(
                    $autoIncrementMainKey &&
                    isset($colArr['autoIncrement']) &&
                    $colArr['autoIncrement']
                )
                    continue;
                if(!empty($colArr['onDuplicateKeyColExp']))
                    $onDuplicateKeyColExp[$colName] = $colArr['onDuplicateKeyColExp'];
                array_push($setColumns,$colName);
            }

            if($hasTimeColumns)
                $setColumns = array_merge($setColumns,$this->timeColumns);

            if(!$autoIncrementMainKey){

                foreach($inputs as $index=>$inputArr){

                    $identifier = '';
                    $identifierArr = [];

                    foreach($combinedColumns as $keyCol){
                        $identifier .= $inputArr[$keyCol].'/';
                        array_push($identifierArr,$inputArr[$keyCol]);
                    }

                    $identifier = substr($identifier,0,strlen($identifier)-1);

                    $indexMap[$identifier] = $index;
                    $identifierMap[$index] = $identifier;

                    array_push($identifiers,$identifierArr);
                    if(count($extraKeyColumns) === 0)
                        array_push($itemsToGet,$identifierArr);
                    else{
                        array_pop($identifierArr);
                        array_push($itemsToGet,$identifierArr);
                    }

                    $results[$identifier] = -1;
                }

                if(isset($params['existing']))
                    $existing = $params['existing'];
                else
                    $existing = $this->getItems($itemsToGet, $type, array_merge($params,['updateCache'=>false]));
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
                        $existingArr = $existing[$identifierMap[$index]];
                    }
                    else{
                        $prefix =  explode('/',$identifierMap[$index]);
                        $target = [];
                        for($i=0;$i<$groupByFirstNKeys; $i++)
                            array_push($target,array_pop($prefix));
                        $target = array_reverse($target);
                        $prefix = implode('/',$prefix);
                        $target = implode('/',$target);
                        $existingArr =  isset($existing[$prefix][$target])? $existing[$prefix][$target] : 1;
                    }
                }

                //In this case we are creating an auto-incrementing key, the address does not exist or we couldn't connect to db
                if($autoIncrementMainKey || !is_array($existingArr)){
                    //If we could not connect to the DB, just return because it means we wont be able to connect next
                    if(!$autoIncrementMainKey && $existingArr == -1)
                        return $results;
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
                                $val = IOFrame\Util\str2SafeStr($val);

                            if($colArr['type'] === 'int')
                                $val = (int)$val;
                            elseif($colArr['type'] === 'bool')
                                $val = (bool)$val;

                            if(isset($colArr['considerNull']) && ($val === $colArr['considerNull']) )
                                $val = null;
                            elseif(!isset($colArr['type']) || $colArr['type'] === 'string')
                                $val = [$val,'STRING'];

                            array_push($arrayToSet,$val);
                        }
                        //Add creation and update time
                        if($hasTimeColumns){
                            array_push($arrayToSet,[$timeNow,'STRING']);
                            array_push($arrayToSet,[$timeNow,'STRING']);
                        }

                        if($missingInputs){
                            if(!$autoIncrementMainKey){
                                $results[$identifier] = 3;
                                unset($inputs[$index]);
                                continue;
                            }
                            else{
                                $results = -3;
                                return $results;
                            }
                        }

                        //Add the resource to the array to set
                        array_push($itemsToSet,$arrayToSet);
                        array_push($inputsThatPassed,$inputArr);
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
                        elseif(isset($inputArr[$colName]) && $inputArr[$colName] !== null){
                            if(
                                !empty($colArr['jsonObject'])&&
                                IOFrame\Util\is_json($inputArr[$colName]) &&
                                IOFrame\Util\is_json($existingVal)
                            ){
                                $inputJSON = json_decode($inputArr[$colName],true);
                                $existingJSON = json_decode($existingVal,true);
                                if($inputJSON == null)
                                    $inputJSON = [];
                                if($existingJSON == null)
                                    $existingJSON = [];
                                $val =
                                    json_encode(IOFrame\Util\array_merge_recursive_distinct($existingJSON,$inputJSON,['deleteOnNull'=>true]));
                                if($val == '[]')
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
                            $val = IOFrame\Util\str2SafeStr($val);

                        if($colArr['type'] === 'int')
                            $val = (int)$val;
                        elseif($colArr['type'] === 'bool')
                            $val = (bool)$val;

                        if(isset($colArr['considerNull']) && ($val === $colArr['considerNull']) )
                            $val = null;
                        elseif(!isset($colArr['type']) || $colArr['type'] === 'string')
                            $val = [$val,'STRING'];

                        array_push($arrayToSet,$val);

                    }

                    //Add creation and update time
                    if($hasTimeColumns){
                        $created = isset($typeArray['extraKeyColumns'])?
                            $existingArr['Created'] :
                            $existingArr['Created'];

                        array_push($arrayToSet,[$created,'STRING']);
                        array_push($arrayToSet,[$timeNow,'STRING']);
                    }

                    //Add the resource to the array to set
                    array_push($itemsToSet,$arrayToSet);
                    array_push($inputsThatPassed,$inputArr);
                }

                //Add the identifier to the existing identifiers - differentiates on whether we have extra key columns or not
                if(count($extraKeyColumns) == 0 && $identifier)
                    array_push($existingIdentifiers,$typeArray['cacheName'].$identifier);
                elseif($identifier && !in_array($identifier,$existingIdentifiers))
                    array_push($existingIdentifiers,$typeArray['cacheName'].$identifier);

            }

            //If we got nothing to set, return
            if($itemsToSet==[])
                return $results;

            $res = $this->SQLHandler->insertIntoTable(
                $this->SQLHandler->getSQLPrefix().$typeArray['tableName'],
                $setColumns,
                $itemsToSet,
                array_merge($params,['returnError'=>true,'onDuplicateKey'=>!$autoIncrementMainKey,'returnRows'=>$autoIncrementMainKey,'onDuplicateKeyColExp'=>$onDuplicateKeyColExp])
            );

            if(!$autoIncrementMainKey){
                //This means we either succeeded or got an error code returned
                if($res === true){

                    //TODO log failure
                    if(!$this->updateFathers($inputsThatPassed,$type,$params)){

                    };

                    foreach($identifiers as $identifier){
                        $identifier = implode('/',$identifier);
                        if($results[$identifier] == -1)
                            $results[$identifier] = 0;
                    }
                    //If we succeeded, set results to success and remove them from cache
                    if($existingIdentifiers != [] && $useCache){
                        if(count($existingIdentifiers) == 1)
                            $existingIdentifiers = $existingIdentifiers[0];

                        if($verbose)
                            echo 'Deleting objects of type "'.$type.'" '.json_encode($existingIdentifiers).' from cache!'.EOL;

                        if(!$test)
                            $this->RedisHandler->call('del',[$existingIdentifiers]);
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
                else
                    return $res;
            }
            else{
                //This means we either succeeded or got an error code returned
                if($res === '23000')
                    return -2;
                //This is the code for missing dependencies
                elseif($res === true)
                    return -1;
                else{
                    //TODO log failure
                    if(!$this->updateFathers($inputsThatPassed,$type,$params)){

                    };
                    //If we succeeded, set results to success and remove them from cache
                    if($existingIdentifiers != [] && $useCache){
                        if(count($existingIdentifiers) == 1)
                            $existingIdentifiers = $existingIdentifiers[0];
                        if($verbose)
                            echo 'Deleting objects of type "'.$type.'" '.json_encode($existingIdentifiers).' from cache!'.EOL;

                        if(!$test)
                            $this->RedisHandler->call('del',[$existingIdentifiers]);
                    }
                    return $res;
                }
            }

            return $results;
        }

        /** Delete multiple items
         * @param array $items Array of objects (arrays). Each object needs to contain the keys from they type array's "keyColumns",
         *              each key pointing to the value of the desired item to get.
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         * @param array $params
         *              <valid filter name> - valid filters are found in the type array's "columnFilters" - the param name
         *                                    is the same as the key.
         * @return Int codes:
         *          -1 server error (would be the same for all)
         *           0 success (does not check if items do not exist)
         *
         * @throws \Exception If the item type is invalid
         *
         */
        function deleteItems(array $items, string $type, array $params){

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $hasTimeColumns = isset($params['hasTimeColumns'])? $params['hasTimeColumns'] : ($this->objectsDetails[$type]['hasTimeColumns'] ?? true);
            $useCache = isset($typeArray['useCache']) ? $typeArray['useCache'] : !empty($typeArray['cacheName']);
            $keyColumns = isset($typeArray['extraKeyColumns']) ? array_merge($typeArray['keyColumns'],$typeArray['extraKeyColumns']) : $typeArray['keyColumns'];
            $childCache = isset($typeArray['childCache']) ? $typeArray['childCache'] : [];
            $validFilters = $typeArray['columnFilters'];
            $groupByFirstNKeys = isset($typeArray['groupByFirstNKeys']) ? $typeArray['groupByFirstNKeys'] : 0;

            if($hasTimeColumns){
                $validFilters = array_merge($validFilters,$this->timeFilters);
            }

            $existingIdentifiers = [];
            $identifiers = [];

            foreach($items as $index=>$inputArr){
                $identifier = [];
                //Add the identifier to the existing identifiers - differentiates on whether we have extra key columns or not
                if(!$groupByFirstNKeys){
                    $commonIdentifier = '';
                    foreach($keyColumns as $keyCol){
                        $commonIdentifier .= $inputArr[$keyCol].'/';
                        array_push($identifier,[$inputArr[$keyCol],'STRING']);
                    }
                    $commonIdentifier = substr($commonIdentifier,0,strlen($commonIdentifier)-1);
                    array_push($existingIdentifiers,$typeArray['cacheName'].$commonIdentifier);
                    if(count($childCache))
                        foreach($childCache as $childCollectionCacheName)
                            array_push($existingIdentifiers,$childCollectionCacheName.$commonIdentifier);
                }
                else{
                    $commonIdentifier = '';
                    for($i=0; $i<$groupByFirstNKeys; $i++)
                        $commonIdentifier .= $inputArr[$typeArray['keyColumns'][$i]].'/';
                    $commonIdentifier = substr($commonIdentifier,0,strlen($commonIdentifier)-1);
                    if(!in_array($typeArray['cacheName'].$commonIdentifier,$existingIdentifiers)){
                        array_push($existingIdentifiers,$typeArray['cacheName'].$commonIdentifier);
                        if(count($childCache))
                            foreach($childCache as $childCollectionCacheName)
                                array_push($existingIdentifiers,$childCollectionCacheName.$commonIdentifier);
                    }

                    foreach($keyColumns as $keyCol){
                        array_push($identifier,[$inputArr[$keyCol],'STRING']);
                    }
                }
                array_push($identifiers,$identifier);
            }

            if(count($identifiers) === 0)
                return 1;
            else
                array_push($identifiers,'CSV');

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
                    array_push($DBConditions,$cond);
                }
                elseif(isset($filterArray['default']) && isset($filterArray['alwaysSend'])){
                    $cond = [$filterArray['column'],[$filterArray['default'],'STRING'],$filterArray['filter']];
                    array_push($DBConditions,$cond);
                }
            }

            array_push($DBConditions,'AND');

            $res = $this->SQLHandler->deleteFromTable(
                $this->SQLHandler->getSQLPrefix().$typeArray['tableName'],
                $DBConditions,
                $params
            );

            if($res){
                //TODO log failure
                if(!$this->updateFathers($items,$type,$params)){

                };
                if($useCache) {
                    if ($verbose)
                        echo 'Deleting  cache of ' . json_encode($existingIdentifiers) . EOL;
                    if (!$test)
                        $this->RedisHandler->call('del', [$existingIdentifiers]);
                }
                //Ok we're done
                return 0;
            }
            else
                return -1;
        }

        /** Move multiple items (to a different category or object)
         * @param Array $items  Array of objects (arrays). Each object needs to contain the keys from they type array's "keyColumns",
         *         each key pointing to the value of the desired item to move.
         * @param Array $inputs Array of objects (arrays). Each object needs to contain the keys from they type array's "moveColumns" -
         *         the values are the new identifiers.
         * @param string $type Type of items. See the item objects at the top of the class for the parameters of each type.
         * @param array $params of the form:
         *
         * @returns Int, one of the following codes:
         *      -2 dependency error
         *      -1 db error
         *      0 success
         *      1 input error
         *
         * @throws \Exception If the item type is invalid
         */
        function moveItems(array $items, array $inputs, string $type, array $params){

            if(in_array($type,$this->validObjectTypes))
                $typeArray = $this->objectsDetails[$type];
            else
                throw new \Exception('Invalid object type!');

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $useCache = isset($typeArray['useCache']) ? $typeArray['useCache'] : !empty($typeArray['cacheName']);
            $keyColumns = isset($typeArray['extraKeyColumns']) ? array_merge($typeArray['keyColumns'],$typeArray['extraKeyColumns']) : $typeArray['keyColumns'];
            $childCache = isset($typeArray['childCache']) ? $typeArray['childCache'] : [];

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
                    array_push($assignments, $columnName.' = '.($columnArr['type'] === 'string' ? '\''.$inputValue.'\'' : $inputValue) );
                }
            }

            if(!count($assignments))
                return $result;
            else
                array_push($assignments,'Last_Updated = \''.$timeNow.'\'');


            foreach($items as $index=>$inputArr){
                $identifier = [];
                //Add the identifier to the existing identifiers - differentiates on whether we have extra key columns or not
                if(!isset($typeArray['extraKeyColumns'])){

                    $commonIdentifier = '';

                    foreach($keyColumns as $keyCol){
                        if(!isset($inputArr[$keyCol])){
                            if($verbose)
                                echo 'Input '.$index.' missing identifier column!'.EOL;
                            return 1;
                        }
                        $commonIdentifier .= $inputArr[$keyCol].'/';
                        array_push($identifier,[$inputArr[$keyCol],'STRING']);
                    }

                    $commonIdentifier = substr($commonIdentifier,0,strlen($commonIdentifier)-1);
                    array_push($existingIdentifiers,$typeArray['cacheName'].$commonIdentifier);
                    if(count($childCache))
                        foreach($childCache as $childCollectionCacheName)
                            array_push($existingIdentifiers,$childCollectionCacheName.$commonIdentifier);
                }
                else{

                    $commonIdentifier = '';

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
                        array_push($identifier,[$inputArr[$keyCol],'STRING']);
                    }

                    $commonIdentifier = substr($commonIdentifier,0,strlen($commonIdentifier)-1);
                    if(!in_array($typeArray['cacheName'].$commonIdentifier,$existingIdentifiers)){
                        array_push($existingIdentifiers,$typeArray['cacheName'].$commonIdentifier);
                        if(count($childCache))
                            foreach($childCache as $childCollectionCacheName)
                                array_push($existingIdentifiers,$childCollectionCacheName.$commonIdentifier);
                    }

                }

                array_push($identifier,'CSV');
                array_push($identifiers,$identifier);
            }
            if(!count($identifiers))
                return $result;
            else
                array_push($identifiers,'CSV');

            $conditions = [
                [
                    array_merge($keyColumns,['CSV']),
                    $identifiers,
                    'IN'
                ]
            ];

            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$typeArray['tableName'],
                $assignments,
                $conditions,
                array_merge($params,['returnError'=>true])
            );

            if($res === true){
                //TODO log failure
                if(!$this->updateFathers($items,$type,$params)){

                };
                if($useCache){
                    if($verbose)
                        echo 'Deleting  cache of '.json_encode($existingIdentifiers).EOL;
                    if(!$test)
                        $this->RedisHandler->call( 'del', [$existingIdentifiers] );
                }

                return 0;
            }
            //This is the code for missing dependencies
            elseif($res === '23000'){
                return -2;
            }
            else
                return -1;

        }

    }



}