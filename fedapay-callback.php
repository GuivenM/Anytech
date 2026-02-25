<?php
require_once __DIR__ . '/config.php';

function wlog(string $msg): void
{
    if (!is_dir(LOG_DIR)) mkdir(LOG_DIR, 0755, true);
    file_put_contents(LOG_FILE, '[' . date('Y-m-d H:i:s') . '][webhook] ' . $msg . PHP_EOL, FILE_APPEND);
}

$raw_body = file_get_contents('php://input');
$headers  = getallheaders();

wlog("Headers: " . json_encode($headers));
wlog("Body brut: [" . ($raw_body ?: 'VIDE') . "]");
wlog("POST: " . json_encode($_POST));
wlog("GET: " . json_encode($_GET));

// Fallback si php://input vide
if (empty($raw_body) && !empty($_POST)) {
    $raw_body = json_encode($_POST);
    wlog("Reconstruit depuis POST: $raw_body");
}

if (empty($raw_body) && !empty($_GET)) {
    $fid_get    = $_GET['id']     ?? null;
    $status_get = $_GET['status'] ?? null;
    if ($fid_get && $status_get) {
        $raw_body = json_encode(['v1/transaction' => ['id' => (int)$fid_get, 'status' => $status_get]]);
        wlog("Reconstruit depuis GET: $raw_body");
    }
}

wlog("Webhook reçu. IP=" . ($_SERVER['REMOTE_ADDR'] ?? '?') . " Body=" . substr($raw_body, 0, 200));

$signature_header = $headers['X-FEDAPAY-SIGNATURE'] ?? $headers['x-fedapay-signature'] ?? '';

if ($signature_header) {
    $parts = [];
    foreach (explode(',', $signature_header) as $part) {
        [$k, $v] = explode('=', $part, 2);
        $parts[$k] = $v;
    }
    $timestamp     = $parts['t'] ?? '';
    $received_hash = $parts['v1'] ?? '';
    $computed_hash = hash_hmac('sha256', $timestamp . '.' . $raw_body, FEDAPAY_SECRET_KEY);

    if (!hash_equals($computed_hash, $received_hash)) {
        wlog("SIGNATURE INVALIDE — rejeté");
        http_response_code(401);
        exit('Unauthorized');
    }
} else {
    wlog("Pas de signature — toléré");
}

$payload = json_decode($raw_body, true);
if (!$payload) {
    wlog("JSON invalide ou body vide — raw=[" . $raw_body . "]");
    http_response_code(400);
    exit('Bad Request');
}

$tx_data = $payload['v1/transaction']
        ?? $payload['entity']
        ?? $payload['transaction']
        ?? $payload
        ?? [];

$fedapay_id = $tx_data['id']     ?? null;
$status     = $tx_data['status'] ?? null;

wlog("fedapay_id=$fedapay_id  status=$status");

if (!$fedapay_id || !$status) {
    wlog("Payload incomplet — ignoré");
    http_response_code(200);
    exit('OK');
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    wlog("DB connexion échouée : " . $e->getMessage());
    http_response_code(500);
    exit('DB error');
}

$stmt = $pdo->prepare("SELECT * FROM paiements_fedapay WHERE fedapay_transaction_id = :fid");
$stmt->execute([':fid' => $fedapay_id]);
$paiement = $stmt->fetch();

if (!$paiement) {
    wlog("Transaction $fedapay_id inconnue en base — ignorée");
    http_response_code(200);
    exit('OK');
}

if ($paiement['statut'] === 'approuve') {
    wlog("Transaction $fedapay_id déjà traitée — ignorée");
    http_response_code(200);
    exit('OK');
}

if ($status === 'approved') {

    try {
        $pdo->beginTransaction();

        $proprietaire_id    = (int)$paiement['proprietaire_id'];
        $credits_a_crediter = (int)$paiement['credits_a_crediter'];
        $site_id            = (int)($paiement['site_id_a_renouveler'] ?? 0);
        $package_id         = (int)$paiement['package_id'];
        $montant_fcfa       = (int)$paiement['montant_fcfa'];

        // ✅ SUPPRIMÉ : le UPDATE manuel du solde (le trigger after_insert s'en charge)

        $pkg_stmt = $pdo->prepare("SELECT nom, credits, credits_bonus FROM tarifs_credits WHERE id = :id");
        $pkg_stmt->execute([':id' => $package_id]);
        $pkg = $pkg_stmt->fetch();

        // ✅ On insère — le trigger before_insert calcule solde_apres automatiquement
        //    et le trigger after_insert met à jour le solde du propriétaire
        $pdo->prepare("
            INSERT INTO transactions_credits
                (proprietaire_id, type, montant_fcfa, credits, description, methode_paiement, reference_paiement, statut_paiement)
            VALUES
                (:pid, 'achat', :montant, :credits, :desc, 'mobile_money', :ref, 'valide')
        ")->execute([
            ':pid'     => $proprietaire_id,
            ':montant' => $montant_fcfa,
            ':credits' => $credits_a_crediter,
            ':desc'    => "Achat {$pkg['nom']} ({$pkg['credits']} crédits + {$pkg['credits_bonus']} bonus) via FedaPay #{$fedapay_id}",
            ':ref'     => (string)$fedapay_id,
        ]);

        if ($site_id > 0) {
            $site_stmt = $pdo->prepare("SELECT id FROM routeurs WHERE id = :id AND proprietaire_id = :pid");
            $site_stmt->execute([':id' => $site_id, ':pid' => $proprietaire_id]);
            if ($site_stmt->fetch()) {
                $solde_actuel = (int)$pdo->query("SELECT solde_credits FROM proprietaires WHERE id = $proprietaire_id")->fetchColumn();
                if ($solde_actuel >= 1) {
                    $pdo->prepare("CALL sp_creer_ou_renouveler_site(:pid, :sid, NULL, NULL, NULL, @routeur_id, @code_unique, @date_expiration, @success, @message)")
                        ->execute([':pid' => $proprietaire_id, ':sid' => $site_id]);
                    $renew_result = $pdo->query("SELECT @success as success, @message as message, @date_expiration as date_exp")->fetch();
                    if ($renew_result['success']) {
                        wlog("Site $site_id renouvelé. Exp: {$renew_result['date_exp']}");
                        $pdo->prepare("UPDATE paiements_fedapay SET site_renouvele = 1 WHERE fedapay_transaction_id = :fid")
                            ->execute([':fid' => $fedapay_id]);
                    } else {
                        wlog("Auto-renew échoué : {$renew_result['message']}");
                    }
                }
            }
        }

        $pdo->prepare("UPDATE paiements_fedapay SET statut = 'approuve', traite_le = NOW() WHERE fedapay_transaction_id = :fid")
            ->execute([':fid' => $fedapay_id]);

        $pdo->commit();
        wlog("Transaction $fedapay_id traitée. +{$credits_a_crediter} crédits pour proprietaire $proprietaire_id");

    } catch (Exception $e) {
        $pdo->rollBack();
        wlog("ERREUR : " . $e->getMessage());
        http_response_code(500);
        exit('Processing error');
    }

} elseif (in_array($status, ['declined', 'canceled', 'refunded'])) {

    $pdo->prepare("UPDATE paiements_fedapay SET statut = :statut, traite_le = NOW() WHERE fedapay_transaction_id = :fid")
        ->execute([':statut' => $status, ':fid' => $fedapay_id]);
    wlog("Transaction $fedapay_id marquée $status");

} else {
    wlog("Statut $status non géré — ignoré");
}

http_response_code(200);
echo 'OK';
exit();