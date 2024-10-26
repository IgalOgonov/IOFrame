<?php

require_once __DIR__.'/../../IOFrame/Handlers/UsersHandler.php';
$UsersHandler = new \IOFrame\Handlers\UsersHandler($settings,['SQLManager'=>$SQLManager]);

echo EOL.'Getting all users with many parameters'.EOL;
var_dump($UsersHandler->getUsers([
    'idAtLeast' =>1,
    'idAtMost' => 3,
    'rankAtLeast' => 0,
    'rankAtMost' => 10000,
    'usernameLike' =>'A',
    'emailLike' =>'.com',
    'isActive' => true,
    'isBanned' =>false,
    'isSuspicious' => false,
    'createdBefore' => 999999999999,
    'createdAfter' => 0,
    'orderBy' =>'Email',
    'orderType' => 0,
    'limit' =>5,
    'offset' =>0,
    'test'=>true
]));

echo EOL.'Updating user 1 with all possible parameters'.EOL;
var_dump($UsersHandler->updateUser(
    1,
    [
        'username' =>'Test Username',
        'email' => 'test@test.com',
        'active' => 1,
        'created' => 99999999999,
        'bannedDate' =>99999999999
    ],
    'ID',
    ['test'=>true]
));
echo EOL.'Updating user 1 with 1 parameter'.EOL;
var_dump($UsersHandler->updateUser(
    1,
    [
        'username' =>'Test Username'
    ],
    'ID',
    ['test'=>true]
));

echo EOL.'Changing password of user 1 to R43W32E43Q65:'.EOL;
var_dump($UsersHandler->changePassword(1,'R43W32E43Q65',['test'=>true]));

echo EOL.'Changing email of user 1 to test@test.com:'.EOL;
var_dump($UsersHandler->changeMail(1,'test@test.com',['test'=>true]));
echo EOL;

echo EOL.'Checks whether user 1 can be logged into:'.EOL;
var_dump($UsersHandler->checkUserLogin(1,['test'=>true]));
echo EOL;

echo EOL.'Checks whether unexisting user can be logged into:'.EOL;
var_dump($UsersHandler->checkUserLogin('fake@mail.com',['test'=>true]));
echo EOL;

echo EOL.'Checks whether user one can log in - checks a fake code an a fake IP:'.EOL;
echo $UsersHandler->checkUserLogin(1,['allowCode'=>'code','allowWhitelistedIP'=>'0.1.2.3','test'=>true]).EOL;

echo EOL.'Creating invite token:'.EOL;
echo $UsersHandler->createInviteToken([],['test'=>true]).EOL;

echo EOL.'Creating 2 invite tokens:'.EOL;
var_dump($UsersHandler->createInviteTokens([['token'=>'test_1','action'=>'REGISTER_MAIL','mail'=>'test@test.com'],['token'=>'test_2','action'=>'REGISTER_ANY']],['verbose'=>true]));

echo EOL.'Sending invite mail:'.EOL;
echo $UsersHandler->sendInviteMail('igal@iosoft.io',1,'Test Invite',false,['test'=>true]).EOL;

echo EOL.'Confirming invite token test_1 - without mail'.EOL;
var_dump($UsersHandler->confirmInviteToken('test_1',['test'=>true,'consume'=>false]));

echo EOL.'Confirming invite token test_1 - with mail'.EOL;
var_dump($UsersHandler->confirmInviteToken('test_1',['test'=>true,'mail'=>'test@test.com','consume'=>false]));

echo EOL.'Confirming invite token test_2'.EOL;
var_dump($UsersHandler->confirmInviteToken('test_2',['test'=>true,'consume'=>false]));

echo EOL.'Consuming invite token test_2'.EOL;
var_dump($UsersHandler->confirmInviteToken('test_2',['test'=>true]));

echo EOL.'Consuming invite token test_1'.EOL;
var_dump($UsersHandler->confirmInviteToken('test_1',['test'=>true]));

echo EOL.'Create a new activated user using token test_2'.EOL;
var_dump($UsersHandler->regUser(
    [
        'u'=>'TestUser',
        'm'=>'test@test.com',
        'p'=>'TestPassword1',
    ],
    [
        'test'=>true,
        'activateToken'=>'test_1'
    ]
));