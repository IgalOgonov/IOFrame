<span id="userReg">

    <form novalidate>

        <input :style="{backgroundColor: uStyle}" type="text" id="u_reg" name="u" placeholder="username" v-model="u" required>
        <a href="#"  id="u_reg-tooltip">?</a><br>
        <input :style="{backgroundColor: pStyle}" type="password" id="p_reg" name="p" placeholder="password" v-model="p" required>
        <a href="#"  id="p_reg-tooltip">?</a><br>
        <input :style="{backgroundColor: p2Style}" type="password" id="p2_reg" placeholder="repeat password" v-model="p2" required><br>
        <input :style="{backgroundColor: mStyle}" type="email" id="m_reg" name="m" placeholder="mail" v-model="m" required><br>
        <select id="req_reg" name="req" v-model="req" required hidden>
            <option value="real" selected>Real</option>
            <option value="test">Test</option>
        </select>
        <button @click.prevent="reg">Register</button>

    </form>

    <div hidden>test = {{ test1 }} </div>

    <div hidden>inputs = {{ inputs }}</div>

    <div hidden>{{ resp }}</div>
</span>