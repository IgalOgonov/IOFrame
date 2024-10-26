<?php
$_runBefore = function (&$parameters,&$errors,$opt){
    if($parameters['verbose'])
        echo 'Starting job '.$opt['id'].EOL;
    return true;
};

$_run = function (&$parameters,&$errors,$opt){
    $parameters['sleepy'] = $parameters['sleepy']??[];

    $parameters['sleepy']['sleep'] = $parameters['sleepy']['sleep'] ?? 10;
    $parameters['sleepy']['iterations'] = $parameters['sleepy']['iterations'] ?? 3;
    $parameters['sleepy']['currentIterations'] = $parameters['sleepy']['currentIterations'] ?? 0;
    $parameters['sleepy']['result'] = $parameters['sleepy']['result'] ?? true;

    /* Remember - it's important to check elapsed time inside each job when dealing with time-consuming operations
       (e.g. before processing a new DB table), so  we don't stray too far from maxRuntime */
    sleep(min($parameters['sleepy']['sleep'],$parameters['maxRuntime'] - floor($opt['timingMeasurer']->timeElapsed()/1000000)));
    $parameters['sleepy']['currentIterations']++;

    return ['exit'=>$parameters['sleepy']['iterations']<=$parameters['sleepy']['currentIterations'],'result'=>$parameters['sleepy']['result']];
};

$_runAfter = function (&$parameters,&$errors,$opt){
    if($parameters['verbose'])
        echo 'Finishing job '.$opt['id'].EOL;
    $errorsToSet = $parameters['sleepy']['errors'] ?? [];
    if(!empty($errorsToSet)){
        $errors['sleepy_job'] = $errors['sleepy_job']??[];
        foreach ($errorsToSet as $error => $val)
            $errors['sleepy_job'][$error] = $val;
    }
};