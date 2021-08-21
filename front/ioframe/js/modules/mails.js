 (eventHub === undefined)
    var eventHub = new Vue();


var mails = new Vue({
    el: '#mails',
    name: 'Mails',
    mixins: [IOFrameCommons],
    data: {
        configObject: JSON.parse(JSON.stringify(document.siteConfig)),
        apiName: 'api/v1/mail',
        templates: {},
        action:'getTemplates',
        templateEdit: {
            ID:'',
            Title:{
                original:'',
                changed:false,
                value:'',
            },
            Content:{
                original:'',
                changed:false,
                value:'',
            }
        },
        readyToUpdate:false,
        verbose: false,
        test: false
    },
    created: function () {
        //On get templates
        eventHub.$on('templatesRequest', (payload) => {
            if (payload['@']) delete payload['@'];
            this.templates = payload;
        });

        //On template update
        eventHub.$on('templateSet',(payload)=>{
            if(this.verbose)
                console.log('Template chagned/created return code:',payload);
            if(!this.test){
                const {
                    ID,
                    Content:{value:contentValue},
                    Title:{value:titleValue}
                } = this.templateEdit

                this.templates[ID] = {
                    Content:contentValue,
                    ID,
                    Title:titleValue
                }

                this.$forceUpdate()
                this.handleAPIReturnCods(payload);
            }

        });

        eventHub.$on('removeTemplate', (payload) => {
            if(this.verbose)
                console.log('Remove Template return code:',payload);
            if(!this.test) {
                const deletedId = Number(Object.entries(payload)[0][0]);
                delete this.templates[deletedId];
                this.initTemplateEdit();
                this.$forceUpdate()
                this.handleAPIReturnCods(deletedId);
            }
        });

        //Get templates
        this.getTemplates();
    },
    watch: {
        //On update-if changes made, update button will be shown
        'templateEdit.Title':{
            handler:function ({original,value}) {
                this.templateEdit.Title.changed = (original!== value);
        },deep:true},
        'templateEdit.Content':{
            handler:function ({original,value}) {
                this.templateEdit.Content.changed = (original!== value);
            },deep:true},

    },
    computed:{
        showActionButton:function () {
            return (this.templateEdit.Content.changed || this.templateEdit.Title.changed)
        }
    },
    methods: {
        //Get all templates
        getTemplates() {
            const formData = new FormData();
            formData.append('action', this.action);

            this.apiRequest(formData, this.apiName, 'templatesRequest', {
                verbose: this.verbose,
                parseJSON: true,
                ignoreCSRF:true
            })
        },
        //Update template
        sendAction() {

            const {
                Title:{
                    value:titleValue,
                    changed:titleChanged
                },
                ID:id,
                Content:{
                    value:contentValue,
                    changed:contentChanged
                }
            } = this.templateEdit;


            const formData = new FormData(),
                req = this.test ? 'test' : 'real';

            formData.append('action', this.action);
            if(this.action==='updateTemplate') formData.append('id',id);
            if(titleChanged) formData.append('title',titleValue);
            if(contentChanged) formData.append('content',contentValue);
            formData.append('req', req);

            this.apiRequest(formData, this.apiName, 'templateSet', {
                verbose: this.verbose,
                parseJSON: true
            })
        },
        //Remove a template
        removeTemplate(){
            this.action='deleteTemplates';

            if(window.confirm('Are you sure?')){
                const {
                    ID:id,
                } = this.templateEdit;

                const formData = new FormData(),
                    req = this.test ? 'test' : 'real';

                formData.append('action', this.action);
                formData.append('ids',JSON.stringify([id]));
                formData.append('req', req);

                this.apiRequest(formData, this.apiName, 'removeTemplate', {
                    verbose: this.verbose,
                    parseJSON: true
                })
            }

        },
        //Create template mode
        createTemplate(){
            this.action='createTemplate';
            this.initTemplateEdit()
        },
        initTemplateEdit(){
            this.templateEdit={
                ID:'',
                Title:{
                    original:'',
                    changed:false,
                    value:'',
                },
                Content:{
                    original:'',
                    changed:false,
                    value:'',
                }
            }
        },
        //Template selected
        templateSelect(template){
            this.action='updateTemplate';
            //Set value and original for selected template
            ['Content','Title','ID'].forEach((field)=>{
                if(field==='ID') this.templateEdit[field] = template[field];
                this.templateEdit[field].value= String(template[field]);
                this.templateEdit[field].original= String(template[field]);

            });
        },
        handleAPIReturnCods(code){
            switch (code) {
                case 0:
                    alertLog('success','success',this.$el);
                    break;
                case -1:
                    alertLog('failed to reach DB','error',this.$el);
                    break;
                case 1:
                    alertLog('Template does not exist','warning',this.$el);
                    break
                case 2:
                    alertLog('Id does not exist','error',this.$el);
                    break;
                default:
                    if(code>2) {
                        alertLog('success','success',this.$el);
                    }
                    break;
            }

        }

    },
});