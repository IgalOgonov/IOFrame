<?php
namespace IOFrame\Handlers{
    use IOFrame;
    use PHPMailer\PHPMailer\Exception;

    define('ContactHandler',true);
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';

    /** Handles contacts. Should be extended for more system-specific logic.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */

    class ContactHandler extends IOFrame\abstractDBWithCache
    {

        /** @var string Contact type
         * */
        protected $contactType = null;

        /** @var string Table name - defaults to 'CONTACTS'
         * */
        protected $tableName;

        /** @var string Cache prefix - defaults to lowercase $contactType
         * */
        protected $cachePrefix;

        /** @var string Cache name - defaults to $cachePrefix.$tableName.'_'
         * */
        protected $cacheName;

        function __construct(IOFrame\Handlers\SettingsHandler $localSettings, string $type = null, $params = []){
            parent::__construct($localSettings,$params);
            $this->contactType = $type;
            $this->tableName = isset($params['tableName'])? $params['tableName'] : 'CONTACTS';
            $this->cachePrefix = isset($params['cachePrefix'])? $params['cachePrefix'] : '';
            $this->cacheName = isset($params['cacheName'])? $params['cacheName'] : strtolower($this->tableName).'_'.$this->cachePrefix;
        }

        /** Changes current contact type
         * @param string $newType New contact type
         */
        function setContactType(string $newType = null){
            $this->contactType = $newType;
        }

        /** Gets all contact types
         *
         * @param string $identifier Identifier
         * @param array $params
         *
         * @returns int code:
         *             -1 - DB connection failed
         *          OR
         *              Array of all available types
         *
         */
        function getContactTypes(array $params = []){
            $prefix = $this->SQLHandler->getSQLPrefix();
            $tableQuery = $prefix.$this->tableName;
            $res = $this->SQLHandler->selectFromTable(
                $tableQuery,
                [],
                ['DISTINCT Contact_Type'],
                $params
            );
            $tempRes = [];
            if(is_array($res) && count($res) > 0){
                foreach($res as $resultArray){
                    array_push($tempRes,$resultArray['Contact_Type']);
                }
            }

            return $tempRes;
        }

        /** Gets one contact.
         *
         * @param string $identifier Identifier
         * @param array $params
         *
         * @returns Array of the DB object, or the codes:
         *             -1 - DB connection failed
         *              1 - Contact does not exist
         */
        function getContact(string $identifier,  array $params = []){
            $contactType = isset($params['contactType'])? $params['contactType'] : $this->contactType;
            return $contactType ?
                $this->getContacts([$identifier], $params)[$contactType.'/'.$identifier] :
                $this->getContacts([$identifier], $params)[$identifier]
                ;
        }

        /** Gets either multiple contacts, or all existing contacts.
         *
         * @param array $identifiers Array of contact names. If it is [], will return all contacts up to the max query limit.
         *
         * @param array $params getFromCacheOrDB() params, as well as:
         *          'contactType' => String, defaults to current contact type - overrides current contact type if provided
         *          'firstNameLike' => String, default null - returns results where first name  matches a regex.
         *          'emailLike' => String, email, default null - returns results where email matches a regex.
         *          'countryLike' => String, default null - returns results where country matches a regex.
         *          'cityLike' => String, default null - returns results where city matches a regex.
         *          'companyNameLike' => String, Unix timestamp, default null - returns results where company name matches a regex.
         *          'createdBefore' => String, Unix timestamp, default null - only returns results created before this date.
         *          'createdAfter' => String, Unix timestamp, default null - only returns results created after this date.
         *          'changedBefore' => String, Unix timestamp, default null - only returns results changed before this date.
         *          'changedAfter' => String, Unix timestamp, default null - only returns results changed after this date.
         *          'includeRegex' => String, default null - only includes identifiers containing this regex.
         *          'excludeRegex' => String, default null - only includes identifiers excluding this regex.
         *          'extraDBFilters'    - array, default [] - Do you want even more complex filters than the ones provided?
         *                                This array will be merged with $extraDBConditions before the query, and passed
         *                                to getFromCacheOrDB() as the 'extraConditions' param.
         *                                Each condition needs to be a valid PHPQueryBuilder array.
         *          'extraCacheFilters' - array, default [] - Same as extraDBFilters but merged with $extraCacheConditions
         *                                and passed to getFromCacheOrDB() as 'columnConditions'.
         *          ------ Using the parameters bellow disables caching ------
         *          'fullNameLike' => String, default null - returns results where first name together with last name matche a regex.
         *          'companyNameIDLike' => String, Unix timestamp, default null - returns results where company name together with company ID matche a regex.
         *          'orderBy'            - string, defaults to null. Possible values include 'Created' 'Last_Changed',
         *                                'Local' and 'Address'(default)
         *          'orderType'          - bool, defaults to null.  0 for 'ASC', 1 for 'DESC'
         *          'limit' => typical SQL parameter
         *          'offset' => typical SQL parameter
         *
         * @returns Array of the form:
         *          [
         *              <identifier*> => Array|Code
         *          ] where:
         *              The array is the DB columns array
         *              OR
         *              The code is one of the following:
         *             -1 - DB connection failed
         *              1 - Contact does not exist
         *
         */
        function getContacts(array $identifiers = [], array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $extraDBFilters = isset($params['extraDBFilters'])? $params['extraDBFilters'] : [];
            $extraCacheFilters = isset($params['extraCacheFilters'])? $params['extraCacheFilters'] : [];
            $firstNameLike = isset($params['firstNameLike'])? $params['firstNameLike'] : null;
            $fullNameLike = isset($params['fullNameLike'])? $params['fullNameLike'] : null;
            $emailLike = isset($params['emailLike'])? $params['emailLike'] : null;
            $countryLike = isset($params['countryLike'])? $params['countryLike'] : null;
            $cityLike = isset($params['cityLike'])? $params['cityLike'] : null;
            $companyNameLike = isset($params['companyNameLike'])? $params['companyNameLike'] : null;
            $companyNameIDLike = isset($params['companyNameIDLike'])? $params['companyNameIDLike'] : null;
            $createdAfter = isset($params['createdAfter'])? $params['createdAfter'] : null;
            $createdBefore = isset($params['createdBefore'])? $params['createdBefore'] : null;
            $changedAfter = isset($params['changedAfter'])? $params['changedAfter'] : null;
            $changedBefore = isset($params['changedBefore'])? $params['changedBefore'] : null;
            $includeRegex = isset($params['includeRegex'])? $params['includeRegex'] : null;
            $excludeRegex = isset($params['excludeRegex'])? $params['excludeRegex'] : null;
            $orderBy = isset($params['orderBy'])? $params['orderBy'] : null;
            $orderType = isset($params['orderType'])? $params['orderType'] : null;
            $limit = isset($params['limit'])? $params['limit'] : null;
            $offset = isset($params['offset'])? $params['offset'] : null;
            $contactType = isset($params['contactType'])? $params['contactType'] : $this->contactType;

            $prefix = $this->SQLHandler->getSQLPrefix();
            $retrieveParams = $params;
            $extraDBConditions = [];
            $extraCacheConditions = [];
            $keyDelimiter = '/';
            $colPrefix = $this->SQLHandler->getSQLPrefix().$this->tableName.'.';
            $columns = ($contactType) ? ['Contact_Type','Identifier'] : ['Identifier'];

            //If we are using any of this functionality, we cannot use the cache
            if($offset || $limit ||  $orderBy || $orderType || $fullNameLike || $companyNameIDLike){
                $retrieveParams['useCache'] = false;
                $retrieveParams['orderBy'] = $orderBy? $orderBy : null;
                $retrieveParams['orderType'] = $orderType? $orderType : 0;
                $retrieveParams['limit'] =  $limit? $limit : null;
                $retrieveParams['offset'] =  $offset? $offset : null;
            }

            //Create all the conditions for the db/cache
            if($contactType){
                array_push($extraDBConditions,[$colPrefix.'Contact_Type',[$contactType,'STRING'],'=']);
            }

            if($firstNameLike!== null){
                array_push($extraCacheConditions,['First_Name',$firstNameLike,'RLIKE']);
                array_push($extraDBConditions,[$colPrefix.'First_Name',[$firstNameLike,'STRING'],'RLIKE']);
            }

            if($fullNameLike!== null){
                array_push(
                    $extraDBConditions,
                    ['CONCAT('.$colPrefix.'First_Name," ",'.$colPrefix.'Last_Name)',[$fullNameLike,'STRING'],'RLIKE']
                );
            }

            if($emailLike!== null){
                array_push($extraCacheConditions,['Email',$emailLike,'RLIKE']);
                array_push($extraDBConditions,[$colPrefix.'Email',[$emailLike,'STRING'],'RLIKE']);
            }

            if($countryLike!== null){
                array_push($extraCacheConditions,['Country',$countryLike,'RLIKE']);
                array_push($extraDBConditions,[$colPrefix.'Country',[$countryLike,'STRING'],'RLIKE']);
            }

            if($cityLike!== null){
                array_push($extraCacheConditions,['City',$cityLike,'RLIKE']);
                array_push($extraDBConditions,[$colPrefix.'City',[$cityLike,'STRING'],'RLIKE']);
            }

            if($companyNameLike!== null){
                array_push($extraCacheConditions,['Company_Name',$companyNameLike,'RLIKE']);
                array_push($extraDBConditions,[$colPrefix.'Company_Name',[$companyNameLike,'STRING'],'RLIKE']);
            }

            if($companyNameIDLike!== null){
                array_push(
                    $extraDBConditions,
                    ['CONCAT('.$colPrefix.'Company_Name," ",'.$colPrefix.'Company_ID)',[$companyNameIDLike,'STRING'],'RLIKE']
                );
            }

            if($createdAfter!== null){
                $cond = [$colPrefix.'Created',$createdAfter,'>'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($createdBefore!== null){
                $cond = [$colPrefix.'Created',$createdBefore,'<'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($changedAfter!== null){
                $cond = [$colPrefix.'Last_Updated',$changedAfter,'>'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($changedBefore!== null){
                $cond = [$colPrefix.'Last_Updated',$changedBefore,'<'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($includeRegex!== null){
                array_push($extraCacheConditions,['Identifier',$includeRegex,'RLIKE']);
                array_push($extraDBConditions,[$colPrefix.'Identifier',[$includeRegex,'STRING'],'RLIKE']);
            }

            if($excludeRegex!== null){
                array_push($extraCacheConditions,['Identifier',$excludeRegex,'NOT RLIKE']);
                array_push($extraDBConditions,[$colPrefix.'Identifier',[$excludeRegex,'STRING'],'NOT RLIKE']);
            }

            $extraDBConditions = array_merge($extraDBConditions,$extraDBFilters);
            $extraCacheConditions = array_merge($extraCacheConditions,$extraCacheFilters);

            if($extraCacheConditions!=[]){
                array_push($extraCacheConditions,'AND');
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }
            if($extraDBConditions!=[]){
                array_push($extraDBConditions,'AND');
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            if($identifiers == []){
                $results = [];

                $tableQuery = $prefix.$this->tableName;

                $res = $this->SQLHandler->selectFromTable(
                    $tableQuery,
                    $extraDBConditions,
                    [],
                    $retrieveParams
                );
                $count = $this->SQLHandler->selectFromTable(
                    $tableQuery,
                    $extraDBConditions,
                    ['COUNT(*)'],
                    array_merge($retrieveParams,['limit'=>0])
                );
                if(is_array($res)){
                    $resCount = $res ? count($res[0]) : 0;
                    foreach($res as $resultArray){
                        for($i = 0; $i<$resCount/2; $i++)
                            unset($resultArray[$i]);
                        if($contactType)
                            $results[$resultArray['Contact_Type'].$keyDelimiter.$resultArray['Identifier']] = $resultArray;
                        else
                            $results[$resultArray['Identifier']] = $resultArray;
                    }
                    $results['@'] = array('#' => $count[0][0]);
                }
                return (is_array($res))? $results : [];
            }
            else{
                $retrieveParams['keyDelimiter'] = $keyDelimiter;
                if(!$contactType)
                    $retrieveParams['useCache'] = false;
                foreach($identifiers as $index => $identifier){
                    $identifiers[$index] = $contactType ? [$contactType,$identifier] : [$identifier] ;
                }
                $results = $this->getFromCacheOrDB(
                    $identifiers,
                    $columns,
                    $this->tableName,
                    $this->cacheName,
                    [],
                    $retrieveParams
                );

                return $results;
            }
        }

        /** Creates or updates a single contact
         *
         * @param string $identifier Name of the contact,
         * @param Array $inputs Assoc array of inputs, of the form:
         *                      'firstName' => string, default null, max length 64
         *                      'lastName' => string, default null, max length 64
         *                      'email' => string, default null, max length 256
         *                      'phone' => string, default null, max length 32
         *                      'fax' => string, default null, max length 32
         *                      'contactInfo' => string, default null - Should be a JSON encoded object
         *                      'country' => string, default null, max length 64
         *                      'state' => string, default null, max length 64
         *                      'city' => string, default null, max length 64
         *                      'street' => string, default null, max length 64
         *                      'zipCode' => string, default null, max length 14
         *                      'address' => string, default null - Should be a JSON encoded object
         *                      'companyName' => string, default null, max length 256
         *                      'companyID' => string, default null, max length 64
         *                      'extraInfo' => string, default null - Should be a JSON encoded object
         * @param array $params same as setContacts
         *
         * @returns  Int explained in setContacts
         */
        function setContact(string $identifier, array $inputs, array $params = []){
            return $this->setContacts([[$identifier,$inputs]],$params)[$identifier];
        }

        /** Creates or updates multiple contacts.
         *
         * @param array $inputs Array of input arrays of the inputs from setContact: [$identifier, $inputs]
         * @param array $params:
         *      'update' => bool, default false - Whether we are only allowed to update existing contacts
         *      'override' => bool, default false -  Whether we are allowed to overwrite existing contacts
         *      'existing' => Assoc array: ['<contact name>' => <existing info as would be returned by getContact>]
         *      'possibleTypes' => string[], default [$this->contactType] - Possible contact types (for cache deletion when multiple possible)
         *      'setType' => string, default null - When you want to set a specific type, but dont mind what type you get
         *
         * @returns Array of the form:
         *          <identifier> => <code>
         *          Where each identifier is the contact identifier, and possible codes are:
         *         -1 - failed to connect to db
         *          0 - success
         *          1 - contact does not exist (and update is true)
         *          2 - contact exists (and override is false)
         *          3 - trying to update the contact with no new info.
         *          4 - trying to create a new contact with missing inputs
         *
         * @throws \Exception If you try to set contacts without having a type.
         */
        function setContacts(array $inputs, array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $update = isset($params['update'])? $params['update'] : false;
            $contactType = isset($params['contactType'])? $params['contactType'] : $this->contactType;
            $possibleTypes = isset($params['possibleTypes'])? $params['possibleTypes'] : [$this->contactType];
            $setType = isset($params['setType'])? $params['setType'] : $contactType;
            if(!$contactType && !($setType || $update))
                throw new \Exception('Cannot set contacts without a specific type!');
            //If we are updating, then by default we allow overwriting
            if(!$update)
                $override = isset($params['override'])? $params['override'] : false;
            else
                $override = true;

            $keyDelimiter = '/';
            $identifiers = [];
            $identifierIndexMap = [];
            $results = [];
            $contactsToSet = [];
            $cacheContactsToUnset = [];
            $currentTime = (string)time();

            //Create the usual structures, index/identifier maps, initiate results, etc
            foreach($inputs as $index => $inputArray){
                $identifier = $inputArray[0];
                //Add the identifier to the array
                array_push($identifiers, $identifier);
                $results[$identifier] = -1;
                $identifierIndexMap[$identifier] = $index;
            }

            //Get existing contacts
            if(isset($params['existing']))
                $existing = $params['existing'];
            else
                $existing = $this->getContacts($identifiers, array_merge($params,['updateCache'=>false]));

            //Parse all existing contacts.
            foreach($inputs as $index => $inputArray){
                $identifier = $inputArray[0];
                $dbIdentifier = $contactType ? implode($keyDelimiter,[$contactType,$inputArray[0]]) : $inputArray[0];
                $contactInputs = isset($inputArray[1])? $inputArray[1] : [];
                //Initiate each input
                $contactInputs['firstName'] = isset($contactInputs['firstName'])? $contactInputs['firstName'] : null;
                $contactInputs['lastName'] = isset($contactInputs['lastName'])? $contactInputs['lastName'] : null;
                $contactInputs['email'] = isset($contactInputs['email'])? $contactInputs['email'] : null;
                $contactInputs['phone'] = isset($contactInputs['phone'])? $contactInputs['phone'] : null;
                $contactInputs['fax'] = isset($contactInputs['fax'])? $contactInputs['fax'] : null;
                $contactInputs['contactInfo'] = isset($contactInputs['contactInfo'])? $contactInputs['contactInfo'] : null;
                $contactInputs['country'] = isset($contactInputs['country'])? $contactInputs['country'] : null;
                $contactInputs['state'] = isset($contactInputs['state'])? $contactInputs['state'] : null;
                $contactInputs['city'] = isset($contactInputs['city'])? $contactInputs['city'] : null;
                $contactInputs['street'] = isset($contactInputs['street'])? $contactInputs['street'] : null;
                $contactInputs['zipCode'] = isset($contactInputs['zipCode'])? $contactInputs['zipCode'] : null;
                $contactInputs['address'] = isset($contactInputs['address'])? $contactInputs['address'] : null;
                $contactInputs['companyName'] = isset($contactInputs['companyName'])? $contactInputs['companyName'] : null;
                $contactInputs['companyID'] = isset($contactInputs['companyID'])? $contactInputs['companyID'] : null;
                $contactInputs['extraInfo'] = isset($contactInputs['extraInfo'])? $contactInputs['extraInfo'] : null;

                //If a single contact failed to be gotten from the db, it's safe to bet DB connection failed in general.
                if($existing[$dbIdentifier] === -1)
                    return $results;

                //If we are creating a new contact with update true, or updating one with override false, return that result
                if($existing[$dbIdentifier] === 1 && $update){
                    $results[$identifier] = 1;
                    unset($identifiers[array_search($identifier,$identifiers)]);
                }
                elseif(gettype($existing[$dbIdentifier]) === 'array' && !$override){
                    $results[$identifier] = 2;
                    unset($identifiers[array_search($identifier,$identifiers)]);
                }
                else{
                    $contactToSet = [];
                    $contactToSet['identifier'] = $identifier;
                    $contactToSet['updated'] = $currentTime;
                    //If we are updating, get all missing inputs from the existing result
                    if(gettype($existing[$dbIdentifier]) === 'array'){
                        $contactToSet['created'] = $existing[$dbIdentifier]['Created_On'];

                        //Get existing or set new
                        $arr = [['firstName','First_Name'],['lastName','Last_Name'],['email','Email'],['phone','Phone'],
                            ['fax','Fax'],['country','Country'],['state','State'],['city','City'],['street','Street'],
                            ['zipCode','Zip_Code'],['companyName','Company_Name'],['companyID','Company_ID']];
                        foreach($arr as $attrPair){
                            $inputName = $attrPair[0];
                            $dbName = $attrPair[1];
                            if($contactInputs[$inputName] === '@')
                                $contactToSet[$inputName] = null;
                            elseif($contactInputs[$inputName]!==null)
                                $contactToSet[$inputName] = $contactInputs[$inputName];
                            else
                                $contactToSet[$inputName] = $existing[$dbIdentifier][$dbName];
                        }
                        //contactInfo, address and extraInfo get special treatment
                        $arr = [['contactInfo','Contact_Info'],['address','Address'],['extraInfo','Extra_Info']];
                        foreach($arr as $attrPair){
                            $inputName = $attrPair[0];
                            $dbName = $attrPair[1];
                            if($contactInputs[$attrPair[0]]!==null){
                                if($contactInputs[$inputName] === '@')
                                    $contactToSet[$inputName] = null;
                                else{
                                    $inputJSON = json_decode($inputs[$index][1][$inputName],true);
                                    $existingJSON = json_decode($existing[$dbIdentifier][$dbName],true);
                                    if($inputJSON === null)
                                        $inputJSON = [];
                                    if($existingJSON === null)
                                        $existingJSON = [];
                                    $contactToSet[$inputName] =
                                        json_encode(IOFrame\Util\array_merge_recursive_distinct($existingJSON,$inputJSON,['deleteOnNull'=>true]));
                                    if($contactToSet[$inputName] == '[]')
                                        $contactToSet[$inputName] = null;
                                }
                            }
                            else
                                $contactToSet[$inputName] = ($existing[$dbIdentifier][$dbName] == '')? null : $existing[$dbIdentifier][$dbName];
                        }
                        //Type also gets special treatment
                        $contactToSet['type'] = $setType? $setType : $existing[$dbIdentifier]['Contact_Type'];

                        //This happens if no new info is given.
                        if($contactToSet === []){
                            $results[$identifier] = 3;
                            unset($identifiers[array_search($identifier,$identifiers)]);
                        }

                    }
                    //If are creating a new contact,
                    else{
                        $contactToSet['type'] = $setType;
                        $contactToSet['created'] = $currentTime;
                        $arr = ['firstName','lastName','email','phone','fax','country','state','city','street','zipCode',
                                'companyName','companyID','contactInfo','address','extraInfo'];
                        $anyNewAttributes = false;
                        foreach($arr as $attr){
                            if($contactInputs[$attr] === '' || $contactInputs[$attr] === null)
                                $contactToSet[$attr] = null;
                            else{
                                $anyNewAttributes = true;
                                $contactToSet[$attr] = $contactInputs[$attr];
                            }
                        }
                        //This happens if some info was missing
                        /*if(!$anyNewAttributes){
                            $contactToSet = [];
                            $results[$identifier] = 4;
                            unset($identifiers[array_search($identifier,$identifiers)]);
                        }*/
                    }

                    if($contactToSet !== []){
                        array_push($contactsToSet,$contactToSet);
                        array_push($cacheContactsToUnset,$identifier);
                    }
                }
            }


            $columns = ['Contact_Type','Identifier','First_Name','Last_Name','Email','Phone','Fax',
                'Country','State','City','Street','Zip_Code','Company_Name','Company_ID','Contact_Info','Address','Extra_Info','Created_On','Last_Updated'];
            $insertArray = [];
            foreach($contactsToSet as $contactToSet){
                array_push($insertArray,[
                    [$contactToSet['type'],'STRING'],
                    [$contactToSet['identifier'],'STRING'],
                    [$contactToSet['firstName'],'STRING'],
                    [$contactToSet['lastName'],'STRING'],
                    [$contactToSet['email'],'STRING'],
                    [$contactToSet['phone'],'STRING'],
                    [$contactToSet['fax'],'STRING'],
                    [$contactToSet['country'],'STRING'],
                    [$contactToSet['state'],'STRING'],
                    [$contactToSet['city'],'STRING'],
                    [$contactToSet['street'],'STRING'],
                    [$contactToSet['zipCode'],'STRING'],
                    [$contactToSet['companyName'],'STRING'],
                    [$contactToSet['companyID'],'STRING'],
                    [$contactToSet['contactInfo'],'STRING'],
                    [$contactToSet['address'],'STRING'],
                    [$contactToSet['extraInfo'],'STRING'],
                    [$contactToSet['created'],'STRING'],
                    [$contactToSet['updated'],'STRING'],
                ]);
            }

            //In case we cannot set anything new, return,
            if($insertArray === [])
                return $results;

            //Set the contacts
            $res = $this->SQLHandler->insertIntoTable(
                $this->SQLHandler->getSQLPrefix().$this->tableName,
                $columns,
                $insertArray,
                array_merge($params,['onDuplicateKey'=>true])
            );

            //If successful, set results and erase the cache
            if($res){
                $toDelete = [];
                foreach($identifiers as $index => $identifier){
                    if($results[$identifiers[$index]] === -1)
                        $results[$identifiers[$index]] = 0;
                    foreach ($possibleTypes as $type){
                        array_push($toDelete,$this->cacheName.$type.'/'.$identifiers[$index]);
                    }
                }
                if(count($toDelete)>0){
                    if($verbose)
                        echo 'Deleting identifiers '.json_encode($toDelete).' from cache!'.EOL;
                    if(!$test)
                        $this->RedisHandler->call('del',[$toDelete]);
                }
            }

            return $results;
        }

        /** Deletes a single contact
         *
         * @param mixed $identifier Name of the contact
         * @param array $params same as deleteContacts
         *
         * @returns  Array of the form:
         */
        function deleteContact($identifier, array $params = []){
            return $this->deleteContacts([$identifier],$params);
        }

        /** Deletes multiple contacts.
         *
         * @param array $identifiers
         * @param array $params
         *      'possibleTypes' => string[], default [$this->contactType] - Possible contact types (for cache deletion when multiple possible)
         * @returns Int
         *         -1 - failed to connect to server
         *          0 - success
         *
         * @throws \Exception If you try to delete contacts without having a type.
         */
        function deleteContacts(array $identifiers, array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $contactType = isset($params['contactType'])? $params['contactType'] : $this->contactType;
            $possibleTypes = isset($params['possibleTypes'])? $params['possibleTypes'] : [$this->contactType];

            $dbNames = [];

            foreach($identifiers as $identifier){
                if($contactType)
                    array_push($dbNames,[[$contactType,'STRING'],[$identifier,'STRING'], 'CSV']);
                else
                    array_push($dbNames,[[$identifier,'STRING']]);
            }

            $columns = $contactType? ['Contact_Type','Identifier','CSV'] : ['Identifier'];

            $res = $this->SQLHandler->deleteFromTable(
                $this->SQLHandler->getSQLPrefix().$this->tableName,
                [
                    $columns,
                    $dbNames,
                    'IN'
                ],
                $params
            );

            if($res){
                $toDelete = [];
                foreach($identifiers as $identifier){
                    foreach ($possibleTypes as $type){
                        array_push($toDelete,$this->cacheName.$type.'/'.$identifier);
                    }
                }
                if(count($toDelete)>0){
                    if($verbose)
                        echo 'Deleting identifiers '.json_encode($toDelete).' from cache!'.EOL;
                    if(!$test)
                        $this->RedisHandler->call('del',[$toDelete]);
                }

                //Ok we're done
                return 0;
            }
            else
                return -1;
        }

        /** Renames one single contact
         *
         * @param mixed $identifier Name of the contact, or in case of 'styles' - array of the form [<system name>, <style name>]
         * @param mixed $newIdentifier Name of the contact, or in case of 'styles' - array of the form [<system name>, <style name>]
         * @param array $params
         *
         * @returns  Int of the form:
         *          -1 - db connection failure
         *           0 - success
         *           1 - new identifier already exists
         *
         * @throws \Exception If you try to rename a contact without having a type.
         */
        function renameContact(string $identifier, string $newIdentifier , array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $contactType = isset($params['contactType'])? $params['contactType'] : $this->contactType;
            if(!$contactType)
                throw new \Exception('Cannot set contacts without a specific type!');

            $existing = $this->getContact($newIdentifier, $params);
            if($existing === -1)
                return -1;
            elseif(is_array($existing)){
                return 1;
            }
            else{
                $newName = $newIdentifier;
                $conditions = [
                    [
                        'Contact_Type',
                        [$contactType,'STRING'],
                        '='
                    ],
                    [
                        'Identifier',
                        [$identifier,'STRING'],
                        '='
                    ],
                    'AND'
                ];

                $res = $this->SQLHandler->updateTable(
                    $this->SQLHandler->getSQLPrefix().$this->tableName,
                    ['Identifier = "'.$newName.'"'],
                    $conditions,
                    $params
                );

                if($res){
                    $identifier = implode('/',[$contactType,$identifier]);

                    if($verbose)
                        echo 'Deleting '.$contactType.' cache of '.$identifier.EOL;

                    if(!$test)
                        $this->RedisHandler->call( 'del', [ $this->cacheName.$contactType.'/'.$identifier ] );
                }

                return ($res)? 0 : -1;
            }
        }


    }



}
