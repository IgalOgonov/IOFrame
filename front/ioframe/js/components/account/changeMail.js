Vue.component('change-mail', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //How long until the auth expires - if it does not expire, leave it as -1
        expires: {
            type: Number,
            default: -1
        },
        //Text to use for this component
        text: {
            type: Object,
            default: function(){
                return {
                    title:'New Mail:',
                    expiresIn: 'Expires in',
                    expiresUnits:'seconds',
                    expired:'Mail change expired! Please repeat request',
                    alreadyChanging: 'Already changing mail!',
                    messageFailed:'Failed to change mail!',
                    messageSucceeded:'Changed mail!',
                    send:'Change Mail',
                    responses: {
                        'INPUT_VALIDATION_FAILURE':'Input new mail!',
                        'AUTHENTICATION_FAILURE':'No longer authorized to change mail!',
                        'WRONG_CSRF_TOKEN':'Problem sending form - try again, or refresh the page.',
                        '0':'Mail changed successfully',
                        '1':'User ID does not exist (possible server error)',
                        '2':'Mail change expired',
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
            //New Mail
            input:'',
            //Whether we sent a request
            request: false,
            //whether we succeeded
            success:false,
            //How much time remains until this component expires
            authExpires:-1
        }
    },
    created:function(){
        //Register eventhub
        this.registerHub(eventHub);
        //Register events
        this.registerEvent('mailChange', this.mailChange);

        let context = this;
        this.authExpires = this.expires;
        if(this.authExpires >= 0)
            this.handleExpiry();
    },
    mounted:function(){
    },
    updated: function(){
    },
    computed:{
        validFor:function(){
            if(this.success)
                return this.text.messageSucceeded;
            else if(this.authExpires > 0)
                return this.text.expiresIn + ' ' + this.authExpires + ' '+ this.text.expiresUnits;
            else
                return this.text.expired;
        },
        finished: function(){
            return (this.authExpires !== 0) && !this.success;
        }
    },
    methods:{
        //Countdown function for expiry
        handleExpiry: function(){
            if(this.authExpires > 0){
                this.authExpires--;
                setTimeout(this.handleExpiry,1000);
            }
        },
        //Resends mail
        changeMail: function(){

            if(this.request){
                if(this.verbose)
                    console.log(this.text.alreadyChanging? this.text.alreadyChanging : 'Already changing mail!');
                return;
            }

            if(!this.input.match(/(?:[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:(2(5[0-5]|[0-4][0-9])|1[0-9][0-9]|[1-9]?[0-9]))\.){3}(?:(2(5[0-5]|[0-4][0-9])|1[0-9][0-9]|[1-9]?[0-9])|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/)){
                alertLog((this.wrongMailWarning?this.wrongMailWarning:'Please enter a valid mail to set!'),'warning');
                return;
            }

            //Data to be sent
            let data = new FormData();
            data.append('action', 'changeMail');
            data.append('newMail', this.input);

            if(this.test)
                data.append('req', 'test');

            this.request = true;
            eventHub.$emit('mailChangeRequest', (this.identifier?{from:this.identifier}:undefined) );

            this.apiRequest(
                data,
                "api/v1/users",
                'mailChange',
                {
                    'verbose': this.verbose,
                    'identifier':this.identifier
                }
            );
        },
        //Handle mail activation response
        mailChange: function(request){
            if(this.verbose)
                console.log('mailChange got', request);

            if(!request.from || request.from !== this.identifier)
                return;

            let response = request.content;
            if(typeof response === 'number')
                response += '';

            this.request = false;

            let message;
            let messageType = 'error';
            let extraParams = {};

            switch (response){
                case 'INPUT_VALIDATION_FAILURE':
                case 'AUTHENTICATION_FAILURE':
                case 'WRONG_CSRF_TOKEN':
                    messageType = 'warning';
                    break;
                case '0':
                    messageType = 'success';
                    extraParams = {autoDismiss:2000};
                    this.success = true;
                    break;
                case '1':
                    messageType = 'error';
                    break;
                case '2':
                    this.authExpires = 1;
                    messageType = 'error';
                    break;
                default:
                    message = this.test? ('Test response: '+ response) : this.text.messageFailed;
            }
            if(!message)
                message = this.text.responses[response];
            if(this.alertOptions.use)
                alertLog(message,messageType,this.alertOptions.target,{...this.alertOptions.params,...extraParams});

            eventHub.$emit('mailChangeResult',{
                newMail:this.input,
                response:response,
                message:message,
                messageType:messageType,
                from:(this.identifier?this.identifier:undefined)
            });
        }
    },
    template: `
        <div class="change-mail">
            <h4 v-if="expires > -1" v-text="validFor" :class="success? 'positive' : (authExpires>0 ? 'warning' : 'negative')"></h4>
            <input v-if="finished" name="mail" type="mail" :placeholder="text.title" v-model:value="input">
            <button v-if="finished" @click.prevent="changeMail()" v-text="text.send"></button>
        </div>
    `
});