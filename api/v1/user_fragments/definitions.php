<?php
/*Limit*/
CONST USERS_API_LIMITS =[
    'addUser' => [
        'rate' => [
            'limit'=>60,
            'category'=>0,
            'action'=>3
        ],
    ],
    'logUser' => [
        'rate' => [
            'limit'=>2,
            'category'=>0,
            'action'=>0
        ],
        'markOnLimit'=>true,
        'susOnLimit'=>true,
        'banOnLimit'=>false,
        'ipAction'=>0,
        'userAction'=>0
    ],
    'pwdReset' => [
        'rate' => [
            'limit'=>2,
            'category'=>1,
            'action'=>2
        ],
        'susOnLimit'=>false,
        'banOnLimit'=>false,
        'userAction'=>2
    ],
    'mailReset' => [
        'rate' => [
            'limit'=>2,
            'category'=>1,
            'action'=>2
        ],
        'susOnLimit'=>false,
        'banOnLimit'=>false,
        'userAction'=>2
    ],
    'regConfirm' => [
        'rate' => [
            'limit'=>2,
            'category'=>1,
            'action'=>1
        ],
        'susOnLimit'=>false,
        'banOnLimit'=>false,
        'userAction'=>1
    ],
];

/* AUTH */
CONST GET_USERS_AUTH = 'GET_USERS_AUTH';
CONST SET_USERS_AUTH = 'SET_USERS_AUTH';
CONST BAN_USERS_AUTH = 'BAN_USERS_AUTH';
CONST INVITE_USERS_AUTH = 'INVITE_USERS_AUTH';
CONST SET_INVITE_MAIL_ARGS = 'SET_INVITE_MAIL_ARGS';


/* Input */
//Maximum tree content length
CONST USER_RANK_REGEX = 10000;
CONST LANGUAGE_REGEX = '^[a-zA-Z]{0,32}$';
CONST REGEX_REGEX = '^[\w\.\-\_ ]{1,128}$';
CONST USER_ORDER_COLUMNS = ['Created_On', 'Email', 'Username','ID'];
CONST PHONE_REGEX = '^\+\d{6,20}$';
CONST TWO_FACTOR_AUTH_CODE_REGEX = '^[0-9]{6,6}$';
CONST TWO_FACTOR_AUTH_SMS_REGEX = '^\[a-zA-Z0-9]{6,6}$';
CONST TWO_FACTOR_AUTH_EMAIL_REGEX = '^[a-zA-Z0-9]{6,6}$';
CONST TOKEN_REGEX = '^[\w][\w ]{0,255}$';



