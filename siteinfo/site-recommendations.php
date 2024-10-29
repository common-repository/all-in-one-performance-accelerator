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
require_once ABSPATH . '/wp-admin/includes/update.php';
class SiteWarnings
{
   
    
	protected static $instance = null,$plugin;
	
	public function __construct()
	{
    
        
       
    //  if($result['wordpress_version']['status']=='recommended'){
       
    //  }
    
    
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
        add_action('wp_ajax_get_site_status_details', array($this,'get_status_details'));
		add_action('wp_ajax_get_sitestatus_selected_tab', array($this,'get_sitestatus_selected_tab'));
    }
    
	public function get_sitestatus_selected_tab(){
		$tab = sanitize_text_field($_POST['tab']);
		if($tab === 'undefined'){
			$tab_value = get_option('smack_sitestatus_tab');
			if(empty($tab_value)){
				$tab_name = 'error';
				update_option('smack_sitestatus_tab',$tab_name);
			}else{
				update_option('smack_sitestatus_tab',$tab_value);
			}
		}else{
			update_option('smack_sitestatus_tab',$tab);
		}
		$tab_address = get_option('smack_sitestatus_tab');
		$result['tab'] = $tab_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

    public function get_status_details(){
        $result['critical_error']=$this->get_all_errors('critical');
        $result['recommended_warnings']=$this->get_all_errors('recommended'); 
        $result['passed_errors']=$this->get_all_errors('good');
        $result['success'] = true;
	
		$critical_count = 0;
		foreach( $result['critical_error'] as $error_value){
			if($error_value){
				$critical_count++;	
			}
		}
        
				
		
		$recommend_count = 0;
		foreach( $result['recommended_warnings'] as $warning_value){
			if($warning_value){
			
				$recommend_count++;
				
			}
			
		}
		$result['error_count']=$critical_count;
		$result['warning_count']=$recommend_count;
		
		
        echo wp_json_encode($result);
        wp_die();
    }
    public function get_all_errors($type){
        $result['wordpress_version']=$this->get_test_wordpress_version($type);
        $result['plugin_version']=$this->get_test_plugin_version($type);
        $result['theme_version']=$this->get_test_theme_version($type);
        $result['php_version']=$this->get_test_php_version($type);
        $result['sql_server']=$this->get_test_sql_server($type);
        $result['utf8mb4_version']=$this->get_test_utf8mb4_support($type);
        $result['http_status']=$this->get_test_https_status($type);
        $result['ssl_support']=$this->get_test_ssl_support($type);
		$result['dsl_check']=$this->get_test_dsl_check($type);
		$result['admin_notification_check']=$this->get_admin_notification_check($type);
		$result['user_name_check']=$this->get_user_name_check($type);
		$result['weak_password_check']=$this->get_weak_password_check($type);
        $result['scheduled_events']=$this->get_test_scheduled_events($type);
        $result['extension_updates']=$this->get_test_extension_updates($type);
        $result['http_request']=$this->get_test_http_requests($type);
        $result['debug_mode']=$this->get_test_is_in_debug_mode($type);
        $result['timezone']=$this->get_test_timezone_not_utc($type);
        $result['loopback_request']=$this->get_test_loopback_requests($type);
        $result['background_updates']=$this->get_test_background_updates($type);
        $result['php_extensions']=$this->get_test_php_extensions($type);
        return $result;
    }
    public function get_test_wordpress_version($status) {
		$result = array(
			'label'       => '',
			'status'      => '',
			'badge'       => array(
				'label' => __( 'Performance', 'site-status' ),
				'color' => 'blue',
			),
			'description' => '',
			'actions'     => '',
			'test'        => 'wordpress_version',
		);

		$core_current_version = get_bloginfo( 'version' );
		$core_updates         = get_core_updates();

		if ( ! is_array( $core_updates ) ) {
			$result['status'] = 'recommended';

			$result['label'] = sprintf(
				// translators: %s: Your current version of WordPress.
				__( 'WordPress version %s', 'site-status' ),
				$core_current_version
			);

			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'We were unable to check if any new versions of WordPress are available.', 'site-status' )
			);

			$result['actions'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'update-core.php?force-check=1' ) ),
				__( 'Check for updates manually', 'site-status' )
			);
		} else {
			foreach ( $core_updates as $core => $update ) {
				if ( 'upgrade' === $update->response ) {
					$current_version = explode( '.', $core_current_version );
					$new_version     = explode( '.', $update->version );

					$current_major = $current_version[0] . '.' . $current_version[1];
					$new_major     = $new_version[0] . '.' . $new_version[1];

					$result['label'] = sprintf(
						// translators: %s: The latest version of WordPress available.
						__( 'WordPress update available (%s)', 'site-status' ),
						$update->version
					);

					$result['actions'] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'update-core.php' ) ),
						__( 'Install the latest version of WordPress', 'site-status' )
					);

					if ( $current_major !== $new_major ) {
						// This is a major version mismatch.
						$result['status']      = 'recommended';
						$result['description'] = sprintf(
							'<p>%s</p>',
							__( 'A new version of WordPress is available.', 'site-status' )
						);
					} else {
						// This is a minor version, sometimes considered more critical.
						$result['status']         = 'critical';
						$result['badge']['label'] = __( 'Security', 'site-status' );
						$result['description']    = sprintf(
							'<p>%s</p>',
							__( 'A new minor update is available for your site. Because minor updates often address security, it&#8217;s important to install them.', 'site-status' )
						);
					}
				} else {
					$result['status'] = 'good';
					$result['label']  = sprintf(
						// translators: %s: The current version of WordPress installed on this site.
						__( 'Your WordPress version is up to date (%s)', 'site-status' ),
						$core_current_version
					);

					$result['description'] = sprintf(
						'<p>%s</p>',
						__( 'You are currently running the latest version of WordPress available, keep it up!', 'site-status' )
					);
				}
			}
		}
        if($result['status']== $status){
            return $result;
        }
		
    }
    
    public function get_test_plugin_version($status) {
		$result = array(
			'label'       => __( 'Your plugins are up to date', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Plugins extend your site&#8217;s functionality with things like contact forms, ecommerce and much more. That means they have deep access to your site, so it&#8217;s vital to keep them up to date.', 'site-status' )
			),
			'actions'     => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'plugins.php' ) ),
				__( 'Manage your plugins', 'site-status' )
			),
			'test'        => 'plugin_version',
		);

		$plugins        = get_plugins();
		$plugin_updates = get_plugin_updates();

		$plugins_have_updates = false;
		$plugins_active       = 0;
		$plugins_total        = 0;
		$plugins_need_update  = 0;

		// Loop over the available plugins and check their versions and active state.
		foreach ( $plugins as $plugin_path => $plugin ) {
			$plugins_total++;

			if ( is_plugin_active( $plugin_path ) ) {
				$plugins_active++;
			}

			$plugin_version = $plugin['Version'];

			if ( array_key_exists( $plugin_path, $plugin_updates ) ) {
				$plugins_need_update++;
				$plugins_have_updates = true;
			}
		}

		// Add a notice if there are outdated plugins.
		if ( $plugins_need_update > 0 ) {
			$result['status'] = 'critical';

			$result['label'] = __( 'You have plugins waiting to be updated', 'site-status' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %d: The number of outdated plugins. */
					_n(
						'Your site has %d plugin waiting to be updated.',
						'Your site has %d plugins waiting to be updated.',
						$plugins_need_update,
						'site-status'
					),
					$plugins_need_update
				)
			);

			$result['actions'] .= sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'plugins.php?plugin_status=upgrade' ) ),
				__( 'Update your plugins', 'site-status' )
			);
		} else {
			if ( 1 === $plugins_active ) {
				$result['description'] .= sprintf(
					'<p>%s</p>',
					__( 'Your site has 1 active plugin, and it is up to date.', 'site-status' )
				);
			} else {
				$result['description'] .= sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: %d: The number of active plugins. */
						_n(
							'Your site has %d active plugin, and they are all up to date.',
							'Your site has %d active plugins, and they are all up to date.',
							$plugins_active,
							'site-status'
						),
						$plugins_active
					)
				);
			}
		}

		// Check if there are inactive plugins.
		if ( $plugins_total > $plugins_active && ! is_multisite() ) {
			$unused_plugins = $plugins_total - $plugins_active;

			$result['status'] = 'recommended';

			$result['label'] = __( 'You should remove inactive plugins', 'site-status' );

			$result['description'] .= sprintf(
				'<p>%s %s</p>',
				sprintf(
					/* translators: %d: The number of inactive plugins. */
					_n(
						'Your site has %d inactive plugin.',
						'Your site has %d inactive plugins.',
						$unused_plugins,
						'site-status'
					),
					$unused_plugins
				),
				__( 'Inactive plugins are tempting targets for attackers. If you&#8217;re not going to use a plugin, we recommend you remove it.', 'site-status' )
			);

			$result['actions'] .= sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'plugins.php?plugin_status=inactive' ) ),
				__( 'Manage inactive plugins', 'site-status' )
			);
		}

		if($result['status']== $status){
            return $result;
        }
    }
    
    public function get_test_theme_version($status) {
		$result = array(
			'label'       => __( 'Your themes are up to date', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Themes add your site&#8217;s look and feel. It&#8217;s important to keep them up to date, to stay consistent with your brand and keep your site secure.', 'site-status' )
			),
			'actions'     => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'themes.php' ) ),
				__( 'Manage your themes', 'site-status' )
			),
			'test'        => 'theme_version',
		);

		$theme_updates = get_theme_updates();

		$themes_total        = 0;
		$themes_need_updates = 0;
		$themes_inactive     = 0;

		// This value is changed during processing to determine how many themes are considered a reasonable amount.
		$allowed_theme_count = 1;

		$has_default_theme   = false;
		$has_unused_themes   = false;
		$show_unused_themes  = true;
		$using_default_theme = false;

		// Populate a list of all themes available in the install.
		$all_themes   = wp_get_themes();
		$active_theme = wp_get_theme();

		foreach ( $all_themes as $theme_slug => $theme ) {
			$themes_total++;

			if ( WP_DEFAULT_THEME === $theme_slug ) {
				$has_default_theme = true;

				if ( get_stylesheet() === $theme_slug ) {
					$using_default_theme = true;
				}
			}

			if ( array_key_exists( $theme_slug, $theme_updates ) ) {
				$themes_need_updates++;
			}
		}

		// If this is a child theme, increase the allowed theme count by one, to account for the parent.
		if ( $active_theme->parent() ) {
			$allowed_theme_count++;

			if ( $active_theme->get_template() === WP_DEFAULT_THEME ) {
				$using_default_theme = true;
			}
		}

		// If there's a default theme installed and not in use, we count that as allowed as well.
		if ( $has_default_theme && ! $using_default_theme ) {
			$allowed_theme_count++;
		}

		if ( $themes_total > $allowed_theme_count ) {
			$has_unused_themes = true;
			$themes_inactive   = ( $themes_total - $allowed_theme_count );
		}

		// Check if any themes need to be updated.
		if ( $themes_need_updates > 0 ) {
			$result['status'] = 'critical';

			$result['label'] = __( 'You have themes waiting to be updated', 'site-status' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %d: The number of outdated themes. */
					_n(
						'Your site has %d theme waiting to be updated.',
						'Your site has %d themes waiting to be updated.',
						$themes_need_updates,
						'site-status'
					),
					$themes_need_updates
				)
			);
		} else {
			// Give positive feedback about the site being good about keeping things up to date.
			if ( 1 === $themes_total ) {
				$result['description'] .= sprintf(
					'<p>%s</p>',
					__( 'Your site has 1 installed theme, and it is up to date.', 'site-status' )
				);
			} else {
				$result['description'] .= sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: %d: The number of themes. */
						_n(
							'Your site has %d installed theme, and they are all up to date.',
							'Your site has %d installed themes, and they are all up to date.',
							$themes_total,
							'site-status'
						),
						$themes_total
					)
				);
			}
		}

		if ( $has_unused_themes && $show_unused_themes && ! is_multisite() ) {

			// This is a child theme, so we want to be a bit more explicit in our messages.
			if ( $active_theme->parent() ) {
				// Recommend removing inactive themes, except a default theme, your current one, and the parent theme.
				$result['status'] = 'recommended';

				$result['label'] = __( 'You should remove inactive themes', 'site-status' );

				if ( $using_default_theme ) {
					$result['description'] .= sprintf(
						'<p>%s %s</p>',
						sprintf(
							/* translators: %d: The number of inactive themes. */
							_n(
								'Your site has %d inactive theme.',
								'Your site has %d inactive themes.',
								$themes_inactive,
								'site-status'
							),
							$themes_inactive
						),
						sprintf(
							/* translators: 1: The currently active theme. 2: The active theme's parent theme. */
							__( 'To enhance your site&#8217;s security, we recommend you remove any themes you&#8217;re not using. You should keep your current theme, %1$s, and %2$s, its parent theme.', 'site-status' ),
							$active_theme->name,
							$active_theme->parent()->name
						)
					);
				} else {
					$result['description'] .= sprintf(
						'<p>%s %s</p>',
						sprintf(
							/* translators: %d: The number of inactive themes. */
							_n(
								'Your site has %d inactive theme.',
								'Your site has %d inactive themes.',
								$themes_inactive,
								'site-status'
							),
							$themes_inactive
						),
						sprintf(
							/* translators: 1: The default theme for WordPress. 2: The currently active theme. 3: The active theme's parent theme. */
							__( 'To enhance your site&#8217;s security, we recommend you remove any themes you&#8217;re not using. You should keep %1$s, the default WordPress theme, %2$s, your current theme, and %3$s, its parent theme.', 'site-status' ),
							WP_DEFAULT_THEME,
							$active_theme->name,
							$active_theme->parent()->name
						)
					);
				}
			} else {
				// Recommend removing all inactive themes.
				$result['status'] = 'recommended';

				$result['label'] = __( 'You should remove inactive themes', 'site-status' );

				if ( $using_default_theme ) {
					$result['description'] .= sprintf(
						'<p>%s %s</p>',
						sprintf(
							/* translators: 1: The amount of inactive themes. 2: The currently active theme. */
							_n(
								'Your site has %1$d inactive theme, other than %2$s, your active theme.',
								'Your site has %1$d inactive themes, other than %2$s, your active theme.',
								$themes_inactive,
								'site-status'
							),
							$themes_inactive,
							$active_theme->name
						),
						__( 'We recommend removing any unused themes to enhance your site&#8217;s security.', 'site-status' )
					);
				} else {
					$result['description'] .= sprintf(
						'<p>%s %s</p>',
						sprintf(
							/* translators: 1: The amount of inactive themes. 2: The default theme for WordPress. 3: The currently active theme. */
							_n(
								'Your site has %1$d inactive theme, other than %2$s, the default WordPress theme, and %3$s, your active theme.',
								'Your site has %1$d inactive themes, other than %2$s, the default WordPress theme, and %3$s, your active theme.',
								$themes_inactive,
								'site-status'
							),
							$themes_inactive,
							WP_DEFAULT_THEME,
							$active_theme->name
						),
						__( 'We recommend removing any unused themes to enhance your site&#8217;s security.', 'site-status' )
					);
				}
			}
		}

		// If not default Twenty* theme exists.
		if ( ! $has_default_theme ) {
			$result['status'] = 'recommended';

			$result['label'] = __( 'Have a default theme available', 'site-status' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				__( 'Your site does not have any default theme. Default themes are used by WordPress automatically if anything is wrong with your normal theme.', 'site-status' )
			);
		}

		if($result['status']== $status){
            return $result;
        }
    }
    
    public function get_test_php_version($status) {
		$response = wp_check_php_version();
       
		$result = array(
			'label'       => sprintf(
				// translators: %s: The current PHP version.
				__( 'PHP is up to date (%s)', 'site-status' ),
				PHP_VERSION
			),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'PHP is the programming language we use to build and maintain WordPress. Newer versions of PHP are both faster and more secure, so updating will have a positive effect on your site&#8217;s performance.', 'site-status' )
			),
			'actions'     => sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s <span class="screen-reader-text">%s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
				esc_url( wp_get_update_php_url() ),
				__( 'Learn more about updating PHP', 'site-status' ),
				/* translators: accessibility text */
				__( '(opens in a new tab)', 'site-status' )
			),
			'test'        => 'php_version',
		);

		// PHP is up to date.
		if ( ! $response || version_compare( PHP_VERSION, $response['recommended_version'], '>=' ) ) {
            if($result['status']== $status){
                return $result;
            }
		}

		// The PHP version is older than the recommended version, but still acceptable.
		if ( $response['is_supported'] ) {
			$result['label']  = __( 'We recommend that you update PHP', 'site-status' );
			$result['status'] = 'recommended';

            if($result['status']== $status){
                return $result;
            }
		}

		// The PHP version is only receiving security fixes.
		if ( $response['is_secure'] ) {
			$result['label']  = __( 'Your PHP version should be updated', 'site-status' );
			$result['status'] = 'recommended';

            if($result['status']== $status){
                return $result;
            }
		}

		// Anything no longer secure must be updated.
		$result['label']          = __( 'Your PHP version requires an update', 'site-status' );
		$result['status']         = 'critical';
		$result['badge']['label'] = __( 'Security', 'site-status' );

        if($result['status']== $status){
            return $result;
        }
	}

	public function get_test_php_extensions($status) {
		$result = array(
			'label'       => __( 'Required and recommended modules are installed', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p><p>%s</p>',
				__( 'PHP modules perform most of the tasks on the server that make your site run. Any changes to these must be made by your server administrator.', 'site-status' ),
				sprintf(
					/* translators: %s: Link to the hosting group page about recommended PHP modules. */
					__( 'The WordPress Hosting Team maintains a list of those modules, both recommended and required, in %s.', 'site-status' ),
					sprintf(
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s <span class="screen-reader-text">%3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>',
						/* translators: Localized team handbook, if one exists. */
						esc_url( __( 'https://make.wordpress.org/hosting/handbook/handbook/server-environment/#php-extensions', 'site-status' ) ),
						__( 'the team handbook', 'site-status' ),
						/* translators: accessibility text */
						__( '(opens in a new tab)', 'site-status' )
					)
				)
			),
			'actions'     => '',
			'test'        => 'php_extensions',
		);

		$modules = array(
			'bcmath'    => array(
				'function' => 'bcadd',
				'required' => false,
			),
			'curl'      => array(
				'function' => 'curl_version',
				'required' => false,
			),
			'exif'      => array(
				'function' => 'exif_read_data',
				'required' => false,
			),
			'filter'    => array(
				'function' => 'filter_list',
				'required' => false,
			),
			'fileinfo'  => array(
				'function' => 'finfo_file',
				'required' => false,
			),
			'mod_xml'   => array(
				'extension' => 'libxml',
				'required'  => false,
			),
			'mysqli'    => array(
				'function' => 'mysqli_connect',
				'required' => false,
			),
			'libsodium' => array(
				'constant'            => 'SODIUM_LIBRARY_VERSION',
				'required'            => false,
				'php_bundled_version' => '7.2.0',
			),
			'openssl'   => array(
				'function' => 'openssl_encrypt',
				'required' => false,
			),
			'pcre'      => array(
				'function' => 'preg_match',
				'required' => false,
			),
			'imagick'   => array(
				'extension' => 'imagick',
				'required'  => false,
			),
			'gd'        => array(
				'extension'    => 'gd',
				'required'     => false,
				'fallback_for' => 'imagick',
			),
			'mcrypt'    => array(
				'extension'    => 'mcrypt',
				'required'     => false,
				'fallback_for' => 'libsodium',
			),
			'xmlreader' => array(
				'extension'    => 'xmlreader',
				'required'     => false,
				'fallback_for' => 'xml',
			),
			'zlib'      => array(
				'extension'    => 'zlib',
				'required'     => false,
				'fallback_for' => 'zip',
			),
			'mbstring'  => array(
				'extension' => 'mbstring',
				'required'  => true,
			),
			'json'      => array(
				'extension' => 'json',
				'required'  => true,
			),
		);

		
		$modules = apply_filters( 'site_status_test_php_modules', $modules );

		$failures = array();

		foreach ( $modules as $library => $module ) {
			$extension = ( isset( $module['extension'] ) ? $module['extension'] : null );
			$function  = ( isset( $module['function'] ) ? $module['function'] : null );
			$constant  = ( isset( $module['constant'] ) ? $module['constant'] : null );

			// If this module is a fallback for another function, check if that other function passed.
			if ( isset( $module['fallback_for'] ) ) {
				
				if ( isset( $failures[ $module['fallback_for'] ] ) ) {
					$module['required'] = true;
				} else {
					continue;
				}
			}

			if ( ! $this->test_php_extension_availability( $extension, $function, $constant ) && ( ! isset( $module['php_bundled_version'] ) || version_compare( PHP_VERSION, $module['php_bundled_version'], '<' ) ) ) {
				if ( $module['required'] ) {
					$result['status'] = 'critical';

					$class         = 'error';
					$screen_reader = __( 'Error', 'site-status' );
					$message       = sprintf(
						/* translators: %s: The module name. */
						__( 'The required module, %s, is not installed, or has been disabled.', 'site-status' ),
						$library
					);
				} else {
					$class         = 'warning';
					$screen_reader = __( 'Warning', 'site-status' );
					$message       = sprintf(
						/* translators: %s: The module name. */
						__( 'The optional module, %s, is not installed, or has been disabled.', 'site-status' ),
						$library
					);
				}

				if ( ! $module['required'] && 'good' === $result['status'] ) {
					$result['status'] = 'recommended';
				}

				$failures[ $library ] = "<span class='$class'><span class='screen-reader-text'>$screen_reader</span></span> $message";
			}
		}

		if ( ! empty( $failures ) ) {
			$output = '<ul>';

			foreach ( $failures as $failure ) {
				$output .= sprintf(
					'<li>%s</li>',
					$failure
				);
			}

			$output .= '</ul>';
		}

		if ( 'good' !== $result['status'] ) {
			if ( 'recommended' === $result['status'] ) {
				$result['label'] = __( 'One or more recommended modules are missing', 'site-status' );
			}
			if ( 'critical' === $result['status'] ) {
				$result['label'] = __( 'One or more required modules are missing', 'site-status' );
			}

			$result['description'] .= sprintf(
				'<p>%s</p>',
				$output
			);
		}

        if($result['status']== $status){
            return $result;
        }
	}

	public function get_test_dsl_check($status) {
				
		$result = array(
			'label'       => __( 'SSL cache not applied to page', 'site-status' ),
			'status'      => 'critical',
			'badge'       => array(
				'label' => __( 'Performance', 'site-status' ),
				'color' => 'red',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( '  Your site is not using SSL. This is not secure and is hurting your SEO ranking too. Certain browsers are starting to label sites without SSL as "Not Secure" which may cause users to not trust your site.  ', 'site-status' )
			),
			'actions'     => '',
			'test'        => 'dsl_check',
		);
		
		if ( 'critical' === $result['status'] ) {
			$result['label'] = __( 'SSL cache not applied in your site', 'site-status' );
		}
		 
		 if($result['status']== $status){
		return $result;
	}
	
}
public function get_user_name_check($status) {
		
	$result = array(
		'label'       => __( 'admin check', 'site-status' ),
		'status'      => 'critical',
		'badge'       => array(
			'label' => __( 'Performance', 'site-status' ),
			'color' => 'red',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'There is a user admin on your site.Hackers use this username when trying to gain access to your site', 'site-status' )
		),
		'actions'     => '',
		'test'        => 'user_name_check',
	);
	
	if ( 'critical' === $result['status'] ) {
		$result['label'] = __( 'Admin check - username with "admin"', 'site-status' );
	}
	 
	 if($result['status']== $status){
	return $result;
}

}
public function get_weak_password_check($status) {
		
	$result = array(
		'label'       => __( 'admin check', 'site-status' ),
		'status'      => 'critical',
		'badge'       => array(
			'label' => __( 'Performance', 'site-status' ),
			'color' => 'red',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'weak password is not secure on your site', 'site-status' )
		),
		'actions'     => '',
		'test'        => 'weak_password_check',
	);
	
	if ( 'critical' === $result['status'] ) {
		$result['label'] = __( 'Weak password check', 'site-status' );
	}
	 
	 if($result['status']== $status){
	return $result;
}

}
public function get_admin_notification_check($status) {
		
	$result = array(
		'label'       => __( 'admin check', 'site-status' ),
		'status'      => 'critical',
		'badge'       => array(
			'label' => __( 'Performance', 'site-status' ),
			'color' => 'red',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'Allows you to gather information about your WordPress and server configuration that you may easily share with support representatives for themes, plugins or on the official WordPress.org support forums', 'site-status' )
		),
		'actions'     => '',
		'test'        => 'admin_notification_check',
	);


	if ( 'critical' === $result['status'] ) {
		$result['label'] = __( 'Catch all the admin notification and list out there', 'site-status' );
	}
	 
	 if($result['status']== $status){
	return $result;
}

}


	private function test_php_extension_availability( $extension = null, $function = null, $constant = null ) {
		// If no extension or function is passed, claim to fail testing, as we have nothing to test against.
		if ( ! $extension && ! $function && ! $constant ) {
			return false;
		}

		if ( $extension && ! extension_loaded( $extension ) ) {
			return false;
		}
		if ( $function && ! function_exists( $function ) ) {
			return false;
		}
		if ( $constant && ! defined( $constant ) ) {
			return false;
		}

		return true;
	}


    public function get_test_sql_server($status) {
		$result = array(
			'label'       => __( 'SQL server is up to date', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'The SQL server is a required piece of software for the database WordPress uses to store all your site&#8217;s content and settings.', 'site-status' )
			),
			'actions'     => sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s <span class="screen-reader-text">%s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
				/* translators: Localized version of WordPress requirements if one exists. */
				esc_url( __( 'https://wordpress.org/about/requirements/', 'site-status' ) ),
				__( 'Read more about what WordPress requires to run.', 'site-status' ),
				/* translators: accessibility text */
				__( '(opens in a new tab)', 'site-status' )
			),
			'test'        => 'sql_server',
		);

		$db_dropin = file_exists( WP_CONTENT_DIR . '/db.php' );

		if ( ! isset($this->mysql_rec_version_check) ) {
			$result['status'] = 'recommended';

			$result['label'] = __( 'Outdated SQL server', 'site-status' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: 1: The database engine in use (MySQL or MariaDB). 2: Database server recommended version number. */
					__( 'For optimal performance and security reasons, we recommend running %1$s version %2$s or higher. Contact your web hosting company to correct this.', 'site-status' ),
					( isset($this->is_mariadb) ? 'MariaDB' : 'MySQL' ),
					isset($this->health_check_mysql_rec_version)
				)
			);
		}

		if ( ! isset($this->mysql_min_version_check) ) {
			$result['status'] = 'critical';

			$result['label']          = __( 'Severely outdated SQL server', 'site-status' );
			$result['badge']['label'] = __( 'Security', 'site-status' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: 1: The database engine in use (MySQL or MariaDB). 2: Database server minimum version number. */
					__( 'WordPress requires %1$s version %2$s or higher. Contact your web hosting company to correct this.', 'site-status' ),
					( isset($this->is_mariadb) ? 'MariaDB' : 'MySQL' ),
					isset($this->health_check_mysql_required_version)
				)
			);
		}

		if ( $db_dropin ) {
			$result['description'] .= sprintf(
				'<p>%s</p>',
				wp_kses(
					sprintf(
						/* translators: 1: The name of the drop-in. 2: The name of the database engine. */
						__( 'You are using a %1$s drop-in which might mean that a %2$s database is not being used.', 'site-status' ),
						'<code>wp-content/db.php</code>',
						( isset($this->is_mariadb) ? 'MariaDB' : 'MySQL' )
					),
					array(
						'code' => true,
					)
				)
			);
		}

        if($result['status']== $status){
            return $result;
        }
    }

    public function get_test_utf8mb4_support($status) {
		global $wpdb;

		$result = array(
			'label'       => __( 'UTF8MB4 is supported', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'UTF8MB4 is a database storage attribute that makes sure your site can store non-English text and other strings (for instance emoticons) without unexpected problems.', 'site-status' )
			),
			'actions'     => '',
			'test'        => 'utf8mb4_support',
		);

		if ( ! isset($this->is_mariadb) ) {
			if ( version_compare( isset($this->mysql_server_version), '5.5.3', '<' ) ) {
				$result['status'] = 'recommended';

				$result['label'] = __( 'utf8mb4 requires a MySQL update', 'site-status' );

				$result['description'] .= sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: %s: Version number. */
						__( 'WordPress&#8217; utf8mb4 support requires MySQL version %s or greater. Please contact your server administrator.', 'site-status' ),
						'5.5.3'
					)
				);
			} else {
				$result['description'] .= sprintf(
					'<p>%s</p>',
					__( 'Your MySQL version supports utf8mb4.', 'site-status' )
				);
			}
		} else { // MariaDB introduced utf8mb4 support in 5.5.0
			if ( version_compare( isset($this->mysql_server_version), '5.5.0', '<' ) ) {
				$result['status'] = 'recommended';

				$result['label'] = __( 'utf8mb4 requires a MariaDB update', 'site-status' );

				$result['description'] .= sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: %s: Version number. */
						__( 'WordPress&#8217; utf8mb4 support requires MariaDB version %s or greater. Please contact your server administrator.', 'site-status' ),
						'5.5.0'
					)
				);
			} else {
				$result['description'] .= sprintf(
					'<p>%s</p>',
					__( 'Your MariaDB version supports utf8mb4.', 'site-status' )
				);
			}
		}

		if ( $wpdb->use_mysqli ) {
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_get_client_info
			$mysql_client_version = mysqli_get_client_info();
		} else {
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysql_get_client_info
			$mysql_client_version = mysqli_get_client_info();
		}

		/*
		 * libmysql has supported utf8mb4 since 5.5.3, same as the MySQL server.
		 * mysqlnd has supported utf8mb4 since 5.0.9.
		 */
		if ( false !== strpos( $mysql_client_version, 'mysqlnd' ) ) {
			$mysql_client_version = preg_replace( '/^\D+([\d.]+).*/', '$1', $mysql_client_version );
			if ( version_compare( $mysql_client_version, '5.0.9', '<' ) ) {
				$result['status'] = 'recommended';

				$result['label'] = __( 'utf8mb4 requires a newer client library', 'site-status' );

				$result['description'] .= sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: 1: Name of the library, 2: Number of version. */
						__( 'WordPress&#8217; utf8mb4 support requires MySQL client library (%1$s) version %2$s or newer. Please contact your server administrator.', 'site-status' ),
						'mysqlnd',
						'5.0.9'
					)
				);
			}
		} else {
			if ( version_compare( $mysql_client_version, '5.5.3', '<' ) ) {
				$result['status'] = 'recommended';

				$result['label'] = __( 'utf8mb4 requires a newer client library', 'site-status' );

				$result['description'] .= sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: 1: Name of the library, 2: Number of version. */
						__( 'WordPress&#8217; utf8mb4 support requires MySQL client library (%1$s) version %2$s or newer. Please contact your server administrator.', 'site-status' ),
						'libmysql',
						'5.5.3'
					)
				);
			}
		}

        if($result['status']== $status){
            return $result;
        }
	}
    
    public function get_test_https_status($status) {
		$result = array(
			'label'       => __( 'Your website is using an active HTTPS connection.', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'An HTTPS connection is needed for many features on the web today, it also gains the trust of your visitors by helping to protecting their online privacy.', 'site-status' )
			),
			'actions'     => sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s <span class="screen-reader-text">%s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
				/* translators: Documentation explaining HTTPS and why it should be used. */
				esc_url( __( 'https://wordpress.org/support/article/why-should-i-use-https/', 'site-status' ) ),
				__( 'Read more about why you should use HTTPS', 'site-status' ),
				/* translators: accessibility text */
				__( '(opens in a new tab)', 'site-status' )
			),
			'test'        => 'https_status',
		);

		if ( is_ssl() ) {
			$wp_url   = get_bloginfo( 'wpurl' );
			$site_url = get_bloginfo( 'url' );

			if ( 'https' !== substr( $wp_url, 0, 5 ) || 'https' !== substr( $site_url, 0, 5 ) ) {
				$result['status'] = 'recommended';

				$result['label'] = __( 'Only parts of your site are using HTTPS', 'site-status' );

				$result['description'] = sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: %s: URL to Settings > General to change options. */
						__( 'You are accessing this website using HTTPS, but your <a href="%s">WordPress Address</a> is not set up to use HTTPS by default.', 'site-status' ),
						esc_url( admin_url( 'options-general.php' ) )
					)
				);

				$result['actions'] .= sprintf(
					'<p><a href="%s">%s</a></p>',
					esc_url( admin_url( 'options-general.php' ) ),
					__( 'Update your site addresses', 'site-status' )
				);
			}
		} else {
			$result['status'] = 'recommended';

			$result['label'] = __( 'Your site does not use HTTPS', 'site-status' );
		}

        if($result['status']== $status){
            return $result;
        }
    }
    
	public function get_test_ssl_support($status) {
		$result = array(
			'label'       => '',
			'status'      => '',
			'badge'       => array(
				'label' => __( 'Security', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Securely communicating between servers are needed for transactions such as fetching files, conducting sales on store sites, and much more.', 'site-status' )
			),
			'actions'     => '',
			'test'        => 'ssl_support',
		);

		$supports_https = wp_http_supports( array( 'ssl' ) );

		if ( $supports_https ) {
			$result['status'] = 'good';

			$result['label'] = __( 'Your site can communicate securely with other services', 'site-status' );
		} else {
			$result['status'] = 'critical';

			$result['label'] = __( 'Your site is unable to communicate securely with other services', 'site-status' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				__( 'Talk to your web host about OpenSSL support for PHP.', 'site-status' )
			);
		}

        if($result['status']== $status){
            return $result;
        }
    }
    
    public function get_test_scheduled_events($status) {
		$result = array(
			'label'       => __( 'Scheduled events are running', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Scheduled events are what periodically looks for updates to plugins, themes and WordPress itself. It is also what makes sure scheduled posts are published on time. It may also be used by various plugins to make sure that planned actions are executed.', 'site-status' )
			),
			'actions'     => '',
			'test'        => 'scheduled_events',
		);

		$schedule = new Cron_status();

		if ( is_wp_error( $schedule->has_missed_cron() ) ) {
			$result['status'] = 'critical';

			$result['label'] = __( 'It was not possible to check your scheduled events', 'site-status' );

			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: The error message returned while from the cron scheduler. */
					__( 'While trying to test your site&#8217;s scheduled events, the following error was returned: %s', 'site-status' ),
					$schedule->has_missed_cron()->get_error_message()
				)
			);
		} elseif ( $schedule->has_missed_cron() ) {
			$result['status'] = 'recommended';

			$result['label'] = __( 'A scheduled event has failed', 'site-status' );

			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: The name of the failed cron event. */
					__( 'The scheduled event, %s, failed to run. Your site still works, but this may indicate that scheduling posts or automated updates may not work as intended.', 'site-status' ),
					$schedule->last_missed_cron
				)
			);
		} elseif ( $schedule->has_late_cron() ) {
			$result['status'] = 'recommended';

			$result['label'] = __( 'A scheduled event is late', 'site-status' );

			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: The name of the late cron event. */
					__( 'The scheduled event, %s, is late to run. Your site still works, but this may indicate that scheduling posts or automated updates may not work as intended.', 'site-status' ),
					$schedule->last_late_cron
				)
			);
		}

        if($result['status']== $status){
            return $result;
        }
	}


    public function get_test_background_updates($status) {
		$result = array(
			'label'       => __( 'Background updates are working', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Background updates ensure that WordPress can auto-update if a security update is released for the version you are currently using.', 'site-status' )
			),
			'actions'     => '',
			'test'        => 'background_updates',
		);

		// Run the auto-update tests in a separate class,
		// as there are many considerations to be made.
	//	$automatic_updates = new Auto_Updates_status();
		$tests             = $this->run_tests();
    
		$output = '<ul>';

		foreach ( $tests as $test ) {
			$severity_string = __( 'Passed', 'site-status' );

			if ( 'fail' === $test->severity ) {
				$result['label'] = __( 'Background updates are not working as expected', 'site-status' );

				$result['status'] = 'critical';

				$severity_string = __( 'Error', 'site-status' );
			}

			if ( 'warning' === $test->severity && 'good' === $result['status'] ) {
				$result['label'] = __( 'Background updates may not be working properly', 'site-status' );

				$result['status'] = 'recommended';

				$severity_string = __( 'Warning', 'site-status' );
			}

			$output .= sprintf(
				'<li><span class="%s"><span class="screen-reader-text">%s</span></span> %s</li>',
				esc_attr( $test->severity ),
				$severity_string,
				$test->description
			);
		}

		$output .= '</ul>';

		if ( 'good' !== $result['status'] ) {
			$result['description'] .= sprintf(
				'<p>%s</p>',
				$output
			);
		}

        if($result['status']== $status){
            return $result;
        }
    }

    public function get_test_extension_updates($status) {
		$result = array(
			'label'       => esc_html__( 'Plugin and theme updates are working', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => esc_html__( 'Security', 'site-status' ),
				'color' => 'red',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__( 'Plugins or themes may have their own way of handling updates, which could break or negatively impact normal updates in WordPress.', 'site-status' )
			),
			'actions'     => '',
			'test'        => 'extension_updates',
		);

		$updates = new Check_Updates_status();
		$tests   = $updates->run_tests();

		$output = '<ul>';

		foreach ( $tests as $test ) {
			$severity_string = esc_html__( 'Passed', 'site-status' );

			if ( 'fail' === $test->severity ) {
				$result['label'] = esc_html__( 'Plugin or theme updates are not working', 'site-status' );

				$result['status'] = 'critical';

				$severity_string = esc_html__( 'Error', 'site-status' );
			}

			if ( 'warning' === $test->severity && 'good' === $result['status'] ) {
				$result['label'] = esc_html__( 'Some plugin or theme updates may not work as expected', 'site-status' );

				$result['status'] = 'recommended';

				$severity_string = esc_html__( 'Warning', 'site-status' );
			}

			$output .= sprintf(
				'<li><span class="%s"><span class="screen-reader-text">%s</span></span> %s</li>',
				esc_attr( $test->severity ),
				$severity_string,
				$test->desc
			);
		}

		$output .= '</ul>';

		if ( 'critical' === $result['status'] ) {
			$result['description'] .= sprintf(
				'<p>%s</p>',
				$output
			);
		}

        if($result['status']== $status){
            return $result;
        }
	}

   
    
    public function get_test_http_requests($status) {
		$result = array(
			'label'       => __( 'HTTP requests seem to be working as expected', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'It is possible for site maintainers to block all, or some, communication to other sites and services. If set up incorrectly, this may prevent plugins and themes from working as intended.', 'site-status' )
			),
			'actions'     => '',
			'test'        => 'http_requests',
		);

		$blocked = false;
		$hosts   = array();

		if ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) {
			$blocked = true;
		}

		if ( defined( 'WP_ACCESSIBLE_HOSTS' ) ) {
			$hosts = explode( ',', WP_ACCESSIBLE_HOSTS );
		}

		if ( $blocked && 0 === sizeof( $hosts ) ) {
			$result['status'] = 'critical';

			$result['label'] = __( 'HTTP requests are blocked', 'site-status' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Name of the constant used. */
					__( 'HTTP requests have been blocked by the %s constant, with no allowed hosts.', 'site-status' ),
					'<code>WP_HTTP_BLOCK_EXTERNAL</code>'
				)
			);
		}

		if ( $blocked && 0 < sizeof( $hosts ) ) {
			$result['status'] = 'recommended';

			$result['label'] = __( 'HTTP requests are partially blocked', 'site-status' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: 1: Name of the constant used. 2: List of hostnames whitelisted. */
					__( 'HTTP requests have been blocked by the %1$s constant, with some hosts whitelisted: %2$s.', 'site-status' ),
					'<code>WP_HTTP_BLOCK_EXTERNAL</code>',
					implode( ',', $hosts )
				)
			);
		}

        if($result['status']== $status){
            return $result;
        }
    }
    
    public function get_test_is_in_debug_mode($status) {
		$result = array(
			'label'       => __( 'Your site is not set to output debug information', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Debug mode is often enabled to gather more details about an error or site failure, but may contain sensitive information which should not be available on a publicly available website.', 'site-status' )
			),
			'actions'     => sprintf(
				'<p><a href="%s" target="_blank" rel="noopener noreferrer">%s <span class="screen-reader-text">%s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
				/* translators: Documentation explaining debugging in WordPress. */
				esc_url( __( 'https://wordpress.org/support/article/debugging-in-wordpress/', 'site-status' ) ),
				__( 'Read about debugging in WordPress.', 'site-status' ),
				/* translators: accessibility text */
				__( '(opens in a new tab)', 'site-status' )
			),
			'test'        => 'is_in_debug_mode',
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				$result['label'] = __( 'Your site is set to log errors to a potentially public file.', 'site-status' );

				$result['status'] = 'critical';

				$result['description'] .= sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: %s: WP_DEBUG_LOG */
						__( 'The value, %s, has been added to this website&#8217;s configuration file. This means any errors on the site will be written to a file which is potentially available to normal users.', 'site-status' ),
						'<code>WP_DEBUG_LOG</code>'
					)
				);
			}

			if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
				$result['label'] = __( 'Your site is set to display errors to site visitors', 'site-status' );

				$result['status'] = 'critical';

				$result['description'] .= sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: 1: WP_DEBUG_DISPLAY, 2: WP_DEBUG */
						__( 'The value, %1$s, has either been enabled by %2$s or added to your configuration file. This will make errors display on the front end of your site.', 'site-status' ),
						'<code>WP_DEBUG_DISPLAY</code>',
						'<code>WP_DEBUG</code>'
					)
				);
			}
		}

        if($result['status']== $status){
            return $result;
        }
    }
    
    public function get_test_timezone_not_utc($status) {
		$result = array(
			'label'       => __( 'Your site uses localized timezones', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Daylight Savings Time (DST) may affect the times used and shown by your site, and using an UTC offset, instead of a localized timezone, means that the site does not get automatic DST updates.', 'site-status' )
			),
			'actions'     => '',
			'test'        => 'timezone_not_utc',
		);

		$timezone = get_option( 'timezone_string', null );

		if ( empty( $timezone ) || 'UTC' === substr( $timezone, 0, 3 ) ) {
			$result['status'] = 'recommended';
			$result['label']  = __( 'Your site is not using localized timezones', 'site-status' );

			$result['actions'] .= sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'options-general.php' ) ),
				__( 'Update your site timezone', 'site-status' )
			);
		}

        if($result['status']== $status){
            return $result;
        }
    }
    

    public function get_test_loopback_requests($status) {
		$result = array(
			'label'       => __( 'Your site can perform loopback requests', 'site-status' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Performance', 'site-status' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Loopback requests are used to run scheduled events, and are also used by the built-in editors for themes and plugins to verify code stability.', 'site-status' )
			),
			'actions'     => '',
			'test'        => 'loopback_requests',
		);

		

		$check_loopback = $this->can_perform_loopback();

		$result['status'] = $check_loopback->status;

		if ( 'good' !== $check_loopback->status ) {
			$result['label'] = __( 'Your site could not complete a loopback request', 'site-status' );

			$result['description'] .= sprintf(
				'<p>%s</p>',
				$check_loopback->message
			);
		}

        if($result['status']== $status){
            return $result;
        }
    }
    
    public function can_perform_loopback( $disable_plugin_hash = null, $allowed_plugins = null ) {
		$cookie_array =  filter_input_array(INPUT_COOKIE, FILTER_SANITIZE_STRING);
		$cookies = wp_unslash( $cookie_array );
		$timeout = 10;
		$headers = array(
			'Cache-Control' => 'no-cache',
		);
		$server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
		// Include Basic auth in loopback requests.
		if ( isset( $server_array['PHP_AUTH_USER'] ) && isset( $server_array['PHP_AUTH_PW'] ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $server_array['PHP_AUTH_USER'] ) . ':' . wp_unslash( $server_array['PHP_AUTH_PW'] ) );
		}

		$url = admin_url();

		if ( ! empty( $disable_plugin_hash ) ) {
			$url = add_query_arg(
				array(
					'site-status-disable-plugin-hash' => $disable_plugin_hash,
				),
				$url
			);
		}
		if ( ! empty( $allowed_plugins ) ) {
			if ( ! is_array( $allowed_plugins ) ) {
				$allowed_plugins = (array) $allowed_plugins;
			}

			$url = add_query_arg(
				array(
					'site-status-allowed-plugins' => implode( ',', $allowed_plugins ),
				),
				$url
			);
		}

		$r = wp_remote_get( $url, compact( 'cookies', 'headers', 'timeout' ) );

		if ( is_wp_error( $r ) ) {
            
			return (object) array(
				'status'  => 'critical',
				'message' => sprintf(
					'%s<br>%s',
					esc_html__( 'The loopback request to your site failed, this means features relying on them are not currently working as expected.', 'site-status' ),
					sprintf(
						/* translators: %1$d: The HTTP response code. %2$s: The error message returned. */
						esc_html__( 'Error encountered: (%1$d) %2$s', 'site-status' ),
						wp_remote_retrieve_response_code( $r ),
						$r->get_error_message()
					)
				),
			);
		}

		if ( 200 !== wp_remote_retrieve_response_code( $r ) ) {
			return (object) array(
				'status'  => 'recommended',
				'message' => sprintf(
					/* translators: %d: The HTTP response code returned. */
					esc_html__( 'The loopback request returned an unexpected http status code, %d, it was not possible to determine if this will prevent features from working as expected.', 'site-status' ),
					wp_remote_retrieve_response_code( $r )
				),
			);
		}

		return (object) array(
			'status'  => 'good',
			'message' => __( 'The loopback request to your site completed successfully.', 'site-status' ),
		);
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
    
}