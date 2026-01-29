<?php

/**
 * G-Snippets Main Plugin Class
 *
 * @package G_Snippets
 */

namespace G_Snippets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class G_Snippets
{
    /**
     * Plugin instance
     *
     * @var G_Snippets
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return G_Snippets
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies()
    {
        require_once G_SNIPPETS_PLUGIN_DIR . 'includes/class-g-snippets-post-type.php';
        require_once G_SNIPPETS_PLUGIN_DIR . 'includes/class-g-snippets-acf.php';
        require_once G_SNIPPETS_PLUGIN_DIR . 'includes/class-g-snippets-content-injector.php';
        require_once G_SNIPPETS_PLUGIN_DIR . 'includes/class-g-snippets-list-table.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Initialize components early
        add_action('init', [$this, 'init_components'], 5);
        
        // Enqueue admin styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Initialize plugin components
     */
    public function init_components()
    {
        // Register custom post type
        Post_Type::get_instance();
        
        // Register ACF fields
        ACF_Fields::get_instance();
        
        // Initialize content injector
        Content_Injector::get_instance();
        
        // Customize list table
        List_Table::get_instance();
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only load on our admin pages
        $allowed_hooks = [
            'edit.php',
            'post.php',
            'post-new.php'
        ];

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'g_snippet') {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'g-snippets-admin',
            G_SNIPPETS_PLUGIN_URL . 'admin/css/admin.css',
            [],
            G_SNIPPETS_VERSION
        );
    }
}
