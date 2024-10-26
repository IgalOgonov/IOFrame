<?php

namespace IOFrame\Handlers{

    define('IOFrameHandlersMailTemplateHandler',true);
    /* Handles mail templates
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class MailTemplateHandler extends \IOFrame\Abstract\DBWithCache{
        //Loads a template to serve as the body, using sendMailTemplate
        protected ?string $template = null;
        protected string $mailTemplateCachePrefix = 'mail_template_';

        /** @throws \Exception if undefined settings
         * */
        function __construct(
            SettingsHandler $settings,
            $params = []
        ){
            parent::__construct($settings,array_merge($params,['logChannel'=>\IOFrame\Definitions::LOG_MAILING_CHANNEL]));
        }

        /** TEMPLATE RELATED STUFF **/

        /** Makes selects a template from the Mail_Templates table to serve as the current template for this specific handler.
         * DOES NOT RECOGNIZE TITLES
         * @param string $templateID ID of the template
         * @param array $params of the form:
         *                  'safeStr' - bool, default true - convert from safeString to string
         *
         * Return values:
         * -1 - could not connect to db
         *  0 - all is good
         *  1 - ID doesn't match any template.
         * */
        function setWorkingTemplate(string $templateID, array $params = []): int {

            $test = (isset($params['test']))? $params['test'] : false;
            $verbose = (isset($params['verbose']))? $params['verbose'] : $test;

            $mailTemplate = $this->getTemplate(
                $templateID,
                $params
            );

            if($mailTemplate === -1){
                $this->logger->error('Failed to set working template due to DB error',['template'=>$templateID]);
                return -1;
            }
            if($mailTemplate === 1)
                return 1;

            $this->template = $mailTemplate['Content'];
            if($verbose)
                echo 'Set working template to '.htmlspecialchars($this->template).EOL;

            return 0;
        }

        //Resets Template to null
        function resetWorkingTemplate(): void {
            $this->template = null;
        }

        //Prints current template
        function printActiveTemplate(): void {
            echo EOL.'--Current Template--'.EOL.
                htmlspecialchars($this->template).EOL;
        }

        /** Inserts values from varArray into the fitting places in the $template.
         * Variables in the template are formatted like %%VARIABLE_NAME%% and ARE case-sensitive!
         * @param string|null $template Either a template body, or null to use currently loaded template.
         * @param array $varArray array {"Var1":"Value1", "Var2":"Value2" ...}
         * @return string|null
         */
        function fillTemplate(string $template = null, array $varArray = []): ?string {
            $body=$template??$this->template;
            if(!$body)
                return null;

            foreach ($varArray as $key=>$value){
                $body=str_replace("%%".$key."%%", $value, $body);
            }

            return $body;
        }

        /** Gets a single template.
         * @param string $templateID ID
         * @param array $params
         *
         * @return mixed
         */
        function getTemplate(string $templateID, array $params = []){
            return $this->getTemplates([$templateID],$params)[$templateID];
        }

        /** Gets all templates available
         *
         * @param string[] $templates defaults to [], if not empty will only get specific templates
         * @param array $params getFromCacheOrDB() params, as well as:
         *          'createdAfter'      - int, default null - Only return items created after this date.
         *          'createdBefore'     - int, default null - Only return items created before this date.
         *          'changedAfter'      - int, default null - Only return items last changed after this date.
         *          'changedBefore'     - int, default null - Only return items last changed  before this date.
         *          'includeRegex'      - string, default null - A  regex string that titles need to match in order
         *                                to be included in the result.
         *          'excludeRegex'      - string, default null - A  regex string that titles need to match in order
         *                                to be excluded from the result.
         *          'safeStr'           - bool, default true. Whether to convert Meta to a safe string
         *          ------ Using the parameters below disables caching ------
         *          'limit'             - string, SQL LIMIT, defaults to system default
         *          'offset'            - string, SQL OFFSET
         *
         * @returns array Array of the form:
         *      [
         *       <Template ID> =>   <Array of DB info> | <code from getTemplate()>,
         *      ...
         *      ],
         *
         *      on full search, the array will include the item '@' of the form:
         *      {
         *          '#':<number of total results>
         *      }
         */
        function getTemplates(array $templates = [], array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $createdAfter = $params['createdAfter'] ?? null;
            $createdBefore = $params['createdBefore'] ?? null;
            $changedAfter = $params['changedAfter'] ?? null;
            $changedBefore = $params['changedBefore'] ?? null;
            $includeRegex = $params['includeRegex'] ?? null;
            $excludeRegex = $params['excludeRegex'] ?? null;
            $limit = $params['limit'] ?? null;
            $offset = $params['offset'] ?? null;
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];

            $retrieveParams = $params;
            $extraDBConditions = [];
            $extraCacheConditions = [];

            //If we are using any of this functionality, we cannot use the cache
            if( $offset || $limit){
                $retrieveParams['useCache'] = false;
                $retrieveParams['limit'] =  $limit?: null;
                $retrieveParams['offset'] =  $offset?: null;
            }

            //Create all the conditions for the db/cache
            if($createdAfter!== null){
                $cond = ['Created',$createdAfter,'>'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($createdBefore!== null){
                $cond = ['Created',$createdBefore,'<'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($changedAfter!== null){
                $cond = ['Last_Updated',$changedAfter,'>'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($changedBefore!== null){
                $cond = ['Last_Updated',$changedBefore,'<'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($includeRegex!== null){
                $extraCacheConditions[] = ['Title', $includeRegex, 'RLIKE'];
                $extraDBConditions[] = ['Title', [$includeRegex, 'STRING'], 'RLIKE'];
            }

            if($excludeRegex!== null){
                $extraCacheConditions[] = ['Title', $excludeRegex, 'NOT RLIKE'];
                $extraDBConditions[] = ['Title', [$excludeRegex, 'STRING'], 'NOT RLIKE'];
            }

            if($extraCacheConditions!=[]){
                $extraCacheConditions[] = 'AND';
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }
            if($extraDBConditions!=[]){
                $extraDBConditions[] = 'AND';
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            if($templates == []){
                $results = [];
                $res = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().'MAIL_TEMPLATES',
                    $extraDBConditions,
                    [],
                    $retrieveParams
                );
                $count = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().'MAIL_TEMPLATES',
                    $extraDBConditions,
                    ['COUNT(*)'],
                    array_merge($retrieveParams,['limit'=>null])
                );
                if(is_array($res)){
                    $resCount = isset($res[0]) ? count($res[0]) : 0;
                    foreach($res as $resultArray){
                        for($i = 0; $i<$resCount/2; $i++)
                            unset($resultArray[$i]);
                        if($safeStr)
                            if($resultArray['Content'] !== null)
                                $resultArray['Content'] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($resultArray['Content']);
                        $results[$resultArray['ID']] = $resultArray;
                    }
                    $results['@'] = array('#' => $count[0][0]);
                }
                return ($res)? $results : [];
            }
            else{
                $results = $this->getFromCacheOrDB(
                    $templates,
                    'ID',
                    'MAIL_TEMPLATES',
                    $this->mailTemplateCachePrefix,
                    [],
                    $retrieveParams
                );

                if($safeStr)
                    foreach($results as $template =>$result){
                        if(is_array($result) && ($result['Content'] !== null))
                            $results[$template]['Content'] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($result['Content']);
                    }

                return $results;
            }

        }


        /** Sets a template. ( value NULL ignores something by default, '' sets something to null if allowed )
         * @param string $templateID ID of the template
         * @param string|null $title Title you wish to set
         * @param string|null $content Content of the template
         * @param array $params of the form:
         *          'override' - bool, default true - will overwrite existing templates.
         *          'update' - bool, default false - will only update existing templates.
         *          'existing' - Array, potential existing templates if we already got them earlier.
         *          'safeStr' - bool, default true. Whether to convert Content to a safe string
         * @returns int Code of the form:
         *         -3 - Template exists and override is false
         *         -2 - Template does not exist and required fields are not provided, or 'update' is true
         *         -1 - Could not connect to db
         *          0 - All good
         *         [createNew] ID of the newly created template
         */
        function setTemplate( string $templateID, string $title = null, string $content = null, array $params = []){
            return $this->setTemplates([[$templateID,$title,$content]],$params)[$templateID];
        }

        /** Sets a set of template.
         * @param array $inputs Array of input arrays in the same order as the inputs in setTemplate.
         * @param array $params from setTemplate
         * @returns int[]|int Array of the form
         *          <templateID> => <code>
         *          where the codes come from setTemplate().
         */
        function setTemplates(array $inputs, array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $update = $params['update'] ?? false;
            $override = $params['override'] ?? true;
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];

            $templates = [];
            $existingTemplates = [];
            $templateMap = [];
            $results = [];
            $templatesToSet = [];
            $currentTime = (string)time();

            foreach($inputs as $index=>$input){
                $templates[] = $input[0];
                $results[$input[0]] = -1;
                $templateMap[$index] = $input[0];
            }

            $existing = $params['existing'] ?? $this->getTemplates($templates, array_merge($params, ['updateCache' => false]));

            foreach($inputs as $index=>$input){
                //In this case the template does not exist or couldn't connect to db
                if(!is_array($existing[$templateMap[$index]])){
                    //If we could not connect to the DB, just return because it means we wont be able to connect next
                    if($existing[$templateMap[$index]] == -1)
                        return $results;
                    else{
                        //If we are only updating, continue
                        if($update){
                            $results[$input[0]] = -2;
                            continue;
                        }
                        //If the template does not exist, make sure all needed fields are provided
                        //Set title to null if not provided
                        if(!isset($input[1]))
                            $inputs[$index][1] = null;
                        //content
                        if(!isset($inputs[$index][2]))
                            $inputs[$index][2] = null;
                        elseif($safeStr)
                            $inputs[$index][2] = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($inputs[$index][2]);

                        //Add the template to the array to set
                        $templatesToSet[] = [
                            [$inputs[$index][0], 'STRING'],
                            [$inputs[$index][1], 'STRING'],
                            [$inputs[$index][2], 'STRING'],
                            [$currentTime, 'STRING'],
                            [$currentTime, 'STRING']
                        ];
                    }
                }
                //This is the case where the item existed
                else{
                    //If we are not allowed to override existing templates, go on
                    if(!$override && !$update){
                        $results[$input[0]] = -3;
                        continue;
                    }
                    //Push an existing template in to be removed from the cache
                    $existingTemplates[] = $this->mailTemplateCachePrefix . $input[0];
                    //Complete every field that is NULL with the existing template
                    //title
                    if(!isset($input[1]))
                        $inputs[$index][1] = $existing[$templateMap[$index]]['Title'];
                    //content
                    if(!isset($inputs[$index][2]))
                        $inputs[$index][2] = $existing[$templateMap[$index]]['Content'];
                    if($safeStr)
                        $inputs[$index][2] = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($inputs[$index][2]);
                    //Add the template to the array to set
                    $templatesToSet[] = [
                        [$inputs[$index][0], 'STRING'],
                        [$inputs[$index][1], 'STRING'],
                        [$inputs[$index][2], 'STRING'],
                        [$existing[$templateMap[$index]]['Created'], 'STRING'],
                        [$currentTime, 'STRING']
                    ];
                }
            }

            //If we got nothing to set, return
            if($templatesToSet==[])
                return $results;

            $columns = ['ID','Title','Content','Created','Last_Updated'];

            $res = $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix().'MAIL_TEMPLATES',
                $columns,
                $templatesToSet,
                array_merge($params,['onDuplicateKey'=>true])
            );

            //If we succeeded, set results to success and remove them from cache
            if($res){
                foreach($templates as $template){
                    if($results[$template] == -1)
                        $results[$template] = 0;
                }
                if($existingTemplates != []){
                    if(count($existingTemplates) == 1)
                        $existingTemplates = $existingTemplates[0];

                    if($verbose)
                        echo 'Deleting templates '.json_encode($existingTemplates).' from cache!'.EOL;

                    if(!$test && $useCache)
                        $this->RedisManager->call('del',[$existingTemplates]);
                }
            }
            else{
                $this->logger->error('Failed to set mail templates',['items'=>$templatesToSet]);
            }

            return $results;
        }

        /** Deletes a template
         * @param string $templateID
         * @param array $params
         * @return array
         */
        function deleteTemplate(string $templateID, array $params): array {
            return $this->deleteTemplates([$templateID],$params);
        }

        /** Deletes templates.
         *
         * @param string[] $templates
         * @param array $params
         *          'checkExisting' - bool, default true - whether to check for existing templates
         * @returns array of the form:
         * [
         *       <templateID> =>  <code>,
         *       ...
         * ]
         * Where the codes are from deleteTemplate
         */
        function  deleteTemplates(array $templates, array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $checkExisting = $params['checkExisting'] ?? true;

            $results = [];
            $templatesToDelete = [];
            $templatesToDeleteFromCache = [];
            $failedGetConnection = false;
            $existing = $checkExisting ? $this->getTemplates($templates,array_merge($params,['updateCache'=>false])) : [];

            foreach($templates as $template){
                if($existing!=[] && !is_array($existing[$template])){
                    if($verbose)
                        echo 'Template '.$template.' does not exist!'.EOL;
                    if($existing[$template] == -1)
                        $failedGetConnection = true;
                    $results[$template] = $existing[$template];
                }
                else{
                    $results[$template] = -1;
                    $templatesToDelete[] = [$template, 'STRING'];
                    $templatesToDeleteFromCache[] = $this->mailTemplateCachePrefix . $template;
                }
            }

            //Assuming if one result was -1, all of them were
            if($failedGetConnection){
                return $results;
            }

            if($templatesToDelete == []){
                if($verbose)
                    echo 'Nothing to delete, exiting!'.EOL;
                return $results;
            }

            $res = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().'MAIL_TEMPLATES',
                [
                    'ID',
                    $templatesToDelete,
                    'IN'
                ],
                $params
            );

            if($res){
                foreach($templates as $template){
                    if($results[$template] == -1)
                        $results[$template] = 0;
                }

                if($templatesToDeleteFromCache != []){
                    if(count($templatesToDeleteFromCache) == 1)
                        $templatesToDeleteFromCache = $templatesToDeleteFromCache[0];

                    if($verbose)
                        echo 'Deleting templates '.json_encode($templatesToDeleteFromCache).' from cache!'.EOL;

                    if(!$test && $useCache)
                        $this->RedisManager->call('del',[$templatesToDeleteFromCache]);
                }
            }
            else{
                $this->logger->error('Failed to delete mail templates',['items'=>$templates]);
            }

            return $results;
        }

    }
}