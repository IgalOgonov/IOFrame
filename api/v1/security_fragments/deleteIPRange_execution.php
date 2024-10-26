<?php
$result = $IPHandler->deleteIPRange(
    $inputs['prefix'],
    $inputs['from'],
    $inputs['to'],
    ['test'=>$test]
);