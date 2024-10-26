<?php
namespace IOFrame\Traits{
    define('IOFrameTraitsGetterSetter',true);
    /** Allows dynamically defining which private/protected properties can be read or written to.
     *  This is basically a more restrictive __get and __set wrapper, allowing for better guardrails, while maintaining
     *  similar level of QoL.
     *  Obviously, still wont be supported by IDE tools like error detection / highlighting, though.
     */
    trait GetterSetter{

        /** @var string[] Which properties can be read */
        protected array $_gettableProperties = [];

        /** @var string[] Which properties can be written to */
        protected array $_settableProperties = [];

        /** Add gettable properties
         * @param string[] $r Properties that can be read
         * @param string[] $w Properties that can be written to
         */
        function _addGetSet(array $r = null, array $w = null): void {
            $this->_gettableProperties += $r??[];
            $this->_settableProperties += $w??[];
        }

        /** Checks the defined properties, allowing read access */
        function __get($v){
            if(in_array($v,$this->_gettableProperties) && isset($this->$v))
                return $this->$v;
            else
                return null;
        }

        /** Checks the defined properties, allowing to set them */
        function __set($v,$newValue){
            if(in_array($v,$this->_settableProperties))
                $this->$v = $newValue;
        }
    }
}