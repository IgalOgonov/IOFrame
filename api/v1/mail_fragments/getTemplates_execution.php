<?php

if(!defined('safeSTR'))
    require __DIR__ . '/../../../IOFrame/Util/safeSTR.php';

$params = ['test'=>$test];
if($inputs['ids'] === null){
    $params = array_merge($params,
        [
            'limit'=>$inputs['limit'],
            'offset'=>$inputs['offset'],
            'createdAfter'=>$inputs['createdAfter'],
            'createdBefore'=>$inputs['createdBefore'],
            'changedAfter'=>$inputs['changedAfter'],
            'changedBefore'=>$inputs['changedBefore'],
            'includeRegex'=>$inputs['includeRegex'],
            'excludeRegex'=>$inputs['excludeRegex'],
        ]);
}

$result = $MailHandler->getTemplates($inputs['ids'],$params);
