<?php
//Remember you might be getting something like a group - in that case, the result might be an error code
if(is_array($result))
    foreach($result as $key=>$object){
        if($key != 'Errors'){
            if($action!='rg')
                $result['groupMap'][$key] = $object['Ob_Group'];
            $result[$key] = $object['Object'];
            //Due to the client side JSON decoder not working with '\n', we have to do it ourselves
            $result[$key] = preg_replace('/\n/','',$result[$key]);
        }
    }
if($result == [])
    $result = 0;
