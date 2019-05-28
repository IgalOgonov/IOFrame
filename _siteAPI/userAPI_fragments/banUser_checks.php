<?php


if(!filter_var($inputs['id'],FILTER_VALIDATE_INT)){
    if($test)
        echo 'id must be a number!'.EOL;
    exit('-1');
}

if(!filter_var($inputs['minutes'],FILTER_VALIDATE_INT)){
    if($test)
        echo 'minutes must be a number!'.EOL;
    exit('-1');
}

if(!$auth->isLoggedIn()){
    if($test)
        echo 'Cannot ban users if you\'re not even logged in!'.EOL;
    exit('-2');
}

if( !( $auth->isAuthorized(0) || $auth->hasAction('BAN_USERS_AUTH') ) ){
    if($test)
        echo 'Insufficient auth to ban users!'.EOL;
    exit('-2');
}

$targetRank = $sqlHandler->selectFromTable($sqlHandler->getSQLPrefix().'USERS',['ID',$inputs['id'],'='],['Auth_Rank'],['test'=>$test])[0][0];

//God I hate that this check is done here again
if($targetRank === null){
    if($test)
        echo 'Target user does not exist!'.EOL;
    exit('1');
}

$sesInfo = json_decode($_SESSION['details'],true);

if($sesInfo['Auth_Rank']>=$targetRank){
    if($test)
        echo 'Can only ban users of higher (worse) rank than yourself!'.EOL;
    exit('-2');
}




