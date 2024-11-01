<?php
/**
 * Plugin Name: Simple Compiler SCSS
 * Description: Compile SCSS file to CSS
 * Version: 1.1
 * Author: Tom Baumgarten
 * Author URI: https://www.tombgtn.fr/
 * Text Domain: simple-compiler-scss
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'SIMPLE_COMPILER_SCSS_VERSION' ) ) define( 'SIMPLE_COMPILER_SCSS_VERSION', '1.0' );
if ( ! defined( 'SIMPLE_COMPILER_SCSS_FILE' ) ) define( 'SIMPLE_COMPILER_SCSS_FILE', __FILE__ );

/**
 * Return the SCSS compiled file if user request CSS file who doesn't exist
 */
function SCS_compile_current_file() {

	if (!isset($_SERVER['DOCUMENT_ROOT']) || empty($_SERVER['DOCUMENT_ROOT'])) return;
	if (!isset($_SERVER['REQUEST_URI']) || empty($_SERVER['REQUEST_URI'])) return;

	// If the file requested is not a CSS file, return
	$file_requested = sanitize_text_field(wp_unslash($_SERVER['DOCUMENT_ROOT']) . wp_unslash(strtok($_SERVER["REQUEST_URI"], '?')));
	$file_requested_infos = pathinfo($file_requested);
	if (isset($file_requested_infos['extension']) && $file_requested_infos['extension'] !== 'css') return;

	// If no SCSS file with the same name exist, return
	$file_returned = $file_requested_infos['dirname'] . '/' . $file_requested_infos['filename'] . '.scss';
	if (!file_exists($file_returned)) return;
	
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

	// Return CSS file, HTTP header 200
	global $wp_query;
	status_header( 200 );
	$wp_query->is_404=false;
	header("Content-type: text/css");
	header("HTTP/1.1 200 OK");

	// Load SCSSPHP by Leaf Corcoran
	require_once( plugin_dir_path( SIMPLE_COMPILER_SCSS_FILE ) . 'scssphp/scss.inc.php' );

	try {
		do_action('scs_before_compile', $file_requested, $file_returned);

		$compiler = new ScssPhp\ScssPhp\Compiler();

		// Compile SCSS file
		$filesystem = new WP_Filesystem_Direct( true );
		$file_returned_source = $filesystem->get_contents($file_returned);
		$file_returned_source = apply_filters('scs_scss_before_compile', $file_returned_source, $file_requested, $file_returned);
		$import_path = dirname($file_returned) . '/';
		$compiler->addImportPath($import_path);
		$css = $compiler->compile($file_returned_source);

		$css = apply_filters('scs_css_compiled', $css, $file_requested, $file_returned);

		if (!empty($css) && is_string($css)) {
			echo wp_kses_post($css);
		}

		do_action('scs_after_compile', $file_requested, $file_returned);
	} catch (Exception $e) {
		error_log($e->getMessage());
	}

	die;
}
add_action('template_redirect', 'SCS_compile_current_file');