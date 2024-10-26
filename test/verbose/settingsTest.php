<?php
echo 'Printing meta settings:'.EOL;
$metaSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/metaSettings/',array_merge($defaultSettingsParams,['opMode'=>\IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL]));
$metaSettings->printAll();

echo 'Setting new site setting randomSetting to value 3600:'.EOL;
echo '* Note that this will propagate to all future usage of $siteSettings in this script, so never run test / non-test actions together'.EOL.EOL;
var_dump(
    $siteSettings->setSetting('randomSetting',3600,['createNew'=>true,'test'=>true,'verbose'=>true])
);
echo EOL;

echo 'Unsetting site setting tokenTTL'.EOL;
var_dump(
    $siteSettings->setSetting('tokenTTL',null,['test'=>true,'verbose'=>true])
);
echo EOL;

echo 'Unsetting site settings tokenTTL, maxCacheSize, fakeSetting, updating setting siteName, creating new setting newSetting'.EOL;
var_dump(
    $siteSettings->setSettings([
        'tokenTTL'=>null,
        'maxCacheSize'=>null,
        'fakeSetting'=>null,
        'siteName'=>'Example Site Name',
        'newSetting'=>'Very nice setting',
    ],['createNew'=>true,'test'=>true,'verbose'=>true])
);
echo EOL;

echo 'Syncing local settings with DB (should fail):'.EOL;
var_dump(
    $settings->syncWithDB(['test'=>true,'verbose'=>true])
);
echo EOL;

$combined = new \IOFrame\Handlers\SettingsHandler(
    [$rootFolder.'/localFiles/resourceSettings/',$rootFolder.'/localFiles/mailSettings/',$rootFolder.'/localFiles/userSettings/']
    ,$defaultSettingsParams
);
$noMailSettings = clone $combined;
$noMailSettings->keepSettings(['resourceSettings','userSettings']);
echo 'Printing settings that were created from fetching 3 setting sets and keeping 2:'.EOL;
$noMailSettings ->printAll();
echo EOL;

$mailSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/mailSettings/',$defaultSettingsParams);

echo 'Initiating mail settings DB table:'.EOL;
var_dump(
    $mailSettings->initDB(['test'=>true])
);
echo EOL;

echo 'Syncing mail settings with DB (should succeed):'.EOL;
var_dump(
    $mailSettings->syncWithDB(['localToDB'=>false,'test'=>true,'verbose'=>true])
);
echo EOL;

echo 'Trying to open non-existent settings --'.EOL;

$fakeSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/fakeSettings/',$defaultSettingsParams);
$fakeSettings->printAll();

echo 'Creating new settings folder & table --'.EOL;
$fakeSettings->setSetting('example','value',['createNew'=>true,'initIfNotExists'=>true,'test'=>true]);
echo EOL;

echo 'Writing tag settings'.EOL;

$tagSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/tagSettings/',array_merge($defaultSettingsParams,['base64Storage'=>true]));

$tagArgs = [
    "availableTagTypes" => json_encode(
        ['default-article-tags'=>['title'=>'Default Article Tags','img'=>true,'img_empty_url'=>'ioframe/img/icons/upload.svg','extraMetaParameters'=>['eng'=>['title'=>'Tag Title']]]]
    ),
    "availableCategoryTagTypes" => ''
];

var_dump($tagSettings->setSettings($tagArgs,['createNew'=>true,'test'=>true,'verbose'=>true]));
echo EOL;

echo 'Combined view Logging SQL settings over Default SQL settings: '.EOL;
$logSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/logSettings/',$defaultSettingsParams);
$sqlSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/sqlSettings/',$defaultSettingsParams);
$logSQLSettings = clone $sqlSettings;
$logSQLSettings->combineWithSettings($logSettings,[
    'settingAliases'=>[
        'logs_sql_table_prefix'=>'sql_table_prefix',
        'logs_sql_server_addr'=>'sql_server_addr',
        'logs_sql_server_port'=>'sql_server_port',
        'logs_sql_username'=>'sql_username',
        'logs_sql_password'=>'sql_password',
        'logs_sql_db_name'=>'sql_db_name',
        'logs_sql_persistent'=>'sql_persistent',
    ],
    'includeRegex'=>'logs_sql',
    'ignoreEmptyStrings'=>['logs_sql_server_addr','logs_sql_server_port','logs_sql_username','logs_sql_password','logs_sql_db_name',],
    'verbose'=>true
]);
var_dump($logSQLSettings->getSettings());