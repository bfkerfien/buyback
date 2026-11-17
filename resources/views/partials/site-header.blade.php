@php
    $siteCharacter = auth()->check() ? auth()->user()->characters()->orderByDesc('last_authenticated_at')->first() : null;
@endphp

<div class="evaluation-banner" role="status" aria-label="Evaluation mode notice">
    <div class="evaluation-banner-track">
        <span>EVALUATION MODE: SOME APPRAISALS MAY BE INCORRECT — WE WILL EMAIL YOU IF YOURS IS INCORRECT. SORRY FOR THE INCONVENIENCE.</span>
        <span aria-hidden="true">EVALUATION MODE: SOME APPRAISALS MAY BE INCORRECT — WE WILL EMAIL YOU IF YOURS IS INCORRECT. SORRY FOR THE INCONVENIENCE.</span>
    </div>
</div>

<header class="site-header">
    <a class="site-brand" href="{{ auth()->check() ? route('dashboard') : route('home') }}">
        <img class="eb-logo" src="{{ asset('evebuyback-logo.png') }}" alt="EveBuyback">
    </a>
    <div class="site-user-controls">
        @auth
            <div class="site-character">
                @if($siteCharacter)
                    <img class="site-player-icon" src="https://images.evetech.net/characters/{{ $siteCharacter->eve_character_id }}/portrait?size=64" alt="{{ $siteCharacter->name }}">
                    <span class="site-account">{{ $siteCharacter->name }}</span>
                @else
                    <span class="site-player-icon site-player-placeholder" aria-label="Player account">♙</span>
                @endif
                <a class="site-link-character" href="{{ route('login.eve.link') }}">Link characters</a>
            </div>
            <form method="post" action="{{ route('logout') }}" class="site-logout">@csrf<button type="submit">Sign out</button></form>
        @else
            <a class="site-login" href="{{ route('login.eve') }}">Sign in with EVE</a>
        @endauth
    </div>
</header>

<nav class="site-nav" aria-label="Main site navigation">
    <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
    @auth
        <a href="{{ route('dashboard', ['tab' => 'tab-appraisals']) }}" @if(request()->routeIs('dashboard') && request('tab', 'tab-appraisals') === 'tab-appraisals') aria-current="page" @endif>New Appraisals</a>
        <a href="{{ route('dashboard', ['tab' => 'tab-contracts']) }}" @if(request()->routeIs('dashboard') && request('tab') === 'tab-contracts') aria-current="page" @endif>Appraisals &amp; Contracts</a>
        <a href="{{ route('dashboard', ['tab' => 'tab-how-to']) }}" @if(request()->routeIs('dashboard') && request('tab') === 'tab-how-to') aria-current="page" @endif>How To</a>
    @endauth
    <a href="{{ route('ship-buyback.index') }}" @if(request()->routeIs('ship-buyback.*')) aria-current="page" @endif>Ship Buyback</a>
</nav>

@auth
    @if(auth()->user()->isAdmin())
        <nav class="site-nav site-admin-nav" aria-label="Administration navigation">
            <a href="{{ route('pi-planner.index') }}" @if(request()->routeIs('pi-planner.*')) aria-current="page" @endif>Planetary Industry</a>
            <a href="{{ route('dashboard', ['tab' => 'tab-ore-refining']) }}">Ore Refining</a>
            <a href="{{ route('dashboard', ['tab' => 'tab-marketbaiters']) }}">Marketbaiters</a>
            <a href="{{ route('dashboard', ['tab' => 'tab-admin']) }}">Admin</a>
            <a href="{{ route('dashboard', ['tab' => 'tab-admin-tools']) }}">Admin Tools</a>
        </nav>
    @endif
@endauth

<style>
.evaluation-banner{display:flex;align-items:center;overflow:hidden;min-height:48px;margin:0 0 14px;padding:0 14px;background:#ffd84d;color:#111;border:1px solid #e4b923;border-radius:9px;font-size:15px;font-weight:900;letter-spacing:.025em}
.evaluation-banner-track{display:flex;width:max-content;min-width:100%;animation:evaluation-banner-scroll 34s linear infinite}
.evaluation-banner span{display:block;flex:0 0 auto;padding:0 3rem;white-space:nowrap}
.evaluation-banner:hover .evaluation-banner-track{animation-play-state:paused}
@keyframes evaluation-banner-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media(prefers-reduced-motion:reduce){.evaluation-banner-track{animation:none}.evaluation-banner span+span{display:none}.evaluation-banner{justify-content:center;text-align:center}.evaluation-banner span{white-space:normal;padding:10px 4px}}
.site-header{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:10px 16px;margin:0 0 10px;background:rgba(7,19,27,.94);border:1px solid #315665;border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,.28);position:relative;z-index:40}
.site-brand{display:block;flex:0 1 250px}.site-header .eb-logo{display:block;width:250px;max-width:100%;height:auto}
.site-user-controls{display:flex;align-items:center;gap:16px}.site-character{display:flex;flex-direction:column;align-items:center;gap:4px;min-width:76px}
.site-player-icon{display:block;width:42px;height:42px;object-fit:cover;border-radius:50%;border:2px solid #62d9e9;background:#102630}
.site-player-placeholder{display:grid;place-items:center;font-size:26px;color:#dffaff}
.site-account{font-size:11px;color:#c5dbe0;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.site-link-character{font-size:11px;text-decoration:underline;color:#62d9e9!important;white-space:nowrap}
.site-logout{margin:0}.site-logout button,.site-login{padding:9px 13px;font-size:12px;font-weight:700}
.site-nav{display:flex;align-items:center;justify-content:flex-start;gap:8px;flex-wrap:wrap;padding:9px 12px;margin:0 0 10px;background:rgba(7,19,27,.78);border:1px solid #294a56;border-radius:11px}
.site-nav>a{display:inline-flex;align-items:center;padding:9px 12px;border:1px solid #315665;border-radius:8px;text-decoration:none;color:#dffaff!important;font-size:13px;font-weight:700;background:#102630}
.site-nav>a:hover,.site-nav>a[aria-current="page"]{border-color:#62d9e9;background:#163440}
.site-admin-nav{background:rgba(10,27,36,.88);border-color:rgba(255,155,69,.42);margin-bottom:20px}
.site-admin-nav>a[aria-current="page"]{border-color:#ffad58;background:linear-gradient(110deg,#62d9e9,#ffad58);color:#071018!important}
@media(max-width:700px){.site-header{align-items:center}.site-brand{flex-basis:180px}.site-header .eb-logo{width:180px}.site-nav{gap:6px}.site-nav>a{padding:8px 9px;font-size:12px}}
</style>
