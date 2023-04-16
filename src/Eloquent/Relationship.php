<?php

namespace WaxFramework\Database\Eloquent;

use WaxFramework\Database\Eloquent\Relations\BelongsToMany;
use WaxFramework\Database\Eloquent\Relations\BelongsToOne;
use WaxFramework\Database\Eloquent\Relations\HasOne;
use WaxFramework\Database\Eloquent\Relations\Relation;
use WaxFramework\Database\Query\Builder;
use wpdb;

class Relationship {
    protected function process_relationships( $parent_items, array $relations, Model $model ) {
        if ( empty( $relations ) ) {
            return $parent_items;
        }

        foreach ( $relations as $key => $relation ) {
            /**
             * @var Relation $relationship
             */
            $relationship = $model->$key();

            /**
             * @var Model $related
             */
            $related = $relationship->get_related();

            /**
             * @var Builder $query 
             */
            $query = $relation['query'];

            $table_name = $related::get_table_name();

            $query->from( $table_name );
            
            $local_key   = $relationship->local_key;
            $foreign_key = $relationship->foreign_key;
            $local_ids   = array_column( $parent_items, $local_key );

            if ( $relationship instanceof BelongsToMany ) {
                $pivot_table_name  = $relationship->pivot::get_table_name();
                $foreign_pivot_key = $relationship->foreign_pivot_key;
                $local_pivot_key   = $relationship->local_pivot_key;

                $query->select( "{$table_name}.*", "{$pivot_table_name}.{$local_pivot_key} as pivot_{$local_pivot_key}" )
                ->join( $pivot_table_name, "{$pivot_table_name}.{$foreign_pivot_key}", "{$table_name}.{$foreign_key}" )
                ->where_in( "{$pivot_table_name}.{$local_pivot_key}", $local_ids );
            } else {
                $query->where_in( $query->as . '.' . $foreign_key,  $local_ids );
            }

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
                 * @var Relation $relationship
                 */
                $relationship = $relation['relationship'];

                $local_value = $item->{$relationship->local_key};
                
                if ( $relationship instanceof BelongsToMany ) {
                    $foreign_key = "pivot_{$relationship->local_pivot_key}";
                } else {
                    $foreign_key = $relationship->foreign_key;
                }

                $children_items = array_values(
                    array_filter(
                        $relation['items'], function( $single_item ) use ( $local_value, $foreign_key ) {
                            return $single_item->{$foreign_key} == $local_value;
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