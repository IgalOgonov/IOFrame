<?php
/* Queues are generally not meant to be run more than 1 per process,
   unless you want the backup queue manager(s) to run if the main queue manager failed.
   Parameters explained in cron-management.php dynamicJobProperties variable default definitions.
   Additional _queue parameter object includes:
        listenTo - string|string[], queue(s) to listen to
        url - string, url to file that has the $_handleQueue variable function.
              Example can be found in email-queue-managers/send_mail_default_queue.php
*/

$_runBefore = function (&$parameters,&$errors,$opt){
    if(!$parameters['silent'] && $parameters['verbose'])
        echo 'Starting reporting job '.$opt['id'].EOL;
    return true;
};

$_run = function (&$parameters,&$errors,&$opt){
    $verbose = !$parameters['silent'] && $parameters['verbose'];
    $result = ['exit'=>false,'result'=>true];

    $reportManagers = $parameters['_reporting']['reportManagers']??null;
    if(empty($reportManagers)){
        if($verbose)
            echo 'No reporting managers in '.$opt['id'].EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['no-reporting-managers',$opt['id']],true);
        return array_merge($result,['exit'=>true]);
    }

    foreach ($reportManagers as $managerId => $info){
        if(!is_file($parameters['defaultParams']['absPathToRoot'].'/'.($info['manager']??null))){
            if($verbose)
                echo 'Invalid report manager in '.$managerId.EOL;
            \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['invalid-report-manager',$opt['id'],$managerId],true);
            return array_merge($result,['exit'=>true]);
        }
    }

    $parameters['_reporting']['_results'] = $parameters['_reporting']['_results']??[];


    $reportManagersResult = ['exit'=>false];
    foreach ($reportManagers as $managerId => $info){

        require $parameters['defaultParams']['absPathToRoot'].'/'.($info['manager']??null);

        if(empty($_handleReports) || !is_callable($_handleReports)){
            if($verbose)
                echo 'Logs reporting '.$opt['id'].' manager '.$managerId.' no function!'.EOL;
            \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['no-reporting-handle-function',$opt['id'],$managerId],true);
            $reportManagersResult = array_merge($reportManagersResult,['exit'=>true]);
        }
        else
            $reportManagersResult = array_merge($reportManagersResult,$_handleReports($parameters,$errors,$opt,$managerId));

        if(empty($parameters['_reporting']['reportResults'][$opt['id']][$managerId]))
            \IOFrame\Util\PureUtilFunctions::createPathInObject($parameters,['_reporting','reportResults',$opt['id'],$managerId]);
        if(!empty($reportManagersResult['result']))
            $parameters['_reporting']['reportResults'][$opt['id']][$managerId] = array_merge(
                $parameters['_reporting']['reportResults'][$opt['id']][$managerId],
                $reportManagersResult['result']
            );

        if(!empty($_handleReports))
            unset($_handleReports);
        if(!empty($reportManagersResult['exit']))
            break;
    }

    return array_merge($result,$reportManagersResult);
};

$_runAfter = function (&$parameters,&$errors,&$opt){

    //Override default results with what we saved during the run
    $opt['runResult']['result'] = $parameters['_reporting']['reportResults'][$opt['id']] ?? [];

};