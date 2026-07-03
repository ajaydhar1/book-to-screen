<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Book to Screen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f6f3ee;
            color: #222;
        }

        main {
            max-width: 760px;
            margin: 0 auto;
            padding: 80px 24px;
        }

        .eyebrow {
            margin: 0;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 13px;
            font-weight: 700;
            color: #7a5c3e;
        }

        h1 {
            margin: 12px 0 20px;
            font-size: 52px;
            line-height: 1.1;
        }

        .lede {
            font-size: 20px;
            line-height: 1.7;
            color: #444;
            margin-bottom: 36px;
        }

        .card {
            background: white;
            border: 1px solid #e3d8c8;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(0,0,0,.05);
        }

        .card h2 {
            margin-top: 0;
        }

        ul {
            line-height: 1.9;
            padding-left: 22px;
        }

        .button {
            display: inline-block;
            margin-top: 26px;
            padding: 12px 18px;
            background: #2b2118;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
        }

        footer {
            margin-top: 50px;
            color: #777;
            font-size: 14px;
        }
    </style>
</head>

<body>

<main>

    <p class="eyebrow">Prototype</p>

    <h1>Book to Screen</h1>

    <p class="lede">
        Discover upcoming book-to-screen adaptations through an editorial workflow
        that combines automated news monitoring with human review.
    </p>

    <section class="card">

        <h2>Current Progress</h2>

        <ul>
            <li>✓ SQLite database architecture</li>
            <li>✓ Editorial leads workflow</li>
            <li>✓ Admin dashboard prototype</li>
            <li>⏳ Automated RSS ingestion</li>
            <li>⏳ Public adaptations catalog</li>
        </ul>

        <a class="button" href="/admin/leads.php">
            Open Admin Dashboard →
        </a>

    </section>

    <footer>
        Early development prototype.
    </footer>

</main>

</body>
</html>