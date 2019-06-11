<?php
//if($test) var_dump($result);
foreach($result as $key=>$object){
    if($key != 'Errors'){
        //If the object isn't a comment, unset it and return a relevant error
        if($object['Date_Comment_Created'] == null){
            $result['Errors'][$key] = -1;
            unset($result[$key]);
            continue;
        }
        //No need to set a group map if we are fetching a specific group
        if($action!='rg')
            $result['groupMap'][$key] = $object['Ob_Group'];

        //Set the date map
        $result['dateMap'][$key] = ['created'=>(int)$object['Date_Comment_Created'],'updated'=>(int)$object['Date_Comment_Updated']];

        //Parse according to whether the comment was "trusted" by the system.
        if($result[$key]['Trusted_Comment'])
            $parser->setSafeMode(false);
        else
            $parser->setSafeMode(true);

        $result[$key] = $parser->text($object['Object']);
        //Due to the client side JSON decoder not working with '\n', we have to do it ourselves
        $result[$key] = preg_replace('/\n/','',$result[$key]);
    }
}













