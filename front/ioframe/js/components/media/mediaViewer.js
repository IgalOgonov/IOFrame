if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('media-viewer', {
    mixins:[
        eventHubManager,
        sourceURL
    ],
    props: {
        //Test Mode
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
        //Whether to initiate the view on creation
        initiate: {
            type: Boolean,
            default: true
        },
        //Whether we are dealing with images or videos - possible values are 'img' and 'vid'
        mediaType: {
            type: String,
            default: 'img'
        },
        //Elements we are displaying
        displayElements: {
            type: Object,
            default: function () {
                return {}
            }
        },
        //Elements we are displaying
        displayElementsOrdered: {
            type: Array,
            default: function () {
                return [];
            }
        },
        //The current url which is viewed
        url: {
            type: String,
            default: ''
        },
        //Current target, if any
        target: {
            type: String,
            default: ''
        },
        //Current index, if any
        targetIndex: {
            type: Number,
            default: -1
        },
        //Multiple targets, if any
        multipleTargets: {
            type: Array,
            default: function () {
                return []
            }
        },
        //Whether we are allowed to select one, or multiple objects (cannot be both!)
        selectMultiple: {
            type: Boolean,
            default: false
        },
        //Whether we are showing only folders
        onlyFolders: {
            type: Boolean,
            default: false
        },
        //Whether we are showing only pictures
        onlyPictures: {
            type: Boolean,
            default: false
        },
        //Whether the items should be draggable
        draggable: {
            type: Boolean,
            default: false
        },
        //Whether we are allowing searching - and having a search bar
        allowSearching: {
            type: Boolean,
            default: true
        },
        //Whether we are showing sizes
        showSizes: {
            type: Boolean,
            default: true
        },
        //Whether we are showing names
        showNames: {
            type: Boolean,
            default: true
        },
        //Different Source URL
        urlSource: {
            type: String,
            default: 'ioframe'
        },
    },
    data: function(){
        return {
            //Whether we are editing the URL
            editing: false,
            //Is only true while the view is being initiated
            initiatingView: false,
            //Whether we cropped the images by now
            imagesNeedCropping: true,
            //Identifier of the element we started dragging
            dragStartElement: '',
            //Identifier of the element we are currently above dragging
            dragAboveElement: ''
        }
    },
    template: '\
         <div class="media-viewer">\
            <div class="media-url-container" v-if="allowSearching">\
                <img class="media-url-icon" :src="absoluteIOFrameImage(\'icons/home-icon.svg\')" @click.prevent="goToRoot">\
                <img class="media-url-icon" :src="absoluteIOFrameImage(\'icons/up-arrow-icon.svg\')" @click.prevent="folderUp">\
                <img class="media-url-icon" :src="absoluteIOFrameImage(\'icons/refresh-icon.svg\')" @click.prevent="changeURLRequest(url)">\
                <img class="media-url-icon" :src="absoluteIOFrameImage(\'icons/search-folder-icon.svg\')" @click.prevent="toggleEditing">\
                <input class="media-url" type="text" :value="url" placeholder="Media Folder" :disabled="!editing">\
                <button v-if="editing" class="media-url-change" @click.prevent="changeURL">Go</button>\
            </div>\
            <div class="media-display">\
                <figure\
                class="media-object-container" \
                v-for="(item, key) in elementsToDisplay"\
                :media-identifier="item.local? item.relativeAddress : item.identifier"\
                :class="{mediaFolder:item.folder, selected:shouldBeSelected(key), draggedOver:isDraggedOver(key)}"\
                :item-identifier="key"\
                @click.prevent="requestSelection(key)"\
                >\
                    <div class="image-size-wrapper">\
                        <div class="image-size" v-if="item.size > 0 && showSizes"> {{readableSize(item.size)}} </div>\
                     </div>\
                    <div class="thumbnail-container">\
                        <img \
                            v-if="item.folder" \
                            :src="absoluteIOFrameImage(\'icons/folder.png\')"\
                            :draggable="draggable"\
                            ondragstart="eventHub.$emit(\'dragStart\',event)"\
                            ondragenter="eventHub.$emit(\'dragEnter\',event)"\
                            ondragleave="eventHub.$emit(\'dragLeave\',event)"\
                            ondrop="eventHub.$emit(\'dragDrop\',event)"\
                            ondragover="event.preventDefault()"\
                        >\
                        <img \
                            v-else-if="mediaType === \'img\'" \
                            :src="extractImageURL(item)"\
                            :draggable="draggable"\
                            ondragstart="eventHub.$emit(\'dragStart\',event)"\
                            ondragenter="eventHub.$emit(\'dragEnter\',event)"\
                            ondragleave="eventHub.$emit(\'dragLeave\',event)"\
                            ondrop="eventHub.$emit(\'dragDrop\',event)"\
                            ondragover="event.preventDefault()"\
                        >\
                        <video \
                            v-else-if="mediaType === \'vid\'" \
                            :src="extractImageURL(item)"\
                            preload="metadata"\
                            :draggable="draggable"\
                            ondragstart="eventHub.$emit(\'dragStart\',event)"\
                            ondragenter="eventHub.$emit(\'dragEnter\',event)"\
                            ondragleave="eventHub.$emit(\'dragLeave\',event)"\
                            ondrop="eventHub.$emit(\'dragDrop\',event)"\
                            ondragover="event.preventDefault()"\
                        ></video>\
                    </div>\
                     <figcaption v-if="showNames" v-text="extractName(item)"></figcaption>\
                </figure>\
            </div>\
         </div>\
        ',
    methods: {
        //Extracts the name of an item
        extractName: function(item){
            if(document.selectedLanguage && (item[document.selectedLanguage+'_name'] !== undefined) )
                return item[document.selectedLanguage+'_name'];
            else if(item.name){
                return item.name;
            }
            else if(item.local){
                return this.createDisplayName(item.relativeAddress);
            }
            else
                return item.identifier;
        },
        viewInitiated: function(){
            this.initiatingView = false;
        },
        initiateView: function(url){
            //This is only allowed if we are allowed to search for items
            if(!this.allowSearching){
                if(this.verbose)
                    console.log('Not allowed to search for items!');
                return;
            }
            //Make sure we're not initating view while another initiation is underway
            if(this.initiatingView){
                if(this.verbose)
                    console.log('View already initiating!');
                return;
            }
            else
                this.initiatingView = true;
            if(this.verbose)
                console.log('Querying API for '+url);
            //TODO Signify we're waiting for a response
            //Data to be sent
            let data = new FormData();
            data.append('action', (this.mediaType === 'img' ? 'getImages' : 'getVideos'));
            if(url!=='')
                data.append('address', url);
            //Api url
            let apiURL = document.pathToRoot+"api/v1/media";
            var test = this.test;
            var verbose = this.verbose;
            var thisElement = this.$el;
            var identifier = this.identifier;
            var onlyFolders = this.onlyFolders;
            var onlyPictures = this.onlyPictures;
            //Assume request succeeded and images are not gonna be cropped
            this.imagesNeedCropping = true;
            //Request itself
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
                    //If we are only returning folders, delete all results that aren't ones
                    if(onlyFolders || onlyPictures){
                        for(let k in response){
                            if( (response[k].folder && onlyPictures) || (!response[k].folder && onlyFolders) )
                                delete(response[k]);
                        }
                    }
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
                    console.log('Emitting viewElementsUpdated', request);
                eventHub.$emit('viewElementsUpdated', request);
                eventHub.$emit('viewInitiated', request);
            })
            .catch(function (error) {
                alertLog('View initiation failed! '+error,'error',thisElement);
                    eventHub.$emit('viewInitiated', request);
            });
        },
        folderUp: function(){
            if(this.url === '')
                alertLog('Already at root folder, cannot go up!','warning',this.$el);
            else{
                let newURL = this.url.split('/');
                newURL.pop();
                newURL = newURL.join('/');
                this.changeURLRequest(newURL);
            }
        },
        goToRoot: function(){
            if(this.url === '')
                alertLog('Already at root folder!','warning',this.$el);
            else{
                this.changeURLRequest('');
            }
        },
        toggleEditing: function(){
            this.imagesNeedCropping = false;
            this.editing = !this.editing;
        },
        changeURL: function(){
            const newURL = this.$el.querySelector('.media-url').value;
            if(this.url === newURL){
                if(this.verbose){
                    console.log('Same URL');
                }
            }
            else
                this.changeURLRequest(newURL);
            this.editing = false;
        },
        //Send request to change current URL
        changeURLRequest: function(newURL){
            const request = {
                from:this.identifier,
                content:newURL
            };
            if(this.verbose)
                console.log('Emitting changeURLRequest', request);
            eventHub.$emit('changeURLRequest', request);
            this.imagesCropped = false;
        },
        absoluteIOFrameImage:function(relativeURL){
            return this.sourceURL('ioframe')+'img/'+relativeURL;
        },
        extractImageURL: function(img){
            return img.local?
                this.absoluteMediaURL(img.relativeAddress) :
                (img.dataType? this.calculateDBImageLink(img) : img.identifier)
        },
        absoluteMediaURL:function(relativeURL , type = this.mediaType){
            return document.rootURI + document[(type=== 'img'?'imagePathLocal':'videoPathLocal')]+relativeURL;
        },
        calculateDBImageLink: function(item){
            let url = document.rootURI+'api/media?action=getDBMedia&address='+item.identifier+'&resourceType='+this.mediaType;
            if(item.lastChanged)
                url = url+'&lastChanged='+item.lastChanged.toString();
            return url;
        },
        createDisplayName:function(relativeURL){
            return relativeURL.split('/').pop();
        },
        cropImages: function(){
            let elements = this.elementsToDisplay;
            if(Object.keys(elements).length > 0){
                for( let k in elements ){
                    if(!elements.folder){
                        //In case we are iterating over an ordered array, the identifier will be inside the object
                        if(elements[k].identifier)
                            k = elements[k].identifier;
                        let element = this.$el.querySelector('.media-display > figure[media-identifier="'+k+'"]');
                        let thumbnail = element.querySelector('.thumbnail-container');
                        let image = thumbnail.querySelector('img');
                        let verbose = this.verbose;
                        if(!image)
                            continue;
                        image.onload = function () {
                            let naturalWidth = image.naturalWidth;
                            let naturalHeight = image.naturalHeight;
                            if(naturalWidth < 150 && naturalWidth < 150){
                                if(verbose)
                                    console.log('Centering ' + k, naturalWidth, naturalHeight);
                                thumbnail.classList.add('centerCrop');
                            }
                            else if(naturalHeight > naturalWidth){
                                thumbnail.classList.add('verticalCrop');
                                if(verbose)
                                    console.log('cropping ' + k +' vertically', naturalWidth, naturalHeight);
                            }
                            else if(naturalHeight < naturalWidth){
                                thumbnail.classList.add('horizontalCrop');
                                if(verbose)
                                    console.log('cropping ' + k +' horizontally', naturalWidth, naturalHeight);
                            }
                        };
                    }
                };
                this.imagesCropped = true;
            }
            else{
                if(this.verbose)
                    console.log('No images found to crop');
            }
        },
        readableSize: function(bytes){
            return getReadableSize(bytes);
        },
        requestSelection: function(key){
            this.imagesNeedCropping = false;
            const request = {
                from:this.identifier,
                key:key,
                content:this.displayElements[key]
            };
            if(this.verbose)
                console.log('Emitting select', request);
            eventHub.$emit('select', request);
        },
        shouldBeSelected: function(key){
            //In case we have an ordered array
            if(this.displayElementsOrdered.length !== 0){
                return (key === this.targetIndex || this.multipleTargets.indexOf(key) !== -1);
            }
            //In case of an assoc array
            else{
                let name = key.split('/').pop();
                return (this.target === name || this.multipleTargets.indexOf(name) !== -1);
            }
        },
        isDraggedOver: function(key){
            return(this.dragAboveElement !== '' && key == this.dragAboveElement && key != this.dragStartElement);
        },
        // ---- DRAG RELATED STARTS HERE ----
        dragStart: function(event){

            const mediaObject = event.target.parentNode.parentNode;
            const mediaIdentifier = mediaObject.attributes['item-identifier'].value;
            if(this.verbose)
                console.log('Started dragging object '+mediaIdentifier);

            this.dragStartElement = mediaIdentifier;
        },
        dragEnter: function(event){

            const mediaObject = event.target.parentNode.parentNode;
            const mediaIdentifier = mediaObject.attributes['item-identifier'].value;
            if(this.verbose)
                console.log('Entered object '+mediaIdentifier);

            this.dragAboveElement = mediaIdentifier;
        },
        dragLeave: function(event){

            const mediaObject = event.target.parentNode.parentNode;
            const mediaIdentifier = mediaObject.attributes['item-identifier'].value;
            if(this.verbose)
                console.log('Left object '+mediaIdentifier);

            this.dragAboveElement = '';
        },
        dragDrop: function(event){
            event.preventDefault();

            const mediaObject = event.target.parentNode.parentNode;
            const mediaIdentifier = mediaObject.attributes['item-identifier'].value;
            if(this.verbose)
                console.log('Dropped current object at object '+mediaIdentifier);

            //Emit a request unless we are dropping an item onto itself somehow
            if(this.dragStartElement !== mediaIdentifier){
                const request = {
                    from: this.identifier,
                    content: [this.dragStartElement,mediaIdentifier]
                }
                if(this.verbose)
                    console.log('Emitting drop',request);
                eventHub.$emit('drop',request);
            }

            this.dragStartElement = '';
            this.dragAboveElement = '';
        },
        // ---- DRAG RELATED ENDS HERE ----
    },
    computed:{
        elementsToDisplay:function(){
            if(this.displayElementsOrdered.length !== 0)
                return this.displayElementsOrdered;
            else
                return this.displayElements;
        },
    },
    created:function(){
        if(this.verbose)
            console.log('Viewer ',this.identifier,' created');
        //When this is emitted, it means we finished fetching a request for view initiation and sent a valid update
        this.registerHub(eventHub);
        this.registerEvent('viewInitiated', this.viewInitiated);
        this.registerEvent('dragStart', this.dragStart);
        this.registerEvent('dragEnter', this.dragEnter);
        this.registerEvent('dragLeave', this.dragLeave);
        this.registerEvent('dragDrop', this.dragDrop);
    },
    beforeMount:function(){
        if(this.verbose)
            console.log('Viewer ',this.identifier,' beforeMount');
        if(this.initiate){
            if(this.verbose)
                console.log('Initiating before mount');
            this.initiateView(this.url);
        }
    },
    mounted:function(){
        if(this.verbose)
            console.log('Viewer ',this.identifier,' mounted');
        if(this.verbose)
            console.log('Cropping images on mount');
        this.cropImages();
    },
    beforeUpdate: function(){
        if(this.verbose)
            console.log('Viewer ',this.identifier,' beforeUpdate');
        if(this.initiate){
            if(this.verbose)
                console.log('Initiating before update');
            this.initiateView(this.url);
        }
    },
    updated: function(){
        if(this.verbose)
            console.log('Viewer ',this.identifier,' updated');
        if(this.initiate){
            if(this.verbose)
                console.log('Initiating at update');
            this.initiateView(this.url);
        }
        if(this.imagesNeedCropping){
            if(this.verbose)
                console.log('Cropping images on update');
            this.cropImages();
        }
    }
});