<?php
/**
 * auth-layout.php
 * Layout partagé pour les pages d'authentification (login, register).
 * Hors du layout principal — pas de nav, fond dégradé centré.
 *
 * Usage :
 *   require_once 'auth-layout.php';
 *   renderAuthHeader('Connexion');   // <head> + ouverture card
 *   // ... contenu de la page ...
 *   renderAuthFooter();              // fermeture card + </html>
 */

function renderAuthHeader(string $page_title = 'ANYTECH Hotspot'): void
{ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — ANYTECH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ── Reset ── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        /* ── Fonts landing ── */
        @import url('https://fonts.googleapis.com/css2?family=Syne:wght@700;800&display=swap');

        /* ── Dark tokens ── */
        :root {
            --black:         #080a0f;
            --black-2:       #0d1017;
            --black-3:       #141820;
            --black-4:       #1c2230;
            --green:         #00e676;
            --green-bg:      rgba(0,230,118,0.07);
            --green-border:  rgba(0,230,118,0.2);
            --white:         #f0f2f7;
            --white-dim:     #9aa3b8;
            --white-muted:   #5a6278;
            --card-border:   rgba(255,255,255,0.08);

            --radius-sm:   8px;
            --radius-md:   12px;
            --radius-lg:   16px;
            --radius-xl:   24px;
            --radius-full: 9999px;
            --transition:  all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ── Page background ── */
        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            background: var(--black);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            -webkit-font-smoothing: antialiased;
            position: relative;
            overflow-x: hidden;
            color: var(--white);
        }

        /* Grille verte identique à la landing */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(0,230,118,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,230,118,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 20%, transparent 100%);
            pointer-events: none;
            z-index: 0;
        }

        /* Glow vert centré */
        body::after {
            content: '';
            position: fixed;
            top: -200px; left: 50%;
            transform: translateX(-50%);
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(0,230,118,0.08) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* Lien retour landing */
        .auth-back {
            position: fixed;
            top: 20px; left: 24px;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--white-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .auth-back:hover { color: var(--white); }

        /* ── Auth card ── */
        .auth-card {
            background: var(--black-2);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 460px;
            padding: 44px 40px;
            position: relative;
            z-index: 1;
        }

        /* ── Brand logo ── */
        .auth-brand {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-brand-icon {
            width: 52px;
            height: 52px;
            background: var(--green-bg);
            border: 1px solid var(--green-border);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 14px;
        }

        .auth-brand-name {
            font-family: 'Syne', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--white);
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .auth-brand-sub {
            font-size: 14px;
            color: var(--white-muted);
        }

        /* ── Divider ── */
        .auth-divider {
            height: 1px;
            background: var(--card-border);
            margin: 24px 0;
        }

        /* ── Form elements ── */
        .form-group { margin-bottom: 18px; }

        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--white-dim);
            margin-bottom: 7px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"] {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: var(--radius-md);
            font-size: 14.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--white);
            background: var(--black-3);
            transition: var(--transition);
        }

        input:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(0,230,118,0.1);
        }

        input::placeholder { color: var(--white-muted); }

        /* ── Submit button ── */
        .btn-auth {
            width: 100%;
            padding: 13px;
            background: var(--green);
            color: var(--black);
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: 'Syne', sans-serif;
            letter-spacing: 0.2px;
            margin-top: 4px;
        }

        .btn-auth:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,230,118,0.25);
        }

        .btn-auth:active  { transform: translateY(0); }
        .btn-auth:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* ── Alerts ── */
        .auth-alert {
            padding: 11px 14px;
            border-radius: var(--radius-md);
            margin-bottom: 18px;
            font-size: 13.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            line-height: 1.5;
        }

        .auth-alert.success {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.25);
            color: #6ee7b7;
        }

        .auth-alert.error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5;
        }

        .auth-alert.info {
            background: rgba(59,130,246,0.1);
            border: 1px solid rgba(59,130,246,0.25);
            color: #93c5fd;
        }

        /* ── Footer link ── */
        .auth-footer-link {
            text-align: center;
            margin-top: 22px;
            font-size: 13.5px;
            color: var(--white-muted);
        }

        .auth-footer-link a {
            color: var(--green);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer-link a:hover { text-decoration: underline; }

        /* ── Forgot password ── */
        .forgot-link {
            display: block;
            text-align: right;
            margin-top: 6px;
            font-size: 12.5px;
            color: var(--white-muted);
            text-decoration: none;
            transition: var(--transition);
        }
        .forgot-link:hover { color: var(--green); }

        /* ── Info box ── */
        .auth-info-box {
            background: var(--green-bg);
            border: 1px solid var(--green-border);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            font-size: 13.5px;
            color: #a7f3d0;
            line-height: 1.65;
            margin-bottom: 18px;
        }

        .auth-info-box strong { color: var(--green); }
        .auth-info-box ul { margin: 8px 0 0 16px; }
        .auth-info-box ul li { margin-bottom: 3px; }

        /* ── Spinner ── */
        .btn-spinner {
            display: inline-block;
            width: 15px; height: 15px;
            border: 2px solid rgba(0,0,0,0.3);
            border-top-color: var(--black);
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            vertical-align: middle;
            margin-right: 6px;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Responsive ── */
        @media (max-width: 500px) {
            .auth-card { padding: 32px 22px; }
            .auth-brand-name { font-size: 20px; }
        }
    </style>
</head>
<body>

<a href="index.php" class="auth-back">← Retour à l'accueil</a>

<div class="auth-card">

    <!-- Brand -->
    <div class="auth-brand">
        <div class="auth-brand-icon">📡</div>
        <div class="auth-brand-name">ANYTECH Hotspot</div>
        <div class="auth-brand-sub"><?php echo htmlspecialchars($page_title); ?></div>
    </div>

<?php
} // end renderAuthHeader()


function renderAuthFooter(): void
{ ?>

</div><!-- /.auth-card -->
</body>
</html>
<?php
} // end renderAuthFooter()
?>