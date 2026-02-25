<?php
/**
 * cron-check-expirations.php
 * Tâche CRON quotidienne — Désactivation des sites expirés + renouvellement auto
 *
 * Usage crontab (exécuter chaque jour à 02:00) :
 *   0 2 * * * php /var/www/html/cron-check-expirations.php >> /var/www/html/logs/cron.log 2>&1
 *
 * Pour tester manuellement :
 *   php cron-check-expirations.php
 *   php cron-check-expirations.php --dry-run   (simulation sans modifications)
 */

// -------------------------------------------------------
// CONFIGURATION
// -------------------------------------------------------
define('CRON_VERSION', '1.0.0');
define('LOG_DIR', __DIR__ . '/logs');
define('LOG_FILE', LOG_DIR . '/cron-expirations.log');

// Dry-run mode (--dry-run arg or GET param for debug)
$dry_run = in_array('--dry-run', $argv ?? [], true)
        || (php_sapi_name() !== 'cli' && isset($_GET['dry_run']));

// Security: restrict web access to localhost only
if (php_sapi_name() !== 'cli') {
    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowed_ips = ['127.0.0.1', '::1'];
    if (!in_array($remote_ip, $allowed_ips, true)) {
        http_response_code(403);
        die('Accès refusé. Ce script est réservé au cron serveur.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// -------------------------------------------------------
// LOGGING
// -------------------------------------------------------
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

function logLine(string $level, string $msg): void {
    $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), strtoupper($level), $msg);
    echo $line;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function logInfo(string $msg): void  { logLine('INFO',    $msg); }
function logWarn(string $msg): void  { logLine('WARNING', $msg); }
function logError(string $msg): void { logLine('ERROR',   $msg); }
function logOk(string $msg): void    { logLine('OK',      $msg); }

// -------------------------------------------------------
// START
// -------------------------------------------------------
$start_time = microtime(true);
logInfo("========================================");
logInfo("ANYTECH Cron v" . CRON_VERSION . " démarré" . ($dry_run ? " [DRY-RUN]" : ""));

// -------------------------------------------------------
// DB CONNECTION
// -------------------------------------------------------
define('DB_HOST', 'greentp171.mysql.db:3306');
define('DB_NAME', 'greentp171');
define('DB_USER', 'greentp171');
define('DB_PASS', 'TTp38xZVR5NS');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    logInfo("Connexion DB établie");
} catch (PDOException $e) {
    logError("Connexion DB échouée : " . $e->getMessage());
    exit(1);
}

// -------------------------------------------------------
// STEP 1 : IDENTIFY SITES EXPIRING SOON (for logging)
// -------------------------------------------------------
logInfo("--- Analyse des expirations ---");

$stmt = $pdo->prepare("
    SELECT r.id, r.nom_site, r.date_expiration, r.actif,
           DATEDIFF(r.date_expiration, CURDATE()) as jours_restants,
           p.nom_complet, p.email, p.solde_credits, r.auto_renouvellement
    FROM routeurs r
    INNER JOIN proprietaires p ON r.proprietaire_id = p.id
    WHERE r.date_expiration <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY r.date_expiration ASC
");
$stmt->execute();
$at_risk_sites = $stmt->fetchAll();

if (empty($at_risk_sites)) {
    logInfo("Aucun site à risque dans les 7 prochains jours");
} else {
    logInfo(count($at_risk_sites) . " site(s) à surveiller :");
    foreach ($at_risk_sites as $site) {
        $status = $site['jours_restants'] < 0 ? "EXPIRÉ depuis " . abs((int)$site['jours_restants']) . "j"
                : ($site['jours_restants'] == 0 ? "EXPIRE AUJOURD'HUI"
                : "expire dans {$site['jours_restants']}j");
        logInfo("  → [{$site['id']}] {$site['nom_site']} — {$status} — {$site['email']}");
    }
}

// -------------------------------------------------------
// STEP 2 : AUTO-RENEWAL (before deactivation)
// -------------------------------------------------------
logInfo("--- Renouvellement automatique ---");

$auto_renewed = 0;
$auto_failed  = 0;

$stmt_auto = $pdo->prepare("
    SELECT r.id, r.nom_site, r.proprietaire_id, r.date_expiration,
           DATEDIFF(r.date_expiration, CURDATE()) as jours_restants,
           p.solde_credits, p.nom_complet, p.email
    FROM routeurs r
    INNER JOIN proprietaires p ON r.proprietaire_id = p.id
    WHERE r.auto_renouvellement = 1
      AND r.actif = 1
      AND DATEDIFF(r.date_expiration, CURDATE()) <= 0
");
$stmt_auto->execute();
$auto_sites = $stmt_auto->fetchAll();

foreach ($auto_sites as $site) {
    $credits_needed = 1; // 1 crédit = 1 mois

    if ($site['solde_credits'] >= $credits_needed) {
        logInfo("Auto-renouvellement : [{$site['id']}] {$site['nom_site']} (propriétaire: {$site['nom_complet']})");

        if (!$dry_run) {
            try {
                $pdo->beginTransaction();

                // Extend expiration by 30 days from today (or from current expiration if not yet expired)
                $base_date = (strtotime($site['date_expiration']) < time())
                    ? 'CURDATE()'
                    : "'{$site['date_expiration']}'";

                $pdo->prepare("
                    UPDATE routeurs
                    SET date_expiration = DATE_ADD($base_date, INTERVAL 30 DAY),
                        actif = 1,
                        credits_utilises = credits_utilises + 1
                    WHERE id = :id
                ")->execute([':id' => $site['id']]);

                // Deduct credit
                $pdo->prepare("
                    UPDATE proprietaires
                    SET solde_credits = solde_credits - :credits
                    WHERE id = :id AND solde_credits >= :credits
                ")->execute([':credits' => $credits_needed, ':id' => $site['proprietaire_id']]);

                // Log transaction
                $pdo->prepare("
                    INSERT INTO transactions_credits
                        (proprietaire_id, routeur_id, type, credits, description, statut_paiement)
                    VALUES
                        (:pid, :rid, 'utilisation', :credits, 'Renouvellement automatique - 30 jours', 'approuve')
                ")->execute([
                    ':pid'     => $site['proprietaire_id'],
                    ':rid'     => $site['id'],
                    ':credits' => $credits_needed,
                ]);

                $pdo->commit();
                logOk("  → Renouvelé avec succès (+30j, -1 crédit)");
                $auto_renewed++;
            } catch (Exception $e) {
                $pdo->rollBack();
                logError("  → Échec renouvellement : " . $e->getMessage());
                $auto_failed++;
            }
        } else {
            logInfo("  → [DRY-RUN] Serait renouvelé (solde: {$site['solde_credits']} crédits)");
            $auto_renewed++;
        }
    } else {
        logWarn("  → [{$site['id']}] {$site['nom_site']} — crédits insuffisants ({$site['solde_credits']} disponibles, {$credits_needed} requis)");
        $auto_failed++;
    }
}

if (empty($auto_sites)) {
    logInfo("Aucun site avec renouvellement automatique activé");
}

// -------------------------------------------------------
// STEP 3 : DEACTIVATE EXPIRED SITES
// -------------------------------------------------------
logInfo("--- Désactivation des sites expirés ---");

if (!$dry_run) {
    try {
        // Use stored procedure if available
        try {
            $pdo->query("CALL sp_desactiver_sites_expires()");
            logOk("Procédure sp_desactiver_sites_expires() exécutée");
        } catch (PDOException $e) {
            // Fallback: direct UPDATE if stored procedure doesn't exist
            logWarn("Procédure stockée non disponible, utilisation du fallback SQL");
            $stmt_deactivate = $pdo->prepare("
                UPDATE routeurs
                SET actif = 0
                WHERE date_expiration < CURDATE()
                  AND actif = 1
                  AND auto_renouvellement = 0
            ");
            $stmt_deactivate->execute();
            $deactivated = $stmt_deactivate->rowCount();
            logOk("{$deactivated} site(s) désactivé(s)");
        }
    } catch (Exception $e) {
        logError("Erreur désactivation : " . $e->getMessage());
    }
} else {
    // Dry-run: count what would be deactivated
    $stmt_count = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM routeurs
        WHERE date_expiration < CURDATE() AND actif = 1 AND auto_renouvellement = 0
    ");
    $stmt_count->execute();
    $would_deactivate = $stmt_count->fetch()['cnt'];
    logInfo("[DRY-RUN] {$would_deactivate} site(s) seraient désactivés");
}

// -------------------------------------------------------
// STEP 4 : SUMMARY STATISTICS
// -------------------------------------------------------
logInfo("--- Statistiques globales ---");

$stats_query = $pdo->query("
    SELECT
        COUNT(*) as total_routeurs,
        SUM(actif = 1) as actifs,
        SUM(actif = 0) as inactifs,
        SUM(date_expiration < CURDATE()) as expires,
        SUM(date_expiration >= CURDATE() AND actif = 1) as en_cours,
        SUM(DATEDIFF(date_expiration, CURDATE()) BETWEEN 1 AND 7 AND actif = 1) as expire_bientot
    FROM routeurs
");
$stats = $stats_query->fetch();

logInfo("  Total sites      : {$stats['total_routeurs']}");
logInfo("  Actifs           : {$stats['actifs']}");
logInfo("  Inactifs         : {$stats['inactifs']}");
logInfo("  Expirés          : {$stats['expires']}");
logInfo("  En cours         : {$stats['en_cours']}");
logInfo("  Expirent < 7j    : {$stats['expire_bientot']}");
logInfo("  Auto-renouvelés  : {$auto_renewed}");
if ($auto_failed > 0) {
    logWarn("  Échecs auto-renew : {$auto_failed}");
}

// -------------------------------------------------------
// DONE
// -------------------------------------------------------
$elapsed = round(microtime(true) - $start_time, 3);
logInfo("Cron terminé en {$elapsed}s" . ($dry_run ? " [DRY-RUN - aucune modification effectuée]" : ""));
logInfo("========================================\n");

exit(0);
?>
