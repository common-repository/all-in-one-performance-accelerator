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

class bufferClass {

		private static $cookies;
		private static $post;
    private static $get;
    private static $memoized = [];
		private $tests = [
				'query_string'     => 1,
				'ssl'              => 1,
				'uri'              => 1,
				'rejectedcookie_array'  => 1,
				'mandatorycookie_array' => 1,
				'user_agent'       => 1,
				'mobile'           => 1,
				'donotcachepage'   => 1,
				'wp_404'           => 1,
				'search'           => 1,
		];
    private $last_error = [];
    private static $config_dir_path;
    private static $server;
   
		public function __construct(array $args = [] ) {
			$post_array  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
            $get_array   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
			$server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
		    $cookie_array =  filter_input_array(INPUT_COOKIE, FILTER_SANITIZE_STRING);
				if ( ! isset( $args['server'] ) && ! empty( $server_array ) && is_array( $server_array ) ) {
						$args['server'] = $server_array;
				}
				self::$server = ! empty( $args['server'] ) && is_array( $args['server'] ) ? $args['server'] : [];

				// Provide fallback values.
				if ( ! isset( $args['cookies'] ) && ! empty( $cookie_array ) && is_array( $cookie_array ) ) {
					$args['cookies'] = $cookie_array;
				}

				if ( ! isset( $args['post'] ) && ! empty( $post_array ) && is_array( $post_array ) ) { // WPCS: CSRF ok.
					$args['post'] = $post_array; // WPCS: CSRF ok.
				}
				if ( ! isset( $args['get'] ) && ! empty( $get_array ) && is_array( $get_array ) ) { // WPCS: CSRF ok.
					$args['get'] = $get_array; // WPCS: CSRF ok.
				}

				self::$cookies = ! empty( $args['cookies'] ) && is_array( $args['cookies'] ) ? $args['cookies'] : [];
				self::$post    = ! empty( $args['post'] ) && is_array( $args['post'] ) ? $args['post'] : [];
				self::$get     = ! empty( $args['get'] ) && is_array( $args['get'] ) ? $args['get'] : [];

				if ( self::$post ) {
					self::$post = array_intersect_key(
						// Limit self::$post to the values we need, to save a bit of memory.
						self::$post,
						[
							'wp_customize' => '',
						]
					);
				}
		}

	public function can_init_process() {
		$this->last_error = [];

		//Don't process robots.txt && .htaccess files (it has happened sometimes with weird server configuration).
		if ( $this->is_rejected_file() ) {
			$this->set_error( 'Robots.txt or .htaccess file is excluded.' );
			return false;
		}


		// Don't cache if in admin or ajax.
		if ( $this->is_admin() ) {
			$this->set_error( 'Admin or AJAX URL is excluded.' );
			return false;
		}

		// Don't process the customizer preview.
		if ( $this->is_customizer_preview() ) {
			$this->set_error( 'Customizer preview is excluded.' );
			return false;
		}

		if ( ! $this->has_test() ) {
			$this->last_error = [];
			return true;
		}

		// Don’t process with query strings parameters, but the processed content is served if the visitor comes from an RSS feed, a Facebook action or Google Adsense tracking.
		if ( $this->has_test( 'query_string' ) && ! $this->can_process_query_string() ) {
			$this->set_error( 'Query string URL is excluded.' );
			return false;
		}

		// Don't process SSL.
		if ( $this->has_test( 'ssl' ) && ! $this->can_process_ssl() ) {
			$this->set_error( 'SSL cache not applied to page.' );
			return false;
		}

		// Don't process these pages.
		if ( $this->has_test( 'uri' ) && ! $this->can_process_uri() ) {
			$this->set_error( 'Page is excluded.' );
			return false;
		}

    // Don't process page with these cookies.
		if ( $this->has_test( 'rejectedcookie_array' ) && $this->has_rejectedcookie_array() ) {
			$this->set_error(
				'Excluded cookie found.',
				[
					'excludedcookie_arrays' => $this->has_rejectedcookie_array(),
				]
			);
			return false;
		}


		// Don't process page with these user agents.
		if ( $this->has_test( 'user_agent' ) && ! $this->can_process_user_agent() ) {
			$this->set_error(
				'User Agent is excluded.',
				[
					'user_agent' => $this->getserver_array_input( 'HTTP_USER_AGENT' ),
				]
			);
			return false;
		}

		// Don't process if mobile detection is activated.
		if ( $this->has_test( 'mobile' ) && ! $this->can_process_mobile() ) {
			$this->set_error(
				'Mobile User Agent is excluded.',
				[
					'user_agent' => $this->getserver_array_input( 'HTTP_USER_AGENT' ),
				]
			);
			return false;
		}

		$this->last_error = [];

		return true;
    }
    
    public function getserver_array_input( $entry_name, $default = null ) {
		if ( ! isset( self::$server[ $entry_name ] ) ) {
			return $default;
		}
		return self::$server[ $entry_name ];
    }

	/**
	 * Tell if a test should be performed.
	 * @param  string $test_name Identifier of the test.
	 *                           Possible values are: 'query_string', 'ssl', 'uri', 'rejectedcookie_array', 'mandatorycookie_array', 'user_agent', 'mobile'.
	 * @return bool
	 */
	public function has_test( $test_name = '' ) {
		if ( empty( $test_name ) ) {
			return ! empty( $this->tests );
		}

		return isset( $this->tests[ $test_name ] );
	}

		/**
		 * Set the list of tests to perform.
		 * @param array $tests An array of test names.
		 */
		public function set_tests( array $tests ) {
				$tests = array_flip( $tests );
				array_merge( $this->tests, $tests );
		}

		/**
		 * Tell if the buffer should be processed.
		 */
		public function can_process_buffer( $buffer ) {
				$this->last_error = [];

				if ( strlen( $buffer ) <= 255 ) {
					// Buffer length must be > 255 (IE does not read pages under 255 c).
					$this->set_error( 'Buffer content under 255 caracters.' );
					return false;
				}

				if ( http_response_code() !== 200 ) {
					// Only cache 200.
					$this->set_error( 'Page is not a 200 HTTP response and cannot be cached.' );
					return false;
				}

				if ( $this->has_test( 'donotcachepage' ) && $this->has_donotcachepage() ) {
					// Don't process templates that use the DONOTCACHEPAGE constant.
					$this->set_error( 'DONOTCACHEPAGE is defined. Page cannot be cached.' );
					return false;
				}

				if ( $this->has_test( 'wp_404' ) && $this->is_404() ) {
					// Don't process WP 404 page.
					$this->set_error( 'WP 404 page is excluded.' );
					return false;
				}

				if ( $this->has_test( 'search' ) && $this->is_search() ) {
					// Don't process search results.
					$this->set_error( 'Search page is excluded.' );
					return false;
				}

				$this->last_error = [];

				return true;
    }
    
	/**
	 * Tell if the current URI corresponds to a file that must not be processed.
	 */
	public function is_rejected_file() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		$request_uri = $this->get_request_uri_base();

		if ( ! $request_uri ) {
			return self::memoize( __FUNCTION__, [], false );
		}

		$files = [
			'robots.txt',
			'.htaccess',
		];

		foreach ( $files as $file ) {
			if ( false !== strpos( $request_uri, '/' . $file ) ) {
				return self::memoize( __FUNCTION__, [], true );
			}
		}

		return self::memoize( __FUNCTION__, [], false );
	}

	/**
	 * Tell if the current URI corresponds to a file extension that must not be processed.
	 */
	public function is_rejected_extension() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		$request_uri = $this->get_request_uri_base();

		if ( ! $request_uri ) {
			return self::memoize( __FUNCTION__, [], false );
		}

		if ( strtolower( $request_uri ) === '/index.php' ) {
			// `index.php` is allowed.
			return self::memoize( __FUNCTION__, [], false );
		}

		$extension  = pathinfo( $request_uri, PATHINFO_EXTENSION );
		$extensions = [
			'php' => 1,
			'xml' => 1,
			'xsl' => 1,
		];

		$is_rejected = $extension && isset( $extensions[ $extension ] );
		return self::memoize( __FUNCTION__, [], $is_rejected );
	}

	/**
	 * Tell if we're in the admin area (or ajax) or not.
	 */
	public function is_admin() {
		return is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	/**
	 * Tell if we're displaying a customizer preview.
	 */
	public function is_customizer_preview() {
		return isset( self::$post['wp_customize'] );
	}

	/**
	 * Tell if the request method is allowed to be cached.
	 */
	public function is_allowed_request_method() {
		$allowed = [
			'GET'  => 1,
			'HEAD' => 1,
		];

		if ( isset( $allowed[ $this->get_request_method() ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Don't process with query string parameters, some parameters are allowed though.
	 */
	public function can_process_query_string() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		$params = $this->get_query_params();

		if ( ! $params ) {
			return self::memoize( __FUNCTION__, [], true );
		}

		// The page can be processed if at least one of these parameters is present.
		$allowed_params = [
			'lang'            => 1,
			's'               => 1,
			'permalink_name'  => 1,
			'lp-variation-id' => 1,
		];

		if ( array_intersect_key( $params, $allowed_params ) ) {
			return self::memoize( __FUNCTION__, [], true );
		}

		// The page can be processed if at least one of these parameters is present.
		$allowed_params = $this->get_config( 'cache_query_strings' );

		if ( ! $allowed_params ) {
			// We have query strings but none is in the list set by the user.
			return self::memoize( __FUNCTION__, [], false );
		}

		$can = (bool) array_intersect_key( $params, array_flip( $allowed_params ) );

		return self::memoize( __FUNCTION__, [], $can );
	}

	/**
	 * Process SSL only if set in the plugin settings.
	 */
	public function can_process_ssl() {
		return ! $this->is_ssl() || $this->get_config( 'cache_ssl' );
	}

	/**
	 * Some URIs set in the plugin settings must not be processed.
	 */
	public function can_process_uri() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		// URIs not to cache.
		$uri_pattern = $this->get_config( 'cache_reject_uri' );

		if ( ! $uri_pattern ) {
			return self::memoize( __FUNCTION__, [], true );
		}

		$can = ! preg_match( '#^(' . $uri_pattern . ')$#i', $this->get_clean_request_uri() );

		return self::memoize( __FUNCTION__, [], $can );
	}

	/**
	 * Don't process if some cookies are present.
	 */
	public function has_rejectedcookie_array() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		if ( ! self::$cookies ) {
			return self::memoize( __FUNCTION__, [], false );
		}

		//$rejectedcookie_arrays = $this->get_rejectedcookie_arrays();
		$rejectedcookie_arrays = '#wordpress_logged_in_.+|wp-postpass_|wptouch_switch_toggle|comment_author_|comment_author_email_#';
	
		if ( ! $rejectedcookie_arrays ) {
			return self::memoize( __FUNCTION__, [], false );
		}

		$excludedcookie_arrays = [];

		foreach ( array_keys( self::$cookies ) as $cookie_name ) {
			if ( preg_match( $rejectedcookie_arrays, $cookie_name ) ) {
				$excludedcookie_arrays[] = $cookie_name;
			}
		}

		if ( ! empty( $excludedcookie_arrays ) ) {
			return self::memoize( __FUNCTION__, [], $excludedcookie_arrays );
		}

		return self::memoize( __FUNCTION__, [], false );
	}

	/**
	 * Don't process if some cookies are NOT present.
	 */
	public function has_mandatorycookie_array() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		$mandatorycookie_arrays = $this->get_mandatorycookie_arrays();

		if ( ! $mandatorycookie_arrays ) {
			return self::memoize( __FUNCTION__, [], true );
		}

		$missingcookie_arrays = array_flip( explode( '|', $this->get_config( 'cache_mandatorycookie_arrays' ) ) );

		if ( ! self::$cookies ) {
			return self::memoize( __FUNCTION__, [], $missingcookie_arrays );
		}

		foreach ( array_keys( self::$cookies ) as $cookie_name ) {
			if ( preg_match( $mandatorycookie_arrays, $cookie_name ) ) {
				unset( $missingcookie_arrays[ $cookie_name ] );
			}
		}

		if ( empty( $missingcookie_arrays ) ) {
			return self::memoize( __FUNCTION__, [], true );
		}

		return self::memoize( __FUNCTION__, [], array_flip( $missingcookie_arrays ) );
	}

	/**
	 * Don't process if the user agent is in the forbidden list.
	 */
	public function can_process_user_agent() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		if ( ! $this->getserver_array_input( 'HTTP_USER_AGENT' ) ) {
			return self::memoize( __FUNCTION__, [], true );
		}

		$rejected_uas = $this->get_config( 'cache_reject_ua' );

		if ( ! $rejected_uas ) {
			return self::memoize( __FUNCTION__, [], true );
		}

		$can = ! preg_match( '#' . $rejected_uas . '#', $this->getserver_array_input( 'HTTP_USER_AGENT' ) );

		return self::memoize( __FUNCTION__, [], $can );
	}

	/**
	 * Don't process if the user agent is in the forbidden list.
	 */
	public function can_process_mobile() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		if ( ! $this->getserver_array_input( 'HTTP_USER_AGENT' ) ) {
			return self::memoize( __FUNCTION__, [], true );
		}

		if ( $this->get_config( 'cache_mobile' ) ) {
			return self::memoize( __FUNCTION__, [], true );
		}

		$uas = '2.0\ MMP|240x320|400X240|AvantGo|BlackBerry|Blazer|Cellphone|Danger|DoCoMo|Elaine/3.0|EudoraWeb|Googlebot-Mobile|hiptop|IEMobile|KYOCERA/WX310K|LG/U990|MIDP-2.|MMEF20|MOT-V|NetFront|Newt|Nintendo\ Wii|Nitro|Nokia|Opera\ Mini|Palm|PlayStation\ Portable|portalmmm|Proxinet|ProxiNet|SHARP-TQ-GX10|SHG-i900|Small|SonyEricsson|Symbian\ OS|SymbianOS|TS21i-10|UP.Browser|UP.Link|webOS|Windows\ CE|WinWAP|YahooSeeker/M1A1-R2D2|iPhone|iPod|Android|BlackBerry9530|LG-TU915\ Obigo|LGE\ VX|webOS|Nokia5800';

		if ( preg_match( '#^.*(' . $uas . ').*#i', $this->getserver_array_input( 'HTTP_USER_AGENT' ) ) ) {
			return self::memoize( __FUNCTION__, [], false );
		}

		$uas = 'w3c\ |w3c-|acs-|alav|alca|amoi|audi|avan|benq|bird|blac|blaz|brew|cell|cldc|cmd-|dang|doco|eric|hipt|htc_|inno|ipaq|ipod|jigs|kddi|keji|leno|lg-c|lg-d|lg-g|lge-|lg/u|maui|maxo|midp|mits|mmef|mobi|mot-|moto|mwbp|nec-|newt|noki|palm|pana|pant|phil|play|port|prox|qwap|sage|sams|sany|sch-|sec-|send|seri|sgh-|shar|sie-|siem|smal|smar|sony|sph-|symb|t-mo|teli|tim-|tosh|tsm-|upg1|upsi|vk-v|voda|wap-|wapa|wapi|wapp|wapr|webc|winw|winw|xda\ |xda-';

		if ( preg_match( '#^(' . $uas . ').*#i', $this->getserver_array_input( 'HTTP_USER_AGENT' ) ) ) {
			return self::memoize( __FUNCTION__, [], false );
		}

		return self::memoize( __FUNCTION__, [], true );
    }
    
	/**
	 * Tell if the constant DONOTCACHEPAGE is set and not overridden.
	 * When defined, the page must not be cached.
	 */
	public function has_donotcachepage() {
		if ( ! defined( 'DONOTCACHEPAGE' ) || ! DONOTCACHEPAGE ) {
			return false;
		}
		return ! apply_filters( 'smack_override_donotcachepage', false );
	}

	public function is_404() {
		return ! function_exists( 'is_404' ) || is_404();
	}

	/**
	 * Tell if we're in the WP’s search page.
	 */
	public function is_search() {
		if ( function_exists( 'is_search' ) && ! is_search() ) {
			return false;
		}
		return ! apply_filters( 'smack_cache_search', false );
	}

	public function get_cookies() {
		return self::$cookies;
	}

	public function get_server_input( $entry_name, $default = null ) {
		if ( ! isset( self::$server[ $entry_name ] ) ) {
			return $default;
		}

		return self::$server[ $entry_name ];
	}
	
	/**
	 * Get the IP address from which the user is viewing the current page.
	 */
	public function get_ip() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		$keys = array(
			'HTTP_CF_CONNECTING_IP', // CF = CloudFlare.
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_X_REAL_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $keys as $key ) {
			if ( ! $this->getserver_array_input( $key ) ) {
				continue;
			}

			$ip = explode( ',', $this->getserver_array_input( $key ) );
			$ip = end( $ip );

			if ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return self::memoize( __FUNCTION__, [], $ip );
			}
		}

		return self::memoize( __FUNCTION__, [], '0.0.0.0' );
	}

	/**
	 * Tell if the request comes from a speed test tool.
	 */
	public function is_speed_tool() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		$ips = [
			'208.70.247.157' => '', // GT Metrix - Vancouver 1.
			'204.187.14.70'  => '', // GT Metrix - Vancouver 2.
			'204.187.14.71'  => '', // GT Metrix - Vancouver 3.
			'204.187.14.72'  => '', // GT Metrix - Vancouver 4.
			'204.187.14.73'  => '', // GT Metrix - Vancouver 5.
			'204.187.14.74'  => '', // GT Metrix - Vancouver 6.
			'204.187.14.75'  => '', // GT Metrix - Vancouver 7.
			'204.187.14.76'  => '', // GT Metrix - Vancouver 8.
			'204.187.14.77'  => '', // GT Metrix - Vancouver 9.
			'204.187.14.78'  => '', // GT Metrix - Vancouver 10.
			'199.10.31.194'  => '', // GT Metrix - Vancouver 11.
			'13.85.80.124'   => '', // GT Metrix - Dallas 1.
			'13.84.146.132'  => '', // GT Metrix - Dallas 2.
			'13.84.146.226'  => '', // GT Metrix - Dallas 3.
			'40.74.254.217'  => '', // GT Metrix - Dallas 4.
			'13.84.43.227'   => '', // GT Metrix - Dallas 5.
			'172.255.61.34'  => '', // GT Metrix - London 1.
			'172.255.61.35'  => '', // GT Metrix - London 2.
			'172.255.61.36'  => '', // GT Metrix - London 3.
			'172.255.61.37'  => '', // GT Metrix - London 4.
			'172.255.61.38'  => '', // GT Metrix - London 5.
			'172.255.61.39'  => '', // GT Metrix - London 6.
			'172.255.61.40'  => '', // GT Metrix - London 7.
			'13.70.66.20'    => '', // GT Metrix - Sydney.
			'191.235.85.154' => '', // GT Metrix - São Paulo 1.
			'191.235.86.0'   => '', // GT Metrix - São Paulo 2.
			'52.66.75.147'   => '', // GT Metrix - Mumbai.
			'52.175.28.116'  => '', // GT Metrix - Hong Kong.
		];

		if ( isset( $ips[ $this->get_ip() ] ) ) {
			return self::memoize( __FUNCTION__, [], true );
		}

		if ( ! $this->getserver_array_input( 'HTTP_USER_AGENT' ) ) {
			return self::memoize( __FUNCTION__, [], false );
		}

		$user_agent = preg_match( '#PingdomPageSpeed|DareBoost|Google|PTST|Chrome-Lighthouse|WP Rocket#i', $this->getserver_array_input( 'HTTP_USER_AGENT' ) );

		return self::memoize( __FUNCTION__, [], (bool) $user_agent );
	}

	/**
	 * Determines if SSL is used.
	 * This is basically a copy of the WP function, where $server_array is not used directly.
	 */
	public function is_ssl() {
		if ( null !== $this->getserver_array_input( 'HTTPS' ) ) {
			if ( 'on' === strtolower( $this->getserver_array_input( 'HTTPS' ) ) ) {
				return true;
			}

			if ( '1' === (string) $this->getserver_array_input( 'HTTPS' ) ) {
				return true;
			}
		} elseif ( '443' === (string) $this->getserver_array_input( 'SERVER_PORT' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get the request URI.
	 */
	public function get_raw_request_uri() {
		if ( '' === $this->getserver_array_input( 'REQUEST_URI', '' ) ) {
			return '';
		}

		return '/' . ltrim( $this->getserver_array_input( 'REQUEST_URI' ), '/' );
	}

	/**
	 * Get the request URI without the query strings.
	 */
	public function get_request_uri_base() {
		$request_uri = $this->get_raw_request_uri();

		if ( ! $request_uri ) {
			return '';
		}

		$request_uri = explode( '?', $request_uri );

		return reset( $request_uri );
	}

	/**
	 * Get the request URI. The query string is sorted and some parameters are removed.
	 */
	public function get_clean_request_uri() {
		$request_uri = $this->get_request_uri_base();

		if ( ! $request_uri ) {
			return '';
		}

		$query_string = $this->get_query_string();

		if ( ! $query_string ) {
			return $request_uri;
		}

		return $request_uri . '?' . $query_string;
	}

	/**
	 * Get the request method.
	 */
	public function get_request_method() {
		if ( '' === $this->getserver_array_input( 'REQUEST_METHOD', '' ) ) {
			return '';
		}

		return strtoupper( $this->getserver_array_input( 'REQUEST_METHOD' ) );
    }
    
	/**
	 * Get the query string as an array. Parameters are sorted and some are removed.
	 */
	public function get_query_params() {
		if ( ! self::$get ) {
			return [];
		}

		if(!empty(self::$get)&&!empty($this->get_config( 'cache_ignored_parameters' ))){
			// Remove some parameters.
			$params = array_diff_key(
				self::$get,
				$this->get_config( 'cache_ignored_parameters' )
			);
			if ( $params ) {
				ksort( $params );
			}
		}else{
			$params=[];
		}
		

		
		return $params;
	}

	/**
	 * Get the query string with sorted parameters, and some other removed.
	 */
	public function get_query_string() {
		return http_build_query( $this->get_query_params() );
	}

	/**
	 * Get the `cookies` property.
	 */
	public function getcookie_arrays() {
		return self::$cookies;
	}

	/**
	 * Set an "error".
	 */
	protected function set_error( $message, $data = [] ) {
		$this->last_error = [
			'message' => $message,
			'data'    => (array) $data,
		];
	}

	public function get_last_error() {
		return array_merge(
			[
				'message' => '',
				'data'    => [],
			],
			(array) $this->last_error
		);
    }
    
    public function log_last_test_error() {
		$error = $this->get_last_error();
	 update_option('smack_deferjs_error',$error['message']);
        return $error;
	}

    final public static function is_memoized( $method, $args = [] ) {
        $hash = self::get_memoize_args_hash( $args ); 
				return isset( self::$memoized[ $method ][ $hash ] );
    }

    private static function get_memoize_args_hash( $args ) {
				if ( [] === $args ) {
					return 'd751713988987e9331980363e24189ce'; // `md5( json_encode( [] ) )`
				}
				return md5( call_user_func( 'json_encode', $args ) );
    }

    final public static function get_memoized( $method, $args = [] ) {
				$hash = self::get_memoize_args_hash( $args );
				return isset( self::$memoized[ $method ][ $hash ] ) ? self::$memoized[ $method ][ $hash ] : null;
    }
    
    final public static function memoize( $method, $args = [], $value = null ) {
				$hash = self::get_memoize_args_hash( $args );

				if ( ! isset( self::$memoized[ $method ] ) ) {
					self::$memoized[ $method ] = [];
				}		

        self::$memoized[ $method ][ $hash ] = $value;
				return self::$memoized[ $method ][ $hash ];
    }

    public function get_config_file_path() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		$config_dir_real_path = realpath( self::$config_dir_path ) . DIRECTORY_SEPARATOR;
		$host = $this->get_host();
    
		if ( realpath( self::$config_dir_path . $host . '.php' ) && 0 === stripos( realpath( self::$config_dir_path . $host . '.php' ), $config_dir_real_path ) ) {
			$config_file_path = self::$config_dir_path . $host . '.php';
			return self::memoize(
				__FUNCTION__,
				[],
				[
					'success' => true,
					'path'    => $config_file_path,
				]
			);
		}

		$path = str_replace( '\\', '/', strtok( $this->getserver_array_input( 'REQUEST_URI', '' ), '?' ) );
		$path = preg_replace( '|(?<=.)/+|', '/', $path );
		$path = explode( '%2F', preg_replace( '/^(?:%2F)*(.*?)(?:%2F)*$/', '$1', rawurlencode( $path ) ) );
     
		foreach ( $path as $p ) {
			static $dir;

			if ( realpath( self::$config_dir_path . $host . '.' . $p . '.php' ) && 0 === stripos( realpath( self::$config_dir_path . $host . '.' . $p . '.php' ), $config_dir_real_path ) ) {
				$config_file_path = self::$config_dir_path . $host . '.' . $p . '.php';
				return self::memoize(
					__FUNCTION__,
					[],
					[
						'success' => true,
						'path'    => $config_file_path,
					]
				);
			}

			if ( realpath( self::$config_dir_path . $host . '.' . $dir . $p . '.php' ) && 0 === stripos( realpath( self::$config_dir_path . $host . '.' . $dir . $p . '.php' ), $config_dir_real_path ) ) {
				$config_file_path = self::$config_dir_path . $host . '.' . $dir . $p . '.php';
				return self::memoize(
					__FUNCTION__,
					[],
					[
						'success' => true,
						'path'    => $config_file_path,
					]
				);
			}

			$dir .= $p . '.';
		}

		return self::memoize(
			__FUNCTION__,
			[],
			[
				'success' => false,
				'path'    => self::$config_dir_path . $host . implode( '/', $path ) . '.php',
			]
		);
    }

    public function get_rejectedcookie_arrays() {
		$rejectedcookie_arrays = $this->get_config( 'cache_rejectcookie_arrays' );

		if ( '' === $rejectedcookie_arrays ) {
			return $rejectedcookie_arrays;
		}

		return '#' . $rejectedcookie_arrays . '#';
    }

    public function get_mandatorycookie_arrays() {
		$mandatorycookie_arrays = $this->get_config( 'cache_mandatorycookie_arrays' );

		if ( '' === $mandatorycookie_arrays ) {
			return $mandatorycookie_arrays;
		}

		return '#' . $mandatorycookie_arrays . '#';
    }
    
    public function get_config( $config_name ) {
		$config = $this->get_configs();
		return isset( $config[ $config_name ] ) ? $config[ $config_name ] : null;
    }

    public function get_configs() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

		$config_file_path = $this->get_config_file_path();

		if ( ! $config_file_path['success'] ) {
			return self::memoize( __FUNCTION__, [], false );
		}

		include $config_file_path['path'];

		$config = [
			'cookie_hash'               => '',
			'logged_incookie_array'          => '',
			'common_cache_logged_users' => 0,
			'cache_mobile_files_tablet' => 'desktop',
			'cache_ssl'                 => 0,
			'cache_webp'                => 0,
			'cache_mobile'              => 0,
			'do_caching_mobile_files'   => 0,
			'secret_cache_key'          => '',
			'cache_reject_uri'          => '',
			'cache_query_strings'       => [],
			'cache_ignored_parameters'  => [],
			'cache_rejectcookie_arrays'      => '',
			'cache_reject_ua'           => '',
			'cache_mandatorycookie_arrays'   => '',
			'cache_dynamiccookie_arrays'     => [],
			'url_no_dots'               => 0,
		];

		foreach ( $config as $entry_name => $entry_value ) {
			$var_name = 'smack_' . $entry_name;

			if ( isset( $$var_name ) ) {
				$config[ $entry_name ] = $$var_name;
			}
		}

		return self::memoize( __FUNCTION__, [], $config );
    }
    
    public function get_host() {
		if ( self::is_memoized( __FUNCTION__ ) ) {
			return self::get_memoized( __FUNCTION__ );
		}

        $host = $this->getserver_array_input( 'HTTP_HOST', (string) time() );
		$host = preg_replace( '/:\d+$/', '', $host );
		$host = trim( strtolower( $host ), '.' );
        
		return self::memoize( __FUNCTION__, [], rawurlencode( $host ) );
    }

    final public function define_donotoptimize_true() {
		if ( ! defined( 'DONOTSMACKOPTIMIZE' ) ) {
			define( 'DONOTSMACKOPTIMIZE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		}
    }
    
    public function is_html( $buffer ) {
        return preg_match( '/<\/html>/i', $buffer );
    }
}