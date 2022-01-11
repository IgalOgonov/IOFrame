<?php

/* This the the API that handles system updates.
 * Unlike all other APIs, it also includes the ability to be used via the CLI for local updates (mainly creating new setting files)
 *
 * See standard return values at defaultInputResults.php
 *
 * Parameters:
 * "action"     - Requested action - described bellow
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

if(!$cli && !defined('coreInit'))
    require __DIR__ . '/../main/coreInit.php';

if(!$cli){
    require 'apiSettingsChecks.php';
    require 'defaultInputChecks.php';
    require 'CSRF.php';

    if(!isset($_REQUEST["action"]))
        exit('Action not specified!');
    $action = $_REQUEST["action"];
}
else{
    if(!defined('EOL'))
        define('EOL',PHP_EOL);
    $flags = getopt('htv:');
    if(isset($flags['h']))
        die('Available flags are:'.EOL.'
    -h Displays this help message'.EOL.'
    -t Testing mode (do no actually update)'.EOL.'
    -v Current Version (from which to update) [REQUIRED]'.EOL.'
    ');
    $test = isset($flags['t']);
    $action = 'update';
}

require 'defaultInputResults.php';
require 'update_fragments/definitions.php';

if($test)
    echo 'Testing mode!'.EOL;

$populateTest = $test && !empty($_REQUEST['populateTest']);

//Authorize use
if(!$cli && !($auth->isAuthorized(0) || $auth->hasAction(CAN_UPDATE_SYSTEM)) ){
    if($test)
        echo 'Only admins may use this API!';
    exit(AUTHENTICATION_FAILURE);
}


switch($action){

    case 'getVersionsInfo':
        if(!isset($FileHandler))
            $FileHandler = new IOFrame\Handlers\FileHandler();
        $availableVersion = $FileHandler->readFile($rootFolder.'/meta/', 'ver');
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

    case 'update':

        if(!$cli && !validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        /*Initiate some stuff in case we are in CLI mode*/
        if($cli){
            if(empty($flags['v']))
                die('Current required for update!');
            else
                $currentVersion =$flags['v'];
            if(!defined('helperFunctions'))
                require __DIR__.'/../IOFrame/Util/helperFunctions.php';
            $rootFolder = IOFrame\Util\replaceInString('\\','/',str_replace('\\api','',__DIR__)).'/';;
            $defaultSettingsParams = [];
            if(!defined("EOL"))
                define("EOL",PHP_EOL);
            $prefix = '';
            if(!defined('SettingsHandler'))
                require __DIR__.'/../IOFrame/Handlers/SettingsHandler.php';
        }
        else{
            $prefix = $SQLHandler->getSQLPrefix();
            $currentVersion = $siteSettings->getSetting('ver');
        }
        $next = !empty($versionArray[$currentVersion])? $versionArray[$currentVersion] : null;
        if(!$next)
            die(2);

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
                $newSettings['localSettings'] = ['name'=>'updateTest','value'=>'test'];
            }

            if(count($newActions)===0){
                $newActions['TEST_ACTION'] = 'Some action meant for resting';
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
                array_push($newRoutes,['GET|POST','test','test',null]);

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
                            if($cli){
                                $stageSuccess = true;
                                break;
                            }
                            $userSettings = new IOFrame\Handlers\SettingsHandler($rootFolder.SETTINGS_DIR_FROM_ROOT.'/userSettings/',$defaultSettingsParams);
                            $insertedMailTemplateID = $SQLHandler->insertIntoTable(
                                $prefix.'MAIL_TEMPLATES',
                                ['Title', 'Content'],
                                [
                                    [
                                        ['Invite Mail Default Template','STRING'],
                                        [
                                            \IOFrame\Util\str2SafeStr('Hello!<br> You\'ve been invited to join '.
                                            $siteSettings->getSetting('siteName').
                                            '. Click <a href="http://'.$_SERVER['HTTP_HOST'].$settings->getSetting('pathToRoot').
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
                                $stageSuccess = $SQLHandler->exeQueryBindParam($query,[],['test'=>$test]);
                            if(!$stageSuccess)
                                break;

                            require_once __DIR__.'/../IOFrame/Handlers/RouteHandler.php';
                            $RouteHandler = new IOFrame\Handlers\RouteHandler($settings,$defaultSettingsParams);

                            $routingMatch = false;
                            $routingOriginalMethods = 'GET|POST';
                            foreach ($RouteHandler->getActiveRoutes(['test'=>$test]) as $item){
                                if($item['Match_Name'] === 'api'){
                                    $routingMatch = $item['ID'];
                                    $routingOriginalMethods = $item['Method'];
                                    break;
                                }
                            }
                            $stageSuccess = $routingMatch &&
                                ($RouteHandler->updateRoute((int)$routingMatch,'GET|POST|PUT|DELETE|PATCH|HEAD','api/[*:trailing]','api',null,['test'=>$test]) === 0) &&
                                ($RouteHandler->setMatch('api','api/[trailing]','php',true) === 0);

                            break;
                        default:
                            $stageSuccess = true;
                    }
                    break;
                case 'settingFiles':
                    $metaSettings = new IOFrame\Handlers\SettingsHandler($rootFolder.SETTINGS_DIR_FROM_ROOT.'/metaSettings/',$defaultSettingsParams);
                    $failedSetting = false;
                    foreach($newSettingFiles as $name => $info){
                        $db = !empty($info['type']) && $info['type'] === 'db';
                        $title = empty($info['title']) ? $name : $info['title'];
                        if(!is_dir($rootFolder.SETTINGS_DIR_FROM_ROOT.'/'.$name)){
                            if(!$test){
                                if(!mkdir($rootFolder.SETTINGS_DIR_FROM_ROOT.'/'.$name)){
                                    $failedSetting = true;
                                    break;
                                }
                                fclose(fopen($rootFolder.SETTINGS_DIR_FROM_ROOT.'/'.$name.'/settings','w'));
                            }
                            else
                                echo 'Creating local settings directory '.$name.EOL;
                        }
                        if($db && !$cli) {
                            $newSettingsFile = new IOFrame\Handlers\SettingsHandler(
                                $rootFolder.SETTINGS_DIR_FROM_ROOT.'/'.$name.'/',
                                array_merge($defaultSettingsParams,['opMode'=>IOFrame\Handlers\SETTINGS_OP_MODE_MIXED])
                            );
                            $failedSetting = !$newSettingsFile->initDB(['test'=>$test]);
                        }
                        if(!$failedSetting)
                            $failedSetting = !$metaSettings->setSetting($name,json_encode(['local'=>!$db,'db'=>$db,'title'=>$title]),['createNew'=>true,'test'=>$test]);
                    }
                    if($failedSetting)
                        break;
                    $stageSuccess = true;
                    break;
                case 'settings':
                    $metaSettings = new IOFrame\Handlers\SettingsHandler($rootFolder.SETTINGS_DIR_FROM_ROOT.'/metaSettings/',$defaultSettingsParams);
                    $failedSettings = false;
                    foreach($newSettings as $name => $newSettingsArray){

                        $localSetting = $metaSettings->getSetting($name);
                        if(!empty($localSetting) && \IOFrame\Util\is_json($localSetting)){
                            $localSetting = (bool)json_decode($localSetting,true)['local'];
                        }
                        else
                            $localSetting = true;
                        if(!$localSetting && $cli)
                            continue;

                        $changedSettings = new IOFrame\Handlers\SettingsHandler($rootFolder.SETTINGS_DIR_FROM_ROOT.'/'.$name.'/',$defaultSettingsParams);
                        $oldSettings[$name] = [];
                        $oldSettings[$name]['original'] = $changedSettings->getSettings();
                        $oldSettings[$name]['changed'] = [];
                        if($oldSettings[$name]['original'] === false){
                            $failedSettings = true;
                            break;
                        }
                        $failedSetting = false;

                        foreach($newSettingsArray as $index => $settingArr){
                            $settingName = $settingArr['name'];
                            $settingValue = $settingArr['name'];
                            $overrideSetting = isset($settingArr['override']) ? (bool)$settingArr['override'] : true;
                            $createNewSetting = isset($settingArr['createNew']) ? (bool)$settingArr['createNew'] : true;
                            $existingSetting = isset($oldSettings[$name]['original'][$settingName])?$oldSettings[$name]['original'][$settingName]: null;
                            if( ($existingSetting !== null && !$overrideSetting) || ($existingSetting === null && !$createNewSetting) )
                                continue;
                            $failedSetting = !$changedSettings->setSetting($settingName,$settingValue,['test'=>$test,'createNew'=>$createNewSetting,'backUp'=>( $index === (count($newSettingsArray) - 1) )]);
                            if($failedSetting === -1)
                                $failedSetting = false;
                            if(!$failedSetting)
                                array_push($oldSettings[$name]['changed'],$settingName);
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
                    if($cli || (count($newActions) === 0) ){
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
                    if($cli || (count($newSecurityEvents) === 0) ){
                        $stageSuccess = true;
                        break;
                    }
                    if(!defined('SecurityHandler'))
                        require __DIR__.'/../IOFrame/Handlers/SecurityHandler.php';
                    $SecurityHandler = new IOFrame\Handlers\SecurityHandler(
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
                    if($cli || (count($newSecurityEventsMeta) === 0) ) {
                        $stageSuccess = true;
                        break;
                    }
                    if(!defined('SecurityHandler'))
                        require __DIR__.'/../IOFrame/Handlers/SecurityHandler.php';
                    $SecurityHandler = new IOFrame\Handlers\SecurityHandler(
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
                    if($cli || (count($newRoutes) === 0) ) {
                        $stageSuccess = true;
                        break;
                    }
                    if(!defined('RouteHandler'))
                        require __DIR__.'/../IOFrame/Handlers/RouteHandler.php';
                    $RouteHandler = new IOFrame\Handlers\RouteHandler(
                        $settings,
                        $defaultSettingsParams
                    );
                    $newRouteId = $RouteHandler->addRoutes($newRoutes,['test'=>$test]);
                    $stageSuccess = ($newRouteId>0);
                    break;
                case 'matches':
                    if($cli || (count($newMatches) === 0) ) {
                        $stageSuccess = true;
                        break;
                    }
                    if(!defined('RouteHandler'))
                        require __DIR__.'/../IOFrame/Handlers/RouteHandler.php';
                    $RouteHandler = new IOFrame\Handlers\RouteHandler(
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
                    if($cli || (count($newQueries) === 0) ) {
                        $stageSuccess = true;
                        break;
                    }
                    foreach ($newQueries as $queryPair){
                        if($test)
                            echo 'Query To execute: '.$queryPair[0].EOL;
                        elseif($SQLHandler->exeQueryBindParam($queryPair[0]) === true)
                            $queriesSucceeded++;
                        else
                            break;
                    }
                    $stageSuccess = true;
                    break;
                case 'increaseVersion':
                    $stageSuccess = $cli? true : $siteSettings->setSetting('ver',$next,['test'=>$test]);
                    break;
            }

            if($stageSuccess)
                $currentStage++;
            else
                break;
        }

        /* On failure, rollbacks - if we didn't reach the end of the update stages array, it means something went wrong */
        if( $currentStage < count($updateStages) )
            while($currentStage >= 0){
                $earlyFailure = false;
                switch ($updateStages[$currentStage]){
                    case 'customActions':
                        switch ($next){
                            case '1.1.1.0':
                                if($insertedMailTemplateID > 0)
                                    $earlyFailure = !$SQLHandler->deleteFromTable(
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
                                $earlyFailure = false;
                                if($routingMatch)
                                    $earlyFailure = $RouteHandler->updateRoute((int)$routingMatch,$routingOriginalMethods,'api/[*:trailing]','api',null,true,['test'=>$test]) !== 0;
                                if(!$earlyFailure)
                                    $earlyFailure = !$SQLHandler->exeQueryBindParam('ALTER TABLE '.$prefix.'ROUTING_MAP DROP Match_Partial_URL;',[],['test'=>$test]);
                                break;
                            case '1.2.0.1':
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
                            $changedSettings = new IOFrame\Handlers\SettingsHandler($rootFolder.SETTINGS_DIR_FROM_ROOT.'/'.$settingsName.'/',$defaultSettingsParams);
                            foreach($settingsArr['changed'] as $index => $changedSettingName){
                                $rollbackValue = isset($oldSettings[$settingsName]['original'][$changedSettingName])? $oldSettings[$settingsName]['original'][$changedSettingName] : null;
                                if(!$changedSettings->setSetting($changedSettingName,$rollbackValue,['test'=>$test,'backUp'=>( $index === (count($settingsArr['changed']) - 1) )])){
                                    $earlyFailure = true;
                                    break;
                                };
                            }
                        }
                        break;
                    case 'actions':
                        if($cli || count($oldActions) <= 0)
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
                        };
                        if(count($deleteActions) > 0 && !$auth->deleteActions($deleteActions,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        };
                        break;
                    case 'securityEvents':
                        if($cli || count($oldSecurityEvents) <= 0)
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
                        };
                        if(count($deleteEvents) > 0 && !$auth->deleteRulebookRules($deleteEvents,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        };
                        break;
                    case 'securityEventsMeta':
                        if($cli || count($oldSecurityEventsMeta) <= 0)
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
                        };
                        if(count($deleteEventsMeta) > 0 && !$auth->deleteEventsMeta($deleteEventsMeta,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        };
                        break;
                    case 'routes':
                        if($cli || $newRouteId <= 0)
                            break;
                        $removeIDs = [];
                        for($i = $newRouteId; $i<$newRouteId+count($newRoutes); $i++)
                            array_push($removeIDs,$i);
                        //Remove all new routes added earlier
                        if(!$RouteHandler->deleteRoutes($removeIDs,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        };
                        break;
                    case 'matches':
                        if($cli || count($oldMatches) <= 0)
                            break;
                        $deleteMatches = [];
                        $resetMatches = [];
                        foreach ($oldMatches as $match=>$arr){
                            //Reset all matches changed earlier
                            if($arr)
                                $resetMatches[$match] = [
                                    \IOFrame\Util\is_json($arr['URL'])? json_decode($arr['URL'],true) : $arr['URL'],
                                    !empty($arr['Extensions'])?$arr['Extensions']:null
                                ];
                            //Remove all new matches created earlier
                            else
                                array_push($deleteMatches,$match);
                        }
                        if(count($resetMatches) > 0 && !$RouteHandler->setMatches($resetMatches,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        };
                        if(count($deleteMatches) > 0 && !$RouteHandler->deleteMatches($deleteMatches,['test'=>$test])){
                            $earlyFailure = true;
                            break;
                        };
                        break;
                    case 'queries':
                        //Undo all queries executed earlier
                        if($cli || (count($newQueries) === 0) )
                            break;
                        foreach ($newQueries as $queryPair){
                            if($test)
                                echo 'Query To execute: '.$queryPair[1].EOL;
                            elseif($SQLHandler->exeQueryBindParam($queryPair[1]) === false){
                                $earlyFailure = true;
                                break;
                            };
                        }
                        break;
                    case 'increaseVersion':
                        $earlyFailure = $cli? false : !$siteSettings->setSetting('ver',$currentVersion);
                        break;
                }
                if($earlyFailure)
                    break;
                else
                    $currentStage--;
            }

        /* If we are back at stage -1, it means there was a successful rollback*/
        if($currentStage === -1)
            die('1');
        /* If we didn't reach the end OR start of the update stages, it means catastrophic failure happened somewhere along the way*/
        elseif($currentStage < count($updateStages))
            die('-1');
        else
            die('0');

    default:
        exit('Specified action is not recognized');
}

?>


