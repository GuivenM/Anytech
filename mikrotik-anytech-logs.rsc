:local apiUrl "https://wifizone.greentechnos.com/api-logs.php"
:local hotspotCode "RTR-2025-XXX"

:local sessionsJson "["
:local first 1

:foreach s in=[/ip hotspot active find] do={
    :local mac [/ip hotspot active get $s mac-address]
    :local ip [/ip hotspot active get $s address]
    :local user [/ip hotspot active get $s user]
    :local uptime [/ip hotspot active get $s uptime]
    :local bin [/ip hotspot active get $s bytes-in]
    :local bout [/ip hotspot active get $s bytes-out]
    :local ltime [/ip hotspot active get $s login-time]

    :local entry ("{\"type\":\"active\",\"mac_address\":\"" . $mac . "\",\"ip_address\":\"" . $ip . "\",\"username\":\"" . $user . "\",\"uptime\":\"" . $uptime . "\",\"bytes_in\":" . $bin . ",\"bytes_out\":" . $bout . ",\"login_time\":\"" . $ltime . "\"}")

    :if ($first = 1) do={
        :set sessionsJson ($sessionsJson . $entry)
        :set first 0
    } else={
        :set sessionsJson ($sessionsJson . "," . $entry)
    }
}

:foreach h in=[/ip hotspot host find] do={
    :local mac [/ip hotspot host get $h mac-address]
    :local ip [/ip hotspot host get $h address]
    :local user [/ip hotspot host get $h user]
    :local uptime ""
    :local bin 0
    :local bout 0
    :local ltime ""

    :do { :set uptime [/ip hotspot host get $h uptime] } on-error={}
    :do { :set bin [/ip hotspot host get $h bytes-in] } on-error={}
    :do { :set bout [/ip hotspot host get $h bytes-out] } on-error={}
    :do { :set ltime [/ip hotspot host get $h login-time] } on-error={}

    :local entry ("{\"type\":\"host\",\"mac_address\":\"" . $mac . "\",\"ip_address\":\"" . $ip . "\",\"username\":\"" . $user . "\",\"uptime\":\"" . $uptime . "\",\"bytes_in\":" . $bin . ",\"bytes_out\":" . $bout . ",\"login_time\":\"" . $ltime . "\"}")

    :if ($first = 1) do={
        :set sessionsJson ($sessionsJson . $entry)
        :set first 0
    } else={
        :set sessionsJson ($sessionsJson . "," . $entry)
    }
}

:set sessionsJson ($sessionsJson . "]")

:local payload ("{\"hotspot_code\":\"" . $hotspotCode . "\",\"sessions\":" . $sessionsJson . "}")

:do {
    /tool fetch url=$apiUrl http-method=post http-header-field=("Content-Type: application/json,X-Hotspot-Code: " . $hotspotCode) http-data=$payload output=none mode=https check-certificate=no
    :log info "ANYTECH-LOGS: OK"
} on-error={
    :log error "ANYTECH-LOGS: ECHEC"
}
