if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('tags-editor', {
    name: 'TagsEditor',
    mixins: [
        eventHubManager,
        IOFrameCommons
    ],
    props: {
        tagName: {
            type: String
        },
        tagDetails: {
            type: Object
        },
        categoryDetails: {
            type: Object,
            default: function(){
                return {}
            }
        },
        tag: {
            type: Object
        },
        commonText: {
            type: Object,
            default: function(){
                return {
                    'categories-loading':'Loading Categories...',
                    'categories-error':'Error getting categories',
                    'categories-all':'All Categories',
                    'operation-create-identifier':'Identifier',
                    'operation-create-category':'Category',
                    'operation-create-heb':'Hebrew name',
                    'operation-create-eng':'English name',
                    'operation-create-color':'Color Hex',
                    'operation-thumbnail-reset':'Reset Thumbnail',
                    'operation-confirm':'Confirm',
                    'operation-cancel':'Cancel',
                    'create-missing-input-identifier':'Item must have an identifier',
                    'create-missing-input-category':'Item must have a category',
                    'create-missing-input-required':'Item must have attribute',
                    'create-invalid-input':'Invalid extra parameter',
                    'create-invalid-input-requires-regex':'must match regex',
                    'create-invalid-input-identifier':'Identifier can contain english characters, digits and _,-',
                    'create-invalid-input-category':'Category must be a number',
                    'create-invalid-input-color':'Invalid color hex',
                    'response-unknown':'Unknown response',
                    'response-db-connection-error':'Server internal connection error',
                    'response-update-dependency':'Image no longer exists',
                    'response-update-does-not-exist':'Tag no longer exists',
                    'response-update-success':'Tag updated',
                    'media-selector-reset':'Reset On Close',
                    'media-selector-keep-open':'Dont Reset On Close',
                    'info-weight':'Tag Weight',
                    'info-created':'Created',
                    'info-updated':'Updated',
                    'remove-image':'Remove Image',
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
            language:document.ioframe.selectedLanguage ?? '',
            originalTag:{},
            modifiedTag: {
                updated: {
                    original:'',
                    current:''
                },
                weight: {
                    original:0,
                    current:0
                },
                img: {
                    original: {},
                    current: {}
                }
            },
            //Needed during image selection
            mediaSelector: {
                open:false,
                hidden:false,
                keepOpen:false,
                selectMultiple:false,
                quickSelect:true,
                mode:null,
                selection:{}
            },
            enlargeImage:false,
            selectingAddress:false,
            viewUpToDate:false,
            //View target - editor
            target:'',
            //View url - editor
            url:'',
            //View elements - editor
            viewElements: {}
        };
    },
    created() {
        this.registerHub(eventHub);
        this.registerEvent('editor-media-selector-selection-event' ,this.thumbnailSelection);
        this.registerEvent('tagUpdateResponse', this.parseUpdateResponse);
        if(this.tagDetails.extraMetaParameters)
            for(let i in this.tagDetails.extraMetaParameters){
                Vue.set(this.modifiedTag, i, {
                    original:'',
                    current:''
                });
            }
        for(let i in this.tag)
            if(this.modifiedTag[i] !== undefined){
                Vue.set(this.modifiedTag[i],'original',this.tag[i]);
                Vue.set(this.modifiedTag[i],'current',this.tag[i]);
            }
        Vue.set(this,'originalTag',JSON.parse(JSON.stringify(this.tag)));
    },
    mounted: function(){
    },
    computed: {
        changed: function(){
            let changed = {};
            for(let i in this.modifiedTag){
                if(i === 'img')
                    changed[i] = JSON.stringify(this.modifiedTag[i].current) !== JSON.stringify(this.modifiedTag[i].original);
                else
                    changed[i] = this.modifiedTag[i].current !== this.modifiedTag[i].original;
            }
            return changed;
        },
        modifiedChanged: function () {
            for(let i in this.changed)
                if(this.changed[i])
                    return true;
            return false;
        },
        hasValidColor:function(){
            return !this.originalTag.color || this.modifiedTag.color.current.toLowerCase().match(/^[0-9a-f]{6}$/) !== null;
        },
        categoryTitle: function(){
            return this.categoryDetails[this.language]??this.categoryDetails['eng']??this.originalTag.category??'-';
        }
    },
    methods: {
        //Sets tag to current/Original
        resetTag: function(toCurrent = false){
            let order = toCurrent?['original','current']:['current','original'];
            {
                for(let i in this.modifiedTag)
                    if(i === 'img')
                        Vue.set(this.modifiedTag[i],order[0],JSON.parse(JSON.stringify(this.modifiedTag[i][order[1]])));
                    else
                        this.modifiedTag[i][order[0]] = this.modifiedTag[i][order[1]];
            }
        },
        //Checks inputs for validity
        checkInputs: function(){
            if(this.tagDetails.extraMetaParameters)
                for (let i in this.tagDetails.extraMetaParameters){
                    let extraParamInfo = this.tagDetails.extraMetaParameters[i];
                    if(extraParamInfo.required && !this.modifiedTag[i].current){
                        alertLog(this.commonText['create-missing-input-required']+' '+i,'warning',this.$el);
                        return false;
                    }
                    if(this.modifiedTag[i].current && extraParamInfo.valid && !this.modifiedTag[i].current.match(new RegExp(extraParamInfo.valid))){
                        alertLog(this.commonText['create-invalid-input']+' - '+i+', '+this.commonText['create-invalid-input-requires-regex']+' '+extraParamInfo.valid,'warning',this.$el);
                        return false;
                    }
                    else if( this.modifiedTag[i].current && extraParamInfo.color && !this.modifiedTag[i].current.toLowerCase().match(/^[0-9a-f]{6}$/) ){
                        alertLog(this.commonText['create-invalid-input']+' - '+i+': '+this.commonText['create-invalid-input-color'],'warning',this.$el);
                        return false;
                    }
                }
            return true;
        },
        //Parses update response
        parseUpdateResponse: function(response){

            if(this.test){
                alertLog(response,'info',this.$el);
                console.log(response);
                return;
            }

            let expectedIdentifier = this.tagName + '/' + (this.tagDetails.categories ? this.originalTag.category + '/' : '') + this.originalTag.identifier;

            if((typeof response !== 'object') || response.content[expectedIdentifier] === undefined){
                alertLog(this.commonText['response-unknown'],'error',this.$el);
                console.log(response,expectedIdentifier);
                return;
            }
            response = response.content;
            switch (response[expectedIdentifier]-0) {
                case -2:
                    alertLog(this.commonText['response-update-dependency'],'error',this.$el);
                    break;
                case -1:
                    alertLog(this.commonText['response-db-connection-error'],'error',this.$el);
                    break;
                case 0:
                    alertLog(this.commonText['response-update-success'],'success',this.$el,{autoDismiss:2000});
                    let request = {from:this.identifier,content:{}}
                    for(let i in this.modifiedTag)
                        request.content[i] = JSON.parse(JSON.stringify(this.modifiedTag[i].current));
                    if(Object.keys(request.content['img']).length === 0)
                        request.content['img'] = false;
                    this.modifiedTag.updated.current = (Date.now()/1000).toFixed(0);
                    this.resetTag(true);
                    eventHub.$emit('tagUpdated');
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
        //Resets media selector
        resetMediaSelector: function(){
            Vue.set(this,'mediaSelector',{
                open:false,
                hidden:false,
                keepOpen:false,
                selectMultiple:false,
                quickSelect:true,
                mode:null,
                selection:{}
            });
        },
        //Toggles media selector
        toggleMediaSelector: function(){
            if(this.mediaSelector.keepOpen)
                this.mediaSelector.hidden = !this.mediaSelector.hidden;
            else
                this.mediaSelector.open = !this.mediaSelector.open;
        },
        //Handles thumbnail selection
        thumbnailSelection: function(item){
            if(this.verbose)
                console.log('Setting thumbnail to ',item);

            if(item.local)
                item.address = item.relativeAddress;
            else
                item.address = item.identifier;
            item.meta = {};
            if(item.alt)
                item.meta.alt = item.alt;
            if(item.caption)
                item.meta.caption = item.caption;
            if(item.name)
                item.meta.name = item.name;

            item.updated = item.lastChanged;

            this.toggleMediaSelector();
            Vue.set(this.modifiedTag.img,'current',item);
        },
        //Removes image
        removeImage: function(){
            Vue.set(this.modifiedTag.img, 'current',{});
        },
        confirmOperation(){
            if(this.test)
                console.log('Current Operation ', 'Update Category' ,'Current input ',JSON.parse(JSON.stringify(this.modifiedTag)));

            if(!this.checkInputs())
                return;

            let data = new FormData();

            data.append('action',this.tagDetails['categories']?'setCategoryTags':'setBaseTags');
            data.append('update','1');
            data.append('type',this.tagName);
            if(this.originalTag.category)
                data.append('category',this.originalTag.category);
            if(this.test)
                data.append('req','test');


            let tag = {
                identifier: this.originalTag.identifier
            };
            for(let i in this.modifiedTag){
                if(this.changed[i]){
                    if(i === 'img')
                        tag[i] = Object.keys(this.modifiedTag[i].current).length > 0 ? this.modifiedTag[i].current.address : '@';
                    else
                        tag[i] = this.modifiedTag[i].current;
                }
            }

            data.append('tags',JSON.stringify([tag]));


            this.apiRequest(data, 'api/tags', 'tagUpdateResponse', {
                verbose: this.verbose,
                parseJSON: true
            })
        },
    },
    watch: {
    },
    template: `
<div class="tags-editor">
    <div class="wrapper">
        <div class="image-fields">
            <div class="fields">
                <div class="field identifier">
                    <label v-text="commonText['operation-create-identifier']"></label>
                    <input :value="originalTag.identifier" disabled>
                </div>
                <div class="field category" :class="{changed:changed.category}" v-if="tagDetails.categories">
                    <label v-text="commonText['operation-create-category']"></label>
                    <input :value="categoryTitle" disabled>
                </div>
                <div class="field created">
                    <label v-text="commonText['info-created']"></label>
                    <input :value="originalTag.created" disabled>
                </div>
                <div class="field updated">
                    <label v-text="commonText['info-updated']"></label>
                    <input :value="modifiedTag.updated.current" disabled>
                </div>
                <div class="field weight" :class="{changed:changed.weight}">
                    <label v-text="commonText['info-weight']"></label>
                    <input type="number" min="0" v-model:value="modifiedTag.weight.current">
                </div>
                <div :class="['field',index, {color:item.color,changed:changed[index],required:item.required}]" v-if="tagDetails.extraMetaParameters" v-for="item,index in tagDetails.extraMetaParameters">
                    <label v-text="item.title"></label>
                    <input v-model:value="modifiedTag[index].current" :required="item.required">
                    <span
                        v-if="item.color"
                        class="color"
                        :style="{background:'#'+modifiedTag[index].current??'ffffff'}"
                    ></span>
                </div>
                <div class="field thumbnail-preview" 
                  :class="{changed:changed['img']}"
                  v-if="tagDetails.img">
                    <button class="negative"
                            @click="removeImage()"
                            v-text="commonText['remove-image']"
                    ></button>
                    <img 
                    :src="extractImageAddress(modifiedTag.img.current) ? extractImageAddress(modifiedTag.img.current) : getMediaUrl('ioframe','icons/upload.svg')" 
                    @click.prevent="toggleMediaSelector">
                    <input :value="extractImageAddress(modifiedTag.img.current) ? modifiedTag.img.current.address : ' - '" disabled>
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
                    @click="resetTag()"
            >
                <div v-text="commonText['operation-cancel']"></div>
            </button>
        </div>
    </div>
    <div class="media-selector-container" :class="{hidden:mediaSelector.hidden}" dir="ltr"  v-if="mediaSelector.open">
        <div class="control-buttons">
            <button 
            @click.prevent="mediaSelector.keepOpen = !mediaSelector.keepOpen"
            v-text="mediaSelector.keepOpen?commonText['media-selector-reset']:commonText['media-selector-keep-open']"
            class="cancel"
            >
            </button>
            <img 
            :src="getMediaUrl('ioframe','icons/cancel-icon.svg')" 
            @click.prevent="toggleMediaSelector">
        </div>

        <div
             is="media-selector"
             :identifier="'editor-media-selector'"
             :test="test"
             :verbose="verbose"
        ></div>
    </div>
</div>
    `

});