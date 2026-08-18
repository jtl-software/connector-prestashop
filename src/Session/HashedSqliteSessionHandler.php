<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Session;

use Jtl\Connector\Core\Session\SqliteSessionHandler;
use ReturnTypeWillChange;

/**
 * Hashes the session id before it reaches the underlying SQLite storage so
 * plaintext auth tokens are never persisted to disk.
 *
 * TODO: Local workaround for CO-3583. Remove this class and its wiring in
 * controllers/front/api.php once jtl/connector core hashes session ids
 * natively (tracked as CO-3585) and composer.json requires that core
 * version.
 */
class HashedSqliteSessionHandler extends SqliteSessionHandler
{
    /**
     * @param string $sessionId
     * @return bool|string
     */
    #[ReturnTypeWillChange]
    public function read(string $sessionId): bool|string
    {
        return parent::read($this->hash($sessionId));
    }

    /**
     * @param string $sessionId
     * @param string $sessionData
     * @return bool
     */
    #[ReturnTypeWillChange]
    public function write(string $sessionId, string $sessionData): bool
    {
        return parent::write($this->hash($sessionId), $sessionData);
    }

    /**
     * @param string $sessionId
     * @return bool
     */
    public function destroy(string $sessionId): bool
    {
        return parent::destroy($this->hash($sessionId));
    }

    /**
     * @param string $sessionId
     * @return bool
     */
    public function validateId(string $sessionId): bool
    {
        return parent::validateId($this->hash($sessionId));
    }

    /**
     * @param string $sessionId
     * @param string $sessionData
     * @return bool
     */
    public function updateTimestamp(string $sessionId, string $sessionData): bool
    {
        return parent::updateTimestamp($this->hash($sessionId), $sessionData);
    }

    /**
     * @param string $sessionId
     * @return string
     */
    private function hash(string $sessionId): string
    {
        return \hash('sha256', $sessionId);
    }
}
