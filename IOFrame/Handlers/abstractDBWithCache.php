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
         * @param string $keyCol Key column name
         * @param string $tableName Name of the table WITHOUT THE PREFIX
         * @param string $cacheName Name of cache prefix
         * @param string[] $columns Array of column names.
         * @param array $params
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
         *                                      where possible conditions are '>','<','=', '!=', 'RLIKE' and 'NOT RLIKE'.
         *                                      The conditions work the same as their MySQL counterparts.
         *                                      More complex conditions are not supported as of now
         * @return array Results of the form [$identifier => <result array>],
         *              where the result array is of the form [<Col name> => <Value>]
         *               If the item was not in the DB, will return the following codes instead of <result array>:
         *                  1 - The item was not found in the DB or cache
         *                 -1 - The item was not found in the cache, and failed to connect to DB.
         * */
        protected function getFromCacheOrDB(
            array $targets,
            string $keyCol,
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

            $columnConditions = isset($params['columnConditions'])? $params['columnConditions'] : [];

            $missingErrorCode = 1;

            if(isset($params['useCache']))
                $useCache = $params['useCache'];
            else
                $useCache = (isset($this->defaultSettingsParams['useCache']) &&  $this->defaultSettingsParams['useCache'])?
                    true : false;

            if(isset($params['updateCache']))
                $updateCache = $params['updateCache'];
            else
                $updateCache = $useCache;

            if(isset($params['cacheTTL']))
                $cacheTTL = $params['cacheTTL'];
            else
                $cacheTTL = $this->cacheTTL;

            $cacheResults = [];
            $results = [];
            $dbResults = [];
            $temp = [];

            $indexMap = [];
            $identifierMap = [];
            $cacheTargets = [];

            foreach($targets as $index=>$identifier) {
                array_push($cacheTargets, $cacheName . $identifier);
                $indexMap[$index] = $identifier;
                $identifierMap[$identifier] = $index;
                $temp[$index] = false;
            }

            //If we are using cache, try to get the objects from cache
            if( $useCache && $cacheTargets!==[] ){
                if($verbose)
                    echo 'Querying cache for '.$type.' targets '.implode(',',$targets).
                        ($columnConditions?' with conditions '.json_encode($columnConditions):'').EOL;

                $cachedTempResults = $this->RedisHandler->call('mGet', [$cacheTargets]);


                if(!$cachedTempResults)
                    $cachedTempResults = $temp;

                if($verbose)
                    echo 'Got '.implode(' | ',$cachedTempResults).' from cache! '.EOL;

                foreach($cachedTempResults as $index=>$cachedResult){
                    if ($cachedResult && Util\is_json($cachedResult)) {
                        $cachedResult = json_decode($cachedResult, true);
                        //Check that all required columns exist in the cached object
                        if ($columns != [] && $compareCol) {
                            $colCompare = [];
                            foreach ($columns as $colName) {
                                $colCompare[$colName] = 1;
                            }
                            //If columns do not match, this item is invalid
                            if (count(array_diff_key($colCompare, $cachedResult)) != 0)
                                continue;
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

                            foreach ($columnConditions as $condition) {
                                if(isset($cachedResult[$condition[0]]))
                                    switch($condition[2]){
                                        case '>':
                                            if($cachedResult[$condition[0]]>$condition[1])
                                                $resultPasses++;
                                            break;
                                        case '<':
                                            if($cachedResult[$condition[0]]<$condition[1])
                                                $resultPasses++;
                                            break;
                                        case '=':
                                            if($cachedResult[$condition[0]]==$condition[1])
                                                $resultPasses++;
                                            break;
                                        case '!=':
                                            if($cachedResult[$condition[0]]!=$condition[1])
                                                $resultPasses++;
                                            break;
                                        case 'RLIKE':
                                            if(preg_match('/'.$condition[1].'/',$cachedResult[$condition[0]]))
                                                $resultPasses++;
                                            break;
                                        case 'NOT RLIKE':
                                            if(!preg_match('/'.$condition[1].'/',$cachedResult[$condition[0]]))
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
                                    echo 'Item '.$indexMap[$index].' failed to pass '.($numberOfConditions-$resultPasses).
                                        ' column checks, removing from results'.EOL;
                                unset($targets[$index]);
                                continue;
                            }

                        }

                        unset($targets[$index]);
                        $cacheResults[$indexMap[$index]] = $cachedResult;
                    }
                }

                //Push all cached results into final result array
                if($cacheResults != [])
                    foreach($cacheResults as $identifier=>$cachedResult){
                        $results[$identifier] = $cachedResult;
                    }
            }

            if($targets != [] || $cacheTargets === [])
                $dbResults = $this->getFromTableByKey($targets,$keyCol,$tableName,$columns,array_merge($params,['extraConditions'=>$columnConditions]));

            if($dbResults !== false)
                foreach($dbResults as $identifier=>$dbResult){
                    $results[$identifier] = $dbResult;
                    if($targets != [])
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

            foreach($targets as $target){
                $results[$target] = $missingErrorCode;
            }

            return $results;
        }

    }

}