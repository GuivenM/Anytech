<?php
/**
 * admin-login.php — ANYTECH Superadmin
 * Session séparée "anytech_admin" pour éviter tout conflit
 * avec la session SaaS des clients.
 */

session_name('anytech_admin');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';

// Déjà connecté → panel
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit();
}

$error   = '';
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (defined('ADMIN_PASSWORD') && hash_equals(ADMIN_PASSWORD, $password)) {
        session_regenerate_id(false);
        $_SESSION['admin_logged_in']     = true;
        $_SESSION['admin_last_activity'] = time();
        header('Location: admin.php');
        exit();
    } else {
        $error = 'Mot de passe incorrect.';
        sleep(1);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — ANYTECH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
            background: linear-gradient(135deg, #5b5ef4 0%, #8b5cf6 100%);
        }
        body::before {
            content:''; position:fixed; inset:0; pointer-events:none;
            background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23fff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .box {
            background:#fff; border-radius:20px; padding:48px 40px;
            width:100%; max-width:420px; position:relative;
            box-shadow:0 24px 72px rgba(91,94,244,0.28);
        }
        .badge {
            position:absolute; top:-14px; right:24px;
            background:#0f1023; color:#fff;
            font-size:10px; font-weight:700; letter-spacing:1px;
            text-transform:uppercase; padding:4px 12px; border-radius:20px;
        }
        .logo { text-align:center; margin-bottom:36px; }
        .logo-icon {
            width:68px; height:68px;
            background:linear-gradient(135deg,#5b5ef4,#8b5cf6);
            border-radius:18px; display:inline-flex;
            align-items:center; justify-content:center;
            font-size:30px; margin-bottom:16px;
            box-shadow:0 10px 28px rgba(91,94,244,0.38);
        }
        .logo h1 { font-size:22px; font-weight:700; color:#0f1023; letter-spacing:-.3px; }
        .logo p  { font-size:13px; color:#9399b2; margin-top:4px; }
        label { display:block; font-size:12.5px; font-weight:600; color:#5a607a; margin-bottom:7px; }
        input[type=password] {
            width:100%; padding:12px 16px;
            border:1.5px solid #e4e7f0; border-radius:10px;
            font-size:15px; font-family:'DM Sans',sans-serif;
            outline:none; background:#fafbff;
            transition:border-color .18s, box-shadow .18s;
        }
        input[type=password]:focus {
            border-color:#5b5ef4;
            box-shadow:0 0 0 3px rgba(91,94,244,0.12);
            background:#fff;
        }
        .btn {
            width:100%; margin-top:24px; padding:13px;
            background:linear-gradient(135deg,#5b5ef4,#8b5cf6);
            color:#fff; font-family:'DM Sans',sans-serif;
            font-size:15px; font-weight:600; border:none; border-radius:10px;
            cursor:pointer; box-shadow:0 4px 20px rgba(91,94,244,0.35);
            transition:opacity .18s, transform .18s;
        }
        .btn:hover { opacity:.9; transform:translateY(-1px); }
        .msg { border-radius:9px; padding:11px 15px; font-size:13.5px; margin-bottom:20px; }
        .msg.error   { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
        .msg.timeout { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
        .back { text-align:center; margin-top:22px; }
        .back a { color:#5b5ef4; font-size:13px; text-decoration:none; }
        .back a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="box">
    <div class="badge">🔒 Accès restreint</div>
    <div class="logo">
        <div class="logo-icon">🛡️</div>
        <h1>Espace Superadmin</h1>
        <p>ANYTECH Hotspot — Connexion sécurisée</p>
    </div>

    <?php if ($timeout): ?>
    <div class="msg timeout">⏱️ Session expirée. Veuillez vous reconnecter.</div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="msg error">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div style="margin-bottom:20px">
            <label for="pw">Mot de passe administrateur</label>
            <input type="password" id="pw" name="password"
                   autofocus placeholder="••••••••••••" required autocomplete="current-password">
        </div>
        <button class="btn" type="submit">🔐 Accéder au panel</button>
    </form>
    <div class="back"><a href="dashboard.php">← Retour au SaaS client</a></div>
</div>
</body>
</html>
