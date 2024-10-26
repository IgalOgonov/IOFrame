<?php
namespace IOFrame\Abstract{

    define('IOFrameAbstractDBWithCache',true);

    /**
     * To be extended by modules operate user info (login, register, etc)
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    abstract class DBWithCache extends \IOFrame\Abstract\DB
    {
        /** @var \IOFrame\Managers\RedisManager|null $RedisManager a redis-PHP handler
        */
        public mixed $RedisManager;

        /**
         * @var Int Tells us for how long to cache stuff by default.
         * 0 means indefinitely.
         */
        protected mixed $cacheTTL = 3600;

        /**
         * @var int maximum size of an item (in bytes, since each character is UTF8 encoded)
         *          Defaults to, from lowest to highest priority: 64kb, siteSettings->getSetting('maxCacheSize'), $params['maxCacheSize'];
         */
        protected mixed $maxCacheSize = 65536;

        public array $defaultSettingsParams = [];

        /**
         * Basic construction function
         * @param \IOFrame\Handlers\SettingsHandler $localSettings Settings handler containing LOCAL settings.
         * @param array $params An potentially containing an SQLManager and/or a logger and/or a RedisManager.
         */
        public function __construct(\IOFrame\Handlers\SettingsHandler $localSettings, array $params = []){

            parent::__construct($localSettings,$params);

            //Set defaults
            if(!isset($params['RedisManager']))
                $this->RedisManager = null;
            else
                $this->RedisManager = $params['RedisManager'];

            if($this->RedisManager !== null){
                $this->defaultSettingsParams['RedisManager'] = $this->RedisManager;
            }

            $this->defaultSettingsParams['useCache'] = $this->defaultSettingsParams['useCache']??!empty($this->RedisManager);

            //If we are caching for a custom duration, this should stated here
            if(isset($params['cacheTTL']))
                $this->cacheTTL = $params['cacheTTL'];

            if(isset($params['maxCacheSize']))
                $this->maxCacheSize = $params['maxCacheSize'];
            elseif($this->siteSettings !== null && $this->siteSettings->getSetting('maxCacheSize'))
                $this->maxCacheSize = $this->siteSettings->getSetting('maxCacheSize');
        }

        /** Gets the requested items from the db/cache.
         * @param array $targets Array of keys. If empty, will ignore the cache and get everything from the DB.
         * @param array|string $keyCol Key column name
         * @param string $tableName Name of the table WITHOUT THE PREFIX
         * @param string|null $cacheName Name of cache prefix
         * @param string[] $columns Array of column names.
         * @param array $params getFromTableByKey() params AND:
         *                  'type' - string ,default '' - Extra information about object type - used for verbose output.
         *                  'compareCol' - bool, default true - If true, will compare cache columns to requested columns,
         *                                 and only use the cached result of they match. Is ignored if columns are []
         *                  'columnConditions' - Array, simple condition rules a cache column must pass to be considered
         *                                      valid. The array is of the form:
         *                                      [
         *                                      [<Column Name>,<value>,<Condition>],
         *                                      [<Column Name>,<value>,<Condition>],
         *                                      ...
         *                                       'AND'(Default) / 'OR'
         *                                      ]
         *                                      where possible conditions are '>','<','>=','<=','=', '!=', 'IN', 'INREV', 'ISNULL', 'RLIKE' and 'NOT RLIKE'.
         *                                      The difference between IN and INREV is that in the first, the 1st parameter is
         *                                      the name of the column that matches one of the strings in the 2nd parameter, while
         *                                      with INREV (REV is reverse) the 1st parameter is a string that matches one of the
         *                                      values of the column names in the 2nd parameter. In both cases, the both parameters
         *                                      can be strings - only the order matters.
         *                                      The conditions work the same as their MySQL counterparts.
         *                                      More complex conditions are not supported as of now
         *                  'extraDBConditions'   => Extra conditions one may pass directly to the DB, IF columnConditions is not set
         *                  'fixSpecialConditions' - bool, default false. If true, will check and change specific conditions
         *                                           that are invalid in SQL. Current list is:
         *                                          'INREV'
         *                  'useCache'  - Whether to use cache at all
         *                  'getFromCache' - Whether to try to get items from cache
         *                  'updateCache' - Whether to try to update cache with DB results
         *                  'lockCacheDuringUpdate' - int, default 0 - Whether to lock cache when populating from existing items in db.
         *                                          Value corresponds to time (in MILLISECONDS) for which we await the update
         *                  'extendTTL' - Whether to extend TTL when finding items - defaults to updateCache, but should be set
         *                                to false for items that can be deleted from the DB without direct calls (lke sub-items).
         *                                Note that when correct state is important, it's better to disable cache for such keys alltogether.
         *                  'extraKeyColumns' - a getFromTableByKey parameter, but if present, will discard normal identifier results.
         *                  'groupByFirstNKeys' => getFromTableByKey() parameter - if present, will only use up to the
         *                                         N first keys to identify the item. Read the getFromTableByKey() docs to
         *                                         understand this.
         * @return array Results of the form [$identifier => <result array>],
         *              where the result array is of the form [<Col name> => <Value>]
         *               If the item was not in the DB, will return the following codes instead of <result array>:
         *                  1 - The item was not found in the DB or cache
         *                 -1 - The item was not found in the cache, and failed to connect to DB.
         */
        protected function getFromCacheOrDB(
            array        $targets,
            array|string $keyCol,
            string       $tableName,
            string       $cacheName = null,
            array        $columns = [],
            array        $params = []
        ): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $type = $params['type'] ?? '';

            $compareCol = !isset($params['compareCol']) || $params['compareCol'];

            $fixSpecialConditions = $params['fixSpecialConditions'] ?? false;

            $columnConditions = $params['columnConditions'] ?? [];

            $missingErrorCode = 1;

            if(isset($params['useCache']))
                $useCache = $cacheName && $params['useCache'];
            else
                $useCache = $cacheName && isset($this->defaultSettingsParams['useCache']) && $this->defaultSettingsParams['useCache'];

            if(isset($params['getFromCache']))
                $getFromCache = $useCache && $params['getFromCache'];
            else
                $getFromCache = $useCache;

            if(isset($params['updateCache']))
                $updateCache = $useCache && $params['updateCache'];
            else
                $updateCache = $useCache;

            if(isset($params['lockCacheDuringUpdate']))
                $lockCacheDuringUpdate = $useCache ? $params['lockCacheDuringUpdate'] : 0;
            else
                $lockCacheDuringUpdate = 0;

            $cacheTTL = $params['cacheTTL'] ?? $this->cacheTTL ?? 3600;

            $extraKeyColumns = $params['extraKeyColumns'] ?? [];

            $ConcurrencyHandler = null;
            $UpdateLockPrefix = 'cache_being_updated_';
            if($lockCacheDuringUpdate){
                $ConcurrencyHandler = new \IOFrame\Managers\Extenders\RedisConcurrency($this->RedisManager);
            }

            $totalColCount = count($extraKeyColumns) + (is_array($keyCol) ? count($keyCol) : 1);

            if(isset($params['keyDelimiter'])){
                $keyDelimiter = $params['keyDelimiter'];
            }
            else{
                if(gettype($keyCol) === 'array' && $totalColCount > 1)
                    $keyDelimiter = '/';
                else
                    $keyDelimiter = '';
            }

            if(isset($params['groupByFirstNKeys']) && (is_array($keyCol) || count($extraKeyColumns) > 0)){
                $groupByFirstNKeys = max(0,min($params['groupByFirstNKeys'],$totalColCount-1));
            }
            else
                $groupByFirstNKeys = 0;

            $extendTTL = $params['extendTTL'] ?? $updateCache;

            $cacheResults = [];
            $results = [];
            $dbResults = [];
            $temp = [];

            $indexMap = [];
            $identifierMap = [];
            $cacheTargets = [];
            $cacheLocks = [];

            foreach($targets as $index=>$identifier) {
                $fullIdentifier = $identifier;
                if(gettype($identifier) === 'array'){
                    $fullIdentifier = implode($keyDelimiter,$fullIdentifier);
                    //Optionally fix the identifier
                    if($groupByFirstNKeys !== 0){
                        $identifierCount = count($identifier);
                        for($i = 0; $i < $identifierCount - $groupByFirstNKeys; $i++)
                            array_pop($identifier);
                    }
                    $identifier = implode($keyDelimiter,$identifier);
                }
                if($useCache)
                    $cacheTargets[] = $cacheName . $identifier;
                $indexMap[$index] = $fullIdentifier;
                $identifierMap[$fullIdentifier] = $index;
                $temp[$index] = false;
            }
            //If we are using cache, try to get the objects from cache
            if( $useCache && $getFromCache && ($cacheTargets!==[]) ){
                if($verbose){
                    echo 'Querying cache for '.$type.' targets '.json_encode($cacheTargets).
                        ($columnConditions?' with conditions '.json_encode($columnConditions):'').EOL;
                }

                //TODO Check if any of the targets are currently being updated  in cache - if yes, wait for $lockCacheDuringUpdate ms
                if($lockCacheDuringUpdate){
                    $cacheLocks = array_map(function($target)use($UpdateLockPrefix){ return $UpdateLockPrefix.$target;},$cacheTargets);
                }

                $cachedTempResults = $this->RedisManager->call('mGet', [$cacheTargets]);

                if($verbose){
                    echo 'Got '.(array_filter($cachedTempResults)? implode(' | ',$cachedTempResults) : 'nothing').' from cache! '.EOL;
                }

                if(!$cachedTempResults)
                    $cachedTempResults = $temp;

                //If we were grouping by keys, "expand" the cached results
                if($groupByFirstNKeys){
                    foreach ($cachedTempResults as $tempCachedResultsIndex => $cachedResult){
                        if (!$cachedResult || !\IOFrame\Util\PureUtilFunctions::is_json($cachedResult))
                            continue;
                        $inputIndex = $indexMap[$tempCachedResultsIndex];
                        $cachedTempResults[$inputIndex] = [];
                        $cachedResult = json_decode($cachedResult, true);
                        foreach ($cachedResult as $secondIndex => $result)
                            $cachedTempResults[$inputIndex][$secondIndex] = json_encode($result);
                        unset($cachedTempResults[$tempCachedResultsIndex]);
                    }
                }

                foreach($cachedTempResults as $index=>$cachedResult){
                    //Only if the cache result is valid
                    if (!$cachedResult || !\IOFrame\Util\PureUtilFunctions::is_json($cachedResult))
                        continue;
                    $cachedResult = json_decode($cachedResult, true);
                    //The cache result can either be a DB Object, or an array of DB Objects - but column names can't be "0"
                    $cachedResultIsDBObject = !isset($cachedResult[0]);

                    if($cachedResultIsDBObject)
                        $cachedResultArray = [$cachedResult];
                    else
                        $cachedResultArray = $cachedResult;

                    //Signifies the cache result array had an error
                    $cacheResultArrayHadError = false;
                    //Do the following for each result in the array
                    foreach($cachedResultArray as $index2 => $cachedResult2){
                        //Check that all required columns exist in the cached object
                        if ($columns != [] && $compareCol) {
                            $colCompare = [];
                            foreach ($columns as $colName) {
                                $colCompare[$colName] = 1;
                            }
                            //If columns do not match, this item is invalid
                            $missingColumns = array_diff_key($colCompare, $cachedResult2);
                            if (count($missingColumns) != 0){
                                if($verbose)
                                    echo 'Item '.$indexMap[$index].' failed to pass column checks, '.json_encode($missingColumns).' missing, removing from results'.EOL;
                                $cacheResultArrayHadError = true;
                                continue;
                            }
                            //Else cut all extra columns
                            else
                                foreach($cachedResult2 as $colName=>$value){
                                    if(!in_array($colName,$columns))
                                        unset($cachedResultArray[$index2][$colName]);
                                }
                        }

                        //Check for the column conditions in the object
                        if($columnConditions != []){
                            $numberOfConditions = count($columnConditions);
                            $hasMode = false;
                            //array_splice($columnConditions,0,0);

                            //Mode of operation
                            $mode = 'AND';
                            if($columnConditions[$numberOfConditions-1] == 'OR')
                                $mode = 'OR';

                            //Unset mode of operation indicator
                            if($columnConditions[$numberOfConditions-1] == 'OR' ||
                                $columnConditions[$numberOfConditions-1] == 'AND'){
                                $hasMode = true;
                                $numberOfConditions--;
                            }

                            $resultPasses = 0;

                            foreach ($columnConditions as $index => $condition) {
                                if($hasMode && ($index === $numberOfConditions-1))
                                    continue;
                                if(isset($cachedResult2[$condition[0]])){
                                    switch($condition[2]){
                                        case '>=':
                                            if($cachedResult2[$condition[0]]>=$condition[1])
                                                $resultPasses++;
                                            elseif($verbose)
                                                echo 'Item '.$index2.' failed to pass >= column check'.EOL;
                                            break;
                                        case '<=':
                                            if($cachedResult2[$condition[0]]<=$condition[1])
                                                $resultPasses++;
                                            elseif($verbose)
                                                echo 'Item '.$index2.' failed to pass <= column check'.EOL;
                                            break;
                                        case '>':
                                            if($cachedResult2[$condition[0]]>$condition[1])
                                                $resultPasses++;
                                            elseif($verbose)
                                                echo 'Item '.$index2.' failed to pass > column check'.EOL;
                                            break;
                                        case '<':
                                            if($cachedResult2[$condition[0]]<$condition[1])
                                                $resultPasses++;
                                            elseif($verbose)
                                                echo 'Item '.$index2.' failed to pass < column check'.EOL;
                                            break;
                                        case '=':
                                            if($cachedResult2[$condition[0]]==$condition[1])
                                                $resultPasses++;
                                            elseif($verbose)
                                                echo 'Item '.$index2.' failed to pass = column check'.EOL;
                                            break;
                                        case '!=':
                                            if($cachedResult2[$condition[0]]!=$condition[1])
                                                $resultPasses++;
                                            elseif($verbose)
                                                echo 'Item '.$index2.' failed to pass != column check'.EOL;
                                            break;
                                        case 'INREV':
                                        case 'IN':
                                            $inArray = false;

                                            $colIndex = ($condition[2] === 'IN')? 0 : 1;
                                            $stringIndex = ($condition[2] === 'IN')? 1 : 0;

                                            if(!is_array($condition[$colIndex]))
                                                $arr1 = [$condition[$colIndex]];
                                            else
                                                $arr1 = $condition[$colIndex];

                                            if(!is_array($condition[$stringIndex]))
                                                $arr2 = [$condition[$stringIndex]];
                                            else
                                                $arr2 = $condition[$stringIndex];

                                            foreach($arr1 as $colName)
                                                if(in_array($cachedResult2[$colName],$arr2))
                                                    $inArray = true;
                                            if($inArray)
                                                $resultPasses++;
                                            elseif($verbose)
                                                echo 'Item '.$index2.' failed to pass '.$condition[2].' column check'.EOL;
                                            break;
                                        case 'RLIKE':
                                            if(preg_match('/'.$condition[1].'/',$cachedResult2[$condition[0]]))
                                                $resultPasses++;
                                            elseif($verbose)
                                                echo 'Item '.$index2.' failed to pass RLIKE column check'.EOL;
                                            break;
                                        case 'NOT RLIKE':
                                            if(!preg_match('/'.$condition[1].'/',$cachedResult2[$condition[0]]))
                                                $resultPasses++;
                                            elseif($verbose)
                                                echo 'Item '.$index2.' failed to pass NOT RLIKE column check'.EOL;
                                            break;
                                    }
                                }
                                elseif($condition[1]==='ISNULL'){
                                    if($cachedResult2[$condition[0]]===null)
                                        $resultPasses++;
                                    elseif($verbose)
                                        echo 'Item '.$index2.' failed to pass ISNULL column check'.EOL;
                                }
                            }

                            //If conditions are not met, this item exists but does not meet the conditions, and as such
                            //should not be returned but also not fetched from the DB
                            if(
                                ($mode === 'AND' && $resultPasses<$numberOfConditions) ||
                                ($mode === 'OR' && $resultPasses=0)
                            ){
                                if($verbose)
                                    echo 'Item '.$index2.' failed to pass '.($numberOfConditions-$resultPasses).
                                        ' column checks, removing from results array'.EOL;
                                $cacheResultArrayHadError = true;
                            }
                        }
                    }

                    if($cacheResultArrayHadError){
                        if($verbose)
                            echo 'Item '.($indexMap[$index]??'').' failed to pass column checks, removing from results'.EOL;
                        continue;
                    }

                    //Either way we are removing this
                    unset($targets[$index]);

                    $cacheResults[$indexMap[$index]] = $cachedResultIsDBObject? $cachedResultArray[0] : $cachedResultArray;

                    //Add TTL to the object that was requested
                    if($extendTTL){
                        if(!$test)
                            $this->RedisManager->call('expire',[$indexMap[$index],$cacheTTL]);
                        if($verbose)
                            echo 'Refreshing '.$type.' '.$indexMap[$index].' TTL to '.$cacheTTL.EOL;
                    }

                }

                //Push all cached results into final result array
                if($cacheResults != [])
                    foreach($cacheResults as $identifier=>$cachedResult){
                        $results[$identifier] = $cachedResult;
                    }
            }

            if($columnConditions){
                $dbConditions = $columnConditions;
                if($fixSpecialConditions)
                    foreach ($dbConditions as $index => $condition) {
                        if($condition[2] === 'INREV')
                            //Set this to IN, for the DB query
                            $dbConditions[$index][2] = 'IN';
                    }
            }
            else
                $dbConditions = $params['extraConditions'] ?? [];

            if($targets != [] || $cacheTargets === [])
                $dbResults = $this->getFromTableByKey($targets,$keyCol,$tableName,$columns,array_merge($params,['extraConditions'=>$dbConditions,'ignoreMaxLimit'=>true]));

            if($dbResults !== false){
                //TODO Lock all valid items which we will be updating - skip items that are already being updated
                if($lockCacheDuringUpdate){

                }
                foreach($dbResults as $identifier=>$dbResult){
                    $results[$identifier] = is_array($dbResult)? $dbResult : false;
                    //Unset targets to get
                    if($targets != [] && count($extraKeyColumns)===0 && is_array($dbResult))
                        if(!$groupByFirstNKeys)
                            unset($targets[$identifierMap[$identifier]]);
                        else{
                            foreach ($dbResult as $secondaryKey=>$res)
                                if(!empty($identifierMap[$identifier.$keyDelimiter.$secondaryKey]))
                                    unset($targets[$identifierMap[$identifier.$keyDelimiter.$secondaryKey]]);
                        }
                    //Dont forget to update the cache with the DB objects, if we're using cache
                    if(
                        $updateCache &&
                        $useCache &&
                        is_array($dbResult)
                    ){
                        $actualCacheSizeLimit = $groupByFirstNKeys? count($dbResult)*$this->maxCacheSize : $this->maxCacheSize;
                        $cacheItem = json_encode($dbResult);
                        $cacheItemSize = strlen($cacheItem);
                        if($cacheItemSize < $actualCacheSizeLimit){
                            //TODO Check if we need to update this specific item
                            if($lockCacheDuringUpdate){

                            }
                            if(!$test)
                                $this->RedisManager->call('set',[$cacheName . $identifier,$cacheItem,$cacheTTL]);
                            if($verbose)
                                echo 'Adding '.$type.' '.$cacheName . $identifier.' to cache for '.
                                    $cacheTTL.' seconds as '.$cacheItem.EOL;
                            //TODO If we updated this specific item, unlock its cache
                            if($lockCacheDuringUpdate){

                            }
                        }
                        elseif($verbose){
                            echo $type.' '.$cacheName . $identifier.' not added to cache due to being of size  '.$cacheItemSize.
                                ', when max size is '.$actualCacheSizeLimit.EOL;
                        }
                    }
                }
            }
            else{
                $missingErrorCode = -1;
            }

            //Add missing error codes - if we aren't using extra key columns.
            if(count($extraKeyColumns)===0 && $missingErrorCode !== -1)
                foreach($targets as $target){
                    if(gettype($target) === 'array'){
                        $targetLength = count($target) - $groupByFirstNKeys;
                        if($groupByFirstNKeys)
                            for($i = 0; $i < $targetLength; $i++)
                                array_pop($target);
                        $target = implode($keyDelimiter,$target);
                    }
                    if(empty($results[$target]))
                        $results[$target] = $missingErrorCode;
                }
            return $results;
        }

        /** Deletes all specified redis keys.
         * @param string[] $keys - redis keys
         * @param array $params
         * @return bool true - success, false - failure
         *
         */
        function deleteCacheKeys(array $keys, array $params = []): bool {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            if(!$useCache || (count($keys) === 0))
                return false;

            if($verbose)
                echo 'Deleting cache of '.json_encode($keys).EOL;
            if(!$test)
                $res = $this->RedisManager->call( 'del', [$keys] );
            else
                $res = true;
            return $res;
        }

    }

}