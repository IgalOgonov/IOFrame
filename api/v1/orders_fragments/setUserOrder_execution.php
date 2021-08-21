<?php
if($action === 'assignUserToOrder')
    $result = $PurchaseOrderHandler->assignUserToOrder(
        $inputs['userID'],
        $inputs['orderID'],
        [
            'relationType'=> $inputs['relationType'],
            'meta'=> $inputs['meta'],
        ],
        ['test'=>$test]
    );
else
    $result = $PurchaseOrderHandler->updateOrderUserAssignment(
        $inputs['userID'],
        $inputs['orderID'],
        [
            'relationType'=> $inputs['relationType'],
            'meta'=> $inputs['meta'],
        ],
        ['test'=>$test]
    );
