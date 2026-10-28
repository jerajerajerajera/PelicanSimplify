<x-filament-panels::page>
    @assets
    <link rel="stylesheet" href="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.css') }}">
    @endassets
    <div class="ps-admin-page ps-people">
        <nav class="ps-segmented ps-people-tabs" role="tablist" aria-label="{{ __('pelican-simplify::people.page.people_and_roles') }}">
            <button type="button" role="tab" aria-pressed="{{ $tab === 'people' ? 'true' : 'false' }}" wire:click="$set('tab', 'people')"><x-tabler-users class="ps-ico" />{{ __('pelican-simplify::people.page.people') }}</button>
            <button type="button" role="tab" aria-pressed="{{ $tab === 'roles' ? 'true' : 'false' }}" wire:click="$set('tab', 'roles')"><x-tabler-shield-lock class="ps-ico" />{{ __('pelican-simplify::people.page.roles') }}</button>
        </nav>

        @if($tab === 'people')
            @php($people = $this->people())
            <div class="ps-people-bar">
                <label class="ps-people-search"><x-tabler-search class="ps-ico" /><input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('pelican-simplify::people.page.search_by_name_or_email') }}" aria-label="{{ __('pelican-simplify::people.page.search_people') }}"></label>
                <span class="ps-help ps-people-count">{{ $people['total'] }} {{ \Illuminate\Support\Str::plural('person', $people['total']) }}</span>
                <button type="button" class="ps-btn is-primary" wire:click="mountAction('addPerson')"><x-tabler-user-plus class="ps-ico" />{{ __('pelican-simplify::people.page.add_person') }}</button>
            </div>
            <div class="ps-people-list">
                @forelse($people['rows'] as $person)
                    <article class="ps-person" wire:key="person-{{ $person['id'] }}">
                        <span class="ps-person-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($person['username'], 0, 1)) }}</span>
                        <div class="ps-person-main">
                            <strong>{{ $person['username'] }} @if($person['self'])<span class="ps-badge">{{ __('pelican-simplify::people.page.you') }}</span>@endif</strong>
                            <small>{{ $person['email'] }}</small>
                            <div class="ps-person-roles">
                                @forelse($person['roles'] as $role)
                                    <span class="ps-badge {{ $role === \App\Models\Role::ROOT_ADMIN ? 'is-root' : 'is-info' }}">{{ $role }}</span>
                                @empty
                                    <span class="ps-badge is-muted">{{ __('pelican-simplify::people.page.no_role_everyone') }}</span>
                                @endforelse
                            </div>
                        </div>
                        <div class="ps-person-meta">
                            <span data-tip="{{ __('pelican-simplify::people.page.servers_owned') }}"><x-tabler-server class="ps-ico" />{{ $person['servers'] }}</span>
                            <span data-tip="{{ $person['mfa'] ? __('pelican-simplify::people.page.mfa_on') : __('pelican-simplify::people.page.mfa_off') }}" class="{{ $person['mfa'] ? 'is-ok' : 'is-off' }}"><x-tabler-shield-check class="ps-ico" />{{ __('pelican-simplify::people.page.2fa') }}</span>
                        </div>
                        <div class="ps-person-actions">
                            <button type="button" class="ps-btn is-sm" wire:click="mountAction('editPerson', { user: {{ $person['id'] }} })"><x-tabler-edit class="ps-ico" />{{ __('pelican-simplify::people.page.edit') }}</button>
                            <button type="button" class="ps-btn is-sm is-icon is-subtle" data-tip="{{ __('pelican-simplify::people.page.send_password_reset_link') }}" aria-label="{{ __('pelican-simplify::people.page.send_password_reset_link') }}" wire:click="mountAction('resetPassword', { user: {{ $person['id'] }} })"><x-tabler-key class="ps-ico" /></button>
                            <button type="button" class="ps-btn is-sm is-icon is-subtle is-danger" data-tip="{{ $person['self'] ? __('pelican-simplify::people.page.cannot_delete_self') : ($person['servers'] ? __('pelican-simplify::people.page.owns_servers') : __('pelican-simplify::people.page.delete')) }}" aria-label="{{ __('pelican-simplify::people.page.delete_name', ['name' => $person['username']]) }}" @disabled($person['self'] || $person['servers']) wire:click="mountAction('deletePerson', { user: {{ $person['id'] }} })"><x-tabler-trash class="ps-ico" /></button>
                        </div>
                    </article>
                @empty
                    <p class="ps-empty">{{ __('pelican-simplify::people.page.nobody_matches', ['search' => $search]) }}</p>
                @endforelse
            </div>
            @if($people['pages'] > 1)
                <nav class="ps-people-pages" aria-label="{{ __('pelican-simplify::people.page.pages') }}">
                    <button type="button" class="ps-btn is-sm" @disabled($peoplePage <= 1) wire:click="setPage({{ $peoplePage - 1 }})">{{ __('pelican-simplify::people.page.previous') }}</button>
                    <span class="ps-help">{{ __('pelican-simplify::people.page.page_of', ['page' => $peoplePage, 'pages' => $people['pages']]) }}</span>
                    <button type="button" class="ps-btn is-sm" @disabled($peoplePage >= $people['pages']) wire:click="setPage({{ $peoplePage + 1 }})">{{ __('pelican-simplify::people.page.next') }}</button>
                </nav>
            @endif
        @else
            <div class="ps-people-bar">
                <p class="ps-help ps-people-intro">{{ __('pelican-simplify::people.page.roles_intro') }}</p>
                <button type="button" class="ps-btn is-primary" wire:click="mountAction('addRole')"><x-tabler-shield-plus class="ps-ico" />{{ __('pelican-simplify::people.page.add_role') }}</button>
            </div>
            <div class="ps-role-grid">
                @foreach($this->roles() as $role)
                    <article class="ps-role {{ $role['root'] ? 'is-root' : '' }}" wire:key="role-{{ $role['id'] }}">
                        <header>
                            <span class="ps-role-icon">@if($role['root'])<x-tabler-crown class="ps-ico" />@else<x-tabler-shield class="ps-ico" />@endif</span>
                            <strong>{{ $role['name'] }}</strong>
                        </header>
                        <p class="ps-help">
                            {{ trans_choice('pelican-simplify::people.page.members', $role['members']) }}
                            @if($role['root']) · {{ __('pelican-simplify::people.page.full_access') }} @else · {{ __('pelican-simplify::people.page.role_counts', ['abilities' => $role['abilities'], 'permissions' => $role['permissions']]) }} @endif
                        </p>
                        @unless($role['root'])
                            <footer>
                                <button type="button" class="ps-btn is-sm" wire:click="mountAction('editRole', { role: {{ $role['id'] }} })"><x-tabler-edit class="ps-ico" />{{ __('pelican-simplify::people.page.edit') }}</button>
                                <button type="button" class="ps-btn is-sm is-icon is-subtle is-danger" data-tip="{{ __('pelican-simplify::people.page.delete_role') }}" aria-label="{{ __('pelican-simplify::people.page.delete_role_name', ['name' => $role['name']]) }}" wire:click="mountAction('deleteRole', { role: {{ $role['id'] }} })"><x-tabler-trash class="ps-ico" /></button>
                            </footer>
                        @else
                            <footer><span class="ps-help"><x-tabler-lock class="ps-ico" /> {{ __('pelican-simplify::people.page.managed_by_pelican') }}</span></footer>
                        @endunless
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
