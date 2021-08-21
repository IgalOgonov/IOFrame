<?php
/*Maps*/
$articleSetColumnMap = [
    'id'=>'Article_ID',
    'creatorId'=>'Creator_ID',
    'title'=>'Article_Title',
    'address'=>'Article_Address',
    'language'=>'Article_Language',
    'auth'=>'Article_View_Auth',
    'articleTextContent'=>'Article_Text_Content',
    'thumbnailAddress'=>'Thumbnail_Address',
    'thumbnailLocal'=>'Thumbnail_Local',
    'thumbnailDataType'=>'Data_Type',
    'thumbnailMeta'=>'Thumbnail_Meta',
    'thumbnailUpdated' => 'Thumbnail_Last_Updated',
    'weight'=>'Article_Weight',
    'blockOrder'=>'Block_Order',
    'firstName'=>'Creator_First_Name',
    'lastName'=>'Creator_Last_Name',
    'created'=>'Created',
    'updated'=>'Last_Updated'
];

$articleResultsColumnMap = [
    'Article_ID'=>[
        'resultName'=>'articleId',
        'type'=>'int'
    ],
    'Creator_ID'=>[
        'resultName'=>'creatorId',
        'type'=>'int'
    ],
    'Article_Title'=>'title',
    'Article_Address'=>'articleAddress',
    'Article_Language'=>'language',
    'Article_View_Auth'=>[
        'resultName'=>'articleAuth',
        'type'=>'int'
    ],
    'Article_Text_Content'=>[
        'resultName'=>'meta',
        'type'=>'json',
        'validChildren'=>['caption','subtitle','alt','name'],
    ],
    'Thumbnail_Address'=>[
        'resultName'=>'address',
        'groupBy'=>'thumbnail'
    ],
    'Thumbnail_Local'=>[
        'resultName'=>'local',
        'type'=>'bool',
        'groupBy'=>'thumbnail'
    ],
    'Data_Type'=>[
        'resultName'=>'dataType',
        'groupBy'=>'thumbnail'
    ],
    'Thumbnail_Meta'=>[
        'resultName'=>'meta',
        'type'=>'json',
        'validChildren'=>['caption','alt','name'],
        'groupBy'=>'thumbnail',
    ],
    'Thumbnail_Last_Updated' => [
        'resultName'=>'updated',
        'groupBy'=>'thumbnail'
    ],
    'Article_Weight'=>[
        'resultName'=>'weight',
        'type'=>'int'
    ],
    'Block_Order'=>'blockOrder',
    'Creator_First_Name'=>'firstName',
    'Creator_Last_Name'=>'lastName',
    'Created'=>[
        'resultName'=>'created',
        'type'=>'int'
    ],
    'Last_Updated'=>[
        'resultName'=>'updated',
        'type'=>'int'
    ]
];

$blocksSetColumnMap = [
    'id'=>'Article_ID',
    'type'=>'Block_Type',
    'blockId'=>'Block_ID',
    'text'=>'Text_Content',
    'blockResourceAddress'=>'Resource_Address',
    'blockCollectionName'=>'Collection_Name',
    'blockCollectionMeta'=>'Resource_Collection_Meta',
    'blockMeta'=>'Meta',
    'otherArticleId'=>'Other_Article_ID',
    'otherArticleCreatorId'=>'Other_Article_Creator',
    'otherArticleTitle'=>'Other_Article_Title',
    'otherArticleAddress'=>'Other_Article_Address',
    'otherArticleThumbnailAddress'=>'Thumbnail_Address',
    'otherArticleThumbnailLocal'=>'Thumbnail_Local',
    'otherArticleThumbnailDataType'=>'Data_Type',
    'otherArticleThumbnailMeta'=>'Thumbnail_Meta',
    'otherArticleThumbnailUpdated'=>'Thumbnail_Last_Updated',
    'otherArticleCreatorFirstName'=>'Other_Article_Creator_First_Name',
    'otherArticleCreatorLastName'=>'Other_Article_Creator_Last_Name',
    'created'=>'Created',
    'updated'=>'Last_Updated'
];

$blocksResultsColumnMap = [
    'Article_ID'=>[
        'resultName'=>'articleId',
        'type'=>'int'
    ],
    'Block_Type'=>'type',
    'Block_ID'=>[
        'resultName'=>'blockId',
        'type'=>'int'
    ],
    'Text_Content'=>'text',
    'Resource_Address'=>[
        'resultName'=>'address',
        'groupBy'=>'resource'
    ],
    'Resource_Local'=>[
        'resultName'=>'local',
        'type'=>'bool',
        'groupBy'=>'resource'
    ],
    'Resource_Data_Type'=>[
        'resultName'=>'dataType',
        'groupBy'=>'resource'
    ],
    'Resource_Text_Content'=>[
        'resultName'=>'meta',
        'groupBy'=>'resource',
        'type'=>'json',
        'validChildren'=>['caption','alt','name','height','width','autoplay','controls','loop','mute'],
    ],
    'Resource_Last_Updated' => [
        'resultName'=>'updated',
        'type'=>'int',
        'groupBy'=>'resource'
    ],
    'Collection_Name'=>[
        'resultName'=>'name',
        'groupBy'=>'collection',
    ],
    'Resource_Collection_Meta'=>[
        'resultName'=>'meta',
        'type'=>'json',
        'validChildren'=>['caption','name','autoplay','controls','loop'],
        'groupBy'=>'collection',
    ],
    'Meta'=>[
        'resultName'=>'meta',
        'type'=>'json',
        'validChildren'=>['caption','alt','name','height','width','autoplay','controls','loop','mute'],
    ],
    'Other_Article_ID'=>[
        'resultName'=>'id',
        'type'=>'int',
        'groupBy'=>'otherArticle'
    ],
    'Other_Article_Title'=>[
        'resultName'=>'title',
        'groupBy'=>'otherArticle'
    ],
    'Other_Article_Address'=>[
        'resultName'=>'address',
        'groupBy'=>'otherArticle'
    ],
    'Other_Article_Creator'=>[
        'resultName'=>'id',
        'type'=>'int',
        'groupBy'=>['otherArticle','creator']
    ],
    'Other_Article_Creator_First_Name'=>[
        'resultName'=>'firstName',
        'groupBy'=>['otherArticle','creator']
    ],
    'Other_Article_Creator_Last_Name'=>[
        'resultName'=>'lastName',
        'groupBy'=>['otherArticle','creator']
    ],
    'Thumbnail_Address'=>[
        'resultName'=>'address',
        'groupBy'=>['otherArticle','thumbnail']
    ],
    'Thumbnail_Local'=>[
        'resultName'=>'local',
        'type'=>'bool',
        'groupBy'=>['otherArticle','thumbnail']
    ],
    'Thumbnail_Data_Type'=>[
        'resultName'=>'dataType',
        'groupBy'=>['otherArticle','thumbnail']
    ],
    'Thumbnail_Text_Content'=>[
        'resultName'=>'meta',
        'type'=>'json',
        'validChildren'=>['caption','alt','name'],
        'groupBy'=>['otherArticle','thumbnail']
    ],
    'Thumbnail_Last_Updated'=>[
        'resultName'=>'updated',
        'type'=>'int',
        'groupBy'=>['otherArticle','thumbnail']
    ],
    'Created'=>[
        'resultName'=>'created',
        'type'=>'int'
    ],
    'Last_Updated'=>[
        'resultName'=>'updated',
        'type'=>'int'
    ]
];

$metaMap = [
    'articleTextContent' => ['subtitle','alt','caption','name'],
    'thumbnailMeta' => ['alt','caption','name'],
    'blockMeta' => ['alt','caption','name','height','width','mute','loop','autoplay','controls','embed','center','preview','fullScreenOnClick','slider'],
    'blockCollectionMeta' => ['caption'],
    'otherArticleThumbnailMeta' => ['alt','caption','name'],
];

/* AUTH */
/* Types of auth required */
CONST REQUIRED_AUTH_NONE = 0;
CONST REQUIRED_AUTH_RESTRICTED = 1;
CONST REQUIRED_AUTH_OWNER = 2;
CONST REQUIRED_AUTH_ADMIN = 9999;

/*General action that allows modifying ALL articles - this includes creation, deletion, updating, and even viewing - but NOT creating */
CONST ARTICLES_MODIFY_AUTH = 'ARTICLES_MODIFY_AUTH';
/*General action that allows viewing ALL articles*/
CONST ARTICLES_VIEW_AUTH = 'ARTICLES_VIEW_AUTH';
/*General action that allows creating articles */
CONST ARTICLES_CREATE_AUTH = 'ARTICLES_CREATE_AUTH';
/*General action that allows creating articles */
CONST ARTICLES_UPDATE_AUTH = 'ARTICLES_UPDATE_AUTH';
/*General action that allows creating articles */
CONST ARTICLES_DELETE_AUTH = 'ARTICLES_DELETE_AUTH';
/*General action that allows creating articles */
CONST ARTICLES_BLOCKS_ASSUME_SAFE = 'ARTICLES_BLOCKS_ASSUME_SAFE';

/*Object Auth object type for articles*/
CONST OBJECT_AUTH_TYPE = 'articles';
/*Object Auth object view auth*/
CONST OBJECT_AUTH_VIEW_ACTION = ARTICLES_VIEW_AUTH;
/*Object Auth object modify auth*/
CONST OBJECT_AUTH_MODIFY_ACTION = ARTICLES_MODIFY_AUTH;

/*Article Address*/
CONST ADDRESS_MAX_LENGTH = 128;

/* Auth function */
/** Gets an array of keys (which could be empty), and the level of auth required, and returns the relevant user auth.
 * @param array $params of the form:
 *          int authRequired, defaults to REQUIRED_AUTH_ADMIN - auth required
 *          int[] keys, defaults to [] - article keys
 *          string[] objectAuth, defaults to [] - required object auth actions (if empty, cannot satisfy REQUIRED_AUTH_RESTRICTED)
 *          string[] actionAuth, defaults to [] - required action auth actions (if empty, cannot satisfy admin auth without levelAuth)
 *          int levelAuth, defaults to 0 - required level to be considered an admin for this operation - defaults to 0 (super admin)
 *          Array defaultSettingsParams - IOFrame default settings params, initiated at coreInit.php as $defaultSettingsParams
 *          SettingsHandler localSettings - IOFrame local settings params, initiated at coreInit.php as $settings
 *          IOFrame/Handlers/AuthHandler AuthHandler, defaults to null - standard IOFrame auth handler
 *          IOFrame/Handlers/ObjectAuthHandler ObjectAuthHandler, defaults to null - standard IOFrame object auth handler
 *          IOFrame/Handlers/ArticleHandler ArticleHandler, defaults to null - standard IOFrame article handler
 * @returns array|bool -
 *          IF keys were passed - array of the form:
 *          [
 *              <int, key name> => <int, key index in keys array>
 *          ]
 *          where each key specified is a key that didn't pass the auth test, and index is self explanatory.
 *
 *          IF keys were NOT passed OR not logged in OR DB connection error- true or false, whether the user has auth or not.
 */
function checkAuth($params){
    $test = isset($params['test'])? $params['test'] : false;
    $authRequired = isset($params['authRequired'])? $params['authRequired'] : REQUIRED_AUTH_ADMIN;
    $keys = isset($params['keys'])? $params['keys'] : [];
    $objectAuth = isset($params['objectAuth'])? $params['objectAuth'] : [];
    $actionAuth = isset($params['actionAuth'])? $params['actionAuth'] : [];
    $levelAuth = isset($params['levelAuth'])? $params['levelAuth'] : false;
    $defaultSettingsParams = isset($params['defaultSettingsParams'])? $params['defaultSettingsParams'] : [];
    $localSettings = isset($params['localSettings'])? $params['localSettings'] : null;
    $AuthHandler = isset($params['AuthHandler'])? $params['AuthHandler'] : null;
    $ObjectAuthHandler = isset($params['ObjectAuthHandler'])? $params['ObjectAuthHandler'] : null;
    $ArticleHandler = isset($params['ArticleHandler'])? $params['ArticleHandler'] : null;

    $AuthHandlerPassed = false;

    if(!isset($AuthHandler))
        $AuthHandler = new \IOFrame\Handlers\AuthHandler($localSettings,$defaultSettingsParams);

    //If we are not logged in, nothing left to do
    if(!$AuthHandler->isLoggedIn()){
        if($test)
            echo 'Must be logged in to have any authentication!'.EOL;
        return $AuthHandlerPassed;
    }
    $userId = $AuthHandler->getDetail('ID');

    //All required keys of the objects to check
    $requiredKeys = [];
    //A map of input indexes
    $requiredKeyMap = [];

    //Whether the keys are addresses or IDs
    $areIds = true;
    //Check if any of the keys are actually addresses - in which case, this function wont work for them, and we need to get them from the DB
    foreach($keys as $index => $keyArr){
        if(!isset($keyArr['Article_ID'])){
            $areIds = false;
            break;
        }
    }
    if(!$areIds){
        if(!defined('SQLHandler'))
            require __DIR__ . '/../../IOFrame/Handlers/SQLHandler.php';
        if(!isset($SQLHandler))
            $SQLHandler = new \IOFrame\Handlers\SQLHandler($localSettings,$defaultSettingsParams);
        $addresses = [];
        foreach($keys as $index => $keyArr){
            if(isset($keyArr['Article_Address']))
                array_push($addresses,[$keyArr['Article_Address'],'STRING']);
        }
        if(count($addresses) > 0){
            array_push($addresses,'CSV');
            $requiredIds = $SQLHandler->selectFromTable(
                $SQLHandler->getSQLPrefix().'ARTICLES',
                [
                    'Article_Address',
                    $addresses,
                    'IN'
                ],
                ['Article_ID'],
                ['test'=>$test]
            );
            if(gettype($requiredIds) !== 'array')
                return false;
            else{
                $keys = [];
                foreach ($requiredIds as $dbArray)
                    array_push($keys,['Article_ID'=>$dbArray['Article_ID']]);
            }
        }
    }

    //The "keys" array could be empty - but then, this part just wont matter
    foreach($keys as $index => $keyArr){
        array_push($requiredKeys,(string)($keyArr['Article_ID']));
        $requiredKeyMap[(string)($keyArr['Article_ID'])] = $index;
    }

    //If we could do this with restricted auth, means there are specific keys
    if($authRequired <= REQUIRED_AUTH_RESTRICTED && !empty($requiredKeys)){
        if($test)
            echo 'Testing user auth against object auth of articles '.implode(',',$requiredKeys).EOL;

        if(!defined('ObjectAuthHandler'))
            require __DIR__ . '/../../IOFrame/Handlers/ObjectAuthHandler.php';
        if(!isset($ObjectAuthHandler))
            $ObjectAuthHandler = new \IOFrame\Handlers\ObjectAuthHandler($localSettings,$defaultSettingsParams);

        $userObjectAuth = $ObjectAuthHandler->userObjects(
            OBJECT_AUTH_TYPE,
            $userId,
            [
                'objects' => $requiredKeys,
                'requiredActions' => $objectAuth,
                'test'=>$test
            ]
        );
        //Each auth we found, signify the user does have the required auth
        foreach($userObjectAuth as $pair){
            if(isset($requiredKeyMap[$pair['Object_Auth_Object']])){
                if($test)
                    echo 'Must be logged in to have any authentication!';
            }
            $requiredKeyMap[$pair['Object_Auth_Object']] = -1;
        }

        //All required keys of the objects to check their owners
        $requiredKeys = [];
        //Unset all keys which were
        foreach($requiredKeyMap as $requiredKey => $requiredIndex){
            if($requiredIndex != -1)
                array_push($requiredKeys,(int)$requiredKey);
        }
    }

    //This case can also only happen with specific keys
    if($authRequired <= REQUIRED_AUTH_OWNER && !empty($requiredKeys)){

        if($test)
            echo 'Testing user ownership of articles '.implode(',',$requiredKeys).EOL;

        if(!defined('ArticleHandler'))
            require __DIR__ . '/../../IOFrame/Handlers/ArticleHandler.php';
        if(!isset($ArticleHandler))
            $ArticleHandler = new \IOFrame\Handlers\ArticleHandler($localSettings,$defaultSettingsParams);

        $userArticles = $ArticleHandler->getUserArticles(
            $userId,
            [
                'requiredArticles' => $requiredKeys,
                'test'=>$test
            ]
        );
        if(!is_array($userArticles))
            return $AuthHandlerPassed;
        //Each auth we found, signify the user does have the required auth
        foreach($userArticles as $articleId){
            $requiredKeyMap[$articleId] = -1;
        }

        $requiredKeys = [];
        //Unset all keys which were
        foreach($requiredKeyMap as $requiredKey => $requiredIndex){
            if($requiredIndex != -1)
                array_push($requiredKeys,(int)$requiredKey);
        }
    }

    //This check can happen either if there were no keys to begin with, or there are still keys left which the user has no auth to get.
    //Note that this doesn't check $authRequired, as any auth is smaller or equal to admin auth.
    if( !empty($requiredKeys) || empty($requiredKeyMap) ){
        if($test)
            echo 'Testing user auth level against '.$levelAuth.', then actions against '.implode(',',$actionAuth).EOL;
        $adminAuth = $AuthHandler->isAuthorized($levelAuth);
        foreach($actionAuth as $possibleAuth){
            if(!$adminAuth)
                $adminAuth = $AuthHandler->isAuthorized($possibleAuth);
            else
                break;
        }
        if($adminAuth){
            if(!empty($requiredKeyMap))
                foreach($requiredKeyMap as $key => $index){
                    $requiredKeyMap[$key] = -1;
                }
            else
                $AuthHandlerPassed = true;
        }
    }

    if(!empty($requiredKeyMap)){
        foreach($requiredKeyMap as $key => $index){
            if($index === -1)
                unset($requiredKeyMap[$key]);
        }
        $requiredKeyMap = array_splice($requiredKeyMap,0);
        return $requiredKeyMap;
    }
    else
        return $AuthHandlerPassed;

}