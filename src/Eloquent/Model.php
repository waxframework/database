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

    /**
     * Begin querying the model.
     *
     * @return \WaxFramework\Database\Query\Builder
     */
    public static function query( $as = null ) {
        $model    = new static;
        $builder  = new Builder( $model );
        $resolver = new Resolver;

        $builder->from( $resolver->table( static::get_table_name() ), $as );

        return $builder;
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \WaxFramework\Database\Eloquent\Relations\HasMany
     */
    public function hasMany( $related, $foreignKey, $localKey ) {
        return new HasMany( $related, $foreignKey, $localKey );
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \WaxFramework\Database\Eloquent\Relations\HasOne
     */
    public function hasOne( $related, $foreignKey, $localKey ) {
        return new HasOne( $related, $foreignKey, $localKey );
    }

    /**
     * Define an inverse one-to-one relationship.
     *
     * @param  string $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \WaxFramework\Database\Eloquent\Relations\BelongsToOne
     */
    public function belongsToOne( $related, $foreignKey, $localKey ) {
        return new BelongsToOne( $related, $foreignKey, $localKey );
    }

    /**
     * Define an inverse many-to-many relationship.
     *
     * @param  string $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \WaxFramework\Database\Eloquent\Relations\BelongsToMany
     */
    public function belongsToMany( $related, $foreignKey, $localKey ) {
        return new BelongsToMany( $related, $foreignKey, $localKey );
    }
}