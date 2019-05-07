<?php

foreach($result as $key=>$object){
    if($key != 'Errors'){
        if($action!='rg')
            $result['groupMap'][$key] = $object['Ob_Group'];
        $result[$key] = $object['Object'];
        //Due to the client side JSON decoder not working with '\n', we have to do it ourselves
        $result[$key] = preg_replace('/\n/','',$result[$key]);
    }
}
