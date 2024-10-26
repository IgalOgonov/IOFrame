<?php
/* TODO Saving this here in case I decide to reimplement this type of functionality to preserve fetched logs between individual report managers.
$managerInfo['saveWIPTo'] = $managerInfo['saveWIPTo']??'localFiles/temp/logs/duringReport/default/mail';
$fullWIPUrl = $parameters['defaultParams']['absPathToRoot'].$managerInfo['saveWIPTo'];
$LockManager = new \IOFrame\Managers\LockManager($fullWIPUrl);

//Creating an empty folder is a valid side result even during testing
if(!is_dir($fullWIPUrl) && !@mkdir($fullWIPUrl)){
    \IOFrame\Util\PureUtilFunctions::createPathInObject( $errors, ['reporting-mail-wip-folder-creation-failure',$opt['id'],$managerId], $fullWIPUrl );
    return $result;
}

if(!$LockManager->makeMutex(['sec'=>30,'ignore'=>30]))
    return $result;
*/

/*
 * Parameters inside _reporting->reportManagers->$manager:
 *      'template': string, default 'default_log_report' - mail template to use
 *      'checkInterval': int, default 5 - Don't check more often than once every checkInterval seconds (spare the DB connection)
 *      'calcTimeBoundaries': bool, default false - whether to calculate time boundaries for each individual group, to be used in the report
 *      'assumeLastCheckedBefore': int, default 600 - If we can't find when we last checked, assume it was before 600 (default) seconds
 *      'throttleAfterReport': int, default 300 - How many seconds we wait for we've sent a report, to a specific group,
 *                             of logs up to the highest level we sent in the last timeframe.
 *                             e.g. If we sent, to group A, a report about 10 logs with max log level 400, we will
 *                                  wait up to throttleAfterReport seconds before sending logs of that level again,
 *                                  aggregating logs as we go - but if we get a new log of level 500 before the time is up,
 *                                  we will immediately report with everything we collected, in addition to what we currently fetched.
 *                                  This throttle is unique to each reporting group, and is fully optional (but recommended).
 *                                  If a group has it's own throttling config (saved in its Meta column in the DB), it overrides this one for that group.
*/
$_handleReports = function(array &$parameters, array &$errors, array &$opt, string $managerId){

    $result = ['exit'=>false,'result'=>[]];

    $logSettings = new \IOFrame\Handlers\SettingsHandler($parameters['defaultParams']['absPathToRoot'].'/localFiles/logSettings/',$parameters['defaultParams']);
    $LoggingHandler = new \IOFrame\Handlers\LoggingHandler($parameters['defaultParams']['localSettings'],array_merge($parameters['defaultParams'],['logSettings'=>$logSettings]));
    $verbose = !$parameters['silent'] && $parameters['verbose'];
    $mail = new \IOFrame\Managers\MailManager($parameters['defaultParams']['localSettings'],array_merge($parameters['defaultParams'],['verbose'=>$verbose]));
    $managerInfo = $parameters['_reporting']['reportManagers'][$managerId];
    $managerInfo['template'] = $managerInfo['template']??'default_log_report';
    $managerInfo['checkInterval'] = $managerInfo['checkInterval']??5;
    $managerInfo['calcTimeBoundaries'] = $managerInfo['calcTimeBoundaries']??false;
    $managerInfo['assumeLastCheckedBefore'] = $managerInfo['assumeLastCheckedBefore']??600;
    $managerInfo['throttleAfterReport'] = $managerInfo['throttleAfterReport']??300;
    $startTime = microtime(true);
    $lastCheckedKey = 'cli_reporting_'.$managerId.'_reports_last_checked';
    $groupThrottlingKey = 'cli_reporting_'.$managerId.'_report_groups_throttled';
    $groupsWithAggregatedLogsPrefix = 'cli_reporting_'.$managerId.'_reports_group_with_aggregated_logs_';
    $groupAggregatedLogsPrefix = 'cli_reporting_'.$managerId.'_report_group_aggregated_logs_';

    //Check the last time we got logs
    $lastLogsCheck = $parameters['defaultParams']['RedisManager']->get($lastCheckedKey);
    if(!$lastLogsCheck)
        $lastLogsCheck = $startTime-$managerInfo['assumeLastCheckedBefore'];
    else
        $lastLogsCheck = max($lastLogsCheck,$startTime-$managerInfo['assumeLastCheckedBefore']);

    //Get new logs
    $newLogs = $LoggingHandler->getLogs(
        [],
        [
            'uploadedAfter'=>(string)$lastLogsCheck,
            'disableExtraToGet'=>true,
            'test'=>$parameters['test'],
            'verbose'=>$verbose
        ]
    );
    if($parameters['verbose'] && !$parameters['silent'])
        echo count($newLogs).' new logs found.'.EOL;

    //Match all new logs to groups via report rules
    $groupLogsInfo = \IOFrame\Util\CLI\LoggingReporting\CommonLoggingFunctions::getGroupsRelevantToLogs(
        $parameters,
        $newLogs,
        'email',
        ['calcTimeBoundaries'=>$managerInfo['calcTimeBoundaries']]
    );

    //If any groups with aggregated logs exist, check which ones are throttled
    $throttledGroups = $parameters['defaultParams']['RedisManager']->get($groupThrottlingKey);
    $groupsThrottleInfo = [];
    $notThrottledGroupLogsRedisKeys = [];
    $throttledGroupLogsMap = [];

    if(!empty($throttledGroups)){
        $throttledGroups = explode(',',$throttledGroups);
        $throttledGroupRedisKeys = array_map(
            function ($id) use ($groupsWithAggregatedLogsPrefix){
                return $groupsWithAggregatedLogsPrefix.$id;
            },
            $throttledGroups
        );
        $groupsStillThrottled = $parameters['defaultParams']['RedisManager']->mGet($throttledGroupRedisKeys);
        if(!empty($groupsStillThrottled)){
            $groupsThrottleInfo = array_map(
                function ($throttleInfo) use ($startTime){
                    $throttleInfo = $throttleInfo ? explode('/',$throttleInfo) : [0,0];
                    $throttleUntil = ($throttleInfo[0] >= (string)$startTime) ? $throttleInfo[0] : null;
                    return ['throttledUntil'=> $throttleUntil,'level'=>$throttleInfo[1]];
                },
                $groupsStillThrottled
            );
            $groupsThrottleInfo = array_combine($throttledGroups,$groupsThrottleInfo);
            foreach($groupLogsInfo as $group => $newInfo){
                if( !empty($groupsThrottleInfo[$group]) && $groupsThrottleInfo[$group]['throttledUntil'] && ($groupsThrottleInfo[$group]['level'] < $newInfo['highestLogLevel']) )
                    $groupsThrottleInfo[$group]['throttledUntil'] = null;
            }
        }
    }

    //Populate throttled/unthrottled group keys / map
    foreach ($groupsThrottleInfo as $id => $info){
        if(!$info['throttledUntil'])
            $notThrottledGroupLogsRedisKeys[] = $groupAggregatedLogsPrefix.$id;
        else{
            $throttledGroupLogsMap[$id] = $info;
        }
    }

    // For every group no longer throttled, get its aggregated logs and add to $groupLogsInfo
    //TODO Combine some duplicate logic here and within CommonLoggingFunctions::getGroupsRelevantToLogs()
    if(!empty($notThrottledGroupLogsRedisKeys)){
        $allUnThrottledGroupLogIDs = $parameters['defaultParams']['RedisManager']->mGet($notThrottledGroupLogsRedisKeys);
        if(!empty($allUnThrottledGroupLogIDs))
            foreach($notThrottledGroupLogsRedisKeys as $i => $groupIdWithPrefix){
                $groupId = substr($groupIdWithPrefix,strlen($groupAggregatedLogsPrefix));
                $logs = $allUnThrottledGroupLogIDs[$i];
                if($logs){
                    $logs = explode(',',$logs);

                    //There is a possibility we un-throttled a group that wasn't part of the recent logs
                    if(empty($groupLogsInfo[$groupId]))
                        \IOFrame\Util\PureUtilFunctions::createPathInObject($groupLogsInfo,[$groupId]);

                    if($managerInfo['calcTimeBoundaries']){
                        \IOFrame\Util\PureUtilFunctions::createPathInObject($groupLogsInfo,[$groupId,'earliestLogTime'],0);
                        \IOFrame\Util\PureUtilFunctions::createPathInObject($groupLogsInfo,[$groupId,'latestLogTime'],0);
                    }

                    foreach ($logs as $logInfo){
                        $logInfoObj = array_combine(['channel','level','created','node'],explode('/',$logInfo));

                        //Re-create individual ruleLogIndexes
                        $ruleId = $logInfoObj['channel'].'/'.$logInfoObj['level'].'/email';
                        if(empty($groupLogsInfo[$groupId]['ruleLogIndexes'][$ruleId][$logInfoObj['level']]))
                            \IOFrame\Util\PureUtilFunctions::createPathInObject($groupLogsInfo,[$groupId,'ruleLogIndexes',$ruleId,$logInfoObj['level']]);
                        $groupLogsInfo[$groupId]['ruleLogIndexes'][$ruleId][$logInfoObj['level']][] = $logInfo;

                        //Re-create highest log level
                        if(empty($groupLogsInfo[$groupId]['ruleLogIndexes']['highestLogLevel']))
                            \IOFrame\Util\PureUtilFunctions::createPathInObject($groupLogsInfo,[$groupId,'highestLogLevel'],$logInfoObj['level']);
                        else
                            $groupLogsInfo[$groupId]['ruleLogIndexes']['highestLogLevel'] = max($groupLogsInfo[$groupId]['highestLogLevel'],$logInfoObj['level']);

                        //Re-create latest/earliest log times
                        if($managerInfo['calcTimeBoundaries']){
                            $createdSec = explode('.',$logInfoObj['created'])[0];
                            if(!$groupLogsInfo[$groupId]['earliestLogTime'] || ($groupLogsInfo[$groupId]['earliestLogTime'] > $createdSec) )
                                $groupLogsInfo[$groupId]['earliestLogTime'] = $createdSec;
                            if($groupLogsInfo[$groupId]['latestLogTime'] < $createdSec)
                                $groupLogsInfo[$groupId]['latestLogTime'] = $createdSec;
                        }
                    }

                    if(empty($groupLogsInfo[$groupId]['allLogIndexes']))
                        \IOFrame\Util\PureUtilFunctions::createPathInObject($groupLogsInfo,[$groupId,'allLogIndexes']);
                    $groupLogsInfo[$groupId]['allLogIndexes'] = array_unique(array_merge($groupLogsInfo[$groupId]['allLogIndexes'],$logs));
                }
            }
    }

    //For any throttled group (for this max log level), add logs to aggregated
    if(!empty($throttledGroupLogsMap)){
        $throttledGroupLogsRedisKeys = array_map(
                function ($id) use($groupAggregatedLogsPrefix){ return $groupAggregatedLogsPrefix.$id; },
                array_keys($throttledGroupLogsMap)
            );
        $allThrottledGroupLogIDs =  $parameters['defaultParams']['RedisManager']->mGet(
            $throttledGroupLogsRedisKeys
        );
        if(empty($allThrottledGroupLogIDs))
            $allThrottledGroupLogIDs = [];
        $i = 0;
        foreach ($throttledGroupLogsMap as $id => $info){
            if(empty($groupLogsInfo[$id]['allLogIndexes']))
                continue;
            $existingLogs = $allThrottledGroupLogIDs[$i++]??false;
            $existingLogs = $existingLogs ? explode(',',$existingLogs) : [];
            $existingLogs = implode(',',array_unique(array_merge($existingLogs,$groupLogsInfo[$id]['allLogIndexes'])));
            unset($groupLogsInfo[$id]['allLogIndexes']);
            if($verbose)
                echo 'Setting cache '.$groupAggregatedLogsPrefix.$id.' as '.$existingLogs.EOL;
            if(!$parameters['test'])
                $parameters['defaultParams']['RedisManager']->set($groupAggregatedLogsPrefix.$id,$existingLogs);
        }
        unset($i);
    }

    //If any groups remain, match groups with users
    if(!empty($groupLogsInfo))
        $usersReportInfo = \IOFrame\Util\CLI\LoggingReporting\CommonLoggingFunctions::getRelevantGroupUsers($parameters,$groupLogsInfo, [ 'Email' => 'email' ]);

    //Send async email to all matched users
    //TODO Could optionally fetch group meta-information like titles, but since this is a technical report I'm not sure it's worth the performance cost
    //TODO Add dynamic way to generate report without writing a new report handler
    if(!empty($usersReportInfo)){

        $allMailsSent = true;
        $groupsToThrottle = [];

        foreach ($usersReportInfo as $id => $sendInfo){
            if(empty($sendInfo['email']))
                continue;

            $summaryHeader = '';
            $summaryBody = '';
            $summaryFooter = '';

            if($verbose){
                echo 'Sending async mail to user '.$id.' with mail '.$sendInfo['email'].EOL;
                echo 'Logs originate from groups '.implode(',',$sendInfo['allGroups']).EOL;
                echo 'Highest log level is '.$sendInfo['highestLogLevel'].EOL;
                echo 'Total log count is '.count($sendInfo['allLogIndexes']).EOL;
            }
            $summaryHeader .=' <div class="groups"> 
                                   <span class="title">Logs originate from groups: </span> 
                                   <span class="group-ids">'.implode(', ',$sendInfo['allGroups']).'</span> 
                               </div>';
            $summaryHeader .=' <div class="highest-log-level"> 
                                   <span class="title">Highest log level is </span> 
                                   <span class="level">'.$sendInfo['highestLogLevel'].'</span> 
                               </div>';
            $summaryHeader .=' <div class="total-count"> 
                                   <span class="title">Total log count is </span> 
                                   <span class="count">'.count($sendInfo['allLogIndexes']).'</span> 
                               </div>';

            $channelLevelSummary = [];
            $earliestLogTime = 0;
            $latestLogTime = 0;
            foreach ($sendInfo['allGroups'] as $group){
                $groupInfo = $groupLogsInfo[$group];

                //Even if the same rule exists in different groups, its ruleLogIndexes will be exactly the same
                foreach ($groupInfo['ruleLogIndexes'] as $index => $levelsLogsMap){
                    $fixedIndex = substr($index,0,-strlen('/email'));
                    if(empty($channelLevelSummary[$fixedIndex]))
                        $channelLevelSummary[$fixedIndex] = array_keys($levelsLogsMap);
                }
                if($managerInfo['calcTimeBoundaries']){
                    $earliestLogTime = empty($earliestLogTime) ? $groupInfo['earliestLogTime'] : min($earliestLogTime,$groupInfo['earliestLogTime']);
                    $latestLogTime = max($latestLogTime,$groupInfo['latestLogTime']);
                }
            }
            if($verbose){
                echo 'Channels & levels summary for group '.json_encode($channelLevelSummary,JSON_PRETTY_PRINT).EOL;
                if($managerInfo['calcTimeBoundaries']){
                    echo 'Earliest log time '.$earliestLogTime.EOL;
                    echo 'Latest log time '.$latestLogTime.EOL;
                }
                echo EOL;
            }
            $summaryBody .= '<div class="summary">'.
                            '<div class="title"> Rule: Log levels</div>';
            foreach ($channelLevelSummary as $rule => $levels)
                $summaryBody .= '<div class="rule">'.
                                    '<span class="rule">'.explode('/',$rule)[0].': </span>'.
                                    '<span class="levels">'.implode(',',$levels).'</span>'.
                                '</div>';
            if($managerInfo['calcTimeBoundaries']){
                $summaryBody .=
                    '<div class="earliest-log-time">'.
                        '<span class="title">Earliest Log Time: </span>'.
                        '<span class="levels">'.date('H:i, d M Y eP',(int)$earliestLogTime).': </span>'.
                    '</div>'.
                    '<div class="latest-log-time">'.
                        '<span class="title">Latest Log Time: </span>'.
                        '<span class="levels">'.date('H:i, d M Y eP',(int)$latestLogTime).': </span>'.
                    '</div>';
            }
            $summaryBody .= '</div>';

            $success = $parameters['test'] || $mail->sendMailAsync(
                [
                    'to'=>$sendInfo['email'],
                    'from'=>['',$parameters['defaultParams']['siteSettings']->getSetting('siteName')],
                    'subject'=>( $logSettings->getSetting('defaultReportMailTitle')??'Default Logs Report' ).
                        ' Groups: '.implode(', ',$sendInfo['allGroups']).' Highest Level: '.$sendInfo['highestLogLevel'],
                    'template'=> $logSettings->getSetting('defaultReportMailTemplate') ?? 'default_log_report',
                    'varArray'=>[
                        'siteName'=>$parameters['defaultParams']['siteSettings']->getSetting('siteName'),
                        'Summary_Header'=>$summaryHeader,
                        'Summary_Body'=>$summaryBody,
                        'Summary_Footer'=>$summaryFooter,
                    ]
                ],
                //TODO Add configurable 'successQueue','failureQueue'
                ['test'=>$parameters['test'],'verbose'=>false]
            );
            if($success)
                $groupsToThrottle = array_unique(array_merge($groupsToThrottle,$sendInfo['allGroups']));
            $allMailsSent = $allMailsSent && $success;
        }

        //If we were successful and update all groups
        if($allMailsSent){

            $result['result'][ (string)$startTime ] = [
                'groupsSent' => array_keys($groupLogsInfo),
                'usersSent' => array_map(function($sendInfo){ return $sendInfo['email'] ?? null;},$usersReportInfo)
            ];
        }

    }

    if(!empty($notThrottledGroupLogsRedisKeys)){
        if(!isset($result['result'][(string)$startTime]['groupsDethrottled']))
            \IOFrame\Util\PureUtilFunctions::createPathInObject(
                $result['result'],
                [(string)$startTime,'groupsDethrottled'],
                array_map(
                    function($keyWithID) use($groupAggregatedLogsPrefix){return substr($keyWithID,strlen($groupAggregatedLogsPrefix));},
                    $notThrottledGroupLogsRedisKeys
                )
            );
        if(!$parameters['test'])
            $parameters['defaultParams']['RedisManager']->del($notThrottledGroupLogsRedisKeys);
        if($verbose)
            echo 'Deleting redis keys '.implode(',',$notThrottledGroupLogsRedisKeys).EOL;
    }

    if(!empty($groupsToThrottle)){
        $allThrottledGroups = array_unique(array_merge($groupsToThrottle,array_keys($throttledGroupLogsMap)));
        if(!isset($result['result'][(string)$startTime]['groupsThrottled']))
            \IOFrame\Util\PureUtilFunctions::createPathInObject(
                $result['result'],
                [(string)$startTime,'groupsThrottled'],
                $allThrottledGroups
            );

        if(!$parameters['test'])
            $parameters['defaultParams']['RedisManager']->set($groupThrottlingKey,implode(',',$allThrottledGroups));
        if($verbose)
            echo 'Setting redis key '.$groupThrottlingKey.' to '.implode(',',$allThrottledGroups).EOL;

        foreach ($groupsToThrottle as $group){
            $toSet = ($startTime+$managerInfo['throttleAfterReport']).'/'.$groupLogsInfo[$group]['highestLogLevel'];
            if(!$parameters['test'])
                $parameters['defaultParams']['RedisManager']->set($groupsWithAggregatedLogsPrefix.$group,$toSet);
            if($verbose)
                echo 'Setting redis key '.$groupsWithAggregatedLogsPrefix.$group.' to '.$toSet.EOL;
        }
    }
    elseif(!empty($notThrottledGroupLogsRedisKeys)){
        if(!$parameters['test'])
            $parameters['defaultParams']['RedisManager']->del($groupThrottlingKey);
        if($verbose)
            echo 'Deleting redis key '.$groupThrottlingKey.EOL;
    }

    //Set last-checked to starting time - reminder, even if we didn't send anything, all unsent stuff was still properly throttled.
    if(!$parameters['test'])
        $parameters['defaultParams']['RedisManager']->set($lastCheckedKey,$startTime);
    if($verbose)
        echo 'Setting redis key '.$lastCheckedKey.' to '.$startTime.EOL;

    //If we completed everything quickly, wait for a bit
    if( time() - (int)$startTime <  $managerInfo['checkInterval'] )
        sleep(
            min(
                $managerInfo['checkInterval'] - ( time() - (int)$startTime ),
                \IOFrame\Util\CLI\CommonJobRuntimeFunctions::getRemainingRuntime($parameters,$opt)
            )
        );
    $result['exit'] = $parameters['test'];
    return $result;
};