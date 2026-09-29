<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Infrastructure\Persistence\RecordQueryBuilder;
use Modules\Foundation\Infrastructure\Persistence\RecordSchema;

final class RecordFixtureQuery extends RecordQueryBuilder
{
    public static function table(string $table): self
    {
        $connection = DB::connection();

        return (new self($connection, $connection->getQueryGrammar(), $connection->getPostProcessor()))->from($table);
    }

    public function get($columns = ['*'])
    {
        $rows = parent::get($columns);
        $table = preg_split('/\s+as\s+/i', $this->from)[0];
        $identity = RecordSchema::IDENTITY_NAMES[$table] ?? null;
        foreach ($rows as $row) {
            if ($identity !== null && isset($row->id)) {
                $row->{$identity} = (string) $row->id;
            }
            foreach (RecordSchema::columns($table) as $column) {
                if (isset($row->{$column})) {
                    $row->{$column} = (string) $row->{$column};
                }
            }
        }

        return $rows;
    }
}
