<?php

$params = [
    'getLimitedInfo' =>$inputs['getLimitedInfo'],
    'typeIs'=>$inputs['typeIs'],
    'statusIs'=>$inputs['statusIs'],
    'orderBy'=>$inputs['orderBy'],
    'orderType'=>$inputs['orderType'],
    'limit'=>$inputs['limit'],
    'offset'=>$inputs['offset'],
    'createdAfter'=>$inputs['createdAfter'],
    'createdBefore'=>$inputs['createdBefore'],
    'changedAfter'=>$inputs['changedAfter'],
    'changedBefore'=>$inputs['changedBefore'],
    'test'=>$test,
];

$result = $PurchaseOrderHandler->getOrders(
    $inputs['ids'],
    $params
);
