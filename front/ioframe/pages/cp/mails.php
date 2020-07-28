<!DOCTYPE html>
<?php

require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'headers_start.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'cp_redirect_to_login.php';

array_push($JS,'mixins/sourceURL.js','modules/CPMenu.js','modules/mails.js');

array_push($CSS,'cp.css','modules/CPMenu.css','modules/mails.css');

require $settings->getSetting('absPathToRoot') . $IOFrameTemplateRoot.'/headers_get_resources.php';

echo '<title>Mails</title>';

$frontEndResourceTemplateManager->printResources('CSS');
?>

<?php
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'mails',
            'title' => 'Mails'
        ]
    ]);
?>

<script>
    document.siteConfig = <?php echo json_encode($siteConfig)?>;
    if(document.siteConfig.page.title !== undefined)
        document.title = document.siteConfig.page.title;
</script>


<?php require $settings->getSetting('absPathToRoot') . $IOFrameTemplateRoot.'/headers_end.php'; ?>

<body>

<div class="wrapper">
    <?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'/modules/CPMenu.php';?>
    <?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'/modules/mails.php';?>
</div>

</body>

<?php

require $settings->getSetting('absPathToRoot') . $IOFrameTemplateRoot.'/footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'/footers_end.php';

?>