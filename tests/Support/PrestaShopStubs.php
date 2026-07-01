<?php

declare(strict_types=1);

// --- Constants ---
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

if (!defined('_PS_PROD_IMG_DIR_')) {
    define('_PS_PROD_IMG_DIR_', sys_get_temp_dir() . '/jtl_ps_test/prod/');
}

if (!defined('_PS_CAT_IMG_DIR_')) {
    define('_PS_CAT_IMG_DIR_', sys_get_temp_dir() . '/jtl_ps_test/cat/');
}

if (!defined('_PS_MANU_IMG_DIR_')) {
    define('_PS_MANU_IMG_DIR_', sys_get_temp_dir() . '/jtl_ps_test/manu/');
}

if (!defined('_PS_BASE_URL_')) {
    define('_PS_BASE_URL_', 'http://localhost/');
}

if (!defined('_THEME_PROD_DIR_')) {
    define('_THEME_PROD_DIR_', 'img/p/');
}

if (!defined('_THEME_CAT_DIR_')) {
    define('_THEME_CAT_DIR_', 'img/c/');
}

if (!defined('_THEME_MANU_DIR_')) {
    define('_THEME_MANU_DIR_', 'img/m/');
}

if (!defined('CONNECTOR_DIR')) {
    $connectorTestDir = sys_get_temp_dir() . '/jtl_connector_test';
    @mkdir($connectorTestDir . '/config', 0777, true);
    define('CONNECTOR_DIR', $connectorTestDir);
}

// --- Global PS helper functions ---
if (!function_exists('bqSQL')) {
    function bqSQL(string $string): string
    {
        return addslashes($string);
    }
}

if (!function_exists('pSQL')) {
    function pSQL(string $string, bool $htmlOk = false): string
    {
        return addslashes($string);
    }
}

// --- Exceptions ---
if (!class_exists('PrestaShopException')) {
    class PrestaShopException extends \Exception
    {
    }
}

if (!class_exists('PrestaShopDatabaseException')) {
    class PrestaShopDatabaseException extends \RuntimeException
    {
    }
}

// --- ObjectModel base ---
if (!class_exists('ObjectModel')) {
    class ObjectModel
    {
        public ?int $id = null;

        public function __construct(?int $id = null)
        {
            $this->id = $id ?: null;
        }

        public function add(bool $autoDate = true, bool $nullValues = false): bool
        {
            return true;
        }

        public function update(bool $nullValues = false): bool
        {
            return true;
        }

        public function delete(): bool
        {
            return true;
        }

        public function save(bool $nullValues = false, bool $autoDate = true): bool
        {
            return $this->id ? $this->update($nullValues) : $this->add(false, $nullValues);
        }
    }
}

// --- DbQueryCore / DbQuery ---
if (!class_exists('DbQueryCore')) {
    class DbQueryCore
    {
        protected array $query = [
            'type'   => 'SELECT',
            'select' => [],
            'from'   => [],
            'join'   => [],
            'where'  => [],
            'group'  => [],
            'having' => [],
            'order'  => [],
            'limit'  => ['offset' => 0, 'limit' => 0],
        ];

        public function type(string $type): static
        {
            $this->query['type'] = $type;
            return $this;
        }

        public function select(string $fields): static
        {
            if (!empty($fields)) {
                $this->query['select'][] = $fields;
            }
            return $this;
        }

        public function from($table, $alias = null)
        {
            if (!empty($table)) {
                $query = ($table instanceof DbQueryCore)
                    ? '(' . $table->build() . ')'
                    : '`' . $table . '`';
                $this->query['from'][] = $query . ($alias ? ' ' . $alias : '');
            }
            return $this;
        }

        public function join($join)
        {
            if (!empty($join)) {
                $this->query['join'][] = $join;
            }
            return $this;
        }

        public function leftJoin($table, $alias = null, $on = null)
        {
            return $this->join(
                'LEFT JOIN `' . $table . '`'
                . ($alias ? ' `' . $alias . '`' : '')
                . ($on ? ' ON ' . $on : '')
            );
        }

        public function innerJoin($table, $alias = null, $on = null)
        {
            return $this->join(
                'INNER JOIN `' . $table . '`'
                . ($alias ? ' `' . $alias . '`' : '')
                . ($on ? ' ON ' . $on : '')
            );
        }

        public function leftOuterJoin($table, $alias = null, $on = null)
        {
            return $this->join(
                'LEFT OUTER JOIN `' . $table . '`'
                . ($alias ? ' `' . $alias . '`' : '')
                . ($on ? ' ON ' . $on : '')
            );
        }

        public function naturalJoin($table, $alias = null)
        {
            return $this->join(
                'NATURAL JOIN `' . $table . '`'
                . ($alias ? ' `' . $alias . '`' : '')
            );
        }

        public function rightJoin($table, $alias = null, $on = null)
        {
            return $this->join(
                'RIGHT JOIN `' . $table . '`'
                . ($alias ? ' `' . $alias . '`' : '')
                . ($on ? ' ON ' . $on : '')
            );
        }

        public function where($restriction)
        {
            if (!empty($restriction)) {
                $this->query['where'][] = $restriction;
            }
            return $this;
        }

        public function having($restriction)
        {
            if (!empty($restriction)) {
                $this->query['having'][] = $restriction;
            }
            return $this;
        }

        public function orderBy($fields)
        {
            if (!empty($fields)) {
                if (\is_string($fields)) {
                    $this->query['order'][] = $fields;
                } else {
                    $this->query['order'] = \array_merge($this->query['order'], $fields);
                }
            }
            return $this;
        }

        public function groupBy($fields)
        {
            if (!empty($fields)) {
                if (\is_string($fields)) {
                    $this->query['group'][] = $fields;
                } else {
                    $this->query['group'] = \array_merge($this->query['group'], $fields);
                }
            }
            return $this;
        }

        public function limit($limit, $offset = 0)
        {
            $this->query['limit'] = ['offset' => \max($offset, 0), 'limit' => $limit];
            return $this;
        }

        public function build(): string
        {
            $sql  = $this->query['type'] . ' ';
            $sql .= \implode(', ', $this->query['select']) . ' ';
            if (!empty($this->query['from'])) {
                $sql .= 'FROM ' . \implode(', ', $this->query['from']) . ' ';
            }
            if (!empty($this->query['join'])) {
                $sql .= \implode(' ', $this->query['join']) . ' ';
            }
            if (!empty($this->query['where'])) {
                $sql .= 'WHERE ' . \implode(' AND ', $this->query['where']) . ' ';
            }
            if (!empty($this->query['group'])) {
                $sql .= 'GROUP BY ' . \implode(', ', $this->query['group']) . ' ';
            }
            if (!empty($this->query['having'])) {
                $sql .= 'HAVING ' . \implode(', ', $this->query['having']) . ' ';
            }
            if (!empty($this->query['order'])) {
                $sql .= 'ORDER BY ' . \implode(', ', $this->query['order']) . ' ';
            }
            if (($this->query['limit']['limit'] ?? 0) > 0) {
                $sql .= 'LIMIT ' . $this->query['limit']['offset'] . ', ' . $this->query['limit']['limit'];
            }
            return \trim($sql);
        }

        public function __toString(): string
        {
            return $this->build();
        }

        public function getQuery(): array
        {
            return $this->query;
        }
    }
}

if (!class_exists('DbQuery')) {
    class DbQuery extends DbQueryCore
    {
    }
}

// --- Db ---
if (!class_exists('Db')) {
    class Db
    {
        protected static ?Db $instance = null;

        public static function getInstance(bool $slave = false): static
        {
            if (static::$instance === null) {
                static::$instance = new static();
            }
            return static::$instance;
        }

        public static function setInstance(Db $db): void
        {
            static::$instance = $db;
        }

        public static function resetInstance(): void
        {
            static::$instance = null;
        }

        public function execute(mixed $sql, bool $useCache = true): bool
        {
            return true;
        }

        public function executeS(mixed $sql, bool $array = true, bool $useCache = true): array|bool
        {
            return [];
        }

        public function getValue(mixed $sql, bool $useCache = true): mixed
        {
            return null;
        }

        public function escape(string $string, bool $htmlOk = false, bool $bqSql = false): string
        {
            return $string;
        }

        public function delete(
            string $table,
            string $where = '',
            int $limit = 0,
            bool $useCache = true,
            bool $addPrefix = true
        ): bool {
            return true;
        }
    }
}

// --- Configuration ---
if (!class_exists('Configuration')) {
    class Configuration
    {
        protected static array $data = [];

        public static function get(string $name, mixed $default = null): mixed
        {
            return static::$data[$name] ?? $default;
        }

        public static function set(string $name, mixed $value): void
        {
            static::$data[$name] = $value;
        }

        public static function resetAll(): void
        {
            static::$data = [];
        }
    }
}

// --- Context / Language / Shop ---
if (!class_exists('Language')) {
    class Language
    {
        public int $id = 0;
        public string $iso_code = 'en';
        public string $locale = 'en-US';
        public string $language_code = 'en-us';
        public string $name = 'English';
        public bool $active = true;

        public function __construct(int $id = 0)
        {
            $this->id = $id;
        }

        public static array $mockLanguagesList = [];

        public static function getLanguages(bool $active = true, int|bool $idShop = false): array
        {
            return static::$mockLanguagesList;
        }

        public static function resetMock(): void
        {
            static::$mockLanguagesList = [];
        }

        public static function getIdByIso(string $iso, bool $languageCode = false): int
        {
            return 0;
        }
    }
}

if (!class_exists('Shop')) {
    class Shop extends ObjectModel
    {
        public string $name = '';

        public function __construct(int $id = 0)
        {
            $this->id = $id ?: null;
        }
    }
}

if (!class_exists('Context')) {
    class Context
    {
        public static ?Context $context = null;
        public ?Language $language = null;
        public ?Shop $shop = null;
        public mixed $cart = null;
        public mixed $currency = null;
        public mixed $country = null;

        public static function getContext(): ?static
        {
            return static::$context;
        }

        public static function setContext(Context $ctx): void
        {
            static::$context = $ctx;
        }

        public static function resetContext(): void
        {
            static::$context = null;
        }
    }
}

// --- Tools ---
if (!class_exists('Tools')) {
    class Tools
    {
        public static bool $mockStr2urlNull = false;
        public static bool $mockPasswdGenNull = false;

        public static function str2url(string $str): ?string
        {
            if (static::$mockStr2urlNull) {
                return null;
            }
            $result = \preg_replace('/[^a-zA-Z0-9\-]/', '-', \strtolower($str));
            return \is_string($result) ? $result : $str;
        }

        public static function resetMock(): void
        {
            static::$mockStr2urlNull   = false;
            static::$mockPasswdGenNull = false;
        }

        public static function passwdGen(int $length = 8, string $flag = 'ALPHANUMERIC'): ?string
        {
            if (static::$mockPasswdGenNull) {
                return null;
            }
            return 'TestPassword123!';
        }

        public static function hash(string $string): string
        {
            return \hash('sha256', $string);
        }

        public static function substr(string $str, int $start, int $length): string
        {
            return \substr($str, $start, $length);
        }
    }
}

// --- Customer ---
if (!class_exists('Customer')) {
    class Customer extends ObjectModel
    {
        public int $id_shop = 0;
        public int $id_gender = 0;
        public int $id_default_group = 0;
        public int $id_lang = 0;
        public string $lastname = '';
        public string $firstname = '';
        public string $email = '';
        public string $passwd = '';
        public ?string $birthday = null;
        public bool $newsletter = false;
        public bool $active = true;
        public string $website = '';
        public string $company = '';

        /** When set, ALL Customer instances return this id from the constructor. */
        public static ?int $mockIdOverride = null;

        /**
         * Sequence of booleans for consecutive update() calls.
         * Empty = always return true.
         */
        public static array $mockUpdateResults = [];

        public function __construct(int $id = 0)
        {
            $this->id = static::$mockIdOverride ?? ($id ?: null);
        }

        public function update(bool $nullValues = false): bool
        {
            if (!empty(static::$mockUpdateResults)) {
                return (bool)\array_shift(static::$mockUpdateResults);
            }
            return true;
        }

        public static function resetMock(): void
        {
            static::$mockIdOverride    = null;
            static::$mockUpdateResults = [];
        }

        public function addGroups(array $groups): void
        {
        }

        public function updateGroup(array $groups): void
        {
        }
    }
}

// --- Address ---
if (!class_exists('Address')) {
    class Address extends ObjectModel
    {
        public ?int $id_customer = 0;
        public int $id_country = 0;
        public int $id_state = 0;
        public string $alias = '';
        public string $company = '';
        public string $lastname = '';
        public string $firstname = '';
        public string $address1 = '';
        public string $address2 = '';
        public string $postcode = '';
        public string $city = '';
        public string $phone = '';
        public string $phone_mobile = '';
        public string $vat_number = '';

        public function __construct(int $id = 0)
        {
            $this->id = $id ?: null;
        }
    }
}

// --- Category ---
if (!class_exists('Category')) {
    class Category extends ObjectModel
    {
        public static int $mockRootCategoryId = 1;
        public static bool $mockRootCategoryIdNull = false;
        public static bool $mockUpdateResult = true;
        public static bool $mockDeleteShouldThrow = false;

        /** When true, add() returns false and sets id to $mockAddFailId (if non-null). */
        public static bool $mockAddShouldFail = false;
        public static ?int $mockAddFailId = null;

        public int $id_parent = 0;
        public bool $active = true;
        public int $position = 0;
        public array $name = [];
        public array $description = [];
        public array $meta_description = [];
        public array $meta_keywords = [];
        public array $link_rewrite = [];

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        public function add(bool $autoDate = true, bool $nullValues = false): bool
        {
            if (static::$mockAddShouldFail) {
                if (static::$mockAddFailId !== null) {
                    $this->id = static::$mockAddFailId;
                }
                return false;
            }
            return true;
        }

        public function update(bool $nullValues = false): bool
        {
            return static::$mockUpdateResult;
        }

        public function delete(): bool
        {
            if (static::$mockDeleteShouldThrow) {
                throw new \Exception('Category delete failed');
            }
            return true;
        }

        public function deleteImage(): bool
        {
            return true;
        }

        public static function getRootCategory(): static
        {
            $cat     = new static();
            $cat->id = static::$mockRootCategoryIdNull ? null : static::$mockRootCategoryId;
            return $cat;
        }

        public static function resetMock(): void
        {
            static::$mockRootCategoryId       = 1;
            static::$mockRootCategoryIdNull   = false;
            static::$mockUpdateResult         = true;
            static::$mockDeleteShouldThrow    = false;
            static::$mockAddShouldFail        = false;
            static::$mockAddFailId            = null;
        }
    }
}

// --- Manufacturer ---
if (!class_exists('Manufacturer')) {
    class Manufacturer extends ObjectModel
    {
        public string $name = '';
        public bool $active = true;
        public array $description = [];
        public array $meta_title = [];
        public array $meta_keywords = [];
        public array $meta_description = [];

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        public function deleteImage(): bool
        {
            return true;
        }
    }
}

// --- Currency ---
if (!class_exists('Currency')) {
    class Currency extends ObjectModel
    {
        public string $iso_code = 'EUR';
        public float $conversion_rate = 1.0;

        /** @var array<string,mixed>|false */
        public static array|false $mockCurrencyData = false;

        public static int $mockDefaultCurrencyId = 1;

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        public static function getDefaultCurrency(): static
        {
            return new static(1);
        }

        /** @return array<string,mixed>|false */
        public static function getCurrency(int $id): array|false
        {
            return static::$mockCurrencyData;
        }

        public static function getDefaultCurrencyId(): int
        {
            return static::$mockDefaultCurrencyId;
        }

        public static function resetMock(): void
        {
            static::$mockCurrencyData      = false;
            static::$mockDefaultCurrencyId = 1;
        }
    }
}

// --- Carrier ---
if (!class_exists('Carrier')) {
    class Carrier extends ObjectModel
    {
        public const PS_CARRIERS_ONLY = 1;
        public const PS_CARRIERS_AND_CARRIER_MODULES = 2;
        public const PS_CARRIER_MODULES = 3;

        public string $name = '';
        public string $url = '';
        public bool $active = true;

        /** @var array<int, array<string,mixed>> */
        public static array $mockCarriers = [];

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        /** @return array<int, array<string,mixed>> */
        public static function getCarriers(int $idLang, bool $active = false, bool $delete = false, bool $loading = false, ?int $idZone = null, int $moduleCarriers = self::PS_CARRIERS_ONLY): array
        {
            return static::$mockCarriers;
        }

        public static function resetMock(): void
        {
            static::$mockCarriers = [];
        }
    }
}

// --- Group ---
if (!class_exists('Group')) {
    class Group extends ObjectModel
    {
        public string $name = '';

        /** @var array<int, array<string,mixed>> */
        public static array $mockGroups = [];

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        /** @return array<int, array<string,mixed>> */
        public static function getGroups(int $idLang, bool $idShop = false): array
        {
            return static::$mockGroups;
        }

        public static function resetMock(): void
        {
            static::$mockGroups = [];
        }
    }
}

// --- Country ---
if (!class_exists('Country')) {
    class Country extends ObjectModel
    {
        /** @var array<int, array<string, mixed>> returned by getCountries() */
        public static array $mockActiveCountries = [];

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public static function getCountries(int $idLang, bool $active = false): array
        {
            return static::$mockActiveCountries;
        }

        public static function resetMock(): void
        {
            static::$mockActiveCountries = [];
        }
    }
}

// --- Tax ---
if (!class_exists('Tax')) {
    class Tax extends ObjectModel
    {
        /** @var array<int, array<string,mixed>> */
        public static array $mockTaxes = [];

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        /** @return array<int, array<string,mixed>> */
        public static function getTaxes(int $idLang, bool $active = true): array
        {
            return static::$mockTaxes;
        }

        public static function resetMock(): void
        {
            static::$mockTaxes = [];
        }
    }
}

// --- Cart ---
if (!class_exists('Cart')) {
    class Cart extends ObjectModel
    {
        public int $id_currency = 1;

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        public function getProducts(
            bool $refresh = false,
            bool $delete = false,
            ?int $idCurrency = null,
            bool $fullInfos = false,
            bool $keepOrderPrices = false
        ): array {
            return [];
        }

        public function getTaxCountry(): mixed
        {
            return null;
        }

        public function getShopId(): int
        {
            return 0;
        }
    }
}

// --- Order ---
if (!class_exists('Order')) {
    class Order extends ObjectModel
    {
        /** IDs in this set will result in a null id (simulate "not found") */
        public static array $mockNotFoundIds = [];

        public int $id_currency = 1;
        public int $id_carrier = 0;
        public int $id_customer = 0;
        public int $id_address_invoice = 0;
        public int $id_address_delivery = 0;
        public int $id_cart = 0;
        public int $id_lang = 1;
        public string $reference = '';
        public string $module = '';
        public string $payment = '';
        public string $date_add = '';
        public string $invoice_date = '';
        public string $delivery_date = '';
        public float $total_paid = 0.0;
        public float $total_paid_tax_incl = 0.0;
        public float $total_shipping_tax_excl = 0.0;
        public float $total_shipping_tax_incl = 0.0;
        public float $carrier_tax_rate = 0.0;

        public function __construct(?int $id = null)
        {
            if ($id !== null && \in_array($id, static::$mockNotFoundIds, true)) {
                $this->id = null;
            } else {
                $this->id = $id ?: null;
            }
        }

        public static function resetMock(): void
        {
            static::$mockNotFoundIds = [];
        }

        public function getFirstMessage(): ?string
        {
            return null;
        }

        public function getShippingNumber(): ?string
        {
            return null;
        }

        public function getCartRules(): array|int
        {
            return 0;
        }

        public function getIdOrderCarrier(): int
        {
            return 0;
        }

        public function hasBeenDelivered(): int
        {
            return 0;
        }

        public function hasBeenShipped(): int
        {
            return 0;
        }

        public function getCurrentState(): int
        {
            return 0;
        }

        public function setCurrentState(int $state): bool
        {
            return true;
        }
    }
}

// --- OrderCarrier ---
if (!class_exists('OrderCarrier')) {
    class OrderCarrier extends ObjectModel
    {
        public float $shipping_cost_tax_excl = 0.0;
        public float $shipping_cost_tax_incl = 0.0;
        public string $tracking_number = '';

        public static string $mockTrackingNumber = '';
        public static bool $mockUpdateResult = true;
        public static bool $mockUpdateShouldThrow = false;
        public static string $mockUpdateExceptionMessage = '';

        public function __construct(?int $id = null)
        {
            $this->id = $id;
            $this->tracking_number = static::$mockTrackingNumber;
        }

        public function update(bool $nullValues = false): bool
        {
            if (static::$mockUpdateShouldThrow) {
                throw new \Exception(static::$mockUpdateExceptionMessage ?: 'update failed');
            }
            return static::$mockUpdateResult;
        }

        public static function resetMock(): void
        {
            static::$mockTrackingNumber = '';
            static::$mockUpdateResult = true;
            static::$mockUpdateShouldThrow = false;
            static::$mockUpdateExceptionMessage = '';
        }
    }
}

// --- CartRule ---
if (!class_exists('CartRule')) {
    class CartRule extends ObjectModel
    {
        public string $code = '';

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }
    }
}

// --- Feature (PrestaSpecific) ---
if (!class_exists('Feature')) {
    class Feature extends ObjectModel
    {
        public array $name = [];
        public bool $custom = false;
        public array $localizedNames = [];

        /** @var int next auto-increment for addFeatureImport */
        public static int $mockNextFeatureId = 1;

        /** @var array<string, int> name => id cache for addFeatureImport */
        public static array $mockFeatureImportMap = [];

        /** @var array<int, array<string, mixed>> id => row for getFeature */
        public static array $mockFeatureRows = [];

        /** @var array<array<string, mixed>> collected deleteSelection calls */
        public static array $mockDeletedSelections = [];

        public static bool $mockUpdateResult = true;
        public static bool $mockAddResult = true;

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        public function update(bool $nullValues = false): bool
        {
            return static::$mockUpdateResult;
        }

        public function add(bool $autoDate = true, bool $nullValues = false): bool
        {
            return static::$mockAddResult;
        }

        public static function addFeatureImport(string $name): int
        {
            if (!isset(static::$mockFeatureImportMap[$name])) {
                static::$mockFeatureImportMap[$name] = static::$mockNextFeatureId++;
            }
            return static::$mockFeatureImportMap[$name];
        }

        /**
         * @return array<string, mixed>
         */
        public static function getFeature(int $langId, int $featureId): array
        {
            return static::$mockFeatureRows[$featureId] ?? ['id_feature' => $featureId, 'name' => ''];
        }

        public function deleteSelection(array $feature): bool
        {
            static::$mockDeletedSelections[] = $feature;
            return true;
        }

        public static function resetMock(): void
        {
            static::$mockNextFeatureId     = 1;
            static::$mockFeatureImportMap  = [];
            static::$mockFeatureRows       = [];
            static::$mockDeletedSelections = [];
            static::$mockUpdateResult      = true;
            static::$mockAddResult         = true;
        }
    }
}

// --- FeatureValue (PrestaSpecificValue) ---
if (!class_exists('FeatureValue')) {
    class FeatureValue extends ObjectModel
    {
        public int $id_feature = 0;
        public bool $custom = false;
        public array $value = [];

        /** @var int next auto-increment for addFeatureValueImport */
        public static int $mockNextValueId = 100;

        public static bool $mockUpdateResult = true;
        public static bool $mockSaveResult = true;

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        public function update(bool $nullValues = false): bool
        {
            return static::$mockUpdateResult;
        }

        public function save(bool $nullValues = false, bool $autoDate = true): bool
        {
            return static::$mockSaveResult;
        }

        public static function addFeatureValueImport(
            int $featureId,
            string $value,
            int $productId,
            int $langId,
            bool $custom = false
        ): int {
            return static::$mockNextValueId++;
        }

        public static function resetMock(): void
        {
            static::$mockNextValueId  = 100;
            static::$mockUpdateResult = true;
            static::$mockSaveResult   = true;
        }
    }
}

// --- Product ---
if (!class_exists('Product')) {
    class Product extends ObjectModel
    {
        /** @var array<string, mixed>|null */
        public static ?array $mockCoverData = null;

        public float $price = 0.0;
        public float $wholesale_price = 0.0;
        public float $unit_price = 0.0;
        public array $name = [];
        public array $description = [];
        public array $description_short = [];
        public array $link_rewrite = [];
        public array $meta_description = [];
        public array $meta_keywords = [];
        public array $meta_title = [];
        public bool $active = true;
        public bool $on_sale = false;
        public bool $online_only = false;
        public int $id_tax_rules_group = 0;
        public ?int $id_category_default = null;
        public int $id_manufacturer = 0;
        public float $unit_price_ratio = 0.0;
        public string $unity = '';
        public int $id_shop_default = 1;
        public string $ean13 = '';
        public string $upc = '';
        public string $isbn = '';
        public string $reference = '';
        public string $mpn = '';
        public float $height = 0.0;
        public float $width = 0.0;
        public float $depth = 0.0;
        public float $weight = 0.0;
        public int $minimal_quantity = 1;
        public string $available_date = '';
        public string $date_add = '';
        public string $date_upd = '';

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        public function getTaxesRate(): float
        {
            return 0.0;
        }

        public function getCategories(): array
        {
            return [];
        }

        public function updateCategories(array $categoryIds): bool
        {
            return true;
        }

        public function addAttribute(mixed ...$args): int|false
        {
            return 0;
        }

        public function setCarriers(array $carrierIds): void
        {
        }

        public function checkDefaultAttributes(): void
        {
        }

        public function updateAttribute(mixed ...$args): bool
        {
            return true;
        }

        public function deleteAttributeCombination(int $id): bool
        {
            return true;
        }

        public function getAttributesGroups(int $langId, ?int $variationId = null): array
        {
            return [];
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getFeatures(): array
        {
            return [];
        }

        public function addFeatureProductImport(int $productId, int $featureId, int $featureValueId): void
        {
        }

        /** @return array<string, mixed> */
        public static function getCover(int|string $productId): array
        {
            return static::$mockCoverData ?? [];
        }

        public static function resetMock(): void
        {
            static::$mockCoverData = null;
        }
    }
}

// --- Combination ---
if (!class_exists('Combination')) {
    class Combination extends ObjectModel
    {
        public float $price = 0.0;
        public float $wholesale_price = 0.0;
        public float $weight = 0.0;
        public string $reference = '';
        public ?int $id_product = null;
        public string $ean13 = '';
        public string $upc = '';
        public string $isbn = '';
        public string $mpn = '';
        public int $minimal_quantity = 1;

        /** @var array<int> captured by setAttributes() */
        public array $capturedAttributeIds = [];

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        public function setAttributes(array $ids): bool
        {
            $this->capturedAttributeIds = $ids;
            return true;
        }
    }
}

// --- SpecificPrice ---
if (!class_exists('SpecificPrice')) {
    class SpecificPrice extends ObjectModel
    {
        public int $id_product = 0;
        public int $id_product_attribute = 0;
        public int $id_group = 0;
        public float $price = 0.0;
        public int $from_quantity = 1;
        public int $id_shop = 0;
        public int $id_currency = 0;
        public int $id_country = 0;
        public int $id_customer = 0;
        public float $reduction = 0.0;
        public string $reduction_type = 'amount';
        public int $reduction_tax = 0;
        public string $from = '0000-00-00 00:00:00';
        public string $to = '0000-00-00 00:00:00';

        public function __construct(int|string|null $id = null)
        {
            $this->id = $id !== null ? (int)$id : null;
        }
    }
}

// --- StockAvailable ---
if (!class_exists('StockAvailable')) {
    class StockAvailable
    {
        public static function setQuantity(
            int $productId,
            int $productAttributeId = 0,
            int $quantity = 0,
            ?int $shopId = null
        ): bool {
            return true;
        }

        public static function getQuantityAvailableByProduct(
            ?int $productId = null,
            int $productAttributeId = 0,
            ?int $shopId = null
        ): int {
            return 0;
        }
    }
}

// --- AttributeGroup ---
if (!class_exists('AttributeGroup')) {
    class AttributeGroup extends ObjectModel
    {
        public array $name = [];
        public array $public_name = [];
        public string $group_type = 'select';
        public bool $is_color_group = false;
        public int $position = 0;

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }
    }
}

// --- Image ---
if (!class_exists('Image')) {
    class Image extends ObjectModel
    {
        public string|int $id_product = 0;
        public int $position = 0;
        public mixed $cover = null;
        public array $legend = [];

        public static int $mockNextId = 42;

        public function __construct(?int $id = null)
        {
            $this->id = $id;
        }

        public function save(bool $nullValues = false, bool $autoDate = true): bool
        {
            if ($this->id === null) {
                $this->id = static::$mockNextId++;
            }
            return true;
        }

        public function getPathForCreation(): string
        {
            return sys_get_temp_dir() . '/jtl_ps_test/path_for_creation/' . $this->id;
        }

        public static function getImgFolderStatic(int|string $id): string
        {
            return (string)$id . '/';
        }

        public static function resetMock(): void
        {
            static::$mockNextId = 42;
        }
    }
}

// --- ImageManager ---
if (!class_exists('ImageManager')) {
    class ImageManager
    {
        public static function resize(
            string $file,
            string $dst,
            ?int $width = null,
            ?int $height = null,
            string $type = 'jpg'
        ): bool {
            return true;
        }
    }
}

// --- ImageType ---
if (!class_exists('ImageType')) {
    class ImageType
    {
        /** @var array<int, array<string, mixed>> */
        public static array $mockImageTypes = [];

        /** @return array<int, array<string, mixed>> */
        public static function getImagesTypes(string $type = ''): array
        {
            return static::$mockImageTypes;
        }

        public static function resetMock(): void
        {
            static::$mockImageTypes = [];
        }
    }
}

// --- Hook ---
if (!class_exists('Hook')) {
    class Hook
    {
        public static bool $mockThrow = false;

        public static function exec(string $hookName, array $hookArgs = []): mixed
        {
            if (static::$mockThrow) {
                throw new \PrestaShopException('Hook exception');
            }
            return null;
        }

        public static function resetMock(): void
        {
            static::$mockThrow = false;
        }
    }
}

// --- ProductAttribute ---
if (!class_exists('ProductAttribute')) {
    class ProductAttribute extends ObjectModel
    {
        public int $id_attribute_group = 0;
        /**
         * In real PrestaShop, ProductAttribute::$name is multilingual (array) when constructed
         * without a language id (push path: $attribute->name[$key] = ...) and a scalar string
         * when constructed with a specific langId (pull path: $comb->name as a string).
         */
        public mixed $name = '';
        public int $position = 0;
        public string $color = '';

        public function __construct(?int $id = null, ?int $idLang = null)
        {
            $this->id   = $id;
            $this->name = $idLang !== null ? '' : [];
        }
    }
}

