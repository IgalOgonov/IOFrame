<?php
namespace IOFrame\Util{
    define('helperFunctions',true);
    /**
     The purpose of this is to allow pages to be moved freely and still be able to perform needed actions on php files on the
     server who's location is already defined.
     * @param string $currAdr needs to be the callers $_SERVER['REQUEST_URI']
     * @param string $pathToRoot needs to be the setting 'pathToRoot'
     * @returns string '../' times the number of folders needed to go from given folder to reach server root.
     */
    function htmlDirDist(string $currAdr, string $pathToRoot){
        $res='';
        $rootCount=0;
        $count=0;
        $currAdr = preg_replace('/(\/)+/', '/', $currAdr);
        for($i=0; $i<strlen($pathToRoot); $i++){
            if($pathToRoot[$i]=='/') $rootCount++;
        }
        for($i=0; $i<strlen($currAdr); $i++){
            if($currAdr[$i]=='/') $count++;
        }
        for($i=0; $i<($count-$rootCount); $i++){
            $res.= '../';
        }
        return $res;
    }

    /**Dies if the user isn't logged in*/
    function assertLogin(){
        if(!isset($_SESSION['logged_in'],$_SESSION['details']))
            return false;
        elseif($_SESSION['logged_in']!=true)
            return false;
        return true;
    }

    /**Ensures a string is a JSON*/
    function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }


    /** A small function just to remove any GET variables from a url.
     * @param string $ref URL
     * @returns string */
    function cleanGet(string $ref){
        $res=$ref;
        $pos=stripos($ref,'?');
        if($pos){
            $res = substr( $res, 0, ($pos) );
        }
        return $res;
    };

    /** Works ONLY if THIS FUNCTION is in a predefined folder - util.
    * @returns string absolute server root path */
    function getAbsPath(){
        $path=__DIR__;
        $length = strlen($path);
        $path = substr($path,0,$length-strlen('IOFrame/Util'));
        for($i=0; $i<strlen($path); $i++){
            if($path[$i]=='\\')
                $path[$i]='/';
        }
        return $path;
    }


    /**Replaces ALL $toReplace substrings with $replacement in $string.
     * @param string $toReplace Substring to replace
     * @param string $replacement What to replace that substring with
     * @param string $string URL original string
     * @returns string String with the substring replaced
     */
    function replaceInString(string $toReplace, string $replacement, string $string){

        while(strrpos($string,$toReplace)){
            $remove = strrpos($string,$toReplace);
            $string=substr($string,0,$remove).$replacement.substr($string,$remove+strlen($toReplace));
        }

        return $string;
    }

    /**Generates pseudo-random string of highcase characters and digits of the specified length.
     * @param int $qtd length
     * @param string $Caracteres usable characters
     * @returns string  "Random" character string
    */
    function GeraHash($qtd, $Caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMOPQRSTUVXWYZ0123456789'){
        $QuantidadeCaracteres = strlen($Caracteres);
        $QuantidadeCaracteres--;
        $Hash=NULL;
        for($x=1;$x<=$qtd;$x++){
            $Posicao = rand(0,$QuantidadeCaracteres);
            $Hash .= substr($Caracteres,$Posicao,1);
        }
        return $Hash;
    }

    /** Returns a new PDO database connection to use, from the relevant $settings
     * @param \IOFrame\Handlers\SettingsHandler $sqlSettings IOFrame setting handler
     * @returns \PDO New database connection
    */
    function prepareCon(\IOFrame\Handlers\SettingsHandler $sqlSettings){
        return new \PDO(
            "mysql:host=".$sqlSettings->getSetting('sql_server_addr').
            ";dbname=".$sqlSettings->getSetting('sql_db_name').
            ";port=".$sqlSettings->getSetting('sql_server_port'),
            $sqlSettings->getSetting('sql_username'),
            $sqlSettings->getSetting('sql_password'));
    }


    /** Checks whether a string is a JSON string - oen that decodes into an object/array!
     * @param string $str
     * @returns boolean
    */
    function is_json($str){
        return ( gettype($str) === 'string' ) && is_array( json_decode($str,true));
    }

    /** Use instead of base_convert for large strings
     * @param string $str string to convert
     * @param int $frombase
     * @param int $tobase
     *
     * @returns string converted string
     *
     * @author clifford.ct@gmail.com
    */
    function str_baseconvert($str, $frombase=10, $tobase=36) {
        $str = trim($str);
        if (intval($frombase) != 10) {
            $len = strlen($str);
            $q = 0;
            for ($i=0; $i<$len; $i++) {
                $r = base_convert($str[$i], $frombase, 10);
                $q = bcadd(bcmul($q, $frombase), $r);
            }
        }
        else $q = $str;

        if (intval($tobase) != 10) {
            $s = '';
            while (bccomp($q, '0', 0) > 0) {
                $r = intval(bcmod($q, $tobase));
                $s = base_convert($r, 10, $tobase) . $s;
                $q = bcdiv($q, $tobase, 0);
            }
        }
        else $s = $q;

        return $s;
    }


    /** Combines each consecutive character of 2 strings, into 1 string.
     * For example, "abc" and "def" will combine into "adbecf"
     * @param string $string1  eg "abc"
     * @param string $string2  eg "def"
     * @returns string eg "adbecf"
    */
    function stringScrumble(string $string1, string $string2){
        if(strlen($string1)!=strlen($string2))
            return false;
        $str1 = str_split($string1);
        $str2 = str_split($string2);
        $res = '';
        $ctr = 0;
        foreach($str1 as $char){
            $res.=$str1[$ctr];
            $res.=$str2[$ctr];
            $ctr++;
        }
        return $res;
    }

    /** The inverse function of stringScrumble. Will get 1 string, and return an array containing the initial 2 strings.
    *  Obviously, provided string needs to be of even length.
    * @param string $string  eg "adbecf"
    * @returns bool|string[] eg ["abc","def"], or false if input string length was odd
    */
    function stringDescrumble(string $string){
        if(strlen($string)%2 !=0)
            return false;
        $string = str_split($string);
        $res = ['',''];
        $ctr =0;
        foreach($string as $char){
            if($ctr == 0){
                $ctr=1;
                $res[0].=$char;
            }
            else{
                $ctr=0;
                $res[1].=$char;
            }
        }
        return $res;
    }

    /** NATIVE IMPLEMENTATION - hash_equals
     * Compares two strings, and returns the result after a specific number of static or dynamic time periods.
     * It will calculate a delta time according to the mode, and return a result after a specific time based on that delta,
     * typically total processing time, plus that time modulo delta, rounded up to delta.
     * Used to prevent timing attacks when comparing strings containing secret data.
     * Mode 0: Delta is equal to $waitMS (1 by default) millisecond per character of each string.
     * Mode 1: Delta is $waitMS
     * --TODO Mode 2: Like mode 0, but delta is multiplied by the time it takes the server to compare "a" to "b", rounded up, in milliseconds.
     * */
    function safe_strcmp(string $str1, string $str2, int $mode=0, int $waitMS = 1){

        if(strlen($str1)!=strlen($str2))
            return false;

        //Setting the delta, case by case. Could be written shorter, but written like this for readability.
        $delta = 0;
        switch($mode){
            case 0:
                $delta = $waitMS*strlen($str1);
                break;
            case 1:
                $delta = $waitMS;
                break;
            default:
                return false;
        }

        //Measure when the string comparison started, and ended
        $startTime = explode(" ", microtime())[1];
        $res = strcmp($str1,$str2);
        $endTime = explode(" ", microtime())[1];

        //Maybe it started in one second and ended in a different one.
        if($endTime<$startTime)
            $endTime+=1000;

        //Calculate how much we need to wait, and wait.
        $processingTime =  ($endTime - $startTime);
        $timeToWait =$delta - ($processingTime % $delta);
        usleep($timeToWait);

        return ($res==0)?true:false;
    }

    /**
     * A function that allows to include all files in a single folder.
     * @param string $folder folder in which to include/require files
     * @param bool $require is false for "include", true for "require".
     * @param string[] $exclude is an array of regex expressions to exclude in the file path.
     */
    function include_all_php(string $folder, bool $require = false, array $exclude = []){
        foreach (glob("{$folder}*.php") as $filename){
            $toInclude = true;
            //could be slightly optimized but meh
            foreach($exclude as $val)
                if(preg_match('/'.$val.'/',$filename)!=0)
                    $toInclude = false;
            if($toInclude){
                if($require)
                    require_once $filename;
                else
                    include_once $filename;

            }
        }
    }

    //Recursive folder copying in PHP - simple function
    function folder_copy($src,$dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    folder_copy($src . '/' . $file,$dst . '/' . $file);
                }
                else {
                    copy($src . '/' . $file,$dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    //Recursive folder deletion in PHP - simple function
    function folder_delete($dirPath) {
        if (! is_dir($dirPath)) {
            throw new \InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                folder_delete($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }

    //Creates directories in file_put_contents
    function file_force_contents( $fullPath, $contents, $flags = 0 ){
        $parts = explode( '/', $fullPath );
        array_pop( $parts );
        $dir = implode( '/', $parts );

        if( !is_dir( $dir ) )
            mkdir( $dir, 0777, true );

        file_put_contents( $fullPath, $contents, $flags );
    }

    //Creates directories in rename
    function force_rename($oldname, $newname, $context = null){

        $parts = explode( '/', $newname );
        array_pop( $parts );
        $dir = implode( '/', $parts );
        if( !is_dir( $dir ) )
            mkdir( $dir, 0777, true );

        if($context !== null)
            rename( $oldname, $newname, $context );
        else
            rename( $oldname, $newname );
    }

    /**
     * array_merge_recursive does indeed merge arrays, but it converts values with duplicate
     * keys to arrays rather than overwriting the value in the first array with the duplicate
     * value in the second array, as array_merge does. I.e., with array_merge_recursive,
     * this happens (documented behavior):
     *
     * array_merge_recursive(array('key' => 'org value'), array('key' => 'new value'));
     *     => array('key' => array('org value', 'new value'));
     *
     * array_merge_recursive_distinct does not change the datatypes of the values in the arrays.
     * Matching keys' values in the second array overwrite those in the first array, as is the
     * case with array_merge, i.e.:
     *
     * array_merge_recursive_distinct(array('key' => 'org value'), array('key' => 'new value'));
     *     => array('key' => array('new value'));
     *
     * Parameters are passed by reference, though only for performance reasons. They're not
     * altered by this function.
     *
     * @param array $array1
     * @param array $array2
     * @param array $params of the form:
     *          [
     *              'deleteOnNull' - bool, default false - will delete values instead of overwriting them if the new value is null
     *          ]
     * @return array
     * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
     * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
     * @author Igal Ogonov <igal1333 (at) hotmail (dot) com>
     */
    function array_merge_recursive_distinct ( array $array1, array $array2, array $params = [] )
    {
        $deleteOnNull = isset($params['deleteOnNull'])? $params['deleteOnNull'] : false;
        $merged = $array1;

        //If one of the arrays is null, and we are deletin on null, it means it might has been deleted
        if($deleteOnNull && ($array1 === null || $array2 === null) )
            return ($array1 === null)? $array2 : $array1;

        //Merge every element from array 2 into array 1
        foreach ( $array2 as $key => &$value )
        {
            if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) )
            {
                $merged [$key] = array_merge_recursive_distinct ( $merged [$key], $value, $params );
                //If we merged with an array full of nulls, delete the result
                if($merged[$key] === null)
                    unset($merged [$key]);
            }
            else
            {
                //Merge
                if(!$deleteOnNull || $value !== null)
                    $merged [$key] = $value;
                //Delete on null
                elseif(array_key_exists($key,$merged))
                    unset($merged [$key]);
            }
        }

        if($deleteOnNull && $merged == [])
            $merged = null;

        return $merged;
    }

    /**
     * Ensures the given current debug mode is at least the specified level
     * @param $level int Ensures the current debug setting is at least above given value.
     * @returns boolean True if the debug setting is at least equal to $level
     * */
    function log_level_at_least(int $level, \IOFrame\Handlers\SettingsHandler $siteSettings){
        $expectedLevel= $siteSettings->getSetting('logStatus');
        switch($level){
            //Notice no breaks
            case 0:
                return true;
            case 1:
                if($expectedLevel == LOG_MODE_1);
                    return true;
            case 2:
                if($expectedLevel == LOG_MODE_2);
                    return true;
            case 3:
                if($expectedLevel == LOG_MODE_3);
                    return true;
            case 4:
                if($expectedLevel == LOG_MODE_4);
                    return true;
            default:
                return false;
        }
    }

    /** Parses raw HTTP PUT request
     * @param array[] $a_data
     * @param array[] $serverParams
     * @link https://stackoverflow.com/questions/5483851/manually-parse-raw-multipart-form-data-data-with-php
     */
    function parse_raw_http_request(array &$aData, array $serverParams)
    {
        if(empty($serverParams['CONTENT_TYPE']))
            return;

        // read incoming data
        $input = file_get_contents('php://input');

        // grab multipart boundary from content type header
        preg_match('/boundary=(.*)$/', $serverParams['CONTENT_TYPE'], $matches);
        $boundary = $matches[1];

        // split content by boundary and get rid of last -- element
        $a_blocks = preg_split("/-+$boundary/", $input);
        array_pop($a_blocks);

        // loop data blocks
        foreach ($a_blocks as $id => $block)
        {
            if (empty($block))
                continue;

            // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char

            // parse uploaded files
            if (strpos($block, 'application/octet-stream') !== FALSE)
            {
                // match "name", then everything after "stream" (optional) except for prepending newlines
                preg_match("/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
            }
            // parse all other fields
            else
            {
                // match "name" and optional value in between newline sequences
                preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
            }
            if(isset($matches[2]))
                $aData[$matches[1]] = $matches[2];
        }
    }
}