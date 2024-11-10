<?php
/* To install silently, run the command with flag -f and place the following file at /localFiles:
 * File name: installOptionFile.json
 * Contents are of the following form, where optional options are marked with *:
    {
        "expectedProxy"*: <  >,
        "pathToRoot"*: < local setting pathToRoot, e.g: "/IOFrame/" or "/Framework/Test Folder/" >,
        "dieOnPluginMismatch"*: <local setting dieOnPluginMismatch, e.g: true>,
        "redis"*:{
            "redis_addr": < As in redisSettings, eg: "127.0.0.1" >,
            "redis_port"*: < As in redisSettings, eg: 6379 >,
            "redis_password"*: < As in redisSettings, eg: "password" >,
            "redis_timeout"*: < As in redisSettings, eg: 60 >,
            "redis_default_persistent"*: < As in redisSettings, eg: false >
        },
        "sql":{
            "sql_server_addr": < As in sqlSettings, eg: "127.0.0.1" >,
            "sql_server_port": < As in sqlSettings, eg: 3306 >,
            "sql_username": < As in sqlSettings, eg: "username" >,
            "sql_password": < As in sqlSettings, eg: "password" >,
            "sql_persistent": < As in sqlSettings, eg: "1" >,
            "sql_db_name": < As in sqlSettings, eg: "databaseName" >,
            "sql_table_prefix": < As in sqlSettings, eg: "ABC" >,
        }
    }
    NOTE: If an option is optional but a sub option is required, it is required as long as the parent option is present.
 * */

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../main/definitions.php';
require 'commons/ensure_cli.php';
$baseUrl = \IOFrame\Util\FrameworkUtilFunctions::getBaseUrl();

//Unlike regular initiation, we can't use the core_init
$settings = new \IOFrame\Handlers\SettingsHandler($baseUrl.'/localFiles/localSettings/');

$defaultSettingsParams = [
    'opMode'=>\IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL,
    'useCache'=>false,
    'localSettings'=>$settings,
    'absPathToRoot'=>$baseUrl
];

$initiationDefinitions = [
    '_action'=>[
        'desc'=>'Just returns "install"',
        'cliFlag'=>['-a','--action'],
        'envArg'=>['IOFRAME_CLI_INSTALL_ACTION']
    ],
    'fullInstall'=>[
        'desc'=>'Full framework install',
        'cliFlag'=>['--fi','--full-install'],
        'envArg'=>['IOFRAME_CLI_INSTALL_FULL_INSTALL'],
        'hasInput'=>false
    ],
    'localSettings'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            'The structure of this variable is the same as in /localFiles/localSettings/settings',
            [
                'pathToRoot'=>[
                    'type'=>'string',
                    'default'=>'"/"',
                    'required'=>false,
                    'desc'=>'As in localSettings'
                ],
                'dieOnPluginMismatch'=>[
                    'type'=>'bool',
                    'default'=>1,
                    'desc'=>'As in localSettings'
                ],
                'expectedProxy'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in localSettings'
                ],
                'nodeID'=>[
                    'type'=>'string',
                    'default'=>'<auto generated>',
                    'required'=>false,
                    'desc'=>'As in localSettings'
                ],
                'highScalability'=>[
                    'type'=>'string',
                    'default'=>0,
                    'required'=>false,
                    'desc'=>'As in localSettings'
                ],
            ],
            [
                'Those settings do not include "absPathToRoot", which is initiated automatically.'
            ]
        ),
        'cliFlag'=>['--local','--local-settings'],
        'envArg'=>['IOFRAME_CLI_INSTALL_REDIS_SETTINGS'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'localSettings',[
                'pathToRoot'=>'/',
                'dieOnPluginMismatch'=>1,
                'expectedProxy'=>'',
                'nodeID'=>'',
                'highScalability'=>0
            ]);
        }
    ],
    'redisSettings'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            'The structure of this variable is the same as in /localFiles/redisSettings/settings',
            [
                'redis_addr'=>[
                    'type'=>'string',
                    'required'=>true,
                    'desc'=>'As in redisSettings'
                ],
                'redis_port'=>[
                    'type'=>'int',
                    'default'=>6379,
                    'required'=>false,
                    'desc'=>'As in redisSettings'
                ],
                'redis_prefix'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in redisSettings'
                ],
                'redis_password'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in redisSettings'
                ],
                'redis_timeout'=>[
                    'type'=>'string',
                    'default'=>null,
                    'required'=>false,
                    'desc'=>'As in redisSettings'
                ],
                'redis_default_persistent'=>[
                    'type'=>'bool',
                    'default'=>1,
                    'required'=>false,
                    'desc'=>'As in redisSettings'
                ],
            ],
            [
                'Required values can be initially omitted, but will require interactive user input',
                'Dynamic connection pool support will be added later'
            ]
        ),
        'cliFlag'=>['--redis','--redis-settings'],
        'envArg'=>['IOFRAME_CLI_INSTALL_REDIS_SETTINGS'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'redisSettings',[
                'redis_addr'=>"",
                'redis_port'=>6379,
                'redis_prefix'=>"",
                'redis_password'=>"",
                'redis_timeout'=>null,
                'redis_default_persistent'=>1
            ]);
        }
    ],
    'sqlSettings'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            'The structure of this variable is the same as in /localFiles/sqlSettings/settings',
            [
                'sql_server_addr'=>[
                    'type'=>'string',
                    'required'=>true,
                    'desc'=>'As in sqlSettings'
                ],
                'sql_username'=>[
                    'type'=>'string',
                    'required'=>true,
                    'desc'=>'As in sqlSettings'
                ],
                'sql_password'=>[
                    'type'=>'string',
                    'required'=>true,
                    'desc'=>'As in sqlSettings'
                ],
                'sql_persistent'=>[
                    'type'=>'string',
                    'required'=>false,
                    'default'=>1,
                    'desc'=>'As in sqlSettings'
                ],
                'sql_db_name'=>[
                    'type'=>'string',
                    'required'=>true,
                    'desc'=>'As in sqlSettings'
                ],
                'sql_server_port'=>[
                    'type'=>'int',
                    'default'=>3306,
                    'required'=>false,
                    'desc'=>'As in sqlSettings'
                ],
                'sql_table_prefix'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in sqlSettings'
                ]
            ],
            [
                'Required values can be initially omitted, but will require interactive user input',
                'Dynamic connection pool support will be added later'
            ]
        ),
        'cliFlag'=>['--sql','--sql-settings'],
        'envArg'=>['IOFRAME_CLI_INSTALL_SQL_SETTINGS'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'sqlSettings',[
                'sql_server_addr'=>"",
                'sql_username'=>"",
                'sql_password'=>"",
                'sql_db_name'=>"",
                'sql_persistent'=>1,
                'sql_server_port'=>6379,
                'sql_table_prefix'=>"",
            ]);
        }
    ],
    'siteSettings'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            '[Full Install] The structure of this variable is the same as in /localFiles/siteSettings/settings',
            [
                'siteName'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in siteSettings'
                ],
                'privateKey'=>[
                    'type'=>'string',
                    'default'=>'<auto generalted>',
                    'required'=>false,
                    'desc'=>'As in siteSettings'
                ],
                'captcha_site_key'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in siteSettings'
                ],
                'captcha_secret_key'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in siteSettings'
                ],
                'allowTesting'=>[
                    'type'=>'bool',
                    'default'=>0,
                    'required'=>false,
                    'desc'=>'As in siteSettings'
                ],
                'sslOn'=>[
                    'type'=>'bool',
                    'default'=>1,
                    'required'=>false,
                    'desc'=>'As in siteSettings'
                ],
                'dontSecureCookies'=>[
                    'type'=>'bool',
                    'default'=>0,
                    'required'=>false,
                    'desc'=>'As in siteSettings'
                ],
                'enableStickyCookie'=>[
                    'type'=>'bool',
                    'default'=>0,
                    'required'=>false,
                    'desc'=>'As in siteSettings'
                ],
                'stickyCookieDuration'=>[
                    'type'=>'int',
                    'default'=>28800,
                    'required'=>false,
                    'desc'=>'As in siteSettings'
                ],
            ],
            [
                'Used during full install. See actual installation documentation for details, or run in interactive mode to see short explanation.'
            ]
        ),
        'cliFlag'=>['--site','--site-settings'],
        'envArg'=>['IOFRAME_CLI_INSTALL_SITE_SETTINGS'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'siteSettings',[
                'siteName'=>"",
                'privateKey'=>"",
                'captcha_site_key'=>"",
                'captcha_secret_key'=>"",
                'sslOn'=>1,
                'dontSecureCookies'=>0,
                'enableStickyCookie'=>0,
                'stickyCookieDuration'=>28800,
            ]);
        }
    ],
    'userSettings'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            '[Full Install] The structure of this variable is the same as in /localFiles/userSettings/settings',
            [
                'rememberMe'=>[
                    'type'=>'bool',
                    'default'=>1,
                    'required'=>false,
                    'desc'=>'As in userSettings'
                ],
                'rememberMeLimit'=>[
                    'type'=>'int',
                    'default'=>31536000,
                    'required'=>false,
                    'desc'=>'As in userSettings'
                ],
                'relogWithCookies'=>[
                    'type'=>'bool',
                    'default'=>1,
                    'required'=>false,
                    'desc'=>'As in userSettings'
                ],
                'userTokenExpiresIn'=>[
                    'type'=>'bool',
                    'default'=>0,
                    'required'=>false,
                    'desc'=>'As in userSettings'
                ],
                'passwordResetTime'=>[
                    'type'=>'int',
                    'default'=>5,
                    'required'=>false,
                    'desc'=>'As in userSettings'
                ],
                'selfReg'=>[
                    'type'=>'bool',
                    'default'=>1,
                    'required'=>false,
                    'desc'=>'As in userSettings'
                ],
                'usernameChoice'=>[
                    'type'=>'int',
                    'default'=>0,
                    'required'=>false,
                    'desc'=>'As in userSettings'
                ],
                'regConfirmMail'=>[
                    'type'=>'bool',
                    'default'=>1,
                    'required'=>false,
                    'desc'=>'As in userSettings'
                ],
                'pwdResetExpires'=>[
                    'type'=>'int',
                    'default'=>72,
                    'required'=>false,
                    'desc'=>'As in userSettings'
                ],
                'mailConfirmExpires'=>[
                    'type'=>'int',
                    'default'=>72,
                    'required'=>false,
                    'desc'=>'As in userSettings'
                ],
            ],
            [
                'Used during full install. See actual installation documentation for details, or run in interactive mode to see short explanation.'
            ]
        ),
        'cliFlag'=>['--user','--user-settings'],
        'envArg'=>['IOFRAME_CLI_INSTALL_USER_SETTINGS'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'userSettings',[
                'rememberMe'=>1,
                'rememberMeLimit'=>31536000,
                'relogWithCookies'=>1,
                'userTokenExpiresIn'=>0,
                'passwordResetTime'=>5,
                'selfReg'=>1,
                'usernameChoice'=>0,
                'regConfirmMail'=>1,
                'pwdResetExpires'=>72,
                'mailConfirmExpires'=>72,
            ]);
        }
    ],
    'apiSettings'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            '[Full Install] The structure of this variable is the same as in /localFiles/apiSettings/settings',
            [
                'restrictedArticleByAddress'=>[
                    'type'=>'bool',
                    'default'=>1,
                    'required'=>false,
                    'desc'=>'As in apiSettings'
                ]
            ],
            [
                'Used during full install. See actual installation documentation for details, or run in interactive mode to see short explanation.'
            ]
        ),
        'cliFlag'=>['--api','--api-settings'],
        'envArg'=>['IOFRAME_CLI_INSTALL_API_SETTINGS'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'apiSettings',[
                'restrictedArticleByAddress'=>1
            ]);
        }
    ],
    'logSettings'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            '[Full Install] The structure of this variable is the same as in /localFiles/logSettings/settings',
            [
                'logs_sql_table_prefix'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in logSettings'
                ],
                'logs_sql_server_addr'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in logSettings'
                ],
                'logs_sql_server_port'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in logSettings'
                ],
                'logs_sql_username'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in logSettings'
                ],
                'logs_sql_password'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in logSettings'
                ],
                'logs_sql_persistent'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in logSettings'
                ],
                'logs_sql_db_name'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in logSettings'
                ],
            ],
            [
                'Used during full install. See actual installation documentation for details, or run in interactive mode to see short explanation.'
            ]
        ),
        'cliFlag'=>['--log','--log-settings'],
        'envArg'=>['IOFRAME_CLI_INSTALL_LOG_SETTINGS'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'logSettings',[
                'logs_sql_table_prefix'=>'',
                'logs_sql_server_addr'=>'',
                'logs_sql_server_port'=>'',
                'logs_sql_username'=>'',
                'logs_sql_password'=>'',
                'logs_sql_persistent'=>'',
                'logs_sql_db_name'=>''
            ]);
        }
    ],
    'mailSettings'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            '[Full Install] The structure of this variable is the same as in /localFiles/mailSettings/settings',
            [
                'mailHost'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in mailSettings'
                ],
                'mailEncryption'=>[
                    'type'=>'string',
                    'default'=>'ssl',
                    'required'=>false,
                    'desc'=>'As in mailSettings'
                ],
                'mailUsername'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in mailSettings'
                ],
                'mailPassword'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in mailSettings'
                ],
                'mailPort'=>[
                    'type'=>'string',
                    'default'=>465,
                    'required'=>false,
                    'desc'=>'As in mailSettings'
                ],
                'defaultAlias'=>[
                    'type'=>'string',
                    'default'=>'""',
                    'required'=>false,
                    'desc'=>'As in mailSettings'
                ],
            ],
            [
                'Used during full install. See actual installation documentation for details, or run in interactive mode to see short explanation.'
            ]
        ),
        'cliFlag'=>['--mail','--mail-settings'],
        'envArg'=>['IOFRAME_CLI_INSTALL_MAIL_SETTINGS'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'mailSettings',[
                'mailHost'=>'',
                'mailEncryption'=>'ssl',
                'mailUsername'=>'',
                'mailPassword'=>'',
                'mailPort'=>465,
                'defaultAlias'=>''
            ]);
        }
    ],
    'superAdminCreation'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            [
                '[Full Install] Used to create the initial user (super admin)'
            ],
            [
                'u'=>[
                    'type'=>'string',
                    'required'=>true,
                    'desc'=>'Username'
                ],
                'p'=>[
                    'type'=>'bool',
                    'required'=>true,
                    'desc'=>'Password'
                ],
                'm'=>[
                    'type'=>'bool',
                    'required'=>true,
                    'desc'=>'Email'
                ]
            ],
            [
                'Used during full install. See actual installation documentation for details, or run in interactive mode to see short explanation.'
            ]
        ),
        'cliFlag'=>['--sac','--super-admin-creation'],
        'envArg'=>['IOFRAME_CLI_INSTALL_SUPER_ADMIN_CREATION'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'superAdminCreation',[
                'u'=>'',
                'p'=>'',
                'm'=>'',
            ]);
        }
    ],
    'installOptions'=>[
        'desc'=> \IOFrame\Util\CLI\CommonManagerHelperFunctions::generateOptionsDesc(
            [
                'Certain options that may be passed to modify initial local files, or (dis)allow interactive install.',
                'For full install, see separate option fullInstall (--fi)',
            ],
            [
                'allowInteractive'=>[
                    'type'=>'bool',
                    'default'=>true,
                    'desc'=>'Allows interactive install. If set to false, will fail if any required settings/options are missing.'.EOL.
                        'When running in interactive mode, some messages will disregard "verbose"/"silent" params.'
                ],
                'ignoreOptionalDefaults'=>[
                    'type'=>'bool',
                    'default'=>false,
                    'desc'=>'Allows ignoring all optional defaults during interactive modes.'
                ],
                'deleteMetaFiles'=>[
                    'type'=>'bool',
                    'default'=>true,
                    'desc'=>'Deletes files like INSTALL.md, LICENSE.md, and others, from the root directory'
                ],
                'deleteTestPlugins'=>[
                    'type'=>'bool',
                    'default'=>true,
                    'desc'=>'Deletes the test plugins'
                ],
                'deleteTestFiles'=>[
                    'type'=>'bool',
                    'default'=>true,
                    'desc'=>'Deletes test.php and apiTest.php from the root folder'
                ],
                'hostUrl'=>[
                    'type'=>'string',
                    'default'=>false,
                    'desc'=>'Host url (e.g. "https://www.example.com"). Require in case of full install. Used for initiating default mail templates.'
                ],
            ]
        ),
        'cliFlag'=>['--io','--install-options'],
        'envArg'=>['IOFRAME_CLI_INSTALL_INSTALL_OPTIONS'],
        'hasInput'=>true,
        'recalculated'=>true,
        'func'=>function($options,$context,$params){
            return \IOFrame\Util\CLI\CommonManagerHelperFunctions::genericOptionsLoadingFunctionHelper($options,$context,'installOptions',[
                'allowInteractive'=>1,
                'deleteMetaFiles'=>1,
                'deleteTestPlugins'=>1,
                'deleteTestFiles'=>1,
                'ignoreOptionalDefaults'=>0,
                'hostUrl'=>''
            ]);
        }
    ],
    '_silent'=>[
        'desc'=>'Silent mode',
        'reserved'=>true,
        'cliFlag'=>['-s','--silent'],
        'envArg'=>['IOFRAME_CLI_INSTALL_SILENT'],
    ],
    '_test'=>[
        'desc'=>'Test mode',
        'reserved'=>true,
        'cliFlag'=>['-t','--test'],
        'envArg'=>['IOFRAME_CLI_INSTALL_TEST'],
    ],
    '_verbose'=>[
        'desc'=>'Verbose output',
        'reserved'=>true,
        'cliFlag'=>['-v','--verbose'],
        'envArg'=>['IOFRAME_CLI_INSTALL_VERBOSE'],
    ],
];

$initiationParams = [
    'generateFileRelated'=>true,
    'dieOnFailure'=>false,
    'silent'=>true,
    'action'=>'_action',
    'absPathToRoot'=>$baseUrl,
    'actions'=>[
        'install'=>[
            'desc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateDescNotesExamples(
                [
                    'Installs IOFrame in DB mode (secondary node, or full install with --fi)'
                ],
                [],
                [
                    'php install.php -t -v -a install',
                    '* php install.php -t -v -a install --fp cli/config/examples/install/secondary-install.json',
                    '** php install.php -t -v -a install --fp cli/config/examples/install/full-install.json',
                    'php install.php -t -v -a install --fp /dev/some/mounted/shared/config/folder/default-install-config.json --fpt abs',
                    '* For the above example config, clone repo into "IOFrame_CLI_Test", install main system with a db named "ioframe_test", sql prefix "T_" and redis prefix "test_"',
                    '** For the above example config, clone repo into "IOFrame_CLI_Test", create a db named "ioframe_cli_test", and a logs db "ioframe_cli_test_logs"',
                    '*** For all examples, the SQL port used is 3307 (not the default 3306)',
                ]
            ),
            'func'=>function($inputs,&$errors,$context,$params){
                $defaults = $params['defaultParams'];
                $v = $context->variables;

                $fullInstall = $v['fullInstall'] ?? false;

                if($params['verbose']){
                    echo EOL.'---------Install IOFrame in CLI mode!--------'.EOL.
                        'This will install this instance as a '.($fullInstall?'main node.':'DB reliant (secondary) node.').EOL.
                        ($fullInstall ?
                            'This node will be reliant on the same DB / Redis the other (already installed) nodes are using.'.EOL:
                            'This will initiate all relevant DB values, and potentially overwrite existing data and settings.'.EOL);
                }

                if($v['installOptions']['allowInteractive'])
                    $handle = fopen ("php://stdin","r");
                else
                    $handle = null;

                //TODO Validation / parsing
                $defaultsMap = [
                    'localSettings'=>[
                        'pathToRoot'=>[
                            'default'=>'/',
                            'required'=>$fullInstall,
                            'interactiveMsg'=>'Enter path to project from Apache server root, or press Enter to skip',
                        ],
                        'dieOnPluginMismatch'=>[
                            'default'=>1,
                            'required'=>false,
                            'interactiveMsg'=>'If you do NOT want the node to fail on plugin mismatch, enter 0, or press Enter to skip',
                        ],
                        'expectedProxy'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Enter expected proxy address(es) between the node and the web - For example,'.EOL.
                                'if your load balancer IP is 10.10.11.11, and it itself is behind another balancer'.EOL.
                                'at IP 210.20.1.10, type "210.20.1.10,10.10.11.11" without quotes.'.EOL.
                                'Otherwise, press Enter to skip',
                        ],
                        'nodeID'=>[
                            'default'=>\IOFrame\Util\PureUtilFunctions::GeraHash(8),
                            'required'=>false,
                            'interactiveMsg'=>'Enter a unique node ID, or press enter to auto-generate one.',
                        ],
                        'highScalability'=>[
                            'default'=>0,
                            'required'=>false,
                            'interactiveMsg'=>'If you are installing the system in high scalability mode, enter 1, or press Enter to skip',
                        ],
                    ],
                    'redisSettings'=>[
                        'redis_addr'=>[
                            'default'=>'',
                            'required'=>true,
                            'interactiveMsg'=>'Enter Redis address - E.g. 127.0.0.1',
                        ],
                        'redis_port'=>[
                            'default'=>6379,
                            'required'=>false,
                            'interactiveMsg'=>'Enter Redis port, or press Enter to skip',
                        ],
                        'redis_prefix'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Enter Redis prefix, or press Enter to skip',
                        ],
                        'redis_password'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Enter Redis password, or press Enter to skip',
                        ],
                        'redis_timeout'=>[
                            'default'=>null,
                            'required'=>false,
                            'interactiveMsg'=>'Enter Redis timeout (in seconds), or press Enter to skip',
                        ],
                        'redis_default_persistent'=>[
                            'default'=>1,
                            'required'=>false,
                            'interactiveMsg'=>'If you want Redis to NOT use persistent connections, enter 0, or press Enter to skip',
                        ],
                    ],
                    'sqlSettings'=>[
                        'sql_server_addr'=>[
                            'default'=>'',
                            'required'=>true,
                            'interactiveMsg'=>'Enter SQL server address - E.g 127.0.0.1',
                        ],
                        'sql_username'=>[
                            'default'=>'',
                            'required'=>true,
                            'interactiveMsg'=>'Enter SQL username - E.g. admin',
                        ],
                        'sql_password'=>[
                            'default'=>'',
                            'required'=>true,
                            'interactiveMsg'=>'Enter SQL password - E.g. hunter2',
                        ],
                        'sql_persistent'=>[
                            'required'=>false,
                            'default'=>1,
                            'interactiveMsg'=>'Enter SQL persistent connection value (1 or 0), or press Enter to skip',
                        ],
                        'sql_db_name'=>[
                            'default'=>'',
                            'required'=>true,
                            'interactiveMsg'=>'Enter SQL db name - E.g. ioframe',
                        ],
                        'sql_server_port'=>[
                            'default'=>3306,
                            'required'=>false,
                            'interactiveMsg'=>'Enter SQL port, or press Enter to skip',
                        ],
                        'sql_table_prefix'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Enter SQL table prefix, or press Enter to skip',
                        ]
                    ],
                    'installOptions'=>[
                        'deleteMetaFiles'=>[
                            'default'=>1,
                            'required'=>false,
                            'interactiveMsg'=>'If you do NOT want to delete files like INSTALL.md, LICENSE.md, and others, from the root directory, enter 0, or press Enter to skip',
                        ],
                        'deleteTestPlugins'=>[
                            'default'=>1,
                            'required'=>false,
                            'interactiveMsg'=>'If you do NOT want to delete test plugins, enter 0, or press Enter to skip',
                        ],
                        'deleteTestFiles'=>[
                            'default'=>1,
                            'required'=>false,
                            'interactiveMsg'=>'If you do NOT want to delete test.php and apiTest.php from the root folder, enter 0, or press Enter to skip',
                        ],
                        'hostUrl'=>[
                            'default'=>'',
                            'required'=>$fullInstall,
                            'interactiveMsg'=>'Enter host url (e.g. "https://www.example.com"). Require in case of full install. Used for initiating default mail templates.',
                        ],
                    ],
                ];

                $fullInstallDefaultsMap = [
                    'siteSettings'=>[
                        'siteName'=>[
                            'default'=>'My Website',
                            'required'=>false,
                            'interactiveMsg'=>'Enter a name for your website/system, or press Enter to set a default name "My Website".'.EOL.
                                'This can be changed later, but some things (like mail templates) would have to be edited manually',
                        ],
                        'privateKey'=>[
                            'default'=>bin2hex(openssl_random_pseudo_bytes(32)),
                            'required'=>false,
                            'interactiveMsg'=>'Private key to be used for all default encryption. Press Enter to skip, in which case encryption wont be supported.'.EOL.
                                'MUST BE 64 digits long, numbers or letters a-f. Don\'t change it if you do not know what this is'.EOL.
                                'It is PARAMOUNT you write this down in a secure place. If you do not, you risk losing ALL your encrypted data in the future.',
                        ],
                        'captcha_site_key'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'If you are using hCaptcha, fill the SITE key here, or press Enter to skip',
                        ],
                        'captcha_secret_key'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'If you are using hCaptcha, fill the SECRET key here, or press Enter to skip',
                        ],
                        'allowTesting'=>[
                            'default'=>0,
                            'required'=>false,
                            'interactiveMsg'=>'If you enter "1", testing will be allowed with no restrictions. DONT SET TRUE IN PRODUCTION (live sites)!'.EOL.
                                'Press Enter to skip (no testing allowed)',
                        ],
                        'sslOn'=>[
                            'default'=>1,
                            'required'=>false,
                            'interactiveMsg'=>'All pages on your site will be redirected to SSL by default (press Enter).'.EOL.
                                'Enter "0" to disable this behaviour (not recommended)',
                        ],
                        'dontSecureCookies'=>[
                            'default'=>0,
                            'required'=>false,
                            'interactiveMsg'=>'If set to "1", will DISABLE automatic PHP cookie setting overwrites, which would make cookies secure by default'.EOL.
                                'Can be disabled if cookie settings were set properly in the PHP ini from setup, otherwise press Enter to keep default behaviour',
                        ],
                        'enableStickyCookie'=>[
                            'default'=>0,
                            'required'=>false,
                            'interactiveMsg'=>'If set to "1", will pass a sticky session cookie to the client, based on the node ID',
                        ],
                        'stickyCookieDuration'=>[
                            'default'=>28800,
                            'required'=>false,
                            'interactiveMsg'=>'How long, in seconds, the sticky cookie should last for (default 8 hours)',
                        ],
                    ],
                    'userSettings'=>[
                        'rememberMe'=>[
                            'default'=>1,
                            'required'=>false,
                            'interactiveMsg'=>'If left as "1", users will be able to use the "Remember Me" feature',
                        ],
                        'rememberMeLimit'=>[
                            'default'=>31536000,
                            'required'=>false,
                            'interactiveMsg'=>'If Remember Login is active, this is the time limit (in seconds, default 365 days) for it to expire',
                        ],
                        'relogWithCookies'=>[
                            'default'=>1,
                            'required'=>false,
                            'interactiveMsg'=>'If left as "1", and Remember Login is active, will automatically try to relog using cookies instead of local storage & an API call',
                        ],
                        'userTokenExpiresIn'=>[
                            'default'=>0,
                            'required'=>false,
                            'interactiveMsg'=>'Number of seconds tokens generated for auto-relog are valid for.'.EOL.
                                'If left as "0", tokens never expire. While Remember Login is inactive, this has no effect.',
                        ],
                        'passwordResetTime'=>[
                            'default'=>5,
                            'required'=>false,
                            'interactiveMsg'=>'For how many minutes, after a user successfully clicked the mail link, he can reset the password.',
                        ],
                        'selfReg'=>[
                            'default'=>1,
                            'required'=>false,
                            'interactiveMsg'=>'If this is checked, allows everyone to register new accounts.'.EOL.
                                'If set to "0", users can only be invited, or created by an admin.',
                        ],
                        'usernameChoice'=>[
                            'default'=>0,
                            'required'=>false,
                            'interactiveMsg'=>'Whether the user must ("0"), may ("1"), or cannot ("2") choose a username',
                        ],
                        'regConfirmMail'=>[
                            'default'=>1,
                            'required'=>false,
                            'interactiveMsg'=>'If left as "1", user will have to confirm his mail upon registration for his account to become active.',
                        ],
                        'pwdResetExpires'=>[
                            'default'=>72,
                            'required'=>false,
                            'interactiveMsg'=>'How long until password reset email sent to a client expires, in hours.',
                        ],
                        'mailConfirmExpires'=>[
                            'default'=>72,
                            'required'=>false,
                            'interactiveMsg'=>'How long until registration confirmation email sent to a client expires, in hours.',
                        ],
                    ],
                    'apiSettings'=>[
                        'restrictedArticleByAddress'=>[
                            'default'=>1,
                            'required'=>false,
                            'interactiveMsg'=>'If left as "1", users will be able to get articles with "restricted" auth with direct links'.EOL.
                                'In effect, this makes them hidden for those without the link.',
                        ],
                    ],
                    'logSettings'=>[
                        'logs_sql_table_prefix'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Logs Table Prefix (Max 6 Characters)',
                            'parser'=>function($value,$context){
                                return substr($value,0,6);
                            }
                        ],
                        'logs_sql_server_addr'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Logs SQL server address',
                        ],
                        'logs_sql_server_port'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Logs SQL server port',
                        ],
                        'logs_sql_username'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Logs SQL server username',
                        ],
                        'logs_sql_password'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Logs SQL server password',
                        ],
                        'logs_sql_persistent'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Logs SQL persistent connection',
                        ],
                        'logs_sql_db_name'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Logs SQL server database name',
                        ],
                    ],
                    'mailSettings'=>[
                        'mailHost'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'SMTP Host (e.g. yourHostName.com)',
                        ],
                        'mailEncryption'=>[
                            'default'=>'ssl',
                            'required'=>false,
                            'interactiveMsg'=>'Encryption (see PHPMailer "SMTPSecure" parameter)',
                        ],
                        'mailUsername'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Mail Username (e.g. username@yourHostName.com)',
                        ],
                        'mailPassword'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Mail Password',
                        ],
                        'mailPort'=>[
                            'default'=>'465',
                            'required'=>false,
                            'interactiveMsg'=>'Mail Server Port (default 465, but might be different, check host settings)',
                        ],
                        'defaultAlias'=>[
                            'default'=>'',
                            'required'=>false,
                            'interactiveMsg'=>'Fill this in if you want to be sending system mails as a different alias (not your username)'.EOL.
                            'In most SMTP services (Zoho, Mailgun, etc), you\'ll need to set allowed aliases manually. Consult host documentation.',
                        ],
                    ],
                    'superAdminCreation'=>[
                        'u'=>[
                            'default'=>'',
                            'required'=>true,
                            'interactiveMsg'=>'Admin username.'.EOL.
                                'Must be 3-64 characters long, must contain letters and may contain numbers.',
                            'validator'=>function($value,$context){
                                return \IOFrame\Util\ValidatorFunctions::validateUsername($value);
                            }
                        ],
                        'p'=>[
                            'default'=>'',
                            'required'=>true,
                            'interactiveMsg'=>'Admin password.'.EOL.
                                'Must be 8-64 characters long, must include letters and numbers, can include special characters except \'>\' and \'<\'',
                            'validator'=>function($value,$context){
                                return \IOFrame\Util\ValidatorFunctions::validatePassword($value);
                            }
                        ],
                        'm'=>[
                            'default'=>'',
                            'required'=>true,
                            'interactiveMsg'=>'Admin email.',
                            'validator'=>function($value,$context){
                                return filter_var($value, FILTER_VALIDATE_EMAIL);
                            }
                        ],
                    ]
                ];

                if($fullInstall)
                    $defaultsMap = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($defaultsMap,$fullInstallDefaultsMap);

                foreach ($defaultsMap as $var => $options){

                    if(!empty($v['installOptions']['allowInteractive']))
                        echo $var.' parameters:'.EOL.
                            '----------'.EOL;

                    //This shouldn't be the case now, but there might be a reason for some defaults to be unchangeable from the initial file.
                    if(empty($context->variables[$var])){
                        $context->variables[$var] = [];
                    }

                    foreach ($options as $opt => $optObject){

                        $optObject['required'] = $optObject['required'] ?? false;
                        $optObject['validator'] = $optObject['validator'] ?? null;
                        $optObject['parser'] = $optObject['parser'] ?? null;
                        $optObject['default'] = $optObject['default'] ?? null;

                        //We need to do something if the variable is empty, or equals to its default (and optional defaults aren't ignored)
                        if(
                            (
                                (empty($context->variables[$var][$opt]) && empty($optObject['default'])) ||
                                ( $context->variables[$var][$opt]??null === ($optObject['default']) )
                            ) &&
                            !(
                                $v['installOptions']['ignoreOptionalDefaults'] &&
                                ( $context->variables[$var][$opt]??null === ($optObject['default']) )
                            )
                        ){

                            //If we allow interactive install, we: a) Allow filling required variables. b) Allow changing defaults.
                            if(!empty($v['installOptions']['allowInteractive'])){

                                $maxIncorrectInput = $optObject['maxIncorrectInput'] ?? 3;

                                echo '"'.$opt.'"'. ($optObject['required']? '*' : ' (default '.($optObject['default']?: '<empty>').')').':'.EOL.
                                    $optObject['interactiveMsg'].EOL;

                                while(true){

                                    $input = trim(fgets($handle));
                                    $handledInput = true;

                                    if( ($input !== '') || !$optObject['required'] ){

                                        if($optObject['validator'] && !$optObject['validator']($input,$context)){
                                            echo EOL.'Invalid input '.$opt.' in '.$var.', '.$maxIncorrectInput.' tries remaining'.EOL;
                                            $maxIncorrectInput--;
                                            continue;
                                        }

                                        if($optObject['parser'])
                                            $input = $optObject['parser']($input,$context);
                                        $v[$var][$opt] = $input === ''? ($optObject['default']??'') : $input;
                                    }

                                    if($handledInput || $maxIncorrectInput)
                                        break;
                                    else{
                                        $errors[$var] = $opt;
                                        return false;
                                    }
                                }
                            }
                            elseif(empty($context->variables[$var][$opt]) && !empty($optObject['required'])){
                                if($params['verbose'])
                                    echo 'Missing required '.$opt.' in '.$var.EOL;
                                $errors[$var] = $opt;
                                return false;
                            }

                        }

                    }
                }

                if(!\IOFrame\Util\Installation\UtilityFunctions::initiateLocalFiles(['verbose'=>$params['verbose'],'test'=>$params['test']])){
                    if($params['verbose'])
                        echo 'Failed to initiate local files'.EOL;
                    $errors['initiate-local'] = true;
                    return false;
                }

                //Local + Redis + SQL
                $localSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'localFiles/localSettings',$defaults);
                $sqlSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'localFiles/sqlSettings',$defaults);
                $redisSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'localFiles/redisSettings',$defaults);

                //Full install settings - all those settings will be unused in local install
                $userSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'/localFiles/userSettings/',$defaults);
                $pageSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'/localFiles/pageSettings/',$defaults);
                $mailSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'/localFiles/mailSettings/',$defaults);
                $siteSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'/localFiles/siteSettings/',$defaults);
                $resourceSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'/localFiles/resourceSettings/',$defaults);
                $metaSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'/localFiles/metaSettings/',$defaults);
                $apiSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'/localFiles/apiSettings/',$defaults);
                $tagSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'/localFiles/tagSettings/',array_merge($defaults,['base64Storage'=>true]));
                $logSettings = new \IOFrame\Handlers\SettingsHandler($defaults['absPathToRoot'].'/localFiles/logSettings/',$defaults);

                //In full install, initiate defaults first
                if($fullInstall){
                    if(
                        !\IOFrame\Util\Installation\UtilityFunctions::initiateDefaultSettings(
                            $localSettings,
                            $siteSettings,
                            $userSettings,
                            $pageSettings,
                            $resourceSettings,
                            $apiSettings,
                            $tagSettings,
                            $logSettings,
                            $metaSettings,
                            [
                                'verbose'=>$params['verbose'],
                                'test'=>$params['test'],
                            ]
                        )
                    ){
                        if($params['verbose'])
                            echo 'Failed to initiate default settings!'.EOL;
                        $errors['initiateDefaultSettings'] = false;
                        return false;
                    }
                    elseif ($params['verbose'])
                        echo 'Initiated default settings!'.EOL;
                }

                $targets = [
                    'local'=>[
                        'handler'=>$localSettings,
                        'args'=>array_merge($v['localSettings'],[
                            'absPathToRoot'=>$defaults['absPathToRoot'],
                            'opMode'=>$fullInstall ? \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_MIXED : \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_DB,
                            '_templates_default'=>'',
                            '_templates_maintenance_local'=>'',
                            '_templates_maintenance_global'=>'',
                            '_templates_plugins_mismatch'=>'',
                            '_templates_page_not_found'=>'',
                            '_templates_user_banned'=>'',
                            '_templates_ip_blacklisted'=>'',
                            '_templates_unauthorized_generic'=>'',
                        ])
                    ],
                    'redis'=>[
                        'handler'=>$redisSettings,
                        'args'=>$v['redisSettings']
                    ],
                    'sql'=>[
                        'handler'=>$sqlSettings,
                        'args'=>$v['sqlSettings']
                    ],
                ];
                $res = \IOFrame\Util\Installation\UtilityFunctions::initMultipleSettingHandlers($targets,array_merge($params,['createNew'=>true,'initIfNotExists'=>true]));

                if($res !== true){
                    if($params['verbose'])
                        echo 'Could not set '.$res.' settings!'.EOL;
                    $errors['setSettings'] = $res;
                    return false;
                }

                if($fullInstall){

                    //Sett the rest of the settings
                    $targets = [
                        'site'=>[
                            'handler'=>$siteSettings,
                            'args'=>$v['siteSettings']
                        ],
                        'user'=>[
                            'handler'=>$userSettings,
                            'args'=>$v['userSettings']
                        ],
                        'api'=>[
                            'handler'=>$apiSettings,
                            'args'=>$v['apiSettings']
                        ],
                        'log'=>[
                            'handler'=>$logSettings,
                            'args'=>$v['logSettings']
                        ],
                        'mail'=>[
                            'handler'=>$mailSettings,
                            'args'=>$v['mailSettings']
                        ],
                    ];
                    $res = \IOFrame\Util\Installation\UtilityFunctions::initMultipleSettingHandlers($targets,array_merge($params,['createNew'=>true,'initIfNotExists'=>true]));

                    if($res !== true){
                        if($params['verbose'])
                            echo 'Could not set '.$res.' settings!'.EOL;
                        $errors['setSettings'] = $res;
                        return false;
                    }

                    //Test DB Connection
                    try{
                        \IOFrame\Util\FrameworkUtilFunctions::prepareCon($sqlSettings);
                        if($params['verbose'])
                            echo 'DB Connection Established Successfully';
                    }
                    catch(\Exception $e){
                        if($params['verbose'])
                            echo 'Failed to connect to DB! Error: '.$e->getMessage().EOL;
                        $errors['dbConnection'] = $e->getCode();
                        return false;
                    }

                    //See if we need to scale logs, for both db structure and connection test
                    $scaleLogs = !empty($v['logSettings']['logs_sql_table_prefix']) ||
                        !empty($v['logSettings']['logs_sql_server_addr']) ||
                        !empty($v['logSettings']['logs_sql_server_port']) ||
                        !empty($v['logSettings']['logs_sql_username']) ||
                        !empty($v['logSettings']['logs_sql_password']) ||
                        !empty($v['logSettings']['logs_sql_persistent']) ||
                        !empty($v['logSettings']['logs_sql_db_name']);

                    if($scaleLogs){
                        $logSQLSettings = clone $sqlSettings;
                        $logSQLSettings->combineWithSettings($logSettings,[
                            'settingAliases'=>[
                                'logs_sql_table_prefix'=>'sql_table_prefix',
                                'logs_sql_server_addr'=>'sql_server_addr',
                                'logs_sql_server_port'=>'sql_server_port',
                                'logs_sql_username'=>'sql_username',
                                'logs_sql_password'=>'sql_password',
                                'logs_sql_persistent'=>'sql_persistent',
                                'logs_sql_db_name'=>'sql_db_name',
                            ],
                            'includeRegex'=>'logs_sql',
                            'ignoreEmptyStrings'=>['logs_sql_server_addr','logs_sql_server_port','logs_sql_username','logs_sql_password','logs_sql_db_name','logs_sql_persistent']
                        ]);
                        try{
                            \IOFrame\Util\FrameworkUtilFunctions::prepareCon($logSQLSettings);
                            if($params['verbose'])
                                echo 'Logs DB Connection Established Successfully';
                        }
                        catch(\Exception $e){
                            if($params['verbose'])
                                echo 'Failed to connect to Logs DB! Error: '.$e->getMessage().EOL;
                            $errors['setSettings'] = $e->getCode();
                            return false;
                        }
                    }

                    //Initiate DB Structure
                    $initSettingsBase = [
                        'verbose'=>$params['verbose'],
                        'test'=>$params['test'],
                        'defaultSettingsParams'=>$defaults,
                        'tables'=>[],
                        'hostUrl'=>$v['installOptions']['hostUrl']
                    ];

                    $initSettingsStructure = array_merge($initSettingsBase,['populate'=>false]);
                    if($scaleLogs){
                        $initSettingsStructure['tables']['logging'] = [
                            'highScalability'=>true
                        ];
                    }
                    $initStructure = \IOFrame\Util\Installation\DBInitiationFunctions::initDB($localSettings,$initSettingsStructure);
                    if($initStructure === true) {
                        if($params['verbose'])
                            echo 'Database structure initiated!'.EOL;
                    }
                    else{
                        if($params['verbose'])
                            echo 'Database structure not initiated, error in '.$initStructure.EOL;
                        $errors['database-structure'] = $initStructure;
                        return false;
                    }

                    //Initiate DB Values
                    $initSettingsPopulate =  array_merge($initSettingsBase,['init'=>false]);
                    $populateDB = \IOFrame\Util\Installation\DBInitiationFunctions::initDB($localSettings,$initSettingsPopulate);
                    if($populateDB === true) {
                        if($params['verbose'])
                            echo 'Database default values populated!'.EOL;
                    }
                    else{
                        if($params['verbose'])
                            echo 'Failed to populate some of the default DB values, error in '.$populateDB.EOL;
                        $errors['database-population'] = $populateDB;
                        return false;
                    }

                    //Sync Settings To DB
                    $RedisManager = new \IOFrame\Managers\RedisManager($redisSettings);
                    $defaults['RedisManager'] = $RedisManager;
                    $defaults['siteSettings'] = $siteSettings;
                    $defaults['resourceSettings'] = $resourceSettings;
                    if($RedisManager->isInit){
                        $defaults['useCache'] = true;
                    }
                    $SQLManager = new \IOFrame\Managers\SQLManager(
                        $localSettings,
                        $defaults
                    );
                    $defaults['SQLManager'] = $SQLManager;
                    $defaults['opMode'] = \IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_MIXED;

                    $dbSync = \IOFrame\Util\Installation\UtilityFunctions::syncSettingsToDB(
                        ['user','page','mail','site','resource','api','tag','log','meta'],
                        ['defaultSettingsParams'=>$defaults,'verbose'=>$params['verbose'],'test'=>$params['test']]
                    );

                    if(in_array(false,$dbSync)){
                        $errors['setting-sync'] = $errors['setting-sync'] ?? [];
                        foreach ($dbSync as $name => $result)
                            if(!$result){
                                $errors['setting-sync'][] = $name;
                                if($params['verbose'])
                                    echo 'Failed to sync '.$name.' settings to DB!'.EOL;
                            }
                        return false;
                    }
                    else{
                        if($params['verbose'])
                            echo 'All settings synced to database!'.EOL;
                    }

                    //Create Super Admin
                    $UsersHandler = new \IOFrame\Handlers\UsersHandler($localSettings);
                    $createSuperAdmin = \IOFrame\Util\Installation\UtilityFunctions::createSuperAdmin(
                        ['u'=>$v['superAdminCreation']['u'],'p'=>$v['superAdminCreation']['p'],'m'=>$v['superAdminCreation']['m']],
                        $UsersHandler,
                        ['verbose'=>$params['verbose'],'test'=>$params['test']]
                    );

                    if($createSuperAdmin !== 0){
                        if($params['verbose'])
                            echo 'Failed to create Super Admin, error '.$createSuperAdmin.EOL;
                        $errors['admin-creation'] = $createSuperAdmin;
                        return false;
                    }
                    else{
                        if($params['verbose'])
                            echo 'Super Admin created!'.EOL;
                    }
                }

                //Optional Deletions
                \IOFrame\Util\Installation\UtilityFunctions::deleteDevFiles(
                    $defaults['absPathToRoot'],
                    [
                        'metaFiles'=>!empty($v['installOptions']['deleteMetaFiles']),
                        'testPlugins'=>!empty($v['installOptions']['deleteTestPlugins']),
                        'testFiles'=>!empty($v['installOptions']['deleteTestFiles']),
                        'verbose'=>$params['verbose'],
                        'test'=>$params['test']
                    ]
                );

                //Finish Installation
                return \IOFrame\Util\Installation\UtilityFunctions::finalizeInstallation($defaults['absPathToRoot'],$siteSettings,array_merge($params,['local'=>!$fullInstall]));
            }
        ]
    ],
    'helpDesc'=>\IOFrame\Util\CLI\CommonManagerHelperFunctions::generateGenericDesc(
        [
            'This CLI can be used to install a secondary IOFrame node, or the full system.'
        ]
    )
];

require 'commons/initiate_manager.php';

$initiationError = 'flags-initiation';

require 'commons/checked_failed_initiation.php';

$v = $CLIManager->variables;

$initiation = $CLIManager->populateFromFiles(['test'=>$v['_test'],'verbose'=>$v['_verbose'],'absPathToRoot'=>$baseUrl]);

$initiationError = 'files-initiation';

require 'commons/checked_failed_initiation.php';

$v = $CLIManager->variables;

$params = ['inputs'=>true,'test'=>$v['_test'],'verbose'=>$v['_verbose'] && !$v['_silent'],'defaultParams'=>$defaultSettingsParams];

if(empty($v['_action'])){
    $CLIManager->printHelp();
    die();
}
else{
    $check = $CLIManager->matchAction(
        null,
        $params
    );
    die(json_encode($check,JSON_PRETTY_PRINT));
}

