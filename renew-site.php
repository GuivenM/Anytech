<?php
/**
 * renew-site.php — VERSION 2.0
 */

require_once 'auth-check.php';
require_once 'layout.php';

$pdo = getDBConnection();

$site_id = (int)($_GET['id'] ?? 0);
if (!$site_id) { header('Location: dashboard.php'); exit(); }

$stmt = $pdo->prepare("SELECT * FROM routeurs WHERE id = :id AND proprietaire_id = :pid");
$stmt->execute([':id' => $site_id, ':pid' => $proprietaire_id]);
$site = $stmt->fetch();
if (!$site) { header('Location: dashboard.php'); exit(); }

$stmt = $pdo->prepare("SELECT solde_credits FROM proprietaires WHERE id = :id");
$stmt->execute([':id' => $proprietaire_id]);
$solde_credits = $stmt->fetch()['solde_credits'];

$date_exp      = strtotime($site['date_expiration']);
$aujourd_hui   = strtotime(date('Y-m-d'));
$jours_restants= (int)ceil(($date_exp - $aujourd_hui) / 86400);
$is_expired    = $jours_restants < 0;

$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_renew'])) {
    if (!csrf_verify()) {
        $message = 'Requête invalide. Veuillez réessayer.'; $message_type = 'error';
    } elseif ($solde_credits < 1) {
        $message = 'Crédits insuffisants pour renouveler ce site.'; $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("CALL sp_creer_ou_renouveler_site(
                :pid, :sid, NULL, NULL, NULL,
                @routeur_id, @code_unique, @date_expiration, @success, @message
            )");
            $stmt->execute([':pid' => $proprietaire_id, ':sid' => $site_id]);
            $result = $pdo->query("SELECT @routeur_id as routeur_id, @code_unique as code_unique,
                @date_expiration as date_expiration, @success as success, @message as message")->fetch();

            if ($result['success']) {
                header("Location: site-detail.php?id={$site_id}&renewed=1");
                exit();
            } else {
                $message = $result['message']; $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Erreur lors du renouvellement : ' . $e->getMessage(); $message_type = 'error';
        }
    }
}

renderHeader('sites', 'Renouveler un site');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🔄 Renouveler le site</h1>
        <p class="page-subtitle">Prolongez l'accès pour 30 jours supplémentaires</p>
    </div>
    <a href="site-detail.php?id=<?php echo $site_id; ?>" class="btn btn-secondary">← Retour</a>
</div>

<div class="renew-layout">

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" data-auto-hide>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Site info -->
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <span class="card-title">📡 Informations du site</span>
            <?php if ($is_expired): ?>
                <span class="badge badge-danger">❌ Expiré</span>
            <?php else: ?>
                <span class="badge badge-warning">⚠️ <?php echo $jours_restants; ?> jour(s) restant<?php echo $jours_restants>1?'s':''; ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="site-summary">
                <div class="summary-row">
                    <span class="summary-row-label">Site</span>
                    <span class="summary-row-val"><?php echo htmlspecialchars($site['nom_site']); ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-row-label">Code</span>
                    <code style="font-family:'DM Mono',monospace;background:var(--surface-3);padding:3px 8px;border-radius:4px;font-size:13px">
                        <?php echo htmlspecialchars($site['code_unique']); ?>
                    </code>
                </div>
                <div class="summary-row">
                    <span class="summary-row-label">Date d'expiration actuelle</span>
                    <span class="summary-row-val"><?php echo date('d/m/Y', strtotime($site['date_expiration'])); ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-row-label">Nouvelle date après renouvellement</span>
                    <span class="summary-row-val" style="color:var(--success)">
                        <?php
                        $base = $is_expired ? time() : $date_exp;
                        echo date('d/m/Y', $base + 30 * 86400);
                        ?>
                    </span>
                </div>
            </div>

            <div class="cost-row">
                <span class="cost-row-label">💰 Coût du renouvellement</span>
                <span class="cost-row-val">1 crédit</span>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-md);padding:14px 18px;margin-bottom:20px">
                <div style="font-size:14px;color:var(--text-secondary);font-weight:500">Votre solde actuel</div>
                <div style="font-size:24px;font-weight:700;font-family:'DM Mono',monospace;color:var(--text-primary)"><?php echo $solde_credits; ?> crédit<?php echo $solde_credits>1?'s':''; ?></div>
            </div>

            <?php if ($solde_credits < 1): ?>
            <div class="alert alert-warning" style="margin-bottom:20px">
                ⚠️ Crédits insuffisants. Achetez des crédits pour renouveler ce site.
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <a href="credits.php" class="btn btn-primary" style="justify-content:center">➕ Acheter des crédits</a>
                <a href="dashboard.php"   class="btn btn-secondary" style="justify-content:center">← Retour</a>
            </div>
            <?php else: ?>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <button type="submit" name="confirm_renew" value="1"
                        class="btn btn-primary btn-lg" style="justify-content:center;background:linear-gradient(135deg,#10b981,#059669)">
                        ✅ Confirmer le renouvellement
                    </button>
                    <a href="site-detail.php?id=<?php echo $site_id; ?>"
                       class="btn btn-secondary btn-lg" style="justify-content:center">
                        ✕ Annuler
                    </a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info note -->
    <div class="alert alert-info">
        ℹ️ <strong>Note :</strong> Si le site est expiré, le renouvellement repart de la date d'aujourd'hui. Sinon, 30 jours s'ajoutent à la date d'expiration actuelle.
    </div>

</div>

<?php renderFooter(); ?>
