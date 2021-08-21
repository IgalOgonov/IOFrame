<?php

$result = $PurchaseOrderHandler->getOrder(
        $inputs['id'],
        ['test'=>$test]
    );

if($inputs['includeOrderUsers']){
    $result['userInfo'] = $orderUsers;
}