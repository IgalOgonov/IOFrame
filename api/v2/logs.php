<?php
/* TODO Add throttling setting to Groups via meta
 * Handles logging fetching, as well as rules/groups, in IOFrame.
 * */

require __DIR__.'/../../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if(!defined('IOFrameMainCoreInit'))
    require __DIR__ . '/../../main/core_init.php';
require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputResults.php';

$request = Request::createFromGlobals();
$response = new Response();

$openAPIJSON = \IOFrame\Util\FileSystemFunctions::readFile($settings->getSetting('absPathToRoot').'api/v2/logs.json');

$openAPIJSON = str_replace(
    ['%%ROOT_URL%%'],
    [$settings->getSetting('pathToRoot')],
    $openAPIJSON
);

$v2APIManager = new \IOFrame\Managers\v2APIManager($settings,json_decode($openAPIJSON,true),$defaultSettingsParams);
$v1APIManager = new \IOFrame\Managers\v1APIManager($settings,$apiSettings,$defaultSettingsParams);

require_once __DIR__.'/../defaultTestChecks.php';
require __DIR__ . '/../CSRF.php';

$match = $v2APIManager->validateAction();
if(gettype($match) === 'array' && isset($match['error']))
    die(json_encode($match));
$v2APIManager->initParams(['test'=>$test]);

//On error, return the error
if(gettype($v2APIManager->match) === 'array' && isset($v2APIManager->match['error']))
    die(json_encode($v2APIManager->match));
elseif(get_class($match)!=='League\OpenAPIValidation\PSR7\OperationAddress')
    die(json_encode(['error'=>'Unknown error']));

$logSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/logSettings/',$defaultSettingsParams);
$LoggingHandler = new \IOFrame\Handlers\LoggingHandler($settings,array_merge($defaultSettingsParams,['logSettings'=>$logSettings]));
require 'logs_fragments/definitions.php';
$APIAction = strtolower($v2APIManager->match->method()).$v2APIManager->match->path();

if(!checkApiEnabled('logs',$apiSettings,$SecurityHandler,$APIAction))
    \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>API_DISABLED],!$test);

switch ($APIAction){

    case 'get/logs/default-channels':

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['LOGS_VIEW','REPORTING_VIEW_RULES','REPORTING_VIEW_RULE_GROUPS']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $result = [
            'channels'=>explode(',',$logSettings->getSetting('defaultChannels'))
        ];

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);

        break;

    case 'get/logs':

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['LOGS_VIEW']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = $v2APIManager->parameters['query'];

        if($inputs['onlyStatistics']??false)
            $inputs['limit'] = 0;

        if($inputs['getCreationStatistics']??false)
            $inputs['replaceTypeArray'] = [
                'extraToGet'=>[
                    'Created'=>[
                        'key'=>'intervals',
                        'type'=>'count_interval',
                        'intervals'=>$inputs['statisticsInterval']
                    ]
                ]
            ];

        $result = [
            'logs'=>$LoggingHandler->getLogs(
                [],
                array_merge( $inputs, ['test'=>$test], $v1APIManager::generateDisableExtraToGet($inputs['disableExtraToGet']??null))
            )
        ];
        if(isset($result['logs']['@'])){
            $result['meta'] = $result['logs']['@'];
            unset($result['logs']['@']);
        }
        foreach ($result['logs'] as $i => $item){
            $result['logs'][$i] = $v1APIManager::baseItemParser($item,$logsResultsColumnMap);
        }

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);

        break;

    case 'get/logs/reporting-groups':

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_VIEW_GROUPS']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = $v2APIManager->parameters['query'];

        if($inputs['onlyStatistics']??false)
            $inputs['limit'] = 0;

        $result = [
            'groups'=>$LoggingHandler->getItems(
                [],
                'reportingGroups',
                array_merge( $inputs, ['test'=>$test], $v1APIManager::generateDisableExtraToGet($inputs['disableExtraToGet']??null))
            )
        ];
        if(isset($result['groups']['@'])){
            $result['meta'] = $result['groups']['@'];
            unset($result['groups']['@']);
        }
        foreach ($result['groups'] as $i => $item){
            $result['groups'][$i] = $v1APIManager::baseItemParser($item,$groupsResultsColumnMap);
        }

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);

        break;

    case 'post/logs/reporting-groups/{type}/{id}':
    case 'put/logs/reporting-groups/{type}/{id}':

        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_SET_GROUPS']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['body'],$v2APIManager->parameters['uri']);
        $inputs['create'] = str_starts_with($APIAction,'post');

        //REMINDER - patternProperties might not be supported, but see definitions -> $_logsTitlePurificationFunction
        $errors = [];
        $toSet = $v1APIManager::baseItemParser($inputs,$groupsSetColumnMap,$errors);
        if(empty($toSet['Meta']))
            $errors['_titles'] = 'no-valid-title';
        if(!empty($errors) )
                \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>INPUT_VALIDATION_FAILURE,'params'=>json_encode($errors)],!$test);
        $result = [
            'response'=>$LoggingHandler->setItems(
                [$toSet],
                'reportingGroups',
                ['test'=>$test,'update'=>!$inputs['create'],'overwrite'=>!$inputs['create']]
            )[$inputs['type'].'/'.$inputs['id']]
        ];

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'delete/logs/reporting-groups/{type}/{id}':

        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_REMOVE_GROUPS']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);

        $result = [
            'response'=>$LoggingHandler->deleteItems(
                [$v1APIManager::baseItemParser($inputs,$groupsSetColumnMap)],
                'reportingGroups',
                ['test'=>$test]
            )
        ];

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'get/logs/reporting-groups/{type}/{id}/users':

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_VIEW_GROUP_USERS']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);

        $result = [
            'users'=>$LoggingHandler->getItems(
                [ [ $inputs['type'], $inputs['id'] ] ],
                'reportingGroupUsers',
                array_merge( $inputs, ['test'=>$test], $v1APIManager::generateDisableExtraToGet($inputs['disableExtraToGet']??null))
            )[$inputs['type'].'/'.$inputs['id']]
        ];
        foreach ($result['users'] as $id => $user){
            $result['users'][$id] = $v1APIManager::baseItemParser($user,$groupUsersResultsColumnMap);
        }

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'post/logs/reporting-groups/{type}/{id}/users':

        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_MODIFY_GROUP_USERS']]) ||
            !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['GET_USERS_AUTH']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);
        $newUsers = [];
        foreach ($inputs['users'] as $id){
            $newUsers[] = $v1APIManager::baseItemParser(array_merge($inputs, ['user' => $id]), $groupUsersSetColumnMap);
        }

        $result = [
            'response'=>$LoggingHandler->setItems(
                $newUsers,
                'reportingGroupUsers',
                ['test'=>$test]
            )
        ];

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'delete/logs/reporting-groups/{type}/{id}/users':

        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_MODIFY_GROUP_USERS']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);
        $oldUsers = [];
        foreach ($inputs['users'] as $id){
            $oldUsers[] = $v1APIManager::baseItemParser(array_merge($inputs, ['user' => $id]), $groupUsersSetColumnMap);
        }

        $result = [
            'response'=>$LoggingHandler->deleteItems(
                $oldUsers,
                'reportingGroupUsers',
                ['test'=>$test]
            )
        ];

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'get/logs/reporting-groups/{type}/{id}/rules':

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_VIEW_RULES']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);

        if($inputs['onlyStatistics']??false)
            $inputs['limit'] = 0;

        $result = [
            'rules'=>$LoggingHandler->getItems(
                [],
                'reportingRuleGroups',
                array_merge( $inputs, [ 'test'=>$test, 'fullGroupIn'=>[ [$inputs['type'], $inputs['id'] ] ] ] )
            )
        ];
        if(isset($result['rules']['@'])){
            $result['meta'] = $result['rules']['@'];
            unset($result['rules']['@']);
        }
        foreach ($result['rules'] as $i => $item){
            $result['rules'][$i] = $v1APIManager::baseItemParser($item[$inputs['type'].'/'.$inputs['id']],$ruleGroupsResultsColumnMapRuleMeta);
        }

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'get/logs/reporting-rules':

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_VIEW_RULES']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['query']);

        if($inputs['onlyStatistics']??false)
            $inputs['limit'] = 0;
        $result = [
            'rules'=>$LoggingHandler->getItems(
                [],
                'reportingRules',
                array_merge( $inputs, [ 'test'=>$test ] )
            )
        ];
        if(isset($result['rules']['@'])){
            $result['meta'] = $result['rules']['@'];
            unset($result['rules']['@']);
        }
        foreach ($result['rules'] as $i => $item){
            $result['rules'][$i] = $v1APIManager::baseItemParser($item,$rulesResultsColumnMap);
        }

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'post/logs/reporting-rules/{channel}/{level}/{reportType}':
    case 'put/logs/reporting-rules/{channel}/{level}/{reportType}':

        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_SET_RULES']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['body'],$v2APIManager->parameters['uri']);
        $inputs['create'] = str_starts_with($APIAction,'post');

        //REMINDER - patternProperties might not be supported, but see definitions -> $_logsTitlePurificationFunction
        $errors = [];
        $toSet = $v1APIManager::baseItemParser($inputs,$rulesSetColumnMap,$errors);
        if(empty($toSet['Meta']))
            $errors['_titles'] = 'no-valid-title';
        if(!empty($errors))
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>INPUT_VALIDATION_FAILURE,'params'=>json_encode($errors)],!$test);

        $result = [
            'response'=>$LoggingHandler->setItems(
                [$toSet],
                'reportingRules',
                ['test'=>$test,'update'=>!$inputs['create'],'overwrite'=>!$inputs['create']]
            )[$inputs['channel'].'/'.$inputs['level'].'/'.$inputs['reportType']]
        ];

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'delete/logs/reporting-rules/{channel}/{level}/{reportType}':

        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_REMOVE_RULES']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);

        $result = [
            'response'=>$LoggingHandler->deleteItems(
                [$v1APIManager::baseItemParser($inputs,$rulesSetColumnMap)],
                'reportingRules',
                ['test'=>$test]
            )
        ];

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'get/logs/reporting-rules/{channel}/{level}/{reportType}/groups':

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_VIEW_RULE_GROUPS']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);

        $result = [
            'groups'=>$LoggingHandler->getItems(
                [ [ $inputs['channel'], $inputs['level'], $inputs['reportType'] ] ],
                'reportingRuleGroups',
                array_merge( $inputs, ['test'=>$test], $v1APIManager::generateDisableExtraToGet($inputs['disableExtraToGet']??null))
            )[$inputs['channel'].'/'.$inputs['level'].'/'.$inputs['reportType']]
        ];

        foreach ($result['groups'] as $id => $ruleGroup){
            $result['groups'][$id] = $v1APIManager::baseItemParser($ruleGroup,$ruleGroupsResultsColumnMapGroupMeta);
        }

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'post/logs/reporting-rules/{channel}/{level}/{reportType}/groups':

        /*if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));*/

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_MODIFY_RULE_GROUPS']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri'],$v2APIManager->parameters['body']);
        $errors = [];
        $inputs['groups'] = $v1APIManager::baseItemParser($inputs,$ruleGroupsSetDeepValidationMap,$errors);
        if(!empty($errors['groups']))
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>INPUT_VALIDATION_FAILURE,'param'=>json_encode($errors['groups'])],!$test);

        $newGroups = [];
        foreach ($inputs['groups'] as $obj){
            $newGroups[] = $v1APIManager::baseItemParser(array_merge($inputs, ['type' => $obj['type'], 'id' => $obj['id']]), $ruleGroupsSetColumnMap);
        }
        $result = [
            'response'=>$LoggingHandler->setItems(
                $newGroups,
                'reportingRuleGroups',
                ['test'=>$test]
            )
        ];

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'delete/logs/reporting-rules/{channel}/{level}/{reportType}/groups':

        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));

        if( !$v1APIManager->checkAuth(['test'=>$test,'actionAuth'=>['REPORTING_MODIFY_RULE_GROUPS']]) )
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>AUTHENTICATION_FAILURE],!$test);

        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);
        $errors = [];
        $inputs['groups'] = $v1APIManager::baseItemParser($inputs,$ruleGroupsSetDeepValidationMap,$errors);
        if(!empty($errors['groups']))
            \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>INPUT_VALIDATION_FAILURE,'param'=>json_encode($errors['groups'])],!$test);

        $newGroups = [];
        foreach ($inputs['groups'] as $obj){
            $newGroups[] = $v1APIManager::baseItemParser(array_merge($inputs, ['type' => $obj['type'], 'id' => $obj['id']]), $ruleGroupsSetColumnMap);
        }
        $result = [
            'response'=>$LoggingHandler->deleteItems(
                $newGroups,
                'reportingRuleGroups',
                ['test'=>$test]
            )
        ];

        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    default:
        die(json_encode(['error'=>'Action not handled']));
}