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
if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

if ( SAVEQUERIES && property_exists( $GLOBALS['wpdb'], 'save_queries' ) ) {
	$GLOBALS['wpdb']->save_queries = true;
}

	
class QueryInfo
{
	protected static $instance = null,$plugin;
    public $db_objects = array();
    public $id         = 'db_queries';
   
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
       
        
        add_action('wp_ajax_set_query_display', array($this,'set_query_display'));
        add_action('wp_ajax_get_query_selected_tab', array($this,'get_query_selected_tab'));
        $request_action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
		$request_page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
		//if($request_page == 'all-in-one-performance-accelerator'){
            add_action ( 'wp_after_admin_bar_render',array($this,'smack_db_queries'), 999 );  
		//} 
    }

    public function set_query_display(){
        global $wpdb;
        $displayQueryAdmin=sanitize_text_field($_POST['displayAdmin']);
        $displayQueryStatic=sanitize_text_field($_POST['displayStatic']);
        $in_value_check =  $wpdb->get_results( "select display_query from {$wpdb->prefix}aio_asset_table_entery where id = %s  ", 1  );
        $val_update = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}aio_asset_table_entery SET display_query = %s WHERE id = 1",
                $displayQueryAdmin . $displayQueryStatic
            )
        );
    }
    function smack_db_queries () {
        $this->db_objects = apply_filters( 'qm/collect/db_objects', array(
			'$wpdb' => $GLOBALS['wpdb'],
		) );       

        update_option('smack_db_queries','');   
    }

    public function get_query_selected_tab(){
        $tab = sanitize_text_field($_POST['tab']);
        if($tab === 'undefined'){
            $tab_value = get_option('smack_query_tab');
            if(empty($tab_value)){
                $tab_name = 'component';
                update_option('smack_query_tab',$tab_name);
            }else{
                update_option('smack_query_tab',$tab_value);
            }
        }else{
            update_option('smack_query_tab',$tab);
        }
        $tab_address = get_option('smack_query_tab');
        $result['tab'] = $tab_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
    }

   

  

  



}