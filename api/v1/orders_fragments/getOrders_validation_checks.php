<?php
$retrieveParams = [
    'test'=>$test
];

//specific IDs
if($inputs['ids'] !== null){
    if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['ids'])){
        if($test)
            echo 'ids must be a valid JSON array!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['ids'] = json_decode($inputs['ids'],true);

    foreach ($inputs['ids'] as $id)
        if(!filter_var($id,FILTER_VALIDATE_INT)){
            if($test)
                echo 'id must be an integer!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

    $retrieveParams['limit'] = null;
    $retrieveParams['offset'] = null;
    $retrieveParams['orderBy'] = null;
    $retrieveParams['orderType'] = null;
}
//No specific IDs
else{
    $inputs['ids'] = [];

    $filters = [
        'usersIn'=>[
            'type'=>'int[]'
        ],
        'createdAfter'=>[
            'type'=>'int'
        ],
        'createdBefore'=>[
            'type'=>'int'
        ],
        'changedAfter'=>[
            'type'=>'int'
        ],
        'changedBefore'=>[
            'type'=>'int'
        ],
        'limit'=>[
            'type'=>'int',
            'default'=>50
        ],
        'offset'=>[
            'type'=>'int'
        ],
        'orderType'=>[
            'type'=>'int',
            'valid'=>VALID_ORDER_ORDER_TYPE,
            'default'=>1
        ],
        'orderBy'=>[
            'type'=>'string[]',
            'valid'=>VALID_ORDER_ORDER_BY,
            'default'=>['ID']
        ],
    ];
    baseValidation($inputs,$filters,$retrieveParams,$test);
}