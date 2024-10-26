<?php

$settings->printAll();
$redisSettings->printAll();
$siteSettings->printAll();
$resourceSettings->printAll();
$sqlSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/sqlSettings/',$defaultSettingsParams);
$sqlSettings->printAll();
$userSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/userSettings/',$defaultSettingsParams);
$userSettings->printAll();
$pageSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/pageSettings/',$defaultSettingsParams);
$pageSettings->printAll();
$mailSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/mailSettings/',$defaultSettingsParams);
$mailSettings->printAll();
$apiSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/apiSettings/',$defaultSettingsParams);
$apiSettings->printAll();
$metaSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/metaSettings/',$defaultSettingsParams);
$metaSettings->printAll();

