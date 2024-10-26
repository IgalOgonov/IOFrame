Vue.component('add-2fa', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //Options for the QR display
        options: {
            type: Object,
            default: function(){
                return {
                    //Error collection - LOW, MEDIUM, QUARTILE or HIGH
                    elc:qrcodegen.QrCode.Ecc.QUARTILE,
                    //Minimum version
                    minVer:1,
                    //Maximum version
                    maxVer:40,
                    //SVG border (inner padding of the QR code)
                    border:0,
                    //Pattern mask (0-7, -1 means auto)
                    mask:0,
                    //Increase Error Correcting level within same version
                    boostECC:true,
                    //Name of the issuer to appear in the QR -overrides default site name
                    issuer: ''
                }
            }
        },
        //Text to use for this component
        text: {
            type: Object,
            default: function(){
                return {
                    titleRequest:'Pair account with a Two Factor Authentication (2FA/OTP) app',
                    titleRequesting:'Requesting 2FA Data',
                    titleSuccess:'Paired 2FA!',
                    titleConfirm:'Scan the QR code, then enter the code in your app.',
                    buttonRequest:'Enable 2FA',
                    buttonConfirm:'Confirm Code',
                    alreadyChanging: 'Already executing operation!',
                    validationFailure:'Code must be 6 digits (e.g. 154362)',
                    messageFailed:'Unknown server response!',
                    codePlaceholderExisting:'Existing App Code',
                    codePlaceholderNew:'New App Code',
                    responses: {
                        'AUTHENTICATION_FAILURE':'You are no longer logged in!',
                        'INPUT_VALIDATION_FAILURE':'Invalid code, or request timed out and needs to be retried',
                        '-2':'Wrong code!',
                        '0':'Paired 2FA!'
                    }
                }
            }
        },
        //Whether we are already paired, and require the existing code to require a new one
        requireExistingCode: {
            type: Boolean,
            default: false
        },
        //Alert Options
        alertOptions: {
            type: Object,
            default: function(){
				return {
					use:true, //Whether to use this, or send event instead
					target:document.body, //Alert target
					params:{} //Alert params
				};
			}
        },
        //App Identifier
        identifier: {
            type: String,
            default: ''
        },
        //Test Mode
        test: {
            type: Boolean,
            default: false
        },
        //Verbose Mode
        verbose: {
            type: Boolean,
            default: false
        },
    },
    data: function(){
        return {
            QR:{
                mail:'',
                secret:'',
                issuer:''
            },
            //Whether we sent a request
            request: false,
            //whether we succeeded
            success:false,
            //The code we should enter
            code:'',
            //Which step we're in
            state:'request'
        }
    },
    created:function(){
        //Register eventhub
        this.registerHub(eventHub);
        //Register events
        this.registerEvent('twoFactorAppRequest', this.twoFactorAppResponse);

    },
    mounted:function(){
    },
    updated: function(){
    },
    computed:{
        currentTitle: function(){
            switch (this.state){
                case 'request':
                    return this.text.titleRequest;
                case 'confirm':
                    if(this.success)
                        return this.text.titleSuccess;
                    else
                        return this.text.buttonConfirm;
                default:
                    return '';
            }
        }
    },
    methods:{
        //Renders QR code
        renderQR: function(retry = 200, tries = 15){
            const svg = this.$el.querySelector('svg');
            if(!svg){
                if(tries > 0)
                    setTimeout(this.renderQR,retry,retry,--tries);
                return;
            }
            // Simple operation
            const ecl = this.options.elc? this.options.elc : qrcodegen.QrCode.Ecc.QUARTILE;
            const minVer = this.options.minVer? this.options.minVer : 1;
            const maxVer = this.options.maxVer? this.options.maxVer : 40;
            const mask = this.options.mask? this.options.mask : -1;
            const boostECC  = this.options.boostECC? this.options.boostECC : true;
            const text = "otpauth://totp/"+this.QR.mail+"?secret="+(this.test? 'JBSWY3DPEHPK3PXP' : this.QR.secret)+"&issuer="+(this.test? this.QR.issuer+' Test' : this.QR.issuer);
            const segs = qrcodegen.QrSegment.makeSegments(text);
            const qr = qrcodegen.QrCode.encodeSegments(segs, ecl, minVer, maxVer, mask, boostECC);
            const code = qr.toSvgString(this.options.border? (this.options.border - 0) : 0);
            const viewBox = / viewBox="([^"]*)"/.exec(code)[1];
            const pathD = / d="([^"]*)"/.exec(code)[1];
            svg.setAttribute("viewBox", viewBox);
            svg.querySelector('svg > path').setAttribute("d", pathD);
        },
        //Toggles 2FA
        request2FA: function(){
            this.genericSetFunction('request')
        },
        //Toggles 2FA
        confirmApp: function(){
            this.genericSetFunction('confirm')
        },
        //Wrapper
        genericSetFunction: function(action){

            if(this.request){
                if(this.verbose)
                    console.log(this.text.alreadyChanging? this.text.alreadyChanging : 'Already executing operation!');
                return;
            }

            //Validation
            if( (action==='confirm' || this.requireExistingCode) && !this.code.match(/^\d{6}$/) ){
                if(this.alertOptions.use)
                    alertLog(this.text.validationFailure,'warning');
                eventHub.$emit('toggle2FAValidationFailure');
                return ;
            }

            //Data to be sent
            let data = new FormData();
            data.append('action', action==='request'?'requestApp2FA':'confirmApp');
            if(action==='confirm'){
                data.append('code', this.code);
            }
            else if(this.requireExistingCode){
                data.append('code', this.code);
            }

            if(this.test && (action==='confirm'))
                data.append('req', 'test');

            this.request = true;
            eventHub.$emit(action==='confirm'?'request2FARequest':'confirmApp2FA', (this.identifier?{from:this.identifier}:undefined) );

            this.apiRequest(
                data,
                "api/v1/users",
                'twoFactorAppRequest',
                {
                    'verbose': this.verbose,
                    'identifier':this.identifier+'-'+action,
                    parseJSON:true
                }
            );
        },
        //Handle 2FA toggle
        twoFactorAppResponse: function(request){
            if(this.verbose)
                console.log('twoFactorAppResponse got', request);

            let action;
            switch (request.from){
                case this.identifier+'-request':
                    action = 'request';
                    break;
                case this.identifier+'-confirm':
                    action = 'confirm';
                    break;
                default:
                    return;
            }

            let response = request.content;
            if(typeof response === 'number')
                response += '';

            this.request = false;

            let message;
            let messageType = 'error';
            let extraParams = {};

            switch (response){
                case 'AUTHENTICATION_FAILURE':
                    message = this.text.responses[response];
                    break;
                case 'INPUT_VALIDATION_FAILURE':
                    messageType = 'warning';
                    message = this.text.responses[response];
                    break;
                case '-2':
                    messageType = 'warning';
                    message = this.text.responses[response];
                    break;
                case '0':
                    messageType = 'success';
                    extraParams = {autoDismiss:2000};
                    message = this.text.responses[response];
                    this.success = true;
                    break;
                default:
                    let messageValid = (action === 'request') && (typeof response === 'object');
                    if(!messageValid)
                        message = this.test? ('Test response: '+ response) : this.text.messageFailed;
                    else{
                        this.state = 'confirm';
                        this.QR.mail = response.mail;
                        this.QR.secret = response.secret;
                        this.QR.issuer = this.options.issuer ? this.options.issuer : response.issuer;
                        this.renderQR();
                    }
            }
            if(this.alertOptions.use && message)
                alertLog(message,messageType,document.body,extraParams);
            eventHub.$emit('twoFactorAppResponse',{
                action:action,
                response:response,
                message:message,
                messageType:messageType,
                from:(this.identifier?this.identifier:undefined)
            });
        }
    },
    template: `
        <div class="add-2fa" :class="state">
            <h4 class="add-2fa-text" v-text="currentTitle"></h4>
            <svg v-if="state==='confirm'">
                <path d="" fill="#000000" stroke-width="0"></path>
            </svg>
            <input v-if="state==='confirm' || requireExistingCode" type="text" class="code" v-model:value="code" :placeholder="state==='request' ? text.codePlaceholderExisting : text.codePlaceholderNew">
            <button v-if="!request && !success" @click.prevent="(state==='request') ? request2FA() : confirmApp()" v-text="state==='request' ? this.text.buttonRequest : this.text.buttonConfirm"></button>
        </div>
    `
});