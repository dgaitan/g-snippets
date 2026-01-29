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
class Content_Injector
{
    /**
     * Instance
     *
     * @var Content_Injector
     */
    private static $instance = null;

    /**
     * Cache for matched snippets
     *
     * @var array
     */
    private $snippet_cache = [];

    /**
     * Get instance
     *
     * @return Content_Injector
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
        // Hook into the_content filter with priority after block processing
        add_filter('the_content', [$this, 'inject_snippet'], 15);
    }

    /**
     * Inject snippet into content
     *
     * @param string $content Post content
     * @return string Modified content with snippet
     */
    public function inject_snippet($content)
    {
        // Safety check: ensure ACF is available
        if (!function_exists('get_field') || !is_singular()) {
            return $content;
        }

        global $post;
        if (!$post || !isset($post->ID)) {
            return $content;
        }

        $post_id   = $post->ID;
        $post_type = $post->post_type;
        $snippets  = $this->get_matching_snippets($post_id, $post_type);
        if (empty($snippets)) {
            return $content;
        }

        // Get settings
        $settings = Settings::get_instance();
        $display_option = $settings->get_display_option();
        $space_gap = $settings->get_space_gap();

        // Separate snippets by location and sort by priority
        $before_snippets = [];
        $after_snippets = [];
        $before_count = 0;
        $after_count = 0;
        
        foreach ($snippets as $snippet) {
            $location = get_field('g_snippet_location', $snippet->ID);
            if (!$location) {
                $location = 'after'; // Default to after
            }

            // Apply display option: if "first", only show first snippet per location
            if ($display_option === 'first') {
                if ($location === 'before' && $before_count > 0) {
                    continue;
                }
                if ($location === 'after' && $after_count > 0) {
                    continue;
                }
            }

            $snippet_content = sprintf(
                '<div id="g-snippet-%s" class="g-snippet g-snippet-%s">%s</div>', 
                $snippet->ID, 
                $snippet->ID, 
                $this->get_snippet_content($snippet->ID)
            );
            if (empty($snippet_content)) {
                continue;
            }

            if ($location === 'before') {
                $before_snippets[] = $snippet_content;
                $before_count++;
            } else {
                $after_snippets[] = $snippet_content;
                $after_count++;
            }
        }

        // Apply space gap to snippets (except the last one in each group)
        if (!empty($space_gap)) {
            $before_snippets = $this->apply_space_gap($before_snippets, $space_gap);
            $after_snippets = $this->apply_space_gap($after_snippets, $space_gap);
        }

        // Build final content: before snippets + content + after snippets
        $content_to_display = '';
        if (!empty($before_snippets)) {
            $content_to_display .= implode('', $before_snippets);
        }
        
        $content_to_display .= $content;
        if (!empty($after_snippets)) {
            $content_to_display .= implode('', $after_snippets);
        }
        
        return apply_filters('g_snippets_content_to_display', $content_to_display, $snippets);
    }

    /**
     * Get all matching snippets for current post, sorted by priority
     *
     * @param int    $post_id   Current post ID
     * @param string $post_type Current post type
     * @return array Array of matching snippet posts, sorted by priority (lowest first)
     */
    private function get_matching_snippets($post_id, $post_type)
    {
        // Check cache first
        $cache_key = "{$post_id}_{$post_type}";
        if (isset($this->snippet_cache[$cache_key])) {
            return $this->snippet_cache[$cache_key];
        }

        // Get all active snippets
        $snippets = $this->get_active_snippets();
        if (empty($snippets)) {
            $this->snippet_cache[$cache_key] = [];
            return [];
        }

        $matching_snippets = [];
        // Filter snippets by matching criteria
        foreach ($snippets as $snippet) {
            if ($this->snippet_matches($snippet, $post_id, $post_type)) {
                $matching_snippets[] = $snippet;
            }
        }

        if (empty($matching_snippets)) {
            $this->snippet_cache[$cache_key] = [];
            return [];
        }

        // Sort snippets by priority (lowest number = highest priority = first)
        $sorted_snippets = $this->sort_snippets_by_priority($matching_snippets);

        // Cache result
        $this->snippet_cache[$cache_key] = $sorted_snippets;

        return $sorted_snippets;
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
                // Check if snippet is active
                $active = get_field('g_snippet_active', $snippet->ID);
                if ($active === false || $active === null) {
                    continue;
                }

                $snippets[] = $snippet;
            }
        }

        wp_reset_postdata();

        return $snippets;
    }

    /**
     * Check if snippet matches current post
     *
     * @param \WP_Post $snippet   Snippet post object
     * @param int       $post_id  Current post ID
     * @param string    $post_type Current post type
     * @return bool True if snippet matches
     */
    private function snippet_matches($snippet, $post_id, $post_type)
    {
        $snippet_post_types = get_field('g_snippet_post_types', $snippet->ID);   
        if (empty($snippet_post_types) || !is_array($snippet_post_types)) {
            $snippet_post_types = ['post'];
        }

        if (!in_array($post_type, $snippet_post_types, true)) {
            return false;
        }

        $snippet_categories = get_field('g_snippet_categories', $snippet->ID);        
        if (!empty($snippet_categories)) {
            $snippet_category_ids = is_array($snippet_categories) ? $snippet_categories : [$snippet_categories];
            $post_category_ids = wp_get_post_categories($post_id);
            $matching_categories = array_intersect($snippet_category_ids, $post_category_ids);
            if (empty($matching_categories)) {
                return false;
            }
        }

        $include_posts = get_field('g_snippet_include_posts', $snippet->ID);        
        if (!empty($include_posts)) {
            $include_ids = is_array($include_posts) ? $include_posts : [$include_posts];
            if (!in_array($post_id, $include_ids, true)) {
                return false;
            }
        }

        $exclude_posts = get_field('g_snippet_exclude_posts', $snippet->ID);        
        if (!empty($exclude_posts)) {
            $exclude_ids = is_array($exclude_posts) ? $exclude_posts : [$exclude_posts];
            if (in_array($post_id, $exclude_ids, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sort snippets by priority (lowest number = highest priority = first)
     *
     * @param array $snippets Array of matching snippet posts
     * @return array Sorted array of snippets (lowest priority number first)
     */
    private function sort_snippets_by_priority($snippets)
    {
        if (empty($snippets)) {
            return [];
        }

        if (count($snippets) === 1) {
            return $snippets;
        }

        usort($snippets, function($a, $b) {
            $priority_a = get_field('g_snippet_priority', $a->ID);
            $priority_b = get_field('g_snippet_priority', $b->ID);
            if (empty($priority_a) || !is_numeric($priority_a)) {
                $priority_a = 10;
            } else {
                $priority_a = (int) $priority_a;
            }
            
            if (empty($priority_b) || !is_numeric($priority_b)) {
                $priority_b = 10;
            } else {
                $priority_b = (int) $priority_b;
            }

            // Compare: lower number = higher priority = comes first
            return $priority_a <=> $priority_b;
        });

        return $snippets;
    }

    /**
     * Get snippet content
     *
     * @param int $snippet_id Snippet post ID
     * @return string Snippet content
     */
    private function get_snippet_content($snippet_id)
    {
        $snippet = get_post($snippet_id);
        
        if (!$snippet) {
            return '';
        }

        return apply_filters('g_snippets_snippet_content', $snippet->post_content, $snippet);
    }

    /**
     * Apply space gap to snippets
     *
     * @param array  $snippets Array of snippet HTML strings
     * @param string $space_gap Space gap value (e.g., "20px", "1em")
     * @return array Array of snippet HTML strings with space gap applied
     */
    private function apply_space_gap($snippets, $space_gap)
    {
        if (empty($snippets) || count($snippets) <= 1) {
            return $snippets;
        }

        $result = [];
        $total = count($snippets);
        
        foreach ($snippets as $index => $snippet_html) {
            // Apply margin-bottom to all except the last one
            if ($index < $total - 1) {
                // Add inline style for margin-bottom
                $snippet_html = preg_replace(
                    '/<div id="g-snippet-(\d+)" class="g-snippet g-snippet-\1">/',
                    '<div id="g-snippet-$1" class="g-snippet g-snippet-$1" style="margin-bottom: ' . esc_attr($space_gap) . ';">',
                    $snippet_html
                );
            }
            
            $result[] = $snippet_html;
        }

        return $result;
    }
}
