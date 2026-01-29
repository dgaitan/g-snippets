<?php

/**
 * Plugin Name: G-Snippets
 * Plugin URI: https://github.com/dgaitan
 * Description: Add reusable content snippets to posts and pages using Gutenberg editor. Manage snippets with priority-based matching and flexible display rules.
 * Version: 1.0.0
 * Author: David Gaitan
 * License: GPL v2 or later
 * Text Domain: g-snippets
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('G_SNIPPETS_VERSION', '1.0.0');
define('G_SNIPPETS_PLUGIN_FILE', __FILE__);
define('G_SNIPPETS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('G_SNIPPETS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if ACF is active
 *
 * @return bool
 */
function g_snippets_check_acf_dependency() {
    // Check for ACF Pro constant
    if (defined('ACF_VERSION')) {
        return true;
    }
    
    // Check for ACF functions (works for both free and pro)
    if (function_exists('acf_get_setting') || function_exists('get_field') || function_exists('acf')) {
        return true;
    }
    
    // Check for ACF classes
    if (class_exists('ACF') || class_exists('acf')) {
        return true;
    }
    
    // Check if plugin is active by checking if ACF plugin file exists
    if (function_exists('is_plugin_active')) {
        // Check for ACF Pro
        if (is_plugin_active('advanced-custom-fields-pro/acf.php')) {
            return true;
        }
        // Check for ACF Free
        if (is_plugin_active('advanced-custom-fields/acf.php')) {
            return true;
        }
    }
    
    return false;
}

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function () {
    // Load plugin.php if not already loaded (needed for is_plugin_active)
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    // Check if ACF is installed and active
    if (!g_snippets_check_acf_dependency()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('G-Snippets requires Advanced Custom Fields (ACF) plugin to be installed and activated. Please install ACF first, then try activating G-Snippets again.', 'g-snippets'),
            __('Plugin Activation Error', 'g-snippets'),
            ['back_link' => true]
        );
    }

    // Flush rewrite rules to register custom post type
    flush_rewrite_rules();
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, function () {
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();
});

/**
 * Plugin uninstall hook
 */
register_uninstall_hook(__FILE__, 'g_snippets_uninstall');

function g_snippets_uninstall() {
    // Optionally remove plugin data on uninstall
    // For now, we'll leave snippet posts intact
}

/**
 * Initialize the plugin
 */
function g_snippets_init() {
    // Check ACF dependency before initializing
    if (!g_snippets_check_acf_dependency()) {
        add_action('admin_notices', function () {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    printf(
                        __('G-Snippets requires Advanced Custom Fields (ACF) plugin to be installed and activated. Please <a href="%s">install ACF</a> to use G-Snippets.', 'g-snippets'),
                        admin_url('plugin-install.php?s=advanced-custom-fields&tab=search&type=term')
                    );
                    ?>
                </p>
            </div>
            <?php
        });
        return;
    }

    // Load the main plugin class
    require_once G_SNIPPETS_PLUGIN_DIR . 'includes/class-g-snippets.php';

    return G_Snippets\G_Snippets::get_instance();
}

// Initialize the plugin
add_action('plugins_loaded', 'g_snippets_init');
