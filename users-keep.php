<?php
/**
 * users.php — VERSION 2.0
 */

require_once 'auth-check.php';
require_once 'layout.php';

$pdo = getDBConnection();

$page     = max(1, (int)($_GET['page']    ?? 1));
$per_page = 50;
$offset   = ($page - 1) * $per_page;

$search    = trim($_GET['search']    ?? '');
$site_id   = (int)($_GET['site_id'] ?? 0);
$date_from = $_GET['date_from']      ?? '';
$date_to   = $_GET['date_to']        ?? '';

// Sites list for filter dropdown
$stmt_sites = $pdo->prepare("SELECT id, nom_site FROM routeurs WHERE proprietaire_id = :id AND actif = 1 ORDER BY nom_site");
$stmt_sites->execute([':id' => $proprietaire_id]);
$sites_list = $stmt_sites->fetchAll();

// Build WHERE
$where  = ["r.proprietaire_id = :proprietaire_id"];
$params = [':proprietaire_id' => $proprietaire_id];

if ($search) {
    $where[]          = "(w.nom LIKE :search OR w.prenom LIKE :search OR w.telephone LIKE :search OR w.cni LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($site_id) {
    $where[]           = "w.routeur_id = :site_id";
    $params[':site_id'] = $site_id;
}
if ($date_from) {
    $where[]              = "DATE(w.date_connexion) >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to) {
    $where[]            = "DATE(w.date_connexion) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = implode(" AND ", $where);

// Count
$stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM wifi_users w INNER JOIN routeurs r ON w.routeur_id = r.id WHERE $where_clause");
$stmt_count->execute($params);
$total       = $stmt_count->fetch()['total'];
$total_pages = (int)ceil($total / $per_page);

// Data
$stmt_users = $pdo->prepare("
    SELECT w.id, w.nom, w.prenom, w.cni, w.telephone, w.email, w.code_voucher, w.mac_address, w.date_connexion,
           r.nom_site, r.id as routeur_id
    FROM wifi_users w
    INNER JOIN routeurs r ON w.routeur_id = r.id
    WHERE $where_clause
    ORDER BY w.date_connexion DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) $stmt_users->bindValue($k, $v);
$stmt_users->bindValue(':limit',  $per_page, PDO::PARAM_INT);
$stmt_users->bindValue(':offset', $offset,   PDO::PARAM_INT);
$stmt_users->execute();
$users = $stmt_users->fetchAll();

// Global stats
$stmt_stats = $pdo->prepare("
    SELECT COUNT(*) as total_connexions,
           COUNT(DISTINCT w.telephone) as utilisateurs_uniques,
           COUNT(DISTINCT w.routeur_id) as sites_actifs
    FROM wifi_users w INNER JOIN routeurs r ON w.routeur_id = r.id
    WHERE r.proprietaire_id = :id
");
$stmt_stats->execute([':id' => $proprietaire_id]);
$stats = $stmt_stats->fetch();

// Build pagination URL
$qs_params = array_filter(['search' => $search, 'site_id' => $site_id ?: '', 'date_from' => $date_from, 'date_to' => $date_to]);
$qs = http_build_query($qs_params);

renderHeader('users', 'Utilisateurs WiFi');
?>

<style>
    .filters-bar {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr auto;
        gap: 12px;
        align-items: end;
        padding: 20px;
        background: var(--surface-2);
        border-bottom: 1px solid var(--border);
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

    .page-link:hover { background: var(--surface-2); }
    .page-link.active { background: var(--brand-from); color: white; border-color: var(--brand-from); }

    @media (max-width: 900px) {
        .filters-bar { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 600px) {
        .filters-bar { grid-template-columns: 1fr; }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">👥 Utilisateurs WiFi</h1>
        <p class="page-subtitle"><?php echo number_format($stats['total_connexions']); ?> connexions au total</p>
    </div>
    <a href="export-users.php?<?php echo $qs; ?>" class="btn btn-secondary">⬇️ Exporter CSV</a>
</div>

<!-- STATS -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card" style="border-top:3px solid var(--brand-from)">
        <div class="stat-icon-wrap" style="background:var(--brand-light)">📶</div>
        <div class="stat-value"><?php echo number_format($stats['total_connexions']); ?></div>
        <div class="stat-label">Total connexions</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--success)">
        <div class="stat-icon-wrap" style="background:var(--success-bg)">👤</div>
        <div class="stat-value"><?php echo number_format($stats['utilisateurs_uniques']); ?></div>
        <div class="stat-label">Utilisateurs uniques</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--info)">
        <div class="stat-icon-wrap" style="background:var(--info-bg)">📡</div>
        <div class="stat-value"><?php echo number_format($stats['sites_actifs']); ?></div>
        <div class="stat-label">Sites couverts</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--warning)">
        <div class="stat-icon-wrap" style="background:var(--warning-bg)">🔍</div>
        <div class="stat-value"><?php echo number_format($total); ?></div>
        <div class="stat-label">Résultats filtrés</div>
    </div>
</div>

<!-- TABLE -->
<div class="card">
    <!-- FILTERS -->
    <form method="GET">
        <div class="filters-bar">
            <div class="form-group" style="margin:0">
                <label class="form-label">Rechercher</label>
                <input class="form-control" type="text" name="search"
                    placeholder="Nom, téléphone, CNI..."
                    value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">Site</label>
                <select class="form-control" name="site_id">
                    <option value="">Tous les sites</option>
                    <?php foreach ($sites_list as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $site_id == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['nom_site']); ?>
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
            <div style="display:flex;gap:8px;padding-bottom:1px">
                <button type="submit" class="btn btn-primary">🔍 Filtrer</button>
                <a href="users.php" class="btn btn-secondary">↺</a>
            </div>
        </div>
    </form>

    <?php if (count($users) > 0): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom complet</th>
                    <th>Téléphone</th>
                    <th>CNI</th>
                    <th>Site</th>
                    <th>Code voucher</th>
                    <th>Date & Heure</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div style="font-weight:600"><?php echo htmlspecialchars($u['nom'] . ' ' . $u['prenom']); ?></div>
                        <?php if ($u['email']): ?>
                            <div style="font-size:12px;color:var(--text-muted)"><?php echo htmlspecialchars($u['email']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-family:'DM Mono',monospace"><?php echo htmlspecialchars($u['telephone']); ?></td>
                    <td style="font-family:'DM Mono',monospace;font-size:12px"><?php echo htmlspecialchars($u['cni']); ?></td>
                    <td>
                        <a href="site-detail.php?id=<?php echo $u['routeur_id']; ?>" class="badge badge-info" style="text-decoration:none">
                            <?php echo htmlspecialchars($u['nom_site']); ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($u['code_voucher']): ?>
                            <code style="font-size:12px;background:var(--surface-3);padding:2px 7px;border-radius:4px">
                                <?php echo htmlspecialchars($u['code_voucher']); ?>
                            </code>
                        <?php else: ?>
                            <span style="color:var(--text-muted)">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;color:var(--text-secondary)">
                        <?php echo date('d/m/Y', strtotime($u['date_connexion'])); ?><br>
                        <span style="font-family:'DM Mono',monospace;font-size:11px"><?php echo date('H:i', strtotime($u['date_connexion'])); ?></span>
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
            <a class="page-link <?php echo $i==$page?'active':''; ?>" href="?page=<?php echo $i; ?>&<?php echo $qs; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a class="page-link" href="?page=<?php echo $page+1; ?>&<?php echo $qs; ?>">Suivant →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">🔭</div>
        <div class="empty-title">Aucun utilisateur trouvé</div>
        <div class="empty-text">Aucune connexion ne correspond à vos critères de recherche.</div>
        <?php if ($search || $site_id || $date_from || $date_to): ?>
            <a href="users.php" class="btn btn-secondary">↺ Réinitialiser les filtres</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
