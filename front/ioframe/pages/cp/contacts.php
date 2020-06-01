<!DOCTYPE html>
<?php

require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'cp_redirect_to_login.php';

array_push($CSS, 'cp.css', 'components/searchList.css', 'components/contacts/contactsEditor.css', 'modules/contacts.css', 'modules/CPMenu.css', 'modules/contacts.css');
array_push($JS, 'mixins/sourceURL.js', 'mixins/eventHubManager.js', 'components/searchList.js', 'components/contacts/contactsEditor.js', 'modules/CPMenu.js', 'modules/contacts.js');


require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';

echo '<title>Contacts</title>';

echo '<link rel="stylesheet" href="' . $dirToRoot . $IOFrameCSSRoot . $CSSResources['cp.css']['relativeAddress'] . '"">';
echo '<link rel="stylesheet" href="' . $dirToRoot . $IOFrameCSSRoot . $CSSResources['components/searchList.css']['relativeAddress'] . '"">';
echo '<link rel="stylesheet" href="' . $dirToRoot . $IOFrameCSSRoot . $CSSResources['components/contacts/contactsEditor.css']['relativeAddress'] . '"">';
echo '<link rel="stylesheet" href="' . $dirToRoot . $IOFrameCSSRoot . $CSSResources['modules/contacts.css']['relativeAddress'] . '"">';
echo '<link rel="stylesheet" href="' . $dirToRoot . $IOFrameCSSRoot . $CSSResources['modules/CPMenu.css']['relativeAddress'] . '"">';

?>


<?php
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'contacts',
            'title' => 'Contacts'
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
<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/contacts.php';?>

 </div>

</body>


<?php

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';

echo '<script src="'.$dirToRoot.$IOFrameJSRoot . $JSResources['mixins/sourceURL.js']['relativeAddress'].'"></script>';
echo '<script src="'.$dirToRoot.$IOFrameJSRoot . $JSResources['mixins/eventHubManager.js']['relativeAddress'].'"></script>';
echo '<script src="'.$dirToRoot.$IOFrameJSRoot . $JSResources['components/searchList.js']['relativeAddress'].'"></script>';
echo '<script src="'.$dirToRoot.$IOFrameJSRoot . $JSResources['components/contacts/contactsEditor.js']['relativeAddress'].'"></script>';
echo '<script src="'.$dirToRoot.$IOFrameJSRoot . $JSResources['modules/CPMenu.js']['relativeAddress'].'"></script>';
echo '<script src="'.$dirToRoot.$IOFrameJSRoot . $JSResources['modules/contacts.js']['relativeAddress'].'"></script>';


require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_end.php';

?>