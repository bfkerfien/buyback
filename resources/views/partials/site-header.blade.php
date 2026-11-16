@php
    $siteCharacter = auth()->check() ? auth()->user()->characters()->orderByDesc('last_authenticated_at')->first() : null;
@endphp
<header class="site-header">
    <a class="site-brand" href="{{ auth()->check() ? route('dashboard') : route('home') }}">
        <img class="eb-logo" src="{{ asset('evebuyback-logo.png') }}" alt="EveBuyback">
    </a>
    <nav class="site-nav" aria-label="Main site navigation">
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('ship-buyback.index') }}">Ship Buyback</a>
        @auth
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a href="{{ route('pi-planner.index') }}">Planetary Industry</a>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('dashboard', ['tab' => 'tab-ore-refining']) }}">Ore Refining</a>
                <a href="{{ route('dashboard', ['tab' => 'tab-marketbaiters']) }}">Marketbaiters</a>
                <a href="{{ route('dashboard', ['tab' => 'tab-admin']) }}">Admin</a>
            @endif
            @if($siteCharacter)
                <span class="site-account">{{ $siteCharacter->name }}</span>
            @endif
            <form method="post" action="{{ route('logout') }}" class="site-logout">@csrf<button type="submit">Sign out</button></form>
        @else
            <a href="{{ route('login.eve') }}">Sign in with EVE</a>
        @endauth
    </nav>
</header>
