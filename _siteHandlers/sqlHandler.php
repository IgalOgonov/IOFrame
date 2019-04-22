<?php
namespace IOFrame{

    require_once 'abstractLogger.php';
    require_once 'fileHandler.php';
    require_once __DIR__.'/../_util/helperFunctions.php';
    require_once __DIR__.'/../_util/PHPQueryBuilder.php';
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;

    /**Handles basic sql (mySQL, MariaDB) actions in IOFrame
     * TODO Rewrite for consistency
     * TODO Move PHPQueryGenerator into util folder and its own class
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
    */
    class sqlHandler extends abstractLogger
    {

        /** @var \PDO $conn Typical PDO connection
         * */
        private $conn = null;

        /** @var PHPQueryBuilder we use this to build our queries dynamically
        */
        public $queryBuilder;

        /**
         * Basic construction function.
         * If setting 'dbLockOnAction' is true, will ensure the global lock for DB operations is created.
         * @param settingsHandler $settings  regular settings handler.
         * @param \PDO $conn Typical PDO connection
         */
        function __construct(settingsHandler $localSettings, $params = [])
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
            $this->queryBuilder = new PHPQueryBuilder();

            //Set defaults
            if(!isset($params['redisHandler']))
                $redisHandler = null;
            else
                $redisHandler = $params['redisHandler'];


            //This is only for redisHandler - in order to avoid accidentally creating an infinite loop.
            if($redisHandler != null){
                $this->defaultSettingsParams['redisHandler'] = $redisHandler;
                $this->defaultSettingsParams['useCache'] = true;
            }
            else{
                $this->defaultSettingsParams = [];
            }

            //Now we can initiate the sql settings
            $this->sqlSettings = new settingsHandler(
                $localSettings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/sqlSettings/',
                $this->defaultSettingsParams
            );

            //Standard constructor for classes that use settings and have a DB connection
            $this->conn = prepareCon($this->sqlSettings);

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
         *  exeQueryBindParam('SELECT * FROM USERS WHERE ID=:ID AND Name=:Name', [[':ID',5],[':Name','John']], true) -
         *  This will fetch all people (in this case - one person) with the ID 5 named John.

         *
         * @param string $query Executes a query
         * @param array $params binds all parameter values in $params[1] to their names inside query in $params[0]
         * @param bool $fetchAll Will return the results if $fetchAll is true.
         *
         * @throws \Exception Throws back the exception after releasing the lock, if it was locked
         *
         * @returns mixed
         *      true - no errors executing statement
         *      false - statement not executed
         *      2D Array of results - if $fetchAll was true
         *      Throws SQL exceptions otherwise.
         * */
        function exeQueryBindParam(string $query, array $params = [], bool $fetchAll = false){
            //As a rule of thumb, a query with fetchAll will not disrupt the ROW_COUNT() and LAST_INSERT_ID() functions.
            $lockMode = ( ($this->sqlSettings->getSetting('dbLockOnAction') == true) && !$fetchAll )?
                    true : false;
            if($lockMode){
                $globalLock = fopen(DB_LOCK_FILE,'r');
                flock($globalLock, LOCK_EX);
            };

            $exe = $this->conn->prepare($query);
            //Bind params
            foreach($params as $pair){
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
            if($fetchAll)
                return $exe->fetchAll();
            else
                return $res;
        }


        /** Checks if table $tName is not empty (or doesn't exists).
         *
         * @param string $tName valid table name
         * @param bool $test indicates test mode
         * @returns int
         *      0 - all good
         *      1 - table is empty or does not exist
         *      2 - illegal input
         * */
        function checkTableNotEmpty(string $tName, bool $test =false){
            if(preg_match('/\W/',$tName)|| strlen($tName)>64){
                if($test)
                    echo 'Illegal table name'.EOL;
                return 2;
            }
            $testq = $this->conn->prepare('SELECT EXISTS(SELECT 1 FROM '.$tName.');');
            try{
                $testq->execute();
                $res = $testq->fetchAll();
            }
            catch (\Exception $e){
                if($test)
                    echo 'Error fetching table: '.$e.EOL;
                return 1;
            }
            if($res[0][0] == 1)
                return 0;
            else
                return 1;
        }

        /** Checks if tables in $tName exist.      *
         * @param array $tNames array of table names
         * @param bool $test indicates test mode
         * @returns mixed
         *      Returns a json string of the type {<name>:<error value>} for each table name given.
         *      Will only return errors - in case of 0 errors, returns 0.
         *      $tNames must be an array, else returns 1.
         */
        function checkTablesNotEmpty(array $tNames, bool $test = false){
            $errors = array();
            if(!is_array($tNames))
                return 1;
            foreach($tNames as $val){
                $tempRes = $this->checkTableNotEmpty($val,$test);
                $tempRes == 0? true : $errors += array($val => $tempRes);
            }
            if(count($errors) == 0)
                return 0;
            else
                return json_encode($errors);
        }

        /**
         * Validates exp array recursively. OUT OFDATE DONT USE THIS
        function validateExp(array $exp, $test = false){

            $temp = $this->assertTypes($exp);
            $baseType = $temp[0];
            $type = $temp[1];
            $condLength = $temp[2];

            if($baseType){
                switch($type){
                    case 'comparison':
                        if($condLength!=2){
                            if($test)
                                echo 'Comparison blocks must have 2 parameters'.EOL;
                            return false;
                        }
                        elseif(preg_match('/\W|\./',$exp[0]) || strlen($exp[0])>64){
                            if($test)
                                echo 'First condition must be a legal table column'.EOL;
                            return false;
                        }
                        break;
                    case 'selection':
                        if($condLength!=4){
                            if($test)
                                echo 'Selection block must have the same parameters as selectFromTable, except for $test'.EOL;
                            return false;
                        }
                        if(!$this->validate($exp[0],$exp[1],$exp[2],[],[],$exp[3],$test))
                            return false;
                        break;
                }
                return true;
            }
            //We are validating a condition array
            else{
                $res = true;

                if(($condLength == 0)){
                    if($test)
                        echo 'Condition arrays must actually have condition blocks!'.EOL;
                    return false;
                }
                switch($type){
                    case 'connector':
                        break;
                    case 'unary':
                        if(($condLength > 1)){
                            if($test)
                                echo 'Unary blocks must be of the form [(array)%array%,(string)%functionName%]!'.EOL;
                            return false;
                        }
                        break;
                }
                //Recursively validate all inner blocks/arrays
                for($i=0; $i<$condLength; $i++){
                    $res = ($res || $this->validateExp($exp[$i], $test));
                }
                return $res;
            }
        }
         */


        /** Validation function for select/update/delete/insert
         *
         * @param string $tableName Valid SQL table name.
         * @param array $cond a recursive array of conditions, of the form
         *                      [[<validColumnName>,<validVariable>,<comparisonOperator>],...,<connectorType>]
         *                   connectorType is true for OR, false for AND
         *                   Valid comparison operators: '=', '<=>', '!=', '<=', '>=', '<', '>', 'LIKE', 'NOT LIKE'
         *                   Example 1 [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE'],false]
         *                             translates to ( ( ID > 1 ) AND ( ID < 100 ) AND (Name LIKE '%Tom%') )
         *                   Example 2 [ [['ID',10,'>'],['Name','%Tom%','LIKE'],true]] , ['Age',15,'>'],false]
         *                             translates to ( ( ( ID > 10 ) OR ( Name LIKE '%Tom%' ) ) AND ( Age > 15 ) )
         * @param array $columns A 1D array of valid SQL column names.
         * @param array $values An array of arrays that exists either on insertion, or update (specified by $param['isUpdateQuery'] being set).
         *                      In case of insertion, will make sure the number or values (length of 2nd dimension arrays)
         *                      is the same as number of columns (length of $columns).
         * @param array $tableNames A 1D array of valid SQL table names.
         * @param array $param Any parameter that isn't boolean goes here.
         *                     'orderBy' - Array of valid SQL column names
         *                     'limit' - Digits only
         * @param bool $test
         *
         * @returns bool True if all existing arguments passed
        */
        private function validate(string $tableName = '', array $cond = [], array $columns = [],
                                  array $values = [], array $tableNames = [], array $param=[], bool $test = false){
            if(isset($param['orderBy'])){
                $orderBy = $param['orderBy'];
                if($orderBy != null){
                    if(!is_array($orderBy)){
                        if($test)
                            echo 'Order must be an array of column names, or null!'.EOL;
                        return false;
                    }
                    else{
                        foreach($orderBy as $val)
                            if(preg_match('/\W|\./',$val)|| strlen($val)>64){
                                if($test)
                                    echo 'Illegal column name at orderBy'.EOL;
                                return false;
                            }
                    }
                }
            }
            if(isset($param['limit'])){
                $limit = $param['limit'];
                //limit
                if($limit != null){
                    if(preg_match('/\D/',$limit)){
                        if($test)
                            echo 'Limit must only contain digits'.EOL;
                        return false;
                    }
                }
            }

            //Conditions
            /* Out of date
            if($cond != []){
                if(!$this->validateExp($cond, $test))
                    return false;
            }*/

            //tableName
            if($tableName !== '')
                if(preg_match('/\W/',$tableName)|| strlen($tableName)>64){
                    if($test)
                        echo 'Illegal table name at tableName'.EOL;
                    return false;
                }

            //tableNames
            if($tableNames !== []){
                if(!is_array($tableNames)){
                    if($test)
                        echo 'Table names array must be an array'.EOL;
                    return false;
                }
                foreach($tableNames as $val){
                    if(preg_match('/\W/',$val)|| strlen($val)>64){
                        if($test)
                            echo 'Illegal table name at tableNames'.EOL;
                        return false;
                    }

                }
            }

            //columns
            if($columns != []){
                if(!is_array($columns)){
                    if($test)
                        echo 'columns array must be an array'.EOL;
                    return false;
                }
                foreach($columns as $val)
                    if(preg_match('/\W|\./',$val)|| strlen($val)>64){
                        if($test)
                            echo 'Illegal column name at columns'.EOL;
                        return false;
                    }
            }

            //Values
            if($values != []){
                if(!is_array($values)){
                    if($test)
                        echo 'Values must be an array (of arrays, usually)'.EOL;
                    return false;
                }
                $colNum = count($columns);
                //Only invalid if we aren't updating
                if( !isset($param['isUpdateQuery']) )
                    foreach($values as $valueSet){
                        if(!is_array($valueSet)){
                            if($test)
                                echo 'Each value set must be an array!'.EOL;
                            return false;
                        }
                        if( (count($valueSet) != $colNum)){
                            if($test)
                                echo 'Number of values in a set must be equal to the number of columns!'.EOL;
                            return false;
                        }
                    }
            }

            return true;
        }

        /** Retrieves an object from the database table $tableName.
         * @param string $tName Valid SQL table name.
         * @param array $cond 2D array of conditions, of the form [[<validColumnName>,<validVariable>,<comparisonOperator>]]
         *                   Example [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE']]
         *                   Valid comparison operators: '=', '<=>', '!=', '<=', '>=', '<', '>', 'LIKE', 'NOT LIKE'
         * @param array $columns The columns you want, in a 1D array of the form ['col1','col2',..].
         *                       Have to be actual columns in the DB table, though! Can be *, by setting [].
         * @param array $param Includes multiple possible parameters:
         *                      ''
         *                      'noValidate' if true, will validate what it can
         *                      'orderBy' An array of column names to order by
         *                      'orderType' 1 descending, 0 ascending
         *                      'distinct' If true, will select distinct
         *                      'limit' If not null/0, will limit number of selected rows.
         *                      'justTheQuery' will only return the query it would send instead of executing
         *                      'treatAsString' will ignore all other input and just set SELECT.' '.$tableName as the query.
         * @param bool $test indicates test mode
         * @returns mixed
         *      -2 server error
         *      -1 on illegal input
         *      0 no objects exist
         *      An associative array of the form $obj['ColName'] = <Col Value> otherwise.
        */

        function selectFromTable(string $tableName, array $cond = [], array $columns = [], array $param = [], bool $test = false){

            isset($param['noValidate'])?
                $noValidate = $param['noValidate'] : $noValidate = true;

            if(!$noValidate)
                //Input validation and sanitation
                if(!$this->validate($tableName,$cond,$columns,[],[],$param,$test))
                    return -1;

            //Read $params
            isset($param['useBrackets'])?
                $useBrackets = $param['useBrackets'] : $useBrackets = false;
            isset($param['orderBy'])?
                $orderBy = $param['orderBy'] : $orderBy = null;
            isset($param['orderType'])?
                $orderType = $param['orderType'] : $orderType = 0;
            isset($param['limit'])?
                $limit = $param['limit'] : $limit = null;
            isset($param['justTheQuery'])?
                $justTheQuery = $param['justTheQuery'] : $justTheQuery = false;

            $query = '';

            if($useBrackets)
                $query .= '(';

            $query .= 'SELECT ';

            if(isset($param['ALL']))
                $query .= ' ALL ';
            elseif(isset($param['DISTINCT']))
                $query .= ' DISTINCT ';
            elseif(isset($param['DISTINCTROW']))
                $query .= ' DISTINCTROW ';
            if (isset($param['HIGH_PRIORITY']))
                $query .= ' HIGH_PRIORITY ';
            if (isset($param['STRAIGHT_JOIN']))
                $query .= ' STRAIGHT_JOIN ';
            if (isset($param['SQL_SMALL_RESULT']))
                $query .= ' SQL_SMALL_RESULT ';
            if (isset($param['SQL_BIG_RESULT']))
                $query .= ' SQL_BIG_RESULT ';
            if (isset($param['SQL_BUFFER_RESULT']))
                $query .= ' SQL_BUFFER_RESULT ';
            if (isset($param['SQL_NO_CACHE']))
                $query .= ' SQL_NO_CACHE ';
            if (isset($param['SQL_CALC_FOUND_ROWS']))
                $query .= ' SQL_CALC_FOUND_ROWS ';

            //columns
            if($columns == [])
                $params = '*';
            else{
                $params = implode(',',$columns);
            }

            //orderType
            if($orderType == 0)
                $orderType = 'ASC';
            else
                $orderType = 'DESC';
            //Prepare the query to be executed
            $query .= $params.' FROM '.$tableName;

            //If we have conditions
            if($cond != []){
                $query .= ' WHERE ';
                try{
                    $query .=  $this->queryBuilder->expConstructor($cond);
                }catch (\Exception $e){
                    //TODO log exception
                    if($test){
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
                    $query =  substr($query,0,-2);
                    foreach($orderBy as $val){
                        $query .= $val.', ';
                    }
                }
                else
                    $query .= $orderBy;
                $query .=' '.$orderType;
            }

            //If we have a limit
            if($limit != null){
                $query .= ' LIMIT '.$limit;
            }

            if($useBrackets)
                $query .= ')';

            //If we are just returning the query, this is where we stop
            if($justTheQuery)
                return $query;

            $query .=';';
            //Execute the query
            if($test)
                echo 'Query to send: '.$query.EOL;
            try{
                $obj = $this->exeQueryBindParam($query,[],true);
            }
            catch(\Exception $e){
                //TODO LOG
                return -2;
            }
            //Check whether object exists
            if (count($obj)==0)
                return 0;
            else
                return $obj;
        }

        /** Deletes an object from the database table $tableName.
         * @param string $tableName Valid SQL table name.
         * @param array $cond 2D array of conditions, of the form [[<validColumnName>,<validVariable>,<comparisonOperator>]]
         *                   Example [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE']]
         *                   Valid comparison operators: '=', '<=>', '!=', '<=', '>=', '<', '>', 'LIKE', 'NOT LIKE'
         * @param array $param Includes multiple possible parameters:
         *                      'orderBy' An array of column names to order by
         *                      'orderType' 1 descending, 0 ascending
         *                      'limit' If not null/0, will limit number of deleted rows.
         *                      'returnRows' If true, will return the number of affected rows on success.
         * @param bool $test indicates test mode
         * @returns int
         *      -2 server error
         *      -1 on illegal input
         *      true success
         *      false on failure
         *      <Number of deleted rows> - on success, if $param['returnRows'] is set not to false.
         */
        function deleteFromTable(string $tableName, array $cond = [], array $param = [], bool $test = false){

            isset($param['noValidate'])?
                $noValidate = $param['noValidate'] : $noValidate = true;

            if(!$noValidate)
                //Input validation and sanitation
                if(!$this->validate($tableName,$cond,[],[],[],$param,$test))
                    return -1;

            //Read $params
            isset($param['orderBy'])?
                $orderBy = $param['orderBy'] : $orderBy = null;
            isset($param['orderType'])?
                $orderType = $param['orderType'] : $orderType = 0;
            isset($param['limit'])?
                $limit = $param['limit'] : $limit = null;
            isset($param['returnRows'])?
                $returnRows = $param['returnRows'] : $returnRows = false;
            isset($param['justTheQuery'])?
                $justTheQuery = $param['justTheQuery'] : $justTheQuery = false;

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
                    if($test){
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
            //Execute the query
            if($test){
                echo 'Query to send: '.$query.EOL;
                return true;
            }
            else{
                try{
                    if(!$returnRows)
                        return $this->exeQueryBindParam($query,[],false);
                    else{
                        $this->exeQueryBindParam($query,[],false);
                        return $this->exeQueryBindParam('SELECT ROW_COUNT()',[],true)[0][0];
                    }
                }
                catch(\Exception $e){
                    //TODO LOG
                    return -2;
                }
            }
        }


        /** Inserts an object into the database table $tableName.
         * @param string $tableName Valid SQL table name.
         * @param array $columns The columns you want, in a 1D array of the form ['col1','col2',..].
         * @param array $values An array of arrays of the form [[<col1Value>,<col2Value>,...],[<col1Value>,<col2Value>,...],...]
         *                      Value number MUST be the length of $columns array!
         * @param array $param Includes multiple possible parameters:
         *                      'onDuplicateKey' if true, will add ON DUPLICATE KEY UPDATE.
         *                      'onDuplicateKeyExp' if 'onDuplicateKey' is true, this can be set to be a custom expression for updating
         *                      'returnRows' If true, will return the number of affected rows on success.
         * @param bool $test indicates test mode
         * @returns int
         *      -2 server error
         *      -1 on illegal input
         *      true success
         *      false on failure
         *      array [<last_insert_id>,<ROW_COUNT()>] - on success, if $param['returnRows'] is set not to false.
         */
        function insertIntoTable(string $tableName, array $columns, array $values, array $param = [], bool $test = false){

            isset($param['noValidate'])?
                $noValidate = $param['noValidate'] : $noValidate = true;

            if(!$noValidate)
                //Input validation and sanitation
                if(!$this->validate($tableName,[],$columns,$values,[],$param,$test) || $columns==[])
                    return -1;

            //Read $params
            isset($param['onDuplicateKey'])?
                $onDuplicateKey = $param['onDuplicateKey'] : $onDuplicateKey = false;
            isset($param['onDuplicateKeyExp'])?
                $onDuplicateKeyExp = $param['onDuplicateKeyExp'] : $onDuplicateKeyExp = [];
            isset($param['returnRows'])?
                $returnRows = $param['returnRows'] : $returnRows = false;
            isset($param['justTheQuery'])?
                $justTheQuery = $param['justTheQuery'] : $justTheQuery = false;

            //columns
            $columnNames = ' ('.implode(',',$columns).')';

            //Prepare the query to be executed
            $query = 'INSERT INTO '.$tableName.$columnNames.' VALUES ';

            //Values
            array_push($values,',');
            $query .=  $this->queryBuilder->expConstructor($values);

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
            //Execute the query
            if($test){
                echo 'Query to send: '.$query.EOL;
                return true;
            }
            else{
                try{
                    if(!$returnRows)
                        return $this->exeQueryBindParam($query,[],false);
                    else{
                        $this->exeQueryBindParam($query,[],false);
                        return explode(',',$this->exeQueryBindParam('SELECT CONCAT(LAST_INSERT_ID(),",",ROW_COUNT())',[],true)[0][0]);
                    }
                }
                catch(\Exception $e){
                    //TODO LOG
                    return -2;
                }
            }
        }


        /** Updates an object in the database table $tableName.
         * @param mixed $tableTarget Valid SQL table name, OR an array of such names.
         * @param array $columns The columns you want, in a 1D array of the form ['col1','col2',..].
         * @param array $assignments A 1D array of STRINGS that constitute the assignments.
         *                           For example, if we $tableTarget was ['t1','t2'], $assignments might be:
         *                           ['t1.ID = t2.ID+5','t1.Name = CONCAT(t2.Name, "_clone")']
         *                           or just ['Name = "Anon"']
         * @param array $cond 2D array of conditions, of the form [[<validColumnName>,<validVariable>,<comparisonOperator>]]
         *                   Example [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE']]
         *                   Valid comparison operators: '=', '<=>', '!=', '<=', '>=', '<', '>', 'LIKE', 'NOT LIKE'
         * @param array $param Includes multiple possible parameters:
         *                      'orderBy' An array of column names to order by
         *                      'orderType' 1 descending, 0 ascending
         *                      'limit' If not null/0, will limit number of deleted rows.
         *                      'returnRows' If true, will return the number of affected rows on success.
         * @param bool $test indicates test mode
         * @returns int|string
         *      -2 server error
         *      -1 on illegal input
         *      true success
         *      false on failure
         *      <Number of updated rows> - on success, if $param['returnRows'] is set not to false.
         */
        function updateTable( $tableTarget, array $assignments, array $cond = [], array $param = [], bool $test = false){

            $param['isUpdateQuery'] = true;

            isset($param['noValidate'])?
                $noValidate = $param['noValidate'] : $noValidate = true;

            if(!$noValidate)
                if(gettype($tableTarget) == 'string' ){
                    //Input validation and sanitation
                    if(!$this->validate($tableTarget,$cond,[],$assignments,[],$param,$test))
                        return -1;
                }
                elseif(gettype($tableTarget) == 'array' ){
                    //Input validation and sanitation
                    if(!$this->validate('',$cond,[],$assignments,$tableTarget,$param,$test))
                        return -1;
                }
            //Read $params
            isset($param['orderBy'])?
                $orderBy = $param['orderBy'] : $orderBy = null;
            isset($param['orderType'])?
                $orderType = $param['orderType'] : $orderType = 0;
            isset($param['limit'])?
                $limit = $param['limit'] : $limit = null;
            isset($param['returnRows'])?
                $returnRows = $param['returnRows'] : $returnRows = false;
            isset($param['justTheQuery'])?
                $justTheQuery = $param['justTheQuery'] : $justTheQuery = false;

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
                    if($test){
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
            //Execute the query
            if($test){
                echo 'Query to send: '.$query.EOL;
                return true;
            }
            else{
                try{
                    if(!$returnRows)
                        return $this->exeQueryBindParam($query,[],false);
                    else{
                        $this->exeQueryBindParam($query,[],false);
                        return $this->exeQueryBindParam('SELECT ROW_COUNT()',[],true)[0][0];
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
         * @param string $tableName valid table name
         * @param array $columns An array of column names
         * @param int $timeLimit will allow 1 backup per $timeLimit seconds (e.g if 60 - one backup per minute)
         * @param bool $test indicates test mode
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

        function backupTable(string $tableName, array $columns=[], int $timeLimit = 1, bool $test = false){

            //metaTime is used for meta information regarding the backup
            $metaTime = time();

            //Input validation and sanitation
            //----tableName
            if(preg_match('/\W/',$tableName)|| strlen($tableName)>64){
                if($test)
                    echo 'Illegal table name'.EOL;
                return 1;
            }
            //----Timelimit
            if($timeLimit<1)
                $timeLimit = 1;            //Normal update
            elseif($timeLimit>60*60*24*365)
                $timeLimit = 60*60*24*365; //Limit to yearly update
            elseif(preg_match('/\D/',$timeLimit)){
                if($test)
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
                        if($test)
                            echo 'Illegal column name'.EOL;
                        return 1;
                    }
                $colString = implode(',',$columns);
            }
            else{
                if($test)
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
                    if($test)
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
                        if($test)
                            echo 'Failed to update meta information about backup of '.$tableName.' at time '.$time.', error:'.$e.EOL;

                        /* TODO ADD MODULAR LOGGING CONDITION
                         */
                        return 6;
                    }
                }
                if($test)
                    echo "Query for ".$tableName.": ".$query.EOL;
            }
            catch(\Exception $e){
                if($test)
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
         * @param string[] $tableNames Valid table name array
         * @param string[] $columns Name of the columns you wish to back up of the format 'tableName':['some','columns'] - [] means *.
         * @param integer[] $timeLimits Same as in backupTable
         * @param bool $test test mode
         *
         * @returns mixed
         *      json encoding of all the errors
         *      0 all good
         *      1 if $tableNames is not an array.
         */

        function backupTables(array $tableNames, array $columns=[], array $timeLimits = [], bool $test = false){
            $errors = array();
            if(!is_array($tableNames))
                return 1;
            foreach($tableNames as $val){
                if(!isset($columns[$val]))
                    $columns[$val] = [];
                if(!isset($timeLimits[$val]))
                    $timeLimits[$val] = 1;
                $tempRes = $this->backupTable($val,$columns[$val],$timeLimits[$val],$test);
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
         * @param string $tableName Valid table name
         * @param string $fileName Name of the file you want to restore the table from.
         * @param bool $fullPath If true, will treat $fileName as the absolute path (else will prepend secure_file_priv)
         * @param bool $test test mode
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

        function restoreTable(string $tableName, string $fileName, bool $fullPath = true, bool $test = false){
            //Input validation and sanitation

            //tableName
            if(preg_match('/\W/',$tableName)|| strlen($tableName)>64){
                if($test)
                    echo 'Illegal table name'.EOL;
                return 1;
            }
            //fileName
            if(!$fullPath){
                if(!preg_match('/^[a-zA-Z0-9._ -]+$/',$fileName) || strlen($fileName)>256){
                    if($test)
                        echo 'Illegal filename'.EOL;
                    return 1;
                }
            }
            else{
                if(!preg_match('/^[a-zA-Z0-9\/:._ -]+$/',$fileName) || strlen($fileName)>256){
                    if($test)
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
                if($test)
                    echo "Query for ".$tableName.": ".$query.EOL;
            }
            catch(\Exception $e){
                if($test)
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
         * @param string $tableName Valid table name
         * @param bool $test test mode
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
        function restoreLatestTable(string $tableName, bool $test = false){

            try{
                $availableBackups = $this->selectFromTable('db_backup_meta', ['Table_Name',$tableName,'='], ['Full_Name'],
                    ['orderBy'=>["ID"],'orderType'=>1],$test);
            }
            catch (\Exception $e){
                $this->logger->critical('Failed to query db_backup_meta of '.$tableName.', error:'.$e);
                return -1;
            }
            $res = 7;
            if (!is_array($availableBackups))
                return $res;
            foreach($availableBackups as $backup){
                $res = $this->restoreTable($tableName, $backup['Full_Name'], true, $test);
                if($res === 0 || $res === 1 || $res === 2 || $res === 5 || $res === 6 )
                    return $res;
                else
                    $res = 7;
            }
            return $res;
        }

        /** Restores tables from files.
         *
         * @param array $names 2D Array of arrays of the form ['tableName','fileName']
         * @param bool $test test mode
         *
         * @returns mixed
         *      a json encoding of all the errors
         *      0 if no errors
         *      1 if $names in case of illegal input format - also validates each table name, because they get echo'd.
         */

        function restoreTables(array $names, bool $test = false){
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
                $tempRes = $this->restoreTable($val[0],$val[1], false, $test);
                $tempRes == 0? true : $errors += array($val[0] => $tempRes);
            }
            if(count($errors) == 0)
                return 0;
            else
                return json_encode($errors);
        }

        /** Restores latest tables , using restoreLatestTable.

         * @param string[] $names Array of valid table names.
         * @param bool $test test mode
         *
         * @returns mixed
         *      a json encoding of all the errors
         *      0 if no errors
         *      1 if $names in case of illegal input format - also validates each table name, because they get echo'd.
         */
        function restoreLatestTables(array $names, bool $test = false){
            $errors = array();
            if(!is_array($names))
                return 1;
            foreach($names as $val){
                if(preg_match('/\W/',$val)|| strlen($val)>64)
                    return 1;
                $tempRes = $this->restoreLatestTable($val,$test);
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