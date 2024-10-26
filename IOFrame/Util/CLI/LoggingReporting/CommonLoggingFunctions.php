<?php

namespace IOFrame\Util\CLI\LoggingReporting{

    class CommonLoggingFunctions{

        /** Scans for local log files
         * @param array $parameters see cron-management dynamicCronJobs
         * @param string $logFolder folder where the logs should be located
         * @return array All relevant files
         * */
        public static function getLocalLogFilePaths(array $parameters, string $logFolder): array {
            return \IOFrame\Util\FileSystemFunctions::fetchAllFolderAddresses(
                $parameters['defaultParams']['absPathToRoot'].$logFolder,
                [
                    'include'=> ['_logs\.txt$']
                ]
            );
        }

        /** PArses an array of logs, and returns information about who they should be delivered to, per group.
         * @param array $parameters see cron-management dynamicCronJobs
         * @param array $allLogs All longs
         * @param string|null $reportType Check for a specific report type
         * @param array $params
         *               'calcTimeBoundaries' => <bool, default false - if set, will go over all logs getting 'earliestLogTime' and 'latestLogTime' >
         * @return array Object of the form:
         *      [
         *          $groupId => [
         *              'ruleLogIndexes'=>[
         *                  $logChannel => [
         *                      $logLevel => <int[], log indexes in $allLogs>,
         *                      ...
         *                  ],
         *                  ...
         *              ],
         *              'highestLogLevel' => <int, highest log level among all logs>,
         *              ['earliestLogTime'] => <int, timestamp of earliest log BY CREATION TIME>,
         *              ['latestLogTime'] => <int, timestamp of latest log BY CREATION TIME>,
         *              'allLogIndexes'=> <int[], log indexes in $allLogs>
         *          ],
         *          ...
         *      ]
         *
         * @throws \Exception
         * @throws \Exception
         */
        public static function getGroupsRelevantToLogs(array $parameters, array $allLogs, string $reportType = null, array $params = []): array {
            $result = [];
            $logChannelMap = [];
            $metaInfo = [
                'channels'=>[],
                'highestLevel'=>0
            ];
            $params['calcTimeBoundaries'] =  $params['calcTimeBoundaries'] ?? false;

            if(empty($allLogs))
                return $result;

            //Extract all unique channels and highest log level from logs
            $LoggingHandler = new \IOFrame\Handlers\LoggingHandler($parameters['defaultParams']['localSettings'],$parameters['defaultParams']);
            foreach ($allLogs as $i => $reported){
                $logChannelMap[$reported['Channel']] = $logChannelMap[$reported['Channel']] ?? [];
                $logChannelMap[$reported['Channel']][$reported['Log_Level']] = $logChannelMap[$reported['Channel']][$reported['Log_Level']] ?? [];
                $logChannelMap[$reported['Channel']][$reported['Log_Level']][] = $i;
                $metaInfo['channels'][$reported['Channel']] = true;
                if($metaInfo['highestLevel'] < $reported['Log_Level'])
                    $metaInfo['highestLevel'] = $reported['Log_Level'];
            }

            //Get all potentially relevant rule groups
            if(!empty($metaInfo['channels']))
                $relevantRuleGroups = $LoggingHandler->getItems(
                    [],
                    'reportingRuleGroups',
                    [
                        'channelIn'=>array_keys($metaInfo['channels']),
                        'reportTypeIs'=>$reportType,
                        'levelAtMost'=>$metaInfo['highestLevel'],
                        'getAllSubItems'=>true,
                        'disableExtraToGet'=>true,
                        'test'=>$parameters['test'],
                    ]
                );

            //Match all logs with their relevant rules
            if(!empty($relevantRuleGroups)){
                foreach ($relevantRuleGroups as $channel => $channelRuleGroups)
                    foreach ($channelRuleGroups as $id => $channelRuleGroup){
                        foreach ($logChannelMap as $mapChannel => $levelLogMaps){
                            if($channelRuleGroup['Channel'] !== $mapChannel)
                                continue;
                            foreach ($levelLogMaps as $logLevel => $logs){
                                if($logLevel >= $channelRuleGroup['Log_Level']){

                                    if($params['calcTimeBoundaries'] && !empty($logs)){
                                        \IOFrame\Util\PureUtilFunctions::createPathInObject($result,[$id,'earliestLogTime'],0);
                                        \IOFrame\Util\PureUtilFunctions::createPathInObject($result,[$id,'latestLogTime'],0);
                                        foreach ($logs as $log){
                                            $logInfo = explode('/',$log);
                                            $createdSec = explode('.',$logInfo[2])[0];
                                            if(!$result[$id]['earliestLogTime'] || ($result[$id]['earliestLogTime'] > $createdSec) )
                                                $result[$id]['earliestLogTime'] = $createdSec;
                                            if($result[$id]['latestLogTime'] < $createdSec)
                                                $result[$id]['latestLogTime'] = $createdSec;
                                        }
                                    }

                                    \IOFrame\Util\PureUtilFunctions::createPathInObject($result,[$id,'ruleLogIndexes',$channel,$logLevel],$logs);

                                    if(empty($result[$id]['highestLogLevel']))
                                        \IOFrame\Util\PureUtilFunctions::createPathInObject($result,[$id,'highestLogLevel'],$logLevel);
                                    else
                                        $result[$id]['highestLogLevel'] = max($result[$id]['highestLogLevel'],$logLevel);

                                    if(empty($result[$id]['allLogIndexes']))
                                        \IOFrame\Util\PureUtilFunctions::createPathInObject($result,[$id,'allLogIndexes']);
                                    $result[$id]['allLogIndexes'] = array_unique(array_merge($result[$id]['allLogIndexes'],$logs));

                                }
                            }
                        }
                    }
            }
            return $result;
        }

        /** Gets an object from getGroupsRelevantToLogs(), and returns information about who they should be delivered to, per user.
         * @param array $parameters see cron-management dynamicCronJobs
         * @param array $groupLogs Result from getGroupsRelevantToLogs()
         * @param array $extraInfoMap Extra info to get from the user DB rows and return in the result.
         *                            Structure of the form [ 'DB_Column_Name' => 'nameInResult' ], for example ['Email' => 'email']
         * @return array Object of the form:
         *      [
         *          $userId => [
         *              'nameInResult' => <string, DB_Column_Name value>,
         *              'allGroups' => <string[], all groups the user belonged to that require a report>,
         *              'highestLogLevel' => <int, highest log level from all logs that should be included in the user report>,
         *              'allLogIndexes' => <int[], log indexes in $allLogs (caller should hold this)>,
         *          ],
         *          ...
         *      ]
         *
         * @throws \Exception
         * @throws \Exception
         */
        public static function getRelevantGroupUsers(array $parameters, array $groupLogs, array $extraInfoMap = []): array {
            $result = [];

            $relevantGroupIdentifiers = array_map(
                function ($item) {return explode('/',$item); },
                array_keys($groupLogs)
            );
            $LoggingHandler = new \IOFrame\Handlers\LoggingHandler($parameters['defaultParams']['localSettings'],$parameters['defaultParams']);
            //Get all group users
            if(!empty($relevantGroupIdentifiers)){
                $relevantRuleGroupUsers = $LoggingHandler->getItems(
                    $relevantGroupIdentifiers,
                    'reportingGroupUsers',
                    [
                        'disableExtraToGet'=>true,
                        'test'=>$parameters['test'],
                    ]
                );
            }
            //Match report summary in each group to user
            if(!empty($relevantRuleGroupUsers)){
                foreach ($relevantRuleGroupUsers as $groupId => $users){
                    $groupLogsInfo = $groupLogs[$groupId];
                    foreach ($users as $userId=>$userInfo){
                        if( empty($groupLogsInfo['highestLogLevel']) || empty($groupLogsInfo['allLogIndexes']) )
                            continue;

                        foreach ($extraInfoMap as $dbName => $resName)
                            \IOFrame\Util\PureUtilFunctions::createPathInObject($result,[$userId,$resName],$userInfo[$dbName] ?? null);

                        if(empty($result[$userId]['allGroups']))
                            \IOFrame\Util\PureUtilFunctions::createPathInObject($result,[$userId,'allGroups'],[$groupId]);
                        else
                            $result[$userId]['allGroups'][] = $groupId;

                        if(empty($result[$userId]['highestLogLevel']))
                            \IOFrame\Util\PureUtilFunctions::createPathInObject($result,[$userId,'highestLogLevel'],$groupLogsInfo['highestLogLevel']);
                        else
                            $result[$userId]['highestLogLevel'] = max($result[$userId]['highestLogLevel'],$groupLogsInfo['highestLogLevel']);

                        if(empty($result[$userId]['allLogIndexes']))
                            \IOFrame\Util\PureUtilFunctions::createPathInObject($result,[$userId,'allLogIndexes'],$groupLogsInfo['allLogIndexes']);
                        else
                            $result[$userId]['allLogIndexes'] =  array_unique(array_merge($result[$userId]['allLogIndexes'],$groupLogsInfo['allLogIndexes']));
                    }
                }
            }

            return $result;
        }
    }

}