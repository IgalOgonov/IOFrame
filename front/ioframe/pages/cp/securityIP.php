<!DOCTYPE html>
<?php

require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'cp_redirect_to_login.php';

array_push($CSS, 'cp.css', 'components/searchList.css', 'modules/CPMenu.css');
array_push($JS, 'mixins/sourceUrl.js', 'mixins/searchListFilterSaver.js', 'components/searchList.js','modules/CPMenu.js');
$CSSPackages['CPSecurityIPCSS'] = [
    'items'=>[ 'components/security/securityIPsEditor.css', 'components/security/securityIPRangesEditor.css','modules/securityIP.css'],
    'order'=>-1
];
$JSPackages['CPSecurityIPJS'] = [
    'items'=>[ 'components/security/securityIPsEditor.js','components/security/securityIPRangesEditor.js','modules/securityIP.js'],
    'order'=>-1
];


require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';

echo '<title>(Security) IP</title>';

$frontEndResourceTemplateManager->printResources('CSS');

?>


<?php
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'securityIP',
            'title' => '(Security) IP'
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
<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/securityIP.php';?>

 </div>

</body>


<?php

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');


require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_end.php';