<?php
/**
 * layout.php
 * Composant de mise en page partagé - header, nav, styles globaux
 * Usage: include_once 'layout.php'; puis renderHeader($page_active, $page_title);
 */

function renderHeader(string $active_page = 'dashboard', string $page_title = 'ANYTECH Hotspot') {
    global $proprietaire_nom, $proprietaire_email, $proprietaire_solde;
    $initiales = strtoupper(substr($proprietaire_nom ?? 'U', 0, 1));
    if (strpos($proprietaire_nom ?? '', ' ') !== false) {
        $parts = explode(' ', $proprietaire_nom);
        $initiales = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — ANYTECH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        /* ============================================
           DESIGN SYSTEM — ANYTECH HOTSPOT
        ============================================ */
        :root {
            --brand-from: #5b5ef4;
            --brand-to: #8b5cf6;
            --brand-mid: #7c3aed;
            --brand-light: #ede9fe;
            --brand-glow: rgba(91, 94, 244, 0.18);

            --surface: #ffffff;
            --surface-2: #f7f8fc;
            --surface-3: #eef0f8;
            --border: #e4e7f0;
            --border-strong: #c7cde0;

            --text-primary: #0f1023;
            --text-secondary: #5a607a;
            --text-muted: #9399b2;

            --success: #10b981;
            --success-bg: #ecfdf5;
            --warning: #f59e0b;
            --warning-bg: #fffbeb;
            --danger: #ef4444;
            --danger-bg: #fef2f2;
            --info: #3b82f6;
            --info-bg: #eff6ff;

            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --radius-full: 9999px;

            --shadow-sm: 0 1px 3px rgba(15,16,35,0.06), 0 1px 2px rgba(15,16,35,0.04);
            --shadow-md: 0 4px 12px rgba(15,16,35,0.08), 0 2px 4px rgba(15,16,35,0.04);
            --shadow-lg: 0 12px 32px rgba(15,16,35,0.1), 0 4px 8px rgba(15,16,35,0.06);
            --shadow-brand: 0 4px 20px rgba(91, 94, 244, 0.3);

            --header-h: 68px;
            --nav-h: 52px;
            --sidebar-w: 0px; /* reserved for future sidebar */

            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            background: var(--surface-2);
            color: var(--text-primary);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ---- HEADER ---- */
        .layout-header {
            height: var(--header-h);
            background: linear-gradient(135deg, var(--brand-from) 0%, var(--brand-to) 100%);
            box-shadow: 0 2px 16px rgba(91,94,244,0.25);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .layout-header::after {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }

        .header-inner {
            max-width: 1360px;
            margin: 0 auto;
            padding: 0 24px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: white;
        }

        .brand-icon {
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.2);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(8px);
        }

        .brand-name {
            font-size: 17px;
            font-weight: 700;
            letter-spacing: -0.3px;
            line-height: 1;
        }

        .brand-tagline {
            font-size: 10px;
            opacity: 0.65;
            font-weight: 400;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Credits pill */
        .credits-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.15);
            border: 1.5px solid rgba(255,255,255,0.25);
            border-radius: var(--radius-full);
            padding: 7px 16px 7px 10px;
            text-decoration: none;
            color: white;
            transition: var(--transition);
            backdrop-filter: blur(8px);
        }

        .credits-pill:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .credits-icon-wrap {
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .credits-text {
            display: flex;
            flex-direction: column;
            line-height: 1;
        }

        .credits-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            opacity: 0.7;
            font-weight: 500;
        }

        .credits-value {
            font-size: 18px;
            font-weight: 700;
            font-family: 'DM Mono', monospace;
            letter-spacing: -0.5px;
        }

        .credits-plus {
            width: 20px;
            height: 20px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            margin-left: 2px;
        }

        /* User menu */
        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 6px 14px 6px 6px;
            border-radius: var(--radius-full);
            background: rgba(255,255,255,0.12);
            border: 1.5px solid rgba(255,255,255,0.2);
            transition: var(--transition);
            text-decoration: none;
            color: white;
            position: relative;
        }

        .user-menu:hover { background: rgba(255,255,255,0.22); }

        .avatar {
            width: 30px;
            height: 30px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            border: 1.5px solid rgba(255,255,255,0.4);
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chevron { font-size: 10px; opacity: 0.7; }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            min-width: 200px;
            padding: 8px;
            display: none;
            z-index: 200;
        }

        .user-menu:focus-within .user-dropdown,
        .user-dropdown.open { display: block; }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: var(--transition);
        }

        .dropdown-item:hover { background: var(--surface-2); }

        .dropdown-item.danger { color: var(--danger); }
        .dropdown-item.danger:hover { background: var(--danger-bg); }

        .dropdown-divider {
            height: 1px;
            background: var(--border);
            margin: 6px 0;
        }

        /* ---- NAV ---- */
        .layout-nav {
            height: var(--nav-h);
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: var(--header-h);
            z-index: 90;
        }

        .nav-inner {
            max-width: 1360px;
            margin: 0 auto;
            padding: 0 24px;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
            white-space: nowrap;
        }

        .nav-item:hover {
            color: var(--brand-from);
            background: var(--brand-light);
        }

        .nav-item.active {
            color: var(--brand-from);
            background: var(--brand-light);
            font-weight: 600;
        }

        .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 16px);
            height: 2px;
            background: linear-gradient(90deg, var(--brand-from), var(--brand-to));
            border-radius: 2px 2px 0 0;
        }

        .nav-icon { font-size: 15px; }

        /* ---- MAIN CONTENT WRAPPER ---- */
        .layout-main {
            max-width: 1360px;
            margin: 32px auto;
            padding: 0 24px;
        }

        /* ---- SHARED COMPONENTS ---- */

        /* Page title */
        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 16px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 400;
        }

        /* Card */
        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .card-title {
            font-size: 15px;
            font-weight: 650;
            color: var(--text-primary);
        }

        .card-body { padding: 24px; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            line-height: 1;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brand-from), var(--brand-to));
            color: white;
            box-shadow: 0 2px 8px rgba(91,94,244,0.3);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-brand);
        }

        .btn-secondary {
            background: var(--surface-2);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--surface-3);
            border-color: var(--border-strong);
        }

        .btn-danger {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        .btn-danger:hover { background: #fee2e2; }

        .btn-sm { padding: 7px 14px; font-size: 13px; }
        .btn-lg { padding: 13px 28px; font-size: 15px; }

        /* Alerts */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .alert-success { background: var(--success-bg); color: #065f46; border-color: #a7f3d0; }
        .alert-error { background: var(--danger-bg); color: #991b1b; border-color: #fecaca; }
        .alert-warning { background: var(--warning-bg); color: #92400e; border-color: #fde68a; }
        .alert-info { background: var(--info-bg); color: #1e40af; border-color: #bfdbfe; }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success { background: var(--success-bg); color: #065f46; }
        .badge-warning { background: var(--warning-bg); color: #92400e; }
        .badge-danger { background: var(--danger-bg); color: #991b1b; }
        .badge-info { background: var(--info-bg); color: #1e40af; }
        .badge-neutral { background: var(--surface-3); color: var(--text-secondary); }

        /* Form elements */
        .form-group { margin-bottom: 20px; }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 7px;
            letter-spacing: 0.2px;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text-primary);
            background: var(--surface);
            transition: var(--transition);
            outline: none;
        }

        .form-control:focus {
            border-color: var(--brand-from);
            box-shadow: 0 0 0 3px var(--brand-glow);
        }

        .form-control::placeholder { color: var(--text-muted); }

        .form-control:disabled {
            background: var(--surface-3);
            color: var(--text-muted);
            cursor: not-allowed;
        }

        .form-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 5px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px 22px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -1px;
            font-family: 'DM Mono', monospace;
            line-height: 1;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 5px;
        }

        /* Table */
        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead th {
            background: var(--surface-2);
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            padding: 11px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        tbody td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--border);
            color: var(--text-primary);
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: none; }

        tbody tr:hover { background: var(--surface-2); }

        /* Divider */
        .divider {
            height: 1px;
            background: var(--border);
            margin: 24px 0;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 64px 32px;
            color: var(--text-muted);
        }

        .empty-icon { font-size: 52px; margin-bottom: 16px; }
        .empty-title { font-size: 18px; font-weight: 650; color: var(--text-secondary); margin-bottom: 8px; }
        .empty-text { font-size: 14px; margin-bottom: 24px; }

        /* Spinner */
        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: inline-block;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Responsive */
        @media (max-width: 768px) {
            .layout-main { padding: 0 16px; margin: 20px auto; }
            .header-inner { padding: 0 16px; }
            .nav-inner { padding: 0 16px; gap: 0; overflow-x: auto; }
            .brand-tagline { display: none; }
            .user-name { display: none; }
            .form-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 480px) {
            .credits-pill { padding: 7px 12px 7px 8px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="layout-header">
    <div class="header-inner">
        <div class="header-left">
            <a href="dashboard.php" class="brand">
                <div class="brand-icon">📡</div>
                <div>
                    <div class="brand-name">ANYTECH</div>
                    <div class="brand-tagline">Hotspot SaaS</div>
                </div>
            </a>

            <a href="credits.php" class="credits-pill">
                <div class="credits-icon-wrap">💳</div>
                <div class="credits-text">
                    <span class="credits-label">Crédits</span>
                    <span class="credits-value"><?php echo number_format($proprietaire_solde ?? 0); ?></span>
                </div>
                <div class="credits-plus">+</div>
            </a>
        </div>

        <div class="header-right">
            <div class="user-menu" tabindex="0" onclick="this.querySelector('.user-dropdown').classList.toggle('open')">
                <div class="avatar"><?php echo htmlspecialchars($initiales); ?></div>
                <span class="user-name"><?php echo htmlspecialchars($proprietaire_nom ?? ''); ?></span>
                <span class="chevron">▼</span>
                <div class="user-dropdown">
                    <div style="padding:10px 12px 8px;border-bottom:1px solid var(--border);margin-bottom:6px;">
                        <div style="font-size:13px;font-weight:600;color:var(--text-primary)"><?php echo htmlspecialchars($proprietaire_nom ?? ''); ?></div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px"><?php echo htmlspecialchars($proprietaire_email ?? ''); ?></div>
                    </div>
                    <a href="profile.php" class="dropdown-item">👤 Mon profil</a>
                    <a href="credits.php" class="dropdown-item">💳 Mes crédits</a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item danger">🚪 Déconnexion</a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- NAV -->
<nav class="layout-nav">
    <div class="nav-inner">
        <?php
        $nav_items = [
            ['href' => 'dashboard.php', 'icon' => '📊', 'label' => 'Dashboard', 'key' => 'dashboard'],
            ['href' => 'sites.php',     'icon' => '📡', 'label' => 'Mes sites',  'key' => 'sites'],
            ['href' => 'users.php',     'icon' => '👥', 'label' => 'Utilisateurs','key' => 'users'],
            ['href' => 'sessions.php',  'icon' => '📡', 'label' => 'Sessions',    'key' => 'sessions'],
            ['href' => 'credits.php',   'icon' => '💳', 'label' => 'Crédits',    'key' => 'credits'],
            ['href' => 'profile.php',   'icon' => '⚙️', 'label' => 'Paramètres', 'key' => 'profile'],
        ];
        foreach ($nav_items as $item):
            $cls = $item['key'] === $active_page ? 'nav-item active' : 'nav-item';
        ?>
            <a href="<?php echo $item['href']; ?>" class="<?php echo $cls; ?>">
                <span class="nav-icon"><?php echo $item['icon']; ?></span>
                <?php echo $item['label']; ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>

<!-- MAIN -->
<main class="layout-main">
<?php
} // end renderHeader()


function renderFooter() {
?>
</main>

<script>
// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    document.querySelectorAll('.user-dropdown.open').forEach(function(el) {
        if (!el.closest('.user-menu').contains(e.target)) {
            el.classList.remove('open');
        }
    });
});

// Auto-hide alerts after 5s
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.alert[data-auto-hide]').forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 400);
        }, 5000);
    });
});
</script>
</body>
</html>
<?php
} // end renderFooter()
?>
