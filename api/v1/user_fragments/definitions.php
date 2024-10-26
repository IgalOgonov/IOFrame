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
        'failed2FAReportingRate' => [
            'limit'=>180,
            'category'=>0,
            'action'=>4
        ],
        'userAction'=>[
            'action'=>0,
            'susOnLimit'=>true
        ],
        'ipAction'=>[
            'action'=>0,
            'markOnLimit'=>true,
        ],
        'userAction2FA'=>[
            'action'=>4,
            'susOnLimit'=>true,
            'lockOnLimit'=>true
        ],
        'ipAction2FA'=>[
            'action'=>2,
            'markOnLimit'=>true
        ]
    ],
    'pwdReset' => [
        'rate' => [
            'limit'=>2,
            'category'=>1,
            'action'=>2
        ],
        'userAction'=>[
            'action'=>2,
            'susOnLimit'=>false
        ]
    ],
    'mailReset' => [
        'rate' => [
            'limit'=>2,
            'category'=>1,
            'action'=>2
        ],
        'userAction'=>[
            'action'=>2,
            'susOnLimit'=>false
        ]
    ],
    'regConfirm' => [
        'rate' => [
            'limit'=>2,
            'category'=>1,
            'action'=>1
        ],
        'userAction'=>[
            'action'=>1,
            'susOnLimit'=>false
        ]
    ],
    'requestMail2FA' => [
        'rate' => [
            'limit'=>2,
            'category'=>1,
            'action'=>3
        ],
        'userAction'=>[
            'action'=>3,
            'susOnLimit'=>true
        ]
    ],
];

CONST USER_2FA_AFTER_VALID_LOGIN_CREDENTIALS = 600;

/* AUTH */
CONST GET_USERS_AUTH = 'GET_USERS_AUTH';
CONST SET_USERS_AUTH = 'SET_USERS_AUTH';
CONST BAN_USERS_AUTH = 'BAN_USERS_AUTH';
CONST INVITE_USERS_AUTH = 'INVITE_USERS_AUTH';
CONST SET_INVITE_MAIL_ARGS = 'SET_INVITE_MAIL_ARGS';


/* Input */
CONST LANGUAGE_REGEX = '^[a-zA-Z]{0,32}$';
CONST REGEX_REGEX = '^[\w\.\-\_ ]{1,128}$';
CONST USER_ORDER_COLUMNS = ['Created', 'Email', 'Username','ID'];
CONST PHONE_REGEX = '^\+\d{6,20}$';
CONST TWO_FACTOR_AUTH_CODE_REGEX = '^[0-9]{6}$';
CONST TWO_FACTOR_AUTH_SMS_REGEX = '^\[a-zA-Z0-9]{6}$';
CONST TWO_FACTOR_AUTH_EMAIL_REGEX = '^[a-zA-Z0-9]{8}$';
CONST TOKEN_REGEX = '^[\w][\w ]{0,255}$';



