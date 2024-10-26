<?php
$result = $IPHandler->deleteExpired(
    [
        'range'=>(bool)$inputs['range'],
        'test'=>$test,
    ]
);