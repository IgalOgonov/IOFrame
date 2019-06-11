<?php

//User input validator

if($inputs['id']!=null && (preg_match_all('/[0-9]/',$inputs['id'])<strlen($inputs['id'])) ){
    if($test)
        echo 'Illegal user id!' ;
    exit('-1');
}

if($inputs['code']!='' && (preg_match_all('/[a-z]|[A-Z]|[0-9]/',$inputs['code'])<strlen($inputs['code'])) ){
    if($test)
        echo 'Illegal confirmation code!' ;
    exit('-1');
}

