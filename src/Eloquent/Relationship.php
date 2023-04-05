<?php

namespace WaxFramework\Database\Eloquent;

use WaxFramework\Database\Eloquent\Relations\BelongsToOne;
use WaxFramework\Database\Eloquent\Relations\HasOne;
use WaxFramework\Database\Resolver;

class Relationship {
    protected function processRelationships( $parentItems, array $relations, Model $model ) {
        if ( empty( $relations ) ) {
            return $parentItems;
        }

        $resolver = new Resolver;

        foreach ( $relations as $key => $relation ) {
            /**
             * @var \WaxFramework\Database\Eloquent\Relations\Relation $relationship
             */
            $relationship = $model->$key();

            /**
             * @var \WaxFramework\Database\Eloquent\Model $related
             */
            $related = $relationship->getRelated();

            /**
             * @var \WaxFramework\Database\Query\Builder $query 
             */
            $query = $relation['query'];

            $tableName = $resolver->table( $related::get_table_name() );

            $query->from( $tableName )->whereIn( $tableName . '.' . $relationship->foreignKey, array_column( $parentItems, $relationship->localKey ) );

            global $wpdb;

            /**
             * @var \wpdb $wpdb
             */
            $results = $wpdb->get_results( $query->toSql() );

            $relations[$key]['relationship'] = $relationship;
            $relations[$key]['items']        = $this->processRelationships( $results, $relation['children'], $related );
        }

        return $this->pushRelatedItems( $parentItems, $relations );
    }

    protected function pushRelatedItems( array $parentItems, array $relations ) {
        foreach ( $parentItems as $parentKey => $item ) {

            foreach ( $relations as $key => $relation ) {
                /**
                 * @var \WaxFramework\Database\Eloquent\Relations\Relation $relationship
                 */
                $relationship = $relation['relationship'];

                $localValue = $item->{$relationship->localKey};

                $childrenItems = array_values(
                    array_filter(
                        $relation['items'], function( $singleItem ) use ( $localValue, $relationship ) {
                            return $singleItem->{$relationship->foreignKey} == $localValue;
                        }
                    )
                );

                if ( $relationship instanceof HasOne || $relationship instanceof BelongsToOne ) {
                    $childrenItems = isset( $childrenItems[0] ) ? $childrenItems[0] : null;
                }

                $parentItems[$parentKey]->$key = $childrenItems;
            }
        }
        return $parentItems;
    }
}