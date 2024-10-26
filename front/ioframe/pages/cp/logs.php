<!DOCTYPE html>
<?php

require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'cp_redirect_to_login.php';

array_push($CSS, 'cp.css', 'components/searchList.css', 'modules/CPMenu.css');
$CSSPackages['CPLogsCSS'] = [
    'items'=>['components/logs/groupEditor.css', 'components/logs/ruleEditor.css','modules/logs.css'],
    'order'=>-1
];
array_push($JS, 'mixins/sourceUrl.js','mixins/objectEditor.js','mixins/searchListFilterSaver.js','components/searchList.js', 'modules/CPMenu.js');
$JSPackages['CPLogsJS'] = [
    'items'=>['ext/chart.js/4.4.0/chart.umd.min.js', 'components/logs/groupEditor.js', 'components/logs/ruleEditor.js', 'modules/logs.js'],
    'order'=>-1
];


require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';

echo '<title>Logs</title>';

$frontEndResourceTemplateManager->printResources('CSS');
?>


<?php
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'logs',
            'title' => 'Logs'
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
<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/logs.php';?>

 </div>

</body>


<?php

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_end.php';
