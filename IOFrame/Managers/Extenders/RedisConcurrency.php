<?php
namespace IOFrame\Managers\Extenders{
    define('IOFrameManagersExtendersRedisConcurrency',true);

    /** Uses Redis for mutex and queue functionality
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
    */
    class RedisConcurrency{
        /** param ?\IOFrame\Managers\RedisManager $r Initiated RedisManager*/
        protected ?\IOFrame\Managers\RedisManager $r = null;

        function __construct($RedisManager){
            if(empty($RedisManager->isInit)){
                return null;
            }
            else
                $this->r = $RedisManager;
        }

        /** Remember - this can pass through all the way to PHPRedis, but any functions defined in RedisManager come first
         * */
        public function __call($name, $arguments)
        {
            if(!is_array($arguments))
                $arguments = [$arguments];
            return call_user_func_array(array($this->r, $name), $arguments);
        }


        /**Tries to create a new Redis mutex.
         * IMPORTANT - usleep does not necessarily work properly on Windows. Linux's production environment should be fine.
         * @param string|string[] $locks Shared Identifier of the resource to lock.
         *                               If is array, means any of the keys can be used (e.g. ['key_1','key_2',...]).
         * @param string|null $key Potential value to use as identifier for verifying lock.
         * @param array $params of the form:
         *              'sec' => int, default 2 - How many seconds to hold they key for at most
         *              'maxWait' => int, default 4 - How many seconds to try until timeout.
         *                           This is NOT an exact upper limit to function runtime, but close to one.
         *              'randomDelay' => int, default 100,000 - Up to how many MICROSECONDS to wait before checking - e.g 1,000,000 is 1 second.
         *              'tries' => int, default 10 - How many times to try to get the mutex until timeout
         * @return array Where each item is of form
         *              <key, one of provided keys, even if it was a string> =>
         *                  <int|string result of the form:
         *                      -1 - could not got a mutex due to RedisManager not set, or failure to connect to Redis
         *                      0 - Got the mutex --IF $key was provided
         *                      <number larger than 0> - How long, in MILLISECONDS, an existing mutex still has left to live
         *                      <32 character string> - value of locked identifier on success --IF $key was NOT provided
         *                  >
         */
        function makeRedisMutex(string|array $locks, string $key = null, array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            //Set defaults
            if(!is_array($locks))
                $locks = [$locks];
            $sec = isset($params['sec']) ? (int)$params['sec'] : 2;
            $maxWait = isset($params['maxWait']) ? (int)$params['maxWait'] : 4;
            $randomDelay = isset($params['randomDelay']) ? (int)$params['randomDelay'] : 100000;
            $tries = isset($params['tries']) ? (int)$params['tries'] : 10;
            $sTime = (int)(($maxWait*1000000)/($tries*count($locks)));
            $keyWasProvided = !empty($key);
            $results = [];

            foreach ($locks as $lock){
                $results[$lock] = -1;
            }

            if(!$keyWasProvided){
                try{
                    $bytes = random_bytes(16);
                }
                catch(\Exception){
                    return $results;
                }
                $key = bin2hex($bytes);
            }

            if(!$this->r->isInit)
                return $results;

            //Sleep for a bit
            usleep(rand(0,$randomDelay));

            //Whether we got the lock
            $gotLock = false;

            for($i = 0; $i<$tries; $i++){
                if($gotLock)
                    break;

                foreach ($locks as $lock){

                    if($gotLock)
                        break;

                    //Try to lock the key IF IT DOESNT EXIST
                    if($verbose)
                        echo 'Setting '.$lock.' to '.$key.' for '.$sec.' seconds'.EOL;

                    $result = $test || $this->r->call('set',[$lock, $key,['nx', 'ex'=>$sec]]);

                    //See if we got the mutex
                    if($result)
                        $result = $this->r->call('get',[$lock]);
                    if($verbose && $result)
                        echo 'Got '.$result.' - expected '.$key.EOL;

                    if( ($result === $key) || $test ){
                        $gotLock = true;
                    }
                    else{
                        if($sTime > 1000000)
                            sleep(floor($sTime/1000000));
                        usleep($sTime%1000000);
                    }

                    if(!$gotLock){
                        $ttlMS = $this->r->call('pttl',$lock);
                        $results[$lock] = $ttlMS > 0 ? $ttlMS : 1;
                    }
                    else{
                        $results[$lock] =  $key;
                    }
                }
            }

            return $results;
        }

        /** Releases redis mutex
         * @param string $key Shared Identifier of the resource to lock
         * @param string|null $value Potential value to check - if not provided, wont check the value
         * @param array $params of the form:
         * @return int
         *              -1 - could not got a mutex due to RedisManager not set, or failure to connect to Redis
         *              0 - Released the mutex
         *              1 - could not release a mutex due to $value not matching current value --IF $value was NOT provided
         */
        function releaseRedisMutex(string $key, string $value = null, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(!$this->r->isInit)
                return -1;

            $result = 0;

            $valueWasProvided = !empty($value);
            if($valueWasProvided){
                $result = $this->r->call('get',$key);
                if($verbose)
                    echo 'Got result '.$result.EOL;
                if($result && $result!==$value)
                    return 1;
                elseif($result === false)
                    return -1;
            }

            if(!$test)
                $result = $this->r->call('del',$key);
            if($verbose)
                echo 'Deleting from cache - '.$key.EOL;

            if($result > 0)
                return 0;
            else
                return -1;
        }

        /** Pushes a task / event to a queue(s).
         * This is, in essence, a glorified LPUSH wrapper, but it helps ensure consistent event structure throughout the system.
         * @param string|string[] $queues Queue to send the event to. If array, sends event to multiple queues.
         * @param mixed $data Event data to send. Merged into the event JSON under 'data'.
         * @param array $opt Optional inputs. Any keys except 'data','test','verbose' and 'silent' will be merged into event JSON, but defaults are:
         *                    'type': string, default null - event type, in case queue supports multiple types
         *                    'from': string, default null - some id of the event sender, if there can be multiple
         *                    'to': string, default null - some id of the event receiver, if there can be multiple
         *                    'id': string, default null - some id of the event that's being sent
         *                    'inProgressQueue': string, default null - signifies the event should be moved to this queue while in progress
         *                    'inProgressQueueExp': int, default null - If passed, progress queue should be set to expire after this many seconds
         *                    'successQueue': string, default null - signifies the event should be moved to this queue upon success
         *                    'successQueueExp': int, default null - If passed, success queue should be set to expire after this many seconds
         *                    'failureQueue': string, default null - signifies the event should be moved to this queue upon failure
         *                    'failureQueueExp': int, default null - If passed, failure queue should be set to expire after this many seconds
         *                    'retryLimit': int, default 1 - how many times to retry the task before considering it failed.
         *                    '_currentRetries': int, default 0 - Operational value, how many times the task has been retried.
         *                    'returnLimit': int, default 10 - how many times to return task to queue if picked up by irrelevant listener
         *                     '_currentReturns': int, default 0 - Operational value, how many times the task has been returned to queue.
         *                    'sleepAfterReturn': int, default 1,000,000 - how many microseconds to wait after returning task to queue (default 1s)
         *                    '_history': int, default [] - Operational value, history of handling this task
         *                    'queuePrefix' - string, default 'queue_' - prefix to queue key(s)
         *                    'test': bool, default false - Whether this task should be handled in test mode (no side effects)
         *                    'silent': bool, default false - Whether this task should be handled silently (logging still applies)
         *                    'verbose': bool, default !$silent && $test - Whether this task should print verbose output
         *                    ...
         *                   *Note - 'test' is expected to be respected by the receiver, while silent/verbose are only for this function
         * @param array $params of the form:
         *                  verbose - for this function only
         * @return array of the form
         *              <queueName, string - queue name> =>
         *                  <code, int - code, one of:
         *                  -1 - Could not push event due to redis not being initiated
         *                  0 - Failure to push event due to key existing and not being a list, or connection error
         *                  >0 - number of elements in queue
         * */
        function pushToQueues(string|array $queues, mixed $data, array $opt = [], array $params = []): array {

            if(!is_array($queues))
                $queues = [$queues];

            $results = [];

            $task = [
                'data'=>$data
            ];

            $opt['test'] = $opt['test']??false;

            $silent = $params['silent']??false;
            $verbose = !$silent && ($params['verbose'] ?? $opt['test'] ?? false);

            $defaults = ['type'=>null,'from'=>null,'id'=>null,'inProgressQueue'=>null,'successQueue'=>null,'failureQueue'=>null,'retryLimit'=>1,
                'returnLimit'=>10,'sleepAfterReturn'=>1000000, '_currentRetries'=>0, '_currentReturns'=>0, '_history' => [],'queuePrefix'=>'queue_'];

            foreach ($defaults as $default => $val){
                $opt[$default] = $opt[$default]??$val;
            }

            $task = array_merge($task,$opt);

            foreach ($queues as $queue){
                if(!$this->r->isInit){
                    $results[$queue] = -1;
                    continue;
                }
                $key = $opt['queuePrefix'].$queue;
                $results[$queue] = (int)$this->r->lPush($key,json_encode($task));
                if($verbose)
                    echo ($results[$queue]? 'Pushed': 'Could not push').' task '.json_encode($task,JSON_PRETTY_PRINT).' to queue '.$queue.EOL;
            }

            return $results;
        }

        /** Receives a task / event from a queue(s).
         * @param string|string[] $queues Queue to listen to. If array, listens to multiple queues and gets the first event in either.
         * @param array $returnToQueue Returns task back to the top queue it was taken from, based on task details:
         *                             'idIs' - string|string[] - expression/array of expressions that match id
         *                             'idIsNot' - string|string[]
         *                             'typeIs' - string|string[]
         *                             'typeIsNot' - string|string[]
         *                             'toIs' - string|string[]
         *                             'toIsNot' - string|string[]
         *                             'fromIs' - string|string[]
         *                             'fromIsNot' - string|string[]
         *                             'func' - callable function($task,$queue,$params) that returns true if task needs to be returned to queue
         *                             * Note - if a task is returned this way, it's up to the caller to handle this outcome -
         *                               e.g. wait for N seconds before reading the queue again.
         * @param array $params of the form:
         *              'timeout' - int, default 10, min 1 - how many seconds to wait (blocking).
         *                          "indefinite" intentionally not implemented - PHP has have max_execution_time for a good reason.
         *              'queuePrefix' - string, default 'queue_' - prefix to queue key(s)
         * @return array of the form:
         *         {
         *              outcome: string|null, if we didn't get the task properly, one of the following strings:
         *                     'success' - successfully got item
         *                     'timed-out' - time out reached without reading items
         *                     'invalid-format' - task format invalid
         *                     'max-returns' - Can no longer return item to queue
         *                     'returned-to-queue' - item returned to queue
         *                     'failed-to-return' - failed to return item to queue (very bad if unhandled)
         *                     'not-init' - redis not initiated
         *                     'error' - redis connection error
         *              task: array|null, the task we potentially received, on outcomes 'success', 'returned-to-queue', 'failed-to-return'
         *              queue: string|null, the queue we received / returned the task from / to
         *              errorDetails: array|null, only populated in case of 'returned-to-queue' / 'failed-to-return':
         *                      'queue' => string, which queue the item was (hopefully) returned to
         *                      'returnedBecause' => string, one of the $returnToQueue keys that caused the failure.
         *         }
         * */
        function listenToQueues(string|array $queues, array $returnToQueue = [], array $params = []): array {

            $queuePrefix = $params['queuePrefix']??'queue_';
            $timeout = (int)max($params['timeout']??10,1);

            if(!is_array($queues))
                $queues = [$queues];
            $queues = array_map(function ($key)use($queuePrefix){return $queuePrefix.$key;},$queues);

            $results = [
                'outcome'=>'not-init',
                'task'=>null,
                'queue'=>null,
                'errorDetails'=>null
            ];

            if(!$this->r->isInit)
                return $results;

            $taskInfo = $this->r->brPop($queues,$timeout);

            if(empty($taskInfo) || !is_array($taskInfo)){
                $results['outcome'] = 'timed-out';
                return $results;
            }

            $queueFrom = $taskInfo[0];
            $task = $taskInfo[1];

            if(!\IOFrame\Util\PureUtilFunctions::is_json($task)){
                $results['outcome'] = 'invalid-format';
                return $results;
            }

            $task = json_decode($task,true);

            $results['task'] = $task;
            $results['queue'] = $queueFrom;

            $needsToBeReturned = false;
            $returnReason = null;

            if(!empty($returnToQueue['func']) && !is_callable($returnToQueue['func'])){
                $needsToBeReturned = $returnToQueue['func']($task,$queueFrom,$params);
                $returnReason = 'func';
            }

            $defaultChecks = ['id','type','to','from'];
            $defaultCheckSuffixes = ['Is','Not'];

            foreach ($defaultChecks as $checkBase){
                if($needsToBeReturned)
                    break;
                foreach ($defaultCheckSuffixes as $suffix){
                    if($needsToBeReturned)
                        break;
                    if(!empty($returnToQueue[$checkBase.$suffix])){
                        if(!is_array($returnToQueue[$checkBase.$suffix]))
                            $returnToQueue[$checkBase.$suffix] = [ $returnToQueue[$checkBase.$suffix] ];
                        $anyMatch = false;
                        foreach ($returnToQueue[$checkBase.$suffix] as $exp){
                            if(
                                !empty($task[$checkBase]) &&
                                preg_match('/'.$exp.'/',$task[$checkBase])
                            ){
                                if(($suffix === 'Not')){
                                    $needsToBeReturned = true;
                                    $returnReason = $checkBase.$suffix;
                                    break;
                                }
                                else{
                                    $anyMatch = true;
                                }
                            }
                        }
                        if(($suffix === 'Is') && !$anyMatch){
                            $needsToBeReturned = true;
                            $returnReason = $checkBase.$suffix;
                        }
                    }
                }
            }

            if($needsToBeReturned){

                if($task['_currentReturns']++ >= $task['returnLimit']){
                    $results['outcome'] = 'max-returns';
                    return $results;
                }

                $task['_history'][] = ['_event' => 'return-to-queue', '_time' => time(), 'try' => $task['_currentReturns'], 'queue' => $queueFrom];
                $res = $this->r->rPush($queueFrom,json_encode($task));

                $results['outcome'] = $res !== false? 'returned-to-queue' : 'failed-to-return';
                $results['errorDetails'] = $returnReason;
                return $results;
            }

            $results['outcome'] = 'success';
            return $results;
        }
    }
}