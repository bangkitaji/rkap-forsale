@php
use Illuminate\Support\Facades\Route;
@endphp

<ul class="menu-sub">
  @if (isset($menu))
    @foreach ($menu as $submenu)

    {{-- permission guard --}}
    @if (isset($submenu->permission))
      @cannot($submenu->permission)
        @continue
      @endcannot
    @endif

    {{-- Kepala Departemen restriction for specific reports --}}
    @if (isset($submenu->url) && in_array($submenu->url, ['analytics/cashflow']) && auth()->check() && auth()->user()->isKepalaDepartemen())
      @continue
    @endif

    {{-- Bureau User restriction for cashflow and reconciliation reports --}}
    @if (isset($submenu->url) && in_array($submenu->url, ['analytics/cashflow', 'analytics/reconciliation']) && auth()->check() && auth()->user()->isKepalaBiro())
      @continue
    @endif

    {{-- active menu method --}}
    @php
      $activeClass = null;
      $active = 'active open';
      $currentRouteName = Route::currentRouteName();

      if (gettype($submenu->slug) === 'array') {
          if (in_array($currentRouteName, $submenu->slug)) {
              $activeClass = isset($submenu->submenu) ? 'active open' : 'active';
          } elseif (isset($submenu->submenu)) {
              foreach($submenu->slug as $slug){
                  if (str_contains($currentRouteName,$slug) and strpos($currentRouteName,$slug) === 0) {
                      $activeClass = 'active open';
                  }
              }
          }
      } else {
          if ($currentRouteName === $submenu->slug) {
              $activeClass = isset($submenu->submenu) ? 'active open' : 'active';
          } elseif (isset($submenu->submenu)) {
              if (str_contains($currentRouteName,$submenu->slug) and strpos($currentRouteName,$submenu->slug) === 0) {
                  $activeClass = 'active open';
              }
          }
      }
    @endphp

      <li class="menu-item {{$activeClass}}">
        <a href="{{ isset($submenu->url) ? url($submenu->url) : 'javascript:void(0)' }}" class="{{ isset($submenu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}" @if (isset($submenu->target) and !empty($submenu->target)) target="_blank" @endif>
          @if (isset($submenu->icon))
          <i class="{{ $submenu->icon }}"></i>
          @endif
          <div>{{ isset($submenu->name) ? __($submenu->name) : '' }}</div>
          @isset($submenu->badge)
            <div class="badge rounded-pill bg-{{ $submenu->badge[0] }} text-uppercase ms-auto">{{ $submenu->badge[1] }}</div>
          @endisset
        </a>

        {{-- submenu --}}
        @if (isset($submenu->submenu))
          @include('layouts.sections.menu.submenu',['menu' => $submenu->submenu])
        @endif
      </li>
    @endforeach
  @endif
</ul>
