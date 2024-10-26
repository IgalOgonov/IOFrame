<?php
/* This interface is meant to be used for testing purposes.
 * It can run automated tests (default location located: test/automatic/), as well as seed the DB with test values.
 * * Note that the DB functionality requires both DB and cache being available.
 * TODO Add PHPUnit operation mode / integration
 * */
require 'commons/ensure_cli.php';
if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../main/core_init.php';

$initiationDefinitions = [
    '_action'=>[
        'desc'=>'Action value from cli',
        'cliFlag'=>['-a','--action'],
        'envArg'=>['IOFRAME_CLI_TESTING_ACTION'],
        'func'=>function($action){
            switch ($action){
                case 't':
                    $action = 'test';
                    break;
                case 's':
                case 'sdb':
                case 'seed':
                    $action = 'seedDB';
                    break;
                case 'c':
                case 'cdb':
                case 'clean':
                    $action = 'cleanDB';
                    break;
                case 'dt':
                case 'duplicate':
                    $action = 'duplicateTables';
                default:
            }
            return $action;
        }
    ],
    'tests'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
            [
                'String|String[] of file/folder paths.',
                'All folders/files will be checked against project root, then global root.',
                'Files/folders are ran in the order they were defined.',
                'Files are not duplicated in case of overlapping files/folders.',
            ],
            [
                'To set specific inclusion options with folders, see --test-include-options.',
                'For fore information, see test action description.'
            ]
        ),
        'cliFlag'=>['--tests'],
        'envArg'=>['IOFRAME_CLI_TESTING_FILES'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'required'=>false]
    ],
    'testIncludeOptions'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
            [
                'JSON encoded array that corresponds to IOFrame\Util\FileSystemFunctions::fetchAllFolderAddresses() $params',
                'Options for specific files to include. Will enforce returnFolders:false, include:[\'\.php$\']',
            ],
            [
                'Those options affect only sub-folders of folder(s) in --tests.'
            ]
        ),
        'cliFlag'=>['--tio','--test-include-options'],
        'envArg'=>['IOFRAME_CLI_TESTING_TEST_INCLUDE_OPTIONS'],
        'hasInput'=>true,
        'validation'=>['type'=>'json', 'default'=>[],'required'=>false]
    ],
    'requireBefore'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
            [
                'Requires a number of common files/folders before the tests.',
                'Same format as --tests.'
            ]
        ),
        'cliFlag'=>['--rb','--require-before'],
        'envArg'=>['IOFRAME_CLI_TESTING_REQUIRE_BEFORE'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'required'=>false]
    ],
    'requireBeforeIncludeOptions'=>[
        'desc'=>'Same as testIncludeOptions, for requireBefore',
        'cliFlag'=>['--rbo','--require-before-options'],
        'envArg'=>['IOFRAME_CLI_TESTING_REQUIRE_BEFORE_INCLUDE_OPTIONS'],
        'hasInput'=>true,
        'validation'=>['type'=>'json', 'default'=>[], 'required'=>false]
    ],
    'requireAfter'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
            [
                'Requires a number of common files/folders after the tests.',
                'Same format as --tests.'
            ]
        ),
        'envArg'=>['IOFRAME_CLI_TESTING_REQUIRE_AFTER'],
        'cliFlag'=>['--ra','--require-after'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'required'=>false]
    ],
    'requireAfterIncludeOptions'=>[
        'desc'=>'Same as testIncludeOptions, for requireAfter',
        'cliFlag'=>['--rao','--require-after-options'],
        'envArg'=>['IOFRAME_CLI_TESTING_REQUIRE_AFTER_INCLUDE_OPTIONS'],
        'hasInput'=>true,
        'validation'=>['type'=>'json', 'default'=>[], 'required'=>false]
    ],
    'altDBSettings'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
            [
                'A path to a valid sqlSettings folder, from project root.',
                'This should be a local settings file, properly created elsewhere (e.g. settings CLI)',
                'May point to an alternative Server / DB, or same DB but with a different prefix.'
            ],
            [
                'Should be used for testing, or seeding from/to a db',
                'The new settings handler will be passed in $params as $altDBSettings.',
                'An SQLManager connected to that DB will be passed in $inputs as $AltDBManager.'
            ]
        ),
        'envArg'=>['IOFRAME_CLI_TESTING_ALT_DB_SETTINGS'],
        'cliFlag'=>['--dbs','--db-settings'],
        'hasInput'=>true,
        'validation'=>['type'=>'string', 'required'=>false]
    ],
    'duplicateTables'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
            [
                'This should be a valid JSON of the form {"tableName":"newTableName"}',
                'See action of the same name'
            ]
        ),
        'envArg'=>['IOFRAME_CLI_TESTING_DUPLICATE_TABLES'],
        'cliFlag'=>['--dt','--duplicate-tables'],
        'hasInput'=>true,
        'validation'=>['type'=>'json', 'required'=>false]
    ],
    'duplicationOptions'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            'Options for duplicateTables, in a JSON form. All default null, unless specified.',
            [
                'sourcePrefix'=>[
                    'type'=>'string',
                    'default'=>'null',
                    'desc'=>'Prepends this prefix to the tables you copy to.'
                ],
                'targetPrefix'=>[
                    'type'=>'string',
                    'default'=>'null',
                    'desc'=>'Prepends this prefix to the tables you copy from.'
                ],
                'sourceDB'=>[
                    'type'=>'string',
                    'default'=>'null',
                    'desc'=>'Prepends this database to the tables you copy to.'
                ],
                'targetDB'=>[
                    'type'=>'string',
                    'default'=>'null',
                    'desc'=>'Prepends this database to the tables you copy from.'
                ],
                'createTable'=>[
                    'type'=>'bool',
                    'default'=>'true',
                    'desc'=>'Whether to create new tables.'
                ],
                'includeData'=>[
                    'type'=>'bool',
                    'default'=>'null',
                    'desc'=>'Whether to include data from duplicated tables.'
                ],
                'includeDataWhere'=>[
                    'type'=>'object',
                    'default'=>'[]',
                    'desc'=>'Of the form "tableToCopyFromWithoutPrefixes"=>"sqlWhereClause", Each where clause is appended as-is.'
                ],
            ],
            'sourcePrefix/targetPrefix default to the system SQL prefix, unless explicitly passed false'
        ),
        'envArg'=>['IOFRAME_CLI_TESTING_DUPLICATION_OPTIONS'],
        'cliFlag'=>['--do','--duplication-options'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'dbOptions',[
                'sourcePrefix'=>null,
                'targetPrefix'=>null,
                'sourceDB'=>null,
                'targetDB'=>null,
                'createTable'=>true,
                'includeData'=>null,
                'includeDataWhere'=>[],
            ]);
        }
    ],
    'dbData'=>[
        'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
            [
                'This variable has three different formats - two for seeding, one for deletion.',
                'Passed as a json string, should be loaded from a file with --fp at any reasonable scale.',
                'For seeding, two formats are supported:',
                '',
                '[seedDB] Universal Format',
                'This format is obtained from a query akin to:',
                'SELECT JSON_OBJECT(\'resources\', JSON_ARRAYAGG(JSON_OBJECT(\'Resource_Type\', Resource_Type , \'Address\', Address))) FROM resources;',
                'In this format, an object is returned, where each key is the table name, and its value',
                'is an array of objects of the form {Column => value}',
                'As many tables / rows as you want may be placed in the file this way',
                '* All row objects withing a table MUST have the same keys,',
                '  but MAY have only a subset of keys enough to satisfy any constraints.',
                '* The input may be an object, but tables are populated in-order as written (important for constraints)',
                '',
                '[seedDB] PHPMyAdmin Format',
                'This format uses the JSON export format of PHPMyAdmin.',
                'In this format, the input is an array of objects. Relevant objects are of "type":"table".',
                'Inside such objects, the "data" key is an array of similar format / constraints to the universal format.',
                '',
                '[cleanDB] Unique Format',
                'For deletion, the input is an array of objects. Each object is of the form:',
                json_encode(
                    [
                        '<string - table name>'=>[
                            'drop'=>'<bool, default false - it true, drops the table>',
                            'cond'=>'<array, default [] - any valid SQLManager->deleteFromTable() $cond, e.g. [[\'ID\',1,\'>\'],[\'Name\',\'%Tom%\',\'LIKE\'],\'AND\'] >',
                        ],
                    ],
                JSON_PRETTY_PRINT
                ),
                'Any SQL expression can be built with cond - see PHPQueryBuilder for details',
                'The expressions are executed in the order defined.'
            ]
        ),
        'envArg'=>['IOFRAME_CLI_TESTING_DB_DATA'],
        'cliFlag'=>['--dbd','--db-data'],
        'hasInput'=>true
    ],
    'dbOptions'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            'Options for cleanDB and seedDB, in a JSON form.',
            [
                'targetPrefix'=>[
                    'type'=>'string',
                    'default'=>'null',
                    'desc'=>'Appends this prefix to the tables you seed/clean'
                ],
                'targetDB'=>[
                    'type'=>'string',
                    'default'=>'null',
                    'desc'=>'Appends this database to the tables you seed/clean'
                ],
            ],
            'Keep in mind the tables in the source/target may already be prefixed.'
        ),
        'envArg'=>['IOFRAME_CLI_TESTING_DB_OPTIONS'],
        'cliFlag'=>['--dbo','--db-options'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'dbOptions',[
                'targetPrefix'=>null,
                'targetDB'=>null,
            ]);
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
        'envArg'=>['IOFRAME_CLI_TESTING_LOGGING_OPTIONS'],
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
    '_silent'=>[
        'desc'=>'Silent mode',
        'envArg'=>['IOFRAME_CLI_TESTING_SILENT'],
        'reserved'=>true,
        'cliFlag'=>['-s','--silent']
    ],
    '_test'=>[
        'desc'=>'Test mode',
        'envArg'=>['IOFRAME_CLI_TESTING_TEST'],
        'reserved'=>true,
        'cliFlag'=>['-t','--test']
    ],
    '_verbose'=>[
        'desc'=>'Verbose output',
        'envArg'=>['IOFRAME_CLI_TESTING_VERBOSE'],
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
        'test'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                [
                    'Either runs all tests in a folder(s), and/or specific test file(s) --tests',
                    'All test files are executed procedurally.',
                    'All test files SHOULD contain the function $_test, inheriting the arguments from this function - files without it wont be checked',
                    'All test files MUST have no side effects, but MAY require_once other files without side effects.',
                    'All $_test functions SHOULD have no side effects, but SHOULD respect $test/$verbose/$silent params',
                    'Additional common files/folders MAY be included --rb/--ra. ',
                    'All such files will be included procedurally, in order.',
                    'Files from --rb will run BEFORE the tests, while --ra will run AFTER the tests',
                    'Files from --rb MAY have side effects, but any such effects MUST be reverted in the --ra files.',
                    'A different DB for the tests may be specified with --db-settings',
                ],
                [
                    'Read variable descriptions for formatting.',
                    'Examples are in Powershell syntax'
                ],
                [
                    'php testing.php -v -t -a test --tests "test/automatic/examples" --tio \'{\"exclude\":[\"CLI\"],\"include\":[\"Admin\"]}\' --rb \'[\"test/automatic/examples/scripts/print_hello.php\",\"test/automatic/examples/scripts\"]\' --rbo \'{\"include\":[\"world\"]}\' --ra "test/automatic/examples/scripts" --rao \'{\"exclude\":[\"world\",\"hello\"]}\'',
                    'php testing.php -v -t -a test --tests "test/automatic/examples"',
                ]
            ),
            'logChannel'=> \IOFrame\Definitions::LOG_CLI_TESTING_CHANNEL,
            'logHandlers'=>[$defaultSettingsParams['logHandler']],
            'shouldLog'=> Closure::fromCallable([\IOFrame\Util\CLI\CommonManagerHelperFunctions::class, 'defaultShouldLogMatcher']),
            'logLevelCalculator'=>Closure::fromCallable([\IOFrame\Util\CLI\CommonManagerHelperFunctions::class, 'defaultLogLevelCalculator']),
            'logContextGenerator'=>Closure::fromCallable([\IOFrame\Util\CLI\CommonManagerHelperFunctions::class, 'defaultLogContextGenerator']),
            'logMessageGenerator'=>function($result,$context){
                return \IOFrame\Util\CLI\CommonManagerHelperFunctions::defaultLogMessageGenerator($result, $context, [
                    'errorMsg'=>'Failed to pass CLI tests',
                    'noErrorMsg'=>'Passed CLI tests',
                ]);
            },
            'required'=>['tests'],
            'func'=>function($inputs,&$errors,$context,$params){

                $v = $context->variables;
                $tests = $v['tests'];
                $requireBefore = $v['requireBefore']??null;
                $requireAfter = $v['requireAfter']??null;

                $results = [];
                $testsToRun = [];
                $beforeToRun = [];
                $afterToRun = [];

                if(empty($tests)){
                    $errors['no-tests'] = true;
                    return $results;
                }
                elseif(!\IOFrame\Util\CLI\FileInclusionFunctions::validateAndEnsureArray($tests,$errors,'invalid-tests-format')){
                    return $results;
                }

                if(!empty($requireBefore) && !\IOFrame\Util\CLI\FileInclusionFunctions::validateAndEnsureArray($requireBefore,$errors,'invalid-run-before-format')){
                    return $results;
                }

                if(!empty($requireAfter) && !\IOFrame\Util\CLI\FileInclusionFunctions::validateAndEnsureArray($requireAfter,$errors,'invalid-run-after-format')){
                    return $results;
                }

                $projectRoot = $params['defaultParams']['absPathToRoot'];
                \IOFrame\Util\CLI\FileInclusionFunctions::populateWithPHPScripts($testsToRun,$tests,$v,'testIncludeOptions',$projectRoot);
                \IOFrame\Util\CLI\FileInclusionFunctions::populateWithPHPScripts($beforeToRun,$requireBefore??[],$v,'requireBeforeIncludeOptions',$projectRoot);
                \IOFrame\Util\CLI\FileInclusionFunctions::populateWithPHPScripts($afterToRun,$requireAfter??[],$v,'requireAfterIncludeOptions',$projectRoot);

                if(empty($testsToRun)){
                    $errors['no-valid-tests'] = true;
                    return $results;
                }

                foreach ($beforeToRun as $url){
                    try {
                        require_once $url;
                    }
                    catch (\Exception $e){
                        $errors['failed-run-before'] = $errors['failed-run-before'] ?? [];
                        $errors['failed-run-before'][IOFrame\Util\CLI\FileInclusionFunctions::url2Id($url,$projectRoot)] = $e->getMessage();
                        return $results;
                    }
                }

                foreach ($testsToRun as $url){
                    $id = \IOFrame\Util\CLI\FileInclusionFunctions::url2Id($url,$projectRoot);
                    try {
                        $results[$id] = false;

                        if(!empty($_test))
                            unset($_test);

                        require_once $url;

                        if(empty($_test) || !is_callable($_test)) {
                            $target = empty($_test)?'undefined':'invalid';
                            $errors['failed-test-no-func'] = $errors['failed-test-no-func'] ?? [];
                            $errors['failed-test-no-func'][$id] = $target;
                            continue;
                        }
                        $testErrors = [];
                        $results[$id] = $_test($inputs,$testErrors,$context,$params);
                        if(count($testErrors)){
                            $errors['tests-failed'] = $errors['tests-failed'] ?? [];
                            $errors['tests-failed'][$id] = $testErrors;
                        }
                    }
                    catch (\Exception $e){
                        $errors['tests-failed-exception'] = $errors['tests-failed-exception'] ?? [];
                        $errors['tests-failed-exception'][$id] = $e->getMessage();
                        return $results;
                    }
                }

                foreach ($afterToRun as $url){
                    try {
                        require_once $url;
                    }
                    catch (\Exception $e){
                        $errors['failed-run-after'] = $errors['failed-run-after'] ?? [];
                        $errors['failed-run-after'][IOFrame\Util\CLI\FileInclusionFunctions::url2Id($url,$projectRoot)] = $e->getMessage();
                        return $results;
                    }
                }

                return $results;
            }
        ],
        'duplicateTables'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                [
                    'Allows you to duplicate table structure / data (see --dt).',
                    'You may specify a different SQL server / db using --db-settings, but cant move data between servers.',
                    'Using --do options, you may also decide whether / which data to copy from those tables.',
                    'Default DB prefixes are applied, but can be disabled in --do.',
                    'Per-table prefixes are not supported, however.',
                    'You may also specify source / target dbs in --db, however, you must remember that:',
                    'a) The DBs still need to be set on the same server and accessible with the same user.',
                    'b) This operation does not duplicate constraints, so remember to bring any dependencies as well.',
                ],
                [
                    'This should be used for testing, not populating a production database.',
                    'For the example, if your default prefix is "EXAMPLE_", change the file prefix to something else',
                ],
                [
                    'php testing.php -t -v -a dt --fp cli/config/examples/testing/copy-article-related-tables-and-data.json',
                ]
            ),
            'required'=>['duplicateTables'],
            'func'=>function($inputs,&$errors,$context,$params){
                $test = $params['test'];
                $verbose = $params['verbose'] ?? $test;
                $options = $context->variables['duplicationOptions'];
                $tables = $context->variables['duplicateTables'];
                $defaults = $params['defaultParams'];
                if(isset($params['AltDBManager']))
                    $defaults['SQLManager'] = $params['AltDBManager'];
                $defaultPrefix = $defaults['SQLManager']->getSQLPrefix();
                $res = [];

                foreach ($tables as $source => $target){
                    $source = strtoupper($source);
                    $target = strtoupper($target);
                    $res[$source] = true;
                    $fullSourceName =
                        ($options['sourceDB']? $options['sourceDB'].'.':'').
                        ($options['sourcePrefix']===null? $defaultPrefix : ($options['sourcePrefix']) ).
                        $source;
                    $fullTargetName =
                        ($options['targetDB']? $options['targetDB'].'.':'').
                        ($options['targetPrefix']===null? $defaultPrefix : ($options['targetPrefix']) ).
                        $target;

                    if($options['createTable']){
                        $query = 'CREATE TABLE IF NOT EXISTS '.$fullTargetName.' LIKE '.$fullSourceName;

                        try {
                            if($verbose)
                                echo 'Query: '.$query.';'.EOL;
                            $queryRes = $test || $defaults['SQLManager']->exeQueryBindParam($query,[],[]);
                            if(!$queryRes){
                                $errors['failed-to-copy-structure-from'] = $errors['failed-to-copy-structure-from'] ?? [];
                                $errors['failed-to-copy-structure-from'][$source] = false;
                                $res[$source] = false;
                            }
                        }
                        catch (\Exception $e){
                            $errors['failed-to-copy-structure-from'] = $errors['failed-to-copy-structure-from'] ?? [];
                            $errors['failed-to-copy-structure-from'][$source] = $e->getMessage();
                            $res[$source] = false;
                        }
                    }

                    if($options['includeData']){

                        $query = 'INSERT INTO '.$fullTargetName.' SELECT * FROM '.$fullSourceName;
                        if(!empty($options['includeDataWhere'][$source])){
                            $query .= ' WHERE '.$options['includeDataWhere'][$source];
                        }
                        try {
                            if($verbose)
                                echo 'Query: '.$query.';'.EOL;
                            $queryRes = $test || $defaults['SQLManager']->exeQueryBindParam($query,[],[]);
                            if(!$queryRes){
                                $errors['failed-to-copy-data-from'] = $errors['failed-to-copy-data-from'] ?? [];
                                $errors['failed-to-copy-data-from'][$source] = false;
                                $res[$source] = false;
                            }
                        }
                        catch (\Exception $e){
                            $errors['failed-to-copy-data-from'] = $errors['failed-to-copy-data-from'] ?? [];
                            $errors['failed-to-copy-data-from'][$source] = $e->getMessage();
                            $res[$source] = false;
                        }

                    }

                }

                return $res;
            }
        ],
        'seedDB'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                [
                    'Seeds the DB (by default using sqlSettings, different settings can be loaded with --db-settings)',
                    'The data itself is loaded from --db-data (see relevant variable)',
                    'Default DB prefixes are applied, but can be disabled in --dbo.',
                ],
                [
                    'This is a one-way operation, which may not always be reversible.',
                    'It is highly unrecommended to run this outside of a testing environment',
                ],
                [
                    'php testing.php -t -v -a seed --fp cli/config/examples/testing/seed-resource-collections-table-from-articles-example.json',
                    'php testing.php -t -v -a seed --fp cli/config/examples/testing/seed-resources-table-from-articles-example.json',
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                $test = $params['test'];
                $verbose = $params['verbose'] ?? $test;
                $data = $context->variables['dbData'];
                $options = $context->variables['dbOptions'];
                $defaults = $params['defaultParams'];
                if(isset($params['AltDBManager']))
                    $defaults['SQLManager'] = $params['AltDBManager'];
                $defaultPrefix = $defaults['SQLManager']->getSQLPrefix();
                $res = [];

                $dataType = array_keys($data) !== array_keys(array_keys($data)) ? 'universal' : 'phpmyadmin';

                if($dataType === 'phpmyadmin'){
                    $extractedData = [];
                    foreach ($data as $dataNode){
                        if(empty($dataNode['type']) || ($dataNode['type'] !== 'table'))
                            continue;
                        $tableName = $dataNode['name'];
                        $extractedData[$tableName] = [];
                        if(!empty($dataNode['data']))
                            foreach ($dataNode['data'] as $row){
                                $extractedData[$tableName][] = $row;
                            }
                    }
                    $data = $extractedData;
                }

                foreach ($data as $table => $rows){
                    $table = strtoupper($table);
                    $res[$table] = 'empty-rows';
                    if(empty($rows))
                        continue;

                    $fullTableName =
                        ($options['targetDB']? $options['targetDB'].'.':'').
                        ($options['targetPrefix']===null? $defaultPrefix : ($options['targetPrefix']) ).
                        $table;

                    $columns = [];

                    foreach ($rows[0] as $col => $v)
                        $columns[] = $col;

                    $insertValues = [];
                    foreach ($rows as $row){
                        $toInsert = [];
                        foreach ($row as $value){
                            $toInsert[] = [$value, 'STRING'];
                        }
                        $insertValues[] = $toInsert;
                    }

                    $queryResult = $defaults['SQLManager']->insertIntoTable (
                        $fullTableName,
                        $columns,
                        $insertValues,
                        array_merge($params,['returnError'=>true,'onDuplicateKey'=>true])
                    );
                    if(!$queryResult){
                        $errors['failed-to-seed-table'] = $errors['failed-to-seed-table'] ?? [];
                        $errors['failed-to-seed-table'][$table] = $queryResult;
                    }
                    $res[$table] = $queryResult;
                }



                return $res;
            }
        ],
        'cleanDB'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                [
                    'Cleans the DB, usually from seedDB data (--db-settings works like with seedDB).',
                    'Default DB prefixes are applied, but can be disabled in --dbo.',
                    'The identifiers / columns used for removal from each table, are loaded from --db-data.',
                ],
                [
                    'This is a one-way operation, which may not always be reversible.',
                    'It is highly unrecommended to run this outside of a testing environment',
                ],
                [
                    'php testing.php -t -v -a clean --fp cli/config/examples/testing/clean-some-data-from-example-tables.json',
                    'php testing.php -t -v -a clean --fp cli/config/examples/testing/clean-up-example-tables.json',
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                $test = $params['test'];
                $verbose = $params['verbose'] ?? $test;
                $data = $context->variables['dbData'];
                $options = $context->variables['dbOptions'];
                $defaults = $params['defaultParams'];
                if(isset($params['AltDBManager']))
                    $defaults['SQLManager'] = $params['AltDBManager'];
                $defaultPrefix = $defaults['SQLManager']->getSQLPrefix();
                $res = [];

                foreach ($data as $table => $meta){
                    $table = strtoupper($table);

                    $fullTableName =
                        ($options['targetDB']? $options['targetDB'].'.':'').
                        ($options['targetPrefix']===null? $defaultPrefix : ($options['targetPrefix']) ).
                        $table;

                    if(!$meta['drop']){
                        $queryResult = $defaults['SQLManager']->deleteFromTable (
                            $fullTableName,
                            $meta['cond']??[],
                            array_merge($params,['returnError'=>true,'onDuplicateKey'=>true])
                        );
                    }
                    else{
                        $query = 'DROP TABLE '.$fullTableName;

                        try {
                            if($verbose)
                                echo 'Query: '.$query.';'.EOL;
                            $queryResult = $test || $defaults['SQLManager']->exeQueryBindParam($query,[],[]);
                            if(!$queryResult){
                                $errors['failed-to-drop-table'] = $errors['failed-to-seed-table'] ?? [];
                                $errors['failed-to-drop-table'][$table] = false;
                            }
                        }
                        catch (\Exception $e){
                            $errors['failed-to-drop-table'] = $errors['failed-to-seed-table'] ?? [];
                            $errors['failed-to-drop-table'][$table] = $e->getMessage();
                        }
                    }
                    $res[$table] = $queryResult ?? false;
                }



                return $res;
            }
        ],
    ],
    'helpDesc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
        [
            'This is the default IOFrames testing CLI.',
            'This interface is meant to be used for testing purposes.',
            'It can run automated tests (default location located: test/automatic/), as well as seed the DB with test values.',
            '* Note that the DB functionality requires both DB and cache being available.'
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

$params = ['inputs'=>true,'defaultParams'=>$defaultSettingsParams,'test'=>$v['_test'],'verbose'=>$v['_verbose']];

require 'commons/add_alt_db_to_params.php';

$check = $CLIManager->matchAction(
    null,
    $params
);

die(json_encode($check,JSON_PRETTY_PRINT));
