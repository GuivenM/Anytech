<?php
/**
 * credits.php — VERSION 3.0
 * Fusion de credits.php et buy-credits.php.
 * Toutes les références à buy-credits.php doivent pointer ici.
 * Les liens internes (dashboard, renew-site, layout nav) utilisent credits.php.
 */

require_once 'auth-check.php';
require_once 'layout.php';

$pdo = getDBConnection();

// ── Contexte renouvellement (venant de renew-site.php sans crédits) ──────────
$redirect_site_id = (int)($_GET['site_id'] ?? 0);
$context_renew    = $redirect_site_id > 0;
$context_site     = null;

if ($context_renew) {
    $s = $pdo->prepare("SELECT id, nom_site, date_expiration, DATEDIFF(date_expiration, CURDATE()) as jours
                        FROM routeurs WHERE id = :id AND proprietaire_id = :pid");
    $s->execute([':id' => $redirect_site_id, ':pid' => $proprietaire_id]);
    $context_site = $s->fetch();
    if (!$context_site) { $redirect_site_id = 0; $context_renew = false; }
}

// ── Stats crédits ────────────────────────────────────────────────────────────
$stmt_stats = $pdo->prepare("
    SELECT
        (SELECT COUNT(*)           FROM routeurs            WHERE proprietaire_id = :p1) as total_sites,
        (SELECT COUNT(*)           FROM routeurs            WHERE proprietaire_id = :p2 AND date_expiration >= CURDATE()) as sites_actifs,
        (SELECT COALESCE(SUM(credits),0) FROM transactions_credits WHERE proprietaire_id = :p3 AND type = 'achat')       as total_achetes,
        (SELECT COALESCE(SUM(credits),0) FROM transactions_credits WHERE proprietaire_id = :p4 AND type = 'utilisation') as total_utilises
");
$stmt_stats->execute([':p1'=>$proprietaire_id,':p2'=>$proprietaire_id,':p3'=>$proprietaire_id,':p4'=>$proprietaire_id]);
$stats = $stmt_stats->fetch();

// ── Transactions (50 dernières) ───────────────────────────────────────────────
$stmt_tx = $pdo->prepare("
    SELECT t.*, r.nom_site, r.code_unique
    FROM transactions_credits t
    LEFT JOIN routeurs r ON t.routeur_id = r.id
    WHERE t.proprietaire_id = :id
    ORDER BY t.date_transaction DESC
    LIMIT 50
");
$stmt_tx->execute([':id' => $proprietaire_id]);
$transactions = $stmt_tx->fetchAll();

// ── Packages ──────────────────────────────────────────────────────────────────
$packages = $pdo->query("SELECT * FROM tarifs_credits WHERE actif = TRUE ORDER BY montant_fcfa")->fetchAll();

// ── Message d'erreur paiement ─────────────────────────────────────────────────
$payment_error = $_SESSION['payment_error'] ?? null;
unset($_SESSION['payment_error']);

$credit_label = $proprietaire_solde < 2 ? 'Crédit' : 'Crédits';

renderHeader('credits', 'Crédits');
?>

<!-- ══════════════════════════════════════════════
     HERO SOLDE
══════════════════════════════════════════════ -->
<div class="page-header">
    <div>
        <h1 class="page-title">💳 Crédits</h1>
        <p class="page-subtitle">
            Solde actuel :
            <strong style="color:var(--text-primary)">
                <?php echo number_format($proprietaire_solde); ?>
                <?php echo $credit_label; ?>
            </strong>
            &bull; 1 crédit = 1 site pour 1 mois
        </p>
    </div>
    <a href="#packages" class="btn btn-primary">+ Acheter des crédits</a>
</div>

<!-- ══════════════════════════════════════════════
     ERREUR PAIEMENT
══════════════════════════════════════════════ -->
<?php if ($payment_error): ?>
<div class="alert alert-error" data-auto-hide>
    ❌ <?php echo htmlspecialchars($payment_error); ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     CONTEXTE RENOUVELLEMENT
══════════════════════════════════════════════ -->
<?php if ($context_renew && $context_site): ?>
<div class="renew-context-banner">
    <div class="renew-context-icon">🔄</div>
    <div>
        <strong>Renouvellement de "<?php echo htmlspecialchars($context_site['nom_site']); ?>"</strong><br>
        <?php
        $j = (int)$context_site['jours'];
        if ($j < 0)      echo 'Ce site est <strong>expiré</strong>.';
        elseif ($j === 0) echo 'Ce site expire <strong>aujourd\'hui</strong>.';
        else              echo 'Ce site expire dans <strong>' . $j . ' jour' . ($j > 1 ? 's' : '') . '</strong>.';
        ?>
        Achetez un pack ci-dessous — le site sera renouvelé <strong>automatiquement</strong> après le paiement.
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     STATS RAPIDES
══════════════════════════════════════════════ -->
<div class="stats-grid" style="margin-bottom:28px">
    <div class="stat-card stat-card-success">
        <div class="stat-icon-wrap success">📈</div>
        <div class="stat-value"><?php echo number_format($stats['total_achetes'] ?? 0); ?></div>
        <div class="stat-label">Crédits achetés</div>
    </div>
    <div class="stat-card stat-card-danger">
        <div class="stat-icon-wrap danger">📉</div>
        <div class="stat-value"><?php echo number_format($stats['total_utilises'] ?? 0); ?></div>
        <div class="stat-label">Crédits utilisés</div>
    </div>
    <div class="stat-card stat-card-brand">
        <div class="stat-icon-wrap brand">📡</div>
        <div class="stat-value"><?php echo $stats['sites_actifs']; ?><span style="font-size:14px;color:var(--text-muted);font-weight:400"> / <?php echo $stats['total_sites']; ?></span></div>
        <div class="stat-label">Sites actifs</div>
    </div>
    <div class="stat-card stat-card-warning">
        <div class="stat-icon-wrap warning">⚖️</div>
        <div class="stat-value"><?php echo number_format(($stats['total_achetes'] ?? 0) - ($stats['total_utilises'] ?? 0)); ?></div>
        <div class="stat-label">Solde net</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     PACKAGES
══════════════════════════════════════════════ -->
<?php if (!empty($packages)): ?>
<div id="packages" style="scroll-margin-top:80px">
    <div class="page-header" style="margin-bottom:16px">
        <div>
            <h2 class="page-title" style="font-size:20px">💰 Acheter des crédits</h2>
            <p class="page-subtitle">Choisissez le pack adapté à vos besoins</p>
        </div>
    </div>

    <div class="packages-grid">
        <?php
        $first = true;
        foreach ($packages as $pkg):
            $total_credits   = $pkg['credits'] + $pkg['credits_bonus'];
            $prix_par_credit = $pkg['montant_fcfa'] / $total_credits;
            $economie        = ($total_credits * 2000) - $pkg['montant_fcfa'];
            $pkg_credits_lbl = $total_credits > 1 ? 'crédits' : 'crédit';
            $is_context_hl   = $context_renew && $first;
            $first = false;
        ?>
        <div class="package-card <?php echo $is_context_hl ? 'context-highlight' : ($pkg['recommande'] ? 'recommended' : ''); ?>">

            <?php if ($is_context_hl && $context_renew): ?>
                <div class="context-badge">🔄 Pour renouveler</div>
            <?php elseif ($pkg['recommande']): ?>
                <div class="rec-badge">⭐ Recommandé</div>
            <?php endif; ?>

            <div class="pkg-name"><?php echo htmlspecialchars($pkg['nom']); ?></div>
            <div class="pkg-price">
                <?php echo number_format($pkg['montant_fcfa'], 0, '', ' '); ?>
                <span class="pkg-currency">FCFA</span>
            </div>
            <div class="pkg-credits">
                <?php echo $pkg['credits'] . ' ' . $pkg_credits_lbl; ?>
                <?php echo $pkg['credits_bonus'] > 0 ? " + {$pkg['credits_bonus']} bonus" : ''; ?>
            </div>

            <?php if ($pkg['credits_bonus'] > 0): ?>
            <div class="pkg-bonus">🎁 +<?php echo $pkg['credits_bonus']; ?> crédit<?php echo $pkg['credits_bonus'] > 1 ? 's' : ''; ?> offert<?php echo $pkg['credits_bonus'] > 1 ? 's' : ''; ?> !</div>
            <?php endif; ?>

            <div class="package-details">
                <div class="package-detail">
                    <span>Total crédits</span>
                    <strong><?php echo $total_credits . ' ' . $pkg_credits_lbl; ?></strong>
                </div>
                <div class="package-detail">
                    <span>Prix / crédit</span>
                    <strong><?php echo number_format($prix_par_credit, 0); ?> FCFA</strong>
                </div>
                <div class="package-detail">
                    <span>Équivalent</span>
                    <strong><?php echo $total_credits; ?> mois de site</strong>
                </div>
            </div>

            <?php if ($economie > 0): ?>
            <div class="package-economy">💰 Économie : <?php echo number_format($economie, 0, '', ' '); ?> FCFA</div>
            <?php endif; ?>

            <form action="process-payment.php" method="POST"
                  onsubmit="this.querySelector('button').disabled=true;
                            this.querySelector('button').textContent='Connexion au paiement…'">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="package_id" value="<?php echo $pkg['id']; ?>">
                <?php if ($redirect_site_id): ?>
                <input type="hidden" name="site_id" value="<?php echo $redirect_site_id; ?>">
                <?php endif; ?>
                <button type="submit" class="btn-buy">
                    <?php echo ($is_context_hl && $context_renew) ? '🔄 Acheter &amp; Renouveler' : '💳 Acheter maintenant'; ?>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     COMMENT ÇA MARCHE
══════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <span class="card-title">ℹ️ Comment ça marche ?</span>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px">
            <?php
            $steps = [
                ['1️⃣', 'Choisissez un pack',         'Sélectionnez le nombre de crédits. Plus vous achetez, plus vous économisez.'],
                ['2️⃣', 'Payez par Mobile Money',      'MTN MoMo ou Moov Money — paiement sécurisé via FedaPay.'],
                ['3️⃣', 'Crédits ajoutés en temps réel','Dès confirmation, vos crédits sont disponibles. Aucune attente.'],
                ['4️⃣', 'Activez vos sites',           '1 crédit = 1 site actif pendant 1 mois. Connexions illimitées.'],
            ];
            foreach ($steps as [$icon, $title, $text]): ?>
            <div style="text-align:center;padding:8px">
                <div style="font-size:28px;margin-bottom:10px"><?php echo $icon; ?></div>
                <div style="font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:6px"><?php echo $title; ?></div>
                <div style="font-size:13px;color:var(--text-secondary);line-height:1.6"><?php echo $text; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     HISTORIQUE DES TRANSACTIONS
══════════════════════════════════════════════ -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📋 Historique des transactions</span>
        <span class="badge badge-neutral"><?php echo count($transactions); ?> entrée<?php echo count($transactions) > 1 ? 's' : ''; ?></span>
    </div>

    <?php if (!empty($transactions)): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Montant</th>
                    <th>Crédits</th>
                    <th>Solde après</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td style="font-size:12px;color:var(--text-secondary);font-family:'DM Mono',monospace;white-space:nowrap">
                        <?php echo date('d/m/Y', strtotime($t['date_transaction'])); ?><br>
                        <span style="opacity:.6"><?php echo date('H:i', strtotime($t['date_transaction'])); ?></span>
                    </td>
                    <td>
                        <span class="badge tx-<?php echo $t['type']; ?>">
                            <?php echo ucfirst($t['type']); ?>
                        </span>
                    </td>
                    <td style="font-size:13px;color:var(--text-secondary);max-width:220px">
                        <?php echo htmlspecialchars($t['description'] ?? '—'); ?>
                        <?php if ($t['nom_site']): ?>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;font-family:'DM Mono',monospace">
                                📡 <?php echo htmlspecialchars($t['nom_site']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="font-family:'DM Mono',monospace;font-size:13px;white-space:nowrap">
                        <?php echo $t['montant_fcfa'] ? number_format($t['montant_fcfa'], 0, '', ' ') . ' FCFA' : '—'; ?>
                    </td>
                    <td>
                        <?php if (in_array($t['type'], ['achat', 'bonus', 'remboursement'])): ?>
                            <span class="tx-pos">+<?php echo $t['credits']; ?></span>
                        <?php else: ?>
                            <span class="tx-neg">−<?php echo $t['credits']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-family:'DM Mono',monospace;font-weight:700">
                        <?php echo $t['solde_apres'] !== null ? $t['solde_apres'] : '—'; ?>
                    </td>
                    <td>
                        <?php
                        $statut = $t['statut_paiement'] ?? null;
                        if ($statut === 'valide'):   ?><span class="badge badge-success">Validé</span>
                        <?php elseif ($statut === 'en_attente'): ?><span class="badge badge-warning">En attente</span>
                        <?php elseif ($statut === 'echoue'):   ?><span class="badge badge-danger">Échoué</span>
                        <?php elseif ($statut === 'rembourse'): ?><span class="badge badge-info">Remboursé</span>
                        <?php else: ?><span class="badge badge-neutral">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">🔭</div>
        <div class="empty-title">Aucune transaction pour le moment</div>
        <div class="empty-text">Vos achats de crédits et utilisations apparaîtront ici.</div>
        <a href="#packages" class="btn btn-primary">+ Acheter des crédits</a>
    </div>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>