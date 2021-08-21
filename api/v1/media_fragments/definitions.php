<?php

/* AUTH */
//Allow image upload
CONST IMAGE_UPLOAD_AUTH = 'IMAGE_UPLOAD_AUTH';
//Allow choosing image filename on upload
CONST IMAGE_FILENAME_AUTH = 'IMAGE_FILENAME_AUTH';
//Allow overwriting existing images (has to have filename auth, obviously)
CONST IMAGE_OVERWRITE_AUTH = 'IMAGE_OVERWRITE_AUTH';
//Allows getting all images (and each individual one)
CONST IMAGE_GET_ALL_AUTH = 'IMAGE_GET_ALL_AUTH';
//Allow unlimited image updating (both alt tag and name)
CONST IMAGE_UPDATE_AUTH = 'IMAGE_UPDATE_AUTH';
//Allow unlimited image alt tag changing
CONST IMAGE_ALT_AUTH = 'IMAGE_ALT_AUTH';
//Allow unlimited image name changing
CONST IMAGE_NAME_AUTH = 'IMAGE_NAME_AUTH';
//Allow unlimited image name changing
CONST IMAGE_CAPTION_AUTH = 'IMAGE_CAPTION_AUTH';
//Allow unlimited image moving (local for now)
CONST IMAGE_MOVE_AUTH = 'IMAGE_MOVE_AUTH';
//Allow unlimited image deletion
CONST IMAGE_DELETE_AUTH = 'IMAGE_DELETE_AUTH';
//Allow unlimited image version incrementation
CONST IMAGE_INCREMENT_AUTH = 'IMAGE_INCREMENT_AUTH';
//Allows getting all galleries (and each individual one)
CONST GALLERY_GET_ALL_AUTH = 'GALLERY_GET_ALL_AUTH';
//Allow unlimited gallery creation
CONST GALLERY_CREATE_AUTH = 'GALLERY_CREATE_AUTH';
//Allow unlimited gallery updating - includes adding/removing media to/from gallery
CONST GALLERY_UPDATE_AUTH = 'GALLERY_UPDATE_AUTH';
//Allow unlimited gallery deletion
CONST GALLERY_DELETE_AUTH = 'GALLERY_DELETE_AUTH';
//Allow image upload
CONST MEDIA_FOLDER_CREATE_AUTH = 'MEDIA_FOLDER_CREATE_AUTH';
/*Object Auth object type for media*/
CONST OBJECT_AUTH_TYPE = 'media';

/* Validation */
//Allowed Regex filter regex
CONST RESOURCE_TYPE_REGEX = '^[a-zA-Z][\w]{0,63}$';
//Allowed Regex filter regex
CONST REGEX_REGEX = '^[\w\.\-\_ ]{1,128}$';
//Regex to validate gallery name
CONST GALLERY_REGEX = '^[\w \/]{1,128}$';
//Regex to validate upload name
CONST UPLOAD_NAME_REGEX = '^[\w \/]{1,64}$';
//Regex to validate upload name
CONST UPLOAD_FILENAME_REGEX = '^[\w\_\-\. ]{1,128}$';
//Regex to validate upload name
CONST DATA_TYPE_REGEX = '^\w+\/[-.\w]+(?:\+[-.\w]+)?$';
//Maximum length for image alt
CONST IMAGE_ALT_MAX_LENGTH = 128;
//RMaximum length for image name
CONST IMAGE_NAME_MAX_LENGTH = 128;
//RMaximum length for image name
CONST IMAGE_CAPTION_MAX_LENGTH = 1024;
//RMaximum length for gallery name
CONST GALLERY_NAME_MAX_LENGTH = 128;
//Various extensions
CONST ALLOWED_EXTENSIONS_IMAGES = ['jpg','jpeg','png','gif','bmp','svg','webp'];
CONST ALLOWED_EXTENSIONS_AUDIO = ['ogg','mp3','wav','webm'];
CONST ALLOWED_EXTENSIONS_VIDEO = ['mp4','webm','ogg'];
CONST ALLOWED_EXTENSIONS = ['jpg','jpeg','png','gif','bmp','svg','ogg','mp3','wav','mp4','webm'];






