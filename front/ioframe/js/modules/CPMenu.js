if(eventHub === undefined)
    var eventHub = new Vue();

var CPMenu = new Vue({
    el: '#menu',
    name:'Side Menu',
    mixins:[multipleLanguages,sourceURL],
    data: {
        configObject: JSON.parse(JSON.stringify(document.siteConfig)),
        selected: '',
        logo:{
            imgURL: '',
            url:''
        },
        otherCP:{
            imgURL: '',
            url:'',
            title:''
        },
        update:{
            available:document['ioframe'].currentVersion !== document['ioframe'].availableVersion,
            title:''
        },
        menu:[
            /*
                {
                'id':   id of the page,
                'title': name of the page,
                url:    url of the page
                position: default -2 (append). Other possible values are -1 (prepend), or the index in the original array
                          to which you want to PREPEND the item (so 0 first inserts the item, then the original first menu item).
                          Multiple items with the same position will be inserted into their right place in the order they appear here.
                          Values lower than -3 will DECREASE priority of appending (so something with -3 will be inserted later than -2)
                }
             */
        ],
        open:false,
        test:false,
        verbose:false
    },
    methods: {
        CPMenuImageAddress: function(item, ioframe = false){
            return ioframe ? (this.sourceURL() + 'img/' + item.icon): (document.ioframe.rootURI + document.ioframe.imagePathLocal+item.icon);
        },
    },
    created:function(){

        /*Global config check - can be hardcoded or dynamically aquired from the DB*/
        if(this.configObject === undefined)
            this.configObject = {};
        if(this.configObject.cp === undefined)
            this.configObject.cp = {};

        /* Defaults*/
        //Link to other CP menu
        if(this.configObject.cp.otherCP === undefined)
            this.configObject.cp.otherCP = {};
        if(this.configObject.cp.otherCP.imgURL === undefined)
            this.configObject.cp.otherCP.imgURL = '';
        if(this.configObject.cp.otherCP.url === undefined)
            this.configObject.cp.otherCP.url = '';
        if(this.configObject.cp.otherCP.title === undefined)
            this.configObject.cp.otherCP.title = '';
        this.otherCP = JSON.parse(JSON.stringify(this.configObject.cp.otherCP));

        //Currently selected page -
        if(this.configObject.page === undefined)
            this.configObject.page = {};
        if(this.configObject.page.id === undefined)
            this.configObject.page.id = '';

        this.selected = this.configObject.page.id;

        //Logo
        if(this.configObject.cp.logo === undefined)
            this.configObject.cp.logo = {};
        if(this.configObject.cp.logo.imgURL === undefined)
            this.configObject.cp.logo.icon = 'icons/logo-small.svg';
        if(this.configObject.cp.logo.url === undefined)
            this.configObject.cp.logo.url = document.ioframe.rootURI;
        this.logo = this.configObject.cp.logo;

        //Update Title
        if(this.configObject.cp.update === undefined)
            this.configObject.cp.update = {};
        if(this.configObject.cp.update.title === undefined)
            this.configObject.cp.update.title = 'Update Available';
        this.update.title = this.configObject.cp.update.title;

        //Whether or not to only show non-admin tabs, or all system tabs
        if(this.configObject.cp.hideAdmin === undefined)
            this.configObject.cp.hideAdmin = !document.siteConfig.isAdmin;

        //Menu
        let defaultMenu = [
            {
                id: 'users',
                title: 'Users',
                url: 'users',
                icon: 'icons/CPMenu/users.svg',
                position: 1
            },
            {
                id: 'settings',
                title: 'Settings',
                url: 'settings',
                icon: 'icons/CPMenu/settings.svg',
                position: 2,
            },
            {
                id: 'plugins',
                title: 'Plugins',
                url: 'plugins',
                icon: 'icons/CPMenu/plugins.svg',
                position: 3,
            },
            {
                id: 'contacts',
                title: 'Contacts',
                url: 'contacts',
                icon: 'icons/CPMenu/contacts.svg',
                position: 4,
            },
            {
                id: 'tags',
                title: 'Tags',
                url: 'tags',
                icon: 'icons/CPMenu/tags.svg',
                position: 5,
            },
            {
                id: 'language-objects',
                title: 'Language Obj',
                url: 'language-objects',
                icon: 'icons/CPMenu/language-objects.svg',
                position: 6,
            },
            {
                id: 'articles',
                title: 'Articles',
                url: 'articles',
                icon: 'icons/CPMenu/articles.svg',
                position: 7
            },
            {
                id: 'menus',
                title: 'Menus',
                url: 'menus',
                icon: 'icons/CPMenu/menus.svg',
                position: 8,
            },
            {
                id: 'media',
                title: 'Media',
                url: 'media',
                icon: 'icons/CPMenu/media.svg',
                position: 9,
            },
            {
                id: 'galleries',
                title: 'Galleries',
                url: 'galleries',
                icon: 'icons/CPMenu/galleries.svg',
                position: 10,
            },
            {
                id: 'mails',
                title: 'Mails',
                url: 'mails',
                icon: 'icons/CPMenu/mails.svg',
                position: 11,
            },
            {
                id: 'tokens',
                title: 'Tokens',
                url: 'tokens',
                icon: 'icons/CPMenu/tokens.svg',
                position: 12,
            },
            {
                id: 'logs',
                title: 'Logs',
                url: 'logs',
                icon: 'icons/CPMenu/logs.svg',
                position: 12,
            },
            {
                id: 'auth',
                title: 'Permissions',
                url: 'auth',
                icon: 'icons/CPMenu/permissions.svg',
                position: 13,
            },
            {
                id: 'securityEvents',
                title: 'Events',
                url: 'securityEvents',
                icon: 'icons/CPMenu/security.svg',
                position: 14,
            },
            {
                id: 'securityIP',
                title: 'IP',
                url: 'securityIP',
                icon: 'icons/CPMenu/security.svg',
                position: 15,
            },
            {
                id: 'login',
                title: 'Login Page',
                url: 'login',
                icon: 'icons/CPMenu/login.svg',
                position: -3,
                admin:false
            },
            {
                id: 'account',
                title: 'Account Page',
                url: 'account',
                icon: 'icons/CPMenu/account.svg',
                position: -3,
                admin:false
            }
        ];

        /* Array of id's to ignore in the default menu
         */
        if(this.configObject.cp.ignoreDefaults === undefined)
            this.configObject.cp.ignoreDefaults = [];

        defaultMenu = defaultMenu.filter(item => this.configObject.cp.ignoreDefaults.indexOf(item.id) == -1);

        /* Extra menu items - needs to be an array similar to Menu, will be appended to the end.
         */
        if(this.configObject.cp.extraMenu === undefined)
            this.configObject.cp.extraMenu = [];

        let newMenu = [...defaultMenu, ...this.configObject.cp.extraMenu];

        for (let i in newMenu){
            if(this.configObject.cp.hideAdmin && (newMenu[i].admin !== false)){
                delete newMenu[i];
            }
        }
        newMenu = newMenu.filter(x => x.id);
        newMenu.sort(function(a, b) {
            if(b.position == a.position)
                return 0;
            else if(a.position < -1 && b.position < -1)
                return (a.position > b.position ? -1 : 1);
            else if(a.position < -1)
                return 1;
            else if(b.position < -1)
                return -1;
            else
                return a.position < b.position ? -1 : 1;
        });

        this.menu = newMenu;
    },
    template:`
    <nav id="menu" :class="{open:open}">
        <div class="button-wrapper">
            <button @click.prevent="open = !open" :class="{open:open}">  </button>
        </div>
        <a :href="logo.url" class="logo">
            <picture>
                <source :srcset="CPMenuImageAddress(logo,true)">
                <source :srcset="CPMenuImageAddress(logo)">
                <img :src="CPMenuImageAddress(logo,true)">
            </picture>
        </a>
        <div is="language-selector"
        v-if="languages.length"
        :show-flag="true"
        :outside-flag="false"
        :reload-on-change="true"
        :test="test"
        :verbose="verbose"
        ></div>
        <a v-if="update.available && !configObject.cp.hideAdmin" class="update" href="update">
            <span  v-text="update.title"></span>
        </a>
    
        <a  v-for="item in menu" :href="item.disabled ? '#':item.url" :class="{selected:item.id === selected,disabled:item.disabled}">
            <picture v-if="item.icon">
                <source :srcset="CPMenuImageAddress(item,true)">
                <source :srcset="CPMenuImageAddress(item)">
                <img :src="CPMenuImageAddress(item,true)">
            </picture>
            <span  v-text="item.title"></span>
        </a>
    
        <a v-if="otherCP.url" :href="otherCP.url" class="other-cp">
            <picture v-if="item.icon">
                <source :srcset="CPMenuImageAddress(otherCP,true)">
                <source :srcset="CPMenuImageAddress(otherCP)">
                <img :src="CPMenuImageAddress(otherCP,true)">
            </picture>
            <span v-else="" v-text="otherCP.title"></span>
        </a>
    </nav>
    `
});