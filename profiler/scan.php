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

class Scan {
	
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
		
	}
	
	public function __construct()
	{
		$this->_PROFILER_PATH      = realpath( dirname( __FILE__ ) );
		
		// Debug mode
		$this->_debug_entry = array(
			'profiling_enabled'  => false,
			'recording_ip'       => '',
			'scan_name'          => '',
			'recording'          => false,
			'disable_optimizers' => false,
			'url'                => $this->getting_loaded_url(),
			'visitor_ip'         => $this->get_profiler_ip(),
			'time'               => time(),
			'pid'                => getmypid()
		);
		// Check to see if we should profile
		$opts = array();
		if ( function_exists( 'get_option') ) {
			$opts = get_option('profiler_details' );
			if ( !empty( $opts['profiling_enabled'] ) ) {
				
				$recording_ip=$this->get_profiler_ip();
					$this->_debug_entry['scan_name']          = $opts['profiling_enabled']['name'];
					//$recording_ip       = $opts['profiling_enabled']['ip'];
			}
		}
		register_shutdown_function( array( $this, 'shutdown_function' ) );
		$flag = get_option( 'smack-error_detection' );
		if ( !empty( $flag ) && $flag > time() + 60 ) {
			$this->disable_scanning();
			return $this;
		}

		// Set the error detection flag
		if ( empty( $flag ) ) {
			update_option( 'smack-error_detection', time() );
		}

		// Kludge memory limit / time limit
		if ( (int) @ini_get( 'memory_limit' ) < 256 ) {
			@ini_set( 'memory_limit', '256M' );
		}
		@set_time_limit( 90 );

		// Set the profile file
		$this->_profile_filename = $opts['profiling_enabled']['name'] . '.json';
		
		// Start timing
		$this->_start_time      = microtime( true );
		$this->_last_call_start = microtime( true );
		// Reset state
		$this->_last_call_time     = 0;
		$this->_runtime            = 0;
		$this->_plugin_runtime     = 0;
		$this->_core               = 0;
		$this->_theme              = 0;
		$this->_last_call_category = self::CATEGORY_CORE;
		$this->_last_stack         = array();

		// Add some startup information
		$this->_profile = array(
			'url'   => $this->getting_loaded_url(),
			'ip'    => $this->get_profiler_ip(),
			'pid'   => getmypid(),
			'date'  => @date( 'c' ),
			'stack' => array()
		);
		

		// Monitor all function-calls
		declare( ticks = 1 );
		require __DIR__.'/profile-stream.php';
		FileStreamWrapper::init();
		register_tick_function( array( $this, 'ticker_function' ) );
	}

	public function ticker_function() {
		static $theme_files_cache = array();         // Cache for theme files
		static $content_folder = '';
		if ( empty( $content_folder ) ) {
			$content_folder = basename( WP_CONTENT_DIR );
		}
		$themes_folder = 'themes';

		// Start timing time spent in the profiler
		$start = microtime( true );

		// Calculate the last call time
		$this->_last_call_time = ( $start - $this->_last_call_start );

		if ( self::CATEGORY_PLUGIN == $this->_last_call_category && array() !== $this->_last_stack ) {
			// Write the stack to the profile
			$this->_plugin_runtime += $this->_last_call_time;

			// Add this stack to the profile
			$this->_profile['stack'][] = array(
				'plugin'  => $this->_last_stack['plugin'],
				'runtime' => $this->_last_call_time,
			);

			// Reset the stack
			$this->_last_stack = array();
		} elseif ( self::CATEGORY_THEME == $this->_last_call_category ) {
			$this->_theme += $this->_last_call_time;
		} elseif ( self::CATEGORY_CORE == $this->_last_call_category ) {
			$this->_core += $this->_last_call_time;
		}

		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, 2 );
		
        // Find our function
		$frame = $trace[0];
		if ( isset( $trace[1] ) ){
			$frame = $trace[1];

		$lambda_file = isset( $trace[0]['file'][0] ) ? $trace[0]['file'] : '';
        }
		// Free up memory
		unset( $trace );

		// Include/require
		if ( in_array( strtolower( $frame['function'] ), array( 'include', 'require', 'include_once', 'require_once' ) ) ) {
			$file = $frame['args'][0];

		// Object instances
		} elseif ( isset( $frame['object'] ) && method_exists( $frame['object'], $frame['function'] ) ) {
			try {
				$reflector = new \ReflectionMethod( $frame['object'], $frame['function'] );
				$file      = $reflector->getFileName();
			} catch ( Exception $e ) {
			}

		// Static object calls
		} elseif ( isset( $frame['class'] ) && method_exists( $frame['class'], $frame['function'] ) ) {
			try {
				$reflector = new \ReflectionMethod( $frame['class'], $frame['function'] );
				$file      = $reflector->getFileName();
			} catch ( Exception $e ) {
			}

		// Functions
		} elseif ( !empty( $frame['function'] ) && function_exists( $frame['function'] ) ) {
			try {
				$reflector = new \ReflectionFunction( $frame['function'] );
				$file      = $reflector->getFileName();
			} catch ( Exception $e ) {
			}

		// Lambdas / closures
		} elseif ( '__lambda_func' == $frame['function'] || '{closure}' == $frame['function'] ) {
			$file = preg_replace( '/\(\d+\)\s+:\s+runtime-created function/', '', $lambda_file );

		// Files, no other hints
		} elseif ( isset( $frame['file'] ) ) {
			$file = $frame['file'];

		// No idea
		} else {
			$file = $_SERVER['SCRIPT_FILENAME'];
		}

		// Check for "eval()'d code"
		if ( strpos( $file, "eval()'d" ) ) {
			list($file, $junk) = explode(': eval(', $str, 2);
			$file = preg_replace('/\(\d*\)$/', '', $file);
		}

		// Is it a plugin?
		$plugin = $this->_is_a_plugin_file( $file );
		if ( $plugin ) {
			$plugin_name = $this->getting_plugin_name( $file );
		}

		// Is it a theme?
		$is_a_theme = false;
		if ( FALSE === $plugin ) {
			if ( !$is_a_theme && isset( $theme_files_cache[$file] ) ) {
				$is_a_theme = $theme_files_cache[$file];
			}

			$theme_files_cache[$file] = (
				( FALSE !== strpos( $file, '/' . $themes_folder . '/' ) || FALSE !== strpos( $file, '\\'. $themes_folder . '\\' ) ) &&
				( FALSE !== strpos( $file, '/' . $content_folder . '/' ) || FALSE !== strpos( $file, '\\' . $content_folder . '\\' ) )
			);
			$theme_files_cache[$file];

			if ( $theme_files_cache[$file] ) {
				$is_a_theme = true;
			}
		}

		// If we're in a plugin, queue up the stack to be timed and logged during the next tick
		if ( FALSE !== $plugin ) {
			$this->_last_stack         = array( 'plugin' => $plugin_name );
			$this->_last_call_category = self::CATEGORY_PLUGIN;

		// Track theme times - code can travel from core -> theme -> plugin, and the whole trace
		// will show up in the stack, but we can only categorize it as one time, so we prioritize
		// timing plugins over themes, and thems over the core.
		} elseif ( FALSE !== $is_a_theme ) {
			$this->_last_call_category = self::CATEGORY_THEME;
			if ( !isset( $this->_profile['theme_name'] ) ) {
				$this->_profile['theme_name'] = $file;
			}

		// We must be in the core
		} else {
			$this->_last_call_category = self::CATEGORY_CORE;
		}

		// Count the time spent in here as profiler runtime
		$tmp             = microtime( true );
		$this->_runtime += ( $tmp - $start );

		// Reset the timer for the next tick
		$this->_last_call_start = microtime( true );
	}

	/**
	 * Check if the given file is in the plugins folder
	 * @param string $file
	 * @return bool
	 */
	private function _is_a_plugin_file( $file ) {
		static $plugin_files_cache = array();
		static $plugins_folder     = 'plugins';    // Guess, if it's not defined
		static $muplugins_folder   = 'mu-plugins';
		static $content_folder     = 'wp-content';
		static $folder_flag        = false;

		// Set the plugins folder
		if ( !$folder_flag ) {
			$plugins_folder   = basename( WP_PLUGIN_DIR );
			$muplugins_folder = basename( WPMU_PLUGIN_DIR );
			$content_folder   = basename( WP_CONTENT_DIR );
			$folder_flag      = true;
		}

		if ( isset( $plugin_files_cache[$file] ) ) {
			return $plugin_files_cache[$file];
		}

		$plugin_files_cache[$file] = (
			(
				( FALSE !== strpos( $file, '/' . $plugins_folder . '/' ) || FALSE !== stripos( $file, '\\' . $plugins_folder . '\\' ) ) ||
				( FALSE !== strpos( $file, '/' . $muplugins_folder . '/' ) || FALSE !== stripos( $file, '\\' . $muplugins_folder . '\\' ) )
			) &&
			( FALSE !== strpos( $file, '/' . $content_folder . '/' ) || FALSE !== stripos( $file, '\\' . $content_folder . '\\' ) )
		);

		return $plugin_files_cache[$file];
	}

	/**
	 * Guess a plugin's name from the file path
	 * @param string $path
	 * @return string
	 */
	private function getting_plugin_name( $path ) {
		static $seen_files_cache = array();
		static $plugins_folder   = 'plugins';    // Guess, if it's not defined
		static $muplugins_folder = 'mu-plugins';
		static $content_folder   = 'wp-content';
		static $folder_flag      = false;

		// Set the plugins folder
		if ( !$folder_flag ) {
			$plugins_folder   = basename( WP_PLUGIN_DIR );
			$muplugins_folder = basename( WPMU_PLUGIN_DIR );
			$content_folder   = basename( WP_CONTENT_DIR );
			$folder_flag      = true;
		}

		// Check the cache
		if ( isset( $seen_files_cache[$path] ) ) {
			return $seen_files_cache[$path];
		}

		// Trim off the base path
		$_path = realpath( $path );
		if ( FALSE !== strpos( $_path, '/' . $content_folder . '/' . $plugins_folder . '/' ) ) {
			$_path = substr(
				$_path,
				strpos( $_path, '/' . $content_folder . '/' . $plugins_folder . '/' ) +
				strlen( '/' . $content_folder . '/' . $plugins_folder . '/' )
			);
		} elseif ( FALSE !== stripos( $_path, '\\' . $content_folder . '\\' . $plugins_folder . '\\' ) ) {
			$_path = substr(
				$_path,
				stripos( $_path, '\\' . $content_folder . '\\' . $plugins_folder . '\\' ) +
				strlen( '\\' . $content_folder . '\\' . $plugins_folder . '\\' )
			);
		} elseif ( FALSE !== strpos( $_path, '/' . $content_folder . '/' . $muplugins_folder . '/' ) ) {
			$_path = substr(
				$_path,
				strpos( $_path, '/' . $content_folder . '/' . $muplugins_folder . '/' ) +
				strlen( '/' . $content_folder . '/' . $muplugins_folder . '/' )
			);
		} elseif ( FALSE !== stripos( $_path, '\\' . $content_folder . '\\' . $muplugins_folder . '\\' ) ) {
			$_path = substr(
				$_path, stripos( $_path, '\\' . $content_folder . '\\' . $muplugins_folder . '\\' ) +
				strlen( '\\' . $content_folder . '\\' . $muplugins_folder . '\\' )
			);
		}

		// Grab the plugin name as a folder or a file
		if ( FALSE !== strpos( $_path, DIRECTORY_SEPARATOR ) ) {
			$plugin = substr( $_path, 0, strpos( $_path, DIRECTORY_SEPARATOR ) );
		} else {
			$plugin = substr( $_path, 0, stripos( $_path, '.php' ) );
		}

		// Save it to the cache
		$seen_files_cache[$path] = $plugin;

		// Return
		return $plugin;
	}

	private function getting_loaded_url() {
		static $url = '';
		if ( !empty( $url ) ) {
			return $url;
		}
		$url =  $_SERVER['REQUEST_URI'] ;
		return $url;
	}

	function get_profiler_ip() {
		static $ip = '';
		if ( !empty( $ip ) ) {
			return $ip;
		} else {
			if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} elseif ( !empty ( $_SERVER['HTTP_X_REAL_IP'] ) ) {
				$ip = $_SERVER['HTTP_X_REAL_IP'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
			return $ip;
		}
	}

	

	public function shutdown_function() {
		$error = error_get_last();
		if ( empty( $error ) || E_ERROR !== $error['type'] ) {
			delete_option( 'smack-error_detection' );
		} else {
			
		}
		unset( $error );

		// Write debug log
		$opts = get_option('profiler_details' );
	
		if ( !empty( $opts['debug'] ) ) {
			$this->debug_log_entry();
		}
		// Last call time
		$this->_last_call_time = ( microtime( true ) - $this->_last_call_start );
	
		// Account for the last stack we measured
		if ( self::CATEGORY_PLUGIN == $this->_last_call_category && array() !== $this->_last_stack ) {
			// Write the stack to the profile
			$this->_plugin_runtime += $this->_last_call_time;

			// Add this stack to the profile
			$this->_profile['stack'][] = array(
				'plugin'  => $this->_last_stack['plugin'],
				'runtime' => $this->_last_call_time,
			);
		
			// Reset the stack
			$this->_last_stack = array();
		} elseif ( self::CATEGORY_THEME == $this->_last_call_category ) {
			$this->_theme += $this->_last_call_time;
		} elseif ( self::CATEGORY_CORE == $this->_last_call_category ) {
			$this->_core += $this->_last_call_time;
		}
		// Total runtime by plugin
		$plugin_totals = array();
		if ( !empty( $this->_profile['stack'] ) ) {
			foreach ( $this->_profile['stack'] as $stack ) {
				if ( empty( $plugin_totals[$stack['plugin']] ) ) {
					$plugin_totals[$stack['plugin']] = 0;
				}
				$plugin_totals[$stack['plugin']] += $stack['runtime'];
			}
		}
		foreach ( $plugin_totals as $k => $v ) {
			$plugin_totals[$k] = $v;
		}
		// Stop timing total run
		$tmp     = microtime( true );
		$runtime = ( $tmp - $this->_start_time );

		// Count the time spent in here as profiler runtime
		$this->_runtime += ( $tmp - $this->_last_call_start );

		if ( $this->_is_a_plugin_file( $_SERVER['SCRIPT_FILENAME'] ) ) {
			$this->_profile['runtime'] = array(
				'total'     => $runtime,
				'wordpress' => 0,
				'theme'     => 0,
				'plugins'   => ( $runtime - $this->_runtime ),
				'profile'   => $this->_runtime,
				'breakdown' => array(
					$this->getting_plugin_name( $_SERVER['SCRIPT_FILENAME'] ) => ( $runtime - $this->_runtime ),
				)
			);
		} elseif (
			( FALSE !== strpos( $_SERVER['SCRIPT_FILENAME'], '/themes/' ) || FALSE !== stripos( $_SERVER['SCRIPT_FILENAME'], '\\themes\\' ) ) &&
			(
				FALSE !== strpos( $_SERVER['SCRIPT_FILENAME'], '/' . basename( WP_CONTENT_DIR ) . '/' ) ||
				FALSE !== stripos( $_SERVER['SCRIPT_FILENAME'], '\\' . basename( WP_CONTENT_DIR ) . '\\' )
			)
			) {
			$this->_profile['runtime'] = array(
				'total'     => $runtime,
				'wordpress' => 0.0,
				'theme'     => ( $runtime - $this->_runtime ),
				'plugins'   => 0.0,
				'profile'   => $this->_runtime,
				'breakdown' => array()
			);
		} else {
			// Add runtime information
			$this->_profile['runtime'] = array(
				'total'     => $runtime,
				'wordpress' => $this->_core,
				'theme'     => $this->_theme,
				'plugins'   => $this->_plugin_runtime,
				'profile'   => $this->_runtime,
				'breakdown' => $plugin_totals,
			);
		}

		// Additional metrics
		$this->_profile['memory']    = memory_get_peak_usage( true );
		$this->_profile['stacksize'] = count( $this->_profile['stack'] );
		$this->_profile['queries']   = get_num_queries();
		
		// Throw away unneeded information to make the profiles smaller
		unset( $this->_profile['stack'] );
	
		// Write the profile file
		$file_name =  isset($opts['profiling_enabled']['name']) ? $opts['profiling_enabled']['name'] : '';
		$transient   = get_option('smack_'.$file_name);
		
		if ( false === $transient ) {
			$transient = '';
		}
	
		$transient  .= json_encode( $this->_profile ) . PHP_EOL;
	
		update_option('smack_'.$file_name, $transient );
		$debug_write = get_option('debug_value');
		
		
		if (  $debug_write ) {
			$this->debug_log_entry();
		}
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

}
