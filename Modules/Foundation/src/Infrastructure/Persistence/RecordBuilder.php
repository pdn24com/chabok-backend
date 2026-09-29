<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;

final class RecordBuilder extends Builder
{
    public function getModels($columns = ['*'])
    {
        $original = $this->query->columns;
        $selected = $original ?? $columns;
        if ($this->query->groups === null && ! $this->query->distinct && is_array($selected)
            && count(array_filter($selected, fn ($column) => ! is_string($column) || str_contains($column, '*'))) === 0) {
            $this->query->columns = array_values(array_unique([...$selected, $this->model->qualifyColumn('id')]));
        }
        try {
            return parent::getModels($columns);
        } finally {
            $this->query->columns = $original;
        }
    }
}
