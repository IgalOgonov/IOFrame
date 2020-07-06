<?php
if(!defined('validator'))
    require __DIR__ . '/../../IOFrame/Util/validator.php';

//TODO Check whether this item can be modified via individual auth, then check individual auth
if(false){

}
else{

    if($inputs['alt'] !== null){
        if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_ALT_AUTH) || $auth->isAuthorized(0) ) ){
            if($test)
                echo 'Cannot change image alt tag!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
    }

    if($inputs['name'] !== null){
        if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_NAME_AUTH) || $auth->isAuthorized(0) ) ){
            if($test)
                echo 'Cannot change image name!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
    }


    if($inputs['caption'] !== null){
        if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_CAPTION_AUTH) || $auth->isAuthorized(0) ) ){
            if($test)
                echo 'Cannot change image caption!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
    }

    if($inputs['deleteEmpty']){
        if($inputs['alt'] === null){
            if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_ALT_AUTH) || $auth->isAuthorized(0) ) ){
                if($test)
                    echo 'Cannot change image alt tag!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
        };

        if($inputs['name'] === null){
            if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_NAME_AUTH) || $auth->isAuthorized(0) ) ){
                if($test)
                    echo 'Cannot change image name!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
        };

        if($inputs['caption'] === null){
            if( !( $auth->hasAction(IMAGE_UPDATE_AUTH) ||  $auth->hasAction(IMAGE_CAPTION_AUTH) || $auth->isAuthorized(0) ) ){
                if($test)
                    echo 'Cannot change image caption!'.EOL;
                exit(AUTHENTICATION_FAILURE);
            }
        };
    }
}
