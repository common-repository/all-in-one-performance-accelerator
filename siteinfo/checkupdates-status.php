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

class Check_Updates_status
{
	private $plugins_before;
	private $plugins_after;
	private static $plugins_blocked;
	private $themes_before;
	private $themes_after;
	private static $themes_blocked;


	public function __construct() {
		$this->init();
	}

	
	public function init() {
		$this->plugins_before  = (array) array();
		$this->plugins_after   = (array) array();
		self::$plugins_blocked = (bool) false;

		$this->themes_before  = (array) array();
		$this->themes_after   = (array) array();
		self::$themes_blocked = (bool) false;
	}

	
	public function run_tests() {
		$tests = array();

		foreach ( get_class_methods( $this ) as $method ) {
			if ( 'test_' !== substr( $method, 0, 5 ) ) {
				continue;
			}

			$result = call_user_func( array( $this, $method ) );

			if ( false === $result || null === $result ) {
				continue;
			}

			$result = (object) $result;

			if ( empty( $result->severity ) ) {
				$result->severity = 'warning';
			}

			$tests[ $method ] = $result;
		}

		return $tests;
	}

	
	function test_plugin_updates() {
		// Check if any update hooks have been removed.
		$hooks = $this->check_plugin_update_hooks();
		if ( ! $hooks ) {
			return array(
				'desc'     => esc_html__( 'Plugin update hooks have been removed.', 'health-check' ),
				'severity' => 'fail',
			);
		}

		// Check if update requests are being blocked.
		$blocked = $this->check_plugin_update_pre_request();
		if ( true === $blocked ) {
			return array(
				'desc'     => esc_html__( 'Plugin update requests have been blocked.', 'health-check' ),
				'severity' => 'fail',
			);
		}

		// Check if plugins have been removed from the update requests.
		$diff = (array) $this->check_plugin_update_request_args();
		if ( 0 !== count( $diff ) ) {
			return array(
				'desc'     => sprintf(
					/* translators: %s: List of plugin names. */
					esc_html__( 'The following Plugins have been removed from update checks: %s.', 'health-check' ),
					implode( ',', $diff )
				),
				'severity' => 'warning',
			);
		}

		return array(
			'desc'     => esc_html__( 'Plugin updates should be working as expected.', 'health-check' ),
			'severity' => 'pass',
		);
	}

	
	function check_plugin_update_hooks() {
		$test1 = has_filter( 'load-plugins.php', 'wp_update_plugins' );
		$test2 = has_filter( 'load-update.php', 'wp_update_plugins' );
		$test3 = has_filter( 'load-update-core.php', 'wp_update_plugins' );
		$test4 = has_filter( 'wp_update_plugins', 'wp_update_plugins' );
		$test5 = has_filter( 'admin_init', '_maybe_update_plugins' );
		$test6 = wp_next_scheduled( 'wp_update_plugins' );

		return $test1 && $test2 && $test3 && $test4 && $test5 && $test6;
	}

	
	function check_plugin_update_pre_request() {
		add_action( 'pre_http_request', array( $this, 'plugin_pre_request_check' ), PHP_INT_MAX, 3 );
		add_action( 'pre_http_request', array( $this, 'block_fake_request' ), PHP_INT_MAX, 3 );

		$this->plugin_update_fake_request();

		remove_action( 'pre_http_request', array( $this, 'plugin_pre_request_check' ), PHP_INT_MAX );
		remove_action( 'pre_http_request', array( $this, 'block_fake_request' ), PHP_INT_MAX );

		return self::$plugins_blocked;
	}

	
	function plugin_pre_request_check( $pre, $r, $url ) {
		$check_url = 'api.wordpress.org/plugins/update-check/1.1/';
		if ( 0 !== substr_compare( $url, $check_url, -strlen( $check_url ) ) ) {
			return $pre; // Not a plugin update request.
		}

		// If not false something is blocking update checks
		if ( false !== $pre ) {
			self::$plugins_blocked = (bool) true;
		}

		return $pre;
	}

	
	function check_plugin_update_request_args() {
		add_action( 'http_request_args', array( $this, 'plugin_request_args_before' ), 1, 2 );
		add_action( 'http_request_args', array( $this, 'plugin_request_args_after' ), PHP_INT_MAX, 2 );
		add_action( 'pre_http_request', array( $this, 'block_fake_request' ), PHP_INT_MAX, 3 );

		$this->plugin_update_fake_request();

		remove_action( 'http_request_args', array( $this, 'plugin_request_args_before' ), 1 );
		remove_action( 'http_request_args', array( $this, 'plugin_request_args_after' ), PHP_INT_MAX );
		remove_action( 'pre_http_request', array( $this, 'block_fake_request' ), PHP_INT_MAX );

		$diff = array_diff_key( $this->plugins_before['plugins'], $this->plugins_after['plugins'] );

		$titles = array();
		foreach ( $diff as $item ) {
			$titles[] = $item['Title'];
		}

		return $titles;
	}

	
	function plugin_request_args_before( $r, $url ) {
		$check_url = 'api.wordpress.org/plugins/update-check/1.1/';
		if ( 0 !== substr_compare( $url, $check_url, -strlen( $check_url ) ) ) {
			return $r; // Not a plugin update request.
		}

		$this->plugins_before = (array) json_decode( $r['body']['plugins'], true );

		return $r;
	}

	
	function plugin_request_args_after( $r, $url ) {
		$check_url = 'api.wordpress.org/plugins/update-check/1.1/';
		if ( 0 !== substr_compare( $url, $check_url, -strlen( $check_url ) ) ) {
			return $r; // Not a plugin update request.
		}

		$this->plugins_after = (array) json_decode( $r['body']['plugins'], true );

		return $r;
	}

	
	function plugin_update_fake_request() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		// Prepare data for the request.
		$plugins      = get_plugins();
		$active       = get_option( 'active_plugins', array() );
		$to_send      = compact( 'plugins', 'active' );
		$translations = wp_get_installed_translations( 'plugins' );
		$locales      = array_values( get_available_languages() );
		$locales      = (array) apply_filters( 'plugins_update_check_locales', $locales );
		$locales      = array_unique( $locales );
		$timeout      = 3 + (int) ( count( $plugins ) / 10 );

		// Setup the request options.
		if ( function_exists( 'wp_json_encode' ) ) {
			$options = array(
				'timeout'    => $timeout,
				'body'       => array(
					'plugins'      => wp_json_encode( $to_send ),
					'translations' => wp_json_encode( $translations ),
					'locale'       => wp_json_encode( $locales ),
					'all'          => wp_json_encode( true ),
				),
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			);
		} else {
			$options = array(
				'timeout'    => $timeout,
				'body'       => array(
					'plugins'      => json_encode( $to_send ),
					'translations' => json_encode( $translations ),
					'locale'       => json_encode( $locales ),
					'all'          => json_encode( true ),
				),
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			);
		}

		// Set the URL
		$http_url = 'http://api.wordpress.org/plugins/update-check/1.1/';
		$url      = wp_http_supports( array( 'ssl' ) ) ? set_url_scheme( $http_url, 'https' ) : $http_url;

		// Ignore the response. Just need the hooks to fire.
		wp_remote_post( $url, $options );
	}

	
	function test_constant_theme_updates() {
		// Check if any update hooks have been removed.
		$hooks = $this->check_theme_update_hooks();
		if ( ! $hooks ) {
			return array(
				'desc'     => esc_html__( 'Theme update hooks have been removed.', 'health-check' ),
				'severity' => 'fail',
			);
		}

		// Check if update requests are being blocked.
		$blocked = $this->check_theme_update_pre_request();
		if ( true === $blocked ) {
			return array(
				'desc'     => esc_html__( 'Theme update requests have been blocked.', 'health-check' ),
				'severity' => 'fail',
			);
		}

		// Check if themes have been removed from the update requests.
		$diff = (array) $this->check_theme_update_request_args();
		if ( 0 !== count( $diff ) ) {
			return array(
				'desc'     => sprintf(
					/* translators: %s: List of theme names. */
					esc_html__( 'The following Themes have been removed from update checks: %s.', 'health-check' ),
					implode( ',', $diff )
				),
				'severity' => 'warning',
			);
		}

		return array(
			'desc'     => esc_html__( 'Theme updates should be working as expected.', 'health-check' ),
			'severity' => 'pass',
		);
	}

	
	function check_theme_update_hooks() {
		$test1 = has_filter( 'load-themes.php', 'wp_update_themes' );
		$test2 = has_filter( 'load-update.php', 'wp_update_themes' );
		$test3 = has_filter( 'load-update-core.php', 'wp_update_themes' );
		$test4 = has_filter( 'wp_update_themes', 'wp_update_themes' );
		$test5 = has_filter( 'admin_init', '_maybe_update_themes' );
		$test6 = wp_next_scheduled( 'wp_update_themes' );

		return $test1 && $test2 && $test3 && $test4 && $test5 && $test6;
	}

	
	function check_theme_update_pre_request() {
		add_action( 'pre_http_request', array( $this, 'theme_pre_request_check' ), PHP_INT_MAX, 3 );
		add_action( 'pre_http_request', array( $this, 'block_fake_request' ), PHP_INT_MAX, 3 );

		$this->theme_update_fake_request();

		remove_action( 'pre_http_request', array( $this, 'theme_pre_request_check' ), PHP_INT_MAX );
		remove_action( 'pre_http_request', array( $this, 'block_fake_request' ), PHP_INT_MAX );

		return self::$themes_blocked;
	}

	
	function theme_pre_request_check( $pre, $r, $url ) {
		$check_url = 'api.wordpress.org/themes/update-check/1.1/';
		if ( 0 !== substr_compare( $url, $check_url, -strlen( $check_url ) ) ) {
			return $pre; // Not a theme update request.
		}

		// If not false something is blocking update checks
		if ( false !== $pre ) {
			self::$themes_blocked = (bool) true;
		}

		return $pre;
	}

	
	function check_theme_update_request_args() {
		add_action( 'http_request_args', array( $this, 'theme_request_args_before' ), 1, 2 );
		add_action( 'http_request_args', array( $this, 'theme_request_args_after' ), PHP_INT_MAX, 2 );
		add_action( 'pre_http_request', array( $this, 'block_fake_request' ), PHP_INT_MAX, 3 );

		$this->theme_update_fake_request();

		remove_action( 'http_request_args', array( $this, 'theme_request_args_before' ), 1 );
		remove_action( 'http_request_args', array( $this, 'theme_request_args_after' ), PHP_INT_MAX );
		remove_action( 'pre_http_request', array( $this, 'block_fake_request' ), PHP_INT_MAX );

		$diff = array_diff_key( $this->themes_before['themes'], $this->themes_after['themes'] );

		$titles = array();
		foreach ( $diff as $item ) {
			$titles[] = $item['Title'];
		}

		return $titles;
	}

	
	function theme_request_args_before( $r, $url ) {
		$check_url = 'api.wordpress.org/themes/update-check/1.1/';
		if ( 0 !== substr_compare( $url, $check_url, -strlen( $check_url ) ) ) {
			return $r; // Not a theme update request.
		}

		$this->themes_before = (array) json_decode( $r['body']['themes'], true );

		return $r;
	}


	function theme_request_args_after( $r, $url ) {
		$check_url = 'api.wordpress.org/themes/update-check/1.1/';
		if ( 0 !== substr_compare( $url, $check_url, -strlen( $check_url ) ) ) {
			return $r; // Not a theme update request.
		}

		$this->themes_after = (array) json_decode( $r['body']['themes'], true );

		return $r;
	}

	
	function theme_update_fake_request() {
		$themes            = array();
		$checked           = array();
		$request           = array();
		$installed_themes  = wp_get_themes();
		$translations      = wp_get_installed_translations( 'themes' );
		$request['active'] = get_option( 'stylesheet' );

		foreach ( $installed_themes as $theme ) {
			$checked[ $theme->get_stylesheet() ] = $theme->get( 'Version' );

			$themes[ $theme->get_stylesheet() ] = array(
				'Name'       => $theme->get( 'Name' ),
				'Title'      => $theme->get( 'Name' ),
				'Version'    => $theme->get( 'Version' ),
				'Author'     => $theme->get( 'Author' ),
				'Author URI' => $theme->get( 'AuthorURI' ),
				'Template'   => $theme->get_template(),
				'Stylesheet' => $theme->get_stylesheet(),
			);
		}

		$request['themes'] = $themes;

		$locales = array_values( get_available_languages() );
		$timeout = 3 + (int) ( count( $themes ) / 10 );

		if ( function_exists( 'wp_json_encode' ) ) {
			$options = array(
				'timeout'    => $timeout,
				'body'       => array(
					'themes'       => wp_json_encode( $request ),
					'translations' => wp_json_encode( $translations ),
					'locale'       => wp_json_encode( $locales ),
				),
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			);
		} else {
			$options = array(
				'timeout'    => $timeout,
				'body'       => array(
					'themes'       => json_encode( $request ),
					'translations' => json_encode( $translations ),
					'locale'       => json_encode( $locales ),
				),
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			);
		}

		// Set the URL
		$http_url = 'http://api.wordpress.org/themes/update-check/1.1/';
		$url      = wp_http_supports( array( 'ssl' ) ) ? set_url_scheme( $http_url, 'https' ) : $http_url;

		// Ignore the response. Just need the hooks to fire.
		wp_remote_post( $url, $options );
	}

	
	function block_fake_request( $pre, $r, $url ) {
		switch ( $url ) {
			case 'https://api.wordpress.org/plugins/update-check/1.1/':
				return 'block_request';
			case 'https://api.wordpress.org/themes/update-check/1.1/':
				return 'block_request';
			default:
				return $pre;
		}
	}
}
