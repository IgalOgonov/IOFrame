<?php
/* This CLI controls local/global maintenance mode
 *
 * */
require 'commons/ensure_cli.php';
if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../main/core_init.php';

$initiationDefinitions = [
    '_action'=>[
        'desc'=>'Action value from cli',
        'cliFlag'=>['-a','--action'],
        'envArg'=>['IOFRAME_CLI_MAINTENANCE_ACTION'],
        'func'=>function($action){
            switch ($action){
                case 'sm':
                case 'start':
                    $action = 'startMaintenance';
                    break;
                case 'em':
                case 'end':
                    $action = 'endMaintenance';
                    break;
                case 'info':
                case 'status':
                    $action = 'maintenanceStatus';
                    break;
                case 'up':
                case 'update':
                    $action = 'updateSystem';
                    break;
                case 'logs':
                case 'getRemaining':
                case 'getRemainingLogs':
                    $action = 'getRemainingLocalLogs';
                    break;
                default:
            }
            return $action;
        }
    ],
    'global'=>[
        'desc'=>'Global or local maintenance',
        'cliFlag'=>['-g','--global'],
        'envArg'=>['IOFRAME_CLI_MAINTENANCE_GLOBAL'],
        'hasInput'=>false
    ],
    'includeStart'=>[
        'desc'=>'Includes start time in the maintenance notice',
        'cliFlag'=>['--is','--start','--include-start'],
        'envArg'=>['IOFRAME_CLI_MAINTENANCE_SET_START'],
        'hasInput'=>false
    ],
    'eta'=>[
        'desc'=>'ETA from start, in minutes',
        'cliFlag'=>['-e','--eta'],
        'envArg'=>['IOFRAME_CLI_MAINTENANCE_NAME'],
        'hasInput'=>true,
        'validation'=>['type'=>'int', 'required'=>false]
    ],
    'populateTest'=>[
        'desc'=>'Whether to populate test data when updating',
        'cliFlag'=>['--populate-test'],
        'envArg'=>['IOFRAME_CLI_MAINTENANCE_POPULATE_TEST'],
        'hasInput'=>false,
    ],
    'localUpdate'=>[
        'desc'=>'Only update local files, not the db',
        'cliFlag'=>['--local','--local-update'],
        'envArg'=>['IOFRAME_CLI_MAINTENANCE_LOCAL_UPDATE'],
        'hasInput'=>false,
    ],
    'maintenanceDuringUpdate'=>[
        'desc'=>'Set the system / node into maintenance mode during the update',
        'cliFlag'=>['--maintenance','--maintenance-during-update'],
        'envArg'=>['IOFRAME_CLI_MAINTENANCE_MAINTENANCE_DURING_UPDATES'],
        'hasInput'=>false,
    ],
    'fromSpecificVersion'=>[
        'desc'=>'Update from a specific version',
        'cliFlag'=>['--from','--from-specific-version'],
        'envArg'=>['IOFRAME_CLI_MAINTENANCE_FROM_SPECIFIC_VERSION'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'required'=>false]
    ],
    '_silent'=>[
        'desc'=>'Silent mode',
        'reserved'=>true,
        'cliFlag'=>['-s','--silent'],
        'envArg'=>['IOFRAME_CLI_MAINTENANCE_SILENT'],
    ],
    '_test'=>[
        'desc'=>'Test mode',
        'reserved'=>true,
        'cliFlag'=>['-t','--test'],
        'envArg'=>['IOFRAME_CLI_MAINTENANCE_TEST'],
    ],
    '_verbose'=>[
        'desc'=>'Verbose output',
        'reserved'=>true,
        'cliFlag'=>['-v','--verbose'],
        'envArg'=>['IOFRAME_CLI_MAINTENANCE_VERBOSE'],
    ],
];

$initiationParams = [
    'generateFileRelated'=>true,
    'dieOnFailure'=>false,
    'silent'=>true,
    'action'=>'_action',
    'actions'=>[
        'maintenanceStatus'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Gets maintenance status by printing the relevant settings',
                [],
                [
                    'php maintenance.php -a status'
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                $res = [
                    'global'=>[
                        'active'=>null,
                        'start'=>null,
                        'eta'=>null
                    ],
                    'local'=>[
                        'active'=>null,
                        'start'=>null,
                        'eta'=>null
                    ]
                ];

                $localSettings = $inputs['settings']->getSettings();
                $res['local']['active'] = $localSettings['_maintenance']??false;
                $res['local']['start'] = $localSettings['_maintenance_start']??null;
                $res['local']['eta'] = $localSettings['_maintenance_eta']??null;
                if($inputs['siteSettings'] && !empty($inputs['defaultParams']['useCache'])){
                    $siteSettings = $inputs['siteSettings']->getSettings();
                    $res['global']['active'] = $siteSettings['_maintenance']??false;
                    $res['global']['start'] = $siteSettings['_maintenance_start']??null;
                    $res['global']['eta'] = $siteSettings['_maintenance_eta']??null;
                }

                return $res;
            }
        ],
        'startMaintenance'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Starts local/global maintenance',
                [],
                [
                    'php maintenance.php -t -v -a start',
                    'php maintenance.php -t -v -a start -g --is --eta 90'
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                $v = $context->variables;

                $res = [
                    '_maintenance'=>null,
                    '_maintenance_start'=>null,
                    '_maintenance_eta'=>null,
                ];

                if($v['global'] && !( $inputs['siteSettings'] && !empty($inputs['defaultParams']['useCache']) ) ){
                    $errors['connection-unavailable'] = true;
                    if($params['verbose'])
                        echo 'Cannot connect to cache.'.EOL;
                    return $res;
                }

                $target = $v['global']?$inputs['siteSettings']:$inputs['settings'];
                $toSet = [];
                $toSet['_maintenance'] = true;

                if($v['includeStart'])
                    $toSet['_maintenance_start'] = time();
                if($v['eta'])
                    $toSet['_maintenance_eta'] = $v['eta'];

                foreach ($toSet as $setting=>$value){
                    $res[$setting] = $target->setSetting($setting,$value,array_merge($params,['createNew'=>true]));
                }

                return $res;
            }
        ],
        'endMaintenance'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Ends maintenance',
                [],
                [
                    'php maintenance.php -t -v -a end',
                    'php maintenance.php -t -v -a end -g'
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                $v = $context->variables;

                $res = [
                    '_maintenance'=>null,
                    '_maintenance_start'=>null,
                    '_maintenance_eta'=>null,
                ];

                if($v['global'] && !( $inputs['siteSettings'] && !empty($inputs['defaultParams']['useCache']) ) ){
                    $errors['connection-unavailable'] = true;
                    if($params['verbose'])
                        echo 'Cannot connect to cache.'.EOL;
                    return $res;
                }

                $target = $v['global']?$inputs['siteSettings']:$inputs['settings'];
                $toSet = [
                    '_maintenance'=>null,
                    '_maintenance_start'=>null,
                    '_maintenance_eta'=>null,
                ];

                foreach ($toSet as $setting=>$value){
                    $res[$setting] = $target->setSetting($setting,$value,array_merge($params,['createNew'=>true]));
                }

                return $res;
            }
        ],
        'updateSystem'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                [
                    'Update the system to the next version.',
                    'If used in local mode, must specify current version with --from',
                    'Set the system / node in maintenance mode during the update with --maintenance',
                    'Update only local files with --local',
                    'Populate test update data with --populate-test'
                ],
                [],
                [
                    'php maintenance.php -t -v -a update --from "1.2.2.2" --local',
                    'php maintenance.php -t -v -a update --from "1.2.2.2" --global --maintenance'
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                //Script already equipped to run inside this context
                require __DIR__.'/../api/_update.php';
            }
        ],
        //TODO
        'getRemainingLocalLogs'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                [
                    'Gets remaining local logs from this specific node',
                    'This should only be used in case the logging cron jobs are failing for some reason.',
                ],
                [],
                [
                    'php maintenance.php -t -v -a logs'
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                $errors['todo'] = true;
                return null;
            }
        ]
    ],
    'helpDesc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
        [
            'This is the default IOFrames maintenance CLI.',
            'Ite can set the system/node to maintenance mode, and can handle global/node updates.'
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

$params = ['inputs'=>['settings'=>$settings,'siteSettings'=>$siteSettings,'defaultParams'=>$defaultSettingsParams],'test'=>$v['_test'],'verbose'=>$v['_verbose']];

$check = $CLIManager->matchAction(
    null,
    $params
);

die(json_encode($check,JSON_PRETTY_PRINT));
