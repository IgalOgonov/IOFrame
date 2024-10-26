<?php
/* This interface is meant to be ran on 'db' operation mode nodes (non-local nodes).
 * It is meant to be run on nodes individually in order to install/update/remove plugins locally after they have been
 * installed globally.
 *
 * */
require 'commons/ensure_cli.php';
if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../main/core_init.php';

if(!isset($PluginHandler))
    $PluginHandler = new \IOFrame\Handlers\PluginHandler($settings,$defaultSettingsParams);

$initiationDefinitions = [
    '_action'=>[
        'desc'=>'Action value from cli',
        'cliFlag'=>['-a','--action'],
        'envArg'=>['IOFRAME_CLI_PLUGINS_ACTION'],
        'func'=>function($action){
            switch ($action){
                case 'g':
                case 'get':
                    $action = 'getAvailable';
                    break;
                case 'i':
                case 'inf':
                case 'info':
                    $action = 'getInfo';
                    break;
                case 'in':
                case 'inst':
                    $action = 'install';
                    break;
                case 'un':
                case 'uninst':
                    $action = 'uninstall';
                    break;
                case 'up':
                    $action = 'update';
                    break;
                case 'epi':
                case 'pubimg':
                    $action = 'ensurePublicImages';
                    break;
                default:
            }
            return $action;
        }
    ],
    'pluginName'=>[
        'desc'=>'Usually the target for one of the actions',
        'cliFlag'=>['-n','--plugin-name'],
        'envArg'=>['IOFRAME_CLI_PLUGINS_NAME'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'required'=>false]
    ],
    'options'=>[
        'desc'=>'Plugin options',
        'cliFlag'=>['-o','--options'],
        'envArg'=>['IOFRAME_CLI_PLUGINS_OPTIONS'],
        'hasInput'=>true,
        'validation'=>['type'=>'json', 'required'=>false]
    ],
    'global'=>[
        'desc'=>'Whether to do the operation locally, or globally',
        'cliFlag'=>['-g','--global'],
        'envArg'=>['IOFRAME_CLI_PLUGINS_GLOBAL'],
        'hasInput'=>false
    ],
    'iterationLimit'=>[
        'desc'=>'Limit number of times (new versions) you wish to update the plugin',
        'cliFlag'=>['--il','--iteration-limit'],
        'envArg'=>['IOFRAME_CLI_PLUGINS_ITERATION_LIMIT'],
        'hasInput'=>true,
        'validation'=>['type'=>'int',"min"=>1,'exceptions'=>["-1"],'default'=>-1, 'required'=>false]
    ],
    '_override'=>[
        'desc'=>'Override Whether to try to install/uninstall despite plugin being considered illegal',
        'envArg'=>['IOFRAME_CLI_PLUGINS_OVERRIDE'],
        'reserved'=>true,
        'cliFlag'=>['--ov','--override']
    ],
    '_silent'=>[
        'desc'=>'Silent mode',
        'envArg'=>['IOFRAME_CLI_PLUGINS_SILENT'],
        'reserved'=>true,
        'cliFlag'=>['-s','--silent']
    ],
    '_test'=>[
        'desc'=>'Test mode',
        'envArg'=>['IOFRAME_CLI_PLUGINS_TEST'],
        'reserved'=>true,
        'cliFlag'=>['-t','--test']
    ],
    '_verbose'=>[
        'desc'=>'Verbose output',
        'envArg'=>['IOFRAME_CLI_PLUGINS_VERBOSE'],
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
        'getAvailable'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Gets all available plugins, and their status. May specify plugin -n',
                [],
                [
                    'php plugins.php -v -t -a get',
                    'php plugins.php -v -t -a get -n testPlugin',
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                return $inputs['pluginHandler']->getAvailable(['name'=>$context->variables['pluginName']]);
            }
        ],
        'getInfo'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Gets full plugin -n info.',
                [],
                [
                    'php plugins.php -v -t -a info',
                    'php plugins.php -v -t -a info -n testPlugin',
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                return $inputs['pluginHandler']->getInfo(['name'=>$context->variables['pluginName'],'test'=>$params['test']]);
            }
        ],
        'install'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Installs plugin -n. May add options (-o)',
                [
                    'Examples are in Powershell syntax'
                ],
                [
                    'php plugins.php -v -t -a in -n testPlugin -o \'{\"testOption\":\"test\",\"insertRandomValues\":0}\'',
                    'php plugins.php -v -t -a in -n testPlugin -g -o \'{\"testOption\":\"test\",\"insertRandomValues\":0}\'',
                    'php plugins.php -v -t -a in -n testPlugin2 -o \'{\"testOption\":\"test\"}\'',
                ]
            ),
            'required'=>['pluginName'],
            'func'=>function($inputs,&$errors,$context,$params){
                $v = $context->variables;
                return $inputs['pluginHandler']->install(
                    $v['pluginName'],
                    $v['options'],
                    ['local'=>!$v['global'], 'override'=>$v['_override'], 'test'=>$params['test'], 'verbose'=>$params['verbose']]
                );
            }
        ],
        'uninstall'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Uninstalls plugin -n',
                [],
                [
                    'php plugins.php -v -t -a un -n testPlugin',
                    'php plugins.php -v -t -g -a un -n testPlugin',
                ]
            ),
            'required'=>['pluginName'],
            'func'=>function($inputs,&$errors,$context,$params){
                $v = $context->variables;
                return $inputs['pluginHandler']->uninstall(
                    $v['pluginName'],
                    $v['options'],
                    ['local'=>!$v['global'], 'override'=>$v['_override'], 'test'=>$params['test'], 'verbose'=>$params['verbose']]
                );
            }
        ],
        'update'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Updates plugin -n',
                [],
                [
                    'php plugins.php -v -t -a up -n testPlugin --il 1',
                    'php plugins.php -v -t -g -a up -n testPlugin --il -1',
                ]
            ),
            'required'=>['pluginName'],
            'func'=>function($inputs,&$errors,$context,$params){
                $v = $context->variables;
                return $inputs['pluginHandler']->update(
                    $v['pluginName'],
                    ['local'=>!$v['global'], 'iterationLimit'=>$v['iterationLimit'], 'test'=>$params['test'], 'verbose'=>$params['verbose']]
                );
            }
        ],
        'ensurePublicImages'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Ensures all plugin images are exposed in the public folder',
                [],
                [
                    'php plugins.php -v -t -a epi',
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                $names = array_keys($inputs['pluginHandler']->getAvailable());
                return $inputs['pluginHandler']->ensurePublicImages(
                    $names,
                    ['test'=>$params['test'], 'verbose'=>$params['verbose']]
                );
            }
        ]
    ],
    'helpDesc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
        array_merge(
            [
                'This is the default IOFrames plugins CLI.',
                'It is meant to be ran on "db" operation mode nodes (non-local nodes).',
                'This interface is meant to be run on nodes individually in order to',
                'install/update/remove plugins locally after they\'ve been installed globally.',
            ]
        )
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

$params = ['inputs'=>['pluginHandler'=>$PluginHandler],'test'=>$v['_test'],'verbose'=>$v['_verbose']];

$check = $CLIManager->matchAction(
    null,
    $params
);

die(json_encode($check,JSON_PRETTY_PRINT));
