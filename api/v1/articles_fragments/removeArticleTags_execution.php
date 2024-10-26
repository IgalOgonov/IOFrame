<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result = $ArticleHandler->removeArticleTags(
    $inputs['articleId'],
    $inputs['type'],
    $inputs['tags'],
    ['test'=>$test]
);
