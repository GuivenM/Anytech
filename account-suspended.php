<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte suspendu — ANYTECH Hotspot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-from: #5b5ef4;
            --brand-to:   #8b5cf6;
            --danger:     #ef4444;
            --danger-bg:  #fef2f2;
            --surface:    #ffffff;
            --border:     #e4e7f0;
            --text-primary:   #0f1023;
            --text-secondary: #5a607a;
            --text-muted:     #9399b2;
        }

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
            max-width: 520px;
            width: 100%;
            overflow: hidden;
        }

        .card-top {
            background: var(--danger-bg);
            border-bottom: 1px solid #fecaca;
            padding: 40px 40px 32px;
            text-align: center;
        }

        .icon-wrap {
            width: 80px;
            height: 80px;
            background: white;
            border: 3px solid #fecaca;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
            box-shadow: 0 4px 16px rgba(239,68,68,0.15);
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            color: #991b1b;
            margin-bottom: 8px;
            letter-spacing: -0.4px;
        }

        .subtitle {
            font-size: 14px;
            color: #b91c1c;
            opacity: 0.8;
        }

        .card-body { padding: 32px 40px; }

        p {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 24px;
        }

        .contact-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 28px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 16px;
            background: #f7f8fc;
            border: 1px solid #e4e7f0;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .contact-item:hover {
            border-color: var(--brand-from);
            background: #ede9fe;
            color: var(--brand-from);
        }

        .contact-icon {
            width: 36px;
            height: 36px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            border: 1px solid #e4e7f0;
        }

        .divider {
            height: 1px;
            background: #e4e7f0;
            margin: 24px 0;
        }

        .logout-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .logout-link:hover { color: var(--danger); }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-top">
            <div class="icon-wrap">🔒</div>
            <h1>Compte suspendu</h1>
            <p class="subtitle">Votre accès a été temporairement désactivé</p>
        </div>
        <div class="card-body">
            <p>
                Votre compte ANYTECH Hotspot a été suspendu. Cela peut être dû à un impayé,
                une violation des conditions d'utilisation, ou une demande de votre part.
                Contactez notre équipe support pour régulariser votre situation.
            </p>

            <div class="contact-options">
                <a href="tel:0194601012" class="contact-item">
                    <div class="contact-icon">📞</div>
                    <div>
                        <div style="font-weight:600">Appeler le support</div>
                        <div style="font-size:13px;color:#9399b2">0194601012 — Lun–Sam 8h–18h</div>
                    </div>
                </a>
                <a href="mailto:support@anytech.bj" class="contact-item">
                    <div class="contact-icon">✉️</div>
                    <div>
                        <div style="font-weight:600">Envoyer un email</div>
                        <div style="font-size:13px;color:#9399b2">support@anytech.bj</div>
                    </div>
                </a>
                <a href="https://wa.me/22901946010" target="_blank" class="contact-item">
                    <div class="contact-icon">💬</div>
                    <div>
                        <div style="font-weight:600">WhatsApp</div>
                        <div style="font-size:13px;color:#9399b2">Message direct</div>
                    </div>
                </a>
            </div>

            <div class="divider"></div>

            <a href="logout.php" class="logout-link">
                🚪 Se déconnecter
            </a>
        </div>
    </div>
</body>
</html>
