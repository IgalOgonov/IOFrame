<div id="objectManager">
    <form id="selectionForm" novalidate id="selection">
        <label> Create object: <input type="radio" value="c" v-model="currentAction"> </label> <br/>
        <label> Read Objects: <input  type="radio" value="r" v-model="currentAction"> </label> <br/>
        <label> Read object Group: <input  type="radio" value="rg" v-model="currentAction"> </label> <br/>
        <label> Update object: <input type="radio" value="u" v-model="currentAction">  </label><br/>
        <label> Delete object:  <input type="radio" value="d" v-model="currentAction"> </label><br/>
        <label> Get object Assignments:  <input type="radio" value="ga" v-model="currentAction"> </label><br/>
        <label> Assignment (removal) of an object : <input type="radio" value="a" v-model="currentAction"> </label><br/>
    </form>

    <form id="cForm" novalidate v-if="currentAction == 'c'">
        <h1>Create object</h1> <br/>
        <label> <div>Object:</div> <textarea v-model="c.obj"></textarea> </label> <br/>
        <label> Minimum rank to view: <input type="number" v-model="c.minV" min="-1" max="10000"></label> <br/>
        <label> Minimum rank to modify: <input type="number"  v-model="c.minM" min="0" max="10000">  </label><br/>
        <label> Group (optional):  <input type="text" v-model="c.group" > </label><br/>
        <label> Test Query?  <input type="checkbox" v-model="c.test"> </label><br/>
        <input type="button" @click="cSubmit" value="Create Object"><br/>
    </form>

    <form id="rForm" novalidate v-if="currentAction == 'r'">
        <h1>Read objects</h1>
        <table>
            <thead>
            <tr>
                <th>Object ID</th>
                <th>Last Updated</th>
                <th>Group Name</th>
                <th>Remove Object</th>
            </tr>
            </thead>
            <tbody>
            <tr
                is="objectRequestDiv"
                v-for="(value, key) in r.requestObjects"
                v-bind:key="key"
                v-bind:object-id="key"
                v-bind:time-updated="value[0]"
                v-bind:group-name="value[1]"
                ></tr>
            </tbody>
        </table>
        <div>
            <label>Object ID: <input type="number" v-model="r.newObjID"></label>
            <label>Time Updated: <input type="number" v-model="r.newObjTimeUpdated" min="0" max="10000000000"></label>
            <label>Group: <input type="text" v-model="r.newObjGroup"></label>
            <span><input type="button" value="Add To Request" @click="addObj"></span>
        </div>
        <label> Test Query?  <input type="checkbox" v-model="r.test"> </label><br/>
        <input type="button" @click="rSubmit" value="Read Objects"><br/>
    </form>

    <form id="rgForm" novalidate v-if="currentAction == 'rg'">
        <h1>Read object groups</h1>
        <label> Group:  <input type="text" v-model="rg.group" > </label><br/>
        <label> Test Query?  <input type="checkbox" v-model="rg.test"> </label><br/>
        <input type="button" @click="rgSubmit" value="Read Object Group"><br/>
    </form>

    <form id="uForm" novalidate v-if="currentAction == 'u'">
        <h1>Update object </h1>
        <label> Object ID: <input type="number"  v-model="u.objID" min="0" required></label> <br/>
        <label> <div>Object (optional):</div> <textarea v-model="u.obj"></textarea> </label> <br/>
        <label> Group (optional):  <input type="text" v-model="u.group" > </label><br/>
        <label> Minimum rank to view (optional): <input type="number" v-model="u.minV" min="-1" max="10000"></label> <br/>
        <label> Minimum rank to modify (optional): <input type="number" v-model="u.minM" min="0" max="10000">  </label><br/>
        <label> Change Main Owner (optional): <input type="number" v-model="u.mainO" min="0"></label> <br/>
        <label> Add secondary Owner (optional): <input type="number" v-model="u.addSecO" min="0"></label> <br/>
        <label> Remove secondary Owner (optional): <input type="number" v-model="u.remSecO" min="0"></label> <br/>
        <label> Test Query?  <input type="checkbox" v-model="u.test"> </label><br/>
        <input type="button" @click="uSubmit" value="Update Object"><br/>
    </form>

    <form id="dForm" novalidate v-if="currentAction == 'd'">
        <h1>Delete object</h1>
        <label> Object ID: <input type="number"  v-model="d.objID"></label> <br/>
        <label> Test Query?  <input type="checkbox" v-model="d.test"> </label><br/>
        <input type="button" @click="dSubmit" value="Delete Object"><br/>
    </form>

    <form id="gaForm" novalidate v-if="currentAction == 'ga'">
        <h1>Get object-page assignments</h1>
        <label> Page Name: <input type="text"  v-model="ga.pageName"></label> <br/>
        <label> Time Updated: <input type="number" v-model="ga.date" min="0" max="10000000000"></label> <br/>
        <label> Test Query?  <input type="checkbox" v-model="ga.test"> </label><br/>
        <input type="button" @click="gaSubmit" value="Get Assignment"><br/>
    </form>

    <form id="aForm" novalidate v-if="currentAction == 'a'">
        <h1>Assign object to page / Remove Assignment</h1>
        <label> Object ID: <input type="number" v-model="a.objID" min="0"></label> <br/>
        <label> Page Name: <input type="text" v-model="a.pageName"></label> <br/>
        <label> Remove? (default = assign) <input type="checkbox" v-model="a.rem"> </label><br/>
        <label> Test Query?  <input type="checkbox" v-model="a.test"> </label><br/>
        <input type="button" @click="aSubmit" value="Assign"><br/>
    </form>

    <p>inputs = {{inputs}}</p>

    <p>request = {{request}}</p>

    <br>
</div>




<script>

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
        el: '#objectManager',
        data: {
            inputs: {

            },
            request: {
                'action':null,
                'params':{},
                'req':'test'
            },
            currentAction: 'c',
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
                this.request.params['pages'] = {
                };
                this.request.params['pages'][this.ga.pageName] = this.ga.date;
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
                //sendData = sendData.substr(0,sendData.length-1); No need to waste resources
                console.log(sendData);

                let url=document.pathToRoot+"api\/objects";
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
            }
        },
        created: function(){
            //Listen to events
            //eventHub.$on('qinstall', this.qinstall);
        }
    });
</script>