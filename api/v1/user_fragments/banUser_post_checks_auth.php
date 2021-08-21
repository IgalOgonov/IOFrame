<?php

$targetRank = $SQLHandler->selectFromTable($SQLHandler->getSQLPrefix().'USERS',['ID',$inputs['id'],'='],['Auth_Rank'],['test'=>$test])[0][0];

//God I hate that this check is done here again
if(!is_array($targetRank) || count($targetRank) == 0){
    if($test)
        echo 'Target user does not exist!'.EOL;
    exit('1');
}

$sesInfo = json_decode($_SESSION['details'],true);

if($sesInfo['Auth_Rank']>=$targetRank){
    if($test)
        echo 'Can only ban users of higher (worse) rank than yourself!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}




