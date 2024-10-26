<?php
namespace IOFrame\Handlers{
    define('IOFrameHandlersMenuHandler',true);

    /** The menu handler is meant to handle simple menus.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class MenuHandler extends \IOFrame\Generic\ObjectsHandler{


        /**
         * @var string The table name where the menu resides
         */
        protected string $tableName = 'MENUS';

        /**
         * @var string The table key column for the menu
         */
        protected string $tableKeyCol = 'Menu_ID';

        /**
         * @var string The table column storing the value of the menu
         */
        protected string $menuValueCol = 'Menu_Value';

        /**
         * @var string The table column storing the menu meta
         */
        protected string $menuMetaCol = 'Meta';

        /**
         * @var string The cache name for the menu
         */
        protected string $cacheName = 'menu_';

        /**
         * Basic construction function
         * @param SettingsHandler $settings local settings handler.
         * @param array $params Typical default settings array
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, $params = []){

            $this->validObjectTypes = ['menus'];
            $this->objectsDetails = [
                'menus' => [
                    'tableName' => 'MENUS',
                    'cacheName' => 'menu_',
                    'keyColumns' => ['Menu_ID'],
                    'safeStrColumns' => ['Menu_Value'],
                    'setColumns' => [
                        'Menu_ID' => [
                            'type' => 'string'
                        ],
                        'Title' => [
                            'type' => 'string',
                            'default' => null
                        ],
                        'Menu_Value' => [
                            'type' => 'string',
                            'jsonObject' => true,
                            'default' => null
                        ],
                        'Meta' => [
                            'type' => 'string',
                            'jsonObject' => true,
                            'default' => null
                        ]
                    ],
                    'moveColumns' => [
                    ],
                    'columnFilters' => [
                    ],
                    'extraToGet' => [
                        '#' => [
                            'key' => '#',
                            'type' => 'count'
                        ]
                    ],
                    'orderColumns' => ['Menu_ID']
                ]
            ];

            //By default, menu's should have a much higher priority than regular cached items.
            if(!isset($params['cacheTTL']))
                $params['cacheTTL'] = 24*3600;
            parent::__construct($settings,$params);
        }

        /** Get menu.
         * @param string $identifier - menu identifier, required
         * @param array $params
         *              'safeStr' - bool, whether item is stored in safestring by default.
         * @returns int|array JSON decoded object of the form:
         *     <identifier string, "identifier" of the menu item used for stuff like routing. Assumed to not contain "/"> : {
         *          ['title': <string, Title of the menu item>]
         *          ['meta': <associated array of meta information>]
         *          ['children': <array, objects of the same structure as this one>]
         *          ['order': comma separated list of children identifiers]
         *          //Potentially more stuff, depending on the extending class
         *      }
         * Or one of the following codes:
         *      -3 Menu not a valid json somehow
         *      -2 Menu not found for some reason
         *      -1 Database Error
         */
        function getMenu(string $identifier, array $params = []){
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];

            $result = $this->getFromCacheOrDB(
                [$identifier],
                $this->tableKeyCol,
                $this->tableName,
                $this->cacheName,
                [],
                $params
            )[$identifier];

            if(is_array($result)){
                if(!empty($result['Meta']) && \IOFrame\Util\PureUtilFunctions::is_json($result['Meta']))
                    $meta = json_decode($result['Meta'],true);
                else
                    $meta = [];
                if(!empty($result['Title']) && \IOFrame\Util\PureUtilFunctions::is_json($result['Title']))
                    $title = json_decode($result['Title'],true);
                else
                    $title = null;
                $result = $result[$this->menuValueCol];
                if(empty($result))
                    $result = [];
                else{
                    if($safeStr)
                        $result = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($result);
                    if(!\IOFrame\Util\PureUtilFunctions::is_json($result))
                        $result =  -3;
                    else
                        $result = json_decode($result, true);
                }
                $result['@'] = $meta;
                $result['@title'] = $title;
            }
            elseif($result === 1)
                $result = -2;

            return $result;
        }


        /** Sets (or unsets) a menu item.
         * @param string $identifier - menu identifier, required
         * @param array $inputs of the form:
         *          'address' - string, array of valid identifiers that represent parents. defaults to [] (menu root).
         *                      If a non-existent parent is referenced, will not add the item.
         *          ['identifier' - string, "identifier"  of the menu item used for stuff like routing - may simply be included in the address].
         *          ['delete' - bool, if true, deletes the item instead of modifying.]
         *          ['title': string, Title of the menu item]
         *          ['order' -  string, comma separated list of identifiers to signify item order.
         *          //Potentially more stuff, depending on the extending class
         * @param array $params of the form:
         *          'safeStr' - bool, default true. Whether to convert Meta to a safe string
         * @returns int Code of the form:
         *      -3 Menu not a valid json somehow
         *      -2 Menu not found for some reason
         *      -1 Database Error
         *       0 All good
         *       1 One of the parents not found
         *       2 Item with similar identifier already exists in address
         */
        function setMenuItem(string $identifier, array $inputs, array $params = []){
            return $this->setMenuItems($identifier,[$inputs],$params);
        }

        /** Sets (or unsets) multiple menu item.
         * @param string $identifier - menu identifier, required
         * @param array $inputs Array of input arrays in the same order as the inputs in setMenuItem, HOWEVER:
         *              'address' can also include parents created from inputs in the $inputs array - AS LONG AS THEY
         *                        CAME EARLIER that the child in the array.
         * @param array $params from setMenuItem
         * @returns int Code of the form:
         *      -3 Menu not a valid json somehow
         *      -2 Menu not found for some reason
         *      -1 Database Error
         *       0 All good
         *       1 One of the parents FOR ANY OF THE ITEMS not found
         */
        function setMenuItems(string $identifier, array $inputs, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];

            $existingMenu = $params['existing'] ?? $this->getMenu($identifier, array_merge($params, ['updateCache' => false]));

            if(!is_array($existingMenu))
                return $existingMenu;

            foreach($inputs as $index => $inputArray){
                $target = &$existingMenu;
                if(!empty($inputArray['address']))
                    foreach($inputArray['address'] as $addrIndex => $address){
                        if(isset($target['children'][$address]))
                            $target = &$target['children'][$address];
                        else{
                            if($verbose)
                                echo 'Input '.$index.' address #'.$addrIndex.': '.$address.' does not exist!'.EOL;
                            return 1;
                        }
                    }

                if(!empty($inputArray['delete'])){
                    if(!empty($inputArray['identifier']))
                        unset($target['children'][$inputArray['identifier']]);
                    else
                        unset($target);
                }
                else{

                    if(empty($target['children']))
                        $target['children'] = [];

                    //reserved for the irregular case where we want to update the root menu
                    if(!empty($inputArray['identifier']))
                        $target = &$target['children'][$inputArray['identifier']];

                    if(empty($target))
                        $target = [];
                    foreach($inputArray as $inputIndex => $input){
                        if(!in_array($inputIndex,['children','identifier','delete','address']))
                            $target[$inputIndex] = $input;
                    }
                }
            }
            $existingMenu = json_encode($existingMenu);
            if($safeStr)
                $existingMenu = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($existingMenu);

            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().$this->tableName,
                [$this->menuValueCol.' = "'.$existingMenu.'"'],
                [$this->tableKeyCol,$identifier,'='],
                $params
            );
            if($res){
                $res = 0;
                if(!$test && $useCache)
                    $this->RedisManager->call('del',[$this->cacheName.$identifier]);
                if($verbose)
                    echo 'Deleting cache of '.$this->cacheName.$identifier.EOL;
            }
            else{
                $this->logger->error('Could not update menu',['identifier'=>$identifier]);
                $res = -1;
            }
            return $res;
        }

        /** Moves one branch of the menu to a different root
         * @param string $identifier Identifier of branch
         * @param string $blockIdentifier Identifier of branch
         * @param array $sourceAddress Source address
         * @param array $targetAddress Target address
         * @param array $params
         *          'override' - bool, default false. Whether to override a block with similar address at target address.
         *          'updateOrder' - bool, default true. Will update target and source orders, if possible.
         *          'orderIndex' - int, if set, will insert the target into a specific index at the order. Otherwise,
         *                         if updateOrder is set, will insert it into the end.
         *          'safeStr' - bool, default true. Whether to convert Meta to a safe string
         * @return int|mixed
         */
        function moveMenuBranch(string $identifier, string $blockIdentifier, array $sourceAddress, array $targetAddress, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $override = $params['override'] ?? false;
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];
            $updateOrder = $params['updateOrder'] ?? true;
            $orderIndex = $params['orderIndex'] ?? -1;

            $sameAddress = (count($sourceAddress) === count($targetAddress) && count(array_diff($sourceAddress,$targetAddress)) === 0);
            if($sameAddress && !$updateOrder)
                return 0;

            $existingMenu = $params['existing'] ?? $this->getMenu($identifier, array_merge($params, ['updateCache' => false]));

            if(!is_array($existingMenu))
                return $existingMenu;

            $source = &$existingMenu;
            if(!empty($sourceAddress))
                foreach($sourceAddress as $address)
                    if(isset($source['children'][$address]))
                        $source = &$source['children'][$address];
                    else
                        return 1;

            $target = &$existingMenu;
            if(!empty($targetAddress))
                foreach($targetAddress as $address)
                    if(isset($target['children'][$address]))
                        $target = &$target['children'][$address];
                    else
                        return 2;

            if(empty($source['children'][$blockIdentifier]))
                return 3;

            if(!$sameAddress && !empty($target['children'][$blockIdentifier]) && !$override)
                return 4;
            else
                $replacing = !empty($target['children'][$blockIdentifier]);

            if(empty($target['children']))
                $target['children'] = [];

            if(!$sameAddress)
                $target['children'][$blockIdentifier] = $source['children'][$blockIdentifier];

            if($updateOrder){
                //Source
                if(!$sameAddress && isset($source['order'])){
                    $source['order'] = explode(',',$source['order']);
                    $index = array_search ($blockIdentifier, $source['order']);
                    if($index !== false)
                        array_splice($source['order'],$index,1);
                    $source['order'] = implode(',',$source['order']);
                }

                //Target
                if(!isset($target['order']))
                    $target['order'] = $blockIdentifier;
                else{

                    $target['order'] = explode(',',$target['order']);
                    $targetCount = count($target['order']);

                    if(!$replacing){
                        $orderIndex = ($orderIndex<0 || $orderIndex > $targetCount - 1 ) ? $targetCount : $orderIndex;
                        if($targetCount === $orderIndex)
                            $target['order'][] = $blockIdentifier;
                        else
                            array_splice($target['order'],$orderIndex,0,$blockIdentifier);
                    }
                    elseif($orderIndex > 0){
                        $existingIndex = array_search($blockIdentifier,$target['order']);
                        if($existingIndex !== $orderIndex){
                            if($existingIndex !== false){
                                array_splice($target['order'],$existingIndex,1);
                                $targetCount--;
                                if($existingIndex<$orderIndex)
                                    $orderIndex = max(0,$orderIndex-1);
                            }
                            if($targetCount === $orderIndex)
                                $target['order'][] = $blockIdentifier;
                            else
                                array_splice($target['order'],min($orderIndex,$targetCount - 1),0,$blockIdentifier);
                        }
                    }

                    $target['order'] = implode(',',$target['order']);
                }
            }

            if(!$sameAddress)
                unset($source['children'][$blockIdentifier]);

            $existingMenu = json_encode($existingMenu);
            if($safeStr)
                $existingMenu = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($existingMenu);

            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().$this->tableName,
                [$this->menuValueCol.' = "'.$existingMenu.'"'],
                [$this->tableKeyCol,$identifier,'='],
                $params
            );

            if($res){
                $res = 0;
                if(!$test && $useCache)
                    $this->RedisManager->call('del',[$this->cacheName.$identifier]);
                if($verbose)
                    echo 'Deleting cache of '.$this->cacheName.$identifier.EOL;
            }
            else{
                $this->logger->error('Could not update menu',['identifier'=>$identifier]);
                $res = -1;
            }

            return $res;
        }


    }

}