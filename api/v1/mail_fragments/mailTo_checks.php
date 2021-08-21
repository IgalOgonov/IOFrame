<?php
//Type
if(!$inputs['type'])
    $inputs['type'] = 'normal';
if($inputs['type'] !== 'normal' && $inputs['type'] !== 'template'){
    if($test)
        echo 'Type illegal!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

//mName1
if(!$inputs['mName1'])
    $inputs['mName1']='';
else{

}

//mName2
if(!$inputs['mName2'])
    $inputs['mName2']='';
else{

}

//secToken
if(!$inputs['secToken']){
    if($test)
        echo 'No security token specified'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
else{
    if( (preg_match_all('/[0-9]||[a-z]||[A-Z]/',$inputs['secToken'])<strlen($inputs['secToken']))){
        if($test)
            echo 'Illegal security token!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

//Mail && subject
if(!$inputs['mail']||!$inputs['subj']){
    if($test)
        echo 'The mail must have a subject and recipients.'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
else{
    //Validate Mail
    if( !filter_var($inputs['mail'],FILTER_VALIDATE_EMAIL)){
        if($test)
            echo 'Illegal recipient mail!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

if($inputs['type']=='template'){
    //templateNum
    if(!$inputs['templateNum']){
        if($test)
            echo 'Cannot send template without a number'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    else{
        if( !filter_var($inputs['templateNum'],FILTER_VALIDATE_INT)){
            if($test)
                echo 'Illegal templateNum!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
}
elseif($inputs['type'] == 'normal'){
    //mBody
    if(!$inputs['mBody']){
        if($test)
            echo 'You need a mail body to send a mail'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}