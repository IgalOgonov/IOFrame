<?php

$tagSettingsObject = $categories? $currentCategoryTags : $currentTags;

if(empty($tagSettingsObject[$inputs['type']]) && !($test && $inputs['type'] === $categories? 'category-test' : 'base-test'))
    exit(INPUT_VALIDATION_FAILURE);

if(!empty($tagSettingsObject[$inputs['type']]['img'])){
    $TAGS_INPUT_MAP_DEFINITIONS['setTags']['img'] = [
        'type'=>'string',
        'valid'=>$STANDARD_API_DEFINITIONS['validation']['imageAddressRegex'],
        'required'=>false,
        'exceptions'=>['@']
    ];
    $TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['setMap']['img'] = [
        'resultName'=>'Resource_Address'
    ];
}

if(!empty($tagSettingsObject[$inputs['type']]['extraMetaParameters'])){
    foreach ($tagSettingsObject[$inputs['type']]['extraMetaParameters'] as $tagExtraParamName=>$tagExtraParamArr){
        if(!in_array($tagExtraParamName,$TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['standardTagMap']['Meta']['validChildren']))
            $TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['standardTagMap']['Meta']['validChildren'][] = $tagExtraParamName;

        if(empty($TAGS_INPUT_MAP_DEFINITIONS['setTags'][$tagExtraParamName])){

            $TAGS_INPUT_MAP_DEFINITIONS['setTags'][$tagExtraParamName] = [
                'type'=>'string',
            ];

            $TAGS_INPUT_MAP_DEFINITIONS['setTags'][$tagExtraParamName]['required'] = empty($inputs['update'])? $tagExtraParamArr['required']??true : !$inputs['update'];
            $TAGS_INPUT_MAP_DEFINITIONS['setTags'][$tagExtraParamName]['exceptions'] = $TAGS_INPUT_MAP_DEFINITIONS['setTags'][$tagExtraParamName]['required']? [] : ['@'];
            if(!empty($tagExtraParamArr['exceptions']))
                $TAGS_INPUT_MAP_DEFINITIONS['setTags'][$tagExtraParamName]['exceptions'] = array_merge($TAGS_INPUT_MAP_DEFINITIONS['setTags'][$tagExtraParamName]['exceptions'],$tagExtraParamArr['exceptions']);

            $TAGS_INPUT_MAP_DEFINITIONS['setTags'][$tagExtraParamName]['valid'] = !empty($tagExtraParamArr['valid']) ? $tagExtraParamArr['valid'] : $STANDARD_API_DEFINITIONS['validation']['anyValid'];
            if(!empty($tagExtraParamArr['color']))
                $TAGS_INPUT_MAP_DEFINITIONS['setTags'][$tagExtraParamName]['valid'] = $TAGS_API_DEFINITIONS['validation']['colorHexRegex'];
        }

        if(empty($TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['setMap'][$tagExtraParamName])){

            $TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['setMap'][$tagExtraParamName] = [
                'resultName'=>$tagExtraParamArr['parsingResultName'] ?? $tagExtraParamName,
                'ignoreInGroupIfNull'=>true,
                'groupBy'=>'Meta'
            ];
            if (!($tagExtraParamArr['required'] ?? false))
                $TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['setMap'][$tagExtraParamName]['replaceMap'] = ['@'=>null];

        }
    }
}

/*If in test mode, allow testing with a special type of tag that will never be written anyway*/
if($test){
    $TAGS_API_DEFINITIONS['validation']['validTagTypes'][] = 'base-test';
    $TAGS_API_DEFINITIONS['validation']['validCategoryTagTypes'][] = 'category-test';
    if($inputs['type'] === ($categories?'category-test':'base-test')){
        $TAGS_INPUT_MAP_DEFINITIONS['setTags']['img'] = [
            'type'=>'string',
            'valid'=>$STANDARD_API_DEFINITIONS['validation']['imageAddressRegex'],
            'required'=>false,
            'exceptions'=>['@']
        ];
        $TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['setMap']['img'] = 'Resource_Address';
        array_push($TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['standardTagMap']['Meta']['validChildren'],'test_meta_param_optional','test_meta_color_required');
        $TAGS_INPUT_MAP_DEFINITIONS['setTags']['test_meta_param_optional'] = [
            'type'=>'string',
            'required'=>false,
            'valid'=>$STANDARD_API_DEFINITIONS['validation']['anyValid'],
            'exceptions'=>['@']
        ];
        $TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['setMap']['test_meta_param_optional'] = [
            'resultName'=>'test_1',
            'ignoreInGroupIfNull'=>true,
            'groupBy'=>'Meta',
            'replaceMap' => ['@'=>null]
        ];
        $TAGS_INPUT_MAP_DEFINITIONS['setTags']['test_meta_color_required'] = [
            'type'=>'string',
            'required'=>true,
            'valid'=>$TAGS_API_DEFINITIONS['validation']['colorHexRegex']
        ];
        $TAGS_PARSING_MAP_COMPONENT_DEFINITIONS['setMap']['test_meta_color_required'] = [
            'resultName'=>'test_meta_color_required',
            'ignoreInGroupIfNull'=>true,
            'groupBy'=>'Meta'
        ];
    }
}
