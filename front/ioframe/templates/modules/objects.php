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