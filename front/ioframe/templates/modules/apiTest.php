<span id="apiTest">
    <button @click.prevent="separateVariables = !separateVariables"> Toggle Separate Variables Mode</button>
    <form novalidate>


        <input type="text" id="target" name="target" placeholder="target API" v-model="target" required>
        <span v-if="separateVariables" id="variables">
            <div v-for="(content, name) in variables">
                <button @click.prevent="removeVariable(name)"> X </button>
                <input type="text" v-model:value="name">
                <textarea class="content" name="content" placeholder="content" v-model="content.value"></textarea>
            </div>
            <div>
                <input type="text" v-model:value="newVariableName">
                <button @click.prevent="addVariable(newVariableName)"> Add Variable </button>
            </div>
        </span>
        <textarea v-else="" class="content" name="content" placeholder="content" v-model="content"></textarea>
        <span class="form-group">
            <input type="text" id="imgName" name="imgName" placeholder="Upload POST name" v-model="imgName">
            <img id="preview1" src="" style="height: 100px;width: 100px;cursor: pointer;">
            <input id="uploaded1" name="uploaded1" type="file" style="display:none;">
        </span>
        <span class="form-group">
            <input id="uploaded2" name="uploaded2" type="file" style="display: inline">
        </span>
        <select id="req_log" name="req" v-model="req" value="test" required>
            <option value="real" selected>Real</option>
            <option value="test">Test</option>
        </select>
        <select id="api_target" name="target" v-model="apiTarget" required>
            <option value="">Default API</option>
            <option value="v1" selected>API Version 1</option>
            <option value="v2" selected>API Version 2</option>
        </select>
        <select id="api_method" name="method" v-model="apiMethod" required>
            <option value="post" selected>POST</option>
            <option value="get">GET</option>
            <option value="put">PUT</option>
            <option value="delete">DELETE</option>
            <option value="patch">PATCH</option>
            <option value="head">HEAD</option>
        </select>


        <button @click.prevent="send">Send</button>

    </form>
    <div>inputs = {{ inputs }}</div>

    <div>{{ resp }}</div>
</span>