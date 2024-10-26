Vue.component('contacts-editor', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //Current mode - create or update
        mode: {
            type: String,
            default: 'create' //'create' / 'update'
        },
        //Available types
        types: {
            type: Array,
            default: []
        },
        //Item
        item: {
            type: Object,
            default: null
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
                contactType: {
                    original:'',
                    current:'',
                    regex:/^[a-zA-Z]\w{0,63}$/
                },
                identifier: {
                    original:'',
                    current:'',
                    regex:/^[\w ]{1,256}$/
                },
                created: 0,
                updated: 0,
                firstName: {
                    original:'',
                    current:'',
                    regex:/^[a-zA-Z][a-zA-Z \-\.]{1,63}$/
                },
                lastName: {
                    original:'',
                    current:'',
                    regex:/^[a-zA-Z][a-zA-Z \-\.]{1,63}$/
                },
                companyID: {
                    original:'',
                    current:'',
                    regex:/^[\w \-\.]{1,64}$/
                },
                companyName: {
                    original:'',
                    current:'',
                    regex:/^[a-zA-Z][\w \-\.]{0,255}$/
                },
                phone: {
                    original:'',
                    current:'',
                    regex:/^\+?\d{6,20}$/
                },
                email: {
                    original:'',
                    current:'',
                    regex:/^[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}$/
                },
                fax: {
                    original:'',
                    current:'',
                    regex:/^\+?\d{6,20}$/
                },
                contactInfo: {
                    original:'',
                    current:''
                },
                country: {
                    original:'',
                    current:'',
                    regex:/^[a-zA-Z][\w \-\.]{0,63}$/
                },
                city: {
                    original:'',
                    current:'',
                    regex:/[a-zA-Z][\w \-\.]{0,63}$/
                },
                state: {
                    original:'',
                    current:'',
                    regex:/^[a-zA-Z][\w \-\.]{0,63}$/
                },
                street: {
                    original:'',
                    current:'',
                    regex:/^[a-zA-Z][\w \-\.]{0,63}$/
                },
                zipCode: {
                    original:'',
                    current:'',
                    regex:/^\d{6,12}$/
                },
                address: {
                    original:'',
                    current:''
                },
                extraInfo: {
                    original:'',
                    current:''
                }
            },
            titleMap: {
                "address":'Extra Address Information',
                "zipCode":'Zip Code',
                "city":'City',
                "companyID":'Company ID',
                "companyName":'Company Name',
                "contactInfo":'Extra Contact Information',
                "contactType":'Contact Type',
                "country":'Country',
                "email":'Email',
                "extraInfo":'Extra Meta Information',
                "fax":'Fax',
                "firstName":'First Name',
                "identifier":'Contact Identifier',
                "lastName":'Last Name',
                "phone":'Phone',
                "state":'State',
                "street":'Street',
                "created":'Date Created',
                "updated":'Date Last Updated'
            },
            //Sometimes, you need to manially recompute Vue computed properties
            recompute:{
                changed:false,
                paramMap: false
            },
            //Whether the item is up to date
            upToDate: true,
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


        if(this.mode === 'update')
            this.getItemFromProps();
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
                ;//Do nothing
            for(let i in this.mainItem){
                if( (this.mainItem[i].original !== undefined) && (this.mainItem[i].original != this.mainItem[i].current) )
                    return true;
            }
            return false;
        },
    },
    methods:{
        //Gets the info of the main item
        getItemInfo: function(){

            if(this.initiating){
                if(this.verbose)
                    console.log('Already getting main item info!!');
                return;
            }

            if(this.mode === 'create' || !this.mainItem.identifier || !this.mainItem.contactType){
                if(this.verbose)
                    console.log('Cannot get item info in create mode, or without main item identifier');
                return;
            }

            this.initiating = true;

            if(this.verbose)
                console.log('Getting item information!');

            //Data to be sent
            let data = new FormData();
            data.append('action', 'getContact');
            data.append('contactType', this.mainItem.contactType);
            data.append('id', this.mainItem.identifier);

            this.apiRequest(
                data,
                'api/v1/contacts',
                'getResponse',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        //Gets the info of the main item
        getItemFromProps: function(){
            for(let i in this.item){
                if(this.mainItem[i].original !== undefined){
                    this.mainItem[i]['original'] = this.item[i];
                }
                else
                    this.mainItem[i] = this.item[i];
            }
            this.resetInputs();
        },
        //Tries to update the item
        setItem: function(){

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting item info!');
                return;
            }


            //Data to be sent
            let data = new FormData();
            data.append('action', 'setContact');
            if(this.mode === 'create')
                data.append('override','false');
            else
                data.append('update','true');
            if(this.test)
                data.append('req','test');

            let changedStuff = {};

            for(let i in this.mainItem){
                if(
                    this.mainItem[i].current === undefined ||
                    (
                        (this.mainItem[i].current === this.mainItem[i].original) &&
                        (['contactType','identifier'].indexOf(i) === -1)
                    )
                )
                    continue;
                console.log(i);
                if(this.mainItem[i].regex && !this.mainItem[i].current.match(this.mainItem[i].regex)){
                    alertLog('Parameter '+i+' must match pattern '+this.mainItem[i].regex,'warning',this.$el);
                    return;
                }
                if(this.mainItem[i].current !== this.mainItem[i].original || (['contactType','identifier'].indexOf(i) !== -1)){
                    data.append((i === 'identifier' ? 'id' : i), this.mainItem[i].current);
                    changedStuff[i] = this.mainItem[i].current;
                }
            }

            if(this.verbose)
                console.log('Setting item with parameters ',changedStuff);

            this.apiRequest(
                data,
                'api/v1/contacts',
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

            if(response.from)
                response = response.content;

            if (response === 'AUTHENTICATION_FAILURE') {
                alertLog('Not authorized to ##INSERT ACTION HERE###! Check to see if you are logged in.','error',this.$el);
                return;
            }

             switch (response) {
                case -1:
                    alertLog('Server error!','error',this.$el);
                    break;
                 case 0:
                     alertLog('Item set!','success',this.$el,{autoDismiss:2000});
                     if(this.mode === 'update')
                        this.setInputsAsCurrent();
                     else
                        this.resetInputs();
                     break;
                 case 1:
                     alertLog('Contact does not exist!','warning',this.$el);
                     break;
                 case 2:
                     alertLog('Contact already exists!','warning',this.$el);
                     break;
                 case 3:
                     alertLog('No new info to update contact with!','info',this.$el);
                     break;
                 case 4:
                     alertLog('Not enough new info to create contact!','warning',this.$el);
                     break;
                default:
                    alertLog('Unknown server response '+response,'error',this.$el);
                    break;
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

            if(typeof response === 'object'){
                for(let key in this.mainItem){
                    if(this.mainItem[key]['original'] !== undefined){
                        this.mainItem[key]['original'] = response[key];
                        this.mainItem[key]['current'] = response[key];
                    }
                    else
                        this.mainItem[key] = response[key];
                }
            }

            this.initiating = false;
            this.upToDate = true;
        },
        //Resets inputs
        resetInputs: function(){
            for(let key in this.mainItem){
                if(this.mainItem[key]['original'] !== undefined){
                    this.mainItem[key]['current'] = this.mainItem[key]['original'];
                }
            }
        },
        //Saves inputs as the actual data (in case of a successful update or whatnot)
        setInputsAsCurrent: function(){
            for(let key in this.mainItem){
                if(this.mainItem[key]['original'] !== undefined){
                    this.mainItem[key]['original'] = this.mainItem[key]['current'];
                }
            }
        }
    },
    watch: {
    },
    template: `
    <div class="contacts-editor">
        <div class="wrapper">

            <div class="types" v-if="mode==='create' && types.length > 0">
                <span class="title" v-text="'Existing types:'"></span>
                <span v-for="item in types" class="type" v-if="item">
                    <span class="title" v-text="item"></span>
                </span>
            </div>

            <div class="info message-info-2" v-if="mode==='update'">
                <h2>Contact Info</h2>
                <div class="created">
                    <span class="title" v-text="titleMap['created']"></span>
                    <span class="value" v-text="mainItem.created"></span>
                </div>
                <div class="updated">
                    <span class="title" v-text="titleMap['updated']"></span>
                    <span class="value" v-text="mainItem.updated"></span>
                </div>
                <div class="contactType">
                    <span class="title" v-text="titleMap['contactType']"></span>
                    <span class="value" v-text="mainItem.contactType.original"></span>
                </div>
                <div class="identifier">
                    <span class="title" v-text="titleMap['identifier']"></span>
                    <span class="value" v-text="mainItem.identifier.original"></span>
                </div>
            </div>

            <form>
                <div v-for="(item, key) in mainItem" :class="[{changed:item.current !== item.original},key.replace('.','-')]"
                v-if="['created','updated'].indexOf(key) === -1 && (mode==='create' || ['contactType','identifier'].indexOf(key) === -1)">

                    <span class="title" v-text="titleMap[key]"></span>

                    <input v-if="['address','contactInfo','extraInfo'].indexOf(key) === -1"
                     type="text" v-model:value="item.current"
                    :disabled="item.original === undefined">
                    <textarea v-else="" v-model:value="item.current"></textarea>

                </div>
            </form>

            <div class="control-buttons" v-if="(mode==='update' && changed) || (mode==='create' && mainItem.contactType.current && mainItem.identifier.current)">
                <button  v-text="mode==='update'? 'Update' : 'Create'" @click.prevent="setItem()" class="positive-1"></button>
                <button v-text="'Reset'" @click.prevent="resetInputs()" class="cancel-1"></button>
            </div>
        </div>
    </div>
    `
});