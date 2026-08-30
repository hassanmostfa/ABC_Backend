<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>404 Not Found</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #0b0d12;
            --card: #141821;
            --line: rgba(255, 255, 255, 0.08);
            --text: #f4f6fb;
            --muted: #9aa3b5;
            --red: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at top, rgba(239, 68, 68, 0.14), transparent 38%),
                radial-gradient(circle at 80% 20%, rgba(14, 165, 233, 0.08), transparent 28%),
                var(--bg);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
        }

        main {
            width: min(100%, 520px);
            padding: 48px 32px 40px;
            border: 1px solid var(--line);
            border-radius: 24px;
            background: color-mix(in srgb, var(--card) 88%, transparent);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
            text-align: center;
        }

        .icon {
            width: 88px;
            height: 88px;
            margin: 0 auto 22px;
            display: grid;
            place-items: center;
            border-radius: 22px;
            border: 1px solid rgba(239, 68, 68, 0.22);
            background: rgba(239, 68, 68, 0.08);
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.12);
        }

        .icon svg {
            width: 48px;
            height: 48px;
        }

        .code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            padding: 8px 14px;
            margin-bottom: 18px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
            color: var(--muted);
            font-size: 13px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        h1 {
            font-size: clamp(2.4rem, 8vw, 4.4rem);
            line-height: 1;
            letter-spacing: -0.06em;
            font-weight: 700;
        }

        .red {
            color: var(--red);
        }

        p {
            margin-top: 14px;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <main>
        <div class="icon" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M18 8h8l4 4h8a4 4 0 0 1 4 4v20a4 4 0 0 1-4 4H10a4 4 0 0 1-4-4V12a4 4 0 0 1 4-4h8Z" stroke="#ef4444" stroke-width="2.4" stroke-linejoin="round"/>
                <path d="M18 8v5a1 1 0 0 0 1 1h7" stroke="#ef4444" stroke-width="2.4" stroke-linejoin="round"/>
                <circle cx="22" cy="26" r="5.5" stroke="#ef4444" stroke-width="2.4"/>
                <path d="m26 30 6 6" stroke="#ef4444" stroke-width="2.4" stroke-linecap="round"/>
                <path d="M20 26h4M22 24v4" stroke="#9aa3b5" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="code">Error 404</div>
        <h1>
            <span class="red">N</span>o<span class="red">t</span>
            Fo<span class="red">un</span>d
        </h1>
        <p>The page you are looking for does not exist or has been moved.</p>
    </main>
</body>
</html>
