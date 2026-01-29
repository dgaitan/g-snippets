<?php

/**
 * G-Snippets List Table Customization
 *
 * @package G_Snippets
 */

namespace G_Snippets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * List Table class
 */
class List_Table
{
    /**
     * Instance
     *
     * @var List_Table
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return List_Table
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
        // Add custom columns
        add_filter('manage_g_snippet_posts_columns', [$this, 'add_custom_columns']);
        
        // Populate custom columns
        add_action('manage_g_snippet_posts_custom_column', [$this, 'populate_custom_columns'], 10, 2);
        
        // Make columns sortable
        add_filter('manage_edit-g_snippet_sortable_columns', [$this, 'make_columns_sortable']);
        
        // Handle column sorting
        add_action('pre_get_posts', [$this, 'handle_column_sorting']);
    }

    /**
     * Add custom columns to list table
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_custom_columns($columns)
    {
        // Insert custom columns after title
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Insert after title
            if ($key === 'title') {
                $new_columns['g_snippet_post_types'] = __('Post Types', 'g-snippets');
                $new_columns['g_snippet_location'] = __('Location', 'g-snippets');
                $new_columns['g_snippet_priority'] = __('Priority', 'g-snippets');
                $new_columns['g_snippet_active'] = __('Active', 'g-snippets');
                $new_columns['g_snippet_include_count'] = __('Include', 'g-snippets');
                $new_columns['g_snippet_exclude_count'] = __('Exclude', 'g-snippets');
            }
        }

        return $new_columns;
    }

    /**
     * Populate custom columns
     *
     * @param string $column  Column name
     * @param int     $post_id Post ID
     */
    public function populate_custom_columns($column, $post_id)
    {
        switch ($column) {
            case 'g_snippet_post_types':
                $this->render_post_types_column($post_id);
                break;

            case 'g_snippet_location':
                $this->render_location_column($post_id);
                break;

            case 'g_snippet_priority':
                $this->render_priority_column($post_id);
                break;

            case 'g_snippet_active':
                $this->render_active_column($post_id);
                break;

            case 'g_snippet_include_count':
                $this->render_include_count_column($post_id);
                break;

            case 'g_snippet_exclude_count':
                $this->render_exclude_count_column($post_id);
                break;
        }
    }

    /**
     * Render post types column
     *
     * @param int $post_id Post ID
     */
    private function render_post_types_column($post_id)
    {
        if (!function_exists('get_field')) {
            echo '<span class="g-snippets-empty">—</span>';
            return;
        }
        
        $post_types = get_field('g_snippet_post_types', $post_id);
        
        if (empty($post_types) || !is_array($post_types)) {
            echo '<span class="g-snippets-empty">—</span>';
            return;
        }

        $labels = [];
        foreach ($post_types as $post_type) {
            $post_type_obj = get_post_type_object($post_type);
            if ($post_type_obj) {
                $labels[] = $post_type_obj->label;
            } else {
                $labels[] = $post_type;
            }
        }

        echo esc_html(implode(', ', $labels));
    }

    /**
     * Render location column
     *
     * @param int $post_id Post ID
     */
    private function render_location_column($post_id)
    {
        if (!function_exists('get_field')) {
            echo '<span class="g-snippets-empty">—</span>';
            return;
        }
        
        $location = get_field('g_snippet_location', $post_id);
        
        if (empty($location)) {
            $location = 'after'; // Default
        }

        $labels = [
            'before' => __('Before', 'g-snippets'),
            'after' => __('After', 'g-snippets'),
        ];

        $label = isset($labels[$location]) ? $labels[$location] : $location;
        echo '<span class="g-snippets-location g-snippets-location-' . esc_attr($location) . '">' . esc_html($label) . '</span>';
    }

    /**
     * Render priority column
     *
     * @param int $post_id Post ID
     */
    private function render_priority_column($post_id)
    {
        if (!function_exists('get_field')) {
            echo '<span class="g-snippets-empty">—</span>';
            return;
        }
        
        $priority = get_field('g_snippet_priority', $post_id);
        
        if (empty($priority) || !is_numeric($priority)) {
            $priority = 10; // Default
        }

        echo '<span class="g-snippets-priority">' . esc_html($priority) . '</span>';
    }

    /**
     * Render active column
     *
     * @param int $post_id Post ID
     */
    private function render_active_column($post_id)
    {
        if (!function_exists('get_field')) {
            echo '<span class="g-snippets-empty">—</span>';
            return;
        }
        
        $active = get_field('g_snippet_active', $post_id);
        
        // ACF true_false returns 1/0, check for truthy value
        $is_active = ($active !== false && $active !== null) ? (bool) $active : true;

        if ($is_active) {
            echo '<span class="g-snippets-active g-snippets-active-yes" title="' . esc_attr__('Active', 'g-snippets') . '">✓</span>';
        } else {
            echo '<span class="g-snippets-active g-snippets-active-no" title="' . esc_attr__('Inactive', 'g-snippets') . '">✗</span>';
        }
    }

    /**
     * Render include count column
     *
     * @param int $post_id Post ID
     */
    private function render_include_count_column($post_id)
    {
        if (!function_exists('get_field')) {
            echo '<span class="g-snippets-empty">—</span>';
            return;
        }
        
        $include_posts = get_field('g_snippet_include_posts', $post_id);
        
        if (empty($include_posts)) {
            echo '<span class="g-snippets-empty">—</span>';
            return;
        }

        $count = is_array($include_posts) ? count($include_posts) : 1;
        echo '<span class="g-snippets-count">' . esc_html($count) . '</span>';
    }

    /**
     * Render exclude count column
     *
     * @param int $post_id Post ID
     */
    private function render_exclude_count_column($post_id)
    {
        if (!function_exists('get_field')) {
            echo '<span class="g-snippets-empty">—</span>';
            return;
        }
        
        $exclude_posts = get_field('g_snippet_exclude_posts', $post_id);
        
        if (empty($exclude_posts)) {
            echo '<span class="g-snippets-empty">—</span>';
            return;
        }

        $count = is_array($exclude_posts) ? count($exclude_posts) : 1;
        echo '<span class="g-snippets-count">' . esc_html($count) . '</span>';
    }

    /**
     * Make columns sortable
     *
     * @param array $columns Existing sortable columns
     * @return array Modified sortable columns
     */
    public function make_columns_sortable($columns)
    {
        $columns['g_snippet_priority'] = 'g_snippet_priority';
        $columns['g_snippet_active'] = 'g_snippet_active';
        
        return $columns;
    }

    /**
     * Handle column sorting
     *
     * @param \WP_Query $query WP_Query object
     */
    public function handle_column_sorting($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'g_snippet_priority') {
            $query->set('meta_key', 'g_snippet_priority');
            $query->set('orderby', 'meta_value_num');
        } elseif ($orderby === 'g_snippet_active') {
            $query->set('meta_key', 'g_snippet_active');
            $query->set('orderby', 'meta_value');
        }
    }
}
