<?php
/* Handles logs in IOFrame.
 *
 *
 * */

require_once __DIR__ . '/../../main/core_init.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputResults.php';

$request = Request::createFromGlobals();
$response = new Response();

$openAPIJSON = \IOFrame\Util\FileSystemFunctions::readFile($settings->getSetting('absPathToRoot').'api/v2/articles.json');

$tagSettings = new \IOFrame\Handlers\SettingsHandler(
    $settings->getSetting('absPathToRoot').'localFiles/tagSettings/',
    array_merge($defaultSettingsParams,['base64Storage'=>true])
);
$currentTags = $tagSettings->getSetting('availableTagTypes');
$currentTags = \IOFrame\Util\PureUtilFunctions::is_json($currentTags)? json_decode($currentTags,true) : [];
$tagsArr = [];
foreach ($currentTags as $tagType=>$arr)
    $tagsArr[] = '"'.$tagType.'"';

$openAPIJSON = str_replace(
    ['%%ROOT_URL%%', '"%%ARTICLE_VALID_TAGS%%"'],
    [$settings->getSetting('pathToRoot'), empty($tagsArr)?'':implode(',',$tagsArr)],
    $openAPIJSON
);

$v2APIManager = new \IOFrame\Managers\v2APIManager($settings,json_decode($openAPIJSON,true),array_merge($defaultSettingsParams,[
    'request'=> null,
    'formats'=> [
        [
            'name'=>'article-address',
            'type'=>'string',
            'callback'=>function($value):bool {
                return preg_match('/^([a-z0-9]{1,24})(\-[a-z0-9]{1,24})*$/',$value) && !preg_match('/^\d+$/',$value) ;
            }
        ]
    ]
]));

require_once __DIR__.'/../defaultTestChecks.php';
require __DIR__ . '/../CSRF.php';

//Custom formats
$formats = [
    [
        'name'=>'article-address',
        'type'=>'string',
        'callback'=>function($value):bool {
            return preg_match('/^([a-z0-9]{1,24})(\-[a-z0-9]{1,24})*$/',$value) && !preg_match('/^\d+$/',$value) ;
        }
    ]
];
foreach($formats as $format){
    \League\OpenAPIValidation\Schema\TypeFormats\FormatsContainer::registerFormat($format['type'],$format['name'],$format['callback']);
}

$match = $v2APIManager->validateAction();
if(gettype($match) === 'array' && isset($match['error']))
    die(json_encode($match));
$v2APIManager->initParams(['test'=>$test]);

//On error, return the error
if(gettype($v2APIManager->match) === 'array' && isset($v2APIManager->match['error']))
    die(json_encode($v2APIManager->match));
elseif(get_class($match)!=='League\OpenAPIValidation\PSR7\OperationAddress')
    die(json_encode(['error'=>'Unknown error']));

$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);
require 'articles_fragments/definitions.php';
$APIAction = strtolower($v2APIManager->match->method()).$v2APIManager->match->path();

if(!checkApiEnabled('articles',$apiSettings,$SecurityHandler,$APIAction))
    \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON(['error'=>API_DISABLED],!$test);

switch ($APIAction){

    case 'get/articles':
        $inputs = $v2APIManager->parameters['query'];
        require 'articles_fragments/getArticles_checks.php';
        require 'articles_fragments/getArticles_auth.php';
        require 'articles_fragments/getArticles_execution.php';
        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'get/articles/{id}':
    case 'get/articles/{address}':
        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);
        require 'articles_fragments/getArticle_checks.php';
        require 'articles_fragments/getArticle_auth.php';
        require 'articles_fragments/getArticle_execution.php';
        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'delete/articles/{id}':
    case 'delete/articles':
        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);
        require 'articles_fragments/deleteArticles_checks.php';
        require 'articles_fragments/deleteArticles_auth.php';
        require 'articles_fragments/deleteArticles_execution.php';
        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'put/articles/{id}':
    case 'post/articles':
        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($v2APIManager->parameters['body'],$v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);
        $inputs['create'] = $APIAction === 'post/articles';
        require 'articles_fragments/setArticle_checks.php';
        require 'articles_fragments/setArticle_auth.php';
        require 'articles_fragments/setArticle_execution.php';
        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'post/articles/{id}/tags/{type}':
        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);
        require 'articles_fragments/addArticleTags_checks.php';
        require 'articles_fragments/addArticleTags_auth.php';
        require 'articles_fragments/addArticleTags_execution.php';
        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'delete/articles/{id}/tags/{type}':
        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);
        require 'articles_fragments/removeArticleTags_checks.php';
        require 'articles_fragments/removeArticleTags_auth.php';
        require 'articles_fragments/removeArticleTags_execution.php';
        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'post/articles/{id}/block/markdown':
    case 'post/articles/{id}/block/image':
    case 'post/articles/{id}/block/cover':
    case 'post/articles/{id}/block/gallery':
    case 'post/articles/{id}/block/video':
    case 'post/articles/{id}/block/youtube':
    case 'post/articles/{id}/block/article':
    case 'put/articles/{id}/block/{blockId}/markdown':
    case 'put/articles/{id}/block/{blockId}/image':
    case 'put/articles/{id}/block/{blockId}/cover':
    case 'put/articles/{id}/block/{blockId}/gallery':
    case 'put/articles/{id}/block/{blockId}/video':
    case 'put/articles/{id}/block/{blockId}/youtube':
    case 'put/articles/{id}/block/{blockId}/article':
        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($v2APIManager->parameters['body'],$v2APIManager->parameters['uri']);
        $inputs['create'] = str_starts_with($APIAction, 'post');
        require 'articles_fragments/setArticleBlock_checks.php';
        require 'articles_fragments/setArticleBlock_auth.php';
        require 'articles_fragments/setArticleBlock_execution.php';
        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'delete/articles/{id}/blocks':
        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($v2APIManager->parameters['query'],$v2APIManager->parameters['uri']);
        require 'articles_fragments/deleteArticleBlocks_checks.php';
        require 'articles_fragments/deleteArticleBlocks_auth.php';
        require 'articles_fragments/deleteArticleBlocks_execution.php';
        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'delete/articles/{id}/blocks/clean':
        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($v2APIManager->parameters['body'],$v2APIManager->parameters['uri']);
        require 'articles_fragments/cleanArticleBlocks_checks.php';
        require 'articles_fragments/cleanArticleBlocks_auth.php';
        require 'articles_fragments/cleanArticleBlocks_execution.php';
        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    case 'put/articles/{id}/blocks/{from}/{to}':
        if(!validateThenRefreshCSRFToken($SessionManager))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($v2APIManager->parameters['body'],$v2APIManager->parameters['uri']);
        require 'articles_fragments/moveBlockInArticle_checks.php';
        require 'articles_fragments/moveBlockInArticle_auth.php';
        require 'articles_fragments/moveBlockInArticle_execution.php';
        \IOFrame\Managers\v1APIManager::exitWithResponseAsJSON($result,!$test);
        break;

    default:
        die(json_encode(['error'=>'Action not handled']));
}