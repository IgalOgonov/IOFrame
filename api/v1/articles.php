<?php

/* Handles articles in IOFrame.
 *
 * Parameters:
 * "action"     - Requested action - described bellow
 *_________________________________________________
 * getArticles
 *      Gets all available articles, or specific articles. Will not get article blocks with this action.
 *      params:
 *      keys - json array of ints, can be empty to get all items. If not empty, each member is an article ID.
 *      -- The following are only relevant when not getting specific keys, EXCEPT authAtMost --
 *      limit - can be passed as explicit parameters when not getting specific items - those are SQL pagination parameters.
 *              Default 50.
 *      offset - related to limit
 *      orderBy - string[], default []. Possible columns to order by:
 *                      ['articleId','created','updated']. The value is 'weight' is automatically added first.
 *      orderType - int, default null, when not getting specific items - possible values 0 and 1 - 0 is 'ASC', 1 is 'DESC'
 *      filters - array of filters, an associative array, of the form:
 *                  [
 *                      'titleLike' => <string, limited regex to match title against>,
 *                      'languageIs' => <string, articles with specific language will be matched - DEFAULTS to '' (which is the default language)>,
 *                      'addressIn' => <string[], array of specific addresses the title must match>,
 *                      'addressLike' => <string, limited regex to match address against>,
 *                      'createdBefore' => <int, date before the items were created>,
 *                      'createdAfter' => <int, date after the items were created>,
 *                      'changedBefore' => <int, date before the items were changed>,
 *                      'changedAfter' => <int, date after the items were changed>,
 *                      'authAtMost' => <int, return items with view auth at most this - default 0,
 *                                       1/2 without specific keys and 2 with specific keys require ownership of the article,
 *                                       1 with specific keys requires ownership or relevant object auth for the articles.
 *                                       Auth 9999 is default for "hidden" items that only an admin can get, and requires auth level 0>,
 *                      -- The following values require admin permission --
 *                      'authIn' => <int[], auth is one of those values>,
 *                      'weightIn' => <int[], article weight is one of those,
 *                  ]
 *
 *      Returns json array of the form:
 *          [
 *              <identifier> =>
 *                  <DB array>,
 *                  OR
 *                  <code>
 *          ]
 *          A DB error when $items is [] will result in an empty array returned, not an error.
 *
 *          Possible codes are:
 *          1 if specific item that was requested is not found,
 *          -1 if there was a DB error
 *          "AUTHENTICATION_FAILURE" - when authentication getting this specific object failed.
 *
 *          The following DB arrays have the following structure:
 *          [
 *              'articleID' => <int>,
 *              'creatorID' => <int>,
 *              'firstName'=> <string, creator first name>,
 *              'lastName'=> <string, creator last name>,
 *              'title' => <string, article title>,
 *              'articleAddress' => <string, article address (here for seo purposes)>,
 *              'articleAuth' => <int, article auth (0 - public, 1 - restricted, 2 - private)>,
 *              'meta' => [
 *                  'subtitle' => <string, article subtitle>,
 *                  'alt' => <string, image alt - overrides thumbnail meta>,
 *                  'caption' => <string, image caption - overrides thumbnail meta>,
 *                  'name' => <string, image name - overrides thumbnail meta>,
 *              ],
 *              'thumbnail' => [
 *                  'address' => <string, resource address>,
 *                  'local' => <string, whether resource is local>,
 *                  'dataType' => <string, data type for db resources>,
 *                  'meta' => [
 *                      'alt' => <string, image alt>,
 *                      'caption' => <string, image caption>,
 *                      'name' => <string, image name>,
 *                  ],
 *                  'updated' => <int, thumbnail last update date - unix timestamp>,
 *              ],
 *              'blockOrder' => <string, irrelevant here, but the client may decide to use it somehow>
 *              'weight' => <int, article priority>,
 *              'created' => <int, article creation date - unix timestamp>,
 *              'updated' => <int, article last update date - unix timestamp>,
 *          ]
 *
 *      Examples:
 *          action=getArticles
 *          action=getArticles&limit=5&authAtMost=2
 *          action=getArticles&limit=5&titleLike=t&addressLike=t
 *          action=getArticles&keys=[1,2]&authAtMost=2
 *_________________________________________________
 * getArticle
 *      Gets a specific article and all its blocks
 *      params:
 *      id - int, article ID - Overrides articleAddress if set. Required if articleAddress is not set.
 *      articleAddress - string, valid article address. No effect if id is set. **SETS authAtMost to 0 if set**.
 *      authAtMost => <int, return items with view auth at most this - default 0,
 *                       2 requires ownership of the article, 1 requires ownership or relevant object auth for the article.>,
 *      ignoreOrphan - bool, default true - ignores orphan blocks (not in the article order).
 *                                          Can only be set to false if you are an admin, the article owner, or have the relevant auth.
 *      preloadGalleries - bool, default true - if set, will preload ALL gallery children for gallery blocks.
 *
 *      Returns int|string|json array of the form:
 *          Codes:
 *          -1 - Database error
 *           1 - Article not found
 *          "AUTHENTICATION_FAILURE" - when authentication getting this specific object failed.
 *
 *          Array
 *          [
 *              'articleID' => <int>,
 *              'creatorID' => <int>,
 *              'firstName'=> <string, creator first name>,
 *              'lastName'=> <string, creator last name>,
 *              'title' => <string, article title>,
 *              'articleAddress' => <string, article address (here for seo purposes)>,
 *              'articleAuth' => <int, article auth (0 - public, 1 - restricted, 2 - private)>,
 *              'meta' => [
 *                  'caption' => <string>,
 *                  'subtitle' => <string>,
 *                  'alt' => <string, image alt - overrides thumbnail meta>,
 *                  'name' => <string, image name - overrides thumbnail meta>,
 *              ],
 *              'thumbnail' => [
 *                  'address' => <string, resource address>,
 *                  'local' => <string, whether resource is local>,
 *                  'dataType' => <string, data type for db resources>,
 *                  'meta' => [
 *                      'alt' => <string, image alt>,
 *                      'caption' => <string, image caption>,
 *                      'name' => <string, image name>,
 *                  ]
 *                  'updated' => <int, article last update date - unix timestamp>,
 *              ],
 *              'weight' => <int, article priority>,
 *              'blockOrder' => <string, comma separated list of block IDs - blocks not here are considered orphan>
 *              'blocks' => <array of arrays, of the form:
 *                      [
 *                      'articleID' => <int, redundant>,
 *                      'type' => <string, block type (from the current supported blocks)>,
 *                      'blockId' => <int, block id>,
 *                      'text' => <string, block text content>,
 *                      'orphan' => <bool, whether this block is missing from article order>
 *                      'resource' => [
 *                          'address' => <string, resource address>,
 *                          'local' => <string, whether resource is local>,
 *                          'dataType' => <string, data type for db resources>,
 *                          'meta' => [
 *                              'alt' => <string, image alt, relevant to some blocks>,
 *                              'caption' => <string, image caption, relevant to some blocks>,
 *                              'name' => <string, image name>, relevant to some blocks,
 *                              'height' => <int, relevant to some blocks>,
 *                              'width' => <int, relevant to some blocks,
 *                              'autoplay' => <bool, relevant to some blocks>,
 *                              'loop' => <bool, relevant to some blocks>,
 *                              'mute' => <bool, relevant to some blocks>
 *                          ]
 *                          'updated' => <int, article last update date - unix timestamp>,
 *                      ],
 *                      'collection' => [
 *                          'name' => <string, collection name (relevant in gallery blocks)>,
 *                          'meta' => [
 *                              'caption' => <string, image caption, relevant to some blocks>,
 *                              'name' => <string, image name>, relevant to some blocks
 *                              'autoplay' => <bool, relevant to some blocks>,
 *                              'loop' => <bool, relevant to some blocks>
 *                          ],
 *                          'members' => ordered array of objects the same type 'resource' is.
 *                      ],
 *                      'meta' => [
 *                          'alt' => <string, image alt, relevant to some blocks>,
 *                          'caption' => <string, image caption, relevant to some blocks>,
 *                          'name' => <string, image name>, relevant to some blocks,
 *                          'height' => <int, relevant to some blocks>,
 *                          'width' => <int, relevant to some blocks,
 *                          'autoplay' => <bool, relevant to some blocks>,
 *                          'loop' => <bool, relevant to some blocks>,
 *                          'mute' => <bool, relevant to some blocks>
 *                      ],
 *                      'otherArticle' => [
 *                          'id' => <int, id of the article>,
 *                          'title' => <string, article title>,
 *                          'address' => <string, article address>,
 *                          'creator':[
 *                              'id' => <int, id of the creator>,
 *                              'firstName'=> <string, creator first name>,
 *                              'lastName'=> <string, creator last name>,
 *                          ],
 *                          'thumbnail':[
 *                              'address' => <string, thumbnail address>,
 *                              'local'=> <bool, whether resource is local>,
 *                              'dataType'=> <string, db resource data type>,
 *                              'meta' => [
 *                                  'alt' => <string, image alt>,
 *                                  'caption' => <string, image caption>,
 *                                  'name' => <string, image name>,
 *                              ]
 *                              'updated' => <int, article last update date - unix timestamp>,
 *                          ],
 *                      ]
 *                      'created' => <int, block creation date - unix timestamp>,
 *                      'updated' => <int, block last update date - unix timestamp>,
 *                  >
 *              ],
 *          ]
 *
 *      Examples:
 *          action=getArticle&id=1
 *          action=getArticle&id=1&authAtMost=2
 *          action=getArticle&id=1&ignoreOrphan=false
 *_________________________________________________
 * setArticle [CSRF protected]
 *      Create/Update Articles - requires relevant auth
 *
 *      params:
 *      create - bool, default false - if true, will only create new items. When false, will check specific article auth.
 *      articleId - string, relevant when not creating new articles - id of article to create
 *      title - string, article title
 *      articleAddress - string, default created by default from title, "address" of the article, mainly for seo purposes
 *      articleAuth - int, default 2, 0 means public, 1 means restricted, 2 means private
 *      subtitle -  string, default null, article subtitle
 *      caption -  string, default null, thumbnail caption
 *      alt -  string, default null, thumbnail alt
 *      name -  string, default null, thumbnail name
 *      thumbnailAddress - string, default null, thumbnail address
 *      blockOrder - string, default null, order of block IDs in the article
 *      weight - int, default 0, article weight. Used to promote specific articles
 *      language - string, language of the article
 *
 *
 *      Returns: Int|String|json, updating existing items, array of the form:
 *          <identifier> => <code>
 *          Where each identifier is the contact identifier, and possible codes are:
 *         -2 - failed to create items since one of the dependencies (thumbnail most likely) is missing
 *         -1 - failed to connect to db
 *          0 - success
 *          1 - item does not exist (and update is true)
 *          2 - item exists (and override is false)
 *          3 - trying to create a new item with missing inputs
 *          "AUTHENTICATION_FAILURE" - when authentication setting this specific object failed.
 *
 *          Otherwise, when creating, one of them codes:
 *         -3 - Missing inputs when creating one of the items
 *         -2 - One of the dependencies missing.
 *         -1 - unknown database error
 *         int, ID of the created item
 *
 * Examples:
 *      action=setArticle&create=true&title=Test Title
 *      action=setArticle&create=true&title=Test Title 2&thumbnailAddress=pluginImages/def_icon.png&articleAuth=1&subtitle=Test Subtitle&caption=Test Caption&alt=Test-Alt&name=Nice Name
 *      action=setArticle&articleId=1&title=Test Title
 *      action=setArticle&articleId=2&title=Test Title 2&thumbnailAddress=pluginImages/def_icon.png&articleAuth=1&subtitle=Test Subtitle&caption=Test Caption&alt=Test-Alt&name=Nice Name
 *_________________________________________________
 * deleteArticles [CSRF protected]
 *      Deletes articles. The default, however, is not to delete them but to "hide" them by setting their view auth to 9999.
 *      Hiding requires admin auth or ownership, but to actually delete an article, you must be an admin.
 *
 *      params:
 *      articles - int[], array of article IDs
 *      permanentDeletion - bool, default false - if true, really deletes articles and all their blocks. Requires admin auth.
 *
 *      Returns json array of the form:
 *          [
 *              <int, id> => <int|string, code>
 *          ]
 *          where the possible codes are:
 *          -1 server error (would be the same for all)
 *           0 success (does not check if items do not exist)
 *          "AUTHENTICATION_FAILURE" - when you aren't an admin and at least ONE of the articles isn't created
 *                                            by you (or you have permission to modify it).
 *
 * Examples:
 *      action=deleteArticles&articles=[1,2,3]
 *      action=deleteArticles&articles=[1,2,3]&permanentDeletion=true
 *_________________________________________________
 * setArticleBlock [CSRF protected]
 *      Create/Update Article block.
 *      Creating requires relevant article auth.
 *      Updating requires auth or ownership.
 *
 *      params:
 *      create - bool, default false - if true, will only create new items
 *      type: string, block type, one of: 'markdown','image','cover','gallery','video', 'youtube','article'
 *      articleId: int, id of the article
 *      blockId: string, id of the block WHEN not creating new ones
 *      orderIndex: int, default 10000 - where you want to push the block in the article order (pushes other blocks forward).
 *                  If negative, will stay unpublished.
 *                  If above maximum article number of blocks, will just push to the end of the order.
 *                  10,000 assumes nobody is allowed even close to this number (I mean, seriously).
 *      safe: bool, default false- whether the relevant parameters (such as alt,name,caption, markdown text, etc)
 *            should pass through HTML purification (setting to true requires a special auth action).
 *      -- The following parameters are accepted depending on type --
 *      'markdown':
 *          text: string, markdown text - *SHOULD BE URI ENCODED!* (js function encodeURIComponent)
 *      'image':
 *      'cover':
 *          blockResourceAddress: address of the image
 *          alt : string, image alt, overrides original
 *          caption : string, image caption, overrides original
 *          name : string, image name, overrides original
 *      'gallery':
 *          blockCollectionName: string, name of the gallery
 *          caption: string, caption of the gallery
 *          name : string, gallery name, overrides original
 *          //TODO autoplay : whether to autoplay gallery
 *          //TODO delay : autoplay delay
 *          loop: whether to loop gallery
 *          center: whether to center gallery
 *          preview: whether gallery should have a preview
 *          slider: whether gallery should have a slider (if set to false, preview is always true)
 *          //TODO fullScreenOnClick: Whether to go into full-screen display mode when one of the images is clicked
 *      'video':
 *          blockResourceAddress: address of the video
 *          caption : string, video caption, overrides original
 *          name : string, video name, overrides original
 *          height : preferred player height
 *          width : preferred player width
 *          autoplay : whether to autoplay
 *          mute: whether to start muted
 *          loop: whether to loop
 *          controls: whether to show controls
 *      'youtube':
 *          text: string, identifier of the youtube video
 *          caption : string, block caption, overrides original
 *          height : preferred player height
 *          width : preferred player width
 *          autoplay : whether to autoplay
 *          mute: whether to start muted
 *          loop: whether to loop
 *          controls: whether to show controls
 *          embed: whether the embedded version should be rendered (only relevant when the full one can be rendered instead)
 *      'article':
 *          caption : string, article caption, overrides original
 *          otherArticleId: id of the linked article
 *
 *      Returns: string|int[] Either one of the standard error codes due to input/auth, or an array of codes:
 *          {
 *              'block': <code related to block creation>,
 *              'order': <code related to article order >
 *          }
 *
 *          Where possible codes are:
 *          'block':
 *              UPDATE:
 *                  -2 - failed to create items since one of the dependencies is missing
 *                  -1 - failed to connect to db
 *                   0 - success
 *                   1 - item does not exist (and update is true)
 *                   2 - item exists (and override is false)
 *                   3 - trying to create a new item with missing inputs
 *              CREATE:
 *                   Otherwise, one of them codes:
 *                  -3 - Missing inputs when creating one of the items
 *                  -2 - One of the dependencies missing.
 *                  -1 - unknown database error
 *                   int, >0 - ID of the created item
 *          'order':
 *              -1 db connection failure
 *              0 success
 *              1 article doesn't exist
 *
 * Examples:
 *      action=setArticleBlock&articleId=1&create=true&type=markdown&text=# test!
 *      action=setArticleBlock&articleId=1&create=true&type=image&blockResourceAddress=pluginImages/def_icon.png&alt=Test&caption=Test Caption&name=Test Name
 *      action=setArticleBlock&articleId=1&create=true&type=cover&blockResourceAddress=pluginImages/def_icon.png&alt=Test&caption=Test Caption&name=Test Name
 *      action=setArticleBlock&articleId=1&create=true&type=gallery&blockCollectionName=Test Gallery&caption=Test Caption&name=Test Name
 *      action=setArticleBlock&articleId=1&create=true&type=video&blockResourceAddress=test_vid.mp4&caption=Test Caption&name=Test Name&height=400&width=800&autoplay=true&mute=true&loop=true
 *      action=setArticleBlock&articleId=1&create=true&type=youtube&text=dQw4w9WgXcQ&caption=Test Caption&name=Test Name&height=400&width=800&autoplay=true&mute=true&loop=true&embed=true
 *      action=setArticleBlock&articleId=1&create=true&type=article&otherArticleId=2
 *
 *      action=setArticleBlock&articleId=1&blockId=1&type=markdown&text=<div>Test</div>&safe=true
 *      action=setArticleBlock&articleId=1&blockId=2&type=image&blockResourceAddress=pluginImages/def_icon.png&alt=Test&caption=Test Caption 2&name=Test Name 2
 *      action=setArticleBlock&articleId=1&blockId=3&type=cover&blockResourceAddress=pluginImages/def_icon.png&alt=Test&caption=Test Caption 2&name=Test Name 2
 *      action=setArticleBlock&articleId=1&blockId=4&type=gallery&blockCollectionName=Test Gallery&caption=Test Caption 2&name=Test Name 2
 *      action=setArticleBlock&articleId=1&blockId=5&type=video&blockResourceAddress=test_vid.mp4&caption=Test Caption&name=Test Name&height=400&width=800&autoplay=true&mute=true&loop=true
 *      action=setArticleBlock&articleId=1&blockId=6&type=youtube&text=dQw4w9WgXcQ&caption=Test Caption&name=Test Name&height=400&width=800&autoplay=true&mute=true&loop=true&embed=true
 *      action=setArticleBlock&articleId=1&blockId=7&type=article&otherArticleId=2
 *
 *      action=setArticleBlock&articleId=1&blockId=5&type=video&blockResourceAddress=test_vid.mp4&caption=Test Caption&name=Test Name&height=400&width=800&autoplay=true&mute=true&loop=true&text=dQw4w9WgXcQ&alt=Test&otherArticleId=2
 *_________________________________________________
 * deleteArticleBlocks [CSRF protected]
 *      Deletes article blocks.
 *
 *      params:
 *      articleId: int, id of the article
 *      deletionTargets: int[], json encoded array of block INDEXES in the order if permanentDeletion is false, or BLOCK IDs if permanentDeletion is true
 *      permanentDeletion - bool, default false - if true, really deletes blocks - otherwise, just removes them from order (thus, hiding them).
 *
 *      Returns: string|int[] Either one of the standard error codes due to input/auth, or an array of codes:
 *          {
 *              'block': <code related to block creation - only relevant IF permanentDeletion is true>,
 *              'order': <code related to article order >
 *          }
 *
 *          Where possible codes are:
 *          'block':
 *                  -1 - failed to connect to db
 *                   0 - success
 *          'order':
 *              -1 db connection failure
 *              0 success
 *              1 article doesn't exist
 *
 * Examples:
 *      action=deleteArticleBlocks&articleId=1&permanentDeletion=true&deletionTargets=[1,2,3,4,5]
 *      action=deleteArticleBlocks&articleId=1&deletionTargets=[0,3,5]
 *_________________________________________________
 * moveBlockInArticle [CSRF protected]
 *      Move block in article order - from one index to another (starting at 0).
 *
 *      params:
 *      articleId: int, id of the article
 *      from: int
 *      to: int, if too large, just appends to article end.
 *
 *      Returns: int code
 *              -1 db connection failure
 *              0 success
 *              1 article doesn't exist
 *              2 from index doesn't exist
 *              3 to index doesn't exist (negative)
 *
 * Examples:
 *          action=moveBlockInArticle&articleId=1&from=1&to=5
 *          action=moveBlockInArticle&articleId=1&from=0&to=10000
 *          action=moveBlockInArticle&articleId=57546765&from=0&to=1
 *_________________________________________________
 * cleanArticleBlocks [CSRF protected]
 *      Deletes blocks from article order that no longer exist.
 *
 *      params:
 *      articleId: int, id of the article
 *
 *      Returns: int code
 *              -1 db connection failure
 *              0 success
 *              1 article doesn't exist
 *
 * Examples:
 *          action=cleanArticleBlocks&articleId=1
 *
 * */

if(!defined('coreInit'))
    require __DIR__ . '/../../main/coreInit.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';
require 'articles_fragments/definitions.php';
require __DIR__ . '/../../IOFrame/Handlers/ArticleHandler.php';

if($test)
    echo 'Testing mode!'.EOL;

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];

if(!checkApiEnabled('articles',$apiSettings,$_REQUEST['action']))
    exit(API_DISABLED);

//TODO For everything that has checks before auth, or object auth, add rate-limiting.

switch($action){
    case 'getArticles':

        $arrExpected =["keys","orderBy","orderType","titleLike","languageIs","addressIn","addressLike","createdBefore","createdAfter",
            "changedBefore","changedAfter","authAtMost","authIn","weightIn","offset","limit"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'articles_fragments/getArticles_checks.php';
        require 'articles_fragments/getArticles_auth.php';
        require 'articles_fragments/getArticles_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'getArticle':

        $arrExpected =["id","articleAddress","authAtMost","ignoreOrphan","preloadGalleries"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'articles_fragments/getArticle_checks.php';
        require 'articles_fragments/getArticle_auth.php';
        require 'articles_fragments/getArticle_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'setArticle':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["create","articleId","title","articleAddress","articleAuth","subtitle","caption","alt","name",
            "thumbnailAddress","blockOrder","weight","language"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'articles_fragments/setArticle_checks.php';
        require 'articles_fragments/setArticle_auth.php';
        require 'articles_fragments/setArticle_execution.php';


        echo ($result === 0)?
            '0' : $result;
        break;

    case 'deleteArticles':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["articles","permanentDeletion"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'articles_fragments/deleteArticles_checks.php';
        require 'articles_fragments/deleteArticles_auth.php';
        require 'articles_fragments/deleteArticles_execution.php';
        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'setArticleBlock':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["create","safe","articleId","blockId","orderIndex","type","text","blockResourceAddress","caption","alt","name",
            "blockCollectionName","height","width","autoplay","controls","mute","loop","embed","center","preview","fullScreenOnClick","slider",
            "otherArticleId"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'articles_fragments/setArticleBlock_checks.php';
        require 'articles_fragments/setArticleBlock_auth.php';
        require 'articles_fragments/setArticleBlock_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'deleteArticleBlocks':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["articleId","deletionTargets","permanentDeletion"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'articles_fragments/deleteArticleBlocks_checks.php';
        require 'articles_fragments/deleteArticleBlocks_auth.php';
        require 'articles_fragments/deleteArticleBlocks_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    case 'moveBlockInArticle':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["articleId","from","to"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'articles_fragments/moveBlockInArticle_checks.php';
        require 'articles_fragments/moveBlockInArticle_auth.php';
        require 'articles_fragments/moveBlockInArticle_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'cleanArticleBlocks':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected =["articleId"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'articles_fragments/cleanArticleBlocks_checks.php';
        require 'articles_fragments/cleanArticleBlocks_auth.php';
        require 'articles_fragments/cleanArticleBlocks_execution.php';

        if(is_array($result))
            echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        else
            echo ($result === 0)?
                '0' : $result;
        break;

    default:
        exit('Specified action is not recognized');
}

?>


