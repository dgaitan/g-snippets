<?php

/**
 * G-Snippets ACF Field Group Registration
 *
 * @package G_Snippets
 */

namespace G_Snippets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ACF Fields class
 */
class ACF_Fields
{
    /**
     * Instance
     *
     * @var ACF_Fields
     */
    private static $instance = null;

    /**
     * Track if field group has been registered
     *
     * @var bool
     */
    private static $registered = false;

    /**
     * Get instance
     *
     * @return ACF_Fields
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
    
        add_action('acf/init', [$this, 'register_field_group'], 20);
        add_action('init', [$this, 'register_field_group'], 20);
    }

    /**
     * Get available post types for field options
     *
     * @return array
     */
    private function get_post_type_choices()
    {
        $post_types = get_post_types(['public' => true], 'objects');
        $choices = [];

        foreach ($post_types as $post_type) {
            $choices[$post_type->name] = $post_type->label;
        }

        return $choices;
    }

    /**
     * Register ACF field group
     */
    public function register_field_group()
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Prevent duplicate registration
        if (self::$registered) {
            return;
        }

        // Get post type choices safely
        $post_type_choices = $this->get_post_type_choices();
        
        // Ensure we have at least 'post' as an option
        if (empty($post_type_choices)) {
            $post_type_choices = ['post' => 'Posts'];
        }

        acf_add_local_field_group([
            'key' => 'group_g_snippet_settings',
            'title' => __('Snippet Settings', 'g-snippets'),
            'fields' => [
                [
                    'key' => 'field_g_snippet_post_types',
                    'label' => __('Post Types', 'g-snippets'),
                    'name' => 'g_snippet_post_types',
                    'type' => 'checkbox',
                    'instructions' => __('Select which post types this snippet should apply to. Default: Posts only.', 'g-snippets'),
                    'required' => 1,
                    'choices' => $post_type_choices,
                    'default_value' => ['post'],
                    'layout' => 'vertical',
                ],
                [
                    'key' => 'field_g_snippet_categories',
                    'label' => __('Categories', 'g-snippets'),
                    'name' => 'g_snippet_categories',
                    'type' => 'taxonomy',
                    'instructions' => __('Optionally select categories. If selected, the snippet will only be displayed on posts that have at least one of these categories.', 'g-snippets'),
                    'required' => 0,
                    'taxonomy' => 'category',
                    'field_type' => 'checkbox',
                    'allow_null' => 1,
                    'return_format' => 'id',
                ],
                [
                    'key' => 'field_g_snippet_location',
                    'label' => __('Location', 'g-snippets'),
                    'name' => 'g_snippet_location',
                    'type' => 'select',
                    'instructions' => __('Where should this snippet be displayed relative to the post content?', 'g-snippets'),
                    'required' => 1,
                    'choices' => [
                        'before' => __('Before Content', 'g-snippets'),
                        'after' => __('After Content', 'g-snippets'),
                    ],
                    'default_value' => 'after',
                    'allow_null' => 0,
                    'multiple' => 0,
                ],
                [
                    'key' => 'field_g_snippet_priority',
                    'label' => __('Priority', 'g-snippets'),
                    'name' => 'g_snippet_priority',
                    'type' => 'number',
                    'instructions' => __('Lower numbers = higher priority. If multiple snippets match, the one with the lowest priority number will be displayed.', 'g-snippets'),
                    'required' => 1,
                    'default_value' => 10,
                    'min' => 1,
                    'max' => 999,
                    'step' => 1,
                ],
                [
                    'key' => 'field_g_snippet_active',
                    'label' => __('Active', 'g-snippets'),
                    'name' => 'g_snippet_active',
                    'type' => 'true_false',
                    'instructions' => __('Enable or disable this snippet. Inactive snippets will not be displayed.', 'g-snippets'),
                    'required' => 0,
                    'default_value' => 1,
                    'ui' => 1,
                ],
                [
                    'key' => 'field_g_snippet_include_posts',
                    'label' => __('Include Posts', 'g-snippets'),
                    'name' => 'g_snippet_include_posts',
                    'type' => 'post_object',
                    'instructions' => __('Optionally select specific posts/pages where this snippet should be displayed. Leave empty to apply to all matching post types.', 'g-snippets'),
                    'required' => 0,
                    'post_type' => array_keys($post_type_choices),
                    'taxonomy' => '',
                    'allow_null' => 1,
                    'multiple' => 1,
                    'return_format' => 'id',
                    'ui' => 1,
                ],
                [
                    'key' => 'field_g_snippet_exclude_posts',
                    'label' => __('Exclude Posts', 'g-snippets'),
                    'name' => 'g_snippet_exclude_posts',
                    'type' => 'post_object',
                    'instructions' => __('Optionally select specific posts/pages where this snippet should NOT be displayed.', 'g-snippets'),
                    'required' => 0,
                    'post_type' => array_keys($post_type_choices),
                    'taxonomy' => '',
                    'allow_null' => 1,
                    'multiple' => 1,
                    'return_format' => 'id',
                    'ui' => 1,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'g_snippet',
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'side', // Display in sidebar instead of bottom
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => __('Configure where and how this snippet should be displayed.', 'g-snippets'),
        ]);

        // Mark as registered
        self::$registered = true;
    }
}
