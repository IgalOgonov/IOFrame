<?php
namespace IOFrame\Handlers{
    use IOFrame;
    use Monolog\Logger;
    define('ObjectAuthHandler',true);
    if(!defined('abstractObjectsHandler'))
        require 'abstractObjectsHandler.php';

    /**Handles Object permissions in IOFrame.
     * Object auth is the only scheme in IOFrame that has no meaning by itself, and instead is meant to be tied to
     * different types of objects that might have permissions.
     * Think about a project management system:
     * Each project (the object) has many groups - the client(s), the architect, the project manager.. maybe some
     * those are one person, maybe some are multiple.
     * Each group/person have different permissions - for example, the project manager may see everything, while
     * the plumbing company just needs to see the project files that have the "plumbing" tag.
     * In the above case, you assign the people from the plumbing company to the "Plumbers" group, and give
     * them a specific action - akin to "VIEW_PLUMBING_FILES" - which is TIED TO THIS PROJECT.
     * If a new person from the plumbing company joined the project, he can be assigned into the "plumbing" group which has all relevant premissions.
     * Alternatively, if the project manager decides the interior designer suddenly needs to see the plumbing files, he may
     * give him the "VIEW_PLUMBING_FILES" permission individually.
     * The object auth is only meant to provide a framework for such permission systems, not have explicit meaning by itself.
     *
     * WARNING:
     *  Objects of type 'object', 'action' and 'group' may exist in the cache for up to 1 hour (by default) after their parent's deletion.
     *  Any actual auth objects, however, will be deleted, so in essence they will be empty of any meaningful content,
     *  despite still existing in the cache.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class ObjectAuthHandler extends IOFrame\abstractObjectsHandler{

        /** @var array $objects Used when initiating specific objects - mainly for persistent use
         *             {
         *                  <object id> => [
         *                      'category' => string - object category (part of its identifier)
         *                      'users' => array of user actions of the form:
         *                          [
         *                              <user ID> => [
         *                                               <action_name> => [], //Empty array for now, but will at some point have more data
         *                                               ...,
         *                                               '@' => [
         *                                                           'updated' => int, when the request for all the user's actions was made.
         *                                                      ]
         *                                           ],
         *                              ...
         *                          ],
         *                      'groups' => array of group actions of the form:
         *                          [
         *                              <group id> => [
         *                                               <action_name> => [], //Empty array for now, but will at some point have more data
         *                                               ...,
         *                                               '@' => [
         *                                                           'updated' => int, when the request for all the user's actions was made.
         *                                                      ]
         *                                           ],
         *                              ...
         *                          ],
         *                  ]
         *              }
         * */
        protected $objects=[
            /*
             * 'object_id' =>[
                    'initiated' => true,
                    'category' => 1,
                    'users' => [
                        2 => [
                            'TEST_ACTION_2' => [],
                            'TEST_ACTION_5' => [],
                            '@' =>[
                                'updated' => 1600000000,
                            ]
                       ],
                        5 => [
                            'TEST_ACTION_1' => [],
                            'TEST_ACTION_2' => [],
                            '@' =>[
                                'updated' => 1600000000,
                            ]
                       ],
                    ]
                ];
            */
        ];

        /** @var array $userGroups Used when initiating specific objects (and checking which users are in which groups) - mainly for persistent use
         *             {
         *                  <user id> => [
         *                          <group ID> => [], //Empty array for now, but will at some point have more data
         *                          ...,
         *                          '@' => [
         *                                      'updated' => int, when the request for all the user's groups was made.
         *                                 ]
         *                  ]
         *              }
         * */
        protected $userGroups=[
            /*
             * 'user_id' =>[
                    'group_A' => [],
                    'group_B' => [],
                    '@' =>[
                        'updated' => 1600000000
                    ]
                ],
            */
        ];

        /**
         * Basic construction function
         * @param SettingsHandler $settings local settings handler.
         * @param array $params Typical default settings array
         */
        function __construct(SettingsHandler $settings, $params = []){

            /* Allows dynamically setting table details at construction.
             * As much as I hate variable variables, this is likely one of the only places where their use is for the best.
             * */
            $this->validObjectTypes = ['categories','objects','actions','groups','objectUsers','objectGroups','userGroups'];

            $this->objectsDetails = [
                'categories' => [
                    'tableName' => 'OBJECT_AUTH_CATEGORIES',
                    'cacheName'=> 'object_auth_category_',
                    'keyColumns' => ['Object_Auth_Category'],
                    'setColumns' => [
                        'Object_Auth_Category' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Title' => [
                            'type' => 'string',
                            'default' => null,
                            'required' => false
                        ]
                    ],
                    'columnFilters' => [
                        'titleLike' => [
                            'column' => 'Title',
                            'filter' => 'RLIKE'
                        ],
                        'categoryIs' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => '='
                        ],
                        'categoryIn' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => 'IN'
                        ],
                    ],
                    'orderColumns' => ['Object_Auth_Category'],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                    ]
                ],
                'objects'=>[
                    'tableName' => 'OBJECT_AUTH_OBJECTS',
                    'cacheName'=> 'object_auth_object_',
                    'extendTTL'=> false,
                    'fatherDetails'=>[
                        /*I leave this here specifically to signify this is NOT the case - the categories table
                          simply represents valid categories - the 'Last_Updated' is unrelated to the object [
                            'tableName' => 'OBJECT_AUTH_CATEGORIES',
                            'cacheName' => 'object_auth_category_',
                        ]*/
                    ],
                    'keyColumns' => ['Object_Auth_Category','Object_Auth_Object'],
                    'setColumns' => [
                        'Object_Auth_Category' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Object_Auth_Object' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Title' => [
                            'type' => 'string',
                            'default' => null,
                            'required' => false
                        ],
                        'Is_Public' => [
                            'type' => 'bool',
                            'default' => false,
                            'required' => false
                        ]
                    ],
                    'moveColumns' => [
                        'Object_Auth_Category' => [
                            'type' => 'string',
                            'inputName' => 'New_Category'
                        ]
                    ],
                    'columnFilters' => [
                        'titleLike' => [
                            'column' => 'Title',
                            'filter' => 'RLIKE'
                        ],
                        'categoryIs' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => '='
                        ],
                        'categoryIn' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => 'IN'
                        ],
                        'objectLike' => [
                            'column' => 'Object_Auth_Object',
                            'filter' => 'RLIKE'
                        ],
                        'objectIn' => [
                            'column' => 'Object_Auth_Object',
                            'filter' => 'IN'
                        ],
                        'isPublic' => [
                            'column' => 'Is_Public',
                            'filter' => '=',
                            'default' => 1,
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                        'Object_Auth_Category' => [
                            'key' => 'categories',
                            'type' => 'distinct'
                        ],
                    ],
                    'orderColumns' => ['Object_Auth_Category','Object_Auth_Object']
                ],
                'actions'=>[
                    'tableName' => 'OBJECT_AUTH_ACTIONS',
                    'cacheName'=> 'object_auth_action_',
                    'extendTTL'=> false,
                    'fatherDetails'=>[
                        /*I leave this here specifically to signify this is NOT the case - the categories table
                          simply represents valid categories - the 'Last_Updated' is unrelated to the action [
                            'tableName' => 'OBJECT_AUTH_CATEGORIES',
                            'cacheName' => 'object_auth_category_',
                        ]*/
                    ],
                    'keyColumns' => ['Object_Auth_Category','Object_Auth_Action'],
                    'setColumns' => [
                        'Object_Auth_Category' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Object_Auth_Action' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Title' => [
                            'type' => 'string',
                            'default' => null,
                            'required' => false
                        ]
                    ],
                    'moveColumns' => [
                        'Object_Auth_Category' => [
                            'type' => 'string'
                        ]
                    ],
                    'columnFilters' => [
                        'titleLike' => [
                            'column' => 'Title',
                            'filter' => 'RLIKE'
                        ],
                        'categoryIs' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => '='
                        ],
                        'categoryIn' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => 'IN'
                        ],
                        'actionLike' => [
                            'column' => 'Object_Auth_Action',
                            'filter' => 'RLIKE'
                        ],
                        'actionIn' => [
                            'column' => 'Object_Auth_Action',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                        'Object_Auth_Category' => [
                            'key' => 'categories',
                            'type' => 'distinct'
                        ],
                    ],
                    'orderColumns' => ['Object_Auth_Category','Object_Auth_Object','Object_Auth_Action'],
                ],
                'groups'=>[
                    'tableName' => 'OBJECT_AUTH_GROUPS',
                    'cacheName'=> 'object_auth_group_',
                    'extendTTL'=> false,
                    'childCache' => ['object_auth_object_group_actions_'],
                    'fatherDetails'=>[
                        [
                            'tableName' => 'OBJECT_AUTH_OBJECTS',
                            'cacheName' => 'object_auth_object_'
                        ],
                        'minKeyNum' => 2
                    ],
                    'keyColumns' => ['Object_Auth_Category','Object_Auth_Object','Object_Auth_Group'],
                    'setColumns' => [
                        'Object_Auth_Category' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Object_Auth_Object' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Object_Auth_Group' => [
                            'type' => 'int',
                            'required' => true,
                            'autoIncrement' => true
                        ],
                        'Title' => [
                            'type' => 'string',
                            'default' => null,
                            'required' => false
                        ]
                    ],
                    'moveColumns' => [
                        'Object_Auth_Object' => [
                            'type' => 'string'
                        ],
                    ],
                    'columnFilters' => [
                        'titleLike' => [
                            'column' => 'Title',
                            'filter' => 'RLIKE'
                        ],
                        'categoryIs' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => '='
                        ],
                        'categoryIn' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => 'IN'
                        ],
                        'objectLike' => [
                            'column' => 'Object_Auth_Object',
                            'filter' => 'RLIKE'
                        ],
                        'objectIn' => [
                            'column' => 'Object_Auth_Object',
                            'filter' => 'IN'
                        ],
                        'groupIs' => [
                            'column' => 'Object_Auth_Group',
                            'filter' => '='
                        ],
                        'groupIn' => [
                            'column' => 'Object_Auth_Group',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                        'Object_Auth_Category' => [
                            'key' => 'categories',
                            'type' => 'distinct'
                        ],
                        'Object_Auth_Object' => [
                            'key' => 'objects',
                            'type' => 'distinct'
                        ],
                    ],
                    'orderColumns' => ['Object_Auth_Category','Object_Auth_Object','Object_Auth_Group'],
                    'autoIncrement' => true,
                ],
                'objectUsers'=>[
                    'tableName' => 'OBJECT_AUTH_OBJECT_USERS',
                    'cacheName'=> 'object_auth_object_user_actions_',
                    'useCache'=> false,
                    'fatherDetails'=>[
                        [
                            'tableName' => 'OBJECT_AUTH_OBJECTS',
                            'cacheName' => 'object_auth_object_'
                        ],
                        'minKeyNum' => 2
                    ],
                    'keyColumns' => ['Object_Auth_Category','Object_Auth_Object','ID'],
                    'extraKeyColumns' => ['Object_Auth_Action'],
                    'setColumns' => [
                        'Object_Auth_Category' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Object_Auth_Object' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'ID' => [
                            'type' => 'int',
                            'required' => true
                        ],
                        'Object_Auth_Action' => [
                            'type' => 'string',
                            'required' => true
                        ]
                    ],
                    'moveColumns' => [
                        'Object_Auth_Object' => [
                            'type' => 'string'
                        ],
                    ],
                    'columnFilters' => [
                        'categoryIs' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => '='
                        ],
                        'categoryIn' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => 'IN'
                        ],
                        'objectLike' => [
                            'column' => 'Object_Auth_Object',
                            'filter' => 'RLIKE'
                        ],
                        'objectIn' => [
                            'column' => 'Object_Auth_Object',
                            'filter' => 'IN'
                        ],
                        'userIDIs' => [
                            'column' => 'ID',
                            'filter' => '='
                        ],
                        'userIDIn' => [
                            'column' => 'ID',
                            'filter' => 'IN'
                        ],
                        'actionLike' => [
                            'column' => 'Object_Auth_Action',
                            'filter' => 'RLIKE'
                        ],
                        'actionIn' => [
                            'column' => 'Object_Auth_Action',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                        'Object_Auth_Category' => [
                            'key' => 'categories',
                            'type' => 'distinct'
                        ],
                        'Object_Auth_Object' => [
                            'key' => 'objects',
                            'type' => 'distinct'
                        ],
                    ],
                    'orderColumns' => ['Object_Auth_Category','Object_Auth_Object','ID','Object_Auth_Action'],
                    'groupByFirstNKeys'=>3,
                ],
                'objectGroups'=>[
                    'tableName' => 'OBJECT_AUTH_OBJECT_GROUPS',
                    'cacheName'=> 'object_auth_object_group_actions_',
                    'useCache'=> false,
                    'fatherDetails'=>[
                        [
                            'tableName' => 'OBJECT_AUTH_OBJECTS',
                            'cacheName' => 'object_auth_object_'
                        ],
                        'minKeyNum' => 2
                    ],
                    'keyColumns' => ['Object_Auth_Category','Object_Auth_Object','Object_Auth_Group'],
                    'extraKeyColumns' => ['Object_Auth_Action'],
                    'setColumns' => [
                        'Object_Auth_Category' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Object_Auth_Object' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Object_Auth_Group' => [
                            'type' => 'int',
                            'required' => true
                        ],
                        'Object_Auth_Action' => [
                            'type' => 'string',
                            'required' => true
                        ]
                    ],
                    'moveColumns' => [
                        'Object_Auth_Object' => [
                            'type' => 'string'
                        ],
                    ],
                    'columnFilters' => [
                        'categoryIs' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => '='
                        ],
                        'categoryIn' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => 'IN'
                        ],
                        'objectLike' => [
                            'column' => 'Object_Auth_Object',
                            'filter' => 'RLIKE'
                        ],
                        'objectIn' => [
                            'column' => 'Object_Auth_Object',
                            'filter' => 'IN'
                        ],
                        'groupIs' => [
                            'column' => 'Object_Auth_Group',
                            'filter' => '='
                        ],
                        'groupIn' => [
                            'column' => 'Object_Auth_Group',
                            'filter' => 'IN'
                        ],
                        'actionLike' => [
                            'column' => 'Object_Auth_Action',
                            'filter' => 'RLIKE'
                        ],
                        'actionIn' => [
                            'column' => 'Object_Auth_Action',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                        'Object_Auth_Category' => [
                            'key' => 'categories',
                            'type' => 'distinct'
                        ],
                        'Object_Auth_Object' => [
                            'key' => 'objects',
                            'type' => 'distinct'
                        ],
                    ],
                    'orderColumns' => ['Object_Auth_Category','Object_Auth_Object','Object_Auth_Group','Object_Auth_Action'],
                    'groupByFirstNKeys'=>3,
                ],
                'userGroups'=>[
                    'tableName' => 'OBJECT_AUTH_USERS_GROUPS',
                    'cacheName'=> 'object_auth_object_user_groups_',
                    'useCache'=> false,
                    'fatherDetails'=>[
                        [
                            'tableName' => 'OBJECT_AUTH_OBJECTS',
                            'cacheName' => 'object_auth_object_'
                        ],
                        'minKeyNum' => 2
                    ],
                    'keyColumns' => ['Object_Auth_Category','Object_Auth_Object','ID'],
                    'extraKeyColumns' => ['Object_Auth_Group'],
                    'setColumns' => [
                        'Object_Auth_Category' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Object_Auth_Object' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'ID' => [
                            'type' => 'int',
                            'required' => true
                        ],
                        'Object_Auth_Group' => [
                            'type' => 'int',
                            'required' => true
                        ],
                    ],
                    'moveColumns' => [
                        'Object_Auth_Object' => [
                            'type' => 'string'
                        ],
                    ],
                    'columnFilters' => [
                        'categoryIs' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => '='
                        ],
                        'categoryIn' => [
                            'column' => 'Object_Auth_Category',
                            'filter' => 'IN'
                        ],
                        'objectLike' => [
                            'column' => 'Object_Auth_Object',
                            'filter' => 'RLIKE'
                        ],
                        'objectIn' => [
                            'column' => 'Object_Auth_Object',
                            'filter' => 'IN'
                        ],
                        'userIDIs' => [
                            'column' => 'ID',
                            'filter' => '='
                        ],
                        'userIDIn' => [
                            'column' => 'ID',
                            'filter' => 'IN'
                        ],
                        'groupIs' => [
                            'column' => 'Object_Auth_Group',
                            'filter' => '='
                        ],
                        'groupIn' => [
                            'column' => 'Object_Auth_Group',
                            'filter' => 'IN'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ],
                        'Object_Auth_Category' => [
                            'key' => 'categories',
                            'type' => 'distinct'
                        ],
                        'Object_Auth_Object' => [
                            'key' => 'objects',
                            'type' => 'distinct'
                        ],
                    ],
                    'orderColumns' => ['Object_Auth_Category','Object_Auth_Object','ID','Object_Auth_Group'],
                    'groupByFirstNKeys'=>3,
                ],
            ];

            parent::__construct($settings,$params);
        }

        /** Checks whether a user has actions (or a single one) inside an object. Also searches to see if user is in any groups
         *  with the required actions.
         * @param string $category Category ID
         * @param int $id User ID
         * @param string $object Object identifier
         * @param string[] $actions Array of specific actions we are searching for.
         * @param array $params of the form:
         *              'actionSeparator' - string, default 'AND', whether the user needs to have at least one of the actions, or all of them
         *                                  for this function to return true.
         *
         * @returns Int code:
         *              -1 server error
         *               0 User has one/all of the actions in the specified category/object.
         *               1 User does not have one/all of the actions in the specified category/object.
         */
        function userHasActions(string $category, int $id, string $object, array $actions, array $params){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $actionSeparator = isset($params['actionSeparator'])? $params['actionSeparator'] : 'AND';

            $existingActions = [];
            $existingGroups = [];

            $userParams = array_merge($params,[
                'categoryIs' => $category,
                'objectIn' => [$object],
                'userIDIs' => $id,
                'actionIn' => $actions,
                'getAllSubItems'=>true
            ]);
            $userActions = $this->getItems([],'objectUsers',$userParams);
            if(!isset($userActions['@'])){
                if($verbose)
                    echo 'Database error getting user actions!'.EOL;
                return -1;
            }

            if($userActions['@']['#'] != 0 && isset($userActions[$category.'/'.$object.'/'.$id])){
                foreach($userActions[$category.'/'.$object.'/'.$id] as $actionArray){
                    array_push($existingActions,$actionArray['Object_Auth_Action']);
                }
            }

            if($actionSeparator === 'OR'){
                $success = count(array_diff($actions,$existingActions)) < count($actions);
            }
            else{
                $success = count(array_diff($actions,$existingActions)) === 0;
            }

            if($success)
                return 0;

            $groupParams = array_merge($params,[
                'categoryIs' => $category,
                'objectIn' => [$object],
                'userIDIs' => $id,
                'actionIn' => $actions,
                'getAllSubItems'=>true
            ]);
            $userGroups = $this->getItems([],'userGroups',$groupParams);

            if(!isset($userGroups['@'])){
                if($verbose)
                    echo 'Database error getting user groups!'.EOL;
                return -1;
            }

            if($userGroups['@']['#'] != 0 && isset($userGroups[$category.'/'.$object.'/'.$id])){
                foreach($userGroups[$category.'/'.$object.'/'.$id] as $actionArray){
                    array_push($existingGroups,(int)$actionArray['Object_Auth_Group']);
                }
            }

            if(count($existingGroups) > 0){

                $groupParams = array_merge($params,[
                    'categoryIs' => $category,
                    'objectIn' => [$object],
                    'groupIn' => $existingGroups,
                    'actionIn' => $actions,
                    'getAllSubItems'=>true
                ]);
                $objectGroups = $this->getItems([],'objectGroups',$groupParams);

                if(!isset($objectGroups['@'])){
                    if($verbose)
                        echo 'Database error getting group actions!'.EOL;
                    return -1;
                }

                foreach($objectGroups as $identifier => $groupsArray){
                    if($identifier === '@')
                        continue;
                    foreach($groupsArray as $actionArray){
                        if(!in_array($actionArray['Object_Auth_Action'],$existingActions))
                            array_push($existingActions,$actionArray['Object_Auth_Action']);
                    }
                }

                if($actionSeparator === 'OR'){
                    $success = count(array_diff($actions,$existingActions)) < count($actions);
                }
                else{
                    $success = count(array_diff($actions,$existingActions)) === 0;
                }

                if($success)
                    return 0;

            }

            return 1;
        }

        /** Returns all the objects where the user (or one of its group) has a specific action (or user just exists)
         * @param string $category Category ID
         * @param int $id User ID
         * @param string[] $objects Array of specific objects we are searching.
         * @param array $params of the form
         *              'objects' => string[], default [] - array of objects to limit the search in
         *              'requiredActions' => string[], default [] - the object is only added if one of the following actions is present.
         *
         * @returns Bool|Array of arrays the form:
         *          [
         *              [
         *                  'Object_Auth_Object' => <string, object the user is in>
         *                  'Source' => <string, 'self' if the user is in the object naturally, 'group' if he is in because of a group>
         *              ]
         *          ]
         *          OR
         *          false on DB connection failure
         *
         */
        function userObjects(string $category, int $id, array $params){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);

            $objects = isset($params['objects'])? $params['objects'] : [];
            $requiredActions = isset($params['requiredActions'])? $params['requiredActions'] : [];

            $prefix = $this->SQLHandler->getSQLPrefix();

            foreach($requiredActions as $index => $action){
                $requiredActions[$index] = [$action,'STRING'];
            }
            if(count($requiredActions)){
                array_push($requiredActions,'CSV');
            }

            foreach($objects as $index => $object){
                $objects[$index] = [$object,'STRING'];
            }
            if(count($objects)){
                array_push($objects,'CSV');
            }

            /* Query explanation:
             * SELECT ALL DISTINCT OBJECTS SO THAT
             *      THE USER
             *          [WITH ONE OF THE REQUESTED ACTIONS]
             *      IS IN OBJECT_AUTH_OBJECT_USERS
             *          [OR
             *          THE GROUP
             *              OUT OF THE GROUPS BELONGING TO REQUESTED USER IS IN OBJECT_AUTH_USERS_GROUPS
             *          WITH ONE OF THE REQUESTED ACTIONS IS IN OBJECT_AUTH_OBJECT_GROUPS]
            */
            $categoryCondition = [
                'Object_Auth_Category',
                [$category,'STRING'],
                '='
            ];

            $userCondition = [
                'ID',
                $id,
                '='
            ];

            $actionsCondition = [
                'Object_Auth_Action',
                $requiredActions,
                'IN'
            ];

            $objectsCondition = [
                'Object_Auth_Object',
                $objects,
                'IN'
            ];

            //ObjectUsers conditions
            $usersConditions = [ $userCondition, $categoryCondition ];

            if(count($requiredActions))
                array_push($usersConditions,$actionsCondition);

            if(count($objects))
                array_push($usersConditions,$objectsCondition);

            array_push($usersConditions,'AND');

            $query = $this->SQLHandler->selectFromTable(
                $prefix.$this->objectsDetails['objectUsers']['tableName'],
                $usersConditions,
                ['Object_Auth_Object', '"self" AS Source'],
                ['justTheQuery'=>true,'useBrackets'=>true,'DISTINCT'=>true]
            );

            //GroupActions
            if(count($requiredActions)){

                $groupCondition = [
                    'Object_Auth_Group',
                    $this->SQLHandler->selectFromTable(
                        $prefix.$this->objectsDetails['userGroups']['tableName'],
                        [$categoryCondition, $userCondition,'AND'],
                        ['Object_Auth_Group'],
                        ['justTheQuery'=>true,'useBrackets'=>true]
                    ),
                    'IN'
                ];

                $groupObjectsConditions = [ $groupCondition,$categoryCondition,$actionsCondition ];
                if(count($objects)){}
                    array_push($groupObjectsConditions,$objectsCondition);
                array_push($groupObjectsConditions,'AND');

                $query .= ' UNION '.
                    $this->SQLHandler->selectFromTable(
                        $prefix.$this->objectsDetails['objectGroups']['tableName'],
                        $groupObjectsConditions,
                        ['Object_Auth_Object', '"group" AS Source'],
                        ['justTheQuery'=>true,'useBrackets'=>true,'DISTINCT'=>true]
                    );
            }
            if($verbose)
                echo 'Query to send: '.$query.EOL;
            $response = $this->SQLHandler->exeQueryBindParam($query,[],['fetchAll'=>true]);
            if($response !== false)
                foreach($response as $key => $arr){
                    for($i = 0; $i< 2; $i++)
                        unset($response[$key][$i]);
                }
            return $response;
        }

        /** Returns all the objects where the group has a specific action (or just exists)
         * @param string $category Category ID
         * @param int $id User ID
         * @param string[] $objects Array of specific objects we are searching.
         * @param array $params of the form
         *              'objects' => string[], default [] - array of objects to limit the search in
         *              'requiredActions' => string[], default [] - the object is only added if one of the following actions is present.
         *
         * @returns Bool|Array of objects in which the user has the action.
         *          OR
         *          false on DB connection failure
         *
         */
        function groupObjects(string $category, int $id, array $params){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $objects = isset($params['objects'])? $params['objects'] : [];
            $requiredActions = isset($params['requiredActions'])? $params['requiredActions'] : [];

            $prefix = $this->SQLHandler->getSQLPrefix();

            foreach($requiredActions as $index => $action){
                $requiredActions[$index] = [$action,'STRING'];
            }
            if(count($requiredActions)){
                array_push($requiredActions,'CSV');
            }

            foreach($objects as $index => $object){
                $objects[$index] = [$object,'STRING'];
            }
            if(count($objects)){
                array_push($objects,'CSV');
            }


            /* Query explanation:
             * SELECT ALL DISTINCT OBJECTS SO THAT
             *      THE GROUP
             *          [WITH ONE OF THE REQUESTED ACTIONS]
             *      IS IN OBJECT_AUTH_OBJECT_GROUPS
            */
            $categoryCondition = [
                'Object_Auth_Category',
                [$category,'STRING'],
                '='
            ];

            $groupCondition = [
                'Object_Auth_Group',
                $id,
                '='
            ];

            $actionsCondition = [
                'Object_Auth_Action',
                $requiredActions,
                'IN'
            ];

            $objectsCondition = [
                'Object_Auth_Object',
                $objects,
                'IN'
            ];

            //ObjectUsers conditions
            $groupsConditions = [ $groupCondition, $categoryCondition ];

            if(count($requiredActions))
                array_push($groupsConditions,$actionsCondition);

            if(count($objects))
                array_push($groupsConditions,$objectsCondition);

            array_push($groupsConditions,'AND');

            $query = $this->SQLHandler->selectFromTable(
                $prefix.$this->objectsDetails['objectGroups']['tableName'],
                $groupsConditions,
                ['Object_Auth_Object'],
                ['justTheQuery'=>true,'useBrackets'=>true,'DISTINCT'=>true]
            );

            if($verbose)
                echo 'Query to send: '.$query.EOL;
            $response = $this->SQLHandler->exeQueryBindParam($query,[],['fetchAll'=>true]);

            if($response !== false)
                foreach($response as $key => $arr){
                    $response[$key] = $arr[0];
                }
            return $response;
        }

        /** Returns all users who belong to a group.
         * @param string $category Category ID
         * @param int $groupID Group ID
         * @param array $params of the form
         *              'users' => string[], default [] - array of users to limit the search in
         *
         * @returns Array of users in the group (who answer the action requirements)
         *          OR
         *          false on DB connection failure
         *
         */
        function groupUsers(string $category, int $groupID, array $params){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $users = isset($params['users'])? $params['users'] : [];
            $prefix = $this->SQLHandler->getSQLPrefix();

            if(count($users)){
                array_push($users,'CSV');
            }

            /* Query explanation:
             * SELECT ALL DISTINCT USERS SO THAT
             *      THE GROUP IS IN OBJECT_AUTH_USERS_GROUPS
             *      [AND THE ACTION IS IN ONE OF THE REQUIRED ACTIONS]
            */
            $categoryCondition = [
                'Object_Auth_Category',
                [$category,'STRING'],
                '='
            ];

            $groupCondition = [
                'Object_Auth_Group',
                $groupID,
                '='
            ];

            if(count($users))
                $usersCondition = [
                    'ID',
                    $users,
                    'IN'
                ];

            //ObjectUsers conditions
            $groupsConditions = [ $groupCondition, $categoryCondition ];

            if(count($users))
                array_push($groupsConditions,$usersCondition);

            array_push($groupsConditions,'AND');

            $query = $this->SQLHandler->selectFromTable(
                $prefix.$this->objectsDetails['userGroups']['tableName'],
                $groupsConditions,
                ['ID'],
                ['justTheQuery'=>true,'useBrackets'=>true,'DISTINCT'=>true]
            );

            if($verbose)
                echo 'Query to send: '.$query.EOL;
            $response = $this->SQLHandler->exeQueryBindParam($query,[],['fetchAll'=>true]);

            if($response !== false)
                foreach($response as $key => $arr){
                    $response[$key] = (int)$arr[0];
                }
            return $response;
        }

    }



}

?>