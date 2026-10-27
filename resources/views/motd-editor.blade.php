{{-- Minecraft MOTD editor (js/motd.js, css/motd.css). Include it inside the element that creates the component:
     <div class="ps-motd" x-data="psMotdEditor({ name: () => …, icon: () => … })" x-modelable="value" x-model="…">. --}}
<div class="ps-motd-toolbar" role="toolbar" aria-label="{{ __('pelican-simplify::js.motd.toolbar') }}">
    <div class="ps-motd-colors">
        <template x-for="color in colors" :key="color[0]">
            <button type="button" class="ps-swatch" :style="{'background-color': color[2]}" :data-tip="color[1]" :aria-label="@js(__('pelican-simplify::js.motd.colour_label')).replace(':color', color[1])" :aria-pressed="colorActive(color[0])" x-on:mousedown.prevent x-on:click="setColor(color[0])"></button>
        </template>
    </div>
    <div class="ps-motd-styles">
        <button type="button" class="ps-btn is-icon is-sm" data-tip="{{ __('pelican-simplify::js.motd.bold') }}" aria-label="{{ __('pelican-simplify::js.motd.bold') }}" :aria-pressed="formatActive('l')" x-on:mousedown.prevent x-on:click="toggleFormat('l')"><b>B</b></button>
        <button type="button" class="ps-btn is-icon is-sm" data-tip="{{ __('pelican-simplify::js.motd.italic') }}" aria-label="{{ __('pelican-simplify::js.motd.italic') }}" :aria-pressed="formatActive('o')" x-on:mousedown.prevent x-on:click="toggleFormat('o')"><i>I</i></button>
        <button type="button" class="ps-btn is-icon is-sm" data-tip="{{ __('pelican-simplify::js.motd.underline') }}" aria-label="{{ __('pelican-simplify::js.motd.underline') }}" :aria-pressed="formatActive('n')" x-on:mousedown.prevent x-on:click="toggleFormat('n')"><u>U</u></button>
        <button type="button" class="ps-btn is-icon is-sm" data-tip="{{ __('pelican-simplify::js.motd.strikethrough') }}" aria-label="{{ __('pelican-simplify::js.motd.strikethrough') }}" :aria-pressed="formatActive('m')" x-on:mousedown.prevent x-on:click="toggleFormat('m')"><s>S</s></button>
        <button type="button" class="ps-btn is-icon is-sm" data-tip="{{ __('pelican-simplify::js.motd.obfuscated_tip') }}" aria-label="{{ __('pelican-simplify::js.motd.obfuscated') }}" :aria-pressed="formatActive('k')" x-on:mousedown.prevent x-on:click="toggleFormat('k')">▒</button>
        <span class="ps-motd-divider" aria-hidden="true"></span>
        <button type="button" class="ps-btn is-sm" data-tip="{{ __('pelican-simplify::js.motd.clear_tip') }}" x-on:mousedown.prevent x-on:click="clearFormatting()">{{ __('pelican-simplify::js.motd.clear') }}</button>
    </div>
</div>
<div class="ps-motd-row">
    <img class="ps-motd-icon" alt="" x-show="icon()" :src="icon()">
    <span class="ps-motd-icon" x-show="!icon()" aria-hidden="true"></span>
    <div class="ps-motd-head"><span x-text="name()"></span><span>0/20</span></div>
    <div class="ps-motd-field">
        <div class="ps-motd-mirror" x-ref="mirror0" aria-hidden="true"><template x-for="(part, n) in editorRuns(0)" :key="n"><span :class="{'is-obfuscated': part.obfuscated}" :style="part.style" x-text="part.text"></span></template></div>
        <input type="text" x-ref="line0" aria-label="{{ __('pelican-simplify::js.motd.line_1_label') }}" placeholder="{{ __('pelican-simplify::js.motd.line_1') }}" spellcheck="false" autocomplete="off"
            x-on:input="edit(0, $event)" x-on:keydown="keydown(0, $event)" x-on:scroll="scroll(0, $event)" x-on:keyup="scroll(0, $event)" x-on:click="scroll(0, $event)">
    </div>
    <div class="ps-motd-field">
        <div class="ps-motd-mirror" x-ref="mirror1" aria-hidden="true"><template x-for="(part, n) in editorRuns(1)" :key="n"><span :class="{'is-obfuscated': part.obfuscated}" :style="part.style" x-text="part.text"></span></template></div>
        <input type="text" x-ref="line1" aria-label="{{ __('pelican-simplify::js.motd.line_2_label') }}" placeholder="{{ __('pelican-simplify::js.motd.line_2') }}" spellcheck="false" autocomplete="off"
            x-on:input="edit(1, $event)" x-on:keydown="keydown(1, $event)" x-on:scroll="scroll(1, $event)" x-on:keyup="scroll(1, $event)" x-on:click="scroll(1, $event)">
    </div>
</div>
<div class="ps-motd-meta">
    <span :class="{'is-warn': notice}" x-text="notice || @js(__('pelican-simplify::js.motd.hint'))"></span>
    <span><span x-text="length()"></span>/512 · <button type="button" class="ps-motd-link" x-on:click="showCodes = !showCodes" x-text="showCodes ? @js(__('pelican-simplify::js.motd.hide_codes')) : @js(__('pelican-simplify::js.motd.show_codes'))"></button></span>
</div>
<textarea class="ps-input ps-motd-codes" x-show="showCodes" x-cloak aria-label="{{ __('pelican-simplify::js.motd.codes_label') }}" spellcheck="false" :value="written" x-on:change="editCodes($event)"></textarea>
