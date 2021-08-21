<?php

//Mail
if($inputs['mail']!==null && !filter_var($inputs['mail'],FILTER_VALIDATE_EMAIL) ){
    if($test)
        echo 'Illegal mail!.';
    exit(INPUT_VALIDATION_FAILURE);
}
if($inputs['mail']===null && $action==='sendInviteMail' ){
    if($test)
        echo 'Cannot send a mail invite without a mail!';
    exit(INPUT_VALIDATION_FAILURE);
}

//Token
if($inputs['token']!==null && !preg_match('/'.TOKEN_REGEX.'/',$inputs['token']) ){
    if($test)
        echo 'Illegal token!.';
    exit(INPUT_VALIDATION_FAILURE);
}

//Uses
if($inputs['tokenUses']!== null && !filter_var($inputs['tokenUses'],FILTER_VALIDATE_INT)){
    if($test)
        echo 'tokenUses must be a number!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}
elseif($inputs['tokenUses']=== null){
    $inputs['tokenUses'] = $action==='sendInviteMail'? 1 : PHP_INT_MAX;
}

//TTL
if($inputs['tokenTTL']!== null && !filter_var($inputs['tokenTTL'],FILTER_VALIDATE_INT)){
    if($test)
        echo 'tokenTTL must be a number!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

//extraTemplateArguments
if($inputs['extraTemplateArguments']!== null){
    if(!\IOFrame\Util\is_json($inputs['extraTemplateArguments'])){
        if($test)
            echo 'extraTemplateArguments must be a valid JSON!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $inputs['extraTemplateArguments'] = json_decode($inputs['extraTemplateArguments'],true);
}

//Flags
if($inputs['overwrite'])
    $inputs['overwrite'] = true;
if($inputs['update'])
    $inputs['update'] = true;
