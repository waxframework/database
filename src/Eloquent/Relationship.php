<?php

namespace WaxFramework\Database\Eloquent;

use WaxFramework\Database\Eloquent\Relations\BelongsToOne;
use WaxFramework\Database\Eloquent\Relations\HasOne;
use wpdb;

class Relationship {
    protected function process_relationships( $parent_items, array $relations, Model $model ) {
        if ( empty( $relations ) ) {
            return $parent_items;
        }

        foreach ( $relations as $key => $relation ) {
            /**
             * @var \WaxFramework\Database\Eloquent\Relations\Relation $relationship
             */
            $relationship = $model->$key();

            /**
             * @var \WaxFramework\Database\Eloquent\Model $related
             */
            $related = $relationship->get_related();

            /**
             * @var \WaxFramework\Database\Query\Builder $query 
             */
            $query = $relation['query'];

            $query->from( $related::get_table_name() );
            
            $query->where_in( $query->from . '.' . $relationship->foreign_key, array_column( $parent_items, $relationship->local_key ) );

            global $wpdb;

            /**
             * @var wpdb $wpdb
             */
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $query->to_sql() );

            $relations[$key]['relationship'] = $relationship;
            $relations[$key]['items']        = $this->process_relationships( $results, $relation['children'], $related );
        }

        return $this->push_related_items( $parent_items, $relations );
    }

    protected function push_related_items( array $parent_items, array $relations ) {
        foreach ( $parent_items as $parent_key => $item ) {

            foreach ( $relations as $key => $relation ) {
                /**
                 * @var \WaxFramework\Database\Eloquent\Relations\Relation $relationship
                 */
                $relationship = $relation['relationship'];

                $local_value = $item->{$relationship->local_key};

                $children_items = array_values(
                    array_filter(
                        $relation['items'], function( $single_item ) use ( $local_value, $relationship ) {
                            return $single_item->{$relationship->foreign_key} == $local_value;
                        }
                    )
                );

                if ( $relationship instanceof HasOne || $relationship instanceof BelongsToOne ) {
                    $children_items = isset( $children_items[0] ) ? $children_items[0] : null;
                }

                $parent_items[$parent_key]->$key = $children_items;
            }
        }
        return $parent_items;
    }
}