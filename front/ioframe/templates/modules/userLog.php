<span id="userLog">
    <form novalidate>


        <input :style="{backgroundColor: mStyle}" type="email" id="m_log" name="m" placeholder="email" v-model="m" required>
        <input :style="{backgroundColor: pStyle}" type="password" id="p_log" name="p" placeholder="password" v-model="p" required>
        <input type="checkbox" name="rMe" v-model="rMe" checked>Remember Me!
        <select id="req_log" name="req" v-model="req" value="real" required hidden>
            <option value="real" selected>Real</option>
            <option value="test">Test</option>
        </select>
        <button @click.prevent="log">Login</button>

    </form>
    <div hidden>test = {{ test1 }} </div>

    <div hidden>inputs = {{ inputs }}</div>

    <div hidden>{{ resp }}</div>
</span>