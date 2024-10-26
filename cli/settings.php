<?php
/* This CLI is meant to be able to update local/global settings.
 *
 * */
require 'commons/ensure_cli.php';
if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../main/core_init.php';

$initiationDefinitions = [
    '_action'=>[
        'desc'=>'Action value from cli',
        'cliFlag'=>['-a','--action'],
        'envArg'=>['IOFRAME_CLI_SETTINGS_ACTION'],
        'func'=>function($action){
            switch ($action){
                case 'm':
                case 'gm':
                case 'getMeta':
                    $action = 'getMetaSettings';
                    break;
                case 'pm':
                case 'printMeta':
                    $action = 'printMetaSettings';
                    break;
                case 'sm':
                case 'setMeta':
                    $action = 'setMetaSettings';
                    break;
                case 'g':
                case 'get':
                    $action = 'getSetting';
                    break;
                case 'f':
                case 'gs':
                case 'gf':
                    $action = 'getSettings';
                    break;
                case 'ps':
                case 'print':
                    $action = 'printSettings';
                    break;
                case 's':
                case 'set':
                    $action = 'setSetting';
                    break;
                case 'u':
                case 'un':
                case 'unset':
                    $action = 'unsetSetting';
                    break;
                case 'ss':
                    $action = 'setSettings';
                    break;
                default:
            }
            return $action;
        }
    ],
    'settingsFile'=>[
        'desc'=>'Name of the settings file',
        'cliFlag'=>['-f','--file'],
        'envArg'=>['IOFRAME_CLI_SETTINGS_FILE'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'required'=>false]
    ],
    'settingName'=>[
        'desc'=>'Name of a specific setting',
        'cliFlag'=>['--se','--setting'],
        'envArg'=>['IOFRAME_CLI_SETTINGS_NAME'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'required'=>false]
    ],
    'settingValue'=>[
        'desc'=>'New setting value',
        'cliFlag'=>['--sv','--value'],
        'envArg'=>['IOFRAME_CLI_SETTINGS_VALUE'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'required'=>false]
    ],
    'settingsInputs'=>[
        'desc'=>'For setSettings, the form is a key=>value json object, deletes setting if value is null.'.EOL.
            'For setMetaSettings, object of the form {"local":<bool, default true>,"base64":<bool, default false>,"title":<string, default -f>}',
        'cliFlag'=>['--si','--settings-inputs'],
        'envArg'=>['IOFRAME_CLI_SETTINGS_INPUTS'],
        'hasInput'=>true,
        'validation'=>['type'=>'json', 'required'=>false]
    ],
    'createNew'=>[
        'desc'=>'Allows creating new settings',
        'cliFlag'=>['-c','--create'],
        'envArg'=>['IOFRAME_CLI_SETTINGS_CREATE_NEW'],
        'hasInput'=>false
    ],
    'disregardMeta'=>[
        'desc'=>'Allows operating on settings files that dont exist in settingsMeta',
        'cliFlag'=>['-d','--dr','--drm'],
        'envArg'=>['IOFRAME_CLI_SETTINGS_DISREGARD_META'],
        'hasInput'=>false
    ],
    'opMode'=>[
        'desc'=>'Overrides meta settings and $defaultSettingsParams, indicating opMode.'.EOL.
            'Defaults Meta.local > Var > inMeta? (defaultParams ?? db) : local',
        'hasInput'=>true,
        'cliFlag'=>['-o','--op'],
        'envArg'=>['IOFRAME_CLI_SETTINGS_OP_MODE'],
        'validation'=>[
            'type'=>'string',
            'valid'=> [\IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL,\IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_DB,\IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_MIXED],
            'required'=>false
        ]
    ],
    'base64'=>[
        'desc'=>'Overrides meta settings, indicating a settings file is stored in base64',
        'hasInput'=>false,
        'envArg'=>['IOFRAME_CLI_SETTINGS_BASE_SIXTY_FOUR'],
        'cliFlag'=>['-b','--bsf']
    ],
    '_silent'=>[
        'desc'=>'Silent mode',
        'reserved'=>true,
        'cliFlag'=>['-s','--silent'],
        'envArg'=>['IOFRAME_CLI_SETTINGS_SILENT'],
    ],
    '_test'=>[
        'desc'=>'Test mode',
        'reserved'=>true,
        'cliFlag'=>['-t','--test'],
        'envArg'=>['IOFRAME_CLI_SETTINGS_TEST'],
    ],
    '_verbose'=>[
        'desc'=>'Verbose output',
        'reserved'=>true,
        'cliFlag'=>['-v','--verbose'],
        'envArg'=>['IOFRAME_CLI_SETTINGS_VERBOSE'],
    ],
];

$initiationParams = [
    'generateFileRelated'=>true,
    'dieOnFailure'=>false,
    'silent'=>true,
    'action'=>'_action',
    'actions'=>[
        'getMetaSettings'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Gets meta settings. -f to get specific setting file meta settings',
                [],
                [
                    'php settings.php -v -t -a getMeta',
                    'php settings.php -v -t -a getMeta -f siteSettings',
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                $target = $context->variables['settingsFile']??null;
                return $target? $inputs['metaSettings']->getSetting($target) : $inputs['metaSettings']->getSettings();
            }
        ],
        'printMetaSettings'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Prints meta settings',
                [],
                [
                    'php settings.php -v -t -a printMeta',
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                return $inputs['metaSettings']->printAll();
            }
        ],
        'setMetaSettings'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Sets meta settings of file -f with --si',
                [
                    'Unlike setSettings, the structure of --si is different, and if left unset, deletes setting instead',
                    'If setting existed, will merge changes instead',
                    'Examples are in Powershell syntax'
                ],
                [
                    'php settings.php -v -t -a sm -f siteSettings',
                    'php settings.php -v -t -a setMetaSettings -f exampleSettings --si \'{\"title\":\"Example\",\"base64\":1,\"local\":0}\''
                ]
            ),
            'required'=>['settingsFile'],
            'func'=>function($inputs,&$errors,$context,$params){
                $meta = $inputs['metaSettings'];
                $v = $context->variables;

                if(isset($v['settingsInputs'])){
                    $existing = json_decode($meta->getSetting($v['settingsFile'])??'[]',true);
                    $combined = array_merge($existing,$v['settingsInputs']);

                    $combined['title'] = $combined['title'] ?? $v['settingsFile'];
                    $combined['base64'] = $combined['base64'] ?? 0;
                    $combined['local'] = (int)($combined['local'] ?? !($combined['db']??0));
                    $combined['db']= (int)(!$combined['local']);
                }

                return $meta->setSetting(
                    $v['settingsFile'],
                    isset($combined) ? json_encode($combined) : null,
                    array_merge($params,['createNew'=>true]));
            }
        ],
        'getSetting'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Get single setting --se from file -f',
                [],
                [
                    'php settings.php -v -t -a get --se siteName -f siteSettings',
                    'php settings.php -v -t --drm -a get --se siteSettings -f metaSettings (use getMetaSettings instead)'
                ]
            ),
            'required'=>['settingsFile','settingName'],
            'func'=>function($inputs,&$errors,$context,$params){
                $v = $context->variables;
                $p = $params['func']($inputs,$v,$params);
                if($p['error']){
                    $errors['open-settings'] = $p['error'];
                    return null;
                }
                else{
                    $targetSettings = new \IOFrame\Handlers\SettingsHandler(
                        $inputs['settings']->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$v['settingsFile'].'/',
                        array_merge($inputs['defaultParams'],['opMode'=>$p['opMode'],'base64Storage'=>$p['base64']])
                    );
                    return $targetSettings->getSetting($v['settingName']);
                }
            }
        ],
        'getSettings'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Gets all settings from file -f',
                [],
                [
                    'php settings.php -v -t -a gs -f siteSettings',
                    'php settings.php -v -t --drm -a gs -f metaSettings (use getMetaSettings instead)'
                ]
            ),
            'required'=>['settingsFile'],
            'func'=>function($inputs,&$errors,$context,$params){
                $v = $context->variables;
                $p = $params['func']($inputs,$v,$params);
                if($p['error']){
                    $errors['open-settings'] = $p['error'];
                    return null;
                }
                else{
                    $targetSettings = new \IOFrame\Handlers\SettingsHandler(
                        $inputs['settings']->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$v['settingsFile'].'/',
                        array_merge($inputs['defaultParams'],['opMode'=>$p['opMode'],'base64Storage'=>$p['base64']])
                    );
                    return $targetSettings->getSettings();
                }
            }
        ],
        'printSettings'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Prints settings from file -f',
                [
                    'Better use printMetaSettings to print metaSettings'
                ],
                [
                    'php settings.php -v -t -a print -f siteSettings',
                    'php settings.php -v -t --drm -a print -f metaSettings'
                ]
            ),
            'required'=>['settingsFile'],
            'func'=>function($inputs,&$errors,$context,$params){
                $v = $context->variables;
                $p = $params['func']($inputs,$v,$params);
                if($p['error']){
                    $errors['open-settings'] = $p['error'];
                    return null;
                }
                else{
                    $targetSettings = new \IOFrame\Handlers\SettingsHandler(
                        $inputs['settings']->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$v['settingsFile'].'/',
                        array_merge($inputs['defaultParams'],['opMode'=>$p['opMode'],'base64Storage'=>$p['base64']])
                    );
                    return $targetSettings->printAll();
                }
            }
        ],
        'setSetting'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Set single setting --se in file -f to value --sv',
                [
                    'Can be used to update metaSettings too, with the right flags, but not advised.',
                    'Examples are in Powershell syntax'
                ],
                [
                    'php settings.php  -v -t --drm -c -a set -f metaSettings --se newSettings --sv \'{\"title\":\"New\",\"local\":1,\"db\":0}\' (use setMetaSettings instead)'
                ]
            ),
            'required'=>['settingsFile','settingName','settingValue'],
            'func'=>function($inputs,&$errors,$context,$params){
                $v = $context->variables;
                $p = $params['func']($inputs,$v,$params);
                if($p['error']){
                    $errors['open-settings'] = $p['error'];
                    return null;
                }
                else{
                    $targetSettings = new \IOFrame\Handlers\SettingsHandler(
                        $inputs['settings']->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$v['settingsFile'].'/',
                        array_merge($inputs['defaultParams'],['opMode'=>$p['opMode'],'base64Storage'=>$p['base64']])
                    );
                    return $targetSettings->setSetting(
                        $v['settingName'],
                        $v['settingValue'],
                        array_merge($params,['createNew'=>$v['createNew'],'initIfNotExists'=>true]));
                }
            }
        ],
        'unsetSetting'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Unset single setting --se in file -f',
                [
                    'Better use setMetaSettings to unset metaSettings'
                ],
                [
                    'php settings.php -v -t -a un --se siteName -f siteSettings',
                    'php settings.php -v -t --drm -a un --se siteSettings -f metaSettings'
                ]
            ),
            'required'=>['settingsFile','settingName'],
            'func'=>function($inputs,&$errors,$context,$params){
                $v = $context->variables;
                $p = $params['func']($inputs,$v,$params);
                if($p['error']){
                    $errors['open-settings'] = $p['error'];
                    return null;
                }
                else{
                    $targetSettings = new \IOFrame\Handlers\SettingsHandler(
                        $inputs['settings']->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$v['settingsFile'].'/',
                        array_merge($inputs['defaultParams'],['opMode'=>$p['opMode'],'base64Storage'=>$p['base64']])
                    );
                    return $targetSettings->setSetting($v['settingName'],null,$params);
                }
            }
        ],
        'setSettings'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                'Set settings in file -f with --si',
                [
                    'Examples are in Powershell syntax'
                ],
                [
                    'php settings.php -v -t -a ss -f siteSettings --si \'{\"siteName\":\"Website\",\"sslOn\":0,\"tokenTTL\":600,\"logStatus\":null}\''
                ]
            ),
            'required'=>['settingsFile','settingsInputs'],
            'func'=>function($inputs,&$errors,$context,$params){
                $v = $context->variables;
                $p = $params['func']($inputs,$v,$params);
                if($p['error']){
                    $errors['open-settings'] = $p['error'];
                    return null;
                }
                else{
                    $targetSettings = new \IOFrame\Handlers\SettingsHandler(
                        $inputs['settings']->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/'.$v['settingsFile'].'/',
                        array_merge($inputs['defaultParams'],['opMode'=>$p['opMode'],'base64Storage'=>$p['base64']])
                    );
                    return $targetSettings->setSettings(
                        $v['settingsInputs'],
                        array_merge($params,['createNew'=>$v['createNew'],'initIfNotExists'=>true]));
                }
            }
        ]
    ],
    'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
        [
            'This is the default IOFrames settings CLI.',
            'Can either to be run on nodes individually to set local settings, or used to set global settings from the CLI.',
        ],
        [
            'By default, infers the type of setting from metaSettings.',
            'New setting files/tables can be created by creating a new setting in a non-existent file/table.',
            'The creation of empty new settings files/tables is out of scope for this CLI.',
            'Do not abuse this CLI to set maintenance settings - use the maintenance CLI.'
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

$metaSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/metaSettings/',$defaultSettingsParams);

$decideOnSettings = function ($inputs, $variables, $params){

    $res = [
        'inMeta'=>false,
        'opMode'=>false,
        'base64'=>false,
        'error'=>false
    ];

    $settingsMeta = $inputs['metaSettings']->getSetting($variables['settingsFile']);
    $res['inMeta'] = $settingsMeta && \IOFrame\Util\PureUtilFunctions::is_json($settingsMeta);
    $settingsMeta = $res['inMeta']? json_decode($settingsMeta,true) : [];

    if(!$res['inMeta'] && !$variables['disregardMeta']){
        $res['error'] = 'not-in-meta';
        if($params['verbose'])
            echo 'Settings file not in meta'.EOL;
        return $res;
    }

    /*Meta.local > Var > inMeta? (defaultParams ?? db) : local */
    $res['opMode'] =
        (!empty($settingsMeta['local']) ? \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL : null) ??
        $variables['opMode'] ??
        (
            $res['inMeta'] ?
            ($inputs['defaultParams']['opMode'] ?? \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_DB) :
            \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL
        );
    $res['base64'] = $variables['base64']?? $settingsMeta['base64'] ?? false;

    /* Some actions require redis to work outside of local mode */
    if(
        !$inputs['defaultParams']['RedisManager']->isInit &&
        ($res['opMode'] !== \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL) &&
        in_array($variables['_action'],['setSetting','unsetSetting'])
    ){
        $res['error'] = 'no-redis';
        if($params['verbose'])
            echo 'Cannot operate outside of local mode without Redis being enabled'.EOL;
        return $res;
    }
    return $res;
};

$params = ['inputs'=>['settings'=>$settings,'metaSettings'=>$metaSettings,'defaultParams'=>$defaultSettingsParams],'func'=>$decideOnSettings,'test'=>$v['_test'],'verbose'=>$v['_verbose']];

$check = $CLIManager->matchAction(
    null,
    $params
);

die(json_encode($check,JSON_PRETTY_PRINT));
