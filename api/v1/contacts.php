<?php

/* This the the API that handles all the contacts functions.
 *
 *      See standard return values at defaultInputResults.php
 *
 * Parameters:
 * "action"     - Requested action - described bellow
 * "contactType"- String, contacts type. While not needed for getting a specific contact (or contact types), it's needed for
 *                anything that changes contact state.
 *
 *_________________________________________________
 * getContactTypes
 *      Get all contact types
 *      Returns:
 *          Array of Strings - all contact types available
 *
 *      Examples: action=getContactTypes
 *_________________________________________________
 * getContacts
 *      Get all or some contacts
 *      params - ALL optional:
 *          'contactType' => String, defaults to current contact type - overrides current contact type if provided
 *          'firstNameLike' => String, default null - returns results where first name  matches a regex.
 *          'emailLike' => String, email, default null - returns results where email matches a regex.
 *          'countryLike' => String, default null - returns results where country matches a regex.
 *          'cityLike' => String,  default null - returns results where city matches a regex.
 *          'companyNameLike' => String, Unix timestamp, default null - returns results where company name matches a regex.
 *          'createdBefore' => String, Unix timestamp, default null - only returns results created before this date.
 *          'createdAfter' => String, Unix timestamp, default null - only returns results created after this date.
 *          'changedBefore' => String, Unix timestamp, default null - only returns results changed before this date.
 *          'changedAfter' => String, Unix timestamp, default null - only returns results changed after this date.
 *          'includeRegex' => String, default null - only includes identifiers containing this regex.
 *          'excludeRegex' => String, default null - only includes identifiers excluding this regex.
 *          ------ Using the parameters bellow disables caching ------
 *          'fullNameLike' => String, default null - returns results where first name together with last name matche a regex.
 *          'companyNameIDLike' => String, Unix timestamp, default null - returns results where company name together with company ID matche a regex.
 *          'orderBy'            - string, defaults to null. Possible values are 'Created','Last_Updated','Email',
 *                                  'Country','City' and 'Company_Name'.
 *                                'Local' and 'Address'(default)
 *          'orderType'          - bool, defaults to null.  0 for 'ASC', 1 for 'DESC'
 *          'limit' => typical SQL parameter
 *          'offset' => typical SQL parameter
 *
 *      Returns:
 *          Array of objects (all contacts), of the form: [
 *              <contact identifier> => {
 *                  'type'=><string, contact type>,
 *                  ['firstName']=><string, first name>,
 *                  ['lastName']=><string, last name>,
 *                  ['email']=><string, email>,
 *                  ['phone']=><string, phone>,
 *                  ['fax']=><string, fax>,
 *                  ['contactInfo']=><string, Extra contact info - not location related (is reserved for extensions)>,
 *                  ['country']=><string, country>,
 *                  ['state']=><string, state>,
 *                  ['city']=><string, city>,
 *                  ['street']=><string, street>,
 *                  ['zip']=><string, zip>,
 *                  ['address']=><string, Extra address info - location related (is reserved for extensions)>
 *                  ['companyName']=><string, companyName>,
 *                  ['companyID']=><string, companyID>,
 *                  ['extraInfo']=><string, Extra meta info (is reserved for extensions)>,
 *                  'created'=><int, created time>,
 *                  'updated'=><int, last updated time>
 *              },
 *              ...
 *              '@' => {
 *                   '#'=><Number of results without limit/offset>
 *              }
 *          ]
 *
 *      Examples: action=getContacts&contactType=test&emailLike=test
 *
 *      It is possible to include a PHP file with an array called $extraColumns of the form
 *      [
 *          <DB column name> => <Result name>
 *      ]
 *      before execution, to add non-standard columns to the result. They can be parsed by a later include.
 *      Also, the following variables can be added by an include (validation done by author of the new api):
 *
 *      'extraDBFilters'    - array, default [] - Do you want even more complex filters than the ones provided?
 *                                This array will be merged with $extraDBConditions before the query, and passed
 *                                to getFromCacheOrDB() as the 'extraConditions' param.
 *                                Each condition needs to be a valid PHPQueryBuilder array.
 *      'extraCacheFilters' - array, default [] - Same as extraDBFilters but merged with $extraCacheConditions
 *                                and passed to getFromCacheOrDB() as 'columnConditions'.
 *_________________________________________________
 * getContact
 *      Gets a specific contact.
 *      params:
 *          'id' => <string, contact ID>
 *      returns:
 *           An object like a single array member from getContacts
 *
 *      Examples: action=getContact&contactType=test&id=Test Contact 1
 *_________________________________________________
 * setContact
 *      Sets a contact. null for each db field means "dont change", while an '@' means "set to null".
 *
 *      The special, JSON parameters (contactInfo,address,extraInfo) are supposed to be JSON strings which are merged recursively.
 *      They are NOT validated - thus, regular users should never have direct creation auth, but rather
 *      contacts need to be created through a new API specific to that system's needs.
 *
 *      params:
 *          -- Normal Params --
 *          'id'=> String, identifier
 *          'firstName' => String
 *          'lastName' => String
 *          'email' => String
 *          'phone' => String
 *          'fax' => String
 *          'country' => String
 *          'state' => String
 *          'city' => String
 *          'street' => String
 *          'zipCode' => String
 *          'companyName' => String
 *          'companyID' => String
 *
 *          -- Special Params (NOT VALIDATED) --
 *
 *          'contactInfo' => String
 *          'address' => String
 *          'extraInfo' => String
 *
 *          -- Meta Params --
 *          'update' => bool, default false - Whether we are only allowed to update existing contacts
 *          'override' => bool, default false -  Whether we are allowed to overwrite existing contacts
 *
 *      Returns int codes:
 *              -1 - failed to connect to db
 *              0 - success
 *              1 - contact does not exist (and update is true)
 *              2 - contact exists (and override is false)
 *              3 - trying to update the contact with no new info.
 *              4 - trying to create a new contact with missing inputs
 *
 *      Examples:
 *          action=setContact&contactType=test&id=Test Contact 4&update=false&firstName=Test&lastName=Testov II&email=test@test.com&phone=+972548762345&fax=034567895&country=Israel&state=Center&city=Natanya&street=HaShalom 5&zipCode=546545670&companyName=TestCo&companyID=ses-5465-test-01
 *_________________________________________________
 * deleteContacts
 *      Deletes contacts.
 *      Note that the contacts deleted using this will be ONLY of the same contactType specified with the request.
 *      params:
 *          'identifiers' => string, JSON encoded array of the form [<string: identifier>, ...]
 *      returns:
 *         -1 - failed to connect to server
 *          0 - success
 *
 *      Examples: action=deleteContacts&contactType=test&identifiers=["Test Contact 1","Test Contact 2"]
 *_________________________________________________
 * renameContact
 *      Renames a contact.
 *      Note that ALL the contacts deleted using this will be of the same contactType specified with the request.
 *      params:
 *          'identifier' => string, contact identifier
 *          'newIdentifier' => string, contact identifier
 *      returns: Int of the form:
 *          -1 - db connection failure
 *           0 - success
 *           1 - new identifier already exists
 *
 *      Examples: action=renameContact&contactType=test&identifier=Test Contact 1&newIdentifier=Test Contact 5
 * */

if(!defined('coreInit'))
    require __DIR__ . '/../../main/coreInit.php';

require __DIR__ . '/../../IOFrame/Handlers/ContactHandler.php';
require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';
require 'contacts_fragments/definitions.php';
if($test)
    echo 'Testing mode!'.EOL;

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');
$action = $_REQUEST["action"];

if(!checkApiEnabled('contacts',$apiSettings,$_REQUEST['action']))
    exit(API_DISABLED);

$contactType = null;
if(!isset($_REQUEST['contactType']) && !in_array($action,['getContactTypes','getContacts']))
    exit('contactType required for most actions!');
elseif(isset($_REQUEST['contactType'])){
    $contactType = $_REQUEST['contactType'];
    if(!preg_match('/'.CONTACT_TYPE_REGEX.'/',$contactType)){
        if($test)
            echo 'Contact type needs to match the pattern '.CONTACT_TYPE_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}

$ContactHandler = new IOFrame\Handlers\ContactHandler(
    $settings,
    $contactType,
    array_merge($defaultSettingsParams, ['siteSettings'=>$siteSettings])
);

switch($action){
    case 'getContactTypes':
        require 'contacts_fragments/getContactTypes_auth.php';
        require 'contacts_fragments/getContactTypes_execution.php';
        echo json_encode($result);
        break;

    case 'getContacts':
        $arrExpected =["contactType","firstNameLike","emailLike","countryLike","cityLike","companyNameLike","createdBefore",
            "createdAfter","changedBefore","changedAfter","includeRegex","excludeRegex","fullNameLike","companyNameIDLike",
            "orderBy","orderType","limit","offset"];

        require 'contacts_fragments/translationTable.php';
        require __DIR__ . '/../setExpectedInputs.php';
        require 'contacts_fragments/getContacts_auth.php';
        require 'contacts_fragments/getContacts_checks.php';
        require 'contacts_fragments/getContacts_execution.php';
        echo json_encode($result);
        break;

    case 'getContact':
        $arrExpected =["id"];

        require 'contacts_fragments/translationTable.php';
        require __DIR__ . '/../setExpectedInputs.php';
        require 'contacts_fragments/getContact_auth.php';
        require 'contacts_fragments/getContact_checks.php';
        require 'contacts_fragments/getContact_execution.php';
        echo json_encode($result);
        break;

    case 'setContact':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);
        $arrExpected =["id","firstName","lastName","email","phone","fax","country","state","city","street",
            "zipCode","companyName","companyID","contactInfo","address","extraInfo","update","override"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'contacts_fragments/setContact_auth.php';
        require 'contacts_fragments/setContact_checks.php';
        require 'contacts_fragments/setContact_execution.php';
        echo json_encode($result);
        break;

    case 'deleteContacts':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);
        $arrExpected =["identifiers"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'contacts_fragments/deleteContacts_auth.php';
        require 'contacts_fragments/deleteContacts_checks.php';
        require 'contacts_fragments/deleteContacts_execution.php';
        echo $result? $result : '0';
        break;

    case 'renameContact':
        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);
        $arrExpected =["identifier","newIdentifier"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'contacts_fragments/renameContact_auth.php';
        require 'contacts_fragments/renameContact_checks.php';
        require 'contacts_fragments/renameContact_execution.php';
        echo $result? $result : '0';
        break;

    default:
        exit('Specified action is not recognized');
}

?>


