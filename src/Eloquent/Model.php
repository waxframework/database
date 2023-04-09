<?php

namespace WaxFramework\Database\Eloquent;

use WaxFramework\Database\Eloquent\Relations\BelongsToMany;
use WaxFramework\Database\Eloquent\Relations\BelongsToOne;
use WaxFramework\Database\Eloquent\Relations\HasMany;
use WaxFramework\Database\Eloquent\Relations\HasOne;
use WaxFramework\Database\Query\Builder;
use WaxFramework\Database\Resolver;

abstract class Model {
    abstract static function get_table_name():string;

    abstract public function resolver():Resolver;

    /**
     * Begin querying the model.
     *
     * @return \WaxFramework\Database\Query\Builder
     */
    public static function query( $as = null ) {
        $model   = new static;
        $builder = new Builder( $model );

        $builder->from( $model->resolver()->table( static::get_table_name() ), $as );

        return $builder;
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string $related
     * @param  string $foreign_key
     * @param  string $local_key
     * @return \WaxFramework\Database\Eloquent\Relations\HasMany
     */
    public function has_many( string $related, string $foreign_key, string $local_key ) {
        return new HasMany( $related, $foreign_key, $local_key );
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string $related
     * @param  string  $foreign_key
     * @param  string  $local_key
     * @return \WaxFramework\Database\Eloquent\Relations\HasOne
     */
    public function has_one( $related, $foreign_key, $local_key ) {
        return new HasOne( $related, $foreign_key, $local_key );
    }

    /**
     * Define an inverse one-to-one relationship.
     *
     * @param  string $related
     * @param  string  $foreign_key
     * @param  string  $local_key
     * @return \WaxFramework\Database\Eloquent\Relations\BelongsToOne
     */
    public function belongs_to_one( $related, $foreign_key, $local_key ) {
        return new BelongsToOne( $related, $foreign_key, $local_key );
    }

    /**
     * Define an inverse many-to-many relationship.
     *
     * @param  string $related
     * @param  string  $foreign_key
     * @param  string  $local_key
     * @return \WaxFramework\Database\Eloquent\Relations\BelongsToMany
     */
    public function belongs_to_many( $related, $foreign_key, $local_key ) {
        return new BelongsToMany( $related, $foreign_key, $local_key );
    }
}