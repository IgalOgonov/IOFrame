<?php
namespace IOFrame\Handlers\Extenders{
    define('IOFrameHandlersExtendersRateLimiting',true);

    /** This handler is meant to handle rate limiting.
     *  In this context, rate limiting means 2 things:
     *    - Simply prevent an action X (say, trying to log into a user account) by Y (say, a specific IP) from occurring more than once per Z (can be once per 2 seconds, etc)
     *    - Apply the above but only after certain conditions were met. Those conditions are represented in IOFrame using the Event Rulebooks also used by SecurityHandler.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class RateLimiting extends \IOFrame\Handlers\SecurityHandler{

        /**
         * @var ?\IOFrame\Managers\LockManager A LockManager used
         */
        public ?\IOFrame\Managers\LockManager $LockManager = null;

        /**
         * @var array A map of event types <=> table names
         */
        protected array $eventTableMap = [
            0 => [
                'table'=>'IP_EVENTS',
                'lockPrefix'=>'ip_event_lock_',
                'limitTTLPrefix'=>'ip_event_limited_',
                'key'=>'IP'
            ],
            1 => [
                'table'=>'USER_EVENTS',
                'lockPrefix'=>'user_event_lock_',
                'limitTTLPrefix'=>'user_event_limited_',
                'key'=>'ID'
            ]
        ];

        /**
         * Basic construction function
         * @param \IOFrame\Handlers\SettingsHandler $settings local settings handler.
         * @param array $params Typical default settings array
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, $params = []){

            $this->LockManager = new \IOFrame\Managers\LockManager($settings->getSetting('absPathToRoot').'/localFiles/temp');

            if(isset($params['eventTableMap']))
                $this->eventTableMap = $params['eventTableMap'];

            parent::__construct($settings,$params);
        }

        /** Check whether a specific action can be performed.
         * If it can, locks it for the specified duration. Returns the relevant code.
         *
         * @param int $category Category, be default 0 for IP, 1 for users
         * @param mixed $identifier Identifier, be that user ID or IP
         * @param int $action Action you wish to lock. Must not accidentally match any other valid cache name/identifier combination (e.g "ioframe_events_meta_6")
         * @param int $sec Once per how many seconds can this action be performed.
         * @param array $params of the form:
         *              'randomDelay' => int, default 1,000 - Up to how many MICROSECONDS to wait before checking - e.g 1,000,000 is 1 second.
         *              'tries' => int, default 1 - How many times to try to get the mutex until timeout. Default means do not retry.
         *              'maxWait' => int, default 1 - How many seconds to try until timeout, relevant if "tries" is over 1.
         * @return int|string
         *              -2 - RedisManager not initiated OR invalid category.
         *              -1 - could not got a mutex due to RedisManager not set, or failure to connect to Redis
         *              <number larger than 0> - How long, im milliseconds, an existing mutex still has left to live
         *              <32 character string> - value of locked identifier on success
         */
        function checkAction(int $category, mixed $identifier, int $action, int $sec, array $params): int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Notice those statements MODIFY $params, not just extract data from them.
            $params['maxWait'] = $params['maxWait'] ?? 1;
            $params['randomDelay'] = $params['randomDelay'] ?? 1000;
            $params['tries'] = $params['tries'] ?? 1;
            $params['sec'] = $sec;

            if($this->RedisManager === null || !$this->RedisManager->isInit ||!isset($this->eventTableMap[$category])){
                $this->logger->critical('failed-to-check-action',['category'=>$category,'id'=>$identifier,'params'=>$params]);
                return -2;
            }
            $categoryPrefix = $this->eventTableMap[$category]['lockPrefix'];
            $lock = $categoryPrefix.$identifier.'_'.$action;
            $ConcurrencyHandler = new \IOFrame\Managers\Extenders\RedisConcurrency($this->RedisManager);
            $res = $ConcurrencyHandler->makeRedisMutex($lock,null,$params);
            if($res < 0){
                $this->logger->critical('failed-to-acquire-action-lock',['category'=>$category,'id'=>$identifier,'params'=>$params]);
            }
            return $res[$lock];
        }

        /** An in-depth check against the relevant events table.
         * Checks whether the <identifier> is currently limited, and if yes, returns for now long.
         *
         * @param int $category Category, be default 0 for IP, 1 for users
         * @param mixed $identifier Identifier, be that user ID or IP
         * @param int $action Action you wish to check.
         * @param array $params of the form
         *                      'checkExpiry' => bool, default false. If true, checks for expiry instead of until when it's limited
         * @return int
         *              -2 - RedisManager not initiated OR invalid category.
         *              -1 - DB connection failure OR redis connection failure
         *               0 - Action not limited
         *               <number bigger than 0> - TTL (in seconds) until limit expires.
         */
        function checkActionEventLimit(int $category, mixed $identifier, int $action, array $params): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $checkExpiry = $params['checkExpiry'] ?? false;
            $columnToCheck = $checkExpiry ? 'Sequence_Expires' : 'Sequence_Limited_Until';

            if($this->RedisManager === null || !$this->RedisManager->isInit ||!isset($this->eventTableMap[$category])){
                $this->logger->critical('failed-to-check-action-event',['category'=>$category,'id'=>$identifier,'action'=>$action,'params'=>$params]);
                return -2;
            }

            $cacheIdentifier = $this->eventTableMap[$category]['limitTTLPrefix'].$identifier.'_'.$action;
            $result = false;
            if(!$checkExpiry){
                //Try to get the current limit from cache
                if($verbose)
                    echo 'Getting cache '.$cacheIdentifier.EOL;
                $result = $this->RedisManager->call('get',$cacheIdentifier);
            }
            //If we failed, get it from the database
            if($result === false){
                $dbResult = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().$this->eventTableMap[$category]['table'],
                    [
                        [
                            $this->eventTableMap[$category]['key'],
                            [$identifier,'STRING'],
                            '='
                        ],
                        [
                            'Event_type',
                            $action,
                            '='
                        ],
                        [
                            'Sequence_Expires',
                            ['UNIX_TIMESTAMP()','ASIS'],
                            '>'
                        ],
                        'AND'
                    ],
                    [$columnToCheck],
                    $params
                );
                if($dbResult === -2){
                    $this->logger->critical('failed-to-check-action-event-table',['category'=>$category,'id'=>$identifier,'action'=>$action,'params'=>$params]);
                    return -1;
                }

                if(!empty($dbResult[0][$columnToCheck])){
                    $result = $dbResult[0][$columnToCheck];
                    if(!$checkExpiry && (int)$result > time()){
                        if($verbose)
                            echo 'Setting cache '.$cacheIdentifier.' to '.$result.' for '.((int)$result - time()).' seconds'.EOL;
                        if(!$test)
                            $this->RedisManager->call('set',[$cacheIdentifier,$result,['px'=>(int)$result - time()]]);
                    }
                }
            }
            return $result === false? 0 : (int)$result - time();
        }

        /** Clears a limit.
         * May also remove IP from blacklist, or clear a user from suspicious / banned status.
         *
         * @param int $category Category, be default 0 for IP, 1 for users
         * @param mixed $identifier Identifier, be that user ID or IP
         * @param int $action Action you wish to clear.
         * @param array $params of the form
         *                      'removeBlacklisted' => bool, default false. If true, also removes IPs from blacklist.
         *                      'removeBanned' => bool, default false. If true, also unbans user
         *                      'removeSuspicious' => bool, default false. If true, also makes user not-suspicious.
         * @return int
         *              -2 - RedisManager not initiated OR invalid category.
         *              -1 - DB connection failure OR redis connection failure
         *               0 - Action cleared
         */
        function clearActionEventLimit(int $category, mixed $identifier, int $action, array $params): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $removeBlacklisted = $params['removeBlacklisted'] ?? false;
            $removeBanned = $params['removeBanned'] ?? false;
            $removeSuspicious = $params['removeSuspicious'] ?? false;

            if($this->RedisManager === null || !$this->RedisManager->isInit ||!isset($this->eventTableMap[$category])){
                $this->logger->critical('failed-to-clear-action-event',['category'=>$category,'id'=>$identifier,'action'=>$action,'params'=>$params]);
                return -2;
            }

            $cacheIdentifier = $this->eventTableMap[$category]['limitTTLPrefix'].$identifier.'_'.$action;
            if($verbose)
                echo 'Clearing limit '.$cacheIdentifier.EOL;
            if(!$test)
                $this->RedisManager->call('del',$cacheIdentifier);

            /*Not that this not only deletes all of the active limits, but also all expired ones too.*/
            $deletionResult = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().$this->eventTableMap[$category]['table'],
                [
                    [
                        $this->eventTableMap[$category]['key'],
                        [$identifier,'STRING'],
                        '='
                    ],
                    [
                        'Event_type',
                        $action,
                        '='
                    ],
                    'AND'
                ],
                $params
            );
            if($deletionResult === -2){
                $this->logger->critical('failed-to-clear-action-event-table',['category'=>$category,'id'=>$identifier,'action'=>$action,'params'=>$params]);
                return -1;
            }

            //See if we need to remove anything extra
            if( ($category === 0 && $removeBlacklisted) || ( $category === 1 && ($removeBanned || $removeSuspicious) ) ){
                if($category === 0)
                    $dbResult = $this->SQLManager->deleteFromTable(
                        $this->SQLManager->getSQLPrefix().'IP_LIST',
                        [
                            [
                                'IP',
                                [$identifier,'STRING'],
                                '='
                            ],
                            [
                                'IP_Type',
                                0,
                                '='
                            ],
                            'AND'
                        ],
                        $params
                    );
                else{
                    $updateArray = [];
                    if($removeBanned)
                        $updateArray[] = 'Banned_Until = NULL';
                    if($removeSuspicious)
                        $updateArray[] = 'Suspicious_Until = NULL';
                    $dbResult = $this->SQLManager->updateTable(
                        $this->SQLManager->getSQLPrefix().'USERS_EXTRA',
                        $updateArray,
                        [
                            'ID',
                            $identifier,
                            '='
                        ],
                        $params
                    );
                }
                if($dbResult !== true){
                    $this->logger->critical('failed-to-clear-action-event-db-table',['category'=>$category,'id'=>$identifier,'action'=>$action,'params'=>$params]);
                    return -1;
                }
            }

            return 0;
        }

    }

}