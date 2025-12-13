<?php
/**
 * Plugin Name:       Live Asset Repository
 * Description:       Manages a live, self-updating repository of installed themes and plugins by re-zipping them on update.
 * Version:           1.0.0
 * Author:            Your Name
 */

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// ** IMPORTANT **: Define the absolute path to your secure repository directory.
// Update this path to match the directory you created in Step 1.
define('LAR_SECURE_REPO_PATH', '/home/youruser/wp_asset_repository'); // <-- EDIT THIS LINE

// Optional: Define a webhook URL for Pabbly or Bit Integrations
define('LAR_WEBHOOK_URL', ''); // <-- EDIT THIS LINE (e.g., 'https://connect.pabbly.com/api/v1/workflows/...')

/**
 * Zips a directory.
 *
 * @param string $source      The directory to zip.
 * @param string $destination The path for the output zip file.
 * @return bool True on success, false on failure.
 */
function lar_zip_directory($source, $destination) {
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    // Ensure the destination directory exists
    $destination_dir = dirname($destination);
    if (!is_dir($destination_dir)) {
        mkdir($destination_dir, 0755, true);
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        return false;
    }

    $source = realpath($source);
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($source) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }

    return $zip->close();
}

/**
 * Hook into the `upgrader_process_complete` action, which fires after any update.
 */
add_action('upgrader_process_complete', 'lar_handle_update', 10, 2);

function lar_handle_update($upgrader_object, $options) {
    $type = $options['type'] ?? '';
    $action = $options['action'] ?? '';

    if ($action !== 'update' || !in_array($type, ['plugin', 'theme'])) {
        return;
    }

    $items_to_zip = [];

    if ($type === 'plugin' && !empty($options['plugins'])) {
        foreach ($options['plugins'] as $plugin_file) {
            $slug = dirname($plugin_file);
            $items_to_zip[] = ['type' => 'plugin', 'slug' => $slug];
        }
    } elseif ($type === 'theme' && !empty($options['themes'])) {
        foreach ($options['themes'] as $theme_slug) {
            $items_to_zip[] = ['type' => 'theme', 'slug' => $theme_slug];
        }
    }

    // Process each updated item
    foreach ($items_to_zip as $item) {
        $source_path = WP_CONTENT_DIR . '/' . $item['type'] . 's/' . $item['slug'];
        $destination_zip = LAR_SECURE_REPO_PATH . '/' . $item['slug'] . '.zip';

        if (file_exists($source_path)) {
            $success = lar_zip_directory($source_path, $destination_zip);

            // Optional: Send a notification via webhook
            if ($success && defined('LAR_WEBHOOK_URL') && !empty(LAR_WEBHOOK_URL)) {
                wp_remote_post(LAR_WEBHOOK_URL, [
                    'body' => json_encode([
                        'site'    => get_bloginfo('url'),
                        'type'    => $item['type'],
                        'slug'    => $item['slug'],
                        'status'  => 'Updated and Re-zipped Successfully',
                        'zipfile' => basename($destination_zip),
                        'time'    => current_time('mysql'),
                    ]),
                    'headers' => ['Content-Type' => 'application/json'],
                ]);
            }
        }
    }
}


/**
 * Add a management page to the Tools menu for initial zipping.
 */
add_action('admin_menu', 'lar_add_admin_menu');
function lar_add_admin_menu() {
    add_management_page(
        'Live Asset Repository',
        'Asset Repository',
        'manage_options',
        'lar-repo',
        'lar_admin_page_content'
    );
}

/**
 * Content for the admin management page.
 */
function lar_admin_page_content() {
    ?>
    <div class="wrap">
        <h2>Live Asset Repository Management</h2>
        <p>This tool allows you to create an initial zip of all currently installed themes and plugins.</p>
        <p>The repository is located at: <code><?php echo esc_html(LAR_SECURE_REPO_PATH); ?></code></p>
        <?php
        if (isset($_POST['lar_run_initial_zip'])) {
            check_admin_referer('lar_initial_zip_nonce');
            lar_run_initial_zip_process();
        }
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('lar_initial_zip_nonce'); ?>
            <p>Click the button below to zip all themes and plugins. This may take a while and could time out on servers with strict execution limits. If it times out, simply run it again.</p>
            <input type="submit" name="lar_run_initial_zip" class="button button-primary" value="Zip All Themes & Plugins Now">
        </form>
    </div>
    <?php
}

/**
 * The process to zip all themes and plugins.
 */
function lar_run_initial_zip_process() {
    echo '<h3>Processing...</h3><div style="background:#fff; border:1px solid #ccc; padding:10px; max-height: 400px; overflow-y:scroll;">';

    // Zip Plugins
    $plugins_dir = WP_PLUGIN_DIR;
    $plugins = array_filter(scandir($plugins_dir), function ($item) use ($plugins_dir) {
        return is_dir($plugins_dir . '/' . $item) && !in_array($item, ['.', '..']);
    });

    echo '<h4>Zipping Plugins:</h4><ul>';
    foreach ($plugins as $plugin_slug) {
        $source_path = $plugins_dir . '/' . $plugin_slug;
        $destination_zip = LAR_SECURE_REPO_PATH . '/' . $plugin_slug . '.zip';
        if (lar_zip_directory($source_path, $destination_zip)) {
            echo '<li><strong>SUCCESS:</strong> Zipped ' . esc_html($plugin_slug) . '</li>';
        } else {
            echo '<li><strong>FAILURE:</strong> Could not zip ' . esc_html($plugin_slug) . '</li>';
        }
        ob_flush(); flush();
    }
    echo '</ul>';

    // Zip Themes
    $themes_dir = get_theme_root();
    $themes = array_filter(scandir($themes_dir), function ($item) use ($themes_dir) {
        return is_dir($themes_dir . '/' . $item) && !in_array($item, ['.', '..']);
    });
    
    echo '<h4>Zipping Themes:</h4><ul>';
    foreach ($themes as $theme_slug) {
        $source_path = $themes_dir . '/' . $theme_slug;
        $destination_zip = LAR_SECURE_REPO_PATH . '/' . $theme_slug . '.zip';
        if (lar_zip_directory($source_path, $destination_zip)) {
            echo '<li><strong>SUCCESS:</strong> Zipped ' . esc_html($theme_slug) . '</li>';
        } else {
            echo '<li><strong>FAILURE:</strong> Could not zip ' . esc_html($theme_slug) . '</li>';
        }
        ob_flush(); flush();
    }
    echo '</ul>';

    echo '</div><h3>Initial zipping process complete!</h3>';
}

/**
 * Setup rewrite rules for the sanitized download URL.
 */
add_action('init', function() {
    add_rewrite_rule('^download/?$', 'index.php?lar_download=1', 'top');
});

add_filter('query_vars', function($vars) {
    $vars[] = 'lar_download';
    return $vars;
});

/**
 * Handle the download request.
 */
add_action('template_redirect', function() {
    if (get_query_var('lar_download')) {
        $type = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
        $slug = isset($_GET['slug']) ? sanitize_file_name($_GET['slug']) : '';

        // Basic validation
        if (empty($type) || empty($slug) || !in_array($type, ['plugin', 'theme'])) {
            wp_die('Invalid anameeters.', 'Invalid Request', ['response' => 400]);
        }

        $file_path = LAR_SECURE_REPO_PATH . '/' . $slug . '.zip';

        if (file_exists($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $slug . '.zip"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            ob_clean();
            flush();
            readfile($file_path);
            exit;
        } else {
            // If file not found, show a 404
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part('404');
            exit;
        }
    }
});
