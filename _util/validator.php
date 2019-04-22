<?php

namespace IOFrame;


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

    public static function validatePassword($password){
        if(strlen($password)>64||strlen($password)<8||preg_match_all('/(\s|<|>)/',$password)>0
                ||preg_match_all('/\d/',$password)==0||preg_match_all('/[a-z]|[A-Z]/',$password)==0){
                return false;
            }
        return true;
    }

}