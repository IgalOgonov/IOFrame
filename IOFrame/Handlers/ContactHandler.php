<?php
namespace IOFrame\Handlers{

    define('IOFrameHandlersContactHandler',true);

    /** Handles contacts. Should be extended for more system-specific logic.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */

    class ContactHandler extends \IOFrame\Abstract\DBWithCache
    {

        /** @var string|null Contact type
         * */
        protected ?string $contactType = null;

        /** @var string|null Table name - defaults to 'CONTACTS'
         * */
        protected mixed $tableName;

        /** @var string|null Cache prefix - defaults to lowercase $contactType
         * */
        protected mixed $cachePrefix;

        /** @var string|null Cache name - defaults to $cachePrefix.$tableName.'_'
         * */
        protected mixed $cacheName;

        function __construct(\IOFrame\Handlers\SettingsHandler $localSettings, string $type = null, $params = []){
            parent::__construct($localSettings,$params);
            $this->contactType = $type;
            $this->tableName = $params['tableName'] ?? 'CONTACTS';
            $this->cachePrefix = $params['cachePrefix'] ?? '';
            $this->cacheName = $params['cacheName'] ?? strtolower($this->tableName) . '_' . $this->cachePrefix;
        }

        /** Changes current contact type
         * @param string|null $newType New contact type
         */
        function setContactType(string $newType = null): void {
            $this->contactType = $newType;
        }

        /** Gets all contact types
         *
         * @param array $params
         *
         * @return array
         */
        function getContactTypes(array $params = []): array {
            $prefix = $this->SQLManager->getSQLPrefix();
            $tableQuery = $prefix.$this->tableName;
            $res = $this->SQLManager->selectFromTable(
                $tableQuery,
                [],
                ['DISTINCT Contact_Type'],
                $params
            );
            $tempRes = [];
            if(is_array($res) && count($res) > 0){
                foreach($res as $resultArray){
                    $tempRes[] = $resultArray['Contact_Type'];
                }
            }

            return $tempRes;
        }

        /** Gets one contact.
         *
         * @param string $identifier Identifier
         * @param array $params
         *
         * @return mixed
         */
        function getContact(string $identifier,  array $params = []){
            $contactType = $params['contactType'] ?? $this->contactType;
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
         *          ------ Using the parameters below disables caching ------
         *          'fullNameLike' => String, default null - returns results where first name together with last name matche a regex.
         *          'companyNameIDLike' => String, Unix timestamp, default null - returns results where company name together with company ID matche a regex.
         *          'orderBy'            - string, defaults to null. Possible values include 'Created' 'Last_Changed',
         *                                'Local' and 'Address'(default)
         *          'orderType'          - bool, defaults to null.  0 for 'ASC', 1 for 'DESC'
         *          'limit' => typical SQL parameter
         *          'offset' => typical SQL parameter
         *
         * @returns array of the form:
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
        function getContacts(array $identifiers = [], array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $extraDBFilters = $params['extraDBFilters'] ?? [];
            $extraCacheFilters = $params['extraCacheFilters'] ?? [];
            $firstNameLike = $params['firstNameLike'] ?? null;
            $fullNameLike = $params['fullNameLike'] ?? null;
            $emailLike = $params['emailLike'] ?? null;
            $countryLike = $params['countryLike'] ?? null;
            $cityLike = $params['cityLike'] ?? null;
            $companyNameLike = $params['companyNameLike'] ?? null;
            $companyNameIDLike = $params['companyNameIDLike'] ?? null;
            $createdAfter = $params['createdAfter'] ?? null;
            $createdBefore = $params['createdBefore'] ?? null;
            $changedAfter = $params['changedAfter'] ?? null;
            $changedBefore = $params['changedBefore'] ?? null;
            $includeRegex = $params['includeRegex'] ?? null;
            $excludeRegex = $params['excludeRegex'] ?? null;
            $orderBy = $params['orderBy'] ?? null;
            $orderType = $params['orderType'] ?? null;
            $limit = $params['limit'] ?? null;
            $offset = $params['offset'] ?? null;
            $contactType = $params['contactType'] ?? $this->contactType;

            $prefix = $this->SQLManager->getSQLPrefix();
            $retrieveParams = $params;
            $extraDBConditions = [];
            $extraCacheConditions = [];
            $keyDelimiter = '/';
            $colPrefix = $this->SQLManager->getSQLPrefix().$this->tableName.'.';
            $columns = ($contactType) ? ['Contact_Type','Identifier'] : ['Identifier'];

            //If we are using any of this functionality, we cannot use the cache
            if($offset || $limit ||  $orderBy || $orderType || $fullNameLike || $companyNameIDLike){
                $retrieveParams['useCache'] = false;
                $retrieveParams['orderBy'] = $orderBy?: null;
                $retrieveParams['orderType'] = $orderType?: 0;
                $retrieveParams['limit'] =  $limit?: null;
                $retrieveParams['offset'] =  $offset?: null;
            }

            //Create all the conditions for the db/cache
            if($contactType){
                $extraDBConditions[] = [$colPrefix . 'Contact_Type', [$contactType, 'STRING'], '='];
            }

            if($firstNameLike!== null){
                $extraCacheConditions[] = ['First_Name', $firstNameLike, 'RLIKE'];
                $extraDBConditions[] = [$colPrefix . 'First_Name', [$firstNameLike, 'STRING'], 'RLIKE'];
            }

            if($fullNameLike!== null){
                $extraDBConditions[] = ['CONCAT(' . $colPrefix . 'First_Name," ",' . $colPrefix . 'Last_Name)', [$fullNameLike, 'STRING'], 'RLIKE'];
            }

            if($emailLike!== null){
                $extraCacheConditions[] = ['Email', $emailLike, 'RLIKE'];
                $extraDBConditions[] = [$colPrefix . 'Email', [$emailLike, 'STRING'], 'RLIKE'];
            }

            if($countryLike!== null){
                $extraCacheConditions[] = ['Country', $countryLike, 'RLIKE'];
                $extraDBConditions[] = [$colPrefix . 'Country', [$countryLike, 'STRING'], 'RLIKE'];
            }

            if($cityLike!== null){
                $extraCacheConditions[] = ['City', $cityLike, 'RLIKE'];
                $extraDBConditions[] = [$colPrefix . 'City', [$cityLike, 'STRING'], 'RLIKE'];
            }

            if($companyNameLike!== null){
                $extraCacheConditions[] = ['Company_Name', $companyNameLike, 'RLIKE'];
                $extraDBConditions[] = [$colPrefix . 'Company_Name', [$companyNameLike, 'STRING'], 'RLIKE'];
            }

            if($companyNameIDLike!== null){
                $extraDBConditions[] = ['CONCAT(' . $colPrefix . 'Company_Name," ",' . $colPrefix . 'Company_ID)', [$companyNameIDLike, 'STRING'], 'RLIKE'];
            }

            if($createdAfter!== null){
                $cond = [$colPrefix.'Created',$createdAfter,'>'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($createdBefore!== null){
                $cond = [$colPrefix.'Created',$createdBefore,'<'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($changedAfter!== null){
                $cond = [$colPrefix.'Last_Updated',$changedAfter,'>'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($changedBefore!== null){
                $cond = [$colPrefix.'Last_Updated',$changedBefore,'<'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($includeRegex!== null){
                $extraCacheConditions[] = ['Identifier', $includeRegex, 'RLIKE'];
                $extraDBConditions[] = [$colPrefix . 'Identifier', [$includeRegex, 'STRING'], 'RLIKE'];
            }

            if($excludeRegex!== null){
                $extraCacheConditions[] = ['Identifier', $excludeRegex, 'NOT RLIKE'];
                $extraDBConditions[] = [$colPrefix . 'Identifier', [$excludeRegex, 'STRING'], 'NOT RLIKE'];
            }

            $extraDBConditions = array_merge($extraDBConditions,$extraDBFilters);
            $extraCacheConditions = array_merge($extraCacheConditions,$extraCacheFilters);

            if($extraCacheConditions!=[]){
                $extraCacheConditions[] = 'AND';
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }
            if($extraDBConditions!=[]){
                $extraDBConditions[] = 'AND';
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            if($identifiers == []){
                $results = [];

                $tableQuery = $prefix.$this->tableName;

                $res = $this->SQLManager->selectFromTable(
                    $tableQuery,
                    $extraDBConditions,
                    [],
                    $retrieveParams
                );
                $count = $this->SQLManager->selectFromTable(
                    $tableQuery,
                    $extraDBConditions,
                    ['COUNT(*)'],
                    array_merge($retrieveParams,['limit'=>null])
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
                return $this->getFromCacheOrDB(
                    $identifiers,
                    $columns,
                    $this->tableName,
                    $this->cacheName,
                    [],
                    $retrieveParams
                );
            }
        }

        /** Creates or updates a single contact
         *
         * @param string $identifier Name of the contact,
         * @param array $inputs Assoc array of inputs, of the form:
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
         * @return mixed
         * @throws \Exception
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
         * @returns array of the form:
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
        function setContacts(array $inputs, array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $update = $params['update'] ?? false;
            $contactType = $params['contactType'] ?? $this->contactType;
            $possibleTypes = $params['possibleTypes'] ?? [$this->contactType];
            $setType = $params['setType'] ?? $contactType;
            if(!$contactType && !($setType || $update))
                throw new \Exception('Cannot set contacts without a specific type!');
            //If we are updating, then by default we allow overwriting
            if(!$update)
                $override = $params['override'] ?? false;
            else
                $override = true;

            $keyDelimiter = '/';
            $identifiers = [];
            $results = [];
            $contactsToSet = [];
            $cacheContactsToUnset = [];
            $currentTime = (string)time();

            //Create the usual structures, index/identifier maps, initiate results, etc
            foreach($inputs as $inputArray){
                $identifier = $inputArray[0];
                //Add the identifier to the array
                $identifiers[] = $identifier;
                $results[$identifier] = -1;
            }

            //Get existing contacts
            $existing = $params['existing'] ?? $this->getContacts($identifiers, array_merge($params, ['updateCache' => false]));

            //Parse all existing contacts.
            foreach($inputs as $inputArray){
                $identifier = $inputArray[0];
                $dbIdentifier = $contactType ? implode($keyDelimiter,[$contactType,$inputArray[0]]) : $inputArray[0];
                $contactInputs = $inputArray[1] ?? [];
                //Initiate each input
                $contactInputs['firstName'] = $contactInputs['firstName'] ?? null;
                $contactInputs['lastName'] = $contactInputs['lastName'] ?? null;
                $contactInputs['email'] = $contactInputs['email'] ?? null;
                $contactInputs['phone'] = $contactInputs['phone'] ?? null;
                $contactInputs['fax'] = $contactInputs['fax'] ?? null;
                $contactInputs['contactInfo'] = $contactInputs['contactInfo'] ?? null;
                $contactInputs['country'] = $contactInputs['country'] ?? null;
                $contactInputs['state'] = $contactInputs['state'] ?? null;
                $contactInputs['city'] = $contactInputs['city'] ?? null;
                $contactInputs['street'] = $contactInputs['street'] ?? null;
                $contactInputs['zipCode'] = $contactInputs['zipCode'] ?? null;
                $contactInputs['address'] = $contactInputs['address'] ?? null;
                $contactInputs['companyName'] = $contactInputs['companyName'] ?? null;
                $contactInputs['companyID'] = $contactInputs['companyID'] ?? null;
                $contactInputs['extraInfo'] = $contactInputs['extraInfo'] ?? null;

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
                        $contactToSet['created'] = $existing[$dbIdentifier]['Created'];

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
                                    $inputJSON = json_decode($inputArray[1][$inputName],true);
                                    $existingJSON = json_decode($existing[$dbIdentifier][$dbName],true);
                                    if($inputJSON === null)
                                        $inputJSON = [];
                                    if($existingJSON === null)
                                        $existingJSON = [];
                                    $contactToSet[$inputName] =
                                        json_encode(\IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($existingJSON,$inputJSON,['deleteOnNull'=>true]));
                                    if($contactToSet[$inputName] == '[]')
                                        $contactToSet[$inputName] = null;
                                }
                            }
                            else
                                $contactToSet[$inputName] = ($existing[$dbIdentifier][$dbName] == '')? null : $existing[$dbIdentifier][$dbName];
                        }
                        //Type also gets special treatment
                        $contactToSet['type'] = $setType?: $existing[$dbIdentifier]['Contact_Type'];

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
                        $contactsToSet[] = $contactToSet;
                        $cacheContactsToUnset[] = $identifier;
                    }
                }
            }


            $columns = ['Contact_Type','Identifier','First_Name','Last_Name','Email','Phone','Fax',
                'Country','State','City','Street','Zip_Code','Company_Name','Company_ID','Contact_Info','Address','Extra_Info','Created','Last_Updated'];
            $insertArray = [];
            foreach($contactsToSet as $contactToSet){
                $insertArray[] = [
                    [$contactToSet['type'], 'STRING'],
                    [$contactToSet['identifier'], 'STRING'],
                    [$contactToSet['firstName'], 'STRING'],
                    [$contactToSet['lastName'], 'STRING'],
                    [$contactToSet['email'], 'STRING'],
                    [$contactToSet['phone'], 'STRING'],
                    [$contactToSet['fax'], 'STRING'],
                    [$contactToSet['country'], 'STRING'],
                    [$contactToSet['state'], 'STRING'],
                    [$contactToSet['city'], 'STRING'],
                    [$contactToSet['street'], 'STRING'],
                    [$contactToSet['zipCode'], 'STRING'],
                    [$contactToSet['companyName'], 'STRING'],
                    [$contactToSet['companyID'], 'STRING'],
                    [$contactToSet['contactInfo'], 'STRING'],
                    [$contactToSet['address'], 'STRING'],
                    [$contactToSet['extraInfo'], 'STRING'],
                    [$contactToSet['created'], 'STRING'],
                    [$contactToSet['updated'], 'STRING'],
                ];
            }

            //In case we cannot set anything new, return,
            if($insertArray === [])
                return $results;

            //Set the contacts
            $res = $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix().$this->tableName,
                $columns,
                $insertArray,
                array_merge($params,['onDuplicateKey'=>true])
            );

            //If successful, set results and erase the cache
            if($res){
                $toDelete = [];
                foreach($identifiers as $identifier){
                    if($results[$identifier] === -1)
                        $results[$identifier] = 0;
                    foreach ($possibleTypes as $type){
                        $toDelete[] = $this->cacheName . $type . '/' . $identifier;
                    }
                }
                if(count($toDelete)>0){
                    if($verbose)
                        echo 'Deleting identifiers '.json_encode($toDelete).' from cache!'.EOL;
                    if(!$test && $useCache)
                        $this->RedisManager->call('del',[$toDelete]);
                }
            }
            else
                $this->logger->error('Could not set contacts',['insert'=>$insertArray,'type'=>$contactType,'possibleTypes'=>$possibleTypes,'params'=>$params]);

            return $results;
        }

        /** Deletes a single contact
         *
         * @param mixed $identifier Name of the contact
         * @param array $params same as deleteContacts
         *
         * @returns  array of the form:
         * @throws \Exception
         * @throws \Exception
         */
        function deleteContact(mixed $identifier, array $params = []): int {
            return $this->deleteContacts([$identifier],$params);
        }

        /** Deletes multiple contacts.
         *
         * @param array $identifiers
         * @param array $params
         *      'possibleTypes' => string[], default [$this->contactType] - Possible contact types (for cache deletion when multiple possible)
         * @return int
         */
        function deleteContacts(array $identifiers, array $params = []): int {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $contactType = $params['contactType'] ?? $this->contactType;
            $possibleTypes = $params['possibleTypes'] ?? [$this->contactType];

            $dbNames = [];

            foreach($identifiers as $identifier){
                if($contactType)
                    $dbNames[] = [[$contactType, 'STRING'], [$identifier, 'STRING'], 'CSV'];
                else
                    $dbNames[] = [[$identifier, 'STRING']];
            }

            $columns = $contactType? ['Contact_Type','Identifier','CSV'] : ['Identifier'];

            $res = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().$this->tableName,
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
                        $toDelete[] = $this->cacheName . $type . '/' . $identifier;
                    }
                }
                if(count($toDelete)>0){
                    if($verbose)
                        echo 'Deleting identifiers '.json_encode($toDelete).' from cache!'.EOL;
                    if(!$test && $useCache)
                        $this->RedisManager->call('del',[$toDelete]);
                }

                //Ok we're done
                return 0;
            }
            else{
                $this->logger->error('Could not delete contacts',['ids'=>$identifiers,'type'=>$contactType,'possibleTypes'=>$possibleTypes]);
                return -1;
            }
        }

        /** Renames one single contact
         *
         * @param mixed $identifier Name of the contact, or in case of 'styles' - array of the form [<system name>, <style name>]
         * @param mixed $newIdentifier Name of the contact, or in case of 'styles' - array of the form [<system name>, <style name>]
         * @param array $params
         *
         * @return int
         * @throws \Exception If you try to rename a contact without having a type.
         */
        function renameContact(string $identifier, string $newIdentifier , array $params = []): int {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $contactType = $params['contactType'] ?? $this->contactType;
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

                $res = $this->SQLManager->updateTable(
                    $this->SQLManager->getSQLPrefix().$this->tableName,
                    ['Identifier = "'.$newName.'"'],
                    $conditions,
                    $params
                );

                if($res){
                    $identifier = implode('/',[$contactType,$identifier]);

                    if($verbose)
                        echo 'Deleting '.$contactType.' cache of '.$identifier.EOL;

                    if(!$test && $useCache)
                        $this->RedisManager->call( 'del', [ $this->cacheName.$contactType.'/'.$identifier ] );
                }
                else
                    $this->logger->error('Could not rename contacts',['id'=>$identifier,'newID'=>$newIdentifier,'type'=>$contactType]);

                return ($res)? 0 : -1;
            }
        }


    }



}
