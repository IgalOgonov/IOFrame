<?php
//Auth check
if(!$auth->isAuthorized() && !$auth->hasAction(AUTH_MODIFY_RANK)){
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
    $identityCond = [$SQLManager->getSQLPrefix().'USERS.ID',$params['identifier'],'='];
}
else{
    $identityCond = [$SQLManager->getSQLPrefix().'USERS.Email',[$params['identifier'],'STRING'],'='];
}

$targetUser = $SQLManager->selectFromTable(
    $SQLManager->getSQLPrefix().'USERS',
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

