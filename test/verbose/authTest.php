<?php
echo EOL.'Updating user info from DB:'.EOL;
$auth->updateUserInfoFromDB(['test'=>true]);
echo EOL;

echo EOL.'Modifying rank of user 1 to be 4:'.EOL;
var_dump($auth->modifyUserRank(1,4,['test'=>true]));
echo EOL;

echo EOL.'Modifying user 1 actions - adding TEST_1, removing ASSIGN_OBJECT_AUTH, adding BAN_USERS_AUTH:'.EOL;
var_dump($auth->modifyUserActions(1,['TEST_1'=>true,'BAN_USERS_AUTH'=>true,'ASSIGN_OBJECT_AUTH'=>false],['test'=>true]));
echo EOL;

echo EOL.'Modifying user 1 groups - adding to TEST_G1, removing from TEST_G2, removing from Another Test Group:'.EOL;
var_dump($auth->modifyUserGroups(1,['TEST_G1'=>true,'TEST_G2'=>false,'Another Test Group'=>false],['test'=>true]));
echo EOL;

echo EOL.'Modifying group Test Group - adding STRANGE_ACTION, removing FAKE:'.EOL;
var_dump(
    $auth->modifyGroupActions(
        'Test Group',
        ['STRANGE_ACTION'=>true,'FAKE'=>false],
        ['test'=>true]
    )
);
echo EOL;

echo EOL.'Setting action TEST_ACTION:'.EOL;
var_dump($auth->setActions([ 'TEST_ACTION'=>'Test Action.', 'BAN_USERS_AUTH'=>'Required to ban users.' ],['test'=>true]));
echo EOL;

echo EOL.'Deleting action TEST_ACTION, BAN_USERS_AUTH:'.EOL;
var_dump($auth->deleteActions(['TEST_ACTION', 'BAN_USERS_AUTH' ],['test'=>true]));
echo EOL;

echo EOL.'Setting new descriptions for groups Test Group, Another Test Group:'.EOL;
var_dump(
    $auth->setGroups(
        [ 'Test Group'=>'Test Description.', 'Another Test Group'=>'Test Description II - The Description Strikes Back.' ],
        ['test'=>true]
    )
);
echo EOL;

echo EOL.'Deleting groups Fake Test Group, Test Group:'.EOL;
var_dump($auth->deleteGroups(['Fake Test Group', 'Test Group' ],['test'=>true]));
echo EOL;

echo EOL.'Getting UP TO ONE user with action PLUGIN_GET_INFO_AUTH, or that are in Test Group:'.EOL;
var_dump($auth->getUsers(
        [
            'action'=>['=','PLUGIN_GET_INFO_AUTH'],
            'group'=>['=','Test Group'],
            'separator'=>'OR',
            'limit'=>1,
            'offset'=>0,
            'test'=>true
        ])
    );
echo EOL;

echo EOL.'Getting users with actions that are either PLUGIN_GET_INFO_AUTH or are in Test Group:'.EOL;
var_dump($auth->getUsersWithActions(
        [
            'action'=>['=','PLUGIN_GET_INFO_AUTH'],
            'group'=>['=','Test Group'],
            'separator'=>'OR',
            'test'=>true
        ])
    );
echo EOL;

echo EOL.'Getting groups that have the action BAN_USERS_AUTH, or have the action PLUGIN_GET_INFO_AUTH:'.EOL;
var_dump($auth->getGroups(
        [
            'action'=>[['=','BAN_USERS_AUTH'],['=','PLUGIN_GET_INFO_AUTH']],
            'separator'=>'OR',
            'test'=>true
        ]
        )
    );
echo EOL;

echo EOL.'Getting groups that include BAN_USERS_AUTH or the user with id 1 is in them, and all those groups\' actions:'.EOL;
var_dump($auth->getGroupActions(
        [
            'id'=>[['=','1']],
            'action'=>['=','BAN_USERS_AUTH'],
            'separator'=>'AND',
            'test'=>true
        ])
    );
echo EOL;
