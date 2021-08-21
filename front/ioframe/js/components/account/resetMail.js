if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('reset-mail', {
    mixins: [sourceURL,parseLimit,eventHubManager,IOFrameCommons],
    props: {
        //Text to use for this component
        text: {
            type: Object,
            default: function(){
                return {
                    resetMail:'If you have forgotten your mail, you can request a mail reset mail',
                    resetMailButton:'Request Mail Reset',
                    resetMailPlaceholder:'Email Address',
                    sendingMessage:'Sending mail reset mail',
                    messageSent:'Mail reset mail sent!',
                    messageFailed:'Mail reset mail failed to send.',
                    wrongMailWarning:'Please enter a valid email to reset!',
                    alreadyResetting:'Already resetting mail!',
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
                        '0':'Mail reset mail has been sent!',
                        '1':'No matching mail found in this system!',
                    }
                }
            }
        },
        //Will either display result as an alert (default), or send the parsed event
        alertResult: {
            type: Boolean,
            default: true
        },
        //If provided, will reset using this email
        email: {
            type: String,
            default: ''
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
        this.registerEvent('mailReset', this.mailReset);
    },
    mounted:function(){
    },
    updated: function(){
    },
    computed:{
        resetMailMessage:function(){
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
            if(!this.email && !re.test(String(this.resetMail).toLowerCase())){
                alertLog((this.wrongMailWarning?this.wrongMailWarning:'Please enter a valid email to reset!'),'warning');
                return;
            }

            //Data to be sent
            let data = new FormData();
            data.append('action', 'mailReset');
            data.append('mail', this.email? this.email : this.resetMail);

            if(this.verbose)
                console.log('Sending mail reset request for ',this.email? this.email : this.resetMail);

            if(this.test)
                data.append('req', 'test');

            this.request = true;
            eventHub.$emit('mailResetRequest', (this.identifier?{from:this.identifier}:undefined) );

            this.apiRequest(
                data,
                "api/v1/users",
                'mailReset',
                {
                    'verbose': this.verbose,
                    'identifier':this.identifier
                }
            );
        },
        //Handle mail activation response
        mailReset: function(request){
            if(this.verbose)
                console.log('mailReset got', request);

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
                case 'WRONG_CSRF_TOKEN':
                case 'SECURITY_FAILURE':
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
            if(this.alertResult)
                alertLog(message,messageType);
            else
                eventHub.$emit('mailResetResult',{
                    response:response,
                    message:message,
                    messageType:messageType,
                    from:(this.identifier?this.identifier:undefined)
                });
        }
    },
    template: `
        <div class="reset-mail">
            <div class="reset-mail-text" v-if="text.resetMail" v-text="text.resetMail"></div>
            <div class="resend" v-if="!resetMailMessage">
                <input v-if="!email" type="text" v-model:value="resetMail" :placeholder="text.reactivatePlaceholder?text.reactivatePlaceholder:'Email Address'">
                <button @click.prevent="sendMailReset" v-text="text.resetMailButton?text.resetMailButton:'Request Mail Reset'"></button>
            </div>
            <div v-else="" class="reset-reset-mail-message" v-text="resetMailMessage"></div>
        </div>
    `
});