<?php
namespace IOFrame\Util{
    use IOFrame;
    use IOFrame\Handlers\SettingsHandler;
    use League\OpenAPIValidation\PSR7\OperationAddress;
    use League\OpenAPIValidation\PSR7\ServerRequestValidator;
    use League\OpenAPIValidation\PSR7\SpecFinder;
    use League\OpenAPIValidation\PSR7\ValidatorBuilder;
    use Nyholm\Psr7\Factory\Psr17Factory;
    use Nyholm\Psr7\ServerRequest;
    use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
    use Symfony\Component\HttpFoundation\Request;
    use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;

    define('v2APIManager',true);
    if(!defined('abstractDBWithCache'))
        require __DIR__.'/../Handlers/abstractDBWithCache.php';

    /* A utility class meant to manage the v2 api.
     * As a reminder, this is a REST-ish API, largely based around the Symfony Request/Response, and the OpenAPI standard.
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class v2APIManager extends IOFrame\abstractDBWithCache
    {

        /** @param array The openAPI spec, parsed as an associative array
         */
        public array $spec;

        /** @param ServerRequestValidator Request validator
         */
        public ServerRequestValidator $validator;

        /** @param array Extra formats for the validator
         */
        public array $formats = [];

        /** @param ServerRequest Request
         */
        public ServerRequest $request;

        /** @param array|OperationAddress Result of the latest validateAction() call
         */
        public $match = null;

        /** @param array Parameters from the cookie, query, body and URI. Potentially also default parameters based on current action
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
         * */
        function __construct(SettingsHandler $settings, array $openAPI, $params = [])
        {

            parent::__construct($settings, $params);

            $this->spec = $openAPI;

            if(!empty($params['request']))
                $this->request = $params['request'];
            else{
                $request = Request::createFromGlobals();
                $psr17Factory = new Psr17Factory();
                $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
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
        public function validateAction()
        {
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
                        $match = ['error'=>$exceptionType,'message'=>$e->getMessage(),'param'=>$prev->dataBreadCrumb()->buildChain()[0]];
                        break;
                    default:
                        $match = ['error'=>$exceptionType,'message'=>$e->getMessage()];
                }

            }
            catch(\Exception $e) {
                //Handle generic exception
                $match = ['error'=>'GenericException','exception'=>$e];
            }

            $this->match = $match;

            return $match;
        }

        /** Sets and returns the parameters, based on current action. Will validate the action if not yet validated.
         *
         * @param array $params
         *  @returns array|bool
         *              If action was successfully validated, will set $this->params, and return them.
         *              Otherwise, will return false (error can be checked by checking the $match array).
         *
         * */
        public function initParams(array $params = [])
        {
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
                    IOFrame\Util\parse_raw_http_request($put_vars,$this->request->getServerParams());
                $this->parameters = [
                    'cookie'=>$this->request->getCookieParams(),
                    'query'=>$this->request->getQueryParams(),
                    'body'=>array_merge($this->request->getParsedBody(),$put_vars),
                    'uri'=>$this->match->parseParams(Request::createFromGlobals()->getUri()),
                ];
                $spec = new \cebe\openapi\spec\OpenApi($this->spec);
                $SpecFinder = new SpecFinder($spec);
                foreach ($SpecFinder->findQuerySpecs($this->match) as $key=>$parameter){
                    $data = $parameter->getSerializableData();

                    if(isset($data->schema->type) && $data->schema->type === 'boolean' && array_key_exists($key,$this->parameters['query'])){
                        $this->parameters['query'][$key] = $this->fixBoolean($this->parameters['query'][$key]);
                    }

                    if(!isset($this->parameters['query'][$key])  && empty($data->required) && isset($data->schema->default)){
                        if($verbose)
                            echo '[query] '.$key.' should be set to default value '.$data->schema->default.EOL;
                        $this->parameters['query'][$key] = $data->schema->default;
                    }
                }
                if(!empty($SpecFinder->findBodySpec($this->match)['*/*']->schema->properties ))
                    foreach ($SpecFinder->findBodySpec($this->match)['*/*']->schema->properties as $key=>$parameter){
                        $data = $parameter->getSerializableData();

                        if(isset($data->schema->type) && $data->schema->type === 'boolean' && array_key_exists($key,$this->parameters['body'])){
                            $this->parameters['body'][$key] = $this->fixBoolean($this->parameters['body'][$key]);
                        }

                        if(!isset($this->parameters['body'][$key]) && empty($data->required) && isset($data->default)){
                            if($verbose)
                                echo '[body] '.$key.' should be set to default value '.($data->default?$data->default:($data->type === 'boolean'?'false':($data->type === 'integer'?'0':(string)$data->default))).EOL;
                            $this->parameters['body'][$key] = $data->default;
                        }
                    }
                foreach ($SpecFinder->findCookieSpecs($this->match) as $key=>$parameter){
                    $data = $parameter->getSerializableData();

                    if(isset($data->schema->type) && $data->schema->type === 'boolean' && array_key_exists($key,$this->parameters['cookie'])){
                        $this->parameters['cookie'][$key] = $this->fixBoolean($this->parameters['cookie'][$key]);
                    }

                    if(!isset($this->parameters['cookie'][$key]) && empty($data->required) && isset($data->schema->default)){
                        if($verbose)
                            echo '[cookie] '.$key.' should be set to default value '.$data->schema->default.EOL;
                        $this->parameters['cookie'][$key] = $data->schema->default;
                    }
                }
                foreach ($SpecFinder->findPathSpec($this->match) as $key=>$parameter){
                    $data = $parameter->getSerializableData();
                    if(!isset($this->parameters['path'][$key]) && empty($data->required) && isset($data->schema->default)){
                        if($verbose)
                            echo '[path] '.$key.' should be set to default value '.$data->schema->default.EOL;
                        $this->parameters['path'][$key] = $data->schema->default;
                    }
                }
                return $this->parameters;
            }
        }

        private function jsonDecode(string $json): array
        {
            $value = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ParserException::jsonError(json_last_error_msg());
            }

            return $value;
        }

        private function urlQueryDecode(string $url_query): array
        {
            parse_str($url_query, $result);

            return $result;
        }

        private function fixBoolean(string $boolean): bool{
            return $boolean === 'true' || $boolean === '1';
        }

    }

}






?>