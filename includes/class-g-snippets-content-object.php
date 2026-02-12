<?php

/**
 * G-Snippets Content Injector
 *
 * @package G_Snippets
 */

namespace G_Snippets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Injector class
 */
class Content_Object
{
    /**
     * Current Post
     * 
     * @var WP_Post|null
     */
    private $post = null;

    /**
     * Snippets
     *
     * @var array|null
     */
    private $snippets = null;

    /**
     * Snippet cached data
     *
     * @var array
     */
    private $snippet_cached_data = [];

    /**
     * Post categories
     *
     * @var array|null
     */
    private $post_categories = null;

    /**
     * Constructor     
     */
    public function __construct()
    {
        global $post;
        if (!$post || !isset($post->ID)) {
            throw new \Exception('G-Snippets: Post not found');
        }
        
        $this->post            = $post;
        $this->post_categories = wp_get_post_categories($post->ID);
        $this->load_matching_snippets();
    }

    /**
     * Get post categories
     *
     * @return array Array of category IDs
     */
    public function get_categories()
    {
        return $this->post_categories;
    }

    /**
     * Get snippets
     *
     * @return array Array of WP_Post objects
     */
    public function get_snippets()
    {
        return $this->snippets;
    }

    /**
     * Load matching snippets
     */
    private function load_matching_snippets()
    {
        if (null !== $this->snippets) {
            return $this->snippets;
        }

        $snippets = $this->get_active_snippets();
        if (empty($snippets)) {
            $this->snippets = [];
            return $this->snippets;
        }

        foreach ($snippets as $snippet) {
            if ($this->snippet_matches($snippet)) {
                $this->snippets[] = $snippet;
            }
        }
    }

    /**
     * Check if snippet matches the current post
     *
     * @param WP_Post $snippet Snippet post object
     * @return bool True if snippet matches
     */
    private function snippet_matches($snippet)
    {
        $snippet_data = $this->get_snippet_data($snippet);

        // Check if snippet is for the current post type
        $snippet_post_types = $snippet_data['post_types'];
        if (empty($snippet_post_types) || !is_array($snippet_post_types)) {
            $snippet_post_types = ['post'];
        }

        if (!in_array($this->post->post_type, $snippet_post_types, true)) {
            return false;
        }

        // Check if snippet has categories and the post has at least one matching category
        if ($snippet_data['has_categories'] && $snippet_data['matching_categories_count'] === 0) {
            return false;
        }

        // Check if snippet has include posts and the post is in the include posts
        if ($snippet_data['include_posts'] && !in_array($this->post->ID, $snippet_data['include_posts'], true)) {
            return false;
        }

        // Check if snippet has exclude posts and the post is in the exclude posts
        if ($snippet_data['exclude_posts'] && in_array($this->post->ID, $snippet_data['exclude_posts'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Get all active snippets
     *
     * @return array Array of WP_Post objects
     */
    private function get_active_snippets()
    {
        $args = [
            'post_type'      => 'g_snippet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'g_snippet_priority',
            'order'          => 'ASC',
        ];

        $query = new \WP_Query($args);
        $snippets = [];

        if ($query->have_posts()) {
            foreach ($query->posts as $snippet) {
                $snippet_data = $this->get_snippet_data($snippet);
                if ($snippet_data['active'] === false || $snippet_data['active'] === null) {
                    continue;
                }

                $snippets[] = $snippet;
            }
        }

        wp_reset_postdata();

        return $snippets;
    }

    /**
     * Get snippet cached data
     *
     * @param WP_Post $snippet Snippet post object
     * @return array Snippet cached data
     */
    public function get_snippet_data($snippet)
    {
        if (isset($this->snippet_cached_data[$snippet->ID])) {
            return $this->snippet_cached_data[$snippet->ID];
        }

        $categories = get_field('g_snippet_categories', $snippet->ID);
        $has_categories = !empty($categories);
        $matching_categories = [];
        if ($has_categories) {
            $categories = is_array($categories) ? $categories : [$categories];
            $matching_categories = array_intersect($categories, $this->post_categories);
        }

        $this->snippet_cached_data[$snippet->ID] = [
            'post_types'                => get_field('g_snippet_post_types', $snippet->ID),
            'categories'                => $categories,
            'has_categories'            => $has_categories,
            'matching_categories'       => $matching_categories,
            'matching_categories_count' => count($matching_categories),
            'include_posts'             => get_field('g_snippet_include_posts', $snippet->ID),
            'exclude_posts'             => get_field('g_snippet_exclude_posts', $snippet->ID),
            'active'                    => get_field('g_snippet_active', $snippet->ID),
            'priority'                  => (int) get_field('g_snippet_priority', $snippet->ID),
            'location'                  => get_field('g_snippet_location', $snippet->ID),
        ];

        return $this->snippet_cached_data[$snippet->ID];
    }
}