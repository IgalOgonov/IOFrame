<?php

if($inputs['returnIDMeta'] === null)
    $inputs['returnIDMeta'] = true;

$params = [
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
    'returnIDMeta'=>$inputs['returnIDMeta'],
    'test'=>$test,
];

$result = $PurchaseOrderHandler->archiveOrders(
    $inputs['ids'],
    $params
);
