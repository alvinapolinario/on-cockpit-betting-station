@php
$configData = Helper::appClasses();
$currentRouteName = Route::currentRouteName();
$welcomeName = session()->get('welcome_name') ?: session()->get('name') ?: 'Admin';
$accountType = session()->get('account_type') ?: 'User';

$isActive = function ($slug) use ($currentRouteName) {
    if (!$slug || !$currentRouteName) {
        return false;
    }
    if (is_array($slug)) {
        foreach ($slug as $one) {
            if ($currentRouteName === $one || (str_contains($currentRouteName, $one) && strpos($currentRouteName, $one) === 0)) {
                return true;
            }
        }
        return false;
    }
    return $currentRouteName === $slug || (str_contains($currentRouteName, $slug) && strpos($currentRouteName, $slug) === 0);
};

$groups = [];
$currentHeader = 'Menu';
$logoutUrl = url('/logout');
foreach (($menuData[0]->menu ?? []) as $menu) {
    if (isset($menu->menuHeader)) {
        $currentHeader = $menu->menuHeader;
        continue;
    }
    if (($menu->slug ?? '') === 'logout' || ($menu->url ?? '') === '/logout') {
        $logoutUrl = isset($menu->url) ? url($menu->url) : url('/logout');
        continue;
    }
    $groups[$currentHeader][] = $menu;
}
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme arena-nav">
  <div class="menu-inner">
    <div class="side-shell">
      <div class="side-head">
        <a class="side-logo" href="{{ url('/') }}">
          <span class="side-mark">S</span>
          <span class="side-brand">
            <strong>{{ config('variables.templateName') }}</strong>
            <small>Admin</small>
          </span>
        </a>
        <button type="button" class="side-close" aria-label="Close menu">
          <i class="bx bx-x"></i>
        </button>
      </div>

      <nav class="side-nav">
        @foreach ($groups as $header => $items)
          <div class="side-section">
            <p>{{ $header }}</p>
            @foreach ($items as $menu)
              @php
                $childActive = false;
                if (isset($menu->submenu)) {
                    foreach ($menu->submenu as $submenu) {
                        if ($isActive($submenu->slug ?? null)) {
                            $childActive = true;
                        }
                    }
                }
                $active = $isActive($menu->slug ?? null) || $childActive;
              @endphp
              <div class="side-item {{ $active ? 'is-open' : '' }}">
                <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0);' }}"
                   class="side-link {{ $active ? 'is-active' : '' }} {{ isset($menu->submenu) ? 'has-sub' : '' }}"
                   @if (isset($menu->target) and !empty($menu->target)) target="_blank" @endif>
                  <i class="{{ $menu->icon ?? 'bx bx-circle' }}"></i>
                  <span>{{ $menu->name ?? '' }}</span>
                  @isset($menu->submenu)
                    <i class="bx bx-chevron-down side-caret"></i>
                  @endisset
                </a>
                @isset($menu->submenu)
                  <div class="side-sub">
                    @foreach ($menu->submenu as $submenu)
                      <a href="{{ isset($submenu->url) ? url($submenu->url) : 'javascript:void(0);' }}"
                         class="{{ $isActive($submenu->slug ?? null) ? 'is-active' : '' }}">
                        <span>{{ $submenu->name ?? '' }}</span>
                      </a>
                    @endforeach
                  </div>
                @endisset
              </div>
            @endforeach
          </div>
        @endforeach
      </nav>

      <div class="side-foot">
        <div class="side-who">
          <div>
            <b>{{ $welcomeName }}</b>
            <small>{{ $accountType }}</small>
          </div>
          <a href="{{ $logoutUrl }}">Sign out</a>
        </div>
      </div>
    </div>
  </div>
</aside>
