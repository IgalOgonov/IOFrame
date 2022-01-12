if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('menus-editor', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //Current mode - create or update
        mode: {
            type: String,
            default: 'create' //'create' / 'update'
        },

        //Item
        item: {
            type: Object,
            default: function(){
                return {
                    identifier:'',
                    title:null,
                    meta:{}
                }
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
                metaChanged:false,
                menuChanged:false,
                paramMap: false
            },
            //Map of parameters, and vaios properties of each.
            paramMap:{
                menuId:{
                    title:'Identifier',
                    edit: this.mode === 'create',
                    required:true,
                    onUpdate: {
                        validate: function(item){
                            return item.match(/^[a-zA-Z]\w{0,255}$/);
                        }
                    },
                    pattern:'^[a-zA-Z]\\w{0,255}$',
                    validateFailureMessage: 'Menu identifier must be 1-256 characters long, only valid word characters!'
                },
                title:{
                    title:'Menu Title',
                    //What to do on item update
                    onUpdate: {
                        validate: function(item){
                            return item.length > 0 && item.length < 1024;
                        },
                    },
                    pattern:'^.{1,1024}$',
                    validateFailureMessage: 'Article title must be between 1 and 1024 characters long!'
                },
                created:{
                    ignore: this.mode === 'create',
                    title:'Created At',
                    type: 'string',
                    edit: false,
                    onUpdate: {
                        ignore: true
                    },
                    parseOnGet: function(timestamp){
                        timestamp *= 1000;
                        let date = timestampToDate(timestamp).split('-').reverse().join('-');
                        let hours = Math.floor(timestamp%(1000 * 60 * 60 * 24)/(1000 * 60 * 60));
                        let minutes = Math.floor(timestamp%(1000 * 60 * 60)/(1000 * 60));
                        let seconds = Math.floor(timestamp%(1000 * 60)/(1000));
                        if(hours < 10)
                            hours = '0'+hours;
                        if(minutes < 10)
                            minutes = '0'+minutes;
                        if(seconds < 10)
                            seconds = '0'+seconds;
                        return date + ', ' + hours+ ':'+ minutes+ ':'+seconds;
                    }
                },
                updated:{
                    ignore: this.mode === 'create',
                    title:'Updated At',
                    type: 'string',
                    edit: false,
                    onUpdate: {
                        ignore: true
                    },
                    parseOnGet: function(timestamp){
                        timestamp *= 1000;
                        let date = timestampToDate(timestamp).split('-').reverse().join('-');
                        let hours = Math.floor(timestamp%(1000 * 60 * 60 * 24)/(1000 * 60 * 60));
                        let minutes = Math.floor(timestamp%(1000 * 60 * 60)/(1000 * 60));
                        let seconds = Math.floor(timestamp%(1000 * 60)/(1000));
                        if(hours < 10)
                            hours = '0'+hours;
                        if(minutes < 10)
                            minutes = '0'+minutes;
                        if(seconds < 10)
                            seconds = '0'+seconds;
                        return date + ', ' + hours+ ':'+ minutes+ ':'+seconds;
                    }
                },
                identifier:{
                    ignore:true
                },
                meta:{
                    ignore:true
                },
                menu:{
                    ignore:true
                },
            },
            //Meta
            meta:{
                items:{
                    /*
                     * <item key> => {
                     *   value: item value
                     *   changed: whether the item was changed
                     *   add: whether the item was newly added
                     *   remove: whether the item is to be removed
                     * }
                     *
                     * */
                },
                newItem:{
                    key:'',
                    value:''
                }
            },
            //Menu
            menu:{
                original:{},
                current:{},
                moveMenuPending:{},
                preview:{
                    popUp:false,
                    stickToLeft:false,
                    titles:true,
                    address:[],
                    moving:false,
                    addQ: null,
                    moveAddress:[],
                    moveToAddress: [],
                    moveAddresses: [],
                    reservedInputs:['order','@','children'],
                    requiredInputs:['identifier','title'],
                    newChildIndex:-1,
                    subChild:false,
                    newKey: '',
                    newValue: '',
                    newChild:{
                        identifier:'',
                        title:'',
                        '@':{
                            add:true
                        }
                    }
                }
            },
            //Whether the item is up to date
            upToDate: this.mode == 'create',
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
        this.registerEvent('setResponse', this.handleItemSet);
        this.registerEvent('menuSetResponse', this.handleMenuSet);
        this.registerEvent('moveMenuBranchResponse', this.handleMoveMenuBranch);
        this.registerEvent(this.identifier+'-drag-event', this.handleDrugEvent);

        this.setMainItem(this.item);
        this.setMeta(this.item.meta);
        this.setMenu(this.item.menu);
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
            if(this.metaChanged)
                return true;
            return false;
        },
        metaChanged: function(){
            if(this.recompute.metaChanged)
                ;//Do nothing
            for(let i in this.meta.items){
                if(this.meta.items[i].add || this.meta.items[i].change || this.meta.items[i].remove)
                    return true;
            }
            return false;
        },
        menuChanged: function(){
            if(this.recompute.menuChanged)
                ;//Do nothing
            var checkChanges = function(menu){
                if(menu['@'] && (menu['@'].add || menu['@'].changed || menu['@'].remove) )
                    return true;
                else if(menu.children !== undefined){
                    for(let i in menu.children)
                        if(checkChanges(menu.children[i]))
                            return true;
                }
                return false;
            };
            return checkChanges(this.menu.current);
        },
        renderMenuPreview: function(){
            if(this.recompute.menuChanged)
                ;//Do nothing
            if(this.mode === 'create')
                return {};
            let menu = this.menu.current;
            let titles = this.menu.preview.titles;
            let context = this;

            var renderChildren = function(address, children){
                if(children === undefined || typeof children !== 'object' || children.length === undefined)
                    return '';
                let childrenDivs = '';

                for(let i in children){
                    let childKey = children[i].identifier;
                    let childClass= 'menu-child';

                    if(children[i]['@'] === undefined)
                        children[i]['@'] = {};

                    let possibleClasses = ['add','remove','changed'];
                    for(let j in possibleClasses)
                        if(children[i]['@'][possibleClasses[j]])
                            childClass += ' '+possibleClasses[j];


                    //When there are no addresses, we consider all top ones selected
                    let selectedAddress = context.menu.preview.address.length === 0 ?
                        (address.length === context.menu.preview.address.length):
                        (address.length === context.menu.preview.address.length - 1);

                    if(
                        selectedAddress &&
                        context.menu.preview.address.length !== 0 &&
                        [...address,childKey].toString() !== context.menu.preview.address.toString()
                    )
                        selectedAddress = false;

                    if(selectedAddress)
                        childClass += ' viewing';


                    let selectedFather = (address.length === context.menu.preview.address.length);
                    if(selectedFather && address.toString() !== context.menu.preview.address.toString())
                        selectedFather = false;

                    if(selectedFather && (context.menu.preview.newChildIndex == i))
                        childClass += (context.menu.preview.subChild?' child-after sub':' child-before');
                    else if(
                        selectedFather &&
                        (i == children.length -1)&&
                        (context.menu.preview.newChildIndex >= children.length || context.menu.preview.newChildIndex < 0)
                    )
                        childClass += ' child-after';

                    let childrenDiv = `
                    <div
                    class="index-`+i+` `+[...address,childKey].join('-')+` `+childClass+`"`;
                    if(!context.menu.preview.popUp && !context.menuChanged){
                        childrenDiv +=` draggable="true"
                                        ondragstart="eventHub.$emit('`+context.identifier+`-drag-event',event)"
                                        ondragend="eventHub.$emit('`+context.identifier+`-drag-event',event)"
                                        ondragenter="eventHub.$emit('`+context.identifier+`-drag-event',event)"
                                        ondragleave="eventHub.$emit('`+context.identifier+`-drag-event',event)"
                                        ondragover="eventHub.$emit('`+context.identifier+`-drag-event',event)"
                                        ondrop="eventHub.$emit('`+context.identifier+`-drag-event',event)"`;
                    }
                    childrenDiv +=`>`;

                    let title;
                    if(document.selectedLanguage && children[i][document.selectedLanguage+'_title'] && titles)
                        title = children[i][document.selectedLanguage+'_title'];
                    else if(children[i].title && titles)
                        title =  children[i].title
                    else
                        title = childKey;

                    childrenDiv+= '<span class="title">'+title+'</span>';

                    if(children[i].children){
                        childrenDiv += renderChildren([...address, childKey],children[i].children);
                    }

                    childrenDiv += '</div>';

                    childrenDivs += childrenDiv;

                }

                return childrenDivs;
            };

            let html = `<div class="menu-child preview">
                            <div class="title">`+(this.mainItem.title.current?this.mainItem.title.current: 'Menu')+`</div>
                            `+renderChildren([],menu.children)+`
                            `+((context.menu.preview.popUp || context.menuChanged)? `` : `<div>*Drag & Drop Branches To Move Them</div>`)+`
                        </div>`;
            return html;
        },
        focusedMenu: function(){
            if(this.mode === 'create')
                return {};
            let target = this.menu.current;
            for(let i in this.menu.preview.address){
                for(let j in target['children'])
                    if(target['children'][j].identifier === this.menu.preview.address[i]){
                        target = target['children'][j];
                        break;
                    }
            }

            return target;
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
        //Handle drag even for the preview menu
        handleDrugEvent: function(event){
            //This happens on a "fake" dragleave event, when you drop the item on a valid element.
            // Also overrides drop for some reason. Fuck whoever implemented it this way. client on Firefox, offset on Chrome.
            if(event.type ==='dragleave' && (event.clientX < 0 || event.clientY < 0 || event.offsetX < 0 || event.offsetY < 0))
                return;
            if(event.target && event.target.classList && (event.target.classList[2]=== 'menu-child' || event.target.classList[2]=== 'phantom-child')){
                let menu = this.menu.preview;
                switch(event.type){

                    case 'dragstart':
                        menu.moving = true;
                        Vue.set(menu,'moveAddress',event.target.classList[1].split('-'));
                        event.target.classList.add('moving');
                        break;
                    case 'dragend':
                        menu.moving = false;
                        event.target.classList.remove('moving');
                        if(menu.moveToAddress.length > 0){
                            this.$el.querySelector('form > .menu.preview > div  .menu-child.moving-to').classList.remove('moving-to');
                            this.moveMenuBranch(menu.moveAddress.join('-').split('-'),menu.moveToAddress.join('-').split('-'),menu.addQ);
                        }
                        Vue.set(menu,'moveAddress',[]);
                        Vue.set(menu,'moveToAddress',[]);
                        break;

                    /*Important to remember we might not get the enter/leave events in order, so we have to
                    * reach eventual correctness.*/
                    case 'dragenter':
                    case 'dragleave':
                        let isPhantom,targetIndex,targetAddress;
                        isPhantom = (event.target.classList[2] === 'phantom-child');
                        targetAddress = event.target.classList[1];
                        targetIndex = event.target.classList[0].split('-').pop();

                         //Ignore anything exiting or entering the start address, or any of its children
                         let prefix = menu.moveAddress.join('-');
                         if(targetAddress.startsWith(prefix))
                             return;

                         let currentTarget = menu.moveToAddress.join('-');
                         //We only care about entering a different target, or leaving the current target
                         if(
                             ((event.type === 'dragenter') && (currentTarget === targetAddress)) ||
                             ((event.type === 'dragleave') && (currentTarget !== targetAddress))
                         )
                         return;

                         let newTarget, oldTarget;
                         //Unset current target if we're leaving
                         if(event.type === 'dragenter'){
                             newTarget = event.target;
                             oldTarget = this.$el.querySelectorAll('form > .menu.preview > div  .menu-child.moving-to:not(.'+targetAddress+')');
                         }
                         else{
                             newTarget = null;
                             oldTarget = event.target;
                         }

                        if(oldTarget){
                            if(oldTarget.classList){
                                oldTarget.classList.remove('moving-to');
                            }
                            else{
                                for(let i in oldTarget)
                                    if(oldTarget[i].classList)
                                        oldTarget[i].classList.remove('moving-to');
                            }
                            //Only remove those if we haven't entered a new target yet!
                            if(menu.moveToAddress.join('-') === targetAddress){
                                Vue.set(menu,'moveToAddress',[]);
                            }
                        }

                        if(newTarget){
                            newTarget.classList.add('moving-to');
                            Vue.set(menu,'moveToAddress',targetAddress.split('-'));
                        }
                        break;
                    case 'dragover':
                        let target = event.target;
                        let deltaHeight = event.pageY - target.offsetTop;
                        let totalHeight = target.clientHeight;
                        let deltaWidth = event.pageX - target.offsetLeft;
                        let totalWidth = target.clientWidth;
                        if(deltaHeight > totalHeight / 2){
                            if(target.classList.contains('add-before'))
                                target.classList.remove('add-before');
                            if(deltaWidth > totalWidth / 2){
                                if(target.classList.contains('add-after'))
                                    target.classList.remove('add-after');
                                target.classList.add('add-sub-after');
                                menu.addQ = 4;
                            }
                            else{
                                if(target.classList.contains('add-sub-after'))
                                    target.classList.remove('add-sub-after');
                                target.classList.add('add-after');
                                menu.addQ = 3;
                            }
                        }
                        else{
                            if(target.classList.contains('add-after'))
                                target.classList.remove('add-after');
                            if(target.classList.contains('add-sub-after'))
                                target.classList.remove('add-sub-after');
                            target.classList.add('add-before');
                            menu.addQ = (deltaWidth > totalWidth / 2)? 2 : 1;
                        }
                        break;
                }
            }
        },
        //Sets original menu as current
        setMenuAsCurrent: function(){
            let original = JSON.parse(JSON.stringify(this.menu.current));
            var cleanCurrentMenu = function(menu){
                delete menu['@'];
                if(menu.children !== undefined){
                    for(let i in menu.children)
                        if(menu.children[i]['@'].remove)
                            menu.children.splice(i,1);
                    for(let i in menu.children)
                        cleanCurrentMenu(menu.children[i]);
                }
            };
            cleanCurrentMenu(original);
            this.menu.original = original;
            this.menu.original['@'] = JSON.parse(JSON.stringify(this.meta));
            this.menu.original['@title'] = this.mainItem.title.original;
            this.setMenu(this.menu.original);
        },
        //Sets the menu, either from the existing item, or new one from the api
        setMenu: function(item){
            if(this.mode === 'create')
                return;
            this.menu.original = JSON.parse(JSON.stringify(item));
            var prepareOriginalMenu = function(menu){
                for(let i in document.languages){
                    if(menu[document.languages[i]+'_title'] === undefined)
                        menu[document.languages[i]+'_title'] = null;
                }
                if(menu.children !== undefined){
                    for(let i in menu.children)
                        prepareOriginalMenu(menu.children[i]);
                }
            };
            prepareOriginalMenu(this.menu.original);
            let original = JSON.parse(JSON.stringify(this.menu.original));
            this.menu.current = JSON.parse(JSON.stringify(original));
            var prepareCurrentMenu = function(menu){
                menu['@'] = {};
                for(let i in document.languages){
                    if(menu[document.languages[i]+'_title'] === undefined)
                        menu[document.languages[i]+'_title'] = null;
                }
                if(menu.children !== undefined){
                    for(let i in menu.children)
                        prepareCurrentMenu(menu.children[i]);
                }
            };
            if(this.menu.current['@'])
                delete this.menu.current['@'];
            if(this.menu.current['@title'])
                delete this.menu.current['@title'];
            prepareCurrentMenu(this.menu.current);
            this.resetMenuChild();
        },
        //Sets meta
        setMeta: function(meta){
            this.meta.items = {};
            this.meta.original = JSON.parse(JSON.stringify(meta));
            for(let i in this.meta.original){
                this.meta.items[i] = {
                    value: this.meta.original[i],
                    changed:false
                };
            }
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
                    let target = JSON.parse(JSON.stringify(item));
                    let j = 0;
                    while(target !== undefined && typeof target === 'object' && prefixes[j] && target[prefixes[j]]!== undefined){
                        target = target[prefixes[j++]];
                    }
                    let newItem = (target !== undefined && typeof target !== 'object')? target : null;
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
            this.paramMap[i].onUpdate.encodeURI = this.paramMap[i].onUpdate.encodeURI !== undefined ? this.paramMap[i].onUpdate.encodeURI : false;
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

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting item info!');
                return;
            }

            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item info!');
                return;
            }

            //Data to be sent
            var data = new FormData();
            data.append('action', 'setMenus');
            if(this.test)
                data.append('req','test');

            let sendParams = {};

            for(let paramName in this.paramMap){

                let param = this.paramMap[paramName];
                let item = this.mainItem[paramName];

                if(
                    param.ignore ||
                    param.onUpdate.ignore ||
                    (item.current !== undefined && item.current === item.original && !param.considerChanged && !param.required)
                  )
                    continue;
                else if(item.current === undefined){
                    data.append(param.onUpdate.setName, item);
                    sendParams[param.onUpdate.setName] = item;
                    continue;
                }

                if(param.required && item.current === null){
                    let title = param.title? param.title : paramName;
                    alertLog(title+' must be set!','warning',this.$el);
                    return;
                }

                let paramValue = param.onUpdate.parse(item.current);

                if(!param.onUpdate.validate(paramValue)){
                    alertLog(param.onUpdate.validateFailureMessage,'warning',this.$el);
                    return;
                }

                if(param.onUpdate.encodeURI)
                    paramValue = encodeURIComponent(paramValue);

                sendParams[param.onUpdate.setName] = paramValue;
            }
            let meta = {};
            for(let i in this.meta.items){
                let metaItem = JSON.parse(JSON.stringify(this.meta.items[i]));
                if(metaItem.changed || metaItem.add)
                    meta[i] = metaItem.value;
                else if(metaItem.remove)
                    meta[i] = null;
            }
            if(Object.keys(meta).length)
                sendParams['meta'] = JSON.stringify(meta);

            data.append('inputs', JSON.stringify([sendParams]));

            if(this.verbose)
                console.log('Setting item with parameters ',sendParams);

            this.updating = true;

            this.apiRequest(
                data,
                'api/menu',
                'setResponse',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        //Handles menu branch movement
        handleMoveMenuBranch: function(response){
            if(this.verbose)
                console.log('Received handleMoveMenuBranch',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.updating = false;

            if(response.from)
                response = response.content;

            if (response === 'AUTHENTICATION_FAILURE') {
                alertLog('Not authorized to edit menus! Check to see if you are logged in.','error',this.$el);
                return;
            }
            console.log(this.menu.moveMenuPending);
            switch (response) {
                case -2:
                case -3:
                case -1:
                    alertLog('Server error! '+response,'error',this.$el);
                    break;
                case 0:
                    alertLog('Menu Updated!','success',this.$el);
                    eventHub.$emit('searchAgain');
                    this.menu.current = JSON.parse(JSON.stringify(this.menu.moveMenuPending));
                    this.menu.moveMenuPending = {};
                    this.setMenuAsCurrent();
                    this.recompute.menuChanged = !this.recompute.menuChanged;
                    this.$forceUpdate();
                    break;
                case 1:
                case 2:
                case 3:
                case 4:
                    alertLog('Menu structure changed, current operations not possible in new structure. Refresh the page. Error '+response,'error',this.$el);
                    break;
                default:
                    alertLog('Unknown response '+response,'error',this.$el);
                    break;
            }
        },
        //Handles menu update
        handleMenuSet: function(response){

            if(this.verbose)
                console.log('Received handleMenuSet',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.updating = false;

            if(response.from)
                response = response.content;

            if (response === 'AUTHENTICATION_FAILURE') {
                alertLog('Not authorized to edit menus! Check to see if you are logged in.','error',this.$el);
                return;
            }

            switch (response) {
                case -2:
                case -3:
                case -1:
                    alertLog('Server error!','error',this.$el);
                    break;
                case 0:
                    alertLog('Menu Updated!','success',this.$el);
                    eventHub.$emit('searchAgain');
                    this.setMenuAsCurrent();
                    this.recompute.menuChanged = !this.recompute.menuChanged;
                    this.$forceUpdate();
                    break;
                case 1:
                case 2:
                    alertLog('Menu structure changed, current operations not possible in new structure. Refresh the page.','error',this.$el);
                    break;
                default:
                    alertLog('Unknown response '+response,'error',this.$el);
                    break;
            }
        },
        //Handles item  update
        handleItemSet: function(response){

            if(this.verbose)
                console.log('Received handleItemSet',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.updating = false;

            if(response.from)
                response = response.content;

            if (response === 'AUTHENTICATION_FAILURE') {
                alertLog('Not authorized to '+(this.mode === 'create'? 'create menus': 'edit menus')+'! Check to see if you are logged in.','error',this.$el);
                return;
            }

            if(typeof response !== 'object'){
                alertLog('Unknown response '+response,'error',this.$el);
                return;
            }
            else{
                response = this.mode === 'create'? response[this.mainItem.menuId.current] : response[this.mainItem.menuId];
                if(response === undefined){
                    alertLog('Unknown response!','error',this.$el);
                    return;
                }
            }

             switch (response) {
                 case 1:
                 case 2:
                 case 3:
                case -1:
                    alertLog('Server error!','error',this.$el);
                    break;
                 case 0:
                     alertLog('Item '+(this.mode === 'create'? 'created!' : 'Updated'),'success',this.$el);
                     eventHub.$emit('searchAgain');
                     if(this.mode === 'create')
                        this.resetInputs();
                     else
                        this.setInputsAsCurrent();
                     break;
                default:
                    alertLog('Unknown response '+response,'error',this.$el);
                    break;
            }

        },
        
        //Resets inputs
        resetInputs: function(){
            for(let key in this.mainItem){
                if(this.mainItem[key] && this.mainItem[key]['original'] !== undefined){
                    this.mainItem[key]['current'] = this.mainItem[key]['original'];
                }
            }
            this.setMeta(this.meta.original);
            this.recompute.changed = ! this.recompute.changed;
            this.recompute.metaChanged = ! this.recompute.metaChanged;
            this.$forceUpdate();
        },
        //Saves inputs as the actual data (in case of a successful update or whatnot)
        setInputsAsCurrent: function(){
            for(let key in this.mainItem){
                if(this.mainItem[key] && this.mainItem[key]['original'] !== undefined){
                    this.mainItem[key]['original'] = this.mainItem[key]['current'];
                }
            }
            this.recompute.changed = ! this.recompute.changed;
            this.$forceUpdate();
        },

        /*------- META RELATED --------*/
        //Adds new item to meta
        addMetaItem: function(){
            let newItems = JSON.parse(JSON.stringify(this.meta.newItem));
            if(newItems.key === '' || this.meta.items[newItems.key])
                return;
            this.meta.items[newItems.key] = {
                value: newItems.value,
                add: true
            };
            this.recompute.metaChanged = !this.recompute.metaChanged;
        },
        //Removes item from meta
        removeMetaItem: function(key){
            if(!this.meta.items[key])
                return;
            if(this.meta.items[key].add)
                delete this.meta.items[key];
            else if(this.meta.items[key].remove)
                delete this.meta.items[key].remove;
            else
                this.meta.items[key].remove = true;
            this.recompute.metaChanged = !this.recompute.metaChanged;
        },

        /*------- MENU RELATED --------*/
        goUpMenu: function(){
            if( this.menu.preview.address.length !== 0){
                if(this.verbose)
                    console.log('Going up the menu!');
                this.menu.preview.subChild = false;
                 this.menu.preview.address.pop();
            }
        },
        addMenuChild: function(){

            let newChild = JSON.parse(JSON.stringify(this.menu.preview.newChild));
            if(!newChild.identifier){
                alertLog('Identifier for new item is empty!','warning',this.$el);
                return;
            }
            else if(!newChild.identifier.match(/^[a-zA-Z]\w{0,63}$/)){
                alertLog('Identifier must start with a latter, contain regular characters, and be at most 64 characters long!','warning',this.$el);
                return;
            }

            if(this.menu.preview.newChildIndex < 0)
                this.menu.preview.newChildIndex = (this.focusedMenu['children'] !== undefined? this.focusedMenu['children'].length : 0);

            let target = this.menu.preview.subChild ?
                this.focusedMenu['children'][this.menu.preview.newChildIndex]:
                this.focusedMenu;
            if(target['children'] === undefined){
                target['children'] = [];
                target['order'] = [];
            }

            if(target['children'].length === 0){
                target['children'].push(newChild);
                target['order'].push(newChild.identifier);
            }
            else{
                let identifierExists = false;
                for(let i in target['children'])
                    if(target['children'][i].identifier === newChild.identifier){
                        identifierExists = true;
                        break;
                    }
                if(identifierExists){
                    alertLog('Identifier '+newChild.identifier+' already exists!','warning',this.$el);
                    return;
                }
                else{
                    if(this.menu.preview.newChildIndex === target['children'].length){
                        target['children'].push(newChild);
                        target['order'].push(newChild.identifier);
                    }
                    else{
                        target['children'].splice(this.menu.preview.newChildIndex,0,newChild);
                        if(target['order'][this.menu.preview.newChildIndex] !== undefined)
                            target['order'].splice(this.menu.preview.newChildIndex,0,newChild.identifier);
                        else
                            target['order'].push(newChild.identifier);
                    }
                }
            }

            this.resetMenuChild();
            this.recompute.menuChanged = !this.recompute.menuChanged;
            this.$forceUpdate();
        },
        resetMenuChild: function(){
            let newChild = {
                identifier:'',
                title:'',
                '@':{
                    add:true
                }
            };
            for(let i in document.languages){
                newChild[document.languages[i]+'_title'] = '';
            }
            Vue.set(this.menu.preview,'newChild',newChild);
            this.$forceUpdate();
        },
        addMenuChildPair: function(item = null){
            item['@'].changed = true;
            if(item === null)
                item = this.menu.preview.newChild;
            if(
                this.menu.preview.newKey &&
                item[this.menu.preview.newKey] === undefined
                && (this.menu.preview.reservedInputs.indexOf(this.menu.preview.newKey) === -1)
                && (this.menu.preview.requiredInputs.indexOf(this.menu.preview.newKey) === -1)
            )
                item[this.menu.preview.newKey] = this.menu.preview.newValue;
            //TODO describe why this fails
            this.recompute.menuChanged = !this.recompute.menuChanged;
            this.$forceUpdate();
        },
        resetMenuItem: function(index){
            let source = this.menu.original;
            for(let i in this.menu.preview.address){
                for(let j in source['children'])
                    if(source['children'][j].identifier === this.menu.preview.address[i]){
                        source = source['children'][j];
                        break;
                    }
            }

            Vue.set(this.focusedMenu['children'],index,JSON.parse(JSON.stringify(source['children'][index])));

            this.recompute.menuChanged = !this.recompute.menuChanged;
            this.$forceUpdate();
        },
        removeMenuBranch: function(index){

            let identifier = this.focusedMenu['children'][index].identifier;

            if(this.verbose)
                console.log('Removing menu branch ',index,' with identifier ',identifier);

            if(this.focusedMenu['children'][index]['@'].add){
                this.focusedMenu['children'].splice(index,1);
                this.focusedMenu['order'].splice(this.focusedMenu['order'].indexOf(identifier),1);
            }
            else{
                if(!this.focusedMenu['children'][index]['@'].remove){
                    this.focusedMenu['children'][index]['@'].remove = true;
                    this.focusedMenu['order'].splice(this.focusedMenu['order'].indexOf(identifier),1);
                }
                else{
                    this.focusedMenu['children'][index]['@'].remove = false;
                    if(this.focusedMenu['order'].length === 0)
                        this.focusedMenu['order'].push(identifier);
                    else
                        this.focusedMenu['order'].splice(index,0,identifier);
                }
                if(this.menu.preview.newChildIndex === index && this.menu.preview.subChild)
                    this.menu.preview.subChild = false;
            }

            this.recompute.menuChanged = !this.recompute.menuChanged;
            this.$forceUpdate();
        },
        goToChildren: function(index){
            this.menu.preview.address.push(this.focusedMenu['children'][index].identifier);
        },
        setNewMenu: function(){
            let menu = JSON.parse(JSON.stringify(this.menu.current));
            let removals = [];
            let changes = [];
            let additions = [];
            let context = this;
            var initiateMenuSet = function(menu, removals,changes,additions,address){

                let skipChildren = false;
                let rootMenu = false;
                let childrenChanged = false;
                for(let i in menu.children){
                    if(menu.children[i]['@'].add || menu.children[i]['@'].remove){
                        childrenChanged = true;
                        break;
                    }
                }
                if(address === undefined){
                    rootMenu = true;
                    address = [];
                }
                if(menu['@'].remove){
                    let newItem = {
                        address:address,
                        "delete":true
                    };
                    if(!rootMenu)
                        newItem.identifier=menu.identifier;
                    skipChildren = true;
                    removals.push(newItem);
                }
                else if(menu['@'].add || menu['@'].changed || childrenChanged){
                    let newItem = {
                        address:address
                    };
                    if(!rootMenu)
                        newItem.identifier=menu.identifier;

                    if(menu.order)
                        newItem['order']=menu.order.join(',');

                    for(let j in menu){
                        if(context.menu.preview.reservedInputs.indexOf(j) === -1)
                            newItem[j] = menu[j];
                    }

                    if(menu['@'].add)
                        additions.push(newItem);
                    else
                        changes.push(newItem);
                }

                if(!skipChildren)
                    for(let i in menu.children){
                        let child = menu.children[i];
                        initiateMenuSet(child,removals,changes,additions,(rootMenu? [] : [...address,menu.identifier]));
                    }
            };

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting item info!');
                return;
            }

            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item info!');
                return;
            }

            //Data to be sent
            var data = new FormData();
            data.append('action', 'setMenuItems');
            if(this.test)
                data.append('req','test');
            initiateMenuSet(menu, removals,changes,additions);
            let menuToSend = [...removals,...changes,...additions];
            if(this.verbose)
                console.log('Setting new menu!',menuToSend);
            menuToSend = JSON.stringify(menuToSend);
            let identifier = this.mainItem.menuId;
            data.append('inputs',menuToSend);
            data.append('identifier',identifier);

            this.updating = true;

            this.apiRequest(
                data,
                'api/menu',
                'menuSetResponse',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        moveMenuBranch: function(sourceAddress, targetAddress, addQ){

            if(this.verbose)
                console.log('Request to move '+sourceAddress.join('/')+' to Q'+addQ+' '+targetAddress.join('/'));

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting item info!');
                return;
            }

            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item info!');
                return;
            }


            //Get requested target and source
            this.menu.moveMenuPending = JSON.parse(JSON.stringify(this.menu.current));
                let sourceIdentifier = sourceAddress.pop();
            let targetIdentifier = targetAddress.pop();
            let sourceMenu = this.menu.moveMenuPending;
            let targetMenu = this.menu.moveMenuPending;
            let before = addQ <= 2;
            let subAfter = addQ === 4;

            //Check that the source exists
            for(let i in sourceAddress){
                let nextChildExists = false;
                for(let j in sourceMenu['children']){
                    if(sourceMenu['children'][j].identifier === sourceAddress[i]){
                        nextChildExists = true;
                        sourceMenu = sourceMenu['children'][j];
                        break;
                    }
                }
                if(!nextChildExists){
                    alertLog('Source address '+sourceAddress.join('/')+' does not exist!','error',this.$el);
                    return;
                }
            }
            //Calculate source index, and check it exists at all
            let sourceIndex = -1;
            for(let i in sourceMenu['children']){
                if(sourceMenu['children'][i].identifier === sourceIdentifier){
                    sourceIndex = i-0;
                    break;
                }
            }
            if(sourceIndex === -1){
                alertLog('Source address '+[...sourceAddress,sourceIdentifier].join('/')+' does not exist!','error',this.$el);
                return;
            }

            //Check that the target parent level exists
            for(let i in targetAddress){
                let nextChildExists = false;
                for(let j in targetMenu['children']){
                    if(targetMenu['children'][j].identifier === targetAddress[i]){
                        nextChildExists = true;
                        targetMenu = targetMenu['children'][j];
                        break;
                    }
                }
                if(!nextChildExists){
                    alertLog('Source address '+targetAddress.join('/')+' does not exist!','error',this.$el);
                    return;
                }
            }
            //Check that non of the children share an identifier with the source - also, calculate the requested index
            let targetIndex = -1;
            //In this case, we are actually adding the branch as the first child of the target
            if(subAfter){
                for(let j in targetMenu['children']){
                    if(targetMenu['children'][j].identifier === targetIdentifier){
                        targetMenu = targetMenu['children'][j];
                        if(!targetMenu['children'])
                            targetMenu['children'] = [];
                        targetAddress.push(targetIdentifier);
                        targetIndex = 0;
                        break;
                    }
                }
            }

            for(let i in targetMenu['children']){
                if(targetIndex < 0 && targetMenu['children'][i].identifier === targetIdentifier){
                    targetIndex = before? i : i-0+1;
                }
                if(targetMenu['children'][i].identifier === sourceIdentifier){
                    //Make sure we are not moving the item to the same place it was
                    if((sourceAddress.toString() === targetAddress.toString()) && (targetIndex === sourceIndex)){
                        alertLog('Target already at requested position.','info',this.$el);
                        return;
                    }
                    //Make sure we are not moving the item to the same place it was
                    else if(sourceAddress.toString() !== targetAddress.toString()){
                        alertLog('Child '+i+' in the target menu level has the same identifier as the one you tried to move!','error',this.$el);
                        return;
                    }
                }
            }
            if(targetIndex === -1){
                alertLog('Target address '+[...targetAddress,targetIdentifier].join('/')+' does not exist!','error',this.$el);
                return;
            }

            //When removing the item from the same level

            //Set the pending new menu
            if(!targetMenu['children'])
                targetMenu['children'] = [];
            //Variation here is whether target index is after the array's end or not
            if(targetIndex < targetMenu['children'].length)
                targetMenu['children'].splice(targetIndex,0, JSON.parse(JSON.stringify(sourceMenu['children'][sourceIndex])) );
            else
                targetMenu['children'].push( JSON.parse(JSON.stringify(sourceMenu['children'][sourceIndex])) );
            //Only possible variation here is if we are moving an element in the same level, and put it before where it was
            if(targetAddress.toString() !== sourceAddress.toString())
                sourceMenu['children'].splice(sourceIndex,1);
            else
                sourceMenu['children'].splice((targetIndex > sourceIndex? sourceIndex : sourceIndex + 1),1);

            //Prepare the params to send
            let sendParams = {
                blockIdentifier: sourceIdentifier,
                sourceAddress: sourceAddress,
                targetAddress: targetAddress,
                orderIndex: targetIndex
            };

            if(this.verbose)
                console.log('Setting new menu with params ',sendParams);

            //Data to be sent
            let data = new FormData();

            data.append('action', 'moveMenuBranch');

            if(this.test)
                data.append('req','test');

            data.append('identifier',this.mainItem.menuId);

            for(let i in sendParams){
                data.append(i,(typeof sendParams[i] !== 'object' ? sendParams[i] : JSON.stringify(sendParams[i])));
            }

            this.updating = true;

            this.apiRequest(
                data,
                'api/menu',
                'moveMenuBranchResponse',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
    },
    watch: {
    },
    template: `
    <div class="menus-editor" :class="{'preview-pop-up':menu.preview.popUp,'preview-left':menu.preview.stickToLeft,'preview-right':!menu.preview.stickToLeft}">
        <div class="wrapper">

            <div class="info message-info-2" v-if="itemHasInfo">
                <div
                 v-for="(item, key) in mainItem"
                 :class="key.replace('.','-')"
                 v-if="!paramMap[key].edit && paramMap[key].display ">

                    <span class="title" v-text="paramMap[key].title? paramMap[key].title : key"></span>

                    <span class="value" v-if="!paramMap[key].displayHTML" v-text="paramMap[key].parseOnDisplay(item)"></span>
                    <span class="value" v-else="" v-html="paramMap[key].parseOnDisplay(item)"></span>

                </div>

            </div>

            <form>
                <div
                v-for="(item, key) in mainItem"
                v-if="paramMap[key].edit"
                :class="[{changed:item.current !== item.original},key.replace('.','-')]"
                >

                   <span class="title" v-text="paramMap[key].title? paramMap[key].title : key"></span>

                   <input
                    v-if="!paramMap[key].type || ['text','date','number','email'].indexOf(paramMap[key].type) !== -1"
                    class="item-param"
                    :type="paramMap[key].type"
                    :min="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].min !== undefined) ? paramMap[key].min : false"
                    :max="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].max !== undefined)  ? paramMap[key].max : false"
                    :pattern="(['text'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].pattern !== undefined)  ? paramMap[key].pattern : false"
                    :placeholder="paramMap[key].placeholder !== undefined  ? paramMap[key].placeholder : false"
                    v-model:value="item.current"
                    @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                   >
                   <button
                   v-else-if="paramMap[key].type === 'boolean'"
                   class="item-param"
                   v-text="item.current?(paramMap[key].button.positive? paramMap[key].button.positive : 'Yes'):(paramMap[key].button.negative? paramMap[key].button.negative : 'No')"
                    @click.prevent="item.current = paramMap[key].parseOnChange(!item.current);recompute.changed = ! recompute.changed"
                    ></button>

                   <textarea
                   v-else-if="paramMap[key].type === 'textArea'"
                   class="item-param"
                    :placeholder="paramMap[key].placeholder !== undefined  ? paramMap[key].placeholder : false"
                    v-model:value="item.current"
                    @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                    ></textarea>

                   <select
                   v-else-if="paramMap[key].type === 'select'"
                   class="item-param"
                    v-model:value="item.current"
                    @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                    >
                       <option v-for="item in paramMap[key].list" :value="item.value" v-text="item.title? item.title: item.value"></option>
                    </select>

                </div>
                
                <div
                class="meta"
                :class="{changed:metaChanged}"
                >

                   <div class="title" v-text="'Meta'"></div>

                   <div
                   class="meta-pair"
                   v-for="(item, key) in meta.items"
                   :class="{add:item.add,remove:item.remove}"
                   >
                        <span class="title" v-text="key"></span>
                        <input class="value" type="text" palceholder="value" v-model:value="item.value"
                            @change="item.value = $event.target.value;item.changed = true; recompute.metaChanged = !recompute.metaChanged;$forceUpdate()">
                        <button v-if="!item.remove" v-text="'-'" @click.prevent="removeMetaItem(key); recompute.metaChanged = !recompute.metaChanged;$forceUpdate()" class="negative-1"></button>
                        <button v-if="item.remove" v-text="'+'" @click.prevent="removeMetaItem(key); recompute.metaChanged = !recompute.metaChanged;$forceUpdate()" class="positive-1"></button>
                   </div>

                   <div
                   class="meta-pair new"
                   >
                        <span class="title" v-text="'key'"></span>
                        <input class="title" type="text" palceholder="key" v-model:value="meta.newItem.key">
                        <span class="title" v-text="'value'"></span>
                        <input class="value" type="text" palceholder="value" v-model:value="meta.newItem.value">
                        <button v-text="'+'" @click.prevent="addMetaItem();recompute.metaChanged = !recompute.metaChanged;$forceUpdate()" class="positive-1"></button>
                    </div>

                </div>
                
            </form>

            <div class="control-buttons" v-if="changed">
                <button  v-text="this.mode === 'create' ? 'Create' :'Update'" @click.prevent="setItem()" class="positive-1"></button>
                <button v-text="'Reset'" @click.prevent="resetInputs()" class="cancel-1"></button>
            </div>

            <form v-if="mode !== 'create'">

                <div class="menu preview"
                v-if="this.mode === 'update'"
                :class="{'pop-up':menu.preview.popUp,'left':menu.preview.stickToLeft,'moving':menu.preview.moving}"
                 >
                     <div class="color-map">
                        <span class="add"></span>
                        <span class="remove"></span>
                        <span class="changed"></span>
                     </div>
                     <div v-html="renderMenuPreview"></div>
                     <button
                     v-text="'Show '+(menu.preview.titles ? 'Identifiers':'Titles')"
                     @click.prevent="menu.preview.titles = !menu.preview.titles"
                     class="cancel-1"
                     ></button>
                     <button
                     v-if="menu.preview.popUp"
                     class="change-side positive-3"
                     @click.prevent="menu.preview.stickToLeft = !menu.preview.stickToLeft"
                     v-text="menu.preview.stickToLeft? 'Move Right' : 'Move Left'"
                     ></button>
                     <button
                     v-if="menu.preview.popUp && menuChanged"
                     class="save-changes positive-2"
                     @click.prevent="setNewMenu()"
                     ></button>
                     <button
                     class="pop-up positive-3"
                     @click.prevent="menu.preview.popUp = !menu.preview.popUp"
                     v-text="menu.preview.popUp? 'Anchor Preview' : 'Pop Preview Up'"
                     ></button>
                 </div>

                <div
                class="menu edit"
                :class="{changed:menuChanged}"
                >

                   <div class="title" v-text="'Menu'"></div>
                   <button
                   v-if="menuChanged"
                   class="save-changes positive-2"
                   @click.prevent="setNewMenu()"
                   ></button>

                   <div class="address" :class="{none:menu.preview.address.length == 0}">
                        <span
                        v-if="menu.preview.address.length > 0"
                        v-for="address,index in menu.preview.address"
                        >
                            <span class="text" v-text="address"></span>
                            <span class="delimiter" v-if="index < menu.preview.address.length-1" v-text="'/'"></span>
                        </span>
                       <span
                        v-else=""
                        class="address">
                        </span>
                   </div>

                   <button
                   v-if="menu.preview.address.length > 0"
                   class="go-up cancel-1"
                   @click.prevent="goUpMenu()"
                   ></button>

                
                   <div class="menu-child-container" v-for="item,index in focusedMenu.children">

                       <div class="menu-child new" v-if="menu.preview.newChildIndex === index && !menu.preview.subChild" >

                           <button
                           class="add-menu-child positive-1"
                           @click.prevent="addMenuChild()"
                           ></button>

                           <div class="inputs">
                                <div class="input" v-for="value,identifier in menu.preview.newChild" v-if="menu.preview.reservedInputs.indexOf(identifier) === -1">
                                    <span class="title" v-text="identifier"></span>
                                    <input class="value" type="text" palceholder="value" v-model:value="menu.preview.newChild[identifier]">
                                </div>
                           </div>

                           <div class="menu-pair">
                                <span class="title" v-text="'key'"></span>
                                <input class="title" type="text" palceholder="key" v-model:value="menu.preview.newKey">
                                <span class="title" v-text="'value'"></span>
                                <input class="value" type="text" palceholder="value" v-model:value="menu.preview.newValue">
                                <button v-text="'+'" @click.prevent="addMenuChildPair()" class="positive-1"></button>
                            </div>

                           <button
                           class="reset-menu-child cancel-1"
                           @click.prevent="resetMenuChild()"
                           ></button>

                       </div>
                       <button  v-else=""
                       class="add-here positive-1"
                       @click.prevent="menu.preview.newChildIndex = index;menu.preview.subChild=false;"
                       ></button>

                
                       <div class="menu-child"
                       :class="{changed:item['@'].changed && !item['@'].add && !item['@'].remove,add:item['@'].add,remove:item['@'].remove}">

                           <div class="inputs">
                                <div class="info">
                                    <span class="title" v-text="'Identifier'"></span>
                                    <span class="value" v-text="item.identifier"></span>
                                </div>
                                <div class="input" v-for="value,identifier in item" v-if="[...menu.preview.reservedInputs,'identifier'].indexOf(identifier) === -1">
                                    <span class="title" v-text="identifier"></span>
                                    <input
                                    class="value"
                                    type="text"
                                    palceholder="value"
                                    v-model:value="focusedMenu.children[index][identifier]"
                                    @change="item['@'].changed = true; recompute.menuChanged = !recompute.menuChanged; $forceUpdate()">
                                </div>
                           </div>

                           <div class="menu-pair">
                                <span class="title" v-text="'key'"></span>
                                <input class="title" type="text" palceholder="key" v-model:value="menu.preview.newKey">
                                <span class="title" v-text="'value'"></span>
                                <input class="value" type="text" palceholder="value" v-model:value="menu.preview.newValue">
                                <button v-text="'+'" @click.prevent="addMenuChildPair(item)" class="positive-1"></button>
                            </div>

                           <button
                           class="reset-menu-child cancel-1"
                           v-if="item['@'].changed"
                           @click.prevent="resetMenuItem(index)"
                           ></button>

                           <button
                           :class="item['@'].remove? 'restore-menu-branch positive-1':'remove-menu-branch negative-1'"
                           @click.prevent="removeMenuBranch(index)"
                           ></button>

                
                           <div class="menu-child new" v-if="menu.preview.newChildIndex === index && menu.preview.subChild" >
                               <button
                               class="add-menu-child positive-1"
                               @click.prevent="addMenuChild()"
                               ></button>

                               <div class="inputs">
                                    <div class="input" v-for="value,identifier in menu.preview.newChild" v-if="menu.preview.reservedInputs.indexOf(identifier) === -1">
                                        <span class="title" v-text="identifier"></span>
                                        <input class="value" type="text" palceholder="value" v-model:value="menu.preview.newChild[identifier]">
                                    </div>
                               </div>

                               <div class="menu-pair">
                                    <span class="title" v-text="'key'"></span>
                                    <input class="title" type="text" palceholder="key" v-model:value="menu.preview.newKey">
                                    <span class="title" v-text="'value'"></span>
                                    <input class="value" type="text" palceholder="value" v-model:value="menu.preview.newValue">
                                    <button v-text="'+'" @click.prevent="addMenuChildPair()" class="positive-1"></button>
                                </div>

                               <button
                               class="reset-menu-child cancel-1"
                               @click.prevent="resetMenuChild()"
                               ></button>
                           </div>
                           <button  v-else-if="!item['@'].remove"
                           class="add-here sub positive-1"
                           @click.prevent="menu.preview.newChildIndex = index;menu.preview.subChild=true;"
                           ></button>

                           <button
                           class="go-to-children sub positive-3"
                           v-if="item.children && item.children.length && !item['@'].remove"
                           @click.prevent="goToChildren(index)"
                           ></button>

                       </div>

                   </div>

                
                   <div class="menu-child new" v-if="menu.preview.newChildIndex<0 || !focusedMenu.children || menu.preview.newChildIndex>=focusedMenu.children.length">

                       <button
                       class="add-menu-child positive-1"
                       @click.prevent="addMenuChild()"
                       ></button>

                       <div class="inputs">
                            <div class="input" v-for="value,identifier in menu.preview.newChild" v-if="menu.preview.reservedInputs.indexOf(identifier) === -1">
                                <span class="title" v-text="identifier"></span>
                                <input class="value" type="text" palceholder="value" v-model:value="menu.preview.newChild[identifier]">
                            </div>
                       </div>

                       <div class="menu-pair">
                            <span class="title" v-text="'key'"></span>
                            <input class="title" type="text" palceholder="key" v-model:value="menu.preview.newKey">
                            <span class="title" v-text="'value'"></span>
                            <input class="value" type="text" palceholder="value" v-model:value="menu.preview.newValue">
                            <button v-text="'+'" @click.prevent="addMenuChildPair()" class="positive-1"></button>
                        </div>

                       <button
                       class="reset-menu-child cancel-1"
                       @click.prevent="resetMenuChild()"
                       ></button>

                   </div>
                   <button
                   v-else=""
                   class="add-here positive-1"
                   @click.prevent="menu.preview.newChildIndex = -1;menu.preview.subChild=false;"
                   ></button>

                </div>

            </form>

        </div>
    </div>
    `
});