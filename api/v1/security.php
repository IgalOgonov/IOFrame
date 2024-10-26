<?php
/* This the the API that handles all (framework internal) security related functions.
 * There are two types of functions related to security - those related to the event rulebook, and those related to IP blacklists/whitelists.
 *
 *      See standard return values at defaultInputResults.php
 * ---- EVENTS RULEBOOK RELATED ----
 *_________________________________________________
 * getRulebookCategories
 *      - Gets all rulebook categories
 *
 *        Examples: action=getRulebookCategories
 *
 *        Returns Array of the form:
 *
 *          [
 *           <category id> => [
 *              'name' => <string | null, Category name (if exists)>,
 *              'desc' => <string | null, Category description (if exists)>,
 *          ]
 *_________________________________________________
 * getRulebookRules
 *      - Gets rulebook rules - all, or just specific ones.
 *              'category' - Event Category filter, defaults to null - available ones are 0 for IP, 1 for User, but others may be defined
 *              'type'     - Event Type filter, defaults to null - if set, returns events of specific type
 *              *note: If both category and type are set, will be able to use cache for much faster results.
 *
 *        Examples: action=getRulebookRules
 *                  action=getRulebookRules&category=0
 *                  action=getRulebookRules&category=0&type=0
 *
 *      Returns json Array of the form:
 *          [
 *              <int, event category>/<int, event type> => [
 *                  [
 *                      <int, sequence number> => [
 *                          'blacklistFor' => <int, how many seconds the IP/User would get blacklisted for if he reaches this number of events>,
 *                          'addTTL' => <int, number of seconds to "remember" the IP/user event for.
 *                                      Added to any remaining time if an unexpired event of the type exists for the user/IP.>,
 *                          'meta' => <string, JSON Encoded array - optional information about this specific sequence>
 *                      ],
 *
 *                      <int, sequence number> => [
 *                          ...
 *                      ],
 *
 *                      ...,
 *
 *                      '@' => [
 *                          'name' => <string | null, Event type name (if exists)>,
 *                          'desc' => <string | null, Event type description (if exists)>,
 *                      ]
 *                  ]
 *              ],
 *              <int, event category>/<int, event type> => [
 *                  ...
 *              ]
 *          ]
 *
 *_________________________________________________
 * setRulebookRules
 *      - Sets Event rules into the Events rulebook
 *        inputs:  string, JSON encoded array of objects, where each member is of the form:
 *          [
 *              'category' => int, Event category (required)
 *              'type'     => int, Event Type (required)
 *              'sequence' => int, Sequence number (required)
 *              'addTTL'     =>int, default 0 - For how long this action will prolong the "memory" of the current event sequence
 *              'blacklistFor' =>int, default 0 - For how long an IP/User will get blacklisted after the rule is reached (for default categories)
 *              'meta' => string, default null - JSON string that gets recursively merged with with anything that exists, just written otherwise.
 *          ]
 *        override: bool, default true, Will override existing events (defined by 'category','type' and 'sequence')
 *        update: bool, default false, Will only update existing events (defined by 'category','type' and 'sequence')
 *
 *        Examples: action=setRulebookRules&override=false&inputs=[{"category":0,"type":0,"sequence":0,"addTTL":3600},{"category":2,"type":5,"sequence":0,"addTTL":3600,"blacklistFor":5}]
 *
 *        Returns:
 * Array of codes of the form:
 *          [
 *              <int, event category>/<int, event type>/<int, sequence number> => <int Code>
 *          ]
 *          where each code is:
 *          -1 server error (would be the same for all)
 *           0 rule changed successfully
 *           1 rule exists and 'override' is false
 *           2 rule does not exist and 'update' is true
 *_________________________________________________
 * deleteRulebookRules
 *      - Deletes Event rules from the Events rulebook
 *        inputs:  string, JSON encoded array of objects, where each member is of the form:
 *          [
 *              'category' => int, Event category (required)
 *              'type'     => int, Event Type (required)
 *              'sequence' => int, default null, Sequence number.
 *                                  If even one of those isn't set per category/type duo, deletes that whole event type.
 *          ]
 *
 *        Examples:
 *          action=deleteRulebookRules&override=false&inputs=[{"category":0,"type":0,"sequence":0},{"category":0,"type":0,"sequence":5},{"category":2,"type":5}]
 *
 *        Returns integer code:
 *             -1 - failed to connect to DB
 *              0 - success
 *_________________________________________________
 * getEventsMeta
 *      - Gets Events rulebook meta.
 *        inputs:  string, JSON encoded array of objects, where each member is of the form:
 *          [
 *              'category' => int, Event category (required)
 *              'type'     => int, default -1 (for the meta of the event itself) -  Event Type
 *          ]
 *         limit: int, default 50 - typical pagination parameter
 *         offset: int, typical pagination parameter
 *
 *        Examples:
 *          action=getEventsMeta
 *          action=getEventsMeta&inputs=[{"category":0},{"category":0,"type":0},{"category":1,"type":-1}]
 *
 *        Returns array Object of arrays OR INT CODES the form:
 *          [
 *              <int, event category>[/<int, event type>] => [
 *                  'meta' => JSON string, meta information regarding the event category or type
 *              ]
 *              OR
 *              <int, event category>[/<int, event type>] =>
 *                  CODE where the possible codes are:
 *                  -1 - server error
 *                   1 - item does not exist,
 *              ... ,
 *              '@' => [
 *                  '#' => <int, number of results without limit>
 *              ]
 *          ]
 *_________________________________________________
 * setEventsMeta
 *      - Sets Event rules meta information (typically display name)
 *        inputs:  string, JSON encoded array of objects, where each member is of the form:
 *          [
 *              'category' => int, Event category (required)
 *              'type'     => int, Event Type (required)
 *              'meta' => string, default null - JSON string that gets recursively merged with with anything that exists, just written otherwise.
 *          ]
 *        override: bool, default true, Will override existing events meta (defined by 'category','type')
 *        update: bool, default false, Will only update existing events meta (defined by 'category','type')
 *
 *        Examples: action=setEventsMeta&inputs=[{"category":0,"type":0,"meta":"{\"name\":\"IP Incorrect Login Limit\"}"},{"category":1,"type":0,"meta":"{\"name\":\"User Incorrect Login Limit\"}"}]
 *
 *        Returns Array of codes of the form:
 *          [
 *              <int, event category>[/<int, event type>] => <int Code>
 *          ]
 *          where each code is:
 *          -1 server error (would be the same for all)
 *           0 item set successfully
 *           1 item does not exist and 'update' is true
 *           2 item exists and 'override' is false
 *_________________________________________________
 * deleteEventsMeta
 *      - Sets Event rules meta information
 *        inputs:  string, JSON encoded array of objects, where each member is of the form:
 *          [
 *              'category' => int, Event category (required)
 *              'type'     => int, Event Type (required)
 *          ]
 *
 *        Examples: action=deleteEventsMeta&inputs=[{"category":0,"type":0},{"category":1,"type":-1}]
 *
 *        Returns integer codes:
 *          -1 server error (would be the same for all)
 *           0 success (does not check if items do not exist)
 *_________________________________________________
 * ---- IP RELATED ----
 *_________________________________________________
 * getIPs
 *      - Gets all -or some - IPs
 *              'ips' - string, JSON array of strings, defaults to [] - each item must be a valid IPv4.
 *              'reliable'     - bool, default null - if set, only returns results which are or aren't reliable
 *              'type'    - bool, default null - if set, only returns results which are blacklisted (false) or whitelisted (true)
 *              'ignoreExpired' - bool, default true. Whether to ignore expired results
 *              -- if ips are not set --
 *              'limit'     - int, default null -standard SQL limit
 *              'offset'    - int, default null, standard SQL offset
 *
 *        Examples:
 *          action=getIPs
 *          action=getIPs&ips=["10.213.234.0"]&reliable=false&type=true&ignoreExpired=false&limit=50
 *
 *        Returns Array of the form:
 *
 *          [
 *           <ip> => [
 *              'IP' => <string, redundant IP>,
 *              'reliable' => <bool, whether the IP is reliable>,
 *              'type' => <bool, true for whitelist, false for blacklist>,
 *              'expires' => <string, unix timestamp (second precision) of when this listing expires>,
 *              ],
 *           ...,
 *           [if ips is not set] '@' => [
 *                  '#'=><int, number of results without limit or offset>
 *              ]
 *          ]
 *_________________________________________________
 * getIPRanges
 *      - Gets all -or some - IPv4 ranges
 *              'ranges' - string, JSON array of arrays, defaults to [] - each item must be of the form:
 *                          [
 *                              'prefix' => string, defaults to '' - valid IP prefix ('', '54', '46.212', '35.127.213', etc)
 *                              'from' => int, the start of the ip range (0-255)
 *                              'to' =>  int, the start of the ip range (0-255)
 *                          ]
 *              'type'    - bool, default null - if set, only returns results which are blacklisted (false) or whitelisted (true)
 *              'ignoreExpired' - bool, default true. Whether to ignore expired results
 *              -- if ips are not set --
 *              'limit'     - int, default null -standard SQL limit
 *              'offset'    - int, default null, standard SQL offset
 *
 *        Examples:
 *          action=getIPRanges
 *          action=getIPRanges&ranges=[{"prefix":"10.10","from":0,"to":21}]&type=true&ignoreExpired=false&limit=50
 *
 *        Returns Array of the form:
 *          [
 *           <prefix>/<from>/<to> => [
 *              'prefix' => <string, range prefix>,
 *              'from' => <int>,
 *              'to' => <int>,
 *              'type' => <bool, true for whitelist, false for blacklist>,
 *              'expires' => <string, unix timestamp (second precision) of when this listing expires>,
 *              ],
 *           ...,
 *           [if ips is not set] '@' => [
 *                  '#'=><int, number of results without limit or offset>
 *              ]
 *          ]
 *_________________________________________________
 * addIP
 *      - Adds a new IP
 *              'ip' - string, a valid IPv4.
 *              'type' - bool, blacklisted (false) or whitelisted (true) IP
 *              'reliable' - bool, default false - whether the IP is reliable or not
 *              'ttl' - int, default 0 - How long the listing should exist (in seconds, from current time) before it expires. 0 means indefinitely
 *
 *        Examples:
 *          action=addIP&ip=20.145.54.6&type=0
 *          action=addIP&ip=20.145.54.6&type=1&reliable=1&ttl=65857
 *
 *        Returns Array of the form:
 *
 *        1 success
 *        0 failure
 *_________________________________________________
 * updateIP
 *      - Updates an existing IP
 *              'ip' - string, a valid IPv4.
 *              'type' - bool, default null - blacklisted (false) or whitelisted (true) IP
 *              'reliable' - bool, default false - whether the IP is reliable or not
 *              'ttl' - int, default 0 - How long the listing should exist (in seconds, from current time) before it expires. 0 means indefinitely
 *
 *        Examples:
 *          action=updateIP&ip=20.145.54.6&type=0
 *          action=updateIP&ip=20.145.54.6&type=1&reliable=1&ttl=65857
 *
 *        Returns Array of the form:
 *
 *        1 success
 *        0 failure
 *_________________________________________________
 * deleteIP
 *      - Deletes an existing IP
 *              'ip' - string, a valid IPv4.
 *
 *        Examples:
 *          action=deleteIP&ip=10.213.234.0
 *
 *        Returns Array of the form:
 *
 *        1 success
 *        0 failure
 *_________________________________________________
 * addIPRange
 *      - Adds a new IP
 *              'prefix' - string, a valid IPV4 Prefix ('','xxx','xxx.xxx','xxx.xxx.xxx')
 *              'from' - int - Range (0-255)
 *              'to' - int - Range (0-255)
 *              'type' - bool, blacklisted (false) or whitelisted (true) IP
 *              'ttl' - int, default 0 - How long the listing should exist (in seconds, from current time) before it expires. 0 means indefinitely
 *
 *        Examples:
 *          action=addIPRange&prefix=10.23&from=12&to=254&type=false&ttl=36000
 *          action=addIPRange&prefix=10.23&from=12&to=254
 *
 *        Returns Array of the form:
 *
 *        1 success
 *        0 failure
 *_________________________________________________
 * updateIPRange
 *      - Updates an existing IP
 *              'prefix' - string, a valid IPV4 Prefix ('','xxx','xxx.xxx','xxx.xxx.xxx')
 *              'from' - int - Range (0-255)
 *              'to' - int - Range (0-255)
 *              -- Those are optional --
 *              'newFrom' - int - Range (0-255)
 *              'newTo' - int - Range (0-255)
 *              'type' - bool, blacklisted (false) or whitelisted (true) IP
 *              'ttl' - int, default 0 - How long the listing should exist (in seconds, from current time) before it expires. 0 means indefinitely
 *
 *        Examples:
 *          action=updateIPRange&prefix=10.23&from=12&to=255&type=true&newTo=23
 *
 *        Returns Array of the form:
 *
 *        1 success
 *        0 failure
 *_________________________________________________
 * deleteIPRange
 *      - Deletes an existing IP range
 *              'prefix' - string, a valid IPV4 Prefix ('','xxx','xxx.xxx','xxx.xxx.xxx')
 *              'from' - int - Range (0-255)
 *              'to' - int - Range (0-255)
 *
 *        Examples:
 *          action=deleteIPRange&prefix=10.23&from=12&to=255
 *
 *        Returns Array of the form:
 *
 *        1 success
 *        0 failure
 *_________________________________________________
 * deleteExpired
 *      - Deletes expired IPs or IP ranges
 *              'range' - bool, default false. Whether to delete expired IP ranges, or IPs.
 *
 *        Examples:
 *          action=deleteExpired
 *          action=deleteExpired&range=false
 *
 *        Returns Array of the form:
 *
 *        1 success
 *        0 failure
 * */

if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../../main/core_init.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';
require 'security_fragments/definitions.php';

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];

if($test)
    echo 'Testing mode!'.EOL;

if(!checkApiEnabled('security',$apiSettings,$SecurityHandler,$_REQUEST['action']))
    exit(API_DISABLED);

$SecurityHandler = new \IOFrame\Handlers\SecurityHandler($settings,$defaultSettingsParams);
$IPHandler = new \IOFrame\Handlers\IPHandler($settings,$defaultSettingsParams);


//Handle inputs
$inputs = [];

//Standard pagination inputs
$standardPaginationInputs = ['limit','offset'];

switch($action){

    case 'getRulebookCategories':
        $arrExpected = [];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/getRulebookCategories_auth.php';
        require 'security_fragments/getRulebookCategories_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        else
            echo !$result? '0' : $result;
        break;

    case 'getRulebookRules':

        $arrExpected = ["category","type"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/getRulebookRules_auth.php';
        require 'security_fragments/getRulebookRules_checks.php';
        require 'security_fragments/getRulebookRules_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo !$result? '0' : $result;
        break;

    case 'setRulebookRules':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["inputs","override","update"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/setRulebookRules_auth.php';
        require 'security_fragments/setRulebookRules_checks.php';
        require 'security_fragments/setRulebookRules_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo !$result? '0' : $result;
        break;

    case 'deleteRulebookRules':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["inputs"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/deleteRulebookRules_auth.php';
        require 'security_fragments/deleteRulebookRules_checks.php';
        require 'security_fragments/deleteRulebookRules_execution.php';

        echo !$result? '0' : $result;
        break;

    case 'getEventsMeta':

        $arrExpected = ["inputs","limit","offset"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/getEventsMeta_auth.php';
        require 'security_fragments/getEventsMeta_checks.php';
        require 'security_fragments/getEventsMeta_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo !$result? '0' : $result;
        break;

    case 'setEventsMeta':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["inputs","override","update"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/setEventsMeta_auth.php';
        require 'security_fragments/setEventsMeta_checks.php';
        require 'security_fragments/setEventsMeta_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo !$result? '0' : $result;
        break;

    case 'deleteEventsMeta':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["inputs"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/deleteEventsMeta_auth.php';
        require 'security_fragments/deleteEventsMeta_checks.php';
        require 'security_fragments/deleteEventsMeta_execution.php';

        echo !$result? '0' : $result;
        break;

    /*** IP Related ***/

    case 'getIPs':

        $arrExpected = ["ips","reliable","type","ignoreExpired","limit","offset"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/getIPs_auth.php';
        require 'security_fragments/getIPs_checks.php';
        require 'security_fragments/getIPs_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo !$result? '0' : $result;
        break;

    case 'getIPRanges':

        $arrExpected = ["ranges","type","ignoreExpired","limit","offset"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/getIPRanges_auth.php';
        require 'security_fragments/getIPRanges_checks.php';
        require 'security_fragments/getIPRanges_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo !$result? '0' : $result;
        break;

    case 'addIP':
    case 'updateIP':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["ip","type","reliable","ttl"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/setIP_auth.php';
        require 'security_fragments/setIP_checks.php';
        require 'security_fragments/setIP_execution.php';

        echo !$result? '0' : $result;
        break;

    case 'deleteIP':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["ip"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/deleteIP_auth.php';
        require 'security_fragments/deleteIP_checks.php';
        require 'security_fragments/deleteIP_execution.php';

        echo !$result? '0' : $result;
        break;

    case 'addIPRange':
    case 'updateIPRange':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["prefix","from","to","newFrom","newTo","type","ttl"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/setIPRange_auth.php';
        require 'security_fragments/setIPRange_checks.php';
        require 'security_fragments/setIPRange_execution.php';

        echo !$result? '0' : $result;
        break;

    case 'deleteIPRange':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["prefix","from","to"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/deleteIPRange_auth.php';
        require 'security_fragments/deleteIPRange_checks.php';
        require 'security_fragments/deleteIPRange_execution.php';

            echo !$result? '0' : $result;
        break;

    case 'deleteExpired':
        if(!validateThenRefreshCSRFToken($SessionManager))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["range"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'security_fragments/deleteExpired_auth.php';
        require 'security_fragments/deleteExpired_execution.php';

            echo !$result? '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}
