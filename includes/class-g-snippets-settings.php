<?php

/**
 * G-Snippets Settings
 *
 * @package G_Snippets
 */

namespace G_Snippets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class
 */
class Settings
{
    /**
     * Instance
     *
     * @var Settings
     */
    private static $instance = null;

    /**
     * Option name
     *
     * @var string
     */
    private $option_name = 'g_snippets_settings';

    /**
     * Get instance
     *
     * @return Settings
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
        add_action('admin_menu', [$this, 'add_settings_page'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_head', [$this, 'output_custom_css'], 999);
    }

    /**
     * Add settings page
     */
    public function add_settings_page()
    {
        add_submenu_page(
            'edit.php?post_type=g_snippet',
            __('Settings', 'g-snippets'),
            __('Settings', 'g-snippets'),
            'manage_options',
            'g-snippets-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting(
            'g_snippets_settings',
            $this->option_name,
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => [
                    'display_options' => 'all',
                    'space_gap' => '',
                    'custom_css' => '',
                ],
            ]
        );

        add_settings_section(
            'g_snippets_display_section',
            __('Display Options', 'g-snippets'),
            [$this, 'render_display_section'],
            'g-snippets-settings'
        );

        add_settings_field(
            'display_options',
            __('Display Options', 'g-snippets'),
            [$this, 'render_display_options_field'],
            'g-snippets-settings',
            'g_snippets_display_section'
        );

        add_settings_field(
            'space_gap',
            __('Space Gap', 'g-snippets'),
            [$this, 'render_space_gap_field'],
            'g-snippets-settings',
            'g_snippets_display_section'
        );

        add_settings_section(
            'g_snippets_css_section',
            __('Custom CSS', 'g-snippets'),
            [$this, 'render_css_section'],
            'g-snippets-settings'
        );

        add_settings_field(
            'custom_css',
            __('Custom CSS', 'g-snippets'),
            [$this, 'render_custom_css_field'],
            'g-snippets-settings',
            'g_snippets_css_section'
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input settings
     * @return array Sanitized settings
     */
    public function sanitize_settings($input)
    {
        $sanitized = [];

        // Display options
        if (isset($input['display_options'])) {
            $sanitized['display_options'] = in_array($input['display_options'], ['all', 'first'], true)
                ? $input['display_options']
                : 'all';
        } else {
            $sanitized['display_options'] = 'all';
        }

        // Space gap
        if (isset($input['space_gap'])) {
            $sanitized['space_gap'] = sanitize_text_field($input['space_gap']);
        } else {
            $sanitized['space_gap'] = '';
        }

        // Custom CSS
        if (isset($input['custom_css'])) {
            // Allow CSS but sanitize to prevent XSS
            $sanitized['custom_css'] = wp_strip_all_tags($input['custom_css']);
        } else {
            $sanitized['custom_css'] = '';
        }

        return $sanitized;
    }

    /**
     * Render display section
     */
    public function render_display_section()
    {
        echo '<p>' . esc_html__('Configure how snippets are displayed on your site.', 'g-snippets') . '</p>';
    }

    /**
     * Render CSS section
     */
    public function render_css_section()
    {
        echo '<p>' . esc_html__('Add custom CSS to style your snippets. This CSS will be output in the site header.', 'g-snippets') . '</p>';
    }

    /**
     * Render display options field
     */
    public function render_display_options_field()
    {
        $settings = $this->get_settings();
        $value = $settings['display_options'] ?? 'all';
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[display_options]" id="display_options">
            <option value="all" <?php selected($value, 'all'); ?>>
                <?php esc_html_e('All that matches', 'g-snippets'); ?>
            </option>
            <option value="first" <?php selected($value, 'first'); ?>>
                <?php esc_html_e('First matches by Priority', 'g-snippets'); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e('Select whether to display all matching snippets or only the first one per location (before/after) based on priority.', 'g-snippets'); ?>
        </p>
        <?php
    }

    /**
     * Render space gap field
     */
    public function render_space_gap_field()
    {
        $settings = $this->get_settings();
        $value = $settings['space_gap'] ?? '';
        ?>
        <input 
            type="text" 
            name="<?php echo esc_attr($this->option_name); ?>[space_gap]" 
            id="space_gap" 
            value="<?php echo esc_attr($value); ?>" 
            class="regular-text"
            placeholder="20px"
        />
        <p class="description">
            <?php esc_html_e('Specify the space gap between snippets (e.g., 20px, 1em, 2rem). Leave empty for no gap.', 'g-snippets'); ?>
        </p>
        <?php
    }

    /**
     * Render custom CSS field
     */
    public function render_custom_css_field()
    {
        $settings = $this->get_settings();
        $value = $settings['custom_css'] ?? '';
        ?>
        <textarea 
            name="<?php echo esc_attr($this->option_name); ?>[custom_css]" 
            id="custom_css" 
            rows="15" 
            cols="80" 
            class="large-text code g-snippets-css-editor"
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Enter custom CSS code. This will be output in the site header.', 'g-snippets'); ?>
        </p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'g-snippets'));
        }
        ?>
        <div class="wrap g-snippets-settings-page">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('g_snippets_settings'); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('g_snippets_settings');
                do_settings_sections('g-snippets-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Output custom CSS in wp_head
     */
    public function output_custom_css()
    {
        $settings = $this->get_settings();
        $custom_css = $settings['custom_css'] ?? '';

        if (empty($custom_css)) {
            return;
        }

        echo "\n<style id=\"g-snippets-custom-css\">\n";
        echo esc_html($custom_css);
        echo "\n</style>\n";
    }

    /**
     * Get settings
     *
     * @return array Settings array
     */
    public function get_settings()
    {
        $defaults = [
            'display_options' => 'all',
            'space_gap' => '',
            'custom_css' => '',
        ];

        $settings = get_option($this->option_name, $defaults);
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Get display option
     *
     * @return string 'all' or 'first'
     */
    public function get_display_option()
    {
        $settings = $this->get_settings();
        return $settings['display_options'] ?? 'all';
    }

    /**
     * Get space gap
     *
     * @return string Space gap value
     */
    public function get_space_gap()
    {
        $settings = $this->get_settings();
        return $settings['space_gap'] ?? '';
    }
}
