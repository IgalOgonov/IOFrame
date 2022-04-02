if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('security-ip-ranges-editor', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //Mode - 'create' or 'update'
        mode: {
            type: String,
            default: 'create'
        },
        //Item
        item: {
            type: Object,
            default: function(){
                return {
                    prefix:'',
                    from:0,
                    to:1,
                    newFrom:0,
                    newTo:1,
                    expires:Math.floor((Date.now() + 3600000)/1000),
                    type:false
                };
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
                changed:false,
                paramMap: false
            },
            //Map of parameters, and vaios properties of each.
            paramMap:{
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
                 //bool, whether to consider the item changed even if it wasn't
                 considerChanged: false,
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
                 }
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
                 //object, if the type is "boolean" this allows you to modify the button
                 button: {
                 positive: 'Yes',
                 negative: 'No'
                 }
                 }
                 * */
                'prefix':{
                    title: 'Prefix',
                    edit: this.mode === 'create',
                    display: true,
                    considerChanged: this.mode === 'create',
                    onUpdate:{
                        setName:'prefix',
                        validate: function(value){
                            return value === '' || value.match(/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){0,3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/);
                        },
                        validateFailureMessage: 'Prefix must be a valid IPv4 prefix!'
                    }
                },
                'from':{
                    title: 'From',
                    edit: this.mode === 'create',
                    display: true,
                    type:'number',
                    considerChanged: this.mode === 'create',
                    onUpdate:{
                        setName:'from'
                    }
                },
                'to':{
                    title: 'To',
                    edit: this.mode === 'create',
                    display: true,
                    type:'number',
                    considerChanged: this.mode === 'create',
                    onUpdate:{
                        setName:'to'
                    }
                },
                'newFrom':{
                    title: 'New "From"',
                    ignore:this.mode === 'create',
                    edit: true,
                    display: true,
                    type:'number',
                    onUpdate:{
                        setName:'newFrom',
                        validate: function(value){
                            value = value + '';
                            return value.match(/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))$/);
                        },
                        validateFailureMessage: 'IP From must be an int between 0 and 255!'
                    }
                },
                'newTo':{
                    title: 'New "To"',
                    ignore:this.mode === 'create',
                    edit: true,
                    display: true,
                    type:'number',
                    onUpdate:{
                        setName:'newTo',
                        validate: function(value){
                            value = value + '';
                            return value.match(/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))$/);
                        },
                        validateFailureMessage: 'IP From must be an int between 0 and 255!'
                    }
                },
                'type':{
                    title: 'Type',
                    edit: true,
                    considerChanged: this.mode === 'create',
                    display: true,
                    type: 'boolean',
                    button: {
                        positive:'Whitelisted',
                        negative:'Blacklisted'
                    },
                    onUpdate:{
                        setName:'type'
                    }
                },
                'expires':{
                    title: 'Expires At',
                    edit: true,
                    considerChanged: this.mode === 'create',
                    type: 'text',
                    parseOnGet: function(timestamp){
                        timestamp *= 1000;
                        let date = timestampToDate(timestamp).split('-').reverse().join('-');
                        let hours = Math.floor(timestamp%(1000 * 60 * 60 * 24) / (1000 * 60 * 60));
                        let minutes = Math.floor(timestamp%(1000 * 60 * 60) / (1000 * 60));
                        let seconds = Math.floor(timestamp%(1000 * 60) / (1000));
                        if(hours < 10)
                            hours = '0'+hours;
                        if(minutes < 10)
                            minutes = '0'+minutes;
                        if(seconds < 10)
                            seconds = '0'+seconds;
                        return date + ', ' + hours+ ':'+ minutes+ ':'+seconds;
                    },
                    onUpdate:{
                        setName:'ttl',
                        parse: function(timestamp){
                            timestamp = timestamp.replace(/ /g, "");
                            //If the set the timestamp as nothing, it means default value
                            if(timestamp === '')
                                return 0;
                            //In fact, validation is done here - if it fails, the validate function will auto-fail the parameter
                            else if(!timestamp.match(/^(0[1-9]|[1-2][0-9]|3[0-1])\-(0[1-9]|1[0-2])\-(2[0-9]{3})\,([0-1][0-9]|2[0-3])\:[0-5][0-9]\:[0-5][0-9]$/))
                                return false;
                            else{
                                timestamp = timestamp.split(',');
                                let date = timestamp[0];
                                let dateTime = timestamp[1].split(':');
                                dateTime = ((dateTime[0]-0)*3600 + (dateTime[1]-0)*60 + (dateTime[2] - 0))*1000;
                                date = dateToTimestamp(date.split('-').reverse().join('-'));
                                let delta  = (dateTime+date - Date.now())/1000;
                                return delta > 0? Math.floor(delta) : 1 ;
                            }
                        },
                        validate: function(value){
                            console.log(value);
                            return value !== false;
                        },
                        validateFailureMessage: 'Date must be of the form: "DD-MM-YYYY, HH:MM:SS", or be empty!'
                    }
                },
                'identifier':{
                    ignore: true
                }
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
        this.setMainItem(this.item);
        if(this.mode === 'create'){

        }
    },
    mounted:function(){
    },
    updated: function(){
    },
    computed:{
        changed: function(){
            if(this.recompute.changed)
                ;//Do nothing
            for(let i in this.mainItem){
                if(this.mainItem[i].original !== undefined && (this.mainItem[i].original != this.mainItem[i].current || this.paramMap[i].considerChanged))
                    return true;
            }
            return false;
        },
        itemHasInfo: function(){
            if(this.paramMap)
                ;//Do nothing

            for(let i in this.paramMap){
                if(!this.paramMap[i].edit && this.paramMap[i].display)
                    return true;
            }
            return false;
        },
    },
    methods:{
        //What to do if an item was changed
        itemChanged: function(key){
        },
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
                    if(!target)
                        target = [];
                    let finalIdentifier = prefixes.pop();
                    let newItem = (target !== null) && (target[finalIdentifier] !== undefined)? target[finalIdentifier] : null;
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
        //Tries to update the item
        setItem: function(){

            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item info!');
                return;
            }

            //Data to be sent
            let data = new FormData();
            data.append('action', (this.mode === 'create' ? 'addIPRange' : 'updateIPRange') );
            if(this.test)
                data.append('req','test');

            let sendParams = {};

            for(let paramName in this.paramMap){

                let param = this.paramMap[paramName];
                let item = this.mainItem[paramName];

                if(param.ignore || param.onUpdate.ignore || (item.current !== undefined && item.current === item.original && !param.considerChanged))
                    continue;
                else if(item.current === undefined){
                    data.append(param.onUpdate.setName, item);
                    sendParams[param.onUpdate.setName] = item;
                    continue;
                }

                let paramValue = param.onUpdate.parse(item.current);

                if(!param.onUpdate.validate(paramValue)){
                    alertLog(param.onUpdate.validateFailureMessage,'warning',this.$el);
                    return;
                }

                data.append(param.onUpdate.setName, paramValue);
                sendParams[param.onUpdate.setName] = paramValue;
            }

            if(this.verbose)
                console.log('Setting item with parameters ',sendParams);

            this.apiRequest(
                data,
                'api/v1/security',
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
                alertLog('Not authorized to update IP! Check to see if you are logged in.','error',this.$el);
                return;
            }

            switch (response) {
                case 0:
                    alertLog('IP was not '+(this.mode === 'create'? 'created - server error, or already exists!' : 'updated - server error, or no longer exists!'),'error',this.$el);
                    break;
                case 1:
                    alertLog('IP '+(this.mode === 'create'? 'created' : 'updated')+'!','success',this.$el);
                    if(this.mode === 'update')
                        this.setInputsAsCurrent();
                    eventHub.$emit('searchAgain');
                    break;
                default:
                    alertLog('Unknown response '+response,'error',this.$el);
            }
        },
        //Resets inputs
        resetInputs: function(){
            for(let key in this.mainItem){
                if(this.mainItem[key]['original'] !== undefined){
                    this.mainItem[key]['current'] = this.mainItem[key]['original'];
                }
            }
            this.recompute.changed = ! this.recompute.changed;
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
        }
    },
    watch: {
    },
    template: `
    <div class="security-ip-ranges-editor">
        <div class="wrapper">

            <div class="info message-info-2" v-if="this.itemHasInfo">
                <div
                 v-for="(item, key) in mainItem"
                 v-if="!paramMap[key].edit && paramMap[key].display "
                 :class="key.replace('.','-')">

                    <span class="title" v-text="paramMap[key].title? paramMap[key].title : key"></span>

                    <span class="value" v-if="!paramMap[key].displayHTML" v-text="paramMap[key].parseOnDisplay(item)"></span>
                    <span class="value" v-else="" v-html="paramMap[key].parseOnDisplay(item)"></span>

                </div>
            </div>

            <form>
                <div
                v-for="(item, key) in mainItem"
                v-if="paramMap[key].edit"
                :class="[{changed:(item.current !== item.original || paramMap[key].considerChanged)},key.replace('.','-')]"
                >

                    <span class="title" v-text="paramMap[key].title? paramMap[key].title : key"></span>

                    <input
                     v-if="!paramMap[key].type || ['text','date','number','email'].indexOf(paramMap[key].type) !== -1"
                     class="item-param"
                     :type="paramMap[key].type"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                    >
                    <button
                    v-else-if="paramMap[key].type === 'boolean'"
                    class="item-param"
                    :class="{'positive-1':item.current,'cancel-1':!item.current}"
                    v-text="item.current?(paramMap[key].button.positive? paramMap[key].button.positive : 'Yes'):(paramMap[key].button.negative? paramMap[key].button.negative : 'No')"
                     @click.prevent="item.current = paramMap[key].parseOnChange(!item.current);recompute.changed = ! recompute.changed"
                     ></button>

                    <textarea
                    v-else-if="paramMap[key].type === 'textArea'"
                    class="item-param"
                     v-model:value="item.current"
                     @change="itemChanged(key)"
                     ></textarea>

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