<!DOCTYPE html>
<?php

require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';

$CSS[] = 'welcome.css';
foreach($languages as $languagePrefix)
    $CSS[] = $languagePrefix . '_welcome.css';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';

echo '<title>IOFrame Installed!</title>';

$frontEndResourceTemplateManager->printResources('CSS');

?>

<script>
    document.siteConfig = <?php echo json_encode($siteConfig)?>;
</script>

<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_end.php'; ?>

<body>

<div id="welcome">
    <div class="title"></div>
    <div class="explanation">
        <div class="why-this-page-is-here"></div>
        <div class="owner"></div>
        <div class="dev"></div>
    </div>
    <div class="links">
        <div class="control-panel">
            <a href="cp/login"></a>
        </div>
        <div class="docs-site">
            <a href="https://ioframe.io"></a>
        </div>
    </div>
</div>

</body>


<?php

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_end.php';
