if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('security-events-editor', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //Current mode - create or update
        mode: {
            type: String,
            default: 'create' //'create' / 'update'
        },
        
        //Item
        item: {
            type: Object
        },
        //Which stuff already exists and can't be created from scratch
        existingTypes: {
            type: Object,
            default: function(){
                return {};
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
            items: [],
            //Sometimes, you need to manially recompute Vue computed properties
            recompute:{
                changed:false,
                paramMap: false
            },
            //Map of parameters, and vaios properties of each.
            paramMap:{
                'items':{
                    ignore: true
                },
                'identifier':{
                    ignore: true
                },
                'category':{
                    ignore: false,
                    title: 'Events Category',
                    edit:false
                },
                'type':{
                    ignore: false,
                    title: 'Event Type',
                    edit:false
                },
                'name':{
                    ignore: this.mode === 'create',
                    title: 'Event Type Title',
                    edit:false
                },
               /*
                'paramName':{
                     //bool, whether to ignore this param completely on get, defaults to false
                     ignore: false,
                     //string, title to display, defaults to parameter name
                     title: 'paramName',
                     //bool, whether it can be edited, default true
                     edit: true,
                     //string, type (if editable). Valid types are "text"(default), "textArea", "boolean", "date", "number" and "email"
                     type: "text",
                     //bool, whether the item should be displayed (if it's not editable), default true
                     display: true,
                     //What to do on item update
                     onUpdate: {
                        //bool, default false - do no send this key on update
                         ignore: false,
                         //function, if this item is sent, parse the value with this function before sending
                         parse: function(value){
                            return value;
                         },
                         //Validates value and either suceeds or returns false
                         validate: function(value){
                            return true;
                         },
                         //The name to send. Defaults to <paramName>
                         setName: 'paramName'
                         //Custom message on validation failure (if null, default message is "<paramName> failed validation")
                        validateFailureMessage: 'Parameter '+paramName+' failed validation!',
                     },
                     //function, parsing function on item get
                     parseOnGet: function(value){
                         return value;
                     },
                     //function, parsing function on item only when displaying
                     parseOnDisplay: function(value){
                     return value;
                     },
                     //function, parsing function on item change (if it can be edited)
                     parseOnChange: function(value){
                         return value;
                     },
                     //bool, displays item (after parsing) as HTML. Only if the item is displayed and not editable. Defaults to false
                     displayHTML: false
                    }
                * */
            },
            newItem: {
                sequence: 0,
                addTTL:0,
                blacklistFor:0
            },
            //Whether the item is up to date
            upToDate: this.mode == 'create',
            //Whether anything changed
            changed: false,
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
        this.registerEvent('deleteRulebookRulesResponse' ,this.handleDeleteRulebookRules);
        this.registerEvent('setRulebookRulesResponse' ,this.handleSetRulebookRules);

          if(this.mode === 'update') {
              this.setMainItem(this.item);
              let items = JSON.parse(JSON.stringify(this.item.items));
              this.items = [];
              for (let i in items) {
                  let newItem = {
                      changed: false,
                      remove: false,
                      add: false
                  };
                  for (let j in items[i]) {
                      if (j === 'sequence')
                          newItem[j] = items[i][j];
                      else {
                          newItem[j] = {};
                          newItem[j].current = items[i][j];
                          newItem[j].original = items[i][j];
                      }
                  }
                  this.items.push(newItem);
              }
          }
          else {
            this.mainItem = {
                category: 0,
                type: 0
            };
            while(this.existingTypes[this.mainItem.category+'/'+this.mainItem.type]){
                this.mainItem.type++;
            }
          }
    },
    mounted:function(){
    },
    updated: function(){
    },
    computed:{
    },
    methods:{
        //Sets main item
        setMainItem(item){

            for(let i in item){
                if(typeof item[i] === 'object')
                    continue;

                this.setSingleParam(i);

                if(!this.paramMap[i].ignore)
                    this.mainItem[i] =
                        this.paramMap[i].edit ?
                        {
                            original:this.paramMap[i].parseOnGet(item[i]),
                            current:this.paramMap[i].parseOnGet(item[i])
                        }
                            :
                            this.paramMap[i].parseOnGet(item[i]);
            }

            for(let i in this.paramMap){
                if((item[i] === undefined || typeof item[i] === 'object') && !this.paramMap[i].ignore){
                    this.setSingleParam(i);
                    let prefixes = i.split('.');
                    let itemClone = JSON.parse(JSON.stringify(item));
                    let target = itemClone[prefixes[0]];
                    if(target !== undefined){
                        for(let j = 1; j < prefixes.length; j ++){
                            if(target[j]!==undefined)
                                target = target[j];
                        }
                    }
                    let finalIdentifier = prefixes.pop();
                    let newItem = target[finalIdentifier] !== undefined? target[finalIdentifier] : null;
                    this.setSingleMainItem(i,newItem);
                }
            }
        },
        //Helper function for setMainItem
        setSingleParam: function(i){
            if(!this.paramMap[i])
                this.paramMap[i] ={};
            this.paramMap[i].ignore = this.paramMap[i].ignore !== undefined ? this.paramMap[i].ignore : false;
            this.paramMap[i].title = this.paramMap[i].title !== undefined ? this.paramMap[i].title : i;
            this.paramMap[i].edit = this.paramMap[i].edit !== undefined ? this.paramMap[i].edit: true;
            this.paramMap[i].type = this.paramMap[i].type !== undefined ? this.paramMap[i].type : "text";
            this.paramMap[i].display = this.paramMap[i].display !== undefined ?  this.paramMap[i].display: true;
            this.paramMap[i].considerChanged = this.paramMap[i].considerChanged !== undefined ?  this.paramMap[i].considerChanged: false;
            this.paramMap[i].required = this.paramMap[i].required !== undefined ?  this.paramMap[i].required: false;

            if(!this.paramMap[i].onUpdate)
                this.paramMap[i].onUpdate = {};
            this.paramMap[i].onUpdate.ignore = this.paramMap[i].onUpdate.ignore !== undefined ? this.paramMap[i].onUpdate.ignore : false;
            this.paramMap[i].onUpdate.parse = this.paramMap[i].onUpdate.parse !== undefined ? this.paramMap[i].onUpdate.parse : function(value){
                return value;
            };
            this.paramMap[i].onUpdate.validate = this.paramMap[i].onUpdate.validate !== undefined ? this.paramMap[i].onUpdate.validate : function(value){
                return true;
            };
            this.paramMap[i].onUpdate.validateFailureMessage = this.paramMap[i].onUpdate.validateFailureMessage !== undefined ? this.paramMap[i].onUpdate.validateFailureMessage : 'Parameter '+i+' failed validation!';
            this.paramMap[i].onUpdate.setName = this.paramMap[i].onUpdate.setName !== undefined ? this.paramMap[i].onUpdate.setName : i;

            this.paramMap[i].parseOnGet = this.paramMap[i].parseOnGet !== undefined ? this.paramMap[i].parseOnGet : function(value){
                return value;
            };
            this.paramMap[i].parseOnDisplay = this.paramMap[i].parseOnDisplay !== undefined ? this.paramMap[i].parseOnDisplay : function(value){
                return value;
            };
            this.paramMap[i].parseOnChange = this.paramMap[i].parseOnChange !== undefined ? this.paramMap[i].parseOnChange : function(value){
                return value;
            };
            this.paramMap[i].displayHTML = this.paramMap[i].displayHTML !== undefined ? this.paramMap[i].displayHTML : false;

            if(!this.paramMap[i].button)
                this.paramMap[i].button = {};
            this.paramMap[i].button.positive = this.paramMap[i].button.positive !== undefined ? this.paramMap[i].button.positive : 'Yes';
            this.paramMap[i].button.negative = this.paramMap[i].button.negative !== undefined ? this.paramMap[i].button.negative : 'No';
        },
        //Helper functin for setMainItem
        setSingleMainItem: function(i, item){
            this.mainItem[i] =
                this.paramMap[i].edit ?
                {
                    original:this.paramMap[i].parseOnGet(item),
                    current:this.paramMap[i].parseOnGet(item)
                }
                    :
                    this.paramMap[i].parseOnGet(item);
        },

        //Adds newly selected item to sequences
        addItem: function(){
            let existing = this.items.filter(item => item.sequence == this.newItem.sequence);
            if(existing.length > 0){
                alertLog('Sequence '+this.newItem.sequence+' already exists!','warning',this.$el);
                return;
            }
            this.items.push({
                addTTL:{
                    current:this.newItem.addTTL,
                    original:this.newItem.addTTL,
                },
                blacklistFor:{
                    current:this.newItem.blacklistFor,
                    original:this.newItem.blacklistFor,
                },
                changed:true,
                add:true,
                sequence:this.newItem.sequence
            });
            this.items.sort(function(a,b){
                return a.sequence === b.sequence? 0: (a.sequence > b.sequence? 1 : -1);
            });

            this.changed = true;
            this.$forceUpdate();
        },
        //Adds or removes an existing sequence
        toggleItem: function(key){
            let selected = this.items[key];
            if(selected.add){
                this.items.splice(key,1);
            }
            else if(selected.remove){
                this.items[key].remove = false;
            }
            else{
                this.items[key].remove = true;
            }
            
            this.changed = true;
            this.$forceUpdate();
        },
        //Tries to update the item
        setItem: function(){

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting item info!');
                return;
            }
            var data;
            let stuffToDelete = [];
            let stuffToSet = [];

            for(let index in this.items){

                let item = this.items[index];

                if(item.remove){
                    stuffToDelete.push({
                        category:this.mainItem.category - 0,
                        type:this.mainItem.type - 0,
                        sequence:item.sequence - 0
                    });
                }
                else if(item.add || item.changed){
                    stuffToSet.push({
                        category:this.mainItem.category - 0,
                        type:this.mainItem.type - 0,
                        sequence:item.sequence - 0,
                        addTTL:item.addTTL.current - 0,
                        blacklistFor:item.blacklistFor.current - 0
                    });
                }
            }

            if(this.verbose){
                console.log('Stuff to set ',stuffToSet);
                console.log('Stuff to delete ',stuffToDelete);
            }

            if(stuffToDelete.length){
                //Data to be sent
                data = new FormData();
                data.append('action', 'deleteRulebookRules');
                data.append('inputs', JSON.stringify(stuffToDelete));
                if(this.test)
                    data.append('req','test');

                if(this.verbose)
                    console.log('Setting sequences with parameters ',stuffToDelete);

                this.apiRequest(
                    data,
                    'api/v1/security',
                    'deleteRulebookRulesResponse',
                    {
                        'verbose': this.verbose,
                        'parseJSON':true,
                        'identifier':this.identifier
                    }
                );
            }

            if(stuffToSet.length){
                //Data to be sent
                data = new FormData();
                data.append('action', 'setRulebookRules');
                data.append('inputs', JSON.stringify(stuffToSet));
                if(this.test)
                    data.append('req','test');

                if(this.verbose)
                    console.log('Setting sequences with parameters ',stuffToSet);

                this.apiRequest(
                    data,
                    'api/v1/security',
                    'setRulebookRulesResponse',
                    {
                        'verbose': this.verbose,
                        'parseJSON':true,
                        'identifier':this.identifier
                    }
                );
            }
        },
        //Handles item update
        handleSetRulebookRules: function(response){

            if(this.verbose)
                console.log('Received handleSetRulebookRules',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.updating = false;

            if(response.from)
                response = response.content;

            if (response === 'AUTHENTICATION_FAILURE') {
                alertLog('Not authorized to set items! Check to see if you are logged in.','error',this.$el);
                return;
            }

            if(typeof response !== 'object'){
                alertLog('Unknown handleSetRulebookRules response '+response,'error',this.$el);
                return;
            }

            let stuffToDelete = false;
            let errors = [];
            let success = [];
            let successKeys = [];

            for(let key in this.items){
                let item = this.items[key];
                if(item.remove){
                    stuffToDelete = true;
                    continue;
                }
                let identifier = this.mainItem.category + '/' + this.mainItem.type + '/' + item.sequence;
                //The identifier will always be set if we tried to set the item
                if(response[identifier] !== undefined){
                    switch(response[identifier]){
                        case 0:
                            success.push(item.sequence);
                            successKeys.push(key);
                            break;
                        case -1:
                        case 1:
                        case 2:
                        default :
                            errors.push(item.sequence);
                            break;
                    }
                }
            }

            if(errors.length > 0){
                alertLog('The following sequences were not set: '+errors.join(', '),'error',this.$el);
            }
            else if(!stuffToDelete){
                this.changed = false;
                if(this.mode === 'create')
                    eventHub.$emit('returnToMainApp');
                eventHub.$emit('searchAgain');
            }

            if(success.length > 0){
                alertLog('The following sequences were set: '+success.join(', '),'success',this.$el);
                for(let i in successKeys){
                    for(let j in this.items[successKeys[i]]){
                        if(this.items[successKeys[i]][j].original)
                            this.items[successKeys[i]][j].original = this.items[successKeys[i]][j].current;
                    }
                    this.items[successKeys[i]].add = false;
                    this.items[successKeys[i]].remove = false;
                    this.items[successKeys[i]].changed = false;
                }
            }

        },
        //Handles items deletion
        handleDeleteRulebookRules: function(response){

            if(this.verbose)
                console.log('Received deleteRulebookRules',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.updating = false;

            if(response.from)
                response = response.content;

            if (response === 'AUTHENTICATION_FAILURE') {
                alertLog('Not authorized to set items! Check to see if you are logged in.','error',this.$el);
                return;
            }

            if(response !== -1 && response !== 0){
                alertLog('Unknown response deleting sequences '+response,'error',this.$el);
            }
            else if(response == -1){
                alertLog('Sequences were not deleted due to server error!','error',this.$el);
            }
            else{
                let stuffToSet = false;

                for(let i = this.items.length-1; i >= 0; i--){
                    let item = this.items[i];
                    if(item.remove){
                        this.items.splice(i,1);
                    }
                    else if(item.add || item.changed){
                        stuffToSet = true
                    }
                }
                alertLog('Sequences were deleted!','success',this.$el);
                if(!stuffToSet){
                    this.changed = false;
                    eventHub.$emit('searchAgain');
                    if(this.mode === 'create' || !this.items.length)
                        eventHub.$emit('returnToMainApp');
                }
            }

        },
        
        //Resets inputs
        resetInputs: function(){
            for(let index in this.items){

                for(let paramIndex in this.items[index]){
                    if(this.items[index][paramIndex].original){
                        this.items[index][paramIndex].current = this.items[index][paramIndex].original;
                    }
                }
                this.items[index].changed = false;

                if(this.items[index].remove)
                    this.items[index].remove = false;
                else if(this.items[index].add)
                    this.items.splice(index,1);
            }
            this.changed = false;
            this.$forceUpdate();
        },
        //Saves inputs as the actual data (in case of a successful update or whatnot)
        setInputsAsCurrent: function(){
            for(let key in this.mainItem){
                if(this.mainItem[key]['original'] !== undefined){
                    this.mainItem[key]['original'] = this.mainItem[key]['current'];
                }
            }
            this.recompute.changed = ! this.recompute.changed;
            this.$forceUpdate();
        },
        //Checks if current category/type combo exists
        checkExisting: function(){
            return this.existingTypes[this.mainItem.category+'/'+this.mainItem.type] !== undefined;
        }
    },
    watch: {
        'mainItem.category': function(newVal,oldVal){
            if(this.mode === 'create'){
                let identifier = this.mainItem.category+'/'+this.mainItem.type;
                if(this.existingTypes[identifier]){
                    alertLog('Event type (category/type combo) already exists! Pleas edit the existing one.','error',this.$el);
                    this.mainItem.category = oldVal;
                }
                else
                    this.mainItem.category = newVal - 0;
            }
        },
        'mainItem.type': function(newVal,oldVal){
            if(this.mode === 'create'){
                let identifier = this.mainItem.category+'/'+this.mainItem.type;
                if(this.existingTypes[identifier]){
                    alertLog('Event type (category/type combo) already exists! Pleas edit the existing one.','error',this.$el);
                    this.mainItem.type = oldVal;
                }
                else
                    this.mainItem.type = newVal - 0;
            }
        },
    },
    template: `
    <div class="security-events-editor">
        <div class="wrapper">

            <div class="info message-info-2">
                <div
                 v-for="(item, key) in mainItem"
                 v-if="!paramMap[key].ignore "
                 :class="key.replace('.','-')">

                    <span class="title" v-text="paramMap[key].title? paramMap[key].title : key"></span>

                    <span class="value" v-if="mode === 'update'" v-text="paramMap[key].parseOnDisplay(item)"></span>
                    <input v-else="" type="number" min="0" v-model:value="mainItem[key]">

                </div>
            </div>

            <form>
                <div
                v-for="(item, key) in items"
                :class="{changed:item.changed && ! item.add,add:item.add,remove:item.remove}"
                >
                    <div>
                        <span class="title" v-text="'Sequence number'"></span>
                        <span class="value" v-text="item.sequence"></span>
                    </div>

                    <div>
                        <span class="title" v-text="'TTL to add'"></span>
                        <input class="item-param" type="number" v-model:value="item.addTTL.current" @change="item.changed = true; changed = true;">
                    </div>

                    <div>
                        <span class="title" v-text="'Blacklist For'"></span>
                        <input class="item-param" type="number" v-model:value="item.blacklistFor.current" @change="item.changed = true; changed = true;">
                    </div>

                    <button
                    class="item-toggle"
                    :class="item.remove?'add':'remove'"
                    v-text="item.remove?'+':'-'"
                     @click.prevent="toggleItem(key)"
                     ></button>

                </div>

                <div class="add-new">
                    <span class="title" v-text="'Sequence number'"></span>
                    <input class="item-param" type="number" v-model:value="newItem.sequence">

                    <span class="title" v-text="'TTL to add'"></span>
                    <input class="item-param" type="number" v-model:value="newItem.addTTL">

                    <span class="title" v-text="'Blacklist For'"></span>
                    <input class="item-param" type="number" v-model:value="newItem.blacklistFor">

                    <button
                    class="item-toggle add"
                    v-text="'+'"
                     @click.prevent="addItem()"
                     ></button>

                </div>
            </form>

            <div class="control-buttons" v-if="changed">
                <button  v-text="'Update'" @click.prevent="setItem()" class="positive-1"></button>
                <button v-text="'Reset'" @click.prevent="resetInputs()" class="cancel-1"></button>
            </div>

        </div>
    </div>
    `
});