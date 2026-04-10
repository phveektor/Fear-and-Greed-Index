<?php
/**
 * Plugin Name: Fear & Greed Gauge Widget
 * Plugin URI: https://github.com/phveektor
 * Description: Displays cryptocurrency Fear & Greed Index with animated gauge
 * Version: 1.0.0
 * Author: Phveektor
 * Author URI: https://github.com/phveektor
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Text Domain: fear-greed-gauge
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
if ( ! defined( 'FGG_PLUGIN_VERSION' ) ) {
	define( 'FGG_PLUGIN_VERSION', '1.0.0' );
}

if ( ! defined( 'FGG_PLUGIN_FILE' ) ) {
	define( 'FGG_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'FGG_PLUGIN_DIR' ) ) {
	define( 'FGG_PLUGIN_DIR', plugin_dir_path( FGG_PLUGIN_FILE ) );
}

if ( ! defined( 'FGG_PLUGIN_URL' ) ) {
	define( 'FGG_PLUGIN_URL', plugin_dir_url( FGG_PLUGIN_FILE ) );
}

if ( ! defined( 'FGG_INCLUDES_DIR' ) ) {
	define( 'FGG_INCLUDES_DIR', FGG_PLUGIN_DIR . 'includes/' );
}

if ( ! defined( 'FGG_ASSETS_DIR' ) ) {
	define( 'FGG_ASSETS_DIR', FGG_PLUGIN_DIR . 'assets/' );
}

if ( ! defined( 'FGG_TEMPLATES_DIR' ) ) {
	define( 'FGG_TEMPLATES_DIR', FGG_PLUGIN_DIR . 'templates/' );
}

/**
 * Activation hook
 */
function fgg_activate() {
	// Mark installed time - lightweight activation task
	if ( false === get_option( 'fgg_installed' ) ) {
		update_option( 'fgg_installed', time() );
	}
}

/**
 * Deactivation hook
 */
function fgg_deactivate() {
	// Placeholder for cleanup actions if needed in future
}

register_activation_hook( FGG_PLUGIN_FILE, 'fgg_activate' );
register_deactivation_hook( FGG_PLUGIN_FILE, 'fgg_deactivate' );

/**
 * Main plugin class
 */
if ( ! class_exists( 'FG_Gauge_Plugin' ) ) :

class FG_Gauge_Plugin {

	public static function init() {
		// Load required files
		self::load_includes();

		// Register hooks
	add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );
	add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
	// Register assets on init; enqueue later when widget/shortcode is rendered
	add_action( 'init', array( __CLASS__, 'register_assets' ) );
	}

	public static function load_includes() {
		// Include data and widget classes if present
		$incs = FGG_INCLUDES_DIR;

		if ( file_exists( $incs . 'class-fg-data.php' ) ) {
			require_once $incs . 'class-fg-data.php';
			if ( ! class_exists( 'FG_Data' ) ) {
				error_log( 'Fear & Greed Gauge: FG_Data class failed to load despite file existing.' );
			}
		} else {
			error_log( 'Fear & Greed Gauge: class-fg-data.php file not found.' );
		}

		if ( file_exists( $incs . 'class-fg-widget.php' ) ) {
			require_once $incs . 'class-fg-widget.php';
			if ( ! class_exists( 'FG_Widget' ) ) {
				error_log( 'Fear & Greed Gauge: FG_Widget class failed to load despite file existing.' );
			}
		} else {
			error_log( 'Fear & Greed Gauge: class-fg-widget.php file not found.' );
		}
		// Admin includes
		if ( is_admin() && file_exists( $incs . 'admin-settings.php' ) ) {
			require_once $incs . 'admin-settings.php';
		}
	}

	/**
	 * Register (but don't enqueue) frontend assets so they can be conditionally enqueued later
	 */
	public static function register_assets() {
		$style_path = FGG_ASSETS_DIR . 'css/gauge-styles.css';
		$script_path = FGG_ASSETS_DIR . 'js/gauge-animation.js';

		// AGGRESSIVE CACHE BUSTING: Use file modification time as the version number.
		$style_ver = file_exists( $style_path ) ? filemtime( $style_path ) : time();
		$script_ver = file_exists( $script_path ) ? filemtime( $script_path ) : time();

		// Use non-minified assets to aid debugging and avoid serving .min files
		$css = 'assets/css/gauge-styles.css';
		$js  = 'assets/js/gauge-animation.js';

		wp_register_style( 'fgg-gauge-styles', FGG_PLUGIN_URL . $css, array(), $style_ver );
		wp_register_script( 'fgg-gauge-animation', FGG_PLUGIN_URL . $js, array(), $script_ver, true );
	}

	/**
	 * Enqueue registered frontend assets when rendering widget or shortcode
	 */
	public static function enqueue_frontend_assets() {
		// Only enqueue when widget is active in sidebars or shortcode present on page
		$should_load = false;

		// 1) If widget is active anywhere
		if ( is_active_widget( false, false, 'fear_greed_gauge_widget', true ) ) {
			$should_load = true;
		}

		// 2) If current post content contains the shortcode
		if ( ! $should_load && is_singular() ) {
			global $post;
			if ( isset( $post->post_content ) && has_shortcode( $post->post_content, 'fear_greed_gauge' ) ) {
				$should_load = true;
			}
		}

		if ( ! $should_load ) {
			return;
		}

		if ( ! wp_style_is( 'fgg-gauge-styles', 'enqueued' ) ) {
			wp_enqueue_style( 'fgg-gauge-styles' );
		}
		if ( ! wp_script_is( 'fgg-gauge-animation', 'enqueued' ) ) {
			wp_enqueue_script( 'fgg-gauge-animation' );
			// Localize common data
			wp_localize_script( 'fgg-gauge-animation', 'fgg_ajax', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'fgg_nonce' ),
			) );
		}
	}

	public static function register_widget() {
		// Only register if widget class is available
		if ( class_exists( 'FG_Widget' ) ) {
			register_widget( 'FG_Widget' );
		}
	}

	public static function register_shortcodes() {
		add_shortcode( 'fear_greed_gauge', array( __CLASS__, 'shortcode_render' ) );
	}

	public static function shortcode_render( $atts = array(), $content = null ) {
		$atts = shortcode_atts( array(
			'size' => 'medium',
			'show_chart' => 'yes',
			'title' => '',
			'display_title' => 'no',
		), $atts, 'fear_greed_gauge' );

		// Get global admin options and merge with shortcode attributes (shortcode attrs take priority)
		$opts = get_option( 'fgg_settings', array() );
		$opts = array_merge( $opts, $atts );

		// Enqueue assets for shortcode output
		if ( method_exists( __CLASS__, 'enqueue_frontend_assets' ) ) {
			self::enqueue_frontend_assets();
		}

		// Fetch data
		$data = class_exists( 'FG_Data' ) ? FG_Data::get_data() : array(
			'current_value' => 0,
			'change_24h' => 0.0,
			'weekly_data' => array(),
			'timestamp' => time(),
		);

		// Set template variables
		$template_vars = array(
			'data' => $data,
			'size' => $atts['size'],
			'size_class' => 'gauge-' . $atts['size'],
			'show_chart' => $atts['show_chart'],
			'title' => $atts['title'],
			'display_title' => $atts['display_title'],
			'opts' => $opts,
		);

		ob_start();

		$template = FGG_TEMPLATES_DIR . 'gauge-display.php';
		if ( file_exists( $template ) ) {
			extract( $template_vars );
			include $template;
		} else {
			// Fallback minimal output removed to avoid printing duplicate plugin name; template should exist.
		}

		return ob_get_clean();
	}

}

endif; // class_exists

// Initialize plugin after all plugins are loaded
add_action( 'plugins_loaded', array( 'FG_Gauge_Plugin', 'init' ) );

// Load textdomain and perform basic compatibility check
add_action( 'plugins_loaded', function(){
	load_plugin_textdomain( 'fear-greed-gauge', false, dirname( plugin_basename( FGG_PLUGIN_FILE ) ) . '/languages' );
});