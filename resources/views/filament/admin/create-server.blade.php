<x-filament-panels::page>
    @assets
    <link rel="stylesheet" href="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.css') }}">
    <script src="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.js') }}"></script>
    @endassets
    <div class="ssc-wizard" wire:key="ssc-creator-shell" wire:ignore.self x-data="sscDraft($wire)" :class="{'is-saving-draft':saving}" x-on:click="buttonClicked($event)" x-on:input="changed()" x-on:change="fieldChanged($event)" x-on:focusin="fieldFocused($event)" x-on:focusout="fieldExited($event)" x-on:livewire-upload-start="uploading=true;changed()" x-on:livewire-upload-finish="uploading=false;changed();flush(false).catch(()=>{})" x-on:livewire-upload-error="uploading=false;changed()" x-on:livewire-upload-cancel="uploading=false;changed()" x-on:ssc-draft-completed.window="complete()">
        <div class="ssc-draft-bar" wire:ignore role="group" aria-label="Draft actions">
            <span class="ssc-draft-status" role="status" x-text="status"></span>
            <span class="ssc-draft-indicator" :data-tip="status" :aria-label="status" :class="{'is-unsaved':dirty,'is-saving':saving}">
                <svg x-effect="if(refreshCount>0 &amp;&amp; !window.matchMedia('(prefers-reduced-motion: reduce)').matches)$el.animate([{transform:'rotate(0deg)'},{transform:'rotate(360deg)'}],{duration:450,easing:'ease-out'})" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12l4 4v12a2 2 0 0 1-2 2Z"/><path d="M7 3v6h10V3M7 21v-8h10v8"/></svg>
                <svg class="ssc-save-check" x-show="savedVisible()" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" x-effect="if(savedCount>0)$el.animate([{opacity:0,transform:'translateY(6px) scale(.5)'},{opacity:1,transform:'translateY(-2px) scale(1.1)',offset:.18},{opacity:1,transform:'translateY(0) scale(1)'}],{duration:450})"><path d="m5 12 4 4 10-10"/></svg>
            </span>
            <div class="ssc-delete-concept" :class="{'is-confirming':deleteConfirm}"><button type="button" class="ps-btn is-sm ssc-discard" x-show="!deleteConfirm" x-on:click="discard()" :disabled="saving || uploading || completed" aria-label="Discard draft">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M5 6l1 14a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1l1-14M10 10v7M14 10v7"/></svg><span>Discard</span>
            </button>
            <div class="ssc-delete-confirm" x-show="deleteConfirm" x-cloak><button type="button" class="ps-btn is-icon" data-tip="Keep draft" aria-label="Keep draft" x-on:click="deleteConfirm=false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button><button type="button" class="ps-btn is-icon is-danger" data-tip="Delete draft" aria-label="Confirm draft deletion" :disabled="saving||uploading||completed" x-on:click="discard(true)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4 10-10"/></svg></button></div></div>
        </div>
        <nav class="ssc-progress" wire:ignore style="--ssc-steps: {{ count($this->wizardStepLabels()) }}" :style="{'--ssc-step': step}" aria-label="Server creation progress">
            @foreach($this->wizardStepLabels() as $index=>$label)
                <button type="button" :class="{'is-current':step==={{ $index+1 }},'is-complete':furthestStep>{{ $index+1 }}}" :disabled="{{ $index+1 }}>furthestStep || switching || uploading" :aria-current="step==={{ $index+1 }}?'step':null" x-on:click="goToStep({{ $index+1 }})"><span>{{ $index+1 }}</span>{{ $label }}</button>
                @unless($loop->last)<span class="ssc-step-arrow" aria-hidden="true">&gt;</span>@endunless
            @endforeach
            <span class="ssc-progress-count" x-text="'Step '+step+' of {{ count($this->wizardStepLabels()) }}'"></span>
        </nav>
        @if($draftIcon)
            <div class="ssc-draft-icon"><img src="data:image/png;base64,{{ $draftIcon }}" alt="Saved server icon" width="48" height="48"><span>Saved server icon · upload a new icon to replace it.</span><button type="button" class="ps-btn is-sm" wire:click="removeDraftIcon" x-on:click="changed()">Remove</button></div>
        @endif
        <form wire:submit="create">
            {{ $this->form }}
        </form>
    </div>

</x-filament-panels::page>
