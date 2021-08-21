<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('ArticleHandler',true);
    if(!defined('abstractObjectsHandler'))
        require 'abstractObjectsHandler.php';

    /** The article handler is meant to handle block-based articles.
     *  Not much to write here, most of the explanation is in the api.
     *  WARNING:
     *    Both articles and blocks may take up to 1 hour to sync with changes of related tables (resources, contacts, etc)
     *    due to cache. Do not count on changes being through different handlers to those resources reflecting here at once.
     *    Still, in most cases those resources don't change (who suddenly changes their name? Or uploads a different
     *    image to the same address?).
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class ArticleHandler extends IOFrame\abstractObjectsHandler{


        /** @var array $validBlockTypes Array of valid block types - those are the objects under an article.
         * */
        private $validBlockTypes = [];

        /** @var array $articleCacheName String cachename for a full article (this object is the article, and all its blocks)
         * */
        private $articleCacheName = 'full_article_';

        /**
         * Basic construction function
         * @param SettingsHandler $settings local settings handler.
         * @param array $params Typical default settings array
         */
        function __construct(SettingsHandler $settings, $params = []){

            //Types of blocks - 'general-block' just refers to getting blocks in general, and has no set columns.
            $this->validBlockTypes = ['general-block','markdown-block','image-block','cover-block','gallery-block','video-block',
                'youtube-block','article-block'];
            //The first type is the main articles table. The other tables are for individual blocks.
            $this->validObjectTypes = array_merge(['articles'],$this->validBlockTypes);

            $this->objectsDetails = [
                'articles' => [
                    'tableName' => 'ARTICLES',
                    'joinOnGet' => [
                        [
                            'tableName' => 'RESOURCES',
                            'on' => [
                                ['Thumbnail_Resource_Type','Resource_Type'],
                                ['Thumbnail_Address','Address'],
                            ],
                        ],
                        [
                            'tableName'=> 'CONTACTS',
                            'on' => [
                                [['user','STRING'],'Contact_Type'],
                                ['Creator_ID','Identifier'],
                            ],
                        ]
                    ],
                    'columnsToGet' => [
                        [
                            'tableName' => 'RESOURCES',
                            'column' => 'Resource_Local',
                            'as' => 'Thumbnail_Local'
                        ],
                        [
                            'tableName' => 'RESOURCES',
                            'column' => 'Data_Type'
                        ],
                        [
                            'tableName' => 'RESOURCES',
                            'column' => 'Text_Content',
                            'as' => 'Thumbnail_Meta'
                        ],
                        [
                            'tableName' => 'RESOURCES',
                            'column' => 'Last_Updated',
                            'as' => 'Thumbnail_Last_Updated'
                        ],
                        [
                            'tableName' => 'CONTACTS',
                            'column' => 'First_Name',
                            'as' => 'Creator_First_Name'
                        ],
                        [
                            'tableName' => 'CONTACTS',
                            'column' => 'Last_Name',
                            'as' => 'Creator_Last_Name'
                        ]
                    ],
                    'extendTTL' => false,
                    'cacheName' => 'article_',
                    'childCache' => ['article_blocks_'],
                    'keyColumns' => ['Article_ID'],
                    'safeStrColumns' => ['Thumbnail_Meta'],
                    'setColumns' => [
                        'Article_ID' => [
                            'type' => 'int',
                            'autoIncrement' => true
                        ],
                        'Creator_ID' => [
                            'type' => 'int'
                        ],
                        'Article_Title' => [
                            'type' => 'string'
                        ],
                        'Article_Address' => [
                            'type' => 'string'
                        ],
                        'Article_Language' => [
                            'type' => 'string',
                            'default' => null,
                            'considerNull' => '@'
                        ],
                        'Article_View_Auth' => [
                            'type' => 'int',
                            'default' => 2
                        ],
                        'Article_Text_Content' => [
                            'type' => 'string',
                            'jsonObject' => true,
                            'default' => null
                        ],
                        'Thumbnail_Address' => [
                            'type' => 'string',
                            'default' => null,
                            'considerNull' => '@'
                        ],
                        'Block_Order' => [
                            'type' => 'string',
                            'default' => ''
                        ],
                        'Article_Weight' => [
                            'type' => 'int',
                            'default' => 0
                        ],
                    ],
                    'moveColumns' => [
                    ],
                    'columnFilters' => [
                        'articleIs' => [
                            'column' => 'Article_ID',
                            'filter' => '='
                        ],
                        'articleIn' => [
                            'column' => 'Article_ID',
                            'filter' => 'IN'
                        ],
                        'creatorIs' => [
                            'column' => 'Creator_ID',
                            'filter' => '='
                        ],
                        'creatorIn' => [
                            'column' => 'Creator_ID',
                            'filter' => 'IN'
                        ],
                        'titleLike' => [
                            'column' => 'Article_Title',
                            'filter' => 'RLIKE'
                        ],
                        'addressIs' => [
                            'column' => 'Article_Address',
                            'filter' => '='
                        ],
                        'addressIn' => [
                            'column' => 'Article_Address',
                            'filter' => 'IN'
                        ],
                        'languageIs' => [
                            'column' => 'Article_Language',
                            'filter' => '=',
                            'considerNull' => '@'
                        ],
                        'addressLike' => [
                            'column' => 'Article_Address',
                            'filter' => 'RLIKE'
                        ],
                        'authIs' => [
                            'column' => 'Article_View_Auth',
                            'filter' => '='
                        ],
                        'authAtMost' => [
                            'column' => 'Article_View_Auth',
                            'filter' => '<='
                        ],
                        'authIn' => [
                            'column' => 'Article_View_Auth',
                            'filter' => 'IN'
                        ],
                        'weightIs' => [
                            'column' => 'Article_Weight',
                            'filter' => '='
                        ],
                        'weightIn' => [
                            'column' => 'Article_Weight',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                        'Creator_ID' => [
                            'key' => 'creators',
                            'type' => 'distinct'
                        ]
                    ],
                    'orderColumns' => ['Article_ID','Article_Weight'],
                    'autoIncrement'=>true
                ]
            ];

            $commonBlocksTable = 'ARTICLE_BLOCKS';
            $commonBlocksCache = 'article_blocks_';
            $commonBlocksFatherDetails =[
                [
                    'tableName' => 'ARTICLES',
                    'cacheName' => 'article_'
                ]
            ];
            $commonBlocksKeys = ['Article_ID'];
            $commonBlocksSafeStrColumns = ['Text_Content','Meta','Resource_Collection_Meta','Resource_Text_Content','Thumbnail_Text_Content'];
            $commonBlocksExtraKeys = ['Block_ID'];
            $commonBlockJoins = [
                [
                    'tableName' => ['RESOURCES','res1'],
                    'on' => [
                        ['Resource_Type','Resource_Type'],
                        ['Resource_Address','Address'],
                    ],
                ],
                [
                    'tableName' => 'RESOURCE_COLLECTIONS',
                    'on' => [
                        ['Resource_Type','Resource_Type'],
                        ['Collection_Name','Collection_Name'],
                    ],
                ],
                [
                    'tableName' => 'ARTICLES',
                    'on' => [
                        ['Other_Article_ID','Article_ID']
                    ],
                ],
                [
                    'tableName' => ['RESOURCES','res2'],
                    'leftTableName' => 'ARTICLES',
                    'on' => [
                        ['Thumbnail_Resource_Type','Resource_Type'],
                        ['Thumbnail_Address','Address'],
                    ],
                ],
                [
                    'tableName'=> 'CONTACTS',
                    'leftTableName' => 'ARTICLES',
                    'on' => [
                        [['user','STRING'],'Contact_Type'],
                        ['Creator_ID','Identifier'],
                    ],
                ],
            ];
            $commonBlockColumns = [
                [
                    'tableName' => 'res1',
                    'alias'=>true,
                    'column' => 'Resource_Local'
                ],
                [
                    'tableName' => 'res1',
                    'alias'=>true,
                    'column' => 'Data_Type',
                    'as' => 'Resource_Data_Type'
                ],
                [
                    'tableName' => 'res1',
                    'alias'=>true,
                    'column' => 'Text_Content',
                    'as' => 'Resource_Text_Content'
                ],
                [
                    'tableName' => 'res1',
                    'alias'=>true,
                    'column' => 'Last_Updated',
                    'as' => 'Resource_Last_Updated'
                ],
                [
                    'tableName' => 'RESOURCE_COLLECTIONS',
                    'column' => 'Meta',
                    'as' => 'Resource_Collection_Meta'
                ],
                [
                    'tableName' => 'ARTICLES',
                    'column' => 'Article_Title',
                    'as' => 'Other_Article_Title'
                ],
                [
                    'tableName' => 'ARTICLES',
                    'column' => 'Article_Address',
                    'as' => 'Other_Article_Address'
                ],
                [
                    'tableName' => 'ARTICLES',
                    'column' => 'Creator_ID',
                    'as' => 'Other_Article_Creator'
                ],
                [
                    'tableName' => 'ARTICLES',
                    'column' => 'Thumbnail_Resource_Type'
                ],
                [
                    'tableName' => 'ARTICLES',
                    'column' => 'Thumbnail_Address'
                ],
                [
                    'tableName' => 'res2',
                    'alias'=>true,
                    'column' => 'Resource_Local',
                    'as' => 'Thumbnail_Local'
                ],
                [
                    'tableName' => 'res2',
                    'alias'=>true,
                    'column' => 'Last_Updated',
                    'as' => 'Thumbnail_Last_Updated'
                ],
                [
                    'tableName' => 'res2',
                    'alias'=>true,
                    'column' => 'Data_Type',
                    'as' => 'Thumbnail_Data_Type'
                ],
                [
                    'tableName' => 'res2',
                    'alias'=>true,
                    'column' => 'Text_Content',
                    'as' => 'Thumbnail_Text_Content'
                ],
                [
                    'tableName' => 'CONTACTS',
                    'column' => 'First_Name',
                    'as' => 'Other_Article_Creator_First_Name'
                ],
                [
                    'tableName' => 'CONTACTS',
                    'column' => 'Last_Name',
                    'as' => 'Other_Article_Creator_Last_Name'
                ]
            ];
            $commonBlocksFilters =[
                'articleIs' => [
                    'column' => 'Article_ID',
                    'filter' => '='
                ],
                'articleIn' => [
                    'column' => 'Article_ID',
                    'filter' => 'IN'
                ],
            ];
            $commonBlocksOrder =['Article_ID'];
            $commonBlocksSetColumns = [
                'Article_ID' => [
                    'type' => 'int'
                ],
                'Block_ID' => [
                    'type' => 'int',
                    'autoIncrement' => true
                ],
                'Meta' => [
                    'type' => 'string',
                    'default' => null,
                    'jsonObject' => true
                ]
            ];

            foreach($this->validBlockTypes as $blockType){
                switch($blockType){
                    case 'markdown-block':
                        $blockSetColumns = array_merge(
                            $commonBlocksSetColumns,
                            [
                                'Block_Type' => [
                                    'type' => 'string',
                                    'forceValue' => 'markdown'
                                ],
                                'Text_Content' => [
                                    'type' => 'string'
                                ]
                            ]
                        );
                        $blockMoveColumns = [];
                        $blockExtraGetColumns = [];
                        break;
                    case 'image-block':
                        $blockSetColumns = array_merge(
                            $commonBlocksSetColumns,
                            [
                                'Block_Type' => [
                                    'type' => 'string',
                                    'forceValue' => 'image'
                                ],
                                'Resource_Type' => [
                                    'type' => 'string',
                                    'forceValue' => 'img'
                                ],
                                'Resource_Address' => [
                                    'type' => 'string'
                                ]
                            ]
                        );
                        $blockMoveColumns = [];
                        $blockExtraGetColumns = [];
                        break;
                    case 'cover-block':
                        $blockSetColumns = array_merge(
                            $commonBlocksSetColumns,
                            [
                                'Block_Type' => [
                                    'type' => 'string',
                                    'forceValue' => 'cover'
                                ],
                                'Resource_Type' => [
                                    'type' => 'string',
                                    'forceValue' => 'img'
                                ],
                                'Resource_Address' => [
                                    'type' => 'string'
                                ]
                            ]
                        );
                        $blockMoveColumns = [];
                        $blockExtraGetColumns = [];
                        break;
                    case 'gallery-block':
                        $blockSetColumns = array_merge(
                            $commonBlocksSetColumns,
                            [
                                'Block_Type' => [
                                    'type' => 'string',
                                    'forceValue' => 'gallery'
                                ],
                                'Resource_Type' => [
                                    'type' => 'string',
                                    'forceValue' => 'img'
                                ],
                                'Collection_Name' => [
                                    'type' => 'string'
                                ]
                            ]
                        );
                        $blockMoveColumns = [];
                        $blockExtraGetColumns = [];
                        break;
                    case 'video-block':
                        $blockSetColumns = array_merge(
                            $commonBlocksSetColumns,
                            [
                                'Block_Type' => [
                                    'type' => 'string',
                                    'forceValue' => 'video'
                                ],
                                'Resource_Type' => [
                                    'type' => 'string',
                                    'forceValue' => 'vid'
                                ],
                                'Resource_Address' => [
                                    'type' => 'string'
                                ]
                            ]
                        );
                        $blockMoveColumns = [];
                        $blockExtraGetColumns = [];
                        break;
                    case 'youtube-block':
                        $blockSetColumns = array_merge(
                            $commonBlocksSetColumns,
                            [
                                'Block_Type' => [
                                    'type' => 'string',
                                    'forceValue' => 'youtube'
                                ],
                                'Text_Content' => [
                                    'type' => 'string'
                                ]
                            ]
                        );
                        $blockMoveColumns = [];
                        $blockExtraGetColumns = [];
                        break;
                    case 'article-block':
                        $blockSetColumns = array_merge(
                            $commonBlocksSetColumns,
                            [
                                'Block_Type' => [
                                    'type' => 'string',
                                    'forceValue' => 'article'
                                ],
                                'Other_Article_ID' => [
                                    'type' => 'int'
                                ]
                            ]
                        );
                        $blockMoveColumns = [];
                        $blockExtraGetColumns = [];
                        break;
                    case 'general-block':
                    default:
                        $blockSetColumns = [];
                        $blockMoveColumns = [];
                        $blockExtraGetColumns = [];
                }

                $this->objectsDetails[$blockType] = [
                    'tableName' => $commonBlocksTable,
                    'extendTTL' => false,
                    'cacheName' => $commonBlocksCache,
                    'fatherDetails' => $commonBlocksFatherDetails,
                    'keyColumns' => $commonBlocksKeys,
                    'safeStrColumns' => $commonBlocksSafeStrColumns,
                    'extraKeyColumns' =>$commonBlocksExtraKeys,
                    'joinOnGet' => $commonBlockJoins,
                    'columnsToGet' => $commonBlockColumns,
                    'setColumns' => $blockSetColumns,
                    'moveColumns' => $blockMoveColumns,
                    'columnFilters' => $commonBlocksFilters,
                    'extraToGet' => $blockExtraGetColumns,
                    'orderColumns' => $commonBlocksOrder,
                    'groupByFirstNKeys'=>1,
                    'autoIncrement'=>true
                ];
            }

            parent::__construct($settings,$params);
        }


        /** Get articles of a specific owner, without getting a lot of unrelated info as opposed to getItems.
         *  However, unlike getItems, this cannot use cache either.
         *
         * @param int $userID
         * @param array $params
         *              'requiredArticles' => int[], default [] - if set, limites the search to those articles.
         * @returns int[]|int Array of article IDs the user owns, OR code -1 if the database connection failed.
         */
        function getUserArticles(int $userID, array $params = []){

            $requiredArticles = isset($params['requiredArticles'])? $params['requiredArticles'] : [];

            $prefix = $this->SQLHandler->getSQLPrefix();

            if(count($requiredArticles)){
                array_push($requiredArticles,'CSV');
            }

            $userCondition = [
                'Creator_ID',
                $userID,
                '='
            ];

            $articlesCondition = [
                'Article_ID',
                $requiredArticles,
                'IN'
            ];

            //ObjectUsers conditions
            $conditions = [ $userCondition ];

            if(count($requiredArticles))
                array_push($conditions,$articlesCondition);

            array_push($conditions,'AND');

            $res = $this->SQLHandler->selectFromTable(
                $prefix.$this->objectsDetails['articles']['tableName'],
                $conditions,
                ['Article_ID'],
                $params
            );

            if(!is_array($res))
                $res = -1;
            else
                foreach($res as $index=>$arr)
                    $res[$index] = $arr['Article_ID'];

            return $res;
        }

        /** Instead of deleting articles, sets them to "hidden" (highest possible view auth).
         *  This is done so actions are potentially more easily reversed.
         *
         * @param int[] $articleIDs
         * @param array $params
         * @returns int 0 on success, -1 on db connection failure
         */
        function hideArticles(array $articleIDs, array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            if(count($articleIDs) < 1)
                return false;

            $existingIDs = [];
            foreach($articleIDs as $id){
                array_push($existingIDs,$this->objectsDetails['articles']['cacheName'].$id);
            }
            array_push($articleIDs, 'CSV');

            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->objectsDetails['articles']['tableName'],
                ['Article_View_Auth = 9999'],
                [['Article_ID',$articleIDs,'IN']],
                $params
            );

            if($res === true){
                if($verbose)
                    echo 'Deleting  cache of article '.json_encode($existingIDs).EOL;
                if(!$test)
                    $this->RedisHandler->call( 'del', [$existingIDs] );
                return 0;
            }
            else
                return -1;
        }

        /* Common function, relevant to addBlocksToArticle,removeBlocksFromArticle,moveBlockInArticle and removeOrphanBlocksFromArticle*/
        function articleBlockOrderWrapper(int $articleID,string $type, array $params){

            $articles = $this->getItems([[$articleID]], 'articles', array_merge($params,['updateCache'=>false]));

            if(!is_array($articles[$articleID]))
                return $articles[$articleID];

            $order = $articles[$articleID]['Block_Order'];
            if(empty($order))
                $order = [];
            else
                $order = explode(',',$order);

            //Puts tinfoil hat
            $newOrder = [];

            switch($type){
                case 'add':
                    $blockInsertions = $params['blockInsertions'];
                    //Hey, pointers! Finally, Algo class finally pays off.
                    foreach($order as $index => $id){
                        if(isset($blockInsertions[$index])){
                            if(gettype($blockInsertions[$index]) !== 'array')
                                $blockInsertions[$index] = [$blockInsertions[$index]];
                            foreach($blockInsertions[$index] as $item){
                                array_push($newOrder,$item);
                            }
                            unset($blockInsertions[$index]);
                        }
                        array_push($newOrder,$id);
                    }
                    $blockInsertions = array_splice($blockInsertions,0);
                    foreach($blockInsertions as $insertion){
                        if(gettype($insertion) !== 'array')
                            $insertion = [$insertion];
                        foreach($insertion as $item){
                            array_push($newOrder,$item);
                        }
                    }
                    break;
                case 'remove-orphan':
                    //Get not orphan blocks, so we know what to keep
                    $existingBlocks = $this->SQLHandler->selectFromTable(
                        $this->SQLHandler->getSQLPrefix().$this->objectsDetails['general-block']['tableName'],
                        [['Article_ID',$articleID,'=']],
                        ['Block_ID'],
                        $params
                    );

                    $blocksToKeep = [];

                    //Cannot continue on a DB failure
                    if(!is_array($existingBlocks))
                        return -1;
                    else
                        foreach($existingBlocks as $blockArr)
                            array_push($blocksToKeep,$blockArr['Block_ID']);
                    //Remove all blocks that we do not keep
                    foreach($order as $index => $id){
                        if(!in_array($id,$blocksToKeep))
                            unset($order[$index]);
                    }

                    $newOrder = array_splice($order,0);
                    break;
                case 'remove':
                    $blocksToRemove = $params['blocksToRemove'];
                    foreach($blocksToRemove as $index){
                        if(isset($order[$index]))
                            unset($order[$index]);
                    }
                    $newOrder = array_splice($order,0);
                    break;
                case 'move':
                    $orderCount = count($order);
                    if($params['from'] >  $orderCount - 1 || $params['from'] < 0)
                        return 2;
                    if($params['to'] < 0)
                        return 3;
                    $temp = $order[$params['from']];
                    $insertToEnd = ($params['to'] > $orderCount - 1);
                    foreach($order as $index=>$item){
                        if($index === $params['to'])
                            array_push($newOrder,$temp);
                        if($index !== $params['from'])
                            array_push($newOrder,$item);
                    }
                    if($insertToEnd)
                        array_push($newOrder,$temp);
                    break;
                default:
                    return -1;
            }

            $newOrder = count($newOrder) ? implode(',',$newOrder) : '';

            $setRes = $this->setItems([['Article_ID'=>$articleID,'Block_Order'=>$newOrder]],'articles',array_merge($params,['update'=>true,'override'=>true,'existing'=>$articles]));

            return $setRes[$articleID];
        }

        /** Adds blocks to the article order.
         * @param int $articleID Article ID
         * @param int[] $blockInsertions Associative array of the form: [
         *                                                                  <int, block destination> => <int|int[], block ID(s) to be inserted at this position>
         *                                                              ]
         *              Each block pushes the existing blocks forward. Array starts from 0.
         *              If the index is higher than the length of the current article order, pushes it to the end.
         *              This WILL insert non-existent IDs, and will not remove them once a block is removed from an article -
         *              Up to the front-end to realize the order may contain non-existent blocks.
         *              If an array is provided to be inserted at a destination, it will be inserted in the order provided.
         * @param array $params
         * @returns int
         *      -1 db connection failure
         *      0 success
         *      1 article doesn't exist
         */
        function addBlocksToArticle(int $articleID,array $blockInsertions,array $params = []){
            return $this->articleBlockOrderWrapper($articleID,'add',array_merge($params,['blockInsertions'=>$blockInsertions]));
        }

        /** Removes blocks from the article order.
         * @param int $articleID Article ID
         * @param int[] $blocksToRemove INDEXES (not IDs, as those can be duplicate) of blocks to remove
         * @param array $params
         * @returns int
         *      -1 db connection failure
         *      0 success
         *      1 article doesn't exist
         */
        function removeBlocksFromArticle(int $articleID,array $blocksToRemove,array $params = []){
            return $this->articleBlockOrderWrapper($articleID,'remove',array_merge($params,['blocksToRemove'=>$blocksToRemove]));
        }

        /** Moves a block in the article ID from one index to another.
         * @param int $articleID Article ID
         * @param int $from
         * @param int $to
         * @param array $params
         * @returns int
         *      -1 db connection failure
         *      0 success
         *      1 article doesn't exist
         *      2 from index doesn't exist
         *      3 to index doesn't exist
         */
        function moveBlockInArticle(int $articleID,int $from,int $to,array $params = []){
            return $this->articleBlockOrderWrapper($articleID,'move',array_merge($params,['from'=>$from,'to'=>$to]));
        }

        /** Removes ALL orphan blocks from the article order.
         * @param int $articleID Article ID
         * @param array $params
         * @returns int
         *      -1 db connection failure
         *      0 success
         *      1 article doesn't exist
         */
        function removeOrphanBlocksFromArticle(int $articleID,array $params = []){
            return $this->articleBlockOrderWrapper($articleID,'remove-orphan',$params);
        }

    }

}

?>