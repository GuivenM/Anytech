<?php
/**
 * api-register.php
 * API pour enregistrer les utilisateurs WiFi
 * Identification par code routeur (X-Hotspot-Code header ou hotspot_code dans POST)
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

// Fonction de logging
function logAPI($message, $level = 'INFO') {
    $log_dir = __DIR__ . '/logs';
    if (!file_exists($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/api_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Fonction réponse JSON
function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Gérer OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logAPI('Méthode non-POST: ' . $_SERVER['REQUEST_METHOD'], 'WARNING');
    sendResponse(false, 'Méthode non autorisée', null, 405);
}

try {
    // Connexion DB
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    // Récupérer les données
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logAPI('JSON invalide: ' . json_last_error_msg(), 'ERROR');
        sendResponse(false, 'Données JSON invalides', null, 400);
    }
    
    // Identifier le routeur
    $code_routeur = $_SERVER['HTTP_X_HOTSPOT_CODE'] ?? ($data['hotspot_code'] ?? null);
    
    // Types de pièce d'identité acceptés
    $types_piece_valides = ['cni', 'passeport', 'cip'];
    
    if (!$code_routeur) {
        logAPI('Code routeur manquant', 'ERROR');
        sendResponse(false, 'Code routeur requis (X-Hotspot-Code header ou hotspot_code)', null, 401);
    }
    
    logAPI("Requête reçue pour routeur: $code_routeur");
    
    // Trouver le routeur et le propriétaire
    $sql_routeur = "SELECT 
                        r.id as routeur_id,
                        r.nom_site,
                        r.date_expiration,
                        r.proprietaire_id,
                        p.nom_complet,
                        p.statut
                    FROM routeurs r
                    INNER JOIN proprietaires p ON r.proprietaire_id = p.id
                    WHERE r.code_unique = :code
                    AND r.actif = 1
                    AND p.statut IN ('actif', 'essai')";
    
    $stmt_routeur = $pdo->prepare($sql_routeur);
    $stmt_routeur->execute([':code' => $code_routeur]);
    $routeur = $stmt_routeur->fetch();
    
    if (!$routeur) {
        logAPI("Routeur non trouvé ou inactif: $code_routeur", 'WARNING');
        sendResponse(false, 'Routeur non autorisé ou propriétaire inactif', null, 403);
    }
    
    $routeur_id = $routeur['routeur_id'];
    $proprietaire_id = $routeur['proprietaire_id'];
    
    logAPI("Routeur trouvé: {$routeur['nom_site']} (ID: $routeur_id, Proprio: $proprietaire_id)", 'INFO');
    

    $mois_actuel = date('Y-m');
    
    $sql_count = "SELECT COUNT(*) as total 
                  FROM wifi_users w
                  INNER JOIN routeurs r ON w.routeur_id = r.id
                  WHERE r.proprietaire_id = :id
                  AND DATE_FORMAT(w.date_connexion, '%Y-%m') = :mois";
    
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([':id' => $proprietaire_id, ':mois' => $mois_actuel]);
    $connexions_mois = $stmt_count->fetch()['total'];
    
    // Valider les données
    $requiredFields = ['nom', 'prenom', 'cni', 'telephone'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            logAPI("Champ manquant: $field", 'WARNING');
            sendResponse(false, "Champ '$field' obligatoire", null, 400);
        }
    }

    // Valider le type de pièce d'identité (optionnel, défaut : cni)
    $type_piece = strtolower(trim($data['type_piece'] ?? 'cni'));
    if (!in_array($type_piece, $types_piece_valides, true)) {
        logAPI("Type de pièce invalide: $type_piece", 'WARNING');
        sendResponse(false, "type_piece invalide. Valeurs acceptées : cni, passeport, cip", null, 400);
    }
    
    // Nettoyer les données
    $nom = htmlspecialchars(trim($data['nom']), ENT_QUOTES, 'UTF-8');
    $prenom = htmlspecialchars(trim($data['prenom']), ENT_QUOTES, 'UTF-8');
    $cni = htmlspecialchars(trim($data['cni']), ENT_QUOTES, 'UTF-8');
    $telephone = preg_replace('/[^0-9]/', '', trim($data['telephone']));
    $email = isset($data['email']) ? htmlspecialchars(trim($data['email']), ENT_QUOTES, 'UTF-8') : null;
    $code_voucher = isset($data['code_voucher']) ? htmlspecialchars(trim($data['code_voucher']), ENT_QUOTES, 'UTF-8') : null;
    $mac_address = isset($data['mac_address']) ? htmlspecialchars(trim($data['mac_address']), ENT_QUOTES, 'UTF-8') : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Validations
    if (strlen($nom) < 2 || strlen($nom) > 100) {
        sendResponse(false, 'Le nom doit contenir entre 2 et 100 caractères', null, 400);
    }
    if (strlen($prenom) < 2 || strlen($prenom) > 100) {
        sendResponse(false, 'Le prénom doit contenir entre 2 et 100 caractères', null, 400);
    }
    if (strlen($telephone) < 8 || strlen($telephone) > 15) {
        sendResponse(false, 'Le téléphone doit contenir entre 8 et 15 chiffres', null, 400);
    }
    if ($routeur['date_expiration'] < date('Y-m-d')) {
        sendResponse(false, 'Site expiré. Veuillez renouveler votre abonnement.', null, 403);
    } 
    
    // Insérer l'utilisateur
    $sql_insert = "INSERT INTO wifi_users 
                   (routeur_id, nom, prenom, type_piece, cni, telephone, email, code_voucher, mac_address, ip_address, user_agent, date_connexion) 
                   VALUES 
                   (:routeur_id, :nom, :prenom, :type_piece, :cni, :telephone, :email, :code_voucher, :mac_address, :ip_address, :user_agent, NOW())";
    
    $stmt_insert = $pdo->prepare($sql_insert);
    $result = $stmt_insert->execute([
        ':routeur_id'  => $routeur_id,
        ':nom'         => $nom,
        ':prenom'      => $prenom,
        ':type_piece'  => $type_piece,
        ':cni'         => $cni,
        ':telephone'   => $telephone,
        ':email'       => $email,
        ':code_voucher'=> $code_voucher,
        ':mac_address' => $mac_address,
        ':ip_address'  => $ip_address,
        ':user_agent'  => $user_agent
    ]);
    
    if ($result) {
        $user_id = $pdo->lastInsertId();
        
        logAPI("✅ Utilisateur enregistré: ID=$user_id, $nom $prenom, Tel=$telephone, Pièce=$type_piece, Site={$routeur['nom_site']}", 'SUCCESS');
        
        sendResponse(true, 'Utilisateur enregistré avec succès', [
            'user_id' => $user_id,
            'site' => $routeur['nom_site'],
            'connexions_mois' => $connexions_mois + 1,
        ], 201);
    } else {
        logAPI('Échec insertion DB', 'ERROR');
        sendResponse(false, 'Erreur lors de l\'enregistrement', null, 500);
    }
    
} catch (PDOException $e) {
    logAPI('Erreur PDO: ' . $e->getMessage(), 'ERROR');
    sendResponse(false, $e->getMessage(), null, 500);
} catch (Exception $e) {
    logAPI('Erreur: ' . $e->getMessage(), 'ERROR');
    sendResponse(false, 'Erreur serveur', null, 500);
}
?>
