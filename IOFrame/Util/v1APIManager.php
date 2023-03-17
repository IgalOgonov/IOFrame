<?php
namespace IOFrame\Util{
    use IOFrame;
    use IOFrame\Handlers\SettingsHandler;

    define('v1APIManager',true);
    if(!defined('abstractDBWithCache'))
        require __DIR__.'/../Handlers/abstractDBWithCache.php';

    /* A utility class meant to manage the v1 api.
     * As a reminder, the request type in this API is meaningless (GET/POST), and the API itself is based around actions, and 3 main stages - validation, authorization, and execution.
     * Thus, most functions here are static
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * */
    class v1APIManager extends IOFrame\abstractDBWithCache
    {

        /** @param SettingsHandler|null API Settings
         */
        public ?SettingsHandler $apiSettings;

        /** Standard constructor
         *
         * Constructs an instance of the class, getting the main settings file and an existing DB connection, or generating
         * such a connection itself.
         *
         * @param SettingsHandler $settings Local settings
         * @param SettingsHandler $apiSettings API Settings
         * @param array $params Default settings
         * */
        function __construct(SettingsHandler $settings, SettingsHandler $apiSettings = null, $params = [])
        {
            $this->apiSettings = $apiSettings ?? null;
            parent::__construct($settings, $params);
        }

        /** Validates inputs, based on a filters object.
         * @param array $inputs Inputs object, keys and values
         * @param array $filters Filters object of the form: {
         *                  <inputKey>:{
         *                      'type':<string, one of 'bool','string','int','string[]','int[]','json','function'>
         *                      'exceptions':<string[], in case of basic values, array of exceptions that would be considered valid regardless of validation>
         *                      'replaceExceptionWithNull':<bool, replaces exceptions with null>
         *                      'replaceException':<mixed, replaces exceptions with this value>
         *                      'replaceExceptionMap':<array, replaces each exception - represented by the array keys - with the value>
         *                      'default':<mixed, replaces input with default value if it is unset/null>
         *                      'required':<bool, default true if no defaults, false if defaults exist - whether the input is required or optional>
         *                      'ignoreNull':<bool, default false - if true, allows null as a valid value without trying to replace it with a default one>
         *                      'valid':<mixed, for base types or arrays, can be either an array of specific valid values, or for a string - a regex string (without the "/"'s).
         *                              for functions, uses an object of the form ['inputs'=>$inputs,'filters'=>$filters,'index'=>$input,'input'=>$inputs[$input],'filter'=>$filterArr,'params'=>$params]>
         *                      'min':<int, for integers - minimum value, for strings - minimum length. propagates to array elements>
         *                      'max':<int, for integers - maximum value, for strings - maximum length. propagates to array elements>
         *                      'keepJson':<bool, default false - if not explicitly set to true, will convert json strings to objects in the original input array>
         *                  }
         *              }
         * @param array|null $externalOutput optional External output array for the inputs
         * @param array $params
         *
         */
        static function baseValidation(array &$inputs, array $filters, array &$externalOutput = null, array $params = []){
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

                if(!isset($inputs[$input]) || $inputs[$input] === null){
                    /*If required, which defaults to true if no default, false if default is set*/
                    if( $filterArr['required'] ?? !isset($filterArr['default']) ){
                        $result['passed'] = false;
                        array_push($result['failed'],$input);
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
                            if(!empty($filterArr['valid']) && is_array($filterArr['valid']) && !in_array($inputs[$input],$filterArr['valid'])){
                                if($test)
                                    echo $input.' must be in '.json_encode($filterArr['valid']).EOL;
                                $result['passed'] = false;
                                array_push($result['failed'],$input);
                            }
                            elseif(!empty($filterArr['valid']) && !is_array($filterArr['valid']) && ($filterArr['type'] === 'string') && !preg_match('/'.$filterArr['valid'].'/',$inputs[$input])){
                                if($test)
                                    echo $input.' must match '.$filterArr['valid'].EOL;
                                $result['passed'] = false;
                                array_push($result['failed'],$input);
                            }
                            elseif(($filterArr['type'] === 'string') && !empty($filterArr['min']) && (strlen($inputs[$input]) < $filterArr['min']) ){
                                if($test)
                                    echo $input.' must be at least the length '.$filterArr['min'].EOL;
                                $result['passed'] = false;
                                array_push($result['failed'],$input);
                            }
                            elseif(($filterArr['type'] === 'string') && !empty($filterArr['max']) && (strlen($inputs[$input]) > $filterArr['max']) ){
                                if($test)
                                    echo $input.' must be at most the length'.$filterArr['max'].EOL;
                                $result['passed'] = false;
                                array_push($result['failed'],$input);
                            }
                            elseif(($filterArr['type'] === 'int') && !filter_var($inputs[$input],FILTER_VALIDATE_INT)){
                                if($test)
                                    echo $input.' must be a valid integer!'.EOL;
                                $result['passed'] = false;
                                array_push($result['failed'],$input);
                            }
                            elseif(($filterArr['type'] === 'int') && !empty($filterArr['min']) && ($inputs[$input] < $filterArr['min']) ){
                                if($test)
                                    echo $input.' must be at least '.$filterArr['min'].EOL;
                                $result['passed'] = false;
                                array_push($result['failed'],$input);
                            }
                            elseif(($filterArr['type'] === 'int') && !empty($filterArr['max']) && ($inputs[$input] > $filterArr['max']) ){
                                if($test)
                                    echo $input.' must be at most '.$filterArr['max'].EOL;
                                $result['passed'] = false;
                                array_push($result['failed'],$input);
                            }
                            break;
                        case 'string[]':
                        case 'int[]':
                        case 'json':
                            if(!is_array($inputs[$input])){
                                if(!\IOFrame\Util\is_json($inputs[$input])){
                                    if($test)
                                        echo $input.' must be a valid json!'.EOL;
                                    $result['passed'] = false;
                                    array_push($result['failed'],$input);
                                }
                                $inputs[$input] = json_decode($inputs[$input],true);
                            }
                            if(empty($filterArr['valid']) && ($filterArr['type'] === 'int[]')){
                                foreach ($inputs[$input] as $integer){
                                    if(!filter_var($integer,FILTER_VALIDATE_INT)){
                                        if($test)
                                            echo $input.' must be a valid integer array!'.EOL;
                                        $result['passed'] = false;
                                        array_push($result['failed'],$input);
                                    }
                                }
                            }
                            elseif(!empty($filterArr['valid']) && !is_array($filterArr['valid']) && ($filterArr['type'] !== 'int[]')){
                                foreach ($inputs[$input] as $string){
                                    if(!preg_match('/'.$filterArr['valid'].'/',$string)){
                                        if($test)
                                            echo $input.' must all match '.$filterArr['valid'].EOL;
                                        $result['passed'] = false;
                                        array_push($result['failed'],$input);
                                    }
                                }
                            }
                            elseif(!empty($filterArr['min'])){
                                foreach ($inputs[$input] as $item){
                                    $cond = ($filterArr['type'] === 'int[]') ? $item<$filterArr['min'] : strlen($item)<$filterArr['min'];
                                    if($cond){
                                        if($test)
                                            echo $input.' must all be at least '.$filterArr['min'].EOL;
                                        $result['passed'] = false;
                                        array_push($result['failed'],$input);
                                    }
                                }
                            }
                            elseif(!empty($filterArr['max'])){
                                foreach ($inputs[$input] as $item){
                                    $cond = ($filterArr['type'] === 'int[]') ? $item>$filterArr['max'] : strlen($item)>$filterArr['max'];
                                    if($cond){
                                        if($test)
                                            echo $input.' must all be at least '.$filterArr['max'].EOL;
                                        $result['passed'] = false;
                                        array_push($result['failed'],$input);
                                    }
                                }
                            }
                            elseif(!empty($filterArr['valid']) && is_array($filterArr['valid']) && (count(array_diff($inputs[$input],$filterArr['valid'])) > 0)){
                                if($test)
                                    echo $input.' must be in '.json_encode($filterArr['valid']).EOL;
                                $result['passed'] = false;
                                array_push($result['failed'],$input);
                            }
                            if(!empty($filterArr['keepJson']))
                                $inputs[$input] = json_encode($inputs[$input]);
                            break;
                        case 'function':
                            if(!$filterArr['valid'](['inputs'=>$inputs,'filters'=>$filters,'index'=>$input,'input'=>$inputs[$input],'filter'=>$filterArr,'params'=>$params])){
                                if($test)
                                    echo $input.' must pass the validation function'.EOL;
                                $result['passed'] = false;
                                array_push($result['failed'],$input);
                            }
                            break;
                    }
                }
                elseif($isException){
                    if($filterArr['replaceExceptionWithNull'])
                        $inputs[$input] = null;
                    elseif($filterArr['replaceException'])
                        $inputs[$input] = $filterArr['replaceException'];
                    elseif($filterArr['replaceExceptionMap'])
                        $inputs[$input] = $filterArr['replaceExceptionMap'][$inputs[$input]] ?? null;
                }

                if($externalOutput)
                    $externalOutput[$input] = $inputs[$input];
            }

            return $result;
        }

        /** Parses items (typically results of "get" actions) via maps
         * @param array $item A single item in the object
         * @param array $map Map to parse item, of the form:
         *              {
         *                  <string, column name> => string/object, string defaults to an object {resultName:"string"}, object is of the form:
         *                  {
         *                      "resultName":<string, what you want the key to be in the result>,
         *                      "type":<string, one of "object","function","json","string","bool","int","double">
         *                      "validChildren":<string[], in case of a "json type", you can quickly parse its valid children, and set nulls as default when they dont exist>
         *                      "expandChildren":<bool, default false - if true, will save children by their name, rather than under their specific key>
         *                      "function":<function, in case of "function" type, uses an object of the form ['item'=>$item,'index'=>$resKey,'rawValue'=>$resValue,'map'=>$map,'params'=>$params]>
         *                      "exclude":<array, excludes specific values)>
         *                      "replaceMap":<array, replaces specific values with different ones (runs after the initial parsing), of the form [ <final Value> => <replacement value> ]>
         *                      "map":<array, in case of "object" type, pass a new map for the recursive parsing>
         *                      "keyFunction": <function, can override resultName, uses an object of the form ['item'=>$item,'index'=>$resKey,'parsedValue'=>$resValue,'map'=>$map,'params'=>$params]>
         *                      "groupBy": <string|string[]|function, if passed, will group the value under the key (or recursively chain the keys) - objects that dont exist get created. Function same as keyFunction>
         *                  }
         *
         *              }
         * @param array $externalOutput where to output the parsed item to
         * @param array $params
         */
        static function baseItemParser(array $item, array $map, array &$externalOutput = null, array $params = []){

            $finalRes = [];
            foreach($item as $resKey => $resValue){

                if(empty($map[$resKey]))
                    continue;

                if(!empty($map[$resKey]['exclude']) && in_array($resValue,$map[$resKey]['exclude']))
                    continue;

                if(gettype($map[$resKey]) === 'string')
                    $map[$resKey] = [
                        'resultName'=> $map[$resKey]
                    ];

                $map[$resKey]['type'] = $map[$resKey]['type'] ?? 'string';

                if(($map[$resKey]['type'] === 'object'))
                    $resValue = \IOFrame\Util\is_json($resValue) ? json_decode($resValue,true) : [];
                elseif(($map[$resKey]['type'] === 'json')  && ($resValue === null) )
                        $resValue = '{}';

                if(!is_array($resValue) && ($resValue !== null) ){
                    switch($map[$resKey]['type']){
                        case 'function':
                            $resValue = $map[$resKey]['function'](['item'=>$item,'index'=>$resKey,'rawValue'=>$resValue,'map'=>$map,'params'=>$params]);
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
                            if(!\IOFrame\Util\is_json($resValue) || empty($map[$resKey]['validChildren']))
                                break;
                            else
                                $resValue = json_decode($resValue,true);
                            $tempRes = [];
                            foreach($map[$resKey]['validChildren'] as $validChild){
                                $tempRes[$validChild] = isset($resValue[$validChild])? $resValue[$validChild] : null;
                            }
                            $resValue = $tempRes;
                            break;
                    }
                }
                elseif (is_array($resValue)){
                    if(($map[$resKey]['type'] !== 'object') || empty($map[$resKey]['map']))
                        continue;
                    $trashArray = [];
                    $resValue = v1APIManager::baseItemParser($resValue,$map[$resKey]['map'],$trashArray,$params);
                }

                if(!empty($map[$resKey]['replaceMap']) && array_key_exists($resValue,$map[$resKey]['replaceMap']))
                    $resValue = $map[$resKey]['replaceMap'][$resValue];


                if(!empty($map[$resKey]['keyFunction']))
                    $saveKey = $map[$resKey]['keyFunction'](['item'=>$item,'index'=>$resKey,'parsedValue'=>$resValue,'map'=>$map,'params'=>$params]);
                else
                    $saveKey = $map[$resKey]['resultName'];

                if(!empty($map[$resKey]['groupBy'])){
                    $groupByKey = gettype($map[$resKey]['groupBy'] !== 'function')?
                        $map[$resKey]['groupBy'] :
                        $map[$resKey]['groupBy'](['item'=>$item,'index'=>$resKey,'parsedValue'=>$resValue,'map'=>$map,'params'=>$params]);

                    if(!is_array($groupByKey))
                        $groupByKey = [$groupByKey];

                    $targets = [$finalRes,$externalOutput];
                    foreach ($targets as $targetArray)
                        if($targetArray){
                            $tempTarget = &$targetArray;
                            foreach($groupByKey as $groupLevel){
                                if(empty($tempTarget[$groupLevel]))
                                    $tempTarget[$groupLevel] = [];
                                $tempTarget = &$tempTarget[$groupLevel];
                            }
                            if( ($map[$resKey]['type'] === 'json') && ($map[$resKey]['expandChildren'] ?? false) ){
                                foreach($map[$resKey]['validChildren'] as $validChild)
                                    $tempTarget[$validChild] = $tempRes[$validChild];
                            }
                            else{
                                $tempTarget[$saveKey] = $resValue;
                            }
                        }

                }
                else{
                    if( ($map[$resKey]['type'] === 'json') && ($map[$resKey]['expandChildren'] ?? false) ){
                        foreach($map[$resKey]['validChildren'] as $validChild){
                            if($externalOutput)
                                $externalOutput[$validChild] = $tempRes[$validChild]??null;
                            $finalRes[$validChild] = $tempRes[$validChild]??null;
                        }
                    }
                    else{
                        if($externalOutput)
                            $externalOutput[$saveKey] = $resValue;
                        $finalRes[$saveKey] = $resValue;
                    }
                }
            }
            return $finalRes;
        }


        /** Gets an array of keys (which could be empty), and the level of auth required, and returns the relevant user auth.
         * @param array $params of the form:
         *          int authRequired, defaults to $authTable['admin'] - auth required
         *          array[] keys, defaults to [] - item keys, each in array form (even for 1 key)
         *          string[] objectAuth, defaults to [] - required object auth actions (if empty, cannot satisfy $authTable['restricted'])
         *          string[] actionAuth, defaults to [] - required action auth actions (if empty, cannot satisfy admin auth without levelAuth)
         *          int levelAuth, defaults to 0 - required level to be considered an admin for this operation - defaults to 0 (super admin)
         *          Array defaultSettingsParams - IOFrame default settings params, initiated at coreInit.php as $defaultSettingsParams
         *          SettingsHandler localSettings - IOFrame local settings params, initiated at coreInit.php as $settings
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
         */
        static function checkAuth($params){
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
            $defaultSettingsParams = $params['defaultSettingsParams'] ?? [];
            $localSettings = $params['localSettings'] ?? null;
            $AuthHandler = $params['AuthHandler'] ?? null;
            $ObjectAuthHandler = $params['ObjectAuthHandler'] ?? null;
            $objectAuthCategory = $params['objectAuthCategory'] ?? null;

            $AuthHandlerPassed = false;

            if(!isset($AuthHandler))
                $AuthHandler = new \IOFrame\Handlers\AuthHandler($localSettings,$defaultSettingsParams);

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
                array_push($requiredKeys,$key);
                $requiredKeyMap[$key] = $index;
            }

            //If we could do this with restricted auth, means there are specific keys
            if($authRequired <= $authTable['restricted'] && !empty($requiredKeys) && $objectAuthCategory && $objectAuth){
                if($test)
                    echo 'Testing user auth against object auth of '.$objectAuthCategory.' '.json_encode($requiredKeys).EOL;

                if(!defined('ObjectAuthHandler'))
                    require __DIR__ . '/../../IOFrame/Handlers/ObjectAuthHandler.php';
                if(!isset($ObjectAuthHandler))
                    $ObjectAuthHandler = new \IOFrame\Handlers\ObjectAuthHandler($localSettings,$defaultSettingsParams);

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
                        array_push($requiredKeys,(int)$requiredKey);
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
                            $requiredLevelAuth = $AuthHandler->hasAction($possibleAuth);
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
                $requiredKeyMap = array_splice($requiredKeyMap,0);
                return $requiredKeyMap;
            }
            else
                return $AuthHandlerPassed;

        }

    }

}
