<?php
namespace IOFrame\Util{

    define('IOFrameUtilPureUtilFunctions',true);

    class PureUtilFunctions{

        /** Generates pseudo-random string of highcase characters and digits of the specified length.
         * @param int $qtd length
         * @param string $Caracteres usable characters
         * @returns string  "Random" character string
         */
        public static function GeraHash(int $qtd, string $Caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMOPQRSTUVXWYZ0123456789'): ?string {
            $QuantidadeCaracteres = strlen($Caracteres)-1;
            $Hash=NULL;
            for($x=1;$x<=$qtd;$x++){
                $pos = rand(0,$QuantidadeCaracteres);
                $Hash .= substr($Caracteres,$pos,1);
            }
            return $Hash;
        }


        /** Checks whether a string is a JSON string - oen that decodes into an object/array!
         * @param string|mixed $str
         * @returns boolean
         */
        public static function is_json(mixed $str): bool {
            return ( gettype($str) === 'string' ) && is_array( json_decode($str,true));
        }

        //Check if an array is sequential or not
        public static function array_has_string_keys(array $array): bool {
            return count(array_filter(array_keys($array), 'is_string')) > 0;
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
         * @param array|null $array1
         * @param array|null $array2
         * @param array $params of the form:
         *          [
         *              'deleteOnNull' - bool, default false - will delete values instead of overwriting them if the new value is null
         *          ]
         * @return array|null
         * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
         * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
         * @author Igal Ogonov <igal1333 (at) hotmail (dot) com>
         */
        public static function array_merge_recursive_distinct( array|null $array1, array|null $array2, array $params = [] ): ?array {
            $deleteOnNull = $params['deleteOnNull'] ?? false;

            //If one of the arrays is null, and we are deleting on null, it means it might has been deleted
            if($deleteOnNull && ($array1 === null || $array2 === null) )
                return ($array1 === null)? $array2 : $array1;

            $array1 = $array1??[];
            $array2 = $array2??[];

            $merged = $array1;
            //Merge every element from array 2 into array 1
            foreach ( $array2 as $key => $value )
            {
                if (is_array ( $value ) && self::array_has_string_keys($value) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) )
                {
                    $merged [$key] = self::array_merge_recursive_distinct ( $merged [$key], $value, $params );
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

        /** Creates a path in an object if it didn't exist.
         * Each value until the last is an empty array, the last value can be something different
         * @param array $object Object we are modifying
         * @param string[] $path Path we are creating inside object
         * @param mixed $finalValue Final value in newly creacted path
         * @param bool $asArray Will treat final value as an item in an array, and push it
         * */
        public static function createPathInObject(array &$object, array $path, mixed $finalValue = [], bool $asArray = false): void {
            $nextKey = array_shift($path);
            if(count($path)){
                $object[$nextKey] = $object[$nextKey] ??  [];
                self::createPathInObject($object[$nextKey],$path,$finalValue,$asArray);
            }
            else{
                if(!$asArray)
                    $object[$nextKey] = $finalValue;
                else{
                    $object[$nextKey] = $object[$nextKey] ??  [];
                    $object[$nextKey][] = $finalValue;
                }
            }
        }

        /** Simple public static function that iterates over an object, and sets defaults in an input object, if they don't exist already.
         * For more complex stuff, v1APIManager::baseValidation() is advised.
         * @param $inputs
         * @param $defaults
         */
        public static function setDefaults(&$inputs, $defaults): void {
            foreach ($defaults as $key => $default)
                $inputs[$key] = $inputs[$key] ?? $default;
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
        public static function str_baseconvert(string $str, int $frombase=10, int $tobase=36): int|string {
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
                    $q = bcdiv($q, $tobase);
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
        public static function stringScrumble(string $string1, string $string2): bool|string {
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

        /** The inverse public static function of stringScrumble. Will get 1 string, and return an array containing the initial 2 strings.
         *  Obviously, provided string needs to be of even length.
         * @param string $string  eg "adbecf"
         * @returns bool|string[] eg ["abc","def"], or false if input string length was odd
         */
        public static function stringDescrumble(string $string): array|bool {
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

        /** Checks if a value matches any of the regex expressions (without opening/closing '/')
         * @param string $str String to match
         * @param string|array $regex array|string[] of regex expressions
         * @param bool $include Whether we need to match at least one of the expressions, or not match any of them
         * @return bool
         */
        public static function matchesRegex(string $str, string|array $regex, bool $include = true): bool {
            if(!is_array($regex))
                $regex = [$regex];
            /* Exclude - true on start, false on match. Include - false on start, true on match*/
            $passedChecks = !$include;
            foreach ($regex as $exp){
                if(preg_match('/'.$exp.'/',$str))
                    $passedChecks = $include;
            }
            return $passedChecks;
        }

    }

}