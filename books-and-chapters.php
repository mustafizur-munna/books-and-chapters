<?php

/*
* Plugin Name: Books and Chapters
* Author: M. Munna
* Author URI: http://www.something.com
* Description: A test pl from HH
*/




class Books_and_chapters {
    public $user_type = 'free';
    private static $instance;

    private function __construct() {
        // Constructor should not create a new instance within itself
        add_action("init", array( $this, "init" ) );
    }

    public function init (){
        add_action("init", array( $this,"Books_and_chapters") );
        add_filter("the_content", array( $this,"show_book_chapters") );
        add_filter("the_content", array( $this,"show_thumbnail_on_chapters") );
        add_filter("post_type_link", array( $this,"chapter_cpt_slug_fix"), 1, 2 );

        // Columns Creation

        add_filter("manage_chapter_posts_columns", array( $this,"chapter_column_create"), 10, 1 );
        add_action("manage_chapter_posts_custom_column", array( $this,"add_chapter_column_value"), 10, 2 );
        
        add_filter("manage_book_posts_columns", array( $this,"book_column_create"), 10, 1 );
        add_action("manage_book_posts_custom_column", array( $this,"add_book_column_value"), 10, 2 );

        // Sortable column

        add_filter('manage_edit-book_sortable_columns', array( $this,'book_sortable_columns'));

        // Show related books

        // add_filter('the_content', array( $this,'show_related_books_by_meta'));
        add_filter('the_content', array( $this,'show_related_books_by_taxonomy'));

        // show hide chapters by user type

        add_filter('the_content', array( $this,'show_hide_chapters_by_user_type'));


        /* ================== Hooks from ACF and CPT UI ================= */

        add_action( 'init', array($this, 'cptui_register_my_cpts') );
        add_action( 'init', array( $this,'cptui_register_my_taxes_genre') );

        add_action('acf/include_fields', array( $this,'acf_all_fields_import') );

    }



    public function show_hide_chapters_by_user_type( $content ){
        if( is_singular( 'chapter' ) ){
            if( $this->user_type == 'paid' ){
                $content = $content;
            } else{
                $chapter_id = get_the_ID();
                if( get_post_meta( $chapter_id, 'chapter_order', true ) == 1 ){
                    $content = $content;
                } else {
                    $content = "<p>Sorry, you are not allowed to view this page. <a style='color: #1B75D0;' href='#'>Purchase our premium package</a> </p>";
                }
            }
        }
        return $content;
    }
    
    public function show_related_books_by_taxonomy( $content ){
        if( is_singular( 'book' ) ){
            $book_id = get_the_ID();
            $genres = wp_get_post_terms( $book_id,'genre');
            $genre = $genres[0]->term_id;
            $args = array(
                'post_type' => 'book',
                'post__not_in' => array( $book_id ),
                'tax_query' => array(
                    array(
                        'taxonomy' => 'genre',
                        'field' => 'term_id',
                        'terms' => $genre,
                    )
                ),
            );
            $books = get_posts( $args );

            if( $books ) {
                $heading = '<h2>Related Books</h2>';
                $content .= $heading;
                $content .= '<ul>';
                foreach( $books as $book ) {
                    $content .= '<li><a href="' . get_permalink($book->ID) . '">' . $book->post_title . ' </a></li>';
                }
                $content .= '</ul>';
            }
        }
        return $content;
    }

    // public function show_related_books_by_meta( $content ){
    //     if( is_singular( 'book' ) ){
    //         $book_id = get_the_ID();
    //         $genre = get_post_meta( $book_id,'genre', true );
    //         $args = array(
    //             'post_type' => 'book',
    //             'post__not_in' => array( $book_id ),
    //             'meta_key' => 'genre',
    //             'meta_value' => $genre,
    //         );
    //         $books = get_posts( $args );

    //         if( $books ) {
    //             $heading = '<h2>Related Books by Meta</h2>';
    //             $content .= $heading;
    //             $content .= '<ul>';
    //             foreach( $books as $book ) {
    //                 $content .= '<li><a href="' . get_permalink($book->ID) . '">'. $book->post_title . ' </a></li>';
    //             }
    //             $content .= '</ul>';
    //         }
    //     }
    //     return $content;
    // }

    public function book_sortable_columns($columns){
        $columns['chapters'] = 'Number of Chapters'; // Column ID and sort field
        return $columns;
    }

    public function book_column_create($columns){
        $new_columns = array();
        foreach($columns as $key => $column){
            if( 'title' ==  $key){
                $new_columns [$key] = $column;
                $new_columns['genre'] = 'Genre';
                $new_columns['chapters'] = 'Number of Chapters';
            } else{
                $new_columns[$key] = $column;
            }

        }
        return $new_columns;
    }

    public function add_book_column_value( $column_name, $post_id ){

            // Define the query arguments
            $args = array(
                'post_type' => 'chapter', // Replace with your child post type
                'meta_query' => array(
                    array(
                        'key' => 'book_name', // Replace with your meta key
                        'value' => $post_id,
                        'compare' => '='
                    )
                )
            );
        
            // Execute the query
            $query = new WP_Query($args);
        
            // Return the count of found posts
            $count = $query->found_posts;

        if( 'chapters' == $column_name ){
            echo $count;
        }

        if( 'genre' == $column_name ){
            echo get_field('genre');
        }
    }


    public function chapter_column_create( $columns ){
        $new_columns = array();
        foreach($columns as $key => $column){
            if( 'title' ==  $key){
                $new_columns [$key] = $column;
                $new_columns['book'] = 'Book Name';
            } else{
                $new_columns[$key] = $column;
            }

        }
        return $new_columns;
    }

    public function add_chapter_column_value( $column_name, $post_id ){
        if( "book" == $column_name ){
            $book_id = get_field('book_name');
            $book = get_post( $book_id );
            echo "<a class='row-title' href='" . get_permalink($book_id) . "'>$book->post_title</a>";
        }
    }

    public function show_thumbnail_on_chapters( $content ) {
        if( is_singular( 'chapter' ) ){
            $book_id = get_field('book_name');
            if( $book_id ) {
                $book_thumbnail = get_the_post_thumbnail_url( $book_id );
                $book_url = get_post_permalink( $book_id );
                if( $book_thumbnail ) {
                    return "<div><img src='{$book_thumbnail}'></div>" . $content . "<div><a href='{$book_url}'>Read The Book</a></div>";
                }
            }
        }   
        return $content;
    }

    public function show_book_chapters( $content ) {
        if(is_singular( "book" )) {
            $book_id = get_the_ID();

                $chapters_args = array(
                    "post_type"=> "chapter",
                    "posts_per_page" => 100,
                    "meta_query" => array(
                        array(
                            "key"=> "book_name",
                            "value" => $book_id,
                            "compare" => "=",
                        )
                    ),
                    "meta_key" => "chapter_order",
                    "orderby"=> "meta_value_num",
                    "order"=> "ASC",
                );

            $chapters = get_posts( $chapters_args );

            if( ! empty( $chapters ) ) {
                $heading = "<h2>Chapters</h2>";
                $content .= $heading;
                $content .= "<ul>";
                foreach( $chapters as $chapter ) {
                    $content .= "<li><a href=". get_permalink($chapter->ID) .">{$chapter->post_title}</a></li>";
                }
                $content .= "</ul>";
            }
        }
        return $content;
    }

    public function chapter_cpt_slug_fix($post_link, $post){
        if( get_post_type( $post ) == 'chapter' ){
            $book_id = get_field('book_name');
            $book = get_post( $book_id );
            $post_link = str_replace( '%book%', $book->post_name, $post_link);
        }
        return $post_link;
    }

    function Books_and_chapters() {

        /**
         * Post Type: Books.
         */
    
        $labels = [
            "name" => esc_html__( "Books", "twentytwentyfour" ),
            "singular_name" => esc_html__( "Book", "twentytwentyfour" ),
            "menu_name" => esc_html__( "My Books", "twentytwentyfour" ),
            "all_items" => esc_html__( "All Books", "twentytwentyfour" ),
            "add_new" => esc_html__( "Add New Book", "twentytwentyfour" ),
            "add_new_item" => esc_html__( "Add New Book", "twentytwentyfour" ),
            "edit_item" => esc_html__( "Edit Book", "twentytwentyfour" ),
            "new_item" => esc_html__( "New Book", "twentytwentyfour" ),
            "view_item" => esc_html__( "View Book", "twentytwentyfour" ),
            "view_items" => esc_html__( "View Books", "twentytwentyfour" ),
            "search_items" => esc_html__( "Search Books", "twentytwentyfour" ),
        ];
    
        $args = [
            "label" => esc_html__( "Books", "twentytwentyfour" ),
            "labels" => $labels,
            "description" => "",
            "public" => true,
            "publicly_queryable" => true,
            "show_ui" => true,
            "show_in_rest" => true,
            "rest_base" => "",
            "rest_controller_class" => "WP_REST_Posts_Controller",
            "rest_namespace" => "wp/v2",
            "has_archive" => false,
            "show_in_menu" => true,
            "show_in_nav_menus" => true,
            "delete_with_user" => false,
            "exclude_from_search" => false,
            "capability_type" => "post",
            "map_meta_cap" => true,
            "hierarchical" => false,
            "can_export" => false,
            "rewrite" => [ "slug" => "book", "with_front" => true ],
            "query_var" => true,
            "menu_position" => 5,
            "menu_icon" => "dashicons-book",
            "supports" => [ "title", "editor", "thumbnail" ],
            "show_in_graphql" => false,
        ];
    
        register_post_type( "book", $args );
    }
    
    

    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self(); // Create a new instance only if it doesn't exist
        }
        return self::$instance;
    }



    /* ============= Codes from ACF and CPT UI =============*/

    function cptui_register_my_cpts() {

        /**
         * Post Type: Books.
         */
    
        $labels = [
            "name" => esc_html__( "Books", "twentytwentyfour" ),
            "singular_name" => esc_html__( "Book", "twentytwentyfour" ),
            "menu_name" => esc_html__( "My Books", "twentytwentyfour" ),
            "all_items" => esc_html__( "All Books", "twentytwentyfour" ),
            "add_new" => esc_html__( "Add New Book", "twentytwentyfour" ),
            "add_new_item" => esc_html__( "Add New Book", "twentytwentyfour" ),
            "edit_item" => esc_html__( "Edit Book", "twentytwentyfour" ),
            "new_item" => esc_html__( "New Book", "twentytwentyfour" ),
            "view_item" => esc_html__( "View Book", "twentytwentyfour" ),
            "view_items" => esc_html__( "View Books", "twentytwentyfour" ),
            "search_items" => esc_html__( "Search Books", "twentytwentyfour" ),
        ];
    
        $args = [
            "label" => esc_html__( "Books", "twentytwentyfour" ),
            "labels" => $labels,
            "description" => "",
            "public" => true,
            "publicly_queryable" => true,
            "show_ui" => true,
            "show_in_rest" => true,
            "rest_base" => "",
            "rest_controller_class" => "WP_REST_Posts_Controller",
            "rest_namespace" => "wp/v2",
            "has_archive" => false,
            "show_in_menu" => true,
            "show_in_nav_menus" => true,
            "delete_with_user" => false,
            "exclude_from_search" => false,
            "capability_type" => "post",
            "map_meta_cap" => true,
            "hierarchical" => false,
            "can_export" => false,
            "rewrite" => [ "slug" => "book", "with_front" => true ],
            "query_var" => true,
            "menu_position" => 5,
            "menu_icon" => "dashicons-book",
            "supports" => [ "title", "editor", "thumbnail" ],
            "show_in_graphql" => false,
        ];
    
        register_post_type( "book", $args );
    
        /**
         * Post Type: Chapters.
         */
    
        $labels = [
            "name" => esc_html__( "Chapters", "twentytwentyfour" ),
            "singular_name" => esc_html__( "Chapter", "twentytwentyfour" ),
            "menu_name" => esc_html__( "My Chapters", "twentytwentyfour" ),
            "all_items" => esc_html__( "All Chapters", "twentytwentyfour" ),
            "add_new" => esc_html__( "Add New Chapter", "twentytwentyfour" ),
            "add_new_item" => esc_html__( "Add New Chapter", "twentytwentyfour" ),
            "edit_item" => esc_html__( "Edit Chapter", "twentytwentyfour" ),
        ];
    
        $args = [
            "label" => esc_html__( "Chapters", "twentytwentyfour" ),
            "labels" => $labels,
            "description" => "",
            "public" => true,
            "publicly_queryable" => true,
            "show_ui" => true,
            "show_in_rest" => true,
            "rest_base" => "",
            "rest_controller_class" => "WP_REST_Posts_Controller",
            "rest_namespace" => "wp/v2",
            "has_archive" => false,
            "show_in_menu" => true,
            "show_in_nav_menus" => true,
            "delete_with_user" => false,
            "exclude_from_search" => false,
            "capability_type" => "post",
            "map_meta_cap" => true,
            "hierarchical" => false,
            "can_export" => false,
            "rewrite" => [ "slug" => "book/%book%/chapter", "with_front" => true ],
            "query_var" => true,
            "menu_position" => 5,
            "menu_icon" => "dashicons-edit-page",
            "supports" => [ "title", "editor", "thumbnail" ],
            "show_in_graphql" => false,
        ];
    
        register_post_type( "chapter", $args );
    }

    function cptui_register_my_taxes_genre() {

        /**
         * Taxonomy: Genre.
         */
    
        $labels = [
            "name" => esc_html__( "Genre", "twentytwentyfour" ),
            "singular_name" => esc_html__( "Genre", "twentytwentyfour" ),
        ];
    
        
        $args = [
            "label" => esc_html__( "Genre", "twentytwentyfour" ),
            "labels" => $labels,
            "public" => true,
            "publicly_queryable" => true,
            "hierarchical" => false,
            "show_ui" => true,
            "show_in_menu" => true,
            "show_in_nav_menus" => true,
            "query_var" => true,
            "rewrite" => [ 'slug' => 'genre', 'with_front' => true, ],
            "show_admin_column" => false,
            "show_in_rest" => true,
            "show_tagcloud" => false,
            "rest_base" => "genre",
            "rest_controller_class" => "WP_REST_Terms_Controller",
            "rest_namespace" => "wp/v2",
            "show_in_quick_edit" => false,
            "sort" => false,
            "show_in_graphql" => false,
        ];
        register_taxonomy( "genre", [ "book" ], $args );
    }


    function acf_all_fields_import() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }
    
        acf_add_local_field_group( array(
        'key' => 'group_6693b18193a48',
        'title' => 'Books Meta',
        'fields' => array(
            array(
                'key' => 'field_6693b1827f28d',
                'label' => 'Price',
                'name' => 'price',
                'aria-label' => '',
                'type' => 'number',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'min' => '',
                'max' => '',
                'placeholder' => '',
                'step' => '',
                'prepend' => '',
                'append' => '',
            ),
            array(
                'key' => 'field_6693b1c97f28e',
                'label' => 'Genre',
                'name' => 'genre',
                'aria-label' => '',
                'type' => 'select',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'choices' => array(
                    'Sci-Fi' => 'Sci-Fi',
                    'RomCom' => 'RomCom',
                    'Comedy' => 'Comedy',
                    'Thriller' => 'Thriller',
                    'Horror' => 'Horror',
                    'Classic' => 'Classic',
                    'Kids' => 'Kids',
                    'Detective' => 'Detective',
                    'Educational' => 'Educational',
                ),
                'default_value' => false,
                'return_format' => 'value',
                'multiple' => 0,
                'allow_null' => 0,
                'ui' => 0,
                'ajax' => 0,
                'placeholder' => '',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'book',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
        'show_in_rest' => 0,
    ) );
    
        acf_add_local_field_group( array(
        'key' => 'group_6694b260d41d6',
        'title' => 'Chapters Meta',
        'fields' => array(
            array(
                'key' => 'field_6694b261f8877',
                'label' => 'Book Name',
                'name' => 'book_name',
                'aria-label' => '',
                'type' => 'post_object',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'post_type' => array(
                    0 => 'book',
                ),
                'post_status' => '',
                'taxonomy' => '',
                'return_format' => 'id',
                'multiple' => 0,
                'allow_null' => 0,
                'bidirectional' => 0,
                'ui' => 1,
                'bidirectional_target' => array(
                ),
            ),
            array(
                'key' => 'field_66963eaed29ad',
                'label' => 'Chapter Order',
                'name' => 'chapter_order',
                'aria-label' => '',
                'type' => 'number',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'min' => 1,
                'max' => '',
                'placeholder' => '',
                'step' => '',
                'prepend' => '',
                'append' => '',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'chapter',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
        'show_in_rest' => 0,
    ) );
    }


}

Books_and_chapters::get_instance(); // This line creates and initializes the singleton instance
