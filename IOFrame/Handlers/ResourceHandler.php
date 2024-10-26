<?php
namespace IOFrame\Handlers{
    define('IOFrameHandlersResourceHandler',true);

    /*  This class manages resources.
     *  Ranges from uploading/deleting/viewing images, creating image galleries, managing CSS/JS links and more.
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class ResourceHandler extends \IOFrame\Abstract\DBWithCache
    {

        public ?\IOFrame\Handlers\SettingsHandler $siteSettings = null;
        public ?\IOFrame\Handlers\SettingsHandler $resourceSettings = null;

        /**
         * @var array Extra columns to get/set from/to the DB (for normal resources)
         */
        protected array $extraColumns = [];

        /**
         * @var array An associative array for each extra column defining how it can be set on setResource
         *            For each column, if a matching input isn't set (or is null), it cannot be set.
         */
        protected array $extraInputs = [
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
        protected string $resourceTableName = 'RESOURCES';

        /**
         * @var string The cache name for single resources.
         */
        protected string $resourceCacheName = 'ioframe_resource_';

        /**
         * @var string The table name for the single resources db
         */
        protected string $resourceCollectionTableName = 'RESOURCE_COLLECTIONS';

        /**
         * @var string The cache name for resource collection.
         */
        protected string $resourceCollectionCacheName = 'ioframe_resource_collection_';

        /**
         * @var string The cache name for a resource collection's items.
         */
        protected string $resourceCollectionItemsCacheName = 'ioframe_resource_collection_items_';

        /** Standard constructor
         *
         * @param \IOFrame\Handlers\SettingsHandler $settings The standard settings object
         * @param array $params of the form:
         *          'type'              - string, forces a specific resource type ('img', 'js' and 'css' mainly)
         *
         * @throws \Exception
         * @throws \Exception
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, array $params = []){

            parent::__construct($settings,array_merge($params,['logChannel'=>\IOFrame\Definitions::LOG_RESOURCES_CHANNEL]));

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
                $this->siteSettings = new \IOFrame\Handlers\SettingsHandler(
                    $this->settings->getSetting('absPathToRoot').'/localFiles/siteSettings/',
                    $this->defaultSettingsParams
                );

            if(isset($params['resourceSettings']))
                $this->resourceSettings = $params['resourceSettings'];
            else
                $this->resourceSettings = new \IOFrame\Handlers\SettingsHandler(
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
         *          ------ Using the parameters below disables caching ------
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
        function getResources(array $addresses, string $type, array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $extraDBFilters = $params['extraDBFilters'] ?? [];
            $extraCacheFilters = $params['extraCacheFilters'] ?? [];
            $createdAfter = $params['createdAfter'] ?? null;
            $createdBefore = $params['createdBefore'] ?? null;
            $changedAfter = $params['changedAfter'] ?? null;
            $changedBefore = $params['changedBefore'] ?? null;
            $includeRegex = $params['includeRegex'] ?? null;
            $excludeRegex = $params['excludeRegex'] ?? null;
            $ignoreLocal = $params['ignoreLocal'] ?? null;
            $onlyLocal = $params['onlyLocal'] ?? null;
            $dataTypeNotNull = $params['dataTypeNotNull'] ?? null;
            $dataType = $params['dataType'] ?? null;
            $ignoreBlob = $params['ignoreBlob'] ?? false;
            $orderBy = $params['orderBy'] ?? null;
            $orderType = $params['orderType'] ?? null;
            $limit = $params['limit'] ?? null;
            $offset = $params['offset'] ?? null;
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];

            $retrieveParams = $params;
            $extraDBConditions = [];
            $extraCacheConditions = [];

            //If we are using any of this functionality, we cannot use the cache
            if( $orderBy || $orderType || $offset || $limit){
                $retrieveParams['useCache'] = false;
                $retrieveParams['orderBy'] = $orderBy?: null;
                $retrieveParams['orderType'] = $orderType?: 0;
                $retrieveParams['limit'] =  $limit?: null;
                $retrieveParams['offset'] =  $offset?: null;
            }

            $columns = array_merge(['Resource_Type','Address','Resource_Local','Minified_Version','Version','Created','Last_Updated',
                'Text_Content','Data_Type'],$this->extraColumns);
            if(!$ignoreBlob)
                $columns = array_merge($columns,['Blob_Content']);

            //Create all the conditions for the db/cache
            $extraCacheConditions[] = ['Resource_Type', $type, '='];
            $extraDBConditions[] = ['Resource_Type', [$type, 'STRING'], '='];

            if($createdAfter!== null){
                $cond = ['Created',$createdAfter,'>'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($createdBefore!== null){
                $cond = ['Created',$createdBefore,'<'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($changedAfter!== null){
                $cond = ['Last_Updated',$changedAfter,'>'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($changedBefore!== null){
                $cond = ['Last_Updated',$changedBefore,'<'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($includeRegex!== null){
                $extraCacheConditions[] = ['Address', $includeRegex, 'RLIKE'];
                $extraDBConditions[] = ['Address', [$includeRegex, 'STRING'], 'RLIKE'];
            }

            if($excludeRegex!== null){
                $extraCacheConditions[] = ['Address', $excludeRegex, 'NOT RLIKE'];
                $extraDBConditions[] = ['Address', [$excludeRegex, 'STRING'], 'NOT RLIKE'];
            }
            //ignoreLocal and onlyLocal are connected
            if($onlyLocal){
                $cond = ['Resource_Local',1,'='];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }
            elseif($ignoreLocal){
                $cond = ['Resource_Local',0,'='];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($dataTypeNotNull !== null){
                $cond = $dataTypeNotNull ? [['Data_Type','ISNULL'],'NOT'] : ['Data_Type','ISNULL'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($dataType !== null){
                $dataType = $dataType === '@'? null: $dataType;
                $cond = ['Data_Type',$dataType,'='];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            $extraDBConditions = array_merge($extraDBConditions,$extraDBFilters);
            $extraCacheConditions = array_merge($extraCacheConditions,$extraCacheFilters);

            if($extraCacheConditions!=[]){
                $extraCacheConditions[] = 'AND';
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }
            if($extraDBConditions!=[]){
                $extraDBConditions[] = 'AND';
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            if($addresses == []){
                $results = [];
                $res = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().$this->resourceTableName,
                    $extraDBConditions,
                    $columns,
                    $retrieveParams
                );
                $count = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().$this->resourceTableName,
                    $extraDBConditions,
                    ['COUNT(*)'],
                    array_merge($retrieveParams,['limit'=>null])
                );
                if(is_array($res)){
                    $resCount = isset($res[0]) ? count($res[0]) : 0;
                    foreach($res as $resultArray){
                        for($i = 0; $i<$resCount/2; $i++)
                            unset($resultArray[$i]);
                        if($safeStr)
                            if($resultArray['Text_Content'] !== null)
                                $resultArray['Text_Content'] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($resultArray['Text_Content']);
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
                        if( (gettype($result) === 'array') && ($result['Text_Content'] !== null) )
                            $results[$address]['Text_Content'] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($result['Text_Content']);
                    }

                return $results;
            }

        }


        /** Sets a resource. Will create each non-existing address, or overwrite an existing one.
         * For each of the parameters below except for address, set NULL to ignore.
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
         * For each of the parameters below except for address, set NULL to ignore.
         * @param array $inputs Array of input arrays in the same order as the inputs in setResource, EXCLUDING 'type'
         * @param string $type All resources must be of the same type
         * @param array $params from setResource
         * @returns int[] Array of the form
         *          <Address> => <code>
         *          where the codes come from setResource()
         */
        function setResources(array $inputs, string $type, array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $override = $params['override'] ?? false;
            $update = $params['update'] ?? false;
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];
            $mergeMeta = !isset($params['mergeMeta']) || $params['mergeMeta'];

            $addresses = [];
            $existingAddresses = [];
            $addressMap = [];
            $results = [];
            $resourcesToSet = [];
            $currentTime = (string)time();

            foreach($inputs as $index=>$inputArr){
                $addresses[] = $inputArr['address'];
                $results[$inputArr['address']] = -1;
                $addressMap[$index] = $inputArr['address'];
            }

            //Figure out which extra columns to set, and what is their input
            $extraColumns = [];
            $extraInputs = [];
            foreach($this->extraColumns as $extraColumn){
                if($this->extraInputs[$extraColumn]){
                    $extraColumns[] = $extraColumn;
                    $extraInputs[] = [
                        'input' => $this->extraInputs[$extraColumn]['input'],
                        'default' => $this->extraInputs[$extraColumn]['default'] ?? null
                    ];
                }
            }

            $existing = $params['existing'] ?? $this->getResources($addresses, $type, array_merge($params, ['updateCache' => false, 'ignoreBlob' => false]));

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
                        if(!isset($inputArr['local']))
                            $inputs[$index]['local'] = true;
                        //Set minified to false if not provided
                        if(!isset($inputArr['minified']))
                            $inputs[$index]['minified'] = false;
                        //text
                        if(!isset($inputArr['text']))
                            $inputs[$index]['text'] = null;
                        elseif($safeStr)
                            $inputs[$index]['text'] = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($inputArr['text']);
                        //blob
                        if(!isset($inputArr['blob']))
                            $inputs[$index]['blob'] = null;
                        //data type
                        if(!isset($inputArr['dataType']))
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
                            $arrayToSet[] = [$inputs[$index][$extraInputArr['input']], 'STRING'];
                        }

                        //Add the resource to the array to set
                        $resourcesToSet[] = $arrayToSet;
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
                    $existingAddresses[] = $type . '_' . $this->resourceCacheName . $inputArr['address'];
                    //Complete every field that is NULL with the existing resource
                    //local
                    if(!isset($inputArr['local']))
                        $inputs[$index]['local'] = $existing[$addressMap[$index]]['Resource_Local'];
                    //minified
                    if(!isset($inputs[$index]['minified']))
                        $inputs[$index]['minified'] = $existing[$addressMap[$index]]['Minified_Version'];
                    //text
                    if(!isset($inputs[$index]['text']))
                        $inputs[$index]['text'] = $existing[$addressMap[$index]]['Text_Content'];
                    else{
                        //This is where we merge the arrays as JSON if they are both valid json
                        if( $mergeMeta &&
                            \IOFrame\Util\PureUtilFunctions::is_json($inputs[$index]['text']) &&
                            \IOFrame\Util\PureUtilFunctions::is_json($existing[$addressMap[$index]]['Text_Content'])
                        ){
                            $inputJSON = json_decode($inputs[$index]['text'],true);
                            $existingJSON = json_decode($existing[$addressMap[$index]]['Text_Content'],true);
                            if($inputJSON == null)
                                $inputJSON = [];
                            if($existingJSON == null)
                                $existingJSON = [];
                            $inputs[$index]['text'] =
                                json_encode(\IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($existingJSON,$inputJSON,['deleteOnNull'=>true]));
                            if($inputs[$index]['text'] == '[]')
                                $inputs[$index]['text'] = null;
                        }
                        //Here we convert back to safeString
                        if($safeStr && $inputs[$index]['text'] !== null)
                            $inputs[$index]['text'] = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($inputs[$index]['text']);
                    }
                    //blob
                    if(!isset($inputs[$index]['blob']))
                        $inputs[$index]['blob'] = $existing[$addressMap[$index]]['Blob_Content'];
                    //data type
                    if(!isset($inputs[$index]['dataType']))
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
                        if(!isset($inputs[$index][$extraInputArr['input']]))
                            $inputs[$index][$extraInputArr['input']] = $existing[$addressMap[$index]][$extraColumns[$extraIndex]];
                        $arrayToSet[] = [$inputs[$index][$extraInputArr['input']], 'STRING'];
                    }

                    //Add the resource to the array to set
                    $resourcesToSet[] = $arrayToSet;
                }
            }

            //If we got nothing to set, return
            if($resourcesToSet==[])
                return $results;
            $res = $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix().$this->resourceTableName,
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

                    if(!$test && $useCache)
                        $this->RedisManager->call('del',[$existingAddresses]);
                }
            }
            else
                $this->logger->error('Failed to insert resources to db',['items'=>$resourcesToSet]);

            return $results;
        }

        /** Renames a resource
         * @param string $address Old address
         * @param string $newAddress New address
         * @param string $type
         * @param array $params of the form:
         *          'copy'             - bool, default false - copies the file instead of moving it
         *          'existingAddresses' - Array, potential existing addresses if we already got them earlier.
         * @return int
         */
        function renameResource( string $address, string $newAddress, string $type, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $copy = $params['copy'] ?? false;
            $existingAddresses = $params['existingAddresses'] ?? $this->getResources([$address, $newAddress], $type, $params);
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
                        $oldColumns[] = $key;
                        if(gettype($oldInfo) === 'string')
                            $oldInfo = [$oldInfo,'STRING'];
                        if($key === 'Address')
                            $newValues[] = [$newAddress, 'STRING'];
                        else
                            $newValues[] = $oldInfo;
                    }
                }
                //Just insert the new values into the table
                $res = $this->SQLManager->insertIntoTable(
                    $this->SQLManager->getSQLPrefix().$this->resourceTableName,
                    $oldColumns,
                    [$newValues],
                    $params
                );
            }
            else
                $res = $this->SQLManager->updateTable(
                    $this->SQLManager->getSQLPrefix().$this->resourceTableName,
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
                if(!$test && $useCache)
                    $this->RedisManager->call('del',$type.'_'.$this->resourceCacheName.$address);
                if($verbose)
                    echo 'Deleting '.$this->resourceCacheName.$address.' from cache!'.EOL;
                return 0;
            }
            else{
                $this->logger->error('Failed to move resource',['oldAddress'=>$address,'newAddress'=>$newAddress]);
                return -1;
            }
        }

        /** Deletes a resource
         * @param string $address Resource address
         * @param string $type
         * @param array $params
         * @return mixed
         */
        function deleteResource(string $address, string $type, array $params){
            return $this->deleteResources([$address],$type,$params)[$address];
        }

        /** Deletes resources.
         *
         * @param array $addresses
         * @param string $type
         * @param array $params
         *          'checkExisting' - bool, default true - whether to check for existing addresses
         * @return array|int
         */
        function  deleteResources(array $addresses, string $type, array $params = []): int|array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $checkExisting = $params['checkExisting'] ?? true;

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
                    $addressesToDelete[] = [$address, 'STRING'];
                    $addressesToDeleteFromCache[] = $type . '_' . $this->resourceCacheName . $address;
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

            $res = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().$this->resourceTableName,
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

                    if(!$test && $useCache)
                        $this->RedisManager->call('del',[$addressesToDeleteFromCache]);
                }
            }
            else{
                $this->logger->error('Failed to delete db resources',['addresses'=>$addresses,'type'=>$type]);
                return -1;
            }

            return $results;
        }


        /** Increments a version of something.
         *
         * @param string $address Address of the resource
         * @param string $type
         * @param array $params
         * @return int
         */
        function incrementResourceVersion(string $address, string $type, array $params = []): int {
            return $this->incrementResourcesVersions([$address],$type,$params);
        }


        /** Increments a version of something.
         *
         * @param array $addresses
         * @param string $type
         * @param array $params same as incrementResourcesVersions
         * @return int
         */
        function incrementResourcesVersions(array $addresses, string $type, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $dbAddresses =[];
            $cacheAddresses = [];

            foreach($addresses as $address){
                $dbAddresses[] = [$address, 'STRING'];
                $cacheAddresses[] = $type . '_' . $this->resourceCacheName . $address;
            }

            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().$this->resourceTableName,
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

            if(!$res){
                $this->logger->error('Failed to increment resource versions',['addresses'=>$addresses,'type'=>$type]);
                return -1;
            }

            if(count($cacheAddresses) == 1)
                $cacheAddresses = $cacheAddresses[0];

            if(!$test && $useCache)
                $this->RedisManager->call('del',[$cacheAddresses]);
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
         * @return array|int
         */
        function getCollectionsOfResource(string $address , string $type, array $params = []): array|int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $res = $this->SQLManager->selectFromTable(
                $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS_MEMBERS',
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
                    $collections[] = $res[$i][0];
                }
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
         * @return mixed
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
         *       <Collection Name> => Int|array described in getResourceCollection(),
         *      ...
         *  ]
         *      on full search, the array will include the item '@' of the form:
         *      {
         *          '#':<number of total results>
         *      }
         * */
        function getResourceCollections(array $names, string $type, array $params): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $getMembers = $params['getMembers'] ?? false;
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];

            //If there are no names, show the user everything in the DB
            if($names ===[]){
                if($verbose)
                    echo 'Only returning all resource collection info!'.EOL;


                $createdAfter = $params['createdAfter'] ?? null;
                $createdBefore = $params['createdBefore'] ?? null;
                $changedAfter = $params['changedAfter'] ?? null;
                $changedBefore = $params['changedBefore'] ?? null;
                $includeRegex = $params['includeRegex'] ?? null;
                $excludeRegex = $params['excludeRegex'] ?? null;
                $orderBy = $params['orderBy'] ?? null;
                $orderType = $params['orderType'] ?? null;
                $limit = $params['limit'] ?? null;
                $offset = $params['offset'] ?? null;

                $dbConditions = [['Resource_Type',[$type,'STRING'],'=']];

                $retrieveParams = $params;
                $retrieveParams['orderBy'] = $orderBy?: null;
                $retrieveParams['orderType'] = $orderType?: 0;
                $retrieveParams['limit'] =  $limit?: null;
                $retrieveParams['offset'] =  $offset?: null;

                if($createdAfter!== null){
                    $cond = ['Created',$createdAfter,'>'];
                    $dbConditions[] = $cond;
                }

                if($createdBefore!== null){
                    $cond = ['Created',$createdBefore,'<'];
                    $dbConditions[] = $cond;
                }

                if($changedAfter!== null){
                    $cond = ['Last_Updated',$changedAfter,'>'];
                    $dbConditions[] = $cond;
                }

                if($changedBefore!== null){
                    $cond = ['Last_Updated',$changedBefore,'<'];
                    $dbConditions[] = $cond;
                }

                if($includeRegex!== null){
                    $dbConditions[] = ['Collection_Name', [$includeRegex, 'STRING'], 'RLIKE'];
                }

                if($excludeRegex!== null){
                    $dbConditions[] = ['Collection_Name', [$excludeRegex, 'STRING'], 'NOT RLIKE'];
                }

                if($dbConditions!=[]){
                    $dbConditions[] = 'AND';
                }

                $res = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().$this->resourceCollectionTableName,
                    $dbConditions,
                    [],
                    $retrieveParams
                );

                $count = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().$this->resourceCollectionTableName,
                    $dbConditions,
                    ['COUNT(*)'],
                    array_merge($retrieveParams,['limit'=>null])
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
                            $array['Meta'] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($array['Meta']);
                        $results[$name] = [
                            '@' => $array
                        ];
                    }
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
                            \IOFrame\Util\SafeSTRFunctions::safeStr2Str($collectionInfo[$name]['Meta']) : $collectionInfo[$name]['Meta'];
                    $results[$name] = [
                        '@' => $collectionInfo[$name]
                    ];
                    //If the collection was ordered, mark its order as members you need to get from the cache.
                    if($collectionInfo[$name]['Collection_Order'] && $getMembers){
                        $order = explode(',',$collectionInfo[$name]['Collection_Order']);
                        $resourceTargets[$name] = [];
                        foreach($order as $item){
                            $resourceTargets[$name][] = $item;
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
                $collectionTable = $this->SQLManager->getSQLPrefix().$this->resourceCollectionTableName;
                $collectionsResourcesTable = $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS_MEMBERS';
                $resourcesTable = $this->SQLManager->getSQLPrefix().$this->resourceTableName;
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
                    $cachedResult = $useCache ? $this->RedisManager->call('get',[$type.'_'.$this->resourceCollectionItemsCacheName.$name]) : false;

                    //If we got a hit, get the relevant items normally
                    if($cachedResult && \IOFrame\Util\PureUtilFunctions::is_json($cachedResult)){
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

                        $collectionMembers = $this->SQLManager->selectFromTable(
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
                            $itemsToCache[] = $array['Address'];
                            if($safeStr)
                                $results[$name][$array['Address']]['Text_Content'] = ($results[$name][$array['Address']]['Text_Content'])?
                                    \IOFrame\Util\SafeSTRFunctions::safeStr2Str($results[$name][$array['Address']]['Text_Content']) : $results[$name][$array['Address']]['Text_Content'];
                        }

                        if($itemsToCache !=[]){
                            if($verbose)
                                echo 'Pushing items '.json_encode($itemsToCache).' of collection '.$name.' into cache!'.EOL;
                            if(!$test && $useCache)
                                $this->RedisManager->call(
                                    'set',
                                    [$type.'_'.$this->resourceCollectionItemsCacheName.$name,json_encode($itemsToCache)]
                                );
                        }

                    }
                }
            }

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
         * @returns Int|array
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
         *       <Collection Name> => Int|array described in setResourceCollection(),
         *      ...
         *  ]
         * */
        function setResourceCollections(array $inputs, string $type, array $params = []): int|array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $update = $params['update'] ?? false;
            //If we are updating, then by default we allow overwriting
            if(!$update)
                $override = $params['override'] ?? false;
            else
                $override =true;
            $mergeMeta = !isset($params['mergeMeta']) || $params['mergeMeta'];
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];

            $results = [];
            $names = [];
            $nameToIndexMap = [];
            $currentTime = (string)time();

            foreach($inputs as $index => $inputArray){
                $name = $inputArray[0];
                $names[] = $name;
                $nameToIndexMap[$name] = $index;
                $results[$name] = -1;
            }

            //Get existing collections if override is false or update is true
            if(!$override || $update){
                $existing = $params['existingCollections'] ?? $this->getResourceCollections($names, $type, array_merge($params, ['getMembers' => false, 'updateCache' => false]));

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
                            if($mergeMeta && \IOFrame\Util\PureUtilFunctions::is_json($newMeta) && \IOFrame\Util\PureUtilFunctions::is_json($existingMeta) ){
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
            foreach($inputs as $inputArray){
                //Parse the meta
                $meta = $inputArray[1] ?? null;
                if($meta !== null){
                    if($safeStr)
                        $meta = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($meta);
                    $meta = [$meta,'STRING'];
                }

                //Check if we are changing an existing gallery
                if(is_array($existing[$inputArray[0]] ?? null))
                    $createdTime = $existing[$inputArray[0]]['@']['Created'];
                else
                    $createdTime = $currentTime;

                $toSet[] = [
                    [$inputArray[0], 'STRING'],
                    [$type, 'STRING'],
                    [$createdTime, 'STRING'],
                    [$currentTime, 'STRING'],
                    $meta
                ];
            }

            $res = $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix().$this->resourceCollectionTableName,
                ['Collection_Name','Resource_Type','Created','Last_Updated','Meta'],
                $toSet,
                array_merge($params, ['onDuplicateKey'=>true])
            );

            if($res){
                $cacheAddresses = [];

                foreach($results as $index => $result){
                    if($result == -1){
                        $results[$index] = 0;
                        $cacheAddresses[] = $type . '_' . $this->resourceCollectionCacheName . $index;
                    }
                }

                if(!$test && $useCache)
                    $this->RedisManager->call('del',[$cacheAddresses]);
                if($verbose)
                    echo 'Deleting '.json_encode($cacheAddresses).' from cache!'.EOL;
            }
            else{
                $this->logger->error('Failed to set resource collections',['collections'=>$toSet,'type'=>$type]);
                return -1;
            }

            return $results;
        }


        /* Deletes a resource collection.
         * @param string $name Name of the collection
         * @param string $type Type of the collection
         * @returns Int|array
         *          Possible codes:
         *          -1 - Could not connect to db.
         *           0 - All good
         * */
        function deleteResourceCollection(string $name, string $type, array $params = []): int {
            return $this->deleteResourceCollections([$name],$type,$params);
        }

        /* Deletes resource collections.
         * @param array $names
         * @param array $type
         * @returns Int|array
         *          Possible codes:
         *          -1 - Could not connect to db.
         *           0 - All good
         * */
        function deleteResourceCollections(array $names, string $type, array $params = []): int {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $dbNames = [];

            foreach($names as $name)
                $dbNames[] = [$name, 'STRING'];

            $res = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().$this->resourceCollectionTableName,
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

                    if(!$test && $useCache)
                        $this->RedisManager->call(
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
            else{
                $this->logger->error('Failed to delete resource collections',['collections'=>$names,'type'=>$type]);
                return -1;
            }

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
         * @returns array of the form:
         *      <address> => <Code>
         *      Where the codes come from addResourceToCollection, and all of them are 2 if the collection does not exist.
         */
        function addResourcesToCollection( array $addresses, string $collection, string $type, array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $pushToOrder = $params['pushToOrder'] ?? false;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $existingAddresses = $params['existingAddresses'] ?? $this->getResources($addresses, $type, $params);

            $existingCollection = $params['existingCollection'] ?? $this->getResourceCollection($collection, $type, array_merge($params, ['getMembers' => true]));

            $results = [];
            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                foreach($addresses as $address)
                    $results[$address] = ($existingAddresses == -1) ? -1 : 2;
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
                $toSet[] = [[$type, 'STRING'], [$collection, 'STRING'], [$address, 'STRING']];
            }

            //First, update Last Changed of the collection
            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                ],
                [
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
                        [$collection,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            //If we failed to set Last_Updated, exit. Else write the changes to the DB.
            if(!$res){
                $this->logger->error('Failed to set last updated before adding resources to collection',['resources'=>$addresses,'collection'=>$collection,'type'=>$type]);
                return $results;
            }

            $res = $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS_MEMBERS',
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
                    $orderHandler = new \IOFrame\Managers\OrderManager($this->settings,$orderParams);
                    $orderHandler->pushToOrderMultiple(
                        $addresses,
                        array_merge($params,['rowExists' => true,'order'=>$order,'useCache'=>false])
                    );
                }
                //.., and delete the collection cache
                if($verbose)
                    echo 'Deleting collection cache of '.$collection.EOL;

                if(!$test && $useCache)
                    $this->RedisManager->call(
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
            else
                $this->logger->error('Failed to add resources to collection',['resources'=>$addresses,'collection'=>$collection,'type'=>$type]);

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
            $removeFromOrder = $params['removeFromOrder'] ?? false;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $existingCollection = $params['existingCollection'] ?? $this->getResourceCollection($collection, $type, array_merge($params, ['getMembers' => false]));

            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                return $existingCollection;
            }

            //If we are here, there are addresses we can add to a collection
            $dbAddresses = [];

            foreach($addresses as $address){
                $dbAddresses[] = [$address, 'STRING'];
            }

            //First, update Last Changed of the collection
            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                ],
                [
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
                        [$collection,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            //If we failed to set Last_Updated, exit. Else write the changes to the DB.
            if(!$res){
                $this->logger->error('Failed to set last updated before removing resources from collection',['resources'=>$addresses,'collection'=>$collection,'type'=>$type]);
                return -1;
            }

            $res = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS_MEMBERS',
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
                    $orderHandler = new \IOFrame\Managers\OrderManager($this->settings,$orderParams);
                    $orderHandler->removeFromOrderMultiple(
                        $addresses,'name',
                        array_merge($params,['rowExists' => true,'order'=>$order,'useCache'=>false])
                    );
                }
                //.., and delete the collection cache
                if($verbose)
                    echo 'Deleting collection cache of '.$collection.EOL;

                if(!$test && $useCache)
                    $this->RedisManager->call(
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
            else
                $this->logger->error('Failed to remove resources from collection',['resources'=>$addresses,'collection'=>$collection,'type'=>$type]);

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
        function moveCollectionOrder(int $from, int $to, string $collection, string $type, array $params): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $existingCollection = $params['existingCollection'] ?? $this->getResourceCollection($collection, $type, array_merge($params, ['getMembers' => false]));

            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                return $existingCollection == 1? 2 : -1;
            }

            //First, update Last Changed of the collection
            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                ],
                [
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
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
                $orderHandler = new \IOFrame\Managers\OrderManager($this->settings,$orderParams);
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

                if(!$test && $useCache)
                    $this->RedisManager->call(
                        'del',
                        [
                            $type.'_'.$this->resourceCollectionCacheName.$collection
                        ]
                    );

                //Ok we're done
                return 0;

            }
            else
                $this->logger->error('Failed to set last updated before move in resource collection order',['from'=>$from,'to'=>$to,'collection'=>$collection,'type'=>$type]);

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
        function swapCollectionOrder(int $num1,int $num2, string $collection, string $type, array $params): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $existingCollection = $params['existingCollection'] ?? $this->getResourceCollection($collection, $type, array_merge($params, ['getMembers' => false]));

            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                return $existingCollection == 1? 2 : -1;
            }

            //First, update Last Changed of the collection
            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                ],
                [
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
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
                $orderHandler = new \IOFrame\Managers\OrderManager($this->settings,$orderParams);
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

                if(!$test && $useCache)
                    $this->RedisManager->call(
                        'del',
                        [
                            $type.'_'.$this->resourceCollectionCacheName.$collection
                        ]
                    );

                //Ok we're done
                return 0;

            }
                $this->logger->error('Failed to set last updated before swap in resource collection order',['num1'=>$num1,'num2'=>$num2,'collection'=>$collection,'type'=>$type]);

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
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $existingCollection = $params['existingCollection'] ?? $this->getResourceCollection($collection, $type, array_merge($params, ['getMembers' => true]));

            $addresses = [];

            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                return $existingCollection;
            }

            foreach($existingCollection as $address=>$member){
                if($address!='@')
                    $addresses[] = $address;
            }

            //First, update Last Changed of the collection
            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                ],
                [
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
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
                $orderHandler = new \IOFrame\Managers\OrderManager($this->settings,$orderParams);
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

                if(!$test && $useCache)
                    $this->RedisManager->call(
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
                $this->logger->error('Failed to add all resources to collection order',['collection'=>$collection,'type'=>$type]);

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
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $existingCollection = $params['existingCollection'] ?? $this->getResourceCollection($collection, $type, array_merge($params, ['getMembers' => false]));

            //If we failed to connect to db for the collection, or it does not exist..
            if(!is_array($existingCollection)){
                return $existingCollection;
            }

            //First, update Last Changed of the collection
            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().$this->resourceCollectionTableName,
                [
                    $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Last_Updated = '.time(),
                    $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Order = NULL',
                ],
                [
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Resource_type',
                        [$type,'STRING'],
                        '='
                    ],
                    [
                        $this->SQLManager->getSQLPrefix().'RESOURCE_COLLECTIONS.Collection_Name',
                        [$collection,'STRING'],
                        '='
                    ],
                    'AND'
                ],
                $params
            );

            //If we failed to set Last_Updated, We will return. Else,  update the order
            if(!$res){
                $this->logger->error('Failed to remove all resources from collection order',['collection'=>$collection,'type'=>$type]);
                return -1;
            }

            //delete the collection cache - but not the items!
            if($verbose)
                echo 'Deleting collection cache of '.$collection.EOL;

            if(!$test && $useCache)
                $this->RedisManager->call(
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