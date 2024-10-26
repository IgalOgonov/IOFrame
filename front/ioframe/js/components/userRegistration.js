/*Captcha functions, since we must call global ones*/
if(captchaRegistrationSuccess === undefined)
    var captchaRegistrationSuccess = function(response){
        eventHub.$emit('registrationSuccess',response)
    }
if(captchaRegistrationExpired === undefined)
    var captchaRegistrationExpired = function(response){
        eventHub.$emit('registrationExpired',response)
    }
if(captchaRegistrationError === undefined)
    var captchaRegistrationError = function(response){
        eventHub.$emit('registrationError',response)
    }

Vue.component('user-registration', {
    props:{
        //Up to the parent to enforce that if you are REQUIRED to have a username, you also CAN have it
       canHaveUsername:{
            type: Boolean,
            default: true
        },
        requiresUsername:{
            type: Boolean,
            default: true
        },
        //Whether the user has an invite token
        inviteToken:{
            type: String,
            default: null
        },
        //Whether the invite token requires a specific mail
        inviteMail:{
            type: String,
            default: null
        },
        //Whether a captcha is required. Is irrelevant if the site does not have a captcha site key at document.ioframe.captchaSiteKey.
        requireCaptcha:{
            type: Boolean,
            default: true
        },
        //Various captcha options
        captchaOptions:{
            type: Object,
            default: function(){
                return {
                    siteKey: document.ioframe.captchaSiteKey ? document.ioframe.captchaSiteKey : false, /*if not provided from anywhere, wont use captcha*/
                    theme: 'light', /*light or dark*/
                    size: 'normal' /*noraml or compact*/
                };
            }
        },
        //Various agreements to be rendered
        agreementOptions:{
            type: Array,
            default: function(){
                return [
                    /* {
                     *  [agreeable: bool, default true - whether this allows the user to check it],
                     *  [required: bool, default true - whether user checking this is required],
                     *  [relativeLink: bool, default false - whether the link is relative or absolute],
                     *  link: string, link to agreement,
                     *  text:{
                     *      prefix: html content before the link (via v-html),
                     *      link: html content inside the link,
                     *      suffix: html content after the link,
                     *      required: html content for the message that pops up if the user does not agree to a required agreement.
                     *  },
                     *  [onSend:{
                     *    sendAs: string, send this as the following parameter,
                     *    parse: function that parses the value (true or false) and sends it under the key defined by sendAs
                     *  }]
                     * }
                    * */
                ];
            }
        },
        //Various alert related options
        alertOptions:{
            type: Object,
            default: function(){
                return {
                    alertTarget: document.body, /*where to append default alerts to*/
                    defaultAlerts: true, /*whether default alerts are displayed - if false, always sends events*/
                    alertEvents: false /*sends events when (or instead of) displaying alerts*/
                };
            }
        },
        text: {
            type: Object,
            default: function(){
                return {
                    username: this.requiresUsername? 'Username' : '[Optional] Username',
                    userNameHelp: (
                        this.requiresUsername?
                        'Must be 6-16 characters long, Must contain numbers and letters' :
                        '{OPTIONAL} Must be 6-16 characters long, Must contain numbers and letters'
                    ),
                    password: 'Password',
                    passwordHelp: "Must be 8-64 characters long<br> Must include latters and numbers<br>Can include special characters except '>' and '<'",
                    repeatPassword: 'Repeat password',
                    useToken:'Use provided invite token',
                    inviteToken:'Invite Token',
                    email: 'Email',
                    captcha:{
                        expired: 'Registration captcha expired! Please revalidate.',
                        error: 'Registration captcha error! Captcha reset.',
                        validation: 'Please complete the captcha!',
                        serverError: 'Server error trying to validate registration captcha!',
                        invalid: 'Captcha does not appear to be valid!',
                        alreadyValidated: 'Captcha has already been validated! Captcha reset, validate again.',
                    },
                    rateLimit:{
                        second: 'second',
                        seconds: 'seconds',
                        minute: 'minute',
                        minutes: 'minutes',
                        hour: 'hour',
                        hours: 'hours',
                        day: 'day',
                        days: 'days',
                        tryAgain: 'You cannot do this right now! Try again in',
                        connector: ' and '
                    },
                    registrationButton: 'Register'
                };
            }
        },
        customTooltips:{
            type: Object,
            default: function(){
                return {
                    username:'',
                    password:'',
                    activityClass:''
                }
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
    data: function(){
        return {
            u:{
                val:'',
                class:''
            },
            p:{
                val:'',
                class:''
            },
            p2:{
                val:'',
                class:''
            },
            m:{
                val:this.inviteMail?this.inviteMail:'',
                class:''
            },
            useToken:!!this.inviteToken,
            agreements:[],
            captcha: '',
            captchaID: '',
            req: 'real',
            resp: ''
        }
    },
    computed:{
        visibleToken:function(){
            return this.inviteToken.length < 20 ? this.inviteToken : this.inviteToken.substring(0,8)+' ... '+this.inviteToken.substr(this.inviteToken.length-8,8)
        }
    },
    created:function(){
        eventHub.$on('registrationSuccess',this.captchaSuccess);
        eventHub.$on('registrationExpired',this.captchaExpired);
        eventHub.$on('registrationError',this.captchaError);
        for(let i in this.agreementOptions){
            Vue.set(this.agreements,i,JSON.parse(JSON.stringify(this.agreementOptions[i])));
            //Set defaults
            this.agreements[i].agree = false;
            if(this.agreements[i].agreeable === undefined)
                this.agreements[i].agreeable = true;
            if(this.agreements[i].required === undefined)
                this.agreements[i].required = true;
            if(this.agreements[i].relativeLink)
                this.agreements[i].link = document.ioframe.rootURI + this.agreements[i].link;
            //Function has to be assigned directly
            if(this.agreements[i].onSend !== undefined && this.agreements[i].onSend.parse !== undefined){
                this.agreements[i].onSend.parse = this.agreementOptions[i].onSend.parse;
            }
        }
    },
    methods:{
        reg: function(){
            let errors = 0;
            let req = this.test? 'test' : 'real';
            let context = this;
            //output user inputs for testing
            if(this.verbose)
            console.log("Username:"+this.u.val+", password:"+ this.p.val+", email:"+this.m.val+", req:"+this.req);

            //validate username
            if(this.canHaveUsername && this.u.val.length > 0){
                if( (this.u.val.length>16) ||(this.u.val.length<6) || (this.u.val.match(/\W/g)!=null) ){
                    this.u.class = "error";
                    errors++;
                }
                else
                    this.u.class = "success";

            }
            else if(this.requiresUsername && (this.u.val.length === 0)){
                this.u.class = "error";
                errors++;
            }

            //validate password
            if( (this.p.val.length>64) ||(this.p.val.length<8) || (this.p.val.match(/(\s|<|>)/g)!=null)
                || (this.p.val.match(/[0-9]/g) == null) || (this.p.val.match(/[a-z]|[A-Z]/g) == null) ){
                this.p.class = "error";
                errors++;
            }
            else
                this.p.class = "success";

            //validate 2nd pass
            if(this.p.val!==this.p2.val || this.p2.val===undefined){
                this.p2.class = "error";
                errors++;
            }
            else
                this.p2.class = "success";

            //validate email
            if(!this.m.val){
                this.m.class = "error";
                errors++;
            }
            else
                this.m.class = "success";

            //validate captcha
            if(this.requireCaptcha && this.captchaOptions.siteKey && (this.captcha === '')){
                if(this.alertOptions.defaultAlerts)
                    alertLog(this.text.captcha.validation,'warning',this.alertOptions.alertTarget);
                if(this.alertOptions.alertEvents)
                    eventHub.$emit('registrationEvent',{from:'registration-component',text:this.text.captcha.validation,type:'warning'});
                errors++;
            }
            let agreement;
            //Check whether any required agreement is checked
            for(let i in this.agreements){
                agreement = this.agreements[i];
                if(agreement.required && !this.agreements[i].agree){
                    if(this.alertOptions.defaultAlerts)
                        alertLog(agreement.text.required,'warning',this.alertOptions.alertTarget);
                    if(this.alertOptions.alertEvents)
                        eventHub.$emit('registrationEvent',{from:'registration-component',text:agreement.text.required,type:'warning'});
                    errors++;
                }
            }

            //if no errors - send data
            if(errors<1){
                this.resp = "Posting...";
                //Data to be sent
                let data = new FormData();
                data.append('action', 'addUser');
                if(this.language)
                    data.append('captcha', this.captcha);
                if(this.captcha)
                    data.append('captcha', this.captcha);
                if(this.inviteToken && this.useToken)
                    data.append('token',this.inviteToken);
                if(this.canHaveUsername && this.u.val.length > 0)
                    data.append('u', this.u.val);
                data.append('m', this.m.val);
                data.append('p', context.p.val);
                data.append('req', req);
                for(let i in this.agreements){
                    agreement = this.agreements[i];
                    if(agreement.agree && (agreement.onSend!==undefined) && (agreement.onSend.sendAs!==undefined)){
                        let value = (agreement.onSend.parse!==undefined)? agreement.onSend.parse(agreement.agree) : agreement.agree;
                        data.append(agreement.onSend.sendAs, value);
                    }
                }
                let url=document.ioframe.pathToRoot+"api\/v1\/users";
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
                        let respType;
                        let response = data;
                        // success
                        if(context.verbose)
                            console.log("Posted: to user API"+" ,Succeeded in getting response ",response," to post.");
                        context.resp = response;
                        /*This will display messages that the addUser page could return
                         *0 - success
                         *1 - failed - incorrect input - wrong or missing
                         *2 - failed - username already in use
                         *3 - failed - email already in use
                         *4 - failed - server error
                         *5 - failed - registration not allowed!
                         **/
                        switch(response){
                            case 'CAPTCHA_SERVER_FAILURE':
                                respType='error';
                                response = context.text.captcha.serverError;
                                break;
                            case 'CAPTCHA_INVALID':
                                respType='warning';
                                response = context.text.captcha.invalid;
                                break;
                            case 'CAPTCHA_ALREADY_VALIDATED':
                                respType='info';
                                response = context.text.captcha.alreadyValidated;
                                hcaptcha.reset(context.captchaID);
                                break;
                            case 'INPUT_VALIDATION_FAILURE':
                                respType='warning';
                                break;
                            case 'AUTHENTICATION_FAILURE':
                                respType='warning';
                                break;
                            case 'WRONG_CSRF_TOKEN':
                                respType='warning';
                                break;
                            case '0':
                                respType='success';
                                break;
                            case '1':
                                respType='warning';
                                if(context.canHaveUsername)
                                    context.u.class = "warning";
                                hcaptcha.reset(context.captchaID);
                                break;
                            case '2':
                                context.m.class = "#fcf8e3";
                                respType='warning';
                                hcaptcha.reset(context.captchaID);
                                break;
                            case '3':
                                respType='warning';
                                break;
                            default:
                                /*Special Case*/
                                if(response.startsWith('RATE_LIMIT_REACHED')){
                                    let secondsLeft = response.split('@')[1];
                                    let timeArray = [];
                                    if(secondsLeft > 86400){
                                        const days = Math.floor(secondsLeft/86400);
                                        timeArray.push(days+' '+(days>1?context.text.rateLimit.days : context.text.rateLimit.day));
                                        secondsLeft = secondsLeft%86400;
                                    }
                                    if(secondsLeft > 3600){
                                        const hours = Math.floor(secondsLeft/3600);
                                        timeArray.push(hours+' '+(hours>1?context.text.rateLimit.hours : context.text.rateLimit.hour));
                                        secondsLeft = secondsLeft%3600;
                                    }
                                    if(secondsLeft > 60){
                                        const minutes = Math.floor(secondsLeft/60);
                                        timeArray.push(minutes+' '+(minutes>1?context.text.rateLimit.minutes : context.text.rateLimit.minute));
                                        secondsLeft = secondsLeft%60;
                                    }
                                    if(secondsLeft > 0){
                                        timeArray.push(secondsLeft+' '+(secondsLeft>1?context.text.rateLimit.seconds : context.text.rateLimit.second));
                                    }
                                    response = context.text.rateLimit.tryAgain+' '+timeArray.join(context.text.rateLimit.connector);
                                }
                                respType='warning';
                        }

                        //Use AlertLog to tell the user what the result was
                        //Can be implemented differently in different apps
                        eventHub.$emit('registrationResponse',{body:response,type:respType});
                        if(response == '0'){
                            context.u.val = '';
                            context.p.val = '';
                            context.p2.val = '';
                            context.m.val = '';
                            for(let i in context.agreements){
                                context.agreements[i].agree = false;
                            }
                        }
                })
                .catch(function (error) {
                    if(context.verbose)
                        console.log("Posted: ",data,"to user api"+" ,Failed in getting response to post.");
                    //Can be implemented differently in different apps
                    eventHub.$emit('registrationResponse',{body:'Failed to reach API, cannot log in. Error text '+error,type:'error'});
                    console.log('Error: ' + error); // An error occurred during the request.
                });
            }
        },
        //Captcha related functions
        captchaSuccess: function(response){
            if(this.verbose)
                console.log('Captcha succeeded!',response);
            this.captcha = response;
        },
        captchaExpired: function(response){
            if(this.verbose)
                console.log('Captcha expired!',response);
            if(this.alertOptions.defaultAlerts)
                alertLog(this.text.captcha.expired,'warning',this.alertOptions.alertTarget);
            if(this.alertOptions.alertEvents)
                eventHub.$emit('registrationEvent',{from:'registration-component',text:this.text.captcha.expired,type:'warning'});
            this.captcha = '';
        },
        captchaError: function(response){
            if(this.verbose)
                console.log('Captcha error!',response);
            if(this.alertOptions.defaultAlerts)
                alertLog(this.text.captcha.error,'warning',this.alertOptions.alertTarget);
            if(this.alertOptions.alertEvents)
                eventHub.$emit('registrationEvent',{from:'registration-component',text:this.text.captcha.error,type:'warning'});
            this.captcha = '';
            hcaptcha.reset(this.captchaID);
        }
    },
    mounted: function(){
        if(!this.popupHandler)
            this.popupHandler = new ezPopup("pop-up-tooltip");
        if(this.canHaveUsername)
            this.popupHandler.initPopup(this.identifier+'u_reg-tooltip',this.text.userNameHelp,this.customTooltips.username,this.customTooltips.activityClass);
        this.popupHandler.initPopup(this.identifier+'p_reg-tooltip',this.text.passwordHelp,this.customTooltips.password,this.customTooltips.activityClass);
        if(this.verbose)
            console.log(this.captchaOptions.siteKey,this.requireCaptcha);
        if(this.requireCaptcha && this.captchaOptions.siteKey){
            if(this.verbose)
                console.log('Rendering registration captcha!');
            this.captchaID = hcaptcha.render(this.$el.querySelector('.h-captcha'),JSON.parse(JSON.stringify(this.captchaOptions)));
        }
    },
    watch:{
        useToken:function(newVal){
            if(newVal && this.inviteMail)
                this.m.val = this.inviteMail;
        }
    },
    template: `
    <span class="user-registration">
        <form novalidate>
    
        <input :class="[m.class]" :disabled="useToken && inviteMail" type="email" :id="identifier+'m_reg'" name="m" :placeholder="text.email" v-model="m.val" required>
    
        <label v-if="canHaveUsername">
            <input :class="[u.class]" type="text" :id="identifier+'u_reg'" name="u" :placeholder="text.username" v-model="u.val" required>
            <a href="#"  :id="identifier+'u_reg-tooltip'">?</a>
        </label>
    
        <label>
            <input :class="[p.class]" type="password" :id="identifier+'p_reg'" name="p" :placeholder="text.password" v-model="p.val" required>
            <a href="#"  :id="identifier+'p_reg-tooltip'">?</a>
        </label>
    
        <input :class="[p2.class]" type="password" :id="identifier+'p2_reg'" :placeholder="text.repeatPassword" v-model="p2.val" required>
    
        <label v-if="inviteToken">
            <span v-text="text.useToken"></span>
            <input type="checkbox" :id="identifier+'use_token'" name="use_token" v-model="useToken">
        </label>
        
        <label v-if="inviteToken && useToken">
            <span v-text="text.inviteToken"></span>
            <input type="text" :id="identifier+'invite_token'" :value="visibleToken" disabled>
        </label>
        
        <div v-for="(options, index) in agreements" class="agreement" :class="{required:options.required}">
                <input v-if="options.agreeable" type="checkbox" v-model:value="options.agree">
                <span v-if="options.text.prefix" v-html="options.text.prefix"></span>
                <a v-if="options.text.link" v-html="options.text.link" :href="options.link"></a>
                <span v-if="options.text.suffix" v-html="options.text.suffix"></span>
        </div>
        
        <div v-if="requireCaptcha && captchaOptions.siteKey" class="h-captcha" :data-sitekey="captchaOptions.siteKey" data-callback="captchaRegistrationSuccess" data-expired-callback="captchaRegistrationExpired" data-error-callback="captchaRegistrationError"></div>
    
        <button @click.prevent="reg" v-text="text.registrationButton"></button>
    
        </form>
    </span>`
});