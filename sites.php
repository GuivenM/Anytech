<?php
/**
 * sites.php Ã¢â‚¬â€ VERSION 2.0
 */

require_once 'auth-check.php';
require_once 'layout.php';

$pdo = getDBConnection();

$sql = "
    SELECT r.*,
        COUNT(w.id)               as total_connexions,
        COUNT(DISTINCT w.telephone) as utilisateurs_uniques,
        DATEDIFF(r.date_expiration, CURDATE()) as jours_restants
    FROM routeurs r
    LEFT JOIN wifi_users w ON r.id = w.routeur_id
    WHERE r.proprietaire_id = :id
    GROUP BY r.id
    ORDER BY r.date_expiration ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $proprietaire_id]);
$sites = $stmt->fetchAll();

$total   = count($sites);
$actifs  = count(array_filter($sites, fn($s) => $s['jours_restants'] >= 0 && $s['actif']));
$expires = count(array_filter($sites, fn($s) => $s['jours_restants'] < 0));
$expirant = count(array_filter($sites, fn($s) => $s['jours_restants'] >= 0 && $s['jours_restants'] <= 7 && $s['actif']));

renderHeader('sites', 'Mes sites');
?>

<style>
    .sites-grid { display: flex; flex-direction: column; gap: 12px; }

    .site-card {
        display: grid;
        grid-template-columns: 1fr auto auto auto;
        gap: 20px;
        align-items: center;
        padding: 18px 22px;
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-md);
        transition: var(--transition);
    }

    .site-card:hover { border-color: var(--brand-from); box-shadow: 0 0 0 3px var(--brand-glow); }
    .site-card.expired  { border-color: #fca5a5; background: #fff5f5; }
    .site-card.expiring { border-color: #fcd34d; background: #fffdf0; }

    .site-name { font-size: 16px; font-weight: 650; color: var(--text-primary); margin-bottom: 3px; }
    .site-code { font-family: 'DM Mono', monospace; font-size: 12px; color: var(--text-muted); }
    .site-loc  { font-size: 12px; color: var(--text-muted); margin-top: 3px; }

    .site-stats-cols { display: flex; gap: 24px; }

    .stat-col { text-align: center; }
    .stat-col-val { font-size: 20px; font-weight: 700; font-family: 'DM Mono', monospace; color: var(--text-primary); line-height: 1; }
    .stat-col-lbl { font-size: 10px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; }

    .exp-col { text-align: center; min-width: 96px; }
    .exp-date { font-size: 11.5px; color: var(--text-muted); font-family: 'DM Mono', monospace; margin-top: 5px; }

    .actions-col { display: flex; flex-direction: column; gap: 7px; min-width: 106px; }

    @media (max-width: 768px) {
        .site-card { grid-template-columns: 1fr; }
        .site-stats-cols { justify-content: flex-start; }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">📡 Mes sites</h1>
        <p class="page-subtitle"><?php echo $total; ?> site<?php echo $total > 1 ? 's' : ''; ?> au total</p>
    </div>
    <a href="add-site.php" class="btn btn-primary">+ Ajouter un site</a>
</div>

<!-- STATS -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card" style="border-top:3px solid var(--brand-from)">
        <div class="stat-icon-wrap" style="background:var(--brand-light)">📌</div>
        <div class="stat-value"><?php echo $total; ?></div>
        <div class="stat-label">Total</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--success)">
        <div class="stat-icon-wrap" style="background:var(--success-bg)">✅</div>
        <div class="stat-value"><?php echo $actifs; ?></div>
        <div class="stat-label">Actifs</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--warning)">
        <div class="stat-icon-wrap" style="background:var(--warning-bg)">⚠️</div>
        <div class="stat-value"><?php echo $expirant; ?></div>
        <div class="stat-label">Expirent bientôt</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--danger)">
        <div class="stat-icon-wrap" style="background:var(--danger-bg)">❌</div>
        <div class="stat-value"><?php echo $expires; ?></div>
        <div class="stat-label">Expirés</div>
    </div>
</div>

<!-- LIST -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Tous mes sites</span>
    </div>
    <div class="card-body" style="padding:16px">
        <?php if ($total > 0): ?>
        <div class="sites-grid">
            <?php foreach ($sites as $site):
                $expired  = $site['jours_restants'] < 0;
                $expiring = !$expired && $site['jours_restants'] <= 7;
                $cls = $expired ? 'expired' : ($expiring ? 'expiring' : '');
            ?>
            <div class="site-card <?php echo $cls; ?>">
                <div>
                    <div class="site-name"><?php echo htmlspecialchars($site['nom_site']); ?></div>
                    <div class="site-code">🔑<?php echo htmlspecialchars($site['code_unique']); ?></div>
                    <?php if ($site['ville']): ?>
                        <div class="site-loc">📍 <?php echo htmlspecialchars($site['ville']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="site-stats-cols">
                    <div class="stat-col">
                        <div class="stat-col-val"><?php echo number_format($site['total_connexions']); ?></div>
                        <div class="stat-col-lbl">Connexions</div>
                    </div>
                    <div class="stat-col">
                        <div class="stat-col-val"><?php echo number_format($site['utilisateurs_uniques']); ?></div>
                        <div class="stat-col-lbl">Utilisateurs</div>
                    </div>
                </div>

                <div class="exp-col">
                    <?php if ($expired): ?>
                        <span class="badge badge-danger">❌ Expiré</span>
                    <?php elseif ($expiring): ?>
                        <span class="badge badge-warning">⚠️ <?php echo $site['jours_restants']; ?>j</span>
                    <?php else: ?>
                        <span class="badge badge-success">✅ Actif</span>
                    <?php endif; ?>
                    <div class="exp-date"><?php echo date('d/m/Y', strtotime($site['date_expiration'])); ?></div>
                </div>

                <div class="actions-col">
                    <a href="site-detail.php?id=<?php echo $site['id']; ?>" class="btn btn-primary btn-sm">👁 Voir</a>
                    <?php if ($expired || $expiring): ?>
                        <a href="renew-site.php?id=<?php echo $site['id']; ?>" class="btn btn-sm" style="background:var(--success-bg);color:#065f46;border:1px solid #a7f3d0">🔄 Renouveler</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <div class="empty-title">Aucun site</div>
            <div class="empty-text">Créez votre premier site pour commencer.</div>
            <a href="add-site.php" class="btn btn-primary">+ Créer un site</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php renderFooter(); ?>