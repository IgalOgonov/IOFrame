<?php

$setColumnMap = [
    'menuId'=>'Menu_ID',
    'title'=>'Title',
    'meta'=>'Meta',
];

$resultsColumnMap = [
    'Menu_ID'=>'menuId',
    'Title'=>'title',
    'Menu_Value'=>[
        'resultName'=>'menu',
        'type'=>'json',
    ],
    'Meta'=>[
        'resultName'=>'meta',
        'type'=>'json',
    ],
    'Created'=>[
        'resultName'=>'created',
        'type'=>'int'
    ],
    'Last_Updated'=>[
        'resultName'=>'updated',
        'type'=>'int'
    ],
];

/* AUTH */
//Allows getting all menus
CONST MENUS_GET_AUTH = 'MENUS_GET_AUTH';
//Allows modifying menus (includes deletion)
CONST MENUS_MODIFY_AUTH = 'MENUS_MODIFY_AUTH';

/* Validation */
CONST MENU_IDENTIFIER_REGEX = '^[a-zA-Z]\w{0,255}$';
CONST MENU_ITEM_IDENTIFIER_REGEX = '^[a-zA-Z]\w{0,63}$';
CONST MENU_TITLE_MAX_LENGTH = 1024;
CONST MENU_ITEM_TITLE_MAX_LENGTH = 128;

//A function to recursively convert menu children from a lookup array into a regular array, based on order.
function parseMenuItems(&$menu): void {
    if(!isset($menu['children']))
        return;
    $newChildren = [];
    $order = !empty($menu['order']) ? explode(',',$menu['order']) : [];
    foreach($menu['children'] as $identifier => $childMenu){
        parseMenuItems($menu['children'][$identifier]);
        $menu['children'][$identifier]['identifier'] = $identifier;
    }

    foreach($order as $identifier)
        if(!empty($menu['children'][$identifier])){
            $newChildren[] = $menu['children'][$identifier];
            unset($menu['children'][$identifier]);
        }

    foreach($menu['children'] as $identifier => $childMenu){
        if(!empty($menu['children'][$identifier])){
            $newChildren[] = $menu['children'][$identifier];
            $order[] = $identifier;
        }
    }

    $menu['order'] = $order;
    $menu['children'] = $newChildren;
}

