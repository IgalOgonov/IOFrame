<?php
namespace IOFrame\Handlers{

    define('IOFrameHandlersLanguageObjectHandler',true);

    /** This handler manages everything related to the regular, and category tags.
     * @author Igal Ogonov <igal1333@hotmail.com>
     */

    class LanguageObjectHandler extends \IOFrame\Generic\ObjectsHandler
    {
        /** @param array $loadedObjects loaded language objects, as gotten from getLanguageObjects (associative array), without the '@'
         */
        protected array $loadedObjects = [];

        function __construct(\IOFrame\Handlers\SettingsHandler $settings, $params = []){

            $this->validObjectTypes = ['language-objects'];


            $this->objectsDetails = [
                'language-objects' => [
                    'tableName' => 'LANGUAGE_OBJECTS',
                    'joinOnGet' => [
                    ],
                    'columnsToGet' => [
                    ],
                    'extendTTL' => false,
                    'cacheName' => 'language_objects_',
                    'keyColumns' => ['Object_Name'],
                    'safeStrColumns' => ['Object'],
                    'setColumns' => [
                        'Object_Name' => [
                            'type' => 'string',
                            'required' => true
                        ],
                        'Object' => [
                            'type' => 'string',
                            'required' => true,
                            'jsonObject' => true
                        ]
                    ],
                    'moveColumns' => [
                        'Object_Name' => [
                            'type' => 'string'
                        ]
                    ],
                    'columnFilters' => [
                        'includeRegex' => [
                            'column' => 'Object_Name',
                            'filter' => 'RLIKE'
                        ],
                        'excludeRegex' => [
                            'column' => 'Object_Name',
                            'filter' => 'NOT RLIKE'
                        ],
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ]
                    ],
                    'orderColumns' => ['Object_Name']
                ]
            ];

            parent::__construct($settings,$params);
        }

        /** Gets one Language Object.
         *
         * @param string $name
         * @param array $params
         *
         * @return mixed
         * @throws \Exception
         */
        function getLanguageObject(string $name, array $params = []){
            return $this->getLanguageObjects([$name],$params)[$name];
        }

        /** Gets either multiple Language Objects, or all existing Language Objects.
         *
         * @param array $names Array of Language Object names. If it is [], will return all Language Objects up to the max query limit.
         * @param array $params Same as abstractObjectHandler::getItems()
         *              'load' - loads the result into this class, saving it for future use
         *
         * @returns array of the form:
         *
         * @throws \Exception
         * @throws \Exception
         */
        function getLanguageObjects(array $names = [], array $params = []): int|array {
            $load = $params['load']??true;
            $res = $this->getItems($names,'language-objects',$params);
            if(is_array($res) && $load)
                foreach ($res as $key=>$value){
                    if(($key === '@'))
                        continue;
                    elseif(($value === -1))
                        return -1;
                    $this->loadedObjects[$key] = $value;
                }
            return $res;
        }

        /** Sets a language object.
         *
         * @param string $name Object name
         * @param string $object Object (json encoded)
         * @param array $params Same as abstractObjectHandler::setItems()
         *
         * @returns array of the form:
         *              setObjects Code
         *
         * @throws \Exception
         * @throws \Exception
         */
        function setLanguageObject(string $name, string $object, array $params = []): array|bool|int|string|null {
            $tempInputs = [
                [
                    'Object_Name' => $name,
                    'Object' => $object
                ]
            ];
            return $this->setItems($tempInputs,'language-objects',$params);
        }

        /** Deletes a single LanguageObject
         *
         * @param string $name Name of the LanguageObject
         * @param array $params same as deleteLanguageObjects
         *
         * @returns  array of the form:
         */
        function deleteContentCategory(string $name, array $params = []): int {
            return $this->deleteLanguageObjects([$name],$params);
        }

        /** Deletes multiple LanguageObjects.
         *
         * @param array $names Array of LanguageObject names to delete.
         * @param array $params Same as abstractObjectHandler::deleteItems()
         *
         * @returns array of the form:
         *
         * @throws \Exception
         * @throws \Exception
         */
        function deleteLanguageObjects(array $names, array $params = []): int {
            $tempInputs = [];
            foreach ($names as $name){
                $tempInputs[] = [
                    'Object_Name' => $name
                ];
            }
            return $this->deleteItems($tempInputs,'language-objects',$params);
        }

        /** Returns specific language objects, in a specific language, or false if it doesn't exist.
         *
         * @param array $names Array of LanguageObject names to get.
         * @param array $params Same as abstractObjectHandler::deleteItems()
         *        language - string, default null - language code to get, if null gets the whole object with all its languages, and potentially other data
         *        loadMissing - bool, default true - will try to load all objects which aren't yet loaded
         *
         * @returns array Object of the form {
         *      <key> => <bool|array - false if object doesn't exist or specific language doesn't exist, full object if no language is set, specific language sub-object if it's set and exists>
         * }
         *
         */
        function getLoadedObjects(array $names,array $params = []): array {
            $language = $params['language']??null;
            $loadMissing = $params['loadMissing']??true;

            $missingNames = array_diff($names,array_keys($this->loadedObjects));
            if($loadMissing && !empty($missingNames))
                $this->getLanguageObjects($missingNames,['test'=>$params['test']??false, 'load'=>true]);

            $res = [];
            foreach ($names as $name){
                if(empty($this->loadedObjects[$name]) || !is_array($this->loadedObjects[$name]) || !\IOFrame\Util\PureUtilFunctions::is_json($this->loadedObjects[$name]['Object']))
                    $res[$name] =  false;
                else {
                    $obj = json_decode($this->loadedObjects[$name]['Object'],true);
                    $updated = $this->loadedObjects[$name]['Last_Updated'];
                    if($language)
                        $res[$name] = ['object'=>$this->_recursiveLanguageUnpack($language,$obj),'updated'=>$updated];
                    else
                        $res[$name] = ['object'=>$obj,'updated'=>$updated];
                }
            }
            return $res;
        }
        /* object {title:{eng:'test',de:'test2'},garbageObj:{trashData1:'data',trashData2:'data'},noRightLang:{eng:'test'},garbageData:'data'} with language 'de' will return {title:'test2'} */
        function _recursiveLanguageUnpack($language, $obj){
            $res = [];
            foreach ($obj as $key=>$value){
                if($key === $language)
                    return $value;
                else if(is_array($value)){
                    $temp = $this->_recursiveLanguageUnpack($language, $value);
                    if(!empty($temp))
                        $res[$key] = $temp;
                }
            }
            return $res;
        }


    }

}

