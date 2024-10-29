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

class Reducecode {
	
    protected static $instance = null,$plugin;

	public static $collection = [];

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
		
		add_action('wp_ajax_get_collect_assets', array($this,'get_collect_assets'));
		add_action('wp_ajax_dequeue_styles',array($this,'dequeue_styles'));
		add_action('wp_ajax_get_asset_selected_tab', array($this,'get_asset_selected_tab'));
			
	}

	public function get_asset_selected_tab(){
		$tab = sanitize_text_field($_POST['tab']);
		if($tab === 'undefined'){
			$tab_value = get_option('smack_asset_tab');
			if(empty($tab_value)){
				$tab_name = 'plugins';
				update_option('smack_asset_tab',$tab_name);
			}else{
				update_option('smack_asset_tab',$tab_value);
			}
		}else{
			update_option('smack_asset_tab',$tab);
		}
		$tab_address = get_option('smack_asset_tab');
		$result['tab'] = $tab_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public function dequeue_styles(){
		global $wpdb;		
		$dequeue_name = sanitize_text_field($_POST['scriptname']);
		$dequeue_checked = sanitize_text_field($_POST['checked']);
		$dequeue_coretype = sanitize_text_field($_POST['coretype']);
		$dequeue_script_type = sanitize_text_field($_POST['script_type']);
		$current_page_url = sanitize_text_field($_POST['url']);
		if($dequeue_script_type == 'core'){
			
			$core_table_name = esc_sql($wpdb->prefix . 'aio_asset_table_core_entery');
			$plugin_table =  $wpdb->get_results( $wpdb->prepare( "select current_page from $core_table_name where script_name =%s and type=%s",$dequeue_name,$dequeue_coretype) );
			foreach($plugin_table as $table){
				$array = unserialize($table->current_page);
				foreach($array as $url_key => $value){
					if($url_key == $current_page_url){
						$array[$url_key] = $dequeue_checked;
					}
				}
			}
			$update_schedule = $wpdb->update( $core_table_name, array(
                'current_page' => serialize($array),
            ), array( 'script_name' => $dequeue_name,'type' => $dequeue_coretype ) );

		}else{

			$core_table_name = esc_sql($wpdb->prefix . 'aio_asset_table_entery');
			$plugin_table =  $wpdb->get_results( $wpdb->prepare( "select current_page from $core_table_name where script_name =%s and type=%s",$dequeue_name,$dequeue_coretype) );
			foreach($plugin_table as $table){
				$array = unserialize($table->current_page);
				foreach($array as $url_key => $value){
					if($url_key == $current_page_url){
						$array[$url_key] = $dequeue_checked;
					}
				}
			}
			$update_schedule = $wpdb->update( $core_table_name, array(
                'current_page' => serialize($array),
            ), array( 'script_name' => $dequeue_name,'type' => $dequeue_coretype ) );
		}
		
		$dequeue_result['result'] = true;
		echo wp_json_encode($dequeue_result);
		wp_die();

	}	

	public function get_collect_assets(){
		$plugin_count = 0;
		$plugin_size = 0;
		$count_size = 0;
		$plugin_collection=get_option('smack_aio_assert_plugin_table');
		$core_collection=get_option('smack_aio_assert_core_table');
		foreach($plugin_collection as $plugins => $plug){
			foreach($plug as $plug_value){
				$plugin_size+= $plug_value->size;
			}
			$plugin_count += count($plugin_collection[$plugins]);
		}
		foreach($core_collection as $core_script => $co_script){
			foreach($co_script as $co){
				$count_size+= $co->size;
			}
		}
		$core_count = count($core_collection);
		$count = $plugin_count + $core_count;
		$total_size = $plugin_size + $count_size;
		$result_asserts_value['plugin_collection'] = $plugin_collection; 
		$result_asserts_value['core_collection'] = $core_collection; 
		$result_asserts_value['total_size'] = $total_size; 
		$result_asserts_value['total_scripts'] = $count; 
		$result_asserts_value['optimized_size'] = $total_size; 
		$result_asserts_value['success'] = true;
		
		echo wp_json_encode($result_asserts_value);
		wp_die();
	}
}