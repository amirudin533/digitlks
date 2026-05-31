<?php

require_once __DIR__ . '/../src/Core/EnvLoader.php';
\Core\EnvLoader::load();

$env = fn($key, $default = null) => \Core\EnvLoader::get($key, $default);

$authUsers = \Core\EnvLoader::parseAuthUsers($env('AUTH_USERS', 'admin:admin123'));

$storagePath = $env('STORAGE_PATH', 'storage');
if (!str_starts_with($storagePath, '/')) {
    $storagePath = __DIR__ . '/../' . ltrim($storagePath, '/');
}

// ── RBAC: seed users.json dari .env jika masih kosong ──
require_once __DIR__ . '/../src/Core/Auth.php';
$auth = new \Core\Auth($storagePath);
if (!$auth->userExists() && !empty($authUsers)) {
    foreach ($authUsers as $u => $p) {
        $auth->addUser($u, $p, 'administrator', $u);
    }
}

return [
    'app' => [
        'name'     => $env('APP_NAME', 'DigitLKS'),
        'version'  => '2.0.0',
        'env'      => $env('APP_ENV', 'production'),
        'url'      => $env('APP_URL', 'https://portalsoal.page.gd'),
        'timezone' => $env('APP_TIMEZONE', 'Asia/Jakarta'),
    ],
    date_default_timezone_set($env('APP_TIMEZONE', 'Asia/Jakarta')),
    'database' => (function () use ($env, $storagePath) {
        $dbConfig = [
            'host'   => $env('DB_HOST', 'localhost'),
            'port'   => $env('DB_PORT', '3306'),
            'dbname' => $env('DB_NAME', 'portalsoal'),
            'user'   => $env('DB_USER', 'root'),
            'pass'   => $env('DB_PASS', ''),
        ];

        $dbJsonPath = $storagePath . '/config/database.json';
        if (file_exists($dbJsonPath)) {
            $dbJson = json_decode(file_get_contents($dbJsonPath), true);
            if (is_array($dbJson) && !empty($dbJson['host'])) {
                $dbConfig = array_merge($dbConfig, $dbJson);
            }
        }

        return $dbConfig;
    })(),
    'auth' => $auth,
    'security' => [
        'users'             => $auth->userExists() ? [] : $authUsers,
        'pin_max_attempts'  => (int) $env('PIN_MAX_ATTEMPTS', 3),
        'pin_lock_minutes'  => (int) $env('PIN_LOCK_MINUTES', 10),
    ],
    'storage' => [
        'path' => $storagePath,
    ],
    'whatsapp' => [
        'child_number' => $env('WA_CHILD_NUMBER', ''),
    ],
    'timer_options' => [
        0 => 0,
        1 => 5,
        2 => 10,
        3 => 15,
        4 => 20,
        5 => 30,
        6 => 45,
        7 => 60,
        8 => 90,
        9 => 120,
    ],
    'default_timer_menit' => (int) $env('DEFAULT_TIMER_MINUTES', 20),
    'groq' => (function () use ($env, $storagePath) {
        $groqConfig = [
            'api_key' => $env('GROQ_API_KEY', ''),
            'model'   => $env('GROQ_MODEL', 'mixtral-8x7b-32768'),
        ];
        $groqJsonPath = $storagePath . '/config/groq.json';
        if (file_exists($groqJsonPath)) {
            $groqJson = json_decode(file_get_contents($groqJsonPath), true);
            if (is_array($groqJson)) {
                if (!empty($groqJson['api_key'])) $groqConfig['api_key'] = $groqJson['api_key'];
                if (!empty($groqJson['model'])) $groqConfig['model'] = $groqJson['model'];
            }
        }
        $groqConfig['enabled'] = !empty($groqConfig['api_key']);
        return $groqConfig;
    })(),
];
