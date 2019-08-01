<div id="plugin-order-list">
    <h1 class ="plugins">Plugin Order List</h1>
    <button @click="toggleVisible">Show Plugin Order</button>
    <div  :class="{isVisible:isVisible, isInvisible:!isVisible}">
        <label>Drag & Drop mode:</label>
        <button @click="setMovement('move')" :class="{activeButton: (movementType == 'move')}">Move</button>
        <button @click="setMovement('swap')" :class="{activeButton: (movementType == 'swap')}">Swap</button>
        <table class="plugin-order-table" :class = "{lowVisual: replyInTransit}">
            <tr>
                <th>Index</th>
                <th>Exact-Name: Long Name</th>
                <th>Drag&Drop</th>
            </tr>
            <tr is="plugin-order"
                v-for="(value, index) in pluginOrder"
                v-bind:index="index"
                v-bind:file-name="value.fileName"
                v-bind:name="value.name"
                v-bind:icon="value.icon"
                v-bind:thumbnail="value.thumbnail"></tr>
        </table>
    </div>
</div>