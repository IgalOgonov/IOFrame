if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('user-login', {
    mixins:[eventHubManager,IOFrameCommons],
    props:{
        hasRememberMe:{
            type: Boolean,
            default: true
        },
        //Suggests 2FA even before the user tries to log in
        suggest2FA:{
            type: Boolean,
            default: false
        },
        //2FA options. Possible ones are 'app', 'mail' and 'sms', depending on the system.
        typesOf2FA:{
            type: Array,
            default: function(){
                return ['app','mail'];
            }
        },
        text: {
            type: Object,
            default: function(){
                return {
                    email:'email',
                    password: 'password',
                    rememberMe: 'Remember Me',
                    loginButton: 'Login',
                    errors:'Input errors',
                    '2FA':{
                        'required':'Two-Factor Authentication Required',
                        'suggested':'Two-Factor Authentication required for this account?',
                        'suggested2FA':'Choose Method',
                        'suggestedNo2FA':'I dont require 2FA',
                        'select':'Please select a 2FA method',
                        'differentMethod':'Select a different 2FA method',
                        'methods': {
                            app:{
                                'text':'Authenticator App',
                                'url':document.ioframe.rootURI+'front/ioframe/img/icons/CPMenu/security.svg',
                                'instruction':'Please enter the Authenticator code here:'
                            },
                            mail:{
                                'text':'Email Code',
                                'url':document.ioframe.rootURI+'front/ioframe/img/icons/CPMenu/mails.svg',
                                'request':'Send Code via Mail',
                                'instruction':'Once you receive the email, enter the code here:',
                                'authResponse':'Either the email is not verified, or you need to login again',
                                'invalidResponse':'Mail not sent',
                                'rateLimit':'Rate limit reached, try again in ',
                                'seconds':'seconds'
                            },
                            sms:{
                                'text':'SMS Code',
                                'url':document.ioframe.rootURI+'front/ioframe/img/icons/CPMenu/sms.svg',
                                'request':'Send Code via SMS',
                                'instruction':'Once you receive the SMS, enter the code here:'
                            },
                        },
                    }
                };
            }
        },
        //Selected language
        language: {
            type: String,
            default: document.ioframe.selectedLanguage ?? ''
        },
        //Identifier
        identifier: {
            type: String,
            default: ''
        },
        test:{
            type: Boolean,
            default: false
        },
        verbose:{
            type: Boolean,
            default: false
        },
    },
    data: function(){return {
        configObject: JSON.parse(JSON.stringify(document.siteConfig)),
        m:{
            val:'',
            class:''
        },
        p:{
            val:'',
            class:''
        },
        rMe: this.hasRememberMe,
        requires2FA:false,
        loginConfirmed:false,
        requests:{
            mail:false,
            sms:false
        },
        regex:{
            app:/^\d{6}$/,
            mail:/^[a-zA-Z0-9]{8}$/,
            sms:/^[a-zA-Z0-9]{6}$/,
        },
        twoFactorAuthType:'',
        twoFactorAuthCode:'',
        resp: '',
        requesting:false
    }
    },
    created: function(){
        this.registerHub(eventHub);
        this.registerEvent('requestMail2FA', this.send2FAMailResponse);
    },
    methods:{
        //Requests SMS/Mail
        requestCode: function(type){
            if(type !== 'mail'){
                alertLog('Authentication method not supported!','error');
                return;
            }

            this.requesting = true;

            //Data to be sent
            let data = new FormData();
            data.append('action', 'requestMail2FA');

            if(this.verbose)
                console.log('Requesting 2FA via email');

            this.apiRequest(
                data,
                'api/v1/users',
                'requestMail2FA',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        send2FAMailResponse: function (response){

            if(this.identifier && (!response.from || (response.from !== this.identifier)) )
                return;

            if(this.verbose)
                console.log('Got send2FAMailResponse response',response);

            this.requesting = false;

            response = this.identifier ? response.content : response;

            if((typeof response === 'string')){
                if(response.startsWith('RATE_LIMIT_REACHED')){
                    let seconds = response.split('@')[1];
                    alertLog(this.text['2FA'].methods.mail.rateLimit+' '+seconds+' '+this.text['2FA'].methods.mail.seconds,'warning',this.$el);
                }
                else if(response === 'AUTHENTICATION_FAILURE'){
                    alertLog(this.text['2FA'].methods.mail.authResponse,'warning',this.$el);
                    this.loginConfirmed = false;
                    this.twoFactorAuthType = '';
                    this.requires2FA = false;
                }
            }
            else if((response-0) !== 0){
                alertLog(this.text['2FA'].methods.mail.invalidResponse,'error',this.$el);
            }
            else{
                this.requests.mail = true;
            }
        },
        //Log in
        log: function(){
            let errors = 0;

            //output user inputs for testing
            if(this.verbose)
                console.log('Inputs are ' + "Email:"+this.m.val+", password:"+this.p.val);
            //validate email
            if( (this.m.val===undefined) || (this.m.val==="") || !this.m.val.match(/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/)){
                this.m.class = "error";
                errors++;
            }
            else
                this.m.class = "";

            //validate password
            if( (this.p.val.length>64) || (this.p.val.length<8) || (this.p.val.match(/(\s|<|>)/g)!=null)
                || (this.p.val.match(/[0-9]/g) == null) || (this.p.val.match(/[A-Z]/g) == null)){
                this.p.class = "error";
                errors++;
            }
            else
                this.p.class = "";

            //validate 2FA code
            if( this.requires2FA ){
                if(!this.twoFactorAuthCode.match(this.regex[this.twoFactorAuthType]))
                    errors++;
            }

            //If no errors, log in
            var context = this;
            if(errors<1){
                this.resp = "Logging in...";
                //Data to be sent

                updateCSRFToken().then(
                    function(token){
                        let req = context.test? 'test' : 'real';
                        let data = new FormData();
                        data.append('action', 'logUser');
                        data.append('m', context.m.val);
                        data.append('p', context.p.val);
                        data.append('log', 'in');
                        data.append('req', req);
                        data.append('CSRF_token', token);
                        if(context.rMe)
                            data.append('userID', localStorage.getItem('deviceID'));
                        if(context.requires2FA){
                            data.append('2FAType', context.twoFactorAuthType);
                            data.append('2FACode', context.twoFactorAuthCode);
                        }
                        if(context.language)
                            data.append('language', context.language);
                        //Api url
                        let url=document.ioframe.rootURI+"api\/v1\/users";
                        context.requesting = true;
                        //Request itself
                        fetch(url, {
                            method: 'post',
                            body: data,
                            mode: 'cors'
                        })
                            .then(function (json) {
                                return json.text();
                            })
                            .then(function (data) {
                                context.requesting = false;
                                let respType;
                                let response = data;
                                // success
                                if(context.verbose)
                                    console.log( "Posted: "+data+"to user API"+" ,Succeeded in getting response to post.");
                                context.resp = response;
                                /*This will display messages that the API could return
                                 **/
                                switch(response){
                                    case 'INPUT_VALIDATION_FAILURE':
                                        respType='warning';
                                        break;
                                    case 'AUTHENTICATION_FAILURE':
                                        respType='warning';
                                        break;
                                    case 'WRONG_CSRF_TOKEN':
                                        respType='warning';
                                        break;
                                    case '-1':
                                        respType='danger';
                                        break;
                                    case '0':
                                        respType='success';
                                        //If we specifically don't want to be remembered - we wont be.
                                        if(context.rMe){
                                            localStorage.removeItem("sesID");
                                            localStorage.removeItem("sesIV");
                                        }
                                        //Remember to udate session info!
                                        if(!context.test)
                                            updateSesInfo(document.ioframe.pathToRoot,{
                                                'sessionInfoUpdated': function(){
                                                    location.reload();
                                                }
                                            });
                                        break;
                                    case '1':
                                        respType='danger';
                                        break;
                                    case '2':
                                        respType='warning';
                                        break;
                                    case '3':
                                        respType='warning';
                                        break;
                                    case '4':
                                        context.requires2FA = true;
                                        context.loginConfirmed = true;
                                        return;
                                    case '5':
                                        respType='danger';
                                        break;
                                    case '6':
                                        respType='danger';
                                        break;
                                    case '7':
                                        respType='warning';
                                        break;
                                    case '8':
                                        respType='error';
                                        break;
                                    case '9':
                                        respType='error';
                                        break;
                                    default:
                                        let loginSuccess = false;
                                        if(response.length >= 32){
                                            //This means we got both sesID and IV
                                            if(IsJsonString(response) ){
                                                let resp = JSON.parse(response);
                                                if(resp['sesID'].length === 32){
                                                    localStorage.setItem("sesID",resp['sesID']);
                                                    loginSuccess = true;
                                                }
                                                if(resp['iv'].length === 32){
                                                    localStorage.setItem("sesIV",resp['iv']);
                                                    loginSuccess = true;
                                                }
                                            }
                                            //This means we just got a new ID
                                            else{
                                                if(response.length === 32){
                                                    localStorage.setItem("sesID",response);
                                                    loginSuccess = true;
                                                }
                                            }
                                            if(loginSuccess){
                                                context.resp = '0';
                                                respType='success';
                                                //Remember to udate session info!
                                                updateSesInfo(document.ioframe.pathToRoot,{
                                                    'sessionInfoUpdated': function(){
                                                        location.reload();
                                                    }
                                                });
                                            }
                                        }
                                        //Means this is something else...
                                        if(!loginSuccess){
                                            respType='danger';
                                        }
                                }

                                //Use AlertLog to tell the user what the resault was
                                //Can be implemented differently in different apps
                                eventHub.$emit('loginResponse',{body:context.resp,type:respType});
                                context.m.class = respType;
                                context.p.class = respType;
                            })
                            .catch(function (error) {
                                context.requesting = false;
                                if(context.verbose)
                                    console.log("Posted: "+data+"to user api"+" ,Failed in getting response to post.");
                                //Can be implemented differently in different apps
                                eventHub.$emit('loginResponse',{body:'Failed to reach API, cannot log in. Error text '+xhr.responseText,type:'danger'});
                                console.log('Error: ' + error); // An error occurred during the request.
                            });
                    },
                    function(reject){
                        console.log(reject);
                        eventHub.$emit('loginResponse',{body:'CSRF token expired. Please refresh the page to submit the form.',type:'danger'});
                    }
                );
            }
            else
                alertLog(this.text.errors,'error',this.$el);
        }
    },
    template:
        `<div class="user-login" :class="{requesting:requesting}">
            <form novalidate>
            
                <input v-if="!loginConfirmed && !requesting" :class="[m.class]" type="email" :id="identifier+'m_log'" name="m" :placeholder="text.email" v-model="m.val" required>
                <input v-if="!loginConfirmed && !requesting" :class="[p.class]" type="password" :id="identifier+'p_log'" name="p" :placeholder="text.password" v-model="p.val" required>
                <label v-if="!loginConfirmed && !requesting && hasRememberMe"> <input type="checkbox" name="rMe" v-model="rMe"> <span v-text="text.rememberMe"></span> </label>
                
                <div class="suggest-2fa" v-if="!loginConfirmed && suggest2FA">
                    <span v-text="text['2FA'].suggested"></span>
                    <button @click.prevent="requires2FA = !requires2FA" v-text="requires2FA ? text['2FA'].suggestedNo2FA : text['2FA'].suggested2FA"></button>
                </div>
                
                <div class="required-2fa" v-if="requires2FA && !requesting">
                
                    <div class="required" v-if="loginConfirmed" v-text="text['2FA'].required"></div>
                
                    <div class="select-method" v-if="!twoFactorAuthType">
                        <div v-text="text['2FA'].select"></div>
                        <button v-for="item in typesOf2FA" @click.prevent="twoFactorAuthType = item">
                            <img v-if="text['2FA'].methods[item].url" :src="text['2FA'].methods[item].url">
                            <span v-text="text['2FA'].methods[item].text"></span>
                        </button>
                    </div>
                    
                    <div v-else="" class="2fa" :class="twoFactorAuthType" >
                        <button @click.prevent="twoFactorAuthType = ''" v-text="text['2FA'].differentMethod"></button>
                        
                        <div class="request" v-if="twoFactorAuthType==='mail' && !requests.mail">
                            <button @click.prevent="requestCode('mail')" v-text="text['2FA'].methods['mail'].request"></button>
                        </div>
                        
                        <div class="request" v-else-if="twoFactorAuthType==='sms' && !requests.sms">
                            <button @click.prevent="requestCode('sms')" v-text="text['2FA'].methods['sms'].request"></button>
                        </div>
                        
                        <div class="code" v-else="">
                            <div v-text="text['2FA'].methods[twoFactorAuthType].instruction"></div>
                            <input type="text" :pattern="regex[twoFactorAuthType].toString().substr(1,regex[twoFactorAuthType].toString().length-2)" v-model="twoFactorAuthCode">
                        </div>
                        
                    </div>
                    
                </div>
                
                <button v-if="!requesting" @click.prevent="log" v-text="text.loginButton"></button>
            </form>
        </div>`
});