<?php
$result = $PurchaseOrderHandler->setOrderUsers(
    $inputs['orderID'],
    [
        [
            'user'=>$inputs['userID'],
            'relation'=> $inputs['relationType'],
            'meta'=> $inputs['meta'],
        ]
    ],
    ['update'=>($action !== 'assignUserToOrder'),'overwrite'=>($action !== 'assignUserToOrder'),'test'=>$test]
);