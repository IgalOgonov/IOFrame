if(eventHub === undefined)
    var eventHub = new Vue();

var users = new Vue({
    el: '#account',
    name: 'account',
    mixins:[sourceURL,eventHubManager,IOFrameCommons],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            rootURI:document.rootURI,
            //SearchList API
            url: document.pathToRoot+ 'api/users',
            user: {
                id:-1,
                username:'',
                email:'',
                phone:'',
                active:'',
                require2FA:'',
                has2FAApp:'',
                rank:'',
                created:''
            },
            text:{
                notLoggedIn:'You are not logged in!',
                goToLogin:'Log In',
                serverError:'Failed to get user info!',
                user: {
                    id:'ID',
                    username:'Username',
                    email:'Email',
                    phone:'Phone',
                    active:'Email Verified?',
                    generalYes:'Yes',
                    generalNo:'No',
                    require2FA:'Requires Two-Factor Authentication (2FA)?',
                    has2FAApp:'Paired with Two-Factor Authentication (2FA) app?',
                    rank:'User Rank',
                    created:'Date Created'
                }
            },
            //Whether we got a valid user
            initiated: false,
            //Whether we are currently loading
            initiating: true,
            //Whether we are currently updating
            updating: false,
            //Whether we are logged in
            loggedIn:document.loggedIn,
            identifier:'account',
            verbose:false,
            test:false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        this.registerEvent('getMyUser', this.parseUser);
        this.registerEvent('regConfirmResult', this.parseComponentResult);
        this.registerEvent('mailChangeResult', this.parseComponentResult);
        this.registerEvent('toggle2FAResult', this.parseComponentResult);
        this.registerEvent('twoFactorAppResponse', this.parseComponentResult);



        if(!this.configObject.account)
            this.configObject.account = {};

        if(!this.configObject.account.mail)
            this.configObject.account.mail = {};

        if(!this.configObject.account.password)
            this.configObject.account.password = {};

        if(this.configObject.account.text)
            this.text = JSON.parse(JSON.stringify(this.configObject.account.text));

        if(document.loggedIn)
            this.getMyUser();
        else
            this.initiating = false;
    },
    computed:{
        parsedDate: function(){
            if(!this.user.created)
                return '?';
            else
                return timeStampToReadableFullDate(this.user.created);
        }
    },
    mounted:function(){
    },
    watch:{
    },
    methods:{
        //Parses result from a componnent
        parseComponentResult:function(request){
            if(this.verbose)
                console.log('Got parseComponentResult',request)
            switch(request.from){
                case 'change-mail':
                    if(request.response === '0')
                        this.user.email = request.newMail;
                    break;
                case 'activate-account':
                    if(request.response === '0')
                        this.user.active = true;
                    break;
                case 'toggle-2fa':
                    if(request.response === '0')
                        this.user.require2FA = !this.user.require2FA;
                    break;
                case 'add-2fa':
                    if(request.response === '0'){
                        this.user.require2FA = true;
                        this.user.has2FAApp = true;
                        setTimeout(function(){
                            location.reload();
                        },3000);
                    }
                    break;
                default:
                    return;
            }
        },
        //Tries to get user
        getMyUser: function(){

            //Data to be sent
            let data = new FormData();
            data.append('action', 'getMyUser');

            if(this.verbose)
                console.log('Getting my user.');

            this.apiRequest(
                data,
                'api/v1/users',
                'getMyUser',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        //Parses the user results
        parseUser: function(response){
            if(this.verbose)
                console.log('Received response',response);

            if(!response.from || response.from !== this.identifier)
                return;

            //Either way, the items should be considered initiated
            this.initiating = false;

            response = response.content;

            if(typeof response === 'object'){
                Vue.set(this,'user',JSON.parse(JSON.stringify(response)));
                this.initiated = true;
                return;
            }
            switch (response){
                case -1:
                    alertLog('Server error when getting user!','error',this.$el);
                    break;
                case 1:
                    this.loggedIn = false;
                    break;
                default:
                    alertLog('Unknown response: '+response,'error',this.$el);
            }

        },
    },
    template:`
    <div id="account" class="main-app">
    
        <div class="loading-cover" v-if="initiating || updating">
        </div>
        
        <div v-if="!initiating">
            
            <div v-if="!loggedIn" class="not-logged-in" >
                <div
                        is="user-login"
                        :has-remember-me="configObject.login.hasRememberMe"
                        :text="text.login? text.login : undefined"
                        :test="test"
                        :verbose="verbose"
                ></div>
                <div 
                v-if="!configObject.account.password.canReset || (configObject.account.password.expires<1)" 
                is="reset-password"
                :test="test"
                :verbose="verbose"
                identifier="reset-password-forgot"></div>
                <div 
                v-else="" 
                is="change-password"
                :expires="configObject.account.password.expires"
                :test="test"
                :verbose="verbose"
                identifier="change-password-forgot"></div>
            </div>
            
            <div v-else-if="!initiated" class="message message-error-2" v-text="text.serverError"></div>
            
            <form class="account-info" v-else="">
                <div class="id">
                    <span class="title" v-text="text.user.id"></span><span class="value" v-text="user.id"></span>
                </div>
                <div class="username">
                    <span class="title" v-text="text.user.username"></span><span class="value" v-text="user.username"></span>
                    <div 
                    v-if="!configObject.account.password.canReset || (configObject.account.password.expires<1)" 
                    is="reset-password"
                    :email="user.email"
                    :test="test"
                    :verbose="verbose"
                    identifier="reset-password-existing"></div>
                    <div 
                    v-else="" 
                    is="change-password"
                    :expires="configObject.account.password.expires"
                    :test="test"
                    :verbose="verbose"
                    identifier="change-password-existing"></div>
                </div>
                <div class="rank">
                    <span class="title" v-text="text.user.rank"></span><span class="value" v-text="user.rank"></span>
                </div>
                <div class="created">
                    <span class="title" v-text="text.user.created"></span><span class="value" v-text="parsedDate"></span>
                </div>
                <div class="email">
                    <span class="title" v-text="text.user.email"></span><span class="value" v-text="user.email"></span>
                    <div 
                    v-if="!configObject.account.mail.canReset || (configObject.account.mail.expires<1)" 
                    is="reset-mail"
                    :email="user.email"
                    :test="test"
                    :verbose="verbose"
                    identifier="reset-mail"></div>
                    <div 
                    v-else="" 
                    is="change-mail"
                    :expires="configObject.account.mail.expires"
                    :test="test"
                    :verbose="verbose"
                    identifier="change-mail"></div>
                </div>
                <div class="active" :class="{positive:user.active,negative:!user.active}">
                    <span class="title" v-text="text.user.active"></span><span class="value" v-text="user.active? text.user.generalYes : text.user.generalNo"></span>
                    <div 
                    v-if="!user.active" 
                    is="activate-account"
                    :email="user.email"
                    :test="test"
                    :verbose="verbose"
                    identifier="activate-account"></div>
                </div>
                <div class="phone">
                    <span class="title" v-text="text.user.phone"></span><span class="value" v-text="user.phone?user.phone:'-'"></span>
                    <div class="phone-change">
                        <!--TODO Add phone change component-->
                    </div>
                </div>
                <div class="require2FA" :class="{positive:user.require2FA,negative:!user.require2FA}">
                    <span class="title" v-text="text.user.require2FA"></span><span class="value" v-text="user.require2FA? text.user.generalYes : text.user.generalNo"></span>
                    <div 
                    v-if="configObject.account.twoFactor.hasApp || configObject.account.twoFactor.hasPhone" 
                    is="toggle-2fa"
                    :initial-state="user.require2FA"
                    :test="test"
                    :verbose="verbose"
                    identifier="toggle-2fa"></div>
                </div>
                <div class="has2FAApp" :class="{positive:user.has2FAApp,negative:!user.has2FAApp}">
                    <span class="title" v-text="text.user.has2FAApp"></span><span class="value" v-text="user.has2FAApp? text.user.generalYes : text.user.generalNo"></span>
                    <div is="">
                    </div>
                    <div 
                    v-if="!user.has2FAApp"
                    is="add-2fa"
                    :test="test"
                    :verbose="verbose"
                    identifier="add-2fa"></div>
                </div>
            </form>
        </div>
    
    </div>
    `
});