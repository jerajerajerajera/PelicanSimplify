{{-- Browse Hub: the Pelican Hub's themes and plugins with their makers up front. Installing happens on the Hub itself. --}}
@if(!$hubLoaded)
    <div class="ps-hub-loading" wire:init="loadHub"><span class="ps-spinner" aria-hidden="true"></span><span>Loading the Pelican Hub…</span></div>
@else
    @php($hub = $this->hub())
    @if($hub['error'])
        <div class="ps-alert is-warn" role="alert"><x-tabler-cloud-off class="ps-ico" aria-hidden="true" /><div><strong>{{ $hub['error'] }}</strong><small>Installed plugins keep working. You can also browse on <a href="https://hub.pelican.dev/plugins" target="_blank" rel="noopener">hub.pelican.dev</a>.</small></div></div>
    @else
        <div class="ps-hub" wire:ignore wire:key="hub-{{ $hub['at'] }}" x-data="simplifyHubBrowser(@js($hub))" @keydown.escape.window="close()">
            <div class="ps-alert is-info ps-hub-intro" role="note">
                <x-tabler-heart-handshake class="ps-ico" aria-hidden="true" />
                <div>
                    <strong>Made by the Pelican community</strong>
                    <small>Install on Hub opens the item on the Pelican Hub. Sign in there with Discord, connect this panel once and press Install, so every download counts for its maker. It then shows up under Installed.</small>
                </div>
            </div>
            <div class="ps-hub-bar">
                <label class="ps-people-search ps-hub-search"><x-tabler-search class="ps-ico" /><input type="search" x-model="q" :placeholder="'Search ' + typeLabel(type, 2) + ' or makers'" aria-label="Search the Hub"></label>
                <div class="ps-segmented" role="group" aria-label="Type">
                    <template x-for="t in types" :key="t">
                        <button type="button" :aria-pressed="type === t" @click="setType(t)"><span x-text="typeLabel(t, 2, true)"></span><span class="ps-hub-count" x-text="count(t)"></span></button>
                    </template>
                </div>
                <select class="ps-hub-sort" x-model="sort" aria-label="Sort">
                    <option value="downloads">Most downloads</option>
                    <option value="updated">Recently updated</option>
                    <option value="name">Name</option>
                </select>
            </div>

            <section class="ps-hub-makers" aria-label="Makers">
                <p class="ps-hub-label">Made by</p>
                <div class="ps-hub-maker-row">
                    <template x-for="m in makers[type]" :key="m.name">
                        <button type="button" class="ps-hub-maker" :aria-pressed="maker === m.name" @click="maker = maker === m.name ? null : m.name">
                            <span class="ps-hub-avatar" :style="avatar(m.name)" x-text="initial(m.name)" aria-hidden="true"></span>
                            <span><strong x-text="m.name"></strong><small x-text="m.count + ' ' + typeLabel(type, m.count) + ' · ' + m.downloads.toLocaleString() + ' downloads'"></small></span>
                        </button>
                    </template>
                </div>
            </section>
            <p class="ps-help ps-hub-filter" x-show="maker" x-cloak>Showing work by <strong x-text="maker"></strong> <button type="button" class="ps-btn is-sm is-subtle" @click="maker = null">Show all</button></p>

            <div class="ps-alert is-warn" role="note" x-show="type === 'theme' && !(build.node && build.yarn)" x-cloak>
                <x-tabler-alert-triangle class="ps-ico" aria-hidden="true" />
                <div>
                    <strong x-text="(build.node ? '' : 'Node.js 22.12+') + (!build.node && !build.yarn ? ' and ' : '') + (build.yarn ? '' : 'Yarn') + (build.node || build.yarn ? ' is' : ' are') + ' missing on this panel server'"></strong>
                    <small>Pelican rebuilds the panel style after a theme is installed, and that needs both. Install them on the panel server first, then install the theme. Make node and yarn available for the web server and queue worker.</small>
                    <pre class="ps-hub-cmd">curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash -
sudo apt install -y nodejs
sudo corepack enable</pre>
                </div>
            </div>

            <div class="ps-hub-grid" :class="type === 'theme' ? 'has-shots' : ''">
                <template x-for="item in list()" :key="item.slug">
                    <article class="ps-hub-card">
                        <template x-if="item.screenshots.length">
                            <div class="ps-hub-shot">
                                <button type="button" class="ps-hub-shot-open" @click="show(item)" :aria-label="'Show ' + item.title"><img :src="item.screenshots[slide[item.slug] || 0]" :alt="'Screenshot of ' + item.title" loading="lazy" referrerpolicy="no-referrer"></button>
                                <template x-if="item.screenshots.length > 1">
                                    <div>
                                        <button type="button" class="ps-hub-nav is-prev" @click="step(item, -1)" aria-label="Previous screenshot"><x-tabler-chevron-left /></button>
                                        <button type="button" class="ps-hub-nav is-next" @click="step(item, 1)" aria-label="Next screenshot"><x-tabler-chevron-right /></button>
                                        <span class="ps-hub-dots" aria-hidden="true"><template x-for="(s, i) in item.screenshots" :key="i"><i :class="i === (slide[item.slug] || 0) ? 'is-on' : ''"></i></template></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <div class="ps-hub-body">
                            <div class="ps-hub-by">
                                <span class="ps-hub-avatar" :style="avatar(item.author)" x-text="initial(item.author)" aria-hidden="true"></span>
                                <span><small>Made by</small><strong x-text="item.author"></strong></span>
                            </div>
                            <div class="ps-hub-title"><strong x-text="item.title"></strong><small x-show="item.version" x-text="'v' + item.version"></small><span class="ps-badge is-ok" x-show="item.installed" x-cloak><i></i><span>Installed</span></span></div>
                            <p x-show="item.summary" x-text="item.summary"></p>
                            <p class="ps-hub-based" x-show="item.based" x-cloak>Based on the work of <a :href="item.based?.url" target="_blank" rel="noopener" x-text="item.based?.name"></a></p>
                            <div class="ps-hub-meta">
                                <span><x-tabler-download class="ps-ico" aria-hidden="true" /><span x-text="item.downloads.toLocaleString() + ' downloads'"></span></span>
                                <span x-show="item.license" x-text="item.license"></span>
                                <span x-show="item.updated" x-text="'Updated ' + date(item.updated)"></span>
                            </div>
                            <div class="ps-hub-actions">
                                <a class="ps-btn is-sm" :class="item.installed ? '' : 'is-primary'" :href="item.url" target="_blank" rel="noopener"><span x-text="item.installed ? 'View on Hub' : 'Install on Hub'"></span><x-tabler-external-link class="ps-ico" aria-hidden="true" /></a>
                                <button type="button" class="ps-btn is-sm" x-show="item.screenshots.length" @click="show(item)">Details</button>
                            </div>
                        </div>
                    </article>
                </template>
            </div>
            <p class="ps-empty" x-show="!list().length" x-cloak>Nothing found for “<span x-text="q"></span>”.</p>

            <footer class="ps-hub-foot">
                <span>From <a href="https://hub.pelican.dev/plugins" target="_blank" rel="noopener">hub.pelican.dev</a>@if($hub['at']), read {{ \Carbon\Carbon::createFromTimestamp($hub['at'])->diffForHumans() }}@endif. The list is read again once an hour.</span>
                <button type="button" class="ps-btn is-sm is-subtle" wire:click="refreshHub"><x-tabler-refresh class="ps-ico" />Read again</button>
            </footer>

            <template x-teleport="body">
            <div class="ps-hub-layer" x-show="open" x-cloak @click.self="close()">
                <div class="ps-hub-dialog" role="dialog" aria-modal="true" :aria-label="open?.title">
                    <template x-if="open">
                        <div>
                            <div class="ps-hub-big"><img :src="open.screenshots[shot]" :alt="'Screenshot of ' + open.title" referrerpolicy="no-referrer"></div>
                            <div class="ps-hub-thumbs" x-show="open.screenshots.length > 1">
                                <template x-for="(s, i) in open.screenshots" :key="i"><button type="button" :aria-current="i === shot" @click="shot = i"><img :src="s" :alt="'Screenshot ' + (i + 1)" loading="lazy" referrerpolicy="no-referrer"></button></template>
                            </div>
                            <div class="ps-hub-dialog-body">
                                <div class="ps-hub-dialog-head">
                                    <div><strong x-text="open.title"></strong><small x-text="['v' + open.version, open.license].filter(Boolean).join(' · ')"></small></div>
                                    <button type="button" class="ps-btn is-sm is-subtle" @click="close()" aria-label="Close"><x-tabler-x class="ps-ico" /></button>
                                </div>
                                <div class="ps-hub-maker-card">
                                    <span class="ps-hub-avatar is-lg" :style="avatar(open.author)" x-text="initial(open.author)" aria-hidden="true"></span>
                                    <span><small>Made by</small><strong x-text="open.author"></strong><small x-text="makerLine(open)"></small></span>
                                    <button type="button" class="ps-btn is-sm" x-show="(makers[open.type].find(m => m.name === open.author)?.count || 0) > 1" @click="maker = open.author; close()">More by this maker</button>
                                </div>
                                <p x-text="open.summary"></p>
                                <div class="ps-hub-actions">
                                    <a class="ps-btn is-primary" :href="open.url" target="_blank" rel="noopener"><span x-text="open.installed ? 'View on Hub' : 'Install on Hub'"></span><x-tabler-external-link class="ps-ico" aria-hidden="true" /></a>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            </template>
        </div>
    @endif
@endif
