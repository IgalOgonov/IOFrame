<?php
if(isset($inputs['mail'])){
    $userId = $SQLHandler->selectFromTable($SQLHandler->getSQLPrefix().'USERS',['Email',$inputs['mail'],'='],['ID'],[]);
    if(count($userId)>0)
        $userId = (int)$userId[0]['ID'];
    else
        $userId = null;
    require 'limit_rate_check.php';
}