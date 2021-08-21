<?php
$retrieveParams = [
    'test'=>$test,
    'update'=>$inputs['update'] === null? false : $inputs['update'],
    'override'=>$inputs['override'] === null? true : $inputs['override']
];

if(!\IOFrame\Util\is_json($inputs['inputs'])){
    if($test)
        echo 'inputs must be a valid JSON array!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
$inputs['inputs'] = json_decode($inputs['inputs'],true);

switch($type){
    case 'categories':
        $setColumns = [
            'title' => false
        ];
        if($retrieveParams['update'] !== $retrieveParams['override']){
            if($test)
                echo 'update and override must have the same value when setting/creating '.$type.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        if($retrieveParams['update'])
            $setColumns['category'] = true;
        break;
    case 'objects':
        $setColumns = [
            'category' => true,
            'object' => true,
            'title' => false,
            'public' => false,
        ];
        break;
    case 'actions':
        $setColumns = [
            'category' => true,
            'action' => true,
            'title' => false
        ];
        break;
    case 'groups':
        $setColumns = [
            'category' => true,
            'object' => true,
            'title' => false
        ];
        if($retrieveParams['update'] !== $retrieveParams['override']){
            if($test)
                echo 'update and override must have the same value when setting/creating '.$type.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
        if($retrieveParams['update'])
            $setColumns['group'] = true;
        break;
    case 'objectUsers':
        $setColumns = [
            'category' => true,
            'object' => true,
            'userID' => true,
            'action' => true,
        ];
        break;
    case 'objectGroups':
        $setColumns = [
            'category' => true,
            'object' => true,
            'group' => true,
            'action' => true,
        ];
        break;
    case 'userGroups':
        $setColumns = [
            'category' => true,
            'object' => true,
            'userID' => true,
            'group' => true,
        ];
        break;
    default:
        $setColumns = [];
        break;
}

$setRequiredColumns = [];

foreach($setColumns as $setColumn => $required){
    if($required)
        array_push($setRequiredColumns,$setColumn);
}

foreach($inputs['inputs'] as $index => $value){

    if(!is_array($value)){
        if($test)
            echo 'Each input must be an array!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    foreach($setRequiredColumns as $requiredColumn){
        if(!isset($value[$requiredColumn])){
            if($test)
                echo 'Each array must contain at least the columns '.implode(',',$setRequiredColumns).EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }

    foreach($setColumns as $setColumn => $required){

        if(!isset($value[$setColumn])){
            if(!$required)
                continue;
            else{
                if($test)
                    echo 'Required column '.$setColumn.' in item #'.$index.' missing!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }

        switch($setColumn){
            case 'category':
            case 'userID':
            case 'group':
                if(!filter_var($value[$setColumn],FILTER_VALIDATE_INT)){
                    if($test)
                        echo 'Value #'.$setColumn.' in key #'.$index.' must be a valid integer!'.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;

            case 'object':
                if(!preg_match('/'.OBJECT_REGEX.'/',$value[$setColumn])){
                    if($test)
                        echo 'Value #'.$setColumn.' in key #'.$index.' must match the pattern '.OBJECT_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;

            case 'action':
                if(!preg_match('/'.ACTION_REGEX.'/',$value[$setColumn])){
                    if($test)
                        echo 'Value #'.$setColumn.' in key #'.$index.' must match the pattern '.ACTION_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;

            case 'title':
                if(!preg_match('/'.OBJECT_REGEX.'/',$value[$setColumn])){
                    if($test)
                        echo 'Value #'.$setColumn.' in key #'.$index.' must match the pattern '.TITLE_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
                break;

            case 'public':
                $inputs['inputs'][$index][$setColumn] = (bool)$inputs['inputs'][$index][$setColumn];
                break;
        }

        $inputs['inputs'][$index][$columnMap[$setColumn]] = $inputs['inputs'][$index][$setColumn];
        unset($inputs['inputs'][$index][$setColumn]);
    }

}