<?php


$tagSettings = new \IOFrame\Handlers\SettingsHandler(
    $settings->getSetting('absPathToRoot').'localFiles/tagSettings/',
    array_merge($defaultSettingsParams,['base64Storage'=>true])
);

$currentTags = $tagSettings->getSetting('availableTagTypes');
$currentCategoryTags = $tagSettings->getSetting('availableCategoryTagTypes');
$currentTags = \IOFrame\Util\PureUtilFunctions::is_json($currentTags)? json_decode($currentTags,true) : [];
$currentCategoryTags = \IOFrame\Util\PureUtilFunctions::is_json($currentCategoryTags)? json_decode($currentCategoryTags,true) : [];


$TAGS_API_DEFINITIONS = [
    'validation'=>[
        'validTagTypes'=>[],
        'validCategoryTagTypes'=>[],
        'tagRegex'=>'^\w[\w\d\-_]{0,63}$',
        'colorHexRegex'=>'^[0-9a-f]{6,6}$',
    ],
    'auth'=>[
        'getAdminParams'=>'TAGS_GET_ADMIN_PARAMS',
        'set'=>'TAGS_SET',
        'delete'=>'TAGS_DELETE'
    ]
];
$TagHandlerBaseTagTypes = [];
$TagHandlerCategoryTagTypes = [];

foreach ($currentTags as $tagType=>$arr){
    $TagHandlerBaseTagTypes[] = $tagType;
    $TAGS_API_DEFINITIONS['validation']['validTagTypes'][] = $tagType;
}

foreach ($currentCategoryTags as $tagType=>$arr){
    $TagHandlerCategoryTagTypes[] = $tagType;
    $TAGS_API_DEFINITIONS['validation']['validCategoryTagTypes'][] = $tagType;
}

if($test){
    $TagHandlerBaseTagTypes[] = 'base-test';
    $TagHandlerCategoryTagTypes[] = 'category-test';
}



$TAGS_INPUT_MAP_COMPONENT_DEFINITIONS = [
    'standardSetValidation'=>[
        'tags'=>[
            'type'=>'json'
        ]
    ],
    'standardDeleteValidation'=>[
        'overwrite'=>[
            'type'=>'bool',
            'default'=>true
        ],
        'update'=>[
            'type'=>'bool',
            'default'=>false
        ]
    ],
    'standardRenameValidation'=>[
        'name'=>[
            'type'=>'string',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['tagRegex']
        ],
        'newName'=>[
            'type'=>'string',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['tagRegex']
        ],
    ]
];
$TAGS_INPUT_MAP_DEFINITIONS = [
    'getBaseTags'=>[
        'type'=>[
            'type'=>'string',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['validTagTypes'],
            'required'=>true /*just here as an example*/
        ],
    ],
    'getCategoryTags'=>[
        'type'=>[
            'type'=>'string',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['validCategoryTagTypes']
        ],
        'category'=>[
            'type'=>'int',
            'required'=>false
        ]
    ],
    'setBaseTags'=>[
        'type'=>[
            'type'=>'string',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['validTagTypes']
        ]
    ],
    'setCategoryTags'=>[
        'type'=>[
            'type'=>'string',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['validCategoryTagTypes']
        ],
        'category'=>[
            'type'=>'int'
        ]
    ],
    'setTags'=>[
        'identifier'=>[
            'type'=>'string',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['tagRegex']
        ]
    ],
    'deleteBaseTags'=>[
        'type'=>[
            'type'=>'string',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['validTagTypes']
        ],
        'identifiers'=>[
            'type'=>'string[]',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['tagRegex'],
        ],
    ],
    'deleteCategoryTags'=>[
        'type'=>[
            'type'=>'string',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['validCategoryTagTypes']
        ],
        'category'=>[
            'type'=>'int'
        ],
        'identifiers'=>[
            'type'=>'string[]',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['tagRegex'],
        ],
    ],
    'renameBaseTags'=>[
        'type'=>[
            'type'=>'string',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['validTagTypes']
        ]
    ],
    'renameCategoryTags'=>[
        'type'=>[
            'type'=>'string',
            'valid'=>$TAGS_API_DEFINITIONS['validation']['validCategoryTagTypes']
        ],
        'category'=>[
            'type'=>'int'
        ]
    ],
];
$TAGS_PARSING_MAP_COMPONENT_DEFINITIONS = [
    'standardTagMap'=>[
        'Meta'=>[
            'resultName'=>'meta',
            'type'=>'json',
            'validChildren'=>[],
            'expandChildren'=>true,
        ],
        'Resource_Address'=>[
            'resultName'=>'address',
            'groupBy'=>'img'
        ],
        'Resource_Local'=>[
            'resultName'=>'local',
            'type'=>'bool',
            'groupBy'=>'img'
        ],
        'Data_Type'=>[
            'resultName'=>'dataType',
            'groupBy'=>'img'
        ],
        'Resource_Last_Updated' => [
            'resultName'=>'updated',
            'groupBy'=>'img'
        ],
        'Resource_Meta'=>[
            'resultName'=>'meta',
            'type'=>'json',
            'validChildren'=>['caption','alt','name'],
            'expandChildren'=>true,
            'groupBy'=>'img',
        ],
    ],
    'setMap'=>[
        'type'=>'Tag_Type',
        'identifier'=>'Tag_Name',
        'weight'=>'Weight'
    ],
    'deleteMap'=>[
        'type'=>'Tag_Type',
        'identifier'=>'Tag_Name',
    ]
];
$TAGS_PARSING_MAP_DEFINITIONS = [
    'getBaseTags'=>[
    ],
    'getCategoryTags'=>[
        'Category_ID'=>[
            'resultName'=>'category',
            'type'=>'int'
        ]
    ],
    'setBaseTags'=>[
    ],
    'setCategoryTags'=>[
        'category'=>'Category_ID',
    ],
    'deleteBaseTags'=>[
    ],
    'deleteCategoryTags'=>[
        'category'=>'Category_ID',
    ]
];