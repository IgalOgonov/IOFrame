<?php


//TODO Check whether this item can be modified via individual auth, then check individual auth
if(false){

}
else{

    if($action === 'updateImage'){
        if($inputs['alt'] !== null){
            if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_ALT_AUTH) || $auth->isAuthorized() ) ){
                if($test)
                    echo 'Cannot change image alt tag!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
        }
    }
    elseif($action === 'updateVideo'){

        $videoMeta = ["autoplay","loop","mute","controls","poster","preload"];
        foreach($videoMeta as $attr){
            if($inputs[$attr] !== null){
                if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) || $auth->isAuthorized() ) ){
                    if($test)
                        echo 'Cannot change video  meta!'.EOL;
                    exit(AUTHENTICATION_FAILURE);
                }
            }
        }
    }

    if($inputs['name'] !== null){
        if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_NAME_AUTH) || $auth->isAuthorized() ) ){
            if($test)
                echo 'Cannot change image name!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
    }


    if($inputs['caption'] !== null){
        if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_CAPTION_AUTH) || $auth->isAuthorized() ) ){
            if($test)
                echo 'Cannot change image caption!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
    }

    if($inputs['deleteEmpty']){
        if($inputs['alt'] === null){
            if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_ALT_AUTH) || $auth->isAuthorized() ) ){
                if($test)
                    echo 'Cannot change image alt tag!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
        }

        if($inputs['name'] === null){
            if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_NAME_AUTH) || $auth->isAuthorized() ) ){
                if($test)
                    echo 'Cannot change image name!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
        }

        if($inputs['caption'] === null){
            if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_CAPTION_AUTH) || $auth->isAuthorized() ) ){
                if($test)
                    echo 'Cannot change image caption!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
        }
    }
}
