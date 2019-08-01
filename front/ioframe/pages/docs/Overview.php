<?php

if(!defined('coreInit'))
    require __DIR__ . '/../../../../main/coreInit.php';

?>

<!DOCTYPE html>
<?php require $settings->getSetting('absPathToRoot').'front/ioframe/templates/headers.php';


echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<script src="'.$dirToRoot.'front/ioframe/js/docs/highlight.js/highlight.pack.js"></script>';
echo '<script src="'.$dirToRoot.'front/ioframe/js/ezPopup.js"></script>';
echo '<script>hljs.initHighlightingOnLoad();</script>';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/global.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/docs/ioframe-highlight.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/docs/main.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/docs/docs.css">';
if($auth->isAuthorized(0))
    echo '<script src="'.$dirToRoot.'front/ioframe/js/vue/2.6.10/vue.js"></script>';
else
    echo '<script src="'.$dirToRoot.'front/ioframe/js/vue/2.6.10/vue.min.js"></script>';

echo '<title>IOFrame - Overview</title>';
?>

<!--
This document is meant to host all documentation of the IOFrame framework. Inside the actual framework website, contents may be split, depending on the layout.
-->

<body>

<?php
    require $settings->getSetting('absPathToRoot').'front/ioframe/templates/docs/header.php';
    require $settings->getSetting('absPathToRoot').'front/ioframe/templates/docs/docs.php';
    require $settings->getSetting('absPathToRoot').'front/ioframe/templates/docs/footer.php';
?>

</body>

<?php
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/docs/doc.css">';
echo '<link rel="stylesheet" href="'.$dirToRoot.'front/ioframe/css/docs/toc.css">';
echo '<script src="'.$dirToRoot.'front/ioframe/js/docs/modules/toc.js"></script>';
echo '<script src="'.$dirToRoot.'front/ioframe/js/docs/modules/doc.js"></script>';

require $settings->getSetting('absPathToRoot').'front/ioframe/templates/footers.php';
?>