<?php

/**
 * Set precise Google Maps embed URLs on each faculty contact page.
 *
 * Sources:
 * - FSEG / FLSH: Campus Mutanga OSM way 124316871 (centroid ≈ -3.37606, 29.38333)
 * - FSI: Campus Kiriri / Chaussée Prince Louis Rwagasore (Nominatim ≈ -3.39507, 29.37161)
 * - MED: CHUK Kamenge (Nominatim Hôpital Universitaire de Kamenge ≈ -3.35547, 29.38567)
 * - FABI: Campus Zege, Gitega (RN15) — place query + Gitega/Zege area ≈ -3.4085, 29.9340
 *
 * Usage: php scripts/update-faculty-maps.php
 */

declare(strict_types=1);

function loadEnvValue(string $envPath, string $key): ?string
{
    if (! is_file($envPath)) {
        return null;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k === $key) {
            return trim($v, " \t\"'");
        }
    }

    return null;
}

function googleEmbed(float $lat, float $lon, string $label, int $zoom = 17): string
{
    $q = rawurlencode(sprintf('%.6f,%.6f (%s)', $lat, $lon, $label));

    return "https://www.google.com/maps?q={$q}&hl=fr&z={$zoom}&output=embed";
}

$maps = [
    'fseg' => [
        'lat'     => -3.376061,
        'lon'     => 29.383330,
        'label'   => 'FSEG — Campus Mutanga, Université du Burundi',
        'address' => 'Campus Mutanga, Avenue de l’UNESCO / Université du Burundi, B.P. 1550, Bujumbura',
        'zoom'    => 17,
    ],
    'flsh' => [
        'lat'     => -3.376061,
        'lon'     => 29.383330,
        'label'   => 'FLSH — Campus Mutanga, Université du Burundi',
        'address' => 'Campus Mutanga, Université du Burundi, B.P. 1550, Bujumbura',
        'zoom'    => 17,
    ],
    'fsi' => [
        'lat'     => -3.389031,
        'lon'     => 29.375322,
        'label'   => 'FSI — 164 Chaussée Prince Louis Rwagasore, Campus Kiriri',
        'address' => '164, Chaussée Prince Louis Rwagasore, Campus Kiriri, B.P. 2700, Bujumbura',
        'zoom'    => 17,
    ],
    'med' => [
        'lat'     => -3.355472,
        'lon'     => 29.385669,
        'label'   => 'Faculté de Médecine — CHUK Kamenge',
        'address' => 'Centre Hospitalo-Universitaire de Kamenge (CHUK), Boulevard de Mwewi Gisabo, B.P. 2210, Bujumbura',
        'zoom'    => 17,
    ],
    'fabi' => [
        // Campus Zege (Gitega), RN15 — quartier Zege north of Gitega centre
        'lat'     => -3.408500,
        'lon'     => 29.934000,
        'label'   => 'FABI — Campus Zege, Gitega',
        'address' => 'Campus Zege (RN15), Gitega — Faculté d’Agronomie et de Bio-Ingénierie, Université du Burundi',
        'zoom'    => 15,
    ],
];

$envFile = dirname(__DIR__) . '/fseg/.env';
$db = new mysqli(
    loadEnvValue($envFile, 'database.default.hostname') ?? '127.0.0.1',
    loadEnvValue($envFile, 'database.default.username') ?? 'root',
    loadEnvValue($envFile, 'database.default.password') ?? '',
    loadEnvValue($envFile, 'database.default.database') ?? 'ub_shared',
    (int) (loadEnvValue($envFile, 'database.default.port') ?? '3306')
);
if ($db->connect_error) {
    fwrite(STDERR, $db->connect_error . PHP_EOL);
    exit(1);
}
$db->set_charset('utf8mb4');
$now = date('Y-m-d H:i:s');

foreach ($maps as $slug => $info) {
    $mapUrl = googleEmbed($info['lat'], $info['lon'], $info['label'], $info['zoom']);

    $site = $db->query('SELECT id FROM sites WHERE slug = "' . $db->real_escape_string($slug) . '" LIMIT 1')->fetch_assoc();
    if ($site === null) {
        echo "SKIP {$slug}: site missing\n";
        continue;
    }
    $siteId = (int) $site['id'];

    $page = $db->query('SELECT id, content FROM pages WHERE site_id = ' . $siteId . ' AND `key` = "contact" LIMIT 1')->fetch_assoc();
    if ($page === null) {
        echo "SKIP {$slug}: contact page missing\n";
        continue;
    }

    $content = json_decode((string) $page['content'], true);
    if (! is_array($content)) {
        $content = [];
    }
    $content['map_url'] = $mapUrl;
    $content['banner_subtitle'] = $info['label'];
    $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt = $db->prepare('UPDATE pages SET content = ?, updated_at = ? WHERE id = ?');
    $pageId = (int) $page['id'];
    $stmt->bind_param('ssi', $json, $now, $pageId);
    $stmt->execute();

    // Keep sites.address + contact.address setting in sync
    $addr = $info['address'];
    $u = $db->prepare('UPDATE sites SET address = ?, updated_at = ? WHERE id = ?');
    $u->bind_param('ssi', $addr, $now, $siteId);
    $u->execute();

    $sel = $db->prepare('SELECT id FROM settings WHERE site_id = ? AND `key` = "contact.address" LIMIT 1');
    $sel->bind_param('i', $siteId);
    $sel->execute();
    $existing = $sel->get_result()->fetch_assoc();
    if ($existing) {
        $sid = (int) $existing['id'];
        $upd = $db->prepare('UPDATE settings SET value = ?, updated_at = ? WHERE id = ?');
        $upd->bind_param('ssi', $addr, $now, $sid);
        $upd->execute();
    } else {
        $class = 'App\\Settings\\Site';
        $key = 'contact.address';
        $type = 'string';
        $ctx = 'contact';
        $ins = $db->prepare('INSERT INTO settings (site_id, class, `key`, value, type, context, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)');
        $ins->bind_param('isssssss', $siteId, $class, $key, $addr, $type, $ctx, $now, $now);
        $ins->execute();
    }

    echo "OK {$slug} -> {$info['lat']},{$info['lon']}\n";
    echo "   {$mapUrl}\n";
}

echo "Done.\n";
