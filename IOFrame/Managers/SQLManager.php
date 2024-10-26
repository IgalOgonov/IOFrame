<?php /** @noinspection ALL */

namespace IOFrame\Managers{
    define('IOFrameManagersSQLManager',true);

    /**Handles basic sql (mySQL, MariaDB) actions in IOFrame
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class SQLManager extends \IOFrame\Abstract\Logger
    {

        /** @var \PDO $conn Typical PDO connection
         * */
        private $conn = null;

        /** @var string $prefixAlias Alias of the table prefix setting
         * */
        private $prefixAlias = null;

        /** @var \IOFrame\Util\PHPQueryBuilder we use this to build our queries dynamically
         */
        public \IOFrame\Util\PHPQueryBuilder $queryBuilder;
        public \IOFrame\Handlers\SettingsHandler $sqlSettings;
        public array $defaultSettingsParams = [];

        /**
         * Basic construction function.
         * @param \IOFrame\Handlers\SettingsHandler $localSettings  regular settings handler.
         * @param array $params
         *              'sqlSettings' => Alternative SQL settings to connect to.
         *              'settingAliases' => Allows passing different setting names to \IOFrame\Util\FrameworkUtilFunctions::prepareCon()
         *              'prefixAlias' => Allows passing a different table prefix SETTING NAME
         *
         * @throws \PDOException|\Exception If connection fails
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $localSettings, $params = [])
        {
            //Remember this only affects the settings - does not create anything else
            parent::__construct($localSettings);

            $sqlSettings = $params['sqlSettings'] ?? null;
            $settingAliases = $params['settingAliases'] ?? [];
            $this->prefixAlias = $params['prefixAlias'] ?? 'sql_table_prefix';

            //Separates fields on backup/restore
            if(!defined('DB_FIELD_SEPARATOR'))
                define('DB_FIELD_SEPARATOR', '#&%#');

            //This is the core of this handler
            $this->queryBuilder = new \IOFrame\Util\PHPQueryBuilder();

            //Set defaults
            if(!isset($params['RedisManager'])){
                $this->RedisManager = null;
                $this->defaultSettingsParams['useCache'] = false;
            }
            else{
                $this->RedisManager = $params['RedisManager'];
                $this->defaultSettingsParams['RedisManager'] = $this->RedisManager;
                $this->defaultSettingsParams['useCache'] = $this->defaultSettingsParams['useCache'] ?? !empty($this->RedisManager);
            }

            //Now we can initiate the sql settings
            if(!empty($sqlSettings) && (gettype($sqlSettings) === 'object') && (get_class($sqlSettings) === 'IOFrame\Handlers\SettingsHandler')){
                $this->sqlSettings = $sqlSettings;
            }
            else
                $this->sqlSettings = new \IOFrame\Handlers\SettingsHandler(
                    $localSettings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/sqlSettings/',
                    $this->defaultSettingsParams
                );

            //Standard constructor for classes that use settings and have a DB connection
            $this->conn = \IOFrame\Util\FrameworkUtilFunctions::prepareCon($this->sqlSettings, $settingAliases);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        }

        /**
         * @returns int last inserted ID
         */
        function lastInsertId(){
            return $this->conn->lastInsertId();
        }

        /** DOES NOT FILTER INPUT! I REPEAT, THIS FUNCTION DOES NOT FILTER INPUT!
         * Only use this function with pre-filtered, validated input.
         *
         * Example:
         *  exeQueryBindParam('SELECT * FROM USERS WHERE ID=:ID AND Name=:Name', [[':ID',5],[':Name','John']], ['fetchAll'=>true]) -
         *  This will fetch all people (in this case - one person) with the ID 5 named John.

         *
         * @param string $query Executes a query
         * @param array $paramsToBind binds all parameter values in $params[1] to their names inside query in $params[0]
         * @param array $params Includes parameters:
         *                      'fetchAll' bool, default false - If true, also fetches all the results of the query.
         *                      EXCLUDES 'test' and 'verbose'!
         *                      'returnError' => bool default, if set to true will return error on failure.
         *                                       error list can be found at https://docstore.mik.ua/orelly/java-ent/jenut/ch08_06.htm
         * @throws \Exception Throws back the exception after releasing the lock, if it was locked
         *
         * @returns mixed
         *      true - no errors executing statement
         *      false - statement not executed
         *      2D Array of results - if $fetchAll was true
         *      Throws SQL exceptions otherwise.
         * */
        function exeQueryBindParam(string $query, array $paramsToBind = [], array $params = []){

            $fetchAll = $params['fetchAll'] ?? false;

            $exe = $this->conn->prepare($query);
            //Bind params
            foreach($paramsToBind as $pair){
                $exe->bindValue($pair[0],$pair[1]);
            }
            //Execute query, and release the lock after it succeeds/fails
            try{
                $res = $exe->execute();
            }
            catch(\Exception $e){
                throw new \Exception($e);
            }

            if($res === false && isset($params['returnError']) && $params['returnError'])
                return $exe->errorCode();
            elseif($fetchAll && $res !== false)
                return $exe->fetchAll();
            else
                return $res;
        }

        /** Retrieves an object from the database table $tableName.
         * @param string $tableName Valid SQL table name.
         * @param array $cond 2D array of conditions, of the form [[<validColumnName>,<validVariable>,<comparisonOperator>]]
         *                   Example [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE']]
         *                   Valid comparison operators: '=', '<=>', '!=', '<=', '>=', '<', '>', 'LIKE', 'NOT LIKE'
         * @param array $columns The columns you want, in a 1D array of the form ['col1','col2',..].
         *                       Have to be actual columns in the DB table, though! Can be *, by setting [].
         * @param array $params Includes multiple possible parameters:
         *                      'escapeBackslashes' DEFAULT TRUE - will replace every backslash with '\\' in the query
         *                      'orderBy' An array of column names to order by
         *                      'groupBy' An array of column names to group by
         *                      'orderType' int|array, 1 descending, 0 ascending. Can be passed as an array to match orderBy columns
         *                      'useBrackets' will surround the query with brackets
         *                      'limit' If not empty, will limit number of selected rows. Can be explicitly 0 to get nothing (useful in niche cases)
         *                      'offset' SQL offset
         *                      'justTheQuery' will only return the query it would send instead of executing
         *                      'returnError' will return the SQL error instead of "-2" when possible. Notice that errors are not
         *                                    always properly provided.
         *                      'DISTINCT','DISTINCTROW','HIGH_PRIORITY',...other valid SELECT flags will add the after "SELECT"
         * @returns mixed
         *      -2 server error
         *      -1 on illegal input
         *      An associative array of the form $obj['ColName'] = <Col Value> otherwise.
         */

        function selectFromTable(string $tableName, array $cond = [], array $columns = [], array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            isset($params['escapeBackslashes'])?
                $escapeBackslashes = $params['escapeBackslashes'] : $escapeBackslashes = true;
            isset($params['useBrackets'])?
                $useBrackets = $params['useBrackets'] : $useBrackets = false;
            isset($params['orderBy'])?
                $orderBy = $params['orderBy'] : $orderBy = null;
            isset($params['groupBy'])?
                $groupBy = $params['groupBy'] : $groupBy = null;
            isset($params['orderType'])?
                $orderType = $params['orderType'] : $orderType = null;
            isset($params['limit'])?
                $limit = $params['limit'] : $limit = null;
            isset($params['offset'])?
                $offset = $params['offset'] : $offset = null;
            isset($params['justTheQuery'])?
                $justTheQuery = $params['justTheQuery'] : $justTheQuery = false;
            (isset($params['returnError']))?
                $returnError = $params['returnError'] : $returnError = false;

            $query = '';

            if($useBrackets)
                $query .= '(';

            $query .= 'SELECT ';

            if(isset($params['ALL']))
                $query .= ' ALL ';
            elseif(isset($params['DISTINCT']))
                $query .= ' DISTINCT ';
            elseif(isset($params['DISTINCTROW']))
                $query .= ' DISTINCTROW ';
            if (isset($params['HIGH_PRIORITY']))
                $query .= ' HIGH_PRIORITY ';
            if (isset($params['STRAIGHT_JOIN']))
                $query .= ' STRAIGHT_JOIN ';
            if (isset($params['SQL_SMALL_RESULT']))
                $query .= ' SQL_SMALL_RESULT ';
            if (isset($params['SQL_BIG_RESULT']))
                $query .= ' SQL_BIG_RESULT ';
            if (isset($params['SQL_BUFFER_RESULT']))
                $query .= ' SQL_BUFFER_RESULT ';
            if (isset($params['SQL_NO_CACHE']))
                $query .= ' SQL_NO_CACHE ';
            if (isset($params['SQL_CALC_FOUND_ROWS']))
                $query .= ' SQL_CALC_FOUND_ROWS ';

            //columns
            if($columns == [])
                $columnsToSelect = '*';
            else{
                $columnsToSelect = implode(',',$columns);
            }

            //orderType
            //Prepare the query to be executed
            $query .= $columnsToSelect.' FROM '.$tableName;

            //If we have conditions
            if($cond != []){
                $query .= ' WHERE ';
                try{
                    $query .=  $this->queryBuilder->expConstructor($cond);
                }catch (\Exception $e){
                    $this->logger->notice('Select query construction exception '.$e->getMessage(),['trace'=>$e->getTrace()]);
                    if($verbose){
                        echo $e->getMessage().EOL;
                    }
                    return -1;
                }
            }

            //If we have an order
            if(!empty($orderBy)){
                $query .= ' ORDER BY ';
                if(is_array($orderBy)){
                    $toAdd = [];
                    foreach ($orderBy as $i=>$col){
                        if(is_array($orderType))
                            array_push($toAdd,$col.' '.$orderType[$i%count($orderType)]?'DESC ':'ASC ');
                        elseif($orderType !== null)
                            array_push($toAdd, $col.' '.($orderType?'DESC ':'ASC '));
                    }
                    $query .= implode(',',$toAdd);
                }
                else{
                    $query .= $orderBy;
                    if($orderType !== null){
                        if(is_array($orderType))
                            $orderType = $orderType[0];
                        $query .= ' '.($orderType?'DESC ':'ASC ');
                    }
                }
            }

            //If we have an group
            if(!empty($groupBy)){
                $query .= ' GROUP BY ';
                if(is_array($groupBy)){
                    $query .= implode(',',$groupBy);
                }
                else
                    $query .= $groupBy;
            }

            //If we have a limit
            if(!empty($limit) || ($limit === 0)){
                $query .= ' LIMIT ';
                if($offset)
                    $query .=$offset.',';
                $query .= $limit;
            }

            if($useBrackets)
                $query .= ')';

            //Escape backslashes
            if($escapeBackslashes)
                $query = str_replace('\\','\\\\',$query);

            //If we are just returning the query, this is where we stop
            if($justTheQuery)
                return $query;

            $query .=';';
            //Execute the query
            if($verbose)
                echo 'Query to send: '.$query.EOL;
            try{
                $obj = $this->exeQueryBindParam($query,[],['returnError'=>$returnError,'fetchAll'=>true]);
            }
            catch(\Exception $e){
                $this->logger->notice('Select query exception '.$e->getMessage(),['trace'=>$e->getTrace()]);
                return -2;
            }
            //Check whether object exists
            if ($obj === false)
                return -2;
            else
                return $obj;
        }

        /** Deletes an object from the database table $tableName.
         * @param string $tableName Valid SQL table name.
         * @param array $cond Any conditional array that can be resolved by expConstructor
         *                   Example [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE'],'AND']
         * @param array $params Includes multiple possible parameters:
         *                      'escapeBackslashes' DEFAULT TRUE - will replace every backslash with '\\' in the query
         *                      'orderBy' An array of column names to order by
         *                      'orderType' 1 descending, 0 ascending
         *                      'limit' If not null/0, will limit number of selected rows.
         *                      'offset' SQL offset
         *                      'returnRows' If true, will return the number of affected rows on success.
         *                      'justTheQuery' If true, will only return the query and not execute it.
         *                      'returnError' will return the SQL error instead of "-2" when possible. Notice that errors are not
         *                                    always properly provided.
         *                      'tables' An array of table names one may use - generally useful if deleting from multiple tables at once
         * @param bool $test indicates test mode
         * @returns int
         *      -2 server error
         *      -1 on illegal input
         *      true success
         *      false on failure
         *      <Number of deleted rows> - on success, if $params['returnRows'] is set not to false.
         */
        function deleteFromTable(string $tableName, array $cond = [], array $params = []){

            //Read $params
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            isset($params['escapeBackslashes'])?
                $escapeBackslashes = $params['escapeBackslashes'] : $escapeBackslashes = true;
            isset($params['orderBy'])?
                $orderBy = $params['orderBy'] : $orderBy = null;
            isset($params['orderType'])?
                $orderType = $params['orderType'] : $orderType = 0;
            isset($params['limit'])?
                $limit = $params['limit'] : $limit = null;
            isset($params['offset'])?
                $offset = $params['offset'] : $offset = null;
            isset($params['returnRows'])?
                $returnRows = $params['returnRows'] : $returnRows = false;
            isset($params['justTheQuery'])?
                $justTheQuery = $params['justTheQuery'] : $justTheQuery = false;
            (isset($params['returnError']))?
                $returnError = $params['returnError'] : $returnError = false;
            isset($params['tables'])?
                $tables = $params['tables'] : $tables = [];

            //orderType
            if($orderType == 0)
                $orderType = 'ASC';
            else
                $orderType = 'DESC';

            //Prepare the query to be executed
            $query = 'DELETE'.(count($tables) === 0? ' ' : ' '.implode(',',$tables).' ').'FROM '.$tableName;

            //If we have conditions
            if($cond != []){
                $query .= ' WHERE ';
                try{
                    $query .=  $this->queryBuilder->expConstructor($cond);
                }catch (\Exception $e){
                    $this->logger->notice('Delete query construction exception '.$e->getMessage(),['trace'=>$e->getTrace()]);
                    if($verbose){
                        echo $e->getMessage().' || trace: '.EOL;
                        var_dump($e->getTrace());
                    }
                    return -1;
                }
            }

            //If we have an order
            if($orderBy != null){
                $query .= ' ORDER BY ';
                foreach($orderBy as $val){
                    $query .= $val.', ';
                }
                $query =  substr($query,0,-2);
                $query .=' '.$orderType;
            }

            //If we have a limit
            if($limit != null){
                $query .= ' LIMIT ';
                if($offset)
                    $query .=$offset.',';
                $query .= $limit;
            }

            //Escape backslashes
            if($escapeBackslashes)
                $query = str_replace('\\','\\\\',$query);

            //If we are just returning the query, this is where we stop
            if($justTheQuery)
                return $query;

            $query .=';';

            if($verbose)
                echo 'Query to send: '.$query.EOL;

            //Execute the query
            if($test)
                return true;
            else{
                try{
                    if(!$returnRows)
                        return $this->exeQueryBindParam($query,[],['returnError'=>$returnError]);
                    else{
                        $res = $this->exeQueryBindParam($query,[],['returnError'=>$returnError]);
                        if($res === true)
                            return $this->exeQueryBindParam('SELECT ROW_COUNT()',[],['fetchAll'=>true,'returnError'=>$returnError])[0][0];
                        else
                            return $returnError ? $res : -1;
                    }
                }
                catch(\Exception $e){
                    $this->logger->notice('Delete query exception '.$e->getMessage(),['trace'=>$e->getTrace()]);
                    return -2;
                }
            }
        }


        /** Inserts an object into the database table $tableName.
         * @param string $tableName Valid SQL table name.
         * @param array $columns The columns you want, in a 1D array of the form ['col1','col2',..].
         * @param array|string $values An array of arrays of the form
         *                      [[<col1Value>,<col2Value>,...],[<col1Value>,<col2Value>,...],...]
         *                      Values number MUST be the length of $columns array!
         *                      May also be a string - in which case it will be simply inserted.
         * @param array $params Includes multiple possible parameters:
         *                      'escapeBackslashes' DEFAULT TRUE - will replace every backslash with '\\' in the query
         *                      'onDuplicateKey' if true, will add ON DUPLICATE KEY UPDATE.
         *                      'onDuplicateKeyExp' if 'onDuplicateKey' is true, this can be set to be a custom expression for updating
         *                      'onDuplicateKeyColExp' allows custom expressions only for specific columns.
         *                      'returnRows' If true, will return the ID if the FIRST inserted row (their count depends on $values)
         *                      'justTheQuery' If true, will only return the query and not execute it.
         *                      'returnError' will return the SQL error instead of "-2" when possible. Notice that errors are not
         *                                    always properly provided.
         *                      'IGNORE','LOW_PRIORITY','HIGH_PRIORITY',...other valid INSERT flags will add the after "INSERT"
         *                      'REPLACE' will replace the INSERT with REPLACE, which behaves similarly but only updates existing rows.
         * @param bool $test indicates test mode
         * @returns int
         *      -2 server error
         *      -1 on illegal input
         *      true success
         *      false on failure
         *      <LAST_INSERT_ID()> - on success, if $params['returnRows'] is set not to false.
         */
        function insertIntoTable(string $tableName, array $columns, $values, array $params = []){

            //Read $params
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            isset($params['escapeBackslashes'])?
                $escapeBackslashes = $params['escapeBackslashes'] : $escapeBackslashes = true;
            isset($params['onDuplicateKey'])?
                $onDuplicateKey = $params['onDuplicateKey'] : $onDuplicateKey = false;
            isset($params['onDuplicateKeyExp'])?
                $onDuplicateKeyExp = $params['onDuplicateKeyExp'] : $onDuplicateKeyExp = [];
            isset($params['onDuplicateKeyColExp'])?
                $onDuplicateKeyColExp = $params['onDuplicateKeyColExp'] : $onDuplicateKeyColExp = [];
            isset($params['returnRows'])?
                $returnRows = $params['returnRows'] : $returnRows = false;
            isset($params['justTheQuery'])?
                $justTheQuery = $params['justTheQuery'] : $justTheQuery = false;
            (isset($params['returnError']))?
                $returnError = $params['returnError'] : $returnError = false;

            //columns
            $columnNames = ' ('.implode(',',$columns).')';

            //Prepare the query to be executed
            $query = 'INSERT';
            if (isset($params['IGNORE']))
                $query .= ' IGNORE ';

            //Unlike the others, replace replaces INSERT and ignores IGNORE
            if (isset($params['REPLACE'])){
                $query = 'REPLACE';
                $onDuplicateKey = false;
            }

            if (isset($params['HIGH_PRIORITY']))
                $query .= ' HIGH_PRIORITY ';
            elseif(isset($params['LOW_PRIORITY']))
                $query .= ' LOW_PRIORITY';
            elseif(isset($params['DELAYED']))
                $query .= ' DELAYED ';

            $query .=' INTO '.$tableName.$columnNames.' ';

            //Values
            try {
                if(is_array($values)){
                    $query .='VALUES ';
                    array_push($values,',');
                    $query .=  $this->queryBuilder->expConstructor($values);
                }
                else
                    $query .=  $values;

                //If we have on duplicate key..
                if($onDuplicateKey){
                    $query .= ' ON DUPLICATE KEY UPDATE ';
                    //By default, updates each column with relevant values
                    if($onDuplicateKeyExp == []){
                        foreach($columns as $colName){
                            if(!isset($onDuplicateKeyColExp[$colName]))
                                $query .= $colName.'=VALUES('.$colName.'), ';
                            else
                                $query .= $colName.'='.$this->queryBuilder->expConstructor($onDuplicateKeyColExp[$colName]).', ';
                        }
                        $query =  substr($query,0,-2).' ';
                    }
                    //A custom expression is also possible
                    else{
                        $query .=  $this->queryBuilder->expConstructor($onDuplicateKeyExp);
                    }
                }
            }
            catch (\Exception $e){
                $this->logger->notice('Insert query construction exception '.$e->getMessage(),['trace'=>$e->getTrace()]);
                return -1;
            }

            //Escape backslashes
            if($escapeBackslashes)
                $query = str_replace('\\','\\\\',$query);

            //If we are just returning the query, this is where we stop
            if($justTheQuery)
                return $query;

            $query .=';';
            if($verbose)
                echo 'Query to send: '.$query.EOL;
            //Execute the query
            if($test){
                return !$returnRows? true : 1;
            }
            else{
                try{
                    if(!$returnRows)
                        return $this->exeQueryBindParam($query,[],['returnError'=>$returnError]);
                    else{
                        $res = $this->exeQueryBindParam($query,[],['returnError'=>$returnError]);

                        if($res === true)
                            return (int)$this->exeQueryBindParam('SELECT LAST_INSERT_ID()',[],['fetchAll'=>true,'returnError'=>$returnError])[0][0];
                        else
                            return ($returnError ? $res : -1);
                    }
                }
                catch(\Exception $e){
                    $this->logger->notice('Insert query exception '.$e->getMessage(),['trace'=>$e->getTrace()]);
                    return -2;
                }
            }
        }


        /** Updates an object in the database table $tableName.
         * @param mixed $tableTarget Valid SQL table name, OR an array of such names.
         * @param array $assignments A 1D array of STRINGS that constitute the assignments.
         *                           For example, if we $tableTarget was ['t1','t2'], $assignments might be:
         *                           ['t1.ID = t2.ID+5','t1.Name = CONCAT(t2.Name, "_clone")']
         *                           or just ['Name = "Anon"']
         * @param array $cond 2D array of conditions, of the form [[<validColumnName>,<validVariable>,<comparisonOperator>]]
         *                   Example [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE']]
         *                   Valid comparison operators: '=', '<=>', '!=', '<=', '>=', '<', '>', 'LIKE', 'NOT LIKE'
         * @param array $params Includes multiple possible parameters:
         *                      'escapeBackslashes' DEFAULT TRUE - will replace every backslash with '\\' in the query
         *                      'orderBy' An array of column names to order by
         *                      'orderType' 1 descending, 0 ascending
         *                      'limit' If not null/0, will limit number of deleted rows.
         *                      'returnRows' If true, will return the number of affected rows on success.
         *                      'justTheQuery' If true, will only return the query and not execute it.
         *                      'returnError' will return the SQL error instead of "-2" when possible. Notice that errors are not
         *                                    always properly provided.
         * @param bool $test indicates test mode
         * @returns int|string
         *      -2 server error
         *      -1 on illegal input
         *      true success
         *      false on failure
         *      <Number of updated rows> - on success, if $params['returnRows'] is set not to false.
         */
        function updateTable( $tableTarget, array $assignments, array $cond = [], array $params = []){

            //Read $params
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            isset($params['escapeBackslashes'])?
                $escapeBackslashes = $params['escapeBackslashes'] : $escapeBackslashes = true;
            isset($params['orderBy'])?
                $orderBy = $params['orderBy'] : $orderBy = null;
            isset($params['orderType'])?
                $orderType = $params['orderType'] : $orderType = 0;
            isset($params['limit'])?
                $limit = $params['limit'] : $limit = null;
            isset($params['returnRows'])?
                $returnRows = $params['returnRows'] : $returnRows = false;
            isset($params['justTheQuery'])?
                $justTheQuery = $params['justTheQuery'] : $justTheQuery = false;
            (isset($params['returnError']))?
                $returnError = $params['returnError'] : $returnError = false;

            //orderType - Note that without LIMIT, this is meaningless
            if(strtolower($orderType) == 'asc')
                $orderType = 'ASC';
            else
                $orderType = 'DESC';

            //tableName
            $tableName = (gettype($tableTarget) == 'string') ?
                $tableTarget : implode(',',$tableTarget);

            //Prepare the query to be executed
            $query = 'UPDATE '.$tableName.' SET ';

            //Assignments are treated differently as STRINGS! So remember, no binding, and sanitize it beforehand.
            // Mark strings with " or ' inside your assignments.
            $query .= implode(',',$assignments);

            //If we have conditions
            if($cond != []){
                $query .= ' WHERE ';
                try{
                    $query .=  $this->queryBuilder->expConstructor($cond);
                }catch (\Exception $e){
                    $this->logger->notice('Update query construction exception '.$e->getMessage(),['trace'=>$e->getTrace()]);
                    if($verbose){
                        echo $e->getMessage().' || trace: '.EOL;
                        var_dump($e->getTrace());
                    }
                    return -1;
                }
            }

            //If we have an order - Note that without LIMIT, this is meaningless
            if($orderBy != null){
                $query .= ' ORDER BY ';
                if(is_array($orderBy))
                    foreach($orderBy as $val){
                        $query .= $val.', ';
                        $query =  substr($query,0,-2);
                    }
                else
                    $query .= $orderBy.' ';
                $query .=' '.$orderType;
            }
            //If we have a limit
            if($limit != null){
                $query .= ' LIMIT '.$limit;
            }

            //Escape backslashes
            if($escapeBackslashes)
                $query = str_replace('\\','\\\\',$query);

            //If we are just returning the query, this is where we stop
            if($justTheQuery)
                return $query;

            $query .=';';
            if($verbose)
                echo 'Query to send: '.$query.EOL;
            //Execute the query
            if($test){
                return true;
            }
            else{
                try{
                    if(!$returnRows)
                        return $this->exeQueryBindParam($query,[],['returnError'=>$returnError]);
                    else{
                        $res = $this->exeQueryBindParam($query,[],['returnError'=>$returnError]);

                        if($res === true)
                            return $this->exeQueryBindParam('SELECT ROW_COUNT()',[],['fetchAll'=>true,'returnError'=>$returnError])[0][0];
                        else
                            return $returnError ? $res : -1;
                    }
                }
                catch(\Exception $e){
                    $this->logger->notice('Update query exception '.$e->getMessage(),['trace'=>$e->getTrace()]);
                    return -2;
                }
            }
        }

        /** Returns the SQL table prefix setting.
         * @param array $params
         *      'prefixAlias': string, default $this->prefixAlias - name of the setting
         * @returns string
         */
        function getSQLPrefix($params = []){
            return $this->sqlSettings->getSetting($params['prefixAlias']??$this->prefixAlias);
        }


        /** @todo Not necessarily good to use, backups should be performed differently
         * Backs up a database table by name $tableName
         * May choose specific columns to back up, or limit backup by time - once every $timeLimit seconds.
         * @param string $tableName valid table name
         * @param array $columns An array of column names
         * @param array $params of the form:
         *                                      'timeLimit' => int, default 1, will allow 1 backup per $timeLimit seconds
         *                                                      (e.g if 60 - one backup per minute)
         *                                      'cond'      => array, default [] -  2D array of conditions, parsed by PHPQueryBuilder.
         *                                                     Like the $cond param of the other functions here.
         *                                      'meta'      => string, default null - optional meta information about the backup.
         *                                      'orderBy' Same as the selectFromTable() param
         *                                      'orderType' Same as the selectFromTable() param
         *                                      'limit' Same as the selectFromTable() param
         *                                      'offset' Same as the selectFromTable() param
         * @returns int
         *      0 on success
         *      1 on illegal input
         *      2 column not found for one of the columns
         *      3 table or view not found
         *      4 file already exists - or "Time Limit reached" in case timeLimit isn't 1
         *      5 could not get core value secure_file_priv from database
         *      6 failed to update meta information
         *      7 general error
         */

        function backupTable(string $tableName, array $columns=[], array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            isset($params['timeLimit'])?
                $timeLimit = $params['timeLimit'] : $timeLimit = 1;
            isset($params['cond'])?
                $cond = $params['cond'] : $cond = [];
            isset($params['meta'])?
                $meta = $params['meta'] : $meta = null;
            isset($params['orderBy'])?
                $orderBy = $params['orderBy'] : $orderBy = null;
            isset($params['orderType'])?
                $orderType = $params['orderType'] : $orderType = 0;
            isset($params['limit'])?
                $limit = $params['limit'] : $limit = null;
            isset($params['offset'])?
                $offset = $params['offset'] : $offset = null;

            //metaTime is used for meta information regarding the backup
            $metaTime = time();
            $prefix = $this->getSQLPrefix();

            //Input validation and sanitation
            //----tableName
            if(preg_match('/\W/',$tableName)|| strlen($tableName)>64){
                if($verbose)
                    echo 'Illegal table name'.EOL;
                return 1;
            }
            //----Timelimit
            if($timeLimit<1)
                $timeLimit = 1;            //Normal update
            elseif($timeLimit>60*60*24*365)
                $timeLimit = 60*60*24*365; //Limit to yearly update
            elseif(preg_match('/\D/',$timeLimit)){
                if($verbose)
                    echo 'timeLimit must only contain digits!'.EOL;
                return 1;
            }
            if($timeLimit == 1)
                $time = time();
            else
                $time = 'timeLimit_'.(int)(time()/$timeLimit);
            //----columns
            if($columns == [])
                $colString = '*';
            elseif(is_array($columns)){
                foreach($columns as $val)
                    if(preg_match('/\W/',$val)|| strlen($val)>64){
                        if($verbose)
                            echo 'Illegal column name'.EOL;
                        return 1;
                    }
                $colString = implode(',',$columns);
            }
            else{
                if($verbose)
                    echo 'columns array must be an array'.EOL;
                return 1;
            }

            //Get the secure_file_priv
            if($this->settings->getSetting('secure_file_priv') != null)
                $backUpFolder = $this->settings->getSetting('secure_file_priv');
            else{
                $query = "SELECT * FROM ".$prefix."CORE_VALUES WHERE tableKey = :tableKey;";
                $sfp = $this->conn->prepare($query);
                $sfp->bindValue(':tableKey','secure_file_priv');
                try{
                    $sfp->execute();
                }catch(\Exception $e){
                    if($verbose)
                        echo 'Failed to get secure_file_priv, error:'.$e.EOL;
                    $this->logger->critical('Failed to get secure_file_priv, error: '.$e);
                    return 5;
                }
                $backUpFolder = $sfp->fetchAll()[0]['tableValue'];
            }

            $backUpFolder = str_replace('\\','/',$backUpFolder);

            $outputFile = $backUpFolder.$prefix.$tableName."_backup_".$time.".txt";

            //In case the file already exists but we didn't limit time, but we can try to wait until the nearest second.
            if(is_file($outputFile)){
                while($metaTime === time())
                    usleep(1000);
                $outputFile = $backUpFolder.$prefix.$tableName."_backup_".$time.".txt";
                if(is_file($outputFile))
                    return 4;
            }

            //Make the query
            $query = "SELECT ".$colString." INTO OUTFILE '".$outputFile."'
                          FIELDS TERMINATED BY '".DB_FIELD_SEPARATOR."'
                          LINES TERMINATED BY '\n'
                           FROM ".$prefix.$tableName;

            //If we have conditions
            if($cond != []){
                $query .= ' WHERE ';
                try{
                    $query .=  $this->queryBuilder->expConstructor($cond);
                }catch (\Exception $e){
                    //TODO log exception
                    if($verbose){
                        echo $e->getMessage().' || trace: '.EOL;
                        var_dump($e->getTrace());
                    }
                    return -1;
                }
            }

            //orderType
            if($orderType == 0)
                $orderType = 'ASC';
            else
                $orderType = 'DESC';

            //If we have an order
            if($orderBy != null){
                $query .= ' ORDER BY ';
                if(is_array($orderBy)){
                    $query .= implode(',',$orderBy);
                }
                else
                    $query .= $orderBy;
                $query .=' '.$orderType;
            }

            //If we have a limit
            if($limit != null){
                $query .= ' LIMIT ';
                if($offset)
                    $query .=$offset.',';
                $query .= $limit;
            }

            $backUp = $this->conn->prepare($query);
            try{
                if(!$test){
                    $backUp->execute();
                }
                if($verbose)
                    echo "Query for ".$prefix.$tableName.": ".$query.EOL;

                //Update meta information
                $updateMeta = $this->insertIntoTable(
                    $prefix."DB_BACKUP_META",
                    ['Backup_Date','Table_Name','Full_Name','Meta'],
                    [
                        [
                            [(string)$metaTime,'STRING'],
                            [$prefix.$tableName,'STRING'],
                            [$backUpFolder.$prefix.$tableName."_backup_".$time.".txt",'STRING'],
                            [$meta,'STRING']
                        ]
                    ],
                    $params
                    );
                if($updateMeta === false)
                    return 6;
            }
            catch(\Exception $e){
                $e = $e->getMessage();
                if($verbose)
                    echo "Query failed for ".$prefix.$tableName.", error: ".$e.EOL;
                $this->logger->critical("Backup query failed for ".$prefix.$tableName.", error: ".$e);
                if(str_contains($e, 'Column not found')){
                    return 2;
                }
                elseif(str_contains($e, 'Base table or view not found')){
                    return 3;
                }
                elseif(str_contains($e, '\' already exists')){
                    return 4;
                }
                return 7;
            }
            return 0;
        }

        /** @todo Not necessarily good to use, backups should be performed differently
         * Backs up tables.
         * @param string[] $tableNames Valid table name array
         * @param string[] $columns Name of the columns you wish to back up of the format 'tableName':['some','columns'] - [] means *.
         * @param integer[] $timeLimits Same as in backupTable
         * @param array $params Same as backupTables - BEWARE that 'meta' and 'where' would be the same for ALL the tables.
         *
         * @returns mixed
         *      json encoding of all the errors
         *      0 all good
         *      1 if $tableNames is not an array.
         */

        function backupTables(array $tableNames, array $columns=[], array $timeLimits = [], array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $errors = array();
            if(!is_array($tableNames))
                return 1;
            foreach($tableNames as $val){
                if(!isset($columns[$val]))
                    $columns[$val] = [];
                if(!isset($timeLimits[$val]))
                    $timeLimits[$val] = 1;
                $tempRes = $this->backupTable($val,$columns[$val],$params);
                if($tempRes !== 0)
                    $errors += array($val[0] => $tempRes);
            }
            if(count($errors) == 0)
                return 0;
            else
                return json_encode($errors);
        }

        /** @todo Not necessarily good to use, backups should be performed differently
         * Restores a table from an EXISTING file
         * Must have the files $url FROM secure_file_priv folder - including extension.
         * Example: restoreTable('OBJECT_CAHCE_META','OBJECT_CACHE_META_backup_timeLimit_17831.txt');
         *
         * @param string $tableName Valid table name
         * @param string $fileName Name of the file you want to restore the table from.
         * @param array $params of the form:
         *                              [
         *                              'fullPath' => bool, default true, if true will treat $fileName as the absolute path
         *                                              (else will prepend secure_file_priv)
         *                              ]
         *
         * @returns int
         *      0 on success,
         *      1 illegal input
         *      2 listed table doesnt exist
         *      3 file does not exist
         *      4 folder outside of allowed
         *      5 duplicate value of one of the unique columns
         *      6 incorrect column value (probably tried to restore a different column)
         *      7 general error
         */

        function restoreTable(string $tableName, string $fileName, $params){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            isset($params['fullPath'])?
                $fullPath = $params['fullPath'] : $fullPath = true;

            $prefix = $this->getSQLPrefix();
            //tableName
            if(preg_match('/\W/',$tableName)|| strlen($tableName)>64){
                if($verbose)
                    echo 'Illegal table name'.EOL;
                return 1;
            }
            //fileName
            if(!$fullPath){
                if(!preg_match('/^[a-zA-Z0-9._ -]+$/',$fileName) || strlen($fileName)>256){
                    if($verbose)
                        echo 'Illegal filename'.EOL;
                    return 1;
                }
            }
            else{
                if(!preg_match('/^[a-zA-Z0-9\/:._ -]+$/',$fileName) || strlen($fileName)>256){
                    if($verbose)
                        echo 'Illegal filename'.EOL;
                    return 1;
                }
            }
            //Get the secure_file_priv if this isn't the full path
            if($fullPath)
                $backUpFolder = '';
            elseif($this->settings->getSetting('secure_file_priv') != null)
                $backUpFolder = $this->settings->getSetting('secure_file_priv');
            else{
                $query = "SELECT * FROM ".$this->getSQLPrefix()."CORE_VALUES WHERE tableKey = :tableKey;";
                $sfp = $this->conn->prepare($query);
                $sfp->bindValue(':tableKey','secure_file_priv');
                try{
                    $sfp->execute();
                    $backUpFolder = $sfp->fetchAll()[0]['tableValue'];
                }
                catch (\Exception $e){
                    $this->logger->critical('Failed to get secure_file_priv of '.$prefix.$tableName.', error:'.$e);
                    return 7;
                }
            }
            //Finish the query
            $charset = 'utf8';                      //Only charset supported for now
            $query = "LOAD DATA INFILE '".$backUpFolder.$prefix.$fileName."'
                          INTO TABLE ".$prefix.$tableName."
                          CHARACTER SET ".$charset."
                          FIELDS TERMINATED BY '".DB_FIELD_SEPARATOR."'
                          LINES TERMINATED BY '\n';";
            $backUpCoreValue = $this->conn->prepare($query);
            try{
                if(!$test)
                    $backUpCoreValue->execute();
                if($verbose)
                    echo "Query for ".$prefix.$tableName.": ".$query.EOL;
            }
            catch(\Exception $e){
                $e = $e->getMessage();
                if($verbose)
                    echo 'Failed to restore '.$prefix.$tableName.', error:'.$e.EOL;
                /* TODO ADD MODULAR LOGGING CONDITION
                 */
                if(str_contains($e, 'Base table or view not found: 1146')){
                    return 2;
                }
                elseif(str_contains($e, 'No such file or directory')){
                    return 3;
                }
                elseif(str_contains($e, 'server is running with the --secure-file-priv option so it cannot execute this statement')){
                    return 4;
                }
                elseif(str_contains($e, '1062 Duplicate entry')){
                    return 5;
                }
                elseif(preg_match('/SQLSTATE[HY000]: General error: 1366/',$e)){
                    return 6;
                }
                return 7;
            }
            return 0;
        }

        /** @todo Not necessarily good to use, backups should be performed differently
         * Restores the latest backup of a table, using db_backup_meta table.
         * Will cycle all available backups by ID, from the latest to the earliest, until it succeeds, gets error 1/2/5/6, or tries all of them.
         *
         * @param string $tableName Valid table name
         * @param array $params
         *
         * @returns integer
         *      -1 server error
         *      0 on success,
         *      1 illegal input
         *      2 listed table doesnt exist
         *      5 duplicate value of one of the unique columns
         *      6 incorrect column value (probably tried to restore a different column)
         *      7 tried all available restores, they all returned errors other than above
         * */
        function restoreLatestTable(string $tableName, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $prefix = $this->getSQLPrefix();

            try{
                $availableBackups = $this->selectFromTable(
                    $this->getSQLPrefix().'DB_BACKUP_META',
                    ['Table_Name',$prefix.$tableName,'='],
                    ['Full_Name'],
                    ['orderBy'=>["ID"],'orderType'=>1,'test'=>$test,'verbose'=>$verbose]
                );
            }
            catch (\Exception $e){
                $this->logger->critical('Failed to query db_backup_meta of '.$prefix.$tableName.', error:'.$e);
                return -1;
            }
            $res = 7;
            if (!is_array($availableBackups) || count($availableBackups) == 0)
                return $res;
            foreach($availableBackups as $backup){
                $res = $this->restoreTable($tableName, $backup['Full_Name'], ['fullPath'=>true, 'test'=>$test,'verbose'=>$verbose]);
                if($res === 0 || $res === 1 || $res === 2 || $res === 5 || $res === 6 )
                    return $res;
                else
                    $res = 7;
            }
            return $res;
        }

        /** @todo Not necessarily good to use, backups should be performed differently
         * Restores tables from files.
         *
         * @param array $names 2D Array of arrays of the form ['tableName','fileName']
         * @param array $params
         *
         * @returns mixed
         *      a json encoding of all the errors
         *      0 if no errors
         *      1 if $names in case of illegal input format - also validates each table name, because they get echo'd.
         */

        function restoreTables(array $names, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $errors = array();
            if(!is_array($names))
                return 1;
            foreach($names as $val){
                if(!is_array($val))
                    return 1;
                if(!isset($val[0]) || preg_match('/\W/',$val[0])|| strlen($val[0])>64)
                    return 1;
                if(!isset($val[1]))
                    return 1;
                $tempRes = $this->restoreTable($val[0],$val[1], ['fullPath'=>false, 'test'=>$test,'verbose'=>$verbose]);
                if($tempRes !== 0)
                    $errors += array($val[0] => $tempRes);
            }
            if(count($errors) == 0)
                return 0;
            else
                return json_encode($errors);
        }

        /** @todo Not necessarily good to use, backups should be performed differently
         * Restores latest tables , using restoreLatestTable.
         * @param string[] $names Array of valid table names.
         * @param array $params
         *
         * @returns mixed
         *      a json encoding of all the errors
         *      0 if no errors
         *      1 if $names in case of illegal input format - also validates each table name, because they get echo'd.
         */
        function restoreLatestTables(array $names, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $errors = array();
            if(!is_array($names))
                return 1;
            foreach($names as $val){
                if(preg_match('/\W/',$val)|| strlen($val)>64)
                    return 1;
                $tempRes = $this->restoreLatestTable($val,['test'=>$test,'verbose'=>$verbose]);
                if($tempRes !== 0)
                    $errors += array($val => $tempRes);
            }
            if(count($errors) == 0)
                return 0;
            else
                return json_encode($errors);
        }
    }
}