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
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset'
    ];

    /**
     * Compile a select query into SQL.
     *
     * @param  \WaxFramework\Database\Query\Builder $query
     * @return string
     */
    public function compileSelect( Builder $query ) {
        return $this->concatenate( $this->compileComponents( $query ) );
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @param  \WaxFramework\Database\Query\Builder $query
     * @param  array  $values
     * @return string
     */
    public function compileInsert( Builder $query, array $values ) {
        if ( ! is_array( reset( $values ) ) ) {
            $values = [$values];
        } else {
            foreach ( $values as $key => $value ) {
                ksort( $value );

                $values[$key] = $value;
            }
        }

        $columns = $this->columnize( array_keys( reset( $values ) ) );

        $parameters = implode(
            ', ', array_map(
                function( $record ) use( $query ) {
                    return '(' . implode(
                        ', ', array_map(
                            function( $item ) use( $query ) {
                                return $query->setBinding( $item );
                            }, $record
                        ) 
                    ) . ')';
                }, $values
            )
        );

        $table = $query->from;

        return "insert into $table ($columns) values $parameters";
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param  \WaxFramework\Database\Query\Builder $query
     * @param  array  $values
     * @return string
     */
    public function compileUpdate( Builder $query, array $values ) {

        $keys = array_keys( $values );

        $columns = implode(
            ', ', array_map(
                function( $value, $key ) use( $query ){
                        return $key . ' = ' . $query->setBinding( $value );
                }, $values, $keys
            )
        );

        $where = $this->compileWheres( $query );

        return "update {$query->from} set {$columns} {$where}";
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @param  \WaxFramework\Database\Query\Builder $query
     * @return string
     */
    public function compileDelete( Builder $query ) {
        $where = $this->compileWheres( $query );
        
        return "delete from {$query->from} {$where}";
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
     * @param  \WaxFramework\Database\Query\Builder $query
     * @param  array  $columns
     * @return string|null
     */
    protected function compileColumns( Builder $query, $columns ) {
        if ( ! is_null( $query->aggregate ) ) {
            return;
        }

        if ( $query->distinct ) {
            $select = 'select distinct ';
        } else {
            $select = 'select ';
        }

        return $select . $this->columnize( $columns );
    }

    /**
     * Compile an aggregated select clause.
     *
     * @param  \WaxFramework\Database\Query\Builder $query
     * @param  array  $aggregate
     * @return string
     */
    protected function compileAggregate( Builder $query, $aggregate ) {
        $column = $this->columnize( $query->aggregate['columns'] );
        
        if ( $query->distinct ) {
            $column = 'distinct ' . $column;
        }

        return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
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
        if ( empty( $query->wheres ) ) {
            return '';
        }

        if ( $query instanceof JoinClause ) {
            $where_query = "on";
        } else {
            $where_query = "where";
        }

        return $this->compileWhereOrHaving( $query, $query->wheres, $where_query );
    }

    protected function compileWhereOrHaving( Builder $query, array $items, string $type = 'where' ) {
        $where_query = $type;

        foreach ( $items as $where ) {
            switch ( $where['type'] ) {
                case 'basic':
                    $where_query .= " {$where['boolean']} {$where['column']} {$where['operator']} {$query->setBinding($where['value'])}";
                    break;
                case 'between':
                    $between      = $where['not'] ? 'not between' : 'between';
                    $where_query .= " {$where['boolean']} {$where['column']} {$between} {$query->setBinding($where['values'][0])} and {$query->setBinding($where['values'][1])}";
                    break;
                case 'in':
                    $in           = $where['not'] ? 'not in' : 'in';
                    $values       = implode(
                        ', ', array_map(
                            function( $value ) use( $query ) {
                                return $query->setBinding( $value );
                            }, $where['values']
                        ) 
                    );
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
     * Compile the "order by" portions of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @param  array  $orders
     * @return string
     */
    protected function compileOrders( Builder $query, $orders ) {
        if ( empty( $orders ) ) {
            return '';
        }

        return 'order by ' . implode(
            ', ', array_map(
                function( $order ) {
                    return $order['column'] . ' ' . $order['direction'];
                }, $orders
            )
        );
    }

    /**
     * Compile the "having" portions of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder $query
     * @return string
     */
    protected function compileHavings( Builder $query ) {
        if ( empty( $query->havings ) ) {
            return '';
        }
        return $this->compileWhereOrHaving( $query, $query->havings, 'having' );
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder $query
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset( Builder $query, $offset ) {
        return 'offset ' . $query->setBinding( $offset );
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit( Builder $query, $limit ) {
        return 'limit ' . $query->setBinding( $limit );
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
     * Convert an array of column names into a delimited string.
     *
     * @param  array  $columns
     * @return string
     */
    public function columnize( array $columns ) {
        return implode( ', ', $columns );
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