<?php

namespace WaxFramework\Database\Eloquent\Relations;

use WaxFramework\Database\Eloquent\Model;

class HasMany
{
    public Model $related;

    public $foreignKey;

    public $localKey;

    public function __construct( $related, $foreignKey, $localKey ) {
        $this->related    = new $related;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
    }

    public function getRelated() {
        return $this->related;
    }
}