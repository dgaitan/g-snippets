<?php

/**
 * G-Snippets Custom Post Type
 *
 * @package G_Snippets
 */

namespace G_Snippets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Post Type class
 */
class Post_Type
{
    /**
     * Instance
     *
     * @var Post_Type
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Post_Type
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('init', [$this, 'register_post_type'], 20);
    }

    /**
     * Register custom post type
     */
    public function register_post_type()
    {
        $labels = [
            'name'                  => _x('Snippets', 'Post type general name', 'g-snippets'),
            'singular_name'         => _x('Snippet', 'Post type singular name', 'g-snippets'),
            'menu_name'             => _x('G-Snippets', 'Admin Menu text', 'g-snippets'),
            'name_admin_bar'        => _x('Snippet', 'Add New on Toolbar', 'g-snippets'),
            'add_new'               => __('Add New', 'g-snippets'),
            'add_new_item'          => __('Add New Snippet', 'g-snippets'),
            'new_item'              => __('New Snippet', 'g-snippets'),
            'edit_item'             => __('Edit Snippet', 'g-snippets'),
            'view_item'             => __('View Snippet', 'g-snippets'),
            'all_items'             => __('All Snippets', 'g-snippets'),
            'search_items'          => __('Search Snippets', 'g-snippets'),
            'parent_item_colon'     => __('Parent Snippets:', 'g-snippets'),
            'not_found'             => __('No snippets found.', 'g-snippets'),
            'not_found_in_trash'    => __('No snippets found in Trash.', 'g-snippets'),
            'featured_image'        => _x('Snippet Featured Image', 'Overrides the "Featured Image" phrase', 'g-snippets'),
            'set_featured_image'    => _x('Set snippet featured image', 'Overrides the "Set featured image" phrase', 'g-snippets'),
            'remove_featured_image' => _x('Remove snippet featured image', 'Overrides the "Remove featured image" phrase', 'g-snippets'),
            'use_featured_image'    => _x('Use as snippet featured image', 'Overrides the "Use as featured image" phrase', 'g-snippets'),
            'archives'              => _x('Snippet archives', 'The post type archive label used in nav menus', 'g-snippets'),
            'insert_into_item'      => _x('Insert into snippet', 'Overrides the "Insert into post"/"Insert into page" phrase', 'g-snippets'),
            'uploaded_to_this_item' => _x('Uploaded to this snippet', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'g-snippets'),
            'filter_items_list'     => _x('Filter snippets list', 'Screen reader text for the filter links', 'g-snippets'),
            'items_list_navigation' => _x('Snippets list navigation', 'Screen reader text for the pagination', 'g-snippets'),
            'items_list'            => _x('Snippets list', 'Screen reader text for the items list', 'g-snippets'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-editor-code',
            'supports'           => ['title', 'editor'],
            'show_in_rest'       => true, // Enable Gutenberg editor
        ];

        register_post_type('g_snippet', $args);
    }
}
