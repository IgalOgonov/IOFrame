<?php
namespace IOFrame\Handlers{
    use IOFrame;
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;
    define('SQLHandler',true);
    if(!defined('abstractLogger'))
        require 'abstractLogger.php';
    if(!defined('FileHandler'))
        require 'FileHandler.php';
    if(!defined('helperFunctions'))
        require __DIR__ . '/../Util/helperFunctions.php';
    if(!defined('PHPQueryBuilder'))
        require __DIR__ . '/../Util/PHPQueryBuilder.php';

    /**Handles basic sql (mySQL, MariaDB) actions in IOFrame
     * TODO Rewrite for consistency
     * TODO Move PHPQueryGenerator into util folder and its own class
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class SQLHandler extends IOFrame\abstractLogger
    {

        /** @var \PDO $conn Typical PDO connection
         * */
        private $conn = null;

        /** @var IOFrame\Util\PHPQueryBuilder we use this to build our queries dynamically
         */
        public $queryBuilder;

        /**
         * Basic construction function.
         * If setting 'dbLockOnAction' is true, will ensure the global lock for DB operations is created.
         * @params SettingsHandler $settings  regular settings handler.
         * @params array $params
         */
        function __construct(SettingsHandler $localSettings, $params = [])
        {
            //Remember this only affects the settings - does not create anything else
            parent::__construct($localSettings);

            //Separates fields on backup/restore
            if(!defined('DB_FIELD_SEPARATOR'))
                define('DB_FIELD_SEPARATOR', '#&%#');

            //Location of the local "lock" file for database operations - in single node mode.
            if(!defined('DB_LOCK_FILE'))
                define('DB_LOCK_FILE', $localSettings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/nodeDatabase.lock');

            //This is the core of this handler
            $this->queryBuilder = new IOFrame\Util\PHPQueryBuilder();

            //Set defaults
            if(!isset($params['RedisHandler']))
                $RedisHandler = null;
            else
                $RedisHandler = $params['RedisHandler'];


            //This is only for RedisHandler - in order to avoid accidentally creating an infinite loop.
            if($RedisHandler != null){
                $this->defaultSettingsParams['RedisHandler'] = $RedisHandler;
                $this->defaultSettingsParams['useCache'] = true;
            }
            else{
                $this->defaultSettingsParams = [];
            }

            //Now we can initiate the sql settings
            $this->sqlSettings = new SettingsHandler(
                $localSettings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/sqlSettings/',
                $this->defaultSettingsParams
            );

            //Standard constructor for classes that use settings and have a DB connection
            $this->conn = IOFrame\Util\prepareCon($this->sqlSettings);

            //This is important - the DB handler may only use IOFrameHandler in local mode! Else you create An infinite dependency loop!
            //This overrides the default constructor
            $this->loggerHandler = new IOFrameHandler($this->settings, null, 'local');
            $this->logger = new Logger(LOG_DEFAULT_CHANNEL);
            $this->logger->pushHandler($this->loggerHandler);


            //Ensure the global DB operation lock exists, in case we are running in that mode
            if($this->sqlSettings->getSetting('dbLockOnAction') == true)
                if(!is_file(DB_LOCK_FILE))
                    fclose(fopen(DB_LOCK_FILE,'w'));
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
         * @params string $query Executes a query
         * @params array $paramsToBind binds all parameter values in $params[1] to their names inside query in $params[0]
         * @params array $params Includes parameters:
         *                      'fetchAll' bool, default true - If true, also fetches all the results of the query.
         *                      EXCLUDES 'test' and 'verbose'!
         *
         * @throws \Exception Throws back the exception after releasing the lock, if it was locked
         *
         * @returns mixed
         *      true - no errors executing statement
         *      false - statement not executed
         *      2D Array of results - if $fetchAll was true
         *      Throws SQL exceptions otherwise.
         * */
        function exeQueryBindParam(string $query, array $paramsToBind = [], array $params = []){

            if(isset($params['fetchAll']))
                $fetchAll = $params['fetchAll'];
            else
                $fetchAll = false;

            //As a rule of thumb, a query with fetchAll will not disrupt the ROW_COUNT() and LAST_INSERT_ID() functions.
            $lockMode = ( ($this->sqlSettings->getSetting('dbLockOnAction') == true) && !$fetchAll )?
                true : false;
            if($lockMode){
                $globalLock = fopen(DB_LOCK_FILE,'r');
                flock($globalLock, LOCK_EX);
            };

            $exe = $this->conn->prepare($query);
            //Bind params
            foreach($paramsToBind as $pair){
                $exe->bindValue($pair[0],$pair[1]);
            }
            //Execute query, and release the lock after it succeeds/fails
            try{
                $res = $exe->execute();
                if($lockMode){
                    flock($globalLock, LOCK_UN);
                    fclose($globalLock);
                };
            }
            catch(\Exception $e){
                if($lockMode){
                    flock($globalLock, LOCK_UN);
                    fclose($globalLock);
                };
                throw new \Exception($e);
            }
            if($fetchAll && $res !== false)
                return $exe->fetchAll();
            else
                return $res;
        }

        /** Retrieves an object from the database table $tableName.
         * @params string $tName Valid SQL table name.
         * @params array $cond 2D array of conditions, of the form [[<validColumnName>,<validVariable>,<comparisonOperator>]]
         *                   Example [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE']]
         *                   Valid comparison operators: '=', '<=>', '!=', '<=', '>=', '<', '>', 'LIKE', 'NOT LIKE'
         * @params array $columns The columns you want, in a 1D array of the form ['col1','col2',..].
         *                       Have to be actual columns in the DB table, though! Can be *, by setting [].
         * @params array $params Includes multiple possible parameters:
         *                      'orderBy' An array of column names to order by
         *                      'orderType' 1 descending, 0 ascending
         *                      'useBrackets' will surround the query with brackets
         *                      'limit' If not null/0, will limit number of selected rows.
         *                      'offset' SQL offset
         *                      'justTheQuery' will only return the query it would send instead of executing
         *                      'DISTINCT','DISTINCTROW','HIGH_PRIORITY',...other valid SELECT flags will add the after "SELECT"
         * @params bool $test indicates test mode
         * @returns mixed
         *      -2 server error
         *      -1 on illegal input
         *      An associative array of the form $obj['ColName'] = <Col Value> otherwise.
         */

        function selectFromTable(string $tableName, array $cond = [], array $columns = [], array $params = []){



            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            isset($params['useBrackets'])?
                $useBrackets = $params['useBrackets'] : $useBrackets = false;
            isset($params['orderBy'])?
                $orderBy = $params['orderBy'] : $orderBy = null;
            isset($params['orderType'])?
                $orderType = $params['orderType'] : $orderType = 0;
            isset($params['limit'])?
                $limit = $params['limit'] : $limit = null;
            isset($params['offset'])?
                $offset = $params['offset'] : $offset = null;
            isset($params['justTheQuery'])?
                $justTheQuery = $params['justTheQuery'] : $justTheQuery = false;

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
            if($orderType == 0)
                $orderType = 'ASC';
            else
                $orderType = 'DESC';
            //Prepare the query to be executed
            $query .= $columnsToSelect.' FROM '.$tableName;

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
                $query .= ' LIMIT '.$limit;
                if($offset)
                    $query .=', '.$offset;
            }

            if($useBrackets)
                $query .= ')';

            //If we are just returning the query, this is where we stop
            if($justTheQuery)
                return $query;

            $query .=';';
            //Execute the query
            if($verbose)
                echo 'Query to send: '.$query.EOL;
            try{
                $obj = $this->exeQueryBindParam($query,[],['fetchAll'=>true]);
            }
            catch(\Exception $e){
                //TODO Log exception
                return -2;
            }
            //Check whether object exists
            if ($obj === false)
                return -2;
            else
                return $obj;
        }

        /** Deletes an object from the database table $tableName.
         * @params string $tableName Valid SQL table name.
         * @params array $cond 2D array of conditions, of the form [[<validColumnName>,<validVariable>,<comparisonOperator>]]
         *                   Example [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE']]
         *                   Valid comparison operators: '=', '<=>', '!=', '<=', '>=', '<', '>', 'LIKE', 'NOT LIKE'
         * @params array $params Includes multiple possible parameters:
         *                      'orderBy' An array of column names to order by
         *                      'orderType' 1 descending, 0 ascending
         *                      'limit' If not null/0, will limit number of deleted rows.
         *                      'returnRows' If true, will return the number of affected rows on success.
         *                      'justTheQuery' If true, will only return the query and not execute it.
         * @params bool $test indicates test mode
         * @returns int
         *      -2 server error
         *      -1 on illegal input
         *      true success
         *      false on failure
         *      <Number of deleted rows> - on success, if $params['returnRows'] is set not to false.
         */
        function deleteFromTable(string $tableName, array $cond = [], array $params = []){

            //Read $params
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
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

            //orderType
            if($orderType == 0)
                $orderType = 'ASC';
            else
                $orderType = 'DESC';

            //Prepare the query to be executed
            $query = 'DELETE FROM '.$tableName;

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
                $query .= ' LIMIT '.$limit;
            }

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
                        return $this->exeQueryBindParam($query,[]);
                    else{
                        $this->exeQueryBindParam($query,[]);
                        return $this->exeQueryBindParam('SELECT ROW_COUNT()',[],['fetchAll'=>true])[0][0];
                    }
                }
                catch(\Exception $e){
                    //TODO LOG
                    return -2;
                }
            }
        }


        /** Inserts an object into the database table $tableName.
         * @params string $tableName Valid SQL table name.
         * @params array $columns The columns you want, in a 1D array of the form ['col1','col2',..].
         * @params array|string $values An array of arrays of the form
         *                      [[<col1Value>,<col2Value>,...],[<col1Value>,<col2Value>,...],...]
         *                      Values number MUST be the length of $columns array!
         *                      May also be a string - in which case it it will be simple inserted.
         * @params array $params Includes multiple possible parameters:
         *                      'onDuplicateKey' if true, will add ON DUPLICATE KEY UPDATE.
         *                      'onDuplicateKeyExp' if 'onDuplicateKey' is true, this can be set to be a custom expression for updating
         *                      'returnRows' If true, will return the number of affected rows on success.
         *                      'justTheQuery' If true, will only return the query and not execute it.
         * @params bool $test indicates test mode
         * @returns int
         *      -2 server error
         *      -1 on illegal input
         *      true success
         *      false on failure
         *      array [<last_insert_id>,<ROW_COUNT()>] - on success, if $params['returnRows'] is set not to false.
         */
        function insertIntoTable(string $tableName, array $columns, $values, array $params = []){

            //Read $params
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            isset($params['onDuplicateKey'])?
                $onDuplicateKey = $params['onDuplicateKey'] : $onDuplicateKey = false;
            isset($params['onDuplicateKeyExp'])?
                $onDuplicateKeyExp = $params['onDuplicateKeyExp'] : $onDuplicateKeyExp = [];
            isset($params['returnRows'])?
                $returnRows = $params['returnRows'] : $returnRows = false;
            isset($params['justTheQuery'])?
                $justTheQuery = $params['justTheQuery'] : $justTheQuery = false;

            //columns
            $columnNames = ' ('.implode(',',$columns).')';

            //Prepare the query to be executed
            $query = 'INSERT INTO '.$tableName.$columnNames.' ';

            //Values
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
                        $query .= $colName.'=VALUES('.$colName.'), ';
                    }
                    $query =  substr($query,0,-2).' ';
                }
                //A custom expression is also possible
                else{
                    $query .=  $this->queryBuilder->expConstructor($onDuplicateKeyExp);
                }
            }

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
                        return $this->exeQueryBindParam($query,[]);
                    else{
                        $this->exeQueryBindParam($query,[]);
                        return explode(',',$this->exeQueryBindParam('SELECT CONCAT(LAST_INSERT_ID(),",",ROW_COUNT())',[],['fetchAll'=>true])[0][0]);
                    }
                }
                catch(\Exception $e){
                    //TODO LOG
                    return -2;
                }
            }
        }


        /** Updates an object in the database table $tableName.
         * @params mixed $tableTarget Valid SQL table name, OR an array of such names.
         * @params array $columns The columns you want, in a 1D array of the form ['col1','col2',..].
         * @params array $assignments A 1D array of STRINGS that constitute the assignments.
         *                           For example, if we $tableTarget was ['t1','t2'], $assignments might be:
         *                           ['t1.ID = t2.ID+5','t1.Name = CONCAT(t2.Name, "_clone")']
         *                           or just ['Name = "Anon"']
         * @params array $cond 2D array of conditions, of the form [[<validColumnName>,<validVariable>,<comparisonOperator>]]
         *                   Example [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE']]
         *                   Valid comparison operators: '=', '<=>', '!=', '<=', '>=', '<', '>', 'LIKE', 'NOT LIKE'
         * @params array $params Includes multiple possible parameters:
         *                      'orderBy' An array of column names to order by
         *                      'orderType' 1 descending, 0 ascending
         *                      'limit' If not null/0, will limit number of deleted rows.
         *                      'returnRows' If true, will return the number of affected rows on success.
         *                      'justTheQuery' If true, will only return the query and not execute it.
         * @params bool $test indicates test mode
         * @returns int|string
         *      -2 server error
         *      -1 on illegal input
         *      true success
         *      false on failure
         *      <Number of updated rows> - on success, if $params['returnRows'] is set not to false.
         */
        function updateTable( $tableTarget, array $assignments, array $cond = [], array $params = []){

            //Read $params
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
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
                    //TODO log exception
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
                        return $this->exeQueryBindParam($query,[]);
                    else{
                        $this->exeQueryBindParam($query,[]);
                        return $this->exeQueryBindParam('SELECT ROW_COUNT()',[],['fetchAll'=>true])[0][0];
                    }
                }
                catch(\Exception $e){
                    //TODO LOG
                    return -2;
                }
            }
        }


        /** Backs up a database table by name $tableName
         * May choose specific columns to back up, or limit backup by time - once every $timeLimit seconds.
         * @params string $tableName valid table name
         * @params array $columns An array of column names
         * @params array $params of the form:
         *                                      [
         *                                      'timeLimit' => int, default 1, will allow 1 backup per $timeLimit seconds
         *                                                      (e.g if 60 - one backup per minute)
         *                                      ]
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

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            isset($params['timeLimit'])?
                $timeLimit = $params['timeLimit'] : $timeLimit = 1;

            //metaTime is used for meta information regarding the backup
            $metaTime = time();

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
                $query = "SELECT * FROM "."CORE_VALUES WHERE tableKey = :tableKey;";
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

            //Update meta information
            $metaQuery = "INSERT INTO "."DB_BACKUP_META(Backup_Date, Table_Name, Full_Name) VALUES(:Backup_Date, :Table_Name, :Full_Name) ";
            $updateMeta = $this->conn->prepare($metaQuery);
            $updateMeta->bindValue(':Backup_Date',$metaTime);
            $updateMeta->bindValue(':Table_Name',$tableName);
            $updateMeta->bindValue(':Full_Name',$backUpFolder.$tableName."_backup_".$time.".txt");
            //^to be executed after the main query^

            //Finish it
            $query = "SELECT ".$colString." INTO OUTFILE '".$backUpFolder.$tableName."_backup_".$time.".txt'
                          FIELDS TERMINATED BY '".DB_FIELD_SEPARATOR."'
                          LINES TERMINATED BY '\n'
                           FROM ".$tableName;
            $backUp = $this->conn->prepare($query);
            try{
                if(!$test){
                    $backUp->execute();
                    try{
                        $updateMeta->execute();
                    }catch(\Exception $e){
                        if($verbose)
                            echo 'Failed to update meta information about backup of '.$tableName.' at time '.$time.', error:'.$e.EOL;

                        /* TODO ADD MODULAR LOGGING CONDITION
                         */
                        return 6;
                    }
                }
                if($verbose)
                    echo "Query for ".$tableName.": ".$query.EOL;
            }
            catch(\Exception $e){
                if($verbose)
                    echo "Query failed for ".$tableName.", error: ".$e.EOL;
                $this->logger->critical("Backup query failed for ".$tableName.", error: ".$e);
                if(preg_match('/Column not found/',$e)){
                    return 2;
                }
                elseif(preg_match('/Base table or view not found/',$e)){
                    return 3;
                }
                elseif(preg_match('/\' already exists/',$e)){
                    return 4;
                }
                return 7;
            }
            return 0;
        }

        /** Backs up tables.
         * @params string[] $tableNames Valid table name array
         * @params string[] $columns Name of the columns you wish to back up of the format 'tableName':['some','columns'] - [] means *.
         * @params integer[] $timeLimits Same as in backupTable
         * @params array $params
         *
         * @returns mixed
         *      json encoding of all the errors
         *      0 all good
         *      1 if $tableNames is not an array.
         */

        function backupTables(array $tableNames, array $columns=[], array $timeLimits = [], array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $errors = array();
            if(!is_array($tableNames))
                return 1;
            foreach($tableNames as $val){
                if(!isset($columns[$val]))
                    $columns[$val] = [];
                if(!isset($timeLimits[$val]))
                    $timeLimits[$val] = 1;
                $tempRes = $this->backupTable($val,$columns[$val],['timeLimit'=>$timeLimits[$val],'test'=>$test,'verbose'=>$verbose]);
                $tempRes == 0? true : $errors += array($val => $tempRes);
            }
            if(count($errors) == 0)
                return 0;
            else
                return json_encode($errors);
        }

        /** Restores a table from an EXISTING file
         * Must have the files $url FROM secure_file_priv folder - including extension.
         * Example: restoreTable('OBJECT_CAHCE_META','OBJECT_CACHE_META_backup_timeLimit_17831.txt');
         *
         * @params string $tableName Valid table name
         * @params string $fileName Name of the file you want to restore the table from.
         * @params array $params of the form:
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

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            isset($params['fullPath'])?
                $fullPath = $params['fullPath'] : $fullPath = true;

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
                $query = "SELECT * FROM ".$this->sqlSettings->getSetting('sql_table_prefix').
                    "CORE_VALUES WHERE tableKey = :tableKey;";
                $sfp = $this->conn->prepare($query);
                $sfp->bindValue(':tableKey','secure_file_priv');
                try{
                    $sfp->execute();
                    $backUpFolder = $sfp->fetchAll()[0]['tableValue'];
                }
                catch (\Exception $e){
                    $this->logger->critical('Failed to get secure_file_priv of '.$tableName.', error:'.$e);
                    return 7;
                }
            }
            //Finish the query
            $charset = 'utf8';                      //Only charset supported for now
            $query = "LOAD DATA INFILE '".$backUpFolder.$fileName."'
                          INTO TABLE ".$tableName."
                          CHARACTER SET ".$charset."
                          FIELDS TERMINATED BY '".DB_FIELD_SEPARATOR."'
                          LINES TERMINATED BY '\n';";
            $backUpCoreValue = $this->conn->prepare($query);
            try{
                if(!$test)
                    $backUpCoreValue->execute();
                if($verbose)
                    echo "Query for ".$tableName.": ".$query.EOL;
            }
            catch(\Exception $e){
                if($verbose)
                    echo 'Failed to restore '.$tableName.', error:'.$e.EOL;
                /* TODO ADD MODULAR LOGGING CONDITION
                 */
                if(preg_match('/Base table or view not found: 1146/',$e)){
                    return 2;
                }
                elseif(preg_match('/No such file or directory/',$e)){
                    return 3;
                }
                elseif(preg_match('/server is running with the --secure-file-priv option so it cannot execute this statement/',$e)){
                    return 4;
                }
                elseif(preg_match('/1062 Duplicate entry/',$e)){
                    return 5;
                }
                elseif(preg_match('/SQLSTATE[HY000]: General error: 1366/',$e)){
                    return 6;
                }
                return 7;
            }
            return 0;
        }

        /** Restores the latest backup of a table, using db_backup_meta table.
         * Will cycle all available backups by ID, from the latest to the earliest, until it succeeds, gets error 1/2/5/6, or tries all of them.
         *
         * @params string $tableName Valid table name
         * @params array $params
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

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            try{
                $availableBackups = $this->selectFromTable('db_backup_meta', ['Table_Name',$tableName,'='], ['Full_Name'],
                    ['orderBy'=>["ID"],'orderType'=>1,'test'=>$test,'verbose'=>$verbose]);
            }
            catch (\Exception $e){
                $this->logger->critical('Failed to query db_backup_meta of '.$tableName.', error:'.$e);
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

        /** Restores tables from files.
         *
         * @params array $names 2D Array of arrays of the form ['tableName','fileName']
         * @params array $params
         *
         * @returns mixed
         *      a json encoding of all the errors
         *      0 if no errors
         *      1 if $names in case of illegal input format - also validates each table name, because they get echo'd.
         */

        function restoreTables(array $names, array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

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
                $tempRes == 0? true : $errors += array($val[0] => $tempRes);
            }
            if(count($errors) == 0)
                return 0;
            else
                return json_encode($errors);
        }

        /** Restores latest tables , using restoreLatestTable.

         * @params string[] $names Array of valid table names.
         * @params array $params
         *
         * @returns mixed
         *      a json encoding of all the errors
         *      0 if no errors
         *      1 if $names in case of illegal input format - also validates each table name, because they get echo'd.
         */
        function restoreLatestTables(array $names, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $errors = array();
            if(!is_array($names))
                return 1;
            foreach($names as $val){
                if(preg_match('/\W/',$val)|| strlen($val)>64)
                    return 1;
                $tempRes = $this->restoreLatestTable($val,['test'=>$test,'verbose'=>$verbose]);
                $tempRes == 0? true : $errors += array($val => $tempRes);
            }
            if(count($errors) == 0)
                return 0;
            else
                return json_encode($errors);
        }

        /** Returns the SQL table prefix setting.
         * @returns string
         */
        function getSQLPrefix(){
            return $this->sqlSettings->getSetting('sql_table_prefix');
        }
    }
}
?>