<?php
/*Maps*/
$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.AllowedElements', []);
$purifier = new HTMLPurifier($config);
$_logsTitlePurificationFunction = function($context,&$errors)use($purifier){
    if(!preg_match('/^title(_[a-z]{2,12})?$/',$context['index'])){
        $errors['title_'] = 'invalid-title';
        return null;
    }
    elseif(!preg_match('/^.{0,512}$/',(string)$context['rawValue'])) {
        $errors[$context['index']] = 'Title too long';
        return null;
    }
    else
        return $purifier->purify((string)$context['rawValue']);
};

$logsResultsColumnMap = [
    'Channel'=>[
        'resultName'=>'channel',
        'type'=>'string'
    ],
    'Log_Level'=>[
        'resultName'=>'level',
        'type'=>'int'
    ],
    'Created'=>[
        'resultName'=>'created',
        'type'=>'double'
    ],
    'Node'=>[
        'resultName'=>'node',
        'type'=>'string'
    ],
    'Message'=>[
        'resultName'=>'meta',
        'type'=>'json',
        'validChildren'=>['message','context'],
    ]
];

$groupsSetColumnMap = [
    'type'=>'Group_Type',
    'id'=>'Group_ID',
    '_patternKeys'=>[
        '^title(_[a-z]{2,12})?$'=>[
            'type'=>'function',
            'function'=>$_logsTitlePurificationFunction,
            'groupBy'=>'Meta'
        ]
    ]
];

$groupsResultsColumnMap = [
    'Group_Type'=>[
        'resultName'=>'type',
        'type'=>'string'
    ],
    'Group_ID'=>[
        'resultName'=>'id',
        'type'=>'string'
    ],
    'User_Count'=>[
        'resultName'=>'userCount',
        'type'=>'int'
    ],
    'Meta'=>[
        'resultName'=>'titles',
        'type'=>'json',
        'validChildrenPatterns'=>['^title(_[a-z]{2,12})?$'],
    ],
    'Created'=>[
        'resultName'=>'created',
        'type'=>'int'
    ],
    'Last_Updated'=>[
        'resultName'=>'updated',
        'type'=>'int'
    ]
];

$groupUsersSetColumnMap = [
    'type'=>'Group_Type',
    'id'=>'Group_ID',
    'user'=>'User_ID',
];

$groupUsersResultsColumnMap = [
    'Group_Type'=>[
        'resultName'=>'type',
        'type'=>'string'
    ],
    'Group_ID'=>[
        'resultName'=>'groupId',
        'type'=>'string'
    ],
    'User_ID'=>[
        'resultName'=>'userId',
        'type'=>'int'
    ],
    'Email'=>[
        'resultName'=>'email',
        'type'=>'string'
    ],
    'Phone'=>[
        'resultName'=>'phone',
        'type'=>'string'
    ],
    'Created'=>[
        'resultName'=>'created',
        'type'=>'int'
    ],
    'Last_Updated'=>[
        'resultName'=>'updated',
        'type'=>'int'
    ]
];

$rulesSetColumnMap = [
    'channel'=>'Channel',
    'level'=>'Log_Level',
    'reportType'=>'Report_Type',
    '_patternKeys'=>[
        '^title(_[a-z]{2,12})?$'=>[
            'type'=>'function',
            'function'=>$_logsTitlePurificationFunction,
            'groupBy'=>'Meta'
        ]
    ]
];

$rulesResultsColumnMap = [
    'Channel'=>[
        'resultName'=>'channel',
        'type'=>'string'
    ],
    'Log_Level'=>[
        'resultName'=>'level',
        'type'=>'string'
    ],
    'Report_Type'=>[
        'resultName'=>'reportType',
        'type'=>'string'
    ],
    'Meta'=>[
        'resultName'=>'titles',
        'type'=>'json',
        'validChildrenPatterns'=>['^title(_[a-z]{2,12})?$'],
    ],
    'Created'=>[
        'resultName'=>'created',
        'type'=>'int'
    ],
    'Last_Updated'=>[
        'resultName'=>'updated',
        'type'=>'int'
    ]
];

$ruleGroupsSetColumnMap = [
    'channel'=>'Channel',
    'level'=>'Log_Level',
    'reportType'=>'Report_Type',
    'type'=>'Group_Type',
    'id'=>'Group_ID'
];

$ruleGroupsResultsColumnMap = [
    'Channel'=>[
        'resultName'=>'channel',
        'type'=>'string'
    ],
    'Log_Level'=>[
        'resultName'=>'level',
        'type'=>'string'
    ],
    'Report_Type'=>[
        'resultName'=>'reportType',
        'type'=>'string'
    ],
    'Group_Type'=>[
        'resultName'=>'type',
        'type'=>'string'
    ],
    'Group_ID'=>[
        'resultName'=>'id',
        'type'=>'string'
    ],
    'Created'=>[
        'resultName'=>'created',
        'type'=>'int'
    ],
    'Last_Updated'=>[
        'resultName'=>'updated',
        'type'=>'int'
    ]
];
$ruleGroupsResultsColumnMapRuleMeta = array_merge($ruleGroupsResultsColumnMap,[
    'Rule_Meta'=>[
        'resultName'=>'titles',
        'type'=>'json',
        'validChildrenPatterns'=>['^title(_[a-z]{2,12})?$'],
    ]
]);
$ruleGroupsResultsColumnMapGroupMeta = array_merge($ruleGroupsResultsColumnMap,[
    'Group_Meta'=>[
        'resultName'=>'titles',
        'type'=>'json',
        'validChildrenPatterns'=>['^title(_[a-z]{2,12})?$'],
    ]
]);

$ruleGroupsSetDeepValidationMap = [
    'groups'=>[
        "type" => "json",
        'minChildrenPatterns'=>1,
        'validChildrenPatterns'=>['^\d+$'],
        'validChildrenPatternsParsing'=>[
            '^\d+$'=>[
                "type"=>"json",
                "expandChildren"=>false,
                "validChildren"=>['type','id'],
                "requiredChildren"=>['type','id'],
                'validChildrenValidation'=>[
                    'id'=>[
                        'type'=>'string',
                        'valid'=>'^[a-zA-Z0-9][\w\-\.\_ ]{0,63}$'
                    ],
                    'type'=>[
                        'type'=>'string',
                        'valid'=>'^[a-zA-Z0-9][\w\-\.\_ ]{0,63}$'
                    ]
                ]
            ]
        ]
    ]
];
