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

	
	class BrowserCache{

		
		public $htaccess_file;

	
		public $unique_string;

		
		public $htaccess_code;

	
		public $valid;

		
		public $pattern;

	
		public $message;

	
        public $custom_link;
        
        protected static $instance = null,$plugin;

		
		public function __construct() {

            $browser_cache='true';
             if($browser_cache=='true'){
                $this->caching_code_to_add();
             }else{
                $this->remove_htaccess_code();
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
			add_action('wp_ajax_save_advanced_rules_options', array($this,'advanced_rules_options'));
			add_action('wp_ajax_get_advanced_rules_options', array($this,'send_advanced_rules_options'));
			add_action('wp_ajax_get_advancerule_selected_tab', array($this,'get_advancerule_selected_tab'));
        }
		
		public function get_advancerule_selected_tab(){
			$tab = sanitize_text_field($_POST['tab']);
			if($tab === 'undefined'){
				$tab_value = get_option('smack_advance_tab');
				if(empty($tab_value)){
					$tab_name = 'cachequerystrings';
					update_option('smack_advance_tab',$tab_name);
				}else{
					update_option('smack_advance_tab',$tab_value);
				}
			}else{
				update_option('smack_advance_tab',$tab);
			}
			$tab_address = get_option('smack_advance_tab');
			$result['tab'] = $tab_address;
			$result['success'] = true;
			echo wp_json_encode($result);
			wp_die();
		}

		public function advanced_rules_options(){
			
			if(isset($_POST)){
			 	$never_cache_path = sanitize_text_field($_POST['excluded_page_paths']);
				$never_cache_ids = sanitize_text_field($_POST['excluded_post_ids']);
				$never_cache_slugs = sanitize_text_field($_POST['excluded_post_slugs']);
				$cache_query_strings = sanitize_text_field($_POST['excluded_query_strings']);
				$never_cache_cookies = sanitize_text_field($_POST['excluded_cookies']);
				update_option('smack_never_cache_path',$never_cache_path);
				update_option('smack_never_cache_ids',$never_cache_ids);
				update_option('smack_never_cache_slugs',$never_cache_slugs);
				update_option('smack_cache_query_strings',$cache_query_strings);
				update_option('smack_never_cache_cookies',$never_cache_cookies);
			}
			$result['success'] = true;
			ClearCache::get_setting();
			echo wp_json_encode($result);
			wp_die();
		}

		public function send_advanced_rules_options(){
			$never_cache_path=get_option('smack_never_cache_path');
			$never_cache_ids=get_option('smack_never_cache_ids');
			$never_cache_slugs=get_option('smack_never_cache_slugs');
			$cache_query_strings=get_option('smack_cache_query_strings');
			$never_cache_cookies=get_option('smack_never_cache_cookies');
			$result['excluded_page_paths']= !empty($never_cache_path) ? $never_cache_path : '';
			$result['excluded_post_ids']= !empty($never_cache_ids) ? $never_cache_ids : '';
			$result['excluded_post_slugs']= !empty($never_cache_slugs) ? $never_cache_slugs : '';
			$result['excluded_query_strings']= !empty($cache_query_strings) ? $cache_query_strings : '';
			$result['excluded_cookies']= !empty($never_cache_cookies) ? $never_cache_cookies : '';
			$result['success'] = true;
			echo wp_json_encode($result);
			wp_die();
		}



        public function caching_code_to_add() {
            $this->htaccess_file = wp_normalize_path( ABSPATH . '.htaccess' );

		
			if ( file_exists( $this->htaccess_file ) ) {

				if ( is_readable( $this->htaccess_file ) && is_writable( $this->htaccess_file ) ) {

					
					$this->unique_string 	= 'LBROWSERCSTART';
					$this->htaccess_code 	= file_get_contents( $this->htaccess_file );
					$this->valid 			= false;

					if ( strpos( $this->htaccess_code, $this->unique_string ) !== false ) {
						$this->valid = true;
					}

					if ( ! $this->valid ) {
						
						$this->htaccess_code = $this->htaccess_code . $this->code_to_add();

						file_put_contents( $this->htaccess_file, $this->htaccess_code );
						
					}
				} else {
					add_action( 'admin_notices', array( $this, 'no_htaccess_access_notice' ) );
				}
			} else {
				add_action( 'admin_notices', array( $this, 'no_htaccess_notice' ) );
			}

        }
		
		public function remove_htaccess_code() {

			$this->htaccess_file = wp_normalize_path( ABSPATH . '.htaccess' );

		
			if ( file_exists( $this->htaccess_file ) ) {

				
				if ( is_readable( $this->htaccess_file ) && is_writable( $this->htaccess_file ) ) {

				
					$this->unique_string 	= 'LBROWSERCSTART';
					$this->htaccess_code 	= file_get_contents( $this->htaccess_file );
					$this->valid 			= false;

					if ( strpos( $this->htaccess_code, $this->unique_string ) !== false ) {
						$this->valid = true;
					}

					if ( $this->valid ) {

						// Code found, remove them.
						$this->pattern 			= '/#\s?LBROWSERCSTART.*?LBROWSERCEND/s';
						$this->htaccess_code 	= preg_replace( $this->pattern, '', $this->htaccess_code );
						$this->htaccess_code 	= preg_replace( "/\n+/","\n", $this->htaccess_code );

						file_put_contents( $this->htaccess_file, $this->htaccess_code );
						// Bye Bye.
					}
				} 
			} 
		}

		/**
		 * Codes to be add.
		 */
		public function code_to_add() {
			$this->htaccess_code  = "\n";
			$this->htaccess_code .= '# LBROWSERCSTART Browser Caching' . "\n";
			$this->htaccess_code .= '<IfModule mod_expires.c>' . "\n";
			$this->htaccess_code .= 'ExpiresActive On' . "\n";
			$this->htaccess_code .= 'ExpiresByType image/gif "access 1 year"' . "\n";
			$this->htaccess_code .= 'ExpiresByType image/jpg "access 1 year"' . "\n";
			$this->htaccess_code .= 'ExpiresByType image/jpeg "access 1 year"' . "\n";
			$this->htaccess_code .= 'ExpiresByType image/png "access 1 year"' . "\n";
			$this->htaccess_code .= 'ExpiresByType image/x-icon "access 1 year"' . "\n";
			$this->htaccess_code .= 'ExpiresByType text/css "access 1 month"' . "\n";
			$this->htaccess_code .= 'ExpiresByType text/javascript "access 1 month"' . "\n";
			$this->htaccess_code .= 'ExpiresByType text/html "access 1 month"' . "\n";
			$this->htaccess_code .= 'ExpiresByType application/javascript "access 1 month"' . "\n";
			$this->htaccess_code .= 'ExpiresByType application/x-javascript "access 1 month"' . "\n";
			$this->htaccess_code .= 'ExpiresByType application/xhtml-xml "access 1 month"' . "\n";
			$this->htaccess_code .= 'ExpiresByType application/pdf "access 1 month"' . "\n";
			$this->htaccess_code .= 'ExpiresByType application/x-shockwave-flash "access 1 month"' . "\n";
			$this->htaccess_code .= 'ExpiresDefault "access 1 month"' . "\n";
			$this->htaccess_code .= '</IfModule>' . "\n";
			$this->htaccess_code .= '# END Caching LBROWSERCEND' . "\n";

			return $this->htaccess_code;
		}

		/**
		 * If htaccess is not exists.
		 */
		public function no_htaccess_notice() {
			$this->message = '<div class="error"><p>';
			$this->message .= __( 'Plugin Leverage Browser Caching: htaccess file not found. This plugin works only for Apache server. If you are using Apace server, please create it.', 'lbrowserc' );
			$this->message .= '</p></div>';
			echo wp_kses_post( $this->message );
		}

		/**
		 * If htaccess is not access able.
		 */
		public function no_htaccess_access_notice() {
			$this->message = '<div class="error"><p>';
			$this->message .= __( 'Plugin Leverage Browser Caching: htaccess file is not readable or writable. Please change permission of htaccess file.', 'lbrowserc' );
			$this->message .= '</p></div>';
			echo wp_kses_post( $this->message );
		}

		/**
		 * Call back for action links.
		 *
		 * @param array $actions links.
		 */
		public function plugin_action_links( $actions ) {
			$this->custom_link = array(
				'configure' => sprintf( '<a target="_blank" href="%s">%s</a>', 'https://www.paypal.me/RinkuYadav', __( 'Donate to Author', 'lbrowserc' ) ),
				);
			return array_merge( $this->custom_link, $actions );
		}

	}

