if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('auth-groups-editor', {
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
            //If we are creating a new item
            newItem:{
                identifier:'',
                description:''
            },
            //New groups to add
            newItems:{

            },
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
            //Items - [create] A list of newly created items
            items:[],
            //Whether the item list is up to date
            upToDate: this.mode == 'create',
            //Currently selected action
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
            updating: false
        }
    },
    created:function(){
        //Register eventhub
        this.registerHub(eventHub);
        //Register events
        this.registerEvent('getResponse' ,this.handleItemGet);
        this.registerEvent('setResponse' ,this.handleItemSet);
        this.registerEvent('requestSelection', this.selectElement);
        this.registerEvent('searchResults', this.parseSearchResults);
        this.registerEvent('goToPage', this.goToPage);

        //Global config
        if(this.configObject === undefined)
            this.configObject = {};

        if(this.mode === 'update')
            this.getItemInfo();

    },
    mounted:function(){
    },
    updated: function(){
        if(this.mode === 'update' && !this.upToDate && !this.initiating)
            this.getItemInfo();
    },
    computed:{
        changed: function(){
            if(this.recompute.changed)
                ;//Literally nothing
            if(this.mode === 'update'){
                for(let item in this.mainItem){
                    if(this.mainItem[item].add || this.mainItem[item].remove)
                        return true;
                }
            }
            else{
                return Object.keys(this.newItems).length > 0;
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
            data.append('action', 'getGroupActions');
            data.append('params', JSON.stringify({"group":["=",this.id]}));

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
        //Tries to update the item
        setItems: function(){

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting items info!');
                return;
            }

            //Data to be sent
            var data = new FormData();
            data.append('action', (this.mode === 'create'? 'setGroups': 'modifyGroupActions'));
            if(this.test)
                data.append('req','test');

            let params = {};
            //Calculate which groups need to be added/removed
            if(this.mode === 'update'){
                params.groupName = this.id;
                params.actions = {};
                for(let item in this.mainItem){
                    if(this.mainItem[item].add)
                        params.actions[item] = true;
                    else if(this.mainItem[item].remove)
                        params.actions[item] = false;
                }
            }
            else{
                params = {groups:this.newItems};
            }

            data.append('params', JSON.stringify(params));


            if(this.verbose)
                console.log('Setting items with parameters',params);

            this.apiRequest(
                data,
                'api/v1/auth',
                'setResponse',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        //Handles item  update
        handleItemSet: function(response){
            if(this.verbose)
                console.log('Received handleItemSet',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            if(response.from)
                response = response.content;
            if (response === 'AUTHENTICATION_FAILURE') {
                alertLog('Not authorized to '+(this.mode === 'create'? 'create':'edit')+' groups! Check to see if you are logged in.','error',this.$el);
                return;
            }

            if(this.mode === 'update'){
                switch (response) {
                    case 0:
                        alertLog('Failed to add/remove actions!','error',this.$el);
                        break;
                    case 1:
                        alertLog('Group updated!','success',this.$el);
                        this.setInputsAsCurrent();
                        break;
                    default:
                        alertLog('Unknown server response: '+response,'error',this.$el);
                }
            }
            else{
                switch (response) {
                    case 0:
                        alertLog('Failed to add/remove actions!','error',this.$el);
                        break;
                    case 1:
                        alertLog('Groups created!','success',this.$el);
                        this.resetInputs();
                        break;
                    default:
                        alertLog('Unknown server response: '+response,'error',this.$el);
                        break;
                }
            }
        },
        //Handles the response to the get request
        handleItemGet: function(response){

            if(this.verbose)
                console.log('Recieved handleItemGet',response);

            if(this.identifier && response.from !== this.identifier)
                return;

            if(response.from)
                response = response.content;

            if(typeof response === 'object') {
                let group = response[this.id] ? response[this.id] : [];
                for(let i = 0; i<group.length; i++){
                    this.mainItem[group[i]] = {
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
        //Resets inputs
        resetInputs: function(){
            //Editing an existing group
            if(this.mode === 'update')
                for(let item in this.mainItem){
                    if(this.mainItem[item].add)
                        delete this.mainItem[item];
                    else
                        this.mainItem[item].remove = false;
                }
            else{
                this.newItems = {};
            }
            this.recompute.changed = !this.recompute.changed;
            this.$forceUpdate();
        },
        //Saves inputs as the actual data (in case of a successful update or whatnot)
        setInputsAsCurrent: function(){
            //Only relevant for an update
            for(let item in this.mainItem){
                this.mainItem[item].remove = false;
                this.mainItem[item].add = false;
            }
            this.recompute.changed = !this.recompute.changed;
            this.$forceUpdate();
        },
        //Removes/adds an action to/from the group
        changeAction: function(action){
            if(this.mainItem[action]){
                if(!this.mainItem[action].add)
                    this.mainItem[action].remove = !this.mainItem[action].remove;
                else
                    delete this.mainItem[action];
            }
            else{
                this.mainItem[action] = {
                    description:null,
                    remove:false,
                    add:true
                }
            }
            this.recompute.changed = !this.recompute.changed;
            this.$forceUpdate();
        },
        //Removes a single item
        removeItem: function(item){
            delete this.newItems[item];
            this.recompute.changed = !this.recompute.changed;
            this.$forceUpdate();
        },
        //Adds a single item
        addItem: function(){
            this.newItems[this.newItem.identifier] = this.newItem.description ? this.newItem.description : null;
            this.newItem = {
                identifier:'',
                description:''
            };
            this.recompute.changed = !this.recompute.changed;
            this.$forceUpdate();
        },
        /* Searchlist related*/
        selectElement: function(request){

            if(!request.from || request.from !== this.identifier+'-search')
                return;

            request = request.content;

            if(this.verbose)
                console.log('Selecting item ',request);

            if(this.selected === -1)
                this.selected = request;
            else{
                this.changeAction(this.items[this.selected].identifier);
                this.selected = -1;
            }
        },
        goToPage: function(page){
            if(!this.initiating && page.from == this.identifier+'-search'){
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

            if(!response.from || response.from !== this.identifier+'-search')
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
        'newItem.identifier':function(newValue,oldValue){
            if(newValue && (!this.newItem.identifier.match(/^[\w ]+$/) || this.newItem.identifier[0].match(/\d/))){
                alertLog('Groups must be only letters, numbers and spaces, and cannot start with a number.','warning',this.$el);
                this.newItem.identifier = oldValue;
            }
        }
    },
    template: `
    <div class="auth-groups-editor">
        <div class="wrapper">

            <div class="control-buttons" v-if="changed">
                <button v-text="mode==='update' ? 'Update' : 'Create'" @click.prevent="setItems()" class="positive-1"></button>
                <button v-text="'Reset'" @click.prevent="resetInputs()" class="cancel-1"></button>
            </div>

            <div class="update" v-if="mode==='update'">
                <h2 v-text="id"></h2>
                <h3> Actions </h3>
                <div class="actions">
                    <div v-for="(item, action) in mainItem" class="action" :class="{remove:item.remove, add:item.add}">
                        <span class="title" v-text="action"></span>
                        <span class="description" v-if="item.description" v-text="item.description"></span>
                        <button v-if="!item.remove" class="negative-2" @click.prevent="changeAction(action)">Remove</button>
                        <button v-else-if="!item.add" class="positive-2" @click.prevent="changeAction(action)">Restore</button>
                    </div>
                </div>
                <h3> Add New Actions: </h3>
                    <div is="search-list"
                     :api-url="url"
                     api-action="getActions"
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
                     :identifier="identifier+'-search'"
                ></div>
            </div>

            <div class="create" v-if="mode==='create'">

                <div class="items" >

                    <div
                    class="item"
                    v-for="(description, group) in newItems"
                    >
                        <div class="title">
                            <span v-text="group"> </span>
                        </div>
                        <div class="desc" v-if="description">
                            <span v-text="description"> </span>
                        </div>
                        <button v-text="'X'" @click.prevent="removeItem(group)" class="negative-2"></button>
                    </div>

                </div>

                <div class="new">
                    <h2>Add a new group:</h2>
                    <input type="text" class="title" v-model:value="newItem.identifier" placeholder="group">
                    <textarea class="value" v-model:value="newItem.description" placeholder="description"></textarea>
                    <button v-text="'+'" @click.prevent="addItem()" class="positive-2"></button>
                </div>

            </div>

        </div>
    </div>
    `
});