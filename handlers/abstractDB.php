<?php
namespace IOFrame{
    define('abstractDB',true);

    if(!defined('abstractLogger'))
        require 'abstractLogger.php';
    if(!defined('sqlHandler'))
        require 'sqlHandler.php';
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;

    /**
     * To be extended by modules operate user info (login, register, etc)
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    abstract class abstractDB extends abstractLogger
    {



        /** Default limit to getting stuff from the DB.
         * */
        protected $defaultQueryLimit = 10000;

        /** Starting from this abstract class and up, everything needs to decide it's default settings mode.
         *  This dictates how handlers extending this class will create settings, and should be set in the main function.
         * */
        protected $defaultSettingsParams;

        /**
         * Basic construction function
         * @param settingsHandler $localSettings Settings handler containing LOCAL settings.
         * @param array $params An potentially containing an sqlHandler and/or a logger.
         */
        public function __construct(settingsHandler $localSettings,  $params = []){

            //Set defaults
            if(!isset($params['sqlHandler']))
                $sqlHandler = null;
            else
                $sqlHandler = $params['sqlHandler'];

            if(!isset($params['opMode']))
                $opMode = SETTINGS_OP_MODE_LOCAL;
            else
                $opMode = $params['opMode'];

            if(isset($params['limit']))
                $this->defaultQueryLimit = $params['limit'];

            //Has to be set before parent construct due to sqlHandler depending on it, and Logger depending on the outcome
            $this->settings=$localSettings;
            $this->sqlHandler = ($sqlHandler == null)? new sqlHandler($this->settings) : $sqlHandler;

            //In case it was missing earlier, it isn't anymore. Make sure to pass it to the Logger
            $params['sqlHandler'] = $this->sqlHandler;

            //Starting from this class, extending classes have to decide their default setting mode.
            $this->defaultSettingsParams['opMode'] = $opMode;
            if($opMode != SETTINGS_OP_MODE_LOCAL)
                $this->defaultSettingsParams['sqlHandler'] = $this->sqlHandler;


            parent::__construct($localSettings,$params);
        }


        /** Retrieves from a table, by key names.
         * @param array $keys Array of keys. If empty, will return the whole table.
         * @param string $keyCol Key column name
         * @param string $tableName Name of the table
         * @param string[] $columns Array of column names.
         * @param array $params
         *              'limit' => SQL parameter LIMIT
         *              'offset'=> SQL parameter OFFSET. Only changes anything if limit is set.
         * @returns mixed
         * a result in the form [<keyName> => <Associated array for row>]
         *  or
         * false if nothing exists, or on different error
         * */
        protected function getFromTableByKey(array $keys, string $keyCol, string $tableName, array $columns = [], array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            if(isset($params['limit']))
                $limit = min((int)$params['limit'],$this->defaultQueryLimit);
            else
                $limit = $this->defaultQueryLimit;

            if(isset($params['offset']))
                $offset = $params['offset'];
            else
                $offset = null;

            if(!in_array($keyCol,$columns) && $columns!=[])
                array_push($columns,$keyCol);
            $conds = [];
            $tableName = $this->sqlHandler->getSQLPrefix().$tableName;
            $tempRes = [];

            //If keys are not empty, we need to get specific rows
            if($keys != []){
                foreach($keys as $i=>$key){
                    if(gettype($key) == 'string')
                        $key = [$key,'STRING'];
                    array_push($conds,$key);
                    $tempRes[$keys[$i]] = 1;
                }
            }

            //If keys were not empty, conditions need to be specific
            if($conds != []){
                array_push($conds,'CSV');
                $conds = [
                    $keyCol,
                    $conds,
                    'IN'
                ];
            }

            $res = $this->sqlHandler->selectFromTable(
                $tableName,
                $conds,
                $columns,
                ['test'=>$test,'verbose'=>$verbose,'limit'=>$limit,'offset'=>$offset]
            );

            if(is_array($res)){
                $resLength = count($res);
                for($i = 0; $i<$resLength; $i+=1){
                    $resLength2 = count($res[$i]);
                    for($j = 0; $j<$resLength2; $j++){
                        unset($res[$i][$j]);//This is ok because no valid column name will consist solely of digits
                    }
                    $tempRes[$res[$i][$keyCol]] = $res[$i];
                }
                return $tempRes;
            }
            else{
                return false;
            }
        }


    }

}