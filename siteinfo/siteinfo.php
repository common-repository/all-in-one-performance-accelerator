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

class SiteInfo
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
        add_action('wp_ajax_get_site_info_details', array($this,'get_site_info'));
        add_action('wp_ajax_get_siteinfo_selected_tab', array($this,'get_siteinfo_selected_tab'));

	}

	public function get_siteinfo_selected_tab(){
		// require_once('../Query-moniter/queryInfo.php');
		require_once(__DIR__ . '/../Query-moniter/queryInfo.php');
		$classA_instance = new SmackQuery1();
		$tab = sanitize_text_field($_POST['tab']);
		if($tab === 'undefined'){
			$tab_value = get_option('smack_siteinfo_tab');
			if(empty($tab_value)){
				$tab_name = 'server';
				update_option('smack_siteinfo_tab',$tab_name);
			}else{
				update_option('smack_siteinfo_tab',$tab_value);
			}
		}else{
			update_option('smack_siteinfo_tab',$tab);
		}
		$tab_address = get_option('smack_siteinfo_tab');
		$result['tab'] = $tab_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

    public function get_site_info(){
		$wordpress_info=$this->get_wordpress_info();
		$server_info=$this->get_server_info();
		$database_info=$this->get_database_info();
		$wordpress_constants_info=$this->get_constants_info();
		$directories_info=$this->get_directories_info();
		$file_permission_info=$this->get_permission_info();
		$active_theme_info=$this->get_active_theme_info();
		$inactive_theme_info=$this->get_inactive_theme_info();
		$active_plugins_info=$this->get_active_plugins_info();
		$inactive_plugins_info=$this->get_inactive_plugins_info();
		$result['wordpress']=$wordpress_info;
		$result['server']=$server_info;
		$result['database']=$database_info;
		$result['constants']=$wordpress_constants_info;
		$result['directories']=$directories_info;
		$result['file_permission']=$file_permission_info;
		$result['active_theme']=$active_theme_info;
		$result['inactive_theme']=$inactive_theme_info;
		$result['active_plugins']=$active_plugins_info;
		$result['inactive_plugins']=$inactive_plugins_info;
		$result['success'] = true;
        echo wp_json_encode($result);
        wp_die();

    }
	
    public function get_wordpress_info(){
        $core_version           = get_bloginfo( 'version' );
		$core_updates           = get_core_updates();
		
		$core_update_needed     = '';
		$permalink_structure    = get_option( 'permalink_structure' );
		$is_ssl                 = is_ssl();
		$users_can_register     = get_option( 'users_can_register' );
		$default_comment_status = get_option( 'default_comment_status' );
		$is_multisite           = is_multisite();
		$total_count = count_users();
		foreach ( $core_updates as $core => $update ) {
			if ( 'upgrade' === $update->response ) {
				// translators: %s: Latest WordPress version number.
				$core_update_needed = ' ' . sprintf( __( '(Latest version: %s)', 'site-info' ), $update->version );
			} else {
				$core_update_needed = '';
			}
		}
		$wp_dotorg = wp_remote_get( 'https://api.wordpress.org', array( 'timeout' => 10 ) );
		if ( ! is_wp_error( $wp_dotorg ) ) {
			$result['wordpress_org']='WordPress.org is reachable';
		}else{
			$result['wordpress_org']='Unable to reach WordPress.org';
		}
		$result['version']=$core_version.$core_update_needed;
		$result['site_lang']=get_locale();
		$result['user_lang']=get_user_locale();
		$result['home_url']=get_bloginfo( 'url' );
		$result['site_url']=get_bloginfo( 'wpurl' );
		$result['ssl']=$is_ssl ? __( 'Yes', 'site-info' ) : __( 'No', 'site-info' );
		$result['multisite']=$is_multisite ? __( 'Yes', 'site-info' ) : __( 'No', 'site-info' );
		$result['user_register']=$users_can_register ? __( 'Yes', 'site-info' ) : __( 'No', 'site-info' );
		$result['comment_status']='open' === $default_comment_status ? _x( 'Open', 'comment status', 'site_info' ) : _x( 'Closed', 'comment status', 'site_info' );
		$result['permalink']= $permalink_structure ? $permalink_structure : __( 'No permalink structure set', 'site_info' );
		$result['user_count']=$total_count['total_users'];
		return $result;
       
	}

	public function get_server_info(){
		global $wpdb, $is_apache;
		if ( function_exists( 'php_uname' ) ) {
			$server_architecture = sprintf( '%s %s %s', php_uname( 's' ), php_uname( 'r' ), php_uname( 'm' ) );
		} else {
			$server_architecture = 'unknown';
		}
		if ( function_exists( 'get_current_user' ) && function_exists( 'getmyuid' ) ) {
			$php_getuid = sprintf(
				'%s (%s)',
				get_current_user(),
				getmyuid()
			);
		} else {
			$php_getuid = 'unknown';
		}
		if ( function_exists( 'phpversion' ) ) {
			$php_version_debug = phpversion();
			// Whether PHP supports 64bit
			$php64bit = ( PHP_INT_SIZE * 8 === 64 );

			$php_version = sprintf(
				'%s %s',
				$php_version_debug,
				( $php64bit ? __( '(Supports 64bit values)', 'site_info' ) : __( '(Does not support 64bit values)', 'site_info' ) )
			);

			if ( $php64bit ) {
				$php_version_debug .= ' 64bit';
			}
		} else {
			$php_version       = __( 'Unable to determine PHP version', 'site_info' );
			$php_version_debug = 'unknown';
		}
		if ( function_exists( 'php_sapi_name' ) ) {
			$php_sapi = php_sapi_name();
		} else {
			$php_sapi = 'unknown';
		}
		if ( function_exists( 'curl_version' ) ) {
			$curl = curl_version();
			$result['curl_version']=sprintf( '%s %s', $curl['version'], $curl['ssl_version'] );
			
		} else {
			$result['curl_version']='Not Avialable';
		}
		if ( ! function_exists( 'ini_get' ) ) {
			$result['max_input_vars']='Unable to determine some settings, as the ini_get() function has been disabled';
			$result['max_execution_time']='Unable to determine some settings, as the ini_get() function has been disabled';
			$result['memory_limit']='Unable to determine some settings, as the ini_get() function has been disabled';
			$result['max_input_time']='Unable to determine some settings, as the ini_get() function has been disabled';
			$result['upload_max_filesize']='Unable to determine some settings, as the ini_get() function has been disabled';
			$result['post_max_size']='Unable to determine some settings, as the ini_get() function has been disabled';
		}else{
			$result['max_input_vars']=ini_get( 'max_input_vars' );
			$result['max_execution_time']=ini_get( 'max_execution_time' );
			$result['memory_limit']= ini_get( 'memory_limit' );
			$result['max_input_time']=ini_get( 'max_input_time' );
			$result['upload_max_filesize']=ini_get( 'upload_max_filesize' );
			$result['post_max_size']=ini_get( 'post_max_size' );
		}
			// Check if a .htaccess file exists.
			if ( $is_apache && is_file( ABSPATH . '.htaccess' ) ) {
				// If the file exists, grab the content of it.
				$htaccess_content = file_get_contents( ABSPATH . '.htaccess' );
	
				// Filter away the core WordPress rules.
				$filtered_htaccess_content = trim( preg_replace( '/\# BEGIN WordPress[\s\S]+?# END WordPress/si', '', $htaccess_content ) );
				$filtered_htaccess_content = ! empty( $filtered_htaccess_content );
	
				$result['htaccess_file_access']=( $filtered_htaccess_content ? __( 'Custom rules have been added to your .htaccess file.', 'site_info' ) : __( 'Your .htaccess file contains only core WordPress features.', 'site_info' ) );
				
			}
			$server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
		$result['server_architechture']=( 'unknown' !== $server_architecture ? $server_architecture : __( 'Unable to determine server architecture', 'site_info' ) );
		$result['server_user']=( 'unknown' !== $php_getuid ? $php_getuid : __( 'Unable to determine the websites server user', 'site_user' ) );
		$result['web_server']=( isset( $server_array['SERVER_SOFTWARE'] ) ? $server_array['SERVER_SOFTWARE'] : __( 'Unable to determine what web server software is used', 'site_info' ) );
		$result['php_version']=$php_version;
		$result['php_sapi']=( 'unknown' !== $php_sapi ? $php_sapi : __( 'Unable to determine PHP SAPI', 'site_info' ) );
		return $result;
				
	}

	public function get_database_info(){
		global $wpdb, $is_apache;
			// Populate the database debug fields.
		if ( is_resource( $wpdb->dbh ) ) {
			// Old mysql extension.
			$extension = 'mysql';
		} elseif ( is_object( $wpdb->dbh ) ) {
			// mysqli or PDO.
			$extension = get_class( $wpdb->dbh );
		} else {
			// Unknown sql extension.
			$extension = null;
		}

		$server = $wpdb->get_var( 'SELECT VERSION()' );

		if ( isset( $wpdb->use_mysqli ) && $wpdb->use_mysqli ) {
			$client_version = $wpdb->dbh->client_info;
		} else {
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysql_get_client_info
			if ( preg_match( '|[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2}|', mysqli_get_client_info(), $matches ) ) {
				$client_version = $matches[0];
			} else {
				$client_version = null;
			}
		}
		

		$result['extension']=$extension;
		$result['Server_version']=$server;
		$result['Client_version']=$client_version;
		$result['Database_user']=$wpdb->dbuser;
		$result['Database_host']=$wpdb->dbhost;
		$result['Database_name']=$wpdb->dbname;
		$result['Database_prefix']=$wpdb->prefix;
		return $result;

	}

	public function get_constants_info(){
		$wp_debug_log_value = __( 'Disabled', 'site-info' );

		if ( is_string( WP_DEBUG_LOG ) ) {
			$wp_debug_log_value = WP_DEBUG_LOG;
		} elseif ( WP_DEBUG_LOG ) {
			$wp_debug_log_value = __( 'Enabled', 'site-info' );
		}
		$result['abspath']=ABSPATH;
		$result['wp_home']=( defined( 'WP_HOME' ) ? WP_HOME : __( 'Undefined', 'site_info' ) );
		$result['wp_site']=( defined( 'WP_SITEURL' ) ? WP_SITEURL : __( 'Undefined', 'site-info' ) );
		$result['wp_content_dir']=WP_CONTENT_DIR;
		$result['wp_plugin_dir']=WP_PLUGIN_DIR;
		$result['max_memory_limit']=WP_MAX_MEMORY_LIMIT;
		$result['wp_debug']=WP_DEBUG ? __( 'Enabled', 'site-info' ) : __( 'Disabled', 'site-info' );
		$result['debug_display']=WP_DEBUG_DISPLAY ? __( 'Enabled', 'site-info' ) : __( 'Disabled', 'site-info' );
		$result['script_debug']=SCRIPT_DEBUG ? __( 'Enabled', 'site-info' ) : __( 'Disabled', 'site-info' );
		$result['wp_debug_log_value']=$wp_debug_log_value;
		
		return $result;
	}

	public function get_directories_info(){
		$size_db=$this->get_database_size();
		$database_size = size_format( $size_db, 2 );
		$upload_dir             = wp_get_upload_dir();
		
		$result['wordpress_dir_loc']=untrailingslashit( ABSPATH );
		$result['uploads_dir_loc']=$upload_dir['basedir'];
		 $result['themes_dir_loc']=get_theme_root();
		 $result['plugins_dir_loc']=WP_PLUGIN_DIR;
		 $result['database_size']=$database_size;
			 $plugin_size=$this->get_dir_size($result['plugins_dir_loc']);
			 $result['plugin_dir_size'] = $this->getFormattedSize($plugin_size);
			  $uploads_size=$this->get_dir_size($upload_dir['basedir']);
			  $result['uploads_dir_size'] = $this->getFormattedSize($uploads_size);
			  $themes_size=$this->get_dir_size(get_theme_root());
			  $result['themes_dir_size'] = $this->getFormattedSize($themes_size);
			  $wordpress_dir_size=$this->get_dir_size(untrailingslashit( ABSPATH ));
			  $result['wordpress_dir_size'] = $this->getFormattedSize($wordpress_dir_size);
			  $total_size=(int)$plugin_size+(int)$uploads_size+(int)$themes_size;
			  $result['total_dir_size'] = $this->getFormattedSize($total_size);
			
			  return $result;
			  
			 
		
	}

	public function get_permission_info(){
		$upload_dir             = wp_get_upload_dir();
		$is_writable_abspath            = wp_is_writable( ABSPATH );
		$is_writable_wp_content_dir     = wp_is_writable( WP_CONTENT_DIR );
		$is_writable_upload_dir         = wp_is_writable( $upload_dir['basedir'] );
		$is_writable_wp_plugin_dir      = wp_is_writable( WP_PLUGIN_DIR );
		$is_writable_template_directory = wp_is_writable( get_template_directory() . '/..' );
		$result['wordpress_directory']=( $is_writable_abspath ? __( 'Writable', 'site_info' ) : __( 'Not writable', 'site_info' ) );
		$result['wp_content_directory']=( $is_writable_wp_content_dir ? __( 'Writable', 'site_info' ) : __( 'Not writable', 'site_info' ) );
		$result['plugins_directory']=( $is_writable_wp_plugin_dir ? __( 'Writable', 'site_info' ) : __( 'Not writable', 'site_info' ) );
		$result['themes_directory']=( $is_writable_template_directory ? __( 'Writable', 'site_info' ) : __( 'Not writable', 'site_info' ) );
		$result['uploads_directory']=( $is_writable_upload_dir ? __( 'Writable', 'site_info' ) : __( 'Not writable', 'site_info' ) );
        return $result;

	}

	public function get_active_theme_info(){
		$active_theme  = wp_get_theme();
		$theme_updates = get_theme_updates();
		$active_theme_version       = $active_theme->Version;
		$active_theme_author_uri = $active_theme->offsetGet( 'Author URI' );
		if ( array_key_exists( $active_theme->stylesheet, $theme_updates ) ) {
			$theme_update_new_version = $theme_updates[ $active_theme->stylesheet ]->update['new_version'];

			// translators: %s: Latest theme version number.
			$active_theme_version       .= ' ' . sprintf( __( '(Latest version: %s)', 'site_info' ), $theme_update_new_version );
		}
		$result['theme_name']= sprintf(__( '%1$s (%2$s)', 'site_info' ),$active_theme->Name,$active_theme->stylesheet);
		$result['theme_version']=$active_theme_version;
		$result['theme_author']=wp_kses( $active_theme->Author, array());
		$result['theme_author_website']=( $active_theme_author_uri ? $active_theme_author_uri : __( 'Undefined', 'site_info' ) );
		$result['parent_theme']=( $active_theme->parent_theme ? $active_theme->parent_theme . ' (' . $active_theme->template . ')' : __( 'None', 'site_info' ) );
		$result['theme_path']=get_stylesheet_directory();
		return $result;
		
	}

	public function get_inactive_theme_info(){
		$active_theme  = wp_get_theme();
		$all_themes = wp_get_themes();
		$theme_updates = get_theme_updates();
		$parent_theme = $active_theme->parent();
		$all_theme_array = [];
		
		foreach ( $all_themes as $theme_slug => $theme ) {
			
			// Ignore the currently active theme from the list of all themes.
			if ( $active_theme->stylesheet === $theme_slug ) {
				continue;
			}

			// Ignore the currently active parent theme from the list of all themes.
			if ( ! empty( $parent_theme ) && $parent_theme->stylesheet === $theme_slug ) {
				continue;
			}

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$theme_version = $theme->Version;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$theme_author = $theme->Author;
		
			// Sanitize
			$theme_author = wp_kses( $theme_author, array() );
			if ( ! empty( $theme_version ) && ! empty( $theme_author ) ) {
				// translators: 1: Theme version number. 2: Theme author name.
				$result['theme_version_and_author'] = sprintf( __( 'Version %1$s by %2$s', 'site_info' ), $theme_version, $theme_author );
			} else {
				if ( ! empty( $theme_author ) ) {
					// translators: %s: Theme author name.
					$result['theme_author'] = sprintf( __( 'By %s', 'site_info' ), $theme_author );
					
				}

				if ( ! empty( $theme_version ) ) {
					// translators: %s: Theme version number.
					$result['theme_version']   = sprintf( __( 'Version %s', 'site_info' ), $theme_version );
					
				}
			}
			
			if ( array_key_exists( $theme_slug, $theme_updates ) ) {
				// translators: %s: Latest theme version number.
				$result['theme_version_and_author'].= ' ' . sprintf( __( '(Latest version: %s)', 'site_info' ), $theme_updates[ $theme_slug ]->update['new_version'] );
			}

			
		
			$result['theme_name_and_slug']=sprintf(
					// translators: 1: Theme name. 2: Theme slug.
					__( '%1$s (%2$s)', 'site_info' ),
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$theme->Name,
					$theme_slug
			);
			// 
			array_push($all_theme_array, $result);
		}
		
		return $all_theme_array;
	
		
	}

	public function get_active_plugins_info(){
		$plugins        = get_plugins();
		$plugin_updates = get_plugin_updates();
		$active_plugin_array = [];
		foreach ( $plugins as $plugin_path => $plugin ) {
			$plugin_part = ( is_plugin_active( $plugin_path ) ) ? 'wp-plugins-active' : 'wp-plugins-inactive';
			$plugin_version = $plugin['Version'];
			$plugin_author  = $plugin['Author'];
			if ( ! empty( $plugin_version ) && ! empty( $plugin_author ) ) {
				// translators: 1: Plugin version number. 2: Plugin author name.
				$result['plugin_version_and_author'] = sprintf( __( 'Version %1$s by %2$s', 'site_info' ), $plugin_version, $plugin_author );
				
			} else {
				if ( ! empty( $plugin_author ) ) {
					// translators: %s: Plugin author name.
					$result['plugin_author']= sprintf( __( 'By %s', 'site_info' ), $plugin_author );
				}

				if ( ! empty( $plugin_version ) ) {
					// translators: %s: Plugin version number.
					$result['plugin_version'] = sprintf( __( 'Version %s', 'site_info' ), $plugin_version );
					
				}
			}

			if ( array_key_exists( $plugin_path, $plugin_updates ) ) {
				// translators: %s: Latest plugin version number.
				$result['plugin_version_and_author'].= ' ' . sprintf( __( '(Latest version: %s)', 'site_info' ), $plugin_updates[ $plugin_path ]->update->new_version );
			}  
			if($plugin_part == 'wp-plugins-active'){

				$result['plugin_name'] = $plugin['Name'];
				$result['version'] = isset($plugin_updates[ $plugin_path ]->update->new_version) ?  $plugin_updates[ $plugin_path ]->update->new_version : null;
				array_push($active_plugin_array, $result);
				
			}
			}		
		return $active_plugin_array;
	}
	public function get_inactive_plugins_info(){
		$plugins        = get_plugins();
		$plugin_updates = get_plugin_updates();
		$inactive_plugin_array = [];
		foreach ( $plugins as $plugin_path => $plugin ) {
			$plugin_part = ( is_plugin_active( $plugin_path ) ) ? 'wp-plugins-active' : 'wp-plugins-inactive';
		
			$plugin_version = $plugin['Version'];
			$plugin_author  = $plugin['Author'];
			
			if ( ! empty( $plugin_version ) && ! empty( $plugin_author ) ) {
				// translators: 1: Plugin version number. 2: Plugin author name.
				$result['plugin_version_and_author'] = sprintf( __( 'Version %1$s by %2$s', 'site_info' ), $plugin_version, $plugin_author );
				
			} else {
				if ( ! empty( $plugin_author ) ) {
					// translators: %s: Plugin author name.
					$result['plugin_author']= sprintf( __( 'By %s', 'site_info' ), $plugin_author );
				}

				if ( ! empty( $plugin_version ) ) {
					// translators: %s: Plugin version number.
					$result['plugin_version'] = sprintf( __( 'Version %s', 'site_info' ), $plugin_version );
					
				}
			}

			if ( array_key_exists( $plugin_path, $plugin_updates ) ) {
				// translators: %s: Latest plugin version number.
				$result['plugin_version_and_author'].= ' ' . sprintf( __( '(Latest version: %s)', 'site_info' ), $plugin_updates[ $plugin_path ]->update->new_version );
			}

			if($plugin_part == 'wp-plugins-inactive'){

				$result['plugin_name'] = $plugin['Name'];
				$result['version'] = isset($plugin_updates[ $plugin_path ]->update->new_version) ? $plugin_updates[ $plugin_path ]->update->new_version : null;
				array_push($inactive_plugin_array, $result);
			}
			
			
		}		
		
		
		return $inactive_plugin_array;
	}

	public static function get_database_size() {
		global $wpdb;
		$size = 0;
		$rows = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );

		if ( $wpdb->num_rows > 0 ) {
			foreach ( $rows as $row ) {
				$size += $row['Data_length'] + $row['Index_length'];
			}
		}

		return (int) $size;
	}

	function get_dir_size($directory){
		$io = popen ( '/usr/bin/du -sk ' . $directory, 'r' );
		$size = fgets ( $io, 4096);
		$size = substr ( $size, 0, strpos ( $size, "\t" ) );
		pclose ( $io );
		return $size;
		//echo 'Directory: ' . $directory . ' => Size: ' . $size;
	} 
	
	function getFormattedSize($sizeInBytes) {

        if($sizeInBytes < 1024) {
			return $sizeInBytes . " bytes";
        } else if($sizeInBytes < 1024*1024) {
            return $fileSize = round($sizeInBytes / 1024,4) . 'KB';
        } else if($sizeInBytes < 1024*1024*1024) {
			return $fileSize = round($sizeInBytes / 1024 / 1024,4) . 'MB';	
        } else if($sizeInBytes < 1024*1024*1024*1024) {
            return $fileSize = round($sizeInBytes / 1024 / 1024 / 1024,4) . 'GB';
        }  else {
            return "Greater than 1024 GB";
        }

    }
			
}
$new_obj = new SiteInfo();
