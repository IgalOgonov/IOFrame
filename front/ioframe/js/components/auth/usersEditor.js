if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('auth-users-editor', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //Current mode - create or update
        mode: {
            type: String,
            default: 'create' //'create' / 'update'
        },
        //Item Identifier
        id: {
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
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            //Main item focused on in this component
            mainItem:{
            },
            //The type of things we are adding
            additionType:'actions',
            //Sometimes, you need to manually recompute Vue computed properties
            recompute:{
                changed:false
            },
            //Current page
            page:0,
            //Go to page
            pageToGoTo: 1,
            //Limit
            limit:25,
            //Total available results
            total: 0,
            //Items - A list of groiups/actions
            items:[],
            //Whether the item list is up to date
            upToDate: false,
            //Currently selected item
            selected:-1,
            //Filters will be custom per search, and passed through extraParams
            filters:[
            ],
            //Actions columns
            columns:[
                {
                    id:'identifier',
                    title:'Action Name'
                },
                {
                    id:'description',
                    title:'Description'
                }
            ],
            //Whether we are currently searching
            searchInitiated: false,
            //SearchList API URL
            url: document.rootURI+'api/v1/auth',
            //Whether we are currently initiating the item
            initiating: false,
            //Whether we are currently updating the item
            updating: {
                actions:false,
                groups:false
            }
        }
    },
    created:function(){
        //Register eventhub
        this.registerHub(eventHub);
        //Register events
        this.registerEvent('getResponse' ,this.handleItemGet);
        this.registerEvent('setGroupsResponse' ,this.handleGroupsSet);
        this.registerEvent('setActionsResponse' ,this.handleActionsSet);
        this.registerEvent('requestSelection', this.selectElement);
        this.registerEvent(this.identifier+'-groups-results', this.parseSearchResults);
        this.registerEvent(this.identifier+'-actions-results', this.parseSearchResults);
        this.registerEvent('goToPage', this.goToPage);

        //Global config
        if(this.configObject === undefined)
            this.configObject = {};

        this.getItemInfo();

    },
    mounted:function(){
    },
    updated: function(){
        if(!this.upToDate && !this.initiating)
            this.getItemInfo();
    },
    computed:{
        changed: function(){
            if(this.recompute.changed)
                ;//Literally nothing
            for(let item in this.mainItem.actions){
                if(this.mainItem.actions[item].add || this.mainItem.actions[item].remove)
                    return true;
            }
            for(let item in this.mainItem.groups){
                if(this.mainItem.groups[item].add || this.mainItem.groups[item].remove)
                    return true;
            }
            return false;
        }
    },
    methods:{
        //Gets the info of the main item
        getItemInfo: function(){

            if(this.initiating){
                if(this.verbose)
                    console.log('Already getting main item info!!');
                return;
            }

            this.initiating = true;

            if(this.verbose)
                console.log('Getting item information!');

            //Data to be sent
            var data = new FormData();
            data.append('action', 'getUsersWithActions');
            data.append('params', JSON.stringify({
                'id':['=',this.id]
            }));

            this.apiRequest(
                data,
                'api/v1/auth',
                'getResponse',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier,
                    ignoreCSRF:true
                }
            );
        },
        //Tries to update the user group/actions
        setItems: function(){

            if(this.initiating || this.updating.actions|| this.updating.groups){
                if(this.verbose)
                    console.log('Still getting item info or updating!');
                return;
            }

            //Find anything that needs to be sent
            let groups = {};
            let actions = {};

            for(let group in this.mainItem.groups){
                if(this.mainItem.groups[group].add)
                    groups[group] = true;
                else if(this.mainItem.groups[group].remove)
                    groups[group] = false;
            }

            for(let action in this.mainItem.actions){
                if(this.mainItem.actions[action].add)
                    actions[action] = true;
                else if(this.mainItem.actions[action].remove)
                    actions[action] = false;
            }

            if(this.verbose)
                console.log('Setting user with groups',groups,' actions ',actions);
            let data;

            //Send group modification request
            if(Object.keys(groups).length > 0){
                this.updating.groups = true;
                //Data to be sent
                data = new FormData();
                data.append('action', 'modifyUserGroups');
                if(this.test)
                    data.append('req','test');

                data.append('params', JSON.stringify({
                    'groups':groups,
                    'id':this.id
                }));

                this.apiRequest(
                    data,
                    'api/v1/auth',
                    'setGroupsResponse',
                    {
                        'verbose': this.verbose,
                        'parseJSON':true,
                        'identifier':this.identifier
                    }
                );
            }

            //Send actions modification request
            if(Object.keys(actions).length > 0){
                this.updating.actions = true;
                //Data to be sent
                data = new FormData();
                data.append('action', 'modifyUserActions');
                if(this.test)
                    data.append('req','test');

                data.append('params', JSON.stringify({
                    'actions':actions,
                    'id':this.id
                }));

                this.apiRequest(
                    data,
                    'api/v1/auth',
                    'setActionsResponse',
                    {
                        'verbose': this.verbose,
                        'parseJSON':true,
                        'identifier':this.identifier
                    }
                );
            }
        },
        handleGroupsSet: function(response){
            this.updating.groups = false;
            this.handleItemSet(response,'groups');
        },
        handleActionsSet: function(response){
            this.updating.actions = false;
            this.handleItemSet(response,'actions');
        },
        //Handles item  update
        handleItemSet: function(response, type){

            if(this.verbose)
                console.log('Received handleItemSet',response,'of type',type);

            if(this.identifier && (response.from !== this.identifier))
                return;

            if(response.from)
                response = response.content;

            if (response === 'AUTHENTICATION_FAILURE') {
                alertLog('Not authorized to modify user '+type+'! Check to see if you are logged in.','error',this.$el);
                return;
            }

            switch (response) {
                case 0:
                    alertLog('Failed to update user '+type,'error',this.$el);
                    break;
                case 1:
                    alertLog('User '+type+' updated!','success',this.$el);
                    this.setInputsAsCurrent(type);
                    break;
                default:
                    alertLog('Failed to update user '+type+', unknown response: '+response,'error',this.$el);
                    break;
            }

        },
        //Handles the response to the get request
        handleItemGet: function(response){

            if(this.verbose)
                console.log('Received handleItemGet',response);

            if(this.identifier && response.from !== this.identifier)
                return;

            if(response.from)
                response = response.content;

            if(typeof response === 'object') {
                let user = response[this.id] ? response[this.id] : {};
                this.mainItem.actions = {};
                this.mainItem.groups = {};
                let target;
                for(let groupName in user){
                    if(groupName === '@')
                        target = this.mainItem.actions;
                    else{
                        this.mainItem.groups[groupName] = {
                            'remove':false,
                            'add':false,
                            'description':null,
                            'items':{}
                        };
                        target = this.mainItem.groups[groupName].items;
                    }
                    for(let i = 0; i<user[groupName].length; i++){
                        let obj = {
                            'description':null
                        };
                        if(groupName === '@'){
                            obj.remove = false;
                            obj.add = false;
                        }
                        target[user[groupName][i]] = obj;
                    }
                }
                for(let i = 0; i<user.length; i++){
                    this.mainItem[user[i]] = {
                        description:null,
                        remove:false,
                        add:false
                    };
                }
            }

            this.initiating = false;
            this.upToDate = true;
            this.$forceUpdate();
        },
        changeAction: function(identifier){
            this.changeItem(identifier,'actions');
        },
        changeGroup: function(identifier){
            this.changeItem(identifier,'groups');
        },
        //Removes/adds an action to/from the group
        changeItem: function(identifier, type='actions'){

            if(this.initiating || this.updating.actions|| this.updating.groups){
                if(this.verbose)
                    console.log('tried to add/remove item during initiation or update!');
                return;
            }

            if(this.verbose)
                console.log('Changing '+type+' '+identifier);

            let target;
            if(type === 'actions')
                target = this.mainItem.actions;
            else
                target = this.mainItem.groups;

            if(target[identifier]){
                if(!target[identifier].add)
                    target[identifier].remove = !target[identifier].remove;
                else
                    delete target[identifier];
            }
            else{
                target[identifier] = (type === 'actions') ?
                {
                    description:null,
                    remove:false,
                    add:true
                }
                    :
                {
                    description:null,
                    remove:false,
                    add:true,
                    items:{}
                }
            }
            this.recompute.changed = !this.recompute.changed;
            this.$forceUpdate();
        },
        //Changes addition type
        changeAdditionType: function(type){
            if(this.initiating || this.updating.actions|| this.updating.groups){
                if(this.verbose)
                    console.log('tried to change addition type during initiation or update!');
                return;
            }
            this.additionType = type;
            this.searchInitiated = false;
            this.selected = -1;
            this.page = 0;
            this.pageToGoTo = 0;
            this.items = [];
        },
        //Resets inputs
        resetInputs: function(type = ''){
            if(type === 'groups' || type === '')
                for(let group in this.mainItem.groups){
                    if(this.mainItem.groups[group].add)
                        delete this.mainItem.groups[group];
                    else
                        this.mainItem.groups[group].remove = false;
                }
            if(type === 'actions' || type === '')
                for(let action in this.mainItem.actions){
                    if(this.mainItem.actions[action].add)
                        delete this.mainItem.actions[action];
                    else
                        this.mainItem.actions[action].remove = false;
                }
            this.recompute.changed = !this.recompute.changed;
            this.$forceUpdate();
        },
        //Saves inputs as the actual data (in case of a successful update or whatnot)
        setInputsAsCurrent: function(type = ''){
            if(type === 'groups' || type === '')
                for(let group in this.mainItem.groups){
                    if(this.mainItem.groups[group].add){
                        this.mainItem.groups[group].items['@'] = true;
                        this.mainItem.groups[group].add = false;
                    }
                    else if(this.mainItem.groups[group].remove){
                        delete this.mainItem.groups[group];
                    }
                }
            if(type === 'actions' || type === '')
                for(let action in this.mainItem.actions){
                    if(this.mainItem.actions[action].add){
                        this.mainItem.actions[action].add = false;
                    }
                    else if(this.mainItem.actions[action].remove){
                        delete this.mainItem.actions[action];
                    }
                }
            this.recompute.changed = !this.recompute.changed;
            this.$forceUpdate();
        },
        /* Searchlist related*/
        selectElement: function(request){

            if(!request.from || (request.from !== this.identifier+'-actions-search' && request.from !== this.identifier+'-groups-search'))
                return;

            request = request.content;

            if(this.verbose)
                console.log('Selecting item ',request);

            if(this.selected === -1)
                this.selected = request;
            else{
                this.changeItem(this.items[this.selected].identifier,this.additionType);
                this.selected = -1;
            }
        },
        goToPage: function(page){
            if(!this.initiating && (page.from === this.identifier+'-actions-search' || page.from === this.identifier+'-groups-search')){
                let newPage;
                page = page.content;

                if(page === 'goto')
                    page = this.pageToGoTo-1;

                if(page < 0)
                    newPage = Math.max(this.page - 1, 0);
                else
                    newPage = Math.min(page,Math.ceil(this.total/this.limit));

                if(this.page === newPage)
                    return;

                this.page = newPage;

                this.searchInitiated = false;

                this.selected = -1;
            }
        },
        //Parses search results returned from a search list
        parseSearchResults: function(response){
            if(this.verbose)
                console.log('Recieved response',response);

            if(!response.from || (response.from !== this.identifier+'-actions-search' && response.from !== this.identifier+'-groups-search'))
                return;

            //Either way, the items should be considered initiated
            this.items = [];
            this.searchInitiated = true;

            //In this case the response was an error code, or the page no longer exists
            if(response.content['@'] === undefined)
                return;

            this.total = (response.content['@']['#'] - 0) ;
            delete response.content['@'];

            for(let k in response.content){
                response.content[k].identifier = k;
                this.items.push(response.content[k]);
            }
        },
    },
    watch: {
    },
    template: `
    <div class="auth-users-editor">
        <div class="wrapper">

            <div class="control-buttons" v-if="changed">
                <button v-text="'Update'" @click.prevent="setItems()" class="positive-1"></button>
                <button v-text="'Reset'" @click.prevent="resetInputs()" class="cancel-1"></button>
            </div>

            <h2 v-text="'User #'+id"></h2>

            <h3> Actions </h3>
            <div class="actions">
                <div v-for="(item, action) in mainItem.actions" class="action" :class="{remove:item.remove, add:item.add}">
                    <span class="title" v-text="action"></span>
                    <span class="description" v-if="item.description" v-text="item.description"></span>
                    <button v-if="!item.remove" class="negative-2" @click.prevent="changeAction(action)">Remove</button>
                    <button v-else-if="!item.add" class="positive-2" @click.prevent="changeAction(action)">Restore</button>
                </div>
            </div>

            <h3> Groups </h3>
            <div class="groups">
                <div class="group" v-for="(item, group) in mainItem.groups" :class="{remove:item.remove, add:item.add}">

                    <div class="group-info">
                        <div class="name">
                            <span>Group name:</span>
                            <span v-text="group"></span>
                        </div>
                        <div class="description" v-if="item.description">
                            <span>Description:</span>
                            <span v-text="item.description"></span>
                        </div>
                    </div>

                    <h4 v-if="item.items && !item.add"> Group Actions </h4>
                    <div class="actions" v-if="item.items && !item.add && !item.items['@']">
                        <div v-for="(actionItem, action) in item.items" class="action">
                            <span class="title" v-text="action"></span>
                            <span class="description" v-if="action.description" v-text="action.description"></span>
                        </div>
                    </div>
                    <div v-else-if="item.items && item.items['@']">
                        <h3>Refresh user to see group actions</h3>
                    </div>

                    <button v-if="!item.remove" class="negative-2" @click.prevent="changeGroup(group)">Remove</button>
                    <button v-else-if="!item.add" class="positive-2" @click.prevent="changeGroup(group)">Restore</button>

                </div>
            </div>

            <div class="types">
                <button class="type positive-3" @click.prevent="changeAdditionType('actions')" :class="{selected:(additionType==='actions')}">Actions</button>
                <button  class="type positive-3" @click.prevent="changeAdditionType('groups')" :class="{selected:(additionType==='groups')}">Groups</button>
            </div>
            `+`
            <div v-if="additionType === 'actions'">
                <h3> Add New Actions: </h3>
                    <div is="search-list"
                     :api-url="url"
                     api-action="getActions"
                     :event-name="identifier+'-actions-results'"
                     :page="page"
                     :limit="limit"
                     :total="total"
                     :items="items"
                     :initiate="!searchInitiated"
                     :columns="columns"
                     :filters="filters"
                     :selected="selected"
                     :test="test"
                     :verbose="verbose"
                     :identifier="identifier+'-actions-search'"
                ></div>
            </div>

            <div v-if="additionType === 'groups'">
                <h3> Add New Groups: </h3>
                    <div is="search-list"
                     :api-url="url"
                     api-action="getGroups"
                     :event-name="identifier+'-groups-results'"
                     :page="page"
                     :limit="limit"
                     :total="total"
                     :items="items"
                     :initiate="!searchInitiated"
                     :columns="columns"
                     :filters="filters"
                     :selected="selected"
                     :test="test"
                     :verbose="verbose"
                     :identifier="identifier+'-groups-search'"
                ></div>
            </div>

        </div>
    </div>
    `
});