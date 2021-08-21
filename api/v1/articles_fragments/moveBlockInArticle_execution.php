<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result =
    $ArticleHandler->moveBlockInArticle(
        $inputs['articleId'],
        $inputs['from'],
        $inputs['to'],
        ['test'=>$test]
    );