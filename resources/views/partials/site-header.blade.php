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
<style>
.evaluation-banner{display:flex;align-items:center;overflow:hidden;min-height:56px;margin:0 0 22px;padding:0 16px;background:#ffd84d;color:#111;border:1px solid #e4b923;border-radius:10px;box-shadow:0 5px 18px rgba(0,0,0,.18);font-size:16px;font-weight:900;letter-spacing:.035em}
.evaluation-banner-track{display:flex;width:max-content;min-width:100%;animation:evaluation-banner-scroll 34s linear infinite}
.evaluation-banner span{display:block;flex:0 0 auto;padding:0 3rem;white-space:nowrap}
.evaluation-banner:hover .evaluation-banner-track{animation-play-state:paused}
@keyframes evaluation-banner-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media(prefers-reduced-motion:reduce){.evaluation-banner-track{animation:none}.evaluation-banner span+span{display:none}.evaluation-banner{justify-content:center;text-align:center}.evaluation-banner span{white-space:normal;padding:12px 4px}}
@media(max-width:600px){.evaluation-banner{font-size:14px;min-height:52px}}
</style>
<div class="evaluation-banner" role="status" aria-label="Evaluation mode notice">
    <div class="evaluation-banner-track">
        <span>EVALUATION MODE: SOME APPRAISALS MAY BE INCORRECT — WE WILL EMAIL YOU IF YOURS IS INCORRECT. SORRY FOR THE INCONVENIENCE.</span>
        <span aria-hidden="true">EVALUATION MODE: SOME APPRAISALS MAY BE INCORRECT — WE WILL EMAIL YOU IF YOURS IS INCORRECT. SORRY FOR THE INCONVENIENCE.</span>
    </div>
</div>
