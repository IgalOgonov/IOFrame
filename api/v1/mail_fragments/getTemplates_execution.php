<?php


$MailManager = new \IOFrame\Managers\MailManager(
    $settings,
    array_merge($defaultSettingsParams,['mode'=>'none'])
);

$params = ['test'=>$test];
if(empty($inputs['ids'])){
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

$result = $MailManager->getTemplates($inputs['ids'],$params);
