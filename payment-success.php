<?php
/**
 * payment-success.php
 * Page de confirmation affichée à l'utilisateur après un paiement FedaPay.
 *
 * FedaPay redirige ici avec ?id=FEDAPAY_TRANSACTION_ID
 * On vérifie le statut en base (le webhook a déjà dû le traiter).
 * Si le webhook est plus lent que le redirect, on poll la DB quelques fois.
 */

require_once 'auth-check.php';
require_once 'config.php';
require_once 'layout.php';

$pdo = getDBConnection();

$fedapay_id = (int)($_GET['id'] ?? 0);
$paiement   = null;
$status     = 'pending';

if ($fedapay_id) {
    // Attendre jusqu'à 6 secondes que le webhook ait traité (poll simple)
    for ($i = 0; $i < 6; $i++) {
        $stmt = $pdo->prepare("
            SELECT pf.*, tc.nom as package_nom, tc.credits, tc.credits_bonus
            FROM paiements_fedapay pf
            LEFT JOIN tarifs_credits tc ON pf.package_id = tc.id
            WHERE pf.fedapay_transaction_id = :fid
              AND pf.proprietaire_id = :pid
        ");
        $stmt->execute([':fid' => $fedapay_id, ':pid' => $proprietaire_id]);
        $paiement = $stmt->fetch();

        if ($paiement && $paiement['statut'] === 'approuve') {
            $status = 'approved';
            break;
        }
        if ($paiement && in_array($paiement['statut'], ['declined', 'canceled'])) {
            $status = 'failed';
            break;
        }
        sleep(1);
    }

    if (!$paiement) {
        // Transaction pas encore en base — peut être un accès direct invalide
        $status = 'unknown';
    } elseif ($status === 'pending') {
        // Webhook pas encore arrivé — on affiche un état intermédiaire
        $status = 'processing';
    }
}

// Recharger le solde frais
$solde_stmt = $pdo->prepare("SELECT solde_credits FROM proprietaires WHERE id = :id");
$solde_stmt->execute([':id' => $proprietaire_id]);
$solde_actuel = (int)$solde_stmt->fetchColumn();

$site_renouvele = $paiement && $paiement['site_renouvele'];
$site_id        = $paiement ? (int)$paiement['site_id_a_renouveler'] : 0;

renderHeader('credits', 'Paiement confirmé');
?>


<div class="success-wrapper" style="max-width: 580px;
        margin: 0 auto;
        text-align: center;
        padding: 16px 0 48px;">

    <!-- ── STATUS HERO ── -->
    <?php if ($status === 'approved'): ?>
    <div class="status-card approved">
        <span class="status-icon">🎉</span>
        <div class="status-title">Paiement confirmé !</div>
        <div class="status-sub">
            Vos crédits ont été ajoutés à votre compte.<br>
            Vous pouvez les utiliser immédiatement.
        </div>
        <div class="credits-badge">
            💳 <?php echo number_format($solde_actuel); ?> crédit<?php echo $solde_actuel !== 1 ? 's' : ''; ?> disponible<?php echo $solde_actuel !== 1 ? 's' : ''; ?>
        </div>
    </div>

    <?php elseif ($status === 'processing'): ?>
    <div class="status-card processing">
        <div class="processing-spinner"></div>
        <div class="status-title">Traitement en cours…</div>
        <div class="status-sub">
            Votre paiement a été reçu. Nous finalisons la mise à jour de votre compte.<br>
            <strong>Ne fermez pas cette page.</strong> Elle se rafraîchira automatiquement.
        </div>
    </div>
    <script>
        // Refresh toutes les 3s pendant 30s max
        let attempts = 0;
        const interval = setInterval(function() {
            attempts++;
            if (attempts >= 10) clearInterval(interval);
            location.reload();
        }, 3000);
    </script>

    <?php elseif ($status === 'failed'): ?>
    <div class="status-card failed">
        <span class="status-icon">❌</span>
        <div class="status-title">Paiement échoué</div>
        <div class="status-sub">
            Votre paiement n'a pas pu être traité.<br>
            Aucun montant n'a été débité. Vous pouvez réessayer.
        </div>
    </div>

    <?php else: ?>
    <div class="status-card unknown">
        <span class="status-icon">❓</span>
        <div class="status-title">Statut inconnu</div>
        <div class="status-sub">
            Nous n'avons pas pu vérifier votre transaction.<br>
            Si vous avez été débité, contactez le support.
        </div>
    </div>
    <?php endif; ?>


    <!-- ── RÉCAPITULATIF TRANSACTION ── -->
    <?php if ($paiement && $status === 'approved'): ?>
    <div class="summary-box">
        <div class="summary-box-title">Récapitulatif</div>
        <div class="summary-row">
            <span class="summary-row-label">Package</span>
            <span class="summary-row-val"><?php echo htmlspecialchars($paiement['package_nom']); ?></span>
        </div>
        <div class="summary-row">
            <span class="summary-row-label">Crédits obtenus</span>
            <span class="summary-row-val" style="color:var(--success)">+<?php echo $paiement['credits_a_crediter']; ?> crédits</span>
        </div>
        <div class="summary-row">
            <span class="summary-row-label">Montant payé</span>
            <span class="summary-row-val"><?php echo number_format($paiement['montant_fcfa'], 0, '', ' '); ?> FCFA</span>
        </div>
        <div class="summary-row">
            <span class="summary-row-label">Référence</span>
            <span class="summary-row-val" style="font-size:12px"><?php echo htmlspecialchars($paiement['internal_ref']); ?></span>
        </div>
    </div>

    <!-- Auto-renewal confirmation -->
    <?php if ($site_renouvele && $site_id): ?>
    <div class="renew-badge">
        ✅ <span>Votre site a été renouvelé automatiquement pour 30 jours supplémentaires.</span>
    </div>
    <?php elseif ($site_id && !$site_renouvele): ?>
    <div class="renew-badge" style="background:var(--warning-bg);border-color:#fde68a;color:#92400e;">
        ⚠️ <span>Le renouvellement automatique n'a pas pu s'effectuer. <a href="renew-site.php?id=<?php echo $site_id; ?>" style="color:#92400e;font-weight:700">Renouveler manuellement →</a></span>
    </div>
    <?php endif; ?>
    <?php endif; ?>


    <!-- ── ACTIONS ── -->
    <div class="actions-row">
        <?php if ($status === 'approved'): ?>
            <a href="dashboard.php" class="btn btn-primary btn-lg">🏠 Retour au Dashboard</a>
            <?php if ($site_id && !$site_renouvele): ?>
                <a href="renew-site.php?id=<?php echo $site_id; ?>" class="btn btn-lg" style="background:var(--success);color:white">🔄 Renouveler le site</a>
            <?php else: ?>
                <a href="credits.php" class="btn btn-secondary btn-lg">💳 Mes crédits</a>
            <?php endif; ?>

        <?php elseif ($status === 'processing'): ?>
            <a href="dashboard.php" class="btn btn-secondary btn-lg">🏠 Retour au Dashboard</a>

        <?php else: ?>
            <a href="credits.php" class="btn btn-primary btn-lg">🔄 Réessayer</a>
            <a href="dashboard.php"  class="btn btn-secondary btn-lg">🏠 Retour</a>
        <?php endif; ?>
    </div>

</div>

<?php renderFooter(); ?>
