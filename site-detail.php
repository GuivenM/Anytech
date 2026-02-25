<?php
/**
 * site-detail.php – VERSION 2.1
 * Aligné sur le design system layout.php (tokens CSS, composants partagés)
 */

require_once 'auth-check.php';
require_once 'layout.php';

$pdo = getDBConnection();

$site_id     = isset($_GET['id'])      ? (int)$_GET['id']  : 0;
$nouveau_site = isset($_GET['new'])    && $_GET['new'] == '1';
$just_renewed = isset($_GET['renewed'])&& $_GET['renewed'] == '1';

if (!$site_id) { header('Location: dashboard.php'); exit(); }

$stmt_site = $pdo->prepare("SELECT * FROM routeurs WHERE id = :id AND proprietaire_id = :proprietaire_id");
$stmt_site->execute([':id' => $site_id, ':proprietaire_id' => $proprietaire_id]);
$site = $stmt_site->fetch();
if (!$site) { header('Location: dashboard.php'); exit(); }

// Expiration
$date_exp      = strtotime($site['date_expiration']);
$aujourd_hui   = strtotime(date('Y-m-d'));
$jours_restants = (int)ceil(($date_exp - $aujourd_hui) / 86400);
$is_expired    = $jours_restants < 0;
$is_expiring   = !$is_expired && $jours_restants <= 7;

// Stats ce mois
$mois_actuel = date('Y-m');
$stmt_stats = $pdo->prepare("
    SELECT
        COUNT(*) as total_connexions,
        COUNT(DISTINCT telephone) as utilisateurs_uniques,
        COUNT(DISTINCT DATE(date_connexion)) as jours_actifs,
        MAX(date_connexion) as derniere_connexion
    FROM wifi_users
    WHERE routeur_id = :id
      AND DATE_FORMAT(date_connexion, '%Y-%m') = :mois
");
$stmt_stats->execute([':id' => $site_id, ':mois' => $mois_actuel]);
$stats = $stmt_stats->fetch();

// Dernières connexions
$stmt_recentes = $pdo->prepare("
    SELECT nom, prenom, telephone, cni, date_connexion
    FROM wifi_users
    WHERE routeur_id = :id
    ORDER BY date_connexion DESC
    LIMIT 10
");
$stmt_recentes->execute([':id' => $site_id]);
$connexions_recentes = $stmt_recentes->fetchAll();

// --- Logs de sessions (onglet) ---
$logs_date_debut = $_GET['log_date_debut'] ?? date('Y-m-d', strtotime('-7 days'));
$logs_date_fin   = $_GET['log_date_fin']   ?? date('Y-m-d');
$logs_mac        = trim($_GET['log_mac']   ?? '');
$logs_username   = trim($_GET['log_username'] ?? '');
$logs_page       = max(1, (int)($_GET['log_page'] ?? 1));
$logs_per_page   = 25;
$logs_offset     = ($logs_page - 1) * $logs_per_page;

$sql_logs_where = "WHERE sl.routeur_id = :routeur_id
                   AND sl.login_time BETWEEN :date_debut AND DATE_ADD(:date_fin, INTERVAL 1 DAY)";
$sql_logs_params = [
    ':routeur_id'  => $site_id,
    ':date_debut'  => $logs_date_debut . ' 00:00:00',
    ':date_fin'    => $logs_date_fin   . ' 23:59:59',
];
if ($logs_mac !== '') {
    $sql_logs_where .= " AND sl.mac_address LIKE :mac";
    $sql_logs_params[':mac'] = '%' . $logs_mac . '%';
}
if ($logs_username !== '') {
    $sql_logs_where .= " AND sl.username LIKE :username";
    $sql_logs_params[':username'] = '%' . $logs_username . '%';
}

// Compter le total pour la pagination
$sql_logs_count = "SELECT COUNT(*) AS total
                   FROM sessions_logs sl
                   $sql_logs_where";
$stmt_lc = $pdo->prepare($sql_logs_count);
$stmt_lc->execute($sql_logs_params);
$logs_total = $stmt_lc->fetch()['total'];
$logs_pages = ceil($logs_total / $logs_per_page);

// Récupérer les logs avec identité
$sql_logs = "SELECT
                sl.id, sl.type, sl.mac_address, sl.ip_address, sl.username,
                sl.uptime, sl.bytes_in, sl.bytes_out, sl.login_time, sl.to_time,
                wu.nom, wu.prenom, wu.telephone, wu.cni
             FROM sessions_logs sl
             LEFT JOIN wifi_users wu
                ON wu.code_voucher = sl.username AND wu.routeur_id = sl.routeur_id
             $sql_logs_where
             ORDER BY sl.login_time DESC
             LIMIT :limit OFFSET :offset";
$stmt_logs = $pdo->prepare($sql_logs);
foreach ($sql_logs_params as $k => $v) {
    $stmt_logs->bindValue($k, $v);
}
$stmt_logs->bindValue(':limit',  $logs_per_page, PDO::PARAM_INT);
$stmt_logs->bindValue(':offset', $logs_offset,   PDO::PARAM_INT);
$stmt_logs->execute();
$sessions_logs_data = $stmt_logs->fetchAll();

// Export CSV
if (isset($_GET['export_logs']) && $_GET['export_logs'] === '1') {
    // On récupère tout (sans limite) pour l'export
    $sql_export = "SELECT
                    sl.mac_address, sl.ip_address, sl.username, sl.type,
                    sl.uptime, sl.bytes_in, sl.bytes_out, sl.login_time, sl.to_time,
                    wu.nom, wu.prenom, wu.telephone, wu.cni
                   FROM sessions_logs sl
                   LEFT JOIN wifi_users wu
                       ON wu.code_voucher = sl.username AND wu.routeur_id = sl.routeur_id
                   $sql_logs_where
                   ORDER BY sl.login_time DESC";
    $stmt_exp = $pdo->prepare($sql_export);
    $stmt_exp->execute($sql_logs_params);
    $export_data = $stmt_exp->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sessions_' . $site['code_unique'] . '_' . date('Ymd_His') . '.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($fp, ['MAC', 'IP', 'Username (voucher)', 'Type', 'Uptime',
                  'Octets IN', 'Octets OUT', 'Connexion', 'Déconnexion',
                  'Nom', 'Prénom', 'Téléphone', 'CNI'], ';');
    foreach ($export_data as $row) {
        fputcsv($fp, [
            $row['mac_address'], $row['ip_address'], $row['username'], $row['type'],
            $row['uptime'], $row['bytes_in'], $row['bytes_out'],
            $row['login_time'], $row['to_time'],
            $row['nom'], $row['prenom'], $row['telephone'], $row['cni'],
        ], ';');
    }
    fclose($fp);
    exit();
}

$api_url      = "https://" . $_SERVER['HTTP_HOST'] . "/api-register.php";
$code_routeur = $site['code_unique'];

renderHeader('sites', $site['nom_site']);
?>

<!-- ══════════════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════════════ -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            📡 <?php echo htmlspecialchars($site['nom_site']); ?>
        </h1>
        <p class="page-subtitle">
            <code style="font-family:'DM Mono',monospace;background:var(--surface-3);padding:2px 8px;border-radius:4px;font-size:12px"><?php echo htmlspecialchars($site['code_unique']); ?></code>
            <?php if ($site['ville']): ?>
                &nbsp;·&nbsp; 📍 <?php echo htmlspecialchars($site['ville']); ?>
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php if ($is_expired || $is_expiring): ?>
            <a href="renew-site.php?id=<?php echo $site_id; ?>" class="btn btn-lg"
               style="background:<?php echo $is_expired ? 'var(--danger)' : 'var(--warning)'; ?>;color:white">
                🔄 Renouveler
            </a>
        <?php endif; ?>
        <a href="sites.php" class="btn btn-secondary">← Retour</a>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     BANNERS (nouveau site / renouvelé)
══════════════════════════════════════════════ -->
<?php if ($nouveau_site): ?>
<div class="success-banner">
    <div class="success-banner-icon">🎉</div>
    <div>
        <div class="success-banner-title">Site créé avec succès !</div>
        <div class="success-banner-text">Un code unique lui a été attribué. Téléchargez la page de login personnalisée ci-dessous pour configurer votre routeur MikroTik.</div>
    </div>
</div>
<?php endif; ?>

<?php if ($just_renewed): ?>
<div class="success-banner">
    <div class="success-banner-icon">✅</div>
    <div>
        <div class="success-banner-title">Site renouvelé avec succès !</div>
        <div class="success-banner-text">
            Nouvelle date d'expiration : <strong><?php echo date('d/m/Y', strtotime($site['date_expiration'])); ?></strong>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ══════════════════════════════════════════════
     BARRE D'EXPIRATION
══════════════════════════════════════════════ -->
<?php
if ($is_expired):
    $exp_class = 'expired';
    $exp_icon  = '❌';
    $exp_text  = 'Ce site est expiré depuis le ' . date('d/m/Y', $date_exp) . '.';
    $exp_badge = '<span class="badge badge-danger">Expiré</span>';
elseif ($is_expiring):
    $exp_class = 'expiring';
    $exp_icon  = '⚠️';
    $exp_text  = $jours_restants === 0
        ? 'Ce site expire <strong>aujourd\'hui</strong>.'
        : 'Ce site expire dans <strong>' . $jours_restants . ' jour' . ($jours_restants > 1 ? 's' : '') . '</strong>.';
    $exp_badge = '<span class="badge badge-warning">⚠️ ' . $jours_restants . 'j</span>';
else:
    $exp_class = 'active';
    $exp_icon  = '✅';
    $exp_text  = 'Actif — expire le <strong>' . date('d/m/Y', $date_exp) . '</strong> (dans ' . $jours_restants . ' jours).';
    $exp_badge = '<span class="badge badge-success">Actif</span>';
endif;
?>
<div class="exp-bar <?php echo $exp_class; ?>">
    <div class="exp-bar-left">
        <span style="font-size:18px"><?php echo $exp_icon; ?></span>
        <span><?php echo $exp_text; ?></span>
        <?php echo $exp_badge; ?>
    </div>
    <div class="exp-bar-right">
        <span class="exp-date">Exp. <?php echo date('d/m/Y', $date_exp); ?></span>
        <?php if ($is_expired || $is_expiring): ?>
            <a href="renew-site.php?id=<?php echo $site_id; ?>"
               class="btn btn-sm"
               style="background:<?php echo $is_expired ? 'var(--danger)' : '#f59e0b'; ?>;color:white">
                🔄 Renouveler
            </a>
        <?php endif; ?>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     STAT CARDS
══════════════════════════════════════════════ -->
<div class="stats-grid" style="margin-bottom:24px">

    <div class="stat-card stat-card-blue">
        <div class="stat-icon-wrap blue">📌</div>
        <div class="stat-value"><?php echo number_format($stats['total_connexions']); ?></div>
        <div class="stat-label">Connexions ce mois</div>
    </div>

    <div class="stat-card stat-card-green">
        <div class="stat-icon-wrap green">👥</div>
        <div class="stat-value"><?php echo number_format($stats['utilisateurs_uniques']); ?></div>
        <div class="stat-label">Utilisateurs uniques</div>
    </div>

    <div class="stat-card stat-card-purple">
        <div class="stat-icon-wrap purple">📅</div>
        <div class="stat-value"><?php echo $stats['jours_actifs']; ?></div>
        <div class="stat-label">Jours actifs ce mois</div>
    </div>

    <div class="stat-card stat-card-orange">
        <div class="stat-icon-wrap orange">⏱️</div>
        <div class="stat-value">
            <?php
            if ($stats['derniere_connexion']) {
                $diff = time() - strtotime($stats['derniere_connexion']);
                if      ($diff < 3600)  echo floor($diff / 60)    . 'm';
                elseif  ($diff < 86400) echo floor($diff / 3600)  . 'h';
                else                    echo floor($diff / 86400) . 'j';
            } else { echo '–'; }
            ?>
        </div>
        <div class="stat-label">Dernière activité</div>
    </div>

</div>


<!-- ══════════════════════════════════════════════
     TABS + CONTENT
══════════════════════════════════════════════ -->
<div class="card">
    <div class="card-body" style="padding:24px">

        <div class="tabs">
            <button class="tab active"  onclick="switchTab('installation', this)">🚀 Installation</button>
            <button class="tab"         onclick="switchTab('integration',  this)">🔧 Intégration</button>
            <button class="tab"         onclick="switchTab('connexions',   this)">👥 Connexions récentes</button>
            <button class="tab" onclick="switchTab('sessions')">📡 Logs de sessions</button>
            <button class="tab"         onclick="switchTab('config',       this)">⚙️ Configuration</button>
        </div>


        <!-- ── TAB: INSTALLATION ── -->
        <div id="tab-installation" class="tab-content active">

            <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:16px">📥 Installation sur MikroTik</h3>

            <p style="color:var(--text-secondary);font-size:14px;line-height:1.7;margin-bottom:20px">
                Téléchargez la page de login personnalisée pour ce site et uploadez-la sur votre routeur MikroTik.
            </p>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:32px">
                <a href="generate-login.php?site_id=<?php echo $site_id; ?>" class="btn btn-primary" download>
                    📥 Télécharger login.html
                </a>
            </div>

            <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:14px">🔧 Configuration MikroTik</h3>

            <p style="color:var(--text-secondary);font-size:14px;margin-bottom:10px"><strong>Étape 1 :</strong> Configuration du Walled Garden</p>
            <div class="code-box"><button class="copy-btn" onclick="copyCode('walled-garden')">Copier</button><div id="walled-garden">/ip hotspot walled-garden
add dst-host=<?php echo $_SERVER['HTTP_HOST']; ?> action=allow comment="ANYTECH API"</div></div>

            <p style="color:var(--text-secondary);font-size:14px;margin:20px 0 10px"><strong>Étape 2 :</strong> Instructions d'installation</p>
            <ol style="margin-left:20px;color:var(--text-secondary);font-size:14px;line-height:2">
                <li>Téléchargez le fichier <code style="font-family:'DM Mono',monospace;background:var(--surface-3);padding:2px 6px;border-radius:4px">login.html</code> ci-dessus</li>
                <li>Connectez-vous à votre routeur MikroTik (WinBox)</li>
                <li>Allez dans <strong>Files</strong></li>
                <li>Uploadez le fichier <code style="font-family:'DM Mono',monospace;background:var(--surface-3);padding:2px 6px;border-radius:4px">login.html</code></li>
                <li>Exécutez la commande Walled Garden ci-dessus dans le Terminal</li>
                <li>Testez en vous connectant au WiFi !</li>
            </ol>

            <!-- SCRIPT LOGS MIKROTIK -->

            <h3 class="section-title" style="margin-top: 40px;">📡 Collecte automatique des logs (ARCEP)</h3>

            <div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px;">
                <strong style="color: #2e7d32;">✅ Script pré-configuré pour ce site</strong>
                <p style="color: #388e3c; margin-top: 6px; font-size: 13px; line-height: 1.6;">
                    Copiez ce script dans <strong>WinBox → System → Scripts → "+" → Name: anytech-logs</strong>, collez dans Source, puis créez un Scheduler toutes les heures.
                </p>
            </div>

            <div class="code-box">
                <button class="copy-btn" onclick="copyCode('mikrotik-script')">Copier</button>
                <div id="mikrotik-script">:local apiUrl "https://<?php echo $_SERVER['HTTP_HOST']; ?>/api-logs.php"
            :local hotspotCode "<?php echo htmlspecialchars($code_routeur); ?>"

            :local sessionsJson "["
            :local first 1

            :foreach s in=[/ip hotspot active find] do={
                :local mac [/ip hotspot active get $s mac-address]
                :local ip [/ip hotspot active get $s address]
                :local user [/ip hotspot active get $s user]
                :local uptime [/ip hotspot active get $s uptime]
                :local entry ("{\"type\":\"active\",\"mac_address\":\"" . $mac . "\",\"ip_address\":\"" . $ip . "\",\"username\":\"" . $user . "\",\"uptime\":\"" . $uptime . "\",\"bytes_in\":0,\"bytes_out\":0,\"login_time\":\"\"}")
                :if ($first = 1) do={
                    :set sessionsJson ($sessionsJson . $entry)
                    :set first 0
                } else={
                    :set sessionsJson ($sessionsJson . "," . $entry)
                }
            }

            :set sessionsJson ($sessionsJson . "]")
            :local payload ("{\"hotspot_code\":\"" . $hotspotCode . "\",\"sessions\":" . $sessionsJson . "}")

            :do {
                /tool fetch url=$apiUrl http-method=post http-header-field=("Content-Type: application/json,X-Hotspot-Code: " . $hotspotCode) http-data=$payload output=none mode=https check-certificate=no
                :log info "ANYTECH-LOGS: OK"
            } on-error={
                :log error "ANYTECH-LOGS: ECHEC"
            }</div>
            </div>

            <div style="margin-top: 20px;">
                <p style="margin-bottom: 12px; color: #666; font-weight: 600;">Étapes d'installation :</p>
                <ol style="margin-left: 20px; color: #666; line-height: 2;">
                    <li>Copiez le script ci-dessus</li>
                    <li><strong>WinBox → System → Scripts → "+"</strong> — Name : <code>anytech-logs</code> — collez dans Source → OK</li>
                    <li><strong>System → Scheduler → "+"</strong> — Name : <code>anytech-logs-scheduler</code> — Interval : <code>01:00:00</code> — On Event : <code>/system script run anytech-logs</code> → OK</li>
                    <li>Tester : <strong>System → Scripts</strong> → sélectionner <code>anytech-logs</code> → <strong>Run</strong></li>
                    <li>Vérifier dans <strong>Log</strong> : tu dois voir <code>ANYTECH-LOGS: OK</code></li>
                </ol>
            </div>


            <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin:28px 0 12px">🔗 URL de l'API</h3>
            <div class="code-box"><button class="copy-btn" onclick="copyCode('api-url')">Copier</button><div id="api-url"><?php echo $api_url; ?></div></div>

            <h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin:20px 0 12px">🔑 Code unique du site</h3>
            <div class="code-box"><button class="copy-btn" onclick="copyCode('code-unique')">Copier</button><div id="code-unique"><?php echo $code_routeur; ?></div></div>

        </div>


        <!-- ── TAB: INTÉGRATION ── -->
        <div id="tab-integration" class="tab-content">

            <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:16px">🔧 Intégration dans une page existante</h3>

            <div class="alert alert-info" style="margin-bottom:24px">
                💡 <strong>Vous avez déjà votre propre page de login ?</strong> Pas besoin de tout refaire. Intégrez simplement nos champs et notre API dans votre design existant.
            </div>

            <p style="color:var(--text-secondary);font-size:14px;margin-bottom:10px">
                <strong>Étape obligatoire :</strong> Walled Garden — exécutez dans le terminal MikroTik :
            </p>
            <div class="code-box"><button class="copy-btn" onclick="copyCode('walled-garden-int')">Copier</button><div id="walled-garden-int">/ip hotspot walled-garden
add dst-host=<?php echo $_SERVER['HTTP_HOST']; ?> action=allow comment="ANYTECH API"</div></div>

            <!-- Méthode 1 -->
            <div class="method-block">
                <div class="method-block-title">📄 Méthode 1 — Script JavaScript externe (recommandé)</div>
                <p style="color:var(--text-secondary);font-size:14px;margin-bottom:16px;line-height:1.65">
                    <strong>Avantages :</strong> plus propre, plus facile à mettre à jour, réutilisable.
                </p>

                <div class="step-label">Étape 1 : Télécharger le script</div>
                <a href="download-integration-script.php?site_id=<?php echo $site_id; ?>"
                   class="btn btn-primary btn-sm" download style="margin-bottom:16px;display:inline-flex">
                    📥 Télécharger anytech-integration.js
                </a>

                <div class="step-label">Étape 2 : Ajouter dans votre formulaire</div>
                <div class="code-box"><button class="copy-btn" onclick="copyCode('code-method1-form')">Copier</button><div id="code-method1-form">&lt;!-- Là où vous voulez les champs --&gt;
&lt;div id="anytech-fields"&gt;&lt;/div&gt;</div></div>

                <div class="step-label">Étape 3 : Ajouter avant &lt;/body&gt;</div>
                <div class="code-box"><button class="copy-btn" onclick="copyCode('code-method1-script')">Copier</button><div id="code-method1-script">&lt;script&gt;
const CONFIG = {
    apiUrl: '<?php echo $api_url; ?>',
    hotspotCode: '<?php echo $code_routeur; ?>'
};
&lt;/script&gt;
&lt;script src="anytech-integration.js"&gt;&lt;/script&gt;</div></div>

                <div style="padding:14px 16px;background:var(--success-bg);border-radius:var(--radius-sm);border-left:3px solid var(--success);font-size:14px;color:#065f46">
                    ✅ <strong>C'est tout !</strong> Le script insère les champs, intercepte le formulaire et enregistre les données automatiquement.
                </div>
            </div>

            <!-- Méthode 2 -->
            <div class="method-block">
                <div class="method-block-title">📝 Méthode 2 — Code inline (tout-en-un)</div>
                <p style="color:var(--text-secondary);font-size:14px;margin-bottom:16px;line-height:1.65">
                    <strong>Avantages :</strong> pas de fichier séparé, tout dans login.html.
                </p>

                <div class="step-label">Étape 1 : Ajouter dans votre formulaire</div>
                <div class="code-box"><button class="copy-btn" onclick="copyCode('code-method2-form')">Copier</button><div id="code-method2-form">&lt;div id="anytech-fields"&gt;&lt;/div&gt;</div></div>

                <div class="step-label">Étape 2 : Ajouter ce code complet avant &lt;/body&gt;</div>
                <div class="code-box" style="max-height:380px;overflow-y:auto"><button class="copy-btn" onclick="copyCode('code-method2-inline')">Copier</button><div id="code-method2-inline">&lt;script&gt;
(function() {
    'use strict';

    const API_URL      = '<?php echo $api_url; ?>';
    const HOTSPOT_CODE = '<?php echo $code_routeur; ?>';

    const style = document.createElement('style');
    style.textContent = `
        .anytech-field { width:100%; padding:12px; margin:8px 0;
            border:2px solid #e0e0e0; border-radius:8px;
            font-size:14px; font-family:inherit; transition:all .3s; }
        .anytech-field:focus { outline:none; border-color:#667eea;
            box-shadow:0 0 0 3px rgba(102,126,234,.1); }
        .anytech-loading { display:none; text-align:center;
            color:#667eea; font-weight:bold; padding:10px; margin-top:10px; }
        .anytech-loading.show { display:block; }
    `;
    document.head.appendChild(style);

    let isSubmitting = false;

    function createFields() {
        const container = document.getElementById('anytech-fields');
        if (!container) { console.error('ANYTECH: div#anytech-fields non trouvée'); return; }
        container.innerHTML = `
            &lt;input class="anytech-field" id="anytech-nom"       type="text" placeholder="Nom"       required /&gt;
            &lt;input class="anytech-field" id="anytech-prenom"    type="text" placeholder="Prénom"    required /&gt;
            &lt;input class="anytech-field" id="anytech-cni"       type="text" placeholder="CNI"       required /&gt;
            &lt;input class="anytech-field" id="anytech-telephone" type="tel"  placeholder="Téléphone" required /&gt;
        `;
    }

    async function sendToAPI(data) {
        try {
            const r = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'X-Hotspot-Code': HOTSPOT_CODE },
                body: JSON.stringify(data)
            });
            const result = await r.json();
            return result.success;
        } catch(e) { console.error('ANYTECH:', e); return false; }
    }

    function interceptForm() {
        const form = document.querySelector('form[name="login"]');
        if (!form) { console.error('ANYTECH: formulaire non trouvé'); return; }
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'anytech-loading';
        loadingDiv.textContent = '⏳ Enregistrement en cours...';
        form.appendChild(loadingDiv);
        const submitBtn = form.querySelector('button[type="submit"],input[type="submit"]');
        form.onsubmit = async function(e) {
            e.preventDefault();
            if (isSubmitting) return;
            isSubmitting = true;
            const data = {
                nom:       document.getElementById('anytech-nom').value.trim(),
                prenom:    document.getElementById('anytech-prenom').value.trim(),
                cni:       document.getElementById('anytech-cni').value.trim(),
                telephone: document.getElementById('anytech-telephone').value.trim(),
                code_voucher:  form.username.value,
                hotspot_code:  HOTSPOT_CODE,
                mac_address:   '\$(mac)',
                date:          new Date().toISOString()
            };
            if (!data.nom||!data.prenom||!data.cni||!data.telephone) {
                alert('⚠️ Veuillez remplir tous les champs.');
                isSubmitting = false; return;
            }
            loadingDiv.classList.add('show');
            if (submitBtn) { submitBtn.disabled = true; }
            await sendToAPI(data);
            loadingDiv.classList.remove('show');
            if (submitBtn) { submitBtn.disabled = false; }
            isSubmitting = false;
            form.submit();
        };
    }

    function init() { createFields(); interceptForm(); console.log('ANYTECH: ✅ Intégration terminée'); }
    if (document.readyState==='loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
&lt;/script&gt;</div></div>
            </div>

            <!-- Personnalisation -->
            <div class="method-block">
                <div class="method-block-title">🎨 Options de personnalisation</div>

                <details style="margin-bottom:12px">
                    <summary>🔍 Masquer certains champs</summary>
                    <div class="detail-body">
                        <div class="code-box"><button class="copy-btn" onclick="copyCode('code-custom-fields')">Copier</button><div id="code-custom-fields">const CONFIG = {
    apiUrl:      '<?php echo $api_url; ?>',
    hotspotCode: '<?php echo $code_routeur; ?>',
    fields: { nom:true, prenom:true, cni:false, telephone:true }
};</div></div>
                    </div>
                </details>

                <details style="margin-bottom:12px">
                    <summary>🎨 Utiliser vos propres styles CSS</summary>
                    <div class="detail-body">
                        <p style="color:var(--text-secondary);font-size:13px;margin-bottom:10px">Désactivez les styles par défaut :</p>
                        <div class="code-box"><button class="copy-btn" onclick="copyCode('code-custom-style')">Copier</button><div id="code-custom-style">const CONFIG = {
    apiUrl:      '<?php echo $api_url; ?>',
    hotspotCode: '<?php echo $code_routeur; ?>',
    styling: { useDefaultStyles: false, fieldsClass:'mon-input-perso' }
};
// Puis ajoutez vos propres styles CSS
.mon-input-perso { /* vos styles */ }</div></div>
                    </div>
                </details>

                <details>
                    <summary>💬 Personnaliser les messages</summary>
                    <div class="detail-body">
                        <div class="code-box"><button class="copy-btn" onclick="copyCode('code-custom-messages')">Copier</button><div id="code-custom-messages">const CONFIG = {
    apiUrl:      '<?php echo $api_url; ?>',
    hotspotCode: '<?php echo $code_routeur; ?>',
    messages: {
        loading:       '⏳ Patientez...',
        missingFields: 'Remplissez tous les champs SVP',
        error:         'Erreur, mais connexion quand même'
    }
};</div></div>
                    </div>
                </details>
            </div>

            <!-- Checklist -->
            <div style="border:1.5px solid #a7f3d0;border-radius:var(--radius-lg);padding:22px;background:var(--success-bg)">
                <div style="font-size:16px;font-weight:700;color:#065f46;margin-bottom:14px">✅ Checklist de test</div>
                <div style="color:#065f46;font-size:14px">
                    <label class="checklist-item"><input type="checkbox" style="margin-right:8px"> Les 4 champs s'affichent correctement</label>
                    <label class="checklist-item"><input type="checkbox" style="margin-right:8px"> Le message "Enregistrement en cours..." apparaît</label>
                    <label class="checklist-item"><input type="checkbox" style="margin-right:8px"> La console (F12) montre "ANYTECH: ✅ Intégration terminée"</label>
                    <label class="checklist-item"><input type="checkbox" style="margin-right:8px"> Les données arrivent dans l'interface admin</label>
                    <label class="checklist-item"><input type="checkbox" style="margin-right:8px"> La connexion WiFi fonctionne normalement</label>
                </div>
            </div>

            <div style="margin-top:16px;padding:16px 18px;background:var(--warning-bg);border-left:3px solid var(--warning);border-radius:var(--radius-sm);font-size:14px;color:#92400e">
                💡 <strong>Besoin d'aide ?</strong> Ouvrez la console (F12) et cherchez les messages "ANYTECH:". Ils indiquent exactement ce qui se passe.
            </div>

        </div>


        <!-- ── TAB: CONNEXIONS RÉCENTES ── -->
        <div id="tab-connexions" class="tab-content">

            <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:20px">👥 Dernières connexions</h3>

            <?php if (count($connexions_recentes) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Téléphone</th>
                            <th>CNI</th>
                            <th>Date & Heure</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($connexions_recentes as $conn): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($conn['nom']); ?></td>
                            <td><?php echo htmlspecialchars($conn['prenom']); ?></td>
                            <td style="font-family:'DM Mono',monospace;font-size:13px"><?php echo htmlspecialchars($conn['telephone']); ?></td>
                            <td style="font-family:'DM Mono',monospace;font-size:13px"><?php echo htmlspecialchars($conn['cni']); ?></td>
                            <td style="font-family:'DM Mono',monospace;font-size:13px"><?php echo date('d/m/Y H:i', strtotime($conn['date_connexion'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:20px">
                <a href="users.php?site_id=<?php echo $site_id; ?>" class="btn btn-primary">Voir tous les utilisateurs →</a>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🔭</div>
                <div class="empty-title">Aucune connexion pour le moment</div>
                <div class="empty-text">Les connexions apparaîtront ici dès que des utilisateurs se connecteront à ce site.</div>
            </div>
            <?php endif; ?>

        </div>

 */


        <!-- ── TAB: CONFIGURATION ── -->
        <div id="tab-config" class="tab-content">

            <h3 style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:20px">⚙️ Informations du site</h3>

            <div class="info-row">
                <div class="info-label">Nom du site</div>
                <div class="info-value"><?php echo htmlspecialchars($site['nom_site']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Code unique</div>
                <div class="info-value"><code><?php echo htmlspecialchars($site['code_unique']); ?></code></div>
            </div>
            <?php if ($site['adresse']): ?>
            <div class="info-row">
                <div class="info-label">Adresse</div>
                <div class="info-value"><?php echo htmlspecialchars($site['adresse']); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($site['ville']): ?>
            <div class="info-row">
                <div class="info-label">Ville</div>
                <div class="info-value"><?php echo htmlspecialchars($site['ville']); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($site['ip_mikrotik']): ?>
            <div class="info-row">
                <div class="info-label">IP MikroTik</div>
                <div class="info-value"><code><?php echo htmlspecialchars($site['ip_mikrotik']); ?></code></div>
            </div>
            <?php endif; ?>
            <?php if ($site['mac_mikrotik']): ?>
            <div class="info-row">
                <div class="info-label">MAC MikroTik</div>
                <div class="info-value"><code><?php echo htmlspecialchars($site['mac_mikrotik']); ?></code></div>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <div class="info-label">Date de création</div>
                <div class="info-value"><?php echo date('d/m/Y à H:i', strtotime($site['date_ajout'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Date d'expiration</div>
                <div class="info-value">
                    <?php echo date('d/m/Y', $date_exp); ?>
                    <?php if ($is_expired): ?>
                        <span class="badge badge-danger" style="margin-left:8px">Expiré</span>
                    <?php elseif ($is_expiring): ?>
                        <span class="badge badge-warning" style="margin-left:8px">⚠️ <?php echo $jours_restants; ?>j</span>
                    <?php else: ?>
                        <span class="badge badge-success" style="margin-left:8px">Actif</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Statut</div>
                <div class="info-value">
                    <?php if ($site['actif']): ?>
                        <span style="color:var(--success);font-weight:600">✅ Actif</span>
                    <?php else: ?>
                        <span style="color:var(--danger);font-weight:600">❌ Inactif</span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap">
                <a href="edit-site.php?id=<?php echo $site_id; ?>" class="btn btn-primary">✏️ Modifier le site</a>
                <?php if ($is_expired || $is_expiring): ?>
                    <a href="renew-site.php?id=<?php echo $site_id; ?>" class="btn btn-sm"
                       style="background:<?php echo $is_expired ? 'var(--danger)' : 'var(--warning)'; ?>;color:white">
                        🔄 Renouveler
                    </a>
                <?php endif; ?>
            </div>

        </div>

    </div><!-- /.card-body -->
</div><!-- /.card -->


<script>
function switchTab(tabName, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');
    btn.classList.add('active');
}

function copyCode(elementId) {
    const el  = document.getElementById(elementId);
    const text = el.innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = el.closest('.code-box').querySelector('.copy-btn');
        const orig = btn.innerText;
        btn.innerText = '✅ Copié !';
        btn.classList.add('copied');
        setTimeout(() => { btn.innerText = orig; btn.classList.remove('copied'); }, 2000);
    });
}
</script>

<?php renderFooter(); ?>