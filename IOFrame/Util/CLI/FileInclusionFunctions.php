<?php

namespace IOFrame\Util\CLI{

    define('IOFrameUtilFileInclusionFunctions',true);

    class FileInclusionFunctions{

        /** Validates a variable is either an array or a JSON string,
         * @param mixed $toValidate Valid json/array will be returned as array, string will be encapsulated in array
         * @param array $errors Errors to populate, see CLIManager
         * @param string $error Name of the error to populate
         * @return bool true if $toValidate could become valid array, false otherwise
         * */
        public static function validateAndEnsureArray(mixed &$toValidate, array &$errors, string $error): bool {
            if(gettype($toValidate) === 'string'){
                if(\IOFrame\Util\PureUtilFunctions::is_json($toValidate))
                    $toValidate = json_decode($toValidate,true);
                else
                    $toValidate = [$toValidate];
            }
            elseif(gettype($toValidate) !== 'array'){
                $errors[$error] = true;
                return false;
            }
            return true;
        }

        /** Constructs default options for each subfolder (see IOFrame\Util\FileSystemFunctions::fetchAllFolderAddresses)
         * @param array $v CLIManager->variables, but can be created dynamically
         * @param string $target Name of the settings variable (should be a JSON, see testing / cron-management cli)
         * @return array Either empty array on failure, or default options that return all PHP file addresses in a folder, without subfolders
         * */
        public static function constructSubFolderOptions(array $v,string $target, array $params = []): array {
            \IOFrame\Util\PureUtilFunctions::createPathInObject($v,[$target,'include']);
            \IOFrame\Util\PureUtilFunctions::createPathInObject($v,[$target,'exclude']);
            \IOFrame\Util\PureUtilFunctions::createPathInObject($v,[$target,'excludeFolders']);
            \IOFrame\Util\PureUtilFunctions::createPathInObject($v,[$target,'includeFolders']);

            return [
                'subFolders'=>$v[$target]['subFolders']??false,
                'returnFolders'=>false,
                'exclude'=>array_merge($v[$target]['exclude'],$params['excludeFileTypes']??[]),
                'include'=>array_merge($v[$target]['include'],$params['includeFileTypes']??[]),
                'excludeFolders'=>array_merge($v[$target]['excludeFolders'],$params['excludeFolders']??[]),
                'includeFolders'=>array_merge($v[$target]['includeFolders'],$params['includeFolders']??[]),
            ];
        }

        /** Populates target array with valid PHP scripts, collected from URLS of files / folders, relative to project / global root.
         * Wrapper around populateWithFiles()
         * */
        public static function populateWithPHPScripts(array &$target, array $urls, array $v, string $optionsName, string $projectRoot,array $params = []): void {
            self::populateWithFiles($target,$urls,$v,$optionsName,$projectRoot,array_merge($params, [ 'includeFileTypes'=>['\.php$'] ]));
        }

        /** Populates target array with valid files scripts, collected from URLS of files / folders, relative to project / global root.
         * @param array $target Array to populate - merges in all unique PHP valid scripts
         * @param array|string $urls file/folder URLs
         * @param array $v CLIManager->variables, but can be created dynamically
         * @param string $optionsName Name of folder inclusion options variable in $v
         * @param string $projectRoot Absolute path to be considered project root
         * @param array $params
         */
        public static function populateWithFiles(array &$target, array|string $urls, array $v, string $optionsName, string $projectRoot, array $params = []): void {

            $options = self::constructSubFolderOptions($v,$optionsName,$params);
            if(!is_array($urls))
                $urls = [$urls];
            foreach ($urls as $path){

                //Either project or global scope are valid
                if(!is_file($path) && !is_dir($path) && (is_file($projectRoot.$path) || is_dir($projectRoot.$path)))
                    $path = $projectRoot.$path;

                if(is_file($path)){
                    // Check inclusion/exclusion requirements
                    $excluded = false;
                    if(!empty($options['exclude'])){
                        foreach($options['exclude'] as $regex){
                            if(preg_match('/'.$regex.'/',$path)) {
                                $excluded = true;
                                break;
                            }
                        }
                    }
                    if(!$excluded && !empty($options['include'])){
                        $excluded = true;
                        foreach($options['include'] as $regex){
                            if(preg_match('/'.$regex.'/',$path)) {
                                $excluded = false;
                                break;
                            }
                        }
                    }

                    //Skip if file is either excluded, or already im
                    if($excluded || in_array($path,$target))
                        continue;

                    $target[] = $path;
                }
                //In case of directory, continue in
                elseif (is_dir($path)){
                    $target = array_unique(array_merge($target,\IOFrame\Util\FileSystemFunctions::fetchAllFolderAddresses($path,$options)));
                }
            }
        }

        /** For URLs that start with the project root, trims it
         * @param string $url
         * @param string $projectRoot
         * @return string Potentially trimmed URL
         * */
        public static function url2Id(string $url, string $projectRoot): string {
            if(str_starts_with($url,$projectRoot))
                return substr($url,strlen($projectRoot));
            else
                return $url;
        }
    }
}