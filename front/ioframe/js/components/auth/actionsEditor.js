if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('auth-actions-editor', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //Current mode - create (equals 'set')
        mode: {
            type: String,
            default: 'create'
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
            items:{
            },
            newItem:{
                identifier:'',
                description:''
            },
            recompute: {
                items:false
            },
            //Whether we are currently updating the item
            updating: false
        }
    },
    created:function(){
        //Register eventhub
        this.registerHub(eventHub);
        //Register events
        this.registerEvent('setResponse' ,this.handleItemSet);
    },
    mounted:function(){
    },
    updated: function(){
    },
    computed:{
        hasNewItems: function(){
            if(this.recompute.items)
                ;//Literally nothing
            return Object.keys(this.items).length > 0;
        }
    },
    methods:{
        //Tries to update the item
        setItems: function(){

            if(this.updating){
                if(this.verbose)
                    alertLog('Still updating item info!','info',this.$el);
                return;
            }

            this.updating = true;

            //Data to be sent
            var data = new FormData();
            data.append('action', 'setActions');
            data.append('params', JSON.stringify({actions:this.items}));
            if(this.test)
                data.append('req','test');

            if(this.verbose)
                console.log('Setting item with parameters',JSON.parse(JSON.stringify(this.items)));

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
                console.log('Recieved handleItemSet',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            //We are done updating
            this.updating = false;

            if(response.from)
                response = response.content;

            if (response === 'AUTHENTICATION_FAILURE') {
                alertLog('Not authorized to create actions! Check to see if you are logged in.','error',this.$el);
                return;
            }

            switch (response) {
                case 0:
                    alertLog('Failed to set !','error',this.$el);
                    break;
                case 1:
                    alertLog('Actions created!','success',this.$el);
                    this.resetInputs();
                    break;
                default:
                    alertLog('Unknown response '+response,'error',this.$el);
                    break;
            }

        },
        //Resets inputs
        resetInputs: function(){
            this.items = {};
            this.newItem = {
                identifier:'',
                description:''
            };
            this.recompute.items = !this.recompute.items;
        },
        //Removes a single item
        removeItem: function(item){
            delete this.items[item];
            this.recompute.items = !this.recompute.items;
        },
        //Adds a single item
        addItem: function(){
            this.items[this.newItem.identifier] = this.newItem.description ? this.newItem.description : null;
            this.newItem = {
                identifier:'',
                description:''
            };
            this.recompute.items = !this.recompute.items;
        },
    },
    watch: {
        'newItem.identifier':function(newValue,oldValue){
            this.newItem.identifier = this.newItem.identifier.toUpperCase();
            if(newValue && !this.newItem.identifier.match(/^[A-Z_]+$/)){
                alertLog('Actions must be UPPERCASE, and SEPARATED_BY_UNDERSCORES','warning',this.$el);
                this.newItem.identifier = oldValue;
            }
        }
    },
    template: `
    <div class="auth-actions-editor">
        <div class="wrapper">

            <div class="items" >

                <div
                class="item"
                v-for="(description, action) in items"
                >
                    <div class="title">
                        <span v-text="action"> </span>
                    </div>
                    <div class="desc" v-if="description">
                        <span v-text="description"> </span>
                    </div>
                    <button v-text="'X'" @click.prevent="removeItem(action)" class="negative-2"></button>
                </div>

            </div>

            <div class="new">
                <h2>Add a new action:</h2>
                <input type="text" class="title" v-model:value="newItem.identifier" placeholder="action">
                <textarea class="value" v-model:value="newItem.description" placeholder="description"></textarea>
                <button v-text="'+'" @click.prevent="addItem()" class="positive-2"></button>
            </div>

            <div class="control-buttons" v-if="hasNewItems">
                <button v-text="'Create'" @click.prevent="setItems()" class="positive-1"></button>
                <button v-text="'Reset'" @click.prevent="resetInputs()" class="cancel-1"></button>
            </div>

        </div>
    </div>
    `
});