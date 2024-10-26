<?php

namespace IOFrame\Util\CLI{

    define('IOFrameUtilCLICommonMailQueueFunctions',true);

    class CommonMailQueueFunctions{

        /** Sets default email params. Can be overwritten by inputs
         * @param array $parameters
         * @param array $overwrite
         * */
        public static function setEmailDefaults(array &$parameters, array $overwrite = []): void{
            \IOFrame\Util\PureUtilFunctions::createPathInObject($parameters,['email']);
            $defaults = array_merge(
                [
                    'batchSize'=>60,
                    'runtimeSafetyMargin'=>1,
                    'provider'=>'smtp',
                    'returnToQueue'=>[],
                    'overwriteSettings'=>[],
                    'inProgressQueue'=>null,
                    'successQueue'=>null,
                    'failureQueue'=>null,
                    'logTable'=>null
                ],
                $overwrite
            );
            \IOFrame\Util\PureUtilFunctions::setDefaults(
                $parameters['email'],
                $defaults
            );
        }

        /** Tries to create MailManager, generates error in case of failure
         * @param array $parameters
         * @param array $errors
         * @param array $opt
         * @return bool
         * */
        public static function tryToCreateMailManager(array &$parameters, array &$errors, array &$opt): bool{
            $verbose = $parameters['verbose'] ?? $parameters['test'] ?? false;
            try {
                $parameters['email']['MailManager'] = new \IOFrame\Managers\MailManager(
                    $parameters['defaultParams']['localSettings'],
                    array_merge(
                        $parameters['defaultParams'],
                        [
                            'verbose'=>$verbose,
                            'mode'=>$parameters['email']['provider']??null,
                            'mailSettingsOverwrite'=>$parameters['email']['overwriteSettings']??null,
                        ]
                    )
                );
                return true;
            }
            catch (\Exception $e){
                $outcomeDetails = ['exception'=>$e->getMessage(), 'time'=>time()];
                if($verbose)
                    echo json_encode($outcomeDetails,JSON_PRETTY_PRINT).EOL;
                \IOFrame\Util\PureUtilFunctions::createPathInObject($errors, [$opt['id'],'mail-queue-not-created'], $outcomeDetails, true);
                //TODO Log failure
                return false;
            }
        }

        /** Checks the received task's data, ensures all required parameters exist, sets all missing to null
         * @param array $parameters
         * @param array $errors
         * @param array $opt
         * @param array $taskResult
         * @param array $differentDefaults
         * @param bool $verbose
         * @return bool
         */
        public static function parseEmailTask(array &$parameters, array &$errors, array &$opt, array &$taskResult, array $differentDefaults = [], bool $verbose = false): bool {

            $taskResult['task']['data'] = $taskResult['task']['data'] ?? [];
            $required = [
                'to'=>true,
                'subject'=>true,
                'from'=>false,
                'body'=>false,
                'altBody'=>false,
                'attachments'=>false,
                'embedded'=>false,
                'stringAttachments'=>false,
                'cc'=>false,
                'bcc'=>false,
                'replies'=>false,
                'template'=>false,
                'varArray'=>false,
            ];
            $failedRequired = false;
            foreach ($required as $param => $req){
                if(!isset($taskResult['task']['data'][$param]) && $req){
                    $failedRequired = $param;
                    break;
                }
                else
                    $taskResult['task']['data'][$param] = $taskResult['task']['data'][$param]??null;
            }
            if(empty($taskResult['task']['data']['body']) && empty($taskResult['task']['data']['template'])){
                $failedRequired = 'bodyOrTemplate';
            }
            if($failedRequired){
                $outcomeDetails = ['id'=>$taskResult['task']['id']??'-', 'queue'=>$taskResult['queue'], 'time'=>time(), 'missing'=>$failedRequired];
                if($verbose)
                    echo json_encode($outcomeDetails,JSON_PRETTY_PRINT).EOL;
                \IOFrame\Util\PureUtilFunctions::createPathInObject($errors, [$opt['id'],'mail-queue-task-missing-required'], $outcomeDetails, true);
                return false;
            }
            return true;
        }

        /** Handles success or failure queues for mails
         * @param array $parameters
         * @param array $opt
         * @param array $taskResult
         * @param string $type
         * @param bool $verbose
         * @return array|null
         */
        public static function handleDefaultMailQueues(array &$parameters, array &$opt, array &$taskResult, string $type = 'failure', bool $verbose = false): ?array {

            $queues = [];
            if(!empty($parameters['email'][$type.'Queue']))
                $queues[$parameters['email'][$type.'Queue']] = $parameters['email'][$type.'QueueExp']??null;
            if(!empty($taskResult['task'][$type.'Queue']))
                $queues[$taskResult['task'][$type.'Queue']] = $taskResult['task'][$type.'QueueExp']??null;
            if(
                ($type === 'failure') &&
                !empty($taskResult['task']['retryLimit']) &&
                ( $taskResult['task']['_currentRetries']++ < min( $taskResult['task']['retryLimit'], $parameters['retries']??1 ) )
            )
                $queues[$taskResult['task'][$type.'Queue']] = $taskResult['task'][$type.'QueueExp']??null;
            if(!empty($queues)){
                return \IOFrame\Util\CLI\CommonJobRuntimeFunctions::pushToNextQueues($parameters,$opt,$taskResult,$queues,['verbose'=>$verbose]);
            }
            else
                return null;
        }

        /** Handles the result of
         * @param array $parameters
         * @param array $errors
         * @param array $opt
         * @param array $taskResult
         * @param callable[]|string[] $handlerFunctions Array of functions | strings. Array keys are possible listenToQueues outcome values,
         *                            as well as the value "_default" for handling the default case.
         *                            Array values can be functions that handle that case, or strings that match one of the keys
         *                            in this array, signifying a similar case.
         *                            For the defaults, and what the functions take as arguments, see the code in this function.
         *
         * @return array
         * */
        public static function handleMailTaskResult(array &$parameters, array &$errors, array &$opt, array &$taskResult, array $handlerFunctions = []): array {

            $defaultHandlerFunctions = [
                'success' => function(array &$parameters, array &$errors, array &$opt, array &$taskResult){

                    $test = $taskResult['task']['test'] ?? false;
                    $silent = $parameters['silent'] || ($taskResult['task']['silent'] ?? false);
                    $verbose = !$silent && ($parameters['verbose'] ?? $test);

                    if(!self::parseEmailTask($parameters,$errors,$opt,$taskResult, [], $verbose)){
                        return ['exit'=>false,'result'=>false];
                    }

                    try{
                        if(!empty($taskResult['task']['data']['body'])){
                            $sent = $test || $parameters['email']['MailManager']->sendMail(
                                    $taskResult['task']['data']['to'],
                                    $taskResult['task']['data']['subject'],
                                    $taskResult['task']['data']['body'],
                                    [
                                        'from'=> $taskResult['task']['data']['from']??null,
                                        'altBody'=> $taskResult['task']['data']['altBody']??null,
                                        'attachments'=> $taskResult['task']['data']['attachments']??null,
                                        'embedded'=> $taskResult['task']['data']['embedded']??null,
                                        'stringAttachments'=> $taskResult['task']['data']['stringAttachments']??null,
                                        'cc'=> $taskResult['task']['data']['cc']??null,
                                        'bcc'=> $taskResult['task']['data']['bcc']??null,
                                        'replies'=> $taskResult['task']['data']['replies']??null,
                                    ]
                                );
                            if ($verbose)
                                echo 'Sending mail with '.json_encode($taskResult['task']['data'],JSON_PRETTY_PRINT);
                        }
                        elseif(!empty($taskResult['task']['data']['template'])){
                            $sent = $test || $parameters['email']['MailManager']->sendMailTemplate(
                                    $taskResult['task']['data']['to'],
                                    $taskResult['task']['data']['subject'],
                                    $taskResult['task']['data']['template'],
                                    [
                                        'varArray'=> $taskResult['task']['data']['varArray']??[],
                                        'from'=> $taskResult['task']['data']['from']??null,
                                        'altBody'=> $taskResult['task']['data']['altBody']??null,
                                        'attachments'=> $taskResult['task']['data']['attachments']??null,
                                        'embedded'=> $taskResult['task']['data']['embedded']??null,
                                        'stringAttachments'=> $taskResult['task']['data']['stringAttachments']??null,
                                        'cc'=> $taskResult['task']['data']['cc']??null,
                                        'bcc'=> $taskResult['task']['data']['bcc']??null,
                                        'replies'=> $taskResult['task']['data']['replies']??null,
                                    ]
                                );
                            if ($verbose)
                                echo 'Sending mail with '.json_encode($taskResult['task']['data'],JSON_PRETTY_PRINT);
                        }
                        else
                            $sent = false;

                        if(!$sent){
                            throw new \Exception('Mailgun message '.(empty($taskResult['task']['data']['body'])?'from template '.$taskResult['task']['data']['template']:'without template').' not sent');
                        }

                        $sendResult = self::handleDefaultMailQueues($parameters, $opt, $taskResult, 'success', $verbose);
                        $outcomeDetails = ['id'=>$taskResult['task']['id']??'-', 'queue'=>$taskResult['queue'], 'outcome'=>'success', 'time'=>time(),'nextQueues'=>$sendResult];
                        if($verbose)
                            echo json_encode($outcomeDetails,JSON_PRETTY_PRINT).EOL;
                        \IOFrame\Util\PureUtilFunctions::createPathInObject($parameters, ['_queue','_results'], $outcomeDetails,true);
                        //TODO Log success
                        return ['exit'=>($parameters['email']['batchSize']-- <= 0),'result'=>false];
                    }
                    catch (\Exception $e){
                        $sendResult = self::handleDefaultMailQueues($parameters, $opt, $taskResult, 'failure', $verbose);

                        $outcomeDetails = ['id'=>$taskResult['task']['id']??'-', 'queue'=>$taskResult['queue'],  'time'=>time(), 'exception'=>$e->getMessage(),'nextQueues'=>$sendResult];
                        if($verbose)
                            echo json_encode($outcomeDetails,JSON_PRETTY_PRINT).EOL;
                        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors, [$opt['id'],'mail-queue-failed-to-send'], $outcomeDetails, true);
                        //TODO Log failure
                        return ['exit'=>false,'result'=>false];
                    }
                },
                'timed-out' => function(array &$parameters, array &$errors, array &$opt, array &$taskResult){
                    return ['exit'=>true,'result'=>true];
                },
                'max-returns' => 'returned-to-queue',
                'returned-to-queue' => function(array &$parameters, array &$errors, array &$opt, array &$taskResult){

                    $test = $taskResult['task']['test'] ?? false;
                    $silent = $parameters['silent'] || ($taskResult['task']['silent'] ?? false);
                    $verbose = !$silent && ($parameters['verbose'] ?? $test);

                    $remainingTime = max(0,\IOFrame\Util\CLI\CommonJobRuntimeFunctions::getRemainingRuntime($parameters,$opt));
                    $outcomeDetails = ['outcome'=>$taskResult['outcome'], 'id'=>$taskResult['task']['id']??'-', 'queue'=>$taskResult['queue'],  'time'=>time() ];
                    if($taskResult['outcome'] === 'returned-to-queue'){
                        $toSleep = min($taskResult['task']['sleepAfterReturn']??0,$remainingTime*1000000);
                        if($toSleep > 0)
                            usleep($toSleep);
                    }
                    else{
                        //TODO Log max returns
                    }
                    if($verbose)
                        echo json_encode($outcomeDetails,JSON_PRETTY_PRINT).EOL;
                    \IOFrame\Util\PureUtilFunctions::createPathInObject($parameters, ['_queue','_results'], $outcomeDetails,true);

                    return ['exit'=>false,'result'=>false];
                },
                '_default' => function(array &$parameters, array &$errors, array &$opt, array &$taskResult){

                    $test = $taskResult['task']['test'] ?? false;
                    $silent = $parameters['silent'] || ($taskResult['task']['silent'] ?? false);
                    $verbose = !$silent && ($parameters['verbose'] ?? $test);

                    $outcomeDetails = ['outcome'=>$taskResult['outcome'], 'errorDetails'=>$taskResult['errorDetails'],  'time'=>time(), 'id'=>$taskResult['task']['id']??'-', 'queue'=>$taskResult['queue'] ];
                    if($verbose)
                        echo json_encode($outcomeDetails,JSON_PRETTY_PRINT).EOL;
                    \IOFrame\Util\PureUtilFunctions::createPathInObject($errors, [$opt['id'],'mail-queue-manager-error'], $outcomeDetails,true);

                    //TODO Log failure
                    return ['exit'=>true,'result'=>false];

                },
            ];

            foreach ($handlerFunctions as $outcome=>$funcOrStr){
                $defaultHandlerFunctions[$outcome] = $funcOrStr;
            }

            //Either we target a defined function, a string pointing to a defined function, or the default handler.
            $defaultTargetHandler = $defaultHandlerFunctions[$taskResult['outcome']]??null;
            $targetHandler = is_callable($defaultTargetHandler) ?
                $defaultTargetHandler :
                (
                is_callable($defaultHandlerFunctions[$defaultTargetHandler]??null) ?
                    $defaultHandlerFunctions[$defaultTargetHandler] :
                    $defaultHandlerFunctions['_default']
                );

            return $targetHandler($parameters, $errors, $opt, $taskResult);
        }
    }

}