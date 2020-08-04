<?php

namespace jtl\Connector\Presta\Auth;

use \jtl\Connector\Authentication\ITokenLoader;

class TokenLoader implements ITokenLoader
{
    public function load()
    {
        return \Configuration::get('jtlconnector_pass');
    }
}
