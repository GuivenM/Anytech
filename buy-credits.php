<?php
/**
 * buy-credits.php – VERSION 2.1
 * Ajout : passage du site_id dans le formulaire pour auto-renouvellement post-paiement.
 */

require_once 'auth-check.php';
require_once 'layout.php';

$pdo = getDBConnection();

// Si l'utilisateur arrive depuis renew-site.php sans crédits
$redirect_site_id = (int)($_GET['site_id'] ?? 0);
$context_renew    = $redirect_site_id > 0;

// Vérifier que le site appartient bien à ce propriétaire
$context_site = null;
if ($context_renew) {
    $s = $pdo->prepare("SELECT id, nom_site, date_expiration FROM routeurs WHERE id = :id AND proprietaire_id = :pid");
    $s->execute([':id' => $redirect_site_id, ':pid' => $proprietaire_id]);
    $context_site = $s->fetch();
    if (!$context_site) {
        $redirect_site_id = 0;
        $context_renew    = false;
    }
}

$stmt_stats = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM routeurs WHERE proprietaire_id = :pid1) as total_sites,
        (SELECT COUNT(*) FROM routeurs WHERE proprietaire_id = :pid2 AND date_expiration >= CURDATE()) as sites_actifs,
        (SELECT COALESCE(SUM(credits), 0) FROM transactions_credits WHERE proprietaire_id = :pid3 AND type = 'achat') as total_achetes,
        (SELECT COALESCE(SUM(credits), 0) FROM transactions_credits WHERE proprietaire_id = :pid4 AND type = 'utilisation') as total_utilises
");
$stmt_stats->execute([':pid1'=>$proprietaire_id,':pid2'=>$proprietaire_id,':pid3'=>$proprietaire_id,':pid4'=>$proprietaire_id]);
$stats = $stmt_stats->fetch();

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

$packages = $pdo->query("SELECT * FROM tarifs_credits WHERE actif = TRUE ORDER BY montant_fcfa")->fetchAll();

$credit = ($proprietaire_solde < 2) ? 'Crédit' : 'Crédits';

// Message d'erreur de process-payment
$payment_error = $_SESSION['payment_error'] ?? null;
unset($_SESSION['payment_error']);

renderHeader('credits', 'Crédits');
?>

<!-- ERROR ALERT -->
<?php if ($payment_error): ?>
<div class="alert alert-error" data-auto-hide style="margin-bottom:20px">
    ❌ <?php echo htmlspecialchars($payment_error); ?>
</div>
<?php endif; ?>

<!-- RENEW CONTEXT BANNER -->
<?php if ($context_renew && $context_site): ?>
<div class="renew-context-banner">
    <div class="renew-context-icon">🔄</div>
    <div>
        <strong>Renouvellement de "<?php echo htmlspecialchars($context_site['nom_site']); ?>"</strong><br>
        Vous n'avez pas assez de crédits. Achetez un pack ci-dessous — votre site sera renouvelé <strong>automatiquement</strong> après le paiement.
    </div>
</div>
<?php endif; ?>

<!-- HERO -->
<div class="credits-hero">
    <div class="hero-label">💳 Votre solde actuel</div>
    <div class="hero-balance"><?php echo number_format($proprietaire_solde) . ' ' . $credit; ?></div>
    <div class="hero-sub">1 crédit = 1 site pour 1 mois • Connexions illimitées</div>
</div>

<!-- PACKAGES -->
<?php if (!empty($packages)): ?>
<div style="margin-bottom:28px">
    <div style="font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:16px">
        Choisissez votre pack
    </div>
    <div class="packages-grid">
        <?php
        $first_shown = false;
        foreach ($packages as $pkg):
            $total_credits  = $pkg['credits'] + $pkg['credits_bonus'];
            $prix_par_credit = $pkg['montant_fcfa'] / $total_credits;
            $economie       = ($total_credits * 2000) - $pkg['montant_fcfa'];
            $pkg_credits    = $total_credits > 1 ? 'crédits' : 'crédit';

            // In renew context, highlight the cheapest pack (first)
            $is_context_highlight = $context_renew && !$first_shown;
            $first_shown = true;
        ?>
        <div class="package-card <?php echo $is_context_highlight ? 'context-highlight' : ($pkg['recommande'] ? 'recommended' : ''); ?>">

            <?php if ($is_context_highlight && $context_renew): ?>
                <div class="context-badge">🔄 Pour renouveler</div>
            <?php elseif ($pkg['recommande']): ?>
                <div class="rec-badge">⭐ Recommandé</div>
            <?php endif; ?>

            <div class="pkg-name"><?php echo htmlspecialchars($pkg['nom']); ?></div>
            <div class="pkg-price"><?php echo number_format($pkg['montant_fcfa'], 0, '', ' '); ?> <span class="pkg-currency">FCFA</span></div>
            <div class="pkg-credits">
                <?php echo $pkg['credits'] . ' ' . $pkg_credits; ?>
                <?php echo $pkg['credits_bonus'] > 0 ? " + {$pkg['credits_bonus']} bonus" : ''; ?>
            </div>

            <?php if ($pkg['credits_bonus'] > 0): ?>
                <div class="pkg-bonus">🎁 +<?php echo $pkg['credits_bonus']; ?> crédit<?php echo $pkg['credits_bonus']>1?'s':''; ?> offert<?php echo $pkg['credits_bonus']>1?'s':''; ?> !</div>
            <?php endif; ?>

            <div class="package-details">
                <div class="package-detail">
                    <span>Total crédits :</span>
                    <strong><?php echo $total_credits . ' ' . $pkg_credits; ?></strong>
                </div>
                <div class="package-detail">
                    <span>Prix par crédit :</span>
                    <strong><?php echo number_format($prix_par_credit, 0); ?> FCFA</strong>
                </div>
                <div class="package-detail">
                    <span>Équivalent :</span>
                    <strong><?php echo $total_credits; ?> mois de site</strong>
                </div>
            </div>

            <?php if ($economie > 0): ?>
                <div class="package-economy">
                    💰 Économie : <?php echo number_format($economie, 0, '', ' '); ?> FCFA
                </div>
            <?php endif; ?>

            <form action="process-payment.php" method="POST"
                  onsubmit="this.querySelector('.btn-buy').classList.add('loading');
                            this.querySelector('.btn-buy').textContent='Connexion au paiement…'">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="package_id" value="<?php echo $pkg['id']; ?>">
                <?php if ($redirect_site_id): ?>
                    <input type="hidden" name="site_id" value="<?php echo $redirect_site_id; ?>">
                <?php endif; ?>
                <button type="submit" class="btn-buy">
                    <?php if ($is_context_highlight && $context_renew): ?>
                        🔄 Acheter &amp; Renouveler
                    <?php else: ?>
                        💳 Acheter maintenant
                    <?php endif; ?>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- HOW IT WORKS -->
<div class="info-section">
    <div class="info-title">ℹ️ Comment ça marche ?</div>

    <div class="info-item">
        <div class="info-icon">1️⃣</div>
        <div class="info-text">
            <strong>Choisissez votre package</strong><br>
            Sélectionnez le nombre de crédits selon vos besoins. Plus vous achetez, plus vous économisez !
        </div>
    </div>

    <div class="info-item">
        <div class="info-icon">2️⃣</div>
        <div class="info-text">
            <strong>Payez par Mobile Money</strong><br>
            MTN MoMo ou Moov Money — vous serez redirigé vers la page de paiement sécurisée FedaPay.
        </div>
    </div>

    <div class="info-item">
        <div class="info-icon">3️⃣</div>
        <div class="info-text">
            <strong>Crédits ajoutés instantanément</strong><br>
            Dès confirmation du paiement, vos crédits sont crédités automatiquement. Aucune validation manuelle.
        </div>
    </div>

    <div class="info-item">
        <div class="info-icon">4️⃣</div>
        <div class="info-text">
            <strong>Utilisez vos crédits</strong><br>
            1 crédit = créez ou renouvelez 1 site pour 1 mois. Connexions illimitées !
        </div>
    </div>
</div>

<!-- ADVANTAGES -->
<div class="info-section">
    <div class="info-title">✨ Avantages</div>
    <div class="info-item"><div class="info-icon">✅</div><div class="info-text"><strong>Crédits valables indéfiniment</strong> — Pas de date d'expiration</div></div>
    <div class="info-item"><div class="info-icon">✅</div><div class="info-text"><strong>Connexions illimitées</strong> — Aucune limite d'utilisateurs par site</div></div>
    <div class="info-item"><div class="info-icon">✅</div><div class="info-text"><strong>Paiement instantané</strong> — Crédits disponibles dès la confirmation</div></div>
    <div class="info-item"><div class="info-icon">✅</div><div class="info-text"><strong>Bonus sur achats groupés</strong> — Plus vous achetez, plus vous économisez</div></div>
</div>

<!-- TRANSACTION HISTORY -->
<?php if (!empty($transactions)): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">📋 Historique des transactions</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Détail</th>
                    <th>Crédits</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td style="font-size:12px;font-family:'DM Mono',monospace">
                        <?php echo date('d/m/Y H:i', strtotime($tx['date_transaction'])); ?>
                    </td>
                    <td>
                        <span class="badge tx-<?php echo $tx['type']; ?>">
                            <?php echo ucfirst($tx['type']); ?>
                        </span>
                    </td>
                    <td style="font-size:13px;max-width:240px">
                        <?php echo htmlspecialchars($tx['description'] ?? '—'); ?>
                    </td>
                    <td>
                        <?php $sign = $tx['type'] === 'utilisation' ? '-' : '+'; ?>
                        <span class="<?php echo $tx['type'] === 'utilisation' ? 'tx-neg' : 'tx-pos'; ?>">
                            <?php echo $sign . $tx['credits']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($tx['statut_paiement'] === 'valide'): ?>
                            <span class="badge badge-success">Validé</span>
                        <?php elseif ($tx['statut_paiement'] === 'en_attente'): ?>
                            <span class="badge badge-warning">En attente</span>
                        <?php elseif ($tx['statut_paiement'] === 'echoue'): ?>
                            <span class="badge badge-danger">Échoué</span>
                        <?php else: ?>
                            <span class="badge">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php renderFooter(); ?>
