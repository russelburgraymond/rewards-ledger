<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../schema.php';

header('Content-Type: application/json');

if (empty($db_exists) || !isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database is not ready.']);
    exit;
}

ensure_schema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$asset_id   = (int)($data['asset_id'] ?? $data['asset'] ?? 0);
$amount_raw = trim((string)($data['amount'] ?? ''));
$date_raw   = trim((string)($data['date'] ?? ''));
$time_raw   = trim((string)($data['time'] ?? ''));

if ($asset_id <= 0 || $amount_raw === '' || !is_numeric($amount_raw) || $date_raw === '' || $time_raw === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Asset, amount, date, and time are required.']);
    exit;
}

$stmt = $conn->prepare("SELECT asset_name, asset_symbol, is_fiat FROM assets WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load asset.']);
    exit;
}
$stmt->bind_param('i', $asset_id);
$stmt->execute();
$res = $stmt->get_result();
$asset = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$asset) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Asset not found.']);
    exit;
}

$amount = (float)$amount_raw;
if ((int)($asset['is_fiat'] ?? 0) === 1) {
    echo json_encode([
        'success' => true,
        'currency_symbol' => '$',
        'unit_price' => 1,
        'unit_price_formatted' => number_format(1, 2, '.', ''),
        'total_value' => $amount,
        'total_value_formatted' => number_format($amount, 2, '.', ''),
        'message' => 'Fiat asset value loaded.'
    ]);
    exit;
}

function rl_parse_date_string(string $value): ?DateTimeImmutable {
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $formats = ['Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y', 'n-j-Y'];
    foreach ($formats as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }
    $ts = strtotime($value);
    return $ts ? (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone(date_default_timezone_get())) : null;
}

function rl_parse_time_string(string $value): ?string {
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $formats = ['H:i', 'H:i:s', 'g:i A', 'g:iA', 'h:i A', 'h:iA'];
    foreach ($formats as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('H:i:s');
        }
    }
    $ts = strtotime($value);
    return $ts ? date('H:i:s', $ts) : null;
}

function rl_http_json(string $url, array $headers): ?array {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false || $response === '') {
        return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function rl_detect_coin_id(array $asset): string {
    $symbol = strtoupper(trim((string)($asset['asset_symbol'] ?? '')));
    $name   = strtolower(trim((string)($asset['asset_name'] ?? '')));

    $symbolMap = [
        'BTC' => 'bitcoin',
        'ETH' => 'ethereum',
        'SOL' => 'solana',
        'BNB' => 'binancecoin',
        'USDT' => 'tether',
        'USDC' => 'usd-coin',
        'GMT' => 'gmt-token',
    ];

    if (isset($symbolMap[$symbol])) {
        return $symbolMap[$symbol];
    }

    if (str_contains($name, 'gomining')) {
        return 'gmt-token';
    }

    if ($name === 'bitcoin') {
        return 'bitcoin';
    }
    if ($name === 'ethereum') {
        return 'ethereum';
    }
    if ($name === 'solana') {
        return 'solana';
    }
    if ($name === 'binance coin' || $name === 'bnb') {
        return 'binancecoin';
    }

    return '';
}

$date_obj = rl_parse_date_string($date_raw);
$time_24 = rl_parse_time_string($time_raw);
if (!$date_obj || !$time_24) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid date or time.']);
    exit;
}

$coin_id = rl_detect_coin_id($asset);
if ($coin_id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Lookup not available for this asset. Enter value manually if needed.']);
    exit;
}

$lookup_date = $date_obj->format('Y-m-d');
$today = (new DateTimeImmutable('now'))->format('Y-m-d');
$api_key = trim(get_setting($conn, 'coingecko_demo_api_key', ''));
$headers = [
    'Accept: application/json',
    'User-Agent: RewardLedger/2.1.0',
];
if ($api_key !== '') {
    $headers[] = 'x-cg-demo-api-key: ' . $api_key;
}

$unit_price = null;
$message = '';

if ($lookup_date >= $today) {
    $url = 'https://api.coingecko.com/api/v3/simple/price?ids=' . rawurlencode($coin_id) . '&vs_currencies=usd';
    $payload = rl_http_json($url, $headers);
    $unit_price = (float)($payload[$coin_id]['usd'] ?? 0);
    $message = 'Loaded current price.';
} else {
    $url = 'https://api.coingecko.com/api/v3/coins/' . rawurlencode($coin_id) . '/history?date=' . $date_obj->format('d-m-Y') . '&localization=false';
    $payload = rl_http_json($url, $headers);
    $unit_price = (float)($payload['market_data']['current_price']['usd'] ?? 0);
    $message = 'Loaded daily historical price.';
}

if ($unit_price <= 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'No price data was returned for that date.',
        'debug' => [
            'coin_id' => $coin_id,
            'lookup_date' => $lookup_date,
            'time' => $time_24,
        ],
    ]);
    exit;
}

$total_value = $amount * $unit_price;
echo json_encode([
    'success' => true,
    'currency_symbol' => '$',
    'unit_price' => $unit_price,
    'unit_price_formatted' => number_format($unit_price, 8, '.', ''),
    'total_value' => $total_value,
    'total_value_formatted' => number_format($total_value, 2, '.', ''),
    'message' => $message,
    'normalized_time' => $time_24,
    'coin_id' => $coin_id,
]);
