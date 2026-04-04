<?php
// Token generation logic for E-Meals integration

if (!defined('SHARED_TOKEN_SECRET')) {
    define('SHARED_TOKEN_SECRET', 'LRNPH_SECRET_INV_2025');
}

if (!function_exists('base64url_encode')) {
    function base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode(string $value): string
    {
        $pad = 4 - (strlen($value) % 4);
        if ($pad < 4)
            $value .= str_repeat('=', $pad);
        return base64_decode(strtr($value, '-_', '+/'));
    }
}

if (!function_exists('generate_shared_token_payload')) {
    function generate_shared_token_payload(array $payload, int $ttl = 300): string
    {
        $data = $payload;
        $data['exp'] = time() + $ttl;
        $data['iss'] = 'lrnph_user_session'; // Match the issuer expected by the app (auth.php)
        $json = json_encode($data);
        $sig = hash_hmac('sha256', $json, SHARED_TOKEN_SECRET, true);
        return base64url_encode($json) . '.' . base64url_encode($sig);
    }
}

if (!function_exists('generate_shared_token')) {
    function generate_shared_token(int $uid, int $ttl = 300): string
    {
        return generate_shared_token_payload(['uid' => $uid], $ttl);
    }
}

if (!function_exists('shared_emeals_token')) {
    function shared_emeals_token(): ?string
    {
        global $conn; // Access the global SQLSRV connection

        $uid = (int) ($_SESSION['uid'] ?? 0);

        // If UID is missing but we have employee_id, try to resolve it from prtl_app_users
        if ($uid <= 0 && !empty($_SESSION['employee_id'])) {
            $eid = $_SESSION['employee_id'];

            // Only attempt query if $conn is valid
            if ($conn) {
                // Check if prtl_app_users table exists first to avoid errors if schema differs
                // But assuming standardized schema based on other files
                $sql = "SELECT TOP 1 id FROM prtl_app_users WHERE employee_id = ?";
                $stmt = $conn->prepare($sql);
    $stmt->execute(array($eid));
                if ($stmt && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $uid = (int) $row['id'];
                    $_SESSION['uid'] = $uid; // Cache it
                }
            }
        }

        if ($uid <= 0)
            return null;
        return generate_shared_token($uid);
    }
}

if (!function_exists('emeals_link_href')) {
    function emeals_link_href(): ?string
    {
        $token = shared_emeals_token();
        if (!$token) {
            return '#';
        }
        $scheme = 'http://'; // Force HTTP for internal IP usually, or detect
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            $scheme = 'https://';

        // Hardcoded to production IP for E-Meals server since it's not local
        $host = '10.2.0.8';
        return $scheme . $host . '/emeals/emeals.php?token=' . rawurlencode($token);
    }
}

if (!function_exists('emeals_settings_link_href')) {
    function emeals_settings_link_href(): ?string
    {
        $token = shared_emeals_token();
        if (!$token) {
            return '#';
        }
        $scheme = 'http://';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            $scheme = 'https://';

        $host = '10.2.0.8';
        return $scheme . $host . '/emeals/settings.php?token=' . rawurlencode($token);
    }
}
?>