<?php
/**
 * dashboard.php – VERSION 2.1
 * Ajout : section "Sites expirant bientôt" avec accès direct au renouvellement
 */

require_once 'auth-check.php';
require_once 'layout.php';

$pdo = getDBConnection();
$mois_actuel = date('Y-m');

$stmt_stats = $pdo->prepare("
    SELECT
        COUNT(DISTINCT r.id) as total_sites,
        COUNT(DISTINCT CASE WHEN r.date_expiration >= CURDATE() AND r.actif = 1 THEN r.id END) as sites_actifs,
        COUNT(DISTINCT CASE WHEN r.date_expiration < CURDATE() THEN r.id END) as sites_expires,
        COUNT(DISTINCT CASE WHEN DATEDIFF(r.date_expiration, CURDATE()) BETWEEN 0 AND 7 AND r.actif = 1 THEN r.id END) as sites_expirant,
        COUNT(w.id) as connexions_mois,
        COUNT(DISTINCT w.telephone) as utilisateurs_uniques
    FROM routeurs r
    LEFT JOIN wifi_users w ON r.id = w.routeur_id
        AND DATE_FORMAT(w.date_connexion, '%Y-%m') = :mois
    WHERE r.proprietaire_id = :proprietaire_id
");
$stmt_stats->execute([':proprietaire_id' => $proprietaire_id, ':mois' => $mois_actuel]);
$stats = $stmt_stats->fetch();

// Sites expirant dans les 7 jours OU déjà expirés — pour la section dédiée
$stmt_urgent = $pdo->prepare("
    SELECT
        r.id, r.nom_site, r.code_unique, r.ville, r.date_expiration, r.actif,
        DATEDIFF(r.date_expiration, CURDATE()) as jours_restants
    FROM routeurs r
    WHERE r.proprietaire_id = :proprietaire_id
      AND DATEDIFF(r.date_expiration, CURDATE()) <= 7
    ORDER BY r.date_expiration ASC
    LIMIT 10
");
$stmt_urgent->execute([':proprietaire_id' => $proprietaire_id]);
$sites_urgents = $stmt_urgent->fetchAll();

// Sites récents (tous statuts)
$stmt_sites = $pdo->prepare("
    SELECT
        r.id, r.nom_site, r.code_unique, r.ville, r.date_expiration, r.actif,
        DATEDIFF(r.date_expiration, CURDATE()) as jours_restants,
        COUNT(w.id) as connexions_mois,
        COUNT(DISTINCT w.telephone) as utilisateurs_uniques
    FROM routeurs r
    LEFT JOIN wifi_users w ON r.id = w.routeur_id
        AND DATE_FORMAT(w.date_connexion, '%Y-%m') = :mois
    WHERE r.proprietaire_id = :proprietaire_id
    GROUP BY r.id
    ORDER BY r.date_expiration ASC
    LIMIT 10
");
$stmt_sites->execute([':proprietaire_id' => $proprietaire_id, ':mois' => $mois_actuel]);
$sites = $stmt_sites->fetchAll();

renderHeader('dashboard', 'Dashboard');
?>

<!-- WELCOME -->
<div class="welcome-banner">
    <div>
        <div class="welcome-title">Bonjour, <?php echo htmlspecialchars(explode(' ', $proprietaire_nom)[0]); ?> 👋</div>
        <div class="welcome-sub">Aperçu de votre activité — <?php echo date('F Y'); ?></div>
    </div>
    <a href="add-site.php" class="btn btn-lg" style="background:rgba(255,255,255,0.2);color:white;border:1.5px solid rgba(255,255,255,0.35);flex-shrink:0">
        + Ajouter un site
    </a>
</div>

<!-- STATS CARDS -->
<div class="stats-grid">
    <div class="stat-card stat-card-success">
        <div class="stat-icon-wrap success">✅</div>
        <div class="stat-value"><?php echo $stats['sites_actifs']; ?><span style="font-size:16px;color:var(--text-muted);font-weight:400"> / <?php echo $stats['total_sites']; ?></span></div>
        <div class="stat-label">Sites actifs</div>
    </div>
    <div class="stat-card stat-card-danger">
        <div class="stat-icon-wrap danger">🔴</div>
        <div class="stat-value"><?php echo $stats['sites_expires']; ?></div>
        <div class="stat-label">Sites expirés</div>
    </div>
    <div class="stat-card stat-card-brand">
        <div class="stat-icon-wrap brand">📶</div>
        <div class="stat-value"><?php echo number_format($stats['connexions_mois']); ?></div>
        <div class="stat-label">Connexions ce mois</div>
    </div>
    <div class="stat-card stat-card-brand">
        <div class="stat-icon-wrap brand">👥</div>
        <div class="stat-value"><?php echo number_format($stats['utilisateurs_uniques']); ?></div>
        <div class="stat-label">Utilisateurs uniques</div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SITES EXPIRANT BIENTÔT                                       -->
<!-- ============================================================ -->
<?php if (count($sites_urgents) > 0): ?>
<section class="expiring-section">
    <div class="expiring-header">
        <div class="expiring-title">
            <div class="expiring-pulse"></div>
            ⏰ Sites expirant bientôt
            <span class="badge badge-danger" style="font-size:12px;padding:3px 8px"><?php echo count($sites_urgents); ?></span>
        </div>
        <a href="sites.php" class="btn btn-secondary btn-sm">Voir tous →</a>
    </div>

    <div class="expiring-cards">
        <?php foreach ($sites_urgents as $su):
            $expired  = $su['jours_restants'] < 0;
            $jours    = abs((int)$su['jours_restants']);
            $has_credits = $proprietaire_solde >= 1;
        ?>
        <div class="expiring-card <?php echo $expired ? 'is-expired' : 'is-expiring'; ?>">

            <div class="expiring-card-icon <?php echo $expired ? 'expired' : 'expiring'; ?>">
                <?php echo $expired ? '❌' : '⚠️'; ?>
            </div>

            <div class="expiring-card-info">
                <div class="expiring-card-name"><?php echo htmlspecialchars($su['nom_site']); ?></div>
                <div class="expiring-card-meta">
                    <?php echo htmlspecialchars($su['code_unique']); ?>
                    <?php if ($su['ville']): ?> · <?php echo htmlspecialchars($su['ville']); ?><?php endif; ?>
                </div>
                <div class="expiring-card-meta" style="margin-top:3px;color:<?php echo $expired?'var(--danger)':'#b45309';?>">
                    <?php echo $expired ? 'Expiré le ' . date('d/m/Y', strtotime($su['date_expiration'])) : 'Expire le ' . date('d/m/Y', strtotime($su['date_expiration'])); ?>
                </div>
            </div>

            <div class="expiring-card-countdown">
                <div class="countdown-number <?php echo $expired ? 'expired' : 'expiring'; ?>">
                    <?php echo $expired ? '-' . $jours : $jours; ?>
                </div>
                <div class="countdown-label"><?php echo $jours <= 1 ? 'jour' : 'jours'; ?></div>
            </div>

            <div class="expiring-card-action">
                <?php if ($has_credits): ?>
                    <a href="renew-site.php?id=<?php echo $su['id']; ?>"
                       class="btn-renew <?php echo $expired ? 'danger' : 'warning'; ?>">
                        🔄 Renouveler
                    </a>
                <?php else: ?>
                    <a href="credits.php?redirect=renew&site_id=<?php echo $su['id']; ?>"
                       class="credits-nudge">
                        💳 Acheter des crédits
                    </a>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$proprietaire_solde): ?>
    <div style="margin-top:12px;padding:12px 16px;background:var(--brand-light);border:1px solid rgba(91,94,244,0.2);border-radius:var(--radius-md);display:flex;align-items:center;gap:12px;font-size:13.5px;color:var(--brand-from);">
        💡 <span>Vous n'avez pas assez de crédits pour renouveler. <strong><a href="buy-credits.php" style="color:var(--brand-from)">Achetez des crédits maintenant →</a></strong></span>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>


<!-- SITES RÉCENTS -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📡 Mes sites récents</span>
        <a href="sites.php" class="btn btn-secondary btn-sm">Voir tous →</a>
    </div>
    <div class="card-body" style="padding:16px">
        <?php if (count($sites) > 0): ?>
        <div class="sites-list">
            <?php foreach ($sites as $site):
                $expired  = $site['jours_restants'] < 0;
                $expiring = !$expired && $site['jours_restants'] <= 7;
                $row_cls  = $expired ? 'expired' : ($expiring ? 'expiring' : '');
            ?>
            <div class="site-row <?php echo $row_cls; ?>">
                <div>
                    <div class="site-name-text"><?php echo htmlspecialchars($site['nom_site']); ?></div>
                    <div class="site-code-text"><?php echo htmlspecialchars($site['code_unique']); ?></div>
                    <?php if ($site['ville']): ?>
                        <div class="site-location-text">📍 <?php echo htmlspecialchars($site['ville']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="site-mini-stats">
                    <div class="mini-stat">
                        <div class="mini-stat-val"><?php echo number_format($site['connexions_mois']); ?></div>
                        <div class="mini-stat-lbl">Connexions</div>
                    </div>
                    <div class="mini-stat">
                        <div class="mini-stat-val"><?php echo number_format($site['utilisateurs_uniques']); ?></div>
                        <div class="mini-stat-lbl">Utilisateurs</div>
                    </div>
                </div>

                <div class="site-exp-col">
                    <?php if ($expired): ?>
                        <span class="badge badge-danger">❌ Expiré</span>
                    <?php elseif ($expiring): ?>
                        <span class="badge badge-warning">⚠️ <?php echo $site['jours_restants']; ?>j</span>
                    <?php else: ?>
                        <span class="badge badge-success">✅ Actif</span>
                    <?php endif; ?>
                    <div class="site-exp-date"><?php echo date('d/m/Y', strtotime($site['date_expiration'])); ?></div>
                </div>

                <div class="site-actions-col">
                    <a href="site-detail.php?id=<?php echo $site['id']; ?>" class="btn btn-primary btn-sm">👁 Voir</a>
                    <?php if ($expired || $expiring): ?>
                        <a href="renew-site.php?id=<?php echo $site['id']; ?>" class="btn btn-sm" style="background:var(--success-bg);color:#065f46;border:1px solid #a7f3d0">🔄 Renouveler</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($stats['total_sites'] > 10): ?>
            <div style="text-align:center;margin-top:16px">
                <a href="sites.php" class="btn btn-secondary">Voir tous les sites →</a>
            </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📡</div>
            <div class="empty-title">Aucun site pour le moment</div>
            <div class="empty-text">Créez votre premier site pour commencer à collecter des données WiFi.</div>
            <a href="add-site.php" class="btn btn-primary">+ Créer mon premier site</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php renderFooter(); ?>
