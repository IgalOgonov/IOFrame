<?php

require_once __DIR__.'/../../IOFrame/Handlers/TokenHandler.php';
$TokenHandler = new \IOFrame\Handlers\TokenHandler($settings,$defaultSettingsParams);
echo 'Setting tokens test1, test2 and test3:'.EOL;
var_dump($TokenHandler->setTokens(
    [
        'test1' => ['action'=>'test action 1','uses'=>10,'ttl'=>4000,'tags'=>['test_tag_1','test_tag_2']],
        'test2' => ['action'=>'test action 2','uses'=>1],
        'test3' => ['action'=>'test action 3','ttl'=>4500,'tags'=>['test_tag_3','test_tag_2']]
    ],
    ['test'=>true,'verbose'=>true,'overwrite'=>true]
));
echo EOL;

echo 'getting all tokens'.EOL;
var_dump($TokenHandler->getTokens(
    [],
    ['test'=>true,'verbose'=>true]));
echo EOL;

echo 'getting all tokens with conditions'.EOL;
var_dump($TokenHandler->getTokens(
    [],
    [
        'tokenLike'=>'test',
        'actionLike'=>'test',
        'usesAtLeast'=>1,
        'usesAtMost'=>9,
        'expiresBefore'=>1600000000,
        'expiresAfter'=>1400000000,
        'ignoreExpired'=>false,
        'test'=>true,
        'verbose'=>true
    ]
));
echo EOL;

echo 'getting all tokens with correct tags test_tag_1'.EOL;
var_dump($TokenHandler->getTokens(
    [],
    [
        'containsTags'=>['test_tag_1'],
        'test'=>true,
        'verbose'=>true
    ]
));
echo EOL;

echo 'getting all tokens with correct tags test_tag_1,test_tag_2'.EOL;
var_dump($TokenHandler->getTokens(
    [],
    [
        'containsTags'=>['test_tag_1','test_tag_2'],
        'test'=>true,
        'verbose'=>true
    ]
));
echo EOL;


echo 'getting tokens test1, test2 and test3:'.EOL;
var_dump($TokenHandler->getTokens(
    ['test2','test1','test3'],
    ['test'=>true,'verbose'=>true]));
echo EOL;

echo 'deleting tokens test1, test2'.EOL;
var_dump($TokenHandler->deleteTokens(
    ['test1', 'test2'],
    [
        'test'=>true,
        'verbose'=>true
    ]
));
echo EOL;

echo 'deleting expired tokens with no time'.EOL;
var_dump($TokenHandler->deleteExpiredTokens(
    [
        'test'=>true,
        'verbose'=>true
    ]
));
echo EOL;

echo 'consumeTokens tokens test1, test2 and test3:'.EOL;
var_dump($TokenHandler->consumeTokens(
    [
        'test1' => ['uses'=>5,'action'=>'test action 1'],
        'test2' => ['uses'=>5],
        'test3' => ['action'=>'n 3$']
    ],
    ['test'=>true,'verbose'=>true]
));