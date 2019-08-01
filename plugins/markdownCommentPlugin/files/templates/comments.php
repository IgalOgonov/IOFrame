<div id="objectManager">
    <form id="selectionForm" novalidate id="selection">
        <label> Read Comments: <input  type="radio" value="r" v-model="currentAction"> </label> <br/>
        <label> Read Comment Group: <input  type="radio" value="rg" v-model="currentAction"> </label> <br/>
        <label> Write Comment: <input type="radio" value="c" v-model="currentAction"> </label> <br/>
        <label> Update Comments: <input type="radio" value="u" v-model="currentAction">  </label><br/>
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

    <form id="cForm" novalidate v-if="currentAction == 'c'">
        <h1>Create object</h1> <br/>
        <label> <div>Object:</div> <textarea v-model="c.obj"></textarea> </label> <br/>
        <label> Minimum rank to view: <input type="number" v-model="c.minV" min="-1" max="10000"></label> <br/>
        <label> Minimum rank to modify: <input type="number"  v-model="c.minM" min="0" max="10000">  </label><br/>
        <label> Group (optional):  <input type="text" v-model="c.group" > </label><br/>
        <label> Mark Comment as Trusted  <input type="checkbox" v-model="c.trusted"> </label><br/>
        <label> Test Query?  <input type="checkbox" v-model="c.test"> </label><br/>
        <input type="button" @click="cSubmit" value="Create Object"><br/>
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
        <label> Mark Comment as Trusted</label>:
            Yes <input type="radio" v-model="u.trusted" value="true">
            No <input type="radio" v-model="u.trusted" value="false">
            Dont Modify <input type="radio" v-model="u.trusted" value="null">
        <br/>
        <label> Test Query?  <input type="checkbox" v-model="u.test"> </label><br/>
        <input type="button" @click="uSubmit" value="Update Object"><br/>
    </form>

    <p>inputs = {{inputs}}</p>

    <p>request = {{request}}</p>

    <br>
</div>