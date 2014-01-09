<?php
/*
Plugin Name: Simple Release Notes
Plugin URI: http://bonsaibudget.com/wordpress/simple-release-notes/
Description: A simple release notes custom post type.  Creates release notes with categories.
Version: 1.0
Author: Katherine Semel
Author URI: http://bonsaibudget.com/
*/

if ( ! class_exists( 'Simple_Release_Notes' ) ) {
    class Simple_Release_Notes {

        function Simple_Release_Notes() {

            // Register our custom post type
            add_action( 'init', array( $this, 'register_custom_post_type' ) );

            // Add the custom taxonomies
            add_action( 'init', array( $this, 'register_custom_taxonomy' ), 1 );

            // Set up the new permalink for this structure
            add_action( 'init', array( $this, 'register_permalinks' ), 1 );

            // Handle our custom permalinks
            add_filter( 'post_type_link', array( $this, 'process_custom_permalink' ), 10, 3 );

            // Filter our custom type off the home page
            add_action( 'pre_get_posts', array( $this, 'customize_query_for_custom_type' ) );

            // On the posts page, add a Category column
            add_filter( 'manage_posts_columns', array( $this, 'add_taxonomy_column' ), 10, 1 );
            add_action( 'manage_posts_custom_column', array( $this, 'manage_taxonomy_column' ), 10, 2 );
        }

        function register_custom_post_type() {
            $labels = array(
                'name'          => __( 'Release Notes' ),
                'singular_name' => __( 'Release Note' ),
                'all_items'     => __( 'All Release Notes' ),
                'add_new_item'  => __( 'Add New Release Notes' ),
                'menu_name'     => __( 'Release Notes' )
            );

            $supports = array(
                'title',
                'editor',
                'author'
            );

            $args = array(
                'labels'              => $labels,
                'description'         => 'Release notes',
                'public'              => true,
                'menu_position'       => 5,
                'supports'            => $supports,
                'rewrite'             => array(
                    'slug'        => "release-notes/%release_notes_category%",
                    'feeds'       => true,
                    'with_front'  => true,
                ),
                'taxonomies'          => array(
                    'release-notes-category'
                ),
                'has_archive'         => true,
                'hierarchical'        => false,
            );


            register_post_type( 'release-notes', $args );
        }

        function register_custom_taxonomy() {
            $taxonomy_name = 'release-notes-category';

            $taxonomy_labels = array(
                'name'              => __( 'Release Note Categories' ),
                'singular_name'     => __( 'Release Note Category' ),
                'all_items'         => __( 'All Release Note Categories' ),
                'edit_item'         => __( 'Edit Release Note Category' ),
                'update_item'       => __( 'Update Release Note Category' ),
                'add_new_item'      => __( 'Add New Release Note Category' ),
                'new_item_name'     => __( 'New Release Note Category' ),
                'menu_name'         => __( 'Categories' )
            );

            register_taxonomy(
                $taxonomy_name,
                array( 'release-notes' ),
                array(
                    'hierarchical'      => false,
                    'labels'            => $taxonomy_labels,
                    'public'            => true,
                    'show_ui'           => true,
                    'show_in_nav_menus' => true,
                    'query_var'         => true,
                    'rewrite' => array(
                        'slug'       => "release-notes",
                        'feeds'      => true,
                        'with_front' => false,
                    ),
                    'has_archive' => false
                )
            );
        }

        function register_permalinks() {
            global $wp_rewrite;

            $wp_rewrite->add_rewrite_tag( "%release_notes_category%", '([^/]+)', "release-notes-category=" );
            $wp_rewrite->add_rewrite_tag( "%release_notes%", '([^/]+)', "release-notes=" );

            // Release Notes
            $release_notes_structure = '/release-notes/%release_notes_category%/%release_notes%';
            $wp_rewrite->add_permastruct( 'release-notes', $release_notes_structure, false );
        }

        function process_custom_permalink( $permalink, $post, $leavename ) {

            $rewritecode = array(
                '%release_notes_category%'
            );

            if ( '' != $permalink && ! in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) {
                $release_notes_category = '';
                $cat_parent  = '';

                if ( strpos( $permalink, '%release_notes_category%' ) !== false ) {
                    $terms = wp_get_object_terms( $post->ID, 'release-notes-category' );
                    if ( !is_wp_error( $terms ) && !empty( $terms ) ) {
                        foreach ( $terms as $term ) {
                            if ( $term->parent == 0 ) {
                                $release_notes_category = $term->slug;
                                $cat_parent  = $term->term_id;
                                break;
                            }
                        }
                    }
                }

                $rewritereplace = array(
                    $release_notes_category
                );

                $permalink = str_replace( $rewritecode, $rewritereplace, $permalink );
            }

            return $permalink;
        }

        function customize_query_for_custom_type( $query ) {
            if ( is_admin() ){
                return;
            }
            if ( $query->is_home() && $query->is_main_query() ) {
                // Exclude our custom type from the home page

                $args = array(
                    'posts_per_page'   => -1,
                    'post_type'        => 'release-notes'
                );
                $posts_in_custom_type = get_posts( $args );
                $post_ids_in_custom_type = implode( ',', wp_list_pluck( $posts_in_custom_type, 'ID' ) ) ;
                //die( '<pre>' . print_r( $post_ids_in_custom_type, true) . '</pre>' );
                $query->set('post__not_in', array( $post_ids_in_custom_type ) );
            }

            if ( $query->is_tax( 'release-notes-category' ) && $query->is_main_query() ) {
                // Show unlimited posts on our taxonomy archive page
                $query->set( 'posts_per_page', -1 );
            }
        }


        function add_taxonomy_column( $columns ) {
            $columns['release-notes-category'] = _x( 'Category', 'column name' );
            return $columns;
        }

        function manage_taxonomy_column( $column_name, $post_id ) {
            if ( $column_name != 'release-notes-category' ) {
                return;
            }

            $post = get_post( $post_id );

            $categories = get_the_terms( $post_id, 'release-notes-category' );
            if ( !empty( $categories ) ) {
                $out = array();
                foreach ( $categories as $category ) {
                    $out[] = sprintf( '<a href="%s">%s</a>',
                        esc_url( add_query_arg( array( 'post_type' => $post->post_type, 'release-notes-category' => $category->slug ), 'edit.php' ) ),
                        esc_html( sanitize_term_field( 'name', $category->name, $category->term_id, 'release-notes-category', 'display' ) )
                    );
                }
                echo join( ', ', $out );
            } else {
                _e( 'No Category' );
            }
        }


    }
    $Simple_Release_Notes = new Simple_Release_Notes();
}

?>
