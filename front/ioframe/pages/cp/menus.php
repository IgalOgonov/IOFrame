<!DOCTYPE html>
<?php

require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'cp_redirect_to_login.php';

array_push($CSS, 'cp.css', 'components/searchList.css', 'modules/CPMenu.css');
array_push($JS, 'mixins/sourceUrl.js', 'components/searchList.js', 'modules/CPMenu.js' );
$CSSPackages['CPMenusCSS'] = [
    'items'=>[ 'components/menus/menusEditor.css', 'modules/menus.css'],
    'order'=>-1
];
$JSPackages['CPMenusJS'] = [
    'items'=>[ 'components/menus/menusEditor.js', 'modules/menus.js'],
    'order'=>-1
];

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';
require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_load_languages.php';

echo '<title>Menus</title>';

$frontEndResourceTemplateManager->printResources('CSS');
?>


<?php
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'menus',
            'title' => 'Menus'
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
<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/menus.php';?>

 </div>

</body>


<?php

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_end.php';
