<?php
/* TODO Decide whether to add WIP folder for subsequent log managers not having to re-fetch handled logs back from DB
*/

$_runBefore = function (&$parameters,&$errors,$opt){
    if(!$parameters['silent'] && $parameters['verbose'])
        echo 'Starting logging job '.$opt['id'].EOL;
    return true;
};

$_run = function (&$parameters,&$errors,&$opt){
    $verbose = !$parameters['silent'] && $parameters['verbose'];
    $result = ['exit'=>false,'result'=>true];

    $logManagers = $parameters['_logging']['logManagers']??null;

    if(empty($logManagers)){
        if($verbose)
            echo 'No logging managers in '.$opt['id'].EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['no-logging-managers',$opt['id']],true);
        return array_merge($result,['exit'=>true]);
    }
    foreach ($logManagers as $managerId => $info){

        if(!is_file($parameters['defaultParams']['absPathToRoot'].'/'.($info['manager']??null))){
            if($verbose)
                echo 'Invalid log manager in '.$managerId.EOL;
            \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['invalid-logging-manager',[$opt['id']],$managerId],true);
            return array_merge($result,['exit'=>true]);
        }
    }

    $logManagersResult = ['exit'=>false];
    foreach ($logManagers as $managerId => $info){

        require $parameters['defaultParams']['absPathToRoot'].'/'.($info['manager']??null);

        if(empty($_handleLogs) || !is_callable($_handleLogs)){
            if($verbose)
                echo 'Logs '.$opt['id'].' manager '.$managerId.' no function!'.EOL;
            \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['no-logging-handle-function',$opt['id'],$managerId],true);
            $logManagersResult = array_merge($logManagersResult,['exit'=>true]);
        }
        else
            $logManagersResult = array_merge($logManagersResult,$_handleLogs($parameters,$errors,$opt,$managerId));

        if(empty($parameters['_logging']['logResults'][$opt['id']][$managerId]))
            \IOFrame\Util\PureUtilFunctions::createPathInObject($parameters,['_logging','logResults',$opt['id'],$managerId]);
        if($logManagersResult['result'])
            $parameters['_logging']['logResults'][$opt['id']][$managerId][] = $logManagersResult['result'];

        if(!empty($_handleLogs))
            unset($_handleLogs);
        if(!empty($logManagersResult['exit']))
            break;
    }

    return array_merge($result,$logManagersResult);
};

$_runAfter = function (&$parameters,&$errors,&$opt){

    //Override default results with what we saved during the run
    $opt['runResult']['result'] =  $parameters['_logging']['logResults'][$opt['id']];

};