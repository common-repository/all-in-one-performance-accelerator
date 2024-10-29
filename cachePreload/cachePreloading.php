<?php
/**
 * All-in-one Performance Accelerator plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\AIOACC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class cachePreloading
{
    protected static $instance = null, $plugin;
	protected $process_id = 'caching process';
    private $cache_dir_path;
	protected $buffer_instance;
    
    public function __construct() {
		$this->buffer_instance = new bufferClass([]);
        $this->cache_dir_path = WP_CONTENT_DIR. '/cache/smack-preload/';
    }


    /**
     * Serve the cache file if it exists. If not, init the buffer.
     */
    public function maybe_init_process() {

		if ( ! $this->buffer_instance->can_init_process() ) {
																																																																																																																																																					
			$this->buffer_instance->define_donotoptimize_true();
					$error_msg=$this->buffer_instance->log_last_test_error();
					update_option('smack_preload_error',$error_msg['message']);
			return;
		}
																																																																																																																																					
																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																										
        /**
         * Serve the cache file if it exists.
         */
        $cache_filepath = $this->get_cache_path();
        $cache_filepath_gzip = $cache_filepath . '_gzip';
        $accept_encoding     = $this->buffer_instance->get_server_input( 'HTTP_ACCEPT_ENCODING' );
        $accept_gzip         = $accept_encoding && false !== strpos( $accept_encoding, 'gzip' );

        // Check if cache file exist.
        if ( $accept_gzip && is_readable( $cache_filepath_gzip ) ) {
            $this->serve_gzip_cache_file( $cache_filepath_gzip );
        }

        if ( is_readable( $cache_filepath ) ) {
            $this->serve_cache_file( $cache_filepath );
        }

        // Maybe we're looking for a webp file.
        $cache_filename = basename( $cache_filepath );
        if ( strpos( $cache_filename, '-webp' ) !== false ) {
            // We're looking for a webp file that doesn't exist: try to locate any `.no-webp` file.
            $cache_dir_path = rtrim( dirname( $cache_filepath ), '/\\' ) . DIRECTORY_SEPARATOR;

            if ( file_exists( $cache_dir_path . '.no-webp' ) ) {
                // We have a `.no-webp` file: try to deliver a non-webp cache file.
                $cache_filepath      = $cache_dir_path . str_replace( '-webp', '', $cache_filename );
                $cache_filepath_gzip = $cache_filepath . '_gzip';

                // Try to deliver the non-webp version instead.
                if ( $accept_gzip && is_readable( $cache_filepath_gzip ) ) {
                    $this->serve_gzip_cache_file( $cache_filepath_gzip );
                }

                if ( is_readable( $cache_filepath ) ) {
                    $this->serve_cache_file( $cache_filepath );
                }
            }
        }
        ob_start( [ $this, 'maybe_process_buffer' ] );
    }

    public function maybe_process_buffer( $buffer ) {
        if ( ! $this->buffer_instance->can_process_buffer( $buffer ) ) {
						$this->buffer_instance->log_last_test_error();
            return $buffer;
        }

        $footprint = '';
        $is_html   = $this->buffer_instance->is_html( $buffer );

        if ( ! static::can_generate_caching_files() ) {
            // Not allowed to generate cache files.
            if ( $is_html ) {
                $footprint = $this->get_smack_footprint();
            }
            return $buffer . $footprint;
        }

        $webp_enabled   = preg_match( '@<!-- (has|no) webp -->@', $buffer, $webp_tag );
        $has_webp       = ! empty( $webp_tag ) ? 'has' === $webp_tag[1] : false;
        $cache_filepath = $this->get_cache_path( [ 'webp' => $has_webp ] );
        $cache_dir_path = dirname( $cache_filepath );

        // Create cache folders.
        $this->smack_mkdir_p( $cache_dir_path );

        if ( $is_html ) {
            $footprint = $this->get_smack_footprint( time() );
        }

        // Webp request.
        if ( $webp_enabled ) {
            $buffer = str_replace( $webp_tag[0], '', $buffer );

            if ( ! $has_webp ) {
                // The buffer doesn’t contain webp files.
                $cache_dir_path = rtrim( dirname( $cache_filepath ), '/\\' );

                $this->maybe_create_nowebp_file( $cache_dir_path );
            }
        }

        // Save the cache file.
		$cache = get_option('smack_enable_mobile_cache');
		if($cache === 'true'){
			$cache_filepaths = $this->get_cache_paths( [ 'webp' => $has_webp ] );
			$this->smack_put_content( $cache_filepaths, $buffer . $footprint );
			if ( function_exists( 'gzencode' ) ) {
				$this->smack_put_content( $cache_filepaths . '_gzip', gzencode( $buffer . $footprint, apply_filters( 'smack_gzencode_level_compression', 3 ) ) );
			}
	
			$this->maybe_create_nginx_mobile_file( $cache_filepaths );
	
			// Send headers with the last modified time of the cache file.
			if ( file_exists( $cache_filepaths ) ) {
				header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $cache_filepaths ) ) . ' GMT' );
			}
		}
        $this->smack_put_content( $cache_filepath, $buffer . $footprint );

        if ( function_exists( 'gzencode' ) ) {
            $this->smack_put_content( $cache_filepath . '_gzip', gzencode( $buffer . $footprint, apply_filters( 'smack_gzencode_level_compression', 3 ) ) );
        }

        $this->maybe_create_nginx_mobile_file( $cache_dir_path );

        // Send headers with the last modified time of the cache file.
        if ( file_exists( $cache_filepath ) ) {
            header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $cache_filepath ) ) . ' GMT' );
        }

        if ( $is_html ) {
            $footprint = $this->get_smack_footprint();
        }
        return $buffer . $footprint;
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

		public static function can_generate_caching_files() {
			return (bool) apply_filters( 'do_smack_generate_caching_files', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		}
    
    public function get_cache_path( $args = [] ) {
		$args             = array_merge(
			[
				'webp' => true,
			],
			$args
		);
		$cookies          = $this->buffer_instance->get_cookies();
		$request_uri_path = $this->get_request_cache_path( $cookies );
		$filename         = 'index';

		$filename = $this->maybe_mobile_filename( $filename );

		// Rename the caching filename for SSL URLs.
		if ( is_ssl() && $this->buffer_instance->get_config( 'cache_ssl' ) ) {
			$filename .= '-https';
		}

		if ( $args['webp'] ) {
			$filename = $this->maybe_webp_filename( $filename );
		}

		$filename = $this->maybe_dynamic_cookies_filename( $filename, $cookies );

		// Ensure proper formatting of the path.
		$request_uri_path = preg_replace_callback( '/%[0-9A-F]{2}/', [ $this, 'reset_lowercase' ], $request_uri_path );
		// Directories in Windows can't contain question marks.
		$request_uri_path = str_replace( '?', '#', $request_uri_path );
		// Limit filename max length to 255 characters.
		$request_uri_path .= '/' . substr( $filename, 0, 250 ) . '.html';

		$post_ids = get_option('smack_never_cache_ids');
		$slugs = get_option('smack_never_cache_slugs');
		if(!empty($post_ids)){
			$post = get_permalink($post_ids);
			$key_word = str_replace('http:/','',$post);
			if(strpos($request_uri_path,$key_word)){
				$request_uri_path = '';
			}
		}
		if(!empty($slugs)){
			if(strpos($request_uri_path,$slugs)){
				$request_uri_path = '';
			}
		}
		return $request_uri_path;
    }

	public function get_cache_paths( $args = [] ) {
		$args             = array_merge(
			[
				'webp' => true,
			],
			$args
		);
		$cookies          = $this->buffer_instance->get_cookies();
		$request_uri_path = $this->get_request_cache_path( $cookies );
		$filename         = 'index-mobile';

		$filename = $this->maybe_mobile_filename( $filename );

		// Rename the caching filename for SSL URLs.
		if ( is_ssl() && $this->buffer_instance->get_config( 'cache_ssl' ) ) {
			$filename .= '-https';
		}

		if ( $args['webp'] ) {
			$filename = $this->maybe_webp_filename( $filename );
		}

		$filename = $this->maybe_dynamic_cookies_filename( $filename, $cookies );

		// Ensure proper formatting of the path.
		$request_uri_path = preg_replace_callback( '/%[0-9A-F]{2}/', [ $this, 'reset_lowercase' ], $request_uri_path );
		// Directories in Windows can't contain question marks.
		$request_uri_path = str_replace( '?', '#', $request_uri_path );
		// Limit filename max length to 255 characters.
		$request_uri_path .= '/' . substr( $filename, 0, 250 ) . '.html';

		$post_ids = get_option('smack_never_cache_ids');
		$slugs = get_option('smack_never_cache_slugs');
		if(!empty($post_ids)){
			$post = get_permalink($post_ids);
			$key_word = str_replace('http:/','',$post);
			if(strpos($request_uri_path,$key_word)){
				$request_uri_path = '';
			}
		}
		if(!empty($slugs)){
			if(strpos($request_uri_path,$slugs)){
				$request_uri_path = '';
			}
		}
		return $request_uri_path;
    }
    
    protected function reset_lowercase( $matches ) {
		return strtolower( $matches[0] );
    }
    
    private function get_request_cache_path( $cookies ) {
		$host = $this->buffer_instance->get_host();

		if ( $this->buffer_instance->get_config( 'url_no_dots' ) ) {
			$host = str_replace( '.', '_', $host );
		}

		$request_uri              = $this->buffer_instance->get_clean_request_uri();
		$cookie_hash              = $this->buffer_instance->get_config( 'cookie_hash' );
		$logged_in_cookie         = $this->buffer_instance->get_config( 'logged_in_cookie' );
		$logged_in_cookie_no_hash = str_replace( $cookie_hash, '', $logged_in_cookie );

		// Get cache folder of host name.
		if ( $logged_in_cookie && isset( $cookies[ $logged_in_cookie ] ) && ! $this->buffer_instance->has_rejected_cookie( $logged_in_cookie_no_hash ) ) {
			if ( $this->buffer_instance->get_config( 'common_cache_logged_users' ) ) {
				return $this->cache_dir_path . $host . '-loggedin' . rtrim( $request_uri, '/' );
			}

			$user_key = explode( '|', $cookies[ $logged_in_cookie ] );
			$user_key = reset( $user_key );
			$user_key = $user_key . '-' . $this->buffer_instance->get_config( 'secret_cache_key' );

			// Get cache folder of host name.
			return $this->cache_dir_path . $host . '-' . $user_key . rtrim( $request_uri, '/' );
		}

		return $this->cache_dir_path . $host . rtrim( $request_uri, '/' );
	}

    private function maybe_mobile_filename( $filename ) {
		$cache_mobile_files_tablet = $this->buffer_instance->get_config( 'cache_mobile_files_tablet' );

		if ( ! ( $this->buffer_instance->get_config( 'cache_mobile' ) && $this->buffer_instance->get_config( 'do_caching_mobile_files' ) ) ) {
			return $filename;
		}

		if ( ! $cache_mobile_files_tablet ) {
			return $filename;
		}

		

		return $filename;
	}

    private function maybe_webp_filename( $filename ) {
		if ( ! $this->buffer_instance->get_config( 'cache_webp' ) ) {
			return $filename;
		}

		$disable_webp_cache = apply_filters( 'smack_disable_webp_cache', false );

		if ( $disable_webp_cache ) {
			return $filename;
		}

		$http_accept = $this->buffer_instance->get_server_input( 'HTTP_ACCEPT', '' );

		if ( ! $http_accept && function_exists( 'apache_request_headers' ) ) {
			$headers     = apache_request_headers();
			$http_accept = isset( $headers['Accept'] ) ? $headers['Accept'] : '';
		}

		if ( ! $http_accept || false === strpos( $http_accept, 'webp' ) ) {
			if ( preg_match( '#Firefox/(?<version>[0-9]{2})#i', $this->buffer_instance->get_server_input( 'HTTP_USER_AGENT' ), $matches ) ) {
				if ( 66 <= (int) $matches['version'] ) {
					return $filename . '-webp';
				}
			}

			return $filename;
		}

		return $filename . '-webp';
    }
    
    private function maybe_dynamic_cookies_filename( $filename, $cookies ) {
			$cache_dynamic_cookies = $this->buffer_instance->get_config( 'cache_dynamic_cookies' );

			if ( ! $cache_dynamic_cookies ) {
				return $filename;
			}

			foreach ( $cache_dynamic_cookies as $key => $cookie_name ) {
				$cookie_array =  filter_input_array(INPUT_COOKIE, FILTER_SANITIZE_STRING);
				if ( is_array( $cookie_name ) ) {
					if ( isset( $cookie_array[ $key ] ) ) {
						foreach ( $cookie_name as $cookie_key ) {
							if ( '' !== $cookies[ $key ][ $cookie_key ] ) {
								$cache_key = $cookies[ $key ][ $cookie_key ];
								$cache_key = preg_replace( '/[^a-z0-9_\-]/i', '-', $cache_key );
								$filename .= '-' . $cache_key;
							}
						}
					}
					continue;
				}

				if ( isset( $cookies[ $cookie_name ] ) && '' !== $cookies[ $cookie_name ] ) {
					$cache_key = $cookies[ $cookie_name ];
					$cache_key = preg_replace( '/[^a-z0-9_\-]/i', '-', $cache_key );
					$filename .= '-' . $cache_key;
				}
			}

			return $filename;
		}

    private function serve_cache_file( $cache_filepath ) {
				header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $cache_filepath ) ) . ' GMT' );

				$if_modified_since = $this->get_if_modified_since();

				// Checking if the client is validating his cache and if it is current.
				if ( $if_modified_since && ( strtotime( $if_modified_since ) === @filemtime( $cache_filepath ) ) ) {
					// Client's cache is current, so we just respond '304 Not Modified'.
					header( $this->buffer_instance->get_server_input( 'SERVER_PROTOCOL', '' ) . ' 304 Not Modified', true, 304 );
					header( 'Expires: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
					header( 'Cache-Control: no-cache, must-revalidate' );
					exit;
				}

				// Serve the cache if file isn't store in the client browser cache.
				readfile( $cache_filepath );
				exit;
    }

    private function get_if_modified_since() {
			if ( function_exists( 'apache_request_headers' ) ) {
				$headers = apache_request_headers();

				return isset( $headers['If-Modified-Since'] ) ? $headers['If-Modified-Since'] : '';
			}

			return $this->buffer_instance->get_server_input( 'HTTP_IF_MODIFIED_SINCE', '' );
		}

    private function serve_gzip_cache_file( $cache_filepath ) {
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $cache_filepath ) ) . ' GMT' );

			$if_modified_since = $this->get_if_modified_since();

			// Checking if the client is validating his cache and if it is current.
			if ( $if_modified_since && ( strtotime( $if_modified_since ) === @filemtime( $cache_filepath ) ) ) {
				// Client's cache is current, so we just respond '304 Not Modified'.
				header( $this->buffer_instance->get_server_input( 'SERVER_PROTOCOL', '' ) . ' 304 Not Modified', true, 304 );
				header( 'Expires: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
				header( 'Cache-Control: no-cache, must-revalidate' );
				exit;
			}

			// Serve the cache if file isn't store in the client browser cache.
			readgzfile( $cache_filepath );
			exit;
		}

		public function smack_direct_filesystem() {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
			return new \WP_Filesystem_Direct( new \StdClass() );
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

		private function get_smack_footprint( $time = '' ) {
			$footprint = "\n" . '<!-- Smack Cached for great performance' ;

			if ( ! empty( $time ) ) {
				$footprint .= ' - Debug: cached@' . $time;
			}
			$footprint .= ' -->';
			return $footprint;
		}

		public function smack_put_content( $file, $content ) {
			$chmod = $this->smack_get_filesystem_perms( 'file' );
			return $this->smack_direct_filesystem()->put_contents( $file, $content, $chmod );
		}

	private function maybe_create_nginx_mobile_file( $cache_dir_path ) {
		global $is_nginx;

		if ( ! $this->buffer_instance->get_config( 'do_caching_mobile_files' ) ) {
			return;
		}

		if ( ! $is_nginx ) {
			return;
		}

		$nginx_mobile_detect = $cache_dir_path . '/.mobile-active';

		if ( $this->smack_direct_filesystem()->exists( $nginx_mobile_detect ) ) {
			return;
		}

		$this->smack_direct_filesystem()->touch( $nginx_mobile_detect );
	}
}