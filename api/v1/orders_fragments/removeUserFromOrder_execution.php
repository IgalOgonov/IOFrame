<?php

$result = $PurchaseOrderHandler->removeUserFromOrder(
    $inputs['orderID'],
    $inputs['userID'],
    ['test'=>$test]
);