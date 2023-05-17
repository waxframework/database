<?php

namespace WaxFramework\Database\Query;

use Closure;
use InvalidArgumentException;
use WaxFramework\Database\Eloquent\Model;
use WaxFramework\Database\Eloquent\Relationship;
use WaxFramework\Database\Query\Compilers\Compiler;
use wpdb;

class Builder extends Relationship {
     /**
     * The current query value bindings.
     *
     * @var array
     */
    public $bindings = [];

    /**
     * The model being queried.
     *
     * @param \WaxFramework\Database\Eloquent\Model
     */
    protected $model;

    /**
     *
     * @var string
     */
    public $from;

    /**
     *
     * @var string
     */
    public $as;

    /**
     * The groupings for the query.
     *
     * @var array
     */
    public $groups;

    /**
     * An aggregate function and column to be run.
     *
     * @var array
     */
    public $aggregate;

    /**
     * The columns that should be returned.
     *
     * @var array
     */
    public $columns = ['*'];

    /**
     * Indicates if the query returns distinct results.
     *
     * Occasionally contains the columns that should be distinct.
     *
     * @var bool|array
     */
    public $distinct = false;

     /**
     * The table joins for the query.
     *
     * @var array
     */
    public $joins;

     /**
     * The table limit for the query.
     *
     * @var int
     */
    public $limit;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $wheres = [];

    /**
     * The having constraints for the query.
     *
     * @var array
     */
    public $havings;

    /**
     * The orderings for the query.
     *
     * @var array
     */
    public $orders;

    /**
     * The number of records to skip.
     *
     * @var int
     */
    public $offset;

    /**
     * The relationships that should be eager loaded.
     *
     * @var array
     */
    protected $relations = [];

    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    public $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>', '&~',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];

    public function __construct( Model $model ) {
        $this->model = $model;
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param  Builder  $query
     * @param  string|null  $as
     * @return $this
     */
    public function from( string $table, $as = null ) {
        $this->from = $this->model->resolver()->table( $table );
        $this->as   = is_null( $as ) ? $table : $as;
        return $this;
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array|mixed  $columns
     * @return $this
     */
    public function select( $columns = ['*'] ) {
        $this->columns = is_array( $columns ) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Force the query to only return distinct results.
     *
     * @param  mixed  ...$distinct
     * @return $this
     */
    public function distinct() {
        $this->distinct = true;
        return $this;
    }

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param  string|array  $relations
     * @param  string|Closure|null  $callback
     * @return $this
     */
    public function with( $relations, $callback = null ) {
        if ( ! is_array( $relations ) ) {
            $relations = [$relations => $callback];
        }

        foreach ( $relations as $relation => $callback ) {
            if ( is_int( $relation ) ) {
                $relation = $callback;
            }

            $current = &$this->relations;

            // Traverse the items string and create nested arrays
            $items = explode( '.', $relation );

            foreach ( $items as $key ) {
                if ( ! isset( $current[$key] ) ) {
                    $query         = new self( $this->model );
                    $current[$key] = [
                        'query'    => $query,
                        'children' => []
                    ];
                } else {
                    $query = $current[$key]['query'];
                }
                $current = &$current[$key]['children'];
            }

            // Apply the callback to the last item
            if ( $callback instanceof Closure ) {
                call_user_func( $callback, $query );
            }
        }

        return $this;
    }

     /**
     * Add a join clause to the query.
     *
     * @param  string $table
     * @param  Closure|string $first
     * @param  string|null  $operator
     * @param  string|null $second
     * @param  string $type
     * @param  bool $where
     * @return $this
     */
    public function join( $table, $first, $operator = null, $second = null, $type = 'inner', $where = false ) {

        $join = new JoinClause( $table, $type, $this->model );

        if ( $first instanceof Closure ) {
            call_user_func( $first, $join );
        } else {
            $method = $where ? 'where' : 'on';
            $join->$method( $first, $operator, $second );
        }

        $this->joins[] = $join;
        return $this;
    }

    /**
     * Add a left join to the query.
     *
     * @param  string  $table
     * @param  Closure|string $first
     * @param  string|null $operator
     * @param  string|null $second
     * @param  bool $where
     * @return $this
     */
    public function left_join( $table, $first, $operator = null, $second = null, $where = false ) {
        return $this->join( $table, $first, $operator, $second, 'left', $where );
    }

    /**
     * Add a right join to the query.
     *
     * @param  string $table
     * @param  Closure|string $first
     * @param  string|null $operator
     * @param  string|null $second
     * @param  bool $where
     * @return $this
     */
    public function right_join( $table, $first, $operator = null, $second = null, $where = false ) {
        return $this->join( $table, $first, $operator, $second, 'right', $where );
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where( $column, $operator = null, $value = null, $boolean = 'and' ) {

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepare_value_and_operator(
            $value, $operator, func_num_args() === 2
        );

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ( $this->invalid_operator( $operator ) ) {
            [$value, $operator] = [$operator, '='];
        }

        $type = 'basic';

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.
        $this->wheres[] = compact( 'type', 'boolean', 'column', 'operator', 'value' );

        return $this;
    }

     /**
     * Add an "or where" clause to the query.
     *
     * @param  Closure|string|array  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where( $column, $operator = null, $value = null ) {
        [$value, $operator] = $this->prepare_value_and_operator(
            $value, $operator, func_num_args() === 2
        );

        return $this->where( $column, $operator, $value, 'or' );
    }

     /**
     * Add a "where" clause comparing two columns to the query.
     * 
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where_column( $column, $operator = null, $value = null, $boolean = 'and' ) {
        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepare_value_and_operator(
            $value, $operator, func_num_args() === 2
        );

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ( $this->invalid_operator( $operator ) ) {
            [$value, $operator] = [$operator, '='];
        }

        $type = 'column';

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.
        $this->wheres[] = compact( 'type', 'boolean', 'column', 'operator', 'value' );

        return $this;
    }

    /**
     * Add a or "where" clause comparing two columns to the query.
     * 
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function or_where_column( $column, $operator = null, $value = null ) {
        return $this->where_column( $column, $operator, $value, 'or' );
    }

    /**
     * Add an exists clause to the query.
     *
     * @param  Closure|static  $callback
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_exists( $callback, $boolean = 'and', $not = false ) {

        if ( $callback instanceof Closure ) {
            $query = new static( $this->model );
            call_user_func( $callback, $query );
        } else {
            $query = $callback;
        }

        $type = 'exists';

        $this->wheres[] = compact( 'type', 'query', 'boolean', 'not' );

        return $this;
    }

     /**
     * Add a where not exists clause to the query.
     *
     * @param  Closure|static  $callback
     * @param  string  $boolean
     * @return $this
     */
    public function where_not_exists( $callback, $boolean = 'and' ) {
        return $this->where_exists( $callback, $boolean, true );
    }

     /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_in( $column, $values, $boolean = 'and', $not = false ) {
        $type = 'in';

        $this->wheres[] = compact( 'type', 'column', 'values', 'boolean', 'not' );

        return $this;
    }

     /**
     * Add a or "where in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function or_where_in( $column, $values ) {
        return $this->where_in( $column, $values, 'or', false );
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @return $this
     */
    public function where_not_in( $column, $values, $boolean = 'and' ) {
        return $this->where_in( $column, $values, $boolean, true );
    }

    /**
     * Add a or "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @return $this
     */
    public function or_where_not_in( $column, $values ) {
        return $this->where_not_in( $column, $values, 'or' );
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function where_between( $column, array $values, $boolean = 'and', $not = false ) {
        $type = 'between';

        $this->wheres[] = compact( 'type', 'boolean', 'column', 'values', 'not' );

        return $this;
    }

    /**
     * Add a or where between statement to the query.
     *
     * @param  string  $column
     * @param  bool  $not
     * @return $this
     */
    public function or_where_between( $column, array $values ) {
        return $this->where_between( $column, $values, 'or', false );
    }

    /**
     * Add a or where not between statement to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @return $this
     */
    public function where_not_between( $column, array $values, $boolean = 'and' ) {
        return $this->where_between( $column, $values, $boolean, true );
    }

    /**
     * Add a where not between statement to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @return $this
     */
    public function or_where_not_between( $column, array $values ) {
        return $this->where_between( $column, $values, 'or', true );
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param  array|string  ...$groups
     * @return $this
     */
    public function group_by( ...$groups ) {
        $this->groups = $groups;
        return $this;
    }

    /**
     * Add a "having" clause to the query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  string|null  $value
     * @param  string  $boolean
     * @return $this
     */
    public function having( $column, $operator = null, $value = null, $boolean = 'and' ) {   
        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepare_value_and_operator(
            $value, $operator, func_num_args() === 2
        );

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ( $this->invalid_operator( $operator ) ) {
            [$value, $operator] = [$operator, '='];
        }

        $type = 'basic';

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.
        $this->havings[] = compact( 'type', 'boolean', 'column', 'operator', 'value' );

        return $this;
    }

     /**
     * Add a "or having" clause to the query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  string|null  $value
     * @param  string  $boolean
     * @return $this
     */
    public function or_having( $column, $operator = null, $value = null ) {   
        return $this->having( $column, $operator, $value, 'or' );
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function order_by( $column, $direction = 'asc' ) {
        $direction = strtolower( $direction );

        if ( ! in_array( $direction, ['asc', 'desc'], true ) ) {
            throw new InvalidArgumentException( 'Order direction must be "asc" or "desc".' );
        }

        $this->orders[] = [
            'column'    => $column,
            'direction' => $direction,
        ];
        return $this;
    }

    /**
     * Add a descending "order by" clause to the query.
     * @return $this
     */
    public function order_by_desc( $column ) {
        return $this->order_by( $column, 'desc' );
    }

    /**
     * Add an "order by raw" clause to the query.
     *
     * @param  string  $sql
     * @return $this
     */
    public function order_by_raw( string $sql ) {
        $this->orders[] = ['column' => $sql, 'direction' => ''];
        return $this;
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function offset( int $value ) {
        $this->offset = max( 0, $value );
        return $this;
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function limit( int $value ) {
        $this->limit = max( 1, $value );
        return $this;
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function to_sql() {
        $compiler = new Compiler;
        return $this->bind_values( $compiler->compile_select( $this ) );
    }

    /**
     * Insert new records into the database.
     *
     * @param  array  $values
     * @return string
     */
    public function to_sql_insert( array $values ) {
        $compiler = new Compiler;
        return $this->bind_values( $compiler->compile_insert( $this, $values ) );
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function to_sql_update( array $values ) {
        $compiler = new Compiler;
        return $this->bind_values( $compiler->compile_update( $this, $values ) );
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function to_sql_delete() {
        $compiler = new Compiler;
        return $this->bind_values( $compiler->compile_delete( $this ) );
    }

    public function get() {
        global $wpdb;
        /**
         * @var wpdb $wpdb
         */
        //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $this->process_relationships( $wpdb->get_results( $this->to_sql() ), $this->relations, $this->model );
    }
    
    public function first() {
        $data = $this->limit( 1 )->get();
        return isset( $data[0] ) ? $data[0] : null;
    }

    /**
     * Insert new records into the database.
     *
     * @param  array  $values
     * @return bool|integer
     */
    public function insert( array $values ) {
        $sql = $this->to_sql_insert( $values );
        global $wpdb;
        /**
         * @var wpdb $wpdb
         */
        //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->query( $sql );
    }

    /**
     * Insert new single record into the database and get id.
     *
     * @param  array  $values
     */
    public function insert_get_id( array $values ) {
        $this->insert( $values );
        global $wpdb;
        /**
         * @var wpdb $wpdb
         */
        return $wpdb->insert_id;
    }
    
    /**
     * Update records in the database.
     *
     * @param array $values
     * @return integer
     */
    public function update( array $values ) {
        $sql = $this->to_sql_update( $values );
        global $wpdb;
        /**
         * @var wpdb $wpdb
         */
        //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query( $sql );
        return $result;
    }

    /**
     * Delete records from the database.
     *
     * @return mixed
     */
    public function delete() {
        global $wpdb;
        /**
         * @var wpdb $wpdb
         */
        //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->query( $this->to_sql_delete() );
    }

    /**
     * Prepare Query Values
     *
     * @param string $sql
     * @return string
     */
    public function bind_values( string $sql ) {
        if ( empty( $this->bindings ) ) {
            return $sql;
        }
        global $wpdb;
        /**
         * @var wpdb $wpdb
         */
        //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->prepare( $sql, ...$this->bindings );
    }

    /**
     * Set query values for the using wpdb::prepare
     *
     * @param mixed $value
     * @return string
     */
    public function set_binding( $value ) {
        $this->bindings[] = $value;

        $type = gettype( $value );

        if ( 'integer' === $type || 'boolean' === $type ) {
            return '%d';
        }

        if ( 'double' === $type ) {
            return '%f';
        }

        return '%s';
    }

    public function aggregate( $function, $columns = ['*'] ) {
        $results = $this->setAggregate( $function, $columns )->get();
        return (int) $results[0]->aggregate;
    }

    /**
     * Set the aggregate property without running the query.
     *
     * @param  string  $function
     * @param  array  $columns
     * @return $this
     */
    protected function setAggregate( $function, $columns ) {
        $this->aggregate = compact( 'function', 'columns' );

        if ( empty( $this->groups ) ) {
            $this->orders = null;
        }

        return $this;
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string  $columns
     * @return int
     */
    public function count( string $column = '*' ) {
        return $this->aggregate( __FUNCTION__, [$column] );
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min( $column ) {
        return $this->aggregate( __FUNCTION__, [$column] );
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max( $column ) {
        return $this->aggregate( __FUNCTION__, [$column] );
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum( $column ) {
        $result = $this->aggregate( __FUNCTION__, [$column] );

        return $result ?: 0;
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg( $column ) {
        return $this->aggregate( __FUNCTION__, [$column] );
    }

      /**
     * Prepare the value and operator for a where clause.
     *
     * @param  string  $value
     * @param  string  $operator
     * @param  bool  $use_default
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function prepare_value_and_operator( $value, $operator, $use_default = false ) {
        if ( $use_default ) {
            return [$operator, '='];
        } elseif ( $this->invalid_operatorAndValue( $operator, $value ) ) {
            throw new InvalidArgumentException( 'Illegal operator and value combination.' );
        }

        return [$value, $operator];
    }

     /**
     * Determine if the given operator and value combination is legal.
     *
     * Prevents using Null values with invalid operators.
     *
     * @param  string  $operator
     * @param  mixed  $value
     * @return bool
     */
    protected function invalid_operatorAndValue( $operator, $value ) {
        return is_null( $value ) && in_array( $operator, $this->operators ) &&
             ! in_array( $operator, ['=', '<>', '!='] );
    }

    /**
    * Determine if the given operator is supported.
    *
    * @param  string  $operator
    * @return bool
    */
    protected function invalid_operator( $operator ) {
        return ! is_string( $operator ) || ! in_array( strtolower( $operator ), $this->operators, true );
    }
}
