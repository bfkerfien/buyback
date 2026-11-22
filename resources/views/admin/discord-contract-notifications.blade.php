<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EveBuyback · Discord Contract Alerts</title>
    <style>
        :root{color-scheme:dark;font-family:Inter,system-ui,sans-serif;background:#071018;color:#eaf7f8}
        *{box-sizing:border-box}
        body{margin:0;padding:24px;background:radial-gradient(ellipse at 50% -10%,#0d5967 0,transparent 52%),#060d13;min-height:100vh}
        main{max-width:1050px;margin:auto}
        .panel{background:#101e27;border:1px solid #294a56;border-radius:14px;padding:22px;margin:18px 0}
        h1,h2{color:#f1fbfc}h1{font-size:28px}h2{font-size:19px;margin-top:0}
        .muted{color:#9bb5bf;font-size:13px;line-height:1.7}
        .status{padding:12px 14px;border-radius:9px;background:#09151c;border:1px solid #315665;margin:12px 0}
        .good{color:#8de4a8}.warn{color:#ffb45f}.bad{color:#ff927c}
        input{width:100%;background:#09151c;color:#eaf7f8;border:1px solid #315665;border-radius:8px;padding:12px;margin:7px 0 14px}
        button{background:#ff9b45;color:#111820;border:0;border-radius:8px;padding:11px 15px;font-weight:800;cursor:pointer}
        .secondary{background:#17313c;color:#dffaff;border:1px solid #315665}
        a{color:#62d9e9}
        .actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .success{color:#8de4a8}.errors{color:#ff927c}
        code{color:#9deaf0}
    </style>
</head>
<body>
<main>
    @include('partials.site-header')
    <h1>Discord contract notifications</h1>
    <p class="muted">Receive a Discord ping when the personal EVE character named <strong>My Eve Online</strong> receives a new in-game contract. This monitor uses the personal character contracts endpoint only. Corporation contract endpoints and corporation contract notifications are not used.</p>

    @if(session('status'))<p class="success">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="panel errors">{{ $errors->first() }}</div>@endif

    <section class="panel">
        <h2>Monitored EVE character</h2>
        @if($character)
            <div class="status">
                <strong>{{ $character->name }}</strong>
                <span class="good"> · Linked</span>
                <div class="muted">EVE character ID: {{ $character->eve_character_id }}</div>
                @if($hasContractScope)
                    <div class="good">Contract permission is present.</div>
                @else
                    <div class="warn">Missing permission: <code>esi-contracts.read_character_contracts.v1</code></div>
                @endif
            </div>
        @else
            <div class="status">
                <span class="bad">My Eve Online is not linked to this Buyback installation.</span>
                <p class="muted">Use Link characters and complete EVE SSO as the personal character named exactly My Eve Online. The monitor cannot read a character's contracts until that character is linked and authorized.</p>
            </div>
        @endif
        <div class="actions">
            <a href="{{ route('login.eve.link') }}">Link / re-authorize an EVE character</a>
            <span class="muted">If My Eve Online is linked to another Buyback account, sign in to that account as My Eve Online instead.</span>
        </div>
    </section>

    <section class="panel">
        <h2>Discord webhook</h2>
        <p class="muted">Create an incoming webhook in the Discord channel where you want alerts. Paste its URL below and save it. The URL is encrypted before it is stored. It is never displayed again or committed to GitHub.</p>
        <div class="status">
            Webhook status:
            @if($webhookConfigured)
                <span class="good">Configured</span>
            @else
                <span class="warn">Not configured</span>
            @endif
            @if($monitorStartedAt)
                <div class="muted">Monitoring baseline: {{ \Illuminate\Support\Carbon::parse($monitorStartedAt)->timezone(config('app.timezone'))->format('M j, Y g:i:s A T') }}</div>
                <div class="muted">The first successful poll after saving a webhook snapshots existing contracts without alerts. Contracts appearing in later polls trigger alerts even if their original issue date is older.</div>
            @endif
        </div>

        <form method="post" action="{{ route('admin.discord-contract-notifications.save') }}">
            @csrf
            <label for="webhook_url">Discord incoming webhook URL</label>
            <input id="webhook_url" name="webhook_url" type="url" autocomplete="off" placeholder="https://discord.com/api/webhooks/..." required>
            <div class="actions">
                <button type="submit">Save Discord webhook</button>
            </div>
        </form>

        <div class="actions" style="margin-top:12px">
            <form method="post" action="{{ route('admin.discord-contract-notifications.test') }}">
                @csrf
                <button class="secondary" type="submit" @if(!$webhookConfigured) disabled @endif>Send test notification</button>
            </form>
            @if($webhookConfigured)
                <form method="post" action="{{ route('admin.discord-contract-notifications.save') }}" onsubmit="return confirm('Disable Discord contract notifications?')">
                    @csrf
                    <input type="hidden" name="clear_webhook" value="1">
                    <button class="secondary" type="submit">Disable notifications</button>
                </form>
            @endif
        </div>
    </section>

    <section class="panel">
        <h2>New appraisal notifications</h2>
        <p class="muted">Use a separate Discord incoming webhook for appraisals. Each new inventory or ship appraisal sends a message with the character (when signed in), estimated buyback amount, origin, item count, and a link to the appraisal.</p>
        <div class="status">
            Appraisal webhook:
            @if($appraisalWebhookConfigured)
                <span class="good">Configured</span>
            @else
                <span class="warn">Not configured</span>
            @endif
            <div class="muted">The webhook URL is encrypted in the database and is never displayed again.</div>
        </div>

        <form method="post" action="{{ route('admin.discord-contract-notifications.appraisals.save') }}">
            @csrf
            <label for="appraisal_webhook_url">Discord appraisal webhook URL</label>
            <input id="appraisal_webhook_url" name="webhook_url" type="url" autocomplete="off" placeholder="https://discord.com/api/webhooks/..." required>
            <div class="actions">
                <button type="submit">Save appraisal webhook</button>
            </div>
        </form>

        <div class="actions" style="margin-top:12px">
            <form method="post" action="{{ route('admin.discord-contract-notifications.appraisals.test') }}">
                @csrf
                <button class="secondary" type="submit" @if(!$appraisalWebhookConfigured) disabled @endif>Send appraisal test</button>
            </form>
            @if($appraisalWebhookConfigured)
                <form method="post" action="{{ route('admin.discord-contract-notifications.appraisals.save') }}" onsubmit="return confirm('Disable Discord appraisal notifications?')">
                    @csrf
                    <input type="hidden" name="clear_webhook" value="1">
                    <button class="secondary" type="submit">Disable appraisal alerts</button>
                </form>
            @endif
        </div>
    </section>

    <section class="panel">
        <h2>How monitoring works</h2>
        <ul class="muted">
            <li>The scheduler checks every two minutes.</li>
            <li>Only personal contracts where My Eve Online is the assignee or acceptor are considered; contracts issued by My Eve Online are excluded.</li>
            <li>Each contract ID is recorded so successful notifications are not repeated.</li>
            <li>Failed Discord deliveries are retried on a later poll.</li>
            <li>The normal dashboard request does not perform any contract polling.</li>
        </ul>
    </section>
</main>
</body>
</html>
