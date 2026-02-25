<?php
/**
 * download-mikrotik-logs.php
 * Génère et télécharge le script RouterOS anytech-logs.rsc
 * avec le hotspot_code et l'URL API pré-remplis pour ce site.
 */

require_once 'auth-check.php';

$pdo = getDBConnection();

$site_id = (int)($_GET['site_id'] ?? 0);
if (!$site_id) {
    http_response_code(400);
    die('site_id manquant.');
}

// Vérifier que ce site appartient bien au proprio connecté
$stmt = $pdo->prepare(
    "SELECT id, nom_site, code_unique
     FROM routeurs
     WHERE id = :id AND proprietaire_id = :proprio AND actif = 1"
);
$stmt->execute([':id' => $site_id, ':proprio' => $proprietaire_id]);
$site = $stmt->fetch();

if (!$site) {
    http_response_code(403);
    die('Accès non autorisé.');
}

$hotspot_code = $site['code_unique'];
$api_url      = 'https://' . $_SERVER['HTTP_HOST'] . '/api-logs.php';
$nom_site     = $site['nom_site'];
$generated_at = date('Y-m-d H:i:s');

// Contenu du script RSC - version testee et validee sur RouterOS
$rsc = <<<RSC
:local apiUrl "{$api_url}"
:local hotspotCode "{$hotspot_code}"

:local sessionsJson "["
:local first 1

:foreach s in=[/ip hotspot active find] do={
    :local mac [/ip hotspot active get \$s mac-address]
    :local ip [/ip hotspot active get \$s address]
    :local user [/ip hotspot active get \$s user]
    :local uptime [/ip hotspot active get \$s uptime]

    :local entry ("{\"type\":\"active\",\"mac_address\":\"" . \$mac . "\",\"ip_address\":\"" . \$ip . "\",\"username\":\"" . \$user . "\",\"uptime\":\"" . \$uptime . "\",\"bytes_in\":0,\"bytes_out\":0,\"login_time\":\"\"}")

    :if (\$first = 1) do={
        :set sessionsJson (\$sessionsJson . \$entry)
        :set first 0
    } else={
        :set sessionsJson (\$sessionsJson . "," . \$entry)
    }
}

:set sessionsJson (\$sessionsJson . "]")
:local payload ("{\"hotspot_code\":\"" . \$hotspotCode . "\",\"sessions\":" . \$sessionsJson . "}")

:do {
    /tool fetch url=\$apiUrl http-method=post http-header-field=("Content-Type: application/json,X-Hotspot-Code: " . \$hotspotCode) http-data=\$payload output=none mode=https check-certificate=no
    :log info "ANYTECH-LOGS: OK"
} on-error={
    :log error "ANYTECH-LOGS: ECHEC"
}
RSC;

$filename = 'anytech-logs-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $hotspot_code)) . '.rsc';

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($rsc));
header('Cache-Control: no-cache, must-revalidate');

echo $rsc;
exit();
