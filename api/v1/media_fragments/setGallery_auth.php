<?php

//Gallery
//TODO Check gallery auth and ownership, THEN image ownership
if(false){
}
else{

    //One type of auth to overwrite existing galleries..
    if( $inputs['update']  || $inputs['overwrite'] ){
        if( !( $auth->hasAction(GALLERY_UPDATE_AUTH) || $auth->isAuthorized() ) ){
            if($test)
                echo 'Cannot update/overwrite existing galleries!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
    }

    //..Another to just create new ones
    if( !$inputs['update'] ){
        if( !( $auth->hasAction(GALLERY_CREATE_AUTH) || $auth->isAuthorized() ) ){
            if($test)
                echo 'Cannot create new galleries galleries!'.EOL;
            exit(AUTHENTICATION_FAILURE);
        }
    }

    if( !( $auth->hasAction(IMAGE_UPLOAD_AUTH) || $auth->isAuthorized() ) ){
        if($test)
            echo 'Cannot upload images'.EOL;
        exit(AUTHENTICATION_FAILURE);
    }

}






