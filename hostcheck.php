<?php
/**
 * Throwaway host check for the ghaith salih API.
 *
 * Upload to public_html, open it in a browser, screenshot the result, DELETE IT.
 * It prints server details, so it should not sit on a live domain any longer
 * than it takes to read.
 */

function row(string $label, bool $ok, string $detail = '', bool $fatal = true): void {
    $mark  = $ok ? '&#10003;' : ($fatal ? '&#10007;' : '!');
    $class = $ok ? 'ok' : ($fatal ? 'bad' : 'warn');
    printf(
        '<tr class="%s"><td class="m">%s</td><td>%s</td><td class="d">%s</td></tr>',
        $class, $mark, htmlspecialchars($label), htmlspecialchars($detail)
    );
}

$gd = function_exists('gd_info') ? gd_info() : [];

echo '<!doctype html><meta charset="utf-8"><title>host check</title>';
echo '<style>
body{font:14px/1.6 system-ui,sans-serif;background:#111;color:#eee;padding:40px;max-width:760px;margin:auto}
h1{font-size:20px} h2{font-size:14px;text-transform:uppercase;letter-spacing:.1em;opacity:.6;margin:28px 0 8px}
table{border-collapse:collapse;width:100%} td{padding:6px 10px;border-bottom:1px solid #333;vertical-align:top}
.m{width:24px;font-weight:700} .d{opacity:.6;font-size:13px}
.ok .m{color:#4ade80} .bad .m{color:#f87171} .warn .m{color:#fbbf24}
.verdict{margin-top:30px;padding:16px;border-radius:8px;background:#1c1c1c;border:1px solid #333}
</style>';

echo '<h1>Host check &mdash; ghaith salih API</h1>';

// ---------------------------------------------------------------- PHP itself
echo '<h2>PHP</h2><table>';
$v  = PHP_VERSION;
$ok = version_compare($v, '8.4.1', '>=');
row('PHP 8.4.1 or newer', $ok, "found $v");
echo '</table>';

// ------------------------------------------------------------------ images
echo '<h2>Images &mdash; every conversion this app makes is .webp</h2><table>';
row('GD extension',        extension_loaded('gd'), $gd ? ('GD ' . ($gd['GD Version'] ?? '?')) : 'missing');
row('GD: WebP support',    function_exists('imagewebp'), 'without this, every photo upload fails');
row('GD: AVIF support',    function_exists('imageavif'), 'only needed if you upload AVIF files', false);
row('GD: JPEG support',    function_exists('imagejpeg'));
row('exif extension',      extension_loaded('exif'), 'orientation and capture data');
echo '</table>';

// ------------------------------------------------------ other requirements
echo '<h2>Other extensions the app needs</h2><table>';
foreach (['pdo_mysql' => 'database', 'mbstring' => 'text handling', 'intl' => 'dates and locales',
          'zip' => 'package handling', 'bcmath' => 'precise numbers', 'fileinfo' => 'upload type checks',
          'openssl' => 'encryption', 'curl' => 'outbound requests'] as $ext => $why) {
    row($ext, extension_loaded($ext), $why);
}
echo '</table>';

// -------------------------------------------------------- gigapixel tiling
echo '<h2>Gigapixel deep-zoom</h2><table>';
$canExec = function_exists('proc_open') && !in_array('proc_open', array_map('trim',
    explode(',', (string) ini_get('disable_functions'))), true);
row('proc_open allowed', $canExec, 'the tiler runs the vips program', false);

$vips = null;
if ($canExec) {
    $out = @shell_exec('vips --version 2>&1');
    $vips = $out && stripos($out, 'vips') !== false ? trim($out) : null;
}
row('vips installed', (bool) $vips, $vips ?: 'not found &mdash; gigapixel tiling cannot run here', false);
echo '</table>';

// ------------------------------------------------------------------ uploads
echo '<h2>Upload limits &mdash; originals are large</h2><table>';
foreach (['upload_max_filesize' => '64M', 'post_max_size' => '64M',
          'memory_limit' => '256M', 'max_execution_time' => '120'] as $key => $want) {
    $have = (string) ini_get($key);
    $norm = fn($s) => (int) $s * (stripos($s, 'G') ? 1024 : (stripos($s, 'M') ? 1 : (stripos($s, 'K') ? 0 : 1)));
    row("$key", $have === '-1' || $norm($have) >= $norm($want), "is $have, want at least $want", false);
}
echo '</table>';

// ------------------------------------------------------------------ verdict
$blockers = [];
if (!version_compare($v, '8.4.1', '>=')) $blockers[] = 'PHP is too old';
if (!function_exists('imagewebp'))       $blockers[] = 'GD has no WebP support';
if (!extension_loaded('pdo_mysql'))      $blockers[] = 'no MySQL driver';

echo '<div class="verdict">';
if ($blockers) {
    echo '<strong>Blocked:</strong> ' . htmlspecialchars(implode('; ', $blockers))
       . '. The site will not run here until these are fixed.';
} elseif (!$vips) {
    echo '<strong>Will run, with one feature lost.</strong> Everything works except '
       . 'gigapixel deep-zoom, which needs the vips program installed on the server.';
} else {
    echo '<strong>All clear.</strong> Everything the site needs is present, gigapixel included.';
}
echo '</div><p class="d">Delete this file once you have read it.</p>';
