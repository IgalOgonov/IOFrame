<?php
namespace IOFrame\Handlers{
    define('IOFrameHandlersLoggingHandler',true);

    /**
     * TODO In highScalability, don't join users table and get groupUsers programmatically
     * This handler manages everything related to logs and log-based reporting rules.
     * IMPORTANT when working with logs, DO NOT use inherited get/set functions, as they may point to a different DB
     * @author Igal Ogonov <igal1333@hotmail.com>
     */

    class LoggingHandler extends \IOFrame\Generic\ObjectsHandler
    {
        /**  @var \IOFrame\Managers\SQLManager
         * SQL Handler used exclusively to access the logs.
         *  Might point to a different db/server, and/or use a different user, all based on optional logSettings
         * */
        public \IOFrame\Managers\SQLManager $LoggingSQLManager;
        /**
         * @var \IOFrame\Handlers\SettingsHandler
         */
        public \IOFrame\Handlers\SettingsHandler $logSettings;
        /** $sqlSettings combined with relevant $logSettings
         * @var \IOFrame\Handlers\SettingsHandler
         */
        protected \IOFrame\Handlers\SettingsHandler $logSQLSettings;
        /**
         * Basic construction function.
         * @params SettingsHandler $settings  regular settings handler.
         * @params array $params
         *              'logTableName' => default 'DEFAULT_LOGS', Alternative name for the logs table;
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, $params = []){

            $params['logTableName'] = $params['logTableName'] ?? 'DEFAULT_LOGS';
            $prefix = $params['SQLManager']->getSQLPrefix();

            $this->validObjectTypes = ['defaultLogs','reportingGroups','reportingRules','reportingRuleGroups','reportingGroupUsers'];

            $this->objectsDetails = [
                'defaultLogs'=>[
                    'tableName' => $params['logTableName'],
                    'hasTimeColumns'=>false,
                    'useCache'=> false,
                    'keyColumns' => ['Channel','Log_Level','Created','Node'],
                    'setColumns' => [
                        'Channel' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Log_Level' => [
                            'type' => 'int',
                            'required' => true
                        ],
                        'Created' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Node' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Message' => [
                            'type' => 'string',
                            'jsonObject' => true,
                            'default' => null
                        ],
                        'Uploaded' => [
                            'type' => 'string',
                            'function' => function($tempInputs){
                                return (string)microtime(true);
                            }
                        ],
                    ],
                    'moveColumns' => [],
                    'columnFilters' => [
                        'channelIs' => [
                            'column' => 'Channel',
                            'filter' => '='
                        ],
                        'channelIn' => [
                            'column' => 'Channel',
                            'filter' => 'IN'
                        ],
                        'levelIs' => [
                            'column' => 'Log_Level',
                            'filter' => '='
                        ],
                        'levelIn' => [
                            'column' => 'Log_Level',
                            'filter' => 'IN'
                        ],
                        'levelAtLeast' => [
                            'column' => 'Log_Level',
                            'filter' => '>='
                        ],
                        'levelAtMost' => [
                            'column' => 'Log_Level',
                            'filter' => '<='
                        ],
                        'createdBefore' => [
                            'column' => 'Created',
                            'filter' => '<='
                        ],
                        'createdAfter' => [
                            'column' => 'Created',
                            'filter' => '>='
                        ],
                        'uploadedBefore' => [
                            'column' => 'Uploaded',
                            'filter' => '<='
                        ],
                        'uploadedAfter' => [
                            'column' => 'Uploaded',
                            'filter' => '>='
                        ],
                        'nodeIs' => [
                            'column' => 'Node',
                            'filter' => '='
                        ],
                        'nodeIn' => [
                            'column' => 'Node',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                        'Channel' => [
                            'key' => 'channels',
                            'type' => 'distinct'
                        ],
                        'Node' => [
                            'key' => 'nodes',
                            'type' => 'distinct'
                        ]
                    ],
                    'orderColumns' => ['Created','Channel','Log_Level']
                ],
                'reportingGroups'=>[
                    'tableName' => 'REPORTING_GROUPS',
                    'cacheName'=> 'reporting_group_',
                    'extendTTL'=> false,
                    'childCache' => ['reporting_group_users_'],
                    'keyColumns' => ['Group_Type','Group_ID'],
                    'columnsToGet'=>[
                        [
                            'expression' => '
                            (SELECT Count(*)
                             FROM '.$prefix.'REPORTING_GROUP_USERS
                             WHERE
                                '.$prefix.'REPORTING_GROUPS.Group_ID = '.$prefix.'REPORTING_GROUP_USERS.Group_ID
                             ) AS "User_Count"
                             '
                        ],
                    ],
                    'setColumns' => [
                        'Group_Type' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Group_ID' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Meta' => [
                            'type' => 'string',
                            'jsonObject' => true,
                            'default' => null
                        ]
                    ],
                    'moveColumns' => [
                        'Group_Type' => [
                            'type' => 'string'
                        ],
                    ],
                    'columnFilters' => [
                        'typeIs' => [
                            'column' => 'Group_Type',
                            'filter' => '='
                        ],
                        'typeIn' => [
                            'column' => 'Group_Type',
                            'filter' => 'IN'
                        ],
                        'groupIs' => [
                            'column' => 'Group_ID',
                            'filter' => '='
                        ],
                        'groupIn' => [
                            'column' => 'Group_ID',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                        'Group_Type' => [
                            'key' => 'types',
                            'type' => 'distinct'
                        ]
                    ],
                    'orderColumns' => ['Group_Type','Group_ID','User_Count']
                ],
                'reportingRules'=>[
                    'tableName' => 'REPORTING_RULES',
                    'cacheName'=> 'reporting_rule_',
                    'extendTTL'=> false,
                    'childCache' => ['reporting_rule_groups_'],
                    'keyColumns' => ['Channel','Log_Level','Report_Type'],
                    'setColumns' => [
                        'Channel' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Log_Level' => [
                            'type' => 'int',
                            'required' => true
                        ],
                        'Report_Type' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Meta' => [
                            'type' => 'string',
                            'jsonObject' => true,
                            'default' => null
                        ]
                    ],
                    'moveColumns' => [
                    ],
                    'columnFilters' => [
                        'channelIs' => [
                            'column' => 'Channel',
                            'filter' => '='
                        ],
                        'channelIn' => [
                            'column' => 'Channel',
                            'filter' => 'IN'
                        ],
                        'levelIs' => [
                            'column' => 'Log_Level',
                            'filter' => '='
                        ],
                        'levelIn' => [
                            'column' => 'Log_Level',
                            'filter' => 'IN'
                        ],
                        'levelAtLeast' => [
                            'column' => 'Log_Level',
                            'filter' => '>='
                        ],
                        'levelAtMost' => [
                            'column' => 'Log_Level',
                            'filter' => '<='
                        ],
                        'reportTypeIs' => [
                            'column' => 'Report_Type',
                            'filter' => '='
                        ],
                        'reportTypeIn' => [
                            'column' => 'Report_Type',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                        'Channel' => [
                            'key' => 'channels',
                            'type' => 'distinct'
                        ],
                    ],
                    'orderColumns' => ['Channel','Log_Level','Report_Type']
                ],
                'reportingRuleGroups'=>[
                    'tableName' => 'REPORTING_RULE_GROUPS',
                    'cacheName'=> 'reporting_rule_groups_',
                    'extendTTL'=> false,
                    'joinOnGet' => [
                        [
                            'tableName' => 'REPORTING_GROUPS',
                            'on' => [
                                ['Group_Type','Group_Type'],
                                ['Group_ID','Group_ID']
                            ],
                        ],
                        [
                            'tableName' => 'REPORTING_RULES',
                            'on' => [
                                ['Channel','Channel'],
                                ['Log_Level','Log_Level'],
                                ['Report_Type','Report_Type']
                            ],
                        ]
                    ],
                    'columnsToGet' => [
                        [
                            'tableName' => 'REPORTING_GROUPS',
                            'column' => 'Meta',
                            'as'=>'Group_Meta'
                        ],
                        [
                            'tableName' => 'REPORTING_RULES',
                            'column' => 'Meta',
                            'as'=>'Rule_Meta'
                        ]
                    ],
                    'fatherDetails'=>[
                        [
                            'tableName' => 'REPORTING_RULES',
                            'cacheName' => 'reporting_rule_'
                        ],
                        'minKeyNum' => 3
                    ],
                    'keyColumns' => ['Channel','Log_Level','Report_Type'],
                    'extraKeyColumns' => ['Group_Type','Group_ID'],
                    'setColumns' => [
                        'Channel' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Log_Level' => [
                            'type' => 'int',
                            'required' => true
                        ],
                        'Report_Type' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Group_Type' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Group_ID' => [
                            'type' => 'string',
                            'required' => true
                        ],
                    ],
                    'moveColumns' => [
                    ],
                    'defaultTableToFilterColumns'=>true,
                    'columnFilters' => [
                        'fullRuleIn' => [
                            'function' => function($context){
                                return \IOFrame\Util\GenericObjectFunctions::filterByMultipleColumns($context,[
                                    'baseTableName'=>'REPORTING_RULE_GROUPS',
                                    'filterName'=>'fullRuleIn',
                                    'filterColumns'=>['Channel','Log_Level','Report_Type'],
                                ]);
                            }
                        ],
                        'fullGroupIn' => [
                            'function' => function($context){
                                return \IOFrame\Util\GenericObjectFunctions::filterByMultipleColumns($context,[
                                    'baseTableName'=>'REPORTING_RULE_GROUPS',
                                    'filterName'=>'fullGroupIn',
                                    'filterColumns'=>['Group_Type','Group_ID'],
                                ]);
                            }
                        ],
                        'typeIs' => [
                            'column' => 'Group_Type',
                            'filter' => '='
                        ],
                        'typeIn' => [
                            'column' => 'Group_Type',
                            'filter' => 'IN'
                        ],
                        'groupIs' => [
                            'column' => 'Group_ID',
                            'filter' => '='
                        ],
                        'groupIn' => [
                            'column' => 'Group_ID',
                            'filter' => 'IN'
                        ],
                        'channelIs' => [
                            'column' => 'Channel',
                            'filter' => '='
                        ],
                        'channelIn' => [
                            'column' => 'Channel',
                            'filter' => 'IN'
                        ],
                        'levelIs' => [
                            'column' => 'Log_Level',
                            'filter' => '='
                        ],
                        'levelIn' => [
                            'column' => 'Log_Level',
                            'filter' => 'IN'
                        ],
                        'levelAtLeast' => [
                            'column' => 'Log_Level',
                            'filter' => '>='
                        ],
                        'levelAtMost' => [
                            'column' => 'Log_Level',
                            'filter' => '<='
                        ],
                        'reportTypeIs' => [
                            'column' => 'Report_Type',
                            'filter' => '='
                        ],
                        'reportTypeIn' => [
                            'column' => 'Report_Type',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                        'Channel' => [
                            'key' => 'channels',
                            'type' => 'distinct'
                        ],
                        'Report_Type' => [
                            'key' => 'reportTypes',
                            'type' => 'distinct'
                        ],
                        'Group_Type' => [
                            'key' => 'groupTypes',
                            'type' => 'distinct'
                        ],
                        'Group_ID' => [
                            'key' => 'groups',
                            'type' => 'distinct'
                        ],
                    ],
                    'orderColumns' => ['Channel','Log_Level','Report_Type','Group_Type','Group_ID',],
                    'groupByFirstNKeys'=>3,
                ],
                'reportingGroupUsers'=>[
                    'tableName' => 'REPORTING_GROUP_USERS',
                    'joinOnGet' => [
                        [
                            'tableName' => 'USERS',
                            'on' => [
                                ['User_ID','ID']
                            ],
                        ]
                    ],
                    'columnsToGet' => [
                        [
                            'tableName' => 'USERS',
                            'column' => 'Email'
                        ],
                        [
                            'tableName' => 'USERS',
                            'column' => 'Phone'
                        ]
                    ],
                    'cacheName'=> 'reporting_group_users_',
                    'useCache'=> false,
                    'fatherDetails'=>[
                        [
                            'tableName' => 'REPORTING_GROUPS',
                            'cacheName' => 'reporting_group_'
                        ],
                        'minKeyNum' => 2
                    ],
                    'keyColumns' => ['Group_Type','Group_ID'],
                    'extraKeyColumns'=>['User_ID'],
                    'setColumns' => [
                        'Group_Type' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Group_ID' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'User_ID' => [
                            'type' => 'int',
                            'required' => true
                        ],
                    ],
                    'moveColumns' => [
                    ],
                    'defaultTableToFilterColumns'=>true,
                    'columnFilters' => [
                        'fullGroupIn' => [
                            'function' => function($context){
                                return \IOFrame\Util\GenericObjectFunctions::filterByMultipleColumns($context,[
                                    'baseTableName'=>'REPORTING_GROUP_USERS',
                                    'filterName'=>'fullGroupIn',
                                    'filterColumns'=>['Group_Type','Group_ID'],
                                ]);
                            }
                        ],
                        'typeIs' => [
                            'column' => 'Group_Type',
                            'filter' => '='
                        ],
                        'typeIn' => [
                            'column' => 'Group_Type',
                            'filter' => 'IN'
                        ],
                        'groupIs' => [
                            'column' => 'Group_ID',
                            'filter' => '='
                        ],
                        'groupIn' => [
                            'column' => 'Group_ID',
                            'filter' => 'IN'
                        ],
                        'userIs' => [
                            'column' => 'User_ID',
                            'filter' => '='
                        ],
                        'userIn' => [
                            'column' => 'User_ID',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                    ],
                    'orderColumns' => ['Group_Type','Group_ID','User_ID'],
                    'groupByFirstNKeys'=>2,
                ],
            ];

            parent::__construct($settings,$params);

            if(!empty($params['logSettings']) && (gettype($params['logSettings']) === 'object') && (get_class($params['logSettings']) === 'IOFrame\Handlers\SettingsHandler')){
                $this->logSettings = $params['logSettings'];
            }
            else
                $this->logSettings = new \IOFrame\Handlers\SettingsHandler(
                    $settings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/logSettings/',
                    $params
                );

            $this->logSQLSettings = clone $this->sqlSettings;
            $this->logSQLSettings->combineWithSettings($this->logSettings,[
                'settingAliases'=>[
                    'logs_sql_table_prefix'=>'sql_table_prefix',
                    'logs_sql_server_addr'=>'sql_server_addr',
                    'logs_sql_server_port'=>'sql_server_port',
                    'logs_sql_username'=>'sql_username',
                    'logs_sql_password'=>'sql_password',
                    'logs_sql_db_name'=>'sql_db_name',
                    'logs_sql_persistent'=>'sql_persistent',
                ],
                'includeRegex'=>'logs_sql',
                'ignoreEmptyStrings'=>['logs_sql_server_addr','logs_sql_server_port','logs_sql_username','logs_sql_password','logs_sql_db_name','logs_sql_persistent',]
            ]);

            $this->LoggingSQLManager= new \IOFrame\Managers\SQLManager(
                $settings,
                ['sqlSettings'=>$this->logSQLSettings]
            );
        }

        /** Gets logs from the DB (potentially another DB).
         * IMPORTANT Remember to use this for log files, not the inherited getItems
         * Params are same as Objects->getItems()
         * @param array $items
         * @param array $params
         * @return array
         *
         * @throws \Exception
         */
        function getLogs(array $items = [], array $params = []): array {

            $temp = $this->SQLManager;
            $this->SQLManager = $this->LoggingSQLManager;

            $res = $this->getItems($items,'defaultLogs',$params);

            $this->SQLManager = $temp;

            return $res;
        }

        /** Sets logs in the DB (potentially another DB).
         * IMPORTANT Remember to use this for log files, not the inherited setItems
         * Params are same as Objects->setItems()
         * @param array $inputs
         * @param array $params
         * @return array|int|string
         *
         * @throws \Exception
         */
        function setLogs(array $inputs = [], array $params = []): int|array|string {

            $temp = $this->SQLManager;
            $this->SQLManager = $this->LoggingSQLManager;

            $res = $this->setItems($inputs,'defaultLogs',array_merge($params,['disableLogging'=>true]));

            $this->SQLManager = $temp;

            return $res;
        }

        /** Deletes logs from the DB (potentially another DB).
         * Do keep in mind that it's HIGHLY advised not to do this directly, but rather use a chron job to archive, then delete.
         * IMPORTANT Remember to use this for log files, not the inherited deleteItems
         * Params are same as Objects->deleteItems()
         * @param array $inputs
         * @param array $params
         * @return int
         *
         * @throws \Exception
         */
        function deleteLogs(array $inputs = [], array $params = []): int {

            $temp = $this->SQLManager;
            $this->SQLManager = $this->LoggingSQLManager;

            $res = $this->deleteItems($inputs,'defaultLogs',$params);

            $this->SQLManager = $temp;

            return $res;
        }

    }

}

