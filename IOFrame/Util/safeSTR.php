<?php

namespace IOFrame\Util{
    define('safeSTR',true);
    /**
     * Returns an array given an hArray
     * @param string|hArray $hArr hArray to convert
     * @returns string[]|bool Actual array, or false on bad input
     */
    function hArray2Array($hArr){
        if(gettype($hArr) == 'string'){
            $res = new hArray($hArr);
            return $res->toArray();
        }
        else if (gettype($hArr) == 'object'){
            if($hArr instanceof hArray)
                return $hArr->toArray();
            else
                return false;
        }
        else
            return false;
    }

    /**
     * Returns an hArray given an array
     * @param array $arr hArray to convert
     * @returns hArray|bool Newly created hArray, or false on bad input
     */
    function array2hArray(array $arr){
        if(gettype($arr) == 'array'){
            $res = new hArray($arr);
            return $res;
        }
        else
            return false;
    }

    /**
     * Returns a normal string given a Safe String
     * Anywhere within an ascii value there can be an expression of the tpye "x<number>x" that means "repeat ascii value <number> times".
     * @param string $str Normal string
     * @returns string safeString
     */
    function safeStr2Str(string $str){
        $curVal = '';
        $tempVal = '';
        $repeat = '1';
        $inAscii = false;
        $inCount = false;
        for($i=0; $i<strlen($str); $i++){
            if($str[$i]=='%'){
                if(!$inAscii){
                    $inAscii = true;
                }
                else{
                    for($j = 0; $j<$repeat; $j++)
                        $curVal .= chr( intval($tempVal) );
                    $tempVal ='';
                    $repeat = '1';
                    $inAscii = false;
                }
            }
            else{
                if(!$inAscii){
                    $curVal .= $str[$i];
                }
                else{
                    if (preg_match('/[0-9]|x/', $str[$i]) != 1){
                        echo 'String conversion failed - ascii value should be 0-9, x, contains letters.'.EOL;
                        echo 'Conversion so far:.'.$curVal.EOL;
                        return false;
                    }
                    if($str[$i] === 'x'){
                        if($inCount === false && $repeat !=='1'){
                            echo 'Multiple "x" characters inside ascii value - error';
                            return false;
                        }
                        if($inCount === false)
                            $repeat = '';
                        $inCount = !$inCount;
                    }
                    else{
                        if(!$inCount){
                            $tempVal .= $str[$i];
                        }
                        else if($str[$i] !== 'x'){
                            $repeat .= $str[$i];
                        }
                    }
                }
            }
        }
        return $curVal;
    }


    /**
     * Returns a Safe String given a normal string
     * @param string $str safeString
     * @returns string Normal string
     */
    function str2SafeStr($str){
        $res=$str;
        $repeats = 1;
        for($j=0; $j<strlen($res); $j++) {
            if (preg_match('/\W/', $res[$j]) != 0) {
                $temp = ord($res[$j]);
                $tempLoc = $j;
                $currentLength = strlen($res);
                while($j<$currentLength-1 && $res[$j] === $res[$j+1]){
                    $repeats++;
                    $j++;
                }
                if($repeats === 1){
                    $res = substr($res, 0, $j) . '%' .$temp . '%' . substr($res, $j + 1);
                    $j+=strlen($temp)+2-1;
                }
                else{
                    $res = substr($res, 0, $tempLoc) . '%x'.$repeats.'x' .$temp . '%' . substr($res, $tempLoc + $repeats);
                    $repeats = 1;
                    $j = $tempLoc + strlen($temp)+4;
                }
            }
        }
        return $res;
    }

}

?>