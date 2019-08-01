<span id="apiTest">
    <form novalidate>


        <input type="text" id="target" name="target" placeholder="target API" v-model="target" required>
        <textarea  id="content" name="content" placeholder="API request content" v-model="content" required></textarea>
        <span class="form-group">
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

        <button @click.prevent="send">Send</button>

    </form>
    <div>inputs = {{ inputs }}</div>

    <div>{{ resp }}</div>
</span>