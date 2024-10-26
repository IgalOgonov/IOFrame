<?php


if(php_sapi_name() != "cli"){
    echo 'Test accessible through the web (no flags, just files):'.EOL;

    $action = $_REQUEST['action']??null;
    $inputs = $_REQUEST['inputs']??null;
    if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs))
        $inputs = null;
    else
        $inputs = json_decode($inputs,true);

    //Define some test functions
    $testActionFailsMaybeSideEffects = function($inputs, &$errors, $context, $params, $sideEffects = true){
        $errors['example-error']=true;
        if($params['verbose']??false)
            $errors['verbose-error']='Verbose Error msg (only gets printed if verbose is true)';
        if($params['silent']??false)
            $errors['silent-error']='Silent error (this msg should not appear)';
        if($context->variables['fake_variable']??false)
            $errors['fake-error']='This msg should not appear either';
        if($context->variables['_help']??false)
            $errors['help-error']='Who will help the helpers?';
        if($inputs['test-input']??false)
            $errors['test-input']='Nooooo you cant just spawn an error only because of test mode noooooo';
        if($sideEffects){
            foreach ($errors as $type=>$error){
                echo 'Error '.$type.': '.$error.EOL;
            }
        }
        return false;
    };
    $testActionFailsFunctionSideEffects = function ($inputs, &$errors, $context, $params) use ($testActionFailsMaybeSideEffects){
        return $testActionFailsMaybeSideEffects($inputs, $errors, $context, $params, true);
    };
    $testActionFailsFunctionNoSideEffects = function ($inputs, &$errors, $context, $params) use ($testActionFailsMaybeSideEffects){
        return $testActionFailsMaybeSideEffects($inputs, $errors, $context, $params, false);
    };
    $testActionSucceedsMaybeSideEffects = function($inputs, &$errors, $context, $params, $sideEffects = true){
        $toPrint = [];

        if($inputs['toPrint']??false)
            $toPrint[] = $inputs['toPrint'];

        $toPrint[] = ' msg';

        if($sideEffects)
            foreach ($toPrint as $strToPrint){
                echo 'Success! '.$strToPrint.EOL;
            }

        return $toPrint;
    };
    $testActionSucceedsSideEffects = function($inputs, &$errors, $context, $params) use ($testActionSucceedsMaybeSideEffects) {
        return $testActionSucceedsMaybeSideEffects($inputs, $errors, $context, $params, true);
    };
    $testActionSucceedsNoSideEffects = function($inputs, &$errors, $context, $params) use ($testActionSucceedsMaybeSideEffects) {
        return $testActionSucceedsMaybeSideEffects($inputs, $errors, $context, $params, false);
    };

    $CLIManager = new \IOFrame\Managers\CLIManager(
        $settings,
        [
            'string'=>[
                'desc'=>'Regular string from a json file',
                'required'=>true,
                'fileObj'=>['type'=>'json','filePath'=>'cli/config/examples/example-1.json','key'=>'regString'],
                'func'=>function($input){
                    return $input.' - Modified';
                },
                'validation'=>[
                    "type"=>'string',
                    "valid"=>'^.*Modified$'
                ]
            ],
            'optionalString'=>[
                'desc'=>'Optional string from a json file',
                'required'=>false,
                'fileObj'=>['type'=>'json','filePath'=>'cli/config/examples/example-1.json','key'=>'_fakeString'],
                'validation'=>[
                    "type"=>'string',
                    "default"=>'Default Value'
                ]
            ],
            'int'=>[
                'desc'=>'Regular int from a json file',
                'fileObj'=>['type'=>'json','filePath'=>'cli/config/examples/example-1.json','key'=>'regInt'],
                'validation'=>[
                    "type"=>'int'
                ]
            ],
            'stringArr'=>[
                'desc'=>'Regular string[] from a json file',
                'fileObj'=>['type'=>'json','filePath'=>'cli/config/examples/example-1.json','key'=>'regStringArray'],
                'validation'=>[
                    "type"=>'string[]',
                    "valid"=>'[a-z]'
                ]
            ],
            'intArr'=>[
                'desc'=>'Regular int[] from a json file',
                'fileObj'=>['type'=>'json','filePath'=>'cli/config/examples/example-1.json','key'=>'regIntArray'],
                'validation'=>[
                    "type"=>'int[]',
                    "min"=>1,
                    "max"=>10
                ]
            ],
            'mixedArr'=>[
                'desc'=>'Mixed array from a json file',
                'fileObj'=>['type'=>'json','filePath'=>'cli/config/examples/example-1.json','key'=>'mixedArray'],
                'validation'=>[
                    "type"=>'json',
                    "valid"=>["ab","cd","ef",1,2,3]
                ]
            ],
            'dottedVar'=>[
                'desc'=>'String with dotted key from a json file',
                'fileObj'=>['type'=>'json','filePath'=>'cli/config/examples/example-1.json','key'=>'dotted.key'],
                'validation'=>[
                    "type"=>'function',
                    "valid"=>function($context){
                        $verbose = !empty($context['params']['verbose']);
                        if($verbose)
                            echo 'Inside dotted var test!'.EOL;
                        //Remember that the index is the name of this variable - NOT the key in the file
                        if(!str_contains($context['index']??'','Var'))
                            return false;
                        elseif(!str_contains($context['input']??'','Dotted'))
                            return false;
                        else
                            return true;
                    }
                ]
            ],
            'object.nestedString'=>[
                'desc'=>'String nested inside object from a json file',
                'fileObj'=>['type'=>'json','filePath'=>'cli/config/examples/example-1.json','key'=>'object.nestedString'],
                'validation'=>[
                    "type"=>'string',
                    "valid"=>'inside object$'
                ]
            ],
            'object.nestedObject'=>[
                'desc'=>'Object nested inside object with dotted key from a json file',
                'fileObj'=>['type'=>'json','filePath'=>'cli/config/examples/example-1.json','key'=>'object.nested.object'],
            ],
            'conf_foo'=>[
                'desc'=>'Normal string from sectioned config file',
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'normal.foo'],
                'validation'=>[
                    "type"=>'string',
                    "valid"=>['bar']
                ]
            ],
            'conf_foo_array'=>[
                'desc'=>'Array from sectioned config file',
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'array.foo'],
                'validation'=>[
                    "type"=>'int[]'
                ]
            ],
            'conf_foo_object'=>[
                'desc'=>'Object from sectioned config file',
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'dictionary.foo'],
                'validation'=>[
                    "type"=>'json'
                ]
            ],
            'conf_foo_nested'=>[
                'desc'=>'Nested string inside a section inside an object',
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'dictionary.foo.debug'],
                'validation'=>[
                    "type"=>'string'
                ]
            ],
            'conf_foo_dotted_2'=>[
                'desc'=>'Dotted key from sectioned config file',
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'multi.foo.data.password'],
                'validation'=>[
                    "type"=>'function',
                    "valid"=>function($context){
                        echo 'Inside conf_foo_dotted_2 test! Value: '.$context['input'].EOL;
                        $verbose = !empty($context['params']['verbose']);
                        if($verbose)
                            echo 'Inside conf_foo_dotted_2 test! Value: '.$context['input'].EOL;
                        return true;
                    }
                ]
            ],
            '_action'=>[
                'desc'=>'Action value manually set from $_REQUEST in the web example',
                'validation'=>['type'=>'string','required'=>false,'default'=>$action]
            ]
        ],
        [
            'checkCLI'=>false,
            'verbose'=>true,
            'action'=>'_action',
            'actions'=>[
                'testAction'=>[
                    'desc'=>'Test action without any requirements or a function (does nothing)'
                ],
                'testActionFailsRequired'=>[
                    'desc'=>'Test action that requires a variable which is not even defined',
                    'required'=>['fake_variable']
                ],
                'testActionFailsFunctionNoSideEffects'=>[
                    'desc'=>'Test action that fails, but has no side effects',
                    /*Specifically implemented here directly (slightly different from above testActionFailsFunctionNoSideEffects() func), as an example*/
                    'func'=>function($inputs, &$errors, $context, $params){
                        $errors['example-error']=true;
                        if($params['verbose']??false)
                            $errors['verbose-error']=true;
                        if($params['silent']??false)
                            $errors['silent-error']=true;
                        if($context->variables['fake_variable']??false)
                            $errors['fake-error']=true;
                        if($context->variables['_help']??false)
                            $errors['help-error']=true;
                        if($inputs['test-input']??false)
                            $errors['test-input']=true;
                        return false;
                    }
                ],
                'testActionFailsFunctionSideEffects'=>[
                    'desc'=>'Test action that fails, and prints out something',
                    'func'=>$testActionFailsFunctionSideEffects
                ],
                'testActionSucceedsNoSideEffects'=>[
                    'desc'=>'Test action that succeeds, printing nothing',
                    'func'=>$testActionSucceedsNoSideEffects
                ],
                'testActionSucceedsSideEffects'=>[
                    'desc'=>'Test action that succeeds, and prints something',
                    'func'=>$testActionSucceedsSideEffects
                ],
            ]

        ]
    );

    echo EOL.EOL.'Variables: '.EOL.EOL;
    var_dump($CLIManager->variables);

    echo EOL.EOL.'Actions: '.EOL.EOL;
    var_dump($CLIManager->actions);

    echo EOL.EOL.'Help: '.EOL.EOL;
    $CLIManager->printHelp();

    echo EOL.EOL.'Actions (manually executed, ignoring REQUEST action): '.EOL.EOL;

    echo EOL.'testActionFailsRequired: '.EOL;
    echo json_encode(
            $CLIManager->matchAction(
                'testActionFailsRequired',
                ['test'=>true,'verbose'=>true,'printResult'=>false]
            )
        ).EOL;

    echo EOL.'testAction with input: '.EOL;
    echo json_encode(
            $CLIManager->matchAction(
                'testAction',
                ['inputs'=>true,'test'=>true,'verbose'=>true,'printResult'=>false]
            )
        ).EOL;

    echo EOL.'testActionFailsFunctionNoSideEffects: '.EOL;
    echo json_encode(
        $CLIManager->matchAction(
            'testActionFailsFunctionNoSideEffects',
            ['inputs'=>['test-input'=>true],'test'=>true,'verbose'=>true,'printResult'=>false]
        )
    ).EOL;

    echo EOL.'testActionFailsFunctionSideEffects: '.EOL;
    echo json_encode(
        $CLIManager->matchAction(
            'testActionFailsFunctionSideEffects',
            ['inputs'=>['test-input'=>true],'test'=>true,'verbose'=>true,'printResult'=>false]
        )
    ).EOL;

    echo EOL.'testActionSucceedsNoSideEffects: '.EOL;
    echo json_encode(
        $CLIManager->matchAction(
            'testActionSucceedsNoSideEffects',
            ['inputs'=>['toPrint'=>'AAAAAAAAAAAA'],'test'=>true,'verbose'=>true,'printResult'=>false]
        )
    ).EOL;

    echo EOL.'testActionSucceedsSideEffects: '.EOL;
    echo json_encode(
        $CLIManager->matchAction(
            'testActionSucceedsSideEffects',
            ['inputs'=>['toPrint'=>'REEEEEEEEEEEEE'],'test'=>true,'verbose'=>true,'printResult'=>false]
        )
    ).EOL;

}
else{
    echo 'Test accessible through cli:'.EOL.EOL;

    //Not auto-retrieved in the CLI
    $settings = new \IOFrame\Handlers\SettingsHandler(IOFrame\Util\HelperFunctions::getAbsPath().'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/localSettings/');

    $CLIManager = new \IOFrame\Managers\CLIManager(
        $settings,
        [
            'conf_foo'=>[
                'desc'=>'Normal string from sectioned config file',
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'normal.foo'],
                'validation'=>[
                    "type"=>'string',
                    "valid"=>['bar']
                ]
            ],
            'conf_foo_array'=>[
                'desc'=>'Array from sectioned config file',
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'array.foo'],
                'validation'=>[
                    "type"=>'int[]'
                ]
            ],
            'conf_foo_object'=>[
                'desc'=>'Object from sectioned config file',
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'dictionary.foo'],
                'validation'=>[
                    "type"=>'json'
                ]
            ],
            'conf_foo_nested'=>[
                'desc'=>'Nested string inside a section inside an object',
                //If passed, the CLI flag will precede the file
                'cliFlag'=>'--foo-debug',
                'hasInput'=>true,
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'dictionary.foo.path'],
                'validation'=>[
                    "type"=>'string'
                ]
            ],
            '_inputs'=>[
                'desc'=>'Inputs from cli',
                'cliFlag'=>['-i','--inputs'],
                'hasInput'=>true,
                'reserved'=>true,
                'validation'=>["type"=>"string",'required'=>false]
            ],
            'extraVar'=>[
                'desc'=>'Normal string from input',
                'cliFlag'=>['-e','--extra-var'],
                'hasInput'=>true,
                'validation'=>["type"=>'string','required'=>false]
            ],
            'loadedFromFile1'=>[
                'desc'=>'String to be loaded with --fp',
                'validation'=>["type"=>'string', 'valid'=>'Success 1','required'=>false],
            ],
            'loadedFromFile2'=>[
                'desc'=>'Int to be loaded with --fp',
                'validation'=>["type"=>'string', 'valid'=>'Success 2','required'=>false],
            ],
            'loadedFromFileArr'=>[
                'desc'=>'Array to be loaded with --fp',
                'validation'=>["type"=>'json', 'required'=>false],
            ],
            'loadWithKFM1'=>[
                'desc'=>'String to be loaded with --kfm',
                'validation'=>["type"=>'string', 'valid'=>'Success 1','required'=>false],
            ],
            'loadWithKFM2'=>[
                'desc'=>'String to be loaded with --kfm, with different key',
                'validation'=>["type"=>'string', 'valid'=>'Different','required'=>false],
            ],
            'loadWithKFMNested1'=>[
                'desc'=>'String to be loaded with --kfm, nested, with different key',
                'validation'=>["type"=>'string', 'valid'=>'Nested','required'=>false],
            ],
            'loadWithKFMNested2'=>[
                'desc'=>'Object to be loaded with --kfm, nested, with different key',
                'validation'=>["type"=>'json','required'=>false],
            ],
            'loadWithFKM1'=>[
                'desc'=>'String to be loaded with --fkm, from file 1',
                'validation'=>["type"=>'string', 'valid'=>'Success 1','required'=>false],
            ],
            'loadWithFKM2'=>[
                'desc'=>'String to be loaded with --fkm, from file 2',
                'validation'=>["type"=>'string', 'valid'=>'Success 2','required'=>false],
            ],
            'loadWithFKMNested1'=>[
                'desc'=>'String to be loaded with --fkm, from file 1, with different key',
                'validation'=>["type"=>'string', 'valid'=>'Nested 1','required'=>false],
            ],
            'loadWithFKMNested2'=>[
                'desc'=>'String to be loaded with --fkm, from file 2, nested, with different key',
                'validation'=>["type"=>'string', 'valid'=>'Nested','required'=>false],
            ],
            'loadWithFKMNested3'=>[
                'desc'=>'Object to be loaded with --fkm, from file 2, nested, with different key',
                'validation'=>["type"=>'json','required'=>false],
            ],
            '_action'=>[
                //You don't need to manually set hasInput for the defined action
                //You also don't need validation, as it must match one of the defined action keys (strings)
                'desc'=>'Action value from cli',
                'cliFlag'=>['-a','--action'],
                //You don't have to use the $context and $params arguments in the function, if you dont want to
                'func'=>function($action){
                    /* You may mutate the action value though a function if you wish - for example, create aliases or set defaults.
                       The mutation function may also have side effects like this, although printing messages should be limited to testing */
                    switch ($action){
                        case 'ta':
                        case 'test-action':
                            $action = 'testAction';
                            break;
                        case 'ptv':
                        case 'prints-test-var':
                            $action = 'printsTestVar';
                            break;
                        case 'pv':
                            $action = 'printVar';
                            break;
                        case 'ev':
                            $action = 'printExtraVar';
                            break;
                        default:
                    }
                    return $action;
                }
            ]
        ],
        [
            'verbose'=>true,
            'generateFileRelated'=>true,
            'action'=>'_action',
            'actions'=>[
                'testAction'=>[
                    'desc'=>'Test action without any requirements or a function (does nothing)'
                ],
                'printsTestVar'=>[
                    'desc'=>'Prints _inputs, provided during call with flag -i or --inputs',
                    'required'=>'_inputs',
                    'func'=>function($inputs, &$errors, $context, $params){
                        if(!empty($context->variables['_inputs']))
                            echo $context->variables['_inputs'].EOL;
                        else
                            $errors[] = 'no-inputs';
                        return true;
                    }
                ],
                'printVar'=>[
                    'desc'=>'Prints variable specified in _inputs, provided during call with flag -i or --inputs',
                    'required'=>'_inputs',
                    'func'=>function($inputs, &$errors, $context, $params){
                        if(!empty($context->variables['_inputs']))
                            echo $context->variables[$inputs].EOL;
                        else
                            $errors[] = 'no-inputs';
                        return true;
                    }
                ],
            ]

        ]
    );

    //Add an additional variable, and an additional action
    $CLIManager->initiate(
        [
            /* Wont work because cli flags can be initiated only ones */
            'wontGetInitiated'=>[
                'desc'=>'Inputs from cli',
                'cliFlag'=>['--wont-get-initiated'],
                'hasInput'=>true,
                'validation'=>["type"=>"string",'required'=>false]
            ],
            /* Will work, because we can always read files (and env variables)*/
            'conf_foo_dotted_2'=>[
                'desc'=>'Dotted key from sectioned config file',
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'multi.foo.data.password'],
                'validation'=>[
                ]
            ],
            /* Modifies extraVar, changing the description, and adding the ability to load it from a file*/
            'extraVar'=>[
                'desc'=>'Normal string from input, or sectioned config file',
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'normal.foo']
            ],
            /* Doesn't modify conf_foo_nested because it was already loaded before, and overwrite is false by default*/
            'conf_foo_nested'=>[
                'desc'=>'Normal string from input, or sectioned config file',
                'fileObj'=>['type'=>'config','sections'=>true,'filePath'=>'cli/config/examples/example-2.conf','key'=>'normal.foo']
            ],
        ],
        [
            'checkCLI'=>false,
            'verbose'=>true,
            'actions'=>[
                'printExtraVar'=>[
                    'desc'=>'Prints the extra variable, defined in the 1st initiation',
                    'required'=>'extraVar',
                    'func'=>function($inputs, &$errors, $context, $params){
                        echo $context->variables['extraVar'].EOL;
                        return true;
                    }
                ],
            ]
        ]
    );
    //If files were provided, run a 2nd initiation. Note that if none were provided, this will return relevant result, too
    echo EOL;
    $initiateWithFiles = $CLIManager->populateFromFiles(['test'=>true,'verbose'=>true]);
    echo 'Initiated from file? '.($initiateWithFiles['file']? 'Yes': 'No').EOL;
    echo 'kfm initiation '.($initiateWithFiles['kfm']??'not-provided').EOL;
    echo 'fkm initiation '.($initiateWithFiles['fkm']??'not-provided').EOL;
    echo 'file initiation result: '.json_encode($initiateWithFiles['init']).EOL;
    echo EOL;

    /* Examples:
        php cliTest.php -h                  //prints full help
        php cliTest.php -a ta -h            //prints testAction help
        php cliTest.php -a test-action -h   //same as above, see variable _action mutation for explanation
        php cliTest.php -a testAction -h    //same as above
        php cliTest.php -a testAction       //literally nothing
        php cliTest.php -a printsTestVar -h //help for printsTestVar
        php cliTest.php -a ptv -h           //same as above
        php cliTest.php -a ptv -i "Hello CLI"                   //prints the input
        php cliTest.php --action ptv --inputs "Hello again CLI" //same as above, different flags and input
        php cliTest.php -a pv -i conf_foo_nested                //prints "/some/path"
        php cliTest.php -a pv -i conf_foo_nested --foo-debug "Hi CLI"   //prints "Hi CLI"
        php cliTest.php -a ev --extra_var "One last test"               //prints "One last test"
        php cliTest.php -a ev --extra_var "One last test" --wont-get-initiated "example" //same as above, see variables to see wontGetInitiated is still null
        php cliTest.php --fp cli/config/examples/load-from-file-extra-1         //initiates variables form file (note some kfm/fkm variables from the same file with names similar to their variables)
        php cliTest.php --kfm cli/config/examples/kfm-example.json                           //prints full help, initiates variables via key-file map
        php cliTest.php --fkm cli/config/examples/fkm-example.json                           //prints full help, initiates variables via file-key map
        php cliTest.php --fp cli/config/examples/load-from-file-extra-1 --kfm cli/config/examples/kfm-example.json --fkm cli/config/examples/fkm-example.json -h  //combined example

        Note that the printed result doesnt come from the action, we're overriding the default print and printing it ourselves.
    */
    $CLIManager->matchAction(
        null,
        ['inputs'=>$CLIManager->variables['_inputs']??$CLIManager->variables['extraVar'],'test'=>true,'verbose'=>true,'checkHelp'=>true,'printResult'=>true]
    );
    echo EOL.EOL.' -- Final Variables --'.EOL.EOL;
    var_dump($CLIManager->variables);
}