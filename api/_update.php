<?php

/* This the the API that handles system updates.
 * Unlike all other APIs, it also includes the ability to be used via the CLI for local updates (mainly creating new setting files)
 *
 * See standard return values at defaultInputResults.php
 *
 * Parameters:
 * "action"     - Requested action - described below
 *_________________________________________________
 * getVersionsInfo
 *
 *      Returns:
 *          Data about version, of the form:
 *          {
 *              current: <string, current version>,
 *              available: <string, available version>,
 *              next: <string, next version to upgrade to. Sometimes, despite updates being available, the next one may be unreachable from the current version>,
 *              versions: <string[], version updates available.>
 *          }
 *
 *      Examples: action=getVersionsInfo
 *_________________________________________________
 * update
 *      Updates to next version.
 *      Returns int:
 *         -1 - catastrophic failure, failed an updated AND failed to roll back
 *          0 - success
 *          1 - failed to update, but successfully rolled back
 *          2 - next update does not exist
 *
 *      Examples: action=update
 * */

$cli = php_sapi_name() == "cli";

if($cli)
    if(!isset($inputs) || !isset($errors) || !isset($context) || !isset($params)){
        die('This script can only be used from the maintenance CLI');
    }

if(!$cli && !defined('IOFrameMainCoreInit'))
    require_once __DIR__ . '/../main/core_init.php';

if(!$cli){
    require 'apiSettingsChecks.php';
    require 'defaultInputChecks.php';
    require 'CSRF.php';

    if(!isset($_REQUEST["action"]))
        exit('Action not specified!');
    $action = $_REQUEST["action"];

    $opMode = $defaultSettingsParams['opMode'];
}
else{
    $test = $params['test']??false;
    $silent = $params['silent']??false;
    $rootFolder = $inputs['defaultParams']['absPathToRoot'];
    $settings = $inputs['settings'];
    $siteSettings = $inputs['siteSettings'];
    $defaultSettingsParams = $inputs['defaultParams'];
    $SQLManager = $defaultSettingsParams['SQLManager'];
    $opMode = empty($context->variables['localUpdate'])? $defaultSettingsParams['opMode'] : \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL;
    $auth = new \IOFrame\Handlers\AuthHandler($inputs['settings'],$inputs['defaultParams']);
    $action = 'update';
}

$opModeExcludesDB = $opMode === \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL;
$opModeExcludesLocal = $opMode === \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_DB;

require 'defaultInputResults.php';
require 'update_fragments/definitions.php';

$verbose = empty($silent) && ( !empty($params['verbose'])? $params['verbose'] : $test);

if($test && $verbose)
    echo 'Testing mode!'.EOL;

$populateTest = $test && (!empty($_REQUEST['populateTest']) || !empty($context->variables['populateTest']));

//Authorize use
if(!$cli && !($auth->isAuthorized() || $auth->hasAction(CAN_UPDATE_SYSTEM)) ){
    if($test)
        echo 'Only admins may use this API!';
    exit(AUTHENTICATION_FAILURE);
}


switch($action){

    case 'getVersionsInfo':

        $availableVersion = \IOFrame\Util\FileSystemFunctions::readFile($rootFolder.'/meta/', 'ver');
        $currentVersion = $siteSettings->getSetting('ver');
        $next = !empty($versionArray[$currentVersion])? $versionArray[$currentVersion] : null;
        $versions = $versionArray;
        echo json_encode(
            [
                'current'=>$currentVersion,
                'available'=>$availableVersion,
                'next'=>$next,
                'versions'=>$versions
            ]
        );
        break;
    // TODO Split "version" cases into individual fragment files to require, for better readability.
    // Files: <ver>.'_definitions.php',<ver>.'_custom_actions.php',<ver>.'_custom_actions_rollback.php'
    case 'update':

        if(!$cli && !validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);
        $maintenance = $cli? !empty($context->variables['maintenanceDuringUpdate']) : ($_REQUEST["maintenance"] ?? false);

        /*Initiate some stuff*/
        $prefix = $SQLManager->getSQLPrefix();
        $currentVersion = $opModeExcludesDB ? null : $siteSettings->getSetting('ver');
        if($cli && !empty($context->variables['fromSpecificVersion']))
            $currentVersion = $context->variables['fromSpecificVersion'];

        $next = $versionArray[$currentVersion] ?? null;
        if(!$next){
            if(!$cli)
                die('2');
            else{
                $errors['unknown-version'] = true;
                return '2';
            }
        }

        /*What to do in each case*/
        //New setting files of the format <string, fileName/tableName> => ['type'=><'db' or 'local'>, 'title'=><string, optional title>]
        $newSettingFiles = [
            /* Example:
                'localSettings'=>['type'=>'local','title'=>'Local Node Settings'],
                'siteSettings'=>['type'=>'db','title'=>'General Site Settings']
             */
        ];
        /*New settings of the format <string, fileName/tableName> => array of objects of the form
            [
        'name'=><string, setting name>,
         'value'=><mixed, setting value>,
         <OPTIONAL>'override'=><bool, default true - if false, will not override existing setting>,
         <OPTIONAL>'createNew'=><bool, default true - if false, will not allow creating a new setting>,
        ]
        */
        $newSettings = [
            /* Example:
                'localSettings'=>[
                    ['name'=>'updateTest','value'=>'test'],
                    ['name'=>'opMode','value'=>'db']
                ],
                'siteSettings'=>[
                    ['name'=>'sslOn','value'=>1],
                    ['name'=>'maxUploadSize','value'=>4000000]
                ]
             */
        ];
        //New actions of the format <string, action name> => <string, action description (gets converted to safeString automatically)>
        $newActions = [
            /* Example:
                'TEST_ACTION'=>'Some action meant for resting',
                'EXAMPLE_ACTION'=>'An action that allows showing examples'
             */
        ];
        //New security events of the format [<int, category>,<int, type>,<int, sequence number>,<int, blacklist for>,<int, ttl>]
        $newSecurityEvents = [
            /* Example:
                [0,2,0,0,86400],
                [0,2,1,0,0],
                [0,2,5,3600,2678400],
                [0,2,6,3600,3600],
                [0,2,7,86400,86400],
                [0,2,8,2678400,2678400],
                [0,2,9,31557600,31557600]
             */
        ];
        //New security events meta of the format ['category' => <int, category>,'type' => <int, type>,'meta' => JSON encoded object of the form ['name'=>string, event name>] ]
        $newSecurityEventsMeta = [
            /* Example:
                [
                    'category'=>0,
                    'type'=>0,
                    'meta'=>json_encode([
                        'name'=>'IP Incorrect Login Limit'
                    ])
                ],
                [
                    'category'=>0,
                    'type'=>1,
                    'meta'=>json_encode([
                        'name'=>'IP Request Reset Mail Limit'
                    ])
                ]
             */
        ];
        //New routes of the format [<string, request type>,<string, route>,<string, match name>,<string, map name>] (added to start!)
        $newRoutes = [
            /* Example:
                ['GET|POST','api/[*:trailing]','api',null],
                ['GET|POST','[*:trailing]','front',null]
             */
        ];
        //New matches of the format <string, match name> => <object as in \IOFrame\Handlers\RouteHandler::setMatch()>
        $newMatches = [
            /* Example:
                'front'=>['front/ioframe/pages/[trailing]', 'php,html,htm'],
                'api'=>['api/[trailing]','php']
             */
        ];
        //New queries to be executed. They come in two's - a query to do something, and one to reverse that change. 2D array.
        $newQueries = [
            /* Example:
                [
                    'CREATE TABLE IF NOT EXISTS '.$prefix.'TEST (
                              Test_1 varchar(16) NOT NULL,
                              Test_2 varchar(45) NOT NULL,
                              PRIMARY KEY (Test_1, Test_2)
                          ) ENGINE=InnoDB DEFAULT CHARSET = utf8;',
                    'DROP TABLE ".$prefix."TEST;'
                ],
                [
                    'ALTER TABLE '.$prefix.'TEST ADD Test_3 INT NULL DEFAULT NULL AFTER Test_2;',
                    'ALTER TABLE '.$prefix.'TEST DROP Test_3;'
                ],
             */
        ];

        //TODO Add direct support for logging rules/groups
        $newLoggingGroups = [];
        $newLoggingRules = [];
        $newLoggingRuleGroups = [];

        //Stuff that may be required upon a rollback
        /*Array of old settings of the form:
            <string, settings file name> => [
                'original' => Associative array of original settings
                'changed'=> Array of changed setting names
            ]
        */
        $oldSettings = [];
        /*Array of old actions of the form:
            <string, action name> => <array|null, old action array if we changed it, or null if we added a new one>
        */
        $oldActions = [];
        /*Array of old security events of the form:
            <string, event identifier of the form category/type/sequence> => <array|null, old sequence array if we changed it, or null if we added a new one>
        */
        $oldSecurityEvents = [];
        /*Array of old security events meta of the form:
            <string, event identifier of the form category/type> => <array|null, old meta array if we changed it, or null if we added a new one>
        */
        $oldSecurityEventsMeta = [];
        //Int, ID of the first route added
        $newRouteId = -1;
        /*Array of old route matches of the form:
            <string, identifier of match> => <array|int, old array if we changed it, or 1 if it didn't exist>
        */
        $oldMatches = [];
        //Int, number of queries that succeeded
        $queriesSucceeded = 0;
        /* --
            The above is always executed in the order it's defined here, and attempted to be reversed in the reverse order.
         --*/
        $updateStages = ['customActions','settingFiles','settings','actions','securityEvents','securityEventsMeta','routes','matches','queries','increaseVersion'];
        $currentStage = 0;
        $allGood = true;

        //Set maintenance if relevant
        if($maintenance){
            if(!$opModeExcludesLocal){
                $settings->setSetting('_maintenance','1',['test'=>$test,'verbose'=>$verbose,'createNew'=>true]);
            }
            if(!$opModeExcludesDB){
                $siteSettings->setSetting('_maintenance','1',['test'=>$test,'verbose'=>$verbose,'createNew'=>true]);
            }
        }

        /* Fill the relevant arrays based on the update */
        switch ($next){
            case '1.1.0.0':
                array_push(
                    $newQueries,
                    [
                        'ALTER TABLE '.$prefix.'USERS ADD Phone VARCHAR(32) NULL DEFAULT NULL AFTER Email, ADD UNIQUE (Phone);',
                        'ALTER TABLE '.$prefix.'USERS DROP Phone;',
                    ],
                    [
                        'ALTER TABLE '.$prefix.'USERS ADD Two_Factor_Auth TEXT NULL AFTER authDetails;',
                        'ALTER TABLE '.$prefix.'USERS DROP Two_Factor_Auth;',
                    ]
                );
                $newSettings['userSettings'] = [
                    ['name'=>'allowSMS2FA','value'=>0],
                    ['name'=>'sms2FAExpires','value'=>300],
                    ['name'=>'allowMail2FA','value'=>1],
                    ['name'=>'mail2FAExpires','value'=>1800],
                    ['name'=>'allowApp2FA','value'=>1]
                ];
                $newSettings['pageSettings'] = [
                    ['name'=>'loginPage','value'=>'cp/login','override'=>false],
                    ['name'=>'registrationPage','value'=>'cp/account','override'=>false],
                    ['name'=>'regConfirm','value'=>'cp/account','override'=>false],
                    ['name'=>'pwdReset','value'=>'cp/account','override'=>false],
                    ['name'=>'mailReset','value'=>'cp/account','override'=>false]
                ];
                $newSettings['apiSettings'] = [
                    ['name'=>'articles','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'auth','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'contacts','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'mail','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'media','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'menu','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'object-auth','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'objects','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'orders','value'=>json_encode(['active'=>0]),'override'=>true],
                    ['name'=>'plugins','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'security','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'session','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'settings','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'tokens','value'=>json_encode(['active'=>1]),'override'=>true],
                    ['name'=>'trees','value'=>json_encode(['active'=>0]),'override'=>true],
                    ['name'=>'users','value'=>json_encode(['active'=>1]),'override'=>true]
                ];
                $newActions['CAN_ACCESS_CP'] = 'Allows accessing the control panel even when not an admin';
                $newActions['CAN_UPDATE_SYSTEM'] = 'Allows updating the system even when not an admin';
                break;
            case '1.1.1.0':
                array_push(
                    $newQueries,
                    [
                        'ALTER TABLE '.$prefix.'IOFRAME_TOKENS CHANGE Uses_Left Uses_Left BIGINT(20) UNSIGNED NOT NULL DEFAULT 1',
                        'ALTER TABLE '.$prefix.'IOFRAME_TOKENS CHANGE Uses_Left Uses_Left INT NOT NULL'
                    ],
                    [
                        'ALTER TABLE '.$prefix.'IOFRAME_TOKENS ADD Tags VARCHAR(256) NULL DEFAULT NULL AFTER Locked_At, ADD INDEX Tags (Tags);',
                        'ALTER TABLE '.$prefix.'IOFRAME_TOKENS DROP Tags;'
                    ]
                );
                $newSettings['userSettings'] = [
                    ['name'=>'inviteExpires','value'=>774],
                    ['name'=>'inviteMailTitle','value'=>'You\'ve been invited to '.$siteSettings->getSetting('siteName')],
                ];
                $newActions['INVITE_USERS_AUTH'] = 'Allows inviting users - either via mail, or by just creating invites';
                $newActions['SET_INVITE_MAIL_ARGS'] = 'Allows passing invite mail arguments';
                break;
            case '1.2.0.0':
                break;
            case '1.2.0.1':
            case '1.2.1.0':
            case '1.2.2.0':
            case '1.2.2.1':
                break;
            case '1.2.2.2':
                array_push(
                    $newQueries,
                    [
                        'ALTER TABLE '.$prefix.'ARTICLES CHANGE Block_Order Block_Order VARCHAR(2048) NULL DEFAULT NULL',
                        'ALTER TABLE '.$prefix.'ARTICLES CHANGE Block_Order Block_Order VARCHAR(2048) NOT NULL DEFAULT \'\'',
                    ]
                );
                break;
            case '2.0.0.0rc':
                $userSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/userSettings/',$defaultSettingsParams);
                //Yeah it's annoying, but I still haven't added the native rules/groups support to this script
                $newLoggingRulesExp = '';
                foreach ($loggingRulesToConstruct as $ruleInfo){
                    $newLoggingRulesExp .= $SQLManager->queryBuilder->expConstructor(
                            [
                                [$ruleInfo['Channel'],'STRING'],
                                $ruleInfo['Log_Level'],
                                [$ruleInfo['Report_Type'],'STRING'],
                                [$ruleInfo['Meta'],'STRING'],
                                time(),
                                time(),
                            ]
                        ).',';
                }
                $newLoggingRulesExp = substr($newLoggingRulesExp,0,-1);
                array_push(
                    $newQueries,
                    [
                        'ALTER TABLE '.$prefix.'MAIL_TEMPLATES CHANGE `Created_On` `Created` VARCHAR(14) NOT NULL DEFAULT "0" ',
                        'ALTER TABLE '.$prefix.'MAIL_TEMPLATES CHANGE `Created` `Created_On` VARCHAR(14) NOT NULL DEFAULT "0" ',
                    ],
                    [
                        'ALTER TABLE '.$prefix.'CONTACTS CHANGE `Created_On` `Created` VARCHAR(14) NOT NULL DEFAULT "0" ',
                        'ALTER TABLE '.$prefix.'CONTACTS CHANGE `Created` `Created_On` VARCHAR(14) NOT NULL DEFAULT "0" ',
                    ],
                    [
                        'ALTER TABLE '.$prefix.'USERS CHANGE `Created_On` `Created` VARCHAR(14) NOT NULL DEFAULT "0" ',
                        'ALTER TABLE '.$prefix.'USERS CHANGE `Created` `Created_On` VARCHAR(14) NOT NULL DEFAULT "0" ',
                    ],
                    [
                        'ALTER TABLE '.$prefix.'ROUTING_MAP CHANGE `ID` `ID` VARCHAR(64) NOT NULL; ',
                        'ALTER TABLE '.$prefix.'ROUTING_MAP CHANGE `ID` `ID` int(14) NOT NULL DEFAULT AUTO_INCREMENT ',
                    ],
                    /* Do you really think someone would do that? Just go on the internet and rename default foreign keys?*/
                    [
                        '
                            ALTER TABLE '.$prefix.'LOGIN_HISTORY DROP FOREIGN KEY '.$prefix.'LOGIN_HISTORY_IBFK_1;
                            UPDATE '.$prefix.'LOGIN_HISTORY SET '.$prefix.'LOGIN_HISTORY.Username  = (SELECT ID FROM '.$prefix.'USERS WHERE '.$prefix.'USERS.Username = '.$prefix.'LOGIN_HISTORY.Username);
                            ALTER TABLE '.$prefix.'LOGIN_HISTORY CHANGE `Username` `ID` INT NOT NULL; 
                            ALTER TABLE '.$prefix.'LOGIN_HISTORY ADD CONSTRAINT '.$prefix.'LOGIN_HISTORY_IBFK_1 FOREIGN KEY (`ID`) REFERENCES '.$prefix.'USERS(`ID`) ON DELETE CASCADE ON UPDATE CASCADE;
                        ',
                        '
                            ALTER TABLE '.$prefix.'LOGIN_HISTORY DROP FOREIGN KEY '.$prefix.'LOGIN_HISTORY_IBFK_1;
                            ALTER TABLE '.$prefix.'LOGIN_HISTORY CHANGE `ID` `Username` VARCHAR(16) NOT NULL; 
                            UPDATE '.$prefix.'LOGIN_HISTORY SET '.$prefix.'LOGIN_HISTORY.Username  = (SELECT Username FROM '.$prefix.'USERS WHERE '.$prefix.'USERS.ID = '.$prefix.'LOGIN_HISTORY.Username);
                            ALTER TABLE '.$prefix.'LOGIN_HISTORY ADD CONSTRAINT '.$prefix.'LOGIN_HISTORY_IBFK_1 FOREIGN KEY (`Username`) REFERENCES '.$prefix.'USERS(`Username`) ON DELETE CASCADE ON UPDATE CASCADE;
                        '
                    ],
                    [
                        'ALTER TABLE '.$prefix.'USERS CHANGE `Active` `Active` INT(11) NOT NULL; ',
                        'ALTER TABLE '.$prefix.'USERS CHANGE `Active` `Active` TINYINT(1) NOT NULL; ',
                    ],
                    [
                        'ALTER TABLE '.$prefix.'USERS_EXTRA ADD `Locked_Until` VARCHAR(14) NULL DEFAULT NULL AFTER `Banned_Until`; ',
                        'ALTER TABLE '.$prefix.'USERS_EXTRA DROP `Locked_Until`; '
                    ],
                    [
                        'DROP FUNCTION IF EXISTS `'.$prefix.'commitEventUser`',
                        '
                        CREATE FUNCTION '.$prefix.'commitEventUser (
                            ID int(11),
                            Event_Type BIGINT(20) UNSIGNED,
                            Add_Weight INT(10) UNSIGNED,
                            Suspicious_On_Limit BOOLEAN,
                            Ban_On_Limit BOOLEAN
                         )
                        RETURNS INT(20)
                        BEGIN
                            DECLARE eventCount INT;
                            DECLARE Add_TTL INT;
                            DECLARE Blacklist_For INT;
                            #Either the event sequence already exists, or a new one needs to be created.
                            SELECT Sequence_Count INTO eventCount FROM
                                       '.$prefix.'USER_EVENTS WHERE(
                                           '.$prefix.'USER_EVENTS.ID = ID AND
                                           '.$prefix.'USER_EVENTS.Event_Type = Event_Type AND
                                           '.$prefix.'USER_EVENTS.Sequence_Expires > UNIX_TIMESTAMP()
                                       )
                                        LIMIT 1;
            
                            #eventCount may be null!
                            IF ISNULL(eventCount) THEN
                                SELECT 0 INTO eventCount;
                            END IF;
            
                            #Either way we need to know how much TTL/Blacklist to add
                            SELECT '.$prefix.'EVENTS_RULEBOOK.Add_TTL,'.$prefix.'EVENTS_RULEBOOK.Blacklist_For INTO Add_TTL,Blacklist_For FROM '.$prefix.'EVENTS_RULEBOOK WHERE
                                                    Event_Category = 1 AND
                                                    '.$prefix.'EVENTS_RULEBOOK.Event_Type = Event_Type AND
                                                    Sequence_Number<=eventCount ORDER BY Sequence_Number DESC LIMIT 1;
            
                            IF eventCount>0 THEN
                                BEGIN
                                    UPDATE '.$prefix.'USER_EVENTS SET
                                            Sequence_Expires = Sequence_Expires + Add_TTL,
                                            Sequence_Count = eventCount + Add_Weight
                                            WHERE
                                                '.$prefix.'USER_EVENTS.ID = ID AND
                                                '.$prefix.'USER_EVENTS.Event_Type = Event_Type AND
                                                '.$prefix.'USER_EVENTS.Sequence_Expires > UNIX_TIMESTAMP();
            
                                END;
                            ELSE
                                BEGIN
                                INSERT INTO '.$prefix.'USER_EVENTS (
                                    ID,
                                    Event_Type,
                                    Sequence_Expires,
                                    Sequence_Start_Time,
                                    Sequence_Count
                                )
                                VALUES (
                                    ID,
                                    Event_Type,
                                    UNIX_TIMESTAMP()+Add_TTL,
                                    UNIX_TIMESTAMP(),
                                    Add_Weight
                                );
                                END;
                            END IF;
            
                            #We might need to blacklist the USER
                            IF Blacklist_For > 0 THEN
                                IF Suspicious_On_Limit THEN 
                                    UPDATE '.$prefix.'USERS_EXTRA SET
                                            Suspicious_Until = IF(ISNULL(Suspicious_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Suspicious_Until,UNIX_TIMESTAMP()+Blacklist_For))
                                            WHERE
                                                '.$prefix.'USERS_EXTRA.ID = ID;
                                END IF;
                                IF Ban_On_Limit THEN 
                                    UPDATE '.$prefix.'USERS_EXTRA SET
                                            Banned_Until = IF(ISNULL(Banned_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Banned_Until,UNIX_TIMESTAMP()+Blacklist_For))
                                            WHERE
                                                '.$prefix.'USERS_EXTRA.ID = ID;
                                END IF;
                                UPDATE '.$prefix.'USER_EVENTS SET
                                        Sequence_Limited_Until = IF(ISNULL(Sequence_Limited_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Sequence_Limited_Until,UNIX_TIMESTAMP()+Blacklist_For))
                                        WHERE
                                            '.$prefix.'USER_EVENTS.ID = ID AND 
                                            '.$prefix.'USER_EVENTS.Event_Type = Event_Type AND 
                                            '.$prefix.'USER_EVENTS.Sequence_Expires > UNIX_TIMESTAMP();
                            END IF;
            
                            RETURN eventCount+Add_Weight;
                        END
                        '
                    ],
                    [
                        '
                        CREATE FUNCTION '.$prefix.'commitEventUser (
                            ID int(11),
                            Event_Type BIGINT(20) UNSIGNED,
                            Add_Weight INT(10) UNSIGNED,
                            Suspicious_On_Limit BOOLEAN,
                            Ban_On_Limit BOOLEAN,
                            Lock_On_Limit BOOLEAN
                         )
                        RETURNS INT(20)
                        BEGIN
                            DECLARE eventCount INT;
                            DECLARE Add_TTL INT;
                            DECLARE Blacklist_For INT;
                            #Either the event sequence already exists, or a new one needs to be created.
                            SELECT Sequence_Count INTO eventCount FROM
                                       '.$prefix.'USER_EVENTS WHERE(
                                           '.$prefix.'USER_EVENTS.ID = ID AND
                                           '.$prefix.'USER_EVENTS.Event_Type = Event_Type AND
                                           '.$prefix.'USER_EVENTS.Sequence_Expires > UNIX_TIMESTAMP()
                                       )
                                        LIMIT 1;
            
                            #eventCount may be null!
                            IF ISNULL(eventCount) THEN
                                SELECT 0 INTO eventCount;
                            END IF;
            
                            #Either way we need to know how much TTL/Blacklist to add
                            SELECT '.$prefix.'EVENTS_RULEBOOK.Add_TTL,'.$prefix.'EVENTS_RULEBOOK.Blacklist_For INTO Add_TTL,Blacklist_For FROM '.$prefix.'EVENTS_RULEBOOK WHERE
                                                    Event_Category = 1 AND
                                                    '.$prefix.'EVENTS_RULEBOOK.Event_Type = Event_Type AND
                                                    Sequence_Number<=eventCount ORDER BY Sequence_Number DESC LIMIT 1;
            
                            IF eventCount>0 THEN
                                BEGIN
                                    UPDATE '.$prefix.'USER_EVENTS SET
                                            Sequence_Expires = Sequence_Expires + Add_TTL,
                                            Sequence_Count = eventCount + Add_Weight
                                            WHERE
                                                '.$prefix.'USER_EVENTS.ID = ID AND
                                                '.$prefix.'USER_EVENTS.Event_Type = Event_Type AND
                                                '.$prefix.'USER_EVENTS.Sequence_Expires > UNIX_TIMESTAMP();
            
                                END;
                            ELSE
                                BEGIN
                                INSERT INTO '.$prefix.'USER_EVENTS (
                                    ID,
                                    Event_Type,
                                    Sequence_Expires,
                                    Sequence_Start_Time,
                                    Sequence_Count
                                )
                                VALUES (
                                    ID,
                                    Event_Type,
                                    UNIX_TIMESTAMP()+Add_TTL,
                                    UNIX_TIMESTAMP(),
                                    Add_Weight
                                );
                                END;
                            END IF;
            
                            #We might need to blacklist the USER
                            IF Blacklist_For > 0 THEN
                                IF Suspicious_On_Limit THEN 
                                    UPDATE '.$prefix.'USERS_EXTRA SET
                                            Suspicious_Until = IF(ISNULL(Suspicious_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Suspicious_Until,UNIX_TIMESTAMP()+Blacklist_For))
                                            WHERE
                                                '.$prefix.'USERS_EXTRA.ID = ID;
                                END IF;
                                IF Ban_On_Limit THEN 
                                    UPDATE '.$prefix.'USERS_EXTRA SET
                                            Banned_Until = IF(ISNULL(Banned_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Banned_Until,UNIX_TIMESTAMP()+Blacklist_For))
                                            WHERE
                                                '.$prefix.'USERS_EXTRA.ID = ID;
                                END IF;
                                IF Lock_On_Limit THEN 
                                    UPDATE '.$prefix.'USERS_EXTRA SET
                                            Locked_Until = IF(ISNULL(Locked_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Locked_Until,UNIX_TIMESTAMP()+Blacklist_For))
                                            WHERE
                                                '.$prefix.'USERS_EXTRA.ID = ID;
                                END IF;
                                UPDATE '.$prefix.'USER_EVENTS SET
                                        Sequence_Limited_Until = IF(ISNULL(Sequence_Limited_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Sequence_Limited_Until,UNIX_TIMESTAMP()+Blacklist_For))
                                        WHERE
                                            '.$prefix.'USER_EVENTS.ID = ID AND 
                                            '.$prefix.'USER_EVENTS.Event_Type = Event_Type AND 
                                            '.$prefix.'USER_EVENTS.Sequence_Expires > UNIX_TIMESTAMP();
                            END IF;
            
                            RETURN eventCount+Add_Weight;
                        END
                        ',
                        'DROP FUNCTION IF EXISTS `'.$prefix.'commitEventUser`',
                    ],
                    [
                        '
                            ALTER TABLE '.$prefix.'MAIL_TEMPLATES CHANGE `ID` `ID` VARCHAR(256) NOT NULL;
                            UPDATE '.$prefix.'MAIL_TEMPLATES SET `ID` = "default_activation" WHERE '.$prefix.'MAIL_TEMPLATES.`ID` = "1";
                            UPDATE '.$prefix.'MAIL_TEMPLATES SET `ID` = "default_password_reset" WHERE '.$prefix.'MAIL_TEMPLATES.`ID` = "2";
                            UPDATE '.$prefix.'MAIL_TEMPLATES SET `ID` = "default_mail_reset" WHERE '.$prefix.'MAIL_TEMPLATES.`ID` = "3";
                            UPDATE '.$prefix.'MAIL_TEMPLATES SET `ID` = "default_invite" WHERE '.$prefix.'MAIL_TEMPLATES.`ID` = "4" ;
                        ',
                        '
                            UPDATE '.$prefix.'MAIL_TEMPLATES SET `ID` = "1" WHERE '.$prefix.'MAIL_TEMPLATES.`ID` = "default_activation";
                            UPDATE '.$prefix.'MAIL_TEMPLATES SET `ID` = "3" WHERE '.$prefix.'MAIL_TEMPLATES.`ID` = "default_password_reset";
                            UPDATE '.$prefix.'MAIL_TEMPLATES SET `ID` = "3" WHERE '.$prefix.'MAIL_TEMPLATES.`ID` = "default_mail_reset";
                            UPDATE '.$prefix.'MAIL_TEMPLATES SET `ID` = "4" WHERE '.$prefix.'MAIL_TEMPLATES.`ID` = "default_invite" ;
                            ALTER TABLE '.$prefix.'MAIL_TEMPLATES CHANGE `ID` `ID` INT NOT NULL AUTO_INCREMENT; 
                        '
                    ],
                    [
                        'INSERT INTO '.$prefix.'MAIL_TEMPLATE (ID, Title, Content, Created, Last_Updated) VALUES
                                                ( "default_mail_2FA", "Mail 2FA Template", "To log into '.$siteSettings->getSetting('siteName').', please enter the following code: <br>'.
                        ' %%Code%% <br>'.
                        ' The code will expire in '.(int)($userSettings->getSetting('mail2FAExpires')/60).' minutes", "'.time().'", "'.time().'")',
                        'DELETE FROM '.$prefix.'MAIL_TEMPLATE WHERE ID = "default_mail_2FA";'
                    ],
                    [
                        'INSERT INTO '.$prefix.'MAIL_TEMPLATE (ID, Title, Content, Created, Last_Updated) VALUES
                                                ( "default_mail_sus", "Suspicious Account Activity", "Suspicious activity has been detected in your account on '.$siteSettings->getSetting('siteName').'.<br>'.
                                                ' If you did not perform any actions such as failing to log in with an incorrect 2FA code, you should change your password immediately.", "'.time().'", "'.time().'")',
                        'DELETE FROM '.$prefix.'MAIL_TEMPLATE WHERE ID = "default_mail_sus";'
                    ],
                    [
                        'INSERT INTO '.$prefix.'MAIL_TEMPLATE (ID, Title, Content, Created, Last_Updated) VALUES
                                                ( "default_log_report", "Default Logs Report", "Logs report from '.$siteSettings->getSetting("siteName").'<br>'.
                        '%%Summary_Header%% '.
                        '%%Summary_Body%% '.
                        '%%Summary_Footer%% ", "'.time().'", "'.time().'")',
                        'DELETE FROM '.$prefix.'MAIL_TEMPLATE WHERE ID = "default_log_report";'
                    ],
                    [
                        'ALTER TABLE '.$prefix.'USERS CHANGE `Username` `Username` VARCHAR(64) NOT NULL; ',
                        'ALTER TABLE '.$prefix.'USERS CHANGE `Username` `Username` VARCHAR(16) NOT NULL;'
                    ],
                    [
                        'ALTER TABLE '.$prefix.'USERS_EXTRA ADD `Preferred_Language` VARCHAR(16) NULL DEFAULT NULL AFTER `Created`; ',
                        'ALTER TABLE '.$prefix.'USERS_EXTRA DROP `Preferred_Language`;'
                    ],
                    [
                        'CREATE TABLE IF NOT EXISTS '.$prefix.'TAGS (
                                                          Tag_Type varchar(64),
                                                          Tag_Name varchar(64),
                                                          Resource_Type varchar(64) DEFAULT \'img\',
                                                          Resource_Address varchar(512) DEFAULT NULL,
                                                          Meta TEXT DEFAULT NULL,
                                                          Weight int NOT NULL DEFAULT 0,
                                                          Created varchar(14) NOT NULL DEFAULT 0,
                                                          Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                          PRIMARY KEY (Tag_Type,Tag_Name),
                                                          FOREIGN KEY (Resource_Type,Resource_Address)
                                                          REFERENCES '.$prefix.'RESOURCES(Resource_Type,Address)
                                                          ON UPDATE CASCADE ON DELETE SET NULL,
                                                          INDEX (Weight),
                                                          INDEX (Created),
                                                          INDEX (Last_Updated)
                                                          ) ENGINE=InnoDB DEFAULT CHARSET = utf8;',
                        'DROP TABLE '.$prefix.'TAGS;'
                    ],
                    [
                        'CREATE TABLE IF NOT EXISTS '.$prefix.'CATEGORY_TAGS (
                                                          Tag_Type varchar(64),
                                                          Tag_Name varchar(64),
                                                          Category_ID int,
                                                          Resource_Type varchar(64) DEFAULT \'img\',
                                                          Resource_Address varchar(512) DEFAULT NULL,
                                                          Meta TEXT DEFAULT NULL,
                                                          Weight int NOT NULL DEFAULT 0,
                                                          Created varchar(14) NOT NULL DEFAULT 0,
                                                          Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                          PRIMARY KEY (Tag_Type,Category_ID,Tag_Name),
                                                          FOREIGN KEY (Resource_Type,Resource_Address)
                                                          REFERENCES '.$prefix.'RESOURCES(Resource_Type,Address)
                                                          ON UPDATE CASCADE ON DELETE SET NULL,
                                                          INDEX (Weight),
                                                          INDEX (Created),
                                                          INDEX (Last_Updated)
                                                          ) ENGINE=InnoDB DEFAULT CHARSET = utf8;',
                        'DROP TABLE '.$prefix.'CATEGORY_TAGS;'
                    ],
                    [
                        'CREATE TABLE IF NOT EXISTS '.$prefix.'ARTICLE_TAGS (
                                                              Article_ID int,
                                                              Tag_Type varchar(64) DEFAULT \'default-article-tags\',
                                                              Tag_Name varchar(64),
                                                              PRIMARY KEY (Article_ID,Tag_Type,Tag_Name),
                                                              FOREIGN KEY (Article_ID)
                                                              REFERENCES '.$prefix.'ARTICLES(Article_ID)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              FOREIGN KEY (Tag_Type,Tag_Name)
                                                              REFERENCES '.$prefix.'TAGS(Tag_Type,Tag_Name)
                                                              ON DELETE CASCADE ON UPDATE CASCADE
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;',
                        'DROP TABLE '.$prefix.'ARTICLE_TAGS;'
                    ],
                    [
                        'CREATE TABLE IF NOT EXISTS '.$prefix.'LANGUAGE_OBJECTS (
                                                              Object_Name varchar(128) PRIMARY KEY,
                                                              Object TEXT NOT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;',
                        'DROP TABLE '.$prefix.'LANGUAGE_OBJECTS;'
                    ],
                    [
                        'CREATE TABLE IF NOT EXISTS '.$prefix.'DEFAULT_LOGS (
                                                              Channel  varchar(128) NOT NULL,
                                                              Log_Level int(11) NOT NULL,
                                                              Created varchar(14) NOT NULL,
                                                              Node varchar(128) NOT NULL,
                                                              Message TEXT DEFAULT NULL,
                                                              Uploaded varchar(20) NOT NULL DEFAULT 0,
    														  PRIMARY KEY(Channel,Log_Level,Created,Node),
                                                              INDEX (Node),
                                                              INDEX (Uploaded)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;',
                        'DROP TABLE '.$prefix.'DEFAULT_LOGS;'
                    ],
                    [
                        'CREATE TABLE IF NOT EXISTS '.$prefix.'REPORTING_GROUPS (
                                                              Group_Type varchar(64) NOT NULL,
                                                              Group_ID varchar(64) NOT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              Meta TEXT DEFAULT NULL,
    														  PRIMARY KEY(Group_Type,Group_ID),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;',
                        'DROP TABLE '.$prefix.'REPORTING_GROUPS;'
                    ],
                    [
                        'CREATE TABLE IF NOT EXISTS '.$prefix.'REPORTING_RULES (
                                                              Channel  varchar(128) NOT NULL,
                                                              Log_Level int(11) NOT NULL,
                                                              Report_Type varchar(64) NOT NULL,
                                                              Meta TEXT DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
    														  PRIMARY KEY(Channel,Log_Level,Report_Type),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;',
                        'DROP TABLE '.$prefix.'REPORTING_RULES;'
                    ],
                    [
                        'INSERT INTO '.$prefix.'REPORTING_RULES (Channel, Log_Level, Report_Type, Meta, Created, Last_Updated) VALUES '.
                        $newLoggingRulesExp
                        .' ON DUPLICATE KEY UPDATE Channel=VALUES(Channel), Log_Level=VALUES(Log_Level), Report_Type=VALUES(Report_Type), Meta=VALUES(Meta), Created=VALUES(Created), Last_Updated=VALUES(Last_Updated);',
                        'TRUNCATE TABLE '.$prefix.'REPORTING_RULES;'
                    ],
                    [
                        'CREATE TABLE IF NOT EXISTS '.$prefix.'REPORTING_RULE_GROUPS (
                                                              Channel  varchar(128) NOT NULL,
                                                              Log_Level int(11) NOT NULL,
                                                              Report_Type varchar(64) NOT NULL,
                                                              Group_Type varchar(64) NOT NULL,
                                                              Group_ID varchar(64) NOT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
    														  PRIMARY KEY(Channel,Log_Level,Report_Type,Group_Type,Group_ID),
                                                              FOREIGN KEY (Channel,Log_Level,Report_Type)
                                                              REFERENCES '.$prefix.'REPORTING_RULES(Channel,Log_Level,Report_Type)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              FOREIGN KEY (Group_Type, Group_ID)
                                                              REFERENCES '.$prefix.'REPORTING_GROUPS(Group_Type, Group_ID)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;',
                        'DROP TABLE '.$prefix.'REPORTING_RULE_GROUPS;'
                    ],
                    [
                        'CREATE TABLE IF NOT EXISTS '.$prefix.'REPORTING_GROUP_USERS (
                                                              Group_Type varchar(64) NOT NULL,
                                                              Group_ID varchar(64) NOT NULL,
                                                              User_ID  int NOT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
    														  PRIMARY KEY(Group_Type,Group_ID,User_ID),
                                                              FOREIGN KEY (Group_Type, Group_ID)
                                                              REFERENCES '.$prefix.'REPORTING_GROUPS(Group_Type, Group_ID)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              FOREIGN KEY (User_ID)
                                                              REFERENCES '.$prefix.'USERS(ID)
                                                              ON DELETE CASCADE
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;',
                        'DROP TABLE '.$prefix.'REPORTING_GROUP_USERS;'
                    ],
                );

                $newActions['TAGS_SET'] = 'Required to modify tags';
                $newActions['TAGS_DELETE'] = 'Required to delete tags';
                $newActions['TAGS_GET_ADMIN_PARAMS'] = 'Get admin params when getting tags';
                $newActions['LANGUAGE_OBJECTS_SET'] = 'Required to modify language objects';
                $newActions['LANGUAGE_OBJECTS_DELETE'] = 'Required to delete language objects';
                $newActions['LANGUAGE_OBJECTS_GET_ADMIN_PARAMS'] = 'Get unparsed language objects';
                $newActions['LOGS_VIEW'] = 'Required to view logs';
                $newActions['REPORTING_VIEW_GROUPS'] = 'Required to view reporting groups';
                $newActions['REPORTING_SET_GROUPS'] = 'Required to add / modify reporting groups';
                $newActions['REPORTING_REMOVE_GROUPS'] = 'Required to remove reporting groups';
                $newActions['REPORTING_VIEW_GROUP_USERS'] = 'Required to view reporting group users';
                $newActions['REPORTING_MODIFY_GROUP_USERS'] = 'Required to add / remove reporting group users';
                $newActions['REPORTING_VIEW_RULES'] = 'Required to view reporting rules';
                $newActions['REPORTING_SET_RULES'] = 'Required to add / modify reporting rules';
                $newActions['REPORTING_REMOVE_RULES'] = 'Required to remove reporting rules';
                $newActions['REPORTING_VIEW_RULE_GROUPS'] = 'Required to view rule groups and group rules';
                $newActions['REPORTING_MODIFY_RULE_GROUPS'] = 'Required to add / remove reporting rules to / from groups';
                $newActions['MAILING_VIEW_MAILING_LISTS'] = 'Required to view mailing lists';
                $newActions['MAILING_SET_MAILING_LISTS'] = 'Required to add / modify mailing lists, including setting the template';
                $newActions['MAILING_REMOVE_MAILING_LISTS'] = 'Required to remove mailing lists';
                $newActions['MAILING_SEND_TO_MAILING_LIST'] = 'Required to begin mass email sending to mailing list';

                array_push($newSecurityEvents,[1,3,0,60,3600]);
                array_push($newSecurityEvents,[1,3,1,60,0]);
                array_push($newSecurityEvents,[1,3,10,3600,86400]);
                array_push($newSecurityEvents,[1,3,11,3600,0]);
                array_push($newSecurityEvents,[1,4,0,10,86400]);
                array_push($newSecurityEvents,[1,4,1,60,0]);
                array_push($newSecurityEvents,[1,4,3,180,86400]);
                array_push($newSecurityEvents,[1,4,4,3600,2678400]);
                array_push($newSecurityEvents,[1,4,5,3600,0]);

                array_push(
                    $newSecurityEventsMeta,
                    [
                        'category'=>1,
                        'type'=>3,
                        'meta'=>json_encode([
                            'name'=>'User 2FA Mail Limit'
                        ])
                    ],
                    [
                        'category'=>1,
                        'type'=>4,
                        'meta'=>json_encode([
                            'name'=>'Incorrect 2FA Code General Limit'
                        ])
                    ]
                );

                //Update all setting tables
                $metaSettings = new \IOFrame\Handlers\SettingsHandler(
                    $settings->getSetting('absPathToRoot').'/localFiles/metaSettings/',
                    $defaultSettingsParams
                );

                foreach ($metaSettings->getSettings() as $setting=>$settingJson){
                    $settingJson = json_decode($settingJson,true);
                    if($settingJson['db'])
                        array_push(
                            $newQueries,
                            [
                                'ALTER TABLE '.strtoupper($SQLManager->getSQLPrefix().\IOFrame\Handlers\SettingsHandler::SETTINGS_TABLE_PREFIX.$setting).' CHANGE `settingValue` `settingValue` TEXT NOT NULL',
                                'ALTER TABLE '.strtoupper($SQLManager->getSQLPrefix().\IOFrame\Handlers\SettingsHandler::SETTINGS_TABLE_PREFIX.$setting).' CHANGE `settingValue` `settingValue` VARCHAR(256) NOT NULL',
                            ]
                        );
                }

                $newSettingFiles['tagSettings'] = ['type'=>'db','base64'=>1,'title'=>'Tag Settings'];
                $newSettingFiles['logSettings'] = ['type'=>'db', 'title'=>'Logging Settings'];
                $newSettings['logSettings'] = [
                    ['name'=>'secStatus','value'=>\Monolog\Logger::NOTICE,'createNew'=>true],
                    ['name'=>'logStatus','value'=>\Monolog\Logger::NOTICE,'createNew'=>true],
                    ['name'=>'logs_sql_table_prefix','value'=>'','createNew'=>true],
                    ['name'=>'logs_sql_server_addr','value'=>'','createNew'=>true],
                    ['name'=>'logs_sql_server_port','value'=>'','createNew'=>true],
                    ['name'=>'logs_sql_username','value'=>'','createNew'=>true],
                    ['name'=>'logs_sql_password','value'=>'','createNew'=>true],
                    ['name'=>'logs_sql_persistent','value'=>'','createNew'=>true],
                    ['name'=>'logs_sql_db_name','value'=>'','createNew'=>true],
                    ['name'=>'defaultReportMailTemplate','value'=>'default_log_report','createNew'=>true],
                    ['name'=>'defaultReportMailTitle','value'=>'Default Logs Report','createNew'=>true],
                    ['name'=>'defaultChannels','value'=>implode(
                        ',',
                        [
                            \IOFrame\Definitions::LOG_DEFAULT_CHANNEL,
                            \IOFrame\Definitions::LOG_GENERAL_SECURITY_CHANNEL,
                            \IOFrame\Definitions::LOG_USERS_CHANNEL,
                            \IOFrame\Definitions::LOG_TOKENS_CHANNEL,
                            \IOFrame\Definitions::LOG_TAGS_CHANNEL,
                            \IOFrame\Definitions::LOG_SETTINGS_CHANNEL,
                            \IOFrame\Definitions::LOG_ROUTING_CHANNEL,
                            \IOFrame\Definitions::LOG_RESOURCES_CHANNEL,
                            \IOFrame\Definitions::LOG_ORDERS_CHANNEL,
                            \IOFrame\Definitions::LOG_PLUGINS_CHANNEL,
                            \IOFrame\Definitions::LOG_MAILING_CHANNEL,
                            \IOFrame\Definitions::LOG_CLI_TESTING_CHANNEL,
                            \IOFrame\Definitions::LOG_CLI_JOBS_CHANNEL
                        ]
                    ),'createNew'=>true],
                ];
                $newSettings['tagSettings'] = [
                    [
                        'name'=>'availableTagTypes',
                        'value'=>json_encode(
                            ['default-article-tags'=>['title'=>'Default Article Tags','img'=>true,'img_empty_url'=>'ioframe/img/icons/upload.svg','extraMetaParameters'=>['eng'=>['title'=>'Tag Title','color'=>false]]]]
                        ),
                        'createNew'=>true,
                        'override'=>false
                    ],
                    ['name'=>'availableCategoryTagTypes','value'=>'','createNew'=>true,'override'=>false]
                ];
                $newSettings['siteSettings'] = [
                    ['name'=>'languagesMap','value'=>'{"eng":{"flag":"gb","title":"English"}}','createNew'=>true,'override'=>false],
                    ['name'=>'defaultLanguage','value'=>'eng','createNew'=>true,'override'=>false],
                    ['name'=>'allowTesting','value'=>'0','createNew'=>true,'override'=>false],
                    ['name'=>'devMode','value'=>'0','createNew'=>true,'override'=>false],
                    ['name'=>'enableStickyCookie','value'=>'0','createNew'=>true,'override'=>false],
                    ['name'=>'stickyCookieDuration','value'=>'0','createNew'=>true,'override'=>false],
                    ['name'=>'maxObjectSize','value'=>null,'override'=>true],
                    ['name'=>'secStatus','value'=>null,'override'=>true],
                    ['name'=>'logStatus','value'=>null,'override'=>true],
                ];
                $newSettings['userSettings'] = [
                    ['name'=>'rememberMeLimit','value'=>31536000,'createNew'=>true,'override'=>true],
                    ['name'=>'regConfirmTemplate','default_activation','createNew'=>true,'override'=>true],
                    ['name'=>'pwdResetTemplate','default_password_reset','createNew'=>true,'override'=>true],
                    ['name'=>'emailChangeTemplate','default_mail_reset','createNew'=>true,'override'=>true],
                    ['name'=>'inviteMailTemplate','default_invite','createNew'=>true,'override'=>true],
                    ['name'=>'email2FATemplate','default_mail_2FA','createNew'=>true,'override'=>true],
                    ['name'=>'email2FATitle','Two Factor Authentication Mail','createNew'=>true,'override'=>true],
                    ['name'=>'email2FATemplate','default_mail_sus','createNew'=>true,'override'=>true],
                    ['name'=>'email2FATitle','Suspicious Account Activity','createNew'=>true,'override'=>true],
                ];
                $newSettings['localSettings'] = [
                    ['name'=>'nodeID','value'=>\IOFrame\Util\PureUtilFunctions::GeraHash(12),'createNew'=>true,'override'=>false],
                    ['name'=>'highScalability','value'=>0,'createNew'=>true,'override'=>false],
                    ['name'=>'_templates_default','value'=>'','createNew'=>true,'override'=>false],
                    ['name'=>'_templates_maintenance_local','value'=>'','createNew'=>true,'override'=>false],
                    ['name'=>'_templates_maintenance_global','value'=>'','createNew'=>true,'override'=>false],
                    ['name'=>'_templates_plugins_mismatch','value'=>'','createNew'=>true,'override'=>false],
                    ['name'=>'_templates_page_not_found','value'=>'','createNew'=>true,'override'=>false],
                    ['name'=>'_templates_user_banned','value'=>'','createNew'=>true,'override'=>false],
                    ['name'=>'_templates_ip_blacklisted','value'=>'','createNew'=>true,'override'=>false],
                    ['name'=>'_templates_unauthorized_generic','value'=>'','createNew'=>true,'override'=>false]
                ];
                $newSettings['apiSettings'] = [
                    ['name'=>'allowTesting','value'=>null,'override'=>true],
                    ['name'=>'articles','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>['getArticles','getArticle'] ]),'override'=>true],
                    ['name'=>'auth','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>['getRank','isLoggedIn'] ]),'override'=>true],
                    ['name'=>'contacts','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>['getContactTypes'] ]),'override'=>true],
                    ['name'=>'language-objects','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>['getLanguageObjects','setPreferredLanguage'] ]),'createNew'=>true],
                    ['name'=>'logs','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>[] ]),'createNew'=>true],
                    ['name'=>'mail','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>[] ]),'override'=>true],
                    ['name'=>'media','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>['getDBMedia','getGallery','getVideoGallery'] ]),'override'=>true],
                    ['name'=>'menu','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>['getMenu'] ]),'override'=>true],
                    ['name'=>'object-auth','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>[] ]),'override'=>true],
                    ['name'=>'objects','value'=>null,'override'=>true],
                    ['name'=>'orders','value'=>json_encode(['active'=>0,'allowUserBannedActions'=>['getOrder']]),'override'=>true],
                    ['name'=>'plugins','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>[] ]),'override'=>true],
                    ['name'=>'security','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>[] ]),'override'=>true],
                    ['name'=>'session','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>[] ]),'override'=>true],
                    ['name'=>'settings','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>[] ]),'override'=>true],
                    ['name'=>'tags','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>['getBaseTags','getCategoryTags','getManifest'] ]),'createNew'=>true],
                    ['name'=>'tokens','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>[] ]),'override'=>true],
                    ['name'=>'trees','value'=>null,'override'=>true],
                    ['name'=>'users','value'=>json_encode(['active'=>1,'allowUserBannedActions'=>['getMyUser','require2FA','requestApp2FA','confirmApp','logUser','pwdReset','changePassword','regConfirm','mailReset','changeMail'] ]),'override'=>true],
                ];
                $newSettings['pageSettings'] = [
                    ['name'=>'isSPA','value'=>'0','createNew'=>true,'override'=>false]
                ];

                $newMatches['front'] = ['front/ioframe/pages/[trailing]', 'php,html,htm',true];
                break;
            default:
        }

        //Add stuff to test to empty arrays
        if($populateTest){

            if(count($newSettingFiles)===0){
                $newSettingFiles['updateLocalTestSettings'] = ['type'=>'local','title'=>'Local Test Settings'];
                $newSettingFiles['updateDBTestSettings'] = ['type'=>'db','title'=>'DB Test Settings'];
            }

            if(count($newSettings)===0){
                $newSettings['localSettings'] = [['name'=>'updateTest','value'=>'test']];
            }

            if(count($newActions)===0){
                $newActions['TEST_ACTION'] = 'Some action meant for testing';
            }

            if(count($newSecurityEvents)===0){
                array_push($newSecurityEvents,[999,99999,0,0,86400]);
            }

            if(count($newSecurityEventsMeta)===0){
                array_push(
                    $newSecurityEventsMeta,
                    [
                        'category'=>999,
                        'type'=>99999,
                        'meta'=>json_encode([
                            'name'=>'IP Incorrect Login Limit'
                        ])
                    ]
                );
            }

            if(count($newRoutes)===0)
                $newRoutes['test'] = ['GET|POST', 'test', 'test', null];

            if(count($newMatches)===0){
                $newMatches['test'] = ['test/[trailing]', 'php,html,htm'];
                $newMatches['api'] = ['api/[trailing]','php'];
            }

            if(count($newQueries)===0)
                array_push(
                    $newQueries,
                    [
                        'CREATE TABLE IF NOT EXISTS '.$prefix.'TEST (
                              Test_1 varchar(16) NOT NULL,
                              Test_2 varchar(45) NOT NULL,
                              PRIMARY KEY (Test_1, Test_2)
                          ) ENGINE=InnoDB DEFAULT CHARSET = utf8;',
                        'DROP TABLE ".$prefix."TEST;'
                    ],
                    [
                        'ALTER TABLE '.$prefix.'TEST ADD Test_3 INT NULL DEFAULT NULL AFTER Test_2;',
                        'ALTER TABLE '.$prefix.'TEST DROP Test_3;'
                    ]
                );
        }

        /* Updates */
        while($currentStage < count($updateStages)){
            $stageSuccess = false;
            switch ($updateStages[$currentStage]){
                case 'customActions':
                    switch ($next){
                        case '1.1.0.0':
                            $stageSuccess = true;
                            break;
                        case '1.1.1.0':
                            if($opModeExcludesDB){
                                $stageSuccess = true;
                                break;
                            }
                            $userSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/userSettings/',$defaultSettingsParams);
                            $insertedMailTemplateID = $SQLManager->insertIntoTable(
                                $prefix.'MAIL_TEMPLATES',
                                ['Title', 'Content'],
                                [
                                    [
                                        ['Invite Mail Default Template','STRING'],
                                        [
                                            \IOFrame\Util\SafeSTRFunctions::str2SafeStr('Hello!<br> You\'ve been invited to join '.
                                            $siteSettings->getSetting('siteName').
                                                '. Click <a href="https://' .$_SERVER['HTTP_HOST'].$settings->getSetting('pathToRoot').
                                            'api/users?action=checkInvite&mail=%%mail%%&token=%%token%%">this link</a> to accept the invite.<br> The invite will expire in '.
                                            (($userSettings->getSetting('inviteExpires')?$userSettings->getSetting('inviteExpires'):774)/24).' days'),
                                            'STRING'
                                        ]
                                    ]
                                ],
                                ['test'=>$test,'returnRows'=>true]
                            );
                            $stageSuccess = $insertedMailTemplateID > 0;
                            array_push($newSettings['userSettings'],['name'=>'inviteMailTemplate','value'=>$insertedMailTemplateID]);
                            break;
                        case '1.2.0.0':

                            $query = 'ALTER TABLE '.$prefix.'ROUTING_MATCH ADD `Match_Partial_URL` BOOLEAN NOT NULL DEFAULT FALSE AFTER `Extensions`;';
                            if($test)
                                echo 'Query To execute: '.$query.EOL;
                            else
                                $stageSuccess = $SQLManager->exeQueryBindParam($query,[],['test'=>$test]);
                            if(!$stageSuccess)
                                break;

                            $RouteHandler = new \IOFrame\Handlers\RouteHandler($settings,$defaultSettingsParams);

                            $routingMatch = false;
                            $routingOriginalMethods = 'GET|POST';
                            foreach ($RouteHandler->getActiveRoutes(['test'=>$test]) as $item){
                                if($item['Match_Name'] === 'api'){
                                    $routingMatch = $item['ID'];
                                    $routingOriginalMethods = $item['Method'];
                                    break;
                                }
                            }
                            //TODO updateRoute wont work with > v2 code - update this to work properly with the new code, on an older system
                            $stageSuccess = $routingMatch &&
                                ($RouteHandler->setRoute($routingMatch,'GET|POST|PUT|DELETE|PATCH|HEAD','api/[*:trailing]','api',null,['test'=>$test]) === 0) &&
                                ($RouteHandler->setMatch('api','api/[trailing]',['php'],true) === 0);

                            break;
                        case '2.0.0.0rc':
                            break;
                        default:
                            $stageSuccess = true;
                    }
                    break;
                case 'settingFiles':
                    $metaSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/metaSettings/',$defaultSettingsParams);
                    $failedSetting = false;
                    foreach($newSettingFiles as $name => $info){
                        $db = !empty($info['type']) && $info['type'] === 'db';
                        $title = empty($info['title']) ? $name : $info['title'];
                        $base64 = empty($info['base64']) ? 0 : $info['base64'];
                        if(!is_dir($rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$name) && !$opModeExcludesLocal){
                            if(!$test){
                                if(!mkdir($rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$name)){
                                    $failedSetting = true;
                                    break;
                                }
                                fclose(fopen($rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$name.'/settings','w'));
                            }
                            else
                                echo 'Creating local settings directory '.$name.EOL;
                        }
                        if($db && !$opModeExcludesDB) {
                            $newSettingsFile = new \IOFrame\Handlers\SettingsHandler(
                                $rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$name.'/',
                                array_merge($defaultSettingsParams,['opMode'=>$opMode])
                            );
                            $failedSetting = !$newSettingsFile->initDB(['test'=>$test]);
                        }
                        if(!$failedSetting)
                            $failedSetting = !$metaSettings->setSetting($name,json_encode(['local'=>!$db,'db'=>$db,'base64'=>$base64,'title'=>$title]),['createNew'=>true,'test'=>$test]);
                    }
                    if($failedSetting)
                        break;
                    $stageSuccess = true;
                    break;
                case 'settings':
                    $metaSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/metaSettings/',$defaultSettingsParams);
                    $failedSettings = false;
                    foreach($newSettings as $name => $newSettingsArray){

                        $metaSettingsInfo = $metaSettings->getSetting($name);
                        $localSetting = true;
                        $base64 = false;
                        $mergeArr = [];
                        if(!empty($metaSettingsInfo) && \IOFrame\Util\PureUtilFunctions::is_json($metaSettingsInfo)){
                            $metaSettingsInfo = json_decode($metaSettingsInfo,true);
                            $localSetting = (bool)($metaSettingsInfo['local'] ?? !$metaSettingsInfo['db'] ?? false);
                            $base64 = (bool)($metaSettingsInfo['base64'] ?? false);
                        }
                        if( (!$localSetting && $opModeExcludesDB) || ($localSetting && $opModeExcludesLocal) )
                            continue;

                        if($localSetting)
                            $mergeArr['opMode'] = \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL;
                        if($base64)
                            $mergeArr['base64Storage'] = true;

                        $changedSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$name.'/',array_merge($defaultSettingsParams,$mergeArr));
                        $oldSettings[$name] = [];
                        $oldSettings[$name]['original'] = $changedSettings->getSettings();
                        $oldSettings[$name]['changed'] = [];
                        if($oldSettings[$name]['original'] === false){
                            $failedSettings = true;
                            break;
                        }
                        $failedSetting = false;

                        $toSet = [];

                        foreach($newSettingsArray as $index => $settingArr){
                            $settingName = $settingArr['name'];
                            $settingValue = $settingArr['value'];
                            $overrideSetting = !isset($settingArr['override']) || (bool)$settingArr['override'];
                            $createNewSetting = !isset($settingArr['createNew']) || (bool)$settingArr['createNew'];
                            $existingSetting = isset($oldSettings[$name]['original'][$settingName])?$oldSettings[$name]['original'][$settingName]: null;
                            if( ($existingSetting !== null && !$overrideSetting) || ($existingSetting === null && !$createNewSetting) )
                                continue;
                            else
                                $toSet[$settingName] = $settingValue;
                        }

                        $settingResults = $changedSettings->setSettings($toSet,['test'=>$test,'createNew'=>$createNewSetting,'backUp'=>( $index === (count($newSettingsArray) - 1) )]);

                        foreach ($settingResults as $settingName => $result){
                            if($result === true)
                                array_push($oldSettings[$name]['changed'],$settingName);
                            else
                                $failedSetting = true;
                        }

                        if($failedSetting){
                            $failedSettings = true;
                            break;
                        }

                    }
                    if($failedSettings)
                        break;
                    $stageSuccess = true;
                    break;
                case 'actions':
                    if($opModeExcludesDB || (count($newActions) === 0) ){
                        $stageSuccess = true;
                        break;
                    }
                    $allActions = $auth->getActions(['test'=>$test]);
                    foreach ($newActions as $action=>$desc){
                        $oldActions[$action] = isset($allActions[$action]) ? $allActions[$action] : null;
                    }
                    if(!$auth->setActions($newActions,['test'=>$test]))
                         break;
                    $stageSuccess = true;
                    break;
                case 'securityEvents':
                    if($opModeExcludesDB || (count($newSecurityEvents) === 0) ){
                        $stageSuccess = true;
                        break;
                    }
                    $SecurityHandler = new \IOFrame\Handlers\SecurityHandler(
                        $settings,
                        $defaultSettingsParams
                    );
                    $stuffToAdd = [];
                    $allRules = $SecurityHandler->getRulebookRules(['test'=>$test]);
                    foreach ($newSecurityEvents as $newEventArray){
                        $oldSecurityEvents[$newEventArray[0].'/'.$newEventArray[1].'/'.$newEventArray[2]] =
                            isset($allRules[$newEventArray[0].'/'.$newEventArray[1]][$newEventArray[2]]) ?
                                $allRules[$newEventArray[0].'/'.$newEventArray[1]][$newEventArray[2]] : null;
                        array_push($stuffToAdd,[
                            'category'=>$newEventArray[0],
                            'type'=>$newEventArray[1],
                            'sequence'=>$newEventArray[2],
                            'addTTL'=>$newEventArray[3],
                            'blacklistFor'=>$newEventArray[4],
                        ]);
                    }
                    $stageSuccess = $SecurityHandler->setRulebookRules($stuffToAdd,['test'=>$test]);
                    foreach ($stageSuccess as $securityEventId => $securityEventRes){
                        if($securityEventRes !== 0){
                            $stageSuccess = false;
                            break;
                        }
                    }
                    if($stageSuccess !== false)
                        $stageSuccess = true;
                    break;
                case 'securityEventsMeta':
                    if($opModeExcludesDB || (count($newSecurityEventsMeta) === 0) ) {
                        $stageSuccess = true;
                        break;
                    }
                    $SecurityHandler = new \IOFrame\Handlers\SecurityHandler(
                        $settings,
                        $defaultSettingsParams
                    );
                    $allMeta = $SecurityHandler->getEventsMeta(['test'=>$test]);
                    $stuffToAdd = [];
                    foreach ($newSecurityEventsMeta as $newMetaArray){
                        $oldSecurityEventsMeta[$newMetaArray['category'].'/'.$newMetaArray['type']] =
                            isset($allMeta[$newMetaArray['category'].'/'.$newMetaArray['type']]) ?
                                $allMeta[$newMetaArray['category'].'/'.$newMetaArray['type']] : null;
                        array_push($stuffToAdd,[
                            'category'=>$newMetaArray['category'],
                            'type'=>$newMetaArray['type'],
                            'meta'=>$newMetaArray['meta']
                        ]);
                    }
                    $stageSuccess = $SecurityHandler->setEventsMeta($newSecurityEventsMeta,['test'=>$test]);
                    foreach ($stageSuccess as $securityEventId => $securityEventRes){
                        if($securityEventRes !== 0){
                            $stageSuccess = false;
                            break;
                        }
                    }
                    if($stageSuccess !== false)
                        $stageSuccess = true;
                    break;
                case 'routes':
                    if($opModeExcludesDB || (count($newRoutes) === 0) ) {
                        $stageSuccess = true;
                        break;
                    }
                    $RouteHandler = new \IOFrame\Handlers\RouteHandler(
                        $settings,
                        $defaultSettingsParams
                    );
                    $newRouteId = $RouteHandler->setRoutes($newRoutes,['test'=>$test]);
                    $stageSuccess = ($newRouteId>0);
                    break;
                case 'matches':
                    if($opModeExcludesDB || (count($newMatches) === 0) ) {
                        $stageSuccess = true;
                        break;
                    }
                    $RouteHandler = new \IOFrame\Handlers\RouteHandler(
                        $settings,
                        $defaultSettingsParams
                    );
                    $matchesToGet = [];
                    foreach ($newMatches as $matchName => $matchArray){
                        array_push($matchesToGet,$matchName);
                    }
                    $oldMatches = $RouteHandler->getMatches($matchesToGet,['test'=>$test]);
                    $setMatches = $RouteHandler->setMatches($newMatches,['test'=>$test]);
                    foreach ($setMatches as $matchName => $result){
                        if($result !== 0)
                            break;
                    }
                    $stageSuccess = true;
                    break;
                case 'queries':
                    if($opModeExcludesDB || (count($newQueries) === 0) ) {
                        $stageSuccess = true;
                        break;
                    }
                    foreach ($newQueries as $queryPair){
                        if($test)
                            echo 'Query To execute: '.$queryPair[0].EOL;
                        elseif($SQLManager->exeQueryBindParam($queryPair[0]) === true)
                            $queriesSucceeded++;
                        else
                            break;
                    }
                    $stageSuccess = true;
                    break;
                case 'increaseVersion':
                    $stageSuccess = $opModeExcludesDB || $siteSettings->setSetting('ver', $next, ['test' => $test]);
                    break;
            }

            if($stageSuccess)
                $currentStage++;
            else
                break;
        }

        /* On failure, rollbacks - if we didn't reach the end of the update stages array, it means something went wrong */
        if( $currentStage < count($updateStages) ){
            while($currentStage >= 0){
                $earlyFailure = false;
                switch ($updateStages[$currentStage]){
                    case 'customActions':
                        switch ($next){
                            case '1.1.1.0':
                                if($insertedMailTemplateID > 0)
                                    $earlyFailure = !$SQLManager->deleteFromTable(
                                        $prefix.'MAIL_TEMPLATES',
                                        [
                                            [
                                                'ID',
                                                $insertedMailTemplateID,
                                                '='
                                            ]
                                        ],
                                        ['test'=>$test]
                                    );
                                break;
                            case '1.2.0.0':
                                //TODO updateRoute wont work with > v2 code - update this for an older system
                                $earlyFailure = false;
                                if($routingMatch)
                                    $earlyFailure = $RouteHandler->setRoute((int)$routingMatch,$routingOriginalMethods,'api/[*:trailing]','api',null,['test'=>$test]) !== 0;
                                if(!$earlyFailure)
                                    $earlyFailure = !$SQLManager->exeQueryBindParam('ALTER TABLE '.$prefix.'ROUTING_MAP DROP Match_Partial_URL;',[],['test'=>$test]);
                                break;
                            case '1.2.0.1':
                                break;
                            case '1.2.1.0':
                            case '1.2.2.0':
                            case '1.2.2.1':
                            case '1.2.2.2':
                            case '2.0.0.0rc':
                                break;
                            default:
                        }
                        break;
                    case 'settingFiles':
                        //Delete new setting files ONLY from "meta" - the files/tables will still exist, just not be indexed.
                        foreach($newSettingFiles as $name => $info){
                            if(!$metaSettings->setSetting($name,json_encode(['local'=>!$db,'db'=>$db,'title'=>$title]),['createNew'=>true,'test'=>$test])){
                                $earlyFailure = true;
                                break;
                            }
                        }
                        break;
                    case 'settings':
                        //Delete all new settings added earlier && Reset all settings changed earlier
                        foreach($oldSettings as $settingsName =>$settingsArr){
                            $changedSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$settingsName.'/',$defaultSettingsParams);
                            foreach($settingsArr['changed'] as $index => $changedSettingName){
                                $rollbackValue = $settingsArr['original'][$changedSettingName] ?? null;
                                //TODO Confirm this works correctly with db/local definitions from metaSettings, and opMode
                                if(!$changedSettings->setSetting($changedSettingName,$rollbackValue,['test'=>$test,'backUp'=>( $index === (count($settingsArr['changed']) - 1) )])){
                                    $earlyFailure = true;
                                    break;
                                }
                            }
                        }
                        break;
                    case 'actions':
                        if($opModeExcludesDB || count($oldActions) <= 0)
                            break;
                        $deleteActions = [];
                        $resetActions = [];
                        foreach ($oldActions as $action=>$arr){
                            // Reset all actions changed earlier
                            if($arr)
                                $resetActions[$action] = !empty($arr['description'])?$arr['description']:null;
                            //Remove all new actions created earlier
                            else
                                array_push($deleteActions,$action);
                        }
                        if(count($resetActions) > 0 && !$auth->setActions($resetActions,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        }
                        if(count($deleteActions) > 0 && !$auth->deleteActions($deleteActions,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        }
                        break;
                    case 'securityEvents':
                        if($opModeExcludesDB || count($oldSecurityEvents) <= 0)
                            break;
                        $deleteEvents = [];
                        $resetEvents = [];
                        foreach ($oldSecurityEvents as $event=>$arr){
                            $identifier = explode($event,'/');
                            //Reset all security events changed earlier
                            if($arr)
                                array_push($resetEvents,[
                                    'category'=>$identifier[0],
                                    'type'=>$identifier[1],
                                    'sequence'=>$identifier[2],
                                    'addTTL'=>$arr['Blacklist_For'],
                                    'blacklistFor'=>$arr['Add_TTL']
                                ]);
                            //Remove all new security events created earlier
                            else
                                array_push($deleteEvents,[
                                    'category'=>$identifier[0],
                                    'type'=>$identifier[1],
                                    'sequence'=>$identifier[2]
                                ]);
                        }
                        if(count($resetEvents) > 0 && !$auth->setRulebookRules($resetEvents,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        }
                        if(count($deleteEvents) > 0 && !$auth->deleteRulebookRules($deleteEvents,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        }
                        break;
                    case 'securityEventsMeta':
                        if($opModeExcludesDB || count($oldSecurityEventsMeta) <= 0)
                            break;
                        $deleteEventsMeta = [];
                        $resetEventsMeta = [];
                        foreach ($oldSecurityEventsMeta as $event=>$arr){
                            $identifier = explode($event,'/');
                            //Reset all security events changed earlier
                            if($arr)
                                array_push($resetEventsMeta,[
                                    'category'=>$identifier[0],
                                    'type'=>$identifier[1],
                                    'meta'=>$arr['Meta']
                                ]);
                            //Remove all new security events created earlier
                            else
                                array_push($deleteEventsMeta,[
                                    'category'=>$identifier[0],
                                    'type'=>$identifier[1]
                                ]);
                        }
                        if(count($resetEventsMeta) > 0 && !$auth->setEventsMeta($resetEventsMeta,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        }
                        if(count($deleteEventsMeta) > 0 && !$auth->deleteEventsMeta($deleteEventsMeta,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        }
                        break;
                    case 'routes':
                        if($opModeExcludesDB || $newRouteId <= 0)
                            break;
                        $removeIDs = [];
                        for($i = $newRouteId; $i<$newRouteId+count($newRoutes); $i++)
                            array_push($removeIDs,$i);
                        //Remove all new routes added earlier
                        if(!$RouteHandler->deleteRoutes($removeIDs,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        }
                        break;
                    case 'matches':
                        if($opModeExcludesDB || count($oldMatches) <= 0)
                            break;
                        $deleteMatches = [];
                        $resetMatches = [];
                        foreach ($oldMatches as $match=>$arr){
                            //Reset all matches changed earlier
                            if($arr)
                                $resetMatches[$match] = [
                                    \IOFrame\Util\PureUtilFunctions::is_json($arr['URL'])? json_decode($arr['URL'],true) : $arr['URL'],
                                    !empty($arr['Extensions'])?$arr['Extensions']:null
                                ];
                            //Remove all new matches created earlier
                            else
                                array_push($deleteMatches,$match);
                        }
                        if(count($resetMatches) > 0 && !$RouteHandler->setMatches($resetMatches,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        }
                        if(count($deleteMatches) > 0 && !$RouteHandler->deleteMatches($deleteMatches,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        }
                        break;
                    case 'queries':
                        //Undo all queries executed earlier
                        if($opModeExcludesDB || (count($newQueries) === 0) )
                            break;
                        foreach ($newQueries as $queryPair){
                            if($test)
                                echo 'Query To execute: '.$queryPair[1].EOL;
                            elseif($SQLManager->exeQueryBindParam($queryPair[1]) === false){
                                $earlyFailure = true;
                                break;
                            }
                        }
                        break;
                    case 'increaseVersion':
                        $earlyFailure = !$opModeExcludesDB && !$siteSettings->setSetting('ver', $currentVersion);
                        break;
                }
                if($earlyFailure)
                    break;
                else
                    $currentStage--;
            }
        }

        //Exit maintenance if it was set
        if($maintenance){
            if(!$opModeExcludesLocal){
                $settings->setSetting('_maintenance',null,['test'=>$test,'verbose'=>$verbose,'createNew'=>true]);
            }
            if(!$opModeExcludesDB){
                $siteSettings->setSetting('_maintenance',null,['test'=>$test,'verbose'=>$verbose,'createNew'=>true]);
            }
        }

        /* If we are back at stage -1, it means there was a successful rollback*/
        if($currentStage === -1)
            $finalResult = '1';
        /* If we didn't reach the end OR start of the update stages, it means catastrophic failure happened somewhere along the way*/
        elseif($currentStage < count($updateStages))
            $finalResult = '-1';
        else
            $finalResult = '0';

        if(!$cli)
            die($finalResult);
        else{
            if($finalResult !== '0'){
                switch ($finalResult){
                    case '1':
                        $errors['failure-then-rollback'] = true;
                        break;
                    case '-1':
                        $errors['catastrophic-failure'] = true;
                        break;
                    default:
                        $errors['unknown-error'] = true;
                        break;
                }
            }
            return $finalResult;
        }

    default:
        exit('Specified action is not recognized');
}



