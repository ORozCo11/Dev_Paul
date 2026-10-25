<?php
// Inspect a disposable in-memory database. Never connect to the fleet database.
$root = dirname(__DIR__, 2);
foreach ([
    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
    'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/backend-api/vendor/autoload.php';
$app = require $root.'/backend-api/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
    'database.connections.sqlite.url' => null]);
Illuminate\Support\Facades\DB::purge('sqlite');
$result = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
if ($result !== 0) {
    fwrite(STDERR, Illuminate\Support\Facades\Artisan::output());
    exit($result);
}
$db = Illuminate\Support\Facades\DB::connection();
$schema = [];
foreach ($db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $row) {
    $table = $row->name;
    $quoted = '"'.str_replace('"', '""', $table).'"';
    $schema[$table] = [
        'columns' => $db->select("PRAGMA table_info($quoted)"),
        'foreign_keys' => $db->select("PRAGMA foreign_key_list($quoted)"),
        'indexes' => $db->select("PRAGMA index_list($quoted)"),
    ];
}
$out = dirname(__DIR__).'/sources/schema.json';
file_put_contents($out, json_encode(['revision' => '352fd0e', 'engine' => 'SQLite :memory:',
    'method' => 'Fresh migration; no seeders or application data', 'tables' => $schema], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo count($schema)." table definitions exported; fleet database untouched.\n";
