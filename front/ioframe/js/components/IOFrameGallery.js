if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('ioframe-gallery', {
    props:{
        //Array of URLs - images to display
        images: {
            type: Array,
            default: function(){
                return [
                /* array of objects of the form:
                     {
                        url: image url,
                        alt: alt text
                        //TODO add captions, and more
                     }
                 */
                ];
            }
        },
        //Displays only this number of items in the gallery wheel. You can still navigate to all items
        displayNumber:{
            type: Number,
            default: 5
        },
        //Instead of changing "selected", always keeps it as the middle member of the gallery (rounded up) and rotates the
        //gallery to match it
        centerGallery:{
            type: Boolean,
            default: false
        },
        //Whether the preview div should be displayed
        hasPreview:{
            type: Boolean,
            default: false
        },
        //Whether the preview image should instead be the div background
        previewInBackground:{
            type: Boolean,
            default: false
        },
        //Whether the image slider should be displayed
        hasSlider:{
            type: Boolean,
            default: true
        },
        //Whether each slider image should instead be the div background
        sliderInBackground:{
            type: Boolean,
            default: false
        },
        //Whether to go into full-screen display mode when one of the images is clicked
        fullScreenOnClick:{
            type: Boolean,
            default: false
        },
        //Whether to provide a link to the image when in full screen
        linkOnFullScreen:{
            type: Boolean,
            default: true
        },
        //What the text / image / etc should be for full screen links!
        linkOnFullScreenContent:{
            type: String,
            default: 'Direct Image Link'
        },
        //If this is set, all images will link to this URL while the assets are loading.
        loadingImageUrl:{
            type: String,
            default: ''
        },
        //If this is set, all images that are missing will link to this.
        missingImageUrl:{
            type: String,
            default: ''
        },
        //If this is set, when there are more images than can be displayed, those elements will be displayed at the side where
        //the additional images cannot be displayed (for example - images of 3 dots)
        outOfDisplayImagesUrl:{
            type: Object,
            default:  function(){
                return {
                    prev: null,
                    next: null
                };
            }
        },
        //URLs to images of left/right buttons. If not provided, buttons will be styled or not styled according to styledNavigationButtons
        navigationButtons:{
            type: Object,
            default:  function(){
                return {
                    prev: '',
                    next: ''
                };
            }
        },
        //TODO If set to false, navigation buttons will be empty and not styled, left for any external CSS to style them
        styledNavigationButtons:{
            type: Boolean,
            default: true
        },
        //Indicates buttons should be on top of the gallery rather than near the slider
        buttonsOnTop:{
            type: Boolean,
            default: false
        },
        //If set to false, will not allow looping
        infiniteLoop:{
            type: Boolean,
            default: true
        },
        //Potential caption for the gallery
        caption:{
            type: String,
            default: ''
        },
        /** When emitting events, if identifier is '', just the resulting object would be emitted.
         *  If an identifier is set, the emitted object would be of the form {from:this.identifier,content:<whatever would be emitted without identifier>}
         *  This is useful for when multiple galleries might exist on one page.**/
        identifier:{
            type: String,
            default: ''
        },
        //test mode will currently not do much - reserved for future use
        test:{
            type: Boolean,
            default: false
        },
        //Verbose mode will log various runtime debug messages into the console
        verbose:{
            type: Boolean,
            default: false
        },
    },
    data: function(){
        return {
            //Offset (0 to gallery end, then it just loops)
            offset: 0,
            //Currently selected item
            selected: 0,
            //Array of image objects
            gallery: [
                /* Objects of the form:
                *  {
                *       url: image url (taken from the prop)
                *       loaded: false (whether the image was successfully loaded)
                *  }
                * */
            ],
            //Whether we are in full screen mode
            fullScreen: false,
            finishedMounting: false
        }
    },
    created: function(){
    },
    beforeMount: function(){
        this.constructGallery();
    },
    mounted: function(){
        this.finishedMounting = true;
    },
    methods:{
        //Constructs the gallery from given images
        constructGallery: function(){
            /*//Has map to see which images exist
            let imagesHashMap = {};
            let galleryHashMap = {};
            for(let index in this.images){
                imagesHashMap[this.images[index].url] = true;
            }
            //Check what exists in gallery that isn't in images
            for(let index in this.gallery){
                galleryHashMap[this.gallery[index].url] = true;
                //If an image no longer exists in the gallery, it needs to be removed
                if(imagesHashMap[this.gallery[index].url] !== true)
                    delete this.gallery;
            }
            //Check what exists in images that isn't in gallery
            for(let index in this.images){
                //Add anything that wasn't in the gallery
                if(galleryHashMap[this.images[index].url] !== true)
                    this.gallery.push({url:this.images[index].url,loaded:false});
            }

            this.gallery.filter(x => x);*/

            this.gallery = [];
            for(let index in this.images){
                this.gallery.push({url:this.images[index].url,alt:this.images[index].alt,loaded:false});
            }

            //Remember currently selected
            if(this.centerGallery)
                this.selected =  Math.ceil(this.gallery.length / 2) -1;

            this.initImages(250,10);
        },
        /*Loads all images (if loadingImageURL is provided), and fixes all missing images (if missingImageURL is provided)
        * delay and count are used for recursion - count tries, with delay in MS between each try. This is due to render timing*/
        initImages: function(delay, count){
            if(!this.finishedMounting && count > 0){
                setTimeout(this.initImages,delay,delay,(count -1));
                return;
            }

            //Iterate over each member
            for(let index in this.gallery){
                let hasPreview = this.hasPreview;
                //No need to do anything with loaded images
                if(this.gallery[index].loaded)
                    continue;
                let preview;
                let image = this.$el.querySelector('.gallery-member-'+index);
                if(hasPreview)
                    preview = this.$el.querySelector('.gallery-member-preview-'+index);

                //This means we might be too early
                if(image === null){
                    if(count > 0)
                        setTimeout(this.initImages,delay,delay,(count -1));
                    return;
                }
                let context = this;
                let placeholder;
                let previewPlaceholder;
                placeholder = this.$el.querySelector('.gallery-member-placeholder-'+index);
                if(hasPreview)
                    previewPlaceholder = this.$el.querySelector('.gallery-member-preview-placeholder-'+index);
                let hasMissingUrl = this.missingImageUrl;

                /*/!*In some cases, we'll return here from previous search results, and have an invalid image "loaded" from cache*!/
                if(context.missingImageUrl !== '' && image.naturalHeight === 0 && image.complete){
                    if(context.verbose)
                        console.log('Image '+index+' cached despite being invalid!');
                    context.gallery[index].url = context.missingImageUrl;
                    continue;
                }*/

                //Image starts loading
                image.onloadstart = function(){
                    //Make the placeholder visible and move the image away
                    if(context.loadingImageUrl !== ''){
                        image.style.height = '0';
                        image.style.width = '0';
                        image.style.opacity = '0';
                        placeholder.style.display = 'initial';
                        if(hasPreview){
                            preview.style.position = 'fixed';
                            preview.style.left = '100vw';
                            previewPlaceholder.style.display = 'initial';
                        }
                    }
                };

                //Error
                image.onerror = function(){
                    if(context.missingImageUrl !== ''){
                        context.gallery[index].url = context.missingImageUrl;
                        if(context.verbose)
                            console.log('Image '+index+' is not valid!');
                    }
                    image.onerror = function(){};
                };

                //Image finished loading
                image.onloadend = function(){
                    image.onloadstart = function(){};
                    //Image was loaded
                    context.gallery[index].loaded = true;

                    //Remove placeholder, return image to normal position
                    if(context.loadingImageUrl !== ''){
                        image.style = {};
                        placeholder.style.display = 'none';
                        if(hasPreview){
                            preview.style = {};
                            previewPlaceholder.style.display = 'none';
                        }
                    }
                    //If the image isn't valid, and missingImageUrl is set, set the image to that Url
                    let valid = (
                        image.naturalHeight !== 0 &&
                        !(image.attributes.src === context.missingImageUrl && context.gallery[index].url !== context.missingImageUrl)
                    );
                    if(!valid && context.missingImageUrl !== ''){
                        context.gallery[index].url = context.missingImageUrl;
                        if(context.verbose)
                            console.log('Image '+index+' is not valid!');
                    }
                    image.onloadend = function(){};
                };
            }
        },
        //Moves selection to the next/previous image
        moveSelection: function(next = true){
            if(!this.centerGallery){
                if(this.infiniteLoop)
                    this.selected = (next? (this.selected + 1)%this.gallery.length : (this.selected != 0) ? this.selected - 1 : this.gallery.length - 1);
                else
                    this.selected = Math.max(0, Math.min(this.gallery.length - 1, this.selected + (next? 1 : -1)));
                let request = this.identifier !== ''? {from:this.identifier,content:this.selected}: this.selected ;
                eventHub.$emit('selectInGallery',request);
                if(this.verbose)
                    console.log('Emitting selectInGallery',request);
            }
            else{
                this.rotateGallery(next? 1 : -1);
            }
        },
        //Selects an image
        select: function(index){
            if(!this.centerGallery){
                this.selected = Math.max(0, Math.min(this.gallery.length - 1, index));
                let request = this.identifier !== ''? {from:this.identifier,content:this.selected}: this.selected ;
                eventHub.$emit('selectInGallery',request);
                if(this.verbose)
                    console.log('Emitting selectInGallery',request);
            }
            else{
                let requestedOffset = index - this.selected;
                this.rotateGallery(requestedOffset);
            }
        },
        //Rotates gallery
        rotateGallery: function(offset){
            offset = offset - 0;
            let tempGallery = [];
            let galleryArrLength = this.gallery.length;
            for(let i in this.gallery){
                i = i - 0;
                console.log(i, offset);
                let newIndex = i+offset;
                if(newIndex > galleryArrLength - 1)
                    newIndex -= galleryArrLength;
                else if(newIndex < 0)
                    newIndex += galleryArrLength;

                tempGallery.push(JSON.parse(JSON.stringify(this.gallery[newIndex])));
                console.log(JSON.parse(JSON.stringify(this.gallery[newIndex])),newIndex);
            }
            for(let j in tempGallery){
                for(let property in tempGallery[j]){
                    this.gallery[j][property] = tempGallery[j][property];
                }
            }
        },
        //Toggles Full Screen
        toggleFullScreen: function(){
            if(this.fullScreenOnClick)
                this.fullScreen = !this.fullScreen;
        }
    },
    computed:{
        //whether navigation button URLs were provided or not
        navigationButtonsProvided: function(){
            return this.navigationButtons.prev && this.navigationButtons.next;
        },
        //Returns if current indices that are in display range
        indicesInDisplayRange: function(){
            let indices = [];
            let leftOverBack = 0;
            let leftOverFront = 0;
            let arrLength = this.gallery.length;
            let offsetForward = Math.ceil(this.displayNumber / 2);
            let offsetBack = Math.floor(this.displayNumber / 2);

            //In this case, just return everything
            if(this.gallery.length <= this.displayNumber){
                for(let i = 0; i<this.displayNumber; i++)
                    indices.push(i);
                return indices;
            }

            for(let i = this.selected - offsetBack; i<this.selected; i++){
                if(i < 0)
                    leftOverBack ++;
                else
                    indices.push(i);
            }
            for(let i = this.selected; i<this.selected + offsetForward + leftOverBack; i++){
                if(i > arrLength)
                    leftOverFront ++;
                else
                    indices.push(i);
            }
            for(let i = this.selected - offsetBack; i>this.selected - offsetBack - leftOverFront; i--){
                if(i > 0)
                    indices.push(i);
            }

            return indices;
        },
        //Calculates whether there are previous items which are out of display range
        prevItemsOutOfDisplayRange: function(){
            if(!this.hasSlider && this.displayNumber > 1)
                return true;
            else if(this.infiniteLoop)
                return this.displayNumber < this.images.length;
            else
                return this.indicesInDisplayRange.indexOf(0) === -1;
        },
        //Calculates whether there are next items which are out of display range
        nextItemsOutOfDisplayRange: function(){
            if(!this.hasSlider && this.displayNumber > 1)
                return true;
            else if(this.infiniteLoop)
                return this.displayNumber < this.images.length;
            else
                return this.indicesInDisplayRange.indexOf(this.images.length) === -1;
        }
    },
    watch: {
        images:{
            handler: function(val, oldVal){
                if(this.verbose)
                    console.log('Images changed!');
                this.constructGallery();
            },
            deep:true
        }
    },
    template: `
        <div class="ioframe-gallery" :class="{'full-screen':fullScreen,'has-full-screen':fullScreenOnClick}">

            <div v-if="hasPreview || displayNumber<2 || !hasSlider" class="gallery-preview">

                <div v-for="(item, index) in gallery"
                 class="preview-container"
                  :class="[{selected:selected === index}, 'preview-'+index]"
                    :style="{
                        'background-image':previewInBackground?'url('+item.url+')':false
                    }"
                  @click="toggleFullScreen()"
                   >
                    <img v-if="loadingImageUrl && !previewInBackground" :src="loadingImageUrl" class="gallery-member-preview" :class="'gallery-member-preview-placeholder-'+index" style="display:none;">
                    <img v-if="!previewInBackground" :src="item.url" :alt="item.alt? item.alt : ''" class="gallery-member-preview" :class="'gallery-member-preview-'+index">
                </div>

            </div>

            <div class="slider" :class="[{'on-top':displayNumber <= 1 || buttonsOnTop || !hasSlider},{'no-gallery':displayNumber<2 || !hasSlider}]">

                <button v-if="prevItemsOutOfDisplayRange" class="prev" @click.prevent="moveSelection(false)">
                    <div v-if="navigationButtons.prev" v-html="navigationButtons.prev"></div>
                    <div v-else="">&#60</div>
                </button>

                <div class="gallery-container" v-if="displayNumber>1">

                    <div v-if="prevItemsOutOfDisplayRange && outOfDisplayImagesUrl.prev"  class="out-of-view prev" v-html="outOfDisplayImagesUrl.prev"></div>

                    <div
                    v-for="(item, index) in gallery"
                    v-if="indicesInDisplayRange.indexOf(index)!==-1 && hasSlider"
                    class="image-container gallery"
                    :class="[{selected:selected === index}, 'image-'+index]"
                    :style="{
                        'background-image':sliderInBackground?'url('+item.url+')':false
                    }"
                    @click.prevent="select(index)"
                    >
                        <img v-if="loadingImageUrl && !sliderInBackground" :src="loadingImageUrl" class="gallery-member" :class="'gallery-member-placeholder-'+index" style="display:none;">
                        <img  v-if="!sliderInBackground" :src="item.url" :alt="item.alt? item.alt : ''" class="gallery-member" :class="'gallery-member-'+index">
                    </div>

                    <div v-if="nextItemsOutOfDisplayRange && outOfDisplayImagesUrl.next" class="out-of-view next" v-html="outOfDisplayImagesUrl.next"></div>

                </div>

                <button v-if="nextItemsOutOfDisplayRange"  class="next" @click.prevent="moveSelection(true)">
                    <div v-if="navigationButtons.next" v-html="navigationButtons.next"></div>
                    <div v-else="">&#62</div>
                </button>

            </div>

            <figcaption v-if="caption" v-text="caption"></figcaption>

            <div class="full-screen-container-wrapper" v-if="fullScreen">
                <div class="full-screen-container" @click="toggleFullScreen()">
                    <img :src="gallery[selected].url" :alt="gallery[selected].alt? gallery[selected].alt : ''" class="full-screen-preview">
                </div>
                <a target="_blank" v-if="linkOnFullScreen" :href="gallery[selected].url" v-html="linkOnFullScreenContent"></a>
            </div>

        </div>
    `
});