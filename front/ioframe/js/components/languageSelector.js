Vue.component('language-selector', {
    mixins:[eventHubManager,IOFrameCommons,multipleLanguages],
    props:{
        showFlag:{
            type: Boolean,
            default: true
        },
        outsideFlag:{
            type: Boolean,
            default: false
        },
        reloadOnChange:{
            type: Boolean,
            default: false
        },
        text: {
            type: Object,
            default: function(){
                return {
                };
            }
        },
        //Identifier
        identifier: {
            type: String,
            default: 'language-selector'
        },
        test:{
            type: Boolean,
            default: false
        },
        verbose:{
            type: Boolean,
            default: false
        },
    },
    data: function(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            available: {

            },
            //Event Parsing Map
            eventParsingMap: {
                //Fully filled as an example
                'setPreferredLanguage':{
                    identifierFrom:this.identifier,
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'number',
                        condition: function(value){return value === 0;}
                    },
                    objectOfResults: false, /*Doesn't really change anything, as the response isn't an object*/
                    errorMap:{
                        '-1':{
                            text:'Server error!',
                            warningType: 'error',
                        }
                    }
                }
            },
            requesting:false
        }
    },
    created: function(){
        this.registerHub(eventHub);
        this.registerEvent('setPreferredLanguage',this.handleSetPreferredLanguage);

        const allLanguages = [this.defaultLanguage,...this.languages ];
        for(let i in allLanguages){
            const lang = allLanguages[i];
            let toSet = {
                'title':lang
            };
            if(this.languagesMap[lang])
                toSet = mergeDeep(toSet,this.languagesMap[lang]);
            if(toSet.flag)
                toSet.flagUrl = document.ioframe.rootURI+'front/ioframe/img/icons/flags/4x3/'+toSet.flag+'.svg';
            Vue.set(this.available,lang,toSet);
        }
    },
    computed:{
        currentFlagUrl:function(){
            return (this.available[this.currentLanguage]??[]).flagUrl??false;
        },
        showOutsideFlag:function (){
            return this.showFlag && this.outsideFlag && this.currentFlagUrl;
        },
        showInsideFlag:function (){
            return this.showFlag && !this.outsideFlag && this.currentFlagUrl;
        }
    },
    watch:{
        currentLanguage:function(newVal,oldVal){
            if(this.requesting){
                this.currentLanguage = oldVal;
            }
            else if(newVal !== oldVal)
                this.changeLanguage(newVal);
        }
    },
    methods:{
        changeLanguage(newLang){

            let data = new FormData();

            data.append('action','setPreferredLanguage');
            data.append('lang',newLang);
            if(this.test)
                data.append('req','test');

            this.apiRequest(data, 'api/language-objects', 'setPreferredLanguage', {
                verbose: this.verbose,
                identifier:this.identifier,
                parseJSON: true
            })
        },
        handleSetPreferredLanguage(response){

            let eventResult = this.eventResponseParser(response,'setPreferredLanguage');

            if(!eventResult.valid??false)
                return;

            this.requesting = false;

            if(this.reloadOnChange)
                location.reload();
            else
                eventHub.$emit('newLanguage',this.currentLanguage);
        }
    },
    template:
        `<div class="language-selector" :class="{requesting:requesting,'no-text':showInsideFlag}">
            <span  v-if="showOutsideFlag" class="flag" :style="{backgroundImage:'url('+currentFlagUrl+')'}"></span>
            <select class="language-selection" v-model:value="currentLanguage" :style="{backgroundImage:(showInsideFlag?'url('+currentFlagUrl+')':false)}">
                <option v-for="(info,flag) in available" :value="flag" v-text="info.title">
                </option>
            </select>
        </div>`
});