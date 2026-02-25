<?php
/**
 * 404.php — Page introuvable
 * À configurer dans .htaccess : ErrorDocument 404 /404.php
 */
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page introuvable — ANYTECH Hotspot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            background: linear-gradient(135deg, #5b5ef4 0%, #8b5cf6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.2);
            max-width: 480px;
            width: 100%;
            padding: 56px 48px;
            text-align: center;
        }

        .code {
            font-family: 'DM Mono', monospace;
            font-size: 88px;
            font-weight: 500;
            background: linear-gradient(135deg, #5b5ef4, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 8px;
        }

        .emoji { font-size: 40px; margin-bottom: 16px; }

        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #0f1023;
            margin-bottom: 10px;
            letter-spacing: -0.3px;
        }

        p {
            font-size: 15px;
            color: #5a607a;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 11px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5b5ef4, #8b5cf6);
            color: white;
            box-shadow: 0 4px 16px rgba(91,94,244,0.3);
        }

        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(91,94,244,0.4); }

        .btn-secondary {
            background: #f7f8fc;
            color: #5a607a;
            border: 1px solid #e4e7f0;
        }

        .btn-secondary:hover { background: #eef0f8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="emoji">🛸</div>
        <div class="code">404</div>
        <h1>Page introuvable</h1>
        <p>La page que vous recherchez n'existe pas ou a été déplacée.<br>Retournez au tableau de bord.</p>
        <div class="actions">
            <a href="dashboard.php" class="btn btn-primary">📊 Dashboard</a>
            <a href="javascript:history.back()" class="btn btn-secondary">← Retour</a>
        </div>
    </div>
</body>
</html>
