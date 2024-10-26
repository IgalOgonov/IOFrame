if(eventHub === undefined)
    var eventHub = new Vue();

var logs = new Vue({
    el: '#logs',
    name: 'logs',
    mixins:[sourceURL,eventHubManager,IOFrameCommons,searchListFilterSaver],
    data(){
        return {
            identifier:'logs',
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            types: {
                logs:{
                    title: 'System Logs',
                    button: 'positive-3'
                },
                groups:{
                    title: 'Logging Groups',
                    button: 'positive-3'
                },
                rules:{
                    title: 'Logging Rules',
                    button: 'positive-3'
                }
            },
            eventParsingMap:{
                'handleItemDeletion':{
                    identifierFrom:this.identifier,
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'number',
                        value: 0
                    },
                    objectOfResults: false,
                    errorMap:{
                        '-1':{
                            text:'Server error!',
                            warningType: 'error',
                        }
                    }
                },
                'parseSearchResults':{
                    identifierFrom:'search',
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'object'
                    }
                },
                'parseDefaultChannelsResults':{
                    identifierFrom:this.identifier,
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'object'
                    }
                }
            },
            currentType: 'logs',
            searchLists:{
                logs:{
                    action: 'getLogs',
                    //Filters to display for the search list
                    filters:[
                        {
                            type:'Group',
                            group: [
                                {
                                    name:'createdAfter',
                                    title:'Created After',
                                    type:'Datetime',
                                    default:(Date.now()-1000*3600*24*30),
                                    parser: function(value){ return Math.round(value/1000); }
                                },
                                {
                                    name:'createdBefore',
                                    title:'Created Before',
                                    type:'Datetime',
                                    default: 'now',
                                    parser: function(value){ return Math.round(value/1000); }
                                }
                            ]
                        },
                        {
                            type:'Group',
                            group: [
                                {
                                    name:'channelIs',
                                    title:'Channel',
                                    type:'Select',
                                    //Updated on search
                                    list:[
                                        {
                                            'value':'',
                                            'title':'All Channels'
                                        }
                                    ]
                                },
                                {
                                    name:'nodeIs',
                                    title:'Node',
                                    type:'Select',
                                    //Updated on search
                                    list:[
                                        {
                                            'value':'',
                                            'title':'All Nodes'
                                        }
                                    ]
                                },
                            ]
                        },
                        {
                            type:'Group',
                            group: [
                                {
                                    name:'levelAtLeast',
                                    title:'Level At Least',
                                    type:'Select',
                                    list:[
                                        {'value':100},
                                        {'value':150},
                                        {'value':200},
                                        {'value':250},
                                        {'value':300},
                                        {'value':350},
                                        {'value':400},
                                        {'value':450},
                                        {'value':500},
                                        {'value':550},
                                        {'value':600},
                                        {'value':650},
                                        {'value':700}
                                    ]
                                },
                                {
                                    name:'levelAtMost',
                                    title:'Level At Most',
                                    type:'Select',
                                    list:[
                                        {'value':700},
                                        {'value':650},
                                        {'value':600},
                                        {'value':550},
                                        {'value':500},
                                        {'value':450},
                                        {'value':400},
                                        {'value':350},
                                        {'value':300},
                                        {'value':250},
                                        {'value':200},
                                        {'value':150},
                                        {'value':100},
                                    ]
                                },
                            ]
                        },
                    ],
                    //Result columns to display, and how to parse them
                    columns:[
                        {
                            id:'channel',
                            title:'Channel'
                        },
                        {
                            id:'level',
                            title:'Log Level'
                        },
                        {
                            id:'created',
                            title:'Date Created',
                            parser:timeStampToReadableFullDate
                        },
                        {
                            id:'node',
                            title:'Node'
                        },
                        {
                            id:'message',
                            title:'Message',
                            parser: function(value){
                                return (value.length > 10)? (value.substring(0,10) + '...') : value
                            }
                        }
                    ],
                    //SearchList API (and probably the only relevant API) URL
                    url: document.ioframe.rootURI+ 'api/v2/logs',
                    //Current page
                    page:0,
                    //Go to page
                    pageToGoTo: 1,
                    //Limit
                    limit:50,
                    //Total available results
                    total: 0,
                    //Main items
                    items: [],
                    meta:{},
                    extraParams: {
                        'getCreationStatistics':1,
                        'statisticsInterval':86400,
                        'orderBy':['Created'].join(','),
                        'orderType':1
                    },
                    selected:-1,
                    afterGoto:false,
                    initiated: false,
                },
                groups:{
                    action: 'getGroups',
                    //Filters to display for the search list
                    filters:[
                        {
                            type:'Group',
                            group: [
                                {
                                    name:'createdAfter',
                                    title:'Created After',
                                    type:'Datetime',
                                    parser: function(value){ return Math.round(value/1000); }
                                },
                                {
                                    name:'createdBefore',
                                    title:'Created Before',
                                    type:'Datetime',
                                    parser: function(value){ return Math.round(value/1000); }
                                }
                            ]
                        },
                        {
                            type:'Group',
                            group: [
                                {
                                    name:'changedAfter',
                                    title:'Changed After',
                                    type:'Datetime',
                                    parser: function(value){ return Math.round(value/1000); }
                                },
                                {
                                    name:'changedBefore',
                                    title:'Changed Before',
                                    type:'Datetime',
                                    parser: function(value){ return Math.round(value/1000); }
                                }
                            ]
                        },
                        {
                            type:'Group',
                            group: [
                                {
                                    name:'typeIs',
                                    title:'Group Type',
                                    type:'Select',
                                    //Updated on search
                                    list:[
                                        {
                                            'value':'',
                                            'title':'All Types'
                                        }
                                    ]
                                }
                            ]
                        },
                    ],
                    //Result columns to display, and how to parse them
                    columns:[
                        {
                            id:'type',
                            title:'Type'
                        },
                        {
                            id:'id',
                            title:'ID'
                        },
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
                            id:'userCount',
                            title:'User #'
                        },
                        {
                            id:'created',
                            title:'Date Created',
                            parser:timeStampToReadableFullDate
                        },
                        {
                            id:'updated',
                            title:'Last Updated',
                            parser:timeStampToReadableFullDate
                        }
                    ],
                    //SearchList API (and probably the only relevant API) URL
                    url: document.ioframe.rootURI+ 'api/v2/logs/reporting-groups',
                    //Current page
                    page:0,
                    //Go to page
                    pageToGoTo: 1,
                    //Limit
                    limit:50,
                    //Total available results
                    total: 0,
                    //Main items
                    items: [],
                    meta:{},
                    extraParams: {
                        'orderBy':['Created'].join(','),
                        'orderType':1
                    },
                    selected:-1,
                    initiated: false,
                },
                rules:{
                    action: 'getRules',
                    //Filters to display for the search list
                    filters:[
                        {
                            type:'Group',
                            group: [
                                {
                                    name:'createdAfter',
                                    title:'Created After',
                                    type:'Datetime',
                                    parser: function(value){ return Math.round(value/1000); }
                                },
                                {
                                    name:'createdBefore',
                                    title:'Created Before',
                                    type:'Datetime',
                                    parser: function(value){ return Math.round(value/1000); }
                                }
                            ]
                        },
                        {
                            type:'Group',
                            group: [
                                {
                                    name:'changedAfter',
                                    title:'Changed After',
                                    type:'Datetime',
                                    parser: function(value){ return Math.round(value/1000); }
                                },
                                {
                                    name:'changedBefore',
                                    title:'Changed Before',
                                    type:'Datetime',
                                    parser: function(value){ return Math.round(value/1000); }
                                }
                            ]
                        },
                        {
                            type:'Group',
                            group: [
                                {
                                    name:'reportTypeIs',
                                    title:'Report Type',
                                    type:'Select',
                                    //Updated on search
                                    list:[
                                        {
                                            'value':'',
                                            'title':'All Types'
                                        },
                                        {
                                            'value':'email',
                                            'title':'Email'
                                        },
                                        {
                                            'value':'sms',
                                            'title':'SMS'
                                        }
                                    ]
                                },
                                {
                                    name:'channelIs',
                                    title:'Channel',
                                    type:'Select',
                                    //Updated on search
                                    list:[
                                        {
                                            'value':'',
                                            'title':'All Channels'
                                        }
                                    ]
                                }
                            ]
                        },
                        {
                            type:'Group',
                            group: [
                                {
                                    name:'levelAtLeast',
                                    title:'Level At Least',
                                    type:'Select',
                                    list:[
                                        {'value':100},
                                        {'value':150},
                                        {'value':200},
                                        {'value':250},
                                        {'value':300},
                                        {'value':350},
                                        {'value':400},
                                        {'value':450},
                                        {'value':500},
                                        {'value':550},
                                        {'value':600},
                                        {'value':650},
                                        {'value':700}
                                    ]
                                },
                                {
                                    name:'levelAtMost',
                                    title:'Level At Most',
                                    type:'Select',
                                    list:[
                                        {'value':700},
                                        {'value':650},
                                        {'value':600},
                                        {'value':550},
                                        {'value':500},
                                        {'value':450},
                                        {'value':400},
                                        {'value':350},
                                        {'value':300},
                                        {'value':250},
                                        {'value':200},
                                        {'value':150},
                                        {'value':100},
                                    ]
                                },
                            ]
                        },
                    ],
                    //Result columns to display, and how to parse them
                    columns:[
                        {
                            id:'channel',
                            title:'Channel'
                        },
                        {
                            id:'level',
                            title:'Log Level'
                        },
                        {
                            id:'reportType',
                            title:'Report Type'
                        },
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
                            id:'created',
                            title:'Date Created',
                            parser:timeStampToReadableFullDate
                        },
                        {
                            id:'updated',
                            title:'Last Updated',
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
                    limit:50,
                    //Total available results
                    total: 0,
                    //Main items
                    items: [],
                    meta:{},
                    extraParams: {
                        'orderBy':['Created'].join(','),
                        'orderType':1,
                    },
                    selected:-1,
                    initiated: false,
                }
            },
            defaultChannels:[],
            logView:{
                open:false,
                log:{

                }
            },
            logIntervals:{
                3600:'1 Hour',
                86400:'1 Day',
                604800:'7 Days',
                18144000:'30 Days',
            },
            currentInterval:86400,
            //TODO Allow for full report level spectrum breakdown
            logChart:null,
            //Current Mode of operation
            currentMode:'search',
            //Current operation
            currentOperation: '',
            //Current operation input
            operationInput: '',
            commonText:{ //TODO Filter titles
                'title-prefix-logs':'Logs',
                'title-prefix-groups':'Logging Groups',
                'title-prefix-rules':'Logging Rules',
                'title-edit':'Edit',
                'title-editing':' - Editing',
                'title-create':'Create',
                'title-creation':' - Creation',
                'title-type':'Item Type',
                'title-actions':'Actions',
                'title-filters-toggle-show':'Show Filters',
                'title-filters-toggle-hide':'Hide Filters',
                'title-channel':'Channel',
                'title-level':'Log Level',
                'title-created-time':'Creation time',
                'title-node':'Node',
                'title-user-count':'User Count',
                'title-log-message':'Log Message',
                'title-log-context':'Additional Data',
                'title-intervals':'Statistic Intervals',
                'switch-mode-no-item':'Please select an item before viewing/editing it!',
                'operation-updating':'Operation already in progress',
                'operation-cancel-title':'Cancel',
                'operation-delete-title':'Delete',
                'operation-delete':'Delete selected?',
                'operation-confirm':'Confirm',
                'operation-cancel':'Cancel',
                'create-invalid-input-requires-regex':'regex to match',
                'response-unknown':'Unknown response',
                'response-db-connection-error':'Server internal connection error',
                'response-create-missing':'Missing inputs for items',
                'response-create-dependency':'Dependency no longer exists for items',
                'response-create-exists':'Identifiers already exist for items',
                'response-create-success':'Items created',
                'response-delete-success':'Items deleted'
            },
            verbose:false,
            test:false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        this.registerEvent('requestSelection', this.selectElement);
        this.registerEvent('defaultChannelsResults', this.parseDefaultChannelsResults);
        this.registerEvent('searchResults', this.parseSearchResults);
        this.registerEvent('handleItemDeletion', this.handleItemDeletion);
        this.registerEvent('goToPage', this.goToPage);
        this.registerEvent('searchAgain', this.searchAgain);
        this.registerEvent('returnToMainApp', this.returnToMainApp);
        this._registerSearchListFilters('search',{}, {startListening:true,registerDefaultEvents:true});
        //TODO decide if this deserve its own func
        this.apiRequest(
            null,
            'api/v2/logs/default-channels',
            'defaultChannelsResults',
            {
                method:'get',
                verbose: this.verbose,
                parseJSON: true,
                identifier: this.identifier
            }
        );
        //TODO for filters/columns this.updateText()
    },
    computed:{
        //Modes, and array of available operations in each mode
        modes:function() {
            switch (this.currentType){
                case 'logs':
                    return {
                        search:{
                            operations:{},
                            title:'View items'
                        }
                    };
                case 'groups':{
                    return {
                        search:{
                            operations:{
                                'delete':{
                                    title:this.commonText['operation-delete-title'],
                                    button:'negative-1'
                                },
                                'cancel':{
                                    title:this.commonText['operation-cancel-title'],
                                    button:'cancel-1'
                                }
                            },
                            title:'View items'
                        },
                        edit:{
                            operations:{},
                            title:'Edit item'
                        },
                        create:{
                            operations:{},
                            title:'Create item'
                        }
                    };
                }
                case 'rules':{
                    return {
                        search:{
                            operations:{
                                'delete':{
                                    title:this.commonText['operation-delete-title'],
                                    button:'negative-1'
                                },
                                'cancel':{
                                    title:this.commonText['operation-cancel-title'],
                                    button:'cancel-1'
                                }
                            },
                            title:'View items'
                        },
                        edit:{
                            operations:{},
                            title:'Edit item'
                        },
                        create:{
                            operations:{},
                            title:'Create item'
                        }
                    };
                }
            }
        },
        //Main title
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return this.commonText['title-prefix-'+this.currentType];
                case 'edit':
                    return this.commonText['title-prefix-'+this.currentType]+this.commonText['title-editing'];
                case 'create':
                    return this.commonText['title-prefix-'+this.currentType]+this.commonText['title-creation'];
                default:
            }
        },
        currentlySelected: function(){
            return this.currentSearchList.items[this.currentSearchList.selected] ?? null;
        },
        //Text for current operation
        currentOperationText:function(){
            switch(this.currentOperation){
                case 'delete':
                    return 'Delete selected?';
                default:
                    return '';
            }
        },
        //Whether the current mode has operations
        currentModeHasOperations:function(){
            return Object.keys(this.modes[this.currentMode].operations).length>0;
        },
        currentSearchList: function (){
            return this.searchLists[this.currentType];
        },
        commonTextEditor: function(){ //TODO more based on currentType and each editor
            return JSON.parse(JSON.stringify(this.commonText));
        }
    },
    watch:{
        'currentType':function(newVal){
            if(this.verbose)
                console.log('Changing type to '+newVal);
            this.closeLogView();
            this.searchLists[newVal].initiated = false;
            this.searchLists[newVal].selected = -1;
            this.searchLists[newVal].page = 0;
            this.searchLists[newVal].pageToGoTo = 0;
            Vue.set(this.searchLists[newVal],'items',[]);
            Vue.set(this.searchListFilters,'search',{});
        },
        'currentInterval':function (newVal){
            this.currentSearchList.extraParams.statisticsInterval = newVal-0;
            this.searchAgain();
        }
    },
    methods:{
        //Log View
        updateLogView: function (){
            if(!this.currentlySelected)
                return;
            this.logView.open = true;
            Vue.set(this.logView,'log',JSON.parse(JSON.stringify(this.currentlySelected)));

            if(!this.logView.log.context || (typeof this.logView.log.context !== 'object'))
                this.logView.log.context = null;
            else
                this.logView.log.context = JSON.stringify(this.logView.log.context);

            this.logView.log.created = timeStampToReadableFullDate(this.logView.log.created,4);
        },
        closeLogView: function(){
            this.logView.open = false;
            Vue.set(this.logView,'log', {});
        },
        initiateChart: function(intervals){
            if(this.logChart)
                this.logChart.destroy();
            let chart = this.$el.querySelector('.logs-chart canvas');
            let intervalValue = this.currentSearchList.extraParams.statisticsInterval;
            let intervalName = null;
            let data = [];
            let createdAfter = dateToTimestamp(this.$el.querySelector('.filters input[name="createdAfter"]').value.split('-').reverse().join('-'),true)/1000;
            let createdBefore = dateToTimestamp(this.$el.querySelector('.filters input[name="createdBefore"]').value.split('-').reverse().join('-'),true)/1000;

            switch (intervalValue){
                case 3600:
                    intervalName = 'hour';
                    break;
                case 86400:
                    intervalName = 'day';
                    break;
                case 604800:
                    intervalName = 'week';
                    break;
                case 18144000:
                    intervalName = 'month';
                    break;
            }

            for(let i in intervals){
                let realTime = i*intervalValue;
                let removeExtraFromEnd = createdAfter - realTime;
                if(removeExtraFromEnd >0)
                    realTime = createdAfter;
                else
                    removeExtraFromEnd = 0;
                let date = realTime;
                switch (intervalValue){
                    case 3600:
                        date = timeStampToReadableFullDate(realTime,1);
                        break;
                    case 86400:
                        date = timeStampToReadableFullDate(realTime,0);
                        break;
                    case 604800:
                        date = timeStampToReadableFullDate(realTime,0) +
                            ' - ' +
                            timeStampToReadableFullDate((realTime +3600*24*6 > createdBefore? createdBefore : realTime +3600*24*6 - removeExtraFromEnd),0);
                        break;
                    case 18144000:
                        date = timeStampToReadableFullDate(realTime,0) +
                            ' - ' +
                            timeStampToReadableFullDate((realTime +3600*24*29 > createdBefore? createdBefore : realTime +3600*24*29 - removeExtraFromEnd),0);
                        break;
                }
                data.push({
                    count:intervals[i],
                    date: date
                });
            }

            this.logChart = new Chart(
                chart,
                {
                    type: 'line',
                    options:{
                        scales:{
                            y:{
                                min: 0
                            }
                        },
                        elements:{
                            line:{
                                borderColor: 'rgb(179,149,0)',
                                borderWidth: '5'
                            }
                        }
                    },
                    data: {
                        labels: data.map(row => row.date),
                        datasets: [
                            {
                                label: 'Logs per '+intervalName,
                                data: data.map(row => row.count)
                            }
                        ]
                    }
                }
            );
        },
        //Returns to main app
        returnToMainApp: function(){
            if(this.verbose)
                console.log('Returning to main app!');
            this.switchModeTo('search');
        },
        //Searches again (meant to be invoked after relevant changes)
        searchAgain: function(){
            if(this.verbose)
                console.log('Searching again!');
            this.currentSearchList.items = [];
            this.currentSearchList.total = 0;
            this.currentSearchList.selected = -1;
            this.currentSearchList.initiated = false;
        },
        //Parses search results returned from a search list
        parseSearchResults: function(response){

            let eventResult = this.eventResponseParser(response,'parseSearchResults',2);

            if(!eventResult)
                return;

            //Either way, the items should be considered initiated
            this.currentSearchList.items = [];
            this.currentSearchList.initiated = true;

            //In this case the response was an error code, or the page no longer exists
            if((response.content['meta'] === undefined) || !eventResult.valid)
                return;

            this.currentSearchList.meta = JSON.parse(JSON.stringify(response.content['meta']));
            delete response.content['meta'];
            this.currentSearchList.total = (this.currentSearchList.meta['#'] - 0) ;

            if(this.currentSearchList.meta.intervals){
                this.initiateChart(this.currentSearchList.meta.intervals);
            }
            //This is done to prevent re-loading statistics after just changing pages
            else if (this.currentSearchList.afterGoto){
                this.currentSearchList.afterGoto = false;
                this.currentSearchList.extraParams.getCreationStatistics = 1;
            }

            if(this.currentSearchList.meta.types){
                this.currentSearchList.extraParams.disableExtraToGet = this.currentSearchList.extraParams.disableExtraToGet??[];
                this.currentSearchList.extraParams.disableExtraToGet.push('Group_Type');
                for (let i in this.currentSearchList.meta.types){
                    const type = this.currentSearchList.meta.types[i];
                    this.currentSearchList.filters[2].group[0].list.push({type:type,value:type});
                }
            }

            if(this.currentSearchList.meta.nodes){
                this.currentSearchList.extraParams.disableExtraToGet = this.currentSearchList.extraParams.disableExtraToGet??[];
                this.currentSearchList.extraParams.disableExtraToGet.push('Node');
                for (let i in this.currentSearchList.meta.nodes){
                    const node = this.currentSearchList.meta.nodes[i];
                    this.currentSearchList.filters[1].group[1].list.push({title:node,value:node});
                }
            }

            if(response.content[this.currentType] && Object.keys(response.content[this.currentType]).length)
                for(let k in response.content[this.currentType]){
                    response.content[this.currentType][k].identifier = k;
                    this.currentSearchList.items.push(response.content[this.currentType][k]);
                }
            else if(this.logChart)
                    this.logChart.destroy();
        },
        parseDefaultChannelsResults: function (response){

            let eventResult = this.eventResponseParser(response,'parseDefaultChannelsResults',2);

            if(!eventResult)
                return;

            let channels = response.content.channels;

            if(channels.length){
                Vue.set(this,'defaultChannels',channels);
                for (let i in channels){
                    const channel = channels[i];
                    this.searchLists.rules.filters[2].group[1].list.push({title:channel,value:channel});
                    this.searchLists.logs.filters[1].group[0].list.push({title:channel,value:channel}) ;
                }
            }
        },
        //Handles operation response
        handleItemDeletion: function(response){

            let eventResult = this.eventResponseParser(response,'handleItemDeletion',2);

            if(!eventResult)
                return;

            this.updating = false;

            if(eventResult.valid){
                this.searchAgain();
            }
        },
        //Goes to relevant page
        goToPage: function(page){
            if(!this.initiating && (page.from === 'search')){
                let newPage;
                page = page.content;

                if(page === 'goto')
                    page = this.currentSearchList.pageToGoTo-1;

                if(page < 0)
                    newPage = Math.max(this.currentSearchList.page - 1, 0);
                else
                    newPage = Math.min(page,Math.ceil(this.currentSearchList.total/this.currentSearchList.limit));

                if(this.currentSearchList.page === newPage)
                    return;

                //This is done to prevent re-loading statistics after just changing pages
                this.currentSearchList.afterGoto = true;
                this.currentSearchList.extraParams.getCreationStatistics = 0;

                this.currentSearchList.page = newPage;
                this.currentSearchList.selected = -1;
                this.currentSearchList.initiated = false;
            }
        },
        //Element selection from search list
        selectElement: function(request){

            if(!request.from || request.from !== 'search')
                return;

            request = request.content;

            if(this.verbose)
                console.log('Selecting item ',request);

            if(this.currentMode === 'search'){
                if(this.currentSearchList.selected === request){
                    if(this.currentType !== 'logs')
                        this.switchModeTo('edit');
                    else{
                        this.currentSearchList.selected = -1;
                        this.closeLogView();
                    }
                }
                else{
                    this.currentSearchList.selected = request;
                    if(this.currentType === 'logs')
                        this.updateLogView();
                }
            }
        },
        shouldDisplayMode: function(index){
            if(index==='edit' && (this.currentSearchList.selected === -1) )
                return false;
            if(index==='create' && (this.currentSearchList.selected !== -1))
                return false;

            return true;
        },
        //Switches to requested mode
        switchModeTo: function(newMode){
            if(this.currentMode === newMode)
                return;
            if(newMode === 'edit' && this.currentSearchList.selected===-1){
                alertLog('Please select an item before you view/edit it!','warning',this.$el);
                return;
            }

            if(newMode==='edit'){
                switch (this.currentMode) {
                    case 'search':
                        this.currentMode = 'edit';
                        return;
                    default:
                        return
                }
            }else {
                this.currentSearchList.selected=-1;
            }
            this.currentMode = newMode;
            this.currentOperation = '';
        },
        //Switches to requested type
        switchTypeTo: function(newType){
            if(this.currentType === newType)
                return;
            this.switchModeTo('search');
            this.currentType = newType;
        },
        //Executes the operation //TODO Add what's needed
        confirmOperation: function(payload){
            if(this.test)
                console.log('Current Operation ', this.currentOperation ,'Current input ',this.operationInput);


            if(this.updating){
                alertLog(this.commonText['operation-updating'], 'warning', this.$el)
            }
            else
                this.updating = true;

            let queryParams = {};
            let currentOperation = this.currentOperation;
            let eventName = '';
            let prefix = '';
            let method = 'post'

            if(this.currentMode === 'search' && this.currentlySelected){
                switch (currentOperation){
                    case 'delete':
                        eventName = 'handleItemDeletion';
                        prefix += this.currentType === 'groups' ?
                            '/reporting-groups/'+this.currentlySelected.identifier :
                            '/reporting-rules/'+this.currentlySelected.identifier;
                        method = 'delete';
                        break;
                    default:
                        break;
                }

                if(!eventName){
                    if(this.verbose)
                        console.log('Returning, no operation set!');
                    return;
                }

                if(this.test)
                    queryParams.req='test';

                 this.apiRequest(
                     null,
                      'api/v2/logs' + prefix,
                     eventName,
                      {
                          queryParams : queryParams,
                          method:method,
                          verbose: this.verbose,
                          parseJSON: true,
                          identifier: this.identifier
                      }
                 );

            }
        },
        //Initiates an operation
        operation: function(operation){

            if(this.test)
                console.log('Operation',operation);
            switch (operation){
                case 'delete':
                    this.currentOperation = 'delete';
                    break;
                case 'cancel':
                    this.cancelOperation();
                    break;
                default:
                    this.currentOperation = operation;
            }
        },
        shouldDisplayOperation: function(index){
            //Search mode
            if(this.currentMode === 'search'){
                if(this.currentSearchList.selected === -1 && index !== 'create')
                    return false;
                else if(this.currentSearchList.selected !== -1 && index === 'create')
                    return false;
            }
            //Edit mode
            else if(this.currentMode === 'edit'){
            }

            return true;
        },
        //Cancels the operation
        cancelOperation: function(){

            if(this.test)
                console.log('Canceling operation');
            if(this.currentMode === 'search'){
                this.currentSearchList.selected = -1;
            }
            else if(this.currentMode === 'edit'){
                this.currentMode = 'search';
                this.currentSearchList.selected = -1;
            }
            this.operationInput= '';
            this.currentOperation = '';

        }
    },
    template:`
        <div id="logs" class="main-app">
            <div class="loading-cover" v-if="!currentSearchList.initiated && currentMode==='search'">
            </div>

            <h1 v-if="title!==''" v-text="title"></h1>
            
            <div class="type-mode">
                <span v-text="commonText['title-type']"></span>
                <select class="types" v-model:value="currentType" :disabled="currentMode !== 'search'">
                    <option v-for="(item, index) in types"
                        class="type"
                        :value="index"
                        v-text="item.title"
                        @click="switchTypeTo(index)"
                        :class="[{selected:(currentType===index)},(item.button? item.button : ' positive-3')]"
                    ></option>
                </select>
            </div>

            <div class="modes">
                <button
                    v-for="(item,index) in modes"
                    v-if="shouldDisplayMode(index)"
                    v-text="item.title"
                    @click="switchModeTo(index)"
                    :class="[{selected:(currentMode===index)},(item.button? item.button : ' positive-3')]"
                >
                </button>
            </div>

            <div class="operations-container" v-if="currentModeHasOperations">
                <div class="operations-title" v-text="'Actions'"></div>
                <div class="operations" v-if="currentOperation===''">
                    <button
                        v-if="shouldDisplayOperation(index)"
                        v-for="(item,index) in modes[currentMode].operations"
                        @click="operation(index)"
                        :class="[index,{selected:(currentOperation===index)},(item.button? item.button : 'positive-3')]"
                    >
                        <div v-text="item.title"></div>
                    </button>
                </div>
            </div>

            <div class="operations" v-if="currentModeHasOperations && currentOperation !==''">
                <label :for="currentOperation" v-text="currentOperationText" v-if="currentOperationText"></label>

                <button
                    :class="(currentOperation === 'delete' ? 'negative-1' : 'positive-1')"
                    @click="confirmOperation">
                    <div v-text="'Confirm'"></div>
                </button>
                <button class="cancel-1" @click="cancelOperation">
                    <div v-text="'Cancel'"></div>
                </button>
            </div>
            
            <div class="logs-chart"  v-if="currentType === 'logs'">
                <div class="intervals">
                    <h5 v-text="commonText['title-intervals']"></h5>
                    <select class="interval-selection" v-model:value="currentInterval">
                        <option v-for="(item, index) in logIntervals"
                            class="interval"
                            :value="index"
                            v-text="item"
                        ></option>
                    </select>
                </div>
                <canvas class="logs-chart"></canvas>
            </div>

            <div class="log-view" v-if="logView.open">
                <div class="channel">
                    <h4 v-text="commonText['title-channel']"></h4>
                    <span class="value" v-text="logView.log.channel"></span>
                </div>
                <div class="level">
                    <h4 v-text="commonText['title-level']"></h4>
                    <span class="value" v-text="logView.log.level"></span>
                </div>
                <div class="created">
                    <h4 v-text="commonText['title-created-time']"></h4>
                    <span class="value" v-text="logView.log.created"></span>
                </div>
                <div class="node">
                    <h4 v-text="commonText['title-node']"></h4>
                    <span class="value" v-text="logView.log.node"></span>
                </div>
                <div class="message">
                    <h4 v-text="commonText['title-log-message']"></h4>
                    <span class="value" v-text="logView.log.message"></span>
                </div>
                <div class="context" v-if="logView.log.context">
                    <h4 v-text="commonText['title-log-context']"></h4>
                    <span class="value" v-text="logView.log.context"></span>
                </div>
            </div>

            <div is="search-list"
                 v-if="currentMode==='search'"
                 :api-url="currentSearchList.url"
                 :api-action="currentSearchList.action"
                 :current-filters="searchListFilters['search']"
                 :extra-params="currentSearchList.extraParams"
                 :page="currentSearchList.page"
                 :limit="currentSearchList.limit"
                 :total="currentSearchList.total"
                 :items="currentSearchList.items"
                 :initiate="!currentSearchList.initiated"
                 :columns="currentSearchList.columns"
                 :filters="currentSearchList.filters"
                 :selected="currentSearchList.selected"
                 :test="test"
                 :verbose="verbose"
                 identifier="search"
            ></div>

            
            <div :is="currentType === 'rules' ? 'rule-editor' : 'group-editor'"
                 v-if="currentMode!=='search'"
                 :mode="currentMode"
                 identifier="editor"
                 :limit-channels="currentType === 'rules' ? defaultChannels : undefined"
                 :id="(currentMode === 'create') ? undefined : (currentlySelected??{}).identifier"
                 :item="currentMode === 'create' ? undefined : currentlySelected"
                 :common-text="commonTextEditor"
                 :test="test"
                 :verbose="verbose"
                ></div>
                
        </div>
    `
});