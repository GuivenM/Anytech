<?php
/**
 * add-site.php — VERSION 2.0
 */

require_once 'auth-check.php';
require_once 'layout.php';

$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT solde_credits FROM proprietaires WHERE id = :id");
$stmt->execute([':id' => $proprietaire_id]);
$solde_credits = $stmt->fetch()['solde_credits'];
$peut_ajouter  = $solde_credits >= 1;

$message      = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $peut_ajouter) {
    // CSRF check
    if (!csrf_verify()) {
        $message      = 'Requête invalide. Veuillez réessayer.';
        $message_type = 'error';
    } else {
        $nom_site    = trim($_POST['nom_site']    ?? '');
        $adresse     = trim($_POST['adresse']     ?? '');
        $ville       = trim($_POST['ville']       ?? '');
        $ip_mikrotik = trim($_POST['ip_mikrotik'] ?? '');

        if (empty($nom_site) || strlen($nom_site) < 3) {
            $message      = 'Le nom du site doit contenir au moins 3 caractères.';
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("CALL sp_creer_ou_renouveler_site(
                    :proprietaire_id, NULL, :nom_site, :adresse, :ville,
                    @routeur_id, @code_unique, @date_expiration, @success, @message
                )");
                $stmt->execute([
                    ':proprietaire_id' => $proprietaire_id,
                    ':nom_site'        => $nom_site,
                    ':adresse'         => $adresse,
                    ':ville'           => $ville,
                ]);

                $result = $pdo->query("SELECT @routeur_id as routeur_id, @code_unique as code_unique,
                    @date_expiration as date_expiration, @success as success, @message as message")->fetch();

                if ($result['success']) {
                    header("Location: site-detail.php?id={$result['routeur_id']}&new=1");
                    exit();
                } else {
                    $message      = $result['message'];
                    $message_type = 'error';
                }
            } catch (PDOException $e) {
                $message      = 'Erreur lors de la création : ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

renderHeader('sites', 'Ajouter un site');
?>

<style>
    .add-site-layout {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 24px;
        align-items: start;
    }

    .info-block {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        padding: 16px;
        background: var(--info-bg);
        border: 1px solid #bfdbfe;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        font-size: 14px;
        color: #1e40af;
    }

    .info-block-icon { font-size: 20px; flex-shrink: 0; }

    .step-list { display: flex; flex-direction: column; gap: 12px; }
    .step-item {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        font-size: 14px;
        color: var(--text-secondary);
    }
    .step-num {
        width: 26px;
        height: 26px;
        background: linear-gradient(135deg, var(--brand-from), var(--brand-to));
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .balance-card {
        background: linear-gradient(135deg, var(--brand-from), var(--brand-to));
        border-radius: var(--radius-lg);
        padding: 24px;
        color: white;
        text-align: center;
        margin-bottom: 16px;
    }
    .balance-val {
        font-size: 52px;
        font-weight: 700;
        font-family: 'DM Mono', monospace;
        letter-spacing: -2px;
        line-height: 1;
        margin: 8px 0;
    }
    .balance-lbl { font-size: 12px; opacity: 0.75; text-transform: uppercase; letter-spacing: 0.8px; }

    @media (max-width: 900px) {
        .add-site-layout { grid-template-columns: 1fr; }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">➕ Ajouter un site</h1>
        <p class="page-subtitle">Créez un nouveau point d'accès WiFi</p>
    </div>
    <a href="sites.php" class="btn btn-secondary">← Retour aux sites</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" data-auto-hide>
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="add-site-layout">
    <!-- FORM -->
    <div>
        <?php if (!$peut_ajouter): ?>
        <div class="card">
            <div class="card-body" style="text-align:center;padding:48px">
                <div style="font-size:64px;margin-bottom:16px">💳</div>
                <h2 style="font-size:20px;font-weight:700;margin-bottom:8px;color:var(--text-primary)">Crédits insuffisants</h2>
                <p style="color:var(--text-secondary);font-size:14px;margin-bottom:8px">
                    Vous n'avez pas de crédits disponibles pour créer un site.
                </p>
                <p style="color:var(--text-muted);font-size:13px;margin-bottom:24px">
                    Coût : <strong>1 crédit = 1 site actif pour 1 mois</strong> (connexions illimitées)
                </p>
                <a href="buy-credits.php" class="btn btn-primary btn-lg">➕ Acheter des crédits</a>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title">📋 Informations du site</span>
            </div>
            <div class="card-body">
                <div class="info-block">
                    <span class="info-block-icon">💡</span>
                    <div>
                        La création coûte <strong>1 crédit</strong> et active le site pour <strong>30 jours</strong>.
                        Un code unique (ex: RTR-2025-001) sera généré automatiquement.
                    </div>
                </div>

                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label class="form-label">Nom du site <span style="color:var(--danger)">*</span></label>
                        <input type="text" class="form-control" name="nom_site" required
                            placeholder="Ex: Restaurant La Palmeraie"
                            value="<?php echo htmlspecialchars($_POST['nom_site'] ?? ''); ?>">
                        <p class="form-hint">Nom de votre établissement ou emplacement (min. 3 caractères)</p>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Ville</label>
                            <input type="text" class="form-control" name="ville"
                                placeholder="Ex: Cotonou"
                                value="<?php echo htmlspecialchars($_POST['ville'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">IP du routeur MikroTik</label>
                            <input type="text" class="form-control" name="ip_mikrotik"
                                placeholder="Ex: 192.168.88.1"
                                value="<?php echo htmlspecialchars($_POST['ip_mikrotik'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adresse complète</label>
                        <input type="text" class="form-control" name="adresse"
                            placeholder="Ex: 123 Rue de la République, Cotonou"
                            value="<?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?>">
                    </div>

                    <div class="divider"></div>

                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
                        <div style="font-size:13.5px;color:var(--text-secondary)">
                            Solde après création : <strong style="color:var(--brand-from)"><?php echo $solde_credits - 1; ?> crédit<?php echo ($solde_credits-1)>1?'s':''; ?></strong>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg">
                            ✅ Créer le site (1 crédit)
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- SIDEBAR -->
    <aside>
        <!-- Balance -->
        <div class="balance-card">
            <div class="balance-lbl">Votre solde</div>
            <div class="balance-val"><?php echo $solde_credits; ?></div>
            <div style="font-size:14px;opacity:0.8">crédit<?php echo $solde_credits>1?'s':''; ?></div>
        </div>
        <?php if ($solde_credits < 3): ?>
            <a href="buy-credits.php" class="btn btn-secondary" style="width:100%;justify-content:center;margin-bottom:16px">
                ➕ Acheter des crédits
            </a>
        <?php endif; ?>

        <!-- How it works -->
        <div class="card">
            <div class="card-header"><span class="card-title">📖 Comment ça marche</span></div>
            <div class="card-body">
                <div class="step-list">
                    <div class="step-item">
                        <div class="step-num">1</div>
                        <div>Remplissez les informations de votre site</div>
                    </div>
                    <div class="step-item">
                        <div class="step-num">2</div>
                        <div>Un code unique est généré automatiquement</div>
                    </div>
                    <div class="step-item">
                        <div class="step-num">3</div>
                        <div>1 crédit est déduit → site actif 30 jours</div>
                    </div>
                    <div class="step-item">
                        <div class="step-num">4</div>
                        <div>Installez la page de login sur votre routeur</div>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</div>

<?php renderFooter(); ?>
