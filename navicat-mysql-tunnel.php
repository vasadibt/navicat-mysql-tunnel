<?php
// version vbt 104-en

// =============================================================================
// 🔧 CONFIGURATION
// =============================================================================

$config = [
    'enable_error_log' => true,
    'enable_debug_log' => true,
    'ip_filtering' => false,
    'error_log_file' => __DIR__ . '/navicat_tunnel_error.log',
    'debug_log_file' => __DIR__ . '/navicat_tunnel_debug.log',
    'allowed_ips_file' => __DIR__ . '/allowedIps.json',
];

const MYSQL_CHARSET = 'utf8';


// =============================================================================
// ⚙️ ENVIRONMENT SETUP
// =============================================================================

if ($config['enable_error_log']) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', $config['error_log_file']);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('log_errors', 0);
    error_reporting(0);
}

header('Content-Type: text/plain; charset=x-user-defined');
set_time_limit(0);

// =============================================================================
// 🧾 DEBUG LOG FUNCTION (conditionally enabled)
// =============================================================================

function logDebug(string $msg): void
{
    global $config;
    if (!$config['enable_debug_log']){
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $ip, $msg);
    file_put_contents($config['debug_log_file'], $line, FILE_APPEND);
}

// =============================================================================
// 🔐 IP ACCESS CONTROL
// =============================================================================

if ($config['ip_filtering']){
    $allowedIps = file_exists($config['allowed_ips_file'])
        ? json_decode(file_get_contents($config['allowed_ips_file']), true)
        : [];

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!in_array($clientIp, $allowedIps)) {
        http_response_code(403);
        logDebug("Access denied for IP: $clientIp");
        exit('Forbidden');
    }
} else {
    logDebug("⚠️ IP filtering is disabled. All clients can access this script.");
}

// =============================================================================
// 🛠 UTILITY FUNCTIONS
// =============================================================================

function getLongBinary($num): string
{
    return pack('N', $num);
}

function getShortBinary($num): string
{
    return pack('n', $num);
}

function getDummy($count): string
{
    return str_repeat("\x00", $count);
}

function getBlock($val): string
{
    $val = (string)($val ?? '');
    $len = strlen($val);
    return ($len < 254)
        ? chr($len) . $val
        : "\xFE" . getLongBinary($len) . $val;
}

function echoHeader(int $errno): void
{
    echo getLongBinary(1111)
        . getShortBinary(202)
        . getLongBinary($errno)
        . getDummy(6);
}

function echoConnInfo($conn): void
{
    echo getBlock(mysqli_get_host_info($conn))
        . getBlock(mysqli_get_proto_info($conn))
        . getBlock(mysqli_get_server_info($conn));
}

function echoResultSetHeader($errno, $affectrows, $insertid, $numfields, $numrows): void
{
    echo getLongBinary($errno)
        . getLongBinary($affectrows)
        . getLongBinary($insertid)
        . getLongBinary($numfields)
        . getLongBinary($numrows)
        . getDummy(12);
}

function echoFieldsHeader($res, int $numfields): void
{
    for ($i = 0; $i < $numfields; $i++) {
        $finfo = mysqli_fetch_field_direct($res, $i);
        echo getBlock($finfo->name)
            . getBlock($finfo->table)
            . getLongBinary($finfo->type)
            . getLongBinary($finfo->flags)
            . getLongBinary($finfo->length);
    }
}

function echoData($res, int $numfields): void
{
    while ($row = mysqli_fetch_row($res)) {
        for ($j = 0; $j < $numfields; $j++) {
            echo is_null($row[$j]) ? "\xFF" : getBlock($row[$j]);
        }
    }
}

// =============================================================================
// 📥 INPUT VALIDATION
// =============================================================================

if (!isset($_POST['actn'], $_POST['host'], $_POST['port'], $_POST['login'])) {
    echoHeader(202);
    echo getBlock('Missing required parameters');
    logDebug("Missing required parameters.");
    exit;
}

// =============================================================================
// 🔐 OPTIONAL BASE64 QUERY DECODING
// =============================================================================

if (!empty($_POST['encodeBase64']) && is_array($_POST['q'] ?? null)) {
    foreach ($_POST['q'] as &$query) {
        $query = base64_decode($query);
    }
    unset($query);
}

// =============================================================================
// 🔗 MYSQL CONNECTION
// =============================================================================

if (!function_exists('mysqli_connect')) {
    echoHeader(203);
    echo getBlock('MySQL support is not available on the server');
    logDebug("mysqli_connect not available.");
    exit;
}

$conn = @mysqli_connect($_POST['host'], $_POST['login'], $_POST['password'], '', (int)$_POST['port']);
$errno_c = mysqli_connect_errno();

mysqli_set_charset($conn, MYSQL_CHARSET);

if ($errno_c > 0) {
    $errMsg = mysqli_connect_error();
    echoHeader($errno_c);
    echo getBlock($errMsg);
    logDebug("Connection error [$errno_c]: $errMsg");
    exit;
}

// =============================================================================
// 📂 SELECT DATABASE IF PROVIDED
// =============================================================================

if (isset($_POST['db']) && $_POST['db'] !== '') {
    if (!@mysqli_select_db($conn, $_POST['db'])) {
        $errno_c = mysqli_errno($conn);
        logDebug("Database selection failed: " . mysqli_error($conn));
    }
}

echoHeader($errno_c);
if ($errno_c > 0) {
    echo getBlock(mysqli_error($conn));
    exit;
}

// =============================================================================
// ✅ CONNECTION TEST MODE
// =============================================================================

if ($_POST['actn'] === 'C') {
    logDebug("Connection test successful.");
    echoConnInfo($conn);
    exit;
}

// =============================================================================
// 🧠 QUERY EXECUTION
// =============================================================================

if ($_POST['actn'] === 'Q' && is_array($_POST['q'] ?? null)) {
    $total = count($_POST['q']);
    foreach ($_POST['q'] as $i => $query) {
        $query = trim($query);
        if ($query === '') continue;

        logDebug("Executing query: " . $query);

        $res = mysqli_query($conn, $query);
        $errno = mysqli_errno($conn);
        $affected = mysqli_affected_rows($conn);
        $insertId = mysqli_insert_id($conn);

        if ($res instanceof mysqli_result) {
            $numfields = mysqli_num_fields($res);
            $numrows = mysqli_num_rows($res);
        } else {
            $numfields = 0;
            $numrows = 0;
        }

        echoResultSetHeader($errno, $affected, $insertId, $numfields, $numrows);

        if ($errno > 0) {
            $err = mysqli_error($conn);
            echo getBlock($err);
            logDebug("Query error [$errno]: $err");
        } elseif ($numfields > 0) {
            echoFieldsHeader($res, $numfields);
            echoData($res, $numfields);
        } else {
            $info = mysqli_info($conn);
            echo getBlock($info ?: '');
        }

        echo ($i < $total - 1) ? "\x01" : "\x00";

        if ($res instanceof mysqli_result) {
            mysqli_free_result($res);
        }
    }
}
