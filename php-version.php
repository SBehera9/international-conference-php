<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

echo 'PHP Version: ' . PHP_VERSION . '<br>';
echo 'SAPI: ' . php_sapi_name() . '<br>';
echo 'MySQL: ' . (extension_loaded('pdo_mysql') ? 'PDO enabled' : 'PDO missing');

echo '<br><br>Required extensions:<br>';
echo 'pdo_mysql: ' . (extension_loaded('pdo_mysql') ? '✅' : '❌') . '<br>';
echo 'fileinfo: '  . (extension_loaded('fileinfo') ? '✅' : '❌') . '<br>';
echo 'mbstring: '  . (extension_loaded('mbstring') ? '✅' : '❌') . '<br>';
echo 'intl: '      . (extension_loaded('intl') ? '✅' : '❌ (optional)') . '<br>';

echo '<br>Timezone: ' . date_default_timezone_get() . '<br>';
echo 'UTC check: ' . (date_default_timezone_get() === 'UTC' ? '✅ PASS' : '❌ FAIL — bootstrap did not set UTC');