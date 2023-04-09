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
    protected $select_components = [
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
    public function compile_select( Builder $query ) {
        return $this->concatenate( $this->compile_components( $query ) );
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @param  \WaxFramework\Database\Query\Builder $query
     * @param  array  $values
     * @return string
     */
    public function compile_insert( Builder $query, array $values ) {
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
                                return $query->set_binding( $item );
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
    public function compile_update( Builder $query, array $values ) {

        $keys = array_keys( $values );

        $columns = implode(
            ', ', array_map(
                function( $value, $key ) use( $query ){
                        return $key . ' = ' . $query->set_binding( $value );
                }, $values, $keys
            )
        );

        $where = $this->compile_wheres( $query );

        return "update {$query->from} set {$columns} {$where}";
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @param  \WaxFramework\Database\Query\Builder $query
     * @return string
     */
    public function compile_delete( Builder $query ) {
        $where = $this->compile_wheres( $query );
        
        return "delete from {$query->from} {$where}";
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @return array
     */
    protected function compile_components( Builder $query ) {
        $sql = [];

        foreach ( $this->select_components as $component ) {
            if ( isset( $query->$component ) ) {
                $method          = 'compile_' . $component;
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
    protected function compile_columns( Builder $query, $columns ) {
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
    protected function compile_aggregate( Builder $query, $aggregate ) {
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
    protected function compile_from( Builder $query, $table ) {
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
    public function compile_wheres( Builder $query ) {
        if ( empty( $query->wheres ) ) {
            return '';
        }

        if ( $query instanceof JoinClause ) {
            $where_query = "on";
        } else {
            $where_query = "where";
        }

        return $this->compile_where_or_having( $query, $query->wheres, $where_query );
    }

    protected function compile_where_or_having( Builder $query, array $items, string $type = 'where' ) {
        $where_query = $type;

        foreach ( $items as $where ) {
            switch ( $where['type'] ) {
                case 'basic':
                    $where_query .= " {$where['boolean']} {$where['column']} {$where['operator']} {$query->set_binding($where['value'])}";
                    break;
                case 'between':
                    $between      = $where['not'] ? 'not between' : 'between';
                    $where_query .= " {$where['boolean']} {$where['column']} {$between} {$query->set_binding($where['values'][0])} and {$query->set_binding($where['values'][1])}";
                    break;
                case 'in':
                    $in           = $where['not'] ? 'not in' : 'in';
                    $values       = implode(
                        ', ', array_map(
                            function( $value ) use( $query ) {
                                return $query->set_binding( $value );
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
                    $sql   = $query->to_sql();

                    $exists       = $where['not'] ? 'not exists' : 'exists';
                    $where_query .= " {$where['boolean']} {$exists} ({$sql})";
            }
        }

        return $this->remove_leading_boolean( $where_query );
    }

     /**
     * Compile the "join" portions of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @param  array  $joins
     * @return string
     */
    protected function compile_joins( Builder $query, $joins ) {
        return implode(
            ' ', array_map(
                function( JoinClause $join ) use( $query ) {
                    if ( is_null( $join->joins ) ) {
                        $table_and_nested_joins = $join->table;
                    } else {
                        $table_and_nested_joins = '(' . $join->table . ' ' . $this->compile_joins( $query, $join->joins ) . ')';
                    }
                    return trim( "{$join->type} join {$table_and_nested_joins} {$this->compile_wheres($join)}" );
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
    protected function compile_orders( Builder $query, $orders ) {
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
    protected function compile_havings( Builder $query ) {
        if ( empty( $query->havings ) ) {
            return '';
        }
        return $this->compile_where_or_having( $query, $query->havings, 'having' );
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder $query
     * @param  int  $offset
     * @return string
     */
    protected function compile_offset( Builder $query, $offset ) {
        return 'offset ' . $query->set_binding( $offset );
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compile_limit( Builder $query, $limit ) {
        return 'limit ' . $query->set_binding( $limit );
    }

     /**
     * Compile the "group by" portions of the query.
     *
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @param array $groups
     * @return string
     */
    protected function compile_groups( Builder $query, $groups ) {
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
    protected function remove_leading_boolean( $value ) {
        return preg_replace( '/and |or /i', '', $value, 1 );
    }
}