<?php
namespace IOFrame\Handlers{
    define('IOFrameHandlersTagHandler',true);

    /** This handler manages everything related to the regular, and category tags.
     * @author Igal Ogonov <igal1333@hotmail.com>
     */

    class TagHandler extends \IOFrame\Generic\ObjectsHandler
    {

        function __construct(\IOFrame\Handlers\SettingsHandler $settings, $params = []){


            $baseTags = $params['baseTags']??[];

            $categoryTags = $params['categoryTags']??[];

            $this->validObjectTypes = array_merge($baseTags,$categoryTags);

            $baseStructure = [
                'tableName' => '',
                '_uniqueLogger'=>\IOFrame\Definitions::LOG_TAGS_CHANNEL,
                'joinOnGet' => [
                    [
                        'tableName' => 'RESOURCES',
                        'on' => [
                            ['Resource_Type','Resource_Type'],
                            ['Resource_Address','Address'],
                        ],
                    ]
                ],
                'columnsToGet' => [
                    [
                        'tableName' => 'RESOURCES',
                        'column' => 'Resource_Local'
                    ],
                    [
                        'tableName' => 'RESOURCES',
                        'column' => 'Data_Type'
                    ],
                    [
                        'tableName' => 'RESOURCES',
                        'column' => 'Text_Content'
                    ],
                    [
                        'tableName' => 'RESOURCES',
                        'column' => 'Last_Updated',
                        'as' => 'Resource_Last_Updated'
                    ]
                ],
                'extendTTL' => false,
                'cacheName' => 'default_tags_',
                'allItemsCacheName' => 'all_base_tags_',
                'keyColumns' => [],
                'safeStrColumns' => ['Meta'],
                'setColumns' => [
                    'Tag_Type' => [
                        'type' => 'string',
                        'forceValue' => ''
                    ],
                    'Tag_Name' => [
                        'type' => 'string',
                        'required' => true
                    ],
                    'Resource_Address' => [
                        'type' => 'string',
                        'default' => null,
                        'considerNull' => '@'
                    ],
                    'Meta' => [
                        'type' => 'string',
                        'jsonObject' => true,
                        'default' => null
                    ],
                    'Weight' => [
                        'type' => 'int',
                        'default' => 0
                    ]
                ],
                'moveColumns' => [
                    'Tag_Name' => [
                        'type' => 'string'
                    ]
                ],
                'columnFilters' => [
                    'typeIs' => [
                        'column' => 'Tag_Type',
                        'filter' => '='
                    ],
                    'typeIn' => [
                        'column' => 'Tag_Type',
                        'filter' => 'IN'
                    ],
                    'includeRegex' => [
                        'column' => 'Tag_Name',
                        'filter' => 'RLIKE'
                    ],
                    'excludeRegex' => [
                        'column' => 'Tag_Name',
                        'filter' => 'NOT RLIKE'
                    ],
                    'weightTo' => [
                        'column' => 'Weight',
                        'filter' => '<='
                    ],
                    'weightFrom' => [
                        'column' => 'Weight',
                        'filter' => '>='
                    ]
                ],
                'extraToGet' => [
                    '#' => [
                        'key' => '#',
                        'type' => 'count'
                    ]
                ],
                'orderColumns' => ['Tag_Name','Weight']
            ];

            $this->objectsDetails = [
            ];

            foreach(array_merge($baseTags,$categoryTags) as $type){
                $hasCategory = in_array($type,$categoryTags);
                $this->objectsDetails[$type] = $baseStructure;
                //Set table
                $this->objectsDetails[$type]['tableName'] = $hasCategory ? 'CATEGORY_TAGS' : 'TAGS';
                //Set key columns
                $this->objectsDetails[$type]['keyColumns'] = $hasCategory ? ['Tag_Type','Category_ID','Tag_Name'] : ['Tag_Type','Tag_Name'];
                //Set type
                $this->objectsDetails[$type]['setColumns']['Tag_Type']['forceValue'] = $type;
                if($hasCategory){
                    //Different all tags cache name
                    $this->objectsDetails[$type]['allItemsCacheName'] = 'all_category_tags_';
                    //If the tag has an extra key column, you must be able to set it..
                    $this->objectsDetails[$type]['setColumns']['Category_ID'] = [
                        'type' => 'int',
                        'required' => true
                    ];
                    //..it has filters..
                    $this->objectsDetails[$type]['columnFilters']['categoryIs'] = [
                        'column' => 'Category_ID',
                        'filter' => '='
                    ];
                    $this->objectsDetails[$type]['columnFilters']['categoryIn'] = [
                        'column' => 'Category_ID',
                        'filter' => 'IN'
                    ];
                    $this->objectsDetails[$type]['columnFilters']['typeCategoryIn'] = [
                        'function' => function($context){
                            $typeCategoryCombinations = $context['params']['typeCategoryIn'] ?? null;
                            if(!$typeCategoryCombinations)
                                return false;

                            foreach ($typeCategoryCombinations as $index => $arr)
                                $typeCategoryCombinations[$index][0] = [$arr[0],'STRING'];

                            return [['Tag_Type','Category_ID'],$typeCategoryCombinations,'IN'];
                        }
                    ];
                    //..and we can get all distinct categories we requested
                    $this->objectsDetails[$type]['extraToGet']['Category_ID'] = [
                        'key' => 'categories',
                        'type' => 'distinct'
                    ];
                }
            }

            parent::__construct($settings,$params);
        }

    }



}

