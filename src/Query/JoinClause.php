<?php

namespace WaxFramework\Database\Query;

use WaxFramework\Database\Eloquent\Model;

class JoinClause extends Builder {
    /**
     * The type of join being performed.
     *
     * @var string
     */
    public $type;

    /**
     * The table the join clause is joining to.
     *
     * @var string
     */
    public $table;

    public array $ons = [];

    /**
     * Create a new join clause instance.
     * 
     * @param  string  $table
     * @param  string  $type
     * @return void
     */
    public function __construct( string $table, string $type, Model $model ) {
        parent::__construct( $model );
        $table = explode( ' as ', $table );
        $this->from( $table[0], isset( $table[1] ) ? $table[1] : null );
        $this->type =  $type;
    }

    /**
     * Add an "on" clause to the join.
     * 
     * @param  string  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @param  string  $boolean
     * @return $this
     */
    public function on( $first, $operator = null, $second = null, $boolean = 'and' ) {
        $this->ons[] = $this->where_column( $first, $operator, $second, $boolean, true );
        return $this;
    }

    /**
     * Add an "on" clause to the join.
     * 
     * @param  string  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @param  string  $boolean
     * @return $this
     */
    public function or_on( $first, $operator = null, $second = null ) {
        $this->ons[] = $this->or_where_column( $first, $operator, $second, true );
        return $this;
    }

    /**
     * Add a "on in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @return $this
     */
    public function on_in( string $column, array $values ) {
        $this->ons[] = $this->where_in( $column, $values, 'and', false, true );
        return $this;
    }

    /**
     * Add a or "on in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @return $this|array
     */
    public function or_on_in( string $column, array $values ) {
        $this->ons[] = $this->or_where_in( $column, $values, true );
        return $this;
    }

    /**
     * Add where raw query
     *
     * @param string $sql
     * @return $this|array
     */
    public function on_raw( string $sql ) {
        $this->ons[] = $this->where_raw( $sql, 'and', true );
        return $this;
    }

    /**
     * Add or on raw query
     *
     * @param string $sql
     * @return $this
     */
    public function or_on_raw( string $sql ) {
        $this->ons[] = $this->or_where_raw( $sql, true );
        return $this;
    }
}