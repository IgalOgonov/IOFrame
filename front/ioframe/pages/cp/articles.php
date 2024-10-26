<!DOCTYPE html>
<?php

require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'cp_redirect_to_login.php';

array_push($CSS,'cp.css', 'components/searchList.css','components/media/mediaViewer.css',
    'components/mediaSelector.css', 'modules/CPMenu.css');
$CSSPackages['CPArticlesCSS'] = [
    'items'=>['ext/highlight.js/ioframe-highlight.css','components/articles/defaultHeadlineRenderer.css','components/IOFrameGalleryDefault.css',
        'components/articles/articleBlockEditor.css','components/articles/articlesEditor.css', 'modules/articles.css'],
    'order'=>-1
];
array_push($JS, 'mixins/indexedDBInterface.js','mixins/cacheableObjectGetter.js','mixins/searchListFilterSaver.js',
    'mixins/sourceUrl.js','mixins/componentSize.js','mixins/objectEditor.js','components/IOFrameGallery.js','components/media/mediaViewer.js',
    'components/searchList.js','components/mediaSelector.js', 'modules/CPMenu.js');
$JSPackages['CPArticlesJS'] = [
    'items'=>['ext/marked/marked.min.js','ext/purifyHTML/purify.min.js', 'ext/highlight.js/highlight.pack.js', 'components/articles/defaultHeadlineRenderer.js',
        'components/articles/articleBlockEditor.js', 'components/articles/articlesEditor.js', 'modules/articles.js' ],
    'order'=>-1
];
$minifyOptions = [];

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';

echo '<title>Articles</title>';

$frontEndResourceTemplateManager->printResources('CSS');

?>


<?php
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'articles',
            'title' => 'Articles'
        ]
    ]);
?>

<script>
    document.siteConfig = <?php echo json_encode($siteConfig)?>;
    if(document.siteConfig.page.title !== undefined)
        document.title = document.siteConfig.page.title;
</script>

<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_end.php'; ?>

<body>

<div class="wrapper">
<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/CPMenu.php';?>
<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/articles.php';?>

 </div>

</body>


<?php

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_end.php';