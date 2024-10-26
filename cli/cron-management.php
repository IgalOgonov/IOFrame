<?php
/* This interface is meant to be used for cron job management.
 * Read the desc for more information
 * */
require 'commons/ensure_cli.php';
if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../main/core_init.php';

$initiationDefinitions = [
    '_action'=>[
        'desc'=>'Action value from cli',
        'cliFlag'=>['-a','--action'],
        'envArg'=>['IOFRAME_CLI_CRON_MANAGEMENT_ACTION'],
        'func'=>function($action){
            switch ($action){
                case 'st':
                case 'status':
                    $action = 'jobsStatus';
                    break;
                case 'd':
                case 'dynamic':
                    $action = 'dynamicCronJobs';
                    break;
                default:
            }
            return $action;
        }
    ],
    'jobs'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
            [
                'String|String[] of cron jobs to run.',
                'Each script needs to include a variable function $_run, which returns a results object.',
                'It can also include a variable function $_runBefore, which runs first, and $_runAfter, which runs after the main loop.',
                'The structure of this object is found in the docs, the default scripts, and can be inferred from the dynamicCronJobs action.',
                'All scripts will be checked against project root, then global root.',
                'Files/folders are ran in the order they were defined.',
                'Files are not duplicated in case of overlapping files/folders.',
            ],
            [
                'To set specific inclusion options with folders, see --jobs-include-options.',
                'For fore information, see dynamicCronJobs action description.'
            ],
        ),
        'cliFlag'=>['-j','--jobs'],
        'envArg'=>['IOFRAME_CLI_CRON_MANAGEMENT_JOBS'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'required'=>false]
    ],
    'jobsIncludeOptions'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
            [
                'JSON encoded array that corresponds to IOFrame\Util\FileSystemFunctions::fetchAllFolderAddresses() $params',
                'Options for specific files to include. Will enforce returnFolders:false, include:[\'\.php$\']',
            ],
            [
                'Those options affect only sub-folders of folder(s) in --jobs.'
            ],
        ),
        'cliFlag'=>['--jio','--jobs-include-options'],
        'envArg'=>['IOFRAME_CLI_CRON_MANAGEMENT_JOBS_INCLUDE_OPTIONS'],
        'hasInput'=>true,
        'validation'=>['type'=>'json','required'=>false]
    ],
    'justRunIt'=>[
        'desc'=>'Runs the jobs, without checking dynamicProperties active and maxParallel',
        'cliFlag'=>['--jri','--just-run-it'],
        'envArg'=>['IOFRAME_CLI_CRON_MANAGEMENT_JUST_RUN_IT'],
        'hasInput'=>false
    ],
    'dynamicJobProperties'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
            [
                'Properties for executing dynamic cron job',
                'Each key points to a project/global job file, with an array of properties passed to the job.',
                'Alternatively, you can run the same file multiple times, if the key is an alias, AND you specify a URL inside',
            ],
            [
                'You can check dynamicCronJobs action in the file for defaults, and the linked scripts for structure examples.'
            ],
        ),
        'envArg'=>['IOFRAME_CLI_CRON_MANAGEMENT_DYNAMIC_JOB_PROPERTIES'],
        'cliFlag'=>['--djp','--dynamic-job-properties'],
        'hasInput'=>true,
        'validation'=>['type'=>'json', 'required'=>false],
        'recalculated'=>true,
        'func'=>function($properties,$context,$params){
            //First initiation without passing any properties
            if($properties === null && !$context->initiated)
                return null;
            //Second initiation, if we initiated in the first one
            elseif(isset($context->variables['dynamicJobProperties']))
                return $context->variables['dynamicJobProperties'];
            //First initiation with properties, or second initiation when we didn't initiate in the first one
            else{
                /* _url - string, default key - use a specific URL for the file different from key
                 * archive - bool, Whether to archive stuff that was cleaned
                 * retries - int, how many times to retry operation on failure
                 * batchSize - int, how many rows to operate on with each query
                 * considerOld - int, how old (in seconds, since Last_Updated) the data needs to be for archiving
                 * tables - mixed, stuff used specifically by the jobs. Specified in each file
                */
                $defaults = [
                    /* Alternative syntax - would only allow one job per file:

                    'cli/cron-management/common/archive_or_clean.php'=>[
                        'active'=>1,
                        ...
                    ]

                    */
                    'commons_clean_expired_ip_events'=>[
                        '_url'=>'cli/cron-management/common/archive_or_clean.php',
                        'active'=>0,
                        'archive'=>1,
                        'retries'=>3,
                        'batchSize'=>2000,
                        'tables'=>[
                            [
                                'name'=>'IP_EVENTS',
                                'identifierColumns'=>['IP','Event_Type','Sequence_Start_Time'],
                                'expiresColumn'=>'Sequence_Expires'
                            ]
                        ]
                    ],
                    'commons_clean_expired_ips'=>[
                        '_url'=>'cli/cron-management/common/archive_or_clean.php',
                        'active'=>0,
                        'archive'=>1,
                        'retries'=>3,
                        'batchSize'=>2000,
                        'tables'=>[
                            [
                                'name'=>'IPV4_RANGE',
                                'identifierColumns'=>['Prefix','IP_From','IP_To'],
                                'expiresColumn'=>'Expires',
                                'delimiter'=>'/'
                            ],
                            [
                                'name'=>'IP_LIST',
                                'identifierColumns'=>['IP'],
                                'expiresColumn'=>'Expires',
                                'delimiter'=>'/'
                            ],
                        ]
                    ],
                    'commons_clean_expired_tokens'=>[
                        '_url'=>'cli/cron-management/common/archive_or_clean.php',
                        'active'=>0,
                        'archive'=>0,
                        'retries'=>3,
                        'batchSize'=>10000,
                        'considerOld'=>0 /* 3600*24*7 Example, if you want to keep expired tokens for a week*/,
                        'tables'=>[
                            [
                                'name'=>'IOFRAME_TOKENS',
                                'identifierColumns'=>['Token'],
                                'expiresColumn'=>'Expires'
                            ]
                        ]
                    ],
                    'commons_clean_expired_user_events'=>[
                        '_url'=>'cli/cron-management/common/archive_or_clean.php',
                        'active'=>0,
                        'archive'=>1,
                        'retries'=>3,
                        'batchSize'=>2000,
                        'tables'=>[
                            [
                                'name'=>'USER_EVENTS',
                                'identifierColumns'=>['ID','Event_Type','Sequence_Start_Time'],
                                'expiresColumn'=>'Sequence_Expires'
                            ]
                        ]
                    ],
                    'commons_archive_old_logins'=>[
                        '_url'=>'cli/cron-management/common/archive_or_clean.php',
                        'active'=>0,
                        'archive'=>1,
                        'retries'=>3,
                        'batchSize'=>10000,
                        'considerOld'=>3600*24*7*48,
                        'tables'=>[
                            [
                                'name'=>'LOGIN_HISTORY',
                                'identifierColumns'=>['ID','IP','Login_Time'],
                                'expiresColumn'=>'Login_Time'
                            ]
                        ]
                    ],
                    'commons_logging'=>[
                        '_url'=>'cli/cron-management/common/log_management.php',
                        'active'=>0,
                        'maxRuntime'=>60, /* Jobs can run in parallel locally, if your log files become massive, you want more jobs */
                        'ignoreMaxParallel'=>true, /* Loggers are local to each note, use local locks, and at most 2 should run at a time */
                        /* Defaults also set inside log/report managers, some are here only for clarity*/
                        '_logging'=>[
                            'logManagers'=>[
                                'default'=>[
                                    'manager'=>'cli/cron-management/common/log-managers/default_db_logging.php',
                                    'logsFolder'=>'localFiles/logs',
                                    'logFilesSafetyMargin'=>20,
                                    'logFileIntervals'=>10
                                ]
                            ]
                        ],
                    ],
                    'commons_reporting'=>[
                        '_url'=>'cli/cron-management/common/report_management.php',
                        'active'=>0,
                        'maxRuntime'=>60,
                        'maxParallel'=>1, /* The default report manager is NOT written in a parallel-safe way */
                        'maxWait'=>20, /* Reports shouldn't be massive, managers should push to delivery queues, but who knows */
                        '_reporting'=>[
                            'reportManagers'=>[
                                'email'=>[
                                    'manager'=>'cli/cron-management/common/report-managers/default_mail_reporting.php',
                                    'template'=>'default_log_report',
                                    'checkInterval'=>5,
                                    'calcTimeBoundaries'=>true,
                                    'assumeLastCheckedBefore'=>600,
                                    'throttleAfterReport'=>300,
                                ],
                                //TODO SMS Handlers are not implemented by default. Once at least one SMS integration is added, they should be implemented using a dynamic SMS handler.
                                'sms'=>[
                                    'manager'=>'cli/cron-management/common/report-managers/default_sms_reporting.php',
                                    'template'=>'default_log_report',
                                    'throttleAfterReport'=>600
                                ],
                            ]
                        ],
                    ],
                    'default_mailing_queue'=>[
                        '_url'=>'cli/cron-management/common/queue_management.php',
                        'active'=>0,
                        'retries'=>2,
                        'maxParallel'=>10,
                        '_queue'=>[
                            'listenTo'=>['default_mailing'],
                            /*Prefix value is default, here only for clarity*/
                            'prefix'=>'queue_',
                            'url'=>'cli/cron-management/common/email-queue-managers/send_mail_default_queue.php'
                        ],
                        /* Defaults also set inside mail queue manager, here only for clarity*/
                        'email'=>[
                            'provider'=>'smtp',
                            'batchSize'=>60,
                            'runtimeSafetyMargin'=>1,
                            'overwriteSettings'=>[],
                            'inProgressQueue'=>null,
                            'successQueue'=>null,
                            'failureQueue'=>null,
                            'logTable'=>null
                        ]
                    ]
                ];

                //If we have new properties now, ensure they are a valid array, then merge them
                if(\IOFrame\Util\PureUtilFunctions::is_json($properties))
                    $properties = json_decode($properties,true);
                if(is_array($properties))
                    $defaults = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($defaults,$properties,['deleteOnNull'=>true]);

                //Returning on first initiation only allowed if we explicitly set valid properties
                if(!$context->initiated)
                    return is_array($properties) ? $defaults : null;
                //Returning at least defaults on second initiation is required
                else
                    return $defaults;
            }
        }
    ],
    'loggingOptions'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            'Options for logging, apply to any action.',
            [
                'logOn'=>[
                    'type'=>'string',
                    'default'=>'"none"',
                    'desc'=>'"errors" to log only on error, "noErrors" to log only on no errors, "any" to log on any result, other value for none'
                ],
                'logLevel'=>[
                    'type'=>'string',
                    'default'=>'"error"',
                    'desc'=>'log at this level, see Monolog\Logger functions for possible values'
                ],
                'toLog'=>[
                    'type'=>'string',
                    'default'=>'"any"',
                    'desc'=>'"errors" to log only errors, "result" to log only result, "any" to log both result and errors, other value for none'
                ],
                'logId'=>[
                    'type'=>'string',
                    'default'=>'null',
                    'desc'=>'If set, will append this id to the log message'
                ],
            ]
        ),
        'envArg'=>['IOFRAME_CLI_CRON_MANAGEMENT_LOGGING_OPTIONS'],
        'cliFlag'=>['--lgopt','--logging-options'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'loggingOptions',[
                'logOn'=>'none',
                'logLevel'=>'error',
                'toLog'=>'any',
                'logId'=>null,
            ]);
        }
    ],
    'altDBSettings'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
            [
                'A path to a valid sqlSettings folder, from project root.',
                'This should be a local settings file, properly created elsewhere (e.g. settings CLI)',
                'May point to an alternative Server / DB, or same DB but with a different prefix.',
                'Can be potentially used to migrate to/from a different DB, archive logs, etc.',
            ],
            [
                'The new settings handler will be passed in $params as $altDBSettings.',
                'An SQLManager connected to that DB will be passed in $inputs as $AltDBManager.'
            ],
        ),
        'envArg'=>['IOFRAME_CLI_CRON_MANAGEMENT_ALT_DB_SETTINGS'],
        'cliFlag'=>['--dbs','--db-settings'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'required'=>false]
    ],
    '_silent'=>[
        'desc'=>'Silent mode',
        'envArg'=>['IOFRAME_CLI_CRON_MANAGEMENT_SILENT'],
        'reserved'=>true,
        'cliFlag'=>['-s','--silent']
    ],
    '_test'=>[
        'desc'=>'Test mode',
        'envArg'=>['IOFRAME_CLI_CRON_MANAGEMENT_TEST'],
        'reserved'=>true,
        'cliFlag'=>['-t','--test']
    ],
    '_verbose'=>[
        'desc'=>'Verbose output',
        'envArg'=>['IOFRAME_CLI_CRON_MANAGEMENT_VERBOSE'],
        'reserved'=>true,
        'cliFlag'=>['-v','--verbose']
    ],
];

$initiationParams = [
    'generateFileRelated'=>true,
    'dieOnFailure'=>false,
    'silent'=>true,
    'action'=>'_action',
    'actions'=>[
        'jobsStatus'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                [
                    'Gets the status of all currently running jobs, or specific --jobs.',
                ],
                [],
                [
                    'php cron-management.php -a status'
                ]
            ),
            'required'=>[],
            'func'=>function($inputs,&$errors,$context,$params){

                $defaults = $params['defaultParams'];
                $v = $context->variables;
                $jobs = $v['jobs'];
                $result = [];

                if(empty($defaults['useCache']))
                    return $result;

                $redisPrefix = $defaults['redisSettings']->getSetting('redis_prefix')??'';

                $targets = isset($jobs)? ( \IOFrame\Util\PureUtilFunctions::is_json($jobs)? json_decode($jobs,true) : [$jobs] ) : null;

                $allJobs = $defaults['RedisManager']->keys('cron_job_*');

                if(is_array($allJobs)){
                    foreach ($allJobs as $i => $job){
                        $jobInTarget = is_array($targets) ? array_reduce($targets,function ($job,$target){ return str_contains($job,$target); },$job) : true;

                        if(!$jobInTarget)
                            unset($allJobs[$i]);
                        else
                            $allJobs[$i] = substr($job,strlen($redisPrefix));
                    }
                    array_splice($allJobs,0,0);
                }

                $allJobsInfo = $defaults['RedisManager']->mGet($allJobs);
                $checkTime = time();

                foreach ($allJobs as $i => $key){
                    $realKey = substr($key,strlen('cron_job_'));
                    $temp = explode('_',$realKey);
                    $jobIndex = array_pop($temp);
                    $realKey = implode('_',$temp);

                    if(empty($result[$realKey]))
                        $result[$realKey] = [];

                    $value = $allJobsInfo[$i]??null;

                    if(!empty($value)){
                        $value = explode('@',$value);
                        $result[$realKey][] = [
                            'node' => $value[0],
                            'index' => $jobIndex,
                            'start' => date('H:i:s, d M Y', $value[1]),
                            'elapsed' => date('H:i:s', $checkTime - $value[1])
                        ];
                    }
                }

                return $result;
            }
        ],
        'dynamicCronJobs'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                [
                    'The method used to run dynamic cron jobs, with custom parameters',
                    'Uses --dynamic-job-properties (see file for defaults / examples) - you can also annul any default',
                    'Can also execute only specific jobs with --jobs, and disregard dynamic property run limits with --jri',
                    'Under the hood, Redis is used to monitor jobs and ensure maxParallel limits - ',
                    ' as such, this script can safely run on multiple nodes without fear, but Redis is required unless --jri is true.',
                    'Each job (or chain of jobs) that isn\'t local can be considered a micro-service - ',
                    ' as such, you may choose to dedicate specific nodes to individual / groups of jobs.'
                ],
                [
                    'In order to run dynamic cron jobs, you still need to call this script in the crontab.'.EOL.
                    ' This script may run up to every minute (* * * * *) with flags -a dynamic -s [--fp file/path/to/dynamicProperties.json]',
                    'When you wish to shut down a node, it\'s a good idea to to first disable the jobs in the cron tab and let existing ones finish'.EOL.
                    ' If you don\'t, some jobs (like local logging/reporting) may terminate incorrectly and unsafely, not unlocking locks and potentially losing data.' ,
                    'You should not dynamically run operations that would require putting the system in maintenance mode.'.EOL.
                    ' Instead, use the maintenance CLI, and run the relevant scripts directly.',
                    'Notice that when running multiple jobs, maxRuntime is PER JOB, so each one may take 60 sec (by default)',
                    'Notice in the examples, longer sleepy jobs still cap at 1 minute, since we did not set a higher maxRunTime',
                    'In the examples, sleepy jobs can be run in parallel, and they have no side effects beside Redis mutexes',
                ],
                [
                    'php cron-management.php -t -v -a dynamic --fp cli/config/cron-management/start-all-defaults-once.json',
                    'php cron-management.php -t -v -a dynamic --fp cli/config/cron-management/archive-old-login-history.json',
                    'php cron-management.php -t -v -a dynamic --fp cli/config/cron-management/start-queue-manager-mailing-default.json',
                    'php cron-management.php -t -v -a dynamic --fp cli/config/cron-management/start-default-logging.json',
                    'php cron-management.php -t -v -a dynamic --fp cli/config/cron-management/start-default-reporting.json',
                    '',
                    'php cron-management.php -v -a dynamic --fp cli/config/examples/cron-management/start-sleepy-job-short.json',
                    'php cron-management.php -v -a dynamic --fp cli/config/examples/cron-management/start-sleepy-job-medium.json',
                    'php cron-management.php -v -a dynamic --fp cli/config/examples/cron-management/start-sleepy-job-medium-with-errors.json',
                    'php cron-management.php -v -a dynamic --fp cli/config/examples/cron-management/start-sleepy-job-medium-with-errors.json',
                    'php cron-management.php -v -a dynamic --fp cli/config/examples/cron-management/start-sleepy-job-long.json',
                    'php cron-management.php -v -a dynamic --fp cli/config/examples/cron-management/start-sleepy-job-long-over-reg-limit.json',
                    'php cron-management.php -v -a dynamic --fp cli/config/examples/cron-management/start-sleepy-job-long-with-errors-logging.json',
                    'php cron-management.php -v -a dynamic --fp cli/config/examples/cron-management/start-sleepy-job-2-long.json',
                    'php cron-management.php -v -a dynamic --fp cli/config/examples/cron-management/start-sleepy-jobs-both-long.json'
                ]
            ),
            'logChannel'=> \IOFrame\Definitions::LOG_CLI_JOBS_CHANNEL,
            'logHandlers'=>[$defaultSettingsParams['logHandler']],
            'shouldLog'=> Closure::fromCallable([\IOFrame\Util\CLI\CommonManagerHelperFunctions::class, 'defaultShouldLogMatcher']),
            'logLevelCalculator'=>Closure::fromCallable([\IOFrame\Util\CLI\CommonManagerHelperFunctions::class, 'defaultLogLevelCalculator']),
            'logContextGenerator'=>Closure::fromCallable([\IOFrame\Util\CLI\CommonManagerHelperFunctions::class, 'defaultLogContextGenerator']),
            'logMessageGenerator'=>function($result,$context){
                return \IOFrame\Util\CLI\CommonManagerHelperFunctions::defaultLogMessageGenerator($result, $context, [
                    'errorMsg'=>'Failed to run cron job',
                    'noErrorMsg'=>'Successfully finished cron job',
                ]);
            },
            'required'=>[],
            'func'=>function($inputs,&$errors,$context,$params){

                $defaults = $params['defaultParams'];
                $ConcurrencyHandler = new \IOFrame\Managers\Extenders\RedisConcurrency($defaults['RedisManager']);
                $v = $context->variables;
                unset($params['input']);

                $projectRoot = $defaults['absPathToRoot'];
                $nodeID = $defaults['localSettings']->getSetting('nodeID')??'unknown';
                $jobsProperties = $v['dynamicJobProperties'];
                $jobs = $v['jobs'];
                $results = [];

                $timingMeasurer = new \IOFrame\Util\TimingMeasurer();

                if(empty($defaults['useCache']) && !$v['justRunIt']){
                    $errors['no-cache'] = true;
                    return $results;
                }

                $jobsToRun = [];

                //Either load scripts specified in --jobs
                if(!empty($jobs)){
                    if(!\IOFrame\Util\CLI\FileInclusionFunctions::validateAndEnsureArray($jobs,$errors,'invalid-jobs-format')){
                        return $results;
                    }
                    \IOFrame\Util\CLI\FileInclusionFunctions::populateWithPHPScripts($jobsToRun,$jobs,$v,'jobsIncludeOptions',$projectRoot);
                }
                //Or load scripts based on definitions in --dynamic-job-properties
                else{
                    $jobsToRun = array_keys($jobsProperties);
                }

                if(empty($jobsToRun)){
                    $errors['no-valid-jobs'] = true;
                    return $results;
                }

                /* Default parameters for each job
                 * active - bool, whether to run job
                 * requiresSuccess - object, of the form $jobId => ( mixed | function($infoObject){ ... return <bool>; } ),
                 *                   previous jobs that need to have succeeded to run this job (order matters!)
                 * maxParallel - int, how many processes (between ALL nodes) can run this job in parallel
                 * ignoreMaxParallel - bool, if set to true will ignore global locks
                 * maxRuntime - int, maximum runtime (in seconds) PER job, INCLUDING getting locks.
                 *              The maxWait lock setting ensures a safety margin for jobs going slightly over maxRuntime.
                 *              *IMPORTANT* Jobs may finish slightly after maxRuntime (margin is ~0.1s + overflow time from inside job)
                 *              *IMPORTANT* 'max_execution_time' has no effect in CLI, so we ensure it here instead
                 * extraLockTTL - int, seconds to extend maximum lock beyond maxRuntime.
                 *              *IMPORTANT* As jobs may finish slightly after maxRuntime, they will report
                 *              not unlocking a non-existent lock if they expired. extraLockTTL is the safety margin.
                 * lockRetries - int, see RedisConcurrency->makeRedisMutex()
                 * maxWait - int, see RedisConcurrency->makeRedisMutex()
                 * randomDelay - int, see RedisConcurrency->makeRedisMutex()
                 *
                 * ---Inherited from $params -
                 * test, verbose, silent, defaultParams, [altDbSettings, AltDBManager]
                */
                $parameterDefaults = [
                    'active'=>true,
                    'requiresSuccess'=>[],
                    'maxParallel'=>1,
                    'maxRuntime'=>60,
                    'extraLockTTL'=>3,
                    'lockRetries'=>10,
                    'maxWait'=>4,
                    'randomDelay'=>100000
                ];

                /* Those are global to all jobs. */
                $parameters = array_merge(
                    $parameterDefaults,
                    $params
                );

                foreach ($jobsToRun as $url){

                    $achievedLock = null;
                    $heldKey = null;
                    $jobResult = ['time-elapsed'=>null,'result'=>false];

                    $id = \IOFrame\Util\CLI\FileInclusionFunctions::url2Id($url,$projectRoot);
                    $jobParameters = $jobsProperties[$id] ?? $jobsProperties[$url] ?? [];
                    if(!empty($jobParameters['_url']))
                        $url = is_file($projectRoot.'/'.$jobParameters['_url']) ? $projectRoot.'/'.$jobParameters['_url'] : ( is_file($parameters['_url']) ? $parameters['_url'] : null);

                    //We can't execute a job without a URL
                    if($url === null)
                        continue;

                    //Merge dynamic job parameters
                    $parameters = array_merge(
                        $parameters,
                        $jobParameters
                    );

                    //If we require previous jobs to be successful, ensure they were
                    if(!empty($parameters['requiresSuccess'])){
                        foreach ($parameters['requiresSuccess'] as $requiredId => $valueOrFunc){
                            $passed = is_callable($valueOrFunc) ?
                                $valueOrFunc(['results'=>$results[$requiredId]??null,'errors'=>$errors,'id'=>$requiredId]) :
                                $results[$requiredId] === $valueOrFunc;
                            if(!$passed)
                                continue;
                        }
                    }

                    $timingMeasurer->start();

                    if(!$parameters['active'] && !$v['justRunIt'])
                        continue;

                    //Check if active, try to get lock
                    if(!$v['justRunIt'] && empty($parameters['ignoreMaxParallel'])){
                        if(!$parameters['maxParallel'])
                            continue;

                        /* Just in case you're curious of collision probability, type this JS code into any browser console (assuming 10 similar jobs starting per second per node):
                         const calculate = (n, k) => {
                           const exponent = (-k * (k - 1)) / (2 * n)
                           return 1 - Math.E ** exponent
                         };
                         calculate(Math.pow(61,12),100); //Those specific numbers will just be rounded to 0 though
                        */
                        $keyWithSignature = $nodeID.'@'.time().'@'.\IOFrame\Util\PureUtilFunctions::GeraHash(12);
                        $lockNames = [];
                        for ($i = 1; $i<=$parameters['maxParallel']; $i++){
                            $lockNames[] = 'cron_job_' . $id . '_' . $i;
                        }
                        $achievedLocks = $ConcurrencyHandler->makeRedisMutex($lockNames,$keyWithSignature,[
                            'sec'=> round($parameters['maxRuntime']+$parameters['extraLockTTL']),
                            'maxWait'=> $parameters['maxWait'],
                            'tries'=> $parameters['lockRetries'],
                            'test'=>$parameters['test'],
                            'verbose'=>$parameters['verbose']
                        ]);
                        foreach ($achievedLocks as $potentialLock=>$key){
                            if(gettype($key) === 'string'){
                                $achievedLock = $potentialLock;
                                $heldKey = $key;
                            }
                        }
                        if(!$achievedLock){
                            \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['no-free-locks',$id],$achievedLocks);
                            continue;
                        }
                    }

                    try{
                        require $url;
                        if(!isset($_run) || !is_callable($_run)){
                            \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['jobs-failed-no-run',$id],true);
                            $jobResult = ['time-elapsed'=>$timingMeasurer->timeElapsed(),'result'=>false];
                        }
                        else{
                            /* Those are local to each job*/
                            $opt = [
                                'id'=>$id,
                                'runResult'=>null,
                                'runBeforeResult'=>null,
                                'runAfterResult'=>null,
                                'timingMeasurer'=>$timingMeasurer,
                                'concurrencyHandler'=>$ConcurrencyHandler
                            ];

                            $opt['runBeforeResult'] = isset($_runBefore) && is_callable($_runBefore)? $_runBefore($parameters,$errors,$opt) : null;

                            while(
                                (floor($timingMeasurer->timeElapsed()/1000000) < $parameters['maxRuntime']) &&
                                empty($opt['runResult']['exit'])
                            ){
                                $opt['runResult'] = $_run($parameters,$errors,$opt);
                            }

                            $opt['runAfterResult'] = isset($_runAfter) && is_callable($_runAfter)? $_runAfter($parameters,$errors,$opt) : null;

                            $jobResult = ['time-elapsed'=>$timingMeasurer->timeElapsed(),'result'=>$opt['runResult']['result']??false];
                        }
                        if(isset($_runBefore))
                            unset($_runBefore);
                        if(isset($_run))
                            unset($_run);
                        if(isset($_runAfter))
                            unset($_runAfter);
                    }
                    catch (\Exception $e){
                        \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['jobs-failed-exception',$id],$e->getMessage());
                        $jobResult = ['time-elapsed'=>$timingMeasurer->timeElapsed(),'result'=>false];
                    }

                    $timingMeasurer->stop();

                    //Free the lock in any case
                    if(!$v['justRunIt'] && empty($parameters['ignoreMaxParallel'])){
                        $releaseLock = $ConcurrencyHandler->releaseRedisMutex($achievedLock,$heldKey,[
                            'test'=>$parameters['test'],
                            'verbose'=>$parameters['verbose']
                        ]);
                        if(!$parameters['test'] && ($releaseLock !== 0)){
                            \IOFrame\Util\PureUtilFunctions::createPathInObject($errors,['jobs-could-not-release-lock',$id],$releaseLock);
                        }
                    }

                    //Note - you can modify job result inside each script - otherwise, defaults to true normally, false on exception
                    $results[$id] = $jobResult;

                }

                return $results;
            }
        ]
    ],
    'helpDesc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
        [
            'This is the default IOFrames cron job management CLI.',
            'It allows both managing and dynamic executing individual or multiple (in order, blocking) cron jobs.'
        ]
    )
];

require 'commons/initiate_manager.php';

$initiationError = 'flags-initiation';

require 'commons/checked_failed_initiation.php';

$v = $CLIManager->variables;

$initiation = $CLIManager->populateFromFiles(['test'=>$v['_test'],'verbose'=>$v['_verbose']]);

$initiationError = 'files-initiation';

require 'commons/checked_failed_initiation.php';

$v = $CLIManager->variables;

$params = ['inputs'=>true,'defaultParams'=>$defaultSettingsParams,'test'=>$v['_test'],'verbose'=>$v['_verbose'],'silent'=>$v['_silent']];

require 'commons/add_alt_db_to_params.php';

$check = $CLIManager->matchAction(
    null,
    $params
);

die(json_encode($check,JSON_PRETTY_PRINT));
