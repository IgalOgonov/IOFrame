<?php

/* Possible results ($results => $id is an array of objects):
 * $id => {'outcome'=>'success','id'=>$taskId, 'queue'=>$queue,'time'=>time(), 'nextQueues' => <array | null, RedisConcurrency->pushToQueues() or null if no queues> }
 * $id => {'outcome'=><'max-returns'|'returned-to-queue'>,'id'=>$taskId, 'queue'=>$queue,'time'=>time()}
 *
 * Possible errors (each $errors => $id => $errorType is an array of objects):
 * $id => 'mail-queue-not-created' => {'exception'=>$exceptionMsg, 'time'=>time()}
 * $id => 'mail-queue-task-missing-required' => {'id'=>$taskId, 'queue'=>$queue, 'time'=>time(), 'missing'=>$missingRequiredFieldInTask}
 * $id => 'mail-queue-failed-to-send' => {'id'=>$taskId, 'queue'=>$queue, 'time'=>time(), 'exception'=>$exceptionMsg, 'nextQueues' => <array | null, RedisConcurrency->pushToQueues() or null if no queues> }
 * $id => 'mail-queue-manager-error' => {'outcome'=>$outcome, 'id'=>$taskId, 'queue'=>$queue, 'time'=>time(), 'errorDetails'=>$taskResult['errorDetails']}
 *
 * We only exit the loop on unknown / default error, timed-out, or on success if we reached our batch size quota
*/
$_handleQueue = function(&$parameters,&$errors,&$opt){

    $result = ['exit'=>true,'result'=>false];

    \IOFrame\Util\CLI\CommonMailQueueFunctions::setEmailDefaults($parameters);

    if(!\IOFrame\Util\CLI\CommonMailQueueFunctions::tryToCreateMailManager($parameters, $errors, $opt))
        return $result;

    //Listen to queue up to remaining time, but consider the safety margin
    $remainingTime = \IOFrame\Util\CLI\CommonJobRuntimeFunctions::getRemainingRuntime($parameters,$opt) - $parameters['email']['runtimeSafetyMargin'];
    if($remainingTime <= 0)
        return $result;

    $taskResult = $opt['concurrencyHandler']->listenToQueues(
        $parameters['_queue']['listenTo'],
        $parameters['email']['returnToQueue'],
        ['timeout'=>$remainingTime,'queuePrefix'=>$parameters['_queue']['prefix']]
    );

    return \IOFrame\Util\CLI\CommonMailQueueFunctions::handleMailTaskResult($parameters, $errors, $opt, $taskResult);
};