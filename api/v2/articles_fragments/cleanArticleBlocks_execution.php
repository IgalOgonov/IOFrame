<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result =
    $ArticleHandler->removeOrphanBlocksFromArticle(
        $inputs['id'],
        ['test'=>$test]
    );
$result = ['response'=>$result];