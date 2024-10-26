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
    'usersIn' => [$inputs['userID']],
    'test'=>$test,
];

$result = $PurchaseOrderHandler->getOrders(
    [],
    $params
);
