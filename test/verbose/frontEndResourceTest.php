<?php

$FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($settings,$defaultSettingsParams);
$IOFrameJSRoot = 'front/ioframe/js/';
$IOFrameCSSRoot = 'front/ioframe/css/';

echo 'Getting ALL js files in the root folder (and remote ones):'.EOL;
var_dump(
    $FrontEndResources->getJS([],['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameJSRoot])
);
echo EOL;

echo 'Getting existing JS files, some in the DB some not:'.EOL;
var_dump(
    $FrontEndResources->getJS(
        ['config.js','fp.js','initPage.js','sec/aes.js'],
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameJSRoot]
    )
);
echo EOL;

echo 'Getting existing JS files and folders, none in the db:'.EOL;
var_dump(
    $FrontEndResources->getJS(
        ['config.js','fp.js','initPage.js','modules'],
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameJSRoot]
    )
);
echo EOL;

echo 'Getting only fake JS files:'.EOL;
var_dump(
    $FrontEndResources->getJS(
        ['fake1.js','fake2.js'],
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameJSRoot]
    )
);
echo EOL;

echo 'Moving JS files, some exist (in filesystem/db), some dont'.EOL;
var_dump(
    $FrontEndResources->moveJSFiles(
        [
            ['config.js','test/config.js'],
            ['fp.js','test/fp.js'],
            ['fake.js','stillFake.js'],
        ],
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameJSRoot]
    )
);
echo EOL;

echo 'Deleting JS files, some exist (in filesystem/db), some dont'.EOL;
var_dump(
    $FrontEndResources->deleteJSFiles(
        [
            'config.js',
            'crypto/md5.js',
            'fake.js',
        ],
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameJSRoot]
    )
);
echo EOL;

echo 'Minifying local JS files, some exist, some dont - no common name'.EOL;
var_dump(
    $FrontEndResources->minifyJSFiles(
        [
            'config.js',
            'crypto/md5.js',
            'fake.js',
        ],
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameJSRoot]
    )
);
echo EOL;

echo 'Minifying local JS files, some exist, some dont - to folder min under name exampleMin'.EOL;
var_dump(
    $FrontEndResources->minifyJSFiles(
        [
            'crypto/md5.js',
            'crypto/sha1.js',
            'fake.js',
        ],
        ['test'=>true,'verbose'=>true,'minifyToFolder'=>'min','minifyName'=>'exampleMin']
    )
);
echo EOL;

echo 'Minifying local JS files, all fake - to folder min under name exampleMin'.EOL;
var_dump(
    $FrontEndResources->minifyJSFiles(
        [
            'fake.js',
            'fake2.js',
        ],
        ['test'=>true,'verbose'=>true,'minifyToFolder'=>'min','minifyName'=>'exampleMin']
    )
);
echo EOL;

echo 'Getting JS collection IOFrameCoreJS'.EOL;
var_dump(
    $FrontEndResources->getJSCollections(
        [
            'IOFrameCoreJS'
        ],
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameJSRoot]
    )
);
echo EOL;

echo 'Minifying local CSS files, some exist, some dont'.EOL;
var_dump(
    $FrontEndResources->minifyCSSFiles(
        [
            'components/media/mediaViewer.css',
            'fake.css',
        ],
        ['test'=>false,'verbose'=>true]
    )
);
echo EOL;

echo 'Compiling fake SCSS to folder'.EOL;
var_dump(
    $FrontEndResources->compileSCSS(
        'fake.scss',
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameCSSRoot]
    )
);
echo EOL;

echo 'Compiling SCSS'.EOL;
var_dump(
    $FrontEndResources->compileSCSS(
        'test.scss',
        ['test'=>true,'verbose'=>true,'rootFolder'=>$IOFrameCSSRoot]
    )
);
echo EOL;

echo 'Compiling SCSS to folder'.EOL;
var_dump(
    $FrontEndResources->compileSCSS(
        'test.scss',
        ['test'=>true,'verbose'=>true,'compileToFolder'=>'scss','rootFolder'=>$IOFrameCSSRoot]
    )
);
echo EOL;

echo 'Getting SCSS, CSS, and a folder that contains both'.EOL;
var_dump(
    $FrontEndResources->getCSS(
        ['test.scss','test'],
        ['test'=>true,'verbose'=>true,'compileToFolder'=>'scss','rootFolder'=>$IOFrameCSSRoot]
    )
);
echo EOL;