<?php

namespace IOFrame\Util\CLI{

    define('IOFrameUtilCLICommonManagerHelperFunctions',true);
    class CommonManagerHelperFunctions{

        /** Generates a generic description, seperated by EOL
         * @param string[] $descriptionElements Lines to add to desc
         * @returns string
         * */
        public static function generateGenericDesc(array $descriptionElements): string {
            $desc = '';
            foreach ($descriptionElements as $descElement)
                $desc .= $descElement.EOL;
            return substr($desc,0,strlen($desc) - strlen(EOL));
        }

        /** Generates a generic Notes description array, that can be used by generateGenericDesc
         * @param string[] $notes Notes
         * @returns array
         * */
        public static function generateDescNotes(string|array $notes): array {
            $descriptionElements = [];
            if(count($notes) === 1)
                $notes = $notes[0];
            if(is_array($notes)){
                $descriptionElements[] = '* Notes:';
                foreach ($notes as $i=>$note){
                    $descriptionElements[] = $i.'. '.$note;
                }
            }
            else{
                $descriptionElements[] = '* Note: '.$notes;
            }
            return $descriptionElements;
        }

        /** Generates a generic Examples description array, that can be used by generateGenericDesc
         * @param string|array $examples
         * @return array
         */
        public static function generateGenericDescExamples(string|array $examples): array {
            $descriptionElements = [];
            if(!is_array($examples))
                $examples = [$examples];
            $descriptionElements[] = 'Examples:';
            foreach ($examples as $example){
                $descriptionElements[] = $example;
            }
            return $descriptionElements;
        }

        /** Generates a common description of an options descriptions
         * @param string|string[] $initialDesc General options description
         * @param array $options Object of the form
         *                                      $option=>[
         *                                          'type' => <string, type of option>,
         *                                          'default' => <string, default value>,
         *                                          'required' => <bool, default empty('default')>,
         *                                          'desc' => <string, option description>
         *                                      ]
         * @param string|string[] $notes Appends this to the description, under "* Notes:"
         * @return string
         */
        public static function generateOptionsDesc(array|string $initialDesc, array $options, array|string $notes = []): string {
            $descriptionElements = is_array($initialDesc)? $initialDesc : [$initialDesc];
            foreach ($options as $option=>$info){
                $descriptionElements[] = (($info['required'] ?? empty($info['default'])) ? '[REQ] ' : '[OPT] ') .
                    '"'.$option.'" - '. ($info['type']??'mixed') .
                    (!empty($info['default']) ? ', default '.$info['default']: '') .
                    ($info['desc']??'No description');
            }
            if(!empty($notes)){
                $descriptionElements = array_merge($descriptionElements,self::generateDescNotes($notes));
            }
            return self::generateGenericDesc($descriptionElements);
        }

        /** Generates a common description of an options descriptions
         * @param string|string[] $initialDesc General options description
         * @param string|string[] $notes Appends this to the description, under "* Notes:"
         * @param array $examples Example commands you can run in the CLI as-is (from the cli folder)
         * @return string
         */
        public static function generateDescNotesExamples(array|string $initialDesc, array|string $notes = [], array $examples = []): string {
            $descriptionElements = is_array($initialDesc)? $initialDesc : [$initialDesc];
            if(!empty($notes)){
                $descriptionElements = array_merge($descriptionElements,self::generateDescNotes($notes));
            }
            if(!empty($examples)){
                $descriptionElements = array_merge($descriptionElements,self::generateGenericDescExamples($examples));
            }
            return self::generateGenericDesc($descriptionElements);
        }

        /** Used in the `func` of variables which should be parsed into an "options" object (regular key-value objects) that have default values
         * @param array|null $options See CLIManager
         * @param mixed $context See CLIManager
         * @param string $var Variable name to which this function belongs
         * @param array $defaultsMap Object of the form
         *                                      $option=><mixed, default null - default value>
         * @param bool $considerFiles If the CLI manager is initiated once from the CLI and once from files,
         *                            we want to return null the first time so the 2nd initiation is not prevented.
         * @return array|null
         */
        public static function genericOptionsLoadingFunctionHelper(?array $options, mixed $context, string $var, array $defaultsMap = [], bool $considerFiles = true): ?array {
            //First initiation without passing any properties
            if(($options === null) && !$context->initiated)
                return $considerFiles? null : [];
            //Second initiation, if we initiated in the first one
            elseif(isset($context->variables[$var]))
                return $context->variables[$var];
            //First initiation with properties, or second initiation when we didn't initiate in the first one
            else{
                if($options === null)
                    $options = [];

                if(!is_array($options)){
                    $options = \IOFrame\Util\PureUtilFunctions::is_json($options)? json_decode($options) : [];
                }

                foreach ($defaultsMap as $opt => $def)
                    $options[$opt] = $options[$opt]??$def;

                return $options;
            }
        }
        /** Default shouldLog decider function
         * @param mixed $context See CLIManager
         * @param string $optionsName Name of the options variable
         * @param string $optionName Name of a specific option to match
         * @param array $matchMap Array that maps the option value to a specific result
         * @param mixed $default Default result when nothing is matched
         * @return mixed
         */
        public static function defaultOptionsMatcher(mixed $context, string $optionsName, string $optionName, array $matchMap, mixed $default = null): mixed {
            $option = $context->variables[$optionsName][$optionName];
            return $matchMap[$option] ?? $default;
        }

        /** Default shouldLog decider function
         * @param array $result See CLIManager
         * @param mixed $context See CLIManager
         * @param array $params
         * @return bool
         */
        public static function defaultShouldLogMatcher(array $result,mixed $context, array $params = []): bool {
            $params['optionsName'] = $params['optionsName'] ?? 'loggingOptions';
            $params['optionName'] = $params['optionName'] ?? 'logOn';
            $params['matchMap'] = $params['matchMap'] ?? [
                'errors' => !empty($result['error']),
                'noErrors' => empty($result['error']),
                'any' => true
            ];
            $params['default'] =  $params['default'] ?? false;
            return self::defaultOptionsMatcher($context,$params['optionsName'], $params['optionName'],$params['matchMap'], $params['default']);
        }

        /** Default log level decider
         * @param array $result See CLIManager
         * @param mixed $context See CLIManager
         * @param array $params
         * @return bool
         */
        public static function defaultLogLevelCalculator(array $result,mixed $context, array $params = []): bool|string {
            $params['optionsName'] = $params['optionsName'] ?? 'loggingOptions';
            $params['optionName'] = $params['optionName'] ?? 'logLevel';
            return $context->variables[$params['optionsName']][$params['optionName']] ?? ($result['error'] ? 'error' : 'notice');
        }

        /** Default shouldLog decider function
         * @param array $result See CLIManager
         * @param mixed $context See CLIManager
         * @param array $params
         * @return bool
         */
        public static function defaultLogContextGenerator(array $result,mixed $context, array $params = []): bool {
            $params['optionsName'] = $params['optionsName'] ?? 'loggingOptions';
            $params['optionName'] = $params['optionName'] ?? 'logOn';
            $params['matchMap'] = $params['matchMap'] ?? [
                'any' => [ 'result'=>$result ],
                'errors' => [ 'error'=>$result['error'], 'errorDetails'=>$result['errorDetails'], 'functionErrors'=>$result['functionErrors'] ],
                'result' => [ 'result'=>$result['result'] ],
            ];
            $params['default'] =  $params['default'] ?? [];
            return self::defaultOptionsMatcher($context,$params['optionsName'], $params['optionName'],$params['matchMap'], $params['default']);
        }

        /** Default log message generator function
         * @param array $result See CLIManager
         * @param mixed $context See CLIManager
         * @param array $params
         * @return bool
         */
        public static function defaultLogMessageGenerator(array $result,mixed $context, array $params = []): bool {
            $params['optionsName'] = $params['optionsName'] ?? 'loggingOptions';
            $params['optionName'] = $params['optionName'] ?? 'logOn';
            $params['idOptionName'] = $params['idOptionName'] ?? 'logId';

            $options = $context->variables[$params['optionsName']];
            $prefix = !empty($options[$params['idOptionName']]) ? ' '.$options[$params['idOptionName']] : '';

            $params['errorMsg'] = ($params['errorMsg'] ?? 'Failed CLI job').$prefix;
            $params['noErrorMsg'] = ($params['noErrorMsg'] ?? 'Passed CLI job').$prefix;

            $params['matchMap'] = $params['matchMap'] ?? [
                'errors' => $params['errorMsg'],
                'noErrors' => $params['noErrorMsg']
            ];

            $params['default'] =  $params['default'] ?? ($result['error'] ? $params['errorMsg'] : $params['noErrorMsg']);

            return self::defaultOptionsMatcher($context,$params['optionsName'], $params['optionName'],$params['matchMap'], $params['default']);
        }
    }

}