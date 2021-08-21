<?php
/* Current API for sending mails, and modifying mail templates.
 *________________________________________
 * mailTo
 *      - Sends a mail to a user.
 *          mail                                - string, mail address to mail to
 *          secToken                            - string, security token
 *          subj                                - string, mail subject
 *          mName1                              - string, mail of the sender which the recipient will see (for example "cars@cars.com").
 *                                                        Defaults to the real address the email was sent from.
 *          mName2                              - string, name  of the sender which the recipient will see (for example "Car Dealership")
 *          type                                - string, "normal" or "template" - send a mail normally or from template.
 *                                                        Defaults to "normal"
 *          varArray                            - string, JSON encoded array explained in MailHandler->fillTemplate()
 *          [type == "template"] templateNum    - int, template ID
 *          [type == "normal"] mBody            - string, mail body
 *
 *
 *        Examples:
 *          action=mailTo&mail=test@test.com&secToken=ASTR6356FGDFY654MGHGFV644&subj=Important Mail&mName1=cars@cars.com&mName2=Car Dealership&type=template&templateNum=7
 *
 *        Returns int Codes which mean:
 *             -1 - failed to reach DB
 *              0 - success
 *
 *________________________________________
 * getTemplates
 *      - Gets templates
 *          ids                 - string, default null - JSON encoded array of IDs. If null, gets all templates instead.
 *          The following are ONLY relevant if IDs is null:
 *              limit: int, default 50, max 500, min 1 - limit the number of items you get
 *              offset: int, default 0 - used for pagination purposes with limit
 *              createdAfter           - int, default null - Only return items created after this date.
 *              createdBefore          - int, default null - Only return items created before this date.
 *              changedAfter           - int, default null - Only return items last changed after this date.
 *              changedBefore          - int, default null - Only return items last changed  before this date.
 *              includeRegex           - string, default null - Titles including this pattern will be included
 *              excludeRegex           - string, default null - Titles including this pattern will be excluded
 *
 *
 *
 *        Examples:
 *          action=getTemplates&ids=[1,2,3]
 *
 *        Returns array of the form:
 *
 *          [
 *           <id> => Code/templates,
 *           <id> => Code/templates,
 *           ...
 *          ]
 *          if ids is null, also returns the object '@' (stands for 'meta') inside which there is a
 *              single key '#', and the value is the total number of results if there was no limit.
 *
 *          Templates and codes are the same as the ones from getTemplate() in MailHandler
 *
 *________________________________________
 * createTemplate
 *      - Creates a new template
 *          title - string, title of the template
 *          content - string, content of the template.
 *
 *        Examples:
 *          action=createTemplate&title=Test Template&content=Hello template!
 *
 *        Returns Codes/Int:
 *          -1 could not connect to db
 *          <ID> ID of the newly created template otherwise.
 *________________________________________
 * updateTemplate
 *      - Updates a template
 *          id - int, id of the template.
 *          title - string, title of the template.
 *          content - string, content of the template.
 *
 *        Examples:
 *          action=createTemplate&id=6&title=Test Template 2&content=Hello again, template!
 *
 *        Returns Codes/Int:
 *          -1 could not connect to db
 *          <ID> ID of the newly created template otherwise.
 *________________________________________
 * deleteTemplates
 *      - Deletes templates
 *          ids - string, JSON encoded array of ids of the templates.
 *
 *        Examples:
 *          action=deleteTemplates&ids=[4,5,6]
 *
 *        Returns Array of the form :
 *          [
 *              <ID>=><Code>
 *          ]
 *          Where the codes are:
 *         -1 - Failed to connect to db
 *          0 - All good
 *          1 - Template does not exist
 *
 *
*/
if(!defined('coreInit'))
    require __DIR__ . '/../../main/coreInit.php';

require __DIR__ . '/../apiSettingsChecks.php';
require __DIR__ . '/../defaultInputChecks.php';
require __DIR__ . '/../defaultInputResults.php';
require __DIR__ . '/../CSRF.php';
require __DIR__ . '/../../IOFrame/Handlers/MailHandler.php';
require 'mail_fragments/definitions.php';

if($test){
    echo 'Testing mode!'.EOL;
    foreach($_REQUEST as $key=>$value)
        echo htmlspecialchars($key.': '.$value).EOL;
}

$MailHandler = new IOFrame\Handlers\MailHandler(
    $settings,
    $defaultSettingsParams
);

if(!isset($_REQUEST["action"]))
    exit('Action not specified!');

$action = $_REQUEST['action'];

if(!checkApiEnabled('mail',$apiSettings,$_REQUEST['action']))
    exit(API_DISABLED);

switch($action){
    case 'mailTo':
        $arrExpected = ["mail","secToken","subj","mName1","mName2","type","templateNum","mBody","varArray"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'mail_fragments/mailTo_checks.php';
        require 'mail_fragments/mailTo_auth.php';
        require 'mail_fragments/mailTo_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'getTemplates':
        $arrExpected = ["ids","limit","offset","createdAfter","createdBefore","changedAfter","changedBefore","includeRegex","excludeRegex"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'mail_fragments/getTemplates_auth.php';
        require 'mail_fragments/getTemplates_checks.php';
        require 'mail_fragments/getTemplates_execution.php';

        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        break;

    case 'createTemplate':
    case 'updateTemplate':

        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["id","title","content"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'mail_fragments/setTemplate_auth.php';
        require 'mail_fragments/setTemplate_checks.php';
        require 'mail_fragments/setTemplate_execution.php';

        echo ($result === 0)?
            '0' : $result;
        break;

    case 'deleteTemplates':

        if(!validateThenRefreshCSRFToken($SessionHandler))
            exit(WRONG_CSRF_TOKEN);

        $arrExpected = ["ids"];

        require __DIR__ . '/../setExpectedInputs.php';
        require 'mail_fragments/deleteTemplates_auth.php';
        require 'mail_fragments/deleteTemplates_checks.php';
        require 'mail_fragments/deleteTemplates_execution.php';

        echo json_encode($result,JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_FORCE_OBJECT);
        break;

    default:
        exit('Specified action is not recognized');
}