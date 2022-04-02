if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('reset-password', {
    mixins: [sourceURL,parseLimit,eventHubManager,IOFrameCommons],
    props: {
        //Text to use for this component
        text: {
            type: Object,
            default: function(){
                return {
                    resetPassword:'If you have forgotten your password, you can request a password reset mail',
                    resetPasswordButton:'Request Password Reset',
                    resetPasswordPlaceholder:'Email Address',
                    sendingMessage:'Sending password reset mail',
                    messageSent:'Password reset mail sent!',
                    messageFailed:'Password reset mail failed to send.',
                    wrongMailWarning:'Please enter a valid email to reset!',
                    alreadyResetting:'Already resetting password!',
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
                        'SECURITY_FAILURE':'Security failure - your IP might be suspicious. Contact support.',
                        'INPUT_VALIDATION_FAILURE':'Input email failed!',
                        'WRONG_CSRF_TOKEN':'Problem sending form - try again, or refresh the page.',
                        '0':'Password reset mail has been sent!',
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
            default: document.selectedLanguage ?? ''
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
            resetMail:'',
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
        this.registerEvent('passwordReset', this.passwordReset);
    },
    mounted:function(){
    },
    updated: function(){
    },
    computed:{
        resetPasswordMessage:function(){
            return (this.request? this.text.sendingMessage : (this.updated ? (this.response!== null ? this.text.messageSent : this.text.messageFailed) : '' ) );
        }
    },
    methods:{
        //Resends password
        sendPasswordReset: function(){

            if(this.request){
                if(this.verbose)
                    console.log(this.text.alreadyResetting? this.text.alreadyResetting : 'Already resetting password!');
                return;
            }

            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            if(!this.email && !re.test(String(this.resetMail).toLowerCase())){
                alertLog((this.wrongMailWarning?this.wrongMailWarning:'Please enter a valid email to reset!'),'warning');
                return;
            }

            //Data to be sent
            let data = new FormData();
            data.append('action', 'pwdReset');
            data.append('mail', this.email? this.email : this.resetMail);
            if(this.language)
                data.append('language', this.language);

            if(this.verbose)
                console.log('Sending password reset request for ',this.email? this.email : this.resetMail);

            if(this.test)
                data.append('req', 'test');

            this.request = true;
            eventHub.$emit('passwordResetRequest', (this.identifier?{from:this.identifier}:undefined) );

            this.apiRequest(
                data,
                "api/v1/users",
                'passwordReset',
                {
                    'verbose': this.verbose,
                    'identifier':this.identifier
                }
            );
        },
        //Handle mail activation response
        passwordReset: function(request){
            if(this.verbose)
                console.log('passwordReset got', request);

            if(!request.from || request.from !== this.identifier)
                return;

            let response = request.content;
            if(typeof response === 'number')
                response += '';

            this.request = false;
            this.updated = true;

            let message;
            let messageType = 'error';

            switch (response){
                case 'INPUT_VALIDATION_FAILURE':
                case 'SECURITY_FAILURE':
                case 'WRONG_CSRF_TOKEN':
                    messageType = 'warning';
                    break;
                case '0':
                    messageType = 'success';
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
                alertLog(message,messageType,this.alertOptions.target,this.alertOptions.params);
            else
                eventHub.$emit('passwordResetResult',{
                    response:response,
                    message:message,
                    messageType:messageType,
                    from:(this.identifier?this.identifier:undefined)
                });
        }
    },
    template: `
        <div class="reset-password">
            <div class="reset-password-text" v-if="text.resetPassword" v-text="text.resetPassword"></div>
            <input v-if="!resetPasswordMessage && !email" type="text" v-model:value="resetMail" :placeholder="text.resetPasswordPlaceholder?text.resetPasswordPlaceholder:'Email Address'">
            <button v-if="!resetPasswordMessage" @click.prevent="sendPasswordReset" v-text="text.resetPasswordButton?text.resetPasswordButton:'Request Password Reset'"></button>
            <div v-else="" class="reset-reset-password-message" v-text="resetPasswordMessage"></div>
        </div>
    `
});