Vue.component('activate-account', {
    mixins: [sourceURL,parseLimit,eventHubManager,IOFrameCommons],
    props: {
        //Text to use for this component
        text: {
            type: Object,
            default: function(){
                return {
                    resendActivation:'If you have still haven\'nt received an activation mail, you can request a new one',
                    resendActivationButton:'Resend Activation Mail',
                    resendActivationPlaceholder:'Email Address',
                    sendingMessage:'Sending activation mail',
                    messageSent:'Activation mail sent!',
                    messageFailed:'Activation mail failed to send.',
                    wrongMailWarning:'Please enter a valid email!',
                    alreadyResetting:'Already sending mail!',
                    rateLimit: {
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
                    responses: {
                        'INPUT_VALIDATION_FAILURE':'Input email failed!',
                        'WRONG_CSRF_TOKEN':'Problem sending form - try again, or refresh the page.',
                        '-3':'Activation code creation failed',
                        '-2':'User already active',
                        '-1':'Mail failed to send',
                        '0':'Activation mail has been sent!',
                        '1':'No matching mail found in this system!',
                    }
                }
            }
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
        //If provided, will reset using this email
        email: {
            type: String,
            default: ''
        },
        //Selected language
        language: {
            type: String,
            default: document.ioframe.selectedLanguage ?? ''
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
            //Email to send reset to
            mail:'',
            //Whether we sent a request
            request: false,
            //Whether we finished updating
            updated: false,
            //Update response
            response: null
        }
    },
    created:function(){
        //Register eventhub
        this.registerHub(eventHub);
        //Register events
        this.registerEvent('regConfirm', this.regConfirm);
    },
    mounted:function(){
    },
    updated: function(){
    },
    computed:{
        resendActivationMessage:function(){
            return (this.request? this.text.sendingMessage : (this.updated ? (this.response!== null ? this.text.messageSent : this.text.messageFailed) : '' ) );
        }
    },
    methods:{
        //Resends mail
        sendMailReset: function(){

            if(this.request){
                if(this.verbose)
                    console.log(this.text.alreadyResetting? this.text.alreadyResetting : 'Already resetting mail!');
                return;
            }

            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            if(!this.email && !re.test(String(this.mail).toLowerCase())){
                alertLog((this.wrongMailWarning?this.wrongMailWarning:'Please enter a valid email to reset!'),'warning');
                return;
            }

            //Data to be sent
            let data = new FormData();
            data.append('action', 'regConfirm');
            if(this.language)
                data.append('language', this.language);
            data.append('mail', this.email? this.email : this.mail);

            if(this.verbose)
                console.log('Sending mail reset request for ',this.email? this.email : this.user.resendActivation);

            if(this.test)
                data.append('req', 'test');

            this.request = true;
            eventHub.$emit('regConfirmRequest', (this.identifier?{from:this.identifier}:undefined) );

            this.apiRequest(
                data,
                "api/v1/users",
                'regConfirm',
                {
                    'verbose': this.verbose,
                    'identifier':this.identifier
                }
            );
        },
        //Handle mail activation response
        regConfirm: function(request){
            if(this.verbose)
                console.log('regConfirm got', request);

            if(!request.from || request.from !== this.identifier)
                return;

            let response = request.content;
            if(typeof response === 'number')
                response += '';

            this.request = false;
            this.updated = true;

            let message;
            let messageType = 'error';
            let extraParams = {};

            switch (response){
                case 'INPUT_VALIDATION_FAILURE':
                case 'WRONG_CSRF_TOKEN':
                    messageType = 'warning';
                    break;
                case '-3':
                    messageType = 'error';
                    break;
                case '-2':
                    messageType = 'info';
                    break;
                case '-1':
                    messageType = 'error';
                    break;
                case '0':
                    messageType = 'success';
                    extraParams = {autoDismiss:2000};
                    this.response = true;
                    break;
                case '1':
                    messageType = 'info';
                    break;
                default:
                    /*Special Case*/
                    let potentialRateMessage = this.parseLimit(response);
                    if(potentialRateMessage)
                        message = this.parseLimit(response);
                    else
                        message = this.test? ('Test response: '+ response) : this.text.messageFailed;
            }
            if(!message){
                message = this.text.responses[response];
            }
            if(this.alertOptions.use)
                alertLog(message,messageType,this.alertOptions.target,{...this.alertOptions.params,...extraParams});

            eventHub.$emit('regConfirmResult',{
                response:response,
                message:message,
                messageType:messageType,
                from:(this.identifier?this.identifier:undefined)
            });
        }
    },
    template: `
        <div class="activate-account">
            <div class="activate-account-text" v-if="text.resendActivation" v-text="text.resendActivation"></div>
            <div class="resend" v-if="!resendActivationMessage">
                <input v-if="!email" type="text" v-model:value="resendActivation" :placeholder="text.reactivatePlaceholder?text.reactivatePlaceholder:'Email Address'">
                <button @click.prevent="sendMailReset" v-text="text.resendActivationButton?text.resendActivationButton:'New Activation Mail'"></button>
            </div>
            <div v-else="" class="reset-reset-mail-message" v-text="resendActivationMessage"></div>
        </div>
    `
});