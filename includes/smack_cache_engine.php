<?php
/**
 * All-in-one Performance Accelerator plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Smack_Cache_Engine {

  
    public static function start() {

        if ( self::should_start() ) {
            new self();
        }
    }

   

    public static $started = false;
    public static $settings;

    public function __construct() {


        // get settings from disk in early start if cache exists
        if (Smack_Cache_Disk::cache_exists() ) {
            self::$settings = Smack_Cache_Disk::get_settings();
        // get settings from database in late start otherwise
        } elseif ( class_exists( 'Smack_Cache_Enhancer' ) ) {
            self::$settings = Smack_Cache_Enhancer::get_settings();
            // set deprecated settings
            Smack_Cache_Enhancer::$options = self::$settings;
            Smack_Cache_Enhancer::$options['webp'] = self::$settings['convert_image_urls_to_webp'];
        }

        // check engine status
        if ( ! empty( self::$settings ) ) {
            self::$started = true;
        }
    }


    /**
     * check if engine should start
     * @return  boolean  true if engine should start, false otherwise
     */

    public static function should_start() {

        // check if engine is running already
        if ( self::$started ) {
            return false;
        }
        $server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
        // check if Ajax request in early start
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX && ! class_exists( 'Smack_Cache_Enhancer' ) ) {
            return false;
        }

        // check if REST API request
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return false;
        }

        // check if XMLRPC request
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            return false;
        }

        // check if Host request header is empty
        if ( empty( $server_array['HTTP_HOST'] ) ) {
            return false;
        }

        // check request URI
        if ( str_replace( array( '.ico', '.txt', '.xml', '.xsl' ), '', $server_array['REQUEST_URI'] ) !== $server_array['REQUEST_URI'] ) {
            return false;
        }

        return true;
    }


    /**
     * check if output buffering should start
     *
     *
     * @return  boolean  true if output buffering should start, false otherwise
     */

    public static function should_buffer() {

        if ( self::$started && self::is_index() ) {
            return true;
        }

        return false;
    }


    /**
     * start output buffering
     *
     */

    public static function start_buffering() {

        if ( self::should_buffer() ) {
            ob_start([__CLASS__, 'end_buffering']);

        }
    }


    /**
     * end output buffering and cache page if applicable
     *
     *
     * @param   string   $page_contents  content of a page from the output buffer
     * @param   integer  $phase          bitmask of PHP_OUTPUT_HANDLER_* constants
     * @return  string   $page_contents  content of a page from the output buffer
     *
     * @hook    string   cache_enabler_before_store
     */

    private static function end_buffering( $page_contents, $phase ) {

        if ( $phase & PHP_OUTPUT_HANDLER_FINAL || $phase & PHP_OUTPUT_HANDLER_END ) {
            // if ( ! self::is_cacheable( $page_contents ) || self::bypass_cache() ) {
            //     return $page_contents;
            // }

            $page_contents = apply_filters( 'cache_enabler_before_store', $page_contents );

            Smack_Cache_Disk::cache_page( $page_contents );

            return $page_contents;
        }
    }


    /**
     * check if installation directory index
     *
     *
     * @return  boolean  true if installation directory index, false otherwise
     */

    private static function is_index() {
        $server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
        if ( strtolower( basename( $server_array['SCRIPT_NAME'] ) ) === 'index.php' ) {
            return true;
        }

        return false;
    }


    /**
     * check if page can be cached
     *
     *
     * @param   string   $page_contents  content of a page from the output buffer
     * @return  boolean                  true if page contents are cacheable, false otherwise
     */

    private static function is_cacheable( $page_contents ) {

        $has_html_tag       = ( stripos( $page_contents, '<html' ) !== false );
        $has_html5_doctype  = preg_match( '/^<!DOCTYPE.+html>/i', ltrim( $page_contents ) );
        $has_xsl_stylesheet = ( stripos( $page_contents, '<xsl:stylesheet' ) !== false || stripos( $page_contents, '<?xml-stylesheet' ) !== false );

        if ( $has_html_tag && $has_html5_doctype && ! $has_xsl_stylesheet ) {
            return true;
        }

        return false;
    }


    /**
     * check permalink structure
     *
     *
     * @return  boolean  true if request URI does not match permalink structure or if plain, false otherwise
     */

    private static function is_wrong_permalink_structure() {
        $server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
        // check if trailing slash is set and missing (ignoring root index and file extensions)
        if ( self::$settings['permalink_structure'] === 'has_trailing_slash' ) {
            if ( preg_match( '/\/[^\.\/\?]+(\?.*)?$/', $server_array['REQUEST_URI'] ) ) {
                return true;
            }
        }

        // check if trailing slash is not set and appended (ignoring root index and file extensions)
        if ( self::$settings['permalink_structure'] === 'no_trailing_slash' ) {
            if ( preg_match( '/\/[^\.\/\?]+\/(\?.*)?$/', $server_array['REQUEST_URI'] ) ) {
                return true;
            }
        }

        // check if custom permalink structure is not set
        if ( self::$settings['permalink_structure'] === 'plain' ) {
            return true;
        }

        return false;
    }


    /**
     * check if page is excluded from cache
     *
     *
     * @return  boolean  true if page is excluded from the cache, false otherwise
     */

    private static function is_excluded() {
       
        
        global $wpdb;
        $settings=Smack_Cache_Disk::get_settings();
        // if post Slug excluded
        $server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
        if ( ! empty( $settings['excluded_post_slugs'] )  ) {
           
             $excluded_slugs= explode(',',$settings['excluded_post_slugs']);
            $excluded_id = '';
            foreach ( $excluded_slugs as  $slugs) {  
                $get_post_ids= $wpdb->get_var($wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}posts WHERE post_name=%s", $slugs));
                $excluded_id .= $get_post_ids . ',';
               
            }
            $excluded_id = rtrim($excluded_id, ',');       
            
           
            if ( in_array( get_queried_object_id(), (array) explode( ',', $excluded_id) ) ) {
                return true;
            }
        }

        // if post ID excluded
        if ( ! empty( $settings['excluded_post_ids'] ) && function_exists( 'is_singular' ) && is_singular() ) {
            if ( in_array( get_queried_object_id(), (array) explode( ',', $settings['excluded_post_ids'] ) ) ) {
                return true;
            }
        }

        // if page path excluded
        if ( ! empty($settings['excluded_page_paths'] ) ) {
            $page_path = parse_url( $server_array['REQUEST_URI'], PHP_URL_PATH );

            if ( preg_match($settings['excluded_page_paths'], $page_path ) ) {
                return true;
            }
        }
        $get   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
       
        // if query string excluded
        if ( ! empty( $get ) ) {
            // set regex matching query strings that should bypass the cache
            if ( ! empty( $settings['excluded_query_strings'] ) ) {
                $query_string_regex = $settings['excluded_query_strings'];
            } else {
                $query_string_regex = '/^(?!(fbclid|ref|mc_(cid|eid)|utm_(source|medium|campaign|term|content|expid)|gclid|fb_(action_ids|action_types|source)|age-verified|usqp|cn-reloaded|_ga|_ke)).+$/';
            }

            $query_string = parse_url( $server_array['REQUEST_URI'], PHP_URL_QUERY );

            if ( preg_match( $query_string_regex, $query_string ) ) {
                return true;
            }
        }
        $cookie_array =  filter_input_array(INPUT_COOKIE, FILTER_SANITIZE_STRING);
        // if cookie excluded
        if ( ! empty( $cookie_array ) ) {
            // set regex matching cookies that should bypass the cache
            if ( ! empty( $settings['excluded_cookies'] ) ) {
                $cookies_regex = $settings['excluded_cookies'];
            } else {
                $cookies_regex = '/^(wp-postpass|wordpress_logged_in|comment_author)_/';
            }
            // bypass cache if an excluded cookie is found
            foreach ( $cookie_array as $key => $value) {
                if ( preg_match( $cookies_regex, $key ) ) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * check if mobile template
     *
     *
     * @return  boolean  true if mobile template, false otherwise
     */

    private static function is_mobile() {

        return ( strpos( TEMPLATEPATH, 'wptouch' ) || strpos( TEMPLATEPATH, 'carrington' ) || strpos( TEMPLATEPATH, 'jetpack' ) || strpos( TEMPLATEPATH, 'handheld' ) );
    }


    /**
     * check if cache should be bypassed
     *
     *
     * @return  boolean  true if cache should be bypassed, false otherwise
     *
     * @hook    boolean  bypass_cache
     */

    private static function bypass_cache() {
        $server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
        // bypass cache hook
        if ( apply_filters( 'bypass_cache', false ) ) {
            return true;
        }

        // check request method
        if ( ! isset( $server_array['REQUEST_METHOD'] ) || $server_array['REQUEST_METHOD'] !== 'GET' ) {
            return true;
        }

        // check HTTP status code
        if ( http_response_code() !== 200 ) {
            return true;
        }

        // check WP_CACHE constant
        if ( defined( 'WP_CACHE' ) && ! WP_CACHE ) {
            return true;
        }

        // check DONOTCACHEPAGE constant
        if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
            return true;
        }

        // check conditional tags
        if ( self::is_wrong_permalink_structure() || self::is_excluded() ) {
            return true;
        }

        // check conditional tags when output buffering has ended
        if ( class_exists( 'WP' ) ) {
            if ( is_admin() || is_search() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() || self::is_mobile() ) {
                return true;
            }
        }

        return false;
    }


    /**
     * deliver cache
     *
     */

    public static function deliver_cache() {

        if ( ! self::$started || self::bypass_cache() ) {
            return;
        }

        readfile( Smack_Cache_Disk::get_cache() );
        exit;
    }
}
