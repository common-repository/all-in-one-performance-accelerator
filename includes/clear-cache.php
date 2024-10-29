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

/**
 * Core class.
 *
 * @package 	Leverage Browser Caching
 */

	
	class ClearCache{

      
	
        
        protected static $instance = null,$plugin;
       
        var $post_id;
        var $options;
        var $ob_started = false;
        private static $settings_dir = WP_CONTENT_DIR . '/settings/smack-cache';
       // const MOBILE_AGENTS = 'android|iphone|iemobile|up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|ipod|xoom|blackberry';
		
		public function __construct() {
            $plugin_cache=get_option('smack_enable_plugin_cache');
            $comments_cache=get_option('smack_enable_comments_cache');
            $complete_cache=get_option('smack_complete_cache');
            if($plugin_cache === 'true'){
                add_action( 'activated_plugin', array( $this, 'on_plugin_cache' ), 10, 2 );
                add_action( 'deactivated_plugin', array( $this, 'on_plugin_cache' ), 10, 2 );
            }  
            if($comments_cache === 'true'){
                add_action( 'pre_comment_approved', array( $this, 'on_plugin_cache' ), 99, 2 ); 
            }  
            if($complete_cache === 'true'){
                add_action( 'save_post', array( $this, 'on_transition_post_status' ), 10, 3 );               
            }

            $reduce_life_span=get_option('smack_cache_time');
            if(!empty($reduce_life_span)){
                if ( ! wp_next_scheduled( 'smack_cache_schedule_event' ) ) {
                    wp_schedule_event( time(), 'preload_timing', 'smack_cache_schedule_event');
                }
            }else{
                wp_clear_scheduled_hook( 'smack_cache_schedule_event' );
            }
		}

        public static function getInstance() {
            if ( null == self::$instance ) {
                self::$instance = new self;
                self::$plugin = Plugin::getInstance();
                self::$instance->doHooks();
            }
        return self::$instance;
        }
    
        
        public function doHooks(){
            add_action('wp_ajax_save_cache_options', array($this,'cache_options'));
            add_action('wp_ajax_get_cache_selected_tab', array($this,'get_cache_selected_tab'));
            add_action('wp_ajax_get_cache_options', array($this,'send_cache_options'));
            $user_cache=get_option('smack_user_cache');
            if($user_cache== 'true'){
            
            add_action( 'admin_notices', array(__CLASS__,'admin_notice'),1);
            }
        }

        public static function on_transition_post_status( $post ) {
                self::on_plugin_cache();
        }
  
        public function on_plugin_cache(){
            $adminbar_instance = new adminbarFunction();
            $adminbar_instance->smack_clean_domain('');
            $adminbar_instance->run_smack_bot( 'cache-preload', '' );
        }

          
        public function get_cache_selected_tab(){
            $tab = sanitize_text_field($_POST['tab']);
            if($tab === 'undefined'){
                $tab_value = get_option('smack_cache_tab');
                if(empty($tab_value)){
                    $tab_name = 'completecache';
                    update_option('smack_cache_tab',$tab_name);
                }else{
                    update_option('smack_cache_tab',$tab_value);
                }
            }else{
                update_option('smack_cache_tab',$tab);
            }
            $tab_address = get_option('smack_cache_tab');
            $result['tab'] = $tab_address;
            $result['success'] = true;
            echo wp_json_encode($result);
            wp_die();
        }

        function cache_options(){
         
            if(isset($_POST)){
                
                $complete_cache = sanitize_text_field($_POST['clear_complete_cache_on_saved_post']);
                $comments_cache = sanitize_text_field($_POST['clear_complete_cache_on_new_comment']);
                $plugin_cache = sanitize_text_field($_POST['clear_complete_cache_on_changed_plugin']);
                $compress_cache = sanitize_text_field($_POST['compress_cache']);
                $webp_cache = sanitize_text_field($_POST['convert_image_urls_to_webp']);
                $mobile_cache = sanitize_text_field($_POST['enale_mobile_cache']);
                $reduce_life_span = intval($_POST['reduce_life_span']);
                $reduce_life_span_selected_option = intval($_POST['reduce_life_span_selected_option']);
                update_option('smack_complete_cache',$complete_cache);
                update_option('smack_enable_comments_cache',$comments_cache);
                update_option('smack_enable_plugin_cache',$plugin_cache);
                update_option('smack_compress_cache',$compress_cache);
                update_option('smack_webp_cache',$webp_cache);
                update_option('smack_cache_time',$reduce_life_span);
                update_option('smack_cache_life_span',$reduce_life_span_selected_option);
                update_option('smack_enable_mobile_cache',$mobile_cache);
    
            }
            $result['success'] = true;
            self::get_setting();
		echo wp_json_encode($result);
		wp_die();
        }

        public function send_cache_options(){
            $complete_cache=get_option('smack_complete_cache');
            $comments_cache=get_option('smack_enable_comments_cache');
            $plugin_cache=get_option('smack_enable_plugin_cache');
            $compress_cache=get_option('smack_compress_cache');
            $webp_cache=get_option('smack_webp_cache');
            $mobile_cache=get_option('smack_enable_mobile_cache');
            $reduce_life_span=get_option('smack_cache_time');
            $reduce_life_span_selected_option=get_option('smack_cache_life_span');
            $result['clear_complete_cache_on_saved_post']=$complete_cache=== 'true'? true: false;
            $result['clear_complete_cache_on_new_comment']=$comments_cache=== 'true'? true: false;
            $result['clear_complete_cache_on_changed_plugin']=$plugin_cache=== 'true'? true: false;
            $result['compress_cache']=$compress_cache=== 'true'? true: false;
            $result['convert_image_urls_to_webp']=$webp_cache=== 'true'? true: false;
            $result['mobilecache']=$mobile_cache=== 'true'? true: false;
            $result['reduce_life_span']=(int)$reduce_life_span;
            $result['reduce_life_span_selected_option']=$reduce_life_span_selected_option;
            $result['success'] = true;
            echo wp_json_encode($result);
            wp_die();
        }


        public static function get_setting() {
            $complete_cache= get_option('smack_complete_cache');
            $comments_cache=get_option('smack_enable_comments_cache');
            $plugin_cache=get_option('smack_enable_plugin_cache');
            $compress_cache=get_option('smack_compress_cache');
            $webp_cache=get_option('smack_webp_cache');
            $reduce_life_span=get_option('smack_cache_time');
            $never_cache_path=get_option('smack_never_cache_path');
            $never_cache_ids=get_option('smack_never_cache_ids');
            $never_cache_slugs=get_option('smack_never_cache_slugs');
            $cache_query_strings=get_option('smack_cache_query_strings');
            $never_cache_cookies=get_option('smack_never_cache_cookies'); 
            if(!empty($reduce_life_span)&&$reduce_life_span!=0){
                $cache_expires='1';
            }else{
                $cache_expires='0';
            }
            $settings['clear_complete_cache_on_saved_post']=$complete_cache=== 'true'? 1: 0;
            $settings['clear_complete_cache_on_new_comment']=$comments_cache=== 'true'? 1: 0;
            $settings['clear_complete_cache_on_changed_plugin']=$plugin_cache=== 'true'? 1: 0;
            $settings['compress_cache']=$compress_cache=== 'true'? 1: 0;
            $settings['convert_image_urls_to_webp']=$webp_cache=== 'true'? 1: 0;
            $settings['cache_expiry_time']=(int)$reduce_life_span;
            $settings['excluded_page_paths']= !empty($never_cache_path) ? $never_cache_path : '';
            $settings['excluded_post_ids']= !empty($never_cache_ids) ? $never_cache_ids : '';
            $settings['excluded_post_slugs']= !empty($never_cache_slugs) ? $never_cache_slugs : '';
            $settings['excluded_query_strings']= !empty($cache_query_strings) ? $cache_query_strings : '';
            $settings['excluded_cookies']= !empty($never_cache_cookies) ? $never_cache_cookies : '';
            $settings['permalink_structure']=(string) self::get_permalink_structure();
            $settings['cache_expires']=$cache_expires;
            $settings['minify_html']='0';
            $settings['minify_inline_css_js']='0';
            update_option('smack_settings_info',$settings);
            $settings_file = self::get_settings_file();    
       $new_settings_file_contents  = '<?php' . PHP_EOL;
       $new_settings_file_contents .= '/**' . PHP_EOL;
       $new_settings_file_contents .= ' * Cache settings for ' . home_url() . PHP_EOL;
       $new_settings_file_contents .= ' *' . PHP_EOL;
       $new_settings_file_contents .= ' * @generated  ' . date_i18n( 'd.m.Y H:i:s', current_time( 'timestamp' ) ) . PHP_EOL;
       $new_settings_file_contents .= ' */' . PHP_EOL;
       $new_settings_file_contents .= PHP_EOL;
       $new_settings_file_contents .= 'return ' . var_export( $settings, true ) . ';';

       file_put_contents($settings_file, $new_settings_file_contents );
        }


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

        private static function get_settings_file( $fallback_for_sub_install = false, $fallback_for_sub_network = false ) {

            // single site not in a subdirectory, any site of subdomain network, or main site of subdirectory network (fallback)
            $blog_path = '';
            $server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
            // subdirectory network or subdirectory installation (fallback)
            if ( $fallback_for_sub_install || is_multisite() && defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL && ! $fallback_for_sub_network ) {
                if ( function_exists( 'home_url' ) ) {
                    $url_path = parse_url( home_url( '/' ), PHP_URL_PATH ); // trailing slash required
                } else {
                    $url_path = $server_array['REQUEST_URI'];
                }
    
                $url_path_pieces = explode( '/', $url_path, 3 );
                $blog_path = $url_path_pieces[1];
    
                if ( ! empty( $blog_path ) ) {
                    $blog_path = '.' . $blog_path;
                }
            }
            self::smack_mkdir_p(self::$settings_dir);
            // get settings file
            $settings_file = sprintf(
                '%s/%s.php',
                self::$settings_dir,
                parse_url( ( function_exists( 'home_url' ) ) ? home_url() : 'http://' . strtolower( $server_array['HTTP_HOST'] ), PHP_URL_HOST ) . $blog_path
            );
    
            return $settings_file;
        }

        public function smack_mkdir( $dir ) {
			 $chmod = self::smack_get_filesystem_perms( 'dir' );
			 return self::smack_direct_filesystem()->mkdir( $dir, $chmod );
		}

        public function smack_direct_filesystem() {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
			return new \WP_Filesystem_Direct( new \StdClass() );
		}

        public function smack_get_filesystem_perms( $type ) {
			static $perms = [];
		
			// Allow variants.
			switch ( $type ) {
				case 'dir':
				case 'dirs':
				case 'folder':
				case 'folders':
					$type = 'dir';
					break;
		
				case 'file':
				case 'files':
					$type = 'file';
					break;
		
				default:
					return 0755;
			}
		
			if ( isset( $perms[ $type ] ) ) {
				return $perms[ $type ];
			}
		
			// If the constants are not defined, use fileperms() like WordPress does.
			switch ( $type ) {
				case 'dir':
					if ( defined( 'SMACK_FS_CHMOD_DIR' ) ) {
						$perms[ $type ] = SMACK_FS_CHMOD_DIR;
					} else {
						$perms[ $type ] = fileperms( ABSPATH ) & 0777 | 0755;
					}
					break;
		
				case 'file':
					if ( defined( 'SMACK_FS_CHMOD_FILE' ) ) {
						$perms[ $type ] = SMACK_FS_CHMOD_FILE;
					} else {
						$perms[ $type ] = fileperms( ABSPATH . 'index.php' ) & 0777 | 0644;
					}
			}
			return $perms[ $type ];
		}

        public function smack_mkdir_p( $target ) {

            // from php.net/mkdir user contributed notes.
            $target = str_replace( '//', '/', $target );
        
            // safe mode fails with a trailing slash under certain PHP versions.
            $target = untrailingslashit( $target );
            if ( empty( $target ) ) {
                $target = '/';
            }
        
            if ( self::smack_direct_filesystem()->exists( $target ) ) {
                return self::smack_direct_filesystem()->is_dir( $target );
            }
        
            // Attempting to create the directory may clutter up our display.
            if ( self::smack_mkdir( $target ) ) {
                return true;
            } elseif ( self::smack_direct_filesystem()->is_dir( dirname( $target ) ) ) {
                return false;
            }
        
            // If the above failed, attempt to create the parent node, then try again.
            if ( ( '/' !== $target ) && ( self::smack_mkdir_p( dirname( $target ) ) ) ) {
                return self::smack_mkdir_p( $target );
            }
        
            return false;
        }        

	}

    $new_obj = new ClearCache();