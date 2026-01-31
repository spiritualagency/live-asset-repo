<?php
/**
 * Plugin Name:       Permanent Plugin Theme Downloader
 * Description:       Generate permanent, shareable download URLs for installed plugins and themes that automatically serve the latest versions.
 * Version:           0.1.0
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Author:            WordPress Telex
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       permanent-plugin-theme-downloader
 *
 * @package PermanentPluginThemeDownloader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PPTD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPTD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PPTD_DOWNLOAD_DIR', WP_CONTENT_DIR . '/pptd-downloads' );
define( 'PPTD_DOWNLOAD_URL', content_url( 'pptd-downloads' ) );
define( 'PPTD_LOG_FILE', PPTD_DOWNLOAD_DIR . '/update-log.json' );
define( 'PPTD_SECRET_KEY_OPTION', 'pptd_secret_key' );

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 */
function permanent_plugin_theme_downloader_block_init() {
	if ( file_exists( PPTD_PLUGIN_DIR . 'build/block.json' ) ) {
		register_block_type( PPTD_PLUGIN_DIR . 'build/' );
	}
}
add_action( 'init', 'permanent_plugin_theme_downloader_block_init' );

/**
 * Get or generate the secret key for token generation.
 */
function permanent_plugin_theme_downloader_get_secret_key() {
	$secret_key = get_option( PPTD_SECRET_KEY_OPTION );
	
	if ( empty( $secret_key ) ) {
		$secret_key = wp_generate_password( 64, true, true );
		update_option( PPTD_SECRET_KEY_OPTION, $secret_key );
	}
	
	return $secret_key;
}

/**
 * Generate a secure token for a specific file.
 */
function permanent_plugin_theme_downloader_generate_token( $filename ) {
	$secret_key = permanent_plugin_theme_downloader_get_secret_key();
	$data = $filename . '|' . site_url();
	return hash_hmac( 'sha256', $data, $secret_key );
}

/**
 * Verify a token for a specific file.
 */
function permanent_plugin_theme_downloader_verify_token( $filename, $token ) {
	$expected_token = permanent_plugin_theme_downloader_generate_token( $filename );
	return hash_equals( $expected_token, $token );
}

/**
 * Get the secure download URL with token.
 */
function permanent_plugin_theme_downloader_get_secure_url( $filename ) {
	$token = permanent_plugin_theme_downloader_generate_token( $filename );
	return add_query_arg(
		array(
			'pptd_download' => $filename,
			'token' => $token,
		),
		home_url( '/' )
	);
}

/**
 * Handle download requests with token authentication.
 */
function permanent_plugin_theme_downloader_handle_download() {
	if ( ! isset( $_GET['pptd_download'] ) || ! isset( $_GET['token'] ) ) {
		return;
	}
	
	$filename = sanitize_file_name( $_GET['pptd_download'] );
	$token = sanitize_text_field( $_GET['token'] );
	
	if ( ! permanent_plugin_theme_downloader_verify_token( $filename, $token ) ) {
		wp_die( esc_html__( 'Invalid download token.', 'permanent-plugin-theme-downloader' ), 403 );
	}
	
	$file_path = PPTD_DOWNLOAD_DIR . '/' . $filename;
	
	if ( ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
		wp_die( esc_html__( 'File not found.', 'permanent-plugin-theme-downloader' ), 404 );
	}
	
	if ( strpos( realpath( $file_path ), realpath( PPTD_DOWNLOAD_DIR ) ) !== 0 ) {
		wp_die( esc_html__( 'Invalid file path.', 'permanent-plugin-theme-downloader' ), 403 );
	}
	
	if ( substr( $filename, -4 ) !== '.zip' ) {
		wp_die( esc_html__( 'Invalid file type.', 'permanent-plugin-theme-downloader' ), 403 );
	}
	
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '"' );
	header( 'Content-Length: ' . filesize( $file_path ) );
	header( 'Cache-Control: no-cache, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	
	readfile( $file_path );
	exit;
}
add_action( 'init', 'permanent_plugin_theme_downloader_handle_download', 1 );

/**
 * Create the downloads directory if it doesn't exist.
 */
function permanent_plugin_theme_downloader_ensure_directory() {
	if ( ! file_exists( PPTD_DOWNLOAD_DIR ) ) {
		wp_mkdir_p( PPTD_DOWNLOAD_DIR );
		
		$htaccess_content = "<IfModule mod_rewrite.c>\nRewriteEngine Off\n</IfModule>\n";
		file_put_contents( PPTD_DOWNLOAD_DIR . '/.htaccess', $htaccess_content );
		
		file_put_contents( PPTD_DOWNLOAD_DIR . '/index.php', '<?php // Silence is golden' );
	}
	
	if ( ! file_exists( PPTD_LOG_FILE ) ) {
		file_put_contents( PPTD_LOG_FILE, json_encode( array() ) );
	}
}

/**
 * Initialize plugin on activation.
 */
function permanent_plugin_theme_downloader_activate() {
	permanent_plugin_theme_downloader_ensure_directory();
	
	permanent_plugin_theme_downloader_get_secret_key();
	
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	
	$all_plugins = get_plugins();
	foreach ( $all_plugins as $plugin_file => $plugin_data ) {
		permanent_plugin_theme_downloader_create_plugin_zip( $plugin_file, false );
	}
	
	$all_themes = wp_get_themes();
	foreach ( $all_themes as $theme_slug => $theme_obj ) {
		permanent_plugin_theme_downloader_create_theme_zip( $theme_slug, false );
	}
}
register_activation_hook( __FILE__, 'permanent_plugin_theme_downloader_activate' );

/**
 * Log an update event.
 */
function permanent_plugin_theme_downloader_log_update( $type, $slug, $name, $old_version, $new_version ) {
	permanent_plugin_theme_downloader_ensure_directory();
	
	$log = array();
	if ( file_exists( PPTD_LOG_FILE ) ) {
		$log_content = file_get_contents( PPTD_LOG_FILE );
		$log = json_decode( $log_content, true );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
	}
	
	$log[] = array(
		'type' => $type,
		'slug' => $slug,
		'name' => $name,
		'old_version' => $old_version,
		'new_version' => $new_version,
		'timestamp' => current_time( 'mysql' ),
		'unix_timestamp' => time(),
	);
	
	file_put_contents( PPTD_LOG_FILE, json_encode( $log, JSON_PRETTY_PRINT ) );
}

/**
 * Get the update log.
 */
function permanent_plugin_theme_downloader_get_log() {
	if ( ! file_exists( PPTD_LOG_FILE ) ) {
		return array();
	}
	
	$log_content = file_get_contents( PPTD_LOG_FILE );
	$log = json_decode( $log_content, true );
	
	if ( ! is_array( $log ) ) {
		return array();
	}
	
	usort( $log, function( $a, $b ) {
		return $b['unix_timestamp'] - $a['unix_timestamp'];
	});
	
	return $log;
}

/**
 * Create or update a ZIP file for a plugin.
 */
function permanent_plugin_theme_downloader_create_plugin_zip( $plugin_file, $log_update = false ) {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all_plugins = get_plugins();
	if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
		return false;
	}

	$plugin_data = $all_plugins[ $plugin_file ];
	$slug = dirname( $plugin_file );
	if ( $slug === '.' ) {
		$slug = basename( $plugin_file, '.php' );
	}

	$plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
	if ( dirname( $plugin_file ) === '.' ) {
		$plugin_dir = WP_PLUGIN_DIR;
		$source_path = WP_PLUGIN_DIR . '/' . $plugin_file;
	} else {
		$source_path = $plugin_dir;
	}

	permanent_plugin_theme_downloader_ensure_directory();

	$sanitized_slug = sanitize_file_name( $slug );
	$zip_filename = 'plugin-' . $sanitized_slug . '.zip';
	$zip_path = PPTD_DOWNLOAD_DIR . '/' . $zip_filename;

	$old_version = null;
	if ( file_exists( $zip_path ) && $log_update ) {
		$old_version = get_option( 'pptd_plugin_version_' . $sanitized_slug );
	}

	if ( file_exists( $zip_path ) ) {
		unlink( $zip_path );
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		return false;
	}

	$zip = new ZipArchive();
	if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
		return false;
	}

	if ( is_file( $source_path ) ) {
		$zip->addFile( $source_path, basename( $source_path ) );
	} else {
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_path, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		$base_name = basename( $source_path );

		foreach ( $files as $file ) {
			$file_path = $file->getRealPath();
			$relative_path = $base_name . '/' . substr( $file_path, strlen( $source_path ) + 1 );

			if ( $file->isDir() ) {
				$zip->addEmptyDir( $relative_path );
			} else {
				$zip->addFile( $file_path, $relative_path );
			}
		}
	}

	$zip->close();

	if ( $log_update && $old_version && $old_version !== $plugin_data['Version'] ) {
		permanent_plugin_theme_downloader_log_update(
			'plugin',
			$sanitized_slug,
			$plugin_data['Name'],
			$old_version,
			$plugin_data['Version']
		);
	}

	update_option( 'pptd_plugin_version_' . $sanitized_slug, $plugin_data['Version'] );

	return array(
		'path' => $zip_path,
		'url' => permanent_plugin_theme_downloader_get_secure_url( $zip_filename ),
		'filename' => $zip_filename,
	);
}

/**
 * Create or update a ZIP file for a theme.
 */
function permanent_plugin_theme_downloader_create_theme_zip( $theme_slug, $log_update = false ) {
	$theme = wp_get_theme( $theme_slug );
	if ( ! $theme->exists() ) {
		return false;
	}

	$theme_dir = $theme->get_stylesheet_directory();

	permanent_plugin_theme_downloader_ensure_directory();

	$sanitized_slug = sanitize_file_name( $theme_slug );
	$zip_filename = 'theme-' . $sanitized_slug . '.zip';
	$zip_path = PPTD_DOWNLOAD_DIR . '/' . $zip_filename;

	$old_version = null;
	if ( file_exists( $zip_path ) && $log_update ) {
		$old_version = get_option( 'pptd_theme_version_' . $sanitized_slug );
	}

	if ( file_exists( $zip_path ) ) {
		unlink( $zip_path );
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		return false;
	}

	$zip = new ZipArchive();
	if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
		return false;
	}

	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $theme_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	$base_name = basename( $theme_dir );

	foreach ( $files as $file ) {
		$file_path = $file->getRealPath();
		$relative_path = $base_name . '/' . substr( $file_path, strlen( $theme_dir ) + 1 );

		if ( $file->isDir() ) {
			$zip->addEmptyDir( $relative_path );
		} else {
			$zip->addFile( $file_path, $relative_path );
		}
	}

	$zip->close();

	$theme_version = $theme->get( 'Version' );

	if ( $log_update && $old_version && $old_version !== $theme_version ) {
		permanent_plugin_theme_downloader_log_update(
			'theme',
			$sanitized_slug,
			$theme->get( 'Name' ),
			$old_version,
			$theme_version
		);
	}

	update_option( 'pptd_theme_version_' . $sanitized_slug, $theme_version );

	return array(
		'path' => $zip_path,
		'url' => permanent_plugin_theme_downloader_get_secure_url( $zip_filename ),
		'filename' => $zip_filename,
	);
}

/**
 * Get all installed plugins with their download URLs.
 */
function permanent_plugin_theme_downloader_get_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all_plugins = get_plugins();
	$plugins_data = array();

	foreach ( $all_plugins as $plugin_file => $plugin_data ) {
		$slug = dirname( $plugin_file );
		if ( $slug === '.' ) {
			$slug = basename( $plugin_file, '.php' );
		}

		$zip_info = permanent_plugin_theme_downloader_create_plugin_zip( $plugin_file );

		$plugins_data[] = array(
			'name' => $plugin_data['Name'],
			'slug' => $slug,
			'version' => $plugin_data['Version'],
			'file' => $plugin_file,
			'url' => $zip_info ? $zip_info['url'] : '',
			'zip_exists' => $zip_info ? true : false,
		);
	}

	return $plugins_data;
}

/**
 * Get all installed themes with their download URLs.
 */
function permanent_plugin_theme_downloader_get_themes() {
	$all_themes = wp_get_themes();
	$themes_data = array();

	foreach ( $all_themes as $theme_slug => $theme_obj ) {
		$zip_info = permanent_plugin_theme_downloader_create_theme_zip( $theme_slug );

		$themes_data[] = array(
			'name' => $theme_obj->get( 'Name' ),
			'slug' => $theme_slug,
			'version' => $theme_obj->get( 'Version' ),
			'url' => $zip_info ? $zip_info['url'] : '',
			'zip_exists' => $zip_info ? true : false,
		);
	}

	return $themes_data;
}

/**
 * Register REST API endpoint for fetching plugins and themes.
 */
function permanent_plugin_theme_downloader_register_rest_routes() {
	register_rest_route(
		'pptd/v1',
		'/items',
		array(
			'methods' => 'GET',
			'callback' => 'permanent_plugin_theme_downloader_rest_get_items',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_rest_route(
		'pptd/v1',
		'/regenerate',
		array(
			'methods' => 'POST',
			'callback' => 'permanent_plugin_theme_downloader_rest_regenerate',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);

	register_rest_route(
		'pptd/v1',
		'/log',
		array(
			'methods' => 'GET',
			'callback' => 'permanent_plugin_theme_downloader_rest_get_log',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'permanent_plugin_theme_downloader_register_rest_routes' );

/**
 * REST API callback to get all plugins and themes.
 */
function permanent_plugin_theme_downloader_rest_get_items() {
	return array(
		'plugins' => permanent_plugin_theme_downloader_get_plugins(),
		'themes' => permanent_plugin_theme_downloader_get_themes(),
	);
}

/**
 * REST API callback to regenerate all ZIP files.
 */
function permanent_plugin_theme_downloader_rest_regenerate() {
	permanent_plugin_theme_downloader_get_plugins();
	permanent_plugin_theme_downloader_get_themes();

	return array(
		'success' => true,
		'message' => __( 'All ZIP files have been regenerated.', 'permanent-plugin-theme-downloader' ),
	);
}

/**
 * REST API callback to get the update log.
 */
function permanent_plugin_theme_downloader_rest_get_log() {
	return array(
		'log' => permanent_plugin_theme_downloader_get_log(),
	);
}

/**
 * Regenerate ZIP files when plugins are activated, deactivated, or updated.
 */
function permanent_plugin_theme_downloader_on_plugin_change( $plugin ) {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all_plugins = get_plugins();
	if ( isset( $all_plugins[ $plugin ] ) ) {
		permanent_plugin_theme_downloader_create_plugin_zip( $plugin, true );
	}
}
add_action( 'activated_plugin', 'permanent_plugin_theme_downloader_on_plugin_change' );
add_action( 'deactivated_plugin', 'permanent_plugin_theme_downloader_on_plugin_change' );
add_action( 'upgrader_process_complete', 'permanent_plugin_theme_downloader_on_update', 10, 2 );

/**
 * Regenerate ZIP files when themes or plugins are updated.
 */
function permanent_plugin_theme_downloader_on_update( $upgrader_object, $options ) {
	if ( $options['action'] === 'update' ) {
		if ( $options['type'] === 'plugin' && isset( $options['plugins'] ) ) {
			foreach ( $options['plugins'] as $plugin ) {
				permanent_plugin_theme_downloader_create_plugin_zip( $plugin, true );
			}
		}

		if ( $options['type'] === 'theme' && isset( $options['themes'] ) ) {
			foreach ( $options['themes'] as $theme ) {
				permanent_plugin_theme_downloader_create_theme_zip( $theme, true );
			}
		}
	}
}

/**
 * Clean up old ZIP files on plugin deactivation.
 */
function permanent_plugin_theme_downloader_cleanup() {
	if ( file_exists( PPTD_DOWNLOAD_DIR ) ) {
		$files = glob( PPTD_DOWNLOAD_DIR . '/*.zip' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
		}
	}
}
register_deactivation_hook( __FILE__, 'permanent_plugin_theme_downloader_cleanup' );