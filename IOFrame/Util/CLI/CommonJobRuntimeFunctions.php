<?php

namespace IOFrame\Util\CLI{

    define('IOFrameUtilCLICommonJobRuntimeFunctions',true);

    class CommonJobRuntimeFunctions{

        /** Calculates remaining runtime, assuming default parameter names
         * @param array $parameters
         * @param array $opt
         * @param bool $nano
         * */
        public static function getRemainingRuntime(array $parameters, array $opt, bool $nano = false): int {
            return (int)( $parameters['maxRuntime'] * ($nano? 1000000 : 1) - floor($opt['timingMeasurer']->timeElapsed()/($nano? 1 : 1000000)) );
        }

        /** Pushes a task to the next queue
         * @param array $parameters
         * @param array $opt
         * @param array $taskResult
         * @param array $queues Potential queues of the form QueueName => QueueExp
         * @param array $params
         * @return array | null
         */
        public static function pushToNextQueues(array $parameters, array $opt, array $taskResult, array $queues = [], array $params = []): ?array {
            $verbose = $params['verbose']??false;
            if(!empty($queues)){
                $taskMetaData = $taskResult['task'];
                $data = $taskMetaData['data'];
                unset($taskMetaData['data']);
                $taskMetaData['_history'][] = ['_event' => 'sent-to-queue', '_time' => time(), 'queue' => $taskResult['queue']];
                $result = $opt['concurrencyHandler']->pushToQueues(array_keys($queues),$data,$taskMetaData,['verbose'=>$verbose]);
                foreach ($queues as $key => $exp){
                    if($exp){
                        $parameters['defaultParams']['RedisManager']->expire(($parameters['_queue']['prefix']).$key,$exp);
                    }
                }
                if($verbose)
                    echo 'Task '.($taskResult['task']['id']??'-').' from queue '.$taskResult['queue'].' send to queues '.json_encode($queues).EOL;
                return $result;
            }
            else
                return null;
        }
    }

}