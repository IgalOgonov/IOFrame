<?php

$targetLanguage = $details['Preferred_Language']??null;
$gotNewLanguageObjects = false;
if($targetLanguage &&
    ( $targetLanguage !== $siteSettings->getSetting('defaultLanguage') ) &&
    !empty($languageObjects) &&
    is_array($languageObjects)
){
    if(!isset($LanguageObjectHandler))
        $LanguageObjectHandler = new \IOFrame\Handlers\LanguageObjectHandler(
            $settings,
            array_merge($defaultSettingsParams, ['siteSettings'=>$siteSettings])
        );
    $languageObjects = $LanguageObjectHandler->getLoadedObjects($languageObjects, ['language'=>$targetLanguage]);
    $gotNewLanguageObjects = true;
}