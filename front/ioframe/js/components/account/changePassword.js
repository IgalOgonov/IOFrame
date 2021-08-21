if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('change-password', {
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
                    title:'New Password:',
                    secondary: "Must be 8-64 characters long<br> Must include letters and numbers<br>Can include special characters except '>' and '<'",
                    expiresIn: 'Expires in',
                    expiresUnits:'seconds',
                    expired:'Password change expired! Please repeat request',
                    alreadyChanging: 'Already changing password!',
                    messageFailed:'Failed to change password!',
                    messageSucceeded:'Changed password!',
                    send:'Change Password',
                    responses: {
                        'INPUT_VALIDATION_FAILURE':'Input new password!',
                        'AUTHENTICATION_FAILURE':'No longer authorized to change password!',
                        'WRONG_CSRF_TOKEN':'Problem sending form - try again, or refresh the page.',
                        '0':'Password changed successfully',
                        '1':'User ID does not exist (possible server error)',
                        '2':'Password change expired',
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
            //New Password
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
        this.registerEvent('passwordChange', this.passwordChange);

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
        //Resends password
        changePassword: function(){

            if(this.request){
                if(this.verbose)
                    console.log(this.text.alreadyChanging? this.text.alreadyChanging : 'Already changing password!');
                return;
            }

            const validPassword = this.input.length >=8 && this.input.length <=64 && this.input.match(/[a-zA-Z]/) && this.input.match(/[0-9]/) && !this.input.match(/></);
            if(!validPassword){
                alertLog((this.wrongMailWarning?this.wrongMailWarning:'Please enter a valid password to set!'),'warning');
                return;
            }

            //Data to be sent
            let data = new FormData();
            data.append('action', 'changePassword');
            data.append('newPassword', this.input);

            if(this.test)
                data.append('req', 'test');

            this.request = true;
            eventHub.$emit('passwordChangeRequest', (this.identifier?{from:this.identifier}:undefined) );

            this.apiRequest(
                data,
                "api/v1/users",
                'passwordChange',
                {
                    'verbose': this.verbose,
                    'identifier':this.identifier
                }
            );
        },
        //Handle mail activation response
        passwordChange: function(request){
            if(this.verbose)
                console.log('passwordChange got', request);

            if(!request.from || request.from !== this.identifier)
                return;

            let response = request.content;
            if(typeof response === 'number')
                response += '';

            this.request = false;

            let message;
            let messageType = 'error';

            switch (response){
                case 'INPUT_VALIDATION_FAILURE':
                case 'AUTHENTICATION_FAILURE':
                case 'WRONG_CSRF_TOKEN':
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
            if(this.alertResult)
                alertLog(message,messageType);

            eventHub.$emit('passwordChangeResult',{
                newPassword:this.input,
                response:response,
                message:message,
                messageType:messageType,
                from:(this.identifier?this.identifier:undefined)
            });
        }
    },
    template: `
        <div class="change-password">
            <h4 v-if="expires > -1" v-text="validFor" :class="success? 'positive' : (authExpires>0 ? 'warning' : 'negative')"></h4>
            <label v-if="finished" for="password" v-text="text.title"></label>
            <input v-if="finished" name="password" type="password" v-model:value="input">
            <h4 v-if="finished" v-html="text.secondary"></h4>
            <button v-if="finished" @click.prevent="changePassword()" v-text="text.send"></button>
        </div>
    `
});