<?php
namespace IOFrame\Handlers{
    define('IOFrameHandlersArticleHandler',true);

    /** TODO In highScalability, don't join users table and get groupUsers programmatically
     * The article handler is meant to handle block-based articles.
     *  Not much to write here, most of the explanation is in the api.
     *  WARNING:
     *    Both articles and blocks may take up to 1 hour to sync with changes of related tables (resources, contacts, etc)
     *    due to cache. Do not count on changes being through different handlers to those resources reflecting here at once.
     *    Still, in most cases those resources don't change (who suddenly changes their name? Or uploads a different
     *    image to the same address?).
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class ArticleHandler extends \IOFrame\Generic\ObjectsHandler{


        /** @var array $validBlockTypes Array of valid block types - those are the objects under an article.
         * */
        private array $validBlockTypes;

        /**
         * Basic construction function
         * @param SettingsHandler $settings local settings handler.
         * @param array $params Typical default settings array
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, $params = []){

            //Types of blocks - 'general-block' just refers to getting blocks in general, and has no set columns.
            $this->validBlockTypes = ['general-block','markdown-block','image-block','cover-block','gallery-block','video-block',
                'youtube-block','article-block'];
            //The first type is the main articles table. The other tables are for individual blocks.
            $this->validObjectTypes = array_merge(['articles','article-tags'],$this->validBlockTypes);

            $prefix = $params['SQLManager']->getSQLPrefix();

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
                        ],
                        [
                            'expression' => '
                            (SELECT GROUP_CONCAT(CONCAT(Tag_Type,"/",Tag_Name))
                             FROM '.$prefix.'ARTICLE_TAGS
                             WHERE
                                '.$prefix.'ARTICLE_TAGS.Article_ID = '.$prefix.'ARTICLES.Article_ID
                             ) AS "Tags"
                             '
                        ],
                    ],
                    'extendTTL' => false,
                    'cacheName' => 'article_',
                    'childCache' => ['article_tags_','article_blocks_'],
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
                            'default' => null
                        ],
                        'Article_Weight' => [
                            'type' => 'int',
                            'default' => 0
                        ],
                    ],
                    'moveColumns' => [
                    ],
                    'columnFilters' => [
                        'contactTypeIs' => [
                            'tableName' => $prefix.'CONTACTS',
                            'column' => 'Contact_Type',
                            'filter' => '='
                        ],
                        'contactTypeIn' => [
                            'tableName' => $prefix.'CONTACTS',
                            'column' => 'Contact_Type',
                            'filter' => 'IN'
                        ],
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
                        'tagsIn' => [
                            'function' => function($context){
                                return \IOFrame\Util\GenericObjectFunctions::filterByStuffInAnotherTable($context,[
                                    'filterName'=>'tagsIn',
                                    'baseTableName'=>'ARTICLES',
                                    'foreignTableName'=>'ARTICLE_TAGS',
                                    'inputMap'=>['type','name'],
                                    'foreignColumns'=>['Tag_Type','Tag_Name'],
                                    'mainColumns'=>['Article_ID']
                                ]);
                            }
                        ],
                        /*have to be explicitly set, due to ambiguous where conditions when getting tags (that also have time columns) with those filters*/
                        'createdBefore' => [
                            'tableName' => $prefix.'ARTICLES',
                            'column' => 'Created',
                            'filter' => '<'
                        ],
                        'createdAfter' => [
                            'tableName' => $prefix.'ARTICLES',
                            'column' => 'Created',
                            'filter' => '>'
                        ],
                        'changedBefore' => [
                            'tableName' => $prefix.'ARTICLES',
                            'column' => 'Last_Updated',
                            'filter' => '<'
                        ],
                        'changedAfter' => [
                            'tableName' => $prefix.'ARTICLES',
                            'column' => 'Last_Updated',
                            'filter' => '>'
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
                ],
                'article-tags' => [
                    'tableName' => 'ARTICLE_TAGS',
                    'joinOnGet' => [
                        [
                            'tableName' => 'TAGS',
                            'on' => [
                                ['Tag_Type','Tag_Type'],
                                ['Tag_Name','Tag_Name']
                            ],
                        ]
                    ],
                    'columnsToGet' => [
                    ],
                    'extendTTL' => false,
                    'cacheName' => 'article_tags_',
                    'fatherDetails'=>[
                        [
                            'tableName' => 'ARTICLES',
                            'cacheName' => 'article_'
                        ],
                        'minKeyNum' => 1
                    ],
                    'keyColumns' => ['Article_ID','Tag_Type','Tag_Name'],
                    'safeStrColumns' => [],
                    'setColumns' => [
                        'Article_ID' => [
                            'type' => 'int',
                            'required' => true
                        ],
                        'Tag_Type' => [
                            'type' => 'string',
                            'default' => 'default-article-tags'
                        ],
                        'Tag_Name' => [
                            'type' => 'string',
                            'required' => true
                        ]
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
                        'typeIs' => [
                            'column' => 'Tag_Type',
                            'filter' => '='
                        ],
                        'typeIn' => [
                            'column' => 'Tag_Type',
                            'filter' => 'IN'
                        ],
                        'tagIs' => [
                            'column' => 'Tag_Name',
                            'filter' => '='
                        ],
                        'tagIn' => [
                            'column' => 'Tag_Name',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                    ],
                    'orderColumns' => ['Article_ID','Tag_Type','Tag_Name'],
                    'hasTimeColumns'=>false,
                    'groupByFirstNKeys'=>1,
                ],
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
         * @return array|bool|int|string|string[]
         */
        function getUserArticles(int $userID, array $params = []): array|bool|int|string {

            $requiredArticles = $params['requiredArticles'] ?? [];

            $prefix = $this->SQLManager->getSQLPrefix();

            if(count($requiredArticles)){
                $requiredArticles[] = 'CSV';
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
                $conditions[] = $articlesCondition;

            $conditions[] = 'AND';

            $res = $this->SQLManager->selectFromTable(
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
         * @return false|int
         */
        function hideArticles(array $articleIDs, array $params = []): bool|int {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            if(count($articleIDs) < 1)
                return false;

            $existingIDs = [];
            foreach($articleIDs as $id){
                $existingIDs[] = $this->objectsDetails['articles']['cacheName'] . $id;
            }
            $articleIDs[] = 'CSV';

            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().$this->objectsDetails['articles']['tableName'],
                ['Article_View_Auth = 9999'],
                [['Article_ID',$articleIDs,'IN']],
                $params
            );

            if($res === true){
                if($verbose)
                    echo 'Deleting  cache of article '.json_encode($existingIDs).EOL;
                if(!$test && $useCache)
                    $this->RedisManager->call( 'del', [$existingIDs] );
                return 0;
            }
            else{
                $this->logger->error('Could not hide articles',['articles'=>$articleIDs]);
                return -1;
            }
        }


        /** Adds tags to an article.
         *
         * @param array $inputs Array of strings of tag Names
         * @param array $params Same as abstractObjectHandler::setItems()
         *
         * @returns array of the form:
         *              setObjects Code
         *
         * @throws \Exception
         * @throws \Exception
         * @throws \Exception
         */
        function setArticleTags(int $id, string $type, array $inputs, array $params = []): array|bool|int|string|null {
            $tempInputs = [];
            foreach ($inputs as $tagName){
                $tempInputs[] = [
                    'Article_ID' => $id,
                    'Tag_Type' => $type,
                    'Tag_Name' => $tagName
                ];
            }
            return $this->setItems($tempInputs,'article-tags',$params);
        }

        /** Deletes tags from an article
         *
         * @param mixed $id Name of the Content
         * @param array $params same as deleteContent
         *
         * @returns  array of the form:
         * @throws \Exception
         * @throws \Exception
         * @throws \Exception
         */
        function removeArticleTags(int $id, string $type, array $inputs, array $params = []): int {
            $tempInputs = [];
            foreach ($inputs as $tagName){
                $tempInputs[] = [
                    'Article_ID' => $id,
                    'Tag_Type' => $type,
                    'Tag_Name' => $tagName
                ];
            }
            return $this->deleteItems($tempInputs,'article-tags',$params);
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
         * @return array|bool|int|string|string[]|null
         */
        function addBlocksToArticle(int $articleID,array $blockInsertions,array $params = []): array|bool|int|string|null {
            return $this->articleBlockOrderWrapper($articleID,'add',array_merge($params,['blockInsertions'=>$blockInsertions]));
        }

        /** Removes blocks from the article order.
         * @param int $articleID Article ID
         * @param int[] $blocksToRemove INDEXES (not IDs, as those can be duplicate) of blocks to remove
         * @param array $params
         * @return array|bool|int|string|string[]|null
         */
        function removeBlocksFromArticle(int $articleID,array $blocksToRemove,array $params = []): array|bool|int|string|null {
            return $this->articleBlockOrderWrapper($articleID,'remove',array_merge($params,['blocksToRemove'=>$blocksToRemove]));
        }

        /** Moves a block in the article ID from one index to another.
         * @param int $articleID Article ID
         * @param int $from
         * @param int $to
         * @param array $params
         * @return array|bool|int|string|string[]|null
         */
        function moveBlockInArticle(int $articleID,int $from,int $to,array $params = []): array|bool|int|string|null {
            return $this->articleBlockOrderWrapper($articleID,'move',array_merge($params,['from'=>$from,'to'=>$to]));
        }

        /** Removes ALL orphan blocks from the article order.
         * @param int $articleID Article ID
         * @param array $params
         * @return array|bool|int|string|string[]|null
         */
        function removeOrphanBlocksFromArticle(int $articleID,array $params = []): array|bool|int|string|null {
            return $this->articleBlockOrderWrapper($articleID,'remove-orphan',$params);
        }

        /* Common function, relevant to addBlocksToArticle,removeBlocksFromArticle,moveBlockInArticle and removeOrphanBlocksFromArticle*/
        protected function articleBlockOrderWrapper(int $articleID,string $type, array $params): array|bool|int|string|null {

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
                                $newOrder[] = $item;
                            }
                            unset($blockInsertions[$index]);
                        }
                        $newOrder[] = $id;
                    }
                    $blockInsertions = array_splice($blockInsertions,0);
                    foreach($blockInsertions as $insertion){
                        if(gettype($insertion) !== 'array')
                            $insertion = [$insertion];
                        foreach($insertion as $item){
                            $newOrder[] = $item;
                        }
                    }
                    break;
                case 'remove-orphan':
                    //Get not orphan blocks, so we know what to keep
                    $existingBlocks = $this->SQLManager->selectFromTable(
                        $this->SQLManager->getSQLPrefix().$this->objectsDetails['general-block']['tableName'],
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
                            $blocksToKeep[] = $blockArr['Block_ID'];
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
                            $newOrder[] = $temp;
                        if($index !== $params['from'])
                            $newOrder[] = $item;
                    }
                    if($insertToEnd)
                        $newOrder[] = $temp;
                    break;
                default:
                    return -1;
            }

            $newOrder = count($newOrder) ? implode(',',$newOrder) : '';
            $setRes = $this->setItems([['Article_ID'=>$articleID,'Block_Order'=>$newOrder]],'articles',array_merge($params,['update'=>true,'override'=>true,'existing'=>$articles]));

            return $setRes[$articleID] ?? $setRes;
        }

    }

}