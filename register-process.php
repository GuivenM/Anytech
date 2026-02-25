<?php
/**
 * register-process.php
 * Traite l'inscription d'un nouveau propriétaire
 */

header('Content-Type: application/json; charset=utf-8');

// Configuration base de données

define('DB_HOST', 'greentp171.mysql.db:3306');
define('DB_NAME', 'greentp171');
define('DB_USER', 'greentp171');
define('DB_PASS', 'TTp38xZVR5NS');

// Fonction pour envoyer une réponse JSON
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Méthode non autorisée');
}

try {
    // Connexion à la base de données
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    // Récupérer et valider les données
    $nom_complet = trim($_POST['nom_complet'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $nom_entreprise = trim($_POST['nom_entreprise'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    
    // Validations
    if (empty($nom_complet) || strlen($nom_complet) < 3) {
        sendResponse(false, 'Le nom complet doit contenir au moins 3 caractères');
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Email invalide');
    }
    
    if (empty($telephone) || strlen($telephone) < 8) {
        sendResponse(false, 'Numéro de téléphone invalide');
    }
    
    if (empty($mot_de_passe) || strlen($mot_de_passe) < 6) {
        sendResponse(false, 'Le mot de passe doit contenir au moins 6 caractères');
    }
    
    // Vérifier si l'email existe déjà
    $stmt = $pdo->prepare("SELECT id FROM proprietaires WHERE email = :email");
    $stmt->execute([':email' => $email]);
    
    if ($stmt->fetch()) {
        sendResponse(false, 'Cet email est déjà utilisé');
    }
    
    // Hasher le mot de passe
    $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
    
    // Insérer le nouveau propriétaire (avec 0 crédits au départ)
    $sql = "INSERT INTO proprietaires 
            (nom_complet, email, telephone, nom_entreprise, mot_de_passe, solde_credits, statut) 
            VALUES 
            (:nom_complet, :email, :telephone, :nom_entreprise, :mot_de_passe, 0, 'actif')";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':nom_complet' => htmlspecialchars($nom_complet, ENT_QUOTES, 'UTF-8'),
        ':email' => $email,
        ':telephone' => htmlspecialchars($telephone, ENT_QUOTES, 'UTF-8'),
        ':nom_entreprise' => htmlspecialchars($nom_entreprise, ENT_QUOTES, 'UTF-8'),
        ':mot_de_passe' => $mot_de_passe_hash
    ]);
    
    if ($result) {
        $proprietaire_id = $pdo->lastInsertId();
        
        // Log de l'inscription
        error_log("Nouvelle inscription - ID: $proprietaire_id, Email: $email");
        
        sendResponse(true, 'Inscription réussie ! Vous allez être redirigé...', [
            'proprietaire_id' => $proprietaire_id,
            'email' => $email
        ]);
    } else {
        sendResponse(false, 'Erreur lors de l\'inscription');
    }
    
} catch (PDOException $e) {
    error_log("Erreur SQL: " . $e->getMessage());
    sendResponse(false, 'Erreur de base de données. Veuillez réessayer.');
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    sendResponse(false, 'Une erreur s\'est produite. Veuillez réessayer.');
}
?>
