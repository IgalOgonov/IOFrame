/** Common functions
 * **/
const IOFrameCommons = {
    /* Meant to help parse and validate various events, typically API responses
    data:{
    * eventParsingMap :{
    * Objects of the form:
    * <key>: {
    *   identifierFrom: string, defaults to this.identifier - if not false, will expect the response to be sent FROM an identifier,
    *   identifierTo: string, defaults to undefined - if not false, will expect the response to be sent TO an identifier,
    *   apiResponses: bool, default false - whether to expect common API responses,
    *   alertErrors: bool, default false - alert errors,
    *   //If valid.value unset, will assume anything that isn't in the error map is valid
    *   valid:{
    *       type: string, default 'number' - 'number', 'object' or 'string',
    *       value: mixed | array, default undefined - value or values to consider valid. In case of an object, will check all values,
    *       condition: function, default null - if set, will take the param value as its only input, and return true if it's valid, false if it's not
    *   },
    *   objectOfResults: bool, default true - if type is object, whether the expected object is an object of values to be validated,
    *   errorMap:{
    *       <invalid response value>:{
    *           stop: bool, default false - whether to stop checking after this, in case of an object,
    *           text: string, default 'Invalid '+<key>+' response' - text that may be alerted in case of failure,
    *           warningType: string, default 'error' - warning type that may be alerted,
    *           target: DOM node, default this.$el - element to attach alert to
    *       },
    *   },
    *   //CommonErrors may be overwritten with different text - defaults similar to errorMap, but 'stop' is true, and apiResponses errors always treat the type as 'string'
    *   commonErrors: {
    *       [apiResponses]'API_DISABLED':{
    *           text: 'API Disabled',
    *           warningType: 'error'
    *       },
    *       [apiResponses]'INPUT_VALIDATION_FAILURE':{
    *           text: 'Input validation error!',
    *           warningType: 'error'
    *       },
    *       [apiResponses]'OBJECT_AUTHENTICATION_FAILURE':{
    *           text: 'Not authorized to view this object',
    *           warningType: 'error'
    *       },
    *       [apiResponses]'AUTHENTICATION_FAILURE':{
    *           text: 'Authorization failure. Check if you are logged in.',
    *           warningType: 'error'
    *       },
    *       [apiResponses]'WRONG_CSRF_TOKEN':{
    *           text: 'CSRF token invalid. Try refreshing the page, or disabling privacy extensions if this continues. Common error when your browser does not send referrer headers.',
    *           warningType: 'warning'
    *       },
    *       [apiResponses]'SECURITY_FAILURE':{
    *           text: 'Security related failure',
    *           warningType: 'error'
    *       },
    *       [apiResponses]'RATE_LIMIT_REACHED':{
    *           text: 'You cannot perform this action for now',
    *           warningType: 'warning'
    *       },
    *       [apiResponses]'CAPTCHA_MISSING':{
    *           text: 'Captcha result missing',
    *           warningType: 'error'
    *       },
    *       [apiResponses]'CAPTCHA_INVALID':{
    *           text: 'Captcha result invalid',
    *           warningType: 'error'
    *       },
    *       [apiResponses]'CAPTCHA_ALREADY_VALIDATED':{
    *           text: 'Captcha has already been validated',
    *           warningType: 'warning'
    *       },
    *       [apiResponses]'CAPTCHA_SERVER_FAILURE':{
    *           text: 'Captcha server unreachable',
    *           warningType: 'error'
    *       },
    *       '_tryAgainIn':{
    *           text: 'try again in'
    *       },
    *       '_seconds':{
    *           text: 'seconds'
    *       },
    *       '_invalidValue':{
    *           text: 'Invalid response value',
    *           warningType: 'error'
    *       },
    *       '_invalidType':{
    *           text: 'Invalid response type',
    *           warningType: 'error'
    *       },
    *   }
    *
    *
    * }
    }
    */
    methods: {
        //Gets media URL
        getAbsoluteFrontUrl: function(suffix = ''){
            return  document.ioframe.rootURI+'front/'+ suffix;
        },
        getMediaUrl: function(systemName='ioframe',suffix = ''){
            return  document.ioframe.rootURI+'front/'+systemName+'/img/'+ suffix;
        },
        absoluteMediaURL:function(relativeURL , type = this.mediaType){
            return document.ioframe.rootURI + document.ioframe[(type=== 'img'?'imagePathLocal':'videoPathLocal')]+relativeURL;
        },
        calculateDBImageLink: function(item, mediaType = 'img',updatedName = 'lastChanged'){
            let url = document.ioframe.rootURI+'api/media?action=getDBMedia&address='+item.identifier+'&resourceType='+mediaType;
            if(item[updatedName])
                url = url+'&lastChanged='+item[updatedName].toString();
            return url;
        },
        extractImageURL: function(img){
            return (img.local-0)?
                this.absoluteMediaURL(img.address) :
                (img.dataType? this.calculateDBImageLink(img) : img.identifier);
        },
        extractResourceType: function(realDataType){
            if(realDataType.startsWith('image'))
                return 'img';
            else if(realDataType.startsWith('video'))
                return 'vid';
            else if(realDataType.startsWith('audio'))
                return 'audio';
        },
        //Extracts the image address from an image object, as commonly returned by the API
        extractImageAddress: function(item,updatedName = 'updated'){
            if(!item)
                return false;

            if(this.verbose)
                console.log('Extracting address from ',item);

            if(!Object.keys(item).length)
                return false;

            let trueAddress = item.address;
            trueAddress = (item.local)?
                (document.ioframe.rootURI + document.ioframe.imagePathLocal+trueAddress) :
                (
                    item.dataType?
                        this.calculateDBImageLink({identifier:trueAddress},this.extractResourceType(item.dataType),updatedName):
                        trueAddress
                );
            return trueAddress;
        },
        /** Sends a request to an API, given form data. Then emits an event with the returned data
         * @param data An instance of FormData with the required information
         * @param apiName Name of the API. E.G 'api/orders
         * @param eventName Name of thew event that will be emitted once the request resolves
         * @param params Of the form: {
         *                             method: string, default 'post' - method to use.
         *                             mode: string, default 'cors' - refer to fetch mode, possible values 'cors', 'no-cors', 'same-origin',
         *                             contentType: string, default null - if set, replaces the content-type header (e.g 'application/json')
         *                             queryParams: object, default {} - Params to append to the query, rather than send as data.
         *                                          Structure <string, key> => <string, value>
         *                             dontSendBody: bool, default false - Will not send body even for methods that may have it
         *                             parseJSON: bool, Parses result if it is a valid json string
         *                             identifier: string, if set, the emitted event will be of the form:
         *                                  {
         *                                   from: <identifier>
         *                                   content: <response>
         *                                  }
         *                             extraEvents: object, extra events to emit - the key is the name of the
         *                                          event, the value is bool - whether to include API response
         *                             urlPrefix: string, defaults to document.ioframe.rootURI - prefix of the URL,
         *                             ignoreCSRF: bool, default false - whether to ignore requesting/sending the CSRF token.
         *                            }
         *
         * */
        apiRequest: function(data,apiName,eventName,params = {}){
            params.queryParams  = params.queryParams ?? {};
            let verbose = params.verbose || false;
            let ignoreCSRF = params.ignoreCSRF || false;
            let dontSendBody = params.dontSendBody ?? false;
            let method = params.method ?? 'post';
            let urlPrefix = params.urlPrefix === undefined ? document.ioframe.rootURI : params.urlPrefix;
            let apiURL = urlPrefix + apiName;
            let context = this;
            if(verbose)
                console.log('Sending API request to '+apiURL);
            if(!ignoreCSRF){
                updateCSRFToken().then(
                    function(token){
                        let methodHasBody = !dontSendBody && (['put','post','patch'].indexOf(method)!== -1);
                        if(methodHasBody)
                            data.append('CSRF_token', token);
                        else
                            params.queryParams.CSRF_token = token;
                        context._apiRequest(data,apiURL,eventName,params);
                    },
                    function(reject){
                        alertLog('CSRF token expired. Please refresh the page to submit the form.','error');
                    }
                );
            }
            else{
                this._apiRequest(data,apiURL,eventName,params);
            }
        },
        //The api reqeust itself, since the one before is a wrapper
        _apiRequest: function(data,apiURL,eventName,params){
            let verbose = params.verbose ?? false;
            let parseJSON = params.parseJSON ?? false;
            let identifier = params.identifier ?? this.identifier ?? false;
            let extraEvents = params.extraEvents ?? false;
            let method = params.method ?? 'post';
            let mode = params.mode ?? 'cors';
            let contentType = params.contentType ?? null;
            let queryParams = params.queryParams ?? {};
            let dontSendBody = params.dontSendBody ?? false;

            let toSend = {
                method: method,
                mode: mode,
            };

            if(contentType)
                toSend.headers = {
                    'Content-Type': contentType
                };

            let methodHasBody = !dontSendBody && (['put','post','patch'].indexOf(method)!== -1);
            if(methodHasBody)
                toSend.body = data;

            if(Object.keys(queryParams).length){
                apiURL += '?';
                for(let key in queryParams){
                    //Presumably, URI encoding is done automatically by the browser. Don't know any that don't.
                    apiURL += key+'='+ queryParams[key]+'&';
                }
                apiURL = apiURL.substring(0,apiURL.length-1);
            }

            fetch(
                apiURL,
                toSend
            )
                .catch(function (error) {
                    console.warn('Catastrophic API failure, error ',error);
                    let response;
                    if(identifier)
                        response = {
                            from:identifier,
                            error:error
                        };
                    else
                        response = error;

                    if(verbose)
                        console.log('Emitting ',eventName);
                    eventHub.$emit(eventName,response);

                    return null;
                })
                .then(function (json) {
                    if(json.status >= 400){
                        return {
                            error:'invalid-return-status',
                            errorStatus: json.status
                        };
                    }
                    else
                        return json.text();
                })
                .then(function (data) {
                    let response;

                    //A valid response would be a JSON
                    if(parseJSON && IsJsonString(data)){
                        response = JSON.parse(data);
                        if(response.length === 0)
                            response = {};
                    }
                    //Any non-json response is invalid
                    else
                        response = data;

                    if(verbose)
                        console.log('Request data',response);

                    if(identifier)
                        response = {
                            from:identifier,
                            content:response
                        };

                    if(verbose)
                        console.log('Emitting ',eventName);
                    eventHub.$emit(eventName,response);

                    if(extraEvents){
                        for(let secondaryEventName in extraEvents){
                            if(extraEvents[secondaryEventName]){
                                if(verbose)
                                    console.log('Emitting extra event with full response: ',secondaryEventName);
                                eventHub.$emit(secondaryEventName,response);
                            }
                            else{
                                if(verbose)
                                    console.log('Emitting extra event without response: ',secondaryEventName);
                                eventHub.$emit(secondaryEventName);
                            }
                        }
                    }
                });
        },

        /** A common function to mainly parse API responses.
         * Mainly meant to handle errors, allowing the main component handle a correct result.
         * Based on an eventParsingMap object
         * @param response The event response
         * @param key eventParsingMap key
         * @param apiVersion Behaves differently with API v2
         *
         * @returns object|bool returns false if response should be parsed, otherwise returns object of the form:
         *      {
         *          valid: bool|array - true/false if response is valid.
         *                              In case of an object, will instead be an array of keys of valid values
         *          invalid: array - in case of an object, will be an array of keys of invalid values,
         *          [error]: {
         *              type: string - 'common', 'api' or 'mapped',
         *              response: string, response value
         *          },
         *          [errors]: object, default {} - in case of more than 1 error with type object, will save each object key, value similar to error
         *      }
         * */
        eventResponseParser:function (response, key, apiVersion = 1){
            //Parse
            if(!this.eventParsingMap || !this.eventParsingMap[key])
                return false;

            let eventObject = JSON.parse(JSON.stringify(this.eventParsingMap[key]));
            //Set defaults
            eventObject.identifierFrom = eventObject.identifierFrom ?? this.identifier;
            eventObject.apiResponses = eventObject.apiResponses ?? false;
            eventObject.alertErrors = eventObject.alertErrors ?? false;
            eventObject.valid = eventObject.valid ?? {};
            eventObject.valid.type = eventObject.valid.type ?? 'number';
            eventObject.objectOfResults = eventObject.objectOfResults ?? true;
            eventObject.errorMap = eventObject.errorMap ?? {};
            eventObject.commonErrors = eventObject.commonErrors ?? {};
            eventObject.commonErrors['_invalidValue'] = eventObject.commonErrors['_invalidValue'] ?? {};
            eventObject.commonErrors['_invalidValue'].text = eventObject.commonErrors['_invalidValue'].text ?? 'Invalid response value';
            eventObject.commonErrors['_invalidValue'].warningType = eventObject.commonErrors['_invalidValue'].warningType ?? 'error';
            eventObject.commonErrors['_invalidType'] = eventObject.commonErrors['_invalidType'] ?? {};
            eventObject.commonErrors['_invalidType'].text = eventObject.commonErrors['_invalidType'].text ?? 'Invalid response type';
            eventObject.commonErrors['_invalidType'].warningType = eventObject.commonErrors['_invalidType'].warningType ?? 'error';
            eventObject.commonErrors['_tryAgainIn'] = eventObject.commonErrors['_tryAgainIn'] ?? {};
            eventObject.commonErrors['_tryAgainIn'].text = eventObject.commonErrors['_tryAgainIn'].text ?? 'try again in';
            eventObject.commonErrors['_seconds'] = eventObject.commonErrors['_seconds'] ?? {};
            eventObject.commonErrors['_seconds'].text = eventObject.commonErrors['_seconds'].text ?? 'seconds';
            if(eventObject.apiResponses){
                eventObject.commonErrors['invalid-return-status'] = eventObject.commonErrors['invalid-return-status'] ?? {};
                eventObject.commonErrors['invalid-return-status'].text = eventObject.commonErrors['invalid-return-status'].text ?? 'Invalid Return Status';
                eventObject.commonErrors['invalid-return-status'].warningType = eventObject.commonErrors['invalid-return-status'].warningType ?? 'error';
                eventObject.commonErrors['API_DISABLED'] = eventObject.commonErrors['API_DISABLED'] ?? {};
                eventObject.commonErrors['API_DISABLED'].text = eventObject.commonErrors['API_DISABLED'].text ?? 'API Disabled';
                eventObject.commonErrors['API_DISABLED'].warningType = eventObject.commonErrors['API_DISABLED'].warningType ?? 'error';
                eventObject.commonErrors['INPUT_VALIDATION_FAILURE'] = eventObject.commonErrors['INPUT_VALIDATION_FAILURE'] ?? {};
                eventObject.commonErrors['INPUT_VALIDATION_FAILURE'].text = eventObject.commonErrors['INPUT_VALIDATION_FAILURE'].text ?? 'Input validation error!';
                eventObject.commonErrors['INPUT_VALIDATION_FAILURE'].warningType = eventObject.commonErrors['INPUT_VALIDATION_FAILURE'].warningType ?? 'error';
                eventObject.commonErrors['OBJECT_AUTHENTICATION_FAILURE'] = eventObject.commonErrors['OBJECT_AUTHENTICATION_FAILURE'] ?? {};
                eventObject.commonErrors['OBJECT_AUTHENTICATION_FAILURE'].text = eventObject.commonErrors['OBJECT_AUTHENTICATION_FAILURE'].text ?? 'Not authorized to view this object';
                eventObject.commonErrors['OBJECT_AUTHENTICATION_FAILURE'].warningType = eventObject.commonErrors['OBJECT_AUTHENTICATION_FAILURE'].warningType ?? 'error';
                eventObject.commonErrors['AUTHENTICATION_FAILURE'] = eventObject.commonErrors['AUTHENTICATION_FAILURE'] ?? {};
                eventObject.commonErrors['AUTHENTICATION_FAILURE'].text = eventObject.commonErrors['AUTHENTICATION_FAILURE'].text ?? 'Authorization failure. Check if you are logged in.';
                eventObject.commonErrors['AUTHENTICATION_FAILURE'].warningType = eventObject.commonErrors['AUTHENTICATION_FAILURE'].warningType ?? 'error';
                eventObject.commonErrors['WRONG_CSRF_TOKEN'] = eventObject.commonErrors['WRONG_CSRF_TOKEN'] ?? {};
                eventObject.commonErrors['WRONG_CSRF_TOKEN'].text = eventObject.commonErrors['WRONG_CSRF_TOKEN'].text ?? 'CSRF token invalid. Try refreshing the page, or disabling privacy extensions if this continues. Common error when your browser does not send referrer headers.';
                eventObject.commonErrors['WRONG_CSRF_TOKEN'].warningType = eventObject.commonErrors['WRONG_CSRF_TOKEN'].warningType ?? 'warning';
                eventObject.commonErrors['SECURITY_FAILURE'] = eventObject.commonErrors['SECURITY_FAILURE'] ?? {};
                eventObject.commonErrors['SECURITY_FAILURE'].text = eventObject.commonErrors['SECURITY_FAILURE'].text ?? 'Security related failure';
                eventObject.commonErrors['SECURITY_FAILURE'].warningType = eventObject.commonErrors['SECURITY_FAILURE'].warningType ?? 'error';
                eventObject.commonErrors['RATE_LIMIT_REACHED'] = eventObject.commonErrors['RATE_LIMIT_REACHED'] ?? {};
                eventObject.commonErrors['RATE_LIMIT_REACHED'].text = eventObject.commonErrors['RATE_LIMIT_REACHED'].text ?? 'You cannot perform this action for now';
                eventObject.commonErrors['RATE_LIMIT_REACHED'].warningType = eventObject.commonErrors['RATE_LIMIT_REACHED'].warningType ?? 'error';
                eventObject.commonErrors['CAPTCHA_MISSING'] = eventObject.commonErrors['CAPTCHA_MISSING'] ?? {};
                eventObject.commonErrors['CAPTCHA_MISSING'].text = eventObject.commonErrors['CAPTCHA_MISSING'].text ?? 'Captcha result missing';
                eventObject.commonErrors['CAPTCHA_MISSING'].warningType = eventObject.commonErrors['CAPTCHA_MISSING'].warningType ?? 'error';
                eventObject.commonErrors['CAPTCHA_INVALID'] = eventObject.commonErrors['CAPTCHA_INVALID'] ?? {};
                eventObject.commonErrors['CAPTCHA_INVALID'].text = eventObject.commonErrors['CAPTCHA_INVALID'].text ?? 'Captcha result invalid';
                eventObject.commonErrors['CAPTCHA_INVALID'].warningType = eventObject.commonErrors['CAPTCHA_INVALID'].warningType ?? 'error';
                eventObject.commonErrors['CAPTCHA_ALREADY_VALIDATED'] = eventObject.commonErrors['CAPTCHA_ALREADY_VALIDATED'] ?? {};
                eventObject.commonErrors['CAPTCHA_ALREADY_VALIDATED'].text = eventObject.commonErrors['CAPTCHA_ALREADY_VALIDATED'].text ?? 'Captcha has already been validated';
                eventObject.commonErrors['CAPTCHA_ALREADY_VALIDATED'].warningType = eventObject.commonErrors['CAPTCHA_ALREADY_VALIDATED'].warningType ?? 'error';
                eventObject.commonErrors['CAPTCHA_SERVER_FAILURE'] = eventObject.commonErrors['CAPTCHA_SERVER_FAILURE'] ?? {};
                eventObject.commonErrors['CAPTCHA_SERVER_FAILURE'].text = eventObject.commonErrors['CAPTCHA_SERVER_FAILURE'].text ?? 'Captcha server unreachable';
                eventObject.commonErrors['CAPTCHA_SERVER_FAILURE'].warningType = eventObject.commonErrors['CAPTCHA_SERVER_FAILURE'].warningType ?? 'error';
            }

            if(this.verbose)
                console.log('Received '+key,response);

            if((eventObject.identifierFrom??false) && (response.from !== eventObject.identifierFrom)){
                if(this.verbose)
                    console.log('Invalid from identifier');
                return false;
            }
            if((eventObject.identifierTo??false) && (response.to !== eventObject.identifierTo)){
                if(this.verbose)
                    console.log('Invalid to identifier');
                return false;
            }

            if(typeof response !== 'object'){
                if(this.test)
                    alertLog(response, 'info', this.$el);
                else{
                    const error = (apiVersion === 1)?response : response.error;
                    const target = eventObject.commonErrors[error] ? eventObject.commonErrors[error] : eventObject.commonErrors['_invalidValue'];
                    alertLog(target.text, target.warningType, this.$el);
                }
                return false;
            }

            if((response.from || response.to) && !response.error)
                response = response.content;

            let result = {
                valid: eventObject.valid.type === 'object'? [] : true,
                invalid: eventObject.valid.type === 'object'? [] : undefined,
                error:null,
                errors: {}
            }

            //Remember that in test mode, response will not be a valid object
            const errorResponse = (apiVersion === 1)?(response.error ?? response) : response.error;
            let resultResponse = (apiVersion === 1)?response : (response.response ?? response);

            if(eventObject.apiResponses && eventObject.commonErrors[errorResponse]){
                if(eventObject.alertErrors)
                    alertLog(eventObject.commonErrors[errorResponse].text, eventObject.commonErrors[errorResponse].warningType, this.$el);
                result.valid = false;
                result.error = {type:'api',response:errorResponse};
                return result;
            }
            else if(eventObject.apiResponses && (typeof errorResponse === 'string') && errorResponse.match(/^RATE_LIMIT_REACHED\@\d+$/)){
                let seconds = errorResponse.split('@')[1];
                if(eventObject.alertErrors)
                    alertLog(
                        eventObject.commonErrors['RATE_LIMIT_REACHED'].text+', '+eventObject.commonErrors['_tryAgainIn'].text+' '+seconds+' '+eventObject.commonErrors['_seconds'].text,
                        eventObject.commonErrors['RATE_LIMIT_REACHED'].warningType,
                        this.$el)
                    ;
                result.valid = false;
                result.error = {type:'api',response:errorResponse};
                return result;
            }

            if(  (eventObject.valid.type === 'number') && (typeof resultResponse === 'string') && (resultResponse.match(/^\d+$/)) )
                resultResponse = resultResponse-0;

            if(eventObject.valid.type !== typeof resultResponse){
                //Remember that response in test mode will always be an invalid string
                if(eventObject.alertErrors)
                    alertLog(!this.test? eventObject.commonErrors['_invalidType'].text : resultResponse, eventObject.commonErrors['_invalidType'].warningType, this.$el);
                result.valid = false;
                result.error = {type:'common',response:'_invalidType'};
                return result;
            }

            let isObject = eventObject.valid.type === 'object';

            if(isObject && !eventObject.objectOfResults)
                return result;

            let target = resultResponse;
            if(!isObject){
                target = {key:resultResponse};
            }

            for(let targetKey in target){

                let responseValue = target[targetKey];

                if(eventObject.valid.value !== undefined){
                    let valid = typeof eventObject.valid.value !== 'object'? [eventObject.valid.value] : eventObject.valid.value;
                    if(valid.indexOf(responseValue)!==-1){
                        //Reminder - if not object, result.valid is already true, and this is the only iteration
                        if(isObject)
                            result.valid.push(targetKey);
                        continue;
                    }
                }
                else if(eventObject.valid.condition??false){
                    if(eventObject.valid.condition(responseValue)){
                        if(isObject)
                            result.valid.push(targetKey);
                        continue;
                    }
                }

                if(eventObject.errorMap[responseValue]){
                    if(eventObject.alertErrors)
                        alertLog(
                            eventObject.errorMap[responseValue].text?? ('Invalid '+key+' response'),
                            eventObject.errorMap[responseValue].warningType?? 'error',
                            eventObject.errorMap[responseValue].target?? this.$el
                        );

                    if(!isObject)
                        result.error = {type:'mapped',response:responseValue};
                    else
                        result.errors[targetKey] ={type:'mapped',response:responseValue};

                    result.valid = false;

                    if(eventObject.errorMap[responseValue].stop ?? false)
                        break ;
                }
                /*Only matters if there were specific valid values to begin with, which we didn't match*/
                else if((eventObject.valid.value !== undefined) && (eventObject.valid.condition??false)){
                    if(eventObject.alertErrors)
                        alertLog(
                            eventObject.commonErrors['_invalidValue'].text+responseValue,
                            eventObject.commonErrors['_invalidValue'].warningType,
                            eventObject.commonErrors['_invalidValue'].target?? this.$el
                        );

                    if(!isObject)
                        result.error = {type:'common',response:'_invalidValue'};
                    else
                        result.errors[targetKey] ={type:'common',response:'_invalidValue'};

                    result.valid = false;
                }

            }

            return result;

        }
    }
};
