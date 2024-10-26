Vue.component('settings-editor', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //Item Identifier
        id: {
            type: String,
            default: ''
        },
        //The settings item from getSettingsMeta
        item: {
            type: Object,
            default: function(){
                return null;
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
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            //Main item focused on in this component
            mainItem:{
            },
            //Sometimes, you need to manially recompute Vue computed properties
            recompute:{
                changed:false
            },
            newSetting:{
                key:'',
                value:''
            },
            //The setting we are currently changing
            changing: '',
            //Whether we were deleting
            deleted: false,
            //Whether we are SURE we want to delete
            deletionPrompt: false,
            //Whether the item is up to date
            upToDate: false,
            //Whether we are currently updating the item
            initiating: false
        }
    },
    created:function(){
        //Register eventhub
        this.registerHub(eventHub);
        //Register events
        this.registerEvent('getResponse' ,this.handleItemGet);
        this.registerEvent('setResponse' ,this.handleItemSet);

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
                ;//Just to recompute

            if(this.mainItem)
                for(let index in this.mainItem){
                    let settingItem = this.mainItem[index];
                    if(settingItem.original !== settingItem.current)
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

            /* ---- TODO Add validity checks if relevant ---- */

            this.initiating = true;

            if(this.verbose)
                console.log('Getting item information!');

            //Data to be sent
            let data = new FormData();
            data.append('action', 'getSettings');
            data.append('target', this.id);

            this.apiRequest(
                data,
                'api/v1/settings',
                'getResponse',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        //Tries to update the item.
        //To delete, set inputs.delete to true. Else, to create a new setting, set createNew to be true. Else, tries to update inputs.key with the current value.
        setItem: function(key,inputs = {}){

            if(this.changing !== ''){
                alertLog('Still updating setting '+this.changing,'error',this.$el);
                return;
            }

            let newValue = null;
            if(inputs.createNew){
                if((this.newSetting.value === '') || (this.newSetting.key === '') ){
                    alertLog('Invalid setting key or value!','error',this.$el);
                    return;
                }
                if(this.mainItem[this.newSetting.key]){
                    alertLog('Key already exists!','error',this.$el);
                    return;
                }
                newValue = this.newSetting.value;
                key = this.newSetting.key;
            }

            //Data to be sent
            let data = new FormData();
            data.append('action', inputs.delete? 'unsetSetting' : 'setSetting');
            data.append('target', this.item.identifier);
            if(this.test)
                data.append('req','test');

            //What we're changing
            this.changing = key;
            this.deleted = inputs.delete;

            //Params
            let params = {
                settingName: key
            };
            if(!inputs.delete)
                params.settingValue = (newValue === null) ? this.mainItem[key].current : newValue;
            if(newValue !== null)
                params.createNew = 1;

            data.append('params', JSON.stringify(params));

            if(this.verbose)
                console.log('Setting',params);

            this.apiRequest(
                data,
                'api/v1/settings',
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
                alertLog('Not authorized to update setting! Check to see if you are logged in.','error',this.$el);
                return;
            }

            switch (response) {
                case 1:
                    if(!this.deleted)
                        alertLog('Setting '+this.changing+' updated!','success',this.$el,{autoDismiss:2000});
                    else
                        alertLog('Setting '+this.changing+' deleted!','warning',this.$el);
                    this.setInputsAsCurrent(this.changing, this.changing === this.newSetting.key);
                    break;
                case 0:
                    alertLog('Could not update setting!','error',this.$el);
                    break;
                default:
                    alertLog('Unknown response: '+response,'error',this.$el);
                    break;
            }

            //Either way we are done
            this.changing = '';
            this.deleted = false;

        },
        //Handles the response to the get request
        handleItemGet: function(response){

            if(this.verbose)
                console.log('Recieved handleItemGet',response);

            if(this.identifier && response.from !== this.identifier)
                return;

            if(response.from)
                response = response.content;

            if(typeof response === 'object'){
                this.mainItem = {};

                for(let setting in response){
                    this.mainItem[setting] = {
                        'setting':setting,
                        'original':response[setting],
                        'current':response[setting],
                        type: typeof response[setting]
                    };
                }
            }

            this.initiating = false;
            this.upToDate = true;
        },
        //Resets inputs
        resetInputs: function(){
            for(let index in this.mainItem){
                this.mainItem[index].current = this.mainItem[index].original;
            }
            this.recompute.changed = !this.recompute.changed;
        },
        //Saves inputs as the actual data (in case of a successful update or whatnot)
        setInputsAsCurrent: function(key = '', newValue = false){
            if(this.deleted)
                delete this.mainItem[key];
            else if(!newValue){
                if(key === '')
                    for(let index in this.mainItem){
                        this.mainItem[index].original = this.mainItem[index].current;
                    }
                else
                    this.mainItem[key].original = this.mainItem[key].current;
            }
            else{
                this.mainItem[this.newSetting.key] = {};
                this.mainItem[this.newSetting.key].current = this.newSetting.value ? this.newSetting.value : 0;
                this.mainItem[this.newSetting.key].original = this.mainItem[this.newSetting.key].current;
            }

            this.recompute.changed = !this.recompute.changed;
        }
    },
    watch: {
    },
    template: `
    <div class="settings-editor">
        <div class="wrapper">

            <h4 v-if="item.local" class="message message-warning-2">
                This is a <u>local</u> settings file.<br>
                Generally, local setting files should <u>NOT be edited</u> through this API.<br>
                Editing here will also <u>NOT affect any of the other nodes</u> in the system, potentially leading to <u>system inconsistencies</u>.<br>
                Do not touch this file unless you know what you're doing.
            </h4>

            <h1 v-text="item.title"></h1>

            <h4 v-if="deletionPrompt" class="message message-warning-1">
                If you are SURE you want to delete the setting, press the deletion again. <br>
                 <button v-text="'Cancel Deletion Operation'" @click.prevent="deletionPrompt = false;" class="cancel-1"></button>
            </h4>

            <form>
                <div v-for="(item, key) in mainItem"
                 :class="[{changed:item.current !== item.original},key.replace('.','-')]"
                 >
                    <span class="title" v-text="key"></span>
                    <input v-if="item.type === 'string'" type="text" v-model:value="item.current" @change="recompute.changed = !recompute.changed">
                    <input v-else-if="item.type === 'number'" type="number" v-model:value="item.current" @change="recompute.changed = !recompute.changed">
                    <input v-else-if="item.type === 'boolean'" type="checkbox" v-model:value="item.current" @change="recompute.changed = !recompute.changed">
                    
                    <button v-if="item.current !== item.original" v-text="'Update'" @click.prevent="setItem(key)" class="positive-1"></button>
                    <button v-if="item.current !== item.original" v-text="'Reset'" @click.prevent="item.current = item.original;recompute.changed = !recompute.changed;" class="cancel-1"></button>
                    
                    <button v-else="" v-text="'X'" @click.prevent="if(!deletionPrompt) deletionPrompt = true; else{deletionPrompt = false;setItem(key,{delete:true})}" class="negative-2"></button>
                </div>
                <div :class="{changed:(newSetting.value !== '') && (newSetting.key !== '')}">
                    <input class="title" type="text" v-model:value="newSetting.key ">
                    <input type="text" v-model:value="newSetting.value" @change="recompute.changed = !recompute.changed">
                    <button v-if="(newSetting.value !== '') && (newSetting.key !== '')" v-text="'Create'" @click.prevent="setItem('',{createNew:true})" class="positive-1"></button>
                    <button v-text="'Reset'" @click.prevent="newSetting.key = '';newSetting.value = '';" class="cancel-1"></button>
                </div>
            </form>

            <div class="control-buttons" v-if="changed">
                <button v-text="'Reset'" @click.prevent="resetInputs()" class="cancel-1"></button>
            </div>

        </div>
    </div>
    `
});