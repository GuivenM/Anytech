<?php
/**
 * api-logs.php
 * Endpoint de réception des logs de sessions WiFi MikroTik
 * Authentification via header X-Hotspot-Code (même logique que api-register.php)
 * Format attendu : POST JSON avec tableau "sessions" (mode batch)
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Hotspot-Code');
header('Content-Type: application/json; charset=utf-8');

// Configuration
define('DB_HOST', 'greentp171.mysql.db:3306');
define('DB_NAME', 'greentp171');
define('DB_USER', 'greentp171');
define('DB_PASS', 'TTp38xZVR5NS');

// -------------------------------------------------------
function logAPI($message, $level = 'INFO') {
    $log_dir = __DIR__ . '/logs';
    if (!file_exists($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/api_logs_' . date('Y-m-d') . '.log';
    $entry    = '[' . date('Y-m-d H:i:s') . "] [$level] $message" . PHP_EOL;
    @file_put_contents($log_file, $entry, FILE_APPEND);
}

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'data'      => $data,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// -------------------------------------------------------
// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Seules les requêtes POST sont acceptées
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logAPI('Méthode non-POST : ' . $_SERVER['REQUEST_METHOD'], 'WARNING');
    sendResponse(false, 'Méthode non autorisée', null, 405);
}

// -------------------------------------------------------
try {
    // Connexion DB
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Lecture du body JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logAPI('JSON invalide : ' . json_last_error_msg(), 'ERROR');
        sendResponse(false, 'Données JSON invalides', null, 400);
    }

    // -------------------------------------------------------
    // Identification du routeur via X-Hotspot-Code
    $code_routeur = $_SERVER['HTTP_X_HOTSPOT_CODE'] ?? ($data['hotspot_code'] ?? null);

    if (!$code_routeur) {
        logAPI('Code routeur manquant', 'ERROR');
        sendResponse(false, 'Code routeur requis (X-Hotspot-Code header ou hotspot_code)', null, 401);
    }

    logAPI("Requête reçue pour routeur : $code_routeur");

    // Vérification routeur + propriétaire (même logique que api-register.php)
    $stmt_routeur = $pdo->prepare(
        "SELECT r.id AS routeur_id, r.nom_site, r.actif,
                p.id AS proprietaire_id, p.statut AS proprio_statut
         FROM   routeurs r
         INNER  JOIN proprietaires p ON r.proprietaire_id = p.id
         WHERE  r.code_unique = :code
         AND    r.actif = 1
         AND    p.statut IN ('actif', 'essai')"
    );
    $stmt_routeur->execute([':code' => $code_routeur]);
    $routeur = $stmt_routeur->fetch();

    if (!$routeur) {
        logAPI("Routeur non trouvé ou inactif : $code_routeur", 'WARNING');
        sendResponse(false, 'Routeur non autorisé ou propriétaire inactif', null, 403);
    }

    $routeur_id = $routeur['routeur_id'];
    logAPI("Routeur validé : {$routeur['nom_site']} (ID: $routeur_id)");

    // -------------------------------------------------------
    // Récupération du tableau de sessions (mode batch)
    $sessions = $data['sessions'] ?? null;

    // Rétrocompatibilité : si le MikroTik envoie une seule session à plat
    if (!$sessions && isset($data['mac_address'])) {
        $sessions = [$data];
    }

    if (empty($sessions) || !is_array($sessions)) {
        logAPI('Aucune session à traiter', 'WARNING');
        sendResponse(false, 'Tableau "sessions" vide ou absent', null, 400);
    }

    // -------------------------------------------------------
    // Prépare le upsert (INSERT … ON DUPLICATE KEY UPDATE)
    // Clé unique : (routeur_id, mac_address, login_time)
    $sql_upsert = "
        INSERT INTO sessions_logs
            (routeur_id, type, mac_address, ip_address, username,
             uptime, bytes_in, bytes_out, login_time, to_time, idle_time)
        VALUES
            (:routeur_id, :type, :mac_address, :ip_address, :username,
             :uptime, :bytes_in, :bytes_out, :login_time, :to_time, :idle_time)
        ON DUPLICATE KEY UPDATE
            type       = VALUES(type),
            ip_address = VALUES(ip_address),
            uptime     = VALUES(uptime),
            bytes_in   = VALUES(bytes_in),
            bytes_out  = VALUES(bytes_out),
            to_time    = VALUES(to_time),
            idle_time  = VALUES(idle_time),
            updated_at = NOW()
    ";
    $stmt = $pdo->prepare($sql_upsert);

    $inserted = 0;
    $updated  = 0;
    $errors   = 0;

    foreach ($sessions as $session) {
        // Validation minimale
        $mac = trim($session['mac_address'] ?? '');
        if (empty($mac)) {
            $errors++;
            continue;
        }

        // Parsing login_time : MikroTik envoie souvent "2026/02/20 14:35:00"
        $login_time = null;
        if (!empty($session['login_time'])) {
            $raw = str_replace('/', '-', $session['login_time']);
            $dt  = date_create($raw);
            $login_time = $dt ? date_format($dt, 'Y-m-d H:i:s') : null;
        }
        // Si pas de login_time, on utilise now() — la clé unique ne pourra pas
        // dédupliquer, mais ça vaut mieux que perdre la session.
        if (!$login_time) {
            $login_time = date('Y-m-d H:i:s');
        }

        $to_time = null;
        if (!empty($session['to_time'])) {
            $raw = str_replace('/', '-', $session['to_time']);
            $dt  = date_create($raw);
            $to_time = $dt ? date_format($dt, 'Y-m-d H:i:s') : null;
        }

        try {
            $stmt->execute([
                ':routeur_id'  => $routeur_id,
                ':type'        => in_array($session['type'] ?? '', ['active', 'host'])
                                    ? $session['type'] : 'active',
                ':mac_address' => strtoupper($mac),
                ':ip_address'  => $session['ip_address']  ?? null,
                ':username'    => $session['username']     ?? null,
                ':uptime'      => $session['uptime']       ?? null,
                ':bytes_in'    => (int)($session['bytes_in']  ?? 0),
                ':bytes_out'   => (int)($session['bytes_out'] ?? 0),
                ':login_time'  => $login_time,
                ':to_time'     => $to_time,
                ':idle_time'   => $session['idle_time']    ?? null,
            ]);

            // rowCount = 1 → INSERT, 2 → UPDATE (MySQL convention)
            if ($stmt->rowCount() == 1) $inserted++;
            else $updated++;

        } catch (PDOException $e) {
            $errors++;
            logAPI("Erreur upsert MAC=$mac : " . $e->getMessage(), 'ERROR');
        }
    }

    $total = count($sessions);
    logAPI("Traitement terminé : $total sessions — insérées: $inserted, mises à jour: $updated, erreurs: $errors");

    sendResponse(true, 'Sessions traitées', [
        'total'    => $total,
        'inserted' => $inserted,
        'updated'  => $updated,
        'errors'   => $errors,
        'site'     => $routeur['nom_site'],
    ], 200);

} catch (PDOException $e) {
    logAPI('Erreur PDO : ' . $e->getMessage(), 'ERROR');
    sendResponse(false, 'Erreur de base de données', null, 500);
} catch (Exception $e) {
    logAPI('Erreur : ' . $e->getMessage(), 'ERROR');
    sendResponse(false, 'Erreur serveur', null, 500);
}
?>
