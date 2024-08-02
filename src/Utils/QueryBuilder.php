<?php

declare(strict_types=1);

namespace jtl\Connector\Presta\Utils;

use DbQuery;

class QueryBuilder extends DbQuery
{
    protected bool $usePrefix = true;

    /**
     * Sets table for FROM clause.
     *
     * @param string|DbQuery $table Table name
     * @param string|null    $alias Table alias
     *
     * @return $this
     * @throws \PrestaShopException
     */
    public function from($table, $alias = null): self // phpcs:ignore
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
     * Adds a LEFT JOIN clause.
     *
     * @param string      $table Table name (without prefix)
     * @param string|null $alias Table alias
     * @param string|null $on    ON clause
     *
     * @return $this
     */
    public function leftJoin($table, $alias = null, $on = null): self // phpcs:ignore
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
    public function innerJoin($table, $alias = null, $on = null): self // phpcs:ignore
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
    public function leftOuterJoin($table, $alias = null, $on = null): self // phpcs:ignore
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
    public function naturalJoin($table, $alias = null): self // phpcs:ignore
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
    public function rightJoin($table, $alias = null, $on = null): self // phpcs:ignore
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
     * Adds an ORDER BY restriction.
     *
     * @param string|array<int, string> $fields fields to sort. E.G. $this->order('myField, b.mySecondField DESC')
     *
     * @return $this
     */
    public function orderBy($fields): self // phpcs:ignore
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
    public function groupBy($fields): self // phpcs:ignore
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
    public function limit($limit, $offset = 0): self // phpcs:ignore
    {
        $this->query['limit'] = [
            'offset' => \max($offset, 0),
            'limit' => $limit,
        ];

        return $this;
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
