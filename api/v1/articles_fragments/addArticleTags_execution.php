<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result = $ArticleHandler->setArticleTags(
    $inputs['articleId'],
    $inputs['type'],
    $inputs['tags'],
    ['test'=>$test]
);
