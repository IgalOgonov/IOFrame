<?php

$requiredAuth = REQUIRED_AUTH_OWNER;

$deletionParams = ['test' =>$test];

if(!$auth->isLoggedIn()){
    if($test)
        echo 'Must be logged in to swap article blocks!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}

if($inputs['articleId'] === null){
    if($test)
        echo 'articleId must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
if(!filter_var($inputs['articleId'],FILTER_VALIDATE_INT)){
    if($test)
        echo 'articleId needs to be a valid int!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}