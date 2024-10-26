<!DOCTYPE html>
<?php

require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'cp_redirect_to_login.php';

array_push($CSS, 'cp.css', 'components/searchList.css', 'modules/CPMenu.css');
$CSSPackages['CPAuthCSS'] = [
    'items'=>['components/auth/actionsEditor.css', 'components/auth/groupsEditor.css', 'components/auth/usersEditor.css', 'components/auth/editor.css', 'modules/auth.css'],
    'order'=>-1
];
array_push($JS, 'mixins/sourceUrl.js','mixins/searchListFilterSaver.js', 'components/searchList.js', 'modules/CPMenu.js');
$JSPackages['CPAuthJS'] = [
    'items'=>['components/auth/actionsEditor.js', 'components/auth/groupsEditor.js', 'components/auth/usersEditor.js', 'modules/auth.js' ],
    'order'=>-1
];

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';

echo '<title>Authorization</title>';

$frontEndResourceTemplateManager->printResources('CSS');

?>


<?php
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'auth',
            'title' => 'Authorization'
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
<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/auth.php';?>

 </div>

</body>


<?php

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_end.php';
