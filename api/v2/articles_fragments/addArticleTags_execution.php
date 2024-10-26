<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result = $ArticleHandler->setArticleTags(
    $inputs['id'],
    $inputs['type'],
    $inputs['tags'],
    ['test'=>$test]
);
$result = [
    'response'=>$result
];