<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Utils;

use DbQuery;

class QueryBuilder
{
    /**
     * List of data to build the query.
     *
     * @var array{
     *      type: string,
     *      select: array<int,string>,
     *      from: array<int,string>,
     *      join: array<int,string>,
     *      where: array<int,string>,
     *      group: array<int,string>,
     *      having: array<int,string>,
     *      order: array<int,string>,
     *      limit: array{
     *       offset: int<0, max>,
     *       limit: int
     *   }
     *  }
     */
    protected array $query = [
        'type' => 'SELECT',
        'select' => [],
        'from' => [],
        'join' => [],
        'where' => [],
        'group' => [],
        'having' => [],
        'order' => [],
        'limit' => ['offset' => 0, 'limit' => 0],
    ];

    protected bool $usePrefix = true;

    /**
     * Sets type of the query.
     *
     * @param string $type SELECT|DELETE
     *
     * @return $this
     */
    public function type(string $type): self
    {
        $types = ['SELECT', 'DELETE'];

        if (!empty($type) && \in_array($type, $types)) {
            $this->query['type'] = $type;
        }

        return $this;
    }

    /**
     * Adds fields to SELECT clause.
     *
     * @param string $fields List of fields to concat to other fields
     *
     * @return $this
     */
    public function select(string $fields): self
    {
        if (!empty($fields)) {
            $this->query['select'][] = $fields;
        }

        return $this;
    }

    /**
     * Sets table for FROM clause.
     *
     * @param string|DbQuery $table Table name
     * @param string|null    $alias Table alias
     *
     * @return $this
     * @throws \PrestaShopException
     */
    public function from(DbQuery|string $table, ?string $alias = null): self
    {
        if ($this->usePrefix) {
            if (!empty($table)) {
                if ($table instanceof DbQuery) {
                    $query = '(' . $table->build() . ')';
                } else {
                    $query = '`' . \_DB_PREFIX_ . $table . '`';
                }

                $this->query['from'][] = $query . ($alias ? ' ' . $alias : '');
            }

            return $this;
        }

        if (!empty($table)) {
            if ($table instanceof DbQuery) {
                $query = '(' . $table->build() . ')';
            } else {
                $query = '`' . $table . '`';
            }

            $this->query['from'][] = $query . ($alias ? ' ' . $alias : '');
        }

        return $this;
    }

    /**
     * Adds JOIN clause
     * E.g. $this->join('RIGHT JOIN '._DB_PREFIX_.'product p ON ...');.
     *
     * @param string $join Complete string
     *
     * @return $this
     */
    public function join(string $join): self
    {
        if (!empty($join)) {
            $this->query['join'][] = $join;
        }

        return $this;
    }

    /**
     * Adds a LEFT JOIN clause.
     *
     * @param string      $table Table name (without prefix)
     * @param string|null $alias Table alias
     * @param string|null $on    ON clause
     *
     * @return $this
     */
    public function leftJoin(string $table, string|null $alias = null, string|null $on = null): self
    {
        if ($this->usePrefix) {
            return $this->join('LEFT JOIN `'
                . \_DB_PREFIX_ . \bqSQL($table)
                . '`' . ($alias ? ' `' . \pSQL($alias) . '`' : '')
                . ($on ? ' ON ' . $on : ''));
        }

        return $this->join('LEFT JOIN `'
            . \bqSQL($table)
            . '`' . ($alias ? ' `' . \pSQL($alias) . '`' : '')
            . ($on ? ' ON ' . $on : ''));
    }

    /**
     * Adds an INNER JOIN clause
     * E.g. $this->innerJoin('product p ON ...').
     *
     * @param string      $table Table name (without prefix)
     * @param string|null $alias Table alias
     * @param string|null $on    ON clause
     *
     * @return $this
     */
    public function innerJoin(string $table, string|null $alias = null, string|null $on = null): self
    {
        if ($this->usePrefix) {
            return $this->join('INNER JOIN `'
                . \_DB_PREFIX_ . \bqSQL($table)
                . '`' . ($alias ? ' `' . \pSQL($alias) . '`' : '')
                . ($on ? ' ON ' . $on : ''));
        }

        return $this->join('INNER JOIN `'
            . \bqSQL($table)
            . '`' . ($alias ? ' `' . \pSQL($alias) . '`' : '')
            . ($on ? ' ON ' . $on : ''));
    }

    /**
     * Adds a LEFT OUTER JOIN clause.
     *
     * @param string      $table Table name (without prefix)
     * @param string|null $alias Table alias
     * @param string|null $on    ON clause
     *
     * @return $this
     */
    public function leftOuterJoin(string $table, string|null $alias = null, string|null $on = null): self
    {
        if ($this->usePrefix) {
            return $this->join('LEFT OUTER JOIN `'
                . \_DB_PREFIX_ . \bqSQL($table)
                . '`' . ($alias ? ' `' . \pSQL($alias) . '`' : '')
                . ($on ? ' ON ' . $on : ''));
        }

        return $this->join('LEFT OUTER JOIN `'
            . \bqSQL($table)
            . '`' . ($alias ? ' `' . \pSQL($alias) . '`' : '')
            . ($on ? ' ON ' . $on : ''));
    }

    /**
     * Adds a NATURAL JOIN clause.
     *
     * @param string      $table Table name (without prefix)
     * @param string|null $alias Table alias
     *
     * @return $this
     */
    public function naturalJoin(string $table, string|null $alias = null): self
    {
        if ($this->usePrefix) {
            return $this->join('NATURAL JOIN `'
                . \_DB_PREFIX_ . \bqSQL($table)
                . '`' . ($alias ? ' `' . \pSQL($alias) . '`' : ''));
        }
        return $this->join('NATURAL JOIN `'
            . \bqSQL($table)
            . '`' . ($alias ? ' `' . \pSQL($alias) . '`' : ''));
    }

    /**
     * Adds a RIGHT JOIN clause.
     *
     * @param string      $table Table name (without prefix)
     * @param string|null $alias Table alias
     * @param string|null $on    ON clause
     *
     * @return $this
     */
    public function rightJoin(string $table, string|null $alias = null, string|null $on = null): self
    {
        if ($this->usePrefix) {
            return $this->join('RIGHT JOIN `'
                . \_DB_PREFIX_ . \bqSQL($table)
                . '`' . ($alias ? ' `' . \pSQL($alias) . '`' : '')
                . ($on ? ' ON ' . $on : ''));
        }
        return $this->join('RIGHT JOIN `'
            . \bqSQL($table)
            . '`' . ($alias ? ' `' . \pSQL($alias) . '`' : '')
            . ($on ? ' ON ' . $on : ''));
    }

    /**
     * Adds a restriction in WHERE clause (each restriction will be separated by AND statement).
     *
     * @param string $restriction
     *
     * @return $this
     */
    public function where(string $restriction): self
    {
        if (!empty($restriction)) {
            $this->query['where'][] = $restriction;
        }

        return $this;
    }

    /**
     * Adds a restriction in HAVING clause (each restriction will be separated by AND statement).
     *
     * @param string $restriction
     *
     * @return $this
     */
    public function having(string $restriction): self
    {
        if (!empty($restriction)) {
            $this->query['having'][] = $restriction;
        }

        return $this;
    }

    /**
     * Adds an ORDER BY restriction.
     *
     * @param string|array<int, string> $fields List of fields to sort. E.g. $this->order('myField, b.mySecondField DESC')
     *
     * @return $this
     */
    public function orderBy(string|array $fields): self
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

    /**
     * Adds a GROUP BY restriction.
     *
     * @param string|array<int, string> $fields List of fields to group. E.g. $this->group('myField1, myField2')
     *
     * @return $this
     */
    public function groupBy(string|array $fields): self
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

    /**
     * Sets query offset and limit.
     *
     * @param int $limit
     * @param int $offset
     *
     * @return $this
     */
    public function limit(int $limit, int $offset = 0): self
    {
        $this->query['limit'] = [
            'offset' => \max($offset, 0),
            'limit' => $limit,
        ];

        return $this;
    }

    /**
     * Generates query and return SQL string.
     *
     * @return string
     *
     * @throws \PrestaShopException
     */
    public function build(): string
    {
        if ($this->query['type'] == 'SELECT') {
            $sql = 'SELECT ' . ((($this->query['select'])) ? \implode(",\n", $this->query['select']) : '*') . "\n";
        } else {
            $sql = $this->query['type'] . ' ';
        }

        if (!$this->query['from']) {
            throw new \PrestaShopException('Table name not set in DbQuery object. Cannot build a valid SQL query.');
        }

        $sql .= 'FROM ' . \implode(', ', $this->query['from']) . "\n";

        if ($this->query['join']) {
            $sql .= \implode("\n", $this->query['join']) . "\n";
        }

        if ($this->query['where']) {
            $sql .= 'WHERE (' . \implode(') AND (', $this->query['where']) . ")\n";
        }

        if ($this->query['group']) {
            $sql .= 'GROUP BY ' . \implode(', ', $this->query['group']) . "\n";
        }

        if ($this->query['having']) {
            $sql .= 'HAVING (' . \implode(') AND (', $this->query['having']) . ")\n";
        }

        if ($this->query['order']) {
            $sql .= 'ORDER BY ' . \implode(', ', $this->query['order']) . "\n";
        }

        if ($this->query['limit']['limit']) {
            $limit = $this->query['limit'];
            $sql  .= 'LIMIT ' . ($limit['offset'] ? $limit['offset'] . ', ' : '') . $limit['limit'];
        }

        return $sql;
    }

    /**
     * Converts object to string.
     *
     * @return string
     * @throws \PrestaShopException
     */
    public function __toString(): string
    {
        return $this->build();
    }

    /**
     * Get query.
     *
     * @return array{
     *      type: string,
     *      select: array<int,string>,
     *      from: array<int,string>,
     *      join: array<int,string>,
     *      where: array<int,string>,
     *      group: array<int,string>,
     *      having: array<int,string>,
     *      order: array<int,string>,
     *      limit: array{
     *       offset: int<0, max>,
     *       limit: int
     *   }
     *  }
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @param bool $usePrefix
     * @return void
     */
    public function setUsePrefix(bool $usePrefix): void
    {
        $this->usePrefix = $usePrefix;
    }
}
