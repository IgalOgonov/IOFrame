<?php
/* This interface is meant to handle certain frontend related tasks, such as creating modules/components from boiler plates,
 * and manually minifying/packaging/transpiling CSS/JS/SCSS.
 * Read the desc for more information
 * */
require 'commons/ensure_cli.php';
if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../main/core_init.php';
require 'frontend/common/createPage.php';

$initiationDefinitions = [
    '_action'=>[
        'desc'=>'Action value from cli',
        'cliFlag'=>['-a','--action'],
        'envArg'=>['IOFRAME_CLI_FRONTEND_ACTION'],
        'func'=>function($action){
            switch ($action){
                case 'm':
                case 'min':
                    $action = 'minify';
                    break;
                case 'c':
                    $action = 'create';
                    break;
                default:
            }
            return $action;
        }
    ],
    'pageGenerationOptions'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
            [
                'Properties for creating a page, and potentially additional template / CSS / JS files.',
                'Those options are very large, and should typically be loaded via a file.',
                'Unfortunately, the definition is far too long to display here.',
                'You can view find information on ioframe.io/docs, or in this file in the actions\' PHPDoc.',
            ],
        ),
        'cliFlag'=>['--pgo','--page-generation-options'],
        'envArg'=>['IOFRAME_CLI_FRONTEND_PAGE_GENERATION_OPTIONS'],
        'hasInput'=>true,
        'validation'=>['type'=>'json','required'=>false]
    ],
    'minificationType'=>[
        'desc'=>'Either "js" (default) or "css" ',
        'envArg'=>['IOFRAME_CLI_FRONTEND_MINIFICATION_TYPE'],
        'cliFlag'=>['--type','--minification-type'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'valid'=>['js','css'] , 'required'=>false]
    ],
    'minificationTargets'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
            [
                'Array of CSS / JS file / folder addresses',
                'Typically, each file can be transpiled / minified alone, or be packaged together with other files into a new file.',
                'Folders will be automatically packaged. For options, see minificationProperties.',
                'For exact structure, see FrontEndResources->getFrontendResources()',
                '--- Important ---',
                'All addresses are RELATIVE to the resource root folder, which is either specified in minificationProperties,',
                'or defaults (based on type) to resourceSettings->getSetting("jsPathLocal") / resourceSettings->getSetting("cssPathLocal")'
            ],
        ),
        'envArg'=>['IOFRAME_CLI_FRONTEND_MINIFICATION_TARGETS'],
        'cliFlag'=>['--targets','--minification-targets'],
        'hasInput'=>true,
        'validation'=>['type'=>'json', 'required'=>false]
    ],
    'minificationProperties'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
            [
                'Properties for minifying / packaging / transpiling CSS / JS files',
                'Exact structure corresponds to FrontEndResources->getFrontendResources() $params'
            ],
        ),
        'envArg'=>['IOFRAME_CLI_FRONTEND_MINIFICATION_PROPERTIES'],
        'cliFlag'=>['--properties','--minification-properties'],
        'hasInput'=>true,
        'validation'=>['type'=>'json', 'required'=>false]
    ],
    'minificationIncludeOptions'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
            [
                'JSON encoded array that corresponds to IOFrame\Util\FileSystemFunctions::fetchAllFolderAddresses() $params',
                'Options for specific files to include. Will enforce returnFolders:false, include:[\'\.$type\']'
            ],
            [
                'Those options affect only sub-folders of folder(s) in --tests.'
            ]
        ),
        'envArg'=>['IOFRAME_CLI_FRONTEND_MINIFICATION_INCLUDE_OPTIONS'],
        'cliFlag'=>['--mip','--minification-include-options'],
        'hasInput'=>true,
        'validation'=>['type'=>'json', 'required'=>false]
    ],
    '_silent'=>[
        'desc'=>'Silent mode',
        'envArg'=>['IOFRAME_CLI_FRONTEND_SILENT'],
        'reserved'=>true,
        'cliFlag'=>['-s','--silent']
    ],
    '_test'=>[
        'desc'=>'Test mode',
        'envArg'=>['IOFRAME_CLI_FRONTEND_TEST'],
        'reserved'=>true,
        'cliFlag'=>['-t','--test']
    ],
    '_verbose'=>[
        'desc'=>'Verbose output',
        'envArg'=>['IOFRAME_CLI_FRONTEND_VERBOSE'],
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
        'create'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                [
                    'Creates a page, and potentially related templates / JS / CSS, defined via --pgo',
                    'The structure of the file is too long to explain in a help message, but can be found ',
                    'in the PHPDoc of the function createPage(), or at ioframe.io/docs.'
                ],
                [],
                [
                    'php frontend.php -t -v -a create --fp cli/config/frontend/users-page.json'
                ]
            ),
            'required'=>['pageGenerationOptions'],
            'func'=>function($inputs,&$errors,$context,$params){
                if(!is_array($context->variables['pageGenerationOptions']) || empty($context->variables['pageGenerationOptions'])){
                    $errors['notValidJSON'] = true;
                    return false;
                }
                return createPage($context->variables['pageGenerationOptions'],$errors,$params);
            }
        ],
        'minify'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                [
                    'Minifies/packs/transpiles JS/CSS files/folders, based on the properties of',
                    'FrontEndResources->getFrontendResources() with $params {updateDBIfExists:false,ignoreLocal:false,forceMinify:true}',
                    'Fore more information, and exact returned data, see example config, FrontEndResources and frontEndResourceTest.'
                ],
                [],
                [
                    'php frontend.php -t -v -a min --fp cli/config/examples/frontend/minify-all-js-modules.json',
                    'php frontend.php -t -v -a min --fp cli/config/examples/frontend/minify-and-pack-js-a-module-and-its-components.json',
                    'php frontend.php -t -v -a min --fp cli/config/examples/frontend/minify-and-pack-all-js.json',
                    'php frontend.php -t -v -a min --fp cli/config/examples/frontend/minify-and-pack-all-css.json',
                    'php frontend.php -t -v -a min --fp cli/config/examples/frontend/transpile-and-pack-some-scss.json'
                ]
            ),
            'required'=>['minificationType','minificationTargets'],
            'func'=>function($inputs,&$errors,$context,$params){

                $v = $context->variables;
                $defaults = $params['defaultParams'];
                $FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($defaults['localSettings'],$defaults);
                $type = $v['minificationType'];
                $targets = $v['minificationTargets'];
                $properties = $v['minificationProperties']??[];
                $resourceRoot = $properties['rootFolder'] ?? $FrontEndResources->resourceSettings->getSetting($type.'PathLocal');
                $targetAddresses = [];

                if(!\IOFrame\Util\CLI\FileInclusionFunctions::validateAndEnsureArray($targets,$errors,'invalid-target-format'))
                    return false;

                $targets = array_map(function ($target)use($resourceRoot){ return $resourceRoot.$target; },$targets);

                if(!empty($properties) && !is_array($properties)){
                    $errors['invalid-properties'] = true;
                    return false;
                }

                \IOFrame\Util\CLI\FileInclusionFunctions::populateWithFiles(
                    $targetAddresses,
                    $targets,
                    $v,
                    'minificationIncludeOptions',
                    $defaults['absPathToRoot'],
                    [
                        'includeFileTypes'=> ($type === 'js'? ['\.js$'] : ['\.css$','\.scss$']),
                        'excludeFileTypes'=> ($type === 'js'? ['\.min\.js$'] : ['\.min\.css$'])
                    ]
                );

                $targetAddresses = array_map(function ($target)use($defaults,$resourceRoot){
                    return substr($target,strlen($defaults['absPathToRoot'].$resourceRoot));
                },$targetAddresses);

                $sharedParams = array_merge($params,$properties,[
                    'forceMinify'=>true
                ]);
                return ($type === 'js')?
                    $FrontEndResources->minifyJSFiles($targetAddresses,$sharedParams) :
                    $FrontEndResources->minifyCSSFiles($targetAddresses,$sharedParams) ;
            }
        ]
    ],
    'helpDesc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
        [
            'This is the default IOFrames frontend CLI.',
            'It lets you generate front end modules (pages, templates, JS/CSS modules, JS/CSS components), as well as',
            'minify / package / transpile JS/CSS/SCSS manually.'
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

$check = $CLIManager->matchAction(
    null,
    $params
);

die(json_encode($check,JSON_PRETTY_PRINT));
