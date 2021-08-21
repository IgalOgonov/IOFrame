if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('gallery-editor', {
    mixins:[
        eventHubManager,IOFrameCommons,sourceURL
    ],
    props: {
        //Gallery we are displaying. May be {} if nothing is selected. Remember, this is a Vue object, no clean prototype!
        gallery: {
            type: Object
        },
        //Identifier
        identifier: {
            type: String
        },
        //Whether we are dealing with image galleries or video galleries - possible values are 'img' and 'vid'
        galleryType: {
            type: String,
            default: 'img'
        },
        //Gallery members
        galleryMembers: {
            type: Array,
            default: function(){
                return [];
            }
        },
        //Current operation
        currentOperation: {
            type: String,
            default:''
        },
        //Currently selected gallery member
        selected: {
            type: Array,
            default: function(){
                return [];
            }
        },
        //Whether the gallery info is initiated
        initiated: {
            type: Boolean,
            default: false
        },
        view:{
            type: Object,
            default: function(){
                return {
                    elements: {},
                    selected: {},
                    upToDate: {},
                    target: {},
                    url: {}
                };
            }
        },
        searchList:{
            type: Object,
            default: function(){
                return {
                    elements: {},
                    selected: {},
                    upToDate: {},
                    target: {},
                    url: {}
                };
            }
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
    data: function(){
        return {
            //Whether we are currently initiating
            initiating: false,
            //Expected gallery name identifiers
            expectedNames: ['name'],
            //Expected gallery names
            galleryNames: {},
            //Type of media viewer - 'local' or 'db'
            viewerType: 'local',
        };
    },
    template: `
         <div class="gallery-editor">
            <div>
            <div class="info-container">
                <div class="properties">
                    <label v-for="(itemArr, item) in galleryNames"
                    >
                        <div v-text="itemArr.text"></div>
                        <input
                        :name="item"
                        class="property"
                        :class="{changed:itemArr.current !== itemArr.original}"
                        type="text"
                        v-model:value="itemArr.current"
                        :placeholder="itemArr.placeholder">
                    </label>
                </div>
                <div class="operations" v-if="edited">
                    <button class="update positive-1" @click.prevent="updateGalleryNames"">
                        <div v-text="'Confirm'"></div>
                        <img :src="sourceURL()+'img/icons/confirm-icon.svg'">
                    </button>
                    <button class="reset cancel-1" @click.prevent="resetGalleryNames">
                        <div v-text="'Reset'"></div>
                        <img :src="sourceURL()+'img/icons/cancel-icon.svg'">
                    </button>
                </div>
            </div>
            `+`

                <div v-if="galleryMembers.length === 0">
                Nothing to display!
                </div>

                <div  v-else=""
                is="media-viewer"
                class="gallery-viewer"
                :media-type="galleryType"
                :display-elements-ordered="galleryMembers"
                :multiple-targets="selected"
                :select-multiple="allowSelectMultiple"
                :allow-searching="false"
                :show-sizes="false"
                :show-names="false"
                :draggable="true"
                :test="test"
                :verbose="verbose"
                :identifier="identifier+'-viewer1'"
                ></div>

                <div v-if="needViewer">
                    <h1 >Select images from bellow:</h1>

                    <div class="types">
                        <button class="positive-3" :class="{selected:viewerType === 'local'}" @click.prevent="viewerType = 'local'">Local</button>
                        <button class="positive-3" :class="{selected:viewerType === 'db'}" @click.prevent="viewerType = 'db'">Remote</button>
                    </div>

                    <div  v-if="viewerType === 'local'"
                    is="media-viewer"
                    :media-type="galleryType"
                    :url="view.url"
                    :target="view.target"
                    :display-elements="view.elements"
                    :select-multiple="allowSelectMultiple"
                    :multiple-targets="view.selected"
                    :initiate="!view.upToDate"
                    :verbose="verbose"
                    :test="test"
                    :identifier="identifier+'-viewer2'"
                    ></div>
                    <div    v-if="viewerType === 'db'"
                    is="search-list"
                    :_functions="searchList.functions"
                    :api-url="searchList.url"
                    :extra-params="searchList.extraParams"
                    :extra-classes="searchList.extraClasses"
                    api-action="getImages"
                    :page="searchList.page"
                    :limit="searchList.limit"
                    :total="searchList.total"
                    :items="searchList.items"
                    :initiate="!searchList.initiated"
                    :columns="searchList.columns"
                    :filters="searchList.filters"
                    :selected="searchList.selected"
                    :test="test"
                    :verbose="verbose"
                    :identifier="identifier+'-search'"
                    ></div>
                </div>
            </div>
         </div>
        `,
    methods: {
        //Updates gallery name(s)
        updateGalleryNames: function(){

            if(!this.edited){
                if(this.verbose)
                    console.log('Gallery names have not changed, will not update!');
                return;
            }

            //Data to be sent
            let debug = {};
            var data = new FormData();
            data.append('action', this.galleryType === 'img' ? 'setGallery' : 'setVideoGallery');
            data.append('gallery', this.gallery.identifier);
            data.append('update', true);
            for(let i in this.galleryNames){
                if(this.galleryNames[i]['original'] !== this.galleryNames[i]['current']){
                    data.append(i, this.galleryNames[i]['current']);
                    debug[i] = this.galleryNames[i]['current'];
                }
            }

            if(this.verbose)
                console.log('Sending gallery data ',debug);

            if(this.test)
                data.append('req', 'test');

            this.apiRequest(
                data,
                "api/v1/media",
                'galleryUpdateResponse',
                {
                    'verbose': this.verbose,
                    'identifier':this.identifier
                }
            );
        },
        //Responds to gallery names update
        galleryUpdateResponse: function(request){
            if(this.verbose)
                console.log('galleryUpdateResponse got', request);

            if(!request.from || request.from !== this.identifier)
                return;

            let response = request.content;

            if(response == 0){
                this.setGalleryNameToCurrents();
                eventHub.$emit('searchAgain');
            }
            else if(response == -1){
                alertLog('gallery names not updated, server error occurred.','error',this.$el);
            }
            else if(response == 1){
                alertLog('gallery names not updated, gallery no longer exists.','error',this.$el);
            }
            else{
                alertLog('gallery names not updated, unknown error: '+response,'error',this.$el);
            }
        },
        //Initiates the gallery info from the API
        initiateGallery: function(){
            //Make sure we're not initiating already
            if(this.initiating){
                if(this.verbose)
                    console.log('Gallery already initiating!');
                return;
            }
            else
                this.initiating = true;

            if(this.verbose)
                console.log('Querying API for gallery '+this.gallery.identifier);

            //Data to be sent
            var data = new FormData();
            data.append('action', this.galleryType === 'img' ? 'getGallery' : 'getVideoGallery');
            data.append('gallery', this.gallery.identifier);
            //Api url
            let apiURL = document.pathToRoot+"api/v1/media";
            var test = this.test;
            var verbose = this.verbose;
            var thisElement = this.$el;
            var identifier = this.identifier;
            //Assume request succeeded and images are not gonna be cropped
            this.imagesNeedCropping = true;
            //Request itself
            updateCSRFToken().then(
                function(token){
                    data.append('CSRF_token', token);
                    fetch(
                        apiURL,
                        {
                            method: 'post',
                            body: data,
                            mode: 'cors'
                        }
                    )
                        .then(function (json) {
                            return json.text();
                        })
                        .then(function (data) {
                            if(verbose)
                                console.log('Request succeeded!');
                            let response;
                            //A valid response would be a JSON
                            if(IsJsonString(data)){
                                response = JSON.parse(data);
                                if(response.length === 0)
                                    response = {};
                            }
                            //Any non-json response is invalid
                            else
                                response = data;
                            if(verbose)
                                console.log('Request data',response);
                            const request = {
                                from:identifier,
                                content:response
                            };
                            if(verbose)
                                console.log('Emitting parseGallery', request);
                            eventHub.$emit('parseGallery', request);
                        })
                        .catch(function (error) {
                            alertLog('View initiation failed! '+error,'error',thisElement);
                            eventHub.$emit('parseGallery', {});
                        });
                },
                function(reject){
                    alertLog('CSRF token expired. Please refresh the page to submit the form.','error',thisElement);
                    eventHub.$emit('parseGallery', {});
                }
            );
        },
        //Selects a picture inside the main or secondary gallery
        selectElement: function(request){
            if(!request.from || request.from !== this.identifier+'-viewer1')
                return;

            if(this.test)
                console.log('Recieved', request);

            let newTarget =  request.key - 0;

            eventHub.$emit('requestSelectionGallery',newTarget);
        },
        //Signifies the query for the gallery is done
        parseGalleryResponse: function(){
            this.initiating = false;
        },
        //Reset gallery names
        resetGalleryNames: function(){
            for(let i in this.galleryNames){
                this.galleryNames[i]['current'] = this.galleryNames[i]['original'];
            }
        },
        //Sets gallery names to current
        setGalleryNameToCurrents: function(){
            for(let i in this.galleryNames){
                this.galleryNames[i]['original'] = this.galleryNames[i]['current'];
            }
        },
    },
    computed:{
        allowSelectMultiple: function(){
            if(this.currentOperation === 'remove'){

            }
        },
        //We only need the viewer when adding a picture from media to a gallery.
        needViewer:function(){
            return this.currentOperation === 'add';
        },
        //See if anything was edited
        edited: function(){
            for(let i in this.galleryNames){
                if(this.galleryNames[i].original !== this.galleryNames[i].current)
                    return true;
            }
            return false;
        }
    },
    created:function(){
        if(this.verbose)
            console.log('Editor ',this.identifier,' created, current gallery: ',this.gallery);
        this.registerHub(eventHub);
        this.registerEvent('select', this.selectElement);
        this.registerEvent('parseGallery', this.parseGalleryResponse);
        this.registerEvent('galleryUpdateResponse', this.galleryUpdateResponse);

        //Get expected names
        for(let i in document.languages){
            this.expectedNames.push(document.languages[i]+'_name');
        }
        //See which names the gallery has, and which it needs to get
        for(let i in this.expectedNames){
            let name = this.expectedNames[i];
            let existing = this.gallery[name] !== undefined? this.gallery[name] : '';
            Vue.set(this.galleryNames,name,{
                original:existing,
                current:existing,
                text:(!document.languages[i-1] ? 'Gallery Name' : 'Gallery Name ['+document.languages[i-1]+']')
            });
        }
    },
    beforeMount:function(){
        if(this.verbose)
            console.log('Editor ',this.identifier,' beforeMount');
    },
    mounted:function(){
        if(this.verbose)
            console.log('Editor ',this.identifier,' mounted');
        if(!this.initiated && !this.initiating){
            if(this.verbose)
                console.log('Initiating at mount!');
            this.initiateGallery();
        }
    },
    beforeUpdate: function(){
        if(this.verbose)
            console.log('Editor ',this.identifier,' beforeUpdate');
    },
    updated: function(){
        if(this.verbose)
            console.log('Editor ',this.identifier,' updated');
        if(!this.initiated && !this.initiating){
            if(this.verbose)
                console.log('Initiating at update!');
            this.initiateGallery();
        }
    },
    watch:{
        viewerType: function(){
            eventHub.$emit('resetEditorView');
        }
    }
});