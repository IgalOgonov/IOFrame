<?php

if(!defined('UserHandler'))
    require __DIR__ . '/../../../IOFrame/Handlers/UserHandler.php';

if(!isset($UserHandler))
    $UserHandler = new IOFrame\Handlers\UserHandler(
        $settings,
        $defaultSettingsParams
    );

$template = $UserHandler->userSettings->getSetting('inviteMailTemplate');
if(!$template)
    die('1');
$title = $UserHandler->userSettings->getSetting('inviteMailTitle');
if(!$title)
    $title = 'You\'ve been invited to '.$siteSettings->getSetting('siteName');

$params = [
    'test'=>$test,
    'token'=>$inputs['token'],
    'tokenUses'=>$inputs['tokenUses'],
    'tokenTTL'=>$inputs['tokenTTL'],
    'extraTemplateArguments'=>$inputs['extraTemplateArguments']
];

$result = $UserHandler->sendInviteMail($inputs['mail'],(int)$template,$title,false,$params);

?>