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
        echo 'Starting queue manager '.$opt['id'].EOL;
    $parameters['_startTime'] = time();
    return true;
};

$_run = function (&$parameters,&$errors,&$opt){
    $verbose = !$parameters['silent'] && $parameters['verbose'];
    $result = ['exit'=>true,'result'=>false];

    $path = $parameters['defaultParams']['absPathToRoot'].'/'.($parameters['_queue']['url']??null);

    if(!is_file($path)){
        if($verbose)
            echo 'queue '.$opt['id'].' no file!'.EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['no-queue-include-file',$opt['id']],true);
        return $result;
    }

    if(empty($parameters['_queue']['listenTo'])){
        if($verbose)
            echo 'queue '.$opt['id'].' no queue(s)!'.EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['no-queue-to-listen-to',$opt['id']],true);
        return $result;
    }
    elseif (!is_array($parameters['_queue']['listenTo']))
        $parameters['_queue']['listenTo'] = [$parameters['_queue']['listenTo']];

    $parameters['_queue']['prefix'] = $parameters['_queue']['prefix']??'queue_';
    $parameters['_queue']['_results'] = $parameters['_queue']['_results']??[];

    require $path;

    if(empty($_handleQueue) || !is_callable($_handleQueue)){
        if($verbose)
            echo 'queue '.$opt['id'].' no function!'.EOL;
        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['no-queue-handle-function',$opt['id']],true);
        return $result;
    }
    return $_handleQueue($parameters,$errors,$opt);
};

$_runAfter = function (&$parameters,&$errors,&$opt){
    //Override default results with what we saved during the run
    $opt['runResult']['result'] = $parameters['_queue']['_results'];

    if(!$parameters['silent'] && $parameters['verbose']){
        echo 'Exiting queue manager '.$opt['id'].EOL;
        if(!empty($parameters['_queue']['summary'])){
            if(!is_array($parameters['_queue']['summary']))
                $parameters['_queue']['summary'] = [$parameters['_queue']['summary']];
            foreach ($parameters['_queue']['summary'] as $sumItem)
                echo $sumItem.EOL;
        }
    }
};