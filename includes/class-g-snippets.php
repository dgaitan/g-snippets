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
        require_once G_SNIPPETS_PLUGIN_DIR . 'includes/class-g-snippets-settings.php';
        require_once G_SNIPPETS_PLUGIN_DIR . 'includes/class-g-snippets-category-importer.php';
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
        
        // Initialize settings
        Settings::get_instance();
        
        // Initialize category importer
        Category_Importer::get_instance();
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook)
    {
        $screen = get_current_screen();
        
        // Load on snippet post type pages
        if ($screen && $screen->post_type === 'g_snippet') {
            wp_enqueue_style(
                'g-snippets-admin',
                G_SNIPPETS_PLUGIN_URL . 'admin/css/admin.css',
                [],
                G_SNIPPETS_VERSION
            );
        }
        
        // Load on settings page (check hook or GET parameter)
        if ($hook === 'g_snippet_page_g-snippets-settings' || 
            (isset($_GET['page']) && $_GET['page'] === 'g-snippets-settings')) {
            wp_enqueue_style(
                'g-snippets-admin',
                G_SNIPPETS_PLUGIN_URL . 'admin/css/admin.css',
                [],
                G_SNIPPETS_VERSION
            );
        }
        
        // Load on category importer page
        if ($hook === 'g_snippet_page_g-snippets-category-importer' || 
            (isset($_GET['page']) && $_GET['page'] === 'g-snippets-category-importer')) {
            wp_enqueue_style(
                'g-snippets-admin',
                G_SNIPPETS_PLUGIN_URL . 'admin/css/admin.css',
                [],
                G_SNIPPETS_VERSION
            );
        }
    }
}
