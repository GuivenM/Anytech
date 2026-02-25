<?php
/**
 * sessions.php
 * Logs de sessions WiFi reçus depuis MikroTik
 * Conformité ARCEP Bénin — Loi n°2018-18 art. 70-85
 */

require_once 'auth-check.php';
require_once 'layout.php';

$pdo = getDBConnection();

// -------------------------------------------------------
// Vérifier que la table sessions_logs existe
// -------------------------------------------------------
$table_exists = false;
try {
    $pdo->query("SELECT 1 FROM sessions_logs LIMIT 1");
    $table_exists = true;
} catch (PDOException $e) {
    $table_exists = false;
}

// -------------------------------------------------------
// Paramètres filtres
// -------------------------------------------------------
$page       = max(1, (int)($_GET['page']      ?? 1));
$per_page   = 50;
$offset     = ($page - 1) * $per_page;

$site_id    = (int)($_GET['site_id']    ?? 0);
$date_from  = $_GET['date_from']        ?? date('Y-m-d', strtotime('-7 days'));
$date_to    = $_GET['date_to']          ?? date('Y-m-d');
$search_mac = trim($_GET['search_mac']  ?? '');
$search_usr = trim($_GET['search_usr']  ?? '');

// Sites du proprio pour le filtre
$stmt_sites = $pdo->prepare("SELECT id, nom_site FROM routeurs WHERE proprietaire_id = :id AND actif = 1 ORDER BY nom_site");
$stmt_sites->execute([':id' => $proprietaire_id]);
$sites_list = $stmt_sites->fetchAll();

// -------------------------------------------------------
// Export CSV (avant tout output HTML)
// -------------------------------------------------------
if ($table_exists && isset($_GET['export']) && $_GET['export'] === '1') {
    // Construire WHERE pour export (sans pagination)
    $ew  = "r.proprietaire_id = :proprio AND sl.login_time BETWEEN :dd AND :df";
    $ep  = [':proprio' => $proprietaire_id, ':dd' => $date_from . ' 00:00:00', ':df' => $date_to . ' 23:59:59'];
    if ($site_id) { $ew .= " AND sl.routeur_id = :site_id"; $ep[':site_id'] = $site_id; }
    if ($search_mac) { $ew .= " AND sl.mac_address LIKE :mac"; $ep[':mac'] = "%$search_mac%"; }
    if ($search_usr) { $ew .= " AND sl.username LIKE :usr"; $ep[':usr'] = "%$search_usr%"; }

    $stmt_exp = $pdo->prepare("
        SELECT sl.mac_address, sl.ip_address, sl.username, sl.type, sl.uptime,
               sl.bytes_in, sl.bytes_out, sl.login_time, sl.to_time,
               r.nom_site, r.code_unique,
               wu.nom, wu.prenom, wu.cni, wu.telephone
        FROM sessions_logs sl
        INNER JOIN routeurs r ON sl.routeur_id = r.id
        LEFT JOIN wifi_users wu ON wu.code_voucher = sl.username
        WHERE $ew ORDER BY sl.login_time DESC
    ");
    $stmt_exp->execute($ep);
    $rows = $stmt_exp->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sessions_arcep_' . date('Ymd_His') . '.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($fp, ['Site','Code routeur','Type','MAC','IP','Username','Nom','Prénom','CNI','Téléphone','Connexion','Déconnexion','Uptime','Octets IN','Octets OUT'], ';');
    foreach ($rows as $r) {
        fputcsv($fp, [
            $r['nom_site'], $r['code_unique'], $r['type'],
            $r['mac_address'], $r['ip_address'] ?? '', $r['username'] ?? '',
            $r['nom'] ?? '', $r['prenom'] ?? '', $r['cni'] ?? '', $r['telephone'] ?? '',
            $r['login_time'] ?? '', $r['to_time'] ?? '', $r['uptime'] ?? '',
            $r['bytes_in'], $r['bytes_out'],
        ], ';');
    }
    fclose($fp);
    exit();
}

// -------------------------------------------------------
// Données (si table existe)
// -------------------------------------------------------
$sessions    = [];
$total       = 0;
$total_pages = 0;
$stats       = ['total_sessions' => 0, 'appareils_uniques' => 0, 'sites_actifs' => 0, 'actives_now' => 0];

if ($table_exists) {
    // WHERE partagé
    $where  = "r.proprietaire_id = :proprio AND sl.login_time BETWEEN :dd AND :df";
    $params = [':proprio' => $proprietaire_id, ':dd' => $date_from . ' 00:00:00', ':df' => $date_to . ' 23:59:59'];
    if ($site_id)    { $where .= " AND sl.routeur_id = :site_id"; $params[':site_id'] = $site_id; }
    if ($search_mac) { $where .= " AND sl.mac_address LIKE :mac"; $params[':mac'] = "%$search_mac%"; }
    if ($search_usr) { $where .= " AND sl.username LIKE :usr";    $params[':usr'] = "%$search_usr%"; }

    // Stats globales (sans filtre date pour les totaux)
    $stmt_stats = $pdo->prepare("
        SELECT
            COUNT(*) as total_sessions,
            COUNT(DISTINCT sl.mac_address) as appareils_uniques,
            COUNT(DISTINCT sl.routeur_id) as sites_actifs,
            SUM(sl.type = 'active') as actives_now
        FROM sessions_logs sl
        INNER JOIN routeurs r ON sl.routeur_id = r.id
        WHERE r.proprietaire_id = :proprio
    ");
    $stmt_stats->execute([':proprio' => $proprietaire_id]);
    $stats = $stmt_stats->fetch();

    // Count filtré
    $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM sessions_logs sl INNER JOIN routeurs r ON sl.routeur_id = r.id WHERE $where");
    $stmt_count->execute($params);
    $total       = $stmt_count->fetch()['total'];
    $total_pages = (int)ceil($total / $per_page);

    // Data
    $stmt = $pdo->prepare("
        SELECT sl.id, sl.type, sl.mac_address, sl.ip_address, sl.username,
               sl.uptime, sl.bytes_in, sl.bytes_out, sl.login_time, sl.to_time,
               r.nom_site, r.id as routeur_id,
               wu.nom, wu.prenom, wu.telephone, wu.cni
        FROM sessions_logs sl
        INNER JOIN routeurs r ON sl.routeur_id = r.id
        LEFT JOIN wifi_users wu ON wu.code_voucher = sl.username 
        WHERE $where
        ORDER BY sl.login_time DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmt->execute();
    $sessions = $stmt->fetchAll();
}

// Pagination QS
$qs_params = array_filter(['site_id' => $site_id ?: '', 'date_from' => $date_from, 'date_to' => $date_to, 'search_mac' => $search_mac, 'search_usr' => $search_usr]);
$qs = http_build_query($qs_params);

// Helper bytes
function formatBytes(int $b): string {
    if ($b >= 1073741824) return round($b / 1073741824, 1) . ' Go';
    if ($b >= 1048576)    return round($b / 1048576, 1) . ' Mo';
    if ($b >= 1024)       return round($b / 1024, 1) . ' Ko';
    return $b . ' o';
}

renderHeader('sessions', 'Logs de sessions');
?>

<style>
    .filters-bar {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr 1fr auto;
        gap: 12px;
        align-items: end;
        padding: 20px;
        background: var(--surface-2);
        border-bottom: 1px solid var(--border);
    }

    .badge-active {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--success-bg);
        color: var(--success);
        border: 1px solid rgba(16,185,129,0.2);
        padding: 3px 8px;
        border-radius: var(--radius-full);
        font-size: 11px;
        font-weight: 600;
    }

    .badge-host {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--surface-3);
        color: var(--text-muted);
        border: 1px solid var(--border);
        padding: 3px 8px;
        border-radius: var(--radius-full);
        font-size: 11px;
        font-weight: 600;
    }

    .identity-found {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 13px;
    }

    .identity-sub {
        font-size: 11px;
        color: var(--text-muted);
        font-family: 'DM Mono', monospace;
        margin-top: 2px;
    }

    .identity-none {
        color: var(--text-muted);
        font-size: 12px;
        font-style: italic;
    }

    .dot-live {
        width: 7px;
        height: 7px;
        background: var(--success);
        border-radius: 50%;
        display: inline-block;
        animation: pulse-dot 1.5s ease infinite;
    }

    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: 0.5; transform: scale(0.8); }
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        padding: 20px;
        border-top: 1px solid var(--border);
    }

    .page-link {
        padding: 7px 13px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        text-decoration: none;
        color: var(--text-secondary);
        font-size: 13.5px;
        font-weight: 500;
        transition: var(--transition);
        background: var(--surface);
    }

    .page-link:hover  { background: var(--surface-2); }
    .page-link.active { background: var(--brand-from); color: white; border-color: var(--brand-from); }

    .alert-setup {
        background: var(--warning-bg);
        border: 1px solid rgba(245,158,11,0.25);
        border-left: 4px solid var(--warning);
        border-radius: var(--radius-md);
        padding: 20px 24px;
        margin-bottom: 24px;
    }

    @media (max-width: 1100px) { .filters-bar { grid-template-columns: 1fr 1fr 1fr; } }
    @media (max-width: 700px)  { .filters-bar { grid-template-columns: 1fr; } }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">📡 Logs de sessions</h1>
        <p class="page-subtitle">Traçabilité réseau — Conformité ARCEP Bénin</p>
    </div>
    <?php if ($table_exists && $total > 0): ?>
    <a href="sessions.php?<?php echo $qs; ?>&export=1" class="btn btn-secondary">⬇️ Exporter CSV</a>
    <?php endif; ?>
</div>

<?php if (!$table_exists): ?>
<!-- TABLE MANQUANTE -->
<div class="alert-setup">
    <div style="font-weight:700;font-size:15px;color:#92400e;margin-bottom:8px">⚠️ Table sessions_logs non créée</div>
    <p style="color:#92400e;font-size:14px;line-height:1.6">
        Exécutez le fichier <code>sessions_logs.sql</code> sur votre base de données pour activer cette fonctionnalité.
    </p>
</div>

<?php else: ?>

<!-- STATS -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card" style="border-top:3px solid var(--success)">
        <div class="stat-icon-wrap" style="background:var(--success-bg)">🟢</div>
        <div class="stat-value"><?php echo number_format($stats['actives_now'] ?? 0); ?></div>
        <div class="stat-label">Sessions actives</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--brand-from)">
        <div class="stat-icon-wrap" style="background:var(--brand-light)">📋</div>
        <div class="stat-value"><?php echo number_format($stats['total_sessions'] ?? 0); ?></div>
        <div class="stat-label">Total sessions</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--info)">
        <div class="stat-icon-wrap" style="background:var(--info-bg)">📱</div>
        <div class="stat-value"><?php echo number_format($stats['appareils_uniques'] ?? 0); ?></div>
        <div class="stat-label">Appareils uniques</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--warning)">
        <div class="stat-icon-wrap" style="background:var(--warning-bg)">🔍</div>
        <div class="stat-value"><?php echo number_format($total); ?></div>
        <div class="stat-label">Résultats filtrés</div>
    </div>
</div>

<!-- TABLE + FILTRES -->
<div class="card">
    <form method="GET">
        <div class="filters-bar">
            <div class="form-group" style="margin:0">
                <label class="form-label">Site</label>
                <select class="form-control" name="site_id">
                    <option value="">Tous les sites</option>
                    <?php foreach ($sites_list as $sl): ?>
                        <option value="<?php echo $sl['id']; ?>" <?php echo $site_id == $sl['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sl['nom_site']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Date début</label>
                <input class="form-control" type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Date fin</label>
                <input class="form-control" type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Adresse MAC</label>
                <input class="form-control" type="text" name="search_mac"
                    placeholder="AA:BB:CC..." value="<?php echo htmlspecialchars($search_mac); ?>">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Voucher / Username</label>
                <input class="form-control" type="text" name="search_usr"
                    placeholder="code_voucher" value="<?php echo htmlspecialchars($search_usr); ?>">
            </div>
            <div style="display:flex;gap:8px;padding-bottom:1px">
                <button type="submit" class="btn btn-primary">🔍</button>
                <a href="sessions.php" class="btn btn-secondary">↺</a>
            </div>
        </div>
    </form>

    <?php if (count($sessions) > 0): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Statut</th>
                    <th>Appareil</th>
                    <th>Identité</th>
                    <th>Site</th>
                    <th>Connexion</th>
                    <th>Uptime</th>
                    <th>Volume</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $s): ?>
                <tr>
                    <td>
                        <?php if ($s['type'] === 'active'): ?>
                            <span class="badge-active"><span class="dot-live"></span> Actif</span>
                        <?php else: ?>
                            <span class="badge-host">Historique</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code style="font-size:12px;background:var(--surface-3);padding:2px 7px;border-radius:4px;display:block;margin-bottom:3px">
                            <?php echo htmlspecialchars($s['mac_address']); ?>
                        </code>
                        <span style="font-size:11px;color:var(--text-muted)">
                            <?php echo htmlspecialchars($s['ip_address'] ?? '—'); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($s['nom']): ?>
                            <div class="identity-found"><?php echo htmlspecialchars($s['nom'] . ' ' . $s['prenom']); ?></div>
                            <div class="identity-sub"><?php echo htmlspecialchars($s['telephone']); ?></div>
                        <?php else: ?>
                            <span class="identity-none">Non identifié</span>
                            <?php if ($s['username']): ?>
                                <div style="font-size:11px;color:var(--text-muted);font-family:'DM Mono',monospace;margin-top:2px">
                                    <?php echo htmlspecialchars($s['username']); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="site-detail.php?id=<?php echo $s['routeur_id']; ?>"
                           class="badge badge-info" style="text-decoration:none;font-size:12px">
                            <?php echo htmlspecialchars($s['nom_site']); ?>
                        </a>
                    </td>
                    <td style="font-size:13px;color:var(--text-secondary);white-space:nowrap">
                        <?php if ($s['login_time']): ?>
                            <?php echo date('d/m/Y', strtotime($s['login_time'])); ?><br>
                            <span style="font-family:'DM Mono',monospace;font-size:11px">
                                <?php echo date('H:i', strtotime($s['login_time'])); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--text-muted)">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-family:'DM Mono',monospace;font-size:13px">
                        <?php echo htmlspecialchars($s['uptime'] ?? '—'); ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-secondary)">
                        <?php
                        $total_b = ((int)$s['bytes_in']) + ((int)$s['bytes_out']);
                        echo $total_b > 0 ? formatBytes($total_b) : '<span style="color:var(--text-muted)">—</span>';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a class="page-link" href="?page=<?php echo $page-1; ?>&<?php echo $qs; ?>">← Précédent</a>
        <?php endif; ?>
        <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
            <a class="page-link <?php echo $i==$page?'active':''; ?>"
               href="?page=<?php echo $i; ?>&<?php echo $qs; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a class="page-link" href="?page=<?php echo $page+1; ?>&<?php echo $qs; ?>">Suivant →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">📡</div>
        <div class="empty-title">Aucune session trouvée</div>
        <div class="empty-text">
            <?php if ($search_mac || $search_usr || $site_id): ?>
                Aucune session ne correspond à vos filtres.
            <?php else: ?>
                Aucun log reçu sur cette période. Vérifiez que le script MikroTik est actif.
            <?php endif; ?>
        </div>
        <?php if ($search_mac || $search_usr || $site_id): ?>
            <a href="sessions.php" class="btn btn-secondary">↺ Réinitialiser les filtres</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php renderFooter(); ?>
