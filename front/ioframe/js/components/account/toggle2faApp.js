if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('toggle-2fa', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //Initial State - on or off
        initialState: {
            type: Boolean,
            default: false
        },
        //Text to use for this component
        text: {
            type: Object,
            default: function(){
                return {
                    title:'Toggle Two Factor Authentication requirement for your account '+(this.initialState?'off':'on'),
                    alreadyChanging: 'Already toggling 2FA!',
                    messageFailed:'Failed to toggle 2FA!',
                    messageSucceeded:'Toggled 2FA!',
                    responses: {
                        'WRONG_CSRF_TOKEN':'Problem sending form - try again, or refresh the page.',
                        'NO_SUPPORTED_2FA':'User has no 2FA methods available, cannot turn on!',
                        'AUTHENTICATION_FAILURE':'You are no longer logged in!',
                        '0':'Two Factor settings updated!',
                        '1':'User ID does not exist (possible server error)'
                        /*Codes 2-3 should never appear in this case, so they are unknown server errors*/
                    }
                }
            }
        },
        //Will either display result as an alert (default), or send the parsed event
        alertResult: {
            type: Boolean,
            default: true
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
        this.registerEvent('toggle2FA', this.toggle2FAResponse);

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
        send:function (){
            return 'Toggle 2FA '+(this.initialState?'off':'on');
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
        //Toggles 2FA
        toggle2FA: function(){

            if(this.request){
                if(this.verbose)
                    console.log(this.text.alreadyChanging? this.text.alreadyChanging : 'Already toggling 2FA!');
                return;
            }

            //Data to be sent
            let data = new FormData();
            data.append('action', 'require2FA');
            data.append('require2FA', this.initialState? '0' : '1');

            if(this.test)
                data.append('req', 'test');

            this.request = true;
            eventHub.$emit('toggle2FARequest', (this.identifier?{from:this.identifier}:undefined) );

            this.apiRequest(
                data,
                "api/v1/users",
                'toggle2FA',
                {
                    'verbose': this.verbose,
                    'identifier':this.identifier
                }
            );
        },
        //Handle 2FA toggle
        toggle2FAResponse: function(request){
            if(this.verbose)
                console.log('toggle2FA got', request);

            if(!request.from || request.from !== this.identifier)
                return;

            let response = request.content;
            if(typeof response === 'number')
                response += '';

            this.request = false;

            let message;
            let messageType = 'error';

            switch (response){
                case 'AUTHENTICATION_FAILURE':
                case 'WRONG_CSRF_TOKEN':
                case 'NO_SUPPORTED_2FA':
                    messageType = 'warning';
                    break;
                case '0':
                    messageType = 'success';
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
            message = this.text.responses[response]? this.text.responses[response] : this.text.messageFailed;
            if(this.alertResult)
                alertLog(message,messageType);
            eventHub.$emit('toggle2FAResult',{
                response:response,
                message:message,
                messageType:messageType,
                from:(this.identifier?this.identifier:undefined)
            });
        }
    },
    template: `
        <div class="toggle-2fa" :class="initialState?'on':'off'">
            <h4 class="toggle-2fa-text" v-text="text.title" ></h4>
            <button @click.prevent="toggle2FA()" v-text="send"></button>
        </div>
    `
});