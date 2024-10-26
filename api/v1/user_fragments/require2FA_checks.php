<?php
if($inputs['id']===null)
    $inputs['id'] = json_decode($_SESSION['details'],true)['ID'];
$inputs['require2FA'] = $inputs['require2FA'] === null || (bool)$inputs['require2FA'];
