Vue.component('rule-editor', {
    name: 'RuleEditor',
    mixins: [
        eventHubManager,
        IOFrameCommons,
        objectEditor
    ],
    props: {
        mode: {
            type: String,
            default: 'create' //'create' / 'update'
        },
        item: {
            type: Object,
            default: function(){
                return {}
            }
        },
        id: {
            type: String
        },
        passedCommonText: {
            type: Object,
            default: function(){
                return {};
            }
        },
        limitChannels: {
            type: Array,
            default: function(){
                return [];
            }
        },
        //Editor
        identifier: {
            type: String
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
        }
    },
    data: function () {
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            commonText:{
                'operation-create-identifier':'Identifier',
                'operation-delete-title':'Delete',
                'operation-confirm':'Confirm',
                'operation-cancel':'Cancel',
                'title-groups':'Rules',
                'params-group-type-title':'Group Type',
                'params-group-id-title':'Group ID',
                'params-rules-channel-title':'Channel',
                'params-rules-level-title':'Log Level',
                'params-rules-type-title':'Report Type',
                'params-rules-title-title':'Title',
                'params-group-title-title':'Group Title',
                'params-group-created-title':'Created At',
                'params-group-updated-title':'Updated At',
                'create-missing-input-identifier':'Item must have an identifier',
                'create-invalid-input-requires-regex':'must match regex',
                'response-unknown':'Unknown response',
                'response-db-connection-error':'Server internal connection error',
                'response-update-dependency':'Dependency no longer exists',
                'response-update-does-exist':'Item already exists',
                'response-update-does-not-exist':'Item no longer exists',
                'response-update-success':'Item updated',
                'response-creation-success':'Item created',
                'info-created':'Created',
                'info-updated':'Updated'
            },
            //Main item focused on in this component
            mainItem:{
            },
            //Editable portion of the article
            paramMap: {
            },
            //Sometimes, you need to mainally recompute Vue computed properties
            recompute:{
                changed:false,
                paramMap: false
            },
            //Event Parsing Map
            eventParsingMap: {
                //Fully filled as an example
                'handleItemCreate':{
                    identifierFrom:this.identifier,
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'number',
                        value: 0
                    },
                    errorMap:{
                        '-1':{
                            text:'Server error!',
                            warningType: 'error',
                        },
                        '1':{
                            text:'Item already exists',
                            warningType: 'error',
                        },
                    }
                },
                'handleItemUpdate':{
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'number',
                        value: 0
                    },
                    errorMap:{
                        '-1':{
                            text:'Server error!',
                        },
                        '2':{
                            text:'Item no longer exists',
                            warningType: 'error',
                        },
                    }
                }
            },
            currentChildMode:'groups',
            availableChildModes:{
                'groups':'title-groups'
            },
            ruleGroupSearchList:{
                action: 'getRuleGroups',
                //Filters to display for the search list
                filters:[],
                //Result columns to display, and how to parse them
                columns:[
                    {
                        id:'title',
                        title:'Title',
                        custom:true,
                        parser:function (item){
                            if(document.ioframe.selectedLanguage === document.ioframe.defaultLanguage)
                                return item.title ?? item['title_'+document.ioframe.defaultLanguage] ?? ' - ';
                            else
                                return item['title_'+document.ioframe.selectedLanguage] ?? item.title ?? ' - ';
                        }
                    },
                    {
                        id:'type',
                        title:'Group Type'
                    },
                    {
                        id:'id',
                        title:'Group ID'
                    },
                    {
                        id:'created',
                        title:'Created At',
                        parser:timeStampToReadableFullDate
                    },
                    {
                        id:'updated',
                        title:'Updated At',
                        parser:timeStampToReadableFullDate
                    }
                ],
                //SearchList API (and probably the only relevant API) URL
                url: document.ioframe.rootURI+ 'api/v2/logs/reporting-rules',
                //Current page
                page:0,
                //Go to page
                pageToGoTo: 1,
                //Limit
                limit:1000000,
                //Total available results
                total: 0,
                //Main items
                items: [],
                meta:{},
                extraParams: {},
                selectedMultiple:[],
                initiated: false,
            },
            //Whether the item is up to date
            initiated: true,
            //Whether we are currently initiating the item
            initiating: false,
            //Whether we are currently updating the item
            updating: false,
        };
    },
    created() {
        this.registerHub(eventHub);
        this.registerEvent('setResponse', this.handleItemSet);
        this.registerEvent('searchResults', this.parseSearchResults);
        this.registerEvent('requestSelection', this.selectElement);
        this.updateText();
        this.updateParamMap();
        this.setMainItem(this.item);
        if(this.mode === 'edit'){
            this.updateSearchLists();
        }
    },
    mounted: function(){
    },
    computed: {
        modifiedChanged: function () {
            for(let i in this.changed)
                if(this.changed[i])
                    return true;
            return false;
        },
        currentlySelectedGroup: function (){
            let res = [];
            for( let i in this.ruleGroupSearchList.selectedMultiple){
                const item = this.ruleGroupSearchList.items[this.ruleGroupSearchList.selectedMultiple[i]];
                if(item)
                    res.push(item);
            }
            return res.length? res : null;
        },
    },
    methods: {
        //No need for paramMap reactivity so we can set objects directly
        updateParamMap: function(){
            let titleBase = {
                title:this.commonText['params-rules-title-title'],
                required: this.mode === 'create',
                //What to do on item update
                onUpdate: {
                    validate: function(item){
                        return item.length > 0 && item.length < 512;
                    },
                },
                pattern:'^.{1,512}$',
                validateFailureMessage: 'Group title must be between 1 and 512 characters long!'
            };
            this.paramMap.identifier = {
                ignore:true
            };
            this.paramMap.channel = (this.mode === 'create') && this.limitChannels ?
                {
                    title:this.commonText['params-rules-channel-title'],
                    edit: true,
                    required:true,
                    type:'select',
                    list:this.limitChannels.reduce((acc, value)=>({...acc, [value]: value}), {})
                } :
                {
                    title:this.commonText['params-rules-channel-title'],
                    placeholder: "ioframe-example-channel",
                    edit: this.mode === 'create',
                    required:this.mode === 'create',
                    onUpdate: {
                        validate: function(item){
                            return item.match(/^[a-zA-Z0-9][\w\-\._ ]{0,127}$/);
                        },
                    },
                    pattern: "^[a-zA-Z0-9][\\w\\-\\._ ]{0,63}$"
                };
            this.paramMap.level = {
                title:this.commonText['params-rules-level-title'],
                edit: this.mode === 'create',
                required:this.mode === 'create',
                type:'select',
                list:{
                    100:100,
                    150:150,
                    200:200,
                    250:250,
                    300:300,
                    350:350,
                    400:400,
                    450:450,
                    500:500,
                    550:550,
                    600:600,
                    650:650,
                    700:700,
                }
            };
            this.paramMap.reportType = {
                title:this.commonText['params-rules-type-title'],
                placeholder: "Some Specific Alert On Something",
                edit: this.mode === 'create',
                required:this.mode === 'create',
                type:this.mode === 'create'? 'select' : 'text',
                list:{
                    'email':'Email',
                    'sms':'SMS',
                }
            };
            this.paramMap.title=JSON.parse(JSON.stringify(titleBase));

            if(document.ioframe.languages && document.ioframe.languages.length)
                for(let i in document.ioframe.languages){
                    let lang = document.ioframe.languages[i];
                    let newObj = JSON.parse(JSON.stringify(titleBase));
                    newObj.required = false;
                    newObj.title += ' '+lang;
                    this.paramMap['title_'+lang] = newObj;
                }

            this.paramMap.created ={
                ignore: this.mode === 'create',
                title:this.commonText['params-rules-created-title'],
                edit: false,
                onUpdate: {
                    ignore: true
                },
                parseOnGet: timeStampToReadableFullDate
            };
            this.paramMap.updated ={
                ignore: this.mode === 'create',
                title:this.commonText['params-rules-updated-title'],
                edit: false,
                onUpdate: {
                    ignore: true
                },
                parseOnGet: timeStampToReadableFullDate
            };
        },
        updateText: function (){
            for (let i in this.passedCommonText){
                replaceInObjectIfNewValueExists(this.commonText,i,this.passedCommonText[i]);
            }
            for (let event in this.eventParsingMap){
                for (let error in this.eventParsingMap[event].errorMap){
                    const ErrTextMap = {
                        '-2':this.commonText['response-update-dependency'],
                        '-1':this.commonText['response-db-connection-error'],
                        '1':this.commonText['response-update-does-exist'],
                        '2':this.commonText['response-update-does-not-exist'],
                    };
                    if(ErrTextMap[error]){
                        replaceInObjectIfNewValueExists(this.eventParsingMap[event].errorMap[error],'text',ErrTextMap[error]);
                    }
                }
            }
            //TODO Write a proper util function for all this
            for (let i in this.ruleGroupSearchList.columns){
                this.ruleGroupSearchList.columns[i].title = {
                    0:this.commonText['params-group-title-title'],
                    1:this.commonText['params-group-type-title'],
                    2:this.commonText['params-group-id-title'],
                    3:this.commonText['params-group-created-title'],
                    4:this.commonText['params-group-updated-title'],
                }[i] ?? this.ruleGroupSearchList.columns[i].title;
            }
        },
        updateSearchLists: function(){
            this.ruleGroupSearchList.url += '/'+this.mainItem.channel+'/'+this.mainItem.level+'/'+this.mainItem.reportType+'/groups';
        },
        //Parses update response
        handleItemSet: function(response){
            let creation = this.mode === 'create';

            let eventResult = this.eventResponseParser(response,creation?'handleItemCreate':'handleItemUpdate',2);

            if(!eventResult)
                return;

            this.updating = false;

            if(eventResult.valid){
                if(creation){
                    this.resetAllMainItems();
                    eventHub.$emit('searchAgain');
                }
                else{
                    alertLog(
                        creation? this.commonText['response-creation-success'] :  this.commonText['response-update-success'],
                        'success',
                        this.$el,
                        {autoDismiss:2000}
                    );
                    this.updateMainItem();
                    eventHub.$emit('searchAgain');
                }
            }
        },
        //Parses user addition response
        parseSearchResults: function (response){
            if(this.verbose)
                console.log('Received response',response);

            let target = this.ruleGroupSearchList;

            //Either way, the items should be considered initiated
            target.items = [];
            target.initiated = true;

            if(response.content['groups'] && Object.keys(response.content['groups']).length)
                for(let k in response.content['groups']){
                    target.items.push(response.content['groups'][k]);
                }
        },
        //Element selection from search list
        selectElement: function(request){
            if(this.verbose)
                console.log('No selection behaviour needed',request);
        },
        searchAgain: function (){
            this.ruleGroupSearchList.items = [];
            this.ruleGroupSearchList.selectedMultiple = [];
            this.ruleGroupSearchList.initiated = false;
        },
        confirmOperation(){

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting item info!');
                return;
            }

            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item!');
                return;
            }

            this.updating = true;

            const method = this.mode === 'create' ? 'post' : 'put';

            let toSet = this.prepareMainItemSet();

            if(!toSet.errors || Object.keys(toSet.errors).length)
                return;

            toSet = toSet.result;

            let {channel:channel, level:level, reportType:reportType} = toSet;
            delete toSet.channel;
            delete toSet.level;
            delete toSet.reportType;

            let data = new FormData();

            for(let i in toSet){
                data.append(i,toSet[i]);
            }

            if(this.test)
                data.append('req','test');

            this.apiRequest(
                data,
                'api/v2/logs/reporting-rules/'+channel+'/'+level+'/'+reportType,
                'setResponse',
                {
                    identifier: this.identifier,
                    method: method,
                    verbose: this.verbose,
                    parseJSON: true
                }
            )
        },
    },
    watch: {
    },
    template: `
    <div class="group-editor" :class="{initiating:initiating,initiated:initiated,updating:updating}">
        <div class="wrapper">
            <form class="main-items">
                <div
                    v-for="(item, key) in mainItem"
                    v-if="!paramMap[key].edit && !paramMap[key].ignore"
                    :class="['main-item','static',key.replace('.','-')]"
                    >
    
                        <span class="title" v-text="paramMap[key].title?? key"></span>
    
                        <div
                         v-if="paramMap[key].type !== 'boolean'"
                         class="item-param"
                         :type="paramMap[key].type"
                         v-text="item"
                        ></div>
    
                        <button
                        v-else-if="paramMap[key].type === 'boolean'"
                        class="item-param"
                        v-text="item?(paramMap[key].button.positive?? 'Yes'):(paramMap[key].button.negative?? 'No')"
                         ></button>
    
                </div>
    
                <div
                v-for="(item, key) in mainItem"
                v-if="paramMap[key].edit && !paramMap[key].ignore"
                :class="['main-item',{changed:(item.current !== item.original || paramMap[key].considerChanged)},key.replace('.','-'),{required:paramMap[key].required},paramMap[key].type]"
                >
    
                    <span class="title" v-text="paramMap[key].title?? key"></span>
    
                    <input
                     v-if="!paramMap[key].type || (['text','date','number','email'].indexOf(paramMap[key].type) !== -1)"
                     class="item-param"
                     :type="paramMap[key].type"
                     :min="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].min ?? false)"
                     :max="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].max ?? false)"
                     :pattern="(['text'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].pattern ?? false)"
                     :placeholder="paramMap[key].placeholder ?? false"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                    >
                    <button
                    v-else-if="paramMap[key].type === 'boolean'"
                    class="item-param"
                    v-text="item.current?(paramMap[key].button.positive ?? 'Yes'):(paramMap[key].button.negative ?? 'No')"
                     @click.prevent="item.current = paramMap[key].parseOnChange(!item.current);recompute.changed = ! recompute.changed"
                     ></button>
    
                    <textarea
                    v-else-if="paramMap[key].type === 'textArea'"
                    class="item-param"
                     :placeholder="paramMap[key].placeholder ?? false"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                     ></textarea>
    
                    <select
                    v-else-if="paramMap[key].type === 'select'"
                    class="item-param"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                     >
                        <option v-for="(title,value) in paramMap[key].list" :value="value" v-text="title"></option>
                     </select>
                     
                    <button
                    v-if="item.current !== item.original"
                    class="reset-changes"
                     @click.prevent="resetSingleMainItem(key)"
                     >
                     <img :src="getMediaUrl('ioframe','icons/refresh-icon.svg')">
                    </button>
                </div>
            </form>
            <div class="operations" v-if="changed">
                <button class="positive-1"
                        @click="confirmOperation"
                >
                    <div v-text="commonText['operation-confirm']"></div>
                </button>
                <button class="cancel-1"
                        @click="resetAllMainItems()"
                >
                    <div v-text="commonText['operation-cancel']"></div>
                </button>
            </div>

            <div is="search-list"
                 v-if="(mode === 'edit')"
                 :api-url="ruleGroupSearchList.url"
                 :api-action="ruleGroupSearchList.action"
                 :extra-params="ruleGroupSearchList.extraParams"
                 :page="ruleGroupSearchList.page"
                 :limit="ruleGroupSearchList.limit"
                 :total="ruleGroupSearchList.total"
                 :items="ruleGroupSearchList.items"
                 :initiate="!ruleGroupSearchList.initiated"
                 :columns="ruleGroupSearchList.columns"
                 :filters="ruleGroupSearchList.filters"
                 :selected="ruleGroupSearchList.selectedMultiple"
                 :test="test"
                 :verbose="verbose"
                 :identifier="identifier+'-group-'+currentChildMode+'-search'"
            ></div>
            
        </div>
    </div>
    `

});