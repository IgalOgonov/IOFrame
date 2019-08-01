<?php


namespace IOFrame\Util{
    define('hArray',true);
    if(!defined('safeSTR'))
        require 'safeSTR.php';

    /**An hArray is a string representing an 1D Array (a stack, actually). The characters of the string are latters and numbers.
     *Any character that isn't a digit or low/highcase later is converted to %<char ascii value>%.
     *A string created in the method above is a Safe String
     * @author Igal Ogonov <igal1333@hotmail.com>*/

    class hArray{
        /**@param string $hArr The string representation of an hArray
         */
        protected $hArr ="";

        /**Constructor
         *Gets a string or an array and creates an hArray based on them.
         *Note that you can get strings of invalid format like "abc%%#" or "abc%f45%#" or "345sas" that will resault in failure.
         *There is a bit of validation, but this assumes the string was constructed in a proper way and is a valid hArray.
         *
         * @param mixed inp - input
         */
        function __construct($inp='') {
            if(gettype($inp) == "string" || $inp == ''){
                if(preg_match_all('/[a-z]|[A-Z]|[0-9]|#|%/',$inp) == strlen($inp) && preg_match('/%%/',$inp) == 0){
                    $this->hArr = $inp;
                    return true;
                }
                else{
                    $this->hArr = str2SafeStr($inp);
                    return true;
                }
            }
            else if(gettype($inp) == "array"){
                $res = $inp;
                $count = count($res);
                for($i=0; $i<$count; $i++){
                    for($j=0; $j<strlen($res[$i]); $j++) {
                        if (preg_match('/\W/', $res[$i][$j]) != 0) {
                            $temp = ord($res[$i][$j]);
                            $res[$i] = substr($res[$i], 0, $j) . '%' . $temp . '%' . substr($res[$i], $j + 1);
                            $j+=strlen($temp.'aa');
                        }
                    }
                }
                $this->hArr = implode("#",$res).'#';
                return true;
            }
            else{
                throw new \Exception('Wrong parameters - cannot create hArray from this!');
            }
        }

        /**
         * Sets stored hArr value to something new - be careful when using this, you must st it to be a valid hArr string!
         * @param mixed $inp a string or an array and creates an hArray based on them.
         *
         * @return bool
         */
        function hArrSet($inp){
            if(gettype($inp) == "string"){
                $this->hArr = $inp;
                return true;
            }
            else if(gettype($inp) == "array"){
                $this->hArr = array2hArray($inp)->hArrGet();
                return true;
            }
            else return false;

        }

        /**@return string current hArr value*/
        function hArrGet(){
            return $this->hArr;
        }

        /**Pushes an element into the h-array. Automatically converts it to safestring
         * @param string $inp*/
        function hArrPush(string $inp){
            $this->hArr .= str2SafeStr($inp).'#';
        }

        /**@returns array An array object that hArr represents
         */
        function toArray(){
            $res = [];
            $hArr = $this->hArr;
            $curVal = '';
            $tempVal = '';
            $inAscii = false;
            for($i=0; $i<strlen($hArr); $i++){
                if($hArr[$i]=='#'){
                    if($inAscii)
                        return false;
                    if($curVal == '')
                        array_push($res, '');
                    else
                        array_push($res, $curVal);
                    $curVal ='';
                }
                else if($hArr[$i]=='%'){
                    if(!$inAscii){
                        $inAscii = true;
                    }
                    else{
                        $curVal .= chr( intval($tempVal) );
                        $tempVal ='';
                        $inAscii = false;
                    }
                }
                else{
                    if(!$inAscii){
                        $curVal .= $hArr[$i];
                    }
                    else{
                        if (preg_match('/[0-9]/', $hArr[$i]) != 1){
                            echo 'Array convertion failed - ascii value int contains letters.';
                            return false;
                        }
                        $tempVal .= $hArr[$i];
                    }
                }
            }
            return $res;
        }

    }
}


?>