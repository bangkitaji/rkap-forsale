<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait Searchable
{
  /**
   * Scope to search across multiple columns with case-insensitive matching.
   * 
   * Usage:
   * - Activity::search('code|title|workPlan.title|workPlan.code', $searchTerm)->get()
   * - User::search('name|email', $searchTerm)->get()
   * 
   * @param Builder $query
   * @param string|array $columns Pipe-separated column names or array of columns. Supports relations like 'relation.column'
   * @param string $term Search term to look for (case-insensitive)
   * @return Builder
   */
  public function scopeSearch(Builder $query, $columns, string $term): Builder
  {
    if (empty($term)) {
      return $query;
    }

    // Convert pipe-separated columns to array if needed
    $columns = is_string($columns) ? explode('|', $columns) : $columns;
    $lowerTerm = strtolower($term);

    return $query->where(function (Builder $q) use ($columns, $lowerTerm) {
      $isFirstCondition = true;

      foreach ($columns as $column) {
        $column = trim($column);

        // Handle relationship columns (e.g., 'workPlan.title')
        if (strpos($column, '.') !== false) {
          [$relation, $relationColumn] = explode('.', $column, 2);

          if ($isFirstCondition) {
            $q->whereHas($relation, function (Builder $subQuery) use ($relationColumn, $lowerTerm) {
              $subQuery->where(DB::raw("LOWER({$relationColumn})"), 'like', '%' . $lowerTerm . '%');
            });
            $isFirstCondition = false;
          } else {
            $q->orWhereHas($relation, function (Builder $subQuery) use ($relationColumn, $lowerTerm) {
              $subQuery->where(DB::raw("LOWER({$relationColumn})"), 'like', '%' . $lowerTerm . '%');
            });
          }
        } else {
          // Handle direct columns
          if ($isFirstCondition) {
            $q->where(DB::raw("LOWER({$column})"), 'like', '%' . $lowerTerm . '%');
            $isFirstCondition = false;
          } else {
            $q->orWhere(DB::raw("LOWER({$column})"), 'like', '%' . $lowerTerm . '%');
          }
        }
      }
    });
  }
}
