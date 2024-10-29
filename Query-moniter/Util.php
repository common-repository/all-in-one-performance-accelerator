<?php

if ( ! class_exists( 'Util' ) ) {
	
class Util {

	protected static $file_components = array();
	protected static $file_dirs       = array();
	protected static $abspath         = null;
	protected static $contentpath     = null;
	protected static $sort_field      = null;

	private function __construct() {}

	public static function convert_hr_to_bytes( $size ) {

		$bytes = (float) $size;

		if ( $bytes ) {
			$last = strtolower( substr( $size, -1 ) );
			$pos = strpos( ' kmg', $last, 1 );
			if ( $pos ) {
				$bytes *= pow( 1024, $pos );
			}
			$bytes = round( $bytes );
		}

		return $bytes;

	}

	public static function standard_dir( $dir, $path_replace = null ) {

		$dir = self::normalize_path( $dir );

		if ( is_string( $path_replace ) ) {
			if ( ! self::$abspath ) {
				self::$abspath     = self::normalize_path( ABSPATH );
				self::$contentpath = self::normalize_path( dirname( WP_CONTENT_DIR ) . '/' );
			}
			$dir = str_replace( array(
				self::$abspath,
				self::$contentpath,
			), $path_replace, $dir );
		}

		return $dir;

	}

	public static function normalize_path( $path ) {
		if ( function_exists( 'wp_normalize_path' ) ) {
			$path = wp_normalize_path( $path );
		} else {
			$path = str_replace( '\\', '/', $path );
			$path = str_replace( '//', '/', $path );
		}

		return $path;
	}

	public static function get_file_dirs() {
		if ( empty( self::$file_dirs ) ) {
			
			/**
			 * Filters the absolute directory paths that correlate to components.
			 *
			 * Note that this filter is applied before QM adds its built-in list of components. This is
			 * so custom registered components take precedence during component detection.
			 *
			 * See the corresponding `qm/component_name/{$type}` filter for specifying the component name.
			 *
			 * @since 3.6.0
			 *
			 * @param string[] $dirs Array of absolute directory paths keyed by component identifier.
			 */
			self::$file_dirs = apply_filters( 'qm/component_dirs', self::$file_dirs );

			self::$file_dirs['plugin']     = WP_PLUGIN_DIR;
			self::$file_dirs['mu-vendor']  = WPMU_PLUGIN_DIR . '/vendor';
			self::$file_dirs['go-plugin']  = WPMU_PLUGIN_DIR . '/shared-plugins';
			self::$file_dirs['mu-plugin']  = WPMU_PLUGIN_DIR;
			self::$file_dirs['vip-plugin'] = get_theme_root() . '/vip/plugins';

			if ( defined( 'WPCOM_VIP_CLIENT_MU_PLUGIN_DIR' ) ) {
				self::$file_dirs['vip-client-mu-plugin'] = WPCOM_VIP_CLIENT_MU_PLUGIN_DIR;
			}

			if ( defined( '\Altis\ROOT_DIR' ) ) {
				self::$file_dirs['altis-vendor'] = \Altis\ROOT_DIR . '/vendor';
			}

			self::$file_dirs['theme']      = null;
			self::$file_dirs['stylesheet'] = get_stylesheet_directory();
			self::$file_dirs['template']   = get_template_directory();
			self::$file_dirs['other']      = WP_CONTENT_DIR;
			self::$file_dirs['core']       = ABSPATH;
			self::$file_dirs['unknown']    = null;

			foreach ( self::$file_dirs as $type => $dir ) {
				self::$file_dirs[ $type ] = self::standard_dir( $dir );
			}
		}

		return self::$file_dirs;
	}

	public static function get_file_component( $file ) {
 
		# @TODO turn this into a class (eg QM_File_Component)

		$file = self::standard_dir( $file );

		if ( isset( self::$file_components[ $file ] ) ) {
			return self::$file_components[ $file ];
		}

		foreach ( self::get_file_dirs() as $type => $dir ) {
			// this slash makes paths such as plugins-mu match mu-plugin not plugin
			if ( $dir && ( 0 === strpos( $file, trailingslashit( $dir ) ) ) ) {
				break;
			}
		}

		$context = $type;

		switch ( $type ) {
			case 'altis-vendor':
				$plug = str_replace( \Altis\ROOT_DIR . '/vendor/', '', $file );
				$plug = explode( '/', $plug, 3 );
				$plug = $plug[0] . '/' . $plug[1];
				/* translators: %s: Dependency name */
				$name = sprintf( __( 'Dependency: %s', 'query-monitor' ), $plug );
				break;
			case 'plugin':
			case 'mu-plugin':
			case 'mu-vendor':
				$plug = str_replace( '/vendor/', '/', $file );
				$plug = plugin_basename( $plug );
				if ( strpos( $plug, '/' ) ) {
					$plug = explode( '/', $plug );
					$plug = reset( $plug );
				} else {
					$plug = basename( $plug );
				}
				if ( 'plugin' !== $type ) {
					/* translators: %s: Plugin name */
					$name = sprintf( __( '%s', 'query-monitor' ), $plug );
				} else {
					/* translators: %s: Plugin name */
					$name = sprintf( __( '%s', 'query-monitor' ), $plug );
				}
				$context = $plug;
				break;
			case 'go-plugin':
			case 'vip-plugin':
			case 'vip-client-mu-plugin':
				$plug = str_replace( self::$file_dirs[ $type ], '', $file );
				$plug = trim( $plug, '/' );
				if ( strpos( $plug, '/' ) ) {
					$plug = explode( '/', $plug );
					$plug = reset( $plug );
				} else {
					$plug = basename( $plug );
				}
				if ( 'vip-client-mu-plugin' === $type ) {
					/* translators: %s: Plugin name */
					$name = sprintf( __( 'VIP Client MU Plugin: %s', 'query-monitor' ), $plug );
				} else {
					/* translators: %s: Plugin name */
					$name = sprintf( __( 'VIP Plugin: %s', 'query-monitor' ), $plug );
				}
				$context = $plug;
				break;
			case 'stylesheet':
				if ( is_child_theme() ) {
					$name = __( 'Child Theme', 'query-monitor' );
				} else {
					$name = __( 'Theme', 'query-monitor' );
				}
				$type = 'theme';
				break;
			case 'template':
				$name = __( 'Parent Theme', 'query-monitor' );
				$type = 'theme';
				break;
			case 'other':
				// Anything else that's within the content directory should appear as
				// `wp-content/{dir}` or `wp-content/{file}`
				$name    = self::standard_dir( $file );
				$name    = str_replace( dirname( self::$file_dirs['other'] ), '', $name );
				$parts   = explode( '/', trim( $name, '/' ) );
				$name    = $parts[0] . '/' . $parts[1];
				$context = $file;
				break;
			case 'core':
				$name = __( 'Core', 'query-monitor' );
				break;
			case 'unknown':
			default:
				$name = __( 'Unknown', 'query-monitor' );

				/**
				 * Filters the name of a custom or unknown component.
				 *
				 * The dynamic portion of the hook name, `$type`, refers to the component identifier.
				 *
				 * See the corresponding `qm/component_dirs` filter for specifying the component directories.
				 *
				 * @since 3.6.0
				 *
				 * @param string $name The component name.
				 * @param string $file The full file path for the file within the component.
				 */
				$name = apply_filters( "qm/component_name/{$type}", $name, $file );
				break;
		}

		self::$file_components[ $file ] = (object) compact( 'type', 'name', 'context' );

		return self::$file_components[ $file ];
	}



	public static function display_variable( $value ) {
		if ( is_string( $value ) ) {
			return $value;
		} elseif ( is_bool( $value ) ) {
			return ( $value ) ? 'true' : 'false';
		} elseif ( is_scalar( $value ) ) {
			return $value;
		} elseif ( is_object( $value ) ) {
			$class = get_class( $value );

			switch ( true ) {

				case ( $value instanceof WP_Post ):
				case ( $value instanceof WP_User ):
					return sprintf( '%s (ID: %s)', $class, $value->ID );
					break;

				case ( $value instanceof WP_Term ):
					return sprintf( '%s (term_id: %s)', $class, $value->term_id );
					break;

				case ( $value instanceof WP_Comment ):
					return sprintf( '%s (comment_ID: %s)', $class, $value->comment_ID );
					break;

				case ( $value instanceof WP_Error ):
					return sprintf( '%s (%s)', $class, $value->get_error_code() );
					break;

				case ( $value instanceof WP_Role ):
				case ( $value instanceof WP_Post_Type ):
				case ( $value instanceof WP_Taxonomy ):
					return sprintf( '%s (%s)', $class, $value->name );
					break;

				case ( $value instanceof WP_Network ):
					return sprintf( '%s (id: %s)', $class, $value->id );
					break;

				case ( $value instanceof WP_Site ):
					return sprintf( '%s (blog_id: %s)', $class, $value->blog_id );
					break;

				case ( $value instanceof WP_Theme ):
					return sprintf( '%s (%s)', $class, $value->get_stylesheet() );
					break;

				default:
					return $class;
					break;

			}
		} else {
			return gettype( $value );
		}
	}

	/**
	 * Shortens a fully qualified name to reduce the length of the names of long namespaced symbols.
	 *
	 * This initialises portions that do not form the first or last portion of the name. For example:
	 *
	 *     Inpsyde\Wonolog\HookListener\HookListenersRegistry->hook_callback()
	 *
	 * becomes:
	 *
	 *     Inpsyde\W\H\HookListenersRegistry->hook_callback()
	 *
	 * @param string $fqn A fully qualified name.
	 * @return string A shortened version of the name.
	 */
	public static function shorten_fqn( $fqn ) {
		return preg_replace_callback( '#\\\\[a-zA-Z0-9_\\\\]{4,}\\\\#', function( array $matches ) {
			preg_match_all( '#\\\\([a-zA-Z0-9_])#', $matches[0], $m );
			return '\\' . implode( '\\', $m[1] ) . '\\';
		}, $fqn );
	}



	public static function sort( array &$array, $field ) {
		self::$sort_field = $field;
		usort( $array, array( __CLASS__, '_sort' ) );
	}

	public static function rsort( array &$array, $field ) {
		self::$sort_field = $field;
		usort( $array, array( __CLASS__, '_rsort' ) );
	}

	private static function _rsort( $a, $b ) {
		$field = self::$sort_field;

		if ( $a[ $field ] === $b[ $field ] ) {
			return 0;
		} else {
			return ( $a[ $field ] > $b[ $field ] ) ? -1 : 1;
		}
	}

	private static function _sort( $a, $b ) {
		$field = self::$sort_field;

		if ( $a[ $field ] === $b[ $field ] ) {
			return 0;
		} else {
			return ( $a[ $field ] > $b[ $field ] ) ? 1 : -1;
		}
	}

}
}
