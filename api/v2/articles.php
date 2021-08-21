<?php
/* Handles articles in IOFrame.
 *
 *
 * */

require __DIR__.'/../../vendor/autoload.php';

use League\OpenAPIValidation\PSR7\Exception\NoContentType;
use League\OpenAPIValidation\PSR7\Exception\NoOperation;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\Exception\NoResponseCode;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidBody;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidCookies;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidHeaders;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidPath;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidQueryArgs;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidSecurity;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\Schema\Exception\FormatMismatch;
use League\OpenAPIValidation\Schema\Exception\KeywordMismatch;
use League\OpenAPIValidation\Schema\Exception\TypeMismatch;
use League\OpenAPIValidation\PSR7\SpecFinder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use IOFrame\Handlers\FileHandler;
use IOFrame\Util\APIManager;

require_once __DIR__.'/../../main/coreInit.php';
require __DIR__ . '/../apiSettingsChecks.php';

$request = Request::createFromGlobals();
$response = new Response();
$FileHandler = new FileHandler();
$openAPIjson = $FileHandler->readFile($settings->getSetting('absPathToRoot').'api/v2/openapi.json');
$APIManager = new APIManager($settings,json_decode($openAPIjson,true),array_merge($defaultSettingsParams,[
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

$match = $APIManager->validateAction();
if(gettype($match) === 'array' && isset($match['error']))
    die(json_encode($match));
$APIManager->initParams(['test'=>$test]);

//On error, return the error
if(gettype($APIManager->match) === 'array' && isset($APIManager->match['error']))
    die(json_encode($APIManager->match));
elseif(get_class($match)!=='League\OpenAPIValidation\PSR7\OperationAddress')
    die(json_encode(['error'=>'Unknown error']));

$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);
require 'articles_fragments/definitions.php';
$APIAction = strtolower($APIManager->match->method()).$APIManager->match->path();

switch ($APIAction){

    case 'get/articles':
        $inputs = $APIManager->parameters['query'];
        require 'articles_fragments/getArticles_checks.php';
        require 'articles_fragments/getArticles_auth.php';
        require 'articles_fragments/getArticles_execution.php';

        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        break;

    case 'get/articles/{id}':
    case 'get/articles/{address}':
        $inputs = array_merge($APIManager->parameters['query'],$APIManager->parameters['uri']);
        require 'articles_fragments/getArticle_checks.php';
        require 'articles_fragments/getArticle_auth.php';
        require 'articles_fragments/getArticle_execution.php';
        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        break;

    case 'delete/articles/{id}':
    case 'delete/articles':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($APIManager->parameters['query'],$APIManager->parameters['uri']);
        require 'articles_fragments/deleteArticles_checks.php';
        require 'articles_fragments/deleteArticles_auth.php';
        require 'articles_fragments/deleteArticles_execution.php';
        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        break;

    case 'put/articles/{id}':
    case 'post/articles':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($APIManager->parameters['body'],$APIManager->parameters['uri']);
        $inputs['create'] = $APIAction === 'post/articles';
        require 'articles_fragments/setArticle_checks.php';
        require 'articles_fragments/setArticle_auth.php';
        require 'articles_fragments/setArticle_execution.php';
        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
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
        if(!validateThenRefreshCSRFToken($SessionHandler))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($APIManager->parameters['body'],$APIManager->parameters['uri']);
        $inputs['create'] = substr($APIAction,0,4) === 'post';
        require 'articles_fragments/setArticleBlock_checks.php';
        require 'articles_fragments/setArticleBlock_auth.php';
        require 'articles_fragments/setArticleBlock_execution.php';
        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        break;

    case 'delete/articles/{id}/blocks':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($APIManager->parameters['query'],$APIManager->parameters['uri']);
        require 'articles_fragments/deleteArticleBlocks_checks.php';
        require 'articles_fragments/deleteArticleBlocks_auth.php';
        require 'articles_fragments/deleteArticleBlocks_execution.php';
        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        break;

    case 'post/articles/{id}/blocks/clean':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($APIManager->parameters['body'],$APIManager->parameters['uri']);
        require 'articles_fragments/cleanArticleBlocks_checks.php';
        require 'articles_fragments/cleanArticleBlocks_auth.php';
        require 'articles_fragments/cleanArticleBlocks_execution.php';
        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        break;

    case 'put/articles/{id}/blocks/{from}/{to}':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            die(json_encode(['error'=>'CSRF']));
        $inputs = array_merge($APIManager->parameters['body'],$APIManager->parameters['uri']);
        require 'articles_fragments/moveBlockInArticle_checks.php';
        require 'articles_fragments/moveBlockInArticle_auth.php';
        require 'articles_fragments/moveBlockInArticle_execution.php';
        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        break;

    default:
        die(json_encode(['error'=>'Action not handled']));
}