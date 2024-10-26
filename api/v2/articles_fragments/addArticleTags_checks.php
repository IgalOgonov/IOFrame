<?php

$requiredAuth = REQUIRED_AUTH_OWNER;

$setParams = [];

$setParams['test'] = $test;

if(!$auth->isLoggedIn()){
    \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>API_DISABLED,'message'=>'Must be logged in to set article tags!'],!$test);
}