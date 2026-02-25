<?php
/**
 * admin.php — Superadmin ANYTECH Hotspot
 * Vue globale : stats plateforme, clients, sites, crédits & transactions
 */

require_once 'admin-auth.php'; // gère session_name + session_start + guard

$pdo = getDBConnection();

// ── Logout ────────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_unset(); session_destroy();
    header('Location: admin-login.php'); exit();
}

// ── Actions POST ──────────────────────────────────────────────────────────────
$flash = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_credits') {
        $pid     = (int)($_POST['proprietaire_id'] ?? 0);
        $credits = (int)($_POST['credits'] ?? 0);
        $note    = trim($_POST['note'] ?? 'Ajout manuel par admin');
        if ($pid > 0 && $credits > 0) {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE proprietaires SET solde_credits = solde_credits + :c WHERE id = :id")
                ->execute([':c' => $credits, ':id' => $pid]);
            $row = $pdo->prepare("SELECT solde_credits FROM proprietaires WHERE id = :id");
            $row->execute([':id' => $pid]);
            $new_solde = $row->fetchColumn();
            $pdo->prepare("INSERT INTO transactions_credits
                           (proprietaire_id, type, credits, solde_apres, description, statut_paiement, date_transaction)
                           VALUES (:pid,'bonus',:c,:sa,:desc,'valide',NOW())")
                ->execute([':pid'=>$pid,':c'=>$credits,':sa'=>$new_solde,':desc'=>$note]);
            $pdo->commit();
            $flash = ['type'=>'success','msg'=>"✅ {$credits} crédit(s) ajouté(s) avec succès."];
        }
    }

    if ($action === 'toggle_status') {
        $pid    = (int)($_POST['proprietaire_id'] ?? 0);
        $actuel = $_POST['statut'] ?? 'actif';
        $nouveau = $actuel === 'actif' ? 'suspendu' : 'actif';
        $pdo->prepare("UPDATE proprietaires SET statut = :s WHERE id = :id")
            ->execute([':s'=>$nouveau, ':id'=>$pid]);
        $flash = ['type'=> $nouveau==='suspendu'?'warning':'success',
                  'msg' => 'Compte '.($nouveau==='suspendu'?'suspendu ✅':'réactivé ✅').' avec succès.'];
    }

    header('Location: admin.php?tab='.htmlspecialchars($_GET['tab']??'overview'));
    exit();
}

// ── KPIs globaux ──────────────────────────────────────────────────────────────
$kpis = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM proprietaires)                                                                  AS total_clients,
        (SELECT COUNT(*) FROM proprietaires WHERE statut='actif')                                             AS clients_actifs,
        (SELECT COUNT(*) FROM proprietaires WHERE statut='suspendu')                                          AS clients_suspendus,
        (SELECT COUNT(*) FROM proprietaires WHERE DATE(date_inscription) >= DATE_SUB(CURDATE(),INTERVAL 30 DAY))   AS nouveaux_30j,
        (SELECT COUNT(*) FROM routeurs)                                                                       AS total_sites,
        (SELECT COUNT(*) FROM routeurs WHERE actif=1 AND date_expiration >= CURDATE())                        AS sites_actifs,
        (SELECT COUNT(*) FROM routeurs WHERE date_expiration < CURDATE())                                     AS sites_expires,
        (SELECT COUNT(*) FROM wifi_users)                                                                     AS total_connexions,
        (SELECT COUNT(DISTINCT telephone) FROM wifi_users)                                                    AS users_uniques,
        (SELECT COALESCE(SUM(credits),0) FROM transactions_credits WHERE type='achat')                        AS credits_vendus,
        (SELECT COALESCE(SUM(montant_fcfa),0) FROM transactions_credits WHERE type='achat' AND statut_paiement='valide') AS revenu_total,
        (SELECT COALESCE(SUM(montant_fcfa),0) FROM transactions_credits WHERE type='achat' AND statut_paiement='valide' AND DATE_FORMAT(date_transaction,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m')) AS revenu_mois
")->fetch();

// ── Clients ───────────────────────────────────────────────────────────────────
$search_client = trim($_GET['sc'] ?? '');
$where_c = $search_client ? "WHERE (p.nom_complet LIKE :sc OR p.email LIKE :sc OR p.telephone LIKE :sc)" : "WHERE 1";
$stmt_c  = $pdo->prepare("
    SELECT p.id, p.nom_complet, p.email, p.telephone, p.statut, p.solde_credits, p.date_inscription,
           COUNT(DISTINCT r.id) AS nb_sites,
           COUNT(DISTINCT CASE WHEN r.actif=1 AND r.date_expiration>=CURDATE() THEN r.id END) AS nb_actifs,
           COALESCE(SUM(CASE WHEN t.type='achat' AND t.statut_paiement='valide' THEN t.montant_fcfa ELSE 0 END),0) AS total_depense
    FROM proprietaires p
    LEFT JOIN routeurs r ON r.proprietaire_id = p.id
    LEFT JOIN transactions_credits t ON t.proprietaire_id = p.id
    $where_c
    GROUP BY p.id
    ORDER BY p.date_inscription DESC
    LIMIT 100
");
if ($search_client) $stmt_c->bindValue(':sc', "%$search_client%");
$stmt_c->execute();
$clients = $stmt_c->fetchAll();

// ── Sites ─────────────────────────────────────────────────────────────────────
$search_site = trim($_GET['ss'] ?? '');
$where_s = $search_site ? "WHERE (r.nom_site LIKE :ss OR r.code_unique LIKE :ss OR r.ville LIKE :ss OR p.nom_complet LIKE :ss)" : "WHERE 1";
$stmt_s  = $pdo->prepare("
    SELECT r.id, r.nom_site, r.code_unique, r.ville, r.date_expiration, r.actif,
           DATEDIFF(r.date_expiration, CURDATE()) AS jours_restants,
           p.nom_complet AS client_nom, p.id AS client_id,
           COUNT(w.id) AS nb_connexions
    FROM routeurs r
    INNER JOIN proprietaires p ON r.proprietaire_id = p.id
    LEFT JOIN wifi_users w ON w.routeur_id = r.id
    $where_s
    GROUP BY r.id
    ORDER BY r.date_expiration ASC
    LIMIT 100
");
if ($search_site) $stmt_s->bindValue(':ss', "%$search_site%");
$stmt_s->execute();
$all_sites = $stmt_s->fetchAll();

// ── Transactions ──────────────────────────────────────────────────────────────
$transactions = $pdo->query("
    SELECT t.*, p.nom_complet AS client_nom, r.nom_site
    FROM transactions_credits t
    INNER JOIN proprietaires p ON t.proprietaire_id = p.id
    LEFT JOIN routeurs r ON t.routeur_id = r.id
    ORDER BY t.date_transaction DESC
    LIMIT 50
")->fetchAll();

// ── Onglet actif ──────────────────────────────────────────────────────────────
$tab = in_array($_GET['tab'] ?? '', ['overview','clients','sites','transactions']) ? $_GET['tab'] : 'overview';

// ── Helpers ───────────────────────────────────────────────────────────────────
function badge(string $cls, string $text): string {
    return '<span class="badge badge-'.$cls.'">'.$text.'</span>';
}
$tx_badge = ['achat'=>'success','utilisation'=>'danger','bonus'=>'brand','remboursement'=>'info'];
$tx_lbl   = ['achat'=>'Achat','utilisation'=>'Utilisation','bonus'=>'Bonus','remboursement'=>'Remboursement'];
$st_badge = ['valide'=>'success','en_attente'=>'warning','echoue'=>'danger','rembourse'=>'info'];
$st_lbl   = ['valide'=>'Validé','en_attente'=>'Attente','echoue'=>'Échoué','rembourse'=>'Remboursé'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin — ANYTECH Hotspot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand:      #5b5ef4;
            --brand-to:   #8b5cf6;
            --brand-light:#ede9fe;
            --surface:    #ffffff;
            --surface-2:  #f7f8fc;
            --surface-3:  #eef0f8;
            --border:     #e4e7f0;
            --text-1:     #0f1023;
            --text-2:     #5a607a;
            --text-3:     #9399b2;
            --green:      #10b981; --green-bg:#ecfdf5;
            --amber:      #f59e0b; --amber-bg:#fffbeb;
            --red:        #ef4444; --red-bg:  #fef2f2;
            --blue:       #3b82f6; --blue-bg: #eff6ff;
            --r-sm:6px; --r-md:12px; --r-lg:16px; --r-xl:24px;
            --shadow-sm:0 1px 3px rgba(15,16,35,.06);
            --shadow-md:0 4px 16px rgba(15,16,35,.09);
            --shadow-lg:0 12px 40px rgba(15,16,35,.12);
            --shadow-brand:0 4px 24px rgba(91,94,244,.32);
            --t:all .18s cubic-bezier(.4,0,.2,1);
            --header-h:62px; --sidebar-w:230px;
        }
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        html{scroll-behavior:smooth;}
        body{font-family:'DM Sans',sans-serif;background:var(--surface-2);color:var(--text-1);min-height:100vh;-webkit-font-smoothing:antialiased;}

        /* ─ HEADER ─ */
        .header{position:fixed;top:0;left:0;right:0;height:var(--header-h);background:linear-gradient(135deg,var(--brand) 0%,var(--brand-to) 100%);z-index:200;box-shadow:0 2px 20px rgba(91,94,244,.3);}
        .header::after{content:'';position:absolute;inset:0;pointer-events:none;background:url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23fff' fill-opacity='0.04'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h22v20h2V0h2v20h2V0h2v20h2V0h2v20h2V0h2v22H20v-1.5z'/%3E%3C/g%3E%3C/svg%3E");}
        .header-inner{max-width:100%;padding:0 24px;height:100%;display:flex;align-items:center;justify-content:space-between;position:relative;z-index:1;}
        .brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:#fff;}
        .brand-icon{width:38px;height:38px;background:rgba(255,255,255,.2);border:1.5px solid rgba(255,255,255,.3);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;}
        .brand-name{font-size:16px;font-weight:700;letter-spacing:-.3px;}
        .brand-pill{font-size:9px;background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.28);border-radius:20px;padding:2px 9px;letter-spacing:.8px;text-transform:uppercase;font-weight:600;}
        .header-right{display:flex;align-items:center;gap:12px;}
        .header-dot{display:flex;align-items:center;gap:7px;color:rgba(255,255,255,.8);font-size:13px;}
        .dot{width:8px;height:8px;background:#10b981;border-radius:50%;border:2px solid rgba(255,255,255,.4);}
        .btn-out{padding:7px 16px;background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.25);border-radius:var(--r-sm);color:#fff;font-size:13px;font-weight:500;text-decoration:none;transition:var(--t);}
        .btn-out:hover{background:rgba(255,255,255,.25);}

        /* ─ SIDEBAR ─ */
        .sidebar{position:fixed;top:var(--header-h);left:0;width:var(--sidebar-w);height:calc(100vh - var(--header-h));background:var(--surface);border-right:1px solid var(--border);overflow-y:auto;z-index:100;padding:20px 12px;}
        .sl{font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-3);padding:0 8px;margin:18px 0 6px;}
        .sl:first-child{margin-top:0;}
        .sa{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:var(--r-sm);text-decoration:none;color:var(--text-2);font-size:13.5px;font-weight:500;transition:var(--t);}
        .sa:hover{background:var(--surface-2);color:var(--text-1);}
        .sa.active{background:var(--brand-light);color:var(--brand);font-weight:650;}
        .sa-count{margin-left:auto;font-size:11px;background:var(--surface-3);color:var(--text-2);border-radius:20px;padding:1px 7px;font-weight:600;font-family:'DM Mono',monospace;}
        .sa.active .sa-count{background:rgba(91,94,244,.15);color:var(--brand);}
        .sidebar-rev{margin-top:24px;padding:14px;background:var(--brand-light);border-radius:var(--r-md);}
        .sidebar-rev-label{font-size:10px;font-weight:700;color:var(--brand);letter-spacing:.5px;margin-bottom:8px;}
        .sidebar-rev-amount{font-size:21px;font-weight:700;font-family:'DM Mono',monospace;color:var(--text-1);}
        .sidebar-rev-sub{font-size:11px;color:var(--text-3);margin-top:2px;}
        .sidebar-rev-month{margin-top:10px;padding-top:10px;border-top:1px solid rgba(91,94,244,.2);}
        .sidebar-rev-month-amt{font-size:17px;font-weight:700;font-family:'DM Mono',monospace;color:var(--brand);}

        /* ─ MAIN ─ */
        .main{margin-left:var(--sidebar-w);margin-top:var(--header-h);padding:28px 28px 60px;}

        /* ─ FLASH ─ */
        .flash{padding:12px 18px;border-radius:var(--r-md);font-size:14px;font-weight:500;margin-bottom:20px;display:flex;align-items:center;gap:8px;}
        .flash.success{background:var(--green-bg);color:#065f46;border:1px solid #a7f3d0;}
        .flash.warning{background:var(--amber-bg);color:#92400e;border:1px solid #fde68a;}

        /* ─ PAGE HEAD ─ */
        .ph{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;}
        .pt{font-size:22px;font-weight:700;letter-spacing:-.4px;}
        .ps{font-size:13.5px;color:var(--text-2);margin-top:3px;}

        /* ─ KPI GRID ─ */
        .kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:16px;margin-bottom:20px;}
        .kpi{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:20px 22px;box-shadow:var(--shadow-sm);transition:var(--t);position:relative;overflow:hidden;}
        .kpi:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);}
        .kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
        .kpi.c-purple::before{background:linear-gradient(90deg,var(--brand),var(--brand-to));}
        .kpi.c-green::before {background:var(--green);}
        .kpi.c-amber::before {background:var(--amber);}
        .kpi.c-red::before   {background:var(--red);}
        .kpi.c-blue::before  {background:var(--blue);}
        .kpi-icon{width:40px;height:40px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px;}
        .kpi-icon.c-purple{background:var(--brand-light);}
        .kpi-icon.c-green {background:var(--green-bg);}
        .kpi-icon.c-amber {background:var(--amber-bg);}
        .kpi-icon.c-red   {background:var(--red-bg);}
        .kpi-icon.c-blue  {background:var(--blue-bg);}
        .kpi-val{font-size:28px;font-weight:700;font-family:'DM Mono',monospace;letter-spacing:-1px;line-height:1;}
        .kpi-lbl{font-size:12.5px;color:var(--text-3);margin-top:5px;font-weight:500;}
        .kpi-sub{font-size:11.5px;color:var(--text-3);margin-top:3px;}
        .section-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-3);margin:28px 0 10px;}
        .section-label:first-child{margin-top:0;}

        /* ─ CARD ─ */
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--shadow-sm);margin-bottom:24px;overflow:hidden;}
        .card-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border);}
        .card-title{font-size:15px;font-weight:650;display:flex;align-items:center;gap:8px;}

        /* ─ SEARCH ─ */
        .search-row{display:flex;gap:10px;align-items:center;padding:14px 20px;border-bottom:1px solid var(--border);background:var(--surface-2);}
        .search-input{flex:1;padding:9px 14px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:13.5px;outline:none;transition:var(--t);font-family:'DM Sans',sans-serif;}
        .search-input:focus{border-color:var(--brand);}

        /* ─ BUTTONS ─ */
        .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:var(--r-sm);font-size:13.5px;font-weight:500;border:none;cursor:pointer;text-decoration:none;transition:var(--t);font-family:'DM Sans',sans-serif;}
        .btn-primary{background:linear-gradient(135deg,var(--brand),var(--brand-to));color:#fff;box-shadow:var(--shadow-brand);}
        .btn-primary:hover{opacity:.9;transform:translateY(-1px);}
        .btn-secondary{background:var(--surface-3);color:var(--text-2);border:1px solid var(--border);}
        .btn-secondary:hover{background:var(--border);color:var(--text-1);}
        .btn-sm{padding:6px 12px;font-size:12.5px;}
        .btn-xs{padding:4px 10px;font-size:12px;border-radius:6px;cursor:pointer;border:1px solid;font-family:'DM Sans',sans-serif;font-weight:500;}
        .btn-green{background:var(--green-bg);color:#065f46;border-color:#a7f3d0;}
        .btn-red  {background:var(--red-bg);color:#991b1b;border-color:#fecaca;}
        .btn-green:hover{background:#d1fae5;}
        .btn-red:hover  {background:#fee2e2;}

        /* ─ TABLE ─ */
        .tw{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;font-size:13.5px;}
        thead th{background:var(--surface-2);color:var(--text-3);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;padding:10px 16px;text-align:left;border-bottom:1px solid var(--border);}
        tbody td{padding:12px 16px;border-bottom:1px solid var(--border);vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:var(--surface-2);}

        /* ─ BADGES ─ */
        .badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;border-radius:20px;padding:3px 9px;white-space:nowrap;}
        .badge-success{background:var(--green-bg);color:#065f46;}
        .badge-danger {background:var(--red-bg);color:#991b1b;}
        .badge-warning{background:var(--amber-bg);color:#92400e;}
        .badge-info   {background:var(--blue-bg);color:#1e40af;}
        .badge-brand  {background:var(--brand-light);color:var(--brand);}
        .badge-neutral{background:var(--surface-3);color:var(--text-2);}

        /* ─ 2-COL ─ */
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;}

        /* ─ TX ─ */
        .tx-pos{color:var(--green);font-weight:700;font-family:'DM Mono',monospace;}
        .tx-neg{color:var(--red);font-weight:700;font-family:'DM Mono',monospace;}

        /* ─ EMPTY ─ */
        .empty{text-align:center;padding:48px 24px;color:var(--text-3);}
        .empty-icon{font-size:44px;margin-bottom:12px;}

        /* ─ MODAL ─ */
        .modal{position:fixed;inset:0;background:rgba(15,16,35,.5);z-index:1000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
        .modal.open{display:flex;}
        .modal-box{background:var(--surface);border-radius:var(--r-xl);padding:32px;width:100%;max-width:440px;box-shadow:var(--shadow-lg);animation:min .18s ease;}
        @keyframes min{from{transform:translateY(12px);opacity:0;}to{transform:translateY(0);opacity:1;}}
        .modal-title{font-size:18px;font-weight:700;margin-bottom:20px;}
        .fg{margin-bottom:16px;}
        .fl{display:block;font-size:12.5px;font-weight:600;color:var(--text-2);margin-bottom:6px;}
        .fc{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--r-sm);font-size:14px;outline:none;transition:var(--t);font-family:'DM Sans',sans-serif;}
        .fc:focus{border-color:var(--brand);}

        /* ─ RESPONSIVE ─ */
        @media(max-width:900px){.sidebar{display:none;}.main{margin-left:0;padding:20px 16px 40px;}.two-col{grid-template-columns:1fr;}}
        @media(max-width:600px){.kpi-grid{grid-template-columns:1fr 1fr;}.header-dot{display:none;}}
    </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
    <div class="header-inner">
        <a href="admin.php" class="brand">
            <div class="brand-icon">🛡️</div>
            <div>
                <div class="brand-name">ANYTECH Admin</div>
            </div>
            <span class="brand-pill">Superadmin</span>
        </a>
        <div class="header-right">
            <div class="header-dot"><div class="dot"></div> Espace sécurisé</div>
            <a href="admin.php?logout=1" class="btn-out"
               onclick="return confirm('Se déconnecter de l\'espace admin ?')">🚪 Déconnexion</a>
        </div>
    </div>
</header>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sl">Navigation</div>
    <a href="?tab=overview"      class="sa <?php echo $tab==='overview'?'active':''; ?>"><span>📊</span> Vue d'ensemble</a>
    <a href="?tab=clients"       class="sa <?php echo $tab==='clients'?'active':''; ?>"><span>👥</span> Clients <span class="sa-count"><?php echo $kpis['total_clients']; ?></span></a>
    <a href="?tab=sites"         class="sa <?php echo $tab==='sites'?'active':''; ?>"><span>📡</span> Sites <span class="sa-count"><?php echo $kpis['total_sites']; ?></span></a>
    <a href="?tab=transactions"  class="sa <?php echo $tab==='transactions'?'active':''; ?>"><span>💳</span> Transactions</a>

    <div class="sl">Liens</div>
    <a href="dashboard.php" class="sa" target="_blank"><span>↗️</span> SaaS client</a>
    <a href="admin.php?logout=1" class="sa" style="color:var(--red)" onclick="return confirm('Déconnecter ?')"><span>🚪</span> Déconnexion</a>

    <div class="sidebar-rev">
        <div class="sidebar-rev-label">REVENUS PLATEFORME</div>
        <div class="sidebar-rev-amount"><?php echo number_format($kpis['revenu_total'],0,'','&thinsp;'); ?> <span style="font-size:11px;font-weight:400;color:var(--text-3)">FCFA</span></div>
        <div class="sidebar-rev-sub">Total cumulé</div>
        <div class="sidebar-rev-month">
            <div class="sidebar-rev-month-amt"><?php echo number_format($kpis['revenu_mois'],0,'','&thinsp;'); ?> <span style="font-size:10px;font-weight:400;color:var(--text-3)">FCFA</span></div>
            <div style="font-size:11px;color:var(--text-3)">Ce mois-ci</div>
        </div>
    </div>
</aside>

<!-- MAIN -->
<main class="main">

<?php if (!empty($flash)): ?>
<div class="flash <?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['msg']); ?></div>
<?php endif; ?>

<?php /* ═══════════ OVERVIEW ═══════════ */ if ($tab === 'overview'): ?>

<div class="ph">
    <div><div class="pt">📊 Vue d'ensemble</div><div class="ps">Plateforme ANYTECH Hotspot — données en temps réel</div></div>
    <div style="display:flex;gap:8px">
        <a href="?tab=clients" class="btn btn-secondary btn-sm">👥 Clients</a>
        <a href="?tab=transactions" class="btn btn-primary btn-sm">💳 Transactions</a>
    </div>
</div>

<div class="section-label">Clients & Comptes</div>
<div class="kpi-grid">
    <div class="kpi c-purple"><div class="kpi-icon c-purple">👥</div><div class="kpi-val"><?php echo $kpis['total_clients']; ?></div><div class="kpi-lbl">Clients total</div><div class="kpi-sub">+<?php echo $kpis['nouveaux_30j']; ?> ce mois</div></div>
    <div class="kpi c-green" ><div class="kpi-icon c-green">✅</div><div class="kpi-val"><?php echo $kpis['clients_actifs']; ?></div><div class="kpi-lbl">Comptes actifs</div></div>
    <div class="kpi c-red"   ><div class="kpi-icon c-red">🚫</div><div class="kpi-val"><?php echo $kpis['clients_suspendus']; ?></div><div class="kpi-lbl">Comptes suspendus</div></div>
    <div class="kpi c-amber" ><div class="kpi-icon c-amber">🆕</div><div class="kpi-val"><?php echo $kpis['nouveaux_30j']; ?></div><div class="kpi-lbl">Nouveaux (30 j)</div></div>
</div>

<div class="section-label">Sites Hotspot</div>
<div class="kpi-grid">
    <div class="kpi c-purple"><div class="kpi-icon c-purple">📡</div><div class="kpi-val"><?php echo $kpis['total_sites']; ?></div><div class="kpi-lbl">Sites total</div></div>
    <div class="kpi c-green" ><div class="kpi-icon c-green">📶</div><div class="kpi-val"><?php echo $kpis['sites_actifs']; ?></div><div class="kpi-lbl">Sites actifs</div></div>
    <div class="kpi c-red"   ><div class="kpi-icon c-red">❌</div><div class="kpi-val"><?php echo $kpis['sites_expires']; ?></div><div class="kpi-lbl">Sites expirés</div></div>
    <div class="kpi c-blue"  ><div class="kpi-icon c-blue">👤</div><div class="kpi-val"><?php echo number_format($kpis['users_uniques']); ?></div><div class="kpi-lbl">Utilisateurs WiFi uniques</div></div>
</div>

<div class="section-label">Revenus & Crédits</div>
<div class="kpi-grid">
    <div class="kpi c-green" ><div class="kpi-icon c-green">💰</div><div class="kpi-val" style="font-size:22px"><?php echo number_format($kpis['revenu_total'],0,'','&thinsp;'); ?></div><div class="kpi-lbl">Revenu total (FCFA)</div></div>
    <div class="kpi c-purple"><div class="kpi-icon c-purple">📅</div><div class="kpi-val" style="font-size:22px"><?php echo number_format($kpis['revenu_mois'],0,'','&thinsp;'); ?></div><div class="kpi-lbl">Revenu ce mois (FCFA)</div></div>
    <div class="kpi c-amber" ><div class="kpi-icon c-amber">🎫</div><div class="kpi-val"><?php echo number_format($kpis['credits_vendus']); ?></div><div class="kpi-lbl">Crédits vendus total</div></div>
    <div class="kpi c-blue"  ><div class="kpi-icon c-blue">📶</div><div class="kpi-val"><?php echo number_format($kpis['total_connexions']); ?></div><div class="kpi-lbl">Connexions WiFi totales</div></div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-head"><span class="card-title">🏆 Top clients (crédits)</span></div>
        <?php $tops=$pdo->query("SELECT nom_complet,email,solde_credits FROM proprietaires ORDER BY solde_credits DESC LIMIT 8")->fetchAll(); ?>
        <div class="tw"><table>
            <thead><tr><th>Client</th><th>Crédits</th></tr></thead>
            <tbody>
            <?php foreach($tops as $t): ?>
            <tr>
                <td><div style="font-weight:650"><?php echo htmlspecialchars($t['nom']); ?></div><div style="font-size:11.5px;color:var(--text-3)"><?php echo htmlspecialchars($t['email']); ?></div></td>
                <td><span class="badge badge-brand" style="font-family:'DM Mono',monospace"><?php echo number_format($t['solde_credits']); ?> cr</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <div class="card">
        <div class="card-head"><span class="card-title">💳 Derniers paiements</span><a href="?tab=transactions" class="btn btn-secondary btn-sm">Voir tout →</a></div>
        <div class="tw"><table>
            <thead><tr><th>Client</th><th>Montant</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach(array_slice($transactions,0,8) as $t): ?>
            <tr>
                <td><div style="font-size:13px;font-weight:600"><?php echo htmlspecialchars($t['client_nom']); ?></div><div style="font-size:11px;color:var(--text-3)"><?php echo date('d/m H:i',strtotime($t['date_transaction'])); ?></div></td>
                <td style="font-family:'DM Mono',monospace;font-size:13px;white-space:nowrap"><?php echo $t['montant_fcfa']?number_format($t['montant_fcfa'],0,'','&thinsp;').' FCFA':'<span class="tx-pos">+'.htmlspecialchars($t['credits']).' cr</span>'; ?></td>
                <td><?php $s=$t['statut_paiement']??'valide'; echo '<span class="badge badge-'.($st_badge[$s]??'neutral').'">'.($st_lbl[$s]??'—').'</span>'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>

<?php /* ═══════════ CLIENTS ═══════════ */ elseif ($tab === 'clients'): ?>

<div class="ph">
    <div><div class="pt">👥 Gestion des clients</div><div class="ps"><?php echo number_format($kpis['total_clients']); ?> comptes enregistrés</div></div>
</div>
<div class="card">
    <form method="GET"><input type="hidden" name="tab" value="clients">
        <div class="search-row">
            <input class="search-input" type="text" name="sc" value="<?php echo htmlspecialchars($search_client); ?>" placeholder="Rechercher nom, email, téléphone…">
            <button type="submit" class="btn btn-primary btn-sm">🔍</button>
            <?php if($search_client): ?><a href="?tab=clients" class="btn btn-secondary btn-sm">↺</a><?php endif; ?>
        </div>
    </form>
    <?php if(count($clients)>0): ?>
    <div class="tw"><table>
        <thead><tr><th>Client</th><th>Contact</th><th>Sites</th><th>Crédits</th><th>Dépensé</th><th>Statut</th><th>Inscrit</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($clients as $c): ?>
        <tr>
            <td><div style="font-weight:650"><?php echo htmlspecialchars($c['nom']); ?></div></td>
            <td><div style="font-size:12.5px"><?php echo htmlspecialchars($c['email']); ?></div><?php if($c['telephone']): ?><div style="font-size:11.5px;color:var(--text-3);font-family:'DM Mono',monospace"><?php echo htmlspecialchars($c['telephone']); ?></div><?php endif; ?></td>
            <td><span class="badge badge-brand"><?php echo $c['nb_actifs']; ?> actifs</span><?php if($c['nb_sites']>$c['nb_actifs']): ?>&nbsp;<span class="badge badge-neutral"><?php echo $c['nb_sites']; ?> total</span><?php endif; ?></td>
            <td style="font-family:'DM Mono',monospace;font-weight:700"><?php echo number_format($c['solde_credits']); ?><span style="font-size:10px;font-weight:400;color:var(--text-3)"> cr</span></td>
            <td style="font-family:'DM Mono',monospace;font-size:12.5px;color:var(--text-2)"><?php echo number_format($c['total_depense'],0,'','&thinsp;'); ?>&thinsp;FCFA</td>
            <td><?php echo $c['statut']==='actif'?'<span class="badge badge-success">Actif</span>':'<span class="badge badge-danger">Suspendu</span>'; ?></td>
            <td style="font-size:12px;color:var(--text-3);white-space:nowrap"><?php echo date('d/m/Y',strtotime($c['date_inscription'])); ?></td>
            <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <button class="btn-xs btn-green" onclick="openModal(<?php echo $c['id']; ?>,'<?php echo htmlspecialchars(addslashes($c['nom'])); ?>')">➕ Crédits</button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Confirmer ?')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="proprietaire_id" value="<?php echo $c['id']; ?>">
                        <input type="hidden" name="statut" value="<?php echo htmlspecialchars($c['statut']); ?>">
                        <button type="submit" class="btn-xs <?php echo $c['statut']==='actif'?'btn-red':'btn-green'; ?>"><?php echo $c['statut']==='actif'?'🚫 Suspendre':'✅ Réactiver'; ?></button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?>
    <div class="empty"><div class="empty-icon">🔭</div><div>Aucun client trouvé.</div></div>
    <?php endif; ?>
</div>

<?php /* ═══════════ SITES ═══════════ */ elseif ($tab === 'sites'): ?>

<div class="ph">
    <div><div class="pt">📡 Vue globale des sites</div><div class="ps"><?php echo $kpis['total_sites']; ?> sites — <?php echo $kpis['sites_actifs']; ?> actifs, <?php echo $kpis['sites_expires']; ?> expirés</div></div>
</div>
<div class="card">
    <form method="GET"><input type="hidden" name="tab" value="sites">
        <div class="search-row">
            <input class="search-input" type="text" name="ss" value="<?php echo htmlspecialchars($search_site); ?>" placeholder="Site, code, ville ou client…">
            <button type="submit" class="btn btn-primary btn-sm">🔍</button>
            <?php if($search_site): ?><a href="?tab=sites" class="btn btn-secondary btn-sm">↺</a><?php endif; ?>
        </div>
    </form>
    <?php if(count($all_sites)>0): ?>
    <div class="tw"><table>
        <thead><tr><th>Site</th><th>Code</th><th>Ville</th><th>Client</th><th>Expiration</th><th>Statut</th><th>Connexions</th></tr></thead>
        <tbody>
        <?php foreach($all_sites as $s):
            $exp  = $s['jours_restants'] < 0;
            $soon = !$exp && $s['jours_restants'] <= 7;
        ?>
        <tr>
            <td style="font-weight:650"><?php echo htmlspecialchars($s['nom_site']); ?></td>
            <td><code style="background:var(--surface-3);padding:2px 7px;border-radius:4px;font-family:'DM Mono',monospace;font-size:12px"><?php echo htmlspecialchars($s['code_unique']); ?></code></td>
            <td style="font-size:13px;color:var(--text-2)"><?php echo $s['ville']?htmlspecialchars($s['ville']):'<span style="color:var(--text-3)">—</span>'; ?></td>
            <td><span class="badge badge-info"><?php echo htmlspecialchars($s['client_nom']); ?></span></td>
            <td style="font-size:12.5px;white-space:nowrap"><?php echo date('d/m/Y',strtotime($s['date_expiration'])); ?><?php if($exp): ?><div style="font-size:10.5px;color:var(--red)">Expiré il y a <?php echo abs($s['jours_restants']); ?>j</div><?php elseif($soon): ?><div style="font-size:10.5px;color:var(--amber)">Dans <?php echo $s['jours_restants']; ?>j</div><?php endif; ?></td>
            <td><?php echo $exp?'<span class="badge badge-danger">❌ Expiré</span>':($soon?'<span class="badge badge-warning">⚠️ Bientôt</span>':'<span class="badge badge-success">✅ Actif</span>'); ?></td>
            <td style="font-family:'DM Mono',monospace;font-weight:600"><?php echo number_format($s['nb_connexions']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?>
    <div class="empty"><div class="empty-icon">📡</div><div>Aucun site trouvé.</div></div>
    <?php endif; ?>
</div>

<?php /* ═══════════ TRANSACTIONS ═══════════ */ elseif ($tab === 'transactions'): ?>

<div class="ph">
    <div><div class="pt">💳 Toutes les transactions</div><div class="ps">Revenu total : <strong><?php echo number_format($kpis['revenu_total'],0,'','&thinsp;'); ?> FCFA</strong> — Ce mois : <strong><?php echo number_format($kpis['revenu_mois'],0,'','&thinsp;'); ?> FCFA</strong></div></div>
</div>
<div class="card">
    <div class="card-head"><span class="card-title">📋 50 dernières transactions</span><span class="badge badge-neutral"><?php echo count($transactions); ?> entrées</span></div>
    <?php if(count($transactions)>0): ?>
    <div class="tw"><table>
        <thead><tr><th>Date</th><th>Client</th><th>Type</th><th>Description</th><th>Montant</th><th>Crédits</th><th>Solde après</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach($transactions as $t): ?>
        <tr>
            <td style="font-size:12px;color:var(--text-2);white-space:nowrap;font-family:'DM Mono',monospace"><?php echo date('d/m/Y',strtotime($t['date_transaction'])); ?><br><span style="opacity:.6"><?php echo date('H:i',strtotime($t['date_transaction'])); ?></span></td>
            <td style="font-size:13px;font-weight:600"><?php echo htmlspecialchars($t['client_nom']); ?></td>
            <td><?php echo '<span class="badge badge-'.($tx_badge[$t['type']]??'neutral').'">'.($tx_lbl[$t['type']]??ucfirst($t['type'])).'</span>'; ?></td>
            <td style="font-size:12.5px;color:var(--text-2);max-width:200px"><?php echo htmlspecialchars($t['description']??'—'); ?><?php if($t['nom_site']): ?><div style="font-size:11px;color:var(--text-3);margin-top:2px;font-family:'DM Mono',monospace">📡 <?php echo htmlspecialchars($t['nom_site']); ?></div><?php endif; ?></td>
            <td style="font-family:'DM Mono',monospace;font-size:13px;white-space:nowrap"><?php echo $t['montant_fcfa']?number_format($t['montant_fcfa'],0,'','&thinsp;').' FCFA':'—'; ?></td>
            <td><?php echo in_array($t['type'],['achat','bonus','remboursement'])?'<span class="tx-pos">+'.htmlspecialchars($t['credits']).'</span>':'<span class="tx-neg">−'.htmlspecialchars($t['credits']).'</span>'; ?></td>
            <td style="font-family:'DM Mono',monospace;font-weight:700"><?php echo $t['solde_apres']??'—'; ?></td>
            <td><?php $s=$t['statut_paiement']??null; echo '<span class="badge badge-'.($st_badge[$s??'']??'neutral').'">'.($st_lbl[$s??'']??'—').'</span>'; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?>
    <div class="empty"><div class="empty-icon">🔭</div><div>Aucune transaction pour le moment.</div></div>
    <?php endif; ?>
</div>

<?php endif; ?>

</main>

<!-- MODAL CRÉDITS -->
<div class="modal" id="modal">
    <div class="modal-box">
        <div class="modal-title">➕ Ajouter des crédits</div>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_credits">
            <input type="hidden" name="proprietaire_id" id="mpid" value="">
            <div class="fg"><div id="mnom" style="font-weight:650;font-size:15px;color:var(--brand);padding:6px 0"></div></div>
            <div class="fg"><label class="fl">Nombre de crédits</label><input class="fc" type="number" name="credits" min="1" max="9999" value="1" required></div>
            <div class="fg"><label class="fl">Note (visible dans les transactions)</label><input class="fc" type="text" name="note" value="Ajout manuel par admin" maxlength="120"></div>
            <div style="display:flex;gap:10px;margin-top:24px">
                <button type="submit" class="btn btn-primary" style="flex:1">✅ Confirmer</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, nom) {
    document.getElementById('mpid').value = id;
    document.getElementById('mnom').textContent = nom;
    document.getElementById('modal').classList.add('open');
}
function closeModal() { document.getElementById('modal').classList.remove('open'); }
document.getElementById('modal').addEventListener('click', e => { if(e.target===document.getElementById('modal')) closeModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });
</script>
</body>
</html>
