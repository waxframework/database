<?php

namespace WaxFramework\Database\Eloquent;

use WaxFramework\Database\Query\Builder;
use WaxFramework\Database\Resolver;

abstract class Model {
    abstract static function get_table_name():string;

    /**
     * Begin querying the model.
     *
     * @return \WaxFramework\Database\Query\Builder
     */
    public static function query( $as = null ) {
        $builder  = new Builder;
        $resolver = new Resolver;

        $builder->from( $resolver->table( static::get_table_name() ), $as );

        return $builder;
    }
}