<?php
//Auth check
if(!$auth->isAuthorized(0) && !$auth->hasAction(AUTH_MODIFY_RANK)){
    if($test)
        echo 'Must have rank 0, or relevant action!';
    exit(AUTHENTICATION_FAILURE);
}

if($params['newRank'] < $auth->getRank()){
    if($test)
        echo 'You cannot set somebodys rank to be lower than your own!';
    exit(AUTHENTICATION_FAILURE);
}

if(gettype($params['identifier']) == 'integer'){
    $identityCond = [$SQLHandler->getSQLPrefix().'USERS.ID',$params['identifier'],'='];
}
else{
    $identityCond = [$SQLHandler->getSQLPrefix().'USERS.Email',[$params['identifier'],'STRING'],'='];
}

$targetUser = $SQLHandler->selectFromTable(
    $SQLHandler->getSQLPrefix().'USERS',
    $identityCond,
    ['Auth_Rank'],
    ['test'=>$test]
);

if(!is_array($targetUser) || count($targetUser) == 0 ){
    if($test)
        echo 'Target user does not exist!';
    exit('0');
}

$targetRank = $targetUser[0]['Auth_Rank'];

if($targetRank <= $auth->getRank()){
    if($test)
        echo 'Target user is lower or equal rank to you!';
    exit(AUTHENTICATION_FAILURE);
}

