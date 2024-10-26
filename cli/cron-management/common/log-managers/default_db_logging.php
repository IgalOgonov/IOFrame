<?php

/*
 * Parameters inside _logging->logManagers->$manager:
 *      'logsFolder': string, default 'localFiles/logs' - where we keep our logs (from project root)
 *      'logFilesSafetyMargin': int, default 20 - (At least) How many seconds old logs must be for us to handle them
 *      'logFileIntervals': int, default 10 - This corresponds to the default intervals in IOFrameHandler.
 *                          Different value would require a different logFolder, too.
 *
 * Populates:
 *  $opt[$managerId]['_handledLogsFromFile'] - logs that need to be handled by the report manager
*/
$_handleLogs = function(&$parameters,&$errors,&$opt,string $managerId){

    $result = ['exit'=>false,'result'=>false];

    $verbose = !$parameters['silent'] && $parameters['verbose'];
    $managerInfo = $parameters['_logging']['logManagers'][$managerId];
    $managerInfo['logsFolder'] = $managerInfo['logsFolder']??'localFiles/logs';
    $managerInfo['logFilesSafetyMargin'] = $managerInfo['logFilesSafetyMargin']??20;
    $managerInfo['logFileIntervals'] = $managerInfo['logFileIntervals']??10;
    
    //Get all local logs
    $allLogs = \IOFrame\Util\CLI\LoggingReporting\CommonLoggingFunctions::getLocalLogFilePaths($parameters,$managerInfo['logsFolder']);
    if($verbose)
        echo 'Seeing '.count($allLogs).' logs'.EOL;

    if(!empty($allLogs)){
        foreach ($allLogs as $logUrl){
            if(\IOFrame\Util\CLI\CommonJobRuntimeFunctions::getRemainingRuntime($parameters,$opt) < 0)
                break;

            $handledSingleLogFile = false;
            //Get + lock earliest log file
            $url = explode('/',$logUrl);
            $fileName = array_pop($url);
            $url = implode('/',$url);

            //If log time inside logFilesSafetyMargin, dont touch it
            $timeOfLogging = explode('_',$fileName)[0]*($managerInfo['logFileIntervals']);
            if($timeOfLogging + $managerInfo['logFilesSafetyMargin'] > time())
                continue;

            $LockManager = new \IOFrame\Managers\LockManager($logUrl);
            try {
                if($verbose)
                    echo 'Starting work on '.$logUrl.EOL;
                /* We are using both native and LockManager mutexes.

                 The first potential problem is parallel jobs, which will not be able to pass through with both mutexes.

                 It would be bad if we could open files written to via IOFrameHandler, which uses a native mutex, and may
                 live longer than our safety margin, or vice versa.
                 Thus, IOFrameHandler checks for a LockManager type of mutex, and both it and this process use the native mutex as well.
                */
                if(
                    $LockManager->makeMutex(
                        [
                            'sec'=>min(
                                60,
                                \IOFrame\Util\CLI\CommonJobRuntimeFunctions::getRemainingRuntime($parameters,$opt)
                            ),
                            'ignore'=>70
                        ]
                    )
                )
                    $logsInfo = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($url,$fileName,['useNative'=>true]);
                else
                    continue;
                //While testing, pretend we're processing the file for 5 seconds
                if($parameters['test'] && $verbose){
                    echo 'Sleeping for 5 seconds to simulate processing'.EOL;
                    sleep(5);
                }
            }
            catch (\Exception $e){
                //Maybe file was deleted while we were waiting for LockManager mutex, and that's ok, otherwise it isn't
                if(is_file($logUrl))
                    \IOFrame\Util\PureUtilFunctions::createPathInObject(
                        $errors,
                        ['log-file-opening-failure',$opt['id'],$managerId,$fileName],
                        ['message'=>$e->getMessage(),'trace'=>$e->getTrace()]
                    );
                $LockManager->deleteMutex();
                continue;
            }
            $actualLogs = explode(EOL_FILE,$logsInfo['contents']);
            array_pop($actualLogs); //We will always have an empty string in the end.

            //Log to DB
            $LoggingHandler = new \IOFrame\Handlers\LoggingHandler($parameters['defaultParams']['localSettings'],$parameters['defaultParams']);
            $toSet = [];
            foreach ($actualLogs as $i => $log){

                $actualLogs[$i] = json_decode($log,true);
                if(!$actualLogs[$i])
                    continue;

                $failedLoggingAttemptLog = ($actualLogs[$i]['channel'] === \IOFrame\Definitions::LOG_DEFAULT_CHANNEL) && str_starts_with($actualLogs[$i]['message'],'Failed to insert items of');
                if(!$failedLoggingAttemptLog)
                    $toSet[] = [
                        'Channel'=>$actualLogs[$i]['channel'],
                        'Log_Level'=>$actualLogs[$i]['level'],
                        'Created'=>$actualLogs[$i]['datetime'],
                        'Node'=>$parameters['defaultParams']['localSettings']->getSetting('nodeID') ?? 'unknown',
                        'Message'=>json_encode( ['message'=>$actualLogs[$i]['message'], 'context'=>$actualLogs[$i]['context'] ]),
                    ];
                else{
                    //TODO Decide how to avoid infinite recursion
                }

            }
            if($verbose)
                echo 'Sending '.count($actualLogs).' new logs to DB'.EOL;

            $insertResults = $LoggingHandler->setLogs(
                $toSet,
                [
                    'existing'=>[], //No need to assume anything existed
                    'test'=>$parameters['test'],
                    'verbose'=>$verbose
                ]
            );


            $failed = !is_array($insertResults);
            if(!$failed)
                foreach ($insertResults as $insertResult){
                    if($insertResult !== 0)
                        $failed = true;
                }

            try {
                @fclose($logsInfo['fileStream']);
            }catch (\Throwable){}

            //Error if we failed
            if($failed){
                \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['log-db-sync-failure',$opt['id'],$managerId,$fileName],true);
                $LockManager->deleteMutex();
                continue;
            }

            $fileHandled = $parameters['test'] || @unlink($logUrl);
            //Delete file if needed
            if($fileHandled){
                if($verbose)
                    echo 'Successfully handled '.$logUrl.EOL;
                $handledSingleLogFile = true;
                $result['result'] = $logUrl;

                if(empty($opt['_handledLogsFromFile'][$managerId]))
                    \IOFrame\Util\PureUtilFunctions::createPathInObject($opt,['_handledLogsFromFile',$managerId],$actualLogs);
                else
                    $opt['_handledLogsFromFile'][$managerId] = array_merge($opt['_handledLogsFromFile'][$managerId],$actualLogs);
            }
            $LockManager->deleteMutex();

            //If everything went fine, we can break from the loop and go to reporting
            if($handledSingleLogFile)
                break;
        }
    }
    //If wait a bit after each operation / scan
    if(\IOFrame\Util\CLI\CommonJobRuntimeFunctions::getRemainingRuntime($parameters,$opt) > 0)
        $opt['timingMeasurer']->waitUntilIntervalElapsed(1);
    $result['exit'] = $parameters['test'];
    return $result;
};