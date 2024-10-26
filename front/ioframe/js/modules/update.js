if(eventHub === undefined)
    var eventHub = new Vue();

var users = new Vue({
    el: '#update',
    name: 'update',
    mixins:[sourceURL,eventHubManager,IOFrameCommons],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            text:{
                serverError:'Failed to get update info!',
                upToDate:'System is up to date!',
                noUpdatePath:'System cannot be updated farther! Install the next version.',
                currentVersion:'Current Version',
                nextVersion:'Next Version',
                update:'Update',
                messageFailed:'Unknown server response!',
                responses: {
                    'WRONG_CSRF_TOKEN':'Problem sending form - try again, or refresh the page.',
                    '-1':'Catastrophic failure, failed an updated AND failed to roll back! Contact administrator!',
                    '0':'Update Succeeded!',
                    '1':'Failed to update. Try again, or contact administrator.',
                    '2':'No farther updates available.',
                }
            },
            //Main items
            updatesInfo: {},
            identifier:'update',
            //Whether we are currently loading
            initiated: false,
            //Whether we have sent an update request and haven't received it back yet
            request: false,
            verbose:false,
            test:false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        this.registerEvent('getVersionInfo', this.parseVersionInfo);
        this.registerEvent('updateSystem', this.parseSystemUpdate);

        this.getUpdateInfo();
        if(!this.configObject.update)
            this.configObject.update = {};

        if(this.configObject.update.text)
            this.text = JSON.parse(JSON.stringify(this.configObject.update.text));
    },
    computed:{
        gotInfo:function(){
            return Object.keys(this.updatesInfo).length;
        }
    },
    watch:{
    },
    methods:{
        //Parses System Update response
        parseSystemUpdate: function(request){
            if(this.verbose)
                console.log('parseSystemUpdate got', request);

            if(!request.from || request.from !== this.identifier)
                return;

            let response = request.content;
            if(this.test){
                alertLog(request.content,'info');
                return;
            }

            if(typeof response === 'number')
                response += '';

            this.request = false;

            let message;
            let messageType = this.test?'info':'error';
            let alertParams = {};

            switch (response){
                case 'INPUT_VALIDATION_FAILURE':
                case 'WRONG_CSRF_TOKEN':
                    messageType = 'warning';
                    break;
                case '0':
                    messageType = 'success';
                    alertParams = {autoDismiss:2000};
                    this.updatesInfo.current = this.updatesInfo.next;
                    this.updatesInfo.next = this.updatesInfo.versions[this.updatesInfo.next] ?
                        this.updatesInfo.versions[this.updatesInfo.next] : null;
                    break;
                case '1':
                    messageType = 'warning';
                    break;
                case '2':
                    messageType = 'info';
                    break;
                default:
                    message = this.test? ('Test response: '+ response) : this.text.messageFailed;
            }
            if(!message){
                message = this.text.responses[response];
            }
            alertLog(message,messageType,this.$el,alertParams);
        },
        //Tries to run the system update
        update: function(){
            if(this.verbose)
                console.log('Attempting to update to '+this.updatesInfo.next);

            //Data to be sent
            let data = new FormData();
            data.append('action', 'update');
            data.append('maintenance', '1');

            if(this.test)
                data.append('req', 'test');

            this.request = true;

            this.apiRequest(
                data,
                "api/_update",
                'updateSystem',
                {
                    'verbose': this.verbose,
                    'identifier':this.identifier
                }
            );
        },
        //Parses the version info
        parseVersionInfo: function(response){
            if(this.verbose)
                console.log('Received response',response);

            if(!response.from || response.from !== this.identifier)
                return;

            //Either way, the items should be considered initiated
            this.initiated = true;

            Vue.set(this,'updatesInfo',response.content);
        },
        //Gets update info
        getUpdateInfo: function(){

            //Data to be sent
            let data = new FormData();
            data.append('action', 'getVersionsInfo');

            if(this.verbose)
                console.log('Getting my user.');

            this.apiRequest(
                data,
                'api/_update',
                'getVersionInfo',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        }
    },
    template:`
    <div id="update" class="main-app">
        <div class="loading-cover" v-if="!initiated">
        </div>
        <h4 class="server-error message message-error-2" v-else-if="initiated && !gotInfo" v-html="text.serverError"></h4>
        <h4 class="up-to-date message message-info-2" v-else-if="!updatesInfo.next && (updatesInfo.current === updatesInfo.available)" v-html="text.upToDate"></h4>
        <h4 class="no-update-path message message-warning-2" v-else-if="!updatesInfo.next" v-html="text.noUpdatePath"></h4>
        <form class="update" v-else="">
        
            <div class="current-version">
                <span class="title" v-text="text.currentVersion"></span> 
                <span class="version" v-text="updatesInfo.current"></span>
            </div>
            
            <div class="next-version">
                <span class="title" v-text="text.nextVersion"></span>
                <span class="version" v-text="updatesInfo.next"></span>
            </div>
            
            <button class="positive-1" @click.prevent="update()" v-text="text.update"></button>
            
        </form>
    </div>
    `
});