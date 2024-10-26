<?php


if(!isset($UsersHandler))
    $UsersHandler = new \IOFrame\Handlers\UsersHandler(
        $settings,
        $defaultSettingsParams
    );

$template = $UsersHandler->userSettings->getSetting('inviteMailTemplate_'.$inputs['language'])??$UsersHandler->userSettings->getSetting('inviteMailTemplate');
if(!$template)
    die('1');
$title = $UsersHandler->userSettings->getSetting('inviteMailTitle');
if(!$title)
    $title = 'You\'ve been invited to '.$siteSettings->getSetting('siteName');

$params = [
    'test'=>$test,
    'token'=>$inputs['token'],
    'tokenUses'=>$inputs['tokenUses'],
    'tokenTTL'=>$inputs['tokenTTL'],
    'language'=>$inputs['language'],
    'extraTemplateArguments'=>$inputs['extraTemplateArguments']
];

$result = $UsersHandler->sendInviteMail($inputs['mail'],$template,$title,false,$params);
