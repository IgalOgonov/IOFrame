<?php

$result = $PurchaseOrderHandler->deleteOrderUsers(
    $inputs['orderID'],
    [$inputs['userID']],
    ['test'=>$test]
);