<?php

namespace Weap\Junction\Http\Controllers\Filters;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Weap\Junction\Http\Controllers\Controller;
use Weap\Junction\Http\Controllers\Helpers\Table;

class WhereNotIn extends Filter
{
    /**
     * @param Controller $controller
     * @param Builder|Relation $query
     *
     * @throws Exception
     */
    public static function apply(Controller $controller, Builder|Relation $query): void
    {
        $whereNotIns = request()?->input('where_not_in');

        if (! $whereNotIns) {
            return;
        }

        $whereNotIns = (array) $whereNotIns;

        foreach ($whereNotIns as $whereNotIn) {
            $values = (array) ($whereNotIn['values'] ?? []);

            self::traverse($query, $whereNotIn['column'], $values);
        }
    }

    /**
     * @param Builder $query
     * @param string $column
     * @param array $values
     */
    protected static function traverse(Builder $query, string $column, array $values): void
    {
        $relationParts = explode('.', $column);

        // Directly on the main model (no relation)
        if (count($relationParts) === 1) {
            $tableName = $query->getModel()->getTable();
            $columnPath = $tableName ? "$tableName.$column" : $column;

            $query->whereNotIn($columnPath, $values);

            return;
        }

        // Treatment for columns in a relationship
        $actualColumn = array_pop($relationParts);
        $relationPath = implode('.', $relationParts);
        $relation = Table::getRelation($query->getModel()::class, $relationParts);

        $query->whereHas($relationPath, function (Builder $subQuery) use ($actualColumn, $values, $relation) {
            $tableName = $relation instanceof BelongsToMany ? $relation->getTable() : $subQuery->from;
            $fullColumn = $tableName ? "$tableName.$actualColumn" : $actualColumn;

            $subQuery->whereNotIn($fullColumn, $values);
        });
    }
}
