<?
if(isset($_SESSION['logged_in'])) {
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ANYTECH — Hotspot Manager pour le Bénin</title>
    <meta name="description" content="Gérez votre WiFi public en conformité avec l'ARCEP Bénin. Identification des utilisateurs, logs de sessions, tableau de bord simple.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500&family=Manrope:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --black:    #080a0f;
            --black-2:  #0d1017;
            --black-3:  #141820;
            --black-4:  #1c2230;
            --green:    #00e676;
            --green-dim:#00c853;
            --green-bg: rgba(0,230,118,0.06);
            --green-border: rgba(0,230,118,0.18);
            --white:    #f0f2f7;
            --white-dim:#9aa3b8;
            --white-muted: #5a6278;
            --border:   rgba(255,255,255,0.07);
            --radius:   12px;
            --radius-lg:20px;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--black);
            color: var(--white);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ---- NOISE OVERLAY ---- */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
            opacity: 0.4;
        }

        /* ---- NAV ---- */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            padding: 0 48px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            background: rgba(8,10,15,0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-logo {
            width: 34px; height: 34px;
            background: var(--green);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
        }

        .nav-name {
            font-family: 'Syne', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: var(--white);
            letter-spacing: -0.5px;
        }

        .nav-links { display: flex; align-items: center; gap: 8px; }

        .nav-link {
            padding: 8px 16px;
            border-radius: var(--radius);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            color: var(--white-dim);
            transition: color 0.2s;
        }
        .nav-link:hover { color: var(--white); }

        .nav-cta {
            padding: 9px 22px;
            background: var(--green);
            color: var(--black);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            font-family: 'Syne', sans-serif;
        }
        .nav-cta:hover { background: #fff; transform: translateY(-1px); }

        /* ---- HERO ---- */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 120px 48px 80px;
            overflow: hidden;
        }

        /* Grille de fond */
        .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(0,230,118,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,230,118,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 20%, transparent 100%);
        }

        /* Glow vert en haut à droite */
        .hero-glow {
            position: absolute;
            top: -200px; right: -200px;
            width: 700px; height: 700px;
            background: radial-gradient(circle, rgba(0,230,118,0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--green-bg);
            border: 1px solid var(--green-border);
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            color: var(--green);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 28px;
            font-family: 'IBM Plex Mono', monospace;
        }

        .hero-badge-dot {
            width: 6px; height: 6px;
            background: var(--green);
            border-radius: 50%;
            animation: pulse 2s ease infinite;
        }

        @keyframes pulse {
            0%,100% { opacity:1; transform:scale(1); }
            50%      { opacity:0.4; transform:scale(0.7); }
        }

        h1 {
            font-family: 'Syne', sans-serif;
            font-size: clamp(42px, 5vw, 68px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -2px;
            color: var(--white);
            margin-bottom: 24px;
        }

        h1 .accent { color: var(--green); }

        .hero-sub {
            font-size: 17px;
            line-height: 1.7;
            color: var(--white-dim);
            font-weight: 400;
            margin-bottom: 40px;
            max-width: 480px;
        }

        .hero-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .btn-primary {
            padding: 14px 30px;
            background: var(--green);
            color: var(--black);
            border-radius: var(--radius);
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            font-family: 'Syne', sans-serif;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,230,118,0.25); }

        .btn-ghost {
            padding: 14px 30px;
            background: transparent;
            color: var(--white-dim);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-ghost:hover { color: var(--white); border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.04); }

        /* Dashboard mockup */
        .hero-visual {
            position: relative;
        }

        .mockup {
            background: var(--black-3);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 40px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);
        }

        .mockup-bar {
            background: var(--black-4);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid var(--border);
        }

        .mockup-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
        }

        .mockup-body { padding: 20px; }

        .mock-stat-row {
            display: grid;
            grid-template-columns: repeat(3,1fr);
            gap: 10px;
            margin-bottom: 16px;
        }

        .mock-stat {
            background: var(--black-4);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
        }

        .mock-stat-val {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 22px;
            font-weight: 500;
            color: var(--green);
        }

        .mock-stat-lbl {
            font-size: 11px;
            color: var(--white-muted);
            margin-top: 4px;
        }

        .mock-table-head {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 8px;
            padding: 8px 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--white-muted);
            background: var(--black-4);
            border-radius: 8px 8px 0 0;
            border: 1px solid var(--border);
            border-bottom: none;
        }

        .mock-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 8px;
            padding: 10px 12px;
            font-size: 12px;
            border: 1px solid var(--border);
            border-top: none;
            align-items: center;
            animation: fadeRow 0.5s ease both;
        }

        .mock-row:last-child { border-radius: 0 0 8px 8px; }

        @keyframes fadeRow {
            from { opacity:0; transform:translateX(-8px); }
            to   { opacity:1; transform:translateX(0); }
        }

        .mock-row:nth-child(2) { animation-delay:0.1s; }
        .mock-row:nth-child(3) { animation-delay:0.2s; }
        .mock-row:nth-child(4) { animation-delay:0.3s; }

        .mock-name { font-weight:600; font-size:12px; color:var(--white); }
        .mock-sub  { font-size:10px; color:var(--white-muted); margin-top:2px; font-family:'IBM Plex Mono',monospace; }
        .mock-badge-green {
            display:inline-block;
            background:var(--green-bg);
            color:var(--green);
            border:1px solid var(--green-border);
            padding:2px 8px;
            border-radius:999px;
            font-size:10px;
            font-weight:600;
        }
        .mock-time { font-family:'IBM Plex Mono',monospace; font-size:11px; color:var(--white-muted); }

        /* ---- SECTIONS COMMUNES ---- */
        section { position:relative; z-index:1; }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 48px;
        }

        .section-label {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 11px;
            font-weight: 500;
            color: var(--green);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .section-title {
            font-family: 'Syne', sans-serif;
            font-size: clamp(30px, 4vw, 46px);
            font-weight: 800;
            letter-spacing: -1.5px;
            line-height: 1.1;
            margin-bottom: 16px;
        }

        .section-sub {
            font-size: 16px;
            color: var(--white-dim);
            line-height: 1.7;
            max-width: 560px;
        }

        /* ---- PROBLÈME/SOLUTION ---- */
        .problem {
            padding: 100px 0;
            border-top: 1px solid var(--border);
        }

        .problem-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            margin-top: 60px;
        }

        .problem-card {
            background: var(--black-2);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: var(--radius-lg);
            padding: 32px;
        }

        .problem-card h3 {
            font-family: 'Syne', sans-serif;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #f87171;
        }

        .problem-item {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            color: var(--white-dim);
            line-height: 1.6;
        }

        .problem-icon { font-size:16px; flex-shrink:0; margin-top:1px; }

        .solution-card {
            background: var(--black-2);
            border: 1px solid var(--green-border);
            border-radius: var(--radius-lg);
            padding: 32px;
        }

        .solution-card h3 {
            font-family: 'Syne', sans-serif;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--green);
        }

        .solution-item {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            color: var(--white-dim);
            line-height: 1.6;
        }

        /* ---- FEATURES ---- */
        .features {
            padding: 100px 0;
            border-top: 1px solid var(--border);
        }

        .features-header { margin-bottom: 60px; }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2px;
            background: var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .feature-card {
            background: var(--black-2);
            padding: 36px 32px;
            transition: background 0.2s;
        }
        .feature-card:hover { background: var(--black-3); }

        .feature-icon {
            width: 48px; height: 48px;
            background: var(--green-bg);
            border: 1px solid var(--green-border);
            border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            margin-bottom: 20px;
        }

        .feature-title {
            font-family: 'Syne', sans-serif;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: -0.3px;
        }

        .feature-desc {
            font-size: 14px;
            color: var(--white-dim);
            line-height: 1.7;
        }

        /* ---- TARIFS ---- */
        .pricing {
            padding: 100px 0;
            border-top: 1px solid var(--border);
        }

        .pricing-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: start;
            margin-top: 60px;
        }

        .pricing-card {
            background: var(--black-2);
            border: 1px solid var(--green-border);
            border-radius: var(--radius-lg);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .pricing-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--green), var(--green-dim));
        }

        .pricing-amount {
            font-family: 'Syne', sans-serif;
            font-size: 52px;
            font-weight: 800;
            color: var(--green);
            letter-spacing: -2px;
            line-height: 1;
            margin: 20px 0 4px;
        }

        .pricing-amount span {
            font-size: 18px;
            color: var(--white-muted);
            font-weight: 400;
            letter-spacing: 0;
        }

        .pricing-desc {
            font-size: 14px;
            color: var(--white-dim);
            margin-bottom: 28px;
            line-height: 1.6;
        }

        .pricing-features { list-style: none; }
        .pricing-features li {
            display: flex;
            gap: 10px;
            padding: 10px 0;
            font-size: 14px;
            color: var(--white-dim);
            border-bottom: 1px solid var(--border);
        }
        .pricing-features li:last-child { border-bottom: none; }
        .pricing-features li::before { content: '✓'; color: var(--green); font-weight: 700; flex-shrink:0; }

        .pricing-notes {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .pricing-note {
            background: var(--black-3);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 24px;
        }

        .pricing-note-title {
            font-family: 'Syne', sans-serif;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .pricing-note-desc {
            font-size: 13px;
            color: var(--white-dim);
            line-height: 1.6;
        }

        /* ---- FAQ ---- */
        .faq {
            padding: 100px 0;
            border-top: 1px solid var(--border);
        }

        .faq-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px;
            background: var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-top: 60px;
        }

        .faq-item {
            background: var(--black-2);
            padding: 28px 32px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .faq-item:hover { background: var(--black-3); }

        .faq-q {
            font-family: 'Syne', sans-serif;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .faq-toggle {
            width: 24px; height: 24px;
            background: var(--green-bg);
            border: 1px solid var(--green-border);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            color: var(--green);
            flex-shrink: 0;
            transition: transform 0.2s;
        }

        .faq-item.open .faq-toggle { transform: rotate(45deg); }

        .faq-a {
            font-size: 14px;
            color: var(--white-dim);
            line-height: 1.7;
            display: none;
        }
        .faq-item.open .faq-a { display: block; }

        /* ---- À PROPOS ---- */
        .about {
            padding: 100px 0;
            border-top: 1px solid var(--border);
        }

        .about-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
            margin-top: 60px;
        }

        .about-text p {
            font-size: 16px;
            color: var(--white-dim);
            line-height: 1.8;
            margin-bottom: 20px;
        }

        .about-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px;
            background: var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .about-stat {
            background: var(--black-2);
            padding: 28px;
            text-align: center;
        }

        .about-stat-val {
            font-family: 'Syne', sans-serif;
            font-size: 36px;
            font-weight: 800;
            color: var(--green);
            letter-spacing: -1px;
        }

        .about-stat-lbl {
            font-size: 13px;
            color: var(--white-muted);
            margin-top: 6px;
        }

        /* ---- CTA FINAL ---- */
        .cta-final {
            padding: 100px 0;
            border-top: 1px solid var(--border);
        }

        .cta-box {
            background: var(--black-2);
            border: 1px solid var(--green-border);
            border-radius: 24px;
            padding: 72px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-box::before {
            content: '';
            position: absolute;
            top: -100px; left: 50%;
            transform: translateX(-50%);
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(0,230,118,0.08) 0%, transparent 70%);
            pointer-events: none;
        }

        .cta-box .section-title { font-size: clamp(28px,3.5vw,48px); margin-bottom:20px; }
        .cta-box p { font-size:16px; color:var(--white-dim); margin-bottom:40px; line-height:1.7; }

        /* ---- FOOTER ---- */
        footer {
            border-top: 1px solid var(--border);
            padding: 40px 48px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: var(--white-muted);
        }

        .footer-links { display:flex; gap:24px; }
        .footer-links a { color:var(--white-muted); text-decoration:none; transition:color 0.2s; }
        .footer-links a:hover { color:var(--white); }

        /* ---- ANIMATIONS D'ENTRÉE ---- */
        .reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ---- RESPONSIVE ---- */
        @media (max-width: 1024px) {
            .hero-inner, .problem-grid, .pricing-inner, .about-inner { grid-template-columns: 1fr; gap: 48px; }
            .features-grid { grid-template-columns: 1fr 1fr; }
            .faq-grid { grid-template-columns: 1fr; }
            .hero-visual { display: none; }
        }

        @media (max-width: 768px) {
            nav { padding: 0 20px; }
            .hero { padding: 100px 20px 60px; }
            .container { padding: 0 20px; }
            .features-grid { grid-template-columns: 1fr; }
            .about-stats { grid-template-columns: 1fr 1fr; }
            .cta-box { padding: 40px 24px; }
            footer { flex-direction: column; gap: 20px; text-align: center; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <a href="index.php" class="nav-brand">
        <div class="nav-logo">📡</div>
        <span class="nav-name">ANYTECH</span>
    </a>
    <div class="nav-links">
        <a href="#fonctionnalites" class="nav-link">Fonctionnalités</a>
        <a href="#tarifs" class="nav-link">Tarifs</a>
        <a href="#faq" class="nav-link">FAQ</a>
        <a href="login.php" class="nav-link">Connexion</a>
        <a href="register.php" class="nav-cta">Commencer →</a>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-grid"></div>
    <div class="hero-glow"></div>
    <div class="hero-inner">
        <div class="hero-content">
            <div class="hero-badge">
                <span class="hero-badge-dot"></span>
                Conforme ARCEP Bénin 2026
            </div>
            <h1>Votre WiFi<br>public,<br><span class="accent">en règle.</span></h1>
            <p class="hero-sub">
                ANYTECH identifie automatiquement chaque utilisateur de votre hotspot et conserve les logs de sessions.
                Vous êtes protégé juridiquement. Vos clients connectés en toute légalité.
            </p>
            <div class="hero-actions">
                <a href="register.php" class="btn-primary">Créer un compte gratuit →</a>
                <a href="#fonctionnalites" class="btn-ghost">Voir comment ça marche</a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="mockup">
                <div class="mockup-bar">
                    <div class="mockup-dot" style="background:#ef4444"></div>
                    <div class="mockup-dot" style="background:#f59e0b"></div>
                    <div class="mockup-dot" style="background:#10b981"></div>
                    <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#5a6278;margin-left:8px">dashboard.php</span>
                </div>
                <div class="mockup-body">
                    <div class="mock-stat-row">
                        <div class="mock-stat">
                            <div class="mock-stat-val">247</div>
                            <div class="mock-stat-lbl">Connexions ce mois</div>
                        </div>
                        <div class="mock-stat">
                            <div class="mock-stat-val">3</div>
                            <div class="mock-stat-lbl">Sites actifs</div>
                        </div>
                        <div class="mock-stat">
                            <div class="mock-stat-val">12</div>
                            <div class="mock-stat-lbl">Crédits restants</div>
                        </div>
                    </div>
                    <div class="mock-table-head">
                        <div>Utilisateur</div>
                        <div>Statut</div>
                        <div>Heure</div>
                    </div>
                    <div class="mock-row" style="background:var(--black-3)">
                        <div>
                            <div class="mock-name">Kofi Mensah</div>
                            <div class="mock-sub">CNI: BJ-04821</div>
                        </div>
                        <div><span class="mock-badge-green">● Actif</span></div>
                        <div class="mock-time">14:32</div>
                    </div>
                    <div class="mock-row" style="background:var(--black-2)">
                        <div>
                            <div class="mock-name">Aïda Traoré</div>
                            <div class="mock-sub">CNI: BJ-19034</div>
                        </div>
                        <div><span class="mock-badge-green">● Actif</span></div>
                        <div class="mock-time">14:28</div>
                    </div>
                    <div class="mock-row" style="background:var(--black-3)">
                        <div>
                            <div class="mock-name">Saliou Diallo</div>
                            <div class="mock-sub">CNI: BJ-77201</div>
                        </div>
                        <div><span style="font-size:10px;color:#5a6278">Déconnecté</span></div>
                        <div class="mock-time">13:55</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- PROBLÈME / SOLUTION -->
<section class="problem">
    <div class="container">
        <div class="section-label">Le contexte</div>
        <h2 class="section-title">Offrir le WiFi sans<br>autorisation, c'est risqué.</h2>
        <p class="section-sub">Depuis janvier 2026, l'ARCEP Bénin a durci les sanctions contre les opérateurs WiFi non conformes.</p>

        <div class="problem-grid reveal">
            <div class="problem-card">
                <h3>❌ Sans ANYTECH</h3>
                <div class="problem-item"><span class="problem-icon">⚠️</span>Aucune traçabilité des utilisateurs connectés à votre réseau</div>
                <div class="problem-item"><span class="problem-icon">⚠️</span>Impossible de répondre à une réquisition judiciaire</div>
                <div class="problem-item"><span class="problem-icon">⚠️</span>Amendes entre 1 et 10 millions FCFA (Loi n°2018-18)</div>
                <div class="problem-item"><span class="problem-icon">⚠️</span>Risque de fermeture administrative et saisie des équipements</div>
                <div class="problem-item"><span class="problem-icon">⚠️</span>Peines d'emprisonnement jusqu'à 2 ans en cas de récidive</div>
            </div>
            <div class="solution-card">
                <h3>✅ Avec ANYTECH</h3>
                <div class="solution-item"><span class="problem-icon">🛡️</span>Identification automatique : nom, prénom, CNI, téléphone</div>
                <div class="solution-item"><span class="problem-icon">📋</span>Logs de sessions horodatés : MAC, IP, durée de connexion</div>
                <div class="solution-item"><span class="problem-icon">⚡</span>Installation en moins de 30 minutes sur n'importe quel MikroTik</div>
                <div class="solution-item"><span class="problem-icon">📥</span>Export CSV en un clic pour toute réquisition judiciaire</div>
                <div class="solution-item"><span class="problem-icon">🗂️</span>Données conservées et consultables depuis votre dashboard</div>
            </div>
        </div>
    </div>
</section>

<!-- FONCTIONNALITÉS -->
<section class="features" id="fonctionnalites">
    <div class="container">
        <div class="features-header reveal">
            <div class="section-label">Fonctionnalités</div>
            <h2 class="section-title">Tout ce qu'il vous faut,<br>rien de superflu.</h2>
        </div>
        <div class="features-grid reveal">
            <div class="feature-card">
                <div class="feature-icon">🪪</div>
                <div class="feature-title">Identification légale</div>
                <p class="feature-desc">Formulaire de login personnalisé qui collecte nom, prénom, numéro CNI/passeport et téléphone avant chaque connexion.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📡</div>
                <div class="feature-title">Logs de sessions auto</div>
                <p class="feature-desc">Un script MikroTik envoie automatiquement les logs toutes les heures. MAC, IP, durée — tout est tracé sans intervention manuelle.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <div class="feature-title">Dashboard en temps réel</div>
                <p class="feature-desc">Visualisez vos connexions, gérez plusieurs sites depuis une seule interface. Filtres par date, site, utilisateur.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⬇️</div>
                <div class="feature-title">Export CSV instantané</div>
                <p class="feature-desc">Téléchargez vos registres d'identification et logs de sessions en un clic, prêts pour une réquisition judiciaire ou un contrôle ARCEP.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <div class="feature-title">Installation rapide</div>
                <p class="feature-desc">Compatible avec tous les routeurs MikroTik. Le script de collecte est pré-configuré avec votre code unique — copiez, collez, c'est prêt.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏪</div>
                <div class="feature-title">Multi-sites</div>
                <p class="feature-desc">Gérez tous vos hotspots depuis un seul compte. Restaurant, hôtel, cyber-café — chaque site a son propre tableau de bord.</p>
            </div>
        </div>
    </div>
</section>

<!-- TARIFS -->
<section class="pricing" id="tarifs">
    <div class="container">
        <div class="reveal">
            <div class="section-label">Tarifs</div>
            <h2 class="section-title">Simple comme<br>un crédit.</h2>
            <p class="section-sub">Pas d'abonnement mensuel forcé. Vous achetez des crédits et vous les utilisez quand vous voulez.</p>
        </div>
        <div class="pricing-inner reveal">
            <div class="pricing-card">
                <div class="section-label" style="margin:0">1 crédit =</div>
                <div class="pricing-amount">2 000 <span>FCFA</span></div>
                <p class="pricing-desc">Un crédit = un site actif pendant un mois. Les crédits non utilisés ne s'expirent jamais.</p>
                <ul class="pricing-features">
                    <li>Connexions utilisateurs illimitées</li>
                    <li>Logs de sessions illimités</li>
                    <li>Export CSV inclus</li>
                    <li>Support technique inclus</li>
                    <li>Mise à jour du script MikroTik incluse</li>
                </ul>
                <a href="register.php" class="btn-primary" style="margin-top:28px;width:100%;justify-content:center">
                    Commencer maintenant →
                </a>
            </div>
            <div class="pricing-notes">
                <div class="pricing-note">
                    <div class="pricing-note-title">💡 Achat en volume</div>
                    <p class="pricing-note-desc">Plus vous achetez de crédits, plus le tarif est avantageux. Consultez la grille tarifaire depuis votre dashboard après inscription.</p>
                </div>
                <div class="pricing-note">
                    <div class="pricing-note-title">🔄 Renouvellement flexible</div>
                    <p class="pricing-note-desc">Renouvelez site par site, quand vous voulez. Option de renouvellement automatique disponible pour ne jamais interrompre votre service.</p>
                </div>
                <div class="pricing-note">
                    <div class="pricing-note-title">📱 Paiement Mobile Money</div>
                    <p class="pricing-note-desc">MTN MoMo et Moov Money acceptés. Paiement sécurisé via FedaPay.</p>
                </div>
                <div class="pricing-note">
                    <div class="pricing-note-title">🆓 Essai gratuit</div>
                    <p class="pricing-note-desc">Créez votre compte et testez le dashboard sans engagement. Aucune carte bancaire requise.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="faq" id="faq">
    <div class="container">
        <div class="reveal">
            <div class="section-label">FAQ</div>
            <h2 class="section-title">Questions fréquentes</h2>
        </div>
        <div class="faq-grid reveal">
            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-q">Est-ce que ANYTECH remplace mon routeur MikroTik ?<span class="faq-toggle">+</span></div>
                <div class="faq-a">Non. ANYTECH est un logiciel qui s'installe par-dessus votre infrastructure existante. Votre MikroTik continue de gérer le réseau, ANYTECH s'occupe de l'identification des utilisateurs et de la collecte des logs.</div>
            </div>
            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-q">Mon établissement est-il concerné par la réglementation ARCEP ?<span class="faq-toggle">+</span></div>
                <div class="faq-a">Oui, si vous offrez un accès WiFi ouvert au public — restaurant, hôtel, cyber-café, boutique, mosquée, église — vous êtes soumis à la Loi n°2018-18 portant Code du numérique du Bénin (articles 70 à 85). Depuis janvier 2026, l'ARCEP a durci les contrôles.</div>
            </div>
            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-q">Est-ce que je dois obtenir une autorisation ARCEP séparément ?<span class="faq-toggle">+</span></div>
                <div class="faq-a">Oui. ANYTECH couvre la partie technique (identification + logs), mais l'autorisation administrative reste à votre charge. Vous devez déposer un dossier sur e-services.arcep.bj. ANYTECH vous fournit tous les justificatifs techniques nécessaires pour ce dossier.</div>
            </div>
            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-q">Combien de temps faut-il pour installer ANYTECH ?<span class="faq-toggle">+</span></div>
                <div class="faq-a">Moins de 30 minutes pour un technicien ayant accès à WinBox. Le script de collecte de logs est pré-configuré avec votre code unique — copiez-collez dans System > Scripts, créez un Scheduler, c'est prêt.</div>
            </div>
            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-q">Que se passe-t-il si je ne renouvelle pas un site ?<span class="faq-toggle">+</span></div>
                <div class="faq-a">Le site devient inactif : l'API refuse les nouvelles connexions et le script MikroTik ne peut plus envoyer de logs. Vos données historiques restent accessibles dans le dashboard. Vous pouvez réactiver à tout moment avec un crédit.</div>
            </div>
            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-q">Les données de mes clients sont-elles sécurisées ?<span class="faq-toggle">+</span></div>
                <div class="faq-a">Les données sont stockées sur un serveur sécurisé en HTTPS. Seul vous (le propriétaire du compte) avez accès aux données de vos sites. ANYTECH ne revend ni ne partage ces données. En cas de réquisition judiciaire, c'est vous qui fournissez les exports — pas ANYTECH directement.</div>
            </div>
            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-q">Est-ce compatible avec toutes les versions de MikroTik ?<span class="faq-toggle">+</span></div>
                <div class="faq-a">Oui. Le script a été testé et validé sur RouterOS 6.x et 7.x. Il utilise uniquement des commandes de base (/ip hotspot active, /tool fetch) disponibles sur toutes les versions récentes.</div>
            </div>
            <div class="faq-item" onclick="toggleFaq(this)">
                <div class="faq-q">Je gère plusieurs établissements, comment ça fonctionne ?<span class="faq-toggle">+</span></div>
                <div class="faq-a">Un seul compte ANYTECH, autant de sites que vous voulez. Chaque site consomme 1 crédit par mois et a son propre code unique, sa propre page de login et son propre historique. Vous voyez tout depuis un seul dashboard.</div>
            </div>
        </div>
    </div>
</section>

<!-- À PROPOS -->
<section class="about" id="apropos">
    <div class="container">
        <div class="reveal">
            <div class="section-label">À propos</div>
            <h2 class="section-title">Fait au Bénin,<br>pour le Bénin.</h2>
        </div>
        <div class="about-inner reveal">
            <div class="about-text">
                <p>ANYTECH est développé par <strong>Pambou Myveck Jean-Paul Guiven</strong>,  mise à disposition de <strong>GreenTechnos</strong> une entreprise technologique basée à Cotonou. Notre mission est de fournir des outils simples et accessibles aux commerçants et entrepreneurs béninois pour exercer leurs activités numériques en conformité avec la loi.</p>
                <p>Face à la prolifération des WiFi Zone non conformes et au durcissement des sanctions ARCEP en 2026, nous avons conçu une solution qui protège les propriétaires de hotspots sans nécessiter de compétences techniques avancées.</p>
                <p>ANYTECH est pensé pour la réalité du terrain béninois : paiement Mobile Money, interface en français, support local, compatibilité avec le matériel MikroTik déjà en place.</p>
            </div>
            <div class="about-stats">
                <div class="about-stat">
                    <div class="about-stat-val">2026</div>
                    <div class="about-stat-lbl">Année de lancement</div>
                </div>
                <div class="about-stat">
                    <div class="about-stat-val">100%</div>
                    <div class="about-stat-lbl">Made in Bénin</div>
                </div>
                <div class="about-stat">
                    <div class="about-stat-val">ARCEP</div>
                    <div class="about-stat-lbl">Conformité garantie</div>
                </div>
                <div class="about-stat">
                    <div class="about-stat-val">24/7</div>
                    <div class="about-stat-lbl">Collecte automatique</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA FINAL -->
<section class="cta-final">
    <div class="container">
        <div class="cta-box reveal">
            <div class="section-label" style="justify-content:center;display:flex">Prêt à démarrer ?</div>
            <h2 class="section-title">Protégez votre activité<br>dès aujourd'hui.</h2>
            <p>Créez votre compte en 2 minutes. Aucune carte bancaire requise.<br>Premier site actif dans la journée.</p>
            <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
                <a href="register.php" class="btn-primary">Créer mon compte gratuitement →</a>
                <a href="login.php" class="btn-ghost">J'ai déjà un compte</a>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div style="display:flex;align-items:center;gap:10px">
        <div class="nav-logo" style="width:26px;height:26px;font-size:13px">📡</div>
        <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:15px">ANYTECH by Pambou Myveck JP G.</span>
        <span style="color:var(--white-muted)">— GreenTechnos © 2026</span>
    </div>
    <div class="footer-links">
        <a href="#fonctionnalites">Fonctionnalités</a>
        <a href="#tarifs">Tarifs</a>
        <a href="#faq">FAQ</a>
        <a href="#apropos">À propos</a>
        <a href="login.php">Connexion</a>
        <a href="register.php">S'inscrire</a>
    </div>
</footer>

<script>
    // FAQ toggle
    function toggleFaq(el) {
        el.classList.toggle('open');
    }

    // Scroll reveal
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                observer.unobserve(e.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

    // Nav active sur scroll
    const sections = document.querySelectorAll('section[id]');
    window.addEventListener('scroll', () => {
        let current = '';
        sections.forEach(s => {
            if (window.scrollY >= s.offsetTop - 100) current = s.id;
        });
        document.querySelectorAll('.nav-link').forEach(a => {
            a.style.color = a.getAttribute('href') === '#' + current
                ? 'var(--white)'
                : '';
        });
    });
</script>

</body>
</html>
