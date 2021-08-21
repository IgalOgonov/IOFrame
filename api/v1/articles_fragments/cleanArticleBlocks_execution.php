<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result =
    $ArticleHandler->removeOrphanBlocksFromArticle(
        $inputs['articleId'],
        ['test'=>$test]
    );