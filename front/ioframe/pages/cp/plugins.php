<!DOCTYPE html>
<?php

require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'headers_start.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'cp_redirect_to_login.php';

/*God this first Vue module is so shit the same JS/CSS files contain both the module and the components, so no reason to package*/
array_push($JS,'ezPopup.js','mixins/sourceUrl.js','modules/CPMenu.js','modules/plugins.js','modules/pluginList.js');

array_push($CSS,'cp.css','animations.css','popUpTooltip.css','modules/CPMenu.css','modules/plugins.css');

require $settings->getSetting('absPathToRoot') . $IOFrameTemplateRoot.'headers_get_resources.php';

echo '<title>Plugins</title>';

$frontEndResourceTemplateManager->printResources('CSS');

echo '<title>Plugins</title>';
?>

<?php
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'plugins',
            'title' => 'Plugins'
        ]
    ]);
?>

<script>
    document.siteConfig = <?php echo json_encode($siteConfig)?>;
    if(document.siteConfig.page.title !== undefined)
        document.title = document.siteConfig.page.title;
</script>

<?php require $settings->getSetting('absPathToRoot') . $IOFrameTemplateRoot.'headers_end.php'; ?>

<body>

    <div class="wrapper">
        <?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/CPMenu.php';?>
        <div id="plugins">
            <?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/plugins.php';?>
            <?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/pluginList.php';?>
        </div>
    </div>

</body>

<?php

require $settings->getSetting('absPathToRoot') . $IOFrameTemplateRoot.'footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'footers_end.php';

