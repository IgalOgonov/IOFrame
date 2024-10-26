<?php
/*Maps*/
$columnMap = [
    'category'=>'Object_Auth_Category',
    'object'=>'Object_Auth_Object',
    'public'=>'Is_Public',
    'action'=>'Object_Auth_Action',
    'group'=>'Object_Auth_Group',
    'title'=>'Title',
    'created'=>'Created',
    'updated'=>'Last_Updated',
    'userID'=>'ID'
];

$resultsColumnMap = [
    'Object_Auth_Category'=>'category',
    'Title'=>'title',
    'Object_Auth_Object'=>'object',
    'Object_Auth_Action'=>'action',
    'Object_Auth_Group'=>'group',
    'ID'=>'userID',
    'Is_Public'=>'public',
    'Created'=>'created',
    'Last_Updated'=>'updated'
];

/* REGEX */
CONST REGEX_REGEX = '^[\w\-\.\_ ]{1,128}$';
CONST OBJECT_REGEX = '^[a-zA-Z][a-zA-Z0-9\.\-\_ ]{1,255}$';
CONST ACTION_REGEX = '^[a-zA-Z][a-zA-Z0-9\.\-\_ ]{1,255}$';
CONST TITLE_REGEX = '^[a-zA-Z][a-zA-Z0-9\.\-\_ ]{1,1023}$';

/* AUTH */
/*General action that allows viewing ALL object-auth items*/
CONST OBJECT_AUTH_VIEW = 'OBJECT_AUTH_VIEW';
/*General action that allows modifying ALL object-auth items - this includes creation, deletion, movement and any sets with the
  'update' parameter set. */
CONST OBJECT_AUTH_MODIFY = 'OBJECT_AUTH_MODIFY';
