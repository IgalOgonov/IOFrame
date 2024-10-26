<?php
namespace IOFrame\Managers{

    use IOFrame\Handlers\SettingsHandler;
    use JetBrains\PhpStorm\NoReturn;

    define('IOFrameManagersV1APIManager',true);

    /* A utility class meant to manage the v1 api.
     * As a reminder, the request type in this API is meaningless (GET/POST), and the API itself is based around actions, and 3 main stages - validation, authorization, and execution.
     * Thus, most functions here are static
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * */
    class v1APIManager extends \IOFrame\Abstract\DBWithCache
    {

        /** @var \IOFrame\Handlers\SettingsHandler|null API Settings
         */
        public ?\IOFrame\Handlers\SettingsHandler $apiSettings;

        /** @var array Just to save default params for reuse in certain functions
         */
        public array $defaultSettingsParams;

        /** @var \IOFrame\Handlers\SecurityHandler|null Security Handler
         */
        public ?\IOFrame\Handlers\SecurityHandler $SecurityHandler = null;

        /** @var \IOFrame\Handlers\IPHandler|null IP Handler
         */
        public ?\IOFrame\Handlers\IPHandler $IPHandler = null;

        /** @var \IOFrame\Handlers\Extenders\RateLimiting|null RateLimit Handler
         */
        public ?\IOFrame\Handlers\Extenders\RateLimiting $RateLimiting = null;

        /** Standard constructor
         *
         * Constructs an instance of the class, getting the main settings file and an existing DB connection, or generating
         * such a connection itself.
         *
         * @param SettingsHandler $settings Local settings
         * @param SettingsHandler|null $apiSettings API Settings
         * @param array $params Default settings
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, SettingsHandler $apiSettings = null, array $params = [])
        {
            $this->apiSettings = $apiSettings ?? null;
            $this->defaultSettingsParams = $params;
            parent::__construct($settings, $params);
        }

        //TODO Maybe add as a trait
        private function initiateHandlerIfNotExist($type): bool {
            $_noHandler = function ($handler, $name){
                return empty($handler) || !is_a($handler,$name);
            };
            switch ($type){
                case 'IP':
                    if($_noHandler($this->IPHandler,'IOFrame\Handlers\IPHandler'))
                        $this->IPHandler = new \IOFrame\Handlers\IPHandler(
                            $this->settings,
                            $this->defaultSettingsParams
                        );
                    break;
                case 'Security':
                    if($_noHandler($this->SecurityHandler,'IOFrame\Handlers\SecurityHandler'))
                        $this->SecurityHandler = new \IOFrame\Handlers\SecurityHandler(
                            $this->settings,
                            $this->defaultSettingsParams
                        );
                    break;
                case 'RateLimit':
                    if($_noHandler($this->RateLimiting,'IOFrame\Handlers\Extenders\RateLimit'))
                        $this->RateLimiting = new \IOFrame\Handlers\Extenders\RateLimiting(
                            $this->settings,
                            $this->defaultSettingsParams
                        );
                    break;
                default:
                    return false;
            }
            return true;
        }

        #[NoReturn] public static function exitWithResponseAsJSON($response, $setHeader = true): void {
            if($setHeader)
                header('Content-Type: application/json');
            exit(json_encode($response,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS));
        }

        /** Validates inputs, based on a filters object.
         * @param array $inputs Inputs object, keys and values
         * @param array $filters Filters object of the form: {
         *                  <inputKey>:{
         *                      'type':<string, one of 'bool','string','int','string[]','int[]','json','function'>
         *                      'exceptions':<string[], in case of basic values, array of exceptions that would be considered valid regardless of validation>
         *                      'replaceExceptionWithNull':<bool, default false - replaces exceptions with null>
         *                      'replaceException':<mixed, default null - replaces exceptions with this value>
         *                      'replaceExceptionMap':<array, replaces each exception - represented by the array keys - with the value>
         *                      'default':<mixed, replaces input with default value if it is unset/null>
         *                      'required':<bool, default true if no defaults, false if defaults exist - whether the input is required or optional>
         *                      'ignoreNull':<bool, default false - if true, allows null as a valid value without trying to replace it with a default one>
         *                      'valid':<mixed, for base types or arrays, can be either an array of specific valid values, or for a string - a regex string (without the "/"'s).
         *                              Can be a function (required for function type), which uses an object of the form ['inputs'=>$inputs,'filters'=>$filters,'index'=>$input,'input'=>$inputs[$input],'filter'=>$filterArr,'params'=>$params]>
         *                      'min':<int, for integers - minimum value, for strings - minimum length. propagates to array elements>
         *                      'max':<int, for integers - maximum value, for strings - maximum length. propagates to array elements>
         *                      'keepJson':<bool, default false - if not explicitly set to true, will convert json strings to objects in the original input array>
         *                  }
         *              }
         * @param array $params
         *              TODO '_patternFilters':<bool, default false - each key is a pattern to match, each value is an array similar to the above>
         *
         * @return array of the form:
         *              'passed': <bool, whether validation passed>
         *              'failed': <string[], array of input keys of failed inputs>
         */
        public static function baseValidation(array &$inputs, array $filters, array $params = []): array {
            $test = $params['test'] ?? false;
            $result = [
                'passed'=>true,
                'failed'=>[]
            ];

            foreach($filters as $input=>$filterArr){
                $isException = false;
                if(!empty($filterArr['exceptions']))
                    foreach ($filterArr['exceptions'] as $exception)
                        if(($inputs[$input]??null) === $exception)
                            $isException = true;
                if(!isset($inputs[$input])){
                    /*If required, which defaults to true if no default, false if default is set*/
                    if( $filterArr['required'] ?? !isset($filterArr['default']) ){
                        $result['passed'] = false;
                        $result['failed'][] = $input;
                    }
                    elseif(empty($filterArr['ignoreNull']))
                        $inputs[$input] = $filterArr['default'] ?? null;
                }
                elseif(!$isException){
                    switch($filterArr['type']){
                        case 'bool':
                            $inputs[$input] = (bool)$inputs[$input];
                            break;
                        case 'string':
                        case 'int':
                            if(isset($filterArr['valid']) && is_array($filterArr['valid']) && !in_array($inputs[$input],$filterArr['valid'])){
                                if($test)
                                    echo $input.' must be in '.json_encode($filterArr['valid']).EOL;
                                $result['passed'] = false;
                                $result['failed'][] = $input;
                            }
                            elseif(isset($filterArr['valid']) && !is_array($filterArr['valid']) && ($filterArr['type'] === 'string') && !preg_match('/'.$filterArr['valid'].'/u',$inputs[$input])){
                                if($test)
                                    echo $input.' must match '.$filterArr['valid'].EOL;
                                $result['passed'] = false;
                                $result['failed'][] = $input;
                            }
                            elseif(($filterArr['type'] === 'string') && isset($filterArr['min']) && (strlen($inputs[$input]) < $filterArr['min']) ){
                                if($test)
                                    echo $input.' must be at least the length '.$filterArr['min'].EOL;
                                $result['passed'] = false;
                                $result['failed'][] = $input;
                            }
                            elseif(($filterArr['type'] === 'string') && isset($filterArr['max']) && (strlen($inputs[$input]) > $filterArr['max']) ){
                                if($test)
                                    echo $input.' must be at most the length'.$filterArr['max'].EOL;
                                $result['passed'] = false;
                                $result['failed'][] = $input;
                            }
                            elseif(($filterArr['type'] === 'int') && !( filter_var($inputs[$input],FILTER_VALIDATE_INT)  || ($inputs[$input] === 0) ) ){
                                if($test)
                                    echo $input.' must be a valid integer!'.EOL;
                                $result['passed'] = false;
                                $result['failed'][] = $input;
                            }
                            elseif(($filterArr['type'] === 'int') && isset($filterArr['min']) && ($inputs[$input] < $filterArr['min']) ){
                                if($test)
                                    echo $input.' must be at least '.$filterArr['min'].EOL;
                                $result['passed'] = false;
                                $result['failed'][] = $input;
                            }
                            elseif(($filterArr['type'] === 'int') && isset($filterArr['max']) && ($inputs[$input] > $filterArr['max']) ){
                                if($test)
                                    echo $input.' must be at most '.$filterArr['max'].EOL;
                                $result['passed'] = false;
                                $result['failed'][] = $input;
                            }
                            elseif(isset($filterArr['valid']) && is_callable($filterArr['valid']) && !$filterArr['valid'](['inputs'=>$inputs,'filters'=>$filters,'index'=>$input,'input'=>$inputs[$input],'filter'=>$filterArr,'params'=>$params])){
                                if($test)
                                    echo $input.' must pass the validation function'.EOL;
                                $result['passed'] = false;
                                $result['failed'][] = $input;
                            }
                            break;
                        case 'string[]':
                        case 'int[]':
                        case 'json':
                            if(!is_array($inputs[$input])){
                                if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs[$input])){
                                    if($test)
                                        echo $input.' must be a valid json!'.EOL;
                                    $result['passed'] = false;
                                    $result['failed'][] = $input;
                                    break;
                                }
                                $inputs[$input] = json_decode($inputs[$input],true);
                            }
                            if($filterArr['type'] === 'int[]'){
                                foreach ($inputs[$input] as $integer){
                                    if(!( filter_var($integer,FILTER_VALIDATE_INT) || ($integer === 0) ) ){
                                        if($test)
                                            echo $input.' must be a valid integer array!'.EOL;
                                        $result['passed'] = false;
                                        $result['failed'][] = $input;
                                    }
                                }
                            }
                            elseif(isset($filterArr['min'])){
                                foreach ($inputs[$input] as $item){
                                    $cond = strlen($item)<$filterArr['min'];
                                    if($cond){
                                        if($test)
                                            echo $input.' must all be at least '.$filterArr['min'].EOL;
                                        $result['passed'] = false;
                                        $result['failed'][] = $input;
                                    }
                                }
                            }
                            elseif(isset($filterArr['max'])){
                                foreach ($inputs[$input] as $item){
                                    $cond = strlen($item)>$filterArr['max'];
                                    if($cond){
                                        if($test)
                                            echo $input.' must all be at least '.$filterArr['max'].EOL;
                                        $result['passed'] = false;
                                        $result['failed'][] = $input;
                                    }
                                }
                            }
                            elseif(isset($filterArr['valid']) && (gettype($filterArr['valid']) === 'string') && ($filterArr['type'] !== 'int[]')){
                                foreach ($inputs[$input] as $string){
                                    if(!preg_match('/'.$filterArr['valid'].'/',$string)){
                                        if($test)
                                            echo $input.' must all match '.$filterArr['valid'].EOL;
                                        $result['passed'] = false;
                                        $result['failed'][] = $input;
                                    }
                                }
                            }
                            elseif(isset($filterArr['valid']) && is_array($filterArr['valid']) && (count(array_diff($inputs[$input],$filterArr['valid'])) > 0)){
                                if($test)
                                    echo $input.' must be in '.json_encode($filterArr['valid']).EOL;
                                $result['passed'] = false;
                                $result['failed'][] = $input;
                            }
                            elseif(isset($filterArr['valid']) && is_callable($filterArr['valid']) && !$filterArr['valid'](['inputs'=>$inputs,'filters'=>$filters,'index'=>$input,'input'=>$inputs[$input],'filter'=>$filterArr,'params'=>$params])){
                                if($test)
                                    echo $input.' must pass the validation function'.EOL;
                                $result['passed'] = false;
                                $result['failed'][] = $input;
                            }
                            if(!empty($filterArr['keepJson']))
                                $inputs[$input] = json_encode($inputs[$input]);
                            break;
                        case 'function':
                            if(!$filterArr['valid'](['inputs'=>$inputs,'filters'=>$filters,'index'=>$input,'input'=>$inputs[$input],'filter'=>$filterArr,'params'=>$params])){
                                if($test)
                                    echo $input.' must pass the validation function'.EOL;
                                $result['passed'] = false;
                                $result['failed'][] = $input;
                            }
                            break;
                    }
                }
                else {
                    if ($filterArr['replaceExceptionWithNull'] ?? false)
                        $inputs[$input] = null;
                    elseif (isset($filterArr['replaceException']))
                        $inputs[$input] = $filterArr['replaceException'];
                    elseif (isset($filterArr['replaceExceptionMap'][$inputs[$input]]))
                        $inputs[$input] = $filterArr['replaceExceptionMap'][$inputs[$input]];
                }
            }

            if($test && !empty($result['failed']))
                echo 'Validation failure reasons: '.json_encode($result['failed']).EOL;
            return $result;
        }

        /** Parses items (typically results of "get" actions) via maps
         * @param array $item A single item in the object
         * @param array $map Map to parse item, of the form:
         *              {
         *                  <string, column name> => string/object, string defaults to an object {resultName:"string"}, object is of the form:
         *                  TODO anyOfValidChildren, oneOfValidChildren, anyOfValidChildrenPatterns, oneOfValidChildrenPatterns
         *                  {
         *                      "resultName":<string, what you want the key to be in the result>,
         *                      "type":<string, one of "object","csv","function","json","string","bool","int","double">
         *                      "separator":<string, default ',' - separator for a csv list>
         *                      "validChildren":<string[], in case of a "json type", you can quickly parse its valid children, and set nulls as default when they dont exist>
         *                      "validChildrenParsing":<object, default null - keys are relevant children, values are arrays similar to $map>
         *                      "requiredChildren":<string[], default null - if children are missing, will skip this key and populate an error>
         *                      "validChildrenValidation":<object, validation map from baseValidation(). Will populate $errors if any didn't pass>
         *                      "validChildrenPatterns":<string[], in case of a "json type", will match children keys against the valid patterns>
         *                      "validChildrenPatternsParsing":<object, default null - Similar to validChildrenParsing, with keys being patterns>
         *                      "requiredChildrenPatterns":<string[], default null - same as validChildrenPatterns, but matches each pattern>
         *                      "minChildrenPatterns":<int, default null - if passed, requires at least number of pattern children>
         *                      "maxChildrenPatterns":<int, default null - if passed, requires at most this number of pattern children>
         *                      TODO "requiredChildrenPatternsValidation":<object, validation map from baseValidation(). Will populate $errors if any didn't pass>
         *                      "expandChildren":<bool, default true - if true, will save children by their name, rather than under their specific key>
         *                      "function":<function, in case of "function" type, uses an object of the form ['item'=>$item,'index'=>$resKey,'rawValue'=>$resValue,'map'=>$map,'params'=>$params], and passed $errors as the 2nd arg>
         *                      "exclude":<array, excludes specific values)>
         *                      "replaceMap":<array, replaces specific values with different ones (runs after the initial parsing), of the form [ <final Value> => <replacement value> ]>
         *                      "map":<array, in case of "object" type, pass a new map for the recursive parsing>
         *                      "keyFunction": <function, can override resultName, uses an object of the form ['item'=>$item,'index'=>$resKey,'parsedValue'=>$resValue,'map'=>$map,'params'=>$params]>
         *                      "groupBy": <string|string[]|function, if passed, will group the value under the key (or recursively chain the keys) - objects that dont exist get created. Function same as keyFunction>
         *                      "ignoreInGroupIfNull": <bool, default false - if in group (defined by GroupBy), will ignore this item if the input was null (not if it was something that became null)>
         *                  }
         *                  ...
         *                  "_patternKeys": {
         *                      <string, regex pattern without the "/"'s> => <object, same as above>
         *                  }
         *              }
         * @param array|null $errors If set, will be populared by errors. Keys are the item keys, values can be codes, or nested objects in case of 'json' type.
         *                      Codes:
         *                      'no-map' - Item key does not have a map (can be ignored via ignoreNoMapError)
         *                      'excluded-value' - Item value was in the excluded list (doesnt mean this is necessarily an error)
         *                      'missing-children' - Missing required
         *                      'not-enough-children' - minChildrenPatterns
         *                      'too-many-children' - maxChildrenPatterns
         *                      'invalid-children' - One of the children did not pass the provided validation
         *                      'invalid-array-value' - Item value was an array, and either map type wasn't "object", or "map" was missing.
         *                      'ignored-in-group-because-null' - Item value was ignored because of 'ignoreInGroupIfNull' (doesnt mean this is necessarily an error)
         * @param array $params If set, can be passed to validation functions.
         *                      "_logNoMapError": <bool, default false - allows you to not ignore the "no-map" error>
         * @return array Parsed object, with invalid keys filtered
         */
        public static function baseItemParser(array $item, array $map, array &$errors = null, array $params = []): array {

            $finalRes = [];
            foreach($item as $resKey => $resValue){
                $mapObject = null;

                if(!empty($map['_patternKeys']))
                    foreach ($map['_patternKeys'] as $pattern => $newMapObject){
                        if(preg_match('/'.$pattern.'/',$resKey)){
                            $mapObject = $newMapObject;
                            if(empty($mapObject['resultName']))
                                $mapObject['resultName'] = $resKey;
                        }
                        if(!empty($mapObject))
                            break;
                    }

                if(empty($mapObject))
                    $mapObject = $map[$resKey]??null;

                if(empty($mapObject)){
                    if(is_array($errors) && ($params['_logNoMapError']??false))
                        $errors[$resKey] = 'no-map';
                    continue;
                }

                if(!empty($mapObject['exclude']) && in_array($resValue,$mapObject['exclude'])){
                    if(is_array($errors))
                        $errors[$resKey] = 'excluded-value';
                    continue;
                }

                if(gettype($mapObject) === 'string')
                    $mapObject = [
                        'resultName'=> $mapObject
                    ];
                elseif(empty($mapObject['resultName']))
                    $mapObject['resultName'] = $resKey;

                $mapObject['type'] = $mapObject['type'] ?? 'string';

                if(($mapObject['type'] === 'object'))
                    $resValue = \IOFrame\Util\PureUtilFunctions::is_json($resValue) ? json_decode($resValue,true) : [];
                elseif(($mapObject['type'] === 'json')  && ($resValue === null) )
                        $resValue = '{}';

                if( !( is_array($resValue) && ($mapObject['type'] !== 'json') ) && ($resValue !== null) ){
                    switch($mapObject['type']){
                        case 'function':
                            $resValue = $mapObject['function'](['item'=>$item,'index'=>$resKey,'rawValue'=>$resValue,'map'=>$map,'params'=>$params],$errors);
                            break;
                        case 'csv':
                            $resValue = $resValue? explode($mapObject['separator']??',',$resValue):[];
                            break;
                        case 'string':
                            $resValue = (string)$resValue;
                            break;
                        case 'int':
                            $resValue = (int)$resValue;
                            break;
                        case 'bool':
                            $resValue = (bool)$resValue;
                            break;
                        case 'double':
                            $resValue = (double)$resValue;
                            break;
                        case 'json':
                            if( !( \IOFrame\Util\PureUtilFunctions::is_json($resValue) || is_array($resValue) ) || ( empty($mapObject['validChildren']) && empty($mapObject['validChildrenPatterns']) ) )
                                break;
                            elseif(!is_array($resValue))
                                $resValue = json_decode($resValue,true);

                            $tempRes = [];

                            /* Required */
                            if(!empty($mapObject['requiredChildren']))
                                foreach($mapObject['requiredChildren'] as $requiredChild){
                                    if(!isset($resValue[$requiredChild])){
                                        if(is_array($errors))
                                            $errors[$resKey] = 'missing-children';
                                        break;
                                    }
                                }

                            if(!empty($mapObject['requiredChildrenPatterns']))
                                foreach($mapObject['requiredChildrenPatterns'] as $pattern){
                                    $found = false;
                                    foreach ($resValue as $key => $value){
                                        if(preg_match('/'.$pattern.'/',$key))
                                            $tempRes[$key] = $value;
                                        $found = true;
                                        break;
                                    }
                                    if($found){
                                        if(is_array($errors))
                                            $errors[$resKey] = 'missing-children';
                                        break;
                                    }
                                }

                            if(!empty($errors[$resKey]))
                                break;

                            /* Base array population */
                            if(!empty($mapObject['validChildren']))
                                foreach($mapObject['validChildren'] as $validChild){
                                    $tempRes[$validChild] = $resValue[$validChild] ?? null;
                                }

                            if(!empty($mapObject['validChildrenPatterns']))
                                foreach ($mapObject['validChildrenPatterns'] as $pattern){
                                    foreach ($resValue as $key => $value){
                                        if(preg_match('/'.$pattern.'/',$key))
                                            $tempRes[$key] = $value;
                                    }
                                }

                            /* Counts */
                            if( !empty($mapObject['minChildrenPatterns']) && (count($tempRes) < $mapObject['minChildrenPatterns']) ){
                                if(is_array($errors))
                                    $errors[$resKey] = 'not-enough-children';
                                break;
                            }
                            if( !empty($mapObject['maxChildrenPatterns']) && (count($tempRes) < $mapObject['maxChildrenPatterns']) ){
                                if(is_array($errors))
                                    $errors[$resKey] = 'too-many-children';
                                break;
                            }

                            /* Parsing */
                            if(!empty($mapObject['validChildrenParsing'])){
                                $newErrors = [];
                                $tempRes = self::baseItemParser($tempRes, $mapObject['validChildrenParsing'], $newErrors, $params);
                                if(!empty($newErrors)){
                                    $errors[$resKey] = $newErrors;
                                    break;
                                }
                            }
                            if(!empty($mapObject['validChildrenPatternsParsing'])){
                                $newErrors = [];
                                $tempRes = self::baseItemParser($tempRes, [ '_patternKeys' => $mapObject['validChildrenPatternsParsing'] ], $newErrors, $params);
                                if(!empty($newErrors)){
                                    $errors[$resKey] = $newErrors;
                                    break;
                                }
                            }

                            /* Validation */
                            if(!empty($mapObject['validChildrenValidation'])){
                                $validation = self::baseValidation($tempRes, $mapObject['validChildrenValidation'], $params);
                                if(!$validation['passed']){
                                    $errors[$resKey] = $validation['failed'];
                                    break;
                                }
                            }
                            /* TODO
                            if(!empty($mapObject['requiredChildrenPatternsParsing'])){
                                $validation = self::baseValidation($tempRes, $mapObject['requiredChildrenPatternsParsing'], array_merge($params,['_patternFilters'=>true]));
                                if(!$validation['passed']){
                                    $errors[$resKey] = $validation['failed'];
                                    break;
                                }
                            }*/

                            if(!empty($errors[$resKey]))
                                break;

                            $resValue = $tempRes;
                            break;
                    }
                }
                elseif (is_array($resValue)){
                    if(($mapObject['type'] !== 'object') || empty($mapObject['map'])){
                        if(is_array($errors))
                            $errors[$resKey] = 'invalid-array-value';
                        continue;
                    }
                    $resValue = v1APIManager::baseItemParser($resValue,$mapObject['map'],$params);
                }

                if(!empty($errors[$resKey]))
                    continue;

                if(!empty($mapObject['replaceMap']) && array_key_exists($resValue,$mapObject['replaceMap']))
                    $resValue = $mapObject['replaceMap'][$resValue];


                if(!empty($mapObject['keyFunction']))
                    $saveKey = $mapObject['keyFunction'](['item'=>$item,'index'=>$resKey,'parsedValue'=>$resValue,'map'=>$map,'params'=>$params]);
                else
                    $saveKey = $mapObject['resultName'];

                if(!empty($mapObject['groupBy'])){
                    if( ($mapObject['ignoreInGroupIfNull']??false) && ($item[$resKey] === null)){
                        if(is_array($errors))
                            $errors[$resKey] = 'ignored-in-group-because-null';
                        continue;
                    }

                    $groupByKey = gettype($mapObject['groupBy'] !== 'function')?
                        $mapObject['groupBy'] :
                        $mapObject['groupBy'](['item'=>$item,'index'=>$resKey,'parsedValue'=>$resValue,'map'=>$map,'params'=>$params]);

                    if(!is_array($groupByKey))
                        $groupByKey = [$groupByKey];

                    $target = &$finalRes;
                    foreach($groupByKey as $groupLevel){
                        if(empty($target[$groupLevel]))
                            $target[$groupLevel] = [];
                        $target = &$target[$groupLevel];
                    }
                    if( ($mapObject['type'] === 'json') && !( empty($mapObject['validChildren']) && empty($mapObject['validChildrenPatterns']) ) ){

                        if(!empty($mapObject['validChildren']))
                            foreach($mapObject['validChildren'] as $validChild)
                                $target[$validChild] = $resValue[$validChild];

                        if(!empty($mapObject['validChildrenPatterns']))
                            foreach($mapObject['validChildrenPatterns'] as $pattern)
                                foreach ($resValue as $key=>$value)
                                    if(preg_match('/'.$pattern.'/',$key))
                                        $target[$key] = $value;
                    }
                    else{
                        $target[$saveKey] = $resValue;
                    }
                }
                else{
                    if(
                        ($mapObject['type'] === 'json') &&
                        ($mapObject['expandChildren'] ?? true) &&
                        !( empty($mapObject['validChildren']) && empty($mapObject['validChildrenPatterns']) )
                    ){

                        if(!empty($mapObject['validChildren']))
                            foreach($mapObject['validChildren'] as $validChild)
                                $finalRes[$validChild] = $resValue[$validChild]??null;

                        if(!empty($mapObject['validChildrenPatterns']))
                            foreach($mapObject['validChildrenPatterns'] as $pattern)
                                foreach ($resValue as $key=>$value)
                                    if(preg_match('/'.$pattern.'/',$key))
                                        $finalRes[$key] = $value;
                    }
                    else{
                        $finalRes[$saveKey] = $resValue;
                    }
                }
            }
            return $finalRes;
        }

        /** Generates an array to overwrite extraToGet, disabling specific (or all) metadata when getting something
         * @param string[]|bool $disableExtraToGet Array of items to disable. If left empty, will disable ALL extraToGet.
         * */
        public static function generateDisableExtraToGet(mixed $disableExtraToGet = []): array {
            if(!is_array($disableExtraToGet) && empty($disableExtraToGet))
                return ['disableExtraToGet'=>false];
            elseif(!is_array($disableExtraToGet))
                return ['disableExtraToGet'=>true];
            else
                return ['disableExtraToGet'=>$disableExtraToGet];
        }


        /** Gets an array of keys (which could be empty), and the level of auth required, and returns the relevant user auth.
         * @param array $params of the form:
         *          int authRequired, defaults to $authTable['admin'] - auth required
         *          array[] keys, defaults to [] - item keys, each in array form (even for 1 key)
         *          string[] objectAuth, defaults to [] - required object auth actions (if empty, cannot satisfy $authTable['restricted'])
         *          string[] actionAuth, defaults to [] - required action auth actions (if empty, cannot satisfy admin auth without levelAuth)
         *          int levelAuth, defaults to 0 - required level to be considered an admin for this operation - defaults to 0 (super admin)
         *          Array defaultSettingsParams - IOFrame default settings params, initiated at core_init.php as $defaultSettingsParams
         *          SettingsHandler localSettings - IOFrame local settings params, initiated at core_init.php as $settings
         *          IOFrame/Handlers/AuthHandler AuthHandler, defaults to null - standard IOFrame auth handler
         *          IOFrame/Handlers/ObjectAuthHandler ObjectAuthHandler, defaults to null - standard IOFrame object auth handler
         *          string objectAuthCategory, defaults to null - object auth category
         * @returns array|bool -
         *          IF keys were passed - array of the form:
         *          [
         *              <string, item key> => <int, key index in keys array>
         *          ]
         *          where each key specified is a key that didn't pass the auth test, and index is self explanatory.
         *
         *          IF keys were NOT passed OR not logged in OR DB connection error- true or false, whether the user has auth or not.
         * @throws \Exception
         * @throws \Exception
         */
        function checkAuth(array $params): bool|array {
            $test = $params['test'] ?? false;

            $authTable = $params['authTable'] ?? [
                    'none'=>0,
                    'restricted'=>1,
                    'owner'=>2,
                    'admin'=>9999
                ];

            $authRequired = $params['authRequired'] ?? $authTable['admin'];
            $keys = $params['keys'] ?? [];
            $objectAuth = $params['objectAuth'] ?? [];
            $actionAuth = $params['actionAuth'] ?? [];
            $levelAuth = $params['levelAuth'] ?? 0;
            $objectAuthCategory = $params['objectAuthCategory'] ?? null;
            $defaultSettingsParams = $params['defaultSettingsParams'] ?? $this->defaultSettingsParams;
            $AuthHandler = $params['AuthHandler'] ?? $this->defaultSettingsParams['AuthHandler'] ??null;
            $ObjectAuthHandler = $params['ObjectAuthHandler'] ?? null;

            $AuthHandlerPassed = false;

            if(!isset($AuthHandler))
                $AuthHandler = new \IOFrame\Handlers\AuthHandler($this->settings,$defaultSettingsParams);

            //If we are not logged in, nothing left to do
            if(!$AuthHandler->isLoggedIn()){
                if($test)
                    echo 'Must be logged in to have any authentication!'.EOL;
                return $AuthHandlerPassed;
            }
            $userId = $AuthHandler->getDetail('ID');

            //All required keys of the objects to check
            $requiredKeys = [];
            //A map of input indexes
            $requiredKeyMap = [];

            //The "keys" array could be empty - but then, this part just wont matter
            foreach($keys as $index => $key){
                $requiredKeys[] = $key;
                $requiredKeyMap[$key] = $index;
            }

            //If we could do this with restricted auth, means there are specific keys
            if($authRequired <= $authTable['restricted'] && !empty($requiredKeys) && $objectAuthCategory && $objectAuth){
                if($test)
                    echo 'Testing user auth against object auth of '.$objectAuthCategory.' '.json_encode($requiredKeys).EOL;

                if(!isset($ObjectAuthHandler))
                    $ObjectAuthHandler = new \IOFrame\Handlers\ObjectAuthHandler($this->settings,$defaultSettingsParams);

                $userObjectAuth = $ObjectAuthHandler->userObjects(
                    $objectAuthCategory,
                    $userId,
                    [
                        'objects' => $requiredKeys,
                        'requiredActions' => $objectAuth,
                        'test'=>$test
                    ]
                );
                //Each auth we found, signify the user does have the required auth
                foreach($userObjectAuth as $pair){
                    $requiredKeyMap[$pair['Object_Auth_Object']] = -1;
                }

                //All required keys of the objects to check their owners
                $requiredKeys = [];

                //Unset all keys which were
                foreach($requiredKeyMap as $requiredKey => $requiredIndex){
                    if($requiredIndex != -1)
                        $requiredKeys[] = (int)$requiredKey;
                }
            }

            //This check can happen either if there were no keys to begin with, or there are still keys left which the user has no auth to get.
            //Note that this doesn't check $authRequired, as any auth is smaller or equal to admin auth.
            if( !empty($requiredKeys) || empty($requiredKeyMap) ){
                if($test)
                    echo 'Testing user auth level against '.$levelAuth.', then actions against '.implode(',',$actionAuth).EOL;
                $requiredLevelAuth = $AuthHandler->isAuthorized($levelAuth);

                if(!$requiredLevelAuth)
                    foreach($actionAuth as $possibleAuth){
                            $requiredLevelAuth = $requiredLevelAuth || $AuthHandler->hasAction($possibleAuth);
                    }

                if($requiredLevelAuth){
                    if(!empty($requiredKeyMap))
                        foreach($requiredKeyMap as $key => $index){
                            $requiredKeyMap[$key] = -1;
                        }
                    else
                        $AuthHandlerPassed = true;
                }
            }

            if(!empty($requiredKeyMap)){
                foreach($requiredKeyMap as $key => $index){
                    if($index === -1)
                        unset($requiredKeyMap[$key]);
                }
                return array_splice($requiredKeyMap,0);
            }
            else
                return $AuthHandlerPassed;

        }

        /** Checks whether the IP (by default) of whoever is calling this in the session is (by default) blacklisted.
         * @param array $params See IPHandler->checkIP for more parameters
         * @return array [
         *                  'ip'=>?string, user IP if passed check
         *                  'error'?string, error if didn't pass check
         *              ]
         * */
        function checkIP(array $params = []): array {
            $result = [
                'ip'=>null,
                'error'=>null
            ];
            if(!$this->initiateHandlerIfNotExist('IP')){
                $result['error'] = 'no-handler';
                return $result;
            }
            if($this->IPHandler->checkIP($params))
                $result['error'] = 'failed-check';
            else
                $result['ip'] = $this->IPHandler->directIP;
            return $result;
        }

        /** Checks whether an action (typically an API action) is rate limited.
         * Will simply skip checks if relevant identifiers are not set.
         * @param array $rateLimitingObject Object of the form:
         *                  ['rate'] - Object of the form:
         *                      'identifier' - <string, default $params['rateId'] - identifier of who is performing the action>
         *                      'category' - <int, action category>
         *                      'action' - <int, identifier of the action>
         *                      'limit' - <int, default 1 - once per how many seconds this can be performed>
         *                  ['userAction'] - Object of the form:
         *                       'identifier' - <int, default $params['userId'] - user identifier>
         *                       'action' - <int, action id>
         *                  ['userActions'] - object[], same as userAction but performs checks against multiple events
         *                  ['ipAction'] - Object of the form:
         *                       'ip' - <int, default $params['ip'] - specific IP>
         *                       'action' - <int, action id>
         *                  ['ipActions'] - object[], same as ipAction but performs checks against multiple events
         * @param array $params
         *               'rateId' - string, default null - regular rate limit identifier
         *               'userId' - int, default null - apply every check with this specific user ID
         *               'ip' - string|bool, default null - apply every check with this specific ip (bool for session IP)
         *               'checkActionParams' - object, default null, see RateLimiting->checkAction()
         *               'checkIPActionEventLimitParams' - object, default null, see RateLimiting->checkActionEventLimit()
         *               'checkUserActionEventLimitParams' - object, default null, see RateLimiting->checkActionEventLimit()
         * @return array [
         *                   'error' => ?string, whether there was an error,
         *                   'limit' => int, how long (in seconds) until the user/ip can perform the action
         *              ]
         * */
        function checkRateLimits(array $rateLimitingObject, array $params = []): array {
            $test = $params['test'] ?? false;
            $rateId = $params['rateId'] ?? null;
            $userId = $params['userId'] ?? null;
            $ip = $params['ip'] ?? null;
            $checkActionParams = $params['checkActionParams'] ?? [];
            $checkUserActionEventLimitParams = $params['checkUserActionEventLimitParams'] ?? [];
            $checkIPActionEventLimitParams = $params['checkIPActionEventLimitParams'] ?? [];

            if(!$this->initiateHandlerIfNotExist('RateLimit')){
                $result['error'] = 'no-handler';
                return $result;
            }

            $result = [
                'error'=>null,
                'limit'=>0
            ];

            if( isset($rateLimitingObject['userAction']) ){
                $rateLimitingObject['userActions'] = $rateLimitingObject['userActions'] ??[];
                $rateLimitingObject['userActions'][] = $rateLimitingObject['userAction'];
            }

            if( isset($rateLimitingObject['ipAction']) ){
                $rateLimitingObject['ipActions'] = $rateLimitingObject['ipActions'] ??[];
                $rateLimitingObject['ipActions'][] = $rateLimitingObject['ipAction'];
            }

            if(isset($rateLimitingObject['rate'])){
                $rate = $rateLimitingObject['rate'];
                $rate['identifier'] = $rate['identifier'] ?? $rateId;
                if(isset($rate['identifier'])){
                    if(!isset($rate['category']) || !isset($rate['action'])){
                        $result['error'] = 'no-rate-config';
                        return $result;
                    }
                    if(!is_integer($rate['category']) || !is_integer($rate['action'])){
                        $result['error'] = 'invalid-rate-config';
                        return $result;
                    }
                    $limit = $this->RateLimiting->checkAction(
                        $rate['category'],
                        $rate['identifier'],
                        $rate['action'],
                        $rate['limit']??1,
                        array_merge($checkActionParams,['test'=>$test])
                    );
                    if(gettype($limit) === 'integer'){
                        $result['error'] = 'limit-reached';
                        $result['limit'] = max(round($limit/1000),1);
                        return $result;
                    }
                }
            }

            $existingLimit = 0;
            foreach (['user','ip'] as $type){
                if(!empty($rateLimitingObject[$type.'Actions'])){
                    $actions = $rateLimitingObject[$type.'Actions'];
                    foreach ($actions as $action){
                        if(!is_array($action) || !isset($action['action']) || !is_integer($action['action']) ){
                            $result['error'] = 'invalid-'.$type.'-actions-config';
                            return $result;
                        }
                        if($type === 'user'){
                            $action['identifier'] = $action['identifier'] ?? $userId;
                            if(!isset($action['identifier']) || !is_integer($action['identifier']) )
                                continue;

                            $existingLimit = max(
                                $existingLimit,
                                $this->RateLimiting->checkActionEventLimit(
                                    1,
                                    $action['identifier'],
                                    $action['action'],
                                    array_merge($checkUserActionEventLimitParams,['test'=>$test])
                                )
                            );
                        }
                        else{
                            $action['ip'] = $action['ip'] ?? $ip;
                            if(!isset($action['ip']))
                                continue;
                            if(!is_string($action['ip']) ){
                                if(!$this->initiateHandlerIfNotExist('IP')){
                                    $result['error'] = 'no-ip-handler';
                                    return $result;
                                }
                                $action['ip'] = $this->IPHandler->directIP;
                            }
                            $existingLimit = max(
                                $existingLimit,
                                $this->RateLimiting->checkActionEventLimit(
                                    0,
                                    $action['ip'],
                                    $action['action'],
                                    array_merge($checkIPActionEventLimitParams,['test'=>$test])
                                )
                            );
                        }
                    }
                }
            }
            if($existingLimit > 0){
                $result['error'] = 'limit-reached';
                $result['limit'] = $existingLimit;
                return $result;
            }

            return $result;
        }

        /** Commits (an) action(s), based on config
         * Will simply skip actions if relevant identifiers are not set.
         * @param array $actionsObject
         * @param array $params
         *               'userId' - int, default null - apply every check with this specific user ID
         *               'ip' - string|bool, default null - apply every check with this specific (or session) ip
         *               'commitEventUserParams' - object, default null, see RateLimiting->commitEventUser()
         *               'commitEventIPParams' - object, default null, see RateLimiting->commitEventIP()
         * @return array [
         *                    'error' => ?string, whether there was an error,
         *                    'result' => bool, whether all actions were successfully committed
         *               ]
         * @throws \Exception
         */
        function commitActions(array $actionsObject, array $params = []): array {
            $test = $params['test'] ?? false;
            $userId = $params['userId'] ?? null;
            $ip = $params['ip'] ?? null;
            $commitEventUserParams = $params['commitEventUserParams'] ?? [];
            $commitEventIPParams = $params['commitEventIPParams'] ?? [];

            if(!$this->initiateHandlerIfNotExist('RateLimit')){
                $result['error'] = 'no-handler';
                return $result;
            }

            if( isset($actionsObject['userAction']) ){
                $actionsObject['userActions'] = $actionsObject['userActions'] ??[];
                $actionsObject['userActions'][] = $actionsObject['userAction'];
            }

            if( isset($actionsObject['ipAction']) ){
                $actionsObject['ipActions'] = $actionsObject['ipActions'] ??[];
                $actionsObject['ipActions'][] = $actionsObject['ipAction'];
            }

            $result = [
                'error'=>null,
                'result'=>false
            ];
            foreach (['user','ip'] as $type){
                if(!empty($actionsObject[$type.'Actions'])){
                    $actions = $actionsObject[$type.'Actions'];
                    foreach ($actions as $action){
                        if(!is_array($action) || !isset($action['action']) || !is_integer($action['action']) ){
                            $result['error'] = 'invalid-'.$type.'-actions-config';
                            return $result;
                        }
                        if($type === 'user'){
                            $action['identifier'] = $action['identifier'] ?? $userId;
                            if(!isset($action['identifier']) || !is_integer($action['identifier']) )
                                continue;

                            $result['result'] = $this->RateLimiting->commitEventUser(
                                    $action['action'],
                                    $action['identifier'],
                                    array_merge(
                                        $commitEventUserParams,
                                        [
                                            'test'=>$test,
                                            'susOnLimit'=>$action['susOnLimit']??true,
                                            'banOnLimit'=>$action['banOnLimit']??false,
                                            'lockOnLimit'=>$action['lockOnLimit']??false
                                        ]
                                    )
                                );
                            if(!$result['result']){
                                $result['error'] = 'failed-to-commit-user-event-'.$action['action'];
                                return $result;
                            }
                        }
                        else{
                            $action['ip'] = $action['ip'] ?? $ip;
                            if(!isset($action['ip']))
                                continue;
                            if(!is_string($action['ip']) ){
                                if(!$this->initiateHandlerIfNotExist('IP')){
                                    $result['error'] = 'no-ip-handler';
                                    return $result;
                                }
                                $action['ip'] = $this->IPHandler->directIP;
                            }
                            $result['result'] = $this->RateLimiting->commitEventIP(
                                $action['action'],
                                array_merge(['ip'=>$action['ip']],$commitEventIPParams,['test'=>$test,'markOnLimit'=>$action['markOnLimit']??false])
                            );
                            if(!$result['result']){
                                $result['error'] = 'failed-to-commit-ip-event-'.$action['action'];
                                return $result;
                            }
                        }
                    }
                }
            }

            return $result;
        }

    }

}
