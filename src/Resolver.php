<?php

namespace WaxFramework\Database;

class Resolver {
    protected array $network_tables = [
        'commentmeta',
        'comments',
        'links',
        'options',
        'postmeta',
        'posts',
        'termmeta',
        'terms',
        'term_relationships',
        'term_taxonomy'
    ];

    public function set_network_tables( array $tables ) {
        $this->network_tables = array_merge( $this->network_tables, $tables );
    }

    public function table( string $table ) {
        $table_args = func_get_args();

        if ( 1 === count( $table_args ) ) {
            return $this->resolve_table_name( $table );
        }

        return array_map(
            function( $table ) {
                return $this->resolve_table_name( $table );
            }, $table_args
        );
    }

    protected function resolve_table_name( string $table ) {
        global $wpdb;
        if ( in_array( $table, $this->network_tables ) ) {
            return $wpdb->prefix . $table;
        }
        return $wpdb->base_prefix . $table;
    }
}