<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\Relations\IntegerBelongsTo;
use ReflectionClass;

/** Domain identifier names refer to the same auto-incrementing database id. */
trait HasNumericIdentity
{
    public function initializeHasNumericIdentity(): void
    {
        // Application contracts carry decimal identifiers as strings, including foreign keys.
        $columns = RecordSchema::columns($this->referenceTable());
        $name = RecordSchema::IDENTITY_NAMES[$this->referenceTable()] ?? null;
        $this->mergeCasts(array_fill_keys($name === null ? $columns : [...$columns, $name], 'string'));
        $this->mergeCasts(['id' => 'integer']);
    }

    public function referenceTable(): string
    {
        return (new ReflectionClass(static::class))->getDefaultProperties()['table'];
    }

    public function getAttribute($key)
    {
        if ($key === (RecordSchema::IDENTITY_NAMES[$this->referenceTable()] ?? null)) {
            $id = parent::getAttribute('id') ?? parent::getAttribute($key);

            return $id === null ? null : (string) $id;
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if ($key === (RecordSchema::IDENTITY_NAMES[$this->referenceTable()] ?? null)) {
            $key = 'id';
        }

        return parent::setAttribute($key, $value);
    }

    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();
        $name = RecordSchema::IDENTITY_NAMES[$this->referenceTable()] ?? null;
        if ($name !== null && array_key_exists('id', $attributes)) {
            $attributes[$name] = (string) $attributes['id'];
        }

        return $attributes;
    }

    public function newEloquentBuilder($query): RecordBuilder
    {
        return new RecordBuilder($query);
    }

    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new RecordQueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
    }

    protected function newBelongsTo(Builder $query, Model $child, $foreignKey, $ownerKey, $relation)
    {
        return new IntegerBelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
    }
}
