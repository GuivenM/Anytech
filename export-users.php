<?php
/**
 * export-users.php
 * Export CSV des utilisateurs WiFi — respecte les mêmes filtres que users.php
 */

require_once 'auth-check.php';

$pdo = getDBConnection();

$search    = trim($_GET['search']    ?? '');
$site_id   = (int)($_GET['site_id'] ?? 0);
$date_from = $_GET['date_from']      ?? '';
$date_to   = $_GET['date_to']        ?? '';

// Build same WHERE as users.php
$where  = ["r.proprietaire_id = :proprietaire_id"];
$params = [':proprietaire_id' => $proprietaire_id];

if ($search) {
    $where[]          = "(w.nom LIKE :search OR w.prenom LIKE :search OR w.telephone LIKE :search OR w.cni LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($site_id) {
    $where[]           = "w.routeur_id = :site_id";
    $params[':site_id'] = $site_id;
}
if ($date_from) {
    $where[]              = "DATE(w.date_connexion) >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to) {
    $where[]            = "DATE(w.date_connexion) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = implode(" AND ", $where);

$stmt = $pdo->prepare("
    SELECT w.nom, w.prenom, w.telephone, w.type_piece, w.cni, w.email,
           w.code_voucher, w.mac_address, w.date_connexion,
           r.nom_site, r.code_unique
    FROM wifi_users w
    INNER JOIN routeurs r ON w.routeur_id = r.id
    WHERE $where_clause
    ORDER BY w.date_connexion DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Generate filename
$filename = 'anytech-users-' . date('Y-m-d') . '.csv';

// Headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Column headers
fputcsv($output, [
    'Nom', 'Prénom', 'Téléphone', 'Type Pièce', 'N° Pièce', 'Email',
    'Site', 'Code Site', 'Code Voucher', 'MAC Address', 'Date Connexion'
], ';');

// Rows
$labels_piece = ['cni' => 'CNI', 'passeport' => 'Passeport', 'cip' => 'CIP'];

foreach ($users as $u) {
    $type_lbl = $labels_piece[$u['type_piece'] ?? 'cni'] ?? strtoupper($u['type_piece'] ?? 'CNI');
    fputcsv($output, [
        $u['nom'],
        $u['prenom'],
        $u['telephone'],
        $type_lbl,
        $u['cni'],
        $u['email']          ?? '',
        $u['nom_site'],
        $u['code_unique'],
        $u['code_voucher']   ?? '',
        $u['mac_address']    ?? '',
        date('d/m/Y H:i', strtotime($u['date_connexion'])),
    ], ';');
}

fclose($output);
exit();
?>