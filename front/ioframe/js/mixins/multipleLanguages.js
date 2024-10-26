const multipleLanguages = {
    mixins:[eventHubManager],
    data: function (){
        return {
            languages:document.ioframe.languages,
            defaultLanguage: document.ioframe.defaultLanguage,
            preferredLanguage: document.ioframe.preferredLanguage,
            currentLanguage: document.ioframe.selectedLanguage,
            languagesMap: document.ioframe.languagesMap
        };
    },
    created:function(){
        this.registerHub(eventHub);
        if((this.languageChanged !== undefined) && (typeof this.languageChanged === 'function') )
            this.registerEvent('newLanguage',this.languageChanged);
    },
    methods:{
        //Preloads main menu
        languageChanged: function(newLanguage){
            if(newLanguage !== this.currentLanguage)
                this.currentLanguage = newLanguage;
        },
    },
};
