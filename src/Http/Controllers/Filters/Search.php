<?php

namespace Weap\Junction\Http\Controllers\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PDO;
use Weap\Junction\Http\Controllers\Controller;
use Weap\Junction\Http\Controllers\Helpers\Table;

class Search extends Filter
{
    /**
     * @param Controller $controller
     * @param Builder|Relation $query
     */
    public static function apply(Controller $controller, Builder|Relation $query): void
    {
        /** @var Model $model */
        $model = app($controller->model);

        $searchValue = request()->input('search_value');
        $columns = empty(request()->input('search_columns')) ? $controller->searchable() : request()->input('search_columns');

        if (empty($searchValue) || empty($columns)) {
            return;
        }

        $searchValue = $controller->mutateSearchValue($searchValue);

        $query->where(function (Builder $query) use ($searchValue, $model, $columns) {
            $columns = Arr::undot(array_flip($columns));

            self::searchColumnQuery($query, $columns, $model->getTable(), $searchValue);
        });
    }

    /**
     * @param Builder $query
     * @param array $columns
     * @param string $tableName
     * @param string $searchValue
     * @return void
     */
    public static function searchColumnQuery(Builder $query, array $columns, string $tableName, string $searchValue): void
    {
        $connection = DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $likeOperator = $connection === 'pgsql' ? 'ILIKE' : 'LIKE';

        foreach ($columns as $relationName => $relationColumns) {
            if (! is_array($relationColumns)) {
                $columnPath = $tableName ? "$tableName.$relationName" : $relationName;
                $query->orWhere($columnPath, $likeOperator, '%' . $searchValue . '%');
            } else {
                $relation = Table::getRelation($query->getModel()::class, [$relationName]);

                $query->orWhereHas($relationName, function (Builder $query) use ($relationColumns, $relation, $searchValue) {
                    $tableName = $relation instanceof BelongsToMany ? $relation->getTable() : $query->from;

                    $query->where(function (Builder $query) use ($relationColumns, $tableName, $searchValue) {
                        self::searchColumnQuery($query, $relationColumns, $tableName, $searchValue);
                    });
                });
            }
        }
    }
}
