<?php

require_once __DIR__.'/../../IOFrame/Handlers/LoggingHandler.php';
require_once __DIR__.'/../../IOFrame/Util/TimingMeasurer.php';
$timingMeasurer = new \IOFrame\Util\TimingMeasurer();
$loggingRulesToConstruct = [
    ['Channel'=>\IOFrame\Definitions::LOG_DEFAULT_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Default'])],
    ['Channel'=>\IOFrame\Definitions::LOG_GENERAL_SECURITY_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Security'])],
    ['Channel'=>\IOFrame\Definitions::LOG_USERS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Users'])],
    ['Channel'=>\IOFrame\Definitions::LOG_TOKENS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Tokens'])],
    ['Channel'=>\IOFrame\Definitions::LOG_TAGS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Tags'])],
    ['Channel'=>\IOFrame\Definitions::LOG_SETTINGS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Settings'])],
    ['Channel'=>\IOFrame\Definitions::LOG_ROUTING_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Routing'])],
    ['Channel'=>\IOFrame\Definitions::LOG_RESOURCES_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Resources'])],
    ['Channel'=>\IOFrame\Definitions::LOG_ORDERS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Default'])],
    ['Channel'=>\IOFrame\Definitions::LOG_PLUGINS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Plugins'])],
    ['Channel'=>\IOFrame\Definitions::LOG_MAILING_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Mailing'])],
    ['Channel'=>\IOFrame\Definitions::LOG_CLI_JOBS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error CLI Jobs'])]
];
/*
echo 'Initializing Default logger with default handler: '.EOL;
$defaultSettingsParams['logger'] =  new \Monolog\Logger(\IOFrame\Definitions::LOG_DEFAULT_CHANNEL);
$defaultSettingsParams['logger']->pushHandler($defaultSettingsParams['logHandler']);

echo 'Start time:'.$timingMeasurer->start().EOL;
echo 'Logging debug to default channel: '.EOL;
$defaultSettingsParams['logger']->debug('example debug',['logged-in'=>$auth->isLoggedIn(),'example'=>'example context']);


echo 'Logging debug to notice channel, two times in a row: '.EOL;
$defaultSettingsParams['logger']->notice('example notice',['logged-in'=>$auth->isLoggedIn(),'example'=>'example context 2']);
$defaultSettingsParams['logger']->notice('example notice',['logged-in'=>$auth->isLoggedIn(),'example'=>'example context 3']);
echo 'Logging debug to error channel: '.EOL;
$defaultSettingsParams['logger']->error('example error',['logged-in'=>$auth->isLoggedIn(),'example'=>'example context 3']);

echo 'Nanoseconds elapsed (including time to echo): '.$timingMeasurer->timeElapsed().EOL;
*/

/*
echo 'Initializing Different Channel (Article) logger, and handler that ignores logs below WARNING: '.EOL;
echo 'Logging debug to notice channel, then to warning channel: '.EOL;

$articlesLogger = new \Monolog\Logger('Articles');
$warningAndAboveHandler = new IOFrameHandler($settings,['level'=>\Monolog\Logger::WARNING]);
$articlesLogger->pushHandler($warningAndAboveHandler);

$timingMeasurer->start();
$articlesLogger->notice('Could not load requested articles',['logged-in'=>false,'articleIds'=>[5],'userId'=>null]);
$articlesLogger->warning('Could not load requested articles',['logged-in'=>$auth->isLoggedIn(),'articleIds'=>[3,7],'userId'=>5]);
$timingMeasurer->stop();
echo 'Nanoseconds elapsed (not including time to echo): '.$timingMeasurer->timeElapsed().EOL;
*/

echo EOL.'LOG SETTINGS:'.EOL.EOL;
$logSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/logSettings/',$defaultSettingsParams);
var_dump($logSettings->getSettings());

/* For the following tests, create a new database on the same server, define its name in logSettings (e.g. ioframe_logs).
   Create table DEFAULT_LOGS as defined in SQLdbInit.php, and a user also defined in logSettings (e.g. 'example_log_user'@'%', password 'qIP9Zkn/Mtmo*SIV')
   Grant the new user full privileges only on that db.
*/
$LoggingHandler = new \IOFrame\Handlers\LoggingHandler($settings,array_merge($defaultSettingsParams,['logSettings'=>$logSettings]));

echo EOL.'-- LOGS --'.EOL;

echo 'Adding new logs to DB:'.EOL;
var_dump(
    $LoggingHandler->setLogs(
        [
            ['Channel'=>'ioframe-example-channel','Log_Level'=>100,'Created'=>'1696027829.639714','Node'=>'Example_Node_1','Message'=>json_encode(['message'=>'example','context'=>['logged-in'=>true,'example'=>'example-context 1']])],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>250,'Created'=>'1696029729.739714','Node'=>'Example_Node_1','Message'=>json_encode(['message'=>'example 2','context'=>['logged-in'=>true,'example'=>'example-context 2']])],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Created'=>'1696039729.637614','Node'=>'Example_Node_2','Message'=>json_encode(['message'=>'example 3','context'=>['logged-in'=>false,'example'=>'example-context 3']])]
        ],
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all logs LIMIT 10, without aggregated info:'.EOL;
var_dump(
    $LoggingHandler->getLogs([],['limit'=>10,'test'=>true,'verbose'=>true])
);

echo EOL.'Getting all logs LIMIT 10, with daily count:'.EOL;
var_dump(
    $LoggingHandler->getLogs(
        [],
        [
            'test'=>true,
            'verbose'=>true,
            'limit'=>10,
            'replaceTypeArray'=>[
                'extraToGet'=>[
                    'Created'=>[
                        'key'=>'intervals',
                        'type'=>'count_interval',
                        'intervals'=>24*3600
                    ]
                ]
            ]
        ]
    )
);

echo EOL.'Getting all logs LIMIT 10, with daily count, with limits:'.EOL;
var_dump(
    $LoggingHandler->getLogs(
        [],
        [
            'test'=>true,
            'verbose'=>true,
            'limit'=>10,
            'createdAfter'=>'1696089729.637614',
            'createdBefore'=>'1696489729.637614',
            'nodeIs'=>'Example_Node_1',
            'replaceTypeArray'=>[
                'extraToGet'=>[
                    'Created'=>[
                        'key'=>'intervals',
                        'type'=>'count_interval',
                        'intervals'=>24*3600
                    ]
                ]
            ]
        ]
    )
);

echo EOL.'Deleting Logs From DB:'.EOL;
var_dump(
    $LoggingHandler->deleteLogs(
        [
            ['Channel'=>'ioframe-example-channel','Log_Level'=>100,'Created'=>'1696027829.639714','Node'=>'Example_Node_1'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>250,'Created'=>'1696029729.739714','Node'=>'Example_Node_1'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Created'=>'1696039729.637614','Node'=>'Example_Node_2']
        ],
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'-- GROUPS --'.EOL;

echo 'Adding new groups:'.EOL;
var_dump(
    $LoggingHandler->setItems(
        [
            ['Group_Type'=>'tech','Group_ID'=>'sup-1','Meta'=>json_encode(['title'=>'Tech Support - Tier 1'])],
            ['Group_Type'=>'tech','Group_ID'=>'sup-2','Meta'=>json_encode(['title'=>'Tech Support - Tier 2'])],
            ['Group_Type'=>'tech','Group_ID'=>'sre-1','Meta'=>json_encode(['title'=>'Site Reliability Team'])],
        ],
        'reportingGroups',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all groups'.EOL;
var_dump(
    $LoggingHandler->getItems(
        [],
        'reportingGroups',
        [
            'test'=>true,
            'verbose'=>true,
        ]
    )
);

echo EOL.'Deleting groups:'.EOL;
var_dump(
    $LoggingHandler->deleteItems(
        [
            ['Group_Type'=>'tech','Group_ID'=>'sup-1'],
            ['Group_Type'=>'tech','Group_ID'=>'sup-2'],
            ['Group_Type'=>'tech','Group_ID'=>'sre-1'],
        ],
        'reportingGroups',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'-- GROUP USERS --'.EOL;

echo 'Adding new group users:'.EOL;
var_dump(
    $LoggingHandler->setItems(
        [
            ['Group_Type'=>'tech','Group_ID'=>'sup-1','User_ID'=>1],
            ['Group_Type'=>'tech','Group_ID'=>'sup-2','User_ID'=>1],
            ['Group_Type'=>'tech','Group_ID'=>'sre-1','User_ID'=>1],
        ],
        'reportingGroupUsers',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all group users, LIMIT 10'.EOL;
var_dump(
    $LoggingHandler->getItems(
        [],
        'reportingGroupUsers',
        [
            'limit'=>10,
            'test'=>true,
            'verbose'=>true,
        ]
    )
);

echo EOL.'Getting all group users  of groups that contain user 1, no limit, no meta'.EOL;
var_dump(
    $LoggingHandler->getItems(
        [],
        'reportingGroupUsers',
        [
            'userIs'=>1,
            'disableExtraToGet'=>true,
            'test'=>true,
            'verbose'=>true,
        ]
    )
);

echo EOL.'Getting all groups users of group tech/sup-1'.EOL;
var_dump(
    $LoggingHandler->getItems(
        [['tech','sup-1']],
        'reportingGroupUsers',
        [
            'disableExtraToGet'=>true,
            'test'=>true,
            'verbose'=>true,
        ]
    )
);

echo EOL.'Deleting group users:'.EOL;
var_dump(
    $LoggingHandler->deleteItems(
        [
            ['Group_Type'=>'tech','Group_ID'=>'sup-1','User_ID'=>1],
            ['Group_Type'=>'tech','Group_ID'=>'sup-2','User_ID'=>1],
            ['Group_Type'=>'tech','Group_ID'=>'sre-1','User_ID'=>1],
        ],
        'reportingGroupUsers',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'-- RULES --'.EOL;

echo 'Adding new rules:'.EOL;
var_dump(
    $LoggingHandler->setItems(
        [
            ['Channel'=>'ioframe-example-channel','Log_Level'=>300,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Warning'])],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error'])],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Alert'])],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Report_Type'=>'sms','Meta'=>json_encode(['title'=>'SMS On Alert'])],
        ],
        'reportingRules',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all rules LIMIT 10'.EOL;
var_dump(
    $LoggingHandler->getItems(
        [],
        'reportingRules',
        [
            'limit'=>10,
            'test'=>true,
            'verbose'=>true,
        ]
    )
);

echo EOL.'Getting all rules of type "email", at level between 300-400, LIMIT 10'.EOL;
var_dump(
    $LoggingHandler->getItems(
        [],
        'reportingRules',
        [
            'limit'=>10,
            'levelAtLeast'=>300,
            'levelAtMost'=>400,
            'reportTypeIs'=>'email',
            'test'=>true,
            'verbose'=>true,
        ]
    )
);

echo EOL.'Deleting rules:'.EOL;
var_dump(
    $LoggingHandler->deleteItems(
        [
            ['Channel'=>'ioframe-example-channel','Log_Level'=>300,'Report_Type'=>'email'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>400,'Report_Type'=>'email'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Report_Type'=>'email'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Report_Type'=>'sms'],
        ],
        'reportingRules',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'-- RULE GROUPS --'.EOL;

echo 'Adding new rule groups:'.EOL;
var_dump(
    $LoggingHandler->setItems(
        [
            ['Channel'=>'ioframe-example-channel','Log_Level'=>300,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sre-1'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>400,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sre-1'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>400,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sup-2'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sre-1'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sup-2'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sup-1'],
        ],
        'reportingRuleGroups',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all rule groups LIMIT 10'.EOL;
var_dump(
    $LoggingHandler->getItems(
        [],
        'reportingRuleGroups',
        [
            'limit'=>10,
            'test'=>true,
            'verbose'=>true,
        ]
    )
);

echo EOL.'Getting all rule groups filtered by specific group, no limit, no meta data'.EOL;
var_dump(
    $LoggingHandler->getItems(
        [],
        'reportingRuleGroups',
        [
            'fullGroupIn'=>[ ['tech','sre-1'] ],
            'disableExtraToGet'=>true,
            'test'=>true,
            'verbose'=>true,
        ]
    )
);

echo EOL.'Getting all rule groups filtered by specific cures/groups, only count'.EOL;
var_dump(
    $LoggingHandler->getItems(
        [],
        'reportingRuleGroups',
        [
            'fullRuleIn'=>[ ['ioframe-example-channel',400,'email'],['ioframe-example-channel',500,'email'] ],
            'fullGroupIn'=>[ ['tech','sup-2'],['tech','sre-1'] ],
            'disableExtraToGet'=>['Group_Type','Group_ID','Report_Type','Channel'],
            'test'=>true,
            'verbose'=>true,
        ]
    )
);

echo EOL.'Deleting rule groups:'.EOL;
var_dump(
    $LoggingHandler->deleteItems(
        [
            ['Channel'=>'ioframe-example-channel','Log_Level'=>300,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sre-1'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>400,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sre-1'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>400,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sup-2'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sre-1'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sup-2'],
            ['Channel'=>'ioframe-example-channel','Log_Level'=>500,'Report_Type'=>'email','Group_Type'=>'tech','Group_ID'=>'sup-1'],
        ],
        'reportingRuleGroups',
        ['test'=>true,'verbose'=>true]
    )
);