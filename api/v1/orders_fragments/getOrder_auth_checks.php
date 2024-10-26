<?php

//TODO change to proper filter We will use this both for auth and later for execution
$orderUsers = $PurchaseOrderHandler->getItems(
    [],
    'default-orders-users',
    ['test'=>$test,'orderIn'=>[$inputs['id']],'getLimitedInfo'=>false]
);

//Generally, each user may view an order he is associated with
$associated = isset($orderUsers[$details['ID']]);

if(!$associated && !$auth->isAuthorized() && !$auth->hasAction(ORDERS_VIEW_AUTH)){
    if($test)
        echo 'Only an admin, or a user associated with the order, may view it!'.EOL;
    exit(AUTHENTICATION_FAILURE);
}