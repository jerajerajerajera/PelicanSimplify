{{-- Game step: Minecraft with its server types on top, then every other game (installed games and the Pelican egg
     index, searched in the browser, no request per keystroke). A choice is sent with pickGame(); Minecraft moves on. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php
        $data = $getViewData();
        $cards = $data['cards'] ?? [];
        $minecraft = array_diff_key($data['minecraft'] ?? [], ['proxy' => true]);
        $others = $cards !== [] || !$minecraft;
    @endphp
    @if ($minecraft)
        <section @class(['ssc-minecraft', 'is-picked' => ($data['game'] ?? '') === 'minecraft']) aria-labelledby="ssc-minecraft-title"
                 x-data="{ busy: false, pick(type) {
                    if (this.busy) return;
                    this.busy = true;
                    // Moves on once Livewire has re-rendered and enabled Next again (the section itself may be replaced).
                    const next = (tries) => {
                        const button = [...document.querySelectorAll('.fi-sc-wizard .ssc-next-button')].find((b) => b.offsetParent);
                        if (button && !button.disabled) button.click(); else if (tries > 0) setTimeout(() => next(tries - 1), 100);
                    };
                    $wire.pickGame('minecraft', type).then(() => next(30)).finally(() => this.busy = false);
                 } }">
            <div class="ssc-minecraft-head">
                <span class="ssc-minecraft-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="1"/><path fill="currentColor" stroke="none" d="M6 7h4v4H6zm8 0h4v4h-4zm-4 4h4v3h3v5h-3v-3h-4v3H7v-5h3z"/></svg></span>
                <div><h3 id="ssc-minecraft-title">{{ __('pelican-simplify::maker.game.minecraft') }}</h3><p>{{ __('pelican-simplify::maker.game.java_and_bedrock_pick_what_kind') }}</p></div>
            </div>
            <div class="ssc-minecraft-types" role="group" aria-label="{{ __('pelican-simplify::maker.game.minecraft_server_types') }}">
                @foreach ($minecraft as $key => [$label, $line])
                    <button type="button" class="ssc-minecraft-type" aria-pressed="{{ ($data['game'] ?? '') === 'minecraft' && ($data['type'] ?? '') === $key ? 'true' : 'false' }}" x-on:click="pick(@js($key))" :disabled="busy">
                        <strong>{{ $label }}</strong><small>{{ $line }}</small>
                    </button>
                @endforeach
            </div>
        </section>
    @endif
    @if ($others)
    @if ($minecraft)<h3 class="ssc-games-heading">{{ __('pelican-simplify::maker.game.other_games') }}</h3>@endif
    <div class="ssc-games" wire:ignore
         x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            cards: @js($cards),
            query: '',
            filter: 'all',
            limit: 24,
            norm(text) { return String(text || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, ' ').trim(); },
            shown() {
                const words = this.norm(this.query).split(' ').filter(Boolean);
                let list = this.cards.filter((c) => (this.filter !== 'installed' || c.installed) && (this.filter !== 'popular' || c.popular > 0)
                    && words.every((w) => this.norm(c.name + ' ' + c.description).includes(w)));
                list.sort((a, b) => (this.filter === 'popular' || !words.length ? b.popular - a.popular : 0) || (b.installed - a.installed) || a.name.localeCompare(b.name));
                return list;
            },
            initials(name) { return name.replace(/[^A-Za-z0-9 ]/g, ' ').split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?'; },
            hue(name) { let h = 0; for (const ch of name) h = (h * 31 + ch.charCodeAt(0)) % 360; return h; },
            selected() { return this.cards.find((c) => c.key === this.state) || null; },
            installing: null,
            phase: 0,
            timer: null,
            init() {
                // Next on an index game installs its egg first: show that as its own screen until the step changes.
                this.$root.closest('.fi-sc-wizard')?.addEventListener('click', (event) => {
                    const button = event.target.closest('button');
                    if (!button || !/\bnext\b/i.test(button.textContent) || !this.selected() || this.selected().installed) return;
                    this.installing = this.selected().name;
                    this.phase = 0;
                    clearInterval(this.timer);
                    this.timer = setInterval(() => this.phase = Math.min(this.phase + 1, 2), 1600);
                }, true);
                window.Livewire?.hook?.('commit', ({ succeed, fail }) => {
                    if (!this.installing) return;
                    const done = () => setTimeout(() => { clearInterval(this.timer); this.installing = null; }, 150);
                    succeed(done);
                    fail(done);
                });
            },
         }">
        <div class="ssc-games-install" x-show="installing" x-cloak role="status" aria-live="polite">
            <span class="ssc-games-install-spinner" aria-hidden="true"></span>
            <strong>{{ __('pelican-simplify::maker.game.installing') }} <span x-text="installing"></span></strong>
            <ol>
                <li :class="{'is-done': phase > 0, 'is-now': phase === 0}">{{ __('pelican-simplify::maker.game.downloading_the_egg_from_the_pelican') }}</li>
                <li :class="{'is-done': phase > 1, 'is-now': phase === 1}">{{ __('pelican-simplify::maker.game.checking_it_against_the_published_checksum') }}</li>
                <li :class="{'is-now': phase === 2}">{{ __('pelican-simplify::maker.game.adding_it_to_this_panel') }}</li>
            </ol>
            <small>{{ __('pelican-simplify::maker.game.the_creator_continues_by_itself_when') }}</small>
        </div>
        <div class="ssc-games-bar">
            <label class="ssc-games-search">
                <x-tabler-search class="ssc-games-search-icon" />
                <input type="search" x-model.debounce.120ms="query" x-on:input="limit = 24" placeholder="Search {{ count($cards) }} {{ $minecraft ? 'other ' : '' }}games…" aria-label="{{ __('pelican-simplify::maker.game.search_games') }}" autocomplete="off" />
            </label>
            <div class="ssc-games-chips" role="group" aria-label="{{ __('pelican-simplify::maker.game.filter_games') }}">
                <button type="button" :aria-pressed="filter==='all'" x-on:click="filter='all'; limit=24">{{ __('pelican-simplify::maker.game.all') }}</button>
                <button type="button" :aria-pressed="filter==='popular'" x-on:click="filter='popular'; limit=24">{{ __('pelican-simplify::maker.game.popular') }}</button>
                <button type="button" :aria-pressed="filter==='installed'" x-on:click="filter='installed'; limit=24">{{ __('pelican-simplify::maker.game.installed') }}</button>
            </div>
        </div>
        <div class="ssc-games-grid" role="radiogroup" aria-label="{{ __('pelican-simplify::maker.game.games') }}">
            <template x-for="card in shown().slice(0, limit)" :key="card.key">
                <button type="button" class="ssc-game" role="radio" :aria-checked="state===card.key" x-on:click="state = card.key; $wire.pickGame('other', card.key)">
                    <span class="ssc-game-icon" :style="{'--hue': hue(card.name)}" x-text="initials(card.name)"></span>
                    <span class="ssc-game-text">
                        <strong x-text="card.name"></strong>
                        <small x-text="card.description"></small>
                    </span>
                    <span class="ssc-game-badge" :class="card.installed ? 'is-installed' : 'is-available'" x-text="card.installed ? @js(__('pelican-simplify::maker.game.installed')) : @js(__('pelican-simplify::maker.game.available'))"></span>
                </button>
            </template>
        </div>
        <p class="ssc-games-empty" x-show="!shown().length">{{ __('pelican-simplify::maker.game.no_game_matches') }}<span x-text="query"></span>”.@if ($minecraft)<span x-show="/mine|craft|paper|purpur|forge|fabric|quilt|bedrock|modpack|spigot/i.test(query)"> {{ __('pelican-simplify::maker.game.minecraft_is_at_the_top_of') }}</span>@endif</p>
        <button type="button" class="ssc-games-more" x-show="shown().length > limit" x-on:click="limit += 48" x-text="'Show more (' + (shown().length - limit) + ')'"></button>
        <p class="ssc-games-note" x-show="selected() && !selected().installed">
            <strong x-text="selected()?.name"></strong> {{ __('pelican-simplify::maker.game.is_not_on_this_panel_yet') }}
        </p>
    </div>
    @endif
</x-dynamic-component>
