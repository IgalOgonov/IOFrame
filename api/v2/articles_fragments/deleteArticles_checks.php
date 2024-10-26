<?php
$retrieveParams = [
    'test'=>$test
];

$requiredAuth = REQUIRED_AUTH_OWNER;

if($inputs['permanent']??false)
    $requiredAuth = REQUIRED_AUTH_ADMIN;

$inputs['ids'] = $APIAction === 'delete/articles/{id}'? [$inputs['id']] : $inputs['ids'];