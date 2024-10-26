<?php
require __DIR__.'/../../IOFrame/Handlers/ObjectAuthHandler.php';

$ObjectAuthHandler = new \IOFrame\Handlers\ObjectAuthHandler($settings,$defaultSettingsParams);

/* ------------------------------------------------------------
                        Gets
 ------------------------------------------------------------ */

echo EOL.'Getting all categories without conditions (limit 20):'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'categories',
        ['test'=>true,'verbose'=>true,'limit'=>20]
    )
);

echo EOL.'Getting all categories with conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'categories',
        [
            'titleLike'=>'t',
            'categoryIs'=>'test_1',
            'categoryIn'=>[
                'test_1',
                'test_2'
            ],
            'limit'=>5,
            'offset'=>0,
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting specific categories:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [['test_1']],
        'categories',
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);

// The following was done after creating seeding the db with 1,000 categories, creation time set randomly withing the last 365 days
echo EOL.'Getting All Category creation dates by 7 days intervals (no time limit, LIMIT 0)'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'categories',
        [
            'test'=>true,
            'verbose'=>true,
            'limit'=>0,
            'replaceTypeArray'=>[
                'extraToGet'=>[
                    'Created'=>[
                        'key'=>'intervals',
                        'type'=>'count_interval',
                        'intervals'=>7*24*3600
                    ]
                ]
            ]
        ]
    )
);

echo EOL.'Getting All Category creation dates by 1 day intervals (30 days limit, LIMIT 0)'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'categories',
        [
            'test'=>true,
            'verbose'=>true,
            'limit'=>0,
            'createdAfter'=>time()-3600*24*30,
            'replaceTypeArray'=>[
                'extraToGet'=>[
                    'Created'=>[
                        'key'=>'intervals',
                        'type'=>'count_interval',
                        'intervals'=>24*3600
                    ]
                ]
            ]
        ]
    )
);


echo EOL.'Getting all objects without conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'objects',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all objects with conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'objects',
        [
            'titleLike'=>'t',
            'categoryIs'=>'test_1',
            'categoryIn'=>[
                'test_1',
                'test_2'
            ],
            'objectLike'=>'t',
            'objectIn'=>[
                'test_1',
                'test_2'
            ],
            'limit'=>5,
            'offset'=>0,
            'isPublic'=>0,
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting specific objects:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [['test_1','test_2']],
        'objects',
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);



echo EOL.'Getting all actions without conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'actions',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all actions with conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'actions',
        [
            'titleLike'=>'t',
            'categoryIs'=>'test_1',
            'categoryIn'=>[
                'test_1',
                'test_2'
            ],
            'actionLike'=>'t',
            'actionIn'=>[
                'test_1',
                'test_2'
            ],
            'limit'=>5,
            'offset'=>0,
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting specific actions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [['test_1','test_1']],
        'actions',
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);



echo EOL.'Getting all groups without conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'groups',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all groups with conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'groups',
        [
            'titleLike'=>'t',
            'categoryIs'=>'test_1',
            'categoryIn'=>[
                'test_1',
                'test_2'
            ],
            'groupIs'=>0,
            'groupIn'=>[
                1,
                2
            ],
            'limit'=>5,
            'offset'=>0,
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting specific group:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [
            ['test_1','test_1',1]
        ],
        'groups',
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);



echo EOL.'Getting all objectUsers without conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'objectUsers',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all objectUsers with conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'objectUsers',
        [
            'titleLike'=>'t',
            'categoryIs'=>'test_1',
            'categoryIn'=>[
                'test_1',
                'test_2'
            ],
            'userIDIs'=>1,
            'userIDIn'=>[
                1,
                2
            ],
            'actionLike'=>'t',
            'actionIn'=>[
                'test_1',
                'test_2'
            ],
            'limit'=>5,
            'offset'=>0,
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting specific objectUsers:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [
            ['test_1','test_1',1]
        ],
        'objectUsers',
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);



echo EOL.'Getting all objectGroups without conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'objectGroups',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all objectGroups with conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'objectGroups',
        [
            'titleLike'=>'t',
            'categoryIs'=>'test_1',
            'categoryIn'=>[
                'test_1',
                'test_2'
            ],
            'groupIs'=>1,
            'groupIn'=>[
                1,
                2
            ],
            'actionLike'=>'t',
            'actionIn'=>[
                'test_1',
                'test_2'
            ],
            'limit'=>5,
            'offset'=>0,
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting specific objectGroups:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [['test_1','test_1',1]],
        'objectGroups',
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);



echo EOL.'Getting all userGroups without conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'userGroups',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting all userGroups with conditions:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [],
        'userGroups',
        [
            'titleLike'=>'t',
            'categoryIs'=>'test_1',
            'categoryIn'=>[
                'test_1',
                'test_2'
            ],
            'userIDIs'=>1,
            'userIDIn'=>[
                1,
                2
            ],
            'groupIs'=>1,
            'groupIn'=>[
                1,
                2
            ],
            'limit'=>5,
            'offset'=>0,
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting specific userGroups:'.EOL;
var_dump(
    $ObjectAuthHandler->getItems(
        [['test_1','test_1',1]],
        'userGroups',
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);


/* ------------------------------------------------------------
                        Sets
 ------------------------------------------------------------ */

echo EOL.'Creating some categories:'.EOL;
var_dump(
    $ObjectAuthHandler->setItems(
        [
            [
                'Object_Auth_Category' => 'test_1',
                'Title' => 'test 1'
            ],
            [
                'Object_Auth_Category' => 'test_2',
                'Title' => 'test 2'
            ],
        ],
        'categories',
        ['test'=>true,'override'=>false,'verbose'=>true]
    )
);

echo EOL.'Updating some categories:'.EOL;
var_dump(
    $ObjectAuthHandler->setItems(
        [
            [
                'Object_Auth_Category' => 'test_2',
                'Title' => 'test two'
            ],
        ],
        'categories',
        ['test'=>true,'update'=>true,'verbose'=>true]
    )
);


echo EOL.'Setting some objects:'.EOL;
var_dump(
    $ObjectAuthHandler->setItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Title' => 'test',
                'Is_Public' => true
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_2'
            ],
        ],
        'objects',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Setting some actions:'.EOL;
var_dump(
    $ObjectAuthHandler->setItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Action' => 'test_1',
                'Title' => 'test'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Action' => 'test_2',
            ],
        ],
        'actions',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Creating some groups:'.EOL;
var_dump(
    $ObjectAuthHandler->setItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Title' => 'test group'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Title' => 'test group 2'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_2',
                'Title' => 'test group 3'
            ],
        ],
        'groups',
        ['test'=>true,'override'=>false,'verbose'=>true]
    )
);

echo EOL.'Updating some groups:'.EOL;
var_dump(
    $ObjectAuthHandler->setItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 1,
                'Title' => 'test group 3'
            ]
        ],
        'groups',
        ['test'=>true,'update'=>true,'override'=>false,'verbose'=>true]
    )
);

echo EOL.'Inserting an existing group into a different object:'.EOL;
var_dump(
    $ObjectAuthHandler->setItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 3,
                'Title' => 'test group 3'
            ]
        ],
        'groups',
        ['test'=>true,'update'=>false,'override'=>true,'verbose'=>true]
    )
);

echo EOL.'Updating some groups:'.EOL;
var_dump(
    $ObjectAuthHandler->setItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 1,
                'Title' => 'test group 1'
            ]
        ],
        'groups',
        ['test'=>true,'update'=>true,'verbose'=>true]
    )
);


echo EOL.'Setting some object users:'.EOL;
var_dump(
    $ObjectAuthHandler->setItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 1,
                'Object_Auth_Action' => 'test_1'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 1,
                'Object_Auth_Action' => 'test_2'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 2,
                'Object_Auth_Action' => 'test_2'
            ],
        ],
        'objectUsers',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Setting some object groups:'.EOL;
var_dump(
    $ObjectAuthHandler->setItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 1,
                'Object_Auth_Action' => 'test_1'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 2,
                'Object_Auth_Action' => 'test_2'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_2',
                'Object_Auth_Group' => 3,
                'Object_Auth_Action' => 'test_1'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 3,
                'Object_Auth_Action' => 'test_2'
            ],
        ],
        'objectGroups',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Setting some users groups:'.EOL;
var_dump(
    $ObjectAuthHandler->setItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 1,
                'Object_Auth_Group' => 1
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 1,
                'Object_Auth_Group' => 2
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 2,
                'Object_Auth_Group' => 1
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_2',
                'ID' => 2,
                'Object_Auth_Group' => 3
            ],
        ],
        'userGroups',
        ['test'=>true,'verbose'=>true]
    )
);


/* ------------------------------------------------------------
                        Deletes
 ------------------------------------------------------------ */

echo EOL.'Deleting some categories:'.EOL;
var_dump(
    $ObjectAuthHandler->deleteItems(
        [
            [
                'Object_Auth_Category' => 'test_1'
            ],
        ],
        'categories',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Deleting some objects:'.EOL;
var_dump(
    $ObjectAuthHandler->deleteItems(
        [
            [
                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_2'
            ],
        ],
        'objects',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Deleting some actions:'.EOL;
var_dump(
    $ObjectAuthHandler->deleteItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Action' => 'test_1'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Action' => 'test_2',
            ],
        ],
        'actions',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Deleting some groups:'.EOL;
var_dump(
    $ObjectAuthHandler->deleteItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 1
            ]
        ],
        'groups',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Deleting some object users:'.EOL;
var_dump(
    $ObjectAuthHandler->deleteItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 1,
                'Object_Auth_Action' => 'test_1'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 2,
                'Object_Auth_Action' => 'test_2'
            ],
        ],
        'objectUsers',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Deleting some object groups:'.EOL;
var_dump(
    $ObjectAuthHandler->deleteItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 1,
                'Object_Auth_Action' => 'test_1'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 2,
                'Object_Auth_Action' => 'test_2'
            ],
        ],
        'objectGroups',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Deleting some users groups:'.EOL;
var_dump(
    $ObjectAuthHandler->deleteItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 1,
                'Object_Auth_Group' => 1
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 1,
                'Object_Auth_Group' => 2
            ],
        ],
        'userGroups',
        ['test'=>true,'verbose'=>true]
    )
);


/* ------------------------------------------------------------
                        Renaming
 ------------------------------------------------------------ */


echo EOL.'Renaming some objects:'.EOL;
var_dump(
    $ObjectAuthHandler->moveItems(
        [
            [
                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_2'
            ],
        ],
        [
            'Object_Auth_Category' => 5
        ],
        'objects',
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Renaming some actions:'.EOL;
var_dump(
    $ObjectAuthHandler->moveItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Action' => 'test_1'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Action' => 'test_2',
            ],
        ],
        [

            'Object_Auth_Category' => 'test_1'
        ],
        'actions',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Renaming some groups:'.EOL;
var_dump(
    $ObjectAuthHandler->moveItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 1
            ]
        ],
        [
            'Object_Auth_Object' => 'test_2',
        ],
        'groups',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Renaming some object users:'.EOL;
var_dump(
    $ObjectAuthHandler->moveItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 1,
                'Object_Auth_Action' => 'test_1'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 2,
                'Object_Auth_Action' => 'test_2'
            ],
        ],
        [
            'Object_Auth_Object' => 'test_2',
        ],
        'objectUsers',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Renaming some object groups:'.EOL;
var_dump(
    $ObjectAuthHandler->moveItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 1,
                'Object_Auth_Action' => 'test_1'
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'Object_Auth_Group' => 2,
                'Object_Auth_Action' => 'test_2'
            ],
        ],
        [
            'Object_Auth_Object' => 'test_2',
        ],
        'objectGroups',
        ['test'=>true,'verbose'=>true]
    )
);


echo EOL.'Renaming some users groups:'.EOL;
var_dump(
    $ObjectAuthHandler->moveItems(
        [
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 1,
                'Object_Auth_Group' => 1
            ],
            [

                'Object_Auth_Category' => 'test_1',
                'Object_Auth_Object' => 'test_1',
                'ID' => 1,
                'Object_Auth_Group' => 2
            ],
        ],
        [
            'Object_Auth_Object' => 'test_2',
        ],
        'userGroups',
        ['test'=>true,'verbose'=>true]
    )
);

/* ------------------------------------------------------------
                        User Actions
 ------------------------------------------------------------ */


echo EOL.'Checking user actions that all exist (OR)'.EOL;
var_dump(
    $ObjectAuthHandler->userHasActions(
        'test_1',
        1,
        'test_1',
        ['test_1','test_2'],
        ['actionSeparator'=>'OR','test'=>true,'verbose'=>true]
    )
);

echo EOL.'Checking user actions that all exist  (OR) - fake user'.EOL;
var_dump(
    $ObjectAuthHandler->userHasActions(
        'test_1',
        99999,
        'test_1',
        ['test_1','test_2'],
        ['actionSeparator'=>'OR','test'=>true,'verbose'=>true]
    )
);

echo EOL.'Checking user actions that some exist (OR)'.EOL;
var_dump(
    $ObjectAuthHandler->userHasActions(
        'test_1',
        1,
        'test_1',
        ['test_1','test_3','test_5'],
        ['actionSeparator'=>'OR','test'=>true,'verbose'=>true]
    )
);

echo EOL.'Checking user actions (AND)'.EOL;
var_dump(
    $ObjectAuthHandler->userHasActions(
        'test_1',
        1,
        'test_1',
        ['test_1','test_2'],
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Checking user actions that some exist (AND)'.EOL;
var_dump(
    $ObjectAuthHandler->userHasActions(
        'test_1',
        1,
        'test_1',
        ['test_1','test_3','test_5'],
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting user objects'.EOL;
var_dump(
    $ObjectAuthHandler->userObjects(
        'test_1',
        1,
        ['test'=>true,'verbose'=>true]
    )
);

echo EOL.'Getting user objects with actions'.EOL;
var_dump(
    $ObjectAuthHandler->userObjects(
        'test_1',
        1,
        [
            'objects' => ['test_1','test_2'],
            'requiredActions' => ['test_1','test_2'],
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting group objects'.EOL;
var_dump(
    $ObjectAuthHandler->groupObjects(
        'test_1',
        3,
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting group objects with actions'.EOL;
var_dump(
    $ObjectAuthHandler->groupObjects(
        'test_1',
        3,
        [
            'objects' => ['test_1','test_2'],
            'requiredActions' => ['test_1','test_2'],
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting group users'.EOL;
var_dump(
    $ObjectAuthHandler->groupUsers(
        'test_1',
        1,
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);

echo EOL.'Getting group users - specific ones'.EOL;
var_dump(
    $ObjectAuthHandler->groupUsers(
        'test_1',
        1,
        [
            'users' => [1,2,3],
            'test'=>true,
            'verbose'=>true
        ]
    )
);