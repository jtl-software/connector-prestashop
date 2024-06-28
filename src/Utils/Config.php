<?php

declare(strict_types=1);

/**
 * @author    Jan Weskamp <jan.weskamp@jtl-software.com>
 * @copyright 2010-2013 JTL-Software GmbH
 */

namespace jtl\Connector\Presta\Utils;

class Config
{
    protected static ?Config $instance = null;
    private static ?\stdClass $data    = null;

    /**
     * constructor
     * externe Instanzierung verbieten
     */
    protected function __construct()
    {
    }

    public static function getData(): ?\stdClass
    {
        self::getInstance();

        return self::$data;
    }

    /**
     * @param string $file
     *
     * @return Config|null
     */
    public static function getInstance(string $file = \CONNECTOR_DIR . '/config/config.json'): ?Config
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        if (\is_null(self::$data)) {
            $content = @\file_get_contents($file);
            if ($content !== false) {
                /** @var \stdClass|null $data */
                $data = \json_decode($content);
                if ($data !== null) {
                    self::$data = $data;
                } else {
                    self::$data = new \stdClass();
                }
            } else {
                self::$data = new \stdClass();
            }
        }

        return self::$instance;
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public static function set(string $name, mixed $value): void
    {
        self::getInstance();
        self::$data->$name = $value;
        self::save();
    }

    /**
     * @return bool
     */
    public static function save(): bool
    {
        self::getInstance();
        if (\file_put_contents(\CONNECTOR_DIR . '/config/config.json', \json_encode(self::$data)) === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public static function get(string $name): mixed
    {
        self::getInstance();

        return self::$data->$name;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function remove(string $name): bool
    {
        self::getInstance();
        if (self::has($name)) {
            unset(self::$data->$name);

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function has(string $name): bool
    {
        self::getInstance();

        return \array_key_exists($name, (array)self::$data);
    }

    /**
     * clone
     * Kopieren der Instanz von aussen ebenfalls verbieten
     */
    protected function __clone()
    {
    }
}
