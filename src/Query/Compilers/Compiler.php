<?php

namespace WaxFramework\Database\Query\Compilers;

use WaxFramework\Database\Query\Builder;
use WaxFramework\Database\Query\JoinClause;

class Compiler {
    /**
     * The components that make up a select clause.
     *
     * @var string[]
     */
    protected $selectComponents = [
        // 'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        // 'havings',
        // 'orders',
        // 'limit',
        // 'offset',
        // 'lock',
    ];

    /**
     * Compile a select query into SQL.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @return string
     */
    public function compileSelect( Builder $query ) {
        return $this->concatenate( $this->compileComponents( $query ) );
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @return array
     */
    protected function compileComponents( Builder $query ) {
        $sql = [];

        foreach ( $this->selectComponents as $component ) {
            if ( isset( $query->$component ) ) {
                $method          = 'compile' . ucfirst( $component );
                $sql[$component] = $this->$method( $query, $query->$component );
            }
        }

        return $sql;
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @param  array  $columns
     * @return string|null
     */
    protected function compileColumns( Builder $query, $columns ) {
        // If the query is actually performing an aggregating select, we will let that
        // compiler handle the building of the select clauses, as it will need some
        // more syntax that is best handled by that function to keep things neat.
        // if (! is_null($query->aggregate)) {
        //     return;
        // }

        if ( $query->distinct ) {
            $select = 'select distinct ';
        } else {
            $select = 'select ';
        }

        return $select . implode( ', ', $columns );
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @param string $table
     * @return string
     */
    protected function compileFrom( Builder $query, $table ) {
        if ( is_null( $query->as ) ) {
            return 'from ' . $table;
        }
        return "from {$table} as {$query->as}";
    }

    /**
     * Compile the "where" portions of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @return string
     */
    public function compileWheres( Builder $query ) {
        if ( is_null( $query->wheres ) ) {
            return '';
        }

        if ( $query instanceof JoinClause ) {
            $where_query = "on";
        } else {
            $where_query = "where";
        }

        foreach ( $query->wheres as $where ) {
            switch ( $where['type'] ) {
                case 'basic':
                    $where_query .= " {$where['boolean']} {$where['column']} {$where['operator']} {$where['value']}";
                    break;
                case 'between':
                    $between      = $where['not'] ? 'not between' : 'between';
                    $where_query .= " {$where['boolean']} {$where['column']} {$between} {$where['values'][0]} and {$where['values'][1]}";
                    break;
                case 'in':
                    $in           = $where['not'] ? 'not in' : 'in';
                    $values       = implode( ', ', $where['values'] );
                    $where_query .= " {$where['boolean']} {$where['column']} {$in} ({$values})";
                    break;
                case 'exists':
                    /**
                     * @var Builder $query
                     */
                    $query = $where['query'];
                    $sql   = $query->toSql();

                    $exists       = $where['not'] ? 'not exists' : 'exists';
                    $where_query .= " {$where['boolean']} {$exists} ({$sql})";
            }
        }

        return $this->removeLeadingBoolean( $where_query );
    }

     /**
     * Compile the "join" portions of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @param  array  $joins
     * @return string
     */
    protected function compileJoins( Builder $query, $joins ) {
        return implode(
            ' ', array_map(
                function( JoinClause $join ) use( $query ) {
                    if ( is_null( $join->joins ) ) {
                        $tableAndNestedJoins = $join->table;
                    } else {
                        $tableAndNestedJoins = '(' . $join->table . ' ' . $this->compileJoins( $query, $join->joins ) . ')';
                    }
                    return trim( "{$join->type} join {$tableAndNestedJoins} {$this->compileWheres($join)}" );
                }, $joins
            )
        );
    }

     /**
     * Compile the "group by" portions of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @param array $groups
     * @return string
     */
    protected function compileGroups( Builder $query, $groups ) {
        return 'group by ' . implode( ', ', $groups );
    }

     /**
     * Concatenate an array of segments, removing empties.
     *
     * @param  array  $segments
     * @return string
     */
    protected function concatenate( $segments ) {
        return implode(
            ' ', array_filter(
                $segments, function ( $value ) {
                    return (string) $value !== '';
                }
            )
        );
    }

     /**
     * Remove the leading boolean from a statement.
     *
     * @param  string  $value
     * @return string
     */
    protected function removeLeadingBoolean( $value ) {
        return preg_replace( '/and |or /i', '', $value, 1 );
    }
}