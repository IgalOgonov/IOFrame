<?php
if(!isset($FrontEndResources))
    $FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);
$IOFrameImgRoot = 'front/ioframe/img/';
$IOFrameVidRoot = 'front/ioframe/vid/';

echo '---------------------------------------------'.EOL.EOL;
echo '---------------- IMAGES ----------------'.EOL.EOL;
echo '---------------------------------------------'.EOL.EOL;
echo 'Creating test gallery'.EOL;
var_dump(
    $FrontEndResources->setGallery('Test Gallery',null,['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameImgRoot])
);
echo EOL.EOL;

echo 'Updating test gallery with a name'.EOL;
var_dump(
    $FrontEndResources->setGallery(
        'Test Gallery',
        json_encode(['name'=>'Awesome Gallery']),
        ['test'=>true,'verbose'=>true,'update'=>true,'rootFolder'=>$IOFrameImgRoot]
    )
);
echo EOL.EOL;

echo 'Updating test gallery with a tea color'.EOL;
var_dump(
    $FrontEndResources->setGallery(
        'Test Gallery',
        json_encode(['tea color'=>'Green']),
        ['test'=>true,'verbose'=>true,'update'=>true,'rootFolder'=>$IOFrameImgRoot]
    )
);
echo EOL.EOL;

echo 'Getting plugins folder and a single image as well'.EOL;
var_dump(
    $FrontEndResources->getImages(
        ['pluginImages/def_icon.png','pluginImages'],
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameImgRoot]
    )
);
echo EOL.EOL;

echo 'Setting image name and alt tag:'.EOL;
var_dump(
    $FrontEndResources->setResources(
        [
            [
                'address'=>'pluginImages/def_icon.png',
                'local'=>false,
                'minified'=>false,
                'text'=>json_encode(['alt'=>'Alternative Title','name'=>'Prettier Name'])
            ]
        ],
        'img',
        ['test'=>true,'verbose'=>true,'update'=>true,'rootFolder'=>$IOFrameImgRoot])
);
echo EOL.EOL;

echo 'Changing image name:'.EOL;
var_dump(
    $FrontEndResources->setResources(
        [
            [
                'address'=>'pluginImages/def_icon.png',
                'local'=>false,
                'minified'=>false,
                'text'=>json_encode(['name'=>'Prettier Name'])
            ]
        ],
        'img',
        ['test'=>true,'verbose'=>true,'update'=>true,'rootFolder'=>$IOFrameImgRoot])
);
echo EOL.EOL;

echo 'Adding single resource and a folder to gallery'.EOL;
var_dump(
    $FrontEndResources->addImagesToGallery(
        ['pluginImages/def_icon.png','pluginImages'],
        'Test Gallery',
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameImgRoot]
    )
);
echo EOL.EOL;


echo 'Getting REMOTE media files:'.EOL;
var_dump(
    $FrontEndResources->getImages([],['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameImgRoot])
);
echo EOL.EOL;

echo 'Getting ALL LOCAL media files:'.EOL;
var_dump(
    $FrontEndResources->getImages([''],['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameImgRoot,'includeChildFolders'=>true,'includeChildFiles'=>true])
);
echo EOL.EOL;


echo 'Getting all galleries:'.EOL;
var_dump(
    $FrontEndResources->getGalleries([],['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameImgRoot])
);
echo EOL.EOL;


echo 'Getting test gallery:'.EOL;
var_dump(
    $FrontEndResources->getGallery('Test Gallery',['test'=>true,'verbose'=>true,'includeGalleryInfo'=>true,'rootFolder'=>$IOFrameImgRoot])
);
echo EOL.EOL;


echo 'Getting fake gallery:'.EOL;
var_dump(
    $FrontEndResources->getGallery('fake Gallery',['test'=>true,'verbose'=>true,'includeGalleryInfo'=>true,'rootFolder'=>$IOFrameImgRoot])
);
echo EOL.EOL;


echo 'Getting fake gallery:'.EOL;
var_dump(
    $FrontEndResources->getGallery('fake Gallery',['test'=>true,'verbose'=>true,'includeGalleryInfo'=>true,'rootFolder'=>$IOFrameImgRoot])
);
echo EOL.EOL;


echo 'Getting two real galleries AND their members:'.EOL;
var_dump(
    $FrontEndResources->getGalleries(['Test Gallery','Another Gallery'],['test'=>true,'verbose'=>true,'includeGalleryInfo'=>true,'rootFolder'=>$IOFrameImgRoot])
);
echo EOL.EOL;

echo '---------------------------------------------'.EOL.EOL;
echo '---------------- VIDEOS ----------------'.EOL.EOL;
echo '---------------------------------------------'.EOL.EOL;
echo 'Creating test video gallery'.EOL;
var_dump(
    $FrontEndResources->setVideoGallery('Test Video Gallery',null,['test'=>false,'verbose'=>true,'rootFolder'=>$IOFrameVidRoot])
);
echo EOL.EOL;
echo 'Getting video examples folder and the files inside'.EOL;
var_dump(
    $FrontEndResources->getVideos(
        ['examples'],
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameVidRoot,'includeChildFiles'=>true]
    )
);
echo EOL.EOL;

echo 'Setting image name and alt tag:'.EOL;
var_dump(
    $FrontEndResources->setResources(
        [
            [
                'address'=>'examples/example-1.webm',
                'local'=>true,
                'minified'=>false,
                'text'=>json_encode(['name'=>'Interesting video','autoplay'=>true,'loop'=>true,'mute'=>true])
            ]
        ],
        'vid',
        ['test'=>true,'verbose'=>true,'update'=>true,'rootFolder'=>$IOFrameVidRoot])
);
echo EOL.EOL;

echo 'Adding 2 example videos to gallery'.EOL;
var_dump(
    $FrontEndResources->addVideosToVideoGallery(
        ['examples/example-1.webm','examples/example-2.webm'],
        'Test Video Gallery',
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameVidRoot]
    )
);
echo EOL.EOL;

echo 'Getting REMOTE video files:'.EOL;
var_dump(
    $FrontEndResources->getVideos([],['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameVidRoot])
);
echo EOL.EOL;

echo 'Getting ALL LOCAL video files:'.EOL;
var_dump(
    $FrontEndResources->getVideos([''],['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameVidRoot,'includeChildFolders'=>true,'includeChildFiles'=>true])
);
echo EOL.EOL;


echo 'Getting all video galleries:'.EOL;
var_dump(
    $FrontEndResources->getVideoGalleries([],['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameVidRoot])
);
echo EOL.EOL;


echo 'Getting test video gallery:'.EOL;
var_dump(
    $FrontEndResources->getVideoGallery('Test Video Gallery',['test'=>true,'verbose'=>true,'includeGalleryInfo'=>true,'rootFolder'=>$IOFrameVidRoot])
);
echo EOL.EOL;

