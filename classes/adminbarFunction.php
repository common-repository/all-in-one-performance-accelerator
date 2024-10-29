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

	
class adminbarFunction{
    protected static $instance = null, $plugin;
    protected $start_time = 0;
   
    public function __construct() {
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
        add_action('wp_ajax_smack_clear_cache_dashboard', array($this,'smack_clear_cache_dashboard'));
        add_action('wp_ajax_smack_preload_dashboard', array($this,'smack_preload_dashboard'));
        add_action('wp_ajax_smack_purge_opcache_dashboard', array($this,'smack_purge_opcache_dashboard'));
    }

    public function smack_clear_cache_dashboard(){
       $this->smack_clean_domain('');
       $result['success'] = true;
       echo wp_json_encode($result);
       wp_die();
    }

    public function smack_preload_dashboard(){
        $this->run_smack_bot( 'cache-preload', '' );
        update_option('smack_preload_status','success');
        $result['success'] = true;
        echo wp_json_encode($result);
        wp_die();
    }

    public function smack_purge_opcache_dashboard(){
        $reset_opcache = $this->smack_reset_opcache();
        if ( ! $reset_opcache ) {
            $op_purge_result = [
                'result'  => 'error',
                'message' => __( 'OPcache purge failed.', 'smack-enhancer' ),
            ];
        } else {
            $op_purge_result = [
                'result'  => 'success',
                'message' => __( 'OPcache successfully purged', 'smack-enhancer' ),
            ];
            update_option('smack_purge_status',$op_purge_result['result']);
        }
        
         
          set_transient( get_current_user_id() . 'smack_opcache_purge_result', $op_purge_result );
         
        $result['success'] = true;
        echo wp_json_encode($result);
        wp_die();
    }

    public function smack_purge_opcache_function(){
        $gets_array   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
        if ( ! isset( $gets_array['_wpnonce'] ) || ! wp_verify_nonce( $gets_array['_wpnonce'], 'smack_purge_opcache' ) ) {
            wp_nonce_ays( '' );
        }
    
        $reset_opcache = $this->smack_reset_opcache();

        if ( ! $reset_opcache ) {
            $op_purge_result = [
                'result'  => 'error',
                'message' => __( 'OPcache purge failed.', 'smack-enhancer' ),
            ];
        } else {
            $op_purge_result = [
                'result'  => 'success',
                'message' => __( 'OPcache successfully purged', 'smack-enhancer' ),
            ];
        }
    
        set_transient( get_current_user_id() . 'smack_opcache_purge_result', $op_purge_result );
    
        wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
        die();
    }

    public function smack_reset_opcache() {
        static $can_reset;
    
        if ( ! isset( $can_reset ) ) {
            if ( ! function_exists( 'opcache_reset' ) ) {
                $can_reset = false;
    
                return false;
            }
    
            $restrict_api = ini_get( 'opcache.restrict_api' );
    
            if ( $restrict_api && strpos( __FILE__, $restrict_api ) !== 0 ) {
                $can_reset = false;
    
                return false;
            }
    
            $can_reset = true;
        }
    
        if ( ! $can_reset ) {
            return false;
        }
    
        $opcache_reset = opcache_reset();
        return $opcache_reset;
    }

    public function clear_cache_all_function(){
        $this->smack_clean_domain( $lang );
        wp_safe_redirect( wp_get_referer() );
        die();
    }

    public function smack_preload_function(){
        $this->run_smack_bot( 'cache-preload', $lang );
        

        delete_transient( 'smack_preload_errors' );
    
        wp_safe_redirect( wp_get_referer() );
        die();
    }

    private function get_urls( $url ) {
		
        $arguments = array(
            'timeout'    => 10,
			'user-agent' => 'Smack Enhancer/Homepage_Preload',
			'sslverify'  => apply_filters( 'https_local_ssl_verify', false ), // WPCS: prefix ok.
        );
        $args = $this->add_accept_header($arguments);

		$response         = wp_remote_get( $url, $args );
		$errors           = get_transient( 'smack_preload_errors' );
		$errors           = is_array( $errors ) ? $errors : [];
		$errors['errors'] = isset( $errors['errors'] ) && is_array( $errors['errors'] ) ? $errors['errors'] : [];

		if ( is_wp_error( $response ) ) {
			// Translators: %1$s is an URL, %2$s is the error message, %3$s = opening link tag, %4$s = closing link tag.
			$errors['errors'][] = sprintf( __( 'Preload encountered an error. Could not gather links on %1$s because of the following error: %2$s', 'smack' ), $url, $response->get_error_message(),  '</a>' );

			set_transient( 'smack_preload_errors', $errors );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {
			switch ( $response_code ) {
				case 401:
				case 403:
					// Translators: %1$s is an URL, %2$s is the HTTP response code, %3$s = opening link tag, %4$s = closing link tag.
					$errors['errors'][] = sprintf( __( 'Preload encountered an error. %1$s is not accessible to due to the following response code: %2$s. Security measures could be preventing access', 'smack' ), $url, $response_code, '</a>' );

					set_transient( 'smack_preload_errors', $errors );
					break;
				case 404:
					// Translators: %1$s is an URL, %2$s = opening link tag, %3$s = closing link tag.
					$errors['errors'][] = sprintf( __( 'Preload encountered an error. %1$s is not accessible to due to the following response code: 404. Please make sure your homepage is accessible in your browser', 'smack' ), $url, '</a>' );

					set_transient( 'smack_preload_errors', $errors );
					break;
				case 500:
					// Translators: %1$s is an URL, %2$s = opening link tag, %3$s = closing link tag.
					$errors['errors'][] = sprintf( __( 'Preload encountered an error. %1$s is not accessible to due to the following response code: 500. Please check with your web host about server access', 'smack' ), $url,  '</a>' );

					set_transient( 'smack_preload_errors', $errors );
					break;
				default:
					// Translators: %1$s is an URL, %2$s is the HTTP response code, %3$s = opening link tag, %4$s = closing link tag.
					$errors['errors'][] = sprintf( __( 'Preload encountered an error. Could not gather links on %1$s because it returned the following response code: %2$s', 'smack' ), $url, $response_code, '</a>' );

					set_transient( 'smack_preload_errors', $errors );
					break;
			}

			return false;
		}

		$content = wp_remote_retrieve_body( $response );
		preg_match_all( '/<a\s+(?:[^>]+?[\s"\']|)href\s*=\s*(["\'])(?<href>[^"\']+)\1/imU', $content, $urls );
		
		return array_unique( $urls['href'] );
	}

    public function run_smack_bot( $spider = 'cache-preload', $lang = '' ) {
        $urls = [];
      
        if ( ! $lang ) {
            $urls = $this->get_smack_i18n_uri();
        } else {
            $urls[] = $this->get_smack_i18n_home_url( $lang );
        }
        //$this->preload($urls);

        update_option('smack_enhancer_preload_home_urls', $urls);
        wp_safe_redirect( wp_get_referer() );
        
        if ( ! wp_next_scheduled( 'smack_preload_schedule_event' ) ) {
            wp_schedule_event( time(), 'preload_timing', 'smack_preload_schedule_event');
        }
    }

    public function preload() {
        $home_urls = get_option('smack_enhancer_preload_home_urls');
		$preload = 0;
		foreach ( $home_urls as $home_url ) {
			$urls = $this->get_urls( $home_url );
	
			if ( ! $urls ) {
				continue;
			}
			$home_host = wp_parse_url( $home_url, PHP_URL_HOST );

            $urls_tobe_preloaded = [];
			foreach ( $urls as $url ) {
				if ( ! $this->should_preload( $url, $home_url, $home_host ) ) {
					continue;
                } 
              
                //$this->preload_process->push_to_queue( $url );
                $this->preload_task($url);
				array_push($urls_tobe_preloaded, $url);
				
				//if ( $this->time_exceeded() || $this->memory_exceeded() || $this->is_process_cancelled() ) {
				    //break;
				//}
				$preload++;
            }
            
            foreach($urls_tobe_preloaded as $url){
                $preload_instance = new cachePreloading();
                $preload_instance->maybe_init_process();
            }
            wp_clear_scheduled_hook( 'smack_preload_schedule_event' );
		}
      
		if ( 0 === $preload ) {
			return;
        }

		set_transient( 'smack_preload_running', 0 );
		die();
    }

    protected function time_exceeded() {
		$finish = $this->start_time + apply_filters( 'smack_preload_default_time_limit', 20 ); // 20 seconds
		$return = false;

		if ( time() >= $finish ) {
			$return = true;
		}

		return apply_filters( 'smack_preload_time_exceeded', $return );
    }
    
    protected function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;

		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}

		return apply_filters( 'smack_preload_memory_exceeded', $return );
    }
    
    protected function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}

		return intval( $memory_limit ) * 1024 * 1024;
    }
    
    protected function is_process_cancelled() {
		if ( ! $this->smack_direct_filesystem()->exists( WP_CONTENT_DIR . '/cache/' . '.' . 'smack_preload_process_cancelled' ) ) {
			return false;
		}

		return true;
	}

    protected function preload_task( $item ) {
		$count = get_transient( 'smack_preload_running' );
		set_transient( 'smack_preload_running', $count + 1 );

		if ( $this->is_already_cached( $item ) ) {
			return false;
		}

		
        $smack_request_args = array(
            'timeout'    => 0.01,
			'blocking'   => false,
			'user-agent' => 'Smack Enhancer/Preload',
			'sslverify'  => apply_filters( 'https_local_ssl_verify', false ),
        );
        $this->add_accept_header($smack_request_args);

		wp_remote_get( esc_url_raw( $item ), $smack_request_args );

		
		return false;
    }

    protected function is_already_cached( $item ) {
		static $https;

	

		$url = $this->get_smack_parse_url( $item );

		/** This filter is documented in inc/functions/htaccess.php */
		if ( apply_filters( 'smack_url_no_dots', false ) ) {
			$url['host'] = str_replace( '.', '_', $url['host'] );
		}

		$url['path'] = trailingslashit( $url['path'] );

		if ( '' !== $url['query'] ) {
			$url['query'] = '#' . $url['query'] . '/';
		}

		$file_cache_path =  WP_CONTENT_DIR. '/cache/smack-preload/' . $url['host'] . strtolower( $url['path'] . $url['query'] ) . 'index' . $https . '.html';

		return $this->smack_direct_filesystem()->exists( $file_cache_path );
	}
    
    public function add_accept_header( $args ) {
		
		$args['headers']['Accept']      = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8';
		$args['headers']['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8';

		return $args;
	}
    
    private function should_preload( $url, $home_url, $home_host ) {
		$url = html_entity_decode( $url ); // & symbols in URLs are changed to &#038; when using WP Menu editor

		$url_data = $this->get_smack_parse_url( $url );
    
		if ( empty( $url_data ) ) {
			return false;
		}

		if ( ! empty( $url_data['fragment'] ) ) {
			return false;
		}

		if ( empty( $url_data['host'] ) ) {
			$url = home_url( $url );
		}

		$url = $this->smack_add_url_protocol( $url );

		if ( $url === $home_url ) {
			return false;
		}

		if ( $home_host !== $url_data['host'] ) {
			return false;
		}

		if ( $this->is_file_url( $url ) ) {
			return false;
		}

		if ( ! empty( $url_data['path'] ) && preg_match( '#^(' . $this->get_smack_cache_reject_uri() . ')$#', $url_data['path'] ) ) {
            return false;
		}

        // $cache_query_strings = implode( '|', \get_smack_cache_query_string() );
        $cache_query_strings = '';

		if ( ! empty( $url_data['query'] ) && ! preg_match( '/(' . $cache_query_strings . ')/iU', $url_data['query'] ) ) {
            return false;
		}

		return true;
    }

    private function is_file_url( $url ) {
	
		$file_types = apply_filters(
			'smack_preload_file_types',
			[
				'jpg',
				'jpeg',
				'jpe',
				'png',
				'gif',
				'webp',
				'bmp',
				'tiff',
				'mp3',
				'ogg',
				'mp4',
				'm4v',
				'avi',
				'mov',
				'flv',
				'swf',
				'webm',
				'pdf',
				'doc',
				'docx',
				'txt',
				'zip',
				'tar',
				'bz2',
				'tgz',
				'rar',
			]
		);

		$file_types = implode( '|', $file_types );

		if ( preg_match( '#\.(?:' . $file_types . ')$#iU', $url ) ) {
			return true;
		}

		return false;
	}

    public function get_smack_cache_reject_uri() {
        static $uris;
    
        if ( $uris ) {
            return $uris;
        }
    
       
        $uris = [];
     
        $home_root = $this->smack_get_home_dirname();
        if ( '' !== $home_root ) {
            // The site is not at the domain root, it's in a folder.
            $home_root_escaped = preg_quote( $home_root, '/' );
            $home_root_len     = strlen( $home_root );
    
            foreach ( $uris as $i => $uri ) {
                /**
                 * Since these URIs can be regex patterns like `/homeroot(/.+)/`, we can't simply search for the string `/homeroot/` (nor `/homeroot`).
                 * So this pattern searchs for `/homeroot/` and `/homeroot(/`.
                 */
                if ( ! preg_match( '/' . $home_root_escaped . '\(?\//', $uri ) ) {
                    // Reject URIs located outside site's folder.
                    unset( $uris[ $i ] );
                    continue;
                }
    
                // Remove the home directory.
                $uris[ $i ] = substr( $uri, $home_root_len );
            }
        }
    
        // Exclude feeds.
        $uris[] = '/(.+/)?' . $GLOBALS['wp_rewrite']->feed_base . '/?';
    
        // Exlude embedded URLs.
        $uris[] = '/(?:.+/)?embed/';
    
        //$uris = apply_filters( 'smack_cache_reject_uri', $uris );
        $uris = array_filter( $uris );
    
        if ( ! $uris ) {
            return '';
        }
    
        if ( '' !== $home_root ) {
            foreach ( $uris as $i => $uri ) {
                if ( preg_match( '/' . $home_root_escaped . '\(?\//', $uri ) ) {
                    // Remove the home directory from the new URIs.
                    $uris[ $i ] = substr( $uri, $home_root_len );
                }
            }
        }
    
        $uris = implode( '|', $uris );
    
        if ( '' !== $home_root ) {
            // Add the home directory back.
            $uris = $home_root . '(' . $uris . ')';
        }
    
        return $uris;
    }

    public function smack_get_home_dirname() {
        static $home_root;
    
        if ( isset( $home_root ) ) {
            return $home_root;
        }
    
        $home_root = wp_parse_url( $this->smack_get_main_home_url() );
    
        if ( ! empty( $home_root['path'] ) ) {
            $home_root = '/' . trim( $home_root['path'], '/' );
            $home_root = rtrim( $home_root, '/' );
        } else {
            $home_root = '';
        }
    
        return $home_root;
    }

    public function smack_get_main_home_url() {
        static $root_url;
    
        if ( isset( $root_url ) ) {
            return $root_url;
        }
    
        if ( ! is_multisite() || is_main_site() ) {
            $root_url = $this->smack_get_home_url( '/' );
            return $root_url;
        }
    
        $current_network = get_network();
    
        if ( $current_network ) {
            $root_url = set_url_scheme( 'https://' . $current_network->domain . $current_network->path );
            $root_url = trailingslashit( $root_url );
        } else {
            $root_url = $this->smack_get_home_url( '/' );
        }
    
        return $root_url;
    }

    public function smack_get_home_url( $path = '' ) {
        global $wpml_url_filters;
        static $home_url = [];
        static $has_wpml;
    
        if ( isset( $home_url[ $path ] ) ) {
            return $home_url[ $path ];
        }
    
        if ( ! isset( $has_wpml ) ) {
            $has_wpml = $wpml_url_filters && is_object( $wpml_url_filters ) && method_exists( $wpml_url_filters, 'home_url_filter' );
        }
    
        if ( $has_wpml ) {
            remove_filter( 'home_url', [ $wpml_url_filters, 'home_url_filter' ], -10 );
        }
    
        $home_url[ $path ] = home_url( $path );
    
        if ( $has_wpml ) {
            add_filter( 'home_url', [ $wpml_url_filters, 'home_url_filter' ], -10, 4 );
        }
    
        return $home_url[ $path ];
    }
    
    public function smack_add_url_protocol( $url ) {

        if ( strpos( $url, 'http://' ) === false && strpos( $url, 'https://' ) === false ) {
            if ( substr( $url, 0, 2 ) !== '//' ) {
                $url = '//' . $url;
            }
            $url = set_url_scheme( $url );
        }
        return $url;
    }

    public function smack_clean_user( $user_id, $lang = '' ) {
        $urls = ( ! $lang || is_object( $lang ) ) ? $this->get_smack_i18n_uri() : $this->get_smack_i18n_home_url( $lang );
        $urls = (array) $urls;
    
        /** This filter is documented in inc/functions/files.php */
        $urls = apply_filters( 'smack_clean_domain_urls', $urls, $lang );
        $urls = array_filter( $urls );
        $user = get_user_by( 'id', $user_id );
    
        if ( ! $user ) {
            return;
        }
    
        $user_key = $user->user_login . '-' . get__option( 'secret_cache_key' );
    
        foreach ( $urls as $url ) {
            $parse_url = $this->get_smack_parse_url( $url );
    
            /** This filter is documented in inc/front/htaccess.php */
            if ( apply_filters( 'smack_url_no_dots', false ) ) {
                $parse_url['host'] = str_replace( '.', '_', $parse_url['host'] );
            }
    
            $root = WP_CONTENT_DIR . '/cache/smack-preload/' . $parse_url['host'] . '-' . $user_key . '*' . $parse_url['path'];
    
            do_action( 'before_smack_clean_user', $user_id, $lang );
    
            // Delete cache domain files.
            $dirs = glob( $root . '*', GLOB_NOSORT );
            if ( $dirs ) {
                foreach ( $dirs as $dir ) {
                    $this->smack_rrmdir( $dir, $this->get_smack_i18n_to_preserve( $lang ) );
                }
            }
            do_action( 'after_smack_clean_user', $user_id, $lang );
        }
    }

    public function smack_clean_domain( $lang = '' ) {
        $urls = ( ! $lang || is_object( $lang ) || is_array( $lang ) || is_int( $lang ) ) ? $this->get_smack_i18n_uri() : $this->get_smack_i18n_home_url( $lang );
        $urls = (array) $urls;
    
        //$urls = apply_filters( 'smack_clean_domain_urls', $urls, $lang );
        $urls = array_filter( $urls );
     
        foreach ( $urls as $url ) {
            $file = $this->get_smack_parse_url( $url );
    
            if ( apply_filters( 'smack_url_no_dots', false ) ) {
                $file['host'] = str_replace( '.', '_', $file['host'] );
            }
            $root = WP_CONTENT_DIR. '/cache/smack-preload/' . $file['host'] . '*' . $file['path'];
         
            // Delete cache domain files.
            $dirs = glob( $root . '*', GLOB_NOSORT );
         
            if ( $dirs ) {
                foreach ( $dirs as $dir ) {
                    $this->smack_rrmdir( $dir, $this->get_smack_i18n_to_preserve( $lang ) );
                }
            }
        }

        wp_clear_scheduled_hook( 'smack_preload_schedule_event' );
    }

    public function get_smack_parse_url( $url ) {
        if ( ! is_string( $url ) ) {
            return;
        }
    
        $encoded_url = preg_replace_callback(
            '%[^:/@?&=#]+%usD',
            function ( $matches ) {
                return rawurlencode( $matches[0] );
            },
            $url
        );
    
        $url      = wp_parse_url( $encoded_url );
        $host     = isset( $url['host'] ) ? strtolower( urldecode( $url['host'] ) ) : '';
        $path     = isset( $url['path'] ) ? urldecode( $url['path'] ) : '';
        $scheme   = isset( $url['scheme'] ) ? urldecode( $url['scheme'] ) : '';
        $query    = isset( $url['query'] ) ? urldecode( $url['query'] ) : '';
        $fragment = isset( $url['fragment'] ) ? urldecode( $url['fragment'] ) : '';
    
        return apply_filters(
            'smack_parse_url',
            [
                'host'     => $host,
                'path'     => $path,
                'scheme'   => $scheme,
                'query'    => $query,
                'fragment' => $fragment,
            ]
        );
    }
    
    public function smack_rrmdir( $dir, $dirs_to_preserve = array() ) {
        $dir = untrailingslashit( $dir );
    
        do_action( 'before_smack_rrmdir', $dir, $dirs_to_preserve );
    
        // Remove the hidden empty file for mobile detection on NGINX with the  NGINX configuration.
        $nginx_mobile_detect_file = $dir . '/.mobile-active';
    
        if ( $this->smack_direct_filesystem()->is_dir( $dir ) && $this->smack_direct_filesystem()->exists( $nginx_mobile_detect_file ) ) {
            $this->smack_direct_filesystem()->delete( $nginx_mobile_detect_file );
        }
    
        // Remove the hidden empty file for webp.
        $nowebp_detect_file = $dir . '/.no-webp';
    
        if ( $this->smack_direct_filesystem()->is_dir( $dir ) && $this->smack_direct_filesystem()->exists( $nowebp_detect_file ) ) {
            $this->smack_direct_filesystem()->delete( $nowebp_detect_file );
        }
    
        if ( ! $this->smack_direct_filesystem()->is_dir( $dir ) ) {
            $this->smack_direct_filesystem()->delete( $dir );
            return;
        };
    
        $dirs = glob( $dir . '/*', GLOB_NOSORT );
        if ( $dirs ) {
    
            $keys = array();
            foreach ( $dirs_to_preserve as $dir_to_preserve ) {
                $matches = preg_grep( "#^$dir_to_preserve$#", $dirs );
                $keys[]  = reset( $matches );
            }
    
            $dirs = array_diff( $dirs, array_filter( $keys ) );
            foreach ( $dirs as $dir ) {
                if ( $this->smack_direct_filesystem()->is_dir( $dir ) ) {
                    $this->smack_rrmdir( $dir, $dirs_to_preserve );
                } else {
                    $this->smack_direct_filesystem()->delete( $dir );
                }
                $this->smack_direct_filesystem()->delete( $dir );
            }
        }
    
        do_action( 'before_smack_rrmdir', $dir, $dirs_to_preserve );
    }

    public function smack_direct_filesystem() {
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        return new \WP_Filesystem_Direct( new \StdClass() );
    }

    public function smack_clean_cache_busting( $extensions = array( 'js', 'css' ) ) {
        $extensions = is_string( $extensions ) ? (array) $extensions : $extensions;
    
        $cache_busting_path = WP_CONTENT_DIR . '/cache/busting/' . get_current_blog_id();
    
        if ( ! $this->smack_direct_filesystem()->is_dir( $cache_busting_path ) ) {
            $this->smack_mkdir_p( $cache_busting_path );
    
            // Logger::debug( 'No Cache Busting folder found.', [
            //     'mkdir cache busting folder',
            //     'cache_busting_path' => $cache_busting_path,
            // ] );
            return;
        }
    
        try {
            $dir = new RecursiveDirectoryIterator( $cache_busting_path, FilesystemIterator::SKIP_DOTS );
        } catch ( \UnexpectedValueException $e ) {
            // No logging yet.
            return;
        }
    
        try {
            $iterator = new RecursiveIteratorIterator( $dir, RecursiveIteratorIterator::CHILD_FIRST );
        } catch ( \Exception $e ) {
            // No logging yet.
            return;
        }
    
        foreach ( $extensions as $ext ) {
            //do_action( 'before_smack_clean_busting', $ext );
    
            try {
                $files = new RegexIterator( $iterator, '#.*\.' . $ext . '#', RegexIterator::GET_MATCH );
                foreach ( $files as $file ) {
                    $this->smack_direct_filesystem()->delete( $file[0] );
                }
            } catch ( \InvalidArgumentException $e ) {
                // No logging yet.
                return;
            }
    
            //do_action( 'after_smack_clean_cache_busting', $ext );
        }
    
        try {
            foreach ( $iterator as $item ) {
                if ( $this->smack_direct_filesystem()->is_dir( $item ) ) {
                    $this->smack_direct_filesystem()->delete( $item );
                }
            }
        } catch ( \UnexpectedValueException $e ) {
        }
    }
     
    public function smack_mkdir_p( $target ) {
        // from php.net/mkdir user contributed notes.
        $target = str_replace( '//', '/', $target );
    
        // safe mode fails with a trailing slash under certain PHP versions.
        $target = untrailingslashit( $target );
        if ( empty( $target ) ) {
            $target = '/';
        }
    
        if ( $this->smack_direct_filesystem()->exists( $target ) ) {
            return $this->smack_direct_filesystem()->is_dir( $target );
        }
    
        // Attempting to create the directory may clutter up our display.
        if ( $this->smack_mkdir( $target ) ) {
            return true;
        } elseif ( $this->smack_direct_filesystem()->is_dir( dirname( $target ) ) ) {
            return false;
        }
    
        // If the above failed, attempt to create the parent node, then try again.
        if ( ( '/' !== $target ) && ( $this->smack_mkdir_p( dirname( $target ) ) ) ) {
            return $this->smack_mkdir_p( $target );
        }
    
        return false;
    }
    
    public function smack_mkdir( $dir ) {
        $chmod = $this->smack_get_filesystem_perms( 'dir' );
        return $this->smack_direct_filesystem()->mkdir( $dir, $chmod );
    }

    public function smack_get_filesystem_perms( $type ) {
        static $perms = [];
    
        // Allow variants.
        switch ( $type ) {
            case 'dir':
            case 'dirs':
            case 'folder':
            case 'folders':
                $type = 'dir';
                break;
    
            case 'file':
            case 'files':
                $type = 'file';
                break;
    
            default:
                return 0755;
        }
    
        if ( isset( $perms[ $type ] ) ) {
            return $perms[ $type ];
        }
    
        // If the constants are not defined, use fileperms() like WordPress does.
        switch ( $type ) {
            case 'dir':
                if ( defined( 'SMACK_FS_CHMOD_DIR' ) ) {
                    $perms[ $type ] = SMACK_FS_CHMOD_DIR;
                } else {
                    $perms[ $type ] = fileperms( ABSPATH ) & 0777 | 0755;
                }
                break;
    
            case 'file':
                if ( defined( 'SMACK_FS_CHMOD_FILE' ) ) {
                    $perms[ $type ] = SMACK_FS_CHMOD_FILE;
                } else {
                    $perms[ $type ] = fileperms( ABSPATH . 'index.php' ) & 0777 | 0644;
                }
        }
    
        return $perms[ $type ];
    }

    function get_smack_i18n_to_preserve( $current_lang ) {
        // Must not be an empty string.
        if ( empty( $current_lang ) ) {
            return [];
        }
    
        // Must not be anything else but a string.
        if ( ! is_string( $current_lang ) ) {
            return [];
        }
    
        $i18n_plugin = $this->smack_has_i18n();
    
        if ( ! $i18n_plugin ) {
            return [];
        }
    
        $langs = get_smack_i18n_code();
    
        // Remove current lang to the preserve dirs.
        $langs = array_diff( $langs, [ $current_lang ] );
    
        // Stock all URLs of langs to preserve.
        $langs_to_preserve = [];
    
        if ( $langs ) {
            foreach ( $langs as $lang ) {
                $parse_url           = $this->get_smack_parse_url( $this->get_smack_i18n_home_url( $lang ) );
                $langs_to_preserve[] = WP_CONTENT_DIR . '/cache/smack-preload/' . $parse_url['host'] . '(.*)/' . trim( $parse_url['path'], '/' );
            }
        }
        return apply_filters( 'smack_langs_to_preserve', $langs_to_preserve );
    }
    
    public function smack_has_i18n() {
        global $sitepress, $q_config, $polylang;
    
        if ( ! empty( $sitepress ) && is_object( $sitepress ) && method_exists( $sitepress, 'get_active_languages' ) ) {
            // WPML.
            return 'wpml';
        }
    
        if ( ! empty( $polylang ) && function_exists( 'pll_languages_list' ) ) {
            $languages = pll_languages_list();
    
            if ( empty( $languages ) ) {
                return false;
            }
    
            // Polylang, Polylang Pro.
            return 'polylang';
        }
    
        if ( ! empty( $q_config ) && is_array( $q_config ) ) {
            if ( function_exists( 'qtranxf_convertURL' ) ) {
                // qTranslate-x.
                return 'qtranslate-x';
            }
    
            if ( function_exists( 'qtrans_convertURL' ) ) {
                // qTranslate.
                return 'qtranslate';
            }
        }
    
        return false;
    }

    public function get_smack_i18n_code() {
        $i18n_plugin = $this->smack_has_i18n();
    
        if ( ! $i18n_plugin ) {
            return false;
        }
    
        if ( 'wpml' === $i18n_plugin ) {
            // WPML.
            return array_keys( $GLOBALS['sitepress']->get_active_languages() );
        }
    
        if ( 'qtranslate' === $i18n_plugin || 'qtranslate-x' === $i18n_plugin ) {
            // qTranslate, qTranslate-x.
            return ! empty( $GLOBALS['q_config']['enabled_languages'] ) ? $GLOBALS['q_config']['enabled_languages'] : [];
        }
    
        if ( 'polylang' === $i18n_plugin ) {
            // Polylang, Polylang Pro.
            return pll_languages_list();
        }
    
        return false;
    }

    public function get_smack_i18n_uri() {
        $i18n_plugin = $this->smack_has_i18n();
        $urls        = [];
    
        if ( 'wpml' === $i18n_plugin ) {
            // WPML.
            foreach ( $this->get_smack_i18n_code() as $lang ) {
                $urls[] = $GLOBALS['sitepress']->language_url( $lang );
            }
        } elseif ( 'qtranslate' === $i18n_plugin || 'qtranslate-x' === $i18n_plugin ) {
            // qTranslate, qTranslate-x.
            foreach ( $this->get_smack_i18n_code() as $lang ) {
                if ( 'qtranslate' === $i18n_plugin ) {
                    $urls[] = qtrans_convertURL( home_url(), $lang, true );
                } else {
                    $urls[] = qtranxf_convertURL( home_url(), $lang, true );
                }
            }
        } elseif ( 'polylang' === $i18n_plugin ) {
            // Polylang, Polylang Pro.
            $pll = function_exists( 'PLL' ) ? PLL() : $GLOBALS['polylang'];
    
            if ( ! empty( $pll ) && is_object( $pll ) ) {
                $urls = wp_list_pluck( $pll->model->get_languages_list(), 'search_url' );
            }
        }
    
        if ( empty( $urls ) ) {
            $urls[] = home_url();
        }
    
        return $urls;
    }

    public function smack_clean_post( $post_id, $post = null ) {
        static $done = [];
    
        if ( isset( $done[ $post_id ] ) ) {
            return;
        }
    
        $done[ $post_id ] = 1;
    
        if ( defined( 'DOING_AUTOSAVE' ) ) {
            return;
        }
    
        $purge_urls = [];
    
        // Get all post infos if the $post object was not supplied.
        if ( is_null( $post ) ) {
            $post = get_post( $post_id );
        }
    
        // Return if $post is not an object.
        if ( ! is_object( $post ) ) {
            return;
        }
    
        // No purge for specific conditions.
        if ( 'auto-draft' === $post->post_status || 'draft' === $post->post_status || empty( $post->post_type ) || 'nav_menu_item' === $post->post_type || 'attachment' === $post->post_type ) {
            return;
        }
    
        // Don't purge if post's post type is not public or not publicly queryable.
        $post_type = get_post_type_object( $post->post_type );
        if ( ! is_object( $post_type ) || true !== $post_type->public ) {
            return;
        }
    
        // Get the post language.
        $i18n_plugin = $this->smack_has_i18n();
        $lang        = false;
    
        if ( 'wpml' === $i18n_plugin && ! $this->smack_is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {
            // WPML.
            $lang = $GLOBALS['sitepress']->get_language_for_element( $post_id, 'post_' . get_post_type( $post_id ) );
        } elseif ( 'polylang' === $i18n_plugin && function_exists( 'pll_get_post_language' ) ) {
            // Polylang.
            $lang = pll_get_post_language( $post_id );
        }
    
        $purge_urls = $this->smack_get_purge_urls( $post_id, $post );
    
        // Never forget to purge homepage and their pagination.
        $this->smack_clean_home( $lang );
    
        // Purge home feeds (blog & comments).
        $this->smack_clean_home_feeds();
    
       // do_action( 'after_smack_clean_post', $post, $purge_urls, $lang );
       $this->preload_after_clean_post($post, $purge_urls, $lang);
    }

    public function smack_clean_home_feeds() {

        $urls   = array();
        $urls[] = get_feed_link();
        $urls[] = get_feed_link( 'comments_' );
    
        $urls = apply_filters( 'smack_clean_home_feeds', $urls );
        //do_action( 'before_smack_clean_home_feeds', $urls );
    
        $this->smack_clean_files( $urls );
        //do_action( 'after_smack_clean_home_feeds', $urls );
    }

    public function preload_after_clean_post( $post, $purge_urls, $lang ) {
		// if ( ! $this->options->get( 'manual_preload' ) ) {
		// 	return;
		// }

		// Run preload only if post is published.
		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		// Add Homepage URL to $purge_urls for preload.
		array_push( $purge_urls, $this->get_smack_i18n_home_url( $lang ) );

		// Get the author page.
		$purge_author = array( get_author_posts_url( $post->post_author ) );

		// Get all dates archive page.
		$purge_dates = $this->get_smack_post_dates_urls( $post->ID );

		// Remove dates archives page and author page to preload cache.
		$purge_urls = array_diff( $purge_urls, $purge_dates, $purge_author );

		$purge_urls = array_filter( $purge_urls );

		$this->urls = array_merge( $this->urls, $purge_urls );
    }
    
    public function get_smack_post_dates_urls( $post_id ) {
        // Get the day and month of the post.
        $date = explode( '-', get_the_time( 'Y-m-d', $post_id ) );
    
        $urls = array(
            trailingslashit( get_year_link( $date[0] ) ) . 'index.html',
            trailingslashit( get_year_link( $date[0] ) ) . 'index.html_gzip',
            trailingslashit( get_year_link( $date[0] ) ) . $GLOBALS['wp_rewrite']->pagination_base,
            trailingslashit( get_month_link( $date[0], $date[1] ) ) . 'index.html',
            trailingslashit( get_month_link( $date[0], $date[1] ) ) . 'index.html_gzip',
            trailingslashit( get_month_link( $date[0], $date[1] ) ) . $GLOBALS['wp_rewrite']->pagination_base,
            get_day_link( $date[0], $date[1], $date[2] ),
        );
    
        //$urls = apply_filters( 'smack_post_dates_urls', $urls );
        return $urls;
    }

    public function smack_get_purge_urls( $post_id, $post ) {
        $purge_urls = [];
    
        // Get the post language.
        $i18n_plugin = $this->smack_has_i18n();
        $lang        = false;
    
        if ( 'wpml' === $i18n_plugin && ! $this->smack_is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {
            // WPML.
            $lang = $GLOBALS['sitepress']->get_language_for_element( $post_id, 'post_' . get_post_type( $post_id ) );
        } elseif ( 'polylang' === $i18n_plugin && function_exists( 'pll_get_post_language' ) ) {
            // Polylang.
            $lang = pll_get_post_language( $post_id );
        }
    
        // Get the permalink structure.
        $permalink_structure = $this->get_smack_sample_permalink( $post_id );
    
        // Get permalink.
        $permalink = str_replace( [ '%postname%', '%pagename%' ], $permalink_structure[1], $permalink_structure[0] );
    
        // Add permalink.
        if ( $this->smack_extract_url_component( $permalink, PHP_URL_PATH ) !== '/' ) {
            array_push( $purge_urls, $permalink );
        }
    
        // Add Posts page.
        if ( 'post' === $post->post_type && (int) get_option( 'page_for_posts' ) > 0 ) {
            array_push( $purge_urls, get_permalink( get_option( 'page_for_posts' ) ) );
        }
    
        // Add Post Type archive.
        if ( 'post' !== $post->post_type ) {
            $post_type_archive = get_post_type_archive_link( get_post_type( $post_id ) );
            if ( $post_type_archive ) {
                // Rename the caching filename for SSL URLs.
                $filename = 'index';
                if ( is_ssl() ) {
                    $filename .= '-https';
                }
    
                $post_type_archive = trailingslashit( $post_type_archive );
                array_push( $purge_urls, $post_type_archive . $filename . '.html' );
                array_push( $purge_urls, $post_type_archive . $filename . '.html_gzip' );
                array_push( $purge_urls, $post_type_archive . $GLOBALS['wp_rewrite']->pagination_base );
            }
        }
    
        // Add next post.
        $next_post = get_adjacent_post( false, '', false );
        if ( $next_post ) {
            array_push( $purge_urls, get_permalink( $next_post ) );
        }
    
        // Add next post in same category.
        $next_in_same_cat_post = get_adjacent_post( true, '', false );
        if ( $next_in_same_cat_post && $next_in_same_cat_post !== $next_post ) {
            array_push( $purge_urls, get_permalink( $next_in_same_cat_post ) );
        }
    
        // Add previous post.
        $previous_post = get_adjacent_post( false, '', true );
        if ( $previous_post ) {
            array_push( $purge_urls, get_permalink( $previous_post ) );
        }
    
        // Add previous post in same category.
        $previous_in_same_cat_post = get_adjacent_post( true, '', true );
        if ( $previous_in_same_cat_post && $previous_in_same_cat_post !== $previous_post ) {
            array_push( $purge_urls, get_permalink( $previous_in_same_cat_post ) );
        }
    
       
        // Add all terms archive page to purge.
        $purge_terms = $this->get_smack_post_terms_urls( $post_id );
        if ( count( $purge_terms ) ) {
            $purge_urls = array_merge( $purge_urls, $purge_terms );
        }
    
        // Add all dates archive page to purge.
        $purge_dates = $this->get_smack_post_dates_urls( $post_id );
        if ( count( $purge_dates ) ) {
            $purge_urls = array_merge( $purge_urls, $purge_dates );
        }
    
        // Add the author page.
        $purge_author = [ get_author_posts_url( $post->post_author ) ];
        $purge_urls   = array_merge( $purge_urls, $purge_author );
    
        // Add all parents.
        $parents = get_post_ancestors( $post_id );
        if ( (bool) $parents ) {
            foreach ( $parents as $parent_id ) {
                array_push( $purge_urls, get_permalink( $parent_id ) );
            }
        }
    
        return $purge_urls;
    }

    public function get_smack_post_terms_urls( $post_id ) {
        $urls       = array();
        $taxonomies = get_object_taxonomies( get_post_type( $post_id ), 'objects' );
    
        foreach ( $taxonomies as $taxonomy ) {
            if ( ! $taxonomy->public || 'product_shipping_class' === $taxonomy->name ) {
                continue;
            }
    
            // Get the terms related to post.
            $terms = get_the_terms( $post_id, $taxonomy->name );
    
            if ( ! empty( $terms ) ) {
                foreach ( $terms as $term ) {
                    $term_url = get_term_link( $term->slug, $taxonomy->name );
    
                    if ( ! is_wp_error( $term_url ) ) {
                        $urls[] = $term_url;
                    }
                }
            }
        }
        $urls = apply_filters( 'smack_post_terms_urls', $urls );
        return $urls;
    }

    public function smack_is_plugin_active( $plugin ) {
        return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) || $this->smack_is_plugin_active_for_network( $plugin );
    }
    
    public function smack_is_plugin_active_for_network( $plugin ) {
        if ( ! is_multisite() ) {
            return false;
        }
    
        $plugins = get_site_option( 'active_sitewide_plugins' );
        if ( isset( $plugins[ $plugin ] ) ) {
            return true;
        }
    
        return false;
    }

    public function get_smack_sample_permalink( $id, $title = null, $name = null ) {
        $post = get_post( $id );
        if ( ! $post ) {
            return array( '', '' );
        }
    
        $ptype = get_post_type_object( $post->post_type );
    
        $original_status = $post->post_status;
        $original_date   = $post->post_date;
        $original_name   = $post->post_name;
    
        // Hack: get_permalink() would return ugly permalink for drafts, so we will fake that our post is published.
        if ( in_array( $post->post_status, array( 'draft', 'pending' ), true ) ) {
            $post->post_status = 'publish';
            $post->post_name   = sanitize_title( $post->post_name ? $post->post_name : $post->post_title, $post->ID );
        }
    
        // If the user wants to set a new name -- override the current one.
        // Note: if empty name is supplied -- use the title instead, see #6072.
        if ( ! is_null( $name ) ) {
            $post->post_name = sanitize_title( $name ? $name : $title, $post->ID );
        }
    
        $post->post_name = wp_unique_post_slug( $post->post_name, $post->ID, $post->post_status, $post->post_type, $post->post_parent );
    
        $post->filter = 'sample';
    
        $permalink = get_permalink( $post, false );
    
        // Replace custom post_type Token with generic pagename token for ease of use.
        $permalink = str_replace( "%$post->post_type%", '%pagename%', $permalink );
    
        // Handle page hierarchy.
        if ( $ptype->hierarchical ) {
            $uri = get_page_uri( $post );
            $uri = untrailingslashit( $uri );
            $uri = strrev( stristr( strrev( $uri ), '/' ) );
            $uri = untrailingslashit( $uri );
    
            /** This filter is documented in wp-admin/edit-tag-form.php */
            $uri = apply_filters( 'editable_slug', $uri, $post );
            if ( ! empty( $uri ) ) {
                $uri .= '/';
            }
            $permalink = str_replace( '%pagename%', "{$uri}%pagename%", $permalink );
        }
    
        /** This filter is documented in wp-admin/edit-tag-form.php */
        $permalink         = array( $permalink, apply_filters( 'editable_slug', $post->post_name, $post ) );
        $post->post_status = $original_status;
        $post->post_date   = $original_date;
        $post->post_name   = $original_name;
        unset( $post->filter );
    
        return $permalink;
    }

    public function smack_extract_url_component( $url, $component ) {
        return _get_component_from_parsed_url_array( wp_parse_url( $url ), $component );
    }

    function smack_clean_files_users( $urls ) {
        $pattern_urls = [];
        foreach ( $urls as $url ) {
            $parse_url      = $this->get_smack_parse_url( $url );
            $pattern_urls[] = $parse_url['scheme'] . '://' . $parse_url['host'] . '*' . $parse_url['path'];
        }
        return $pattern_urls;
    }

    public function smack_clean_files( $urls ) {
        $urls = (array) $urls;
    
       
        $urls = $this->smack_clean_files_users($urls);

        $urls = array_filter( (array) $urls );
    
        if ( ! $urls ) {
            return;
        }

       // do_action( 'before_smack_clean_files', $urls );
    
        foreach ( $urls as $url ) {
            //do_action( 'before_smack_clean_file', $url );
    
            /** This filter is documented in inc/front/htaccess.php */
            if ( apply_filters( 'smack_url_no_dots', false ) ) {
                $url = str_replace( '.', '_', $url );
            }
    
            $dirs = glob( WP_CONTENT_DIR . '/cache/smack-preload/' . $this->smack_remove_url_protocol( $url ), GLOB_NOSORT );
    
            if ( $dirs ) {
                foreach ( $dirs as $dir ) {
                    $this->smack_rrmdir( $dir );
                }
            }
           
        }
        
    }

    public function smack_remove_url_protocol( $url, $no_dots = false ) {
        $url = str_replace( [ 'http://', 'https://' ], '', $url );
    
        /** This filter is documented in inc/front/htaccess.php */
        if ( apply_filters( 'smack_url_no_dots', $no_dots ) ) {
            $url = str_replace( '.', '_', $url );
        }
        return $url;
    }

    public function smack_clean_home( $lang = '' ) {
        $parse_url = $this->get_smack_parse_url( $this->get_smack_i18n_home_url( $lang ) );
    
        /** This filter is documented in inc/front/htaccess.php */
        if ( apply_filters( 'smack_url_no_dots', false ) ) {
            $parse_url['host'] = str_replace( '.', '_', $parse_url['host'] );
        }
    
        $root = WP_CONTENT_DIR . '/cache/smack-preload/' . $parse_url['host'] . '*' . untrailingslashit( $parse_url['path'] );
    
        
        $root = $this->smack_clean_home_root_for_domain_mapping_siteurl($root, $parse_url['host'], $parse_url['path']);
        //do_action( 'before_smack_clean_home', $root, $lang );
    
        // Delete homepage.
        $files = glob( $root . '/{index,index-*}.{html,html_gzip}', GLOB_BRACE | GLOB_NOSORT );
        if ( $files ) {
            foreach ( $files as $file ) { // no array map to use @.
                $this->smack_direct_filesystem()->delete( $file );
            }
        }
    
        // Delete homepage pagination.
        $dirs = glob( $root . '*/' . $GLOBALS['wp_rewrite']->pagination_base, GLOB_NOSORT );
        if ( $dirs ) {
            foreach ( $dirs as $dir ) {
                $this->smack_rrmdir( $dir );
            }
        }
    
        // Remove the hidden empty file for mobile detection on NGINX with the NGINX configuration.
        $nginx_mobile_detect_files = glob( $root . '/.mobile-active', GLOB_BRACE | GLOB_NOSORT );
        if ( $nginx_mobile_detect_files ) {
            foreach ( $nginx_mobile_detect_files as $nginx_mobile_detect_file ) { // no array map to use @.
                $this->smack_direct_filesystem()->delete( $nginx_mobile_detect_file );
            }
        }
    
        // Remove the hidden empty file for webp.
        $nowebp_detect_files = glob( $root . '/.no-webp', GLOB_BRACE | GLOB_NOSORT );
        if ( $nowebp_detect_files ) {
            foreach ( $nowebp_detect_files as $nowebp_detect_file ) { // no array map to use @.
                $this->smack_direct_filesystem()->delete( $nowebp_detect_file );
            }
        }
        //do_action( 'after_smack_clean_home', $root, $lang );
    }

    public function smack_clean_home_root_for_domain_mapping_siteurl( $root, $host, $path ) {
        $original_siteurl_host       = $this->smack_extract_url_component( get_original_url( 'siteurl' ), PHP_URL_HOST );
        $domain_mapping_siteurl_host = $this->smack_extract_url_component( domain_mapping_siteurl( false ), PHP_URL_HOST );
    
        if ( $original_siteurl_host !== $domain_mapping_siteurl_host ) {
            $root = WP_CONTENT_DIR . '/cache/smack-preload/' . $host . '*';
        }
        return $root;
    }

    public function get_smack_i18n_home_url( $lang = '' ) {
        $i18n_plugin = $this->smack_has_i18n();
    
        if ( ! $i18n_plugin ) {
            return home_url();
        }
    
        switch ( $i18n_plugin ) {
            // WPML.
            case 'wpml':
                return $GLOBALS['sitepress']->language_url( $lang );
            // qTranslate.
            case 'qtranslate':
                return qtrans_convertURL( home_url(), $lang, true );
            // qTranslate-x.
            case 'qtranslate-x':
                return qtranxf_convertURL( home_url(), $lang, true );
            // Polylang, Polylang Pro.
            case 'polylang':
                $pll = function_exists( 'PLL' ) ? PLL() : $GLOBALS['polylang'];
    
                if ( ! empty( $pll->options['force_lang'] ) && isset( $pll->links ) ) {
                    return pll_home_url( $lang );
                }
        }
        return home_url();
    }

    public function smack_clean_term( $term_id, $taxonomy_slug ) {
        $purge_urls = [];
    
        // Get all term infos.
        $term = get_term_by( 'id', $term_id, $taxonomy_slug );
    
        // Get the term language.
        $i18n_plugin = $this->smack_has_i18n();
    
        if ( 'wpml' === $i18n_plugin && ! $this->smack_is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {
            // WPML.
            $lang = $GLOBALS['sitepress']->get_language_for_element( $term_id, 'tax_' . $taxonomy_slug );
        } elseif ( 'polylang' === $i18n_plugin ) {
            // Polylang.
            $lang = pll_get_term_language( $term_id );
        } else {
            $lang = false;
        }
    
        // Get permalink.
        $permalink = get_term_link( $term, $taxonomy_slug );
    
        // Add permalink.
        if ( '/' !== $this->smack_extract_url_component( $permalink, PHP_URL_PATH ) ) {
            array_push( $purge_urls, $permalink );
        }
    
        
        $purge_urls = apply_filters( 'smack_term_purge_urls', $purge_urls, $term );
    
        // Purge all files.
        $this->smack_clean_files( $purge_urls );
    
        // Never forget to purge homepage and their pagination.
        $this->smack_clean_home( $lang );

        
        $this->preload_after_clean_term($term, $purge_urls, $lang);
    }

    public function preload_after_clean_term( $term, $purge_urls, $lang ) {
		// if ( ! $this->options->get( 'manual_preload' ) ) {
		// 	return;
		// }

		// Add Homepage URL to $purge_urls for preload.
		array_push( $purge_urls, $this->get_smack_i18n_home_url( $lang ) );
		$purge_urls = array_filter( $purge_urls );
		$this->urls = array_merge( $this->urls, $purge_urls );
	}

}