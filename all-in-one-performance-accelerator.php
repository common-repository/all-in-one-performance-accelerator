<?php

/**
 * The plugin bootstrap file
 * @link              https://smackcoders.com
 * @since             1.0.0
 * @package           All-in-one Performance Accelerator 
 *
 * @wordpress-plugin
 * Plugin Name:       All-in-one Performance Accelerator 
 * Plugin URI:        https://smackcoders.com
 * Description:       All-in-one Performance Accelerator
 * Version:           1.2
 * Author:            Smackcoders
 * Author URI:        https://smackcoders.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       all-in-one-performance-accelerator
 * Domain Path:       /languages
 */

namespace Smackcoders\AIOACC;
use LazyLoad;
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

include_once(ABSPATH.'wp-admin/includes/plugin.php');

register_activation_hook( __FILE__, array('Smackcoders\\AIOACC\\Plugin','activate'));
register_deactivation_hook( __FILE__, array('Smackcoders\\AIOACC\\Plugin','deactivate'));

/**
 * When plugin loads 
 */
add_action( 'plugins_loaded', 'Smackcoders\\AIOACC\\WPUPE_PluginInit' );
register_activation_hook( __FILE__, array( 'Smack_Cache_Enhancer', 'on_activation' ) );
register_deactivation_hook( __FILE__, array( 'Smack_Cache_Enhancer', 'on_deactivation' ) );
register_uninstall_hook( __FILE__, array( 'Smack_Cache_Enhancer', 'on_uninstall' ) );

// register autoload
//spl_autoload_register( 'cache_enabler_autoload' );
add_action( 'plugins_loaded', array( 'Smack_Cache_Enhancer', 'init' ) );


require_once(__DIR__.'/Admin.php');
require_once(__DIR__.'/plugin.php');
// constants
define( 'SMACK_MIN_WP', '5.1' );
define( 'SMACK_BASE', plugin_basename( __FILE__ ) );
define( 'SMACK_DIR', __DIR__ );
define('SMACK_FONT_CACHE_DIR', ABSPATH . "/wp-content/cache/google-fonts/");
define('SMACK_FONT_CACHE_URL', get_site_url() . "/wp-content/cache/google-fonts/");
// define('WCSVPLUGINSLUG', 'all-in-one-performance-accelerator');
// define('WCSVPLUGINDIR', plugin_dir_path(__FILE__));
add_filter( 'wp_handle_upload',  'Smackcoders\\AIOACC\\filter_wp_handle_upload', 10, 2 );

// load required classes
spl_autoload_register(function ($class) {

    // check if classes were loaded in advanced-cache.php
    if ( in_array( $class, array( 'Smack_Cache_Enhancer', 'Smack_Cache_Engine', 'Smack_Cache_Disk' ) ) && ! class_exists( $class ) ) {
		
        require_once sprintf(
            '%s/includes/%s.php',
            SMACK_DIR,
            strtolower( $class )
		);
		
    }
});


/**
 * Plugin Init Function 
 */
function WPUPE_PluginInit() {
	
	Plugin::getInstance();
	Admin::getInstance();
}
$wpupe_slug = 'all-in-one-performance-accelerator';
$plugin_pages = ['all-in-one-performance-accelerator'];
// Load plugin functionalities when it is activated
if(is_plugin_active( Plugin::$wpupe_slug . '/' . Plugin::$wpupe_slug . '.php')){
	$asset_core_table = esc_sql($wpdb->prefix . "aio_asset_table_core_entery");
        $wpdb->query("CREATE TABLE IF NOT EXISTS $asset_core_table (
			`script_name` VARCHAR(255) ,
			`url_full` VARCHAR(255) ,
			`url_short` VARCHAR(255) ,
			`size` VARCHAR(255) ,
			`type` VARCHAR(255) ,
			`version` VARCHAR(255) ,
            `current_page` LONGTEXT NOT NULL
				) ENGINE=InnoDB");
	if (in_array(isset($_REQUEST['page']), $plugin_pages)) {
		include_once(__DIR__.'/plugin-hooks.php');
		$plugin_pages = [ Plugin::$wpupe_plugin_slug ];
		global $wpupe_plugin_ajax_hooks;
		$request_action = isset($_REQUEST['action']) ? $_REQUEST['action'] :'';
		$request_page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
		update_memory_limits_in_wp_config();
		if($request_page){
			if(in_array($request_page, $plugin_pages)){
				include_wpupe_pluginFiles();
				// WPUPE_PluginInit();
			}
		}elseif($request_action){
			if(in_array($request_action, $wpupe_plugin_ajax_hooks)){
				include_wpupe_pluginFiles();
				// WPUPE_PluginInit();
			}
		}	
	}
}
function update_memory_limits_in_wp_config() {
	$config_file = ABSPATH . 'wp-config.php';

	// Define your desired memory limits
	$max_memory_limit = "define( 'WP_MAX_MEMORY_LIMIT' , '512M' );";
	$memory_limit = "define( 'WP_MEMORY_LIMIT', '512M' );";

	// Get the current content of the wp-config.php file
	$config_content = file_get_contents($config_file);

	// Check if the lines exist in the config file
	$max_memory_limit_exists = strpos($config_content, "define( 'WP_MAX_MEMORY_LIMIT' ,") !== false;
	$memory_limit_exists = strpos($config_content, "define( 'WP_MEMORY_LIMIT',") !== false;

	// Replace or add the memory limit lines
	if ($max_memory_limit_exists) {
		$config_content = preg_replace("/define\( 'WP_MAX_MEMORY_LIMIT' , '.*' \);/i", $max_memory_limit, $config_content);
	} else {
		$config_content .= $max_memory_limit . "\n";
	}

	if ($memory_limit_exists) {
		$config_content = preg_replace("/define\( 'WP_MEMORY_LIMIT', '.*' \);/i", $memory_limit, $config_content);
	} else {
		$config_content .= $memory_limit . "\n";
	}

	// Update the wp-config.php file with the modified content
	file_put_contents($config_file, $config_content);

}

//if(!function_exists('include_wpupe_pluginFiles')){
	function include_wpupe_pluginFiles(){	
		require_once(__DIR__.'/minify/minify-css.php');
		require_once(__DIR__.'/minify/minify-js.php');
		require_once(__DIR__.'/database/database-optimization.php');
		require_once(__DIR__.'/heartbeat/control-heartbeat.php');
		require_once(__DIR__.'/classes/disable-embeds.php');
		require_once(__DIR__.'/classes/disable-emoji.php');
		require_once(__DIR__.'/lazyload/lazy-load.php');
		//require_once(__DIR__.'/classes/disable-googlefonts.php');
		require_once(__DIR__.'/classes/Gzip-compression.php');
		require_once(__DIR__.'/cdn/enable-CDN.php');
		require_once(__DIR__.'/cdn/rewrite-CDN.php');
		require_once(__DIR__.'/includes/browser-cache.php');
		require_once(__DIR__.'/includes/clear-cache.php');
		require_once(__DIR__.'/cachePreload/preload-cache.php');
		require_once(__DIR__.'/combine/combineCSS/combineCSS.php');
		require_once(__DIR__.'/siteinfo/siteinfo.php');
		require_once(__DIR__.'/siteinfo/site-recommendations.php');
		require_once(__DIR__.'/siteinfo/cron-status.php');
		require_once(__DIR__.'/siteinfo/checkupdates-status.php');
		require_once(__DIR__.'/cachePreload/cachePreloading.php');
		require_once(__DIR__.'/tools/download-settings.php');
		require_once(__DIR__.'/database/scheduleDBCleanup.php');
		require_once(__DIR__.'/classes/deferJS.php');
		require_once(__DIR__.'/classes/delay_js.php');
		require_once(__DIR__.'/reduce-code/reduce-code.php');
		require_once(__DIR__.'/image-optimization/image-optimization.php');
	}
//}

add_filter('cron_schedules', 'Smackcoders\\AIOACC\\enhancer_cron_schedules');

function enhancer_cron_schedules($schedules){
	if(!isset($schedules["smack_enhancer_daily"])){
		$schedules["smack_enhancer_daily"] = array(
			'interval' => 86400,
			'display' => __('Smack Enhancer daily'));
	}
	if(!isset($schedules["smack_enhancer_weekly"])){
		$schedules["smack_enhancer_weekly"] = array(
			'interval' => 604800,
			'display' => __('Smack Enhancer weekly'));
	}
	if(!isset($schedules["smack_enhancer_monthly"])){
		$schedules["smack_enhancer_monthly"] = array(
			'interval' => 2592000,
			'display' => __('Smack Enhancer monthly'));
	}
	if(!isset($schedules["preload_timing"])){
		$schedules["preload_timing"] = array(
			'interval' => 60,
			'display' => __('Smack Enhancer Once every 1 minute'));
	}
	return $schedules;
}

add_action('profile_enhancer_schedule_hook' , 'Smackcoders\\AIOACC\\enhancer_schedule_funtion');
add_action('smack_preload_schedule_event' , 'Smackcoders\\AIOACC\\enhancer_preload_schedule');
add_action('smack_cache_schedule_event' , 'Smackcoders\\AIOACC\\enhancer_cache_schedule');

function enhancer_schedule_funtion(){
	if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON == true ) {
		return false;
	}

	$schedule_instance = OptimizeDB::getInstance();
	$schedule_instance->post_cleanup();
}

function enhancer_preload_schedule(){
	if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON == true ) {
		return false;
	}

	$adminbar_instance = new adminbarFunction();
	$adminbar_instance->preload();
}

function enhancer_cache_schedule(){
	if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON == true ) {
		return false;
	}

	$adminbar_instance = new adminbarFunction();
	$adminbar_instance->smack_clean_domain('');
}


function filter_wp_handle_upload($attachment_url){
	$attached_url_path=$attachment_url['file'];
	$image_instance = new ImageOptimization();
	$image_instance->compress_image_on_upload($attached_url_path);
    return $attachment_url;
}
