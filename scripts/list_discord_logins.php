<?php
/**
 * List the distinct Discord accounts that have logged in to the map,
 * with the last time each one logged in, most recent first.
 *
 * CLI only. Run it from the PMSF root so the config can find its includes:
 *
 *   cd /var/www/html && php scripts/list_discord_logins.php [--limit=N] [--days=N] [--csv]
 *
 *   --limit=N   only show the N most recent accounts
 *   --days=N    only show accounts that logged in within the last N days
 *   --csv       print CSV instead of a table
 *
 * Reads the `users` table of the manual database ($manualdb in config.php).
 * Session tokens and access tokens are never selected or printed.
 */

// This file lives under the web root, so refuse to run over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
chdir($root);
require $root . '/config/config.php';

if (!isset($manualdb)) {
    fwrite(STDERR, "\$manualdb is not configured in config/config.php, so there are no login records to read.\n");
    exit(1);
}

$opts = getopt('', ['limit:', 'days:', 'csv', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage: php scripts/list_discord_logins.php [--limit=N] [--days=N] [--csv]\n");
    exit(0);
}

$where = [
    'login_system' => 'discord',
    'ORDER' => ['last_loggedin' => 'DESC'],
];
if (isset($opts['days'])) {
    $days = (int) $opts['days'];
    if ($days <= 0) {
        fwrite(STDERR, "--days must be a positive number\n");
        exit(1);
    }
    $where['last_loggedin[>=]'] = time() - $days * 86400;
}
if (isset($opts['limit'])) {
    $limit = (int) $opts['limit'];
    if ($limit <= 0) {
        fwrite(STDERR, "--limit must be a positive number\n");
        exit(1);
    }
    $where['LIMIT'] = $limit;
}

// Medoo binds every value as a prepared-statement parameter.
$rows = $manualdb->select('users', ['id', 'user', 'access_level', 'last_loggedin'], $where);
if ($rows === null || $rows === false) {
    fwrite(STDERR, "Query failed: " . json_encode($manualdb->error) . "\n");
    exit(1);
}

$out = [];
foreach ($rows as $row) {
    // Discord accounts on the new username system have discriminator 0.
    $name = preg_replace('/#0$/', '', (string) $row['user']);
    $out[] = [
        'discord_id'   => (string) $row['id'],
        'username'     => $name,
        'access_level' => $row['access_level'] === null ? '' : (string) $row['access_level'],
        'last_login'   => $row['last_loggedin'] ? date('Y-m-d H:i:s T', (int) $row['last_loggedin']) : 'never recorded',
    ];
}

$headers = ['discord_id', 'username', 'access_level', 'last_login'];

if (isset($opts['csv'])) {
    $fh = fopen('php://stdout', 'w');
    fputcsv($fh, $headers, ',', '"', '\\');
    foreach ($out as $line) {
        // Prefix cells that a spreadsheet would treat as a formula.
        $line = array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $line);
        fputcsv($fh, array_values($line), ',', '"', '\\');
    }
    fclose($fh);
    exit(0);
}

// Strip control characters so a crafted username cannot mess with the terminal.
$clean = fn($v) => preg_replace('/[\x00-\x1F\x7F]/u', '', $v);
$widths = array_map('strlen', array_combine($headers, $headers));
foreach ($out as $i => $line) {
    foreach ($line as $k => $v) {
        $out[$i][$k] = $clean($v);
        $widths[$k] = max($widths[$k], mb_strwidth($out[$i][$k]));
    }
}
$pad = fn($v, $w) => $v . str_repeat(' ', max(0, $w - mb_strwidth($v)));
echo implode('  ', array_map(fn($h) => $pad($h, $widths[$h]), $headers)) . PHP_EOL;
echo implode('  ', array_map(fn($h) => str_repeat('-', $widths[$h]), $headers)) . PHP_EOL;
foreach ($out as $line) {
    echo implode('  ', array_map(fn($h) => $pad($line[$h], $widths[$h]), $headers)) . PHP_EOL;
}
echo PHP_EOL . count($out) . " Discord account(s)" . PHP_EOL;
