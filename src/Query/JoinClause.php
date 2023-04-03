<?php

namespace WaxFramework\Database\Query;
use WaxFramework\Database\Eloquent\Model;

class JoinClause extends Builder
{
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

    /**
     * Create a new join clause instance.
     * 
     * @param  string  $table
     * @param  string  $type
     * @return void
     */
    public function __construct( string $table, string $type, Model $model ) {
        $this->table = $table;
        $this->type  =  $type;
        parent::__construct( $model );
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
        $this->where( $first, $operator, $second, $boolean );
        return $this;
    }

    /**
     * Add an "on" clause to the join.
     * 
     * @param  \Closure|string  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @param  string  $boolean
     * @return $this
     */
    public function orOn( $first, $operator = null, $second = null ) {
        $this->on( $first, $operator, $second, 'or' );
        return $this;
    }
}