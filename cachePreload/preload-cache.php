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


class PreloadCache
{
	protected static $instance = null,$plugin;
	
	public function __construct()
	{
		
		
        
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
		add_action('wp_ajax_save_preload_options', array($this,'preload_options'));
		add_action('wp_ajax_get_preload_options', array($this,'send_preload_options'));
		add_action('wp_ajax_get_preload_selected_tab', array($this,'get_preload_selected_tab'));
		
	}

	public function get_preload_selected_tab(){
		$tab = sanitize_text_field($_POST['tab']);
		if($tab === 'undefined'){
			$tab_value = get_option('smack_preload_tab');
			if(empty($tab_value)){
				$tab_name = 'activepreload';
				update_option('smack_preload_tab',$tab_name);
			}else{
				update_option('smack_preload_tab',$tab_value);
			}
		}else{
			update_option('smack_preload_tab',$tab);
		}
		$tab_address = get_option('smack_preload_tab');
		$result['tab'] = $tab_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public function preload_options(){
		
		if(isset($_POST)){
			$activate_preloading = sanitize_text_field($_POST['activate_preloading']);
			$activate_sitemap_based_cache = sanitize_text_field($_POST['activate_sitemap_based_cache']);
			$prefetch_dns_requests = esc_url_raw($_POST['prefetch_dns_requests']);
			update_option('smack_activate_preloading',$activate_preloading);
			update_option('smack_activate_sitemap_preloading',$activate_sitemap_based_cache);
			update_option('smack_dns_prefetch',$prefetch_dns_requests);

		}
		
		$result['success'] = true;
		
		echo wp_json_encode($result);
		wp_die();
	}


		

	public function send_preload_options(){
		$activate_preloading=get_option('smack_activate_preloading');
		$preload_error=get_option('smack_preload_error');
        $result['activate_preloading']=$activate_preloading=== 'true'? true: false;
		$result['preload_error']=$preload_error;
		if(empty(get_option('smack_dns_prefetch'))||get_option('smack_dns_prefetch')=== 'false'){
			$result['prefetch_dns_requests']='';
		}else{
			$result['prefetch_dns_requests']=get_option('smack_dns_prefetch');
		}
		$result['success'] = true;
        echo wp_json_encode($result);
        wp_die();
	}


	
	
}
$new_obj = new PreloadCache();