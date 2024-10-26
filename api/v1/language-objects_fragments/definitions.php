<?php
$languages = $siteSettings->getSetting('languages');
if($siteSettings->getSetting('languages'))
    $languages = explode(',',$siteSettings->getSetting('languages'));
else
    $languages = [];
if($siteSettings->getSetting('defaultLanguage'))
    $languages[] = $siteSettings->getSetting('defaultLanguage');

$LANGUAGE_OBJECTS_API_DEFINITIONS = [
    'validation'=>[
        'name'=>'^\w[\w\d\-_]{0,127}$'
    ],
    'auth'=>[
        'getAdminParams'=>'LANGUAGE_OBJECTS_GET_ADMIN_PARAMS',
        'set'=>'LANGUAGE_OBJECTS_SET',
        'delete'=>'LANGUAGE_OBJECTS_DELETE'
    ]
];


$LANGUAGE_OBJECTS_INPUT_MAP_DEFINITIONS = [
    'getLanguageObjects'=>[
        'names'=>[
            'type'=>'string[]',
            'valid'=>$LANGUAGE_OBJECTS_API_DEFINITIONS['validation']['name'],
            'required'=>false,
            'default'=>[]
        ],
        'language'=>[
            'type'=>'string',
            'valid'=>empty($languages)? null : $languages,
            'required'=>false
        ]
    ],
    'setLanguageObjects'=>[
        'objects'=>[
            'type'=>'json',
            /*$LANGUAGE_OBJECTS_API_DEFINITIONS['validation']['name'] has to be passed as the param 'nameRegex' during runtime*/
            'valid'=>function($context){
                foreach ($context['input'] as $key=>$value){
                    if(!preg_match('/'.$context['params']['nameRegex'].'/',$key))
                        return false;
                    if(!is_array($value))
                        return false;
                }
                return true;
            }
        ]
    ],
    'deleteBaseLanguageObjects'=>[
        'identifiers'=>[
            'type'=>'string[]',
            'valid'=>$LANGUAGE_OBJECTS_API_DEFINITIONS['validation']['name'],
        ],
    ],
    'renameLanguageObjects'=>[
        'name'=>[
            'type'=>'string',
            'valid'=>$LANGUAGE_OBJECTS_API_DEFINITIONS['validation']['name']
        ],
        'newName'=>[
            'type'=>'string',
            'valid'=>$LANGUAGE_OBJECTS_API_DEFINITIONS['validation']['name']
        ],
    ],
    'setPreferredLanguage'=>[
        'lang'=>[
            'type'=>'string',
            'valid'=>empty($languages)? null : $languages,
            'required'=>true
        ]
    ],
];
$LANGUAGE_OBJECTS_PARSING_MAP_COMPONENT_DEFINITIONS = [
    'standardLanguageObjectMap'=>[
        'Object'=>[
            'resultName'=>'object',
            'type'=>'json'
        ]
    ]
];
$LANGUAGE_OBJECTS_PARSING_MAP_DEFINITIONS = [
    'setLanguageObjects'=>[
        'name'=>'Object_Name',
        'object'=>'Object'
    ],
    'setCategoryLanguageObjects'=>[
    ],
    'deleteCategoryLanguageObjects'=>[
    ]
];