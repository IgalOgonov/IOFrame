<?php
namespace IOFrame\Managers{

    use cebe\openapi\exceptions\TypeErrorException;
    use IOFrame\Handlers\SettingsHandler;
    use League\OpenAPIValidation\PSR7\Exception\NoPath;
    use League\OpenAPIValidation\PSR7\OperationAddress;
    use League\OpenAPIValidation\PSR7\ServerRequestValidator;
    use League\OpenAPIValidation\PSR7\SpecFinder;
    use League\OpenAPIValidation\PSR7\ValidatorBuilder;
    use Symfony\Component\HttpFoundation\Request;
    use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;

    define('IOFrameManagersV2APIManager',true);

    /* A utility class meant to manage the v2 api.
     * As a reminder, this is a REST-ish API, largely based around the Symfony Request/Response, and the OpenAPI standard.
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class v2APIManager extends \IOFrame\Abstract\DBWithCache
    {

        /** @var array The openAPI spec, parsed as an associative array
         */
        public array $spec;

        /** @var ServerRequestValidator Request validator
         */
        public ServerRequestValidator $validator;

        /** @var array Extra formats for the validator
         */
        public array $formats = [];

        /** @var \Nyholm\Psr7\ServerRequest Request
         */
        public \Nyholm\Psr7\ServerRequest $request;

        /** @var ?array|\League\OpenAPIValidation\PSR7\OperationAddress Result of the latest validateAction() call
         */
        public null | array | \League\OpenAPIValidation\PSR7\OperationAddress $match = null;

        /** @var array Parameters from the cookie, query, body and URI. Potentially also default parameters based on current action
         */
        public array $parameters = [
            'cookie'=>[],
            'query'=>[],
            'body'=>[],
            'uri'=>[],
        ];

        /** Standard constructor
         *
         * Constructs an instance of the class, getting the main settings file and an existing DB connection, or generating
         * such a connection itself.
         *
         * @param SettingsHandler $settings Local settings
         * @param array $openAPI The openAPI spec, parsed as an associative array
         * @param array $params Default settings, as well as:
         *                  'request' - Symfony\Component\HttpFoundation\Request, can be reconstructed automatically as well
         *                  'formats' - Potential formats to add to the openAPI spec
         *
         * @throws \Exception
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, array $openAPI, array $params = [])
        {

            parent::__construct($settings, $params);

            $this->spec = $openAPI;

            if(!empty($params['request']))
                $this->request = $params['request'];
            else{
                $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
                $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
                $psrHttpFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
                $this->request = $psrHttpFactory->createRequest($request);
                [$content_type] = explode(';', $this->request->getHeaderLine('Content-Type'));

                if ($content_type === 'application/json') {
                    $this->request = $this->request->withParsedBody($this->jsonDecode((string) $this->request->getBody()));
                } elseif ($this->request->getMethod() !== 'POST') {
                    switch ($content_type) {
                        case 'application/x-www-form-urlencoded':
                            $this->request = $this->request->withParsedBody($this->urlQueryDecode((string) $this->request->getBody()));
                            break;

                        case 'multipart/form-data':
                            $multipart_parser = \Kekos\MultipartFormDataParser\Parser::createFromRequest(
                                $this->request,
                                $psr17Factory,
                                $psr17Factory
                            );

                            $this->request = $multipart_parser->decorateRequest($this->request);
                            break;
                    }
                }

            }

            $this->validator = (new ValidatorBuilder)->fromJson(json_encode($openAPI))->getServerRequestValidator();

            if(!empty($params['formats'])) {
                foreach($params['formats'] as $format){
                    \League\OpenAPIValidation\Schema\TypeFormats\FormatsContainer::registerFormat($format['type'],$format['name'],$format['callback']);
                }
            }
        }

        /** Validates the current action.
         *  Keep in mind some additional validation might be required based on context (which is not available in openAPI), and parsing might be required as well.
         *
         *  @returns array|OperationAddress
         *              If the path/method combo is defined in the spec, and the inputs are valid,
         *              returns them as an OperationAddress object with the keys 'method' and 'path'.
         *
         *              Else, returns an associated array with the keys:
         *              "param", if a specific parameter that caused the error,
         *              "message", if a specific parameter that caused the error
         *              "error", one of the following strings:
         *                  'NoContentType' - HTTP message(request/response) contains no Content-Type header. General HTTP errors.
         *                  'NoPath' - Path is not found in the spec
         *                  'NoOperation' - Operation os not found in the path
         *                  'NoResponseCode' - response code not found under the operation in the spec
         *                  'TypeMismatch' - Validation for type keyword failed against a given data. For example type:string and value is 12
         *                  'FormatMismatch' - data mismatched a given type format. For example type: string, format: email won't match not-email.
         *                  'InvalidBody' -  Body does not match schema
         *                  'InvalidCookies' - Cookies does not match schema or missing required cookie
         *                  'InvalidHeaders' - Header does not match schema or missing required header
         *                  'InvalidPath' - Path does not match pattern or pattern values does not match schema
         *                  'InvalidQueryArgs' - Query args does not match schema or missing required argument
         *                  'InvalidSecurity' - Request does not match security schema or invalid security headers
         *                  'MultipleOperationsMismatchForRequest' - Request matched multiple operations in the spec, but validation failed for all of them.
         *
         * */
        public function validateAction(): array | OperationAddress {
            try {
                $match = $this->validator->validate($this->request);
            }
            catch(ValidationFailed $e) {

                $temp = explode('\\',get_class($e));

                $exceptionType = array_pop($temp);

                switch ($exceptionType){
                    case 'InvalidQueryArgs':
                        $prev = $e->getPrevious();
                        $match = ['error'=>$exceptionType,'message'=>$e->getMessage(),'param'=>$prev->name()];
                        break;
                    case 'InvalidBody':
                        $prev = $e->getPrevious();
                        $match = ['error'=>$exceptionType,'message'=>$prev->getMessage(),'param'=>$prev->dataBreadCrumb()->buildChain()[0]];
                        break;
                    default:
                        $match = ['error'=>$exceptionType,'message'=>$e->getMessage()];
                }

            }
            catch(\Exception $e) {
                //Handle generic exception
                $match = ['error'=>'GenericException','exception'=>$e->getMessage()];
            }

            $this->match = $match;

            return $match;
        }

        /**
         * Sets and returns the parameters, based on current action. Will validate the action if not yet validated.
         *
         * @param array $params
         * @return array|array[]|false
         * @throws NoPath
         * @throws TypeErrorException
         */
        public function initParams(array $params = []): array|bool {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            if(empty($this->match))
                $this->validateAction();

            if((gettype($this->match) === 'array') && !empty($this->match['error'])){
                $this->parameters = [
                    'cookie'=>[],
                    'query'=>[],
                    'body'=>[],
                    'uri'=>[],
                ];
                return false;
            }
            else{
                $put_vars = [];
                if( $_SERVER['REQUEST_METHOD']==='PUT')
                    \IOFrame\Util\HttpFunctions::parseRawHTTPRequest($put_vars,$this->request->getServerParams());
                $this->parameters = [
                    'cookie'=>$this->request->getCookieParams(),
                    'query'=>$this->request->getQueryParams(),
                    'body'=>array_merge($this->request->getParsedBody(),$put_vars),
                    'uri'=>$this->match->parseParams(Request::createFromGlobals()->getUri()),
                ];
                $spec = new \cebe\openapi\spec\OpenApi($this->spec);
                $componentSchemas = $spec->getSerializableData()->components->schemas;
                $SpecFinder = new SpecFinder($spec);
                foreach ($SpecFinder->findQuerySpecs($this->match) as $key=>$parameter){
                    $data = $parameter->getSerializableData();
                    $this->resolveInlineReferences($data,$componentSchemas);
                    $this->fixType($data->schema,$this->parameters['query'],$key,$verbose);
                    $this->setDefaults($this->parameters['query'],$key,$data->required??false,$data->schema->default??null,$verbose);
                }
                if(!empty($SpecFinder->findBodySpec($this->match)['*/*']->schema->properties ))
                    foreach ($SpecFinder->findBodySpec($this->match)['*/*']->schema->properties as $key=>$parameter){
                        $data = $parameter->getSerializableData();
                        $this->resolveInlineReferences($data,$componentSchemas);
                        $this->fixType($data,$this->parameters['body'],$key,$verbose);
                        $this->setDefaults($this->parameters['body'],$key,$data->required??false,$data->default??null,$verbose);
                    }
                foreach ($SpecFinder->findCookieSpecs($this->match) as $key=>$parameter){
                    $data = $parameter->getSerializableData();
                    $this->resolveInlineReferences($data,$componentSchemas);
                    $this->fixType($data->schema,$this->parameters['cookie'],$key,$verbose);
                    $this->setDefaults($this->parameters['cookie'],$key,$data->required??false,$data->schema->default??null,$verbose);
                }
                $fullActionSpec = $SpecFinder->findPathSpec($this->match)->getSerializableData();
                $method = strtolower($_SERVER['REQUEST_METHOD']);
                if(!empty($fullActionSpec->$method) && !empty($fullActionSpec->$method->parameters))
                    foreach ($fullActionSpec->$method->parameters as $data){
                        if($data->in !== 'path')
                            continue;
                        $key = $data->name;
                        $this->resolveInlineReferences($data,$componentSchemas);
                        $this->fixType($data->schema,$this->parameters['uri'],$key,$verbose);
                        $this->setDefaults($this->parameters['uri'],$key,$data->required??false,$data->schema->default??null,$verbose);
                    }
                return $this->parameters;
            }
        }

        /**
         * @throws \Exception
         */
        private function jsonDecode(string $json): array
        {
            $value = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(json_last_error_msg());
            }

            return $value;
        }

        private function urlQueryDecode(string $url_query): array
        {
            parse_str($url_query, $result);

            return $result;
        }

        private function setDefaults(array &$specificParams, string $key, mixed $required, mixed $default, bool $verbose = false): void {
            if(!isset($specificParams[$key]) && empty($required) && isset($default)){
                if($verbose)
                    echo 'Setting '.$key.' to '.$default.EOL;
                $specificParams[$key] = $default;
            }
        }

        private function resolveInlineReferences(\stdClass $data, \stdClass $componentSchemas): void {
            $ref = '$ref';
            while(!empty($data->schema->$ref)){
                $refKey = substr($data->schema->$ref,strlen('#/components/schemas/'));
                $data->schema = $componentSchemas->$refKey;
            }
        }

        private function fixType(\stdClass $schema, array &$specificParams, string $key, bool $verbose = false): void {
            if(isset($schema->type) && array_key_exists($key,$specificParams)){
                if($schema->type === 'boolean'){
                    if($verbose)
                        echo 'Turning '.$key.' to boolean'.EOL;
                    $specificParams[$key] = $specificParams[$key] === 'true' || $specificParams[$key] === '1';
                }
                elseif(($schema->type === 'integer') && is_string($specificParams[$key]) && preg_match('/^-?\d*$/',$specificParams[$key])){
                    if($verbose)
                        echo 'Turning '.$key.' to integer'.EOL;
                    $specificParams[$key] = (int)$specificParams[$key];
                }
                //At the moment, only comma separated arrays are allowed
                elseif(($schema->type === 'array') && is_string($specificParams[$key])){
                    if($verbose)
                        echo 'Turning '.$key.' to array'.EOL;
                    $specificParams[$key] = explode(',',$specificParams[$key]);
                }
            }
        }

    }

}




