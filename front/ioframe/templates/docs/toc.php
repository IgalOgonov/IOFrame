
<span id="toc">

    <div class="toc-wrapper">
        <h2><a name="ToC"></a>{{tocTitle}}</h2>

        <div v-for="(value, key) in menu" :id="key" class="toc-menu-div" v-if="!isMeta(key)" :class="{open:value['@open'], selected:value['@selected']}">
            <div class="button-container">
                <button @click="displaySection(key)">{{value['@title']}}</button>
                <button v-if="Object.keys(value).length>3"
                        @click="toggleMenu(key)"></button>
            </div>
            <div v-for="(value2, key2) in value" :id="key2" class="toc-menu-div" v-if="!isMeta(key2)" :class="{open:value2['@open'], selected:value2['@selected']}">
                <div class="button-container">
                    <button @click="displaySection(key,key2)">{{value2['@title']}}</button>
                    <button v-if="Object.keys(value2).length>3"
                            @click="toggleMenu(key,key2)"></button>
                </div>
                <div v-for="(value3, key3) in value2"  :id="key3" class="toc-menu-div" v-if="!isMeta(key3)" :class="{selected:value3['@selected']}">
                    <div class="button-container">
                        <button @click="displaySection(key,key2,key3)">{{value3['@title']}}</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

</span>
