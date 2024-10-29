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

class Profiler {
	
    protected static $instance = null,$plugin;
	//public $limit_details = 500;
	private $_core = 0;
	private $_theme = 0;
	private $_runtime = 0;
	private $_plugin_runtime = 0;
	private $_profile = array();
	private $_last_stack = array();
	private $_last_call_time = 0;
	private $_last_call_start = 0;
	private $_last_call_category = '';
	private $_profile_filename = '';
	private $_start_time = 0;
	const CATEGORY_PLUGIN = 1;
	const CATEGORY_THEME = 2;
	const CATEGORY_CORE = 3;
	public static $scan = '';
    public static $profile = '';

	public static function getInstance() {
      
		if ( null == self::$instance ) {
			self::$instance = new self;
			self::$plugin = Plugin::getInstance();
			self::$instance->doHooks();
		}
		return self::$instance;

	}

	public function doHooks(){
		
		add_action('wp_ajax_get_hardware_details', array($this,'get_hardware_details'));
		add_action('wp_ajax_get_profile_selected_tab', array($this,'get_profile_selected_tab'));
		add_action('wp_ajax_get_display_url', array($this,'get_scanning_pages'));
		add_action('wp_ajax_stop_scaning', array($this,'stop_scanning_pages'));
		add_action('wp_ajax_get_latest_profile', array($this,'get_latest_profiles'));
		add_action('wp_ajax_send_Mail_Report', array($this,'send_email_report_content'));
		add_action('wp_ajax_send_Mail', array($this,'send_email'));
		add_action('wp_ajax_get_history_details', array($this,'get_all_profiles'));
		add_action('wp_ajax_clear_log', array($this,'clear_debug_log'));
		add_action('wp_ajax_download_error_log', array($this,'download_error_log'));
		add_action('wp_ajax_view_scan_details', array($this,'view_scan_details'));
		add_action('wp_ajax_delete_scan_details', array($this,'delete_scan_details'));
		add_action('wp_ajax_delete_all_scan_details', array($this,'delete_all_scan_details'));
		add_action('wp_ajax_set_debug_value',array($this,'send_debug_value'));
		
	}
	
	public function get_profile_selected_tab(){
		$tab = sanitize_text_field($_POST['tab']);
		if($tab === 'undefined'){
			$tab_value = get_option('smack_profile_tab');
			if(empty($tab_value)){
				$tab_name = 'runbytime';
				update_option('smack_profile_tab',$tab_name);
			}else{
				update_option('smack_profile_tab',$tab_value);
			}
		}else{
			update_option('smack_profile_tab',$tab);
		}
		$tab_address = get_option('smack_profile_tab');
		$result['tab'] = $tab_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public function send_debug_value(){
		$debug_value = sanitize_text_field($_POST['value']);
		update_option('debug_value',$debug_value);
		$results['success'] = true;
		echo wp_json_encode($results);
		wp_die();
	}
	
	public function get_hardware_details(){
		$cpu_data=$this->get_cpu_data();
		$ram_total=$this->get_ram_total();
		$ram_usage=$this->get_ram_usage();
		$php_ram_usage=$this->get_ram_usage_php();
		$ram_percentage=$this->get_ram_usage_percentage();
		$ram_avialable=$ram_total-$ram_usage['0'];
		$result['CPU_DATA']=$cpu_data['0'];
		$result['RAM_TOTAL']=$ram_total;
		$result['RAM_USAGE']=$ram_usage['0'];
		$result['RAM_PHP']=$php_ram_usage['0'];
		$result['RAM_PERCENTAGE']=$ram_percentage['0'];
		$result['RAM_AVIALABLE']=$ram_avialable;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
		
	}


	public static function isFunctionEnabled( $func ) {
		return is_callable( $func ) && false === stripos( ini_get( 'disable_functions' ), $func );
	}

	public static function isWindows() {
		if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
			return true;
		} else {
			return false;
		}
	}

	public function get_cpu_data() {
		if ($this->isWindows() ) {
			if ($this->isFunctionEnabled( 'shell_exec' ) ) {
				$response = shell_exec( 'wmic cpu get LoadPercentage' );
				$res = explode( " ", trim( $response ) );
				if ( is_array( $response ) && isset( $response[2] ) ) {
					return array( $response[2] );
				}
			}
		} else {
			if ($this->isFunctionEnabled( 'sys_getloadavg' ) ) {
				return sys_getloadavg();
			}
		}
		return null;
	}


    public function get_ram_total() {
		if ( $this->isFunctionEnabled( 'shell_exec' ) ) {
			if ( $this->isWindows() ) {
				$response = shell_exec( 'wmic memorychip get capacity' );
				$response = explode( " ", $response );
				$response = array_sum( $response );
				return number_format( ( $response / 1024 / 1024 / 1024 ), 2 );
			} else {
				$response = shell_exec( 'awk \'/MemTotal/ { print $2 }\' /proc/meminfo' );
				$response = floatval( $response );
				return number_format( ( $response / 1024 / 1024 ), 2 );
			}
		}
		return null;
	}

	public function get_ram_usage() {
		if ( $this->isFunctionEnabled( 'shell_exec' ) ) {
			if ( $this->isWindows() ) {
				$response = shell_exec( 'wmic OS get FreePhysicalMemory /Value' );
				$response = explode( '=', $response );
				if ( is_array( $response ) && isset( $response[1] ) ) {
					$response   = doubleval( number_format( ( doubleval( $response[1] ) / 1024 / 1024 ), 2 ) );
					$total = doubleval( $this->get_ram_total() );
					return array( $total - $response );
				}
			} else {
				$free     = shell_exec( 'free' );
				$free     = (string) trim( $free );
				$free_arr = explode( "\n", $free );

				if ( is_array( $free_arr ) && isset( $free_arr[1] ) ) {
					$memory = explode( " ", $free_arr[1] );
					$memory = array_filter( $memory );
					$memory = array_merge( $memory );

					if ( is_array( $memory ) && isset( $memory[2] ) ) {
						$used = $memory[2] / 1024 / 1024;
						return array( number_format( $used, 4 ) );
					}
				}
			}
		}

		return null;
	}

	public function get_ram_usage_php() {
		$memory = memory_get_peak_usage( true ) / 1024 / 1024;
		return array( number_format( $memory, 2 ) );
	}

	public function get_ram_usage_percentage() {
		if ($this->isFunctionEnabled( 'shell_exec' ) ) {
			if ( $this->isWindows() ) {
				$total = doubleval( $this->get_ram_total() );
				$usage = doubleval( $this->get_ram_usage()[0] );
				$memory_usage = 100 / $total * $usage;
				return array( number_format( $memory_usage, 2 ) );
			} else {
				$free     = shell_exec( 'free' );
				$free     = (string) trim( $free );
				$free_arr = explode( "\n", $free );

				if ( is_array( $free_arr ) && isset( $free_arr[1] ) ) {
					$memory = explode( " ", $free_arr[1] );
					$memory = array_filter( $memory );
					$memory = array_merge( $memory );
					if ( is_array( $memory ) && isset( $memory[1] ) && isset( $memory[2] ) ) {
						$memory_usage = $memory[2] / $memory[1] * 100;
						return array( number_format( $memory_usage, 2 ) );
					}
				}
			}
		}

		return null;
	}

	public static function get_scanning_pages() {
		$filename = sanitize_file_name( basename( $_POST['scanName'] ) );
		define( 'PROFILER_PATH', WP_CONTENT_DIR . '/cache/profiles' );
		@wp_mkdir_p(WP_CONTENT_DIR . '/cache/profiles',0777);
		$page_url = array( get_home_url() ); 
		$words = array_merge( explode( ' ', get_bloginfo( 'name' ) ), explode( ' ', get_bloginfo( 'description' ) ) );
		$page_url[] = home_url( '?s=' . $words[ mt_rand( 0, count( $words ) - 1 ) ] );
		$func = function () { return 'rand()'; };
		add_filter( 'get_terms_orderby', $func );
		$args = array(
			'numberposts' => 5,  // Specify the number of posts you want to retrieve
			// Add any additional arguments as needed
		);
		
		$recent_posts = wp_get_recent_posts($args);
		foreach( $recent_posts as $recent ) {
			$page_url[]= esc_url( get_permalink( $recent['ID'] ) );
			
		}
		// Scan some admin pages, too
		$page_url[] = admin_url();
		$page_url[] = admin_url('edit.php');
		$page_url[] = admin_url('plugins.php');
		$page_url[] = home_url('/'); // Homepage
		$user_id = get_current_user_id();
		$page_url[] = get_author_posts_url($user_id);
		$post_id = get_the_ID();
		// $page_url[] = get_comments_link($post_id);
		$page_url[] = wp_login_url();
		$theme_page_id = get_option('page_for_posts');

		// Check if a posts page is set
		if ($theme_page_id) {
			// Get the permalink of the theme's page
			$page_url[] = get_permalink($theme_page_id);
		} 

		// Fix SSL
		if ( true === force_ssl_admin() ) {
			foreach ( $page_url as $page_key => $page_value ) {
				$page_url[$page_key] = str_replace( 'http://', 'https://', $page_value );
			}
			
		}
		
		$opts = get_option( 'profiler_details' );
		if( empty( $opts ) || !is_array( $opts ) ) {
			$opts = array();
		}
		$opts['profiling_enabled'] = array(
			'name'                 => $filename,
		);

		update_option( 'profiler_details', $opts );

		if ( !file_exists( PROFILER_PATH . "/$filename.json" ) ) {
			$flag = file_put_contents( PROFILER_PATH . "/$filename.json", '' );

		}

		
		$result['scanUrl']=$page_url;
		echo wp_json_encode($result);
		wp_die();
			
	}


	public function stop_scanning_pages(){
		$stop_profiling=sanitize_text_field($_POST['stop_profiling']);
	    if($stop_profiling =='true'){
			$this->disable_scanning();
		}
		delete_option('profiler_details');
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public function __construct()
	{

	}
	
	public function get_latest_profiles(){
		self::$scan = $this->get_latest_profile();
		
		if ( !empty( self::$scan ) ) {
			
			$file = realpath( dirname( __FILE__ ) ) . '/profile-results.php';
			@include_once $file;
				self::$profile = new Profiler_results( self::$scan );
			
			
		} else {
			self::$profile = null;
		}

		$profile_averages = self::$profile->averages;
		$debug_value = get_option('debug_value');
		$result_array['debug_value'] = $debug_value === 'true' ? true : false;
		$result_array['total_time']= isset($profile_averages['total']) ? sprintf('%.4f', $profile_averages['total'] ) : '' ;
		$result_array['site_time']= isset($profile_averages['site']) ? sprintf( '%.4f', $profile_averages['site'] ) : '';
		$result_array['plugin_time']= isset($profile_averages['plugins']) ? sprintf( '%.4f', $profile_averages['plugins'] ) :'' ;
		$result_array['theme_time']= isset($profile_averages['theme']) ? sprintf( '%.4f', $profile_averages['theme'] ) :'';
		$result_array['profile_time']= isset($profile_averages['profile']) ? sprintf( '%.4f', $profile_averages['profile'] ):'';
		$result_array['core_time']= isset($profile_averages['core']) ? sprintf( '%.4f', $profile_averages['core'] ):'';
		$result_array['Errors']= isset($profile_averages['drift']) ? sprintf( '%.4f', $profile_averages['drift'] ):'';
		$result_array['Visits']= number_format( self::$profile->visits );
		$result_array['Php_ticks']= isset($profile_averages['plugin_calls']) ? number_format( $profile_averages['plugin_calls'] ):'';
		$result_array['Memory_usage']= isset($profile_averages['memory']) ? number_format( $profile_averages['memory'] / 1024 / 1024, 2 ):'';
		$result_array['Queries']= isset($profile_averages['queries']) ? round( $profile_averages['queries'] ) :'';
		$result_array['plugins_chart']=self::$profile->plugin_times;
		//$result_array['plugin_load_time']=printf( '%.3f', $profile_averages['plugins'] );
		$result_array['plugin_impact']= isset($profile_averages['plugin_impact']) ? sprintf( '%.1f%%', $profile_averages['plugin_impact'] ):'';
		$result_array['scan_name'] = self::$profile->profile_name;
		$result_array['overAll_breakdowns'] = self::$profile->overAll_breakdowns; 
		$result_array['plugin_load_Times'] = self::$profile->plugin_load_Times; 
		$result_array['theme_load_Times'] = self::$profile->theme_load_Times; 
		$result_array['wordpress_load_Times'] = self::$profile->wordpress_load_Times; 
		$result_array['overAll_page_individual_queries'] = self::$profile->overAll_page_individual_queries; 
		$result_array['success'] = true;
		echo wp_json_encode($result_array);
		wp_die();

	}
	

	public static function get_latest_profile() {
		define( 'PROFILER_PATH', WP_CONTENT_DIR . '/cache/profiles' );
		$dir = opendir( PROFILER_PATH );
		if ( false === $dir ) {
			wp_die( __( 'Cannot read profiles directory', 'smack-profiler' ) );
		}
		$files = array();
		while ( false !== ( $file = readdir( $dir ) ) ) {
			if ( '.json' == substr( $file, -5 ) && filesize( PROFILER_PATH . '/' . $file ) > 0 ) {
				$files[filemtime( PROFILER_PATH . "/$file" )] = PROFILER_PATH . "/$file";
			}
		}
		closedir( $dir );
		if ( empty( $files ) ) {
			return false;
		}
		//returns latest files
		ksort( $files );
		return array_pop( $files );
	}
	
	function disable_scanning() {
		$opts = get_option( 'profiler_details' );
		$path        = WP_CONTENT_DIR . DIRECTORY_SEPARATOR .'cache'. DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR . $opts['profiling_enabled']['name'] . '.json';
		chmod($path, 0777);
		$transient   = get_option( 'smack_'. $opts['profiling_enabled']['name'] );
		
		if ( false === $transient ) {
			$transient = '';
		}
		if ( !empty( $opts ) && array_key_exists( 'profiling_enabled', $opts ) && !empty( $opts['profiling_enabled']['name'] ) ) {
			$put=file_put_contents( $path, $transient );
		}
		delete_option( 'smack_' . $opts['profiling_enabled']['name'], $transient );
		delete_option( 'smack-error_detection' );
		$opts['profiling_enabled'] = false;
		update_option( 'profiler_details', $opts );
	}


	private function debug_log_entry() {
		
		// Get the existing log
		$debug_log = get_option( 'debug_log_value' );
		if ( empty( $debug_log) ) {
			$debug_log = array();
		}
		// Prepend this entry
		array_unshift( $debug_log, $this->_debug_entry );
		if ( count( $debug_log ) >= 100 ) {
			$debug_log = array_slice( $debug_log, 0, 100 );
			$opts = get_option( 'profiler_details' );
			$opts['debug'] = false;
			update_option( 'profiler_details', $opts );
		}
	
		// Write the log
		update_option( 'debug_log_value', $debug_log );
	}

	public function send_email_report_content(){
		$user = wp_get_current_user();
		$response['from']=$user->user_email;
		$response['subject']="Smack Profiler Report for ". get_bloginfo( 'name' ) ;
        $mail_content="H0oray,We profiled your wordpress site with smack profiler and sends reports on that.Please Take a look.";
	    $response['message']=$mail_content;
		$response['success']='true';
		echo wp_json_encode($response);
		wp_die();

	}

	public function send_email(){
		
		if($_POST){
			$mail_to=filter_var($_POST['To'], FILTER_SANITIZE_EMAIL);
			$mail_subject = sanitize_text_field($_POST['Subject']);
			$mail_message = sanitize_text_field($_POST['Message']);
			$mail_result= str_replace(":","<br>",sanitize_text_field($_POST['Result']));
			$mail_results = str_replace("\\","<br>",$mail_result);
			$mail_body=$mail_message.$mail_results;
			$headers = array('Content-Type: text/html; charset=UTF-8');
			$test = wp_mail( $mail_to, $mail_subject, $mail_body, $headers);
			if($test){
				$response['success']='true';
			}else{
				$response['success']='false';
			}
		}

		echo wp_json_encode($response);
		wp_die();
	}

	public function get_all_profiles() {
		$profile_dir = WP_CONTENT_DIR . '/cache/profiles';
		$files          = list_files( $profile_dir );
		$value            = array();
		foreach ( $files as $file ) {
			
			$time  = filemtime( $file );
			$count = count( file( $file ) );
			$key   = basename( $file );
			$name  = substr( $key, 0, -5 ); // strip off .json
			$value[] = array(
				'filename'  => basename( $file ),
				'name'      =>$name,
				'date'      => date( 'D, M jS', $time ) . ' at ' . date( 'g:i a', $time ),
				'count'     => number_format( $count ),
				'filesize'  => $this->get_profile_size( filesize( $file ) ),
			);
		}
		
		echo wp_json_encode($value);
		wp_die();
	}

	public static function get_profile_size( $size ) {
		$units = array(
			_x( 'B',  'Abbreviation for bytes',     'smack-profiler' ),
			_x( 'KB', 'Abbreviation for kilobytes', 'smack-profiler' ),
			_x( 'MB', 'Abbreviation for megabytes', 'smack-profiler' ),
			_x( 'GB', 'Abbreviation for gigabytes', 'smack-profiler' ),
			_x( 'TB', 'Abbreviation for terabytes', 'smack-profiler' )
		);
		$size  = max( $size, 0 );
		$pow   = floor( ( $size ? log( $size ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );
		$size /= pow( 1024, $pow );
		return round( $size, 0 ) . ' ' . $units[$pow];
	}
    
	public function clear_debug_log(){
		update_option( 'debug_log_value', array() );
		$response['success']='true';
		echo wp_json_encode($response);
		wp_die();
	}
	 
	public function view_scan_details(){
		if(isset($_POST)){
			$profile_name=sanitize_text_field($_POST['scanName']);
			$profiler_dir=WP_CONTENT_DIR . '/cache/profiles/';
			$profiler_path=$profiler_dir.$profile_name.'.json';
			if ( !empty($profiler_path) ) {
				
				$file = realpath( dirname( __FILE__ ) ) . '/profile-results.php';
				
				@include_once $file;
					self::$profile = new Profiler_results($profiler_path);
				
			} else {
				self::$profile = null;
			}
	
			$profile_averages = self::$profile->averages;
			
			$result_array['total_time']=sprintf('%.4f', $profile_averages['total'] );
			$result_array['site_time']=sprintf( '%.4f', $profile_averages['site'] );
			$result_array['plugin_time']=sprintf( '%.4f', $profile_averages['plugins'] );
			$result_array['theme_time']=sprintf( '%.4f', $profile_averages['theme'] );
			$result_array['profile_time']=sprintf( '%.4f', $profile_averages['profile'] );
			$result_array['core_time']=sprintf( '%.4f', $profile_averages['core'] );
			$result_array['Errors']=sprintf( '%.4f', $profile_averages['drift'] );
			$result_array['Visits']=number_format( self::$profile->visits );
			$result_array['Php_ticks']=number_format( $profile_averages['plugin_calls'] );
			$result_array['Memory_usage']=number_format( $profile_averages['memory'] / 1024 / 1024, 2 );
			$result_array['Queries']=round( $profile_averages['queries'] );
			$result_array['plugins_chart']=self::$profile->plugin_times;
			//$result_array['plugin_load_time']=printf( '%.3f', $profile_averages['plugins'] );
			$result_array['plugin_impact']=sprintf( '%.1f%%', $profile_averages['plugin_impact'] );
			$result_array['scan_name']=self::$profile->profile_name;
		}
		
		$result_array['success'] = true;
		echo wp_json_encode($result_array);
		wp_die();
	}

	public function delete_scan_details(){
		if(isset($_POST)){
			$profile_name=sanitize_text_field($_POST['scanName']);
			$profiler_dir=WP_CONTENT_DIR . '/cache/profiles/';
			$profiler_path=$profiler_dir.$profile_name.'.json';
			$deleted = unlink($profiler_path);
			if($deleted){
				$result_array['success'] = true;
			}else{
				$result_array['success'] = false;
			}	
		}
		
		echo wp_json_encode($result_array);
		wp_die();
	}

	public function delete_all_scan_details(){
		$path=WP_CONTENT_DIR . '/cache/profiles/';
	
		if ( !file_exists( $path ) )
			return;
		$dir = opendir( $path );
			
		while ( ( $file = readdir( $dir ) ) !== false ) {
			if ( $file != '.' && $file != '..' ) {
				unlink( $path . DIRECTORY_SEPARATOR . $file );
			}
		}
		closedir( $dir );
		rmdir( $path );
		$result_array['success'] = true;
		echo wp_json_encode($result_array);
		wp_die();
	}

	public function download_error_log(){

		$log = get_option( 'debug_log_value' );
		if ( empty( $log ) ) {
			$log = array();
		}
		$new_csv = fopen(WP_CONTENT_DIR . '/cache/smack_debug.csv', 'w');
		// Define the CSV header row
		$header = array(
			"scan_name",
			"url",
			"visitor_ip",
			"time",
			"pid"
		);
		// Write the header row to the CSV file
		fputcsv($new_csv, $header);
		foreach ( (array) $log as $entry ) {
			foreach($entry as $entry_key => $entry_value){
				if(empty($entry_value)){
					unset($entry[$entry_key]);
				}
			}
			$entry['time']=date( 'Y-m-d H:i:s', $entry['time']);
			fputcsv($new_csv, $entry);
		}
  		fclose($new_csv);
		header("Content-type: text/csv");
		header("Content-disposition: attachment; filename = report.csv");
		// readfile(WP_CONTENT_DIR . '/cache/smack_debug.csv');

		$csv_path=content_url(). '/cache/smack_debug.csv';
		$result_array['csv_path']=$csv_path;
		$result_array['success'] = true;
		echo wp_json_encode($result_array);
		wp_die();
	}

}
