<!DOCTYPE html>
<?php
require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'cp_redirect_to_login.php';

array_push($CSS, 'cp.css', 'components/searchList.css','components/media/mediaViewer.css','components/mediaSelector.css', 'modules/CPMenu.css');
array_push($JS, 'mixins/sourceUrl.js','mixins/searchListFilterSaver.js','components/media/mediaViewer.js',
    'components/mediaSelector.js','components/searchList.js', 'modules/CPMenu.js');
$CSSPackages['CPTagsCSS'] = [
    'items'=>[ 'components/tags/tagsEditor.css', 'modules/tags.css'],
    'order'=>-1
];
$JSPackages['CPTagsJS'] = [
    'items'=>[ 'components/tags/tagsEditor.js', 'modules/tags.js'],
    'order'=>-1
];


require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';

echo '<title>Tags</title>';

$frontEndResourceTemplateManager->printResources('CSS');

?>


<?php
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'tags',
            'title' => 'Tags'
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
<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/tags.php';?>

 </div>

</body>


<?php

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');


require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_end.php';
