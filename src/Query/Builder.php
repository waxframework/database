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
     * @param  \WaxFramework\Database\Query\Builder  $query
     * @param  string|null  $as
     * @return $this
     */
    public function from( string $table, $as = null ) {
        $this->from = $table;
        $this->as   = $as;
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
        $current = &$this->relations;

        // Traverse the items string and create nested arrays
        $items = explode( '.', $relations );

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

        return $this;
    }

     /**
     * Add a join clause to the query.
     *
     * @param  string  $table
     * @param  Closure|string  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @param  string  $type
     * @param  bool  $where
     * @return $this
     */
    public function join( $table, $first, $operator = null, $second = null, $type = 'inner' ) {

        $join = new JoinClause( $table, $type, $this->model );

        if ( $first instanceof Closure ) {
            call_user_func( $first, $join );
        } else {
            $join->where( $first, $operator, $second );
        }

        $this->joins[] = $join;
        return $this;
    }

    /**
     * Add a left join to the query.
     *
     * @param  string  $table
     * @param  Closure|string  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @return $this
     */
    public function leftJoin( $table, $first, $operator = null, $second = null ) {
        return $this->join( $table, $first, $operator, $second, 'left' );
    }

    /**
     * Add a right join to the query.
     *
     * @param  string  $table
     * @param  Closure|string  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @return $this
     */
    public function rightJoin( $table, $first, $operator = null, $second = null ) {
        return $this->join( $table, $first, $operator, $second, 'right' );
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
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ( $this->invalidOperator( $operator ) ) {
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
    public function orWhere( $column, $operator = null, $value = null ) {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->where( $column, $operator, $value, 'or' );
    }

    /**
     * Add an exists clause to the query.
     *
     * @param  Closure|static  $callback
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereExists( $callback, $boolean = 'and', $not = false ) {

        if ( $callback instanceof Closure ) {
            call_user_func( $callback, new static( $this->model ) );
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
    public function whereNotExists( $callback, $boolean = 'and' ) {
        return $this->whereExists( $callback, $boolean, true );
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
    public function whereIn( $column, $values, $boolean = 'and', $not = false ) {
        $type = 'in';

        $this->wheres[] = compact( 'type', 'column', 'values', 'boolean', 'not' );

        return $this;
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotIn( $column, $values, $boolean = 'and' ) {
        return $this->whereIn( $column, $values, $boolean, true );
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
    public function whereBetween( $column, array $values, $boolean = 'and', $not = false ) {
        $type = 'between';

        $this->wheres[] = compact( 'type', 'boolean', 'column', 'values', 'not' );

        return $this;
    }

    /**
     * Add a where not between statement to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotBetween( $column, array $values, $boolean = 'and' ) {
        return $this->whereBetween( $column, $values, $boolean, true );
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param  array|string  ...$groups
     * @return $this
     */
    public function groupBy( ...$groups ) {
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
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ( $this->invalidOperator( $operator ) ) {
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
    public function orHaving( $column, $operator = null, $value = null ) {   
        return $this->having( $column, $operator, $value, 'or' );
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderBy( $column, $direction = 'asc' ) {
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
    public function orderByDesc( $column ) {
        return $this->orderBy( $column, 'desc' );
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
    public function toSql() {
        $compiler = new Compiler;
        return $this->bindValues( $compiler->compileSelect( $this ) );
    }

    /**
     * Insert new records into the database.
     *
     * @param  array  $values
     * @return string
     */
    public function toSqlInsert( array $values ) {
        $compiler = new Compiler;
        return $this->bindValues( $compiler->compileInsert( $this, $values ) );
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSqlUpdate( array $values ) {
        $compiler = new Compiler;
        return $this->bindValues( $compiler->compileUpdate( $this, $values ) );
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSqlDelete() {
        $compiler = new Compiler;
        return $this->bindValues( $compiler->compileDelete( $this ) );
    }

    public function get() {
        global $wpdb;
        /**
         * @var wpdb $wpdb
         */
        return $this->processRelationships( $wpdb->get_results( $this->toSql() ), $this->relations, $this->model );
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
        $sql = $this->toSqlInsert( $values );
        global $wpdb;
        /**
         * @var wpdb $wpdb
         */
        return $wpdb->query( $sql );
    }

    /**
     * Insert new single record into the database and get id.
     *
     * @param  array  $values
     */
    public function insertGetId( array $values ) {
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
        $sql = $this->toSqlUpdate( $values );
        global $wpdb;
        /**
         * @var wpdb $wpdb
         */
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
        return $wpdb->query( $this->toSqlDelete() );
    }

    /**
     * Prepare Query Values
     *
     * @param string $sql
     * @return string
     */
    protected function bindValues( string $sql ) {
        global $wpdb;
        /**
         * @var wpdb $wpdb
         */
        return $wpdb->prepare( $sql, ...$this->bindings );
    }

    /**
     * Set query values for the using wpdb::prepare
     *
     * @param mixed $value
     * @return string
     */
    public function setBinding( $value ) {
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
     * @param  bool  $useDefault
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function prepareValueAndOperator( $value, $operator, $useDefault = false ) {
        if ( $useDefault ) {
            return [$operator, '='];
        } elseif ( $this->invalidOperatorAndValue( $operator, $value ) ) {
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
    protected function invalidOperatorAndValue( $operator, $value ) {
        return is_null( $value ) && in_array( $operator, $this->operators ) &&
             ! in_array( $operator, ['=', '<>', '!='] );
    }

    /**
    * Determine if the given operator is supported.
    *
    * @param  string  $operator
    * @return bool
    */
    protected function invalidOperator( $operator ) {
        return ! is_string( $operator ) || ! in_array( strtolower( $operator ), $this->operators, true );
    }
}
