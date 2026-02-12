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
     * Cache for snippet content
     *
     * @var array
     */
    private $snippet_content_cache = [];

    /**
     * Current post ID
     *
     * @var WP_Post|null
     */
    private $current_post = null;

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

        $content_object = new Content_Object();
        $settings = Settings::get_instance();
        $display_option = $settings->get_display_option();
        $display_by_category_option = $settings->get_display_by_category_option();
        $space_gap = $settings->get_space_gap();

        // Separate snippets by location and sort by priority
        $before_snippets = [];
        $after_snippets = [];
        $categories_attached = [];
        $snippets = [];
        if (!$content_object->get_snippets()) {
            return $content;
        }
        
        foreach ($content_object->get_snippets() as $snippet) {
            $snippet_data = $content_object->get_snippet_data($snippet);
            $location = $snippet_data['location'];
            if (!$location) {
                $location = 'after';
            }

            if ($snippet_data['has_categories'] && $display_by_category_option === 'first') {
                $is_category_attached = array_intersect($categories_attached, $content_object->get_categories());
                if (!empty($is_category_attached)) {
                    continue;
                }
            }
            
            $categories_attached = array_merge($categories_attached, $snippet_data['matching_categories']);
            $snippet_content = sprintf(
                '<div id="g-snippet-%s" class="g-snippet g-snippet-%s">%s</div>', 
                $snippet->ID, 
                $snippet->ID, 
                $this->get_snippet_content($snippet)
            );
            if (empty($snippet_content)) {
                continue;
            }

            if ($location === 'before') {
                $before_snippets[] = $snippet_content;
            } else {
                $after_snippets[] = $snippet_content;
            }

            $snippets[] = $snippet;
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
     * @param WP_Post $snippet Snippet post object
     * @return string Snippet content
     */
    private function get_snippet_content($snippet)
    {
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
