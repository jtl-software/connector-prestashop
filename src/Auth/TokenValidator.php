<?php

namespace jtl\Connector\Presta\Auth;

use Jtl\Connector\Core\Authentication\TokenValidatorInterface;
use Jtl\Connector\Core\Exception\TokenValidatorException;

class TokenValidator implements TokenValidatorInterface
{
    /**
     * @var string
     */
    protected string $connectorToken;

    /**
     * @param string $connectorToken
     * @throws TokenValidatorException
     */
    public function __construct(string $connectorToken)
    {
        if ($connectorToken === '') {
            throw TokenValidatorException::emptyToken();
        }
        $this->connectorToken = $connectorToken;
    }

    /**
     * @param string $token
     * @return bool
     */
    public function validate(string $token): bool
    {
        return $this->connectorToken === $token;
    }
}
