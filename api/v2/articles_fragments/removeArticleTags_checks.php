<?php
$requiredAuth = REQUIRED_AUTH_OWNER;

$setParams = [];

$setParams['test'] = $test;

if(!$auth->isLoggedIn()){
    \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE,'message'=>'Must be logged in to remove article tags!'],!$test);
}