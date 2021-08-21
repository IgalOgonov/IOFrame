<?php

$params = ['createNew'=>($action==='createOrder'),'test'=>$test];
$id = ($action==='createOrder')? -1 : $inputs['id'];
$result = $PurchaseOrderHandler->setOrder(
    $id,
    ['orderInfo'=>$inputs['orderInfo'],'orderType'=>$inputs['orderType'],'orderStatus'=>$inputs['orderStatus']],
    $params
);

?>