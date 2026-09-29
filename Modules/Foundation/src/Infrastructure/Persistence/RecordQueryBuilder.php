<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;

/** Resolves domain identity names to id without lookups or secondary identity columns. */
class RecordQueryBuilder extends Builder
{
    public function applyBeforeQueryCallbacks()
    {
        $this->normalizeQuery($this);
        parent::applyBeforeQueryCallbacks();
    }

    public function insert(array $values)
    {
        return parent::insert($this->rows($values));
    }

    public function insertOrIgnore(array $values)
    {
        return parent::insertOrIgnore($this->rows($values));
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return parent::insertGetId($this->row($values), $sequence);
    }

    public function update(array $values)
    {
        return parent::update($this->row($values));
    }

    public function upsert(array $values, array|string $uniqueBy, ?array $update = null)
    {
        $uniqueBy = array_map(fn ($column) => $this->column($column, $this), (array) $uniqueBy);
        if ($update !== null) {
            $update = array_map(fn ($column) => $this->column($column, $this), $update);
        }

        return parent::upsert($this->rows($values), $uniqueBy, $update);
    }

    private function row(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[$this->column($key, $this)] = $value;
        }

        return $result;
    }

    private function rows(array $values): array
    {
        if ($values === []) {
            return [];
        }

        return is_int(array_key_first($values)) && is_array(reset($values))
            ? array_map($this->row(...), array_values($values))
            : $this->row($values);
    }

    private function normalizeQuery(Builder $query, array $aliases = []): void
    {
        foreach ([$query->from, ...array_map(fn ($join) => $join->table, $query->joins ?? [])] as $source) {
            if (is_string($source)) {
                $parts = preg_split('/\s+as\s+/i', $source);
                $aliases[$parts[1] ?? $parts[0]] = $parts[0];
            }
        }
        foreach (['columns', 'groups'] as $property) {
            if (is_array($query->{$property})) {
                $query->{$property} = array_map(fn ($column) => $this->column($column, $query, $aliases, $property === 'columns'), $query->{$property});
            }
        }
        if ($query->aggregate !== null) {
            $query->aggregate['columns'] = array_map(fn ($column) => $this->column($column, $query, $aliases), $query->aggregate['columns']);
        }
        foreach (['wheres', 'havings', 'orders', 'unionOrders'] as $property) {
            if (! is_array($query->{$property})) {
                continue;
            }
            foreach ($query->{$property} as $index => $clause) {
                foreach (['column', 'first', 'second'] as $key) {
                    if (isset($clause[$key])) {
                        $clause[$key] = $this->column($clause[$key], $query, $aliases);
                    }
                }
                if (($clause['query'] ?? null) instanceof Builder) {
                    $this->normalizeQuery($clause['query'], $aliases);
                }
                $query->{$property}[$index] = $clause;
            }
        }
        foreach ($query->joins ?? [] as $join) {
            $this->normalizeQuery($join, $aliases);
        }
        foreach ($query->unions ?? [] as $union) {
            $this->normalizeQuery($union['query'], $aliases);
        }
    }

    private function column(mixed $column, Builder $query, array $aliases = [], bool $select = false): mixed
    {
        if (! is_string($column)) {
            return $column;
        }
        $parts = preg_split('/\s+as\s+/i', $column);
        $name = $parts[0];
        $table = is_string($query->from) ? preg_split('/\s+as\s+/i', $query->from)[0] : '';
        $prefix = '';
        if (str_contains($name, '.')) {
            [$prefix, $name] = explode('.', $name, 2);
            $table = $aliases[$prefix] ?? $prefix;
            $prefix .= '.';
        }
        if ($name !== (RecordSchema::IDENTITY_NAMES[$table] ?? null)) {
            return $column;
        }
        $suffix = isset($parts[1]) ? ' as '.$parts[1] : ($select ? ' as '.$name : '');

        return $prefix.'id'.$suffix;
    }
}
