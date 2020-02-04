<?php
namespace IOFrame{
    define('abstractDBWithCache',true);

    if(!defined('abstractDB'))
        require 'abstractDB.php';
    if(!defined('RedisHandler'))
        require 'RedisHandler.php';
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;

    /**
     * To be extended by modules operate user info (login, register, etc)
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    abstract class abstractDBWithCache extends abstractDB
    {
        /** @var Handlers\RedisHandler $RedisHandler a redis-PHP handler
        */
        protected $RedisHandler;

        /**
         * @var Int Tells us for how long to cache stuff by default.
         * 0 means indefinitely.
         */
        protected $cacheTTL = 3600;

        /**
         * Basic construction function
         * @param Handlers\SettingsHandler $localSettings Settings handler containing LOCAL settings.
         * @param array $params An potentially containing an SQLHandler and/or a logger and/or a RedisHandler.
         */
        public function __construct(Handlers\SettingsHandler $localSettings,  $params = []){

            parent::__construct($localSettings,$params);

            //Set defaults
            if(!isset($params['RedisHandler']))
                $this->RedisHandler = null;
            else
                $this->RedisHandler = $params['RedisHandler'];

            if($this->RedisHandler != null){
                $this->defaultSettingsParams['RedisHandler'] = $this->RedisHandler;
                $this->defaultSettingsParams['useCache'] = true;
            }

            //If we are caching for a custom duration, this should stated here
            if(isset($params['cacheTTL']))
                $this->cacheTTL = $params['cacheTTL'];

        }

        function getCacheTTL(){
            return $this->cacheTTL;
        }

        function setCacheTTL(int $cacheTTL){
            $this->cacheTTL =$cacheTTL;
        }



        /** Gets the requested objects/maps/groups from the db/cache.
         *  @param array params ['type'] is the type of targets.
         * @param array $targets Array of keys. If empty, will ignore the cache and get everything from the DB.
         * @param string|array $keyCol Key column name
         * @param string $tableName Name of the table WITHOUT THE PREFIX
         * @param string $cacheName Name of cache prefix
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
         *                                      where possible conditions are '>','<','=', '!=', 'IN', 'INREV', 'RLIKE' and 'NOT RLIKE'.
         *                                      The difference between IN and INREV is that in the first, the 1st parameter is
         *                                      the name of the column that matches one of the strings in the 2nd parameter, while
         *                                      with INREV (REV is reverse) the 1st parameter is a string that matches one of the
         *                                      values of the column names in the 2nd parameter. In both cases, the both parameters
         *                                      can be strings - only the order matters.
         *                                      The conditions work the same as their MySQL counterparts.
         *                                      More complex conditions are not supported as of now
         *                  'fixSpecialConditions' - bool, default false. If true, will check and change specific conditions
         *                                           that are invalid in SQL. Current list is:
         *                                          'INREV'
         *                  'useCache'  - Whether to use cache at all
         *                  'getFromCache' - Whether to try to get items from cache
         *                  'updateCache' - Whether to try to update cache with DB results
         *                  'extraKeyColumns' - a getFromTableByKey parameter, but if present, will discard normal identifier results.
         *                  'groupByFirstNKeys' => getFromTableByKey() parameter - if present, will only use up to the
         *                                         N first keys to identify the item. Read the getFromTableByKey() docs to
         *                                         understand this.
         * @return array Results of the form [$identifier => <result array>],
         *              where the result array is of the form [<Col name> => <Value>]
         *               If the item was not in the DB, will return the following codes instead of <result array>:
         *                  1 - The item was not found in the DB or cache
         *                 -1 - The item was not found in the cache, and failed to connect to DB.
         * */
        protected function getFromCacheOrDB(
            array $targets,
            $keyCol,
            string $tableName,
            string $cacheName,
            array $columns = [],
            array $params = []
        ){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            $type = isset($params['type'])? $params['type'] : '';

            $compareCol = isset($params['compareCol'])? $params['compareCol'] : true;

            $fixSpecialConditions = isset($params['fixSpecialConditions'])? $params['fixSpecialConditions'] : false;

            $columnConditions = isset($params['columnConditions'])? $params['columnConditions'] : [];

            $missingErrorCode = 1;

            if(isset($params['useCache']))
                $useCache = $params['useCache'];
            else
                $useCache = (isset($this->defaultSettingsParams['useCache']) &&  $this->defaultSettingsParams['useCache'])?
                    true : false;

            if(isset($params['getFromCache']))
                $getFromCache = $params['getFromCache'];
            else
                $getFromCache = $useCache;

            if(isset($params['updateCache']))
                $updateCache = $params['updateCache'];
            else
                $updateCache = $useCache;

            if(isset($params['cacheTTL']))
                $cacheTTL = $params['cacheTTL'];
            else
                $cacheTTL = $this->cacheTTL;

            if(isset($params['keyDelimiter'])){
                $keyDelimiter = $params['keyDelimiter'];
            }
            else{
                if(gettype($keyCol) === 'array' && count($keyCol) > 1)
                    $keyDelimiter = '/';
                else
                    $keyDelimiter = '';
            }

            if(isset($params['extraKeyColumns']))
                $extraKeyColumns = $params['extraKeyColumns'];
            else
                $extraKeyColumns = [];

            if(isset($params['groupByFirstNKeys']) && (is_array($keyCol) || count($extraKeyColumns) > 0)){
                $totalCount = count($extraKeyColumns) + (is_array($keyCol) ? count($keyCol) : 1);
                $groupByFirstNKeys = max(0,min($params['groupByFirstNKeys'],$totalCount-1));
            }
            else
                $groupByFirstNKeys = 0;

            $cacheResults = [];
            $results = [];
            $dbResults = [];
            $temp = [];

            $indexMap = [];
            $identifierMap = [];
            $cacheTargets = [];

            foreach($targets as $index=>$identifier) {
                if(gettype($identifier) === 'array'){
                    //Optionally fix the identifier
                    if($groupByFirstNKeys !== 0){
                        for($i = 0; $i < $groupByFirstNKeys; $i++)
                            array_pop($identifier);
                    }
                    $identifier = implode($keyDelimiter,$identifier);
                }
                array_push($cacheTargets, $cacheName . $identifier);
                $indexMap[$index] = $identifier;
                $identifierMap[$identifier] = $index;
                $temp[$index] = false;
            }

            //If we are using cache, try to get the objects from cache
            if( $useCache && $getFromCache && $cacheTargets!==[] ){
                if($verbose){
                    echo 'Querying cache for '.$type.' targets '.json_encode($cacheTargets).
                        ($columnConditions?' with conditions '.json_encode($columnConditions):'').EOL;
                }

                $cachedTempResults = $this->RedisHandler->call('mGet', [$cacheTargets]);

                if($verbose)
                    echo 'Got '.($cachedTempResults? implode(' | ',$cachedTempResults) : 'nothing').' from cache! '.EOL;

                if(!$cachedTempResults)
                    $cachedTempResults = $temp;

                foreach($cachedTempResults as $index=>$cachedResult){
                    //Only if the cache result is valid
                    if ($cachedResult && Util\is_json($cachedResult)) {
                        $cachedResult = json_decode($cachedResult, true);
                        //The cache result can either be a DB Object, or an array of DB Objects - but column names can't be "0"
                        $cachedResultIsDBObject = isset($cachedResult[0])? false : true;

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
                                if (count(array_diff_key($colCompare, $cachedResult2)) != 0)
                                    continue;
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

                                //Mode of operation
                                $mode = 'AND';
                                if($columnConditions[$numberOfConditions-1] == 'OR')
                                    $mode = 'OR';

                                //Unset mode of operation indicator
                                if($columnConditions[$numberOfConditions-1] == 'OR' ||
                                    $columnConditions[$numberOfConditions-1] == 'AND'){
                                    unset($columnConditions[$numberOfConditions-1]);
                                    $numberOfConditions--;
                                }

                                $resultPasses = 0;

                                foreach ($columnConditions as $index => $condition) {
                                    if(isset($cachedResult2[$condition[0]]))
                                        switch($condition[2]){
                                            case '>':
                                                if($cachedResult2[$condition[0]]>$condition[1])
                                                    $resultPasses++;
                                                break;
                                            case '<':
                                                if($cachedResult2[$condition[0]]<$condition[1])
                                                    $resultPasses++;
                                                break;
                                            case '=':
                                                if($cachedResult2[$condition[0]]==$condition[1])
                                                    $resultPasses++;
                                                break;
                                            case '!=':
                                                if($cachedResult2[$condition[0]]!=$condition[1])
                                                    $resultPasses++;
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
                                                break;
                                            case 'RLIKE':
                                                if(preg_match('/'.$condition[1].'/',$cachedResult2[$condition[0]]))
                                                    $resultPasses++;
                                                break;
                                            case 'NOT RLIKE':
                                                if(!preg_match('/'.$condition[1].'/',$cachedResult2[$condition[0]]))
                                                    $resultPasses++;
                                                break;
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
                                    continue;
                                }
                            }
                        }

                        //Either way we are removing this
                        unset($targets[$index]);

                        if($cacheResultArrayHadError){
                            if($verbose)
                                echo 'Item '.$indexMap[$index].' failed to pass column checks, removing from results'.EOL;
                            continue;
                        }

                        $cacheResults[$indexMap[$index]] = $cachedResultIsDBObject? $cachedResultArray[0] : $cachedResultArray;
                    }

                }

                //Push all cached results into final result array
                if($cacheResults != [])
                    foreach($cacheResults as $identifier=>$cachedResult){
                        $results[$identifier] = $cachedResult;
                    }
            }

            $dbConditions = $columnConditions;
            if($fixSpecialConditions)
                foreach ($dbConditions as $index => $condition) {
                    if($condition[2] === 'INREV')
                        //Set this to IN, for the DB query
                        $dbConditions[$index][2] = 'IN';
                }

            if($targets != [] || $cacheTargets === [])
                $dbResults = $this->getFromTableByKey($targets,$keyCol,$tableName,$columns,array_merge($params,['extraConditions'=>$dbConditions]));
            if($dbResults !== false)
                foreach($dbResults as $identifier=>$dbResult){
                    $results[$identifier] = $dbResult;
                    //Unset targets to get - that is, if not using extra columns as keys
                    if($targets != [] && count($extraKeyColumns)===0)
                        unset($targets[$identifierMap[$identifier]]);
                    //Dont forget to update the cache with the DB objects, if we're using cache
                    if(
                        $updateCache &&
                        $useCache &&
                        is_array($dbResult)
                    ){
                        if(!$test)
                            $this->RedisHandler->call('set',[$cacheName . $identifier,json_encode($dbResult),$cacheTTL]);
                        if($verbose)
                            echo 'Adding '.$type.' '.$cacheName . $identifier.' to cache for '.
                                $this->cacheTTL.' seconds as '.json_encode($dbResult).EOL;
                    }
                }
            else{
                $missingErrorCode = -1;
            }

            //Add missing error codes - if we aren't using extra key columns.
            if(count($extraKeyColumns)===0 && $missingErrorCode !== -1)
                foreach($targets as $target){
                    if(gettype($target) === 'array'){
                        if($groupByFirstNKeys)
                            for($i = 0; $i < $groupByFirstNKeys; $i++)
                                array_pop($target);
                        $target = implode($keyDelimiter,$target);
                    }
                    if(!is_array($results[$target]))
                        $results[$target] = $missingErrorCode;
                }

            return $results;
        }

    }

}