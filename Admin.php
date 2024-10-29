<?php
/**
 * All-in-one Performance Accelerator plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\AIOACC;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}
require_once(__DIR__.'/database/database-optimization.php');
require_once(__DIR__.'/database/orphan-tables.php');
require_once(__DIR__.'/heartbeat/control-heartbeat.php');
require_once(__DIR__.'/cdn/enable-CDN.php');
require_once(__DIR__.'/cdn/rewrite-CDN.php');
require_once(__DIR__.'/minify/minify-css.php');
require_once(__DIR__.'/minify/minify-js.php');
require_once(__DIR__.'/classes/disable-googlefonts.php');
require_once(__DIR__.'/classes/disable-embeds.php');
require_once(__DIR__.'/classes/disable-emoji.php');
require_once(__DIR__.'/lazyload/lazy-load.php');
require_once(__DIR__.'/cachePreload/preload-cache.php');
require_once(__DIR__.'/includes/clear-cache.php');
require_once(__DIR__.'/includes/browser-cache.php');
require_once(__DIR__.'/classes/Gzip-compression.php');
require_once(__DIR__.'/image-optimization/image-optimization.php');
//tools files
require_once(__DIR__.'/tools/download-settings.php');

//siteinfo file
require_once(__DIR__.'/siteinfo/siteinfo.php');
require_once(__DIR__.'/siteinfo/site-recommendations.php');
require_once(__DIR__.'/siteinfo/cron-status.php');
require_once(__DIR__.'/siteinfo/checkupdates-status.php');


// combine CSS files
require_once(__DIR__.'/combine/enhancerCache.php');
require_once(__DIR__.'/combine/combineCSS/autoptimizeCSSmin.php');
require_once(__DIR__.'/combine/combineCSS/combineCSS.php');
require_once(__DIR__.'/combine/combineCSS/lib/Minifier.php');
require_once(__DIR__.'/combine/combineCSS/lib/Colors.php');
require_once(__DIR__.'/combine/combineCSS/lib/Utils.php');

// combine JS files
require_once(__DIR__.'/combine/combineJS/combineJS.php');
require_once(__DIR__.'/combine/combineJS/jsmin.php');

// cache preloading file
require_once(__DIR__.'/cachePreload/cachePreloading.php');

// defer JS file
require_once(__DIR__.'/classes/deferJS.php');

// Profiler file
 require_once(__DIR__.'/profiler/scan-profile.php'); 
  require_once(__DIR__.'/profiler/profiler.php');

//cloudfare file
require_once(__DIR__.'/cloudfare/cloudfare.php');

//assetmanager file
require_once(__DIR__.'/reduce-code/reduce-code.php');
require_once(__DIR__.'/reduce-code/asset-view.php');

// query moniter file
require_once(__DIR__.'/Query-moniter/query-moniter.php');
require_once(__DIR__.'/Query-moniter/query-info.php');
require_once(__DIR__.'/Query-moniter/Util.php');
require_once(__DIR__.'/Query-moniter/Backtrace.php');


require_once(__DIR__.'/database-cleanup/database-cleanup.php');

//delay Js file
require_once(__DIR__.'/classes/delay_js.php');

// schedule DB optimization file
require_once(__DIR__.'/database/scheduleDBCleanup.php');

//adminbar function files
require_once(__DIR__.'/classes/adminbarFunction.php');


//cache function files
require_once(__DIR__.'/includes/smack_cache_disk.php');
require_once(__DIR__.'/includes/smack_cache_engine.php');
require_once(__DIR__.'/includes/smack_cache_enhancer.php');


class Admin
{
	protected static $instance = null,$plugin;
	private $starttime, $servertime;
    private $current_screen,$content,$settings,$wpupe_pluginScreenHookSuffix='';
	public function __construct()
	{
        if(get_option('smack_combine_css') == 'true' || get_option('smack_combine_js') == 'true'){
            $this->define_combine_constants();
            add_action( 'init', array($this,'start_buffering'), -1 );
        }

        if(get_option('smack_activate_preloading') == 'true'){
            $cache_preload_instance = new cachePreloading();
            $cache_preload_instance->maybe_init_process();
        }
        if(get_option('smack_deferred_js') == 'true'){
            $defer_js_instance = new deferJS();
            $defer_js_instance->maybe_init_process();
        }

        if(get_option('smack_lazyload_enable') == 'true' || get_option('smack_lazyload_iframe_videos') == 'true'){
            $lazyload_img_instance = new LazyImages();
            $lazyload_img_instance->EnableLazyLoad();
            $lazyload_img_instance->maybe_init_process();
        }
        if(get_option('smack_combine_google_fonts') == 'true'){
           
        $combine_font_instance = new DisableFonts();
            $combine_font_instance->maybe_init_process();
        }    
        $_query_string = filter_input( INPUT_SERVER, 'QUERY_STRING', FILTER_SANITIZE_URL );
		$_request_uri  = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );
        $current_screen_url = '';
		if ( $_query_string && $_request_uri ) {
			$current_screen_url = wp_unslash( $_query_string . '?' . $_request_uri );
		} elseif ( $_query_string ) {
			$current_screen_url = wp_unslash( $_request_uri );
		} else {
			$current_screen_url = admin_url();
		}
        add_action( 'admin_enqueue_scripts', array( $this, 'disable_heartbeat' ), 99 );
        add_filter( 'heartbeat_settings', array( $this, 'modify_frequency' ), 99, 1 );
		$this->current_screen = wp_parse_url( $current_screen_url );
		if ( '/wp-admin/admin-ajax.php' === $this->current_screen ) {
			return;
		}
		$settings = get_option( 'disable_heart_beat' );
        $this->settings = $settings;
		if ( false === $settings ) {
			return;
		}
		$this->settings = $settings;
        $auto_disable = get_option('disable_auto_start_heart_beat');
        if(empty($auto_disable)){
            update_option('disable_auto_start_heart_beat','true');
        }
        if (defined('WP_ADMIN')) {
            add_action('admin_init', array($this, 'smack_time_to_first_byte'), 9999);
        }
        
	}

    function smack_time_to_first_byte() {

		
		if (!empty($_SERVER['REQUEST_TIME_FLOAT'])) {
			$this->starttime = $_SERVER['REQUEST_TIME_FLOAT'];
			$this->servertime = strval(round(microtime(true) - $this->starttime, 2)) . '<span class="query-ss-new"></span>';
		}
	}
    public static function getInstance() {
		if ( null == self::$instance ) {
            global $pagenow;
            
			self::$instance = new self;
			if(is_plugin_active( Plugin::$wpupe_slug . '/' . Plugin::$wpupe_slug . '.php')){
                $plugin_pages = [ Plugin::$wpupe_plugin_slug ];
                global $wpupe_plugin_ajax_hooks;
                $request_action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
                $request_page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
                if($request_page){
                    if(in_array($request_page, $plugin_pages)){
                        Admin::WPUPE_PluginFunctionalities();
                       
                    }
                }elseif($request_action != 'edit'){
                        Admin::WPUPE_PluginFunctionalities();
                }elseif($pagenow == 'upload.php'){
                    Admin::WPUPE_PluginFunctionalities();
                }
				self::$plugin = Plugin::getInstance();
				self::$instance->doHooks();
			}
		}
		return self::$instance;
	}

	public static function WPUPE_PluginFunctionalities()
	{
		HearbeatControl::getInstance();
		MinifyJs::getInstance();
		MinifyCss::getInstance();
        DisableEmbed::getInstance();
        DisableEmoji::getInstance();
        DelayJS::getInstance();
        DisableFonts::getInstance();
        LazyImages::getInstance();
        PreloadCache::getInstance();
		Compression::getInstance();
		BrowserCache::getInstance();
		ClearCache::getInstance();
		EnableCDN::getInstance();
		RewriteCDN::getInstance();
        OptimizeDB::getInstance();
        SiteInfo::getInstance();
        DownloadSettings::getInstance();
        scheduleDBCleanup::getInstance();
        adminbarFunction::getInstance();
        ImageOptimization::getInstance();
        SiteWarnings::getInstance();
        QueryInfo::getInstance();
        OrphanTables::getInstance();
        Profiler::getInstance();
        Reducecode::getInstance();
        Cloudfare::getInstance();
        DatabaseCleanup::getInstance();
        SmackQuery::getInstance();
	}
	
	public function doHooks(){
		add_action('admin_menu', array($this,'wpupe_pluginAdminmenu'));
        add_action( 'wp_enqueue_scripts',    array( $this, 'smack_assets' ), -9999 );
        add_action('admin_enqueue_scripts',array($this,'wpupe_enqueueAdminScripts'));
        add_action('init', array(__CLASS__, 'drop_table_popup'));
		do_action('popup');
        add_action( 'admin_bar_menu', array($this,'smack_admin_bar' ),999);
       
        add_action( 'admin_post_clear_cache_all', array($this, 'do_admin_post_clear_cache_all'));
        add_action( 'admin_post_smack_preload', array($this, 'do_admin_post_smack_preload'));
        add_action( 'admin_post_smack_purge_opcache', array($this, 'do_admin_post_smack_purge_opcache'));
        add_action( 'admin_post_smack_stop_preload', array($this, 'do_admin_post_smack_stop_preload'));
        add_action( 'admin_post_smack_cloudflare_cache', array($this, 'do_admin_post_smack_cloudflare_cache'));
        add_action( 'admin_notices', array($this, 'dashboard_admin_notice__success'));
        add_action( 'wp_ajax_get_tabs_and_page', array($this, 'get_tabs_and_page'));
        if (!empty($_SERVER['REQUEST_TIME_FLOAT'])) {
            $this->starttime = $_SERVER['REQUEST_TIME_FLOAT'];
            $this->servertime = strval(round(microtime(true) - $this->starttime, 2)) . '<span class="query-ss-new" style="margin:10px">|</span>';
        }		
    
        global $wpdb;
        $data = array();
        $precision = 0;
        $memory_usage = memory_get_peak_usage() / 1048576;
        if ($memory_usage < 10) {
            $precision = 2;
        } else if ($memory_usage < 100) {
            $precision = 1;
        }
    
        $memory_usage = round($memory_usage, $precision);
        $time_usage = (empty($this->starttime)) ? '' : $this->servertime . round(microtime(true) - $this->starttime, 2);
    
        $data['memory'] = $memory_usage;
        $data['time'] = $time_usage;
        $data['queries'] = $wpdb->num_queries;

        
    }
    
    public function get_tabs_and_page(){
        $tab = sanitize_text_field($_POST['tab']);
        $page = sanitize_text_field($_POST['page']);
        if($tab === 'undefined' || empty($tab)){ 
            $tab_value = get_option('smack_tab');
            $page_value = get_option('smack_page');
            if($tab_value === 'undefined' || empty($tab_value)){ 
                $tab_name = 'info';
                $page_name = 'siteinfo';
                update_option('smack_tab',$tab_name);
                update_option('smack_page',$page_name);
            }else{
                update_option('smack_tab',$tab_value);
                update_option('smack_page',$page_value);
            }
        }else{
            update_option('smack_tab',$tab);
            update_option('smack_page',$page);
        }
        $tab_address = get_option('smack_tab');
        $page_address = get_option('smack_page');
        $result['tab'] = $tab_address;
        $result['page'] = $page_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
    }

    function dashboard_admin_notice__success() {
        $purge_cache=get_option('smack_purge_status');
        $preload_cache=get_option('smack_preload_status');
      
         if ($purge_cache=='success' )  {
            delete_option('smack_purge_status');
            echo sprintf(
                '<div class="notice notice-success is-dismissible"><p><strong>%s</strong></p></div>',
                esc_html__( 'Purge cache cleared Successfully.', 'smack-cache' )
            );
            
           
        }
        if($preload_cache=='success'){
            delete_option('smack_preload_status'); 
            echo sprintf(
                '<div class="notice notice-success is-dismissible"><p><strong>%s</strong></p></div>',
                esc_html__( 'Preload cached Successfully.', 'smack-cache' )
            );
           
           
        }
      }

    public function disable_heartbeat() {
		if ( $this->settings != 'false' ) {
            wp_deregister_script( 'heartbeat' );
            return;
        }elseif(get_option('disable_auto_start_heart_beat') === 'true'){
            wp_deregister_script( 'heartbeat' );
            return;
        }
	}

    public function modify_frequency( $frequency ) {
            $frequency_value = get_option('control_heart_beat_frequency');
            $frequency['interval'] = intval( $frequency_value);  
				return $frequency;
	}

    public function  do_admin_post_clear_cache_all(){
        $adminbar_instance = new adminbarFunction();
        $adminbar_instance->clear_cache_all_function();
    }

    public function do_admin_post_smack_preload(){  
        $adminbar_instance = new adminbarFunction();
        $adminbar_instance->smack_preload_function();
    }
    public function do_admin_post_smack_cloudflare_cache(){
        $cloudflare_instance = new Cloudfare();
        $cloudflare_instance->admin_smack_cloudflare_purge();
    }

    public function do_admin_post_smack_stop_preload(){
        wp_clear_scheduled_hook( 'smack_preload_schedule_event' );
        $adminbar_instance = new adminbarFunction();
        $adminbar_instance->smack_direct_filesystem()->touch( WP_CONTENT_DIR . '/cache/' . '.' . 'smack_preload_process_cancelled' );
        wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
        die();
    }
     
    public function do_admin_post_smack_purge_opcache(){
        $adminbar_instance = new adminbarFunction();
        $adminbar_instance->smack_purge_opcache_function();
    }

	public function wpupe_pluginAdminmenu(){
		$this->wpupe_pluginScreenHookSuffix = add_menu_page('All-in-one Performance Accelerator', 'AIO Performance Accelerator', 'manage_options',
			'all-in-one-performance-accelerator',array($this,'display_plugin_admin_page'),plugins_url("assets/images/PerformanceAccelerator.png",__FILE__));
	}

	public function display_plugin_admin_page(){
		?><div id='ultimate-performance-enhancer'>performer enhancer</div><?php
	}

	public function wpupe_enqueueAdminScripts(){
		if(!isset($this->wpupe_pluginScreenHookSuffix)){
			return;
		}
		$screen = get_current_screen();

		if($this->wpupe_pluginScreenHookSuffix == $screen->id){
            wp_enqueue_style(self::$plugin->getPluginSlug() . 'bootstrap-css', plugins_url('assets/css/deps/bootstrap.min.css', __FILE__));
		    wp_enqueue_style(self::$plugin->getPluginSlug() . 'csv-importer-css', plugins_url('assets/css/deps/csv-importer.css', __FILE__));
            wp_register_script(self::$plugin->getPluginSlug() . 'smack_react_script', plugins_url('assets/js/admin.js', __FILE__), array('jquery'));
			wp_enqueue_script(self::$plugin->getPluginSlug() . 'smack_react_script');
            wp_enqueue_style(self::$plugin->getPluginSlug() . 'csv-importer-css', plugins_url('assets/css/deps/csv-importer.css', __FILE__));
            wp_enqueue_style(self::$plugin->getPluginSlug() . 'ultimate-acclerator-css', plugins_url('assets/css/deps/style.css', __FILE__));
            wp_enqueue_style(self::$plugin->getPluginSlug() . 'ultimate-acclerator-info-css', plugins_url('assets/css/deps/siteinfo.css', __FILE__));
            wp_enqueue_style(self::$plugin->getPluginSlug() . 'react-datepicker-css', plugins_url('assets/css/deps/react-datepicker.css', __FILE__));
            wp_enqueue_style(self::$plugin->getPluginSlug() . 'react-toasty-css', plugins_url('assets/css/deps/ReactToastify.min.css', __FILE__));
            wp_localize_script(self::$plugin->getPluginSlug() . 'smack_react_script', 'aioacc_wpr_object', array('file' => '', __FILE__, 'imagePath' => plugins_url('/assets/images/', __FILE__)));
        }
        wp_register_script(self::$plugin->getPluginSlug() . 'smack_query_view', plugins_url('assets/js/query-view.js', __FILE__), array('jquery'));
        wp_enqueue_script(self::$plugin->getPluginSlug() . 'smack_query_view');
        wp_enqueue_style(self::$plugin->getPluginSlug() . 'smack-query-monitor', plugins_url('assets/css/deps/smack-query-monitor.css', __FILE__));	   
    }
    
    public function smack_assets() {
        wp_register_script(self::$plugin->getPluginSlug() . 'smack_query_view', plugins_url('assets/js/query-view.js', __FILE__), array('jquery'));
        wp_enqueue_script(self::$plugin->getPluginSlug() . 'smack_query_view');
        wp_enqueue_style(self::$plugin->getPluginSlug() . 'smack-query-monitor', plugins_url('assets/css/deps/smack-query-monitor.css', __FILE__));	   
    }

    public static function drop_table_popup(){
        $drop_options=get_option('smack_drop_options');
        if($drop_options == 'true'){
            wp_register_script('droptableJs',plugins_url( 'assets/js/deps/wp-deactivate-popup.js', __FILE__), array('jquery'));
            // wp_enqueue_script('droptableJs',plugins_url( 'assets/js/deps/wp-deactivate-popup.js', __FILE__), array('jquery'));
            wp_enqueue_style('droptableCss', plugins_url( 'assets/css/deps/wp-deactivate-popup.css', __FILE__));
        }
		
	}

    function smack_admin_bar( $wp_admin_bar ) {
        global $pagenow, $post;
        $activate_preloading=get_option('smack_activate_preloading');
        $user_cache=get_option('smack_user_cache');
        $cloudflare_info=get_option('smack_cloudflare_info');
        $server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
        if ( ! empty( $server_array['REQUEST_URI'] ) ) {
            $referer = filter_var( wp_unslash( $server_array['REQUEST_URI'] ), FILTER_SANITIZE_URL );
            $referer = '&_wp_http_referer=' . rawurlencode( remove_query_arg( 'fl_builder', $referer ) );
        } else {
            $referer = '';
        }
       
        // $wp_admin_bar->add_menu(
		// 	[
		// 		'id'    => 'performance-enhancer',
		// 		'title' => 'AIO Performance Accelerator',
		// 		'href'  => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=all-in-one-performance-accelerator' ) : false,
		// 	]
        // );
        $custom_content = $this->get_custom_admin_bar_content();

        $wp_admin_bar->add_menu(
            [
                'id'    => 'performance-enhancer',
                'title' => $custom_content['memory'].'M '.$custom_content['time'].'s '.$custom_content['queries'].'Q',
                'href'  => current_user_can('manage_options') ? admin_url('admin.php?page=all-in-one-performance-accelerator') : false,
            ]
        );
        
       

        /**
         * Preload Cache in adminbar
         */
        if($activate_preloading === 'true'){
            $action = 'smack_preload';
            $wp_admin_bar->add_menu(
                [
                    'parent' => 'performance-enhancer',
                    'id'     => 'preload-cache',
                    'title'  => __( 'Preload cache', 'smack-enhancer' ),
                    'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=' . $action . $referer ), $action ),
                ]
               
            );
           
        }

        /**
         * Cloudflare Cache in adminbar
         */
        if(!empty($cloudflare_info)){
            $action = 'smack_cloudflare_cache';
            $wp_admin_bar->add_menu(
                [
                    'parent' => 'performance-enhancer',
                    'id'     => 'cloudflare-cache',
                    'title'  => __( 'Cloudflare cache', 'smack-enhancer' ),
                    'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=' . $action . $referer ), $action ),
                ]
               
            );
           
        }

        /**
		 * Purge OPCache content if OPcache is active in adminbar.
		 */
		$opcache_enabled  = filter_var( ini_get( 'opcache.enable' ), FILTER_VALIDATE_BOOLEAN );
		$restrict_api     = ini_get( 'opcache.restrict_api' );
		$can_restrict_api = true;
		if ( $restrict_api && strpos( __FILE__, $restrict_api ) !== 0 ) {
			$can_restrict_api = false;
		}

		
        $wp_admin_bar->add_node(
            [
                'parent' => 'performance-enhancer',
                'id'     => 'smack-query',
                'title'  => __( 'Smack Query', 'smack-enhancer' ),
                'href'  => '#query-view',
            ]
           
        );

        $wp_admin_bar->add_node(
            [
                'parent' => 'performance-enhancer',
                'id'     => 'asset-view',
                'title'  => __( 'Smack asset', 'smack-enhancer' ),
                'href'  => '#asset-view',
            ]
           
        );

        
    }
    // Custom function to get content for the admin bar
    function get_custom_admin_bar_content() {
        // Your logic to retrieve content for the admin bar goes here
        // For example, let's say you want to display a simple message
		

        if (!empty($_SERVER['REQUEST_TIME_FLOAT'])) {
			$this->starttime = $_SERVER['REQUEST_TIME_FLOAT'];
			$this->servertime = strval(round(microtime(true) - $this->starttime, 2)) . '<span class="query-ss-new" style="margin:10px">|</span>';
		}	

        global $wpdb;
		$data = array();
        $precision = 0;
        $memory_usage = memory_get_peak_usage() / 1048576;
        if ($memory_usage < 10) {
            $precision = 2;
        } else if ($memory_usage < 100) {
            $precision = 1;
        }

        $memory_usage = round($memory_usage, $precision);
        $time_usage = (empty($this->starttime)) ? '' : $this->servertime . round(microtime(true) - $this->starttime, 2);

        $data['memory'] = $memory_usage;
        $data['time'] = $time_usage;
        $data['queries'] = $wpdb->num_queries;

        return $data;
    }

    // // Custom function to set a value for the admin bar
    // function set_custom_admin_bar_value($value) {
    //     // Your logic to set a value for the admin bar goes here
    //     // This function can be used to store data for future use
    //     // For example, you might store a user-specific value
    //     update_user_meta(get_current_user_id(), 'custom_admin_bar_value', $value);
    // }

    public function define_combine_constants(){
        if ( ! defined( 'SMACK_OPTIMIZE_PLUGIN_DIR' ) ) {
            define( 'SMACK_OPTIMIZE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        }

        if ( ! defined( 'SMACK_OPTIMIZE_DIR' ) ) {
            define( 'SMACK_OPTIMIZE_DIR', enhancerCache::get_pathname() );
        }

        if ( ! defined( 'SMACK_OPTIMIZE_CACHE_CHILD_DIR' ) ) {
            define( 'SMACK_OPTIMIZE_CACHE_CHILD_DIR', '/cache/smack-optimize/' );
        }

        if ( ! defined( 'SMACK_OPTIMIZE_WP_SITE_URL' ) ) {
            if ( function_exists( 'domain_mapping_siteurl' ) ) {
                define( 'SMACK_OPTIMIZE_WP_SITE_URL', domain_mapping_siteurl( get_current_blog_id() ) );
            } else {
                define( 'SMACK_OPTIMIZE_WP_SITE_URL', site_url() );
            }
        }

        if ( ! defined( 'SMACK_OPTIMIZE_WP_CONTENT_NAME' ) ) {
            define( 'SMACK_OPTIMIZE_WP_CONTENT_NAME', '/' . wp_basename( WP_CONTENT_DIR ) );
        }

        if ( ! defined( 'SMACK_OPTIMIZE_WP_CONTENT_URL' ) ) {
            if ( function_exists( 'get_original_url' ) ) {
                define( 'SMACK_OPTIMIZE_WP_CONTENT_URL', str_replace( get_original_url( SMACK_OPTIMIZE_WP_SITE_URL ), SMACK_OPTIMIZE_WP_SITE_URL, content_url() ) );
            } else {
                define( 'SMACK_OPTIMIZE_WP_CONTENT_URL', content_url() );
            }
        }

        if ( ! defined( 'SMACK_OPTIMIZE_CACHE_URL' ) ) {
            if ( is_multisite() && apply_filters( 'smack_optimize_separate_blog_caches', true ) ) {
                $blog_id = get_current_blog_id();
                define( 'SMACK_OPTIMIZE_CACHE_URL', SMACK_OPTIMIZE_WP_CONTENT_URL . SMACK_OPTIMIZE_CACHE_CHILD_DIR . $blog_id . '/' );
            } else {
                define( 'SMACK_OPTIMIZE_CACHE_URL', SMACK_OPTIMIZE_WP_CONTENT_URL . SMACK_OPTIMIZE_CACHE_CHILD_DIR );
            }
        }

        if ( ! defined( 'SMACK_OPTIMIZE_HASH' ) ) {
            define( 'SMACK_OPTIMIZE_HASH', wp_hash( SMACK_OPTIMIZE_CACHE_URL ) );
        }

        if( ! defined( 'SMACK_WP_ROOT_DIR' )){
            define( 'SMACK_WP_ROOT_DIR', substr( WP_CONTENT_DIR, 0, strlen( WP_CONTENT_DIR ) - strlen( SMACK_OPTIMIZE_WP_CONTENT_NAME ) ) );
        }

        if ( ! defined( 'SMACK_OPTIMIZE_WP_ROOT_URL' ) ) {
            define( 'SMACK_OPTIMIZE_WP_ROOT_URL', str_replace( SMACK_OPTIMIZE_WP_CONTENT_NAME, '', SMACK_OPTIMIZE_WP_CONTENT_URL ) );
        }
    }

	public function start_buffering()
    {
        if ( $this->should_buffer() ) {
            ob_start( array( $this, 'end_buffering' ) );
        }
    }

	public static function should_buffer( $doing_tests = false )
    {
        static $do_buffering = null;

        // Only check once in case we're called multiple times by others but
        // still allows multiple calls when doing tests.
        if ( null === $do_buffering || $doing_tests ) {

            $ao_noptimize = false;

            // Checking for DONOTMINIFY constant as used by e.g. WooCommerce POS.
            if ( defined( 'DONOTMINIFY' ) && ( constant( 'DONOTMINIFY' ) === true || constant( 'DONOTMINIFY' ) === 'true' ) ) {
                $ao_noptimize = true;
            }

            // Skip checking query strings if they're disabled.
            if ( apply_filters( 'smack_optimize_filter_honor_qs_noptimize', true ) ) {
                // Check for `ao_noptimize` (and other) keys in the query string
                // to get non-optimized page for debugging.
                $keys = array(
                    'ao_noptimize',
                    'ao_noptirocket',
                );
                foreach ( $keys as $key ) {
                    $get   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
                    if(!empty($get)){
                    if ( array_key_exists( $key, $get ) && '1' === $get[ $key ] ) {
                        $ao_noptimize = true;
                        break;
                    }
                }
                }
            }

            // And make sure pagebuilder previews don't get optimized HTML/ JS/ CSS/ ...
            if ( false === $ao_noptimize ) {
                $_qs_pagebuilders = array( 'tve', 'elementor-preview', 'fl_builder', 'vc_action', 'et_fb', 'bt-beaverbuildertheme', 'ct_builder', 'fb-edit', 'siteorigin_panels_live_editor' );
                foreach ( $_qs_pagebuilders as $_pagebuilder ) {
                    if(!empty($get)){
                    if ( array_key_exists( $_pagebuilder, $get ) ) {
                        $ao_noptimize = true;
                        break;
                    }
                }
                }
            }
 
            if(!empty($get)){
                if ( false === $ao_noptimize && array_key_exists( 'PageSpeed', $get ) && 'off' === $get['PageSpeed'] ) {
                    $ao_noptimize = true;
                }
    
            }
           
            // And finally allows blocking of autoptimization on your own terms regardless of above decisions.
            $ao_noptimize = (bool) apply_filters( 'smack_optimize_filter_noptimize', $ao_noptimize );

            // Check for site being previewed in the Customizer (available since WP 4.0).
            $is_customize_preview = false;
            if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
                $is_customize_preview = is_customize_preview();
            }

            /**
             * We only buffer the frontend requests (and then only if not a feed
             * and not turned off explicitly and not when being previewed in Customizer)!
             * NOTE: Tests throw a notice here due to is_feed() being called
             * while the main query hasn't been ran yet. Thats why we use
             * SMACK_OPTIMIZE_INIT_EARLIER in tests.
             */
            $do_buffering = ( ! is_admin() && ! is_feed() && ! is_embed() && ! $ao_noptimize && ! $is_customize_preview );
        }

        return $do_buffering;
    }


    public function end_buffering( $content )
    {
		
        $this->content  = $content;
        // Bail early without modifying anything if we can't handle the content.
        if ( ! $this->is_valid_buffer( $content ) ) {
            return $content;
        }

        // $conf = autoptimizeConfig::instance();
       
        // Determine what needs to be ran.
        $classes = array();
        if ( get_option('smack_combine_js') == 'true' ) {
            $classes[] = 'combineJS';
        }
        if ( get_option('smack_combine_css') == 'true') {
            $classes[] = 'combineCSS';
        }
        
        //$content = apply_filters( 'smack_optimize_filter_html_before_minify', $content );
       
        $get_excluded_js = get_option('smack_excluded_js');
        $get_excluded_js = str_replace('http://',"",$get_excluded_js);
        $explode_excluded_js = explode(",", $get_excluded_js);
      
        $implode_excluded_js = implode(',', $explode_excluded_js);
        $excluded_js=$implode_excluded_js.',wp-includes/js/dist/, wp-includes/js/tinymce/';

        $get_excluded_css = get_option('smack_excluded_css');
        $get_excluded_css = str_replace('http://',"",$get_excluded_css);
        $explode_excluded_css = explode(",", $get_excluded_css);
        $implode_excluded_css = implode(',', $explode_excluded_css);
        $excluded_css=$implode_excluded_css.',wp-content/cache/, wp-content/uploads/';
      
        $class_options = array(
            'combineJS' => array(
                'aggregate' => 1,
                'js_exclude' => '',
                'js_exclude' => $implode_excluded_js,
                'forcehead' => 0,
                'include_inline' => 0,
				'minify_excluded' => 1,
				'inline' => 0,
				'justhead' => 0,
                'cdn_url' => '',
                'trycatch' => 0
			),
			'combineCSS' => array(
				'aggregate' => 1,
                'css_exclude' => '',
                'css_exclude' =>$implode_excluded_css,
                'include_inline' => 1,
				'minify_excluded' => 1,
				'inline' => 0,
				'justhead' => 0,
				'datauris' => 0,
				'defer' => 0,
                'defer_inline' => 0,
                'cdn_url' => ''
			)
		);
		
        // Run the classes!
        foreach ( $classes as $name ) {
		
             //$instance = new $name( $content );
            
            if($name == 'combineJS'){
                $instance = new combineJS( $content );
            }
            elseif($name == 'combineCSS'){
                $instance = new combineCSS( $content );
            }
           
            if ( $instance->read( $class_options[ $name ] ) ) {
              
                $instance->minify();
                $instance->cache();
                $content = $instance->getcontent();
                
            }
            unset( $instance );
        }

        $content = apply_filters( 'smack_optimize_html_after_minify', $content );
        return $content;
	}
	
	public function is_valid_buffer( $content )
    {
        // Defaults to true.
        $valid = true;

        $has_no_html_tag    = ( false === stripos( $content, '<html' ) );
        $has_xsl_stylesheet = ( false !== stripos( $content, '<xsl:stylesheet' ) || false !== stripos( $content, '<?xml-stylesheet' ) );
        $has_html5_doctype  = ( preg_match( '/^<!DOCTYPE.+html>/i', ltrim( $content ) ) > 0 );
        $has_noptimize_page = ( false !== stripos( $content, '<!-- noptimize-page -->' ) );

        if ( $has_no_html_tag ) {
            // Can't be valid amp markup without an html tag preceding it.
            $is_amp_markup = false;
        } else {
            $is_amp_markup = self::is_amp_markup( $content );
        }

        // If it's not html, or if it's amp or contains xsl stylesheets we don't touch it.
        if ( $has_no_html_tag && ! $has_html5_doctype || $is_amp_markup || $has_xsl_stylesheet || $has_noptimize_page ) {
            $valid = false;
        }
        
        return $valid;
	}
	
	/**
     * Returns true if given $content is considered to be AMP markup.
     * This is far from actual validation against AMP spec, but it'll do for now.
     *
     * @param string $content Markup to check.
     *
     * @return bool
     */
    public static function is_amp_markup( $content )
    {
        // Short-circuit when a function is available to determine whether the response is (or will be) an AMP page.
        if ( function_exists( 'is_amp_endpoint' ) ) {
            return is_amp_endpoint();
        }

        $is_amp_markup = preg_match( '/<html[^>]*(?:amp|⚡)/i', $content );

        return (bool) $is_amp_markup;
    }

}
