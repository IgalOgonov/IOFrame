<?php
namespace IOFrame{
    define('abstractDBWithCache',true);

    if(!defined('abstractDB'))
        require 'abstractDB.php';
    if(!defined('redisHandler'))
        require 'redisHandler.php';
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
        /** @var redisHandler $redisHandler a redis-PHP handler
        */
        protected $redisHandler;

        /**
         * @var Int Tells us for how long to cache stuff by default.
         * 0 means indefinitely.
         */
        protected $cacheTTL = 3600;

        /**
         * Basic construction function
         * @param settingsHandler $localSettings Settings handler containing LOCAL settings.
         * @param array $params An potentially containing an sqlHandler and/or a logger and/or a redisHandler.
         */
        public function __construct(settingsHandler $localSettings,  $params = []){

            parent::__construct($localSettings,$params);

            //Set defaults
            if(!isset($params['redisHandler']))
                $this->redisHandler = null;
            else
                $this->redisHandler = $params['redisHandler'];

            if($this->redisHandler != null){
                $this->defaultSettingsParams['redisHandler'] = $this->redisHandler;
                $this->defaultSettingsParams['useCache'] = true;
            }


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
         * @param string $tableName Name of the table
         * @param string $cacheName Name of cache prefix
         * @param string[] $columns Array of column names.
         * @param array $params
         *                  'type' - string ,default '' - Extra information about object type - used for verbose output.
         *                  'compareCol' - bool, default true - If true, will compare cache columns to requested columns,
         *                                 and only use the cached result of they match. Is ignored if columns are []
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
                    echo 'Querying cache for '.$type.' targets '.implode(',',$targets).EOL;

                $cachedTempResults = $this->redisHandler->call('mGet', [$cacheTargets]);


                if(!$cachedTempResults)
                    $cachedTempResults = $temp;

                if($verbose)
                    echo 'Got '.implode(' | ',$cachedTempResults).' from cache! '.EOL;

                foreach($cachedTempResults as $index=>$cachedResult){
                    if ($cachedResult && is_json($cachedResult)) {
                        $cachedResult = json_decode($cachedResult, true);
                        //Check that all required columns exist in the cached object
                        if ($columns != [] && $compareCol) {
                            $colCompare = [];
                            foreach ($columns as $colName) {
                                $colCompare[$colName] = 1;
                            }
                            if (count(array_diff_key($colCompare, $cachedResult)) != 0)
                                continue;
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
                $dbResults = $this->getFromTableByKey($targets,$keyCol,$tableName,$columns,['test'=>$test,'verbose'=>$verbose]);

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
                            $this->redisHandler->call('set',[$cacheName . $identifier,json_encode($dbResult),$cacheTTL]);
                        if($verbose)
                            echo 'Adding '.$type.' '.$identifier.' to cache for '.
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