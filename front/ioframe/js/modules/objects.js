

    //This component is responsible for each object row in the read request
    Vue.component('objectRequestDiv', {
        template: '\
        <tr>\
            <td>{{objectId}}</td>\
            <td>{{timeUpdated}}</td>\
            <td>{{groupName}}</td>\
            <td><input type="button" value="Remove" @click="$parent.remObj(objectId)"></td>\
        </tr>\
        ',
        props: {
            objectId: [String,Number],
            groupName: String,
            timeUpdated: [String,Number]
        }
    });

    //The plugin list component, which is responsible for everything
    var objectManager = new Vue({
        el: '#object-manager',
        data: {
            inputs: {

            },
            request: {
                'action':null,
                'params':{},
                'req':'test'
            },
            currentAction: 'c',
            types: {
                'c':{
                    title:'Create'
                },
                'r':{
                    title:'Read'
                },
                'u':{
                    title:'Update'
                },
                'd':{
                    title:'Delete'
                },
                'rg':{
                    title:'Read Group'
                },
                'ga':{
                    title:'Get Page Assignments'
                },
                'a':{
                    title:'Modify Page Assignments'
                }
            },
            c: {
                obj:'',
                minV:-1,
                minM:0,
                group:'',
                test:true
            },
            r: {
                requestObjects:{},
                groupMap:{},
                newObjID:1,
                newObjTimeUpdated:0,
                newObjGroup:'',
                test:true
            },
            rg: {
                group:'',
                test:true
            },
            u: {
                objID:1,
                obj:null,
                group:null,
                minV:null,
                minM:null,
                mainO:null,
                addSecO:null,
                remSecO:null,
                test:true
            },
            d: {
                objID:1,
                test:true
            },
            ga: {
                pageName:'',
                date:0,
                test:true
            },
            a: {
                objID:1,
                pageName:'',
                rem:false,
                test:true
            }
        },
        methods: {
            cSubmit: function () {
                this.inputs = JSON.stringify(this.c);
                if(this.c.obj != ''){
                    //Encode request params
                    this.request.params['obj'] = this.c.obj;
                    if(this.c.group != null)
                        this.request.params['group'] = this.c.group;
                    if(this.c.minV != null)
                        this.request.params['minViewRank'] = this.c.minV;
                    if(this.c.minM != null)
                        this.request.params['minModifyRank'] = this.c.minM;
                    //Send request
                    this.sendRequest();
                }
            },
            rSubmit: function () {
                this.inputs = JSON.stringify(this.r.requestGroups);
                //Encode request params
                for(let group in this.r.groupMap){
                    this.request.params[group] = {};
                    for(let object in this.r.groupMap[group]){
                        this.request.params[group][object] = this.r.groupMap[group][object];
                    }
                }
                //Send request
                this.sendRequest();
            },
            addObj: function () {
                //Get the inputs right
                this.r.newObjTimeUpdated = Number(this.r.newObjTimeUpdated);
                this.r.newObjID = Number(this.r.newObjID);
                let newGroup = this.r.newObjGroup;
                if(newGroup === '')
                    newGroup = '@';
                this.inputs = JSON.stringify(
                    {
                        "newID":this.r.newObjID,
                        "newTime": this.r.newObjTimeUpdated,
                        "newGroup": newGroup
                    }
                );

                //If the object existed, remove it from its old group
                if(this.r.requestObjects[this.r.newObjID] !== undefined){
                    //Handle the case where the group is '@'
                    let oldGroup = (this.r.requestObjects[this.r.newObjID][1] === '')? '@': this.r.requestObjects[this.r.newObjID][1];
                    //remove
                    this.removeObjectFromGroup(this.r.newObjID,oldGroup);
                }

                //Create a new group if needed
                if(this.r.groupMap[newGroup] === undefined){
                    Vue.set(this.r.groupMap,newGroup,{'@':9999999999});
                }

                Vue.set(this.r.groupMap[newGroup],this.r.newObjID,this.r.newObjTimeUpdated);
                if(this.r.newObjTimeUpdated < this.r.groupMap[newGroup]['@'])
                    Vue.set(this.r.groupMap[newGroup],'@',this.r.newObjTimeUpdated);
                Vue.set(this.r.requestObjects,this.r.newObjID,[this.r.newObjTimeUpdated,newGroup]);
            },
            remObj: function (id) {
                //First update the group
                let objGroup;

                //Objects without a group are in the "@" group
                if(this.r.requestObjects[id][1] !== '@')
                    objGroup = this.r.requestObjects[id][1];
                else
                    objGroup = '@';

                //Delete the object and calculate a new lastUpdated - lowest one of all remaining objects
                this.removeObjectFromGroup(id,objGroup);

                //finally delete the object
                Vue.delete(this.r.requestObjects,id);
            },
            removeObjectFromGroup: function(id,group){
                Vue.delete(this.r.groupMap[group],id);
                if(Object.keys(this.r.groupMap[group]).length === 1){
                    Vue.delete(this.r.groupMap,group);
                }
                else{
                    let newTime = 9999999999;
                    for(let key in this.r.groupMap[group]){
                        if(this.r.groupMap[group][key]<newTime && key!='@')
                            newTime = this.r.groupMap[group][key];
                    }
                    Vue.set(this.r.groupMap[group],'@',newTime);
                }
            },
            rgSubmit: function () {
                this.inputs = JSON.stringify(this.rg);
                //Encode request params
                this.request.params['groupName'] = this.rg.group;
                //Send request
                this.sendRequest();
            },
            uSubmit: function () {
                this.inputs =  JSON.stringify(this.u);
                //Encode request params
                this.request.params['id'] = this.u.objID;
                if(this.u.obj != null)
                    this.request.params['content'] = this.u.obj;
                if(this.u.group != null)
                    this.request.params['group'] = this.u.group;
                if(this.u.minV != null)
                    this.request.params['newVRank'] = this.u.minV;
                if(this.u.minM != null)
                    this.request.params['newMRank'] = this.u.minM;
                if(this.u.mainO != null)
                    this.request.params['mainOwner'] = this.u.mainO;
                if(this.u.addSecO != null){
                    let addOwners = {};
                    addOwners[this.u.addSecO] = this.u.addSecO;
                    this.request.params['addOwners'] = JSON.stringify(addOwners);
                }
                if(this.u.remSecO != null){
                    let removeOwners = {};
                    removeOwners[this.u.remSecO] = this.u.remSecO;
                    this.request.params['remOwners'] = JSON.stringify(removeOwners);
                }
                //Send request
                this.sendRequest();
            },
            dSubmit: function () {
                this.inputs = JSON.stringify(this.d);
                //Encode request params
                this.request.params['id'] = this.d.objID;
                //Send request
                this.sendRequest();
            },
            gaSubmit: function () {
                this.inputs = JSON.stringify(this.ga);
                //Encode request params
                this.request.params['maps'] = {
                };
                this.request.params['maps'][this.ga.pageName] = this.ga.date;
                //Send request
                this.sendRequest();
            },
            aSubmit: function () {
                this.inputs = JSON.stringify(this.a);
                //Encode request params
                this.request.params['id'] = this.a.objID;
                this.request.params['page'] = this.a.pageName;
                //Send request
                this.sendRequest();
            },
            //Main function to handle the install form
            sendRequest: function(){
                //Get the type and request type for this request
                this.request.action = this.currentAction;

                if(this.request.action == 'a' && this.a.rem)
                    this.request.action = 'ra';

                this.request.req = this[this.currentAction]['test']?
                    'test' : 'real';

                //Encode data to send
                let sendData = '';
                let content = null;
                for (let key in this.request) {
                    if(key != 'params')
                        content = this.request[key];
                    else
                        content = JSON.stringify(this.request[key]);
                    sendData += encodeURIComponent(key) + '=' +
                        encodeURIComponent(content) + '&';
                };
                updateCSRFToken().then(
                    function(token){
                        sendData += 'CSRF_token='+token;
                        //sendData = sendData.substr(0,sendData.length-1); No need to waste resources
                        console.log(sendData);

                        let url=document.pathToRoot+"api\/v1\/objects";
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', url+'?'+sendData);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
                        xhr.send(null);
                        xhr.onreadystatechange = function () {
                            var DONE = 4; // readyState 4 means the request is done.
                            var OK = 200; // status 200 is a successful return.
                            if (xhr.readyState === DONE) {
                                if (xhr.status === OK){
                                    let response = xhr.responseText;
                                    if(IsJsonString(response))
                                        console.log(JSON.parse(response));
                                    else
                                        console.log(response);
                                    alertLog(response,'info');
                                    objectManager.request.action =null;
                                    objectManager.request.params ={};
                                    objectManager.request.req ='test';
                                }
                            } else {
                                if(xhr.status < 200 || xhr.status > 299 ){

                                }
                            }
                        };
                    },
                    function(reject){
                        alertLog('CSRF token expired. Please refresh the page to submit the form.','danger');
                    }
                )
            }
        },
        created: function(){
            //Listen to events
            //eventHub.$on('qinstall', this.qinstall);
        }
    });