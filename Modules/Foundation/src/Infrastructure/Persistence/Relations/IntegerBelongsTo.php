<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Persistence\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\RecordSchema;

final class IntegerBelongsTo extends BelongsTo
{
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query = parent::getRelationExistenceQuery($query, $parentQuery, $columns);
        $polymorphic = RecordSchema::POLYMORPHIC[$this->child->referenceTable()][$this->foreignKey] ?? null;
        if ($polymorphic !== null) {
            [$type, $targets] = $polymorphic;
            $types = array_keys(array_filter($targets, fn ($target) => $target[0] === $this->related->referenceTable()));
            $query->whereIn($parentQuery->getModel()->qualifyColumn($type), $types);
        }

        return $query;
    }

    protected function getForeignKeyFrom(Model $model)
    {
        if (isset(RecordSchema::POLYMORPHIC[$model->referenceTable()][$this->foreignKey])) {
            $reference = RecordSchema::reference($model->referenceTable(), $this->foreignKey, $model->getAttributes());
            if (($reference[0] ?? null) !== $this->related->referenceTable()) {
                return null;
            }
        }

        return $model->getAttributes()[$this->foreignKey] ?? null;
    }

    protected function getRelatedKeyFrom(Model $model)
    {
        return $model->getAttributes()[$this->ownerKey] ?? null;
    }
}
