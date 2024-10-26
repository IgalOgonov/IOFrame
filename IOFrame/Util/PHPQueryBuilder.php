<?php


namespace IOFrame\Util{
    define('IOFrameUtilPHPQueryBuilder',true);
    /**A parser to create basic SQL Queries from arrays
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class PHPQueryBuilder
    {

        /** Empty for now
        */
        function __construct(){

        }

        /** Notice $exp IS PASSED BY REFERENCE - default (or assumed) values WILL be explicitly inserted
         * Used to determine, a directly insert implicit types of expressions, examples of which can be seen in the docs
         * and @expConstructor documentation.
         *
         * @param mixed $exp Represents an expression. May be an array or a string. More at @expConstructor
         * @param bool $useContext Use context clues, as explained above and implemented in assertTypes in case of a string
         * @param bool $useClues Use stractural clues, as explained above and implemented in assertTypes in case of a string
         * @param string $clue used to pass down a context clue to a string from its parent expression. More at @expConstructor
         *
         * @returns array Array of the form
         * [
         * $type, - base type of expression, for example 'function'
         * $argRange, - range of valid number of arguements.
         *              For example, for 'LIKE' the range is [2-2] (being a binary operator that works on strings, returns bool).
         * $outputClues - Context for each string inside the expression, if there are any. For example, a clue for '=' is
         *              ['ASIS','STRING'], as often you'd be setting/comparing an array column to some value, like WHERE(Name = 'John').
         * ]
         *
         * BELOW IS THE CODE USED TO GENERATE INITIAL BOILERPLATE, AS WELL AS NOTES ON MODIFICATION OF THE SWITCH STATEMENT:
         * -------------------------
         * Clues for $compare, $arithmetic $assign arrays: ['ASIS','STRING']
         * EXTRACT  connector: ' FROM ',
         * GET_FORMAT clue: ['ASIS','STRING'],
         * CONVERT  connector: ' AS ', | clue: ['ASIS','STRING']
         * JSON_TABLE  structure: (<arg1>, <arg2> COLUMNS (<arg3>) AS <arg4>) | clue: ['STRING','STRING','ASIS','ASIS']
         * 'BETWEEN AND', 'NOT BETWEEN AND' structure: (<arg1> BETWEEN <arg2> AND <arg3>)
         * CASE structure: <arg1> WHEN <arg2> THEN <arg3> [WHEN <arg4> THEN <arg5>  ...] [ELSE <arg(n)> ] END
         * CASE clue code:
         * if(($condLength%2) == 0)
         * $hasElse = false;
         * else
         * $hasElse = true;
         * $outputClues = ['ASIS'];
         * if($hasElse){
         * for($i=1; $i<$condLength-2; $i++)
         * array_push($outputClues,'ASIS','STRING');
         * array_push($outputClues,'STRING');
         * }
         * else{
         * for($i=1; $i<$condLength-1; $i++)
         * $outputClues = ['ASIS','STRING'];
         * }
         * CONCAT_WS clue ['STRING','ASIS','ASIS',...,'ASIS']
         *------------------------------ Code to generate boilerplate ---------------------------
         *
         * $base = ['STRING','ASIS'];
         * //Statement - recursively calls SELECT
         * $statement = ['SELECT'];
         * //Comparison
         * $compare = ['=', '<=>', '!=', '<=', '>=', '<', '>', 'IS', 'IS NOT', 'LIKE', 'NOT LIKE', 'RLIKE', 'NOT RLIKE'];
         * //Arithmetic operators - '->','->>' included despite being JSON
         * $arithmetic = ['+', '-', '*', '/', '%', '|', '&', '^', '<<', '>>', '~', '->', '->>'];
         * //Separators
         * $separator = [' ', ',', 'AND', 'OR', 'XOR', 'CSV','SSV'];
         * //Assignment - not comparison
         * $assign = [':='];
         * //Empty (or more) functions
         * $emptyFunc = ['PI', 'RAND', 'CURDATE', 'CURRENT_DATE', 'CURTIME', 'CURRENT_TIME', 'NOW', 'LOCALTIME',
         * 'LOCALTIMESTAMP','CURRENT_TIMESTAMP',  'SYSDATE', 'UNIX_TIMESTAMP', 'UTC_DATE', 'UTC_TIME',
         * 'UTC_TIMESTAMP', 'RELEASE_ALL_LOCKS', 'CURRENT_ROLE', 'CURRENT_USER',
         * 'CONNECTION_ID', 'FOUND_ROWS', 'ICU_VERSION', 'LAST_INSERT_ID', 'ROLES_GRAPHML', 'ROW_COUNT',
         * 'SCHEMA', 'SESSION_USER', 'SYSTEM_USER', 'USER', 'VERSION', 'JSON_ARRAY',
         * 'JSON_OBJECT', 'UUID', 'UUID_SHORT'];
         * //Unary (or more) functions
         * $unaryFunc = ['COALESCE', 'EXISTS', 'NOT', 'ISNULL','ASCII', 'BIN', 'BIN_LENGTH', 'CHAR', 'CHAR_LENGTH',
         * 'CHARACTER_LENGTH', 'FROM_BASE64', 'TO_BASE64', 'HEX', 'LCASE', 'LENGTH', 'LOAD_FILE', 'LOWER', 'UPPER',
         * 'LTRIM', 'OCT', 'OCTET_LENGTH', 'ORD', 'QUOTE', 'REVERSE', 'RTRIM', 'SOUNDEX', 'SPACE',
         * 'UCASE', 'UNHEX', 'ABS', 'ACOS', 'ASIN', 'ATAN', 'CEIL', 'CEILING','COS', 'COT',
         * 'CRC32', 'DEGREES', 'EXP', 'FLOOR',  'LN', 'LOG', 'LOG2', 'LOG10', 'RADIANS', 'ROUND',
         * 'SIGN', 'SIN', 'SQRT', 'TAN', 'TRUNCATE', 'VALUES', 'CHARSET', 'COLLECTION', 'DATE',
         * 'DAY', 'DAYOFMONTH',  'DAYNAME', 'DAYOFWEEK', 'DAYOFYEAR', 'FROM_DAYS',
         * 'FROM_UNIXTIME', 'HOUR', 'LAST_DAY', 'MICROSECOND', 'MINUTE', 'MONTH', 'MONTHNAME',
         * 'QUARTER', 'SECOND', 'SEC_TO_TIME', 'TIME', 'TIME_TO_SEC', 'TO_DAYS', 'TO_SECONDS',
         * 'WEEK', 'WEEKDAY','WEEKOFYEAR', 'YEAR', 'YEARWEEK', 'BINARY', 'BIT_COUNT','COMPRESS',
         * 'DES_DECRYPT', 'DES_ENCRYPT', 'MD5', 'PASSWORD', 'RANDOM_BYTES', 'SHA1', 'SHA',
         * 'STATEMENT_DIGEST', 'STATEMENT_DIGEST_TEXT', 'UNCOMPRESS', 'UNCOMPRESSED_LENGTH',
         * 'VALIDATE_PASSWORD_STRENGTH', 'IS_FREE_LOCK', 'IS_USED_LOCK', 'RELEASE_LOCK',
         * 'COERCIBILITY', 'COLLATION', 'JSON_QUOTE', 'JSON_KEYS', 'JSON_UNQUOTE',
         * 'JSON_DEPTH', 'JSON_LENGTH', 'JSON_TYPE', 'JSON_VALID', 'JSON_PRETTY', 'JSON_STORAGE_FREE',
         * 'JSON_STORAGE_SIZE', 'AVG', 'BIT_AND', 'BIT_OR', 'BIT_XOR', 'COUNT',
         * 'GROUP_CONCAT', 'JSON_ARRAYAGG', 'JSON_OBJECTAGG', 'MAX', 'MIN', 'STD', 'SUM',
         * 'VAR_POP', 'VAR_SAMP', 'VARIANCE', 'ANY_VALUE', 'GROUPING', 'INET_ATON','INET_NTOA',
         * 'INET6_ATON', 'INET6_NTOA', 'IS_IPV4', 'IS_IPV4_COMPAT', 'IS_IPV4_MAPPED', 'IS_IPV6',
         * 'IS_UUID', 'SLEEP', '!', 'DEFAULT', 'UUID_TO_BIN', 'BIN_TO_UUID',
         *
         * 'COLUMN_CHECK','COLUMN_JSON','COLUMN_LIST','JSON_COMPACT','JSON_DETAILED','JSON_LOOSE',
         * 'CHR', 'VALUE', 'LENGTHB', 'LASTVAL', 'NEXTVAL','PERCENTILE_CONT','PERCENTILE_DISC'];
         * //Binary (or more) connectors
         * $binaryCon = ['NOT IN', 'IN', 'REGEXP', 'RLIKE', 'NOT REGEXP', 'NOT RLIKE','SOUNDS LIKE', 'AS',
         *
         * 'MEDIAN OVER'];
         * //Binary (optionally more) functions -
         * $binaryFunc = ['INTERVAL', 'LEAST', 'GREATEST', 'STRCMP', 'IFNULL', 'NULLIF','CONCAT', 'ELT','FIELD',
         * 'FIND_IN_SET', 'FORMAT', 'INSTR', 'LEFT', 'RIGHT', 'LOCATE', 'MAKE_SET', 'REPEAT',
         * 'SUBSTRING','SUBSTRING_INDEX', 'SUBSTR', 'REGEXP_INSTR', 'REGEXP_LIKE','REGEXP_SUBSTR',
         * 'MOD', 'ATAN2', 'POW', 'POWER', 'ADDDATE', 'DATE_ADD', 'SUBDATE', 'DATE_SUB', 'ADDTIME',
         * 'DATEDIFF', 'DATE_FORMAT', 'EXTRACT', 'GET_FORMAT', 'MAKEDATE', 'PERIOD_ADD', 'PERIOD_DIFF',
         * 'STR_TO_DATE', 'SUBTIME', 'TIMEDIFF', 'TIME_FORMAT', 'CONVERT', 'CAST', 'ExtractValue',
         * 'UpdateXML', 'AES_DECRYPT', 'AES_ENCRYPT', 'DECODE', 'ENCODE', 'SHA2', 'GET_LOCK',
         * 'BENCHMARK', 'JSON_CONTAINS', 'JSON_EXTRACT', 'JSON_SEARCH', 'JSON_MERGE', 'JSON_MERGE_PATCH',
         * 'JSON_MERGE_PRESERVE', 'JSON_REMOVE', 'MASTER_POS_WAIT', 'NAME_CONST','JSON_VALUE',
         *
         *
         * 'COLUMN_CREATE','COLUMN_EXISTS','COLUMN_GET','COLUMN_DELETE','JSON_EXISTS','SETVAL','JSON_QUERY'];
         * //Ternary connectors
         * $ternaryCon = ['BETWEEN AND', 'NOT BETWEEN AND', 'CASE'];
         * //Ternary (optionally more) functions
         * $ternaryFunc = ['IF', 'CONCAT_WS', 'EXPORT_SET', 'LPAD',  'MID', 'REGEXP_REPLACE', 'CONV', 'RPAD',
         * 'CONVERT_TZ', 'MAKETIME', 'TIMESTAMPADD', 'TIMESTAMPDIFF', 'JSON_CONTAINS_PATH',
         * 'JSON_ARRAY_APPEND', 'JSON_ARRAY_INSERT', 'JSON_INSERT', 'JSON_REPLACE', 'JSON_SET', 'REPLACE',
         *
         * 'COLUMN_ADD'];
         * //quaternary functions
         * $quaternaryFunc = [ 'JSON_TABLE', 'INSERT'];
         * //Limited max arguments
         * $limit0 = ['CONNECTION_ID', 'CURRENT_ROLE', 'CURRENT_USER', 'RELEASE_ALL_LOCKS', 'CURDATE', 'CURRENT_DATE', 'PI', 'UTC_DATE',
         * 'ROLES_GRAPHML', 'ROW_COUNT', 'FOUND_ROWS', 'ICU_VERSION', 'UUID', 'UUID_SHORT'];
         * $limit1 = ['UTC_TIME', 'CURTIME', 'CURRENT_TIME', 'NOW', 'LOCALTIME','LOCALTIMESTAMP','CURRENT_TIMESTAMP',  'SYSDATE',
         * 'LAST_INSERT_ID', 'UNIX_TIMESTAMP', 'UTC_TIMESTAMP', 'STRING', 'ASIS', 'RAND', 'EXISTS', 'NOT', 'ISNULL','ASCII',
         * 'BIN', 'BIN_LENGTH', 'CHAR_LENGTH', 'CHARACTER_LENGTH', 'FROM_BASE64', 'TO_BASE64', 'HEX', 'LCASE', 'LENGTH', 'LOAD_FILE',
         * 'LOWER', 'UPPER','LTRIM', 'OCT', 'OCTET_LENGTH', 'ORD', 'QUOTE', 'REVERSE', 'RTRIM', 'SOUNDEX', 'SPACE','UCASE', 'UNHEX',
         * 'ABS', 'ACOS', 'ASIN', 'CEIL', 'CEILING', 'COS', 'COT', 'CRC32', 'DEGREES', 'EXP', 'FLOOR', 'LN', 'LOG2', 'LOG10',
         * 'RADIANS', 'SIGN', 'SIN', 'SQRT', 'TAN', 'VALUES', 'CHARSET', 'DATE', 'DAY', 'DAYOFMONTH',  'DAYNAME', 'DAYOFWEEK',
         * 'DAYOFYEAR', 'FROM_DAYS', 'HOUR', 'LAST_DAY', 'MICROSECOND', 'MINUTE', 'MONTH', 'MONTHNAME', 'QUARTER', 'SECOND',
         * 'SEC_TO_TIME',  'TIME', 'TIME_TO_SEC', 'TO_DAYS', 'TO_SECONDS', 'WEEKDAY','WEEKOFYEAR', 'YEAR', 'BINARY', 'MD5',
         * 'PASSWORD', 'RANDOM_BYTES', 'SHA1', 'SHA', 'STATEMENT_DIGEST', 'STATEMENT_DIGEST_TEXT', 'UNCOMPRESS', 'UNCOMPRESSED_LENGTH',
         * 'VALIDATE_PASSWORD_STRENGTH', 'IS_FREE_LOCK', 'IS_USED_LOCK', 'RELEASE_LOCK', 'COERCIBILITY', 'COLLATION', 'JSON_QUOTE',
         * 'JSON_UNQUOTE', 'JSON_DEPTH', 'JSON_TYPE', 'JSON_VALID', 'JSON_PRETTY', 'JSON_STORAGE_FREE', 'JSON_STORAGE_SIZE', 'AVG',
         * 'GROUP_CONCAT', 'JSON_ARRAYAGG' , 'MAX', 'MIN', 'STD', 'SUM', 'VAR_POP', 'VAR_SAMP', 'VARIANCE', 'ANY_VALUE',
         * 'INET_ATON','INET_NTOA', 'INET6_ATON', 'INET6_NTOA', 'IS_IPV4', 'IS_IPV4_COMPAT', 'IS_IPV4_MAPPED', 'IS_IPV6',
         * 'IS_UUID', 'SLEEP', '!', 'COLUMN_CHECK', 'COLUMN_JSON', 'COLUMN_LIST', 'JSON_COMPACT' ,'JSON_LOOSE', 'CHR', 'VALUE',
         * 'LENGTHB', 'LASTVAL', 'NEXTVAL','PERCENTILE_CONT','PERCENTILE_DISC', 'DEFAULT'];
         * $limit2 = ['=', '<=>', '!=', '<=', '>=', '<', '>', 'IS', 'IS NOT', 'LIKE', 'NOT LIKE', 'RLIKE', 'NOT RLIKE' ':=', 'ATAN', 'LOG', 'ROUND',
         * 'TRUNCATE', 'FROM_UNIXTIME', 'WEEK', 'YEARWEEK', 'BIT_COUNT','COMPRESS','DES_DECRYPT', 'JSON_KEYS', 'JSON_LENGTH',
         * 'JSON_OBJECTAGG', 'JSON_DETAILED', 'REGEXP',, 'NOT REGEXP',, 'SOUNDS LIKE', 'AS', 'MEDIAN OVER',
         * 'STRCMP', 'IFNULL', 'NULLIF', 'CONCAT', 'FIND_IN_SET', 'INSTR', 'LEFT', 'RIGHT', 'REPEAT', 'MOD', 'ATAN2', 'POW',
         * 'POWER', 'ADDDATE', 'DATE_ADD', 'SUBDATE', 'DATE_SUB', 'ADDTIME', 'DATEDIFF', 'DATE_FORMAT', 'EXTRACT',  'GET_FORMAT',
         * 'MAKEDATE', 'PERIOD_ADD', 'PERIOD_DIFF', 'STR_TO_DATE', 'SUBTIME', 'TIMEDIFF', 'TIME_FORMAT', 'CONVERT', 'CAST',
         * 'ExtractValue', 'DECODE', 'ENCODE', 'SHA2', 'GET_LOCK', 'BENCHMARK', 'NAME_CONST', 'UUID_TO_BIN', 'BIN_TO_UUID',
         * 'COLUMN_EXISTS', 'JSON_EXISTS', 'COLUMN_DELETE', 'JSON_QUERY', 'JSON_VALUE' ];
         * $limit3 = ['DES_ENCRYPT', 'FORMAT', 'LOCATE', 'SUBSTRING', 'SUBSTRING_INDEX', 'SUBSTR', 'TIMEDIFF', 'TIME_FORMAT',
         * 'REGEXP_LIKE', 'UpdateXML', 'AES_DECRYPT', 'AES_ENCRYPT', 'JSON_CONTAINS', 'COLUMN_GET', 'BETWEEN AND', 'NOT BETWEEN AND',
         * 'LPAD',  'MID', 'CONV', 'RPAD', 'CONVERT_TZ', 'MAKETIME', 'TIMESTAMPADD', 'TIMESTAMPDIFF', 'REPLACE'];
         * $limit4 = ['SELECT', 'JSON_TABLE', 'MASTER_POS_WAIT', 'SETVAL' ];
         * $limit5 = ['REGEXP_SUBSTR', 'EXPORT_SET', 'INSERT' ];
         * $limit6 = ['REGEXP_REPLACE'];
         *
         *
         *
         * $temp = array_merge($base,$statement,$compare,$arithmetic,$separator,$assign,$emptyFunc,$unaryFunc,$binaryCon,$binaryFunc,$ternaryCon,$ternaryFunc,$quaternaryFunc);
         *
         * echo 'switch($exp[$condLength-1]){'.EOL.EOL;
         * foreach($temp as $name){
         * $minArg = 0;
         * if(in_array($name,$limit0)){
         * $maxArg = 0;
         * }elseif(in_array($name,$limit1)){
         * $maxArg = 1;
         * }elseif(in_array($name,$limit2)){
         * $maxArg = 2;
         * }elseif(in_array($name,$limit3)){
         * $maxArg = 3;
         * }elseif(in_array($name,$limit4)){
         * $maxArg = 4;
         * }elseif(in_array($name,$limit5)){
         * $maxArg = 5;
         * }elseif(in_array($name,$limit6)){
         * $maxArg = 6;
         * }else{
         * $maxArg = 'PHP_INT_MAX';
         * }
         * echo'&nbsp; case(\''.$name.'\'):'.EOL;
         * if(in_array($name,$quaternaryFunc)){
         * $minArg = 4;
         * echo '&nbsp;&nbsp;&nbsp;$argRange = ['.$minArg.','.$maxArg.'];'.EOL;
         * echo '&nbsp;&nbsp;&nbsp;$type = \'function\';'.EOL;
         * echo '&nbsp;&nbsp;&nbsp;$outputClues = [];'.EOL;
         * }
         * elseif(in_array($name,$ternaryFunc) ||in_array($name,$ternaryCon)){
         * $minArg = 3;
         * $type = in_array($name,$ternaryFunc) ? 'function':'connector';
         * echo '&nbsp;&nbsp;&nbsp;$argRange = ['.$minArg.','.$maxArg.'];'.EOL;
         * echo '&nbsp;&nbsp;&nbsp;$type = \''.$type.'\';'.EOL;
         * echo '&nbsp;&nbsp;&nbsp;$outputClues = [];'.EOL;
         * }
         * elseif(in_array($name,$compare) ||in_array($name,$binaryFunc) ||in_array($name,$arithmetic) ||
         * in_array($name,$binaryCon) ||in_array($name,$assign) ){
         * $minArg = 2;
         * $type = in_array($name,$binaryFunc) ? 'function':'connector';
         * echo '&nbsp;&nbsp;&nbsp;$argRange = ['.$minArg.','.$maxArg.'];'.EOL;
         * echo '&nbsp;&nbsp;&nbsp;$type = \''.$type.'\';'.EOL;
         * if(in_array($name,$compare) || in_array($name,$arithmetic) || in_array($name,$assign))
         * echo '&nbsp;&nbsp;&nbsp;$outputClues = [\'ASIS\',\'STRING\'];'.EOL;
         * else
         * echo '&nbsp;&nbsp;&nbsp;$outputClues = [];'.EOL;
         * }
         * elseif(in_array($name,$unaryFunc) ||in_array($name,$base) || in_array($name,$statement) ||in_array($name,$separator) ){
         * $minArg = 1;
         * if(in_array($name,$unaryFunc))
         * $type =  'function';
         * elseif(in_array($name,$base))
         * $type =  'base';
         * elseif(in_array($name,$statement))
         * $type =  'selection';
         * else
         * $type = 'connector';
         * echo '&nbsp;&nbsp;&nbsp;$argRange = ['.$minArg.','.$maxArg.'];'.EOL;
         * echo '&nbsp;&nbsp;&nbsp;$type = \''.$type.'\';'.EOL;
         * if($type == 'selection')
         * echo '&nbsp;&nbsp;&nbsp;$outputClues = [\'STRING\'];'.EOL;
         * else
         * echo '&nbsp;&nbsp;&nbsp;$outputClues = [];'.EOL;
         * }
         * elseif(in_array($name,$emptyFunc)){
         * echo '&nbsp;&nbsp;&nbsp;$argRange = ['.$minArg.','.$maxArg.'];'.EOL;
         * echo '&nbsp;&nbsp;&nbsp;$type = \'function\';'.EOL;
         * echo '&nbsp;&nbsp;&nbsp;$outputClues = [];'.EOL;
         * }
         * echo '&nbsp;&nbsp; break;'.EOL.EOL;
         * }
         * echo    EOL.'}'.EOL;
         *
         */
        private function assertTypes(mixed & $exp, bool $useContext = true, bool $useClues = false, string $clue = ''): array {
            if (!is_array($exp))
                $exp = [$exp];
            $condLength = count($exp); //When returning, always return $condLength - 1 not to parse the last element, which
            //will always be set as the operation, even if it was implicit
            $argRange = [0, PHP_INT_MAX];
            $outputClues = [];
            $tempType = gettype($exp[$condLength - 1]);

            //Reserved for future expansion - has to be out of the elseif chain in order to modify the last element and continue
            if ($tempType == 'array') {
                //For now, just checks if the param OP is set.
                // If it is then loads the array as $extraParams and replaces the element with $extraParams['OP'],
                // Else just pushes the default CSV, sets type to 'base' and returns
                if (isset($exp[$condLength - 1]['OP'])) {
                    $extraParams = $exp[$condLength - 1];
                    $exp[$condLength - 1] = $extraParams['OP'];
                } else {
                    $exp[$condLength] = 'CSV';
                    $type = 'base';
                    return [$type, $argRange, $outputClues];
                }
            }
            //Default option for non-arrays / non-strings, nothing to see here
            if ($tempType == 'integer' || $tempType == 'double' ||
                $tempType == 'NULL' || $tempType == 'boolean'
            ) {
                if ($condLength > 1) {
                    $exp[$condLength] = 'CSV';
                } else {
                    $exp[$condLength] = 'ASIS';
                }
                $type = 'base';
            } //Every possible type


            elseif ($tempType == 'string') {
                //O(1) lookup! Efficiency! Was generated via a script, source included somewhere in the docs..
                switch ($exp[$condLength - 1]) {

                    case('STRING'):
                        $argRange = [1, 1];
                        $type = 'base';
                        break;

                    case('ASIS'):
                        $argRange = [1, 1];
                        $type = 'base';
                        break;

                    case('SELECT'):
                        $argRange = [1, 4];
                        $type = 'selection';
                        $outputClues = ['STRING'];
                        break;

                    case('='):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('<=>'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('!='):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('<='):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('>='):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('<'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('>'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('IS'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('IS NOT'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('LIKE'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('NOT LIKE'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('RLIKE'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('NOT RLIKE'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('+'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('-'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('*'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('/'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('%'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('|'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('&'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('^'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('<<'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('>>'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('~'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('->'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('->>'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case(' '):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'connector';
                        break;

                    case(','):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'connector';
                        break;

                    case('AND'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'connector';
                        break;

                    case('OR'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'connector';
                        break;

                    case('XOR'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'connector';
                        break;

                    case('CSV'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'connector';
                        break;

                    case('SSV'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'connector';
                        break;

                    case(':='):
                        $argRange = [2, 2];
                        $type = 'connector';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('PI'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('RAND'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('CURDATE'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('CURRENT_DATE'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('CURTIME'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('CURRENT_TIME'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('NOW'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('LOCALTIME'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('LOCALTIMESTAMP'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('CURRENT_TIMESTAMP'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('SYSDATE'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('UNIX_TIMESTAMP'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('UTC_DATE'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('UTC_TIME'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('UTC_TIMESTAMP'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('RELEASE_ALL_LOCKS'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('CURRENT_ROLE'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('CURRENT_USER'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('CONNECTION_ID'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('FOUND_ROWS'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('ICU_VERSION'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('LAST_INSERT_ID'):
                        $argRange = [0, 1];
                        $type = 'function';
                        break;

                    case('ROLES_GRAPHML'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('ROW_COUNT'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('SCHEMA'):
                        $type = 'function';
                        break;

                    case('SESSION_USER'):
                        $type = 'function';
                        break;

                    case('SYSTEM_USER'):
                        $type = 'function';
                        break;

                    case('USER'):
                        $type = 'function';
                        break;

                    case('VERSION'):
                        $type = 'function';
                        break;

                    case('JSON_ARRAY'):
                        $type = 'function';
                        break;

                    case('JSON_OBJECT'):
                        $type = 'function';
                        break;

                    case('UUID'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('UUID_SHORT'):
                        $argRange = [0, 0];
                        $type = 'function';
                        break;

                    case('COALESCE'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('EXISTS'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('NOT'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('ISNULL'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('ASCII'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('BIN'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('BIN_LENGTH'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('CHAR'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('CHAR_LENGTH'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('CHARACTER_LENGTH'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('FROM_BASE64'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('TO_BASE64'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('HEX'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('LCASE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('LENGTH'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('LOAD_FILE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('LOWER'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('UPPER'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('LTRIM'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('OCT'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('OCTET_LENGTH'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('ORD'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('QUOTE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('REVERSE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('RTRIM'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('SOUNDEX'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('SPACE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('UCASE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('UNHEX'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('ABS'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('ACOS'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('ASIN'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('ATAN'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('CEIL'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('CEILING'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('COS'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('COT'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('CRC32'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('DEGREES'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('EXP'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('FLOOR'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('LN'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('LOG'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('LOG2'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('LOG10'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('RADIANS'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('ROUND'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('SIGN'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('SIN'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('SQRT'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('TAN'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('TRUNCATE'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('VALUES'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('CHARSET'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('COLLECTION'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('DATE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('DAY'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('DAYOFMONTH'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('DAYNAME'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('DAYOFWEEK'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('DAYOFYEAR'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('FROM_DAYS'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('FROM_UNIXTIME'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('HOUR'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('LAST_DAY'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('MICROSECOND'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('MINUTE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('MONTH'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('MONTHNAME'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('QUARTER'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('SECOND'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('SEC_TO_TIME'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('TIME'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('TIME_TO_SEC'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('TO_DAYS'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('TO_SECONDS'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('WEEK'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('WEEKDAY'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('WEEKOFYEAR'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('YEAR'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('YEARWEEK'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('BINARY'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('BIT_COUNT'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('COMPRESS'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('DES_DECRYPT'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('DES_ENCRYPT'):
                        $argRange = [1, 3];
                        $type = 'function';
                        break;

                    case('MD5'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('PASSWORD'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('RANDOM_BYTES'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('SHA1'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('SHA'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('STATEMENT_DIGEST'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('STATEMENT_DIGEST_TEXT'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('UNCOMPRESS'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('UNCOMPRESSED_LENGTH'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('VALIDATE_PASSWORD_STRENGTH'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('IS_FREE_LOCK'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('IS_USED_LOCK'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('RELEASE_LOCK'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('COERCIBILITY'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('COLLATION'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_QUOTE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_KEYS'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('JSON_UNQUOTE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_DEPTH'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_LENGTH'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('JSON_TYPE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_VALID'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_PRETTY'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_STORAGE_FREE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_STORAGE_SIZE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('AVG'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('BIT_AND'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('BIT_OR'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('BIT_XOR'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('COUNT'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('GROUP_CONCAT'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_ARRAYAGG'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_OBJECTAGG'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('MAX'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('MIN'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('STD'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('SUM'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('VAR_POP'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('VAR_SAMP'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('VARIANCE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('ANY_VALUE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('GROUPING'):
                        $argRange = [1, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('INET_ATON'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('INET_NTOA'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('INET6_ATON'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('INET6_NTOA'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('IS_IPV4'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('IS_IPV4_COMPAT'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('IS_IPV4_MAPPED'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('IS_IPV6'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('IS_UUID'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('SLEEP'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('!'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('DEFAULT'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('UUID_TO_BIN'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('BIN_TO_UUID'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('COLUMN_CHECK'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('COLUMN_JSON'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('COLUMN_LIST'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_COMPACT'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('JSON_DETAILED'):
                        $argRange = [1, 2];
                        $type = 'function';
                        break;

                    case('JSON_LOOSE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('CHR'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('VALUE'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('LENGTHB'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('LASTVAL'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('NEXTVAL'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('PERCENTILE_CONT'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('PERCENTILE_DISC'):
                        $argRange = [1, 1];
                        $type = 'function';
                        break;

                    case('NOT IN'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        break;

                    case('IN'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'connector';
                        break;

                    case('REGEXP'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        break;

                    case('NOT REGEXP'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        break;

                    case('SOUNDS LIKE'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        break;

                    case('AS'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        break;

                    case('MEDIAN OVER'):
                        $argRange = [2, 2];
                        $type = 'connector';
                        break;

                    case('INTERVAL'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('LEAST'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('GREATEST'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('STRCMP'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('IFNULL'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('NULLIF'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('CONCAT'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('ELT'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('FIELD'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('FIND_IN_SET'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('FORMAT'):
                        $argRange = [2, 3];
                        $type = 'function';
                        break;

                    case('INSTR'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('LEFT'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('RIGHT'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('LOCATE'):
                        $argRange = [2, 3];
                        $type = 'function';
                        break;

                    case('MAKE_SET'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('REPEAT'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('SUBSTRING'):
                        $argRange = [2, 3];
                        $type = 'function';
                        break;

                    case('SUBSTRING_INDEX'):
                        $argRange = [2, 3];
                        $type = 'function';
                        break;

                    case('SUBSTR'):
                        $argRange = [2, 3];
                        $type = 'function';
                        break;

                    case('REGEXP_INSTR'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('REGEXP_LIKE'):
                        $argRange = [2, 3];
                        $type = 'function';
                        break;

                    case('REGEXP_SUBSTR'):
                        $argRange = [2, 5];
                        $type = 'function';
                        break;

                    case('MOD'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('ATAN2'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('POW'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('POWER'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('ADDDATE'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('DATE_ADD'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('SUBDATE'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('DATE_SUB'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('ADDTIME'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('DATEDIFF'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('DATE_FORMAT'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('EXTRACT'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('GET_FORMAT'):
                        $argRange = [2, 2];
                        $type = 'function';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('MAKEDATE'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('PERIOD_ADD'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('PERIOD_DIFF'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('STR_TO_DATE'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('SUBTIME'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('TIMEDIFF'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('TIME_FORMAT'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('CONVERT'):
                        $argRange = [2, 2];
                        $type = 'function';
                        $outputClues = ['ASIS', 'STRING'];
                        break;

                    case('CAST'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('ExtractValue'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('UpdateXML'):
                        $argRange = [2, 3];
                        $type = 'function';
                        break;

                    case('AES_DECRYPT'):
                        $argRange = [2, 3];
                        $type = 'function';
                        break;

                    case('AES_ENCRYPT'):
                        $argRange = [2, 3];
                        $type = 'function';
                        break;

                    case('DECODE'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('ENCODE'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('SHA2'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('GET_LOCK'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('BENCHMARK'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('JSON_CONTAINS'):
                        $argRange = [2, 3];
                        $type = 'function';
                        break;

                    case('JSON_EXTRACT'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('JSON_SEARCH'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('JSON_MERGE'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('JSON_MERGE_PATCH'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('JSON_MERGE_PRESERVE'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('JSON_REMOVE'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('MASTER_POS_WAIT'):
                        $argRange = [2, 4];
                        $type = 'function';
                        break;

                    case('NAME_CONST'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('JSON_VALUE'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('COLUMN_CREATE'):
                        $argRange = [2, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('COLUMN_EXISTS'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('COLUMN_GET'):
                        $argRange = [2, 3];
                        $type = 'function';
                        break;

                    case('COLUMN_DELETE'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('JSON_EXISTS'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('SETVAL'):
                        $argRange = [2, 4];
                        $type = 'function';
                        break;

                    case('JSON_QUERY'):
                        $argRange = [2, 2];
                        $type = 'function';
                        break;

                    case('BETWEEN AND'):
                        $argRange = [3, 3];
                        $type = 'connector';
                        break;

                    case('NOT BETWEEN AND'):
                        $argRange = [3, 3];
                        $type = 'connector';
                        break;

                    case('CASE'):
                        $argRange = [3, PHP_INT_MAX];
                        $type = 'connector';
                        //Case [arg1] WHEN [arg2] THEN [arg3] ... [case] - 2+2*n arguements, where n is the number of cases
                        if (($condLength % 2) == 0)
                            $hasElse = false;
                        //Case [arg1] WHEN [arg2] THEN [arg3] ... ELSE [arg n-1] [case] - 3+2*n arguements, where n is the number of cases
                        else
                            $hasElse = true;
                        $outputClues = ['ASIS'];
                        if ($hasElse) {
                            for ($i = 1; $i < $condLength - 2; $i++)
                                array_push($outputClues, 'ASIS', 'STRING');
                            $outputClues[] = 'STRING';
                        } else {
                            for ($i = 1; $i < $condLength - 1; $i++)
                                array_push($outputClues, 'ASIS', 'STRING');
                        }
                        break;

                    case('IF'):
                        $argRange = [3, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('CONCAT_WS'):
                        $argRange = [3, PHP_INT_MAX];
                        $type = 'function';
                        $outputClues = ['STRING'];
                        for ($i = 1; $i < $condLength - 1; $i++)
                            $outputClues[] = 'ASIS';
                        break;

                    case('EXPORT_SET'):
                        $argRange = [3, 5];
                        $type = 'function';
                        break;

                    case('LPAD'):
                        $argRange = [3, 3];
                        $type = 'function';
                        break;

                    case('MID'):
                        $argRange = [3, 3];
                        $type = 'function';
                        break;

                    case('REGEXP_REPLACE'):
                        $argRange = [3, 6];
                        $type = 'function';
                        break;

                    case('CONV'):
                        $argRange = [3, 3];
                        $type = 'function';
                        break;

                    case('RPAD'):
                        $argRange = [3, 3];
                        $type = 'function';
                        break;

                    case('CONVERT_TZ'):
                        $argRange = [3, 3];
                        $type = 'function';
                        break;

                    case('MAKETIME'):
                        $argRange = [3, 3];
                        $type = 'function';
                        break;

                    case('TIMESTAMPADD'):
                        $argRange = [3, 3];
                        $type = 'function';
                        break;

                    case('TIMESTAMPDIFF'):
                        $argRange = [3, 3];
                        $type = 'function';
                        break;

                    case('JSON_CONTAINS_PATH'):
                        $argRange = [3, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('JSON_ARRAY_APPEND'):
                        $argRange = [3, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('JSON_ARRAY_INSERT'):
                        $argRange = [3, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('JSON_INSERT'):
                        $argRange = [3, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('JSON_REPLACE'):
                        $argRange = [3, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('JSON_SET'):
                        $argRange = [3, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('REPLACE'):
                        $argRange = [3, 3];
                        $type = 'function';
                        break;

                    case('COLUMN_ADD'):
                        $argRange = [3, PHP_INT_MAX];
                        $type = 'function';
                        break;

                    case('JSON_TABLE'):
                        $argRange = [4, 4];
                        $type = 'function';
                        $outputClues = ['STRING', 'STRING', 'ASIS', 'ASIS'];
                        break;

                    case('INSERT'):
                        $argRange = [4, 5];
                        $type = 'function';
                        break;

                    default:
                        if ($condLength > 1) {
                            $exp[$condLength] = 'CSV';
                        } else {
                            //If not, try to use clues if you can, else consider the type 'ASIS'
                            $currentClue = ($clue != '') ? $clue : 'ASIS';
                            //Maybe clues were provided by our context?
                            if ($useContext)
                                if ($currentClue == 'ASIS') {
                                    $exp[$condLength] = 'ASIS';
                                } elseif ($currentClue == 'STRING') {
                                    $exp[$condLength] = 'STRING';
                                } //This is the case where he have to guess
                                elseif ($useClues) {
                                    //tableName.columnName regex
                                    if (preg_match('/^[a-zA-Z]\w{0,63}\.[a-zA-Z]\w{0,63}$/', $exp[0]))
                                        $exp[$condLength] = 'ASIS';
                                    else {
                                        $exp[$condLength] = 'STRING';
                                    }
                                } //If we don't have a good guess, return ASIS
                                else {
                                    $exp[$condLength] = 'ASIS';
                                }
                        }
                        $type = 'base';
                }

            } //Invalid types - for example, "resource"
            else {
                $type = 'invalid';
            }

            return [$type, $argRange, $outputClues];
        }

        /** Recursively constructs an expression to be used inside a query.
         *
         * Supports *MOST COMMON* functions/connectors/operators/etc from MySQL, based on
         * https://dev.mysql.com/doc/refman/8.0/en/functions.html ,
         * As well as whatever is present only in MariaDB based on
         * https://mariadb.com/kb/en/library/function-differences-between-mariadb-103-and-mysql-80/
         *
         * 'CSV', 'SSV' Stand for comma/space separated values.
         *  Aka, ['test1','test2',5,'CSV'] will yield (test1','test2',5), or with 'SVV' - (test1' 'test2' 5).
         *
         * About ,'STRING' ,'ASIS' and Clues
         * A problem with working with SQL from PHP is, lets say, you have a the following tables TABLE_NAME and BOBBY_TABLE respectively:
         * '--ID-------------File_Name-----'          '--ID---------exe--------'
         * '--1 -----------"test1.php"-----'          '--1 ------"test1.php"---'
         * '--2 -------"myGif.gif.exe"-----'          '--2 ----"myGif.gif.exe"-'
         * '--3 -----"BOBBY_TABLE.exe"-----'
         * '--4 ------------"exe"----------'
         * Lets say you want to use the query SELECT * FROM TABLE_NAME, BOBBY_TABLE WHERE (TABLE_NAME.File_Name = BOBBY_TABLE.exe)
         * Now, when building the query, and looking on just ['File_Name','BOBBY_TABLE.exe','='], it's impossible
         * to tell whether you meant (File_Name = BOBBY_TABLE.exe) or (File_Name = "BOBBY_TABLE.exe"),
         * which obviously return different results.
         * 1.Now, are 2 ways to resolve this is to say -
         *   A) 2nd string matches ^[a-zA-Z]\w{0,63}\.[a-zA-Z]\w{0,63}$ (valid tableName.columnName regex, AFAIK),
         *      so it's PROBABLY not meant to be a string.
         *   B) You could also deduce that since File_Name is the first string in a comparison, it's PROBABLY meant
         *      to be a column name.
         *   The above is what a clue is. The 1st one is executed if $useClues = true, the 2nd one is executed by default.
         *   But what if the user WANTED to select with condition (TABLE_NAME.File_Name = "BOBBY_TABLE.exe")?
         * 2.Then the user should have specified it explicitly. But, how would the query look if not ['File_Name','BOBBY_TABLE.exe','=']?
         *   The query will be [['File_Name',"ASIS"],['BOBBY_TABLE.exe', "STRING"],'='], specifying BOBBY_TABLE.exe
         *   is meant to be "BOBBY_TABLE.exe" and File_Name should stay the same.
         *   ['File_Name',['BOBBY_TABLE.exe', "STRING"],'='] would have also worked, as the first clue for '=' (comparison)
         *   is 'ASIS', as described in 1.B .
         *
         * Skipped function topics in MySQL:
         *  - Full-Text Search Functions (Match will be implemented in the future)
         *  - Spatial Analysis Functions
         *  - MySQL Enterprise Encryption Functions
         *  - Precision Math (??? I found no functions/operators, just discussion about precision math support. Wrong section?)
         *  TODO ADD - Window functions
         *  TODO ADD - Triggers
         *  TODO Rewrite rules/names according to https://dev.mysql.com/doc/refman/8.0/en/expressions.html
         *
         * Important:
         * - CAST is an alias of CONVERT here!
         *
         * @param mixed $exp Represents an expression. May be an array or a string. More about expressions in the documentation.
         *
         *                   Example 1: [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE'],false]
         *                             translates to ( ( ID > 1 ) AND ( ID < 100 ) AND (Name LIKE '%Tom%') )
         *
         *                   Example 2: [ [['ID',10,'>'],['Name','%Tom%','LIKE'],true]] , ['Age',15,'>'],false]
         *                             translates to ( ( ( ID > 10 ) OR ( Name LIKE '%Tom%' ) ) AND ( Age > 15 ) )
         *
         *                   Example 3: [[$tableName,[['Map_Name',"cp/objects.php",'='],['Last_Changed',$testMetaTime,'>'],'AND'],['Map_Name'],[],'SELECT'],'EXISTS']
         *                              translates to
         *                              EXISTS( SELECT Map_Name FROM OBJECT_MAP WHERE ( ( Map_Name = 'cp/objects.php' ) AND ( Last_Changed > 0 ) ) )
         * @param bool $useContext Use context clues, as explained above and implemented in assertTypes in case of a string
         * @param bool $useClues Use stractural clues, as explained above and implemented in assertTypes in case of a string
         * @param string $clue used to pass down a context clue to a string from its parent expression.
         *               Example: ['Name','%John%','LIKE'] passes clues 'ASIS' and 'STRING' with 'Name' and '%John%', respectively.
         *
         * @throws \Exception Thrown if minimum variable number for an expression is not met,
         *          Example: ['5','+'] can't be (5+). If you wanted ('5','+'), you should have passed ['"5"','"+"'(optionally - ,'CSV')]
         *
         *
         * @returns string The resulting query
         */
        function expConstructor(mixed $exp, bool $useContext = true, bool $useClues = false, string $clue = ''): string {
            $temp = $this->assertTypes($exp, $useContext, $useClues, $clue);
            //Indicates that a type
            $type = $temp[0];
            $argRange = $temp[1];
            $nextClues = $temp[2];
            $res = '';

            $condLength = count($exp);
            //If we are below the minimum number of arguments, log an error and return '';
            if ($condLength - 1 < $argRange[0]) {
                throw new \Exception('Tried to construct query of operation type ' . $exp[$condLength - 1] . ' providing ' .
                    ($condLength - 1) . ' arguments, when the minimal number is ' . $argRange[0]);
            }
            //If we are over the maximum number of arguments, trim them.
            if ($condLength - 1 > $argRange[1]) {
                //TODO In strict enough logging mode, log a notice about trimmed values
                $lastParam = $exp[$condLength - 1];
                $exp = array_slice($exp, 0, $argRange[1]);
                $condLength = count($exp);
                $exp[$condLength - 1] = $lastParam;
            }
            switch ($type) {
                case 'base':
                    //In case of an array
                    if (is_array($exp[0]) || ($condLength > 2)) {
                        $connector = match ($exp[$condLength - 1]) {
                            'SSV', ' ' => ' ',
                            'CSV', ',' => ', ',
                            default => ', ',
                        };
                        $res .= '( ';
                        for ($i = 0; $i < $condLength - 1; $i++) {
                            $res .= $this->expConstructor($exp[$i], $useContext, $useClues) . $connector;
                        }
                        $res = substr($res, 0, -strlen($connector));
                        $res .= ' )';
                    } //In this case, we have an "array" that looks like [<value>,<OP>]
                    else {
                        //In case of a string, we have to decide how to interpret it
                        if (gettype($exp[0]) == 'string') {
                            //Common pitfalls ahead
                            if (strtolower($exp[0]) == 'false')
                                $exp[0] = '0';
                            elseif (strtolower($exp[0]) == 'true')
                                $exp[0] = '1';
                            elseif (strtolower($exp[0]) == 'null')
                                $exp[0] = 'NULL';
                            //Try to determine if a string really need to be 'ASIS', or a string
                            else {
                                //First, lets see if we are told the type explicitly
                                if ($exp[1] == 'STRING') {
                                    $stringType = 'STRING';
                                } //default - ASIS
                                else {
                                    $stringType = 'ASIS';
                                }
                                //Add quotes to the string if you think it's of string type
                                if ($stringType == 'STRING') {
                                    $exp[0] = "'" . $exp[0] . "'";
                                }
                            }
                        }
                        if (gettype($exp[0]) == 'integer' || gettype($exp[0]) == 'double') {
                            $exp[0] = strval($exp[0]);
                        } elseif (gettype($exp[0]) == 'boolean') {
                            if ($exp[0])
                                $exp[0] = '1';
                            else
                                $exp[0] = '0';
                        } elseif (gettype($exp[0]) == 'NULL') {
                            $exp[0] = 'NULL';
                        }
                        //Set the result
                        $res .= $exp[0];
                    }
                    break;
                case 'comparison':
                    if (gettype($exp[1]) == 'string') {
                        if (strtolower($exp[1]) == 'false')
                            $exp[1] = '0';
                        elseif (strtolower($exp[1]) == 'true')
                            $exp[1] = '1';
                        elseif (strtolower($exp[1]) == 'null')
                            $exp[1] = 'NULL';
                        else
                            $exp[1] = "'" . $exp[1] . "'";
                    }
                    if (gettype($exp[1]) == 'integer' || gettype($exp[1]) == 'double') {
                        $exp[1] = strval($exp[1]);
                    } elseif (gettype($exp[1]) == 'boolean') {
                        if ($exp[1])
                            $exp[1] = '1';
                        else
                            $exp[1] = '0';
                    } elseif (gettype($exp[1]) == 'NULL') {
                        $exp[1] = 'NULL';
                    }
                    $comparator = $exp[2] ?? '=';
                    if (!isset($nextClues[0]))
                        $nextClues[0] = 'ASIS';
                    if (!isset($nextClues[0]))
                        $nextClues[1] = 'ASIS';
                    $leftSide = $this->expConstructor($exp[0], $useContext, $useClues, $nextClues[0]);
                    $rightSide = $this->expConstructor($exp[1], $useContext, $useClues, $nextClues[1]);//.$connector;
                    $res .= '( ' . $leftSide . ' ' . $comparator . ' ' . $rightSide . ' )';
                    break;

                case 'selection':
                    for ($i = 1; $i < 4; $i++)
                        if (!isset($exp[$i]))
                            $exp[$i] = [];
                    $exp[3]['justTheQuery'] = true;
                    $res .= $this->selectFromTable($exp[0], $exp[1], $exp[2], $exp[3]);
                    break;
                //Due to similarities, function and connector share this case
                case 'function':
                case 'connector':
                    switch ($exp[$condLength - 1]) {
                        //Handle special structure
                        case 'JSON_TABLE':
                            //JSON_TABLE(<arg1>, <arg2> COLUMNS (<arg3>) AS <arg4>)
                            $res = '( JSON_TABLE(';
                            $res .= $this->expConstructor($exp[0], $useContext, $useClues, $nextClues[0]) . ', ';
                            $res .= $this->expConstructor($exp[1], $useContext, $useClues, $nextClues[1]);
                            $res .= 'COLUMNS{';
                            $res .= $this->expConstructor($exp[2], $useContext, $useClues, $nextClues[2]);
                            $res .= ') ) AS';
                            $res .= $this->expConstructor($exp[3], $useContext, $useClues, $nextClues[3]);
                            $res .= ' )';
                            break;
                        case 'BETWEEN AND':
                        case 'NOT BETWEEN AND':
                            //(<arg1> BETWEEN <arg2> AND <arg3>)
                            if ($exp[$condLength - 1] == 'BETWEEN AND')
                                $connector = ' BETWEEN ';
                            else
                                $connector = ' NOT BETWEEN ';
                            $res = '(';
                            $res .= $this->expConstructor($exp[0], $useContext, $useClues, $nextClues[0]) . $connector;
                            $res .= $this->expConstructor($exp[2], $useContext, $useClues, $nextClues[2]) . ' AND ';
                            $res .= $this->expConstructor($exp[3], $useContext, $useClues, $nextClues[3]);
                            $res .= ' )';
                            break;
                        case 'CASE':
                            //CASE <arg1> WHEN <arg2> THEN <arg3> [WHEN <arg4> THEN <arg5>  ...] [ELSE <arg(n)> ] END
                            if (($condLength % 2) == 0)
                                $hasElse = false;
                            else
                                $hasElse = true;
                            $res = '( CASE ';
                            $res .= $this->expConstructor($exp[0], $useContext, $useClues, $nextClues[0]);
                            if ($hasElse) {
                                for ($i = 1; $i < $condLength - 2; $i += 2) {
                                    $res .= ' WHEN ';
                                    $res .= $this->expConstructor($exp[$i], $useContext, $useClues, $nextClues[$i]);
                                    $res .= ' THEN ';
                                    $res .= $this->expConstructor($exp[$i + 1], $useContext, $useClues, $nextClues[$i + 1]);
                                }
                                $res .= ' ELSE ';
                                $res .= $this->expConstructor($exp[$condLength - 2], $useContext, $useClues, $nextClues[$condLength - 2]);
                            } else {
                                for ($i = 1; $i < $condLength - 1; $i += 2) {
                                    $res .= ' WHEN ';
                                    $res .= $this->expConstructor($exp[$i], $useContext, $useClues, $nextClues[$i]);
                                    $res .= ' THEN ';
                                    $res .= $this->expConstructor($exp[$i + 1], $useContext, $useClues, $nextClues[$i + 1]);
                                }
                            }
                            $res .= ' END)';
                            break;
                        default:
                            if ($type == 'function') {
                                //A few special cases
                                if ($exp[$condLength - 1] == 'EXTRACT')
                                    $connector = ' FROM ';
                                elseif ($exp[$condLength - 1] == 'CONVERT')
                                    $connector = ' AS ';
                                else
                                    $connector = ', ';
                                $prefix = ' ' . $exp[$condLength - 1] . '( ';
                                $suffix = ') ';
                            } else {
                                if ($exp[$condLength - 1] == 'CSV')
                                    $connector = ', ';
                                elseif ($exp[$condLength - 1] == 'SSV')
                                    $connector = ' ';
                                else
                                    $connector = ' ' . $exp[$condLength - 1] . ' ';
                                if ($exp[$condLength - 1] !== ' ' && $exp[$condLength - 1] !== ',') {
                                    $prefix = '( ';
                                    $suffix = ') ';
                                } else {
                                    $prefix = ' ';
                                    $suffix = ' ';
                                }
                            }
                            $res .= $prefix;
                            //Go over all arguments
                            for ($i = 0; $i < $condLength - 1; $i++) {

                                //Add a clue if it's a string
                                if (gettype($exp[$i]) == 'string') {
                                    //If a clue is not set, Loop over clue array, unless it's empty
                                    if (!isset($nextClues[$i])) {
                                        if ($nextClues != [])
                                            $nextClues[$i] = $nextClues[$i % count($nextClues)];
                                        else
                                            $nextClues[$i] = 'ASIS';
                                    } //Ensure the clue is not an array
                                    else {
                                        if (gettype($nextClues[$i]) != 'string') {
                                            $error = 'Clue type must be a string, instead it is not, it\'s ';
                                            if (gettype($nextClues[$i] == 'array'))
                                                $error .= json_encode($error);
                                            else
                                                $error .= $nextClues[$i];
                                            throw new \Exception($error);
                                        }
                                    }
                                } //Add no clues if not a string
                                else
                                    $nextClues[$i] = '';

                                if ($type == 'function') {
                                    $res .= $this->expConstructor($exp[$i], $useContext, $useClues, $nextClues[$i]) . $connector;
                                } elseif ($type == 'connector') {
                                    $res .= $this->expConstructor($exp[$i], $useContext, $useClues, $nextClues[$i]) . $connector;
                                }
                            }
                            $res = substr($res, 0, -strlen($connector)) . ' ';

                            $res .= $suffix;
                    }
                    break;
            }
            return $res;
        }


        function selectFromTable(string $tableName, array $cond = [], array $columns = [], array $param = []): string {

            //Read $params
            isset($param['useBrackets'])?
                $useBrackets = $param['useBrackets'] : $useBrackets = false;
            isset($param['orderBy'])?
                $orderBy = $param['orderBy'] : $orderBy = null;
            isset($param['orderType'])?
                $orderType = $param['orderType'] : $orderType = 0;
            isset($param['limit'])?
                $limit = $param['limit'] : $limit = null;

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
                $query .=  $this->expConstructor($cond);
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

            if($useBrackets)
                $query .= ')';

            //We are just returning the query, this is where we stop
            return $query;
        }

        /** Deletes an object from the database table $tableName.
         * @param string $tableName Valid SQL table name.
         * @param array $cond 2D array of conditions, of the form [[<validColumnName>,<validVariable>,<comparisonOperator>]]
         *                   Example [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE']]
         *                   Valid comparison operators: '=', '<=>', '!=', '<=', '>=', '<', '>', 'LIKE', 'NOT LIKE', 'RLIKE', 'NOT RLIKE'
         * @param array $param Includes multiple possible parameters:
         *                      'orderBy' An array of column names to order by
         *                      'orderType' 1 descending, 0 ascending
         *                      'limit' If not null/0, will limit number of deleted rows.
         *                      'returnRows' If true, will return the number of affected rows on success.
         * @param bool $test indicates test mode
         * @returns int
         *      -2 server error
         *      -1 on illegal input
         *      0 success
         *      <Number of deleted rows> - on success, if $param['returnRows'] is set not to false.
         */
        function deleteFromTable(string $tableName, array $cond = [], array $param = [], bool $test = false): int|string {

            //Read $params
            isset($param['orderBy'])?
                $orderBy = $param['orderBy'] : $orderBy = null;
            isset($param['orderType'])?
                $orderType = $param['orderType'] : $orderType = 0;
            isset($param['limit'])?
                $limit = $param['limit'] : $limit = null;

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
                    $query .=  $this->expConstructor($cond);
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

            return $query;
        }


        /** Inserts an object into the database table $tableName.
         * @param string $tableName Valid SQL table name.
         * @param array $columns The columns you want, in a 1D array of the form ['col1','col2',..].
         * @param array $values An array of arrays of the form [[<col1Value>,<col2Value>,...],[<col1Value>,<col2Value>,...],...]
         *                      Value number MUST be the length of $columns array!
         * @param array $param Includes multiple possible parameters:
         *                      'onDuplicateKey' if true, will add ON DUPLICATE KEY UPDATE
         * @param bool $test indicates test mode
         * @returns int
         *      -1 on illegal input
         *      0 success
         *      array [<last_insert_id>,<ROW_COUNT()>] - on success, if $param['returnRows'] is set not to false.
         * @throws \Exception
         * @throws \Exception
         */
        function insertIntoTable(string $tableName, array $columns, array $values, array $param = [], bool $test = false): int|string {

            //columns
            $columnNames = ' ('.implode(',',$columns).')';

            //Prepare the query to be executed
            $query = 'INSERT INTO '.$tableName.$columnNames.' VALUES ';

            //Values
            $query .=  $this->expConstructor($values);

            //If we have on duplicate key..
            if($param['onDuplicateKey']){
                $query .= ' ON DUPLICATE KEY UPDATE ';
                foreach($columns as $colName){
                    $query .= $colName.'=VALUES('.$colName.'), ';
                }
                $query =  substr($query,0,-2).' ';
            }

            return $query;
        }


        /** Inserts an object into the database table $tableName.
         * @param mixed $tableTarget Valid SQL table name, OR an array of such names.
         * @param array $assignments A 1D array of STRINGS that constitute the assignments.
         *                           For example, if we $tableTarget was ['t1','t2'], $assignments might be:
         *                           ['t1.ID = t2.ID+5','t1.Name = CONCAT(t2.Name, "_clone")']
         *                           or just ['Name = "Anon"']
         * @param array $cond 2D array of conditions, of the form [[<validColumnName>,<validVariable>,<comparisonOperator>]]
         *                   Example [['ID',1,'>'],['ID',100,'<'],['Name','%Tom%','LIKE']]
         *                   Valid comparison operators: '=', '<=>', '!=', '<=', '>=', '<', '>', 'LIKE', 'NOT LIKE', 'RLIKE', 'NOT RLIKE'
         * @param array $param Includes multiple possible parameters:
         *                      'orderBy' An array of column names to order by
         *                      'orderType' 1 descending, 0 ascending
         *                      'limit' If not null/0, will limit number of deleted rows.
         *                      'returnRows' If true, will return the number of affected rows on success.
         * @param bool $test indicates test mode
         * @return int|string
         */
        function updateTable(mixed $tableTarget, array $assignments, array $cond = [], array $param = [], bool $test = false): int|string {

            //Read $params
            isset($param['orderBy'])?
                $orderBy = $param['orderBy'] : $orderBy = null;
            isset($param['orderType'])?
                $orderType = $param['orderType'] : $orderType = 0;
            isset($param['limit'])?
                $limit = $param['limit'] : $limit = null;

            //orderType - Note that without LIMIT, this is meaningless
            if($orderType == 0)
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
                    $query .=  $this->expConstructor($cond);
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

            return $query;
        }
    }
}