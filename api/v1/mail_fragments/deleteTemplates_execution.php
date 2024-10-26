<?php

$MailManager = new \IOFrame\Managers\MailManager(
    $settings,
    array_merge($defaultSettingsParams,['mode'=>'none'])
);

$result = $MailManager->deleteTemplates($inputs['ids'],['test'=>$test]);

