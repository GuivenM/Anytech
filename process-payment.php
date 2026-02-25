<?php
/**
 * process-payment.php
 * Crée une transaction FedaPay et redirige l'utilisateur vers la page de paiement.
 *
 * Paramètres POST :
 *   package_id  — ID du tarif dans tarifs_credits
 *   site_id     — (optionnel) ID du site à renouveler après paiement
 *
 * Flow :
 *   1. Valider le package
 *   2. Créer une entrée "pending" dans paiements_fedapay
 *   3. Appeler l'API FedaPay pour obtenir un payment_url
 *   4. Rediriger vers payment_url
 */

require_once 'auth-check.php';
require_once 'config.php';

// ── Helpers ────────────────────────────────────────────────────────────────

function fedapay_api(string $method, string $endpoint, array $data = []): array
{
    $base = FEDAPAY_ENV === 'live'
        ? 'https://api.fedapay.com/v1'
        : 'https://sandbox-api.fedapay.com/v1';

    $ch = curl_init($base . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . FEDAPAY_SECRET_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    ]);

    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error     = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new RuntimeException("cURL error: $error");
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid JSON from FedaPay (HTTP $http_code): $response");
    }

    return ['code' => $http_code, 'body' => $decoded];
}

function redirect_error(string $msg, string $back = 'buy-credits.php'): never
{
    $_SESSION['payment_error'] = $msg;
    header("Location: $back");
    exit();
}

// ── Guards ──────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: buy-credits.php');
    exit();
}

if (!csrf_verify()) {
    redirect_error('Requête invalide (CSRF). Veuillez réessayer.');
}

$package_id = (int)($_POST['package_id'] ?? 0);
$site_id    = (int)($_POST['site_id']    ?? 0); // pour auto-renouvellement post-paiement

if (!$package_id) {
    redirect_error('Package invalide.');
}

// ── Charger le package ──────────────────────────────────────────────────────

$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM tarifs_credits WHERE id = :id AND actif = TRUE");
$stmt->execute([':id' => $package_id]);
$pkg = $stmt->fetch();

if (!$pkg) {
    redirect_error('Package introuvable ou inactif.');
}

// Si site_id fourni, vérifier qu'il appartient bien à ce propriétaire
if ($site_id) {
    $s = $pdo->prepare("SELECT id, nom_site FROM routeurs WHERE id = :id AND proprietaire_id = :pid");
    $s->execute([':id' => $site_id, ':pid' => $proprietaire_id]);
    if (!$s->fetch()) {
        $site_id = 0; // ignorer silencieusement
    }
}

// ── Charger les infos du propriétaire ─────────────────────────────────────

$stmt_prop = $pdo->prepare("SELECT nom_complet, email, telephone FROM proprietaires WHERE id = :id");
$stmt_prop->execute([':id' => $proprietaire_id]);
$prop = $stmt_prop->fetch();

$total_credits = $pkg['credits'] + $pkg['credits_bonus'];
$montant_fcfa  = (int)$pkg['montant_fcfa'];

// ── Créer la transaction FedaPay ────────────────────────────────────────────

try {
    // Générer un ID de référence interne unique
    $internal_ref = 'ANYT-' . $proprietaire_id . '-' . time() . '-' . rand(100, 999);

    $payload = [
        'description'       => "ANYTECH – {$pkg['nom']} ({$total_credits} crédits)",
        'amount'            => $montant_fcfa,
        'currency'          => ['iso' => 'XOF'],
        'callback_url'      => FEDAPAY_CALLBACK_URL,
        'redirect_url'      => FEDAPAY_RETURN_URL,
        'mode'              => 'popup', // ou 'redirect' selon intégration
        'customer'          => [
            'firstname' => explode(' ', $prop['nom_complet'])[0] ?? 'Client',
            'lastname'  => implode(' ', array_slice(explode(' ', $prop['nom_complet']), 1)) ?: '-',
            'email'     => $prop['email'],
            'phone_number' => [
                'number'  => preg_replace('/\D/', '', $prop['telephone']),
                'country' => 'BJ',
            ],
        ],
        'meta'              => [
            'internal_ref'    => $internal_ref,
            'proprietaire_id' => $proprietaire_id,
            'package_id'      => $package_id,
            'site_id'         => $site_id,
        ],
    ];

    $result = fedapay_api('POST', '/transactions', $payload);

    if ($result['code'] !== 201 || empty($result['body']['v1/transaction']['id'])) {
        $detail = $result['body']['message'] ?? json_encode($result['body']);
        throw new RuntimeException("FedaPay a refusé la transaction : $detail");
    }

    $fedapay_id  = $result['body']['v1/transaction']['id'];
    $payment_url = $result['body']['v1/transaction']['payment_url']
                ?? ($result['body']['payment_url'] ?? null);

    // Si pas de payment_url direct, générer via l'endpoint token
    if (!$payment_url) {
        $token_res = fedapay_api('GET', "/transactions/{$fedapay_id}/token");
        $token     = $token_res['body']['token'] ?? null;
        if ($token) {
            $base = FEDAPAY_ENV === 'live' ? 'https://checkout.fedapay.com' : 'https://sandbox-checkout.fedapay.com';
            $payment_url = "$base/checkout/payment-page?token=$token";
        }
    }

    if (!$payment_url) {
        throw new RuntimeException('Impossible d\'obtenir l\'URL de paiement FedaPay.');
    }

} catch (RuntimeException $e) {
    error_log('[ANYTECH][FedaPay] ' . $e->getMessage());
    redirect_error('Erreur lors de la création du paiement : ' . $e->getMessage());
}

// ── Persister la transaction en base (statut: en_attente) ──────────────────

try {
    $pdo->prepare("
        INSERT INTO paiements_fedapay
            (proprietaire_id, fedapay_transaction_id, internal_ref, package_id,
             montant_fcfa, credits_a_crediter, site_id_a_renouveler, statut)
        VALUES
            (:pid, :fid, :ref, :pkg, :montant, :credits, :site, 'en_attente')
    ")->execute([
        ':pid'     => $proprietaire_id,
        ':fid'     => $fedapay_id,
        ':ref'     => $internal_ref,
        ':pkg'     => $package_id,
        ':montant' => $montant_fcfa,
        ':credits' => $total_credits,
        ':site'    => $site_id ?: null,
    ]);
} catch (PDOException $e) {
    // Table peut ne pas encore exister — lancer le SQL de migration
    error_log('[ANYTECH][DB] paiements_fedapay introuvable : ' . $e->getMessage());
    redirect_error('Erreur base de données. Avez-vous exécuté fedapay-migration.sql ?');
}

// ── Rediriger vers la page de paiement FedaPay ─────────────────────────────

header('Location: ' . $payment_url);
exit();