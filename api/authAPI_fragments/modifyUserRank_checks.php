<?php

//Input checks

if($params == null){
    if($test)
        echo 'Params must be set!';
    exit('-1');
}

$expectedParams = ['identifier','newRank'];

foreach($expectedParams as $expectedParam){

    if( !isset($params[$expectedParam]) ){
        if($test)
            echo 'Parameter '.$expectedParam.' must be set!';
        exit('-1');
    }

    switch($expectedParam){
        case 'identifier':
            if(!filter_var($params[$expectedParam],FILTER_VALIDATE_INT) && !filter_var($params[$expectedParam],FILTER_VALIDATE_EMAIL) ){
                if($test)
                    echo 'identifier must be a number or a valid email!'.EOL;
                exit('-1');
            }
            if(preg_match('/\D/',$params[$expectedParam]) == 0)
                $params[$expectedParam] = (int)$params[$expectedParam];
            break;
        case 'newRank':
            if(!filter_var($params[$expectedParam],FILTER_VALIDATE_INT)){
                if($test)
                    echo 'newRank must be a number!'.EOL;
                exit('-1');
            }
            if($params[$expectedParam]<0){
                if($test)
                    echo 'newRank must be positive or 0!'.EOL;
                exit('-1');
            }

            if($params[$expectedParam] < $auth->getRank()){
                if($test)
                    echo 'You cannot set somebodys rank to be lower than your own!';
                exit('-2');
            }
            break;
    }

}


//Auth check TODO Add relevant actions, not just rank 0

if(!$auth->isAuthorized(0)){
    if($test)
        echo 'Authorization rank must be 0!';
    exit('-2');
}

if(gettype($params['identifier']) == 'integer'){
    $identityCond = [$sqlHandler->getSQLPrefix().'USERS.ID',$params['identifier'],'='];
}
else{
    $identityCond = [$sqlHandler->getSQLPrefix().'USERS.Email',[$params['identifier'],'STRING'],'='];
}

$targetUser = $sqlHandler->selectFromTable(
    $sqlHandler->getSQLPrefix().'USERS',
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
    exit('-2');
}

