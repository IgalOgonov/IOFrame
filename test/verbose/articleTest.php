<?php
require __DIR__.'/../../IOFrame/Handlers/ArticleHandler.php';

$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

/* ------------------------------------------------------------
                        Gets
 ------------------------------------------------------------ */

echo EOL.'Getting all articles without conditions:'.EOL;
var_dump(
    $ArticleHandler->getItems(
        [],
        'articles',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all articles with conditions:'.EOL;
var_dump(
    $ArticleHandler->getItems(
        [],
        'articles',
        [
            'articleIs'=>1,
            'articleIn'=>[
                1,
                2
            ],
            'titleLike'=>'t',
            'addressLike'=>'t',
            'authIs'=>0,
            'weightIn'=>[
                0,
                1
            ],
            'limit'=>5,
            'offset'=>0,
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting articles with specific tags:'.EOL;
var_dump(
    $ArticleHandler->getItems(
        [],
        'articles',
        [
            'tagsIn'=>[
                [
                    'type'=>'default-article-tags',
                    'name'=>'test-1'
                ],
            ],
            'limit'=>5,
            'offset'=>0,
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting specific articles:'.EOL;
var_dump(
    $ArticleHandler->getItems(
        [[4]],
        'articles',
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);


echo EOL.'Getting all blocks without conditions:'.EOL;
var_dump(
    $ArticleHandler->getItems(
        [],
        'general-block',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting specific article blocks:'.EOL;
var_dump(
    $ArticleHandler->getItems(
        [[4]],
        'general-block',
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);



/* ------------------------------------------------------------
                        Sets
 ------------------------------------------------------------ */
echo EOL.'Creating some articles:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Creator_ID' => 1,
                'Article_Title' => "Test Title",
                'Article_Address' => "test-article",
                'Article_View_Auth' => 0,
                'Article_Text_Content' => json_encode([
                    'caption' => 'This is a really nice caption. Best caption. I know people, and they tell me, my captions are the best. Go ask them!'
                ]),
                'Thumbnail_Address' => 'pluginImages/def_icon.png',
                'Article_Weight' => 1
            ],
        ],
        'articles',
        ['test'=>true,'override'=>false,'verbose'=>true]
    )
);

echo EOL.'Updating some articles:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Creator_ID' => 1,
                'Article_Title' => "Another Test Title",
                'Article_Address' => "custom-link-good-for-seo",
                'Article_View_Auth' => 0,
                'Article_Text_Content' => json_encode([
                    'caption' => 'This is a really nice caption. Best caption. I know people, and they tell me, my captions are the best. Go ask them!'
                ]),
                'Thumbnail_Address' => 'pluginImages/def_thumbnail.png',
                'Article_Weight' => 2
            ],
        ],
        'articles',
        ['test'=>true,'update'=>true,'verbose'=>true]
    )
);

echo EOL.'Creating some markdown blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Meta' => json_encode([
                    'test' => 'test meta'
                ]),
                'Text_Content' => "# test",
            ],
        ],
        'markdown-block',
        ['test'=>true,'override'=>false,'verbose'=>true]
    )
);

echo EOL.'Updating some markdown blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Block_ID' => 1,
                'Meta' => json_encode([
                    'test' => 'test meta'
                ]),
                'Text_Content' => "# test 2",
            ],
        ],
        'markdown-block',
        ['test'=>true,'update'=>true,'verbose'=>true]
    )
);

echo EOL.'Creating some image blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Meta' => json_encode([
                    'test' => 'test meta'
                ]),
                'Resource_Address' => "pluginImages/def_icon.png",
            ],
        ],
        'image-block',
        ['test'=>true,'override'=>false,'verbose'=>true]
    )
);

echo EOL.'Updating some image blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Block_ID' => 2,
                'Meta' => json_encode([
                    'test' => 'test meta'
                ]),
                'Resource_Address' => "pluginImages/def_thumbnail.png",
            ],
        ],
        'image-block',
        ['test'=>true,'update'=>true,'verbose'=>true]
    )
);

echo EOL.'Creating some gallery blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Meta' => json_encode([
                    'test' => 'test meta'
                ]),
                'Collection_Name' => "Test Gallery",
            ],
        ],
        'gallery-block',
        ['test'=>true,'override'=>false,'verbose'=>true]
    )
);

echo EOL.'Updating some gallery blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Block_ID' => 3,
                'Meta' => json_encode([
                    'test' => 'testier meta'
                ]),
                'Collection_Name' => "Test Gallery",
            ],
        ],
        'gallery-block',
        ['test'=>true,'update'=>true,'verbose'=>true]
    )
);

echo EOL.'Creating some youtube blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Meta' => json_encode([
                    'test' => 'test youtube meta'
                ]),
                'Text_Content' => "dQw4w9WgXcQ",
            ],
        ],
        'youtube-block',
        ['test'=>true,'override'=>false,'verbose'=>true]
    )
);

echo EOL.'Updating some youtube blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Block_ID' => 4,
                'Meta' => json_encode([
                    'test' => 'testier youtube meta'
                ]),
                'Text_Content' => "dQw4w9WgXcQ",
            ],
        ],
        'youtube-block',
        ['test'=>true,'update'=>true,'verbose'=>true]
    )
);

echo EOL.'Creating some article blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Meta' => json_encode([
                    'test' => 'test article meta'
                ]),
                'Other_Article_ID' => 2,
            ],
        ],
        'article-block',
        ['test'=>true,'override'=>false,'verbose'=>true]
    )
);

echo EOL.'Updating some article blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Block_ID' => 5,
                'Meta' => json_encode([
                    'test' => 'testier article meta'
                ]),
                'Other_Article_ID' => 1,
            ],
        ],
        'article-block',
        ['test'=>true,'update'=>true,'verbose'=>true]
    )
);

echo EOL.'Creating some cover blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Meta' => json_encode([
                    'test' => 'test cover meta'
                ]),
                'Resource_Address' => "pluginImages/def_icon.png",
            ],
        ],
        'cover-block',
        ['test'=>true,'override'=>false,'verbose'=>true]
    )
);

echo EOL.'Updating some cover blocks:'.EOL;
var_dump(
    $ArticleHandler->setItems(
        [
            [
                'Article_ID' => 1,
                'Block_ID' => 6,
                'Meta' => json_encode([
                    'test' => 'testier cover meta'
                ]),
                'Resource_Address' => "pluginImages/def_thumbnail.png",
            ],
        ],
        'cover-block',
        ['test'=>true,'update'=>true,'verbose'=>true]
    )
);

/* ------------------------------------------------------------
                        Deletes
 ------------------------------------------------------------ */

echo EOL.'Hiding some articles:'.EOL;
var_dump(
    $ArticleHandler->hideArticles(
        [
            1,2
        ],
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Deleting some articles:'.EOL;
var_dump(
    $ArticleHandler->deleteItems(
        [
            ['Article_ID' => 1],
            ['Article_ID' => 2],
        ],
        'articles',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Deleting some article blocks:'.EOL;
var_dump(
    $ArticleHandler->deleteItems(
        [
            ['Article_ID' => 1,'Block_ID'=>1],
            ['Article_ID' => 1,'Block_ID'=>2],
            ['Article_ID' => 1,'Block_ID'=>3],
            ['Article_ID' => 1,'Block_ID'=>4],
            ['Article_ID' => 1,'Block_ID'=>5],
            ['Article_ID' => 1,'Block_ID'=>6],
            ['Article_ID' => 2,'Block_ID'=>1],
        ],
        'general-block',
        ['test'=>true,'verbose'=>true]
    )
);

/* ------------------------------------------------------------
                        Extra
 ------------------------------------------------------------ */


echo EOL.'Getting all articles of user 1:'.EOL;
var_dump(
    $ArticleHandler->getUserArticles(
        1,
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting specific articles of user 1:'.EOL;
var_dump(
    $ArticleHandler->getUserArticles(
        1,
        [
            'requiredArticles' => [1,2,3,4],
            'test'=>true,
            'verbose'=>true
        ]
    )
);


echo EOL.'Adding blocks (some fake ones too) to article 1 order'.EOL;
var_dump(
    $ArticleHandler->addBlocksToArticle(
        1,
        [ 0 => 1,1 => 2,2 => 3,3 => 4,4 => 5,5 => [6,6,5,7]],
        ['test'=>true]
    )
);


echo EOL.'Moving block in article 1 order'.EOL;
var_dump(
    $ArticleHandler->moveBlockInArticle(
        1,
        0,
        5,
        ['test'=>true]
    )
);


echo EOL.'Deleting blocks in article 1 order'.EOL;
var_dump(
    $ArticleHandler->removeBlocksFromArticle(
        1,
        [1,3,5,6],
        ['test'=>true]
    )
);


echo EOL.'Deleting orphan blocks in article 1 order'.EOL;
var_dump(
    $ArticleHandler->removeOrphanBlocksFromArticle(
        1,
        ['test'=>true]
    )
);

