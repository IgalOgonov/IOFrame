<?php

$params = [
    'getLimitedInfo' =>$inputs['getLimitedInfo'],
    'returnOrders' =>$inputs['returnOrders'],
    'relationType'=>$inputs['relationType'],
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

$result = $PurchaseOrderHandler->getUserOrders(
    $inputs['userID'],
    $params
);
