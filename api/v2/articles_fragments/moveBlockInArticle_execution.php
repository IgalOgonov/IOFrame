<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result =
    $ArticleHandler->moveBlockInArticle(
        $inputs['id'],
        $inputs['from'],
        $inputs['to'],
        ['test'=>$test]
    );
$result = ['response'=>$result];