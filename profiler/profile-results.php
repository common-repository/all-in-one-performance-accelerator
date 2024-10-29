<?php
namespace Smackcoders\AIOACC;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

 class Profiler_results {
	public $total_time = 0;
	public $site_time = 0;
	public $theme_time = 0;
	public $plugin_time = 0;
	public $profile_time = 0;
	public $core_time = 0;
	public $memory = 0;
	public $plugin_calls = 0;
	public $report_url = '';
	public $report_date = '';
	public $queries = 0;
	public $visits = 0;
	public $detected_plugins = array();
	public $plugin_times = array();
	public $theme_name = '';
	public $averages = array(
		'total' => 0,
		'site' => 0,
		'core' => 0,
		'plugins' => 0,
		'profile' => 0,
		'theme' => 0,
		'memory' => 0,
		'plugin_calls' => 0,
		'queries' => 0,
		'observed' => 0,
		'expected' => 0,
		'drift' => 0,
		'plugin_impact' => 0,
	);
	private $_data = array();
	public $profile_name = '';
    protected static $instance = null,$plugin;
    public static function getInstance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
			self::$plugin = Plugin::getInstance();
			//self::$instance->doHooks();
		}
		return self::$instance;
	}

	public function __construct( $file ) {
		// Open the file
		$fp = fopen( $file, 'r' );
		if ( FALSE === $fp ) {
			throw new Exception( __( 'Cannot open file: ', 'smack-profiler' ) . $file );
		}
			
		while ( !feof( $fp ) ) {
			$line = fgets( $fp );
			if ( empty( $line) ) {
				continue;
			}
			$tmp = json_decode( $line );
			if ( null === $tmp ) {
				throw new Exception( __( 'Cannot parse file: ', 'smack-profiler' ) . $file );
				fclose( $fp );
			}
			$this->_data[] = $tmp;
		}
		
		// Close the file
		fclose( $fp );
		
		// Set the profile name
		$this->profile_name = preg_replace( '/\.json$/', '', basename ( $file ) );
		
		// Parse the data
		$this->get_parse_data();
	}

	/**
	 * Parse from $this->_data and fill in the rest of the member vars
	 * @return void
	 */
	private function get_parse_data() {

		// Check for empty data
		if ( empty( $this->_data ) ) {
			
		}
		$overAll_breakdowns=[];
		$plugin_load_Times=[];
		$theme_load_Times=[];
		$wordpress_load_Times=[];
		$overAll_page_individual_queries=[];
		foreach ( $this->_data as $datas ) {
			// Set report meta-data
			if ( empty( $this->report_date ) ) {
				$this->report_date = strtotime( $datas->date );
				$scheme            = parse_url( $datas->url, PHP_URL_SCHEME );
				$host              = parse_url( $datas->url, PHP_URL_HOST );
				$path              = parse_url( $datas->url, PHP_URL_PATH );
				$this->report_url  = sprintf( '%s://%s%s', $scheme, $host, $path );
				$this->visits      = count( $this->_data );
			}
			
			// Set total times / queries / function calls
			$this->total_time   += $datas->runtime->total;
			$this->site_time    += ( $datas->runtime->total - $datas->runtime->profile );
			$this->theme_time   += $datas->runtime->theme;
			$this->plugin_time  += $datas->runtime->plugins;
			$this->profile_time += $datas->runtime->profile;
			$this->core_time    += $datas->runtime->wordpress;
			$this->memory       += $datas->memory;
			$this->plugin_calls += $datas->stacksize;
			$this->queries      += $datas->queries;
			$this->overAll_breakdowns[$datas->url] = $overAll_breakdowns[$datas->url] = $datas->runtime->breakdown;
			$this->plugin_load_Times[$datas->url] = $plugin_load_Times[$datas->url] = $datas->runtime->plugins;
			$this->theme_load_Times[$datas->url] = $theme_load_Times[$datas->url] = $datas->runtime->theme;
			$this->wordpress_load_Times[$datas->url] = $wordpress_load_Times[$datas->url] = $datas->runtime->wordpress;
			$this->overAll_page_individual_queries[$datas->url] = $overAll_page_individual_queries[$datas->url] = $datas->queries;
			
			// Loop through the plugin data
			foreach ( $datas->runtime->breakdown as $k => $v ) {
				if ( !array_key_exists( $k, $this->plugin_times ) ) {
					$this->plugin_times[$k] = 0;
				}
				$this->plugin_times[$k] += $v;
			}
		}

		// Fix plugin names and average out plugin times
		$tmp                = $this->plugin_times;
		$this->plugin_times = array();
		foreach ( $tmp as $k => $v ) {
			$k = $this->find_plugin_name( $k );
			$this->plugin_times[$k] = $v / $this->visits;
		}

		// Get a list of the plugins we detected
		$this->detected_plugins = array_keys( $this->plugin_times );
		sort( $this->detected_plugins );

		// Calculate the averages
		$this->find_averages();
		
		// Get theme name
		if ( property_exists( $this->_data[0], 'theme_name') ) {
			$this->theme_name = str_replace( realpath( WP_CONTENT_DIR . '/themes/' ), '', realpath( $this->_data[0]->theme_name ) );
			$this->theme_name = preg_replace('|^[\\\/]+([^\\\/]+)[\\\/]+.*|', '$1', $this->theme_name);
			$this->theme_name = $this->find_theme_name( $this->theme_name );
		} else {
			$this->theme_name = 'unknown';
		}
	}

	/**
	 * Calculate the average values
	 * @return void
	 */
	private function find_averages() {
		if ( $this->visits <= 0 ) {
			return;
		}
		$this->averages = array(
			'total'         => $this->total_time / $this->visits,
			'site'          => ( $this->total_time - $this->profile_time ) / $this->visits,
			'core'          => $this->core_time / $this->visits,
			'plugins'       => $this->plugin_time / $this->visits,
			'profile'       => $this->profile_time / $this->visits,
			'theme'         => $this->theme_time / $this->visits,
			'memory'        => $this->memory / $this->visits,
			'plugin_calls'  => $this->plugin_calls / $this->visits,
			'queries'       => $this->queries / $this->visits,
			'observed'      => $this->total_time / $this->visits,
			'overAll_breakdowns'   => $this->overAll_breakdowns ,
			'plugin_load_Times'   => $this->plugin_load_Times ,
			'theme_load_Times'   => $this->theme_load_Times ,
			'wordpress_load_Times'   => $this->wordpress_load_Times ,
			'overAll_page_individual_queries' => $this->overAll_page_individual_queries ,
			'expected'      => ( $this->theme_time + $this->core_time + $this->profile_time + $this->plugin_time) / $this->visits,
		);
		$this->averages['drift']         = $this->averages['observed'] - $this->averages['expected'];
		$this->averages['plugin_impact'] = $this->averages['plugins'] / $this->averages['site'] * 100;
	}

	/**
	 * Return a list of runtimes times by url
	 * Where the key is the url and the value is an array of runtime values
	 * in seconds (float)
	 * @return array
	 */
	public function getting_stats_by_url() {
		$ret = array();
		foreach ( $this->_data as $datas ) {
		
			$tmp = array(
				'url'       => $datas->url,
				'core'      => $datas->runtime->wordpress,
				'plugins'   => $datas->runtime->plugins,
				'profile'   => $datas->runtime->profile,
				'theme'     => $datas->runtime->theme,
				'queries'   => $datas->queries,
				'breakdown' => array()
			);
			foreach ( $datas->runtime->breakdown as $k => $v ) {
				$name = $this->find_plugin_name( $k );
				if ( !array_key_exists( $name, $tmp['breakdown'] ) ) {
					$tmp['breakdown'][$name] = 0;
				}
				$tmp['breakdown'][$name] += $v;
			}
			$ret[] = $tmp;
		}
		return $ret;
	}
	
	/**
	 * Get a raw list (slugs only) of the detected plugins
	 * @return array
	 */
	public function getting_plugin_list() {
		$tmp = array();
		foreach ( $this->_data as $datas ) {
			foreach( $datas->runtime->breakdown as $k => $v ) {
				$tmp[] = $k;
			}
		}
		return array_unique( $tmp );
	}

	/**
	 * Translate a plugin name
	 * Uses get_plugin_data if available.
	 * @param string $plugin Plugin name (possible paths will be guessed)
	 * @return string
	 */
	public function find_plugin_name( $plugin ) {
		if ( function_exists( 'get_plugin_data' ) ) {
			$plugin_info = array();
			$possible_paths = array(
				WP_PLUGIN_DIR . "/$plugin.php",
				WP_PLUGIN_DIR . "/$plugin/$plugin.php",
				WPMU_PLUGIN_DIR . "/$plugin.php"
			);
			foreach ( $possible_paths as $path ) {
				if ( file_exists( $path ) ) {
					$plugin_info = get_plugin_data( $path );
					if ( !empty( $plugin_info ) && !empty( $plugin_info['Name'] ) ) {
						return $plugin_info['Name'];
					}
				}
			}
		}
		return $this->plugin_format_name( $plugin );
	}

	/**
	 * Translate a theme name
	 * Uses get_theme_data if available.
	 * @param string $plugin Theme name (possible path will be guessed)
	 * @return string
	 */
	private function find_theme_name( $theme ) {
		if ( function_exists( 'wp_get_theme') ) {
			$theme_info = wp_get_theme( $theme );
			return $theme_info->get('Name');
		} elseif ( function_exists( 'get_theme_data' ) && file_exists( WP_CONTENT_DIR . '/themes/' . $theme . '/style.css' ) ) {
			$theme_info = get_theme_data( WP_CONTENT_DIR . '/themes/' . $theme . '/style.css' );
			if ( !empty( $theme_info ) && !empty( $theme_info['Name'] ) ) {
				return $theme_info['Name'];
			}
		}
		return $this->plugin_format_name( $theme );
	}
	
	/**
	 * Format plugin / theme name.  This is only to be used if
	 * get_plugin_data() / get_theme_data() aren't available or if the
	 * original files are missing
	 * @param string $name
	 * @return string
	 */
	private function plugin_format_name( $name ) {
		return ucwords( str_replace( array( '-', '_' ), ' ', $name ) );
	}
}
