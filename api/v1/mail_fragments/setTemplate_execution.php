<?php

$MailManager = new \IOFrame\Managers\MailManager(
    $settings,
    array_merge($defaultSettingsParams,['mode'=>'none'])
);

$params = ['test'=>$test,'update'=>($action === 'updateTemplate'),'override'=>($action === 'updateTemplate')];

$result = $MailManager->setTemplate($inputs['id'],$inputs['title'],$inputs['content'],$params);