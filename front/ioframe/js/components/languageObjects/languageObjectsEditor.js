Vue.component('language-objects-editor', {
    name: 'LanguageObjectsEditor',
    mixins: [
        eventHubManager,
        IOFrameCommons,
        multipleLanguages
    ],
    props: {
        languageObject: {
            type: Object,
            default: function (){
                return {};
            }
        },
        commonText: {
            type: Object,
            default: function(){
                return {
                    'operation-create-identifier':'Identifier',
                    'operation-create-object':'Object',
                    'operation-confirm':'Confirm',
                    'operation-cancel':'Cancel',
                    'create-missing-input-identifier':'Item must have an identifier',
                    'create-missing-input-object':'Item must have an object',
                    'create-invalid-input-identifier':'Identifier can contain english characters, digits and _,-',
                    'create-invalid-input-object':'Object must be a valid json',
                    'response-unknown':'Unknown response',
                    'response-db-connection-error':'Server internal connection error',
                    'response-update-does-not-exist':'Object no longer exists',
                    'response-update-success':'Object updated',
                    'info-created':'Created',
                    'info-updated':'Updated',
                    'info-object':'Language Object',
                }
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
            modifiedObject: {
                updated: {
                    original:'',
                    current:''
                },
                object: {
                    original:'',
                    current:''
                },
            }
        };
    },
    created() {
        this.registerHub(eventHub);
        this.registerEvent('objectUpdateResponse', this.parseUpdateResponse);
        for(let i in this.languageObject)
            if(this.modifiedObject[i] !== undefined){
                Vue.set(this.modifiedObject[i],'original',this.languageObject[i]);
                Vue.set(this.modifiedObject[i],'current',this.languageObject[i]);
            }
    },
    mounted: function(){
    },
    computed: {
        changed: function(){
            let changed = {};
            for(let i in this.modifiedObject){
                changed[i] = this.modifiedObject[i].current !== this.modifiedObject[i].original;
            }
            return changed;
        },
        modifiedChanged: function () {
            for(let i in this.changed)
                if(this.changed[i])
                    return true;
            return false;
        },
    },
    methods: {
        //Sets object to current/Original
        resetObject: function(toCurrent = false){
            let order = toCurrent?['original','current']:['current','original'];
            {
                for(let i in this.modifiedObject)
                    this.modifiedObject[i][order[0]] = this.modifiedObject[i][order[1]];
            }
        },
        //Checks inputs for validity
        checkInputs: function(){
            if(!this.modifiedObject['object'].current){
                alertLog(this.commonText['create-missing-input-object'],'warning',this.$el);
                return false;
            }
            if(!IsJsonString(this.modifiedObject['object'].current)){
                alertLog(this.commonText['create-invalid-input-object'],'warning',this.$el);
                return false;
            }
            return true;
        },
        //Parses update response
        parseUpdateResponse: function(response){

            if(this.test){
                alertLog(typeof response !== 'object'? response : JSON.stringify(response),'info',this.$el);
                console.log(response);
                return;
            }

            let expectedIdentifier = this.languageObject.identifier;

            if((typeof response !== 'object') || response.content[expectedIdentifier] === undefined){
                alertLog(this.commonText['response-unknown'],'error',this.$el);
                console.log(response,expectedIdentifier);
                return;
            }
            response = response.content;
            switch (response[expectedIdentifier]-0) {
                case -1:
                    alertLog(this.commonText['response-db-connection-error'],'error',this.$el);
                    break;
                case 0:
                    alertLog(this.commonText['response-update-success'],'success',this.$el,{autoDismiss:2000});
                    let request = {from:this.identifier,content:{}}
                    for(let i in this.modifiedObject)
                        request.content[i] = JSON.parse(JSON.stringify(this.modifiedObject[i].current));
                    this.modifiedObject.updated.current = (Date.now()/1000).toFixed(0);
                    this.resetObject(true);
                    eventHub.$emit('searchAgain');
                    break;
                case 1:
                    alertLog(this.commonText['response-update-does-not-exist'],'error',this.$el);
                    break;
                default:
                    alertLog('Unknown response','error',this.$el);
                    console.log(response);
                    break;
            }
        },
        confirmOperation(){
            if(this.test)
                console.log('Current Operation ', 'Update Category' ,'Current input ',JSON.parse(JSON.stringify(this.modifiedObject)));

            if(!this.checkInputs())
                return;

            let data = new FormData();

            data.append('action','setLanguageObjects');
            data.append('update','1');
            if(this.test)
                data.append('req','test');


            let objects= {};
            objects[this.languageObject.identifier] = JSON.parse(this.modifiedObject['object'].current);

            data.append('objects',JSON.stringify(objects));


            this.apiRequest(data, 'api/language-objects', 'objectUpdateResponse', {
                verbose: this.verbose,
                parseJSON: true
            })
        },
        timeStampToReadableFullDate: function (item){
            return timeStampToReadableFullDate(item);
        }
    },
    watch: {
    },
    template: `
<div class="language-objects-editor">
    <div class="wrapper">
        <div class="image-fields">
            <div class="fields">
                <div class="field identifier">
                    <label v-text="commonText['operation-create-identifier']"></label>
                    <input :value="languageObject.identifier" disabled>
                </div>
                <div class="field created">
                    <label v-text="commonText['info-created']"></label>
                    <input :value="timeStampToReadableFullDate(languageObject.created)" disabled>
                </div>
                <div class="field updated">
                    <label v-text="commonText['info-updated']"></label>
                    <input :value="timeStampToReadableFullDate(modifiedObject.updated.current)" disabled>
                </div>
                <div class="field object required" :class="{changed:changed.object}">
                    <label v-text="commonText['info-object']"></label>
                    <textarea v-model:value="modifiedObject.object.current"></textarea>
                </div>
            </div>
        </div>
        <div class="operations" v-if="modifiedChanged">
            <button class="positive-1"
                    @click="confirmOperation"
            >
                <div v-text="commonText['operation-confirm']"></div>
            </button>
            <button class="cancel-1"
                    @click="resetObject()"
            >
                <div v-text="commonText['operation-cancel']"></div>
            </button>
        </div>
    </div>
</div>
    `

});