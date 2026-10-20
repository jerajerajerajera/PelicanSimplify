<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    {{-- The editor keeps its own state; Livewire only receives the finished MOTD text. --}}
    <div wire:ignore class="ps-motd" x-data="psMotdEditor({ value: $wire.$entangle(@js($getStatePath())), name: () => $wire.$get('data.name') })">
        @include('pelican-simplify::motd-editor')
    </div>
</x-dynamic-component>
