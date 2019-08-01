<div id="plugin-list">
    <h1 class ="plugins">Plugins</h1>
    <div v-bind:class="{isVisible:testMode, isInvisible:!testMode}" class=" alert alert-warning">
        Running in test mode!
    </div>

    <form id="options-form"
          v-bind:class="{isActive:showPrompt, isInactive:!showPrompt}"
        >
    <div is="plugin-install-prompt"
         v-for="(value, key) in currentOptions"
         v-bind:key="key"
         v-bind:option-name="key"
         v-bind:name="value.name"
         v-bind:type="value.type"
         v-bind:list="value.list"
         v-bind:desc="value.desc"
         v-bind:optional="value.optional"
         v-bind:placeholder="value.placeholder"
         v-bind:max-length="value.maxLength"
         v-bind:max-num="value.maxNum"
         class="option">
    </div>
    <span>Are you sure you want to {{currentAction}} {{currentPluginName}}?</span>
        <button @click.prevent="handleForm">Yes</button>
        <button @click.prevent="togglePrompt">No</button>
    </form>

    <table class = plugins>
        <tr>
            <th></th>
            <th>Plugin</th>
            <th>Description</th>
            <th>Status</th>
            <th>Install</th>
            <th>Uninstall</th>
        </tr>
        <tr
            is="plugin"
            v-for="(value, key) in plugins"
            v-bind:key="key"
            v-bind:filename="key"
            v-bind:name="value.name"
            v-bind:status="value.status"
            v-bind:version="value.version"
            v-bind:summary="value.summary"
            v-bind:description="value.description"
            v-bind:icon="value.icon"
            v-bind:thumbnail="value.thumbnail"
            v-bind:install-status="value.installStatus"
            v-bind:uninstall-status="value.uninstallStatus"
            v-bind:install-options="value.installOptions"
            v-bind:uninstall-options="value.uninstallOptions"
            @remove="remove(key)"
            @reverse="reverse(key)"
            ></tr>
    </table>
    <div class="ui-button"><button @click="toggleTest">Toggle Test Mode</button></div>
    <div v-bind:class="{isActive:(showResponse && testMode), isInactive:!showResponse || !testMode}"><b>Server response:</b><br>
            <span v-html="serverResponse"></span>
    </div>
</div>
