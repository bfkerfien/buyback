<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }}</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #0b1018; color: #e8edf5; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        main { width: min(760px, 100%); border: 1px solid #273448; border-radius: 18px; padding: clamp(28px, 6vw, 56px); background: #111a27; box-shadow: 0 24px 80px #0008; }
        .eyebrow { color: #8ca8c9; text-transform: uppercase; letter-spacing: .16em; font-size: 12px; font-weight: 700; }
        h1 { font-size: clamp(32px, 6vw, 54px); margin: 16px 0; letter-spacing: -.04em; }
        p { color: #aab8ca; line-height: 1.7; }
        .status { display: inline-flex; align-items: center; gap: 9px; border: 1px solid #285d50; color: #9ce2c8; border-radius: 999px; padding: 8px 13px; font-size: 13px; margin-top: 18px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #55d6a7; }
        code { color: #c8d8ef; }
    </style>
</head>
<body>
<main>
    <div class="eyebrow">Triglavian Consortium · Logistics</div>
    <h1>{{ $appName }}</h1>
    <p>Independent buyback, runner operations, and financier platform for EVE Online. This service is being built as a standalone application and does not depend on SeAT.</p>
    <div class="status"><span class="dot"></span> Application foundation online</div>
    <p style="margin-top:28px">Service health: <code>/health</code></p>
</main>
</body>
</html>
