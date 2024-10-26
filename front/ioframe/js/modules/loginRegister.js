if(eventHub === undefined)
    var eventHub = new Vue();

var loginRegister = new Vue({
    el: '#login-register',
    name: 'Login or Register',
    mixins:[eventHubManager,IOFrameCommons],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            loggedIn: document.ioframe.loggedIn,
            //Modes, and array of available operations in each mode
            modes: {
                login:{
                    title:'Please log in',
                    hasRememberMe:true,
                    switchToRegistration: true
                },
                logout:{
                    title:'Welcome! You are logged in.'
                },
                register:{
                    title:'Register',
                    canHaveUsername:true,
                    requiresUsername:true,
                    inviteToken:null,
                    inviteMail:null
                }
            },
            currentMode:'login',
            verbose:false,
            test:false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        this.registerEvent('loginResponse', this.parseLoginResponse);
        this.registerEvent('registrationResponse', this.parseRegistrationResponse);

        if(!this.configObject.login)
            this.configObject.login = [];
        if(this.configObject.login.hasRememberMe !== undefined)
            this.modes.login.hasRememberMe = this.configObject.login.hasRememberMe;
        if(this.configObject.login.switchToRegistration !== undefined)
            this.modes.login.switchToRegzistration = this.configObject.login.switchToRegistration;


        if(!this.configObject.register)
            this.configObject.register = [];
        if(this.configObject.register.canHaveUsername !== undefined)
            this.modes.register.canHaveUsername = this.configObject.register.canHaveUsername;
        if(this.configObject.register.requiresUsername !== undefined)
            this.modes.register.requiresUsername = this.configObject.register.requiresUsername;
        if(this.configObject.register.inviteToken !== undefined)
            this.modes.register.inviteToken = this.configObject.register.inviteToken;
        if(this.configObject.register.inviteMail !== undefined)
            this.modes.register.inviteMail = this.configObject.register.inviteMail;

        if(this.loggedIn)
            this.currentMode = 'logout';
        else
            this.currentMode = this.modes.register.inviteToken?'register':'login';
    },
    computed:{
    },
    watch:{
    },
    methods:{
        parseLoginResponse: function(response){
            let responseBody = response.body;
            let responseType = response.type;
            /*This will display messages that the API could return
             **/
            switch(responseBody){
                case 'INPUT_VALIDATION_FAILURE':
                    responseBody = 'User login failed - incorrect input';
                    break;
                case 'AUTHENTICATION_FAILURE':
                    responseBody = 'User login failed - authentication failure';
                    break;
                case 'WRONG_CSRF_TOKEN':
                    responseBody = 'User login failed - CSRF token wrong';
                    break;
                case '-1':
                    responseBody = 'Server Error!';
                    break;
                case '0':
                    responseBody = 'User logged in successfully!';
                    break;
                case '1':
                    responseBody = 'User login failed - username and password combination is wrong!';
                    break;
                case '2':
                    responseBody = 'User login failed - expired auto-login';
                    break;
                case '3':
                    responseBody = 'User login failed - login type not allowed  (why you using this API?!)';
                    break;
                case '4':
                    return;
                case '5':
                    responseBody = '2FA code is incorrect.';
                    break;
                case '6':
                    responseBody = '2FA code expired.';
                    break;
                case '7':
                    responseBody = 'User does not have 2FA method set up.';
                    break;
                case '8':
                    responseBody = 'System does not support chosen 2FA method!';
                    break;
                case '9':
                    responseBody = 'User is both suspicious and banned, so 2FA is not allowed. If this persists, change your password.';
                    break;
                default:
            }
            alertLog(responseBody, responseType);
        },
        parseRegistrationResponse: function(response){
            let responseBody = response.body;
            let responseType = response.type;
            let alertOptions = {};
            /*This will display messages that the API could return
             **/
            switch(responseBody){
                case 'INPUT_VALIDATION_FAILURE':
                    responseBody = 'User creation failed - incorrect input';
                    break;
                case 'AUTHENTICATION_FAILURE':
                    responseBody = 'User creation failed - authentication failure';
                    break;
                case 'WRONG_CSRF_TOKEN':
                    responseBody = 'User creation failed - CSRF token wrong';
                    break;
                case '0':
                    responseBody = 'User created successfully!';
                    alertOptions = {autoDismiss:2000};
                    break;
                case '1':
                    responseBody = 'User creation failed - username already in use!';
                    break;
                case '2':
                    responseBody = 'User creation failed - email already in use!';
                    break;
                case '3':
                    responseBody = 'User creation failed - server error!';
                    break;
                default:
            }
            alertLog(responseBody, responseType, document.body, alertOptions);
        }
    },
    template:`
    <div class="main-app" id="login-register" :mode="currentMode">
        <h1 v-text="modes[currentMode].title"></h1>
    
        <div
            is="user-login"
            v-if="currentMode==='login'"
            :has-remember-me="modes.login.hasRememberMe"
            :test="test"
            :verbose="verbose"
            >
        </div>
        <div
            is="user-logout"
            v-if="currentMode==='logout'"
            :test="test"
            :verbose="verbose"
            >
        </div>
    
        <div
            is="user-registration"
            :can-have-username="modes.register.canHaveUsername"
            :requires-username="modes.register.requiresUsername"
            :invite-token="modes.register.inviteToken"
            :invite-mail="modes.register.inviteMail"
             v-if="currentMode==='register'"
             :test="test"
             :verbose="verbose"
            >
        </div>
    
        <button v-if="modes.login.switchToRegistration && currentMode=='login' && configObject.register.canRegister" v-text="'Register Instead'" @click.prevent="currentMode='register'"></button>
        <button v-if="modes.login.switchToRegistration && currentMode=='register'" v-text="'Login Instead'" @click.prevent="currentMode='login'"></button>
    
    </div>
    `
});