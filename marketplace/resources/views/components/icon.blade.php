@props(['name'])
{{-- Satır içi SVG ikon seti (24x24, stroke). Harici ikon kütüphanesine bağımlılık yok. --}}
<svg {{ $attributes->merge(['class' => 'size-5', 'aria-hidden' => 'true']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    @switch($name)
        @case('search') <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/> @break
        @case('sun') <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/> @break
        @case('moon') <path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5Z"/> @break
        @case('wallet') <path d="M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v2"/><path d="M3 7v11a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-3"/><path d="M21 12h-5a2 2 0 0 0 0 4h5v-4Z"/> @break
        @case('upload') <path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/> @break
        @case('user') <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/> @break
        @case('menu') <path d="M4 7h16M4 12h16M4 17h16"/> @break
        @case('x') <path d="M6 6l12 12M18 6 6 18"/> @break
        @case('chevron-down') <path d="m6 9 6 6 6-6"/> @break
        @case('arrow-right') <path d="M5 12h14M13 6l6 6-6 6"/> @break
        @case('layers') <path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/> @break
        @case('shield') <path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-3Z"/><path d="m9 12 2 2 4-4"/> @break
        @case('coins') <ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v5c0 1.7 2.7 3 6 3s6-1.3 6-3V7"/><path d="M15 11.5c3.3 0 6 1.3 6 3s-2.7 3-6 3-6-1.3-6-3"/><path d="M9 15v2.5c0 1.7 2.7 3 6 3s6-1.3 6-3V14"/> @break
        @case('globe') <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/> @break
        @case('cube') <path d="m12 2 9 5v10l-9 5-9-5V7l9-5Z"/><path d="m3 7 9 5 9-5M12 12v10"/> @break
        @case('heart') <path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/> @break
        @case('download') <path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 20h16"/> @break
        @case('sparkles') <path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2 2M16 16l2 2M6 18l2-2M16 8l2-2"/> @break
        @case('check') <path d="m5 12 5 5 9-10"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/> @break
        @case('store') <path d="M4 10v10h16V10"/><path d="M3 10 5 4h14l2 6a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0Z"/> @break
        @case('logout') <path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 17l-5-5 5-5M5 12h11"/> @break
        @default <circle cx="12" cy="12" r="9"/>
    @endswitch
</svg>
