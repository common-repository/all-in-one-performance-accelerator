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

class DatabaseCleanup
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
        add_action('wp_ajax_get_sacn_result', array($this,'scan_results'));
        add_action('wp_ajax_delete_single_table', array($this,'delete_single_table'));
        add_action('wp_ajax_delete_all_table', array($this,'delete_all_table'));
    }

    function delete_single_table(){
        global $wpdb;
        $db_name = DB_NAME;
        $table = sanitize_text_field($_POST['tablename']);
        $query = "drop table `$db_name`.`$table`";
        $wpdb->query( $query );
        $result['success'] = true;
        echo wp_json_encode($result);
		wp_die();
    }

    function delete_all_table(){
        global $wpdb;
        $db_name = DB_NAME;
        $table=str_replace( "\\", "", sanitize_text_field($_POST['alltablename']));
        $table = json_decode($table,true);
        foreach($table as $tabs){
            $query = "drop table `$db_name`.`$tabs`";
            $wpdb->query( $query );
        }
        $result['success'] = true;
        echo wp_json_encode($result);
		wp_die();
    }

    function get_plugin_list() {
        
        $_SESSION['all-in-one-table-collector'] = null;
        $_SESSION['all-in-one-table-collector']['tables'] = $this->get_tables();
        $plugins = get_plugins();
        $plugins_list = array();
        foreach($plugins as $key=>$plugin) {
            $plugin_short = new \stdClass();
            $key_lc = strtolower( $key ); 
            $plugin_short->key = $key;
            $plugin_short->title = $plugin['Title'];
            $plugin_short->version = $plugin['Version'];
            $plugins_list[] = $plugin_short;
        }
        update_option('scan_details',$plugins_list);
    }

    function get_tables() {
        
        global $wpdb;
        $all_plugins = get_plugins();
        $wp_tables = $wpdb->tables('all', true );
        $non_wp_tables = array();
        $query = 'SHOW TABLES';
        $db_tables = $wpdb->get_col( $query );
        foreach ( $db_tables as $table_name ) {
            if ( in_array( $table_name, $wp_tables ) || 
                strpos( $table_name, $wpdb->prefix, 0)===false ) {
                continue;
            }
            $table = $this->get_values($table_name);
            if ( !empty( $table->plugin_file ) && isset( $all_plugins[ $table->plugin_file ] ) ) {
                $plugin_data = $all_plugins[ $table->plugin_file ];
                $table->plugin_name .= ' '. $plugin_data['Version'];
            }
            $non_wp_tables[] = $table;
        }
        return $non_wp_tables;
    }

    function get_values( $table_name ) {
        global $wpdb;
        
        $query = "SHOW TABLE STATUS FROM `" . DB_NAME . "` LIKE '$table_name'";
        $result = $wpdb->get_results($query);
        $table = new \stdClass;
        $table->name = $table_name;
        $table->name_without_prefix = substr_replace( $table_name, '', 0, strlen( $wpdb->prefix ) );
        $table->records = $result[0]->Rows; 
        $table->kbytes = ROUND( ($result[0]->Data_length + $result[0]->Index_length ) / 1024, 2 ); //$result[0]->kbytes;        
        $table->plugin_name = '';
        $table->plugin_file = '';
        $table->plugin_state = '';
        return $table;
    
    }

    function plugin_name_version(){
        $plugins_list = get_option('scan_details');
        foreach($plugins_list as $plugins){
            $php_files = $this->pgc_get_plugin_php_files( $plugins );    
            $current_file = 0;
            if ( empty( $current_file ) ) {
                $current_file = 0;
            }
                
            $files_to_process = 500;
            for ( $i=$current_file; $i<count( $php_files ); $i++ ) {
                $this->pgc_scan_file( $php_files[$i], $plugins );                
                $files_to_process--;
                if ( $files_to_process<1 ) {
                    break;
                }
            }
            $total_files = count( $php_files );
            $current_file = $i++;
            $answer = array(
                'result'=>'success', 
                'current_file'=>$current_file,
                'total_files'=>$total_files,
                );  
        }
    }

    function pgc_get_plugin_php_files( $plugin ) {   
        
        $all_files = get_plugin_files( $plugin->key );
        $php_files = array();

        foreach ($all_files as $plugin_file) {
            $ext = pathinfo( $plugin_file, PATHINFO_EXTENSION );
            if ( strtolower( $ext )!='php' ) {
                continue;
            }
            $php_files[] = $plugin_file;
        }  
        return $php_files;
    }

    function pgc_scan_file( $file, $plugin ) {
        
        $fh = fopen( WP_PLUGIN_DIR . '/' . $file, 'r' );
        if ( !$fh ) {
            return;
        }
        while ( !feof( $fh ) ) {
            $s = fgets( $fh );
            $s = strtolower( $s );
            foreach( $_SESSION['all-in-one-table-collector']['tables'] as $table){
                if ( strpos( $s, $table->name_without_prefix ) !== false ) {
                    $table->plugin_name = $plugin->title . ' ' . $plugin->version;
                    $table->plugin_file = $plugin->key;
                }
            }
        }
        fclose($fh);
    }

    function scan_results() {
        $get_plugin_list = $this->get_plugin_list();
        $plugin_name_version = $this->plugin_name_version();
        $all_plugins = get_plugins();
        $active_plugins = $this->aio_get_active_plugins();
        foreach ($_SESSION['all-in-one-table-collector']['tables'] as $table) {
            if ($table->plugin_file) {
                $table->plugin_state = $this->aio_get_plugin_state( $all_plugins, $active_plugins, $table->plugin_file );
            } else {
                $table->plugin_state = 'unused';
            }
        }

        $result_scan_value = $_SESSION['all-in-one-table-collector']['tables'];
        echo wp_json_encode($result_scan_value);
		wp_die();

    }

    public function aio_get_active_plugins() {
    
        $list = (array) get_option('active_plugins', array() );
        if ( is_multisite() ) {
            $list2 = get_site_option('active_sitewide_plugins', array() );
            if ( !empty( $list2 ) ) {
                $list = array_merge( $list, array_keys( $list2 ) );
            }
        }
        return $list;
    }

    public function aio_get_plugin_state( $all_plugins,  $active_plugins, $plugin_file ) {
        $plugin_active = false;
        foreach ($active_plugins as $active_plugin) {
            if ($plugin_file == $active_plugin) {
                $plugin_active = true;
                break;
            }
        }
        if ($plugin_active) {
            $plugin_state = 'active';
        } else {
            if ( isset( $all_plugins[$plugin_file] ) ) {
                $plugin_state = 'inactive';
            } else {
                $plugin_state = 'unused';
            }
        }
    
        return $plugin_state;
    }
}
$new_obj = new DatabaseCleanup();
