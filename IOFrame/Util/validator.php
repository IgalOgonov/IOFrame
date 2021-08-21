<?php

namespace IOFrame\Util;
define('validator',true);

/**Common validation operations - mainly for the APIs
 * @author Igal Ogonov <igal1333@hotmail.com>
 */
class validator
{
    /** @var string $tableName name of the table
     * @return bool
     * */
    public static function validateSQLTableName( string $tableName){
        if( preg_match('/\W/',$tableName) || preg_match('/^\d.+$/',$tableName) || strlen($tableName)==0  || strlen($tableName)>64 )
            return false;
        else
            return true;
    }

    /** @var string $key Valid sql key (for a key-value storage - basically a table name but with bigger length)
     * @return bool
     * */
    public static function validateSQLKey(string $key){
        if( preg_match_all('/\w|\ /',$key) < strlen($key) || strlen($key)>255 || strlen($key)==0 || preg_match('/^\d.+$/',$key) )
            return false;
        else
            return true;
    }

    /** @var string $password Password to validate - might define custom validation parameters later.
     * @return bool
     * */
    public static function validatePassword(string $password){
        if(strlen($password)>64||strlen($password)<8||preg_match('/(\s|<|>)/',$password)>0
            ||preg_match('/\d/',$password)==0||preg_match('/[A-Z]/',$password)==0){
            return false;
        }
        return true;
    }


    /** @var string $username Username to validate
     * @return bool
     * */
    public static function validateUsername(string $username){
        return preg_match('/^[a-zA-Z][a-zA-Z0-9]{5,15}/',$username);
    }

    /** @var string $dirPath Directory URL - without the file!
     * @return bool
     * */
    public static function validateRelativeDirectoryPath(string $dirPath){
        if(strlen($dirPath)>260 || !preg_match('/^([a-zA-Z0-9\ \_\-\@\#\$\%]+\/{0,1})*$/',$dirPath)){
            return false;
        }
        return true;
    }

    /** @var string $fileName Filename - without the directory!
     * @var string[] $extensions - array, default [] - allowed extensions
     * @return bool
     * */
    public static function validateFilename(string $fileName, $extensions = []){
        if($extensions == [])
            $extensionRegex = '[a-zA-Z0-9_\-]{1,259}';
        else{
            $extensionRegex = '(';
            foreach($extensions as $extension)
                $extensionRegex .= $extension.'|';
            $extensionRegex = substr($extensionRegex,0,-1);
            $extensionRegex .= ')';
        }

        if(strlen($fileName)>260 || !preg_match('/^([a-zA-Z0-9\ \_\-\@\#\$\%]{0,259}\.'.$extensionRegex.')*$/',$fileName)){
            return false;
        }
        return true;
    }

    /** @var string $fullPath Directory path plus a potential file in the end.
     * @var string[] $extensions - array, default [] - allowed extensions
     * @return bool
     * */
    public static function validateRelativeFilePath(string $fullPath, $extensions = []){

        $pathArray = explode('/',$fullPath);
        $lastElement = array_pop($pathArray);

        //IF the last element in the path is not a file, everything is just one big directory path
        if(strpos($lastElement,'.') === false)
            return validator::validateRelativeDirectoryPath($fullPath);

        //The combined length must not be over 260
        if(strlen($fullPath)>260)
            return false;

        //If those two passed, test the regex of both parts
        return  validator::validateFilename($lastElement, $extensions) &&
                validator::validateRelativeDirectoryPath(implode('/',$pathArray));
    }


    //

}