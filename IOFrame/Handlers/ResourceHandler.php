<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('ResourceHandler',true);
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';
    if(!defined('UploadHandler'))
        require 'UploadHandler.php';
    if(!defined('safeSTR'))
        require __DIR__ . '/../Util/safeSTR.php';

    /*  This class manages resources.
     *  Ranges from uploading/deleting/viewing images, creating image galleries, managing CSS/JS links and more.
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class ResourceHandler extends IOFrame\abstractDBWithCache
    {

        protected $siteSettings = null;
        protected $resourceSettings = null;

        /**
         * @var FileHandler Used to handle files
         */
        protected $FileHandler = null;

        /**
         * @var array Extra columns to get/set from/to the DB (for normal resources)
         */
        protected $extraColumns = [];

        /**
         * @var array An associative array for each extra column defining how it can be set on setResource
         *            For each column, if a matching input isn't set (or is null), it cannot be set.
         */
        protected $extraInputs = [
            /**Each extra input is null, or an associative array of the form
             * '<column name>' => [
             *      'input' => <string, name of expected input>,
             *      'default' => <mixed, default null - default thing to be inserted into the DB on creation>,
             * ]
             */
        ];

        /**
         * @var string The table name for the single resources db
         */
        protected $resourceTableName = 'RESOURCES';

        /**
         * @var string The cache name for single resources.
         */
        protected $resourceCacheName = 'ioframe_resource_';

        /**
         * @var string The table name for the single resources db
         */
        protected $resourceCollectionTableName = 'RESOURCE_COLLECTIONS';

        /**
         * @var string The cache name for resource collection.
         */
        protected $resourceCollectionCacheName = 'ioframe_resource_collection_';

        /**
         * @var string The cache name for a resource collection's items.
         */
        protected $resourceCollectionItemsCacheName = 'ioframe_resource_collection_items_';

        /** Standard constructor
         *
         * @param SettingsHandler $settings The standard settings object
         * @param array $params of the form:
         *          'type'              - string, forces a specific resource type ('img', 'js' and 'css' mainly)
         * */
        function __construct(SettingsHandler $settings, array $params = []){

            parent::__construct($settings,$params);

            if(isset($params['resourceTableName']))
                $this->resourceTableName = $params['resourceTableName'];

            if(isset($params['resourceCollectionTableName']))
                $this->resourceCollectionTableName = $params['resourceCollectionTableName'];

            if(isset($params['extraColumns']))
                $this->extraColumns = $params['extraColumns'];

            if(isset($params['extraInputs']))
                $this->extraColumns = $params['extraInputs'];

            if(isset($params['siteSettings']))
                $this->siteSettings = $params['siteSettings'];
            else
                $this->siteSettings = new SettingsHandler(
                    $this->settings->getSetting('absPathToRoot').'/localFiles/siteSettings/',
                    $this->defaultSettingsParams
                );

            if(isset($params['resourceSettings']))
                $this->resourceSettings = $params['resourceSettings'];
            else
                $this->resourceSettings = new SettingsHandler(
                    $this->settings->getSetting('absPathToRoot').'/localFiles/resourceSettings/',
                    $this->defaultSettingsParams
                );

        }

        /** Gets all resources available, by type.
         * Can also get by specific addresses
         *
         * @param array $addresses defaults to [], if not empty will only get specific resources by addresses
         * @param array  $params getFromCacheOrDB() params, as well as:
         *          'createdAfter'      - int, default null - Only return items created after this date.
         *          'createdBefore'     - int, default null - Only return items created before this date.
         *          'changedAfter'      - int, default null - Only return items last changed after this date.
         *          'changedBefore'     - int, default null - Only return items last changed  before this date.
         *          'includeRegex'      - string, default null - A  regex string that addresses need to match in order
         *                                to be included in the result.
         *          'excludeRegex'      - string, default null - A  regex string that addresses need to match in order
         *                                to be excluded from the result.
         *          'ignoreLocal'       - bool, default false - will not return local files.
         *          'onlyLocal'         - bool, default false - will only return local files.
         *          'dataTypeNotNull'   - bool, default null - will only return files with data type not null if true, null if false.
         *                                More specific results can be achieved with dataType.
         *          'dataType'          - string, default null - will only return files of a specific data type. '@' for null.
         *          'safeStr'           - bool, default true. Whether to convert Meta to a safe string
         *          'extraDBFilters'    - array, default [] - Do you want even more complex filters than the ones provided?
         *                                This array will be merged with $extraDBConditions before the query, and passed
         *                                to getFromCacheOrDB() as the 'extraConditions' param.
         *                                Each condition needs to be a valid PHPQueryBuilder array.
         *          'extraCacheFilters' - array, default [] - Same as extraDBFilters but merged with $extraCacheConditions
         *                                and passed to getFromCacheOrDB() as 'columnConditions'.
         *          ------ Using the parameters bellow disables caching ------
         *          'ignoreBlob'        - bool, default false - if true, will not return the blob column.
         *          'orderBy'           - string, defaults to null. Possible values include 'Created' 'Last_Updated',
         *                                'Local' and 'Address'(default)
         *          'orderType'          - bool, defaults to null.  0 for 'ASC', 1 for 'DESC'
         *          'limit'             - string, SQL LIMIT, defaults to system default
         *          'offset'            - string, SQL OFFSET
         *
         * @returns array Array of the form:
         *      [
         *       <Address> =>   <Array of DB info> | <int 1 if specific resource doesnt exist or fails the filter checks>,
         *      ...
         *      ],
         *
         *      on full search, the array will include the item '@' of the form:
         *      {
         *          '#':<number of total results>
         *      }
         */
        function getResources(array $addresses, string $type, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $extraDBFilters = isset($params['extraDBFilters'])? $params['extraDBFilters'] : [];
            $extraCacheFilters = isset($params['extraCacheFilters'])? $params['extraCacheFilters'] : [];
            $createdAfter = isset($params['createdAfter'])? $params['createdAfter'] : null;
            $createdBefore = isset($params['createdBefore'])? $params['createdBefore'] : null;
            $changedAfter = isset($params['changedAfter'])? $params['changedAfter'] : null;
            $changedBefore = isset($params['changedBefore'])? $params['changedBefore'] : null;
            $includeRegex = isset($params['includeRegex'])? $params['includeRegex'] : null;
            $excludeRegex = isset($params['excludeRegex'])? $params['excludeRegex'] : null;
            $ignoreLocal = isset($params['ignoreLocal'])? $params['ignoreLocal'] : null;
            $onlyLocal = isset($params['onlyLocal'])? $params['onlyLocal'] : null;
            $dataTypeNotNull = isset($params['dataTypeNotNull'])? $params['dataTypeNotNull'] : null;
            $dataType = isset($params['dataType'])? $params['dataType'] : null;
            $ignoreBlob = isset($params['ignoreBlob'])? $params['ignoreBlob'] : false;
            $orderBy = isset($params['orderBy'])? $params['orderBy'] : null;
            $orderType = isset($params['orderType'])? $params['orderType'] : null;
            $limit = isset($params['limit'])? $params['limit'] : null;
            $offset = isset($params['offset'])? $params['offset'] : null;
            $safeStr = isset($params['safeStr'])? $params['safeStr'] : true;

            $retrieveParams = $params;
            $extraDBConditions = [];
            $extraCacheConditions = [];

            //If we are using any of this functionality, we cannot use the cache
            if( $orderBy || $orderType || $offset || $limit){
                $retrieveParams['useCache'] = false;
                $retrieveParams['orderBy'] = $orderBy? $orderBy : null;
                $retrieveParams['orderType'] = $orderType? $orderType : 0;
                $retrieveParams['limit'] =  $limit? $limit : null;
                $retrieveParams['offset'] =  $offset? $offset : null;
            }

            $columns = array_merge(['Resource_Type','Address','Resource_Local','Minified_Version','Version','Created','Last_Updated',
                'Text_Content','Data_Type'],$this->extraColumns);
            if(!$ignoreBlob)
                $columns = array_merge($columns,['Blob_Content']);

            //Create all the conditions for the db/cache
            array_push($extraCacheConditions,['Resource_Type',$type,'=']);
            array_push($extraDBConditions,['Resource_Type',[$type,'STRING'],'=']);

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

            if($includeRegex!== null){
                array_push($extraCacheConditions,['Address',$includeRegex,'RLIKE']);
                array_push($extraDBConditions,['Address',[$includeRegex,'STRING'],'RLIKE']);
            }

            if($excludeRegex!== null){
                array_push($extraCacheConditions,['Address',$excludeRegex,'NOT RLIKE']);
                array_push($extraDBConditions,['Address',[$excludeRegex,'STRING'],'NOT RLIKE']);
            }
            //ignoreLocal and onlyLocal are connected
            if($onlyLocal == true){
                $cond = ['Resource_Local',1,'='];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }
            elseif($ignoreLocal == true){
                $cond = ['Resource_Local',0,'='];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($dataTypeNotNull !== null){
                $cond = $dataTypeNotNull ? [['Data_Type','ISNULL'],'NOT'] : ['Data_Type','ISNULL'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($dataType !== null){
                $dataType = $dataType === '@'? null: $dataType;
                $cond = ['Data_Type',$dataType,'='];
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

            if($addresses == []){
                $results = [];
                $res = $this->SQLHandler->selectFromTable(
                    $this->SQLHandler->getSQLPrefix().$this->resourceTableName,
                    $extraDBConditions,
                    $columns,
                    $retrieveParams
                );
                $count = $this->SQLHandler->selectFromTable(
                    $this->SQLHandler->getSQLPrefix().$this->resourceTableName,
                    $extraDBConditions,
                    ['COUNT(*)'],
                    array_merge($retrieveParams,['limit'=>0])
                );
                if(is_array($res)){
                    $resCount = isset($res[0]) ? count($res[0]) : 0;
                    foreach($res as $resultArray){
                        for($i = 0; $i<$resCount/2; $i++)
                            unset($resultArray[$i]);
                        if($safeStr)
                            if($resultArray['Text_Content'] !== null)
                                $resultArray['Text_Content'] = IOFrame\Util\safeStr2Str($resultArray['Text_Content']);
                        $results[$resultArray['Address']] = $resultArray;
                    }
                    $results['@'] = array('#' => $count[0][0]);
                }
                return ($res)? $results : [];
            }
            else{
                $results = $this->getFromCacheOrDB(
                    $addresses,
                    'Address',
                    $this->resourceTableName,
                    $type.'_'.$this->resourceCacheName,
                    $columns,
                    $retrieveParams
                );
                if($safeStr)
                    foreach($results as $address =>$result){
                        if( (gettype($result) === 'array') && ($results[$address]['Text_Content'] !== null) )
                            $results[$address]['Text_Content'] = IOFrame\Util\safeStr2Str($results[$address]['Text_Content']);
                    }

                return $results;
            }

        }


        /** Sets a resource. Will create each non-existing address, or overwrite an existing one.
         * For each of the parameters bellow except for address, set NULL to ignore.
         * @param array $inputs of the form:
         *          'address' - string, In local mode, a folder relative to the default media folder, otherwise just the identifier.
         *          'local' - bool, Default true - whether the resource is local
         *          'minified' - bool, default false - whether the resource is minified
         *          'text' - string, Default null - text content to set
         *          'blob' - string, Default null - binary content to set
         *          'dataType' - string, Default null - binary data type (if set)
         *          FOR EACH $this->extraInputs, you can add an input. It will always be assumed of a simple type (not), and not required (has a default)
         * @param string $type Resource type
         * @param array $params of the form:
         *          'override' - bool, default true - will overwrite existing resources.
         *          'existing' - Array, potential existing addresses if we already got them earlier.
         *          'safeStr' - bool, default true. Whether to convert Meta to a safe string
         *          'mergeMeta' - bool, default true. Whether to treat Meta as a JSON object, and $text as a JSON array, and try to merge them.
         * @returns int Code of the form:
         *         -1 - Could not connect to db
         *          0 - All good
         *          1 - Resource does not exist and required fields not provided
         *          2 - Resource exists and override is false
         */
        function setResource( array $inputs, string $type, array $params = []){
            return $this->setResources($inputs,$type,$params)[$inputs['address']];
        }

        /** Sets a set of resource. Will create each non-existing address, or overwrite an existing one.
         * For each of the parameters bellow except for address, set NULL to ignore.
         * @param array $inputs Array of input arrays in the same order as the inputs in setResource, EXCLUDING 'type'
         * @param string $type All resources must be of the same type
         * @param array $params from setResource
         * @returns int[] Array of the form
         *          <Address> => <code>
         *          where the codes come from setResource()
         */
        function setResources(array $inputs, string $type, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $override = isset($params['override'])? $params['override'] : false;
            $update = isset($params['update'])? $params['update'] : false;
            $safeStr = isset($params['safeStr'])? $params['safeStr'] : true;
            $mergeMeta = isset($params['mergeMeta'])? $params['mergeMeta'] : true;

            $addresses = [];
            $existingAddresses = [];
            $indexMap = [];
            $addressMap = [];
            $results = [];
            $resourcesToSet = [];
            $currentTime = (string)time();

            foreach($inputs as $index=>$inputArr){
                array_push($addresses,$inputArr['address']);
                $results[$inputArr['address']] = -1;
                $indexMap[$inputArr['address']] = $index;
                $addressMap[$index] = $inputArr['address'];
            }

            //Figure out which extra columns to set, and what is their input
            $extraColumns = [];
            $extraInputs = [];
            foreach($this->extraColumns as $index => $extraColumn){
                if($this->extraInputs[$extraColumn]){
                    array_push($extraColumns,$extraColumn);
                    array_push($extraInputs,[
                        'input'=>$this->extraInputs[$extraColumn]['input'],
                        'default'=>isset($this->extraInputs[$extraColumn]['default'])?$this->extraInputs[$extraColumn]['default']:null
                    ]);
                }
            }

            if(isset($params['existing']))
                $existing = $params['existing'];
            else
                $existing = $this->getResources($addresses, $type, array_merge($params,['updateCache'=>false,'ignoreBlob'=>false]));

            foreach($inputs as $index=>$inputArr){
                //In this case the address does not exist or couldn't connect to db
                if(!is_array($existing[$addressMap[$index]])){
                    //If we could not connect to the DB, just return because it means we wont be able to connect next
                    if($existing[$addressMap[$index]] == -1)
                        return $results;
                    else{
                        //If we are only updating, continue
                        if($update){
                            $results[$inputArr['address']] = 1;
                            continue;
                        }
                        //If the address does not exist, make sure all needed fields are provided
                        //Set local to true if not provided
                        if(!isset($inputs[$index]['local']) || $inputs[$index]['local'] === null)
                            $inputs[$index]['local'] = true;
                        //Set minified to false if not provided
                        if(!isset($inputs[$index]['minified']) || $inputs[$index]['minified'] === null)
                            $inputs[$index]['minified'] = false;
                        //text
                        if(!isset($inputs[$index]['text']))
                            $inputs[$index]['text'] = null;
                        elseif($safeStr)
                            $inputs[$index]['text'] = IOFrame\Util\str2SafeStr($inputs[$index]['text']);
                        //blob
                        if(!isset($inputs[$index]['blob']))
                            $inputs[$index]['blob'] = null;
                        //data type
                        if(!isset($inputs[$index]['dataType']))
                            $inputs[$index]['dataType'] = null;

                        $arrayToSet = [
                            [$type,'STRING'],
                            [$inputs[$index]['address'],'STRING'],
                            $inputs[$index]['local'],
                            $inputs[$index]['minified'],
                            1,
                            [$currentTime,'STRING'],
                            [$currentTime,'STRING'],
                            [$inputs[$index]['text'],'STRING'],
                            [$inputs[$index]['blob'],'STRING'],
                            [$inputs[$index]['dataType'],'STRING'],
                        ];

                        foreach($extraInputs as $extraInputArr){
                            if(!isset($inputs[$index][$extraInputArr['input']]))
                                $inputs[$index][$extraInputArr['input']] = $extraInputArr['default'];
                            array_push($arrayToSet,[$inputs[$index][$extraInputArr['input']],'STRING']);
                        }

                        //Add the resource to the array to set
                        array_push($resourcesToSet,$arrayToSet);
                    }
                }
                //This is the case where the item existed
                else{
                    //If we are not allowed to override existing resources, go on
                    if(!$override && !$update){
                        $results[$inputArr['address']] = 2;
                        continue;
                    }
                    //Push an existing address in to be removed from the cache
                    array_push($existingAddresses,$type.'_'.$this->resourceCacheName.$inputArr['address']);
                    //Complete every field that is NULL with the existing resource
                    //local
                    if(!isset($inputs[$index]['local']) || $inputs[$index]['local'] === null)
                        $inputs[$index]['local'] = $existing[$addressMap[$index]]['Resource_Local'];
                    //minified
                    if(!isset($inputs[$index]['minified']) || $inputs[$index]['minified'] === null)
                        $inputs[$index]['minified'] = $existing[$addressMap[$index]]['Minified_Version'];
                    //text
                    if(!isset($inputs[$index]['text']) || $inputs[$index]['text'] === null)
                        $inputs[$index]['text'] = $existing[$addressMap[$index]]['Text_Content'];
                    else{
                        //This is where we merge the arrays as JSON if they are both valid json
                        if( $mergeMeta &&
                            IOFrame\Util\is_json($inputs[$index]['text']) &&
                            IOFrame\Util\is_json($existing[$addressMap[$index]]['Text_Content'])
                        ){
                            $inputJSON = json_decode($inputs[$index]['text'],true);
                            $existingJSON = json_decode($existing[$addressMap[$index]]['Text_Content'],true);
                            if($inputJSON == null)
                                $inputJSON = [];
                            if($existingJSON == null)
                                $existingJSON = [];
                            $inputs[$index]['text'] =
                                json_encode(IOFrame\Util\array_merge_recursive_distinct($existingJSON,$inputJSON,['deleteOnNull'=>true]));
                            if($inputs[$index]['text'] == '[]')
                                $inputs[$index]['text'] = null;
                        }
                        //Here we convert back to safeString
                        if($safeStr && $inputs[$index]['text'] !== null)
                            $inputs[$index]['text'] = IOFrame\Util\str2SafeStr($inputs[$index]['text']);
                    }
                    //blob
                    if(!isset($inputs[$index]['blob']) || $inputs[$index]['blob'] === null)
                        $inputs[$index]['blob'] = $existing[$addressMap[$index]]['Blob_Content'];
                    //data type
                    if(!isset($inputs[$index]['dataType']) || $inputs[$index]['dataType'] === null)
                        $inputs[$index]['dataType'] = $existing[$addressMap[$index]]['Data_Type'];

                    $arrayToSet = [
                        [$type,'STRING'],
                        [$inputs[$index]['address'],'STRING'],
                        $inputs[$index]['local'],
                        $inputs[$index]['minified'],
                        $existing[$addressMap[$index]]['Version'],
                        [$existing[$addressMap[$index]]['Created'],'STRING'],
                        [$currentTime,'STRING'],
                        [$inputs[$index]['text'],'STRING'],
                        [$inputs[$index]['blob'],'STRING'],
                        [$inputs[$index]['dataType'],'STRING'],
                    ];

                    foreach($extraInputs as $extraIndex => $extraInputArr){
                        if(!isset($inputs[$index][$extraInputArr['input']]) || $inputs[$index][$extraInputArr['input']] === null)
                            $inputs[$index][$extraInputArr['input']] = $existing[$addressMap[$index]][$extraColumns[$extraIndex]];
                        array_push($arrayToSet,[$inputs[$index][$extraInputArr['input']],'STRING']);
                    }

                    //Add the resource to the array to set
                    array_push($resourcesToSet,$arrayToSet);
                }
            }

            //If we got nothing to set, return
            if($resourcesToSet==[])
                return $results;
            $res = $this->SQLHandler->insertIntoTable(
                $this->SQLHandler->getSQLPrefix().$this->resourceTableName,
                array_merge(['Resource_Type','Address','Resource_Local','Minified_Version','Version','Created','Last_Updated',
                    'Text_Content','Blob_Content','Data_Type'],$extraColumns),
                $resourcesToSet,
                array_merge($params,['onDuplicateKey'=>true])
            );

            //If we succeeded, set results to success and remove them from cache
            if($res){
                foreach($addresses as $address){
                    if($results[$address] == -1)
                        $results[$address] = 0;
                }
                if($existingAddresses != []){
                    if(count($existingAddresses) == 1)
                        $existingAddresses = $existingAddresses[0];

                    if($verbose)
                        echo 'Deleting addreses '.json_encode($existingAddresses).' from cache!'.EOL;

                    if(!$test)
                        $this->RedisHandler->call('del',[$existingAddresses]);
                }
            }

            return $results;
        }

        /** Renames a resource
         * @param string $address Old address
         * @param string $newAddress New address
         * @param string $type
         * @param array $params of the form:
         *          'copy'             - bool, default false - copies the file instead of moving it
         *          'existingAddresses' - Array, potential existing addresses if we already got them earlier.
         * @returns int Code of the form:
         *         -1 - Could not connect to db
         *          0 - All good
         *          1 target address already exists
         *          2 source address does not exist
         */
        function renameResource( string $address, string $newAddress, string $type, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $copy = isset($params['copy'])? $params['copy'] : false;
            $existingAddresses =
                isset($params['existingAddresses'])? $params['existingAddresses']
                    :
                    $this->getResources([$address,$newAddress],$type,$params);
            $existingNew = $existingAddresses[$newAddress];
            $existingOld = $existingAddresses[$address];

            //Check for existing resources at the new address
            if($existingNew != 1){
                if($verbose)
                    echo 'New target already exists in the db!'.EOL;
                return 1;
            }

            //Check for existing resources at the old address
            if($existingOld === 1){
                if($verbose)
                    echo 'Source address does not exist in the db!'.EOL;
                return 2;
            }

            //If we are copying,
            if($copy){

                $oldColumns = [];
                $newValues = [];

                foreach($existingOld as $key=>$oldInfo){
                    //Fucking trash results returning twice with number indexes. WHY? WHY???
                    if(!preg_match('/^\d+$/',$key)){
                        array_push($oldColumns,$key);
                        if(gettype($oldInfo) === 'string')
                            $oldInfo = [$oldInfo,'STRING'];
                        if($key === 'Address')
                            array_push($newValues,[$newAddress,'STRING']);
                        else
                            array_push($newValues,$oldInfo);
                    }
                }
                //Just insert the new values into the table
                $res = $this->SQLHandler->insertIntoTable(
                    $this->SQLHandler->getSQLPrefix().$this->resourceTableName,
                    $oldColumns,
                    [$newValues],
                    $params
                );
            }
            else
                $res = $this->SQLHandler->updateTable(
                    $this->SQLHandler->getSQLPrefix().$this->resourceTableName,
                    ['Address = "'.$newAddress.'"'],
                    [
                        [
                            'Address',
                            [$address,'STRING'],
                            '='
                        ],
                        [
                            'Resource_Type',
                            [$type,'STRING'],
                            '='
                        ],
                        'AND'
                    ],
                    $params
                );

            if($res){
                if(!$test)
                    $this->RedisHandler->call('del',$type.'_'.$this->resourceCacheName.$address);
                if($verbose)
                    echo 'Deleting '.$this->resourceCacheName.$address.' from cache!'.EOL;
                return 0;
            }
            else
                return -1;
        }

        /** Deletes a resource
         * @param string $address Resource address
         * @param array $params
         * @returns int Code of the form:
         *         -1 - Failed to connect to db
         *          0 - All good
         *          1 - Resource does not exist
         *
         */
        function deleteResource(string $address, string $type, array $params){
            return $this->deleteResources([$address],$type,$params)[$address];
        }

        /** Deletes resources.
         *
         * @param array $addresses
         * @param array $params
         *          'checkExisting' - bool, default true - whether to check for existing addresses
         * @returns Array of the form:
         * [
         *       <Address> =>  <code>,
         *       ...
         * ]
         * Where the codes are from deleteResource
         */
        function  deleteResources(array $addresses, string $type, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $checkExisting = isset($params['checkExisting'])? $params['checkExisting'] : true;

            $results = [];
            $addressesToDelete = [];
            $addressesToDeleteFromCache = [];
            $failedGetConnection = false;
            $existing = $checkExisting ? $this->getResources($addresses,$type,array_merge($params,['updateCache'=>false])) : [];

            foreach($addresses as $address){
                if($existing!=[] && !is_array($existing[$address])){
                    if($verbose)
                        echo 'Address '.$address.' does not exist!'.EOL;
                    if($existing[$address] == -1)
                        $failedGetConnection = true;
                    $results[$address] = $existing[$address];
                }
                else{
                    $results[$address] = -1;
                    array_push($addressesToDelete,[$address,'STRING']);
                    array_push($addressesToDeleteFromCache,$type.'_'.$this->resourceCacheName.$address);
                }
            }

            //Assuming if one result was -1, all of them were
            if($failedGetConnection){
                return $results;
            }

            if($addressesToDelete == []){
                if($verbose)
                    echo 'Nothing to delete, exiting!'.EOL;
                return $results;
            }

            $res = $this->SQLHandler->deleteFromTable(
                $this->SQLHandler->getSQLPrefix().$this->resourceTableName,
                [
                    [
                        'Address',
                        $addressesToDelete,
                        'IN'
                    ],
                    [
                        'Resource_Type',
                        [$type,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            if($res){
                foreach($addresses as $address){
                    if($results[$address] == -1)
                        $results[$address] = 0;
                }

                if($addressesToDeleteFromCache != []){
                    if(count($addressesToDeleteFromCache) == 1)
                        $addressesToDeleteFromCache = $addressesToDeleteFromCache[0];

                    if($verbose)
                        echo 'Deleting addreses '.json_encode($addressesToDeleteFromCache).' from cache!'.EOL;

                    if(!$test)
                        $this->RedisHandler->call('del',[$addressesToDeleteFromCache]);
                }
            }

            return $results;
        }


        /** Increments a version of something.
         *
         * @param string $address Address of the resource
         * @param array $params
         * @returns int Code of the form:
         *         -1 - Failed to connect to db
         *          0 - All good
         */
        function incrementResourceVersion(string $address, string $type, array $params = []){
            return $this->incrementResourcesVersions([$address],$type,$params);
        }


        /** Increments a version of something.
         *
         * @param string $address Address of the resource
         * @param array $params same as incrementResourcesVersions
         * @returns int Code of the form:
         *         -1 - Failed to connect to db
         *          0 - All good
         */
        function incrementResourcesVersions(array $addresses, string $type, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $dbAddresses =[];
            $cacheAddresses = [];

            foreach($addresses as $address){
                array_push($dbAddresses,[$address,'STRING']);
                array_push($cacheAddresses,$type.'_'.$this->resourceCacheName.$address);
            }

            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->resourceTableName,
                ['Version = Version + 1'],
                [
                    [
                        'Address',
                        $dbAddresses,
                        'IN'
                    ],
                    [
                        'Resource_Type',
                        [$type,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            if(!$res)
                return -1;

            if(count($cacheAddresses) == 1)
                $cacheAddresses = $cacheAddresses[0];

            if(!$test)
                $this->RedisHandler->call('del',[$cacheAddresses]);
            if($verbose)
                echo 'Deleting '.json_encode($cacheAddresses).' from cache!'.EOL;

            return 0;
        }

        /** Returns all collections a resource belongs to. Is quite expensive, should not be used often.
         *
         * @param string $address Name of the resource collection
         * @param string $type
         * @param array $params
         *
         * @returns Int|Array
     *           Possible codes:
         *          -1 Could not connect to db.
         *       Else returns array of the form:
         *          [<collection name 1>, <collection name 2>, ...]
         *
         */
        function getCollectionsOfResource(string $address , string $type, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $res = $this->SQLHandler->selectFromTable(
                $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS_MEMBERS',
                [
                    [
                        'Address',
                        [$address,'STRING'],
                        '='
                    ],
                    [
                        'Resource_Type',
                        [$type,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                ['Collection_Name'],
                ['DISTINCT'=>true,'test'=>$test,'verbose'=>$verbose]
            );

            if($res === false)
                return -1;
            else{
                $collections = [];
                for($i=0; $i<count($res); $i++){
                    array_push($collections,$res[$i][0]);
                };
                return $collections;
            }
        }

        /** Gets a single resource collection. Gets all its members, and returns it in-order if an order exists.
         * @param string $name Name of the resource collection
         * @param string $type
         * @param array $params of the form:
         *          'getMembers' - bool, default false - will also get ALL of the members of the resource collections.
         *                         When this is true, all of the getResources() parameters except 'type' are valid.
         *          'safeStr' - bool, default true. Whether to convert Meta to a safe string
         * @returns Int|Array
         *          Possible codes:
         *          -1 Could not connect to db.
         *           1 Collection does not exist
         *       Else returns array of the form:
         *  [
         *       '@' =>  <array of DB info about the collection>,
         *       <Member 1> => <array of DB info>|<code>,
         *       <Member 2> => <array of DB info>|<code>,
         *       ...
         *  ]
         *      where the members are in order, if there is one, and each member may return the code "1"
         *      in case it's a member from an order that no longer exists for some reason.
        */
        function getResourceCollection(string $name, string $type, array $params = []){
            return $this->getResourceCollections([$name],$type,$params)[$name];
        }

        /* Gets resource collections. Does not return members by default.
         * @param array $names Defaults to []. If empty, will get all collections but without members.
         * @param string $type
         * @param array $params of the form:
         *          'getMembers' - bool, default false - will also get ALL of the members of the resource collections.
         *                         When this is true, all of the getResources() parameters except 'type' are valid.
         * @returns Array of the form
         *  [
         *       <Collection Name> => Int|Array described in getResourceCollection(),
         *      ...
         *  ]
         *      on full search, the array will include the item '@' of the form:
         *      {
         *          '#':<number of total results>
         *      }
         * */
        function getResourceCollections(array $names, string $type, array $params){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $getMembers = isset($params['getMembers'])? $params['getMembers'] : false;
            $safeStr = isset($params['safeStr'])? $params['safeStr'] : true;

            //If there are no names, show the user everything in the DB
            if($names ===[]){
                if($verbose)
                    echo 'Only returning all resource collection info!'.EOL;


                $createdAfter = isset($params['createdAfter'])? $params['createdAfter'] : null;
                $createdBefore = isset($params['createdBefore'])? $params['createdBefore'] : null;
                $changedAfter = isset($params['changedAfter'])? $params['changedAfter'] : null;
                $changedBefore = isset($params['changedBefore'])? $params['changedBefore'] : null;
                $includeRegex = isset($params['includeRegex'])? $params['includeRegex'] : null;
                $excludeRegex = isset($params['excludeRegex'])? $params['excludeRegex'] : null;
                $orderBy = isset($params['orderBy'])? $params['orderBy'] : null;
                $orderType = isset($params['orderType'])? $params['orderType'] : null;
                $limit = isset($params['limit'])? $params['limit'] : null;
                $offset = isset($params['offset'])? $params['offset'] : null;

                $dbConditions = [['Resource_Type',[$type,'STRING'],'=']];

                $retrieveParams = $params;
                $retrieveParams['orderBy'] = $orderBy? $orderBy : null;
                $retrieveParams['orderType'] = $orderType? $orderType : 0;
                $retrieveParams['limit'] =  $limit? $limit : null;
                $retrieveParams['offset'] =  $offset? $offset : null;

                if($createdAfter!== null){
                    $cond = ['Created',$createdAfter,'>'];
                    array_push($dbConditions,$cond);
                }

                if($createdBefore!== null){
                    $cond = ['Created',$createdBefore,'<'];
                    array_push($dbConditions,$cond);
                }

                if($changedAfter!== null){
                    $cond = ['Last_Updated',$changedAfter,'>'];
                    array_push($dbConditions,$cond);
                }

                if($changedBefore!== null){
                    $cond = ['Last_Updated',$changedBefore,'<'];
                    array_push($dbConditions,$cond);
                }

                if($includeRegex!== null){
                    array_push($dbConditions,['Collection_Name',[$includeRegex,'STRING'],'RLIKE']);
                }

                if($excludeRegex!== null){
                    array_push($dbConditions,['Collection_Name',[$excludeRegex,'STRING'],'NOT RLIKE']);
                }

                if($dbConditions!=[]){
                    array_push($dbConditions,'AND');
                }

                $res = $this->SQLHandler->selectFromTable(
                    $this->SQLHandler->getSQLPrefix().$this->resourceCollectionTableName,
                    $dbConditions,
                    [],
                    $retrieveParams
                );

                $count = $this->SQLHandler->selectFromTable(
                    $this->SQLHandler->getSQLPrefix().$this->resourceCollectionTableName,
                    $dbConditions,
                    ['COUNT(*)'],
                    array_merge($retrieveParams,['limit'=>0])
                );

                $results = [];

                if(!$res || $res === []){
                    if($verbose)
                        echo 'Failed to connect to db or no results found!'.EOL;
                    return [];
                }
                else{
                    foreach($res as $array){
                        $name = $array['Collection_Name'];
                        unset($array['Collection_Name']);
                        if($safeStr && $array['Meta'] !== null)
                            $array['Meta'] = IOFrame\Util\safeStr2Str($array['Meta']);
                        $results[$name] = [
                            '@' => $array
                        ];
                    };
                    $results['@'] = array('#' => $count[0][0]);
                }

                return $results;
            }

            $results = [];
            $resourceTargets = [];

            //Get info on collections
            $collectionInfo = $this->getFromCacheOrDB(
                $names,
                'Collection_Name',
                $this->resourceCollectionTableName,
                $type.'_'.$this->resourceCollectionCacheName,
                [],
                array_merge(
                    $params,
                    ['columnConditions' => [['Resource_Type',$type,'=']] ]
                )
            );

            //Set the responses for the collections that do not exist (or db cannot connect)
            foreach($names as $index=>$name){
                //Remove collections that do not exist
                if( !is_array($collectionInfo[$name]) ){
                    $results[$name] = $collectionInfo[$name];
                    unset($names[$index]);
                }
                //Add collection info to those that do.
                else{
                    if($safeStr)
                        $collectionInfo[$name]['Meta'] = ($collectionInfo[$name]['Meta'])?
                            IOFrame\Util\safeStr2Str($collectionInfo[$name]['Meta']) : $collectionInfo[$name]['Meta'];
                    $results[$name] = [
                        '@' => $collectionInfo[$name]
                    ];
                    //If the collection was ordered, mark its order as members you need to get from the cache.
                    if($collectionInfo[$name]['Collection_Order'] && $getMembers){
                        $order = explode(',',$collectionInfo[$name]['Collection_Order']);
                        $resourceTargets[$name] = [];
                        foreach($order as $item){
                            array_push($resourceTargets[$name], $item);
                        }
                    }
                }
            }

            //If no names survived, or we do not want to get members info, return
            if(count($names) == 0 || !$getMembers){
                if($verbose)
                    echo 'Returning before getting members!'.EOL;
                return $results;
            }

            //For each collection, if it was ordered, get its ordered members
            foreach($names as $index => $name){
                if(isset($resourceTargets[$name])){
                    //Remember to enforce collection type if requested
                    $tempParams = $params;
                    if($type)
                        $tempParams['type'] = $collectionInfo[$name]['Resource_Type'];
                    else
                        unset($tempParams['type']);
                    $collectionResources = $this->getResources($resourceTargets[$name],$type,$tempParams);
                    $results[$name] = array_merge($results[$name],$collectionResources);
                    unset($names[$index]);
                }
            }

            //If there are still unordered collections we need to get, get them.
            if($names !== []){
                //For unordered collections, you have to get the members from the DB. You can do it all at once.
                $collectionTable = $this->SQLHandler->getSQLPrefix().$this->resourceCollectionTableName;
                $collectionsResourcesTable = $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS_MEMBERS';
                $resourcesTable = $this->SQLHandler->getSQLPrefix().$this->resourceTableName;
                $columns = [
                    $resourcesTable.'.Address',
                    $resourcesTable.'.Resource_Type',
                    $resourcesTable.'.Resource_Local',
                    $resourcesTable.'.Minified_Version',
                    $resourcesTable.'.Version',
                    $resourcesTable.'.Created',
                    $resourcesTable.'.Last_Updated',
                    $resourcesTable.'.Text_Content',
                    $resourcesTable.'.Blob_Content'
                ];

                foreach($names as $index => $name){

                    //First, try to get the resource identifiers from the cache, if they exist
                    $cachedResult = $this->RedisHandler->call('get',[$type.'_'.$this->resourceCollectionItemsCacheName.$name]);

                    //If we got a hit, get the relevant items normally
                    if($cachedResult && IOFrame\Util\is_json($cachedResult)){
                        if($verbose)
                            echo 'Found items for collection '.$name.' in cache!'.EOL;
                        $cachedResult = json_decode($cachedResult, true);
                        $tempParams = $params;
                        $tempParams['type'] = $collectionInfo[$name]['Resource_Type'];
                        $collectionResources = $this->getResources($cachedResult,$type,$tempParams);
                        $results[$name] = array_merge($results[$name],$collectionResources);
                        unset($names[$index]);
                    }
                    //If we could not find the items in the cache, get them from the DB and set them in the cache
                    else{
                        $conds = [
                            [
                                $collectionTable.'.Collection_Name',
                                [$name,'STRING'],
                                '='
                            ],
                            [
                                $collectionsResourcesTable.'.Resource_Type',
                                [$type,'STRING'],
                                '='
                            ],
                            'AND'
                        ];

                        $collectionMembers = $this->SQLHandler->selectFromTable(
                            $collectionTable.' JOIN '.$collectionsResourcesTable.' ON '.$collectionTable.'
                    .Collection_Name = '.$collectionsResourcesTable.'.Collection_Name JOIN '.$resourcesTable.'
                     ON '.$resourcesTable.'.Address = '.$collectionsResourcesTable.'.Address',
                            $conds,
                            $columns,
                            $params
                        );

                        $itemsToCache = [];

                        foreach($collectionMembers as $array){
                            $results[$name][$array['Address']] = [
                                'Address' => $array['Address'],
                                'Resource_Type' => $array['Resource_Type'],
                                'Resource_Local' => $array['Resource_Local'],
                                'Minified_Version' => $array['Minified_Version'],
                                'Version' => $array['Version'],
                                'Created' => $array['Created'],
                                'Last_Updated' => $array['Last_Updated'],
                                'Text_Content' => $array['Text_Content'],
                                'Blob_Content' => $array['Blob_Content']
                            ];
                            array_push($itemsToCache,$array['Address']);
                            if($safeStr)
                                $results[$name][$array['Address']]['Text_Content'] = ($results[$name][$array['Address']]['Text_Content'])?
                                    IOFrame\Util\safeStr2Str($results[$name][$array['Address']]['Text_Content']) : $results[$name][$array['Address']]['Text_Content'];
                        }

                        if($itemsToCache !=[]){
                            if($verbose)
                                echo 'Pushing items '.json_encode($itemsToCache).' of collection '.$name.' into cache!'.EOL;
                            if(!$test)
                                $this->RedisHandler->call(
                                    'set',
                                    [$type.'_'.$this->resourceCollectionItemsCacheName.$name,json_encode($itemsToCache)]
                                );
                        }

                    }
                }
            };

            return  $results;
        }

        /* Creates a new, empty resource collection.
         * @param string $name Name of the collection
         * @param string $resourceType Anything, but should match the available resource types or signify a generic collection.
         * @param array $params of the form:
         *          'override' - bool, default false - whether to override existing collections.
         *          'update' - bool, default false - whether to only update existing collections
         *          'mergeMeta' - bool, default true. Whether to treat Meta as a JSON object, and $meta as a JSON array,
         *                        and try to merge them.
         *          'safeStr' - bool, default true. Whether to convert Meta to a safe string
         *          'existingCollections' - Array, potential existing collections if we already got them earlier.
         * @returns Int|Array
         *          Possible codes:
         *          -1 - Could not connect to db.
         *           0 - All good
         *           1 - Name already exists and 'override' is false OR Name doesn't exist and 'update' is true
         * */
        function setResourceCollection(string $name, string $type, string $meta = null, array $params = []){
            return $this->setResourceCollections([[$name,$meta]],$type,$params)[$name];
        }


        /* Creates new, empty resource collections.
         * @param array $inputs Same as setResourceCollection
         * @param array $params from setResourceCollection
         * @returns Array of the form
         *  [
         *       <Collection Name> => Int|Array described in setResourceCollection(),
         *      ...
         *  ]
         * */
        function setResourceCollections(array $inputs, string $type, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $update = isset($params['update'])? $params['update'] : false;
            //If we are updating, then by default we allow overwriting
            if(!$update)
                $override = isset($params['override'])? $params['override'] : false;
            else
                $override =true;
            $mergeMeta = isset($params['mergeMeta'])? $params['mergeMeta'] : true;
            $safeStr = isset($params['safeStr'])? $params['safeStr'] : true;

            $results = [];
            $names = [];
            $nameToIndexMap = [];
            $currentTime = (string)time();

            foreach($inputs as $index => $inputArray){
                $name = $inputArray[0];
                array_push($names,$name);
                $nameToIndexMap[$name] = $index;
                $results[$name] = -1;
            }

            //Get existing collections if override is false or update is true
            if(!$override || $update){
                if(isset($params['existingCollections']))
                    $existing = $params['existingCollections'];
                else
                    $existing = $this->getResourceCollections($names, $type,array_merge($params,['getMembers'=>false,'updateCache'=>false]));

                //If a collection exists, and override and update are false, unset the input and update the result.
                if(!$override)
                    foreach($existing as $name=>$exists){
                        if(is_array($exists)){
                            $results[$name] = 1;
                            unset($inputs[$nameToIndexMap[$name]]);
                        }
                    }

                //If we are updating
                if($update)
                    foreach($names as $name){
                        //If a collection doesn't exist, and update is true, unset it
                        if(!is_array($existing[$name])){
                            $results[$name] = 1;
                            unset($inputs[$nameToIndexMap[$name]]);
                        }
                        //If a collection exists, see if we need to do anything
                        else{
                            $existingMeta = $existing[$name]['@']['Meta'];
                            $newMeta = $inputs[$nameToIndexMap[$name]][1];

                            //If our meta is null, take the existing meta instead
                            if($newMeta === null && $existingMeta !== null)
                                $inputs[$nameToIndexMap[$name]][1] = $existingMeta;

                            //If both metas exist, are JSON, and $mergeMeta is true, try to merge them
                            if($mergeMeta && IOFrame\Util\is_json($newMeta) && IOFrame\Util\is_json($existingMeta) ){
                                $inputs[$nameToIndexMap[$name]][1] =
                                    json_encode( array_merge(json_decode($existingMeta,true),json_decode($newMeta,true)) );
                            }
                        }
                    }
            }

            //If you cannot create any collections, return
            if(count($inputs) == 0)
                return $results;

            //Create the collections
            $toSet = [];
            foreach($inputs as $index => $inputArray)

            //Parse the meta
            $meta = isset($inputArray[1])? $inputArray[1] : null;
            if($meta !== null){
                if($safeStr)
                    $meta = IOFrame\Util\str2SafeStr($meta);
                $meta = [$meta,'STRING'];
            }

            //Check if we are changing an existing gallery
            if(is_array($existing[$inputArray[0]]))
                $createdTime = $existing[$inputArray[0]]['@']['Created'];
            else
                $createdTime = $currentTime;

            array_push(
                $toSet,
                [
                    [$inputArray[0],'STRING'],
                    [$type,'STRING'],
                    [$createdTime,'STRING'],
                    [$currentTime,'STRING'],
                    $meta
                ]
            );

            $res = $this->SQLHandler->insertIntoTable(
                $this->SQLHandler->getSQLPrefix().$this->resourceCollectionTableName,
                ['Collection_Name','Resource_Type','Created','Last_Updated','Meta'],
                $toSet,
                array_merge($params, ['onDuplicateKey'=>true])
            );

            if($res){
                $cacheAddresses = [];

                foreach($results as $index => $result){
                    if($result == -1){
                        $results[$index] = 0;
                        array_push($cacheAddresses, $type.'_'.$this->resourceCollectionCacheName.$index);
                    }
                }

                if(!$test)
                    $this->RedisHandler->call('del',[$cacheAddresses]);
                if($verbose)
                    echo 'Deleting '.json_encode($cacheAddresses).' from cache!'.EOL;
            }

            return $results;
        }


        /* Deletes a resource collection.
         * @param string $name Name of the collection
         * @param string $type Type of the collection
         * @returns Int|Array
         *          Possible codes:
         *          -1 - Could not connect to db.
         *           0 - All good
         * */
        function deleteResourceCollection(string $name, string $type, array $params = []){
            return $this->deleteResourceCollections([$name],$type,$params);
        }

        /* Deletes resource collections.
         * @param array $names
         * @param array $type
         * @returns Int|Array
         *          Possible codes:
         *          -1 - Could not connect to db.
         *           0 - All good
         * */
        function deleteResourceCollections(array $names, string $type, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $dbNames = [];

            foreach($names as $index => $name)
                array_push($dbNames,[$name,'STRING']);

            $res = $this->SQLHandler->deleteFromTable(
                $this->SQLHandler->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    [
                        'Collection_Name',
                        $dbNames,
                        'IN'
                    ],
                    [
                        'Resource_Type',
                        [$type,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            if($res){
                //delete the collection cache
                foreach($names as $collection){
                    if($verbose)
                        echo 'Deleting collection cache of '.$collection.EOL;

                    if(!$test)
                        $this->RedisHandler->call(
                            'del',
                            [
                                [
                                    $type.'_'.$this->resourceCollectionItemsCacheName.$collection,
                                    $type.'_'.$this->resourceCollectionCacheName.$collection
                                ]
                            ]
                        );
                }

                //Ok we're done
                return 0;
            }
            else
                return -1;

        }

        /** Adds a resource to a collection.
         * @param string $address - Identifier of the resource
         * @param string $collection - Name of the collection.
         * @param array $params of the form:
         *          'pushToOrder' - bool, default false - whether to add the resource to the collection order.
         * @returns Int
         *          Possible codes:
         *          -1 - Could not connect to db.
         *           0 - All good
         *           1 - Resource does not exist
         *           2 - Collection does not exist
         *           3 - Resource already in collection.
         */
        function addResourceToCollection( string $address, string $collection, string $type, array $params = []){
            return $this->addResourcesToCollection([$address],$collection,$type,$params)[$address];
        }

        /** Adds resources to a collection.
         * @param string[] $addresses - Addresses
         * @param string $collection - Name of the collection.
         * @param array $params of the form:
         *          'pushToOrder' - bool, default false - whether to add the resources to the collection order.
         *          'existingAddresses' - Array, potential existing addresses if we already got them earlier.
         *          'existingCollection' - Array, potential existing collection if we already got them earlier.
         * @returns Array of the form:
         *      <address> => <Code>
         *      Where the codes come from addResourceToCollection, and all of them are 2 if the collection does not exist.
         */
        function addResourcesToCollection( array $addresses, string $collection, string $type, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $pushToOrder = isset($params['pushToOrder'])? $params['pushToOrder'] : false;

            if(isset($params['existingAddresses']))
                $existingAddresses = $params['existingAddresses'];
            else
                $existingAddresses = $this->getResources($addresses,$type,$params);

            if(isset($params['existingCollection']))
                $existingCollection = $params['existingCollection'];
            else
                $existingCollection = $this->getResourceCollection($collection, $type,array_merge($params,['getMembers'=>true]));

            $results = [];
            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                if($existingAddresses == -1)
                    foreach($addresses as $address)
                        $results[$address] = -1;
                //Collection does not exist
                else
                    foreach($addresses as $address)
                        $results[$address] = 2;

                return $results;
            }

            //If the collection does exist..
            $failedToGetAddresses = false;
            foreach($addresses as $index=>$address){
                //Resource does not exist
                if(!is_array($existingAddresses[$address])){
                    if($existingAddresses[$address] == -1)
                        $failedToGetAddresses = true;
                    $results[$address] = $existingAddresses[$address];
                    unset($addresses[$index]);
                }
                //Resource already in collection
                elseif(isset($existingCollection[$address])){
                    $results[$address] = 3;
                    unset($addresses[$index]);
                }
                else{
                    $results[$address] = -1;
                }
            }

            //We can only get here if the addresses returned -1 or we got not addresses left to set
            if($failedToGetAddresses || count($addresses) == 0)
                return $results;

            //If we are here, there are addresses we can add to a collection
            $toSet = [];

            foreach($addresses as $address){
                array_push($toSet,[[$type,'STRING'],[$collection,'STRING'],[$address,'STRING']]);
            }

            //First, update Last Changed of the collection
            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                ],
                [
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
                        [$collection,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            //If we failed to set Last_Updated, exit. Else write the changes to the DB.
            if(!$res){
                return $results;
            }

            $res = $this->SQLHandler->insertIntoTable(
                $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS_MEMBERS',
                ['Resource_type','Collection_Name','Address'],
                $toSet,
                array_merge($params, ['onDuplicateKey'=>true])
            );


            if($res){
                //If we are here, we pushed the resources to the collection. Now we may need to push them to order..
                if($pushToOrder){
                    $orderParams = $this->defaultSettingsParams;
                    $orderParams['tableName'] = $this->resourceCollectionTableName;
                    $orderParams['columnNames'] = [['Resource_Type','Collection_Name'],'Collection_Order'];
                    $orderParams['columnIdentifier'] = [$type,$collection];
                    $order = ($existingCollection['@']['Collection_Order'] != null)?
                        $existingCollection['@']['Collection_Order'] : '';
                    $orderHandler = new OrderHandler($this->settings,$orderParams);
                    $orderHandler->pushToOrderMultiple(
                        $addresses,
                        array_merge($params,['rowExists' => true,'order'=>$order,'useCache'=>false])
                    );
                }
                //.., and delete the collection cache
                if($verbose)
                    echo 'Deleting collection cache of '.$collection.EOL;

                if(!$test)
                    $this->RedisHandler->call(
                        'del',
                        [
                            [
                                $type.'_'.$this->resourceCollectionItemsCacheName.$collection,
                                $type.'_'.$this->resourceCollectionCacheName.$collection
                            ]
                        ]
                    );

                //Ok we're done
                foreach($addresses as $address){
                    if($results[$address] == -1)
                        $results[$address] = 0;
                }

            }

            return $results;
        }

        /** Removes a resource from a collection.
         * @param string $address - Identifier of the resource
         * @param string $collection - Name of the collection.
         * @param array $params of the form:
         *          'removeFromOrder' - bool, default false - whether to remove the resource from the collection order.
         * @returns Int
         *          Possible codes:
         *          -1 - Could not connect to db.
         *           0 - All good
         *           1 - Collection does not exist
         */
        function removeResourceFromCollection( string $address, string $collection, string $type, array $params = []){
            return $this->removeResourcesFromCollection([$address],$collection,$type,$params)[$address];
        }

        /** Removes resources from a collection.
         * @param string[] $addresses - Addresses
         * @param string $collection - Name of the collection.
         * @param array $params of the form:
         *          'removeFromOrder' - bool, default false - whether to remove the resource from the collection order.
         *          'existingCollection' - Array, potential existing collection if we already got them earlier.
         * @returns int SAME AS removeResourceFromCollection (since the number of resources changes nothing)
         */
        function removeResourcesFromCollection( array $addresses, string $collection, string $type, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $removeFromOrder = isset($params['removeFromOrder'])? $params['removeFromOrder'] : false;

            if(isset($params['existingCollection']))
                $existingCollection = $params['existingCollection'];
            else
                $existingCollection = $this->getResourceCollection($collection, $type,array_merge($params,['getMembers'=>false]));

            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                return $existingCollection;
            }

            //If we are here, there are addresses we can add to a collection
            $dbAddresses = [];

            foreach($addresses as $address){
                array_push($dbAddresses,[$address,'STRING']);
            }

            //First, update Last Changed of the collection
            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                ],
                [
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
                        [$collection,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            //If we failed to set Last_Updated, exit. Else write the changes to the DB.
            if(!$res){
                return -1;
            }

            $res = $this->SQLHandler->deleteFromTable(
                $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS_MEMBERS',
                [
                    [
                        'Resource_Type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        'Collection_Name',
                        [$collection,'STRING'],
                        '='
                    ],
                    [
                        'Address',
                        $dbAddresses,
                        'IN'
                    ],
                    'AND'
                ],
                $params
            );


            if($res){
                //If we are here, we pushed the resources to the collection. Now we may need to push them to order..
                if($removeFromOrder){
                    $orderParams = $this->defaultSettingsParams;
                    $orderParams['tableName'] = $this->resourceCollectionTableName;
                    $orderParams['columnNames'] = [['Resource_Type','Collection_Name'],'Collection_Order'];
                    $orderParams['columnIdentifier'] = [$type,$collection];
                    $order = ($existingCollection['@']['Collection_Order'] != null)?
                        $existingCollection['@']['Collection_Order'] : '';
                    $orderHandler = new OrderHandler($this->settings,$orderParams);
                    $orderHandler->removeFromOrderMultiple(
                        $addresses,'name',
                        array_merge($params,['rowExists' => true,'order'=>$order,'useCache'=>false])
                    );
                }
                //.., and delete the collection cache
                if($verbose)
                    echo 'Deleting collection cache of '.$collection.EOL;

                if(!$test)
                    $this->RedisHandler->call(
                        'del',
                        [
                            [
                                $type.'_'.$this->resourceCollectionItemsCacheName.$collection,
                                $type.'_'.$this->resourceCollectionCacheName.$collection
                            ]
                        ]
                    );

                //Ok we're done
                return 0;

            }

            return -1;
        }

        /** Moves an item from one index in the collection order to another.
         *
         * @param int $from From what index
         * @param int $to To what index
         * @param string $collection - Name of the collection.
         * @param array $params
         *          'existingCollection' - Array, potential existing collection if we already got them earlier.
         * @returns int Codes
         *             -1 - Could not connect to DB
         *              0 - All good
         *              1 - Indexes do not exist in order
         *              2 - Collection does not exist
         */
        function moveCollectionOrder(int $from, int $to, string $collection, string $type, array $params){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(isset($params['existingCollection']))
                $existingCollection = $params['existingCollection'];
            else
                $existingCollection = $this->getResourceCollection($collection, $type,array_merge($params,['getMembers'=>false]));

            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                return $existingCollection == 1? 2 : -1;
            }

            //First, update Last Changed of the collection
            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                ],
                [
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
                        [$collection,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            //If we failed to set Last_Updated, We will return. Else,  update the order
            if($res){
                $orderParams = $this->defaultSettingsParams;
                $orderParams['tableName'] = $this->resourceCollectionTableName;
                $orderParams['columnNames'] = [['Resource_Type','Collection_Name'],'Collection_Order'];
                $orderParams['columnIdentifier'] = [$type,$collection];
                $order = ($existingCollection['@']['Collection_Order'] != null)?
                    $existingCollection['@']['Collection_Order'] : '';
                $orderHandler = new OrderHandler($this->settings,$orderParams);
                $res = $orderHandler->moveOrder(
                    $from,
                    $to,
                    array_merge($params,['rowExists' => true,'order'=>$order,'useCache'=>false])
                );

                //Deal with possible errors
                if($res == 3)
                    return -1;

                if($res == 1)
                    return 1;

                //delete the collection cache - but not the items!
                if($verbose)
                    echo 'Deleting collection cache of '.$collection.EOL;

                if(!$test)
                    $this->RedisHandler->call(
                        'del',
                        [
                            $type.'_'.$this->resourceCollectionCacheName.$collection
                        ]
                    );

                //Ok we're done
                return 0;

            }

            return -1;
        }

        /** Swaps 2 items in the collection order
         *
         * @param int $num1 index
         * @param int $num2 index
         * @param string $collection - Name of the collection.
         * @param array $params
         *          'existingCollection' - Array, potential existing collection if we already got them earlier.
         * @returns int Codes
         *             -1 - Could not connect to DB
         *              0 - All good
         *              1 - Indexes do not exist in order
         *              2 - Collection does not exist
         */
        function swapCollectionOrder(int $num1,int $num2, string $collection, string $type, array $params){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(isset($params['existingCollection']))
                $existingCollection = $params['existingCollection'];
            else
                $existingCollection = $this->getResourceCollection($collection, $type,array_merge($params,['getMembers'=>false]));

            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                return $existingCollection == 1? 2 : -1;
            }

            //First, update Last Changed of the collection
            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                ],
                [
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
                        [$collection,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            //If we failed to set Last_Updated, We will return. Else,  update the order
            if($res){
                $orderParams = $this->defaultSettingsParams;
                $orderParams['tableName'] = $this->resourceCollectionTableName;
                $orderParams['columnNames'] = [['Resource_Type','Collection_Name'],'Collection_Order'];
                $orderParams['columnIdentifier'] = [$type,$collection];
                $order = ($existingCollection['@']['Collection_Order'] != null)?
                    $existingCollection['@']['Collection_Order'] : '';
                $orderHandler = new OrderHandler($this->settings,$orderParams);
                $res = $orderHandler->swapOrder(
                    $num1,
                    $num2,
                    array_merge($params,['rowExists' => true,'order'=>$order,'useCache'=>false])
                );

                //Deal with possible errors
                if($res == 3)
                    return -1;

                if($res == 1)
                    return 1;

                //delete the collection cache - but not the items!
                if($verbose)
                    echo 'Deleting collection cache of '.$collection.EOL;

                if(!$test)
                    $this->RedisHandler->call(
                        'del',
                        [
                            $type.'_'.$this->resourceCollectionCacheName.$collection
                        ]
                    );

                //Ok we're done
                return 0;

            }

            return -1;

        }

        /** Adds all collection members to its order (at the end)
         *
         * @param string $collection - Name of the collection.
         * @param array $params
         *          'existingCollection' - Array, potential existing collection if we already got them earlier.
         * @returns int Codes
         *             -1 - Could not connect to DB
         *              0 - All good
         *              1 - Collection does not exist
         */
        function addAllToCollectionOrder(string $collection, string $type, array $params){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(isset($params['existingCollection']))
                $existingCollection = $params['existingCollection'];
            else
                $existingCollection = $this->getResourceCollection($collection, $type,array_merge($params,['getMembers'=>true]));

            $addresses = [];

            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                return $existingCollection;
            }

            foreach($existingCollection as $address=>$member){
                if($address!='@')
                    array_push($addresses,$address);
            }

            //First, update Last Changed of the collection
            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                ],
                [
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
                        [$collection,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            //If we failed to set Last_Updated, We will return. Else,  update the order
            if($res){
                $orderParams = $this->defaultSettingsParams;
                $orderParams['tableName'] = $this->resourceCollectionTableName;
                $orderParams['columnNames'] = [['Resource_Type','Collection_Name'],'Collection_Order'];
                $orderParams['columnIdentifier'] = [$type,$collection];
                $order = '';
                $orderHandler = new OrderHandler($this->settings,$orderParams);
                $res = $orderHandler->pushToOrderMultiple(
                    $addresses,
                    array_merge($params,['rowExists' => true,'order'=>$order,'useCache'=>false])
                );

                //Deal with possible errors
                if($res[$addresses[0]] === 3)
                    return -1;

                //delete the collection cache - but not the items!
                if($verbose)
                    echo 'Deleting collection cache of '.$collection.EOL;

                if(!$test)
                    $this->RedisHandler->call(
                        'del',
                        [
                            [
                                $type.'_'.$this->resourceCollectionItemsCacheName.$collection,
                                $type.'_'.$this->resourceCollectionCacheName.$collection
                            ]
                        ]
                    );

                return 0;
            }
            else
                return -1;
        }

        /** Removes all members from collection order (sets it to null)
         *
         * @param string $collection - Name of the collection.
         * @param array $params
         *          'existingCollection' - Array, potential existing collection if we already got them earlier.
         * @returns int Codes
         *             -1 - Could not connect to DB
         *              0 - All good
         *              1 - Collection does not exist
         */
        function removeAllFromCollectionOrder(string $collection, string $type, array $params){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(isset($params['existingCollection']))
                $existingCollection = $params['existingCollection'];
            else
                $existingCollection = $this->getResourceCollection($collection, $type,array_merge($params,['getMembers'=>false]));

            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                return $existingCollection;
            }

            //First, update Last Changed of the collection
            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                    $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Order = NULL',
                ],
                [
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLHandler->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
                        [$collection,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            //If we failed to set Last_Updated, We will return. Else,  update the order
            if(!$res)
                return -1;

            //delete the collection cache - but not the items!
            if($verbose)
                echo 'Deleting collection cache of '.$collection.EOL;

            if(!$test)
                $this->RedisHandler->call(
                    'del',
                    [
                        [
                            $type.'_'.$this->resourceCollectionItemsCacheName.$collection,
                            $type.'_'.$this->resourceCollectionCacheName.$collection
                        ]
                    ]
                );

            return 0;
        }

    }
}