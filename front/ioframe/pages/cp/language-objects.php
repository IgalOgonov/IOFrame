<!DOCTYPE html>
<?php
require $settings->getSetting('absPathToRoot').'front/ioframe/templates/definitions.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_start.php';

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'cp_redirect_to_login.php';

array_push($CSS, 'cp.css', 'components/searchList.css', 'modules/CPMenu.css');
$CSSPackages['CPLanguageObjectsCSS'] = [
    'items'=>[ 'components/languageObjects/languageObjectsEditor.css', 'modules/languageObjects.css',],
    'order'=>-1
];
array_push($JS, 'mixins/sourceUrl.js','mixins/searchListFilterSaver.js', 'components/searchList.js', 'modules/CPMenu.js');
$JSPackages['CPLanguageObjectsJS'] = [
    'items'=>['components/languageObjects/languageObjectsEditor.js', 'modules/languageObjects.js' ],
    'order'=>-1
];


require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_get_resources.php';

echo '<title>Tags</title>';

$frontEndResourceTemplateManager->printResources('CSS');

?>


<?php

//Language Loading example (without dedicated template)
$languageObjects = ['languageObjectsEditor','languageObjectsCPMain'];
require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'headers_load_languages.php';
if($gotNewLanguageObjects && $languageObjects['languageObjectsCPMain']??false){
    $siteConfig['languageObjects'] = [
            'text'=> $languageObjects['languageObjectsCPMain']['object']
    ];
    if($languageObjects['languageObjectsEditor']??false)
        $siteConfig['languageObjects']['text'] = array_merge($siteConfig['languageObjects']['text'],$languageObjects['languageObjectsEditor']['object']);
}

//Regular stuff
$siteConfig = array_merge($siteConfig,
    [
        'page'=> [
            'id' => 'language-objects',
            'title' => 'Language Objects'
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
<?php require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot.'modules/languageObjects.php';?>

 </div>

</body>


<?php

require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_start.php';

$frontEndResourceTemplateManager->printResources('JS');


require $settings->getSetting('absPathToRoot').$IOFrameTemplateRoot . 'footers_end.php';
