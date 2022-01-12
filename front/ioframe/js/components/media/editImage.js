if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('media-editor', {
    mixins:[eventHubManager,IOFrameCommons,sourceURL],
    props: {
        //Identifier
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
        },
        //Whether we are dealing with images or videos - possible values are 'img' and 'vid'
        mediaType: {
            type: String,
            default: 'img'
        },
        //Identifier
        //Elements we are displaying
        image: {
            type: Object,
            default: function () {
                return {}
            }
        },
        //The current url which is viewed
        url: {
            type: String,
            default: ''
        },
        //Type
        type: {
            type: String,
            default: 'local' //other possibility is 'remote'
        },
        //Current target, if any
        target: {
            type: String,
            default: ''
        }
    },
    data: function(){
        return {
            languages: JSON.parse(JSON.stringify(document.languages)),
            newImageInfo:{
                name:'',
                caption:''
            },
            expectedMeta:{
                name:{
                    text:'Name',
                    placeholder:'Image has no name',
                    type:'input'
                },
                caption:{
                    text:'Caption',
                    placeholder:'Image has no description',
                    type:'textarea'
                },
            },
            h: 0,
            w: 0,
            duration:0,
            initiated:false,
            edited:false,
            //Galleries the image belongs to
            galleries: [],
            //Whether the galleries have been initiated
            galleriesInitiated: false
        }
    },
    template: `
         <div class="image-editor">
            <div class="image-container">
                <img  v-if="mediaType === 'img'" :src="imageURL">
                <video v-else="" class="upload-preview" :src="imageURL" controls="true" loop="true" muted="true" preload="auto"></video>
            </div>
            <div class="info-container">
                <div class="properties">
                    <label v-for="(itemArr, item) in expectedMeta"
                    >
                        <div v-text="itemArr.text"></div>
                        <input
                        v-if="itemArr.type === 'input'"
                        :name="item"
                        class="property"
                        :class="[item]"
                        type="text"
                        v-model:value="newImageInfo[item]"
                        :placeholder="itemArr.placeholder">

                        <textarea
                        v-if="itemArr.type === 'textarea'"
                        :name="item"
                        class="property"
                        :class="[item]"
                        v-model:value="newImageInfo[item]"
                        :placeholder="itemArr.placeholder"
                        ></textarea>

                        <button
                        v-else-if="itemArr.type === 'toggle'"
                        :name="item"
                        class="property"
                        :class="[item,(newImageInfo[item]? 'positive-1' : 'negative-1')]"
                        @click.prevent="newImageInfo[item] = !newImageInfo[item]"
                        v-text="newImageInfo[item]? 'Yes' : 'No'"
                        ></button>
                    </label>
                </div>
                
                <div class="properties">
                    <label v-if="type==='local'" for="size" v-text="'Size'"></label>
                    <div v-if="type==='local'" name="size"" class="size property"  v-text="getImageSize"></div>
                    <label for="dimensions" v-text="'Dimensions (W x H)'"></label>
                    <div name="dimensions" class="dimensions property"  v-text="w + ' x ' + h"></div>
                    <label v-if="mediaType !== 'img'" for="duration" v-text="'Duration (s)'"></label>
                    <div v-if="mediaType !== 'img'" name="duration" class="duration property"  v-text="this.duration + ' seconds'"></div>
                    <label for="address" v-text="'Address'"></label>
                    <div name="address" class="address property" v-text="getImageAddress"></div>
                </div>
                <div class="galleries-container">
                    <h1 class="gallery-title" v-if="galleriesInitiated" v-text="'Galleries'">
                    </h1>
                    <div class="gallery" v-if="galleriesInitiated" v-for="(item,index) in galleries">
                        {{item}}
                    </div>
                    <button class="initiate-galleries" v-if="!galleriesInitiated" v-text="'Show Galleries'" @click.prevent="initiateGalleriesRequest">
                    </button>
                </div>
            </div>
            <div class="operations" v-if="edited">
                <button class="update positive-1" @click.prevent="updateImage"">
                    <div v-text="'Confirm'"></div>
                    <img :src="sourceURL()+'img/icons/confirm-icon.svg'">
                </button>
                <button class="reset cancel-1" @click.prevent="resetImage">
                    <div v-text="'Reset'"></div>
                    <img :src="sourceURL()+'img/icons/cancel-icon.svg'">
                </button>
            </div>
         </div>
        `,
    methods: {
        updateImage: function(){

            if(!this.imageChanged){
                if(this.verbose)
                    console.log('Image has not changed, will not update!');
                return;
            }

            //Data to be sent
            var data = new FormData();
            data.append('action', (this.mediaType === 'img' ? 'updateImage' : 'updateVideo'));
            let address;
            if(this.type === 'local'){
                address =  (this.url === '')? this.url : this.url+'/';
                address += this.target;
            }
            else
                address = this.image.identifier;
            data.append('address', address);
            data.append('deleteEmpty', true);
            for(let i in this.expectedMeta){
                data.append(i, this.newImageInfo[i]);
            }
            if(this.type !== 'local')
                data.append('remote', true);
            if(this.test)
                data.append('req', 'test');

            //Api url
            var apiURL = document.pathToRoot+"api/v1/media";
            var verbose = this.verbose;
            var identifier = this.identifier;
            var thisElement = this.$el;
            var test = this.test;
            let context = this;
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
                            let response = data;
                            if(verbose)
                                console.log('Request data',response);

                            const request = {
                                from:identifier,
                                content:response
                            };

                            eventHub.$emit('imageUpdateResponse', request);
                        })
                        .catch(function (error) {
                            alertLog('View initiation failed! '+error,'error',thisElement);
                        });
                },
                function(reject){
                    alertLog('CSRF token expired. Please refresh the page to submit the form.','error',thisElement);
                }
            );
        },
        resetImage: function(){
            for(let i in this.expectedMeta){
                if(this.image[i] !== undefined)
                    this.newImageInfo[i] = this.image[i];
                else
                    this.newImageInfo[i] = '';
            }

            this.edited = false;
        },
        initiateGalleries: function(request){
            if(!request.from || request.from !== this.identifier)
                return;
            let response = request.content;
            if(typeof response !== 'object'){
                alertLog('Galleries could not be initiated!','warning',this.$el.querySelector('.galleries-container'));
                return;
            }
            if(response.length === 0)
                response = ['*No Galleries Found'];
            this.galleries = response;
            this.galleriesInitiated = true;
        },
        initiateGalleriesRequest: function(){
            if(this.verbose)
                console.log('Querying API for '+this.url);

            //Data to be sent
            var data = new FormData();
            data.append('action', (this.mediaType === 'img' ? 'getGalleriesOfImage' : 'getGalleriesOfVideo'));
            let address;
            if(this.type === 'local'){
                address =  (this.url === '')? this.url : this.url+'/';
                address += this.target;
            }
            else
                address = this.image.identifier;
            data.append('address', address);

            //Api url
            var apiURL = document.pathToRoot+"api/v1/media";
            var test = this.test;
            var verbose = this.verbose;
            var identifier = this.identifier;
            var thisElement = this.$el;

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
                                console.log('Emitting initiateGalleries', request);
                            eventHub.$emit('initiateGalleries', request);
                        })
                        .catch(function (error) {
                            alertLog('View initiation failed! '+error,'error',thisElement);
                        });
                },
                function(reject){
                    alertLog('CSRF token expired. Please refresh the page to submit the form.','error',thisElement);
                }
            );
        },
        checkIfImagePropertiessChanged: function(){
            this.edited = this.imageChanged;
        },
        updateImageDimensions: function(request){
            if(this.initiated)
                return;
            let media = this.$el.querySelector('.image-container > *');
            if(request.from === this.identifier){
                if(this.mediaType === 'img'){
                    this.w = media.naturalWidth;
                    this.h = media.naturalHeight;
                }
                else{
                    this.w = media.videoWidth;
                    this.h = media.videoHeight;
                    this.duration = media.duration;
                }
            }
            this.initiated = true;
        },
        //Handles responses
        imageUpdateResponse: function(request){
            if(this.verbose)
                console.log('imageUpdateResponse got', request);

            if(!request.from || request.from !== this.identifier)
                return;

            let response = JSON.parse(JSON.stringify(request.content));

            if(response == 0){
                if(this.verbose)
                    console.log(this.type+' image updated!', response);
                request.content = JSON.parse(JSON.stringify(this.newImageInfo));
                eventHub.$emit((this.type === 'local'?'updateViewElement':'updateSearchListElement'), request);
            }
            else if(response == -1){
                alertLog(this.type+' image not updated, server error occurred.','error',this.$el);
            }
            else if(response == 1){
                alertLog(this.type+' image not updated, image no longer exists.','error',this.$el);
            }
            else{
                alertLog(this.type+' image not updated, unknown error: '+response,'error',this.$el);
            }
        },

    },
    computed:{
        imageURL: function(){
            let result;
            if(this.type === 'local'){
                result = document.rootURI + document[(this.mediaType=== 'img'?'imagePathLocal':'videoPathLocal')];
                result += (this.url === '')? this.url : this.url+'/';
                result += this.target;
            }
            else{
                if(!this.image.dataType)
                    result = this.image.identifier;
                else
                    result = document.rootURI+'api/v1/media?action=getDBMedia&address='+this.image.identifier+'&resourceType='+this.mediaType+'&lastChanged='+this.image.lastChanged;
            }
            return result;
        },
        getImageSize: function(){
            //Render a "pretty" size
            const size = "" + this.image.size;
            let result = [];
            for(let i = size.length-1; i>=0; i--){
                result.push(result,size[i]);
                if( ( (size.length-1 - i) % 3 === 2 ) && i!==0)
                    result.push(result,',');
            }
            const prettySize = result.reverse().join('');

            //Calculate GB/KB/Etc
            let magnitude = 0;
            let bytes = this.image.size;
            while(bytes > 1000 && magnitude < 5){
                bytes = bytes/1000;
                magnitude++;
            }
            let suffix;
            switch (magnitude){
                case 1:
                    suffix = 'KB';
                    break;
                case 2:
                    suffix = 'MB';
                    break;
                case 3:
                    suffix = 'GB';
                    break;
                case 4:
                    suffix = 'TB';
                    break;
                default:
                    suffix = 'B';
            }
            const approx = Math.round(bytes)+suffix;

            return  prettySize + ' (' + approx + ')';
        },
        getImageAddress: function(){
            if(this.type === 'local'){
                let url = 'Media Folder';
                if(this.url!=='')
                    url += '/'+this.url;
                url += '/'+this.target;
                return url;
            }
            if(!this.image.dataType)
                return this.image.identifier;
            else
                return 'Database';
        },
        imageChanged: function(){
            for(let type in this.expectedMeta){
                if( (this.image[type] !== undefined && this.newImageInfo[type] !== this.image[type]) ||
                (this.image[type] === undefined && this.newImageInfo[type] !== '') )
                    return true;
            }
            return false;
        }
    },
    created:function(){
        if(this.verbose)
            console.log('Viewer ',this.identifier,' created');
        //Checks if image properties were changed
        this.registerHub(eventHub);
        this.registerEvent('changed',this.checkIfImagePropertiessChanged);
        this.registerEvent('updateImageDimensions',this.updateImageDimensions);
        this.registerEvent('initiateGalleries',this.initiateGalleries);
        this.registerEvent('imageUpdateResponse',this.imageUpdateResponse);
        //Add all right properties depending on languages
        for(let i in this.languages){
            let lang = this.languages[i];
            Vue.set(this.newImageInfo,lang+'_caption','');
            Vue.set(this.newImageInfo,lang+'_name','');
            Vue.set(this.expectedMeta,lang+'_name',{
                text:'Name ['+lang+']',
                placeholder:'Image has no '+lang+' name',
                type:'input'
            });
            Vue.set(this.expectedMeta,lang+'_caption',{
                text:'Caption ['+lang+']',
                placeholder:'Image has no '+lang+' caption',
                type:'textarea'
            });
        }
        //Dpending on whether we are uploading a video or image
        if(this.mediaType === 'img'){
            Vue.set(this.expectedMeta,'alt', {
                text:'Alt',
                placeholder:'Image ALT',
                type:'input'
            });
            Vue.set(this.newImageInfo,'alt', '');
        }
        else{
            Vue.set(this.expectedMeta,'autoplay', {
                text:'Autoplay?',
                type:'toggle'
            });
            Vue.set(this.newImageInfo,'autoplay', false);
            Vue.set(this.expectedMeta,'loop', {
                text:'Loop?',
                type:'toggle'
            });
            Vue.set(this.newImageInfo,'loop', true);
            Vue.set(this.expectedMeta,'mute', {
                text:'Start Muted?',
                type:'toggle'
            });
            Vue.set(this.newImageInfo,'mute', true);
            Vue.set(this.expectedMeta,'controls', {
                text:'Show Controls?',
                type:'toggle'
            });
            Vue.set(this.newImageInfo,'controls', false);
            Vue.set(this.expectedMeta,'poster', {
                text:'Poster (URL)',
                placeholder:'Placeholder image URL (absolute!)',
                type:'input'
            });
            Vue.set(this.newImageInfo,'poster', '');
        }
    },
    mounted: function(){
        if(this.verbose)
            console.log('Editor ',this.identifier,' mounted');
        this.resetImage();
        var image = this.$el.querySelector('.image-container > *');
        var identifier = this.identifier;
        var verbose = this.verbose;

        if(this.mediaType === 'img')
            image.onload = function(){
                const request = {
                    from:identifier
                };
                if(verbose)
                    console.log('Emitting updateImageDimensions', request);
                eventHub.$emit('updateImageDimensions', request);
            };
        else
            image.oncanplay = function(){
                const request = {
                    from:identifier
                };
                if(verbose)
                    console.log('Emitting updateImageDimensions', request);
                eventHub.$emit('updateImageDimensions', request);
            };
    },
    updated: function(){
        if(this.verbose)
            console.log('Editor ',this.identifier,' updated');
    },
    watch: {
        newImageInfo:{
            handler: function(val, oldVal){
                this.edited = this.imageChanged;
                /*
                 TODO Fix this
                 var identifier = this.identifier;
                 var verbose = this.verbose;
                 const changeFunc = function(){
                 const request = {
                 from:identifier
                 };
                 if(verbose)
                 console.log('Emitting changed', request);
                 eventHub.$emit('changed', request);
                 };
                 debounce(
                 changeFunc,
                 500,
                 this.identifier+'_image_editing_debounce'
                 );
                 */
            },
            deep:true
        },
        image:{
            handler: function(val, oldVal){
                if(this.verbose)
                    console.log('Image changed!');
                this.resetImage();
            },
            deep:true
        }
    }
});