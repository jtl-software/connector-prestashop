<?php

declare(strict_types=1);

/*
 * Smoke test for the release ZIP. It checks that no ZIP entry (directory or file) except the runtime dirs and
 * db/connector.s3db is world-writable and that the runtime dirs ship web access protection, unpacks the ZIP and, each
 * step in its own PHP process:
 *  - constructs the module class the way PrestaShop does when it lists modules, which must work without the
 *    bundled libraries (see JTLConnector::__construct()),
 *  - runs the 3.0.0 upgrade script on an install with the legacy logs/ dir (and checks it removes the manufacturer link
 *    without endpoint that 2.x saved and migrates the back office menu entry),
 *  - sends JTL-Wawi RPC calls through the module's front controller (controllers/front/api.php), like separate
 *    HTTP requests: with a fresh install's defaults and with developer logging, incl. the CO-3709 rejection checks,
 *    followed by a check that no log contains a session id or token.
 * Only the PrestaShop classes these steps touch are stubbed. This catches what PHPUnit and PHPStan cannot see because
 * they run against the unscoped lib/: bundled classes the scoped build fails to load, e.g. class names that are only
 * built as strings.
 *
 * Usage: php smoke-test-zip.php <release-zip>
 */

const TOKEN = 'smoke-test-token';

// phpcs:disable PSR1.Classes.ClassDeclaration
if (($argv[1] ?? '') === '--construct') {
    #[AllowDynamicProperties]
    class Module
    {
        public function __construct()
        {
        }

        public function l(string $string): string
        {
            return $string;
        }
    }

    $moduleDir = $argv[2];
    \define('_PS_MODULE_DIR_', \dirname($moduleDir) . '/');
    \define('_PS_VERSION_', '9.0.3');

    require $moduleDir . '/jtlconnector.php';
    $module = new JTLConnector();
    $vendor = \array_filter(
        \get_declared_classes(),
        static fn(string $class): bool => \str_starts_with($class, 'JtlConnectorPrestaVendor\\')
            || $class === 'Composer\Autoload\ClassLoader'
    );
    echo \json_encode(['version' => $module->version, 'loadedBundledClasses' => \array_values($vendor)]);
    exit(0);
}

if (($argv[1] ?? '') === '--seed-unauthenticated-session') {
    // Writes a session row WITHOUT the authenticated marker, as a failed core.connector.auth would leave behind,
    // using the bundled (scoped) session handler. The driver then checks the core rejects it (CO-3709).
    [, , $moduleDir, $sessionId] = $argv;
    require $moduleDir . '/lib/autoload.php';
    $handler = new \Jtl\Connector\Core\Session\SqliteSessionHandler($moduleDir . '/var');
    // Fail loudly if the row was not actually written/readable, otherwise the rejection test below would silently
    // degrade to the "unknown session id" case instead of exercising the existing-but-unauthenticated one.
    if ($handler->write($sessionId, '') !== true || $handler->validateId($sessionId) !== true) {
        \fwrite(\STDERR, 'could not seed an unauthenticated session row');
        exit(1);
    }
    exit(0);
}

if (($argv[1] ?? '') === '--rpc') {
    [, , $moduleDir, $method, $params, $session] = $argv;
    // how jtlauth is sent: 'post' (JTL-Wawi's way) or 'query' (also accepted by the core, ends up in the logged URI)
    $transport = $argv[6] ?? 'post';

    class ModuleFrontController
    {
    }

    class Configuration
    {
        public static function get(string $key): string
        {
            return $key === 'jtlconnector_pass' ? TOKEN : '';
        }
    }

    class Db
    {
        public static function getInstance(): self
        {
            return new self();
        }
    }

    \define('CONNECTOR_DIR', $moduleDir . '/');
    $_POST['jtlrpc'] = \json_encode([
        'method' => $method,
        'params' => \json_decode($params, true),
        'jtlrpc' => '2.0',
        'id'     => 'smoke-test',
    ]);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI']    = '/index.php?fc=module&module=jtlconnector&controller=api';
    if ($session !== '' && $transport === 'query') {
        $_GET['jtlauth']          = $session;
        $_SERVER['REQUEST_URI']  .= '&jtlauth=' . \rawurlencode($session);
        $_SERVER['QUERY_STRING']  = (string)\parse_url($_SERVER['REQUEST_URI'], \PHP_URL_QUERY);
    } elseif ($session !== '') {
        $_POST['jtlauth'] = $session;
    }

    require $moduleDir . '/controllers/front/api.php';
    (new JtlconnectorApiModuleFrontController())->initContent();
    // initContent() always exits, a request process must never continue with the test driver below
    exit(1);
}

if (($argv[1] ?? '') === '--upgrade') {
    // Runs a module upgrade script the way PrestaShop does: after the new files were extracted, with the module object.
    [, , $moduleDir, $version] = $argv;
    \define('_PS_VERSION_', '9.0.3');
    \define('_DB_PREFIX_', 'ps_');

    function pSQL(string $string): string
    {
        return \addslashes($string);
    }

    // prints the queries an upgrade script runs, for the driver to check
    class Db
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function execute(string $sql): bool
        {
            echo "SQL: $sql\n";
            return true;
        }

        public function getValue(string $sql): bool
        {
            return false;
        }
    }

    // the back office menu entry of a release before 3.0.0, id 7; prints what the upgrade saves
    class Tab
    {
        public int $id;
        public string $class_name = '';

        public function __construct(int $id = 0)
        {
            $this->id = $id;
        }

        public static function getIdFromClassName(string $className): int
        {
            return $className === 'jtlconnector' ? 7 : 0;
        }

        public function save(): bool
        {
            echo "TAB: save $this->id $this->class_name\n";
            return true;
        }

        public static function initAccess(int $idTab): bool
        {
            return true;
        }
    }

    $module = new class ($moduleDir . '/') {
        public string $name = 'jtlconnector';

        public function __construct(private string $localPath)
        {
        }

        public function getLocalPath(): string
        {
            return $this->localPath;
        }
    };
    require $moduleDir . '/upgrade/install-' . $version . '.php';
    $upgrade = 'upgrade_module_' . \str_replace('.', '_', $version);
    exit($upgrade($module) === true ? 0 : 1);
}
// phpcs:enable

$fail = static function (string $message): never {
    \fwrite(\STDERR, $message . "\n");
    exit(1);
};

$zipFile = $argv[1] ?? '';
$workDir = \sys_get_temp_dir() . '/jtlconnector-smoke-' . \bin2hex(\random_bytes(4));
$zip     = new ZipArchive();
if ($zipFile === '' || !\mkdir($workDir, 0700, true)) {
    $fail("Cannot extract release ZIP '$zipFile'");
}
// registered before anything can fail, so a failing check does not leave the unpacked ZIP behind
\register_shutdown_function(static function () use ($workDir): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($workDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
    }
    \rmdir($workDir);
});
if ($zip->open($zipFile) !== true || !$zip->extractTo($workDir)) {
    $fail("Cannot extract release ZIP '$zipFile'");
}
// Unpacked with unzip on a server, the stored modes apply: only the runtime dirs and db/connector.s3db (shipped
// empty, install() requires it to be writable) may be world-writable, never code.
$writable = [
    'jtlconnector/config/',
    'jtlconnector/db/',
    'jtlconnector/db/connector.s3db',
    'jtlconnector/tmp/',
    'jtlconnector/var/',
];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string)$zip->getNameIndex($i);
    if (\in_array($name, $writable, true)) {
        continue;
    }
    $zip->getExternalAttributesIndex($i, $opsys, $attributes);
    $mode = ($attributes >> 16) & 0777;
    if ($opsys === ZipArchive::OPSYS_UNIX && ($mode & 0002) !== 0) {
        $fail(\sprintf('%s is world-writable in the release ZIP (mode %o)', $name, $mode));
    }
}
echo "no world-writable code in the ZIP: OK\n";
$zip->close();
$moduleDir = $workDir . '/jtlconnector';
// Every directory that holds runtime data (config, sessions, logs, plugins) ships web access protection.
foreach (['config', 'db', 'plugins', 'tmp', 'var'] as $dir) {
    $htaccess = (string)@\file_get_contents("$moduleDir/$dir/.htaccess");
    if (!\str_contains($htaccess, 'Deny from all') || !\is_file("$moduleDir/$dir/index.php")) {
        $fail("$dir/ is shipped without a 'Deny from all' .htaccess and an index.php");
    }
}
if (\file_exists($moduleDir . '/logs')) {
    $fail('the legacy logs/ dir must not be shipped, JTLConnector::install() moves it to var/log');
}
echo "access protection of the runtime dirs: OK\n";

// done by JTLConnector::install()
\copy($moduleDir . '/config/features.example.json', $moduleDir . '/config/features.json');

/**
 * Runs this script in a child process with the given arguments.
 *
 * @param list<string> $arguments
 * @return array{0: mixed, 1: string, 2: string, 3: int} decoded stdout, raw stdout, stderr, exit code
 */
$run = static function (array $arguments): array {
    // stderr goes to a file: reading two pipes one after the other can block both processes on a full pipe
    $stderrFile = (string)\tempnam(\sys_get_temp_dir(), 'jtlconnector-smoke-stderr');
    $process    = \proc_open(
        [
            \PHP_BINARY,
            '-d', 'display_errors=stderr',
            '-d', 'error_reporting=' . (\E_ALL & ~\E_DEPRECATED),
            // logged stack traces keep full arguments, so a leaked session id or token is not cut to a prefix that
            // the leak check below would miss
            '-d', 'zend.exception_ignore_args=0',
            '-d', 'zend.exception_string_param_max_len=1000000',
            __FILE__,
            ...$arguments,
        ],
        [1 => ['pipe', 'w'], 2 => ['file', $stderrFile, 'w']],
        $pipes
    );
    $stdout = (string)\stream_get_contents($pipes[1]);
    $exit   = \proc_close($process);
    $stderr = (string)\file_get_contents($stderrFile);
    \unlink($stderrFile);

    return [\json_decode($stdout, true), $stdout, $stderr, $exit];
};

[$construct, $stdout, $stderr, $exit] = $run(['--construct', $moduleDir]);
// same expression as in JTLConnector::__construct()
\preg_match('/^version:\s*[\'"]?([^\s\'"]+)/m', (string)\file_get_contents($moduleDir . '/build-config.yaml'), $buildConfig);
if (!\is_array($construct) || $construct['version'] !== ($buildConfig[1] ?? null) || $construct['loadedBundledClasses'] !== [] || $stderr !== '' || $exit !== 0) {
    $fail("Constructing the module failed (exit $exit)\n--- output ---\n$stdout\n--- stderr ---\n$stderr");
}
echo "new JTLConnector(): OK\n";

// An install upgraded in place from an old version still has the unprotected legacy logs/ dir: the 3.0.0 upgrade
// script (run from the scoped ZIP) must move it to var/log. It must also remove the manufacturer link without endpoint
// that 2.x saved (CO-3735).
\mkdir($moduleDir . '/logs');
\file_put_contents($moduleDir . '/logs/legacy.log', 'legacy log');
\file_put_contents($moduleDir . '/logs/.htaccess', 'Deny from all');
[, $stdout, $stderr, $exit] = $run(['--upgrade', $moduleDir, '3.0.0']);
if ($exit !== 0 || $stderr !== '' || \file_exists($moduleDir . '/logs') || !\is_file($moduleDir . '/var/log/legacy.log')) {
    $fail("upgrade 3.0.0 did not move logs/ to var/log (exit $exit)\n--- output ---\n$stdout\n--- stderr ---\n$stderr");
}
echo "upgrade 3.0.0 moves the legacy logs/ dir: OK\n";
if (!\str_contains($stdout, 'SQL: DELETE FROM `jtl_connector_link_manufacturer` WHERE `endpoint_id` = 0')) {
    $fail("upgrade 3.0.0 did not remove the manufacturer link without endpoint\n--- output ---\n$stdout");
}
echo "upgrade 3.0.0 removes the manufacturer link without endpoint: OK\n";
// PrestaShop 9 only opens the menu entry with the admin controller of its class name (CO-3743)
if (
    !\str_contains($stdout, 'TAB: save 7 AdminJtlconnector')
    || \substr_count($stdout, 'SQL: UPDATE `ps_authorization_role` SET `slug` = "ROLE_MOD_TAB_ADMINJTLCONNECTOR_') !== 4
) {
    $fail("upgrade 3.0.0 did not migrate the back office menu entry\n--- output ---\n$stdout");
}
echo "upgrade 3.0.0 migrates the back office menu entry: OK\n";

/**
 * @param array<string, mixed> $params
 * @return array<mixed>
 */
$call = static function (string $method, array $params, string $session = '', string $transport = 'post') use ($run, $fail, $moduleDir): array {
    [$response, $stdout, $stderr, $exit] = $run(['--rpc', $moduleDir, $method, (string)\json_encode((object)$params), $session, $transport]);
    if (!\is_array($response) || ($response['error'] ?? null) !== null || !\is_array($response['result'] ?? null) || $stderr !== '' || $exit !== 0) {
        $fail("$method failed (exit $exit)\n--- response ---\n$stdout\n--- stderr ---\n$stderr");
    }
    echo $transport === 'post' ? "$method: OK\n" : "$method (jtlauth in the query string): OK\n";

    return $response['result'];
};

/**
 * Asserts the RPC returns the given JSON-RPC error code (and nothing leaked to stderr).
 *
 * @param array<string, mixed> $params
 * @return void
 */
$expectError = static function (string $method, array $params, string $session, int $expectedCode) use ($run, $fail, $moduleDir): void {
    [$response, $stdout, $stderr, $exit] = $run(['--rpc', $moduleDir, $method, (string)\json_encode((object)$params), $session]);
    $code = $response['error']['code'] ?? null;
    if (!\is_array($response) || $code !== $expectedCode || ($response['result'] ?? null) !== null || $stderr !== '' || $exit !== 0) {
        $fail("$method should have failed with code $expectedCode (exit $exit)\n--- response ---\n$stdout\n--- stderr ---\n$stderr");
    }
    echo "$method (rejected with $expectedCode): OK\n";
};

/**
 * Counts the debug-level lines in the connector logs (the core's default "line" log format: "[time] channel.LEVEL: ").
 */
$countDebugLogLines = static function () use ($moduleDir): int {
    $count = 0;
    foreach (\glob($moduleDir . '/var/log/*.log') ?: [] as $logFile) {
        $count += (int)\preg_match_all('/^\[[^\]]+\] [\w.-]+\.DEBUG: /m', (string)\file_get_contents($logFile));
    }

    return $count;
};

// The RPC sequence runs twice: with a fresh install's defaults (no config.json, info-level logging) and with developer
// logging on (controllers/front/api.php switches the core to debug level). The leak check below needs the second
// pass: the session and RPC details it looks for are only logged at debug level.
$secrets = [TOKEN, 'the-wrong-token'];
foreach (['default config' => null, 'developer logging' => ['debug' => true]] as $pass => $config) {
    if ($config !== null && \file_put_contents($moduleDir . '/config/config.json', \json_encode($config)) === false) {
        $fail('Cannot write config/config.json');
    }
    echo "--- $pass\n";

    $session  = (string)($call('core.connector.auth', ['token' => TOKEN])['sessionId'] ?? '');
    $identify = $call('connector.identify', [], $session);
    $features = $call('core.connector.features', [], $session);
    if ($session === '' || !\is_string($identify['endpointVersion'] ?? null) || !\is_array($features['entities'] ?? null)) {
        $fail('Unexpected RPC results');
    }
    $secrets[] = $session;
    // the core also accepts jtlauth in the query string; the logged request URI must not reveal it either
    $call('connector.identify', [], $session, 'query');

    // Security regression guard (CO-3709): the scoped core must still reject unauthenticated access. Error codes are
    // AuthenticationException 790, SessionException NO_SESSION 789 and INVALID_SESSION -32000.
    $expectError('core.connector.auth', ['token' => 'the-wrong-token'], '', 790);
    $expectError('connector.identify', [], '', 789);
    $expectError('connector.identify', [], '0123456789abcdef0123456789abcdef', -32000);

    // The crux of CO-3709: an existing session that never passed authentication must be rejected, not just an unknown
    // id. Each pass seeds its own row.
    $unauthenticatedSession = 'unauthenticated-session-id-' . ($config === null ? 'default' : 'debug');
    [, , $seedStderr, $seedExit] = $run(['--seed-unauthenticated-session', $moduleDir, $unauthenticatedSession]);
    if ($seedExit !== 0 || $seedStderr !== '') {
        $fail("seeding an unauthenticated session failed\n--- stderr ---\n$seedStderr");
    }
    $expectError('connector.identify', [], $unauthenticatedSession, -32000);

    $debugLines = $countDebugLogLines();
    if ($config === null && $debugLines !== 0) {
        $fail("the default config wrote $debugLines debug log lines, a fresh install must log at info level");
    }
    if ($config !== null && $debugLines === 0) {
        // without debug output the leak check below would not cover what developer logging writes
        $fail('developer logging wrote no debug log lines to var/log, the leak check cannot run');
    }
    echo "$pass: $debugLines debug log lines\n";
}

// Neither the plaintext session ids (the jtlauth bearer credential) nor the submitted tokens may be written to the log.
foreach (\glob($moduleDir . '/var/log/*.log') ?: [] as $logFile) {
    $log = (string)\file_get_contents($logFile);
    foreach ($secrets as $secret) {
        if (\str_contains($log, $secret)) {
            $fail('a plaintext session id or token leaked into ' . \basename($logFile));
        }
    }
}
echo "no plaintext session id or token in the logs: OK\n";

echo "Release ZIP smoke test passed\n";
