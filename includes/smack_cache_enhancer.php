<?php
/**
 * All-in-one Performance Accelerator plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Smack_Cache_Enhancer {

    /**
     * initialize plugin
     *
     */

    public static function init() {

        new self();
    }


    /**
     * settings from database (deprecated)
     *
     */

    public static $options;


   

    /**
     * constructor
     *
     */

    public function __construct() {

        // init hooks
        add_action( 'init', array( 'Smack_Cache_Engine', 'start' ) );
        add_action( 'init', array( 'Smack_Cache_Engine', 'start_buffering' ) );
        add_action( 'init', array( __CLASS__, 'process_clear_cache_request' ) );
        add_action( 'init', array( __CLASS__, 'register_textdomain' ) );


        // settings hooks
        add_action( 'permalink_structure_changed', array( __CLASS__, 'update_backend' ) );
        add_action( 'add_option_cache_enabler', array( __CLASS__, 'on_update_backend' ), 10, 2 );
        add_action( 'update_option_cache_enabler', array( __CLASS__, 'on_update_backend' ), 10, 2 );

        // clear cache hooks
        add_action( 'ce_clear_post_cache', array( __CLASS__, 'clear_page_cache_by_post_id' ) );
        add_action( 'ce_clear_cache', array( __CLASS__, 'clear_complete_cache' ) );
        add_action( '_core_updated_successfully', array( __CLASS__, 'clear_complete_cache' ) );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 10, 2 );
        add_action( 'switch_theme', array( __CLASS__, 'clear_complete_cache' ) );
        add_action( 'activated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'deactivated_plugin', array( __CLASS__, 'on_plugin_activation_deactivation' ), 10, 2 );
        add_action( 'save_post', array( __CLASS__, 'on_save_post' ) );
        add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
        add_action( 'wp_trash_post', array( __CLASS__, 'on_trash_post' ) );
        add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 10, 3 );
        add_action( 'pre_comment_approved', array( __CLASS__, 'new_comment' ), 99, 2 );
        add_action( 'permalink_structure_changed', array( __CLASS__, 'clear_complete_cache' ) );
        // third party
        add_action( 'autoptimize_action_cachepurged', array( __CLASS__, 'clear_complete_cache' ) );

        


        // admin bar hook
        add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_links' ), 90 );

        // admin interface hooks
        if ( is_admin() ) {
            // multisite
            add_action( 'wp_initialize_site', array( __CLASS__, 'install_later' ) );
            add_action( 'wp_uninitialize_site', array( __CLASS__, 'uninstall_later' ) );
            // settings
            add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
     /*  */       // comments
            add_action( 'transition_comment_status', array( __CLASS__, 'change_comment' ), 10, 3 );
            add_action( 'comment_post', array( __CLASS__, 'comment_post' ), 99, 2 );
            add_action( 'edit_comment', array( __CLASS__, 'edit_comment' ) );
            // dashboard
            add_filter( 'dashboard_glance_items', array( __CLASS__, 'add_dashboard_cache_size' ) );
            add_filter( 'plugin_action_links_' . SMACK_BASE, array( __CLASS__, 'add_plugin_action_links' ) );
            // notices
            add_action( 'admin_notices', array( __CLASS__, 'requirements_check' ) );
            add_action( 'admin_notices', array( __CLASS__, 'cache_cleared_notice' ) );
            add_action( 'network_admin_notices', array( __CLASS__, 'cache_cleared_notice' ) );
        }
    }


    /**
     * activation hook
     *
     *
     * @param   boolean  $network_wide  network activated
     */

    public static function on_activation( $network_wide ) {

        // install backend requirements, triggering the settings file(s) to be created
        self::each_site( $network_wide, 'self::install_backend' );

        // configure system files
        Smack_Cache_Disk::setup();
       
        //mu-plugins doesn't exist	
        if ( !file_exists( WPMU_PLUGIN_DIR ) && is_writable( dirname( WPMU_PLUGIN_DIR ) ) ) {
			wp_mkdir_p( WPMU_PLUGIN_DIR );
		}
		if ( file_exists( WPMU_PLUGIN_DIR ) && is_writable( WPMU_PLUGIN_DIR ) ) {
			file_put_contents(
				WPMU_PLUGIN_DIR . '/smack-profile.php',
				'<' . "?php // Start profiling\n@include_once( WP_PLUGIN_DIR . '/all-in-one-performance-accelerator/profiler/scan-profile.php' ); ?" . '>'
			);
		}
    }


    /**
     * upgrade hook
     *
     *
     * @param   WP_Upgrader  $obj   upgrade instance
     * @param   array        $data  update data
     */

    public static function on_upgrade( $obj, $data ) {

        // if setting enabled clear complete cache on any plugin update
        if ( Smack_Cache_Engine::$settings['clear_complete_cache_on_changed_plugin'] ) {
            self::clear_complete_cache();
        }

        // check updated plugins
        if ( $data['action'] === 'update' && $data['type'] === 'plugin' && array_key_exists( 'plugins', $data ) ) {
            foreach ( (array) $data['plugins'] as $plugin_file ) {
                // check if Cache Enabler has been updated
                if ( $plugin_file === SMACK_BASE ) {
                    self::on_ce_update();
                }
            }
        }
    }


    /**
     * Cache Enabler update actions
     *
     */

    public static function on_ce_update() {

        $network_wide  = is_multisite();
        $plugin_update = true;

        // clean system files
        self::each_site( $network_wide, 'Smack_Cache_Disk::clean' );

        // configure system files
        Smack_Cache_Disk::setup();

        // update backend requirements, triggering new settings file(s) to be created
        self::each_site( $network_wide, 'self::update_backend', array( $plugin_update ) );

        // clear complete cache
        self::clear_complete_cache();
    }


    /**
     * deactivation hook
     *
     *
     * @param   boolean  $network_wide  network deactivated
     */

    public static function on_deactivation( $network_wide ) {

        // Remove mu-plugin
		if ( file_exists( WPMU_PLUGIN_DIR . '/smack-profile.php' ) ) {
			if ( is_writable( WPMU_PLUGIN_DIR . '/smack-profile.php' ) ) {
				// Some servers give write permission, but not delete permission.  Empty the file out, first, then try to delete it.
				file_put_contents( WPMU_PLUGIN_DIR . '/smack-profile.php', '' );
				unlink( WPMU_PLUGIN_DIR . '/smack-profile.php' );
			}
		}

        // clean system files
        self::each_site( $network_wide, 'Smack_Cache_Disk::clean' );

        // clear site cache of deactivated site
        if ( is_multisite() && ! $network_wide ) {
            self::clear_site_cache_by_blog_id( get_current_blog_id() );
        // clear complete cache otherwise
        } else {
            self::clear_complete_cache();
        }
    }


    /**
     * uninstall hook
     *
     */

    public static function on_uninstall() {

        // uninstall backend requirements
        self::each_site( is_multisite(), 'self::uninstall_backend' );

        // clear complete cache
        self::clear_complete_cache();
    }


    /**
     * install on new site in multisite network
     *
     *
     * @param   WP_Site  $new_site  new site instance
     */

    public static function install_later( $new_site ) {

        // check if network activated
        if ( ! is_plugin_active_for_network( SMACK_BASE ) ) {
            return;
        }

        // switch to blog
        switch_to_blog( (int) $new_site->blog_id );

        // install backend requirements, triggering the settings file to be created
        self::install_backend();

        // restore blog
        restore_current_blog();
    }


    /**
     * install backend requirements
     *
     */

    private static function install_backend() {

        // if old or current database option exists update backend requirements
        if ( get_option( 'smack-cache-enhancer' ) || get_option( 'smack_cache_enhancer' ) ) {
            self::update_backend();
        // add default database option otherwise
        } else {
            $default_settings = self::get_default_settings();
            add_option( 'smack_cache_enhancer', $default_settings );

            // create settings file if action was not fired, like when in activation hook
            if ( ! has_action( 'add_option_cache_enabler' ) ) {
                self::on_update_backend( 'cache_enabler', $default_settings );
            }
        }
    }


    /**
     * update backend requirements
     *
     *
     * @param   $plugin_update     whether or not an update is in progress
     * @return  $new_option_value  new or current database option value
     */

    public static function update_backend( $plugin_update = false ) {

        // check if backend should be updated
        if ( $plugin_update && ! is_plugin_active( SMACK_BASE ) ) {
            return;
        }

        // delete user(s) meta key from deleted publishing action (1.5.0)
        delete_metadata( 'user', 0, '_clear_post_cache_on_update', '', true );

        // rename database option (1.5.0)
        $old_option_value = get_option( 'cache-enabler' );
        if ( $old_option_value !== false ) {
            delete_option( 'cache-enabler' );
            add_option( 'smack_cache_enhancer', $old_option_value );
        }

        // get defined settings, fall back to empty array if not found
        $old_option_value = get_option( 'smack_cache_enhancer', array() );

        // maybe convert old settings to new settings
        $old_option_value = self::convert_settings( $old_option_value );

        // update default system settings
        $old_option_value = wp_parse_args( self::get_default_settings( 'system' ), $old_option_value );

        // merge defined settings into default settings
        $new_option_value = wp_parse_args( $old_option_value, self::get_default_settings() );

        // update database option
        update_option( 'smack_cache_enhancer', $new_option_value );

        // create settings file if action was not fired, like when in upgrade hook or if a database update was unnecessary
        if ( ! has_action( 'update_option_cache_enabler' ) ) {
            self::on_update_backend( $old_option_value, $new_option_value );
        }

        return $new_option_value;
    }


    /**
     * add or update database option hook
     *
     *
     * @param   mixed  $option            old database option value or name of the option to add
     * @param   mixed  $new_option_value  new database option value
     */

    public static function on_update_backend( $option, $new_option_value ) {

        Smack_Cache_Disk::create_settings_file( $new_option_value );
    }


    /**
     * uninstall on deleted site in multisite network
     *
     *
     * @param   WP_Site  $old_site  old site instance
     */

    public static function uninstall_later( $old_site ) {

        $delete_cache_size_transient = false;

        // clean system files
        Smack_Cache_Disk::clean();

        // clear site cache of deleted site
        self::clear_site_cache_by_blog_id( (int) $old_site->blog_id, $delete_cache_size_transient );
    }


    /**
     * uninstall backend requirements
     *
     */

    private static function uninstall_backend() {

        // delete database option
        delete_option( 'smack_cache_enhancer' );
    }


    /**
     * enter each site
     *
     *
     * @param   boolean  $network          whether or not each site in network
     * @param   string   $callback         callback function
     * @param   array    $callback_params  callback function parameters
     * @return  array    $callback_return  returned value(s) from callback function
     */

    private static function each_site( $network, $callback, $callback_params = array() ) {

        $callback_return = array();

        if ( $network ) {
            $blog_ids = self::get_blog_ids();
            // switch to each site in network
            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
                $callback_return[] = (int) call_user_func_array( $callback, $callback_params );
                restore_current_blog();
            }
        } else {
            $callback_return[] = (int) call_user_func_array( $callback, $callback_params );
        }

        return $callback_return;
    }


    /**
     * plugin activation and deactivation hooks
     *
     */

    public static function on_plugin_activation_deactivation() {

        // if setting enabled clear complete cache on any plugin activation or deactivation
        if ( Smack_Cache_Engine::$settings['clear_complete_cache_on_changed_plugin'] ) {
            self::clear_complete_cache();
        }
    }


    /**
     * get settings from database
     *
     *
     * @return  array  $settings  current settings from database
     */

    public static function get_settings() {

        // get database option value
        $settings = get_option( 'smack_cache_enhancer' );

        // if database option does not exist or settings are outdated
        if ( $settings === false ) {
            $settings = self::update_backend();
        }

        return $settings;
    }


    /**
     * get blog IDs
     *
     *
     * @return  array  blog IDs array
     */

    private static function get_blog_ids() {

        global $wpdb;

        return $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
    }


    /**
     * get blog paths
     *
     *
     * @return  array  blog paths array
     */

    private static function get_blog_paths() {

        global $wpdb;

        return $wpdb->get_col( "SELECT path FROM $wpdb->blogs" );
    }


    /**
     * get permalink structure
     *
     *
     * @return  string  permalink structure
     */

    private static function get_permalink_structure() {

        // get permalink structure
        $permalink_structure = get_option( 'permalink_structure' );

        // permalink structure is custom and has a trailing slash
        if ( $permalink_structure && preg_match( '/\/$/', $permalink_structure ) ) {
            return 'has_trailing_slash';
        }

        // permalink structure is custom and does not have a trailing slash
        if ( $permalink_structure && ! preg_match( '/\/$/', $permalink_structure ) ) {
            return 'no_trailing_slash';
        }

        // permalink structure is not custom
        if ( empty( $permalink_structure ) ) {
            return 'plain';
        }
    }


    /**
     * get cache size
     *
     *
     * @return  integer  $size  cache size (bytes)
     */

    public static function get_cache_size() {

        $cache_size = get_transient( self::get_cache_size_transient_name() );

        if ( ! $cache_size ) {
            $cache_size = Smack_Cache_Disk::cache_size();
            set_transient( self::get_cache_size_transient_name(), $cache_size, MINUTE_IN_SECONDS * 15 );
        }

        return $cache_size;
    }


    /**
     * get the cache size transient name
     *
     *
     * @param   integer $blog_id         blog ID
     * @return  string  $transient_name  transient name
     */

    private static function get_cache_size_transient_name( $blog_id = null ) {

        // set blog ID if provided, get current blog ID otherwise
        $blog_id = ( $blog_id ) ? $blog_id : get_current_blog_id();

        $transient_name = 'cache_enabler_cache_size_' . $blog_id;

        return $transient_name;
    }


    /**
     * get the cache cleared transient name used for the clear notice
     *
     *
     * @return  string  $transient_name  transient name
     */

    private static function get_cache_cleared_transient_name() {

        $transient_name = 'smack_cache_enhancer_cache_cleared_' . get_current_user_id();

        return $transient_name;
    }


    /**
     * get default settings
     *
     *
     * @param   string  $settings_type                              default `system` settings
     * @return  array   $system_default_settings|$default_settings  system or all default settings
     */

    private static function get_default_settings( $settings_type = null ) {

        $system_default_settings = array(
            'permalink_structure' => (string) self::get_permalink_structure(),
        );

        if ( $settings_type === 'system' ) {
            return $system_default_settings;
        }

        $user_default_settings = array(
            'cache_expires'                          => 0,
            'cache_expiry_time'                      => 0,
            'clear_complete_cache_on_saved_post'     => 0,
            'clear_complete_cache_on_new_comment'    => 0,
            'clear_complete_cache_on_changed_plugin' => 0,
            'compress_cache'                         => 0,
            'convert_image_urls_to_webp'             => 0,
            'excluded_post_ids'                      => '',
            'excluded_page_paths'                    => '',
            'excluded_query_strings'                 => '',
            'excluded_cookies'                       => '',
            'minify_html'                            => 0,
            'minify_inline_css_js'                   => 0,
        );

        // merge default settings
        $default_settings = wp_parse_args( $user_default_settings, $system_default_settings );

        return $default_settings;
    }


    /**
     * convert settings to new structure
     *
     *
     * @param   array  $settings  settings
     * @return  array  $settings  converted settings if applicable, unchanged otherwise
     */

    private static function convert_settings( $settings ) {

        // check if there are any settings to convert
        if ( empty( $settings ) ) {
            return $settings;
        }

        // updated settings
        if ( isset( $settings['expires'] ) && $settings['expires'] > 0 ) {
            $settings['cache_expires'] = 1;
        }

        if ( isset( $settings['minify_html'] ) && $settings['minify_html'] === 2 ) {
            $settings['minify_html'] = 1;
            $settings['minify_inline_css_js'] = 1;
        }

        // renamed or removed settings
        $settings_names = array(
            'expires'              => 'cache_expiry_time',
            'new_post'             => 'clear_complete_cache_on_saved_post',
            'update_product_stock' => '', // deprecated in 1.5.0
            'new_comment'          => 'clear_complete_cache_on_new_comment',
            'clear_on_upgrade'     => 'clear_complete_cache_on_changed_plugin',
            'compress'             => 'compress_cache',
            'webp'                 => 'convert_image_urls_to_webp',
            'excl_ids'             => 'excluded_post_ids',
            'excl_regexp'          => 'excluded_page_paths', // < 1.4.0
            'excl_paths'           => 'excluded_page_paths',
            'excl_cookies'         => 'excluded_cookies',
            'incl_parameters'      => '', // deprecated in 1.5.0
        );

        foreach ( $settings_names as $old_name => $new_name ) {
            if ( array_key_exists( $old_name, $settings ) ) {
                if ( ! empty( $new_name ) ) {
                    $settings[ $new_name ] = $settings[ $old_name ];
                }
                unset( $settings[ $old_name ] );
            }
        }

        return $settings;
    }


    /**
     * add plugin action links in the plugins list table
     *
     *
     * @param   array  $action_links  action links
     * @return  array  $action_links  updated action links if applicable, unchanged otherwise
     */

    public static function add_plugin_action_links( $action_links ) {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return $action_links;
        }


        return $action_links;
    }




    /**
     * add dashboard cache size count
     *
     * @since   1.0.0
     * @change  1.5.0
     *
     * @param   array  $items  initial array with dashboard items
     * @return  array  $items  merged array with dashboard items
     */

    public static function add_dashboard_cache_size( $items = array() ) {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return $items;
        }

        // get cache size
        $size = self::get_cache_size();

        // display items
        $items = array(
            sprintf(
                '<a href="%s" title="%s">%s %s</a>',
                admin_url( 'options-general.php?page=ultimate-performance-enhancer' ),
                esc_html__( 'Refreshes every 15 minutes', 'smack-cache' ),
                ( empty( $size ) ) ? esc_html__( 'Empty', 'smack-cache' ) : size_format( $size ),
                esc_html__( 'Cache Size', 'smack-cache' )
            )
        );

        return $items;
    }


    /**
     * add admin links
     *
     *
     * @param   object  menu properties
     *
     * @hook    mixed   user_can_clear_cache
     */

    public static function add_admin_links( $wp_admin_bar ) {

        // check user role
        if ( ! is_admin_bar_showing() || ! apply_filters( 'user_can_clear_cache', current_user_can( 'manage_options' ) ) ) {
            return;
        }

        // get clear complete cache button title
        $title = ( is_multisite() && is_network_admin() ) ? esc_html__( 'Clear Network Cache', 'smack-cache' ) : esc_html__( 'Clear Cache', 'smack-cache' );

        // add "Clear Network Cache" or "Clear Cache" button in admin bar
        // $wp_admin_bar->add_menu(
        //     array(
        //         'id'     => 'clear-cache',
        //         'href'   => wp_nonce_url( add_query_arg( array(
        //                         '_cache'  => 'smack-cache',
        //                         '_action' => 'clear',
        //                     ) ), 'cache_enabler_clear_cache_nonce' ),
        //         'parent' => 'top-secondary',
        //         'title'  => '<span class="ab-item">' . $title . '</span>',
        //         'meta'   => array( 'title' => $title ),
        //     )
        // );

        // add "Clear URL Cache" button in admin bar
        // if ( ! is_admin() ) {
        //     $wp_admin_bar->add_menu(
        //         array(
        //             'id'     => 'clear-url-cache',
        //             'href'   => wp_nonce_url( add_query_arg( array(
        //                             '_cache'  => 'smack-cache',
        //                             '_action' => 'clearurl',
        //                         ) ), 'cache_enabler_clear_cache_nonce' ),
        //             'parent' => 'top-secondary',
        //             'title'  => '<span  class="ab-item">' . esc_html__( 'Clear URL Cache', 'smack-cache' ) . '</span>',
        //             'meta'   => array( 'title' => esc_html__( 'Clear URL Cache', 'smack-cache' ) ),
        //         )
        //     );
        // }
    }


    /**
     * process clear cache request
     *
     */

    public static function process_clear_cache_request() {
        $gets   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
        // check if clear cache request
        if ( empty( $gets['_cache'] ) || empty( $gets['_action'] ) || $gets['_cache'] !== 'smack-cache' && ( $gets['_action'] !== 'clear' || $gets['_action'] !== 'clearurl' ) ) {
            return;
        }

        // validate nonce
        if ( empty( $gets['_wpnonce'] ) || ! wp_verify_nonce( $gets['_wpnonce'], 'cache_enabler_clear_cache_nonce' ) ) {
            return;
        }

        // check user role
        if ( ! is_admin_bar_showing() || ! apply_filters( 'user_can_clear_cache', current_user_can( 'manage_options' ) ) ) {
            return;
        }

        // set clear URL without query string and check if installation is in a subdirectory
        $installation_dir = parse_url( home_url(), PHP_URL_PATH );
        $server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
        $clear_url = str_replace( $installation_dir, '', home_url() ) . preg_replace( '/\?.*/', '', $server_array['REQUEST_URI'] );

        // network activated
        if ( is_multisite() ) {
            // network admin
            if ( is_network_admin() && $gets['_action'] === 'clear' ) {
                // clear complete cache
                self::clear_complete_cache();
            // site admin
            } else {
                if ( $gets['_action'] === 'clearurl' ) {
                    // clear specific site URL cache
                    self::clear_page_cache_by_url( $clear_url );
                } elseif ( $gets['_action'] === 'clear' ) {
                    // clear specific site complete cache
                    self::clear_site_cache_by_blog_id( get_current_blog_id() );
                }
            }
        // site activated
        } else {
            if ( $gets['_action'] === 'clearurl' ) {
                // clear URL cache
                self::clear_page_cache_by_url( $clear_url );
            } elseif ( $gets['_action'] === 'clear' ) {
                // clear complete cache
                self::clear_complete_cache();
            }
        }

        // redirect to same page
        wp_safe_redirect( wp_get_referer() );

        // set transient for clear notice
        if ( is_admin() ) {
            set_transient( self::get_cache_cleared_transient_name(), 1 );
        }

        // clear cache request completed
        exit;
    }


    /**
     * admin notice after cache has been cleared
     *
     *
     * @hook    mixed  user_can_clear_cache
     */

    public static function cache_cleared_notice() {
       
        // check user role
        if ( ! is_admin_bar_showing() || ! apply_filters( 'user_can_clear_cache', current_user_can( 'manage_options' ) ) ) {
            return;
        }

        if ( get_transient( self::get_cache_cleared_transient_name() ) ) {
            // echo sprintf(
            //     '<div class="notice notice-success is-dismissible"><p><strong>%s</strong></p></div>',
            //     ( is_multisite() && is_network_admin() ) ? esc_html__( 'Network cache cleared Successfully.', 'smack-cache' ) : esc_html__( 'Cache cleared Successfully.', 'smack-cache' )
            // );

            delete_transient( self::get_cache_cleared_transient_name() );
        }
    }


    /**
     * save post hook
     *
     *
     * @param   integer  $post_id  post ID
     */

    public static function on_save_post( $post_id ) {

        // if any published post type is created or updated
        if ( get_post_status( $post_id ) === 'publish' ) {
            self::clear_cache_on_post_save( $post_id );
        }
    }


    /**
     * post updated hook
     *
     *
     * @param   integer  $post_id      post ID
     * @param   WP_Post  $post_after   post instance following the update
     * @param   WP_Post  $post_before  post instance before the update
     */

    public static function on_post_updated( $post_id, $post_after, $post_before ) {

        // if setting disabled and any published post type author changes
        if ( $post_before->post_author !== $post_after->post_author ) {
            if ( ! Smack_Cache_Engine::$settings['clear_complete_cache_on_saved_post'] ) {
                // clear before the update author archives
                self::clear_author_archives_cache_by_user_id( $post_before->post_author );
            }
        }
    }


    /**
     * trash post hook
     *
     *
     * @param   integer  $post_id  post ID
     */

    public static function on_trash_post( $post_id ) {

        // if any published post type is trashed
        if ( get_post_status( $post_id ) === 'publish' ) {
            $trashed = true;
            self::clear_cache_on_post_save( $post_id, $trashed );
        }
    }


    /**
     * transition post status hook
     *
     *
     * @param   string   $new_status  new post status
     * @param   string   $old_status  old post status
     * @param   WP_Post  $post        post instance
     */

    public static function on_transition_post_status( $new_status, $old_status, $post ) {

        // if any published post type status has changed
        if ( $old_status === 'publish' && in_array( $new_status, array( 'future', 'draft', 'pending', 'private') ) ) {
            self::clear_cache_on_post_save( $post->ID );
        }
    }


    /**
     * clear cache if post comment
     *
     *
     * @param   integer  $comment_id  comment ID
     * @param   mixed    $approved    approval status
     */

    public static function comment_post( $comment_id, $approved ) {

        // check if comment is approved
        if ( $approved === 1 ) {
            // if setting enabled clear complete cache on new comment
            if ( Smack_Cache_Engine::$settings['clear_complete_cache_on_new_comment'] ) {
                self::clear_complete_cache();
            } else {
                self::clear_page_cache_by_post_id( get_comment( $comment_id )->comment_post_ID );
            }
        }
    }


    /**
     * clear cache if edit comment
     *
     *
     * @param   integer  $comment_id  comment ID
     */

    public static function edit_comment( $comment_id ) {

        // if setting enabled clear complete cache on new comment
        if ( Smack_Cache_Engine::$settings['clear_complete_cache_on_new_comment'] ) {
            self::clear_complete_cache();
        } else {
            self::clear_page_cache_by_post_id( get_comment( $comment_id )->comment_post_ID );
        }
    }


    /**
     * clear cache if new comment
     *
     *
     * @param   mixed  $approved  approval status
     * @param   array  $comment
     * @return  mixed  $approved  approval status
     */

    public static function new_comment( $approved, $comment ) {

        // check if comment is approved
        if ( $approved === 1 ) {
            // if setting enabled clear complete cache on new comment
            if ( Smack_Cache_Engine::$settings['clear_complete_cache_on_new_comment'] ) {
                self::clear_complete_cache();
            } else {
                self::clear_page_cache_by_post_id( $comment['comment_post_ID'] );
            }
        }

        return $approved;
    }


    /**
     * clear cache if comment status changes
     *
     *
     * @param   string  $after_status
     * @param   string  $before_status
     * @param   object  $comment
     */

    public static function change_comment( $after_status, $before_status, $comment ) {

        // check if changes occured
        if ( $after_status !== $before_status ) {
            // if setting enabled clear complete cache on new comment
            if ( Smack_Cache_Engine::$settings['clear_complete_cache_on_new_comment'] ) {
                self::clear_complete_cache();
            } else {
                self::clear_page_cache_by_post_id( $comment->comment_post_ID );
            }
        }
    }


    /**
     * clear complete cache
     *
     */

    public static function clear_complete_cache() {

        // clear complete cache
        Smack_Cache_Disk::clear_cache();

        // delete cache size transient
        delete_transient( self::get_cache_size_transient_name() );

        // clear cache post hook
        do_action( 'ce_action_cache_cleared' );
    }


    /**
     * clear complete cache (deprecated)
     *
     */

    public static function clear_total_cache() {

        self::clear_complete_cache();
    }


    /**
     * clear cached pages that might have changed from any new or updated post
     *
     *
     * @param   WP_Post  $post  post instance
     */

    public static function clear_associated_cache( $post ) {

        // clear post type archives
        self::clear_post_type_archives_cache( $post->post_type );

        // clear taxonomies archives
        self::clear_taxonomies_archives_cache_by_post_id( $post->ID );

        if ( $post->post_type === 'post' ) {
            // clear author archives
            self::clear_author_archives_cache_by_user_id( $post->post_author );
            // date archives
            self::clear_date_archives_cache_by_post_id( $post->ID );
        }
    }


    /**
     * clear post type archives page cache
     *
     *
     * @param   string  $post_type  post type
     */

    public static function clear_post_type_archives_cache( $post_type ) {

        // get post type archives URL
        $post_type_archives_url = get_post_type_archive_link( $post_type );

        if ( ! empty( $post_type_archives_url ) ) {
            // clear post type archives page and its pagination page(s) cache
            self::clear_page_cache_by_url( $post_type_archives_url, 'pagination' );
        }
    }


    /**
     * clear taxonomies archives pages cache by post ID
     *
     *
     * @param   integer  $post_id  post ID
     */

    public static function clear_taxonomies_archives_cache_by_post_id( $post_id ) {

        // get taxonomies
        $taxonomies = get_taxonomies();

        foreach ( $taxonomies as $taxonomy ) {
            if ( wp_count_terms( $taxonomy ) > 0 ) {
                // get terms attached to post
                $term_ids = wp_get_post_terms( $post_id, $taxonomy,  array( 'fields' => 'ids' ) );
                foreach ( $term_ids as $term_id ) {
                    $term_archives_url = get_term_link( (int) $term_id, $taxonomy );
                    // validate URL and ensure it does not have a query string
                    if ( filter_var( $term_archives_url, FILTER_VALIDATE_URL ) && ! filter_var( $term_archives_url, FILTER_VALIDATE_URL, FILTER_FLAG_QUERY_REQUIRED ) ) {
                        // clear taxonomy archives page and its pagination page(s) cache
                        self::clear_page_cache_by_url( $term_archives_url, 'pagination' );
                    }
                }
            }
        }
    }


    /**
     * clear author archives page cache by user ID
     *
     *
     * @param   integer  $user_id  user ID of the author
     */

    public static function clear_author_archives_cache_by_user_id( $user_id ) {

        // get author archives URL
        $author_username     = get_the_author_meta( 'user_login', $user_id );
        $author_base         = $GLOBALS['wp_rewrite']->author_base;
        $author_archives_url = home_url( '/' ) . $author_base . '/' . $author_username;

        // clear author archives page and its pagination page(s) cache
        self::clear_page_cache_by_url( $author_archives_url, 'pagination' );
    }


    /**
     * clear date archives pages cache
     *
     *
     * @param   integer  $post_id  post ID
     */

    public static function clear_date_archives_cache_by_post_id( $post_id ) {

        // get post dates
        $post_date_day   = get_the_date( 'd', $post_id );
        $post_date_month = get_the_date( 'm', $post_id );
        $post_date_year  = get_the_date( 'Y', $post_id );

        // get post dates archives URLs
        $date_archives_day_url   = get_day_link( $post_date_year, $post_date_month, $post_date_day );
        $date_archives_month_url = get_month_link( $post_date_year, $post_date_month );
        $date_archives_year_url  = get_year_link( $post_date_year );

        // clear date archives pages and their pagination pages cache
        self::clear_page_cache_by_url( $date_archives_day_url, 'pagination' );
        self::clear_page_cache_by_url( $date_archives_month_url, 'pagination' );
        self::clear_page_cache_by_url( $date_archives_year_url, 'pagination' );
    }


    /**
     * clear page cache by post ID
     *
     *
     * @param   integer|string  $post_id     post ID
     * @param   string          $clear_type  clear the `pagination` or the entire `dir` instead of only the cached `page`
     */

    public static function clear_page_cache_by_post_id( $post_id, $clear_type = 'page'  ) {

        // validate integer
        if ( ! is_int( $post_id ) ) {
            // if string try to convert to integer
            $post_id = (int) $post_id;
            // conversion failed
            if ( ! $post_id ) {
                return;
            }
        }

        // clear page cache
        self::clear_page_cache_by_url( get_permalink( $post_id ), $clear_type );
    }


    /**
     * clear page cache by URL
     *
     *
     * @param   string  $clear_url   full URL to potentially cached page
     * @param   string  $clear_type  clear the `pagination` or the entire `dir` instead of only the cached `page`
     */

    public static function clear_page_cache_by_url( $clear_url, $clear_type = 'page' ) {

        // validate URL
        if ( ! filter_var( $clear_url, FILTER_VALIDATE_URL ) ) {
            return;
        }

        // clear URL
        Smack_Cache_Disk::clear_cache( $clear_url, $clear_type );

        // clear cache by URL post hook
        do_action( 'ce_action_cache_by_url_cleared', $clear_url );
    }


    /**
     * clear site cache by blog ID
     *
     *
     * @param   integer|string  $blog_id                      blog ID
     * @param   boolean         $delete_cache_size_transient  whether or not the cache size transient should be deleted
     */

    public static function clear_site_cache_by_blog_id( $blog_id, $delete_cache_size_transient = true ) {

        // check if network
        if ( ! is_multisite() ) {
            return;
        }

        // validate integer
        if ( ! is_int( $blog_id ) ) {
            // if string try to convert to integer
            $blog_id = (int) $blog_id;
            // conversion failed
            if ( ! $blog_id ) {
                return;
            }
        }

        // check if blog ID exists
        if ( ! in_array( $blog_id, self::get_blog_ids() ) ) {
            return;
        }

        // get clear URL
        $clear_url = get_home_url( $blog_id );

        // network with subdomain configuration
        if ( is_subdomain_install() ) {
            // clear main site or subsite cache
            self::clear_page_cache_by_url( $clear_url, 'dir' );
        // network with subdirectory configuration
        } else {
            // get blog path
            $blog_path = get_blog_details( $blog_id )->path;

            // main site
            if ( $blog_path === '/' ) {
                $blog_paths  = self::get_blog_paths();
                $blog_domain = parse_url( $clear_url, PHP_URL_HOST );
                $glob_path   = Smack_Cache_Disk::$cache_dir . '/' . $blog_domain;

                // get cached page paths
                $page_paths = glob( $glob_path . '/*', GLOB_MARK | GLOB_ONLYDIR );
                foreach ( $page_paths as $page_path ) {
                    $page_path = str_replace( $glob_path, '', $page_path );
                    // if cached page belongs to main site
                    if ( ! in_array( $page_path, $blog_paths ) ) {
                        // clear page cache
                        self::clear_page_cache_by_url( $clear_url . $page_path, 'dir' );
                    }
                }

                // clear home page cache
                self::clear_page_cache_by_url( $clear_url );
            // subsite
            } else {
                // clear subsite cache
                self::clear_page_cache_by_url( $clear_url, 'dir' );
            }
        }

        // delete cache size transient
        if ( $delete_cache_size_transient ) {
            delete_transient( self::get_cache_size_transient_name( $blog_id ) );
        }
    }


    /**
     * clear cache when any post type is created or updated
     *
     *
     * @param   integer  $post_id  post ID
     * @param   boolean  $trashed  whether this is an existing post being trashed
     */

    public static function clear_cache_on_post_save( $post_id, $trashed = false ) {

        // get post data
        $post = get_post( $post_id );

        // if setting enabled clear complete cache
        if ( Smack_Cache_Engine::$settings['clear_complete_cache_on_saved_post'] ) {
            self::clear_complete_cache();
        // clear page and/or associated cache otherwise
        } else {
            // if updated or trashed
            if ( strtotime( $post->post_modified_gmt ) > strtotime( $post->post_date_gmt ) || $trashed ) {
                // clear page cache
                self::clear_page_cache_by_post_id( $post_id );
            }
            // clear associated cache
            self::clear_associated_cache( $post );
        }
    }


    /**
     * check plugin requirements
     *
     */

    public static function requirements_check() {

        // check WordPress version
        if ( version_compare( $GLOBALS['wp_version'], SMACK_MIN_WP . 'alpha', '<' ) ) {
            echo sprintf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    esc_html__( 'The %1$s plugin is optimized for WordPress %2$s. Please disable the plugin or upgrade your WordPress installation (recommended).', 'smack-cache' ),
                    '<strong>All-in-one Performance Accelerator</strong>',
                    SMACK_MIN_WP
                )
            );
        }

        // check permalink structure
        if ( Smack_Cache_Engine::$settings['permalink_structure'] === 'plain' && current_user_can( 'manage_options' ) ) {
            echo sprintf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    esc_html__( 'The %1$s plugin requires a custom permalink structure to start caching properly. Please enable a custom structure in the %2$s.', 'smack-cache' ),
                    '<strong>All-in-one Performance Accelerator</strong>',
                    sprintf(
                        '<a href="%s">%s</a>',
                        admin_url( 'options-permalink.php' ),
                        esc_html__( 'Permalink Settings', 'smack-cache' )
                    )
                )
            );
        }

        // check permissions
        if ( file_exists( Smack_Cache_Disk::$cache_dir ) && ! is_writable( Smack_Cache_Disk::$cache_dir ) ) {
            echo sprintf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    esc_html__( 'The %1$s plugin requires write permissions %2$s in %3$s. Please change the %4$s.', 'smack-cache' ),
                    '<strong>All-in-one Performance Accelerator</strong>',
                    '<code>755</code>',
                    '<code>wp-content/cache</code>',
                    sprintf(
                        '<a href="%s" target="_blank" rel="nofollow noopener">%s</a>',
                        'https://wordpress.org/support/article/changing-file-permissions/',
                        esc_html__( 'file permissions', 'smack-cache' )
                    )
                )
            );
        }

      
    }


    /**
     * register textdomain
     *
     */

    public static function register_textdomain() {

        // load translated strings
        load_plugin_textdomain( 'smack-cache', false, 'smack-cache/lang' );
    }


    /**
     * register settings
     *
     */

    public static function register_settings() {

        register_setting( 'smack_cache', 'smack_cache', array( __CLASS__, 'validate_settings' ) );
    }


    /**
     * validate regex
     *
     *
     * @param   string  $regex            string containing regex
     * @return  string  $validated_regex  string containing regex or empty string if input is invalid
     */

    public static function validate_regex( $regex ) {

        if ( ! empty( $regex ) ) {
            if ( ! preg_match( '/^\/.*\/$/', $regex ) ) {
                $regex = '/' . $regex . '/';
            }

            if ( @preg_match( $regex, null ) === false ) {
                return '';
            }

            $validated_regex = sanitize_text_field( $regex );

            return $validated_regex;
        }

        return '';
    }


    /**
     * validate settings
     *
     *
     * @param   array  $settings            user defined settings
     * @return  array  $validated_settings  validated settings
     */

    public static function validate_settings( $settings ) {

        // validate array
        if ( ! is_array( $settings ) ) {
            return;
        }

        $validated_settings = array(
            'cache_expires'                          => (int) ( ! empty( $settings['cache_expires'] ) ),
            'cache_expiry_time'                      => (int) @$settings['cache_expiry_time'],
            'clear_complete_cache_on_saved_post'     => (int) ( ! empty( $settings['clear_complete_cache_on_saved_post'] ) ),
            'clear_complete_cache_on_new_comment'    => (int) ( ! empty( $settings['clear_complete_cache_on_new_comment'] ) ),
            'clear_complete_cache_on_changed_plugin' => (int) ( ! empty( $settings['clear_complete_cache_on_changed_plugin'] ) ),
            'compress_cache'                         => (int) ( ! empty( $settings['compress_cache'] ) ),
            'convert_image_urls_to_webp'             => (int) ( ! empty( $settings['convert_image_urls_to_webp'] ) ),
            'excluded_post_ids'                      => (string) sanitize_text_field( @$settings['excluded_post_ids'] ),
            'excluded_page_paths'                    => (string) self::validate_regex( @$settings['excluded_page_paths'] ),
            'excluded_query_strings'                 => (string) self::validate_regex( @$settings['excluded_query_strings'] ),
            'excluded_cookies'                       => (string) self::validate_regex( @$settings['excluded_cookies'] ),
            'minify_html'                            => (int) ( ! empty( $settings['minify_html'] ) ),
            'minify_inline_css_js'                   => (int) ( ! empty( $settings['minify_inline_css_js'] ) ),
        );

        // add default system settings
        $validated_settings = wp_parse_args( $validated_settings, self::get_default_settings( 'system' ) );

        // check if cache should be cleared
        if ( ! empty( $settings['clear_complete_cache_on_saved_settings'] ) ) {
            self::clear_complete_cache();
            set_transient( self::get_cache_cleared_transient_name(), 1 );
        }

        return $validated_settings;
    }



}
