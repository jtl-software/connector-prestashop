<?php

declare(strict_types=1);

/*
 * Smoke test for the release ZIP. It unpacks the ZIP and, each step in its own PHP process:
 *  - constructs the module class the way PrestaShop does when it lists modules, which must work without the
 *    bundled libraries (see JTLConnector::__construct()),
 *  - sends JTL-Wawi RPC calls through the module's front controller (controllers/front/api.php), like separate
 *    HTTP requests.
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
    if ($session !== '') {
        $_POST['jtlauth'] = $session;
    }

    require $moduleDir . '/controllers/front/api.php';
    (new JtlconnectorApiModuleFrontController())->initContent();
    // initContent() always exits, a request process must never continue with the test driver below
    exit(1);
}
// phpcs:enable

$fail = static function (string $message): never {
    \fwrite(\STDERR, $message . "\n");
    exit(1);
};

$zipFile = $argv[1] ?? '';
$workDir = \sys_get_temp_dir() . '/jtlconnector-smoke-' . \bin2hex(\random_bytes(4));
$zip     = new ZipArchive();
if ($zipFile === '' || !\mkdir($workDir, 0700, true) || $zip->open($zipFile) !== true || !$zip->extractTo($workDir)) {
    $fail("Cannot extract release ZIP '$zipFile'");
}
$zip->close();
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
$moduleDir = $workDir . '/jtlconnector';
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
        [\PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'error_reporting=' . (\E_ALL & ~\E_DEPRECATED), __FILE__, ...$arguments],
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

/**
 * @param array<string, mixed> $params
 * @return array<mixed>
 */
$call = static function (string $method, array $params, string $session = '') use ($run, $fail, $moduleDir): array {
    [$response, $stdout, $stderr, $exit] = $run(['--rpc', $moduleDir, $method, (string)\json_encode((object)$params), $session]);
    if (!\is_array($response) || ($response['error'] ?? null) !== null || !\is_array($response['result'] ?? null) || $stderr !== '' || $exit !== 0) {
        $fail("$method failed (exit $exit)\n--- response ---\n$stdout\n--- stderr ---\n$stderr");
    }
    echo "$method: OK\n";

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

$session  = (string)($call('core.connector.auth', ['token' => TOKEN])['sessionId'] ?? '');
$identify = $call('connector.identify', [], $session);
$features = $call('core.connector.features', [], $session);
if ($session === '' || !\is_string($identify['endpointVersion'] ?? null) || !\is_array($features['entities'] ?? null)) {
    $fail('Unexpected RPC results');
}

// Security regression guard (CO-3709): the scoped core must still reject unauthenticated access. Error codes are
// AuthenticationException 790, SessionException NO_SESSION 789 and INVALID_SESSION -32000.
$expectError('core.connector.auth', ['token' => 'the-wrong-token'], '', 790);
$expectError('connector.identify', [], '', 789);
$expectError('connector.identify', [], '0123456789abcdef0123456789abcdef', -32000);

// The crux of CO-3709: an existing session that never passed authentication must be rejected, not just an unknown id.
[, , $seedStderr, $seedExit] = $run(['--seed-unauthenticated-session', $moduleDir, 'unauthenticated-session-id']);
if ($seedExit !== 0 || $seedStderr !== '') {
    $fail("seeding an unauthenticated session failed\n--- stderr ---\n$seedStderr");
}
$expectError('connector.identify', [], 'unauthenticated-session-id', -32000);

// The plaintext session id must never be written to the log (it is the jtlauth bearer credential).
foreach (\glob($moduleDir . '/var/log/*.log') ?: [] as $logFile) {
    if (\str_contains((string)\file_get_contents($logFile), $session)) {
        $fail('the plaintext session id leaked into ' . \basename($logFile));
    }
}
echo "no plaintext session id in the logs: OK\n";

echo "Release ZIP smoke test passed\n";
