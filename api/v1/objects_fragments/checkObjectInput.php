<?php


// Checks that the input is of legal syntax, if it's legal for the users ranks, and if it's legal to change the main owner
function checkObjectInput($obj = null ,$group = '' ,$minViewRank = null ,$minModifyRank = null ,$sesInfo = null,
                      $mainOwner = null, $newOwners = null, $remOwners = null, $siteSettings, $test){
    $res = true;
    if($sesInfo == null){
        $sesInfo = isset($_SESSION['details'])? json_decode($_SESSION['details'],true) : null;
    }
    //If session info is still null, the user is probably not logged in
    if($sesInfo == null)
        return false;

    //Fisrt, check if the object isn't longer than the maximum size allowed by the database.
    if($obj!= null)
        if(strlen($obj) > $siteSettings->getSetting('maxObjectSize') || strlen($obj)==0){
            if($test)
                echo 'Object too large to be stored, or empty. maximum size is '. $siteSettings->getSetting('maxObjectSize').'. ';
            return false;
        }

    //Next, check that the group name contains only numbers and letters - and first character isn't a number
    if($group != '')
        if(!\IOFrame\Util\validator::validateSQLKey($group)){
            if($test)
                echo 'Illegal group name. ';
            return false;
        }

    //check if the ranks are numbers within legal range, and not above the creator's rank
    if($minViewRank!= null)
        if(
            (gettype($minViewRank) == 'string' && preg_match_all('/[0-9]|\-/',$minViewRank)<strlen($minViewRank) )
            || strlen($minViewRank) == 0
            || $minViewRank<-1
            || $minViewRank>IOFrame\MAX_USER_RANK
            || (($sesInfo['Auth_Rank']>$minViewRank) && $minViewRank!=-1)
        ){
            if($test)
                echo 'Object view rank needs to be numbers within legal range, equal or higher (number) than the creator\'s rank. ';
            return false;
        }
    if($minModifyRank!= null)
        if(
            (gettype($minViewRank) == 'string' && preg_match_all('/[0-9]/',$minModifyRank)<strlen($minModifyRank)) ||
            strlen($minModifyRank) == 0 ||
            $minModifyRank<0 ||
            $minModifyRank>IOFrame\MAX_USER_RANK ||
            (($sesInfo['Auth_Rank']>$minModifyRank) && $minModifyRank!=-1)
        ){
            if($test)
                echo 'Object modify rank needs to be numbers within legal range, equal or higher (number) than the creator\'s rank. ';
            return false;
        }

    //Check that the user transfering ownership is actually the old owner
    if($mainOwner!= null){
        if(preg_match_all('/\D/',$mainOwner)>0 || strlen($mainOwner) == 0){
            if($test)
                echo 'Object owner id must be a number. ';
            return false;
        }
    }

    return $res;
}