<?php

namespace IOFrame\Managers{
    define('IOFrameManagersFrontEndResourceTemplateManager',true);
    /**A tool to output FrontEndResources items into pages as actual  resource (JS, CSS) links
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class FrontEndResourceTemplateManager
    {
        /**
         * @var array The result of $FrontEndResources->getJS()
         */
        public array $JSResources = [];

        /**
         * @var array The result of $FrontEndResources->getCSS()
         */
        public array $CSSResources = [];

        /**
         * @var array Order in which the JS resources should be displayed'
         */
        public array $JSOrder = [];

        /**
         * @var array Order in which the CSS resources should be displayed'
         */
        public array $CSSOrder = [];

        /**
         * @var string The root of JS resources relative to server address - defaults to 'front/ioframme/js/'
         */
        public string $JSResourceRoot = 'front/ioframme/js/';

        /**
         * @var string The root of CSS resources relative to server address - defaults to 'front/ioframme/css/'
         */
        public string $CSSResourceRoot = 'front/ioframme/css/';

        /**
         * @var string, default '' - relative URI from this page to server root (relevant with local resources)
         */
        public string $dirToRoot = '';


        /** @param array $params optionally initiate $JSResources and $CSSResources with parameters SResources and CSSResources
         */
        function __construct(array $params = []){
            $validParams = ['JSResources','CSSResources','dirToRoot','JSResourceRoot','CSSResourceRoot','JSOrder','CSSOrder'];
            foreach($validParams as $param){
                if(isset($params[$param]))
                    $this->{$param} = $params[$param];
            }
        }

        /** Adds resources to the resource array
         *  @param string $type Name of the array (JSResources,CSSResources, etc)
         *  @param array $resources Resources to add
         */
        function addResourcesToArray(string $type, array $resources): void {
            $this->{$type} = array_merge($this->{$type},$resources);
        }

        /** Outputs resources of a specific type.
         *  @param string $type Resource type (JS and CSS for now)
         *  @param string[] $resources, default [] - if set, will output resources from this array, in the order of this array.
         *  @param array $params of the form:
         *              'appendLastChanged' - bool, default true - whether to append 'lastChanged' to the end of the
         *                                    resource address as "?changed=$lastChangedValue"
         *              'appendVersion' - bool, default false - whether to append 'version' to the end of the
         *                                    resource address as "?v=$version" (generally not used as lastChanged is modified automatically)
         *              'tags' - object[], a collection of options that allows setting tags (e.g "defer", "async") to all or individual resources.
         *                       Each item is of the form:
         *                       {
         *                          tag: <string - name of the tag to add to the element>,
         *                          value: <string, default null - potential value to be added>,
         *                          regex: <string|string[], default null - match only elements whose final address matches the regex expression (or any of multiple expressions)>
         *                       }
         *                       *Note that the tags ["src"] for scripts, and ["rel","href"] on CSS links will be overwritten
         *                      Example:
         *                       'tags' => [
         *                          [
         *                              'tag'=>'test',
         *                              'value'=>'1'
         *                          ],
         *                          [
         *                              'tag'=>'defer',
         *                              'regex'=>'js\/modules'
         *                          ],
         *                          [
         *                              'tag'=>'async',
         *                              'regex'=>['js\/mixins','js\/components.*Image',]
         *                          ]
         *                       ]
         */
        function printResources(string $type, array $resources = [], array $params = []): void {
            $appendLastChanged = !isset($params['appendLastChanged']) || $params['appendLastChanged'];
            $appendVersion = $params['appendVersion'] ?? false;
            $tags = $params['tags'] ?? [];

            switch($type){
                case 'JS':
                case 'CSS':
                    $resourceArray = $this->{$type.'Resources'};
                    $resourceRoot = $this->{$type.'ResourceRoot'};
                    $resourceOrder = $this->{$type.'Order'};
                    break;
                default:
                    return;
            }
            if(!count($resources)){
                if(!count($resourceOrder)){
                    foreach($resourceArray as $identifier => $arr){
                        if(is_array($arr))
                            $resources[] = $identifier;
                    }
                } else {
                    foreach ($resourceOrder as $identifier) {
                        $resources[] = $identifier;
                    }
                }
            }

            foreach($resources as $resourceIdentifier){
                if(empty($resourceArray[$resourceIdentifier]) || !is_array($resourceArray[$resourceIdentifier]))
                    continue;

                $resource = $resourceArray[$resourceIdentifier];

                $resourceAddress = $resource['local']? $this->dirToRoot.$resourceRoot .$resource['relativeAddress'] : $resource['address'];
                $elementTags = [];

                //Core Functionality
                if($appendLastChanged || $appendVersion){
                    $resourceAddress .= '?';
                }
                if($appendLastChanged && !empty($resource['lastChanged'])){
                    $resourceAddress .= 'changed='.$resource['lastChanged'].'&';
                }
                if($appendVersion && !empty($resource['version'])){
                    $resourceAddress .= 'v='.$resource['version'].'&';
                }
                if($appendLastChanged || $appendVersion){
                    $resourceAddress = substr($resourceAddress,0,strlen($resourceAddress)-1);
                }

                //Tags
                foreach ($tags as $tagParams){
                    if(isset($tagParams['regex'])){
                        if(gettype($tagParams['regex']) === 'string')
                            $tagParams['regex'] = [$tagParams['regex']];
                        $matchingRegex = false;
                        foreach ($tagParams['regex'] as $regExp){
                            if(preg_match('/'.$regExp.'/',$resourceAddress)){
                                $matchingRegex = true;
                                break;
                            }
                        }
                        if(!$matchingRegex)
                            continue;
                    }
                    $elementTags[] = $tagParams['tag'] . (isset($tagParams['value']) ? '="' . $tagParams['value'] . '"' : '');
                }

                if($type === 'JS')
                    echo '<script src="'.$resourceAddress.'" '.implode(' ',$elementTags).'></script>';
                else
                    echo '<link rel="stylesheet" href="' . $resourceAddress . '" '.implode(' ',$elementTags).'>';
            }
        }


    }

}
