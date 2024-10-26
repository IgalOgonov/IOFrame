<?php
/* This the the API that handles all the tokens functions, like getting/setting tokens.

 *      See standard return values at defaultInputResults.php
 *
 * Parameters:
 * "action"     - Requested action - described below
 *_________________________________________________
 * getTokens
 *      Gets tokens.
 *      params:
 *              tokens - string, JSON array, default [] - optional array of specific tokens to get
 *              tokenLike - string, regex pattern, default null - if set, returns results where the token matches this pattern
 *              actionLike - string, regex pattern, default null - if set, returns results where the action matches this pattern
 *              containsTags - string,JSON encoded array, default null - tags, at least one of which the token must contain
 *              usesAtLeast - int, default null - if set, returns results that have at least this many uses left
 *              usesAtMost - int, default null - if set, returns results that have at most this many uses left
 *              expiresBefore - string, unix timestamp, default null - if set, returns results that expire BEFORE specified timestamp
 *              expiresAfter - string, unix timestamp, default null - if set, returns results that expire AFTER specified timestamp
 *              ignoreExpired - bool, default true - whether to ignore expired tokens
 *              limit => SQL parameter LIMIT
 *              offset=> SQL parameter OFFSET. Only changes anything if limit is set.
 *
 *      Returns array of the form:
 *          [
 *           <token> => Code/Array,
 *           <token> => Code/Array,
 *           ...
 *           '@' => [
 *                      '#' => number of tokens without limit
 *                  ]
 *          ]
 *      Array is of the form:
 *          [
 *           'action' => string, action of the token,
 *           'uses' => int, how many uses are left,
 *           'expires' => string, how many uses are left,
 *           'locked' => bool, whether the item is currently locked (and may be undergoing changes)
 *          ]
 *      Possible codes (only when specific tokens are passed) are:
 *          1 - item does not exist
 *
 *      Examples:
 *          action=getTokens
 *          action=getTokens&ignoreExpired=false&tokenLike=test&actionLike=test&usesAtLeast=1&usesAtMost=9&expiresBefore=1600000000&expiresAfter=1400000000
 *_________________________________________________
 * setToken
 *      Sets a token
 *      params:
 *              token - string, up to 256 characters>'
 *              tokenAction - string, up to 1024 characters, defaults to null
 *              uses - int, defaults to 1
 *              ttl - int, TTL in seconds (from creation time), defaultsto an hour
 *              overwrite => bool, whether to override existing tokens or not
 *              update => bool, whether to only update existing tokens or not
 *
 *      Returns int:
 *         -2 - token already exists, overwrite is true, but the token is locked
 *         -1 - could not reach db
 *          0 - success
 *          1 - token already exists and overwrite is false
 *          2 - token doesn't exist and update is true
 *          3 - "action" was not passed, and token did not previously exist
 *
 *      Examples:
 *          action=setToken&token=test1&tokenAction=test action 1&uses=10&ttl=4000
 *_________________________________________________
 * setTokens
 *      Sets a number of tokens
 *      params:
 *              tokens - string, JSON array of objects of the form:
 *              [
 *              '<token name, string, up to 256 characters>' => [
 *                      'action' => string, up to 1024 characters,
 *                      'uses' => int, defaults to 1
 *                      'ttl' => int, TTL in seconds (from creation time)
 *                  ]
 *              ]
 *              overwrite => bool, whether to override existing tokens or not
 *              update => bool, whether to only update existing tokens or not
 *
 *      Returns  array of the form:
 *          [
 *          '<token name>' => code,
 *          ...
 *          ]
 *          where possible codes are:
 *         -2 - token already exists, overwrite is true, but the token is locked
 *         -1 - could not reach db
 *          0 - success
 *          1 - token already exists and overwrite is false
 *          2 - token doesn't exist and update is true
 *
 *      Examples:
 *          action=setTokens&tokens={"test1":{"action":"test action 1","uses":10,"ttl":4000},"test2":{"action":"test action 2","uses":1,"ttl":3600},"test3":{"action":"test action 3","ttl":4500}}
 *_________________________________________________
 * deleteTokens Deletes specific tokens
 *
 *      params:
 *              tokens - string, array of token names
 *
 *      Returns code:
 *         -1 - Failed to connect to db
 *          0 - All good
 *
 *      Examples:
 *          action=deleteTokens&tokens=["test1","test2"]

 *_________________________________________________
 * deleteExpiredTokens
 *      Deletes expired tokens
 *      params:
 *              time - int|string, defaults to time() - time before which tokens are considered expired.
 *
 *      Returns code:
 *         -1 - Failed to connect to db
 *          0 - All good
 *
 *      Examples:
 *          action=deleteExpiredTokens
 *          action=deleteExpiredTokens&time=1600000000
 *
 *
 * */

if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../../main/core_init.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';
require 'tokens_fragments/definitions.php';
require __DIR__ . '/../../IOFrame/Handlers/TokenHandler.php';

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');

if($test)
    echo 'Testing mode!'.EOL;

if(!checkApiEnabled('tokens',$apiSettings,$SecurityHandler,$_REQUEST['action']))
    exit(API_DISABLED);

$target = $_REQUEST["target"] ?? '';

$action = $_REQUEST["action"];

if(isset($_REQUEST['params']))
    $params = json_decode($_REQUEST['params'],true);
else
    $params = null;

switch($action){

    case 'getTokens':

        $arrExpected = ["tokens","tokenLike","actionLike","containsTags","usesAtLeast","usesAtMost","expiresBefore","expiresAfter","ignoreExpired","limit","offset"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'tokens_fragments/getTokens_auth.php';
        require 'tokens_fragments/getTokens_checks.php';
        require 'tokens_fragments/getTokens_execution.php';
        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo !$result? '0' : $result;
        break;

    case 'setToken':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["token","tags","tokenAction","uses","ttl","overwrite","update"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'tokens_fragments/setToken_auth.php';
        require 'tokens_fragments/setToken_checks.php';
        require 'tokens_fragments/setToken_execution.php';
        echo !$result? '0' : $result;
        break;

    case 'setTokens':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["tokens","overwrite","update"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'tokens_fragments/setTokens_auth.php';
        require 'tokens_fragments/setTokens_checks.php';
        require 'tokens_fragments/setTokens_execution.php';
        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        else
            echo !$result? '0' : $result;
        break;

    case 'deleteTokens':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["tokens"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'tokens_fragments/deleteTokens_auth.php';
        require 'tokens_fragments/deleteTokens_checks.php';
        require 'tokens_fragments/deleteTokens_execution.php';
        echo !$result? '0' : $result;
        break;

    case 'deleteExpiredTokens':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["time"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'tokens_fragments/deleteExpiredTokens_auth.php';
        require 'tokens_fragments/deleteExpiredTokens_checks.php';
        require 'tokens_fragments/deleteExpiredTokens_execution.php';
        echo !$result? '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}
