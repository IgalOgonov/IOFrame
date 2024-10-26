<?php

/* Parameters explained in cron-management.php dynamicJobProperties variable default definitions */

$_runBefore = function (&$parameters,&$errors,$opt){
    if($parameters['verbose'])
        echo 'Starting job '.$opt['id'].EOL;
    $parameters['_startTime'] = time();
    return true;
};

$_run = function (&$parameters,&$errors,&$opt){
    return \IOFrame\Util\CLI\ArchiveOrClean\CleanArchiveFunctions::cleanArchiveCommonRun($parameters);
};

$_runAfter = function (&$parameters,&$errors,$opt){
    if($parameters['verbose'])
        echo 'Finishing job '.$opt['id'].EOL;
    \IOFrame\Util\CLI\ArchiveOrClean\CleanArchiveFunctions::cleanArchiveCommonRunAfter($parameters,$errors,$opt['timingMeasurer']->timeElapsed(),$opt['id']);
};