<?php
$deleteParams = [
    'test'=>$test,
];

if ($inputs['menus'] !== null) {

    if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['menus'])){
        if($test)
            echo 'menus must be a valid json!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    $inputs['menus'] = json_decode($inputs['menus'],true);

    if(count($inputs['menus']) === 0){
        if($test)
            echo 'menus cannot be empty!!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    foreach($inputs['menus'] as $index => $menuId){

        if(gettype($menuId) !== 'string' || !preg_match('/'.MENU_IDENTIFIER_REGEX.'/',$menuId)){
            if($test)
                echo 'Each menu Id must match '.MENU_IDENTIFIER_REGEX.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        $inputs['menus'][$index] = [
            'Menu_ID' => $menuId
        ];

    }

}
else{
    if ($test)
        echo 'menus must be set!' . EOL;
    exit(INPUT_VALIDATION_FAILURE);
}