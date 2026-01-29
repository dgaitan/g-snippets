<?php

/**
 * G-Snippets Category Importer
 *
 * @package G_Snippets
 */

namespace G_Snippets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Category Importer class
 */
class Category_Importer
{
    /**
     * Instance
     *
     * @var Category_Importer
     */
    private static $instance = null;

    /**
     * Transient key prefix
     *
     * @var string
     */
    private $transient_prefix = 'g_snippets_import_';

    /**
     * Get instance
     *
     * @return Category_Importer
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
        add_action('admin_menu', [$this, 'add_admin_page'], 25);
        add_action('admin_init', [$this, 'handle_form_submissions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Add admin page
     */
    public function add_admin_page()
    {
        add_submenu_page(
            'edit.php?post_type=g_snippet',
            __('Post Category Assignment', 'g-snippets'),
            __('Post Category Assignment', 'g-snippets'),
            'manage_options',
            'g-snippets-category-importer',
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook)
    {
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

    /**
     * Handle form submissions
     */
    public function handle_form_submissions()
    {
        if (!isset($_POST['g_snippets_importer_action'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'g-snippets'));
        }

        check_admin_referer('g_snippets_importer_action');

        $action = sanitize_text_field($_POST['g_snippets_importer_action']);

        switch ($action) {
            case 'upload':
                $this->handle_file_upload();
                break;
            case 'import':
                $this->handle_import();
                break;
        }
    }

    /**
     * Handle file upload
     */
    private function handle_file_upload()
    {
        if (empty($_FILES['import_file']['name'])) {
            add_settings_error(
                'g_snippets_importer',
                'no_file',
                __('Please select a file to upload.', 'g-snippets'),
                'error'
            );
            return;
        }

        $file = $_FILES['import_file'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validate file type
        if (!in_array($file_ext, ['csv', 'xlsx'], true)) {
            add_settings_error(
                'g_snippets_importer',
                'invalid_file',
                __('Invalid file type. Please upload a CSV or Excel (.xlsx) file.', 'g-snippets'),
                'error'
            );
            return;
        }

        // Handle upload
        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            add_settings_error(
                'g_snippets_importer',
                'upload_error',
                sprintf(__('Error uploading file: %s', 'g-snippets'), $upload['error']),
                'error'
            );
            return;
        }

        // Parse file
        $parsed_data = $this->parse_file($upload['file'], $file_ext);

        if (is_wp_error($parsed_data)) {
            add_settings_error(
                'g_snippets_importer',
                'parse_error',
                sprintf(__('Error parsing file: %s', 'g-snippets'), $parsed_data->get_error_message()),
                'error'
            );
            return;
        }

        if (empty($parsed_data['headers']) || empty($parsed_data['rows'])) {
            add_settings_error(
                'g_snippets_importer',
                'empty_file',
                __('The file appears to be empty or invalid.', 'g-snippets'),
                'error'
            );
            return;
        }

        // Store in transient
        $transient_key = $this->transient_prefix . wp_generate_password(12, false);
        set_transient($transient_key, $parsed_data, HOUR_IN_SECONDS);

        // Clean up uploaded file
        @unlink($upload['file']);

        // Redirect to preview page
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'g-snippets-category-importer',
                    'step' => 'preview',
                    'data' => $transient_key,
                ],
                admin_url('edit.php?post_type=g_snippet')
            )
        );
        exit;
    }

    /**
     * Parse file
     *
     * @param string $file_path File path
     * @param string $file_ext File extension
     * @return array|WP_Error Parsed data or error
     */
    private function parse_file($file_path, $file_ext)
    {
        if ($file_ext === 'csv') {
            return $this->parse_csv($file_path);
        } elseif ($file_ext === 'xlsx') {
            return $this->parse_excel($file_path);
        }

        return new \WP_Error('invalid_format', __('Unsupported file format.', 'g-snippets'));
    }

    /**
     * Parse CSV file
     *
     * @param string $file_path File path
     * @return array|WP_Error Parsed data or error
     */
    private function parse_csv($file_path)
    {
        $headers = [];
        $rows = [];

        if (($handle = fopen($file_path, 'r')) === false) {
            return new \WP_Error('file_open_error', __('Could not open file for reading.', 'g-snippets'));
        }

        // Read headers
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return new \WP_Error('empty_file', __('File is empty or invalid.', 'g-snippets'));
        }

        // Clean headers
        $headers = array_map('trim', $headers);

        // Read rows
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = array_map('trim', $row);
            }
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Parse Excel file
     *
     * @param string $file_path File path
     * @return array|WP_Error Parsed data or error
     */
    private function parse_excel($file_path)
    {
        // Check if PhpSpreadsheet is available
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            // Try to load from common locations
            $possible_paths = [
                ABSPATH . 'wp-content/plugins/phpspreadsheet/vendor/autoload.php',
                ABSPATH . 'vendor/autoload.php',
            ];

            $loaded = false;
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    $loaded = true;
                    break;
                }
            }

            if (!$loaded) {
                return new \WP_Error(
                    'phpspreadsheet_missing',
                    __('PhpSpreadsheet library is required for Excel files. Please install it or use CSV format instead.', 'g-snippets')
                );
            }
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            if (empty($data)) {
                return new \WP_Error('empty_file', __('File is empty or invalid.', 'g-snippets'));
            }

            // First row is headers
            $headers = array_map('trim', array_shift($data));

            // Remaining rows are data
            $rows = [];
            foreach ($data as $row) {
                if (count($row) === count($headers)) {
                    $rows[] = array_map('trim', $row);
                }
            }

            return [
                'headers' => $headers,
                'rows' => $rows,
            ];
        } catch (\Exception $e) {
            return new \WP_Error('parse_error', sprintf(__('Error parsing Excel file: %s', 'g-snippets'), $e->getMessage()));
        }
    }

    /**
     * Handle import process
     */
    private function handle_import()
    {
        $transient_key = isset($_POST['data_key']) ? sanitize_text_field($_POST['data_key']) : '';
        $slug_column = isset($_POST['slug_column']) ? intval($_POST['slug_column']) : -1;
        $category_column = isset($_POST['category_column']) ? intval($_POST['category_column']) : -1;

        if (empty($transient_key) || $slug_column < 0 || $category_column < 0) {
            add_settings_error(
                'g_snippets_importer',
                'missing_data',
                __('Missing required data. Please try uploading the file again.', 'g-snippets'),
                'error'
            );
            return;
        }

        $parsed_data = get_transient($transient_key);

        if (false === $parsed_data) {
            add_settings_error(
                'g_snippets_importer',
                'expired_data',
                __('Import data has expired. Please upload the file again.', 'g-snippets'),
                'error'
            );
            return;
        }

        $results = $this->process_import($parsed_data, $slug_column, $category_column);

        // Delete transient
        delete_transient($transient_key);

        // Store results in transient for display
        $results_key = $this->transient_prefix . 'results_' . wp_generate_password(12, false);
        set_transient($results_key, $results, HOUR_IN_SECONDS);

        // Redirect to results page
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'g-snippets-category-importer',
                    'step' => 'results',
                    'results' => $results_key,
                ],
                admin_url('edit.php?post_type=g_snippet')
            )
        );
        exit;
    }

    /**
     * Process import
     *
     * @param array $parsed_data Parsed file data
     * @param int   $slug_column Column index for post slug
     * @param int   $category_column Column index for category name
     * @return array Import results
     */
    private function process_import($parsed_data, $slug_column, $category_column)
    {
        $results = [
            'success' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $headers = $parsed_data['headers'];
        $rows = $parsed_data['rows'];

        // Validate column indices
        if ($slug_column >= count($headers) || $category_column >= count($headers)) {
            $results['errors'][] = __('Invalid column selection.', 'g-snippets');
            return $results;
        }

        foreach ($rows as $row_index => $row) {
            if (empty($row[$slug_column]) || empty($row[$category_column])) {
                $results['skipped']++;
                continue;
            }

            $post_slug = sanitize_text_field($row[$slug_column]);
            $category_name = sanitize_text_field($row[$category_column]);

            // Find post by slug (post_name)
            $posts = get_posts([
                'name' => $post_slug,
                'post_type' => 'post',
                'post_status' => 'any',
                'numberposts' => 1,
            ]);

            if (empty($posts)) {
                $results['skipped']++;
                continue;
            }

            $post = $posts[0];

            // Get or create category
            $category = $this->get_or_create_category($category_name);

            if (is_wp_error($category)) {
                $results['errors'][] = sprintf(
                    __('Row %d: %s', 'g-snippets'),
                    $row_index + 2,
                    $category->get_error_message()
                );
                continue;
            }

            // Add category to post (add mode, not replace)
            $post_categories = wp_get_post_categories($post->ID);
            if (!in_array($category->term_id, $post_categories, true)) {
                $post_categories[] = $category->term_id;
                wp_set_post_categories($post->ID, $post_categories, false);
                $results['success']++;
            } else {
                // Category already assigned, count as success
                $results['success']++;
            }
        }

        return $results;
    }

    /**
     * Get or create category
     *
     * @param string $category_name Category name
     * @return WP_Term|WP_Error Category term or error
     */
    private function get_or_create_category($category_name)
    {
        // Check if category exists by name
        $category = get_term_by('name', $category_name, 'category');

        if ($category) {
            return $category;
        }

        // Check if category exists by slug
        $category_slug = sanitize_title($category_name);
        $category = get_term_by('slug', $category_slug, 'category');

        if ($category) {
            return $category;
        }

        // Create new category
        $result = wp_create_category($category_name);

        if (is_wp_error($result)) {
            return $result;
        }

        return get_term($result, 'category');
    }

    /**
     * Render admin page
     */
    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'g-snippets'));
        }

        $step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'upload';

        ?>
        <div class="wrap g-snippets-importer-page">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('g_snippets_importer'); ?>

            <?php
            switch ($step) {
                case 'preview':
                    $this->render_preview_step();
                    break;
                case 'results':
                    $this->render_results_step();
                    break;
                case 'upload':
                default:
                    $this->render_upload_step();
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render upload step
     */
    private function render_upload_step()
    {
        ?>
        <div class="g-snippets-importer-upload">
            <h2><?php esc_html_e('Upload File', 'g-snippets'); ?></h2>
            <p><?php esc_html_e('Upload a CSV or Excel (.xlsx) file containing post slugs and category names.', 'g-snippets'); ?></p>

            <form method="post" enctype="multipart/form-data" action="">
                <?php wp_nonce_field('g_snippets_importer_action'); ?>
                <input type="hidden" name="g_snippets_importer_action" value="upload" />

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="import_file"><?php esc_html_e('Select File', 'g-snippets'); ?></label>
                        </th>
                        <td>
                            <input 
                                type="file" 
                                name="import_file" 
                                id="import_file" 
                                accept=".csv,.xlsx"
                                required
                            />
                            <p class="description">
                                <?php esc_html_e('Accepted formats: CSV, Excel (.xlsx)', 'g-snippets'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Upload and Preview', 'g-snippets')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render preview step
     */
    private function render_preview_step()
    {
        $transient_key = isset($_GET['data']) ? sanitize_text_field($_GET['data']) : '';

        if (empty($transient_key)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Missing data. Please upload the file again.', 'g-snippets') . '</p></div>';
            echo '<p><a href="' . esc_url(admin_url('edit.php?post_type=g_snippet&page=g-snippets-category-importer')) . '" class="button">' . esc_html__('Go Back', 'g-snippets') . '</a></p>';
            return;
        }

        $parsed_data = get_transient($transient_key);

        if (false === $parsed_data) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Import data has expired. Please upload the file again.', 'g-snippets') . '</p></div>';
            echo '<p><a href="' . esc_url(admin_url('edit.php?post_type=g_snippet&page=g-snippets-category-importer')) . '" class="button">' . esc_html__('Go Back', 'g-snippets') . '</a></p>';
            return;
        }

        $headers = $parsed_data['headers'];
        $rows = $parsed_data['rows'];
        $preview_rows = array_slice($rows, 0, 50);

        ?>
        <div class="g-snippets-importer-preview">
            <h2><?php esc_html_e('Preview Data', 'g-snippets'); ?></h2>
            <p>
                <?php
                printf(
                    esc_html__('Found %d rows. Showing first %d rows for preview.', 'g-snippets'),
                    count($rows),
                    count($preview_rows)
                );
                ?>
            </p>

            <div class="g-snippets-preview-table-wrapper">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <?php foreach ($headers as $index => $header) : ?>
                                <th><?php echo esc_html($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_rows as $row) : ?>
                            <tr>
                                <?php foreach ($row as $cell) : ?>
                                    <td><?php echo esc_html($cell); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h2><?php esc_html_e('Map Columns', 'g-snippets'); ?></h2>
            <p><?php esc_html_e('Select which column contains the post slug and which contains the category name.', 'g-snippets'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('g_snippets_importer_action'); ?>
                <input type="hidden" name="g_snippets_importer_action" value="import" />
                <input type="hidden" name="data_key" value="<?php echo esc_attr($transient_key); ?>" />

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="slug_column"><?php esc_html_e('Post Slug Column', 'g-snippets'); ?></label>
                        </th>
                        <td>
                            <select name="slug_column" id="slug_column" required>
                                <option value=""><?php esc_html_e('-- Select Column --', 'g-snippets'); ?></option>
                                <?php foreach ($headers as $index => $header) : ?>
                                    <option value="<?php echo esc_attr($index); ?>">
                                        <?php echo esc_html($header); ?> (Column <?php echo esc_html($index + 1); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="category_column"><?php esc_html_e('Category Name Column', 'g-snippets'); ?></label>
                        </th>
                        <td>
                            <select name="category_column" id="category_column" required>
                                <option value=""><?php esc_html_e('-- Select Column --', 'g-snippets'); ?></option>
                                <?php foreach ($headers as $index => $header) : ?>
                                    <option value="<?php echo esc_attr($index); ?>">
                                        <?php echo esc_html($header); ?> (Column <?php echo esc_html($index + 1); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <?php submit_button(__('Import Categories', 'g-snippets'), 'primary', 'submit', false); ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=g_snippet&page=g-snippets-category-importer')); ?>" class="button">
                        <?php esc_html_e('Cancel', 'g-snippets'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render results step
     */
    private function render_results_step()
    {
        $results_key = isset($_GET['results']) ? sanitize_text_field($_GET['results']) : '';

        if (empty($results_key)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Missing results data.', 'g-snippets') . '</p></div>';
            echo '<p><a href="' . esc_url(admin_url('edit.php?post_type=g_snippet&page=g-snippets-category-importer')) . '" class="button">' . esc_html__('Go Back', 'g-snippets') . '</a></p>';
            return;
        }

        $results = get_transient($results_key);

        if (false === $results) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Results data has expired.', 'g-snippets') . '</p></div>';
            echo '<p><a href="' . esc_url(admin_url('edit.php?post_type=g_snippet&page=g-snippets-category-importer')) . '" class="button">' . esc_html__('Go Back', 'g-snippets') . '</a></p>';
            return;
        }

        ?>
        <div class="g-snippets-importer-results">
            <h2><?php esc_html_e('Import Results', 'g-snippets'); ?></h2>

            <div class="notice notice-success">
                <p>
                    <strong><?php esc_html_e('Import completed!', 'g-snippets'); ?></strong>
                </p>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Successfully Assigned', 'g-snippets'); ?></th>
                    <td>
                        <strong style="color: #46b450;"><?php echo esc_html($results['success']); ?></strong>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Skipped', 'g-snippets'); ?></th>
                    <td>
                        <strong><?php echo esc_html($results['skipped']); ?></strong>
                        <p class="description">
                            <?php esc_html_e('Posts that were skipped (post slug not found or empty values).', 'g-snippets'); ?>
                        </p>
                    </td>
                </tr>
                <?php if (!empty($results['errors'])) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Errors', 'g-snippets'); ?></th>
                        <td>
                            <ul style="list-style: disc; margin-left: 20px;">
                                <?php foreach ($results['errors'] as $error) : ?>
                                    <li style="color: #dc3232;"><?php echo esc_html($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>

            <p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=g_snippet&page=g-snippets-category-importer')); ?>" class="button button-primary">
                    <?php esc_html_e('Import Another File', 'g-snippets'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
