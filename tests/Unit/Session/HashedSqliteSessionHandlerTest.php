<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use jtl\Connector\Presta\Session\HashedSqliteSessionHandler;
use PHPUnit\Framework\TestCase;

final class HashedSqliteSessionHandlerTest extends TestCase
{
    private string $databaseDir;

    protected function setUp(): void
    {
        $this->databaseDir = \sys_get_temp_dir() . '/jtl-connector-session-test-' . \uniqid();
    }

    protected function tearDown(): void
    {
        if (!\is_dir($this->databaseDir)) {
            return;
        }

        foreach (\glob(\sprintf('%s/*', $this->databaseDir)) ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($this->databaseDir);
    }

    public function testWriteStoresHashedSessionIdNotPlaintext(): void
    {
        $handler   = new HashedSqliteSessionHandler($this->databaseDir);
        $sessionId = 'plaintext-session-id';

        $handler->write($sessionId, 'session-data');

        $db = new \SQLite3(\sprintf('%s/connector.s3db', $this->databaseDir));
        /** @var array{sessionId: string} $row */
        $row = $db->querySingle('SELECT sessionId FROM session', true);
        $db->close();

        self::assertSame(\hash('sha256', $sessionId), $row['sessionId']);
        self::assertNotSame($sessionId, $row['sessionId']);
    }

    public function testReadReturnsPreviouslyWrittenData(): void
    {
        $handler   = new HashedSqliteSessionHandler($this->databaseDir);
        $sessionId = 'another-session-id';

        $handler->write($sessionId, 'my-data');

        self::assertSame('my-data', $handler->read($sessionId));
    }

    public function testReadReturnsEmptyStringForUnknownSession(): void
    {
        $handler = new HashedSqliteSessionHandler($this->databaseDir);

        self::assertSame('', $handler->read('unknown-session-id'));
    }

    public function testValidateIdReturnsTrueForWrittenSession(): void
    {
        $handler   = new HashedSqliteSessionHandler($this->databaseDir);
        $sessionId = 'valid-session-id';

        $handler->write($sessionId, 'data');

        self::assertTrue($handler->validateId($sessionId));
    }

    public function testValidateIdReturnsFalseForUnknownSession(): void
    {
        $handler = new HashedSqliteSessionHandler($this->databaseDir);

        self::assertFalse($handler->validateId('unknown-session-id'));
    }

    public function testDestroyRemovesSession(): void
    {
        $handler   = new HashedSqliteSessionHandler($this->databaseDir);
        $sessionId = 'to-be-destroyed';

        $handler->write($sessionId, 'data');
        $handler->destroy($sessionId);

        self::assertFalse($handler->validateId($sessionId));
    }

    public function testUpdateTimestampReturnsTrueForWrittenSession(): void
    {
        $handler   = new HashedSqliteSessionHandler($this->databaseDir);
        $sessionId = 'timestamp-session-id';

        $handler->write($sessionId, 'data');

        self::assertTrue($handler->updateTimestamp($sessionId, 'data'));
    }
}
