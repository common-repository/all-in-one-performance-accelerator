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
require_once(__DIR__.'/bufferClass.php');

class deferJS
{
    protected static $instance = null,$plugin;
    protected $buffer_instance;
	
	public function __construct()
	{
        $this->buffer_instance = new bufferClass([]);
    }
    
    public static function getInstance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
            self::$plugin = Plugin::getInstance();
		}
        return self::$instance;
    }
    
    public function maybe_init_process() {
       
		ob_start( [ $this, 'maybe_process_buffer' ] );
    }
    
    public function maybe_process_buffer( $buffer ) {
        $this->maybe_missing_tags($buffer);

		if ( ! $this->buffer_instance->is_html( $buffer ) ) {
			return $buffer;
		}
		
		if ( ! $this->buffer_instance->can_process_buffer( $buffer ) ) {
			$this->buffer_instance->log_last_test_error();
			return $buffer;
		}
        $buffer = $this->smack_defer_js($buffer);
	
		return $buffer;
    }

    public function maybe_missing_tags( $html ) {
		// If there is a redirect the content is empty and can display a false positive notice.
		if ( strlen( $html ) <= 255 ) {
			return;
		}
		// If the http response is not 200 do not report missing tags.
		if ( http_response_code() !== 200 ) {
			return;
		}
        $server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
		// If content type is not HTML do not report missing tags.
		if ( empty( $server_array['content_type'] ) || false === strpos( wp_unslash( $server_array['content_type'] ), 'text/html' ) ) {
			return;
		}
		// If the content does not contain HTML Doctype, do not report missing tags.
		if ( false === stripos( $html, '<!DOCTYPE html' ) ) {
			return;
		}

		// Remove all comments before testing tags. If </html> or </body> tags are commented this will identify it as a missing tag.
		$html         = preg_replace( '/<!--([\\s\\S]*?)-->/', '', $html );
		$missing_tags = [];
		if ( false === strpos( $html, '</html>' ) ) {
			$missing_tags[] = '</html>';
		}

		if ( false === strpos( $html, '</body>' ) ) {
			$missing_tags[] = '</body>';
		}

		if ( did_action( 'wp_footer' ) === 0 ) {
			$missing_tags[] = 'wp_footer()';
		}

		if ( ! $missing_tags ) {
			return;
		}
	}
    
    public function smack_defer_js( $buffer ) {
		
        // if ( ( defined( 'DONOTSMACKOPTIMIZE' ) && DONOTSMACKOPTIMIZE ) || ( defined( 'DONOTASYNCCSS' ) && DONOTASYNCCSS ) ) {
        //     return;
        // }

        $buffer_nocomments = preg_replace( '/<!--(.*)-->/Uis', '', $buffer );
        // Get all JS files with this regex.
        preg_match_all( '#<script\s+([^>]+[\s\'"])?src\s*=\s*[\'"]\s*?([^\'"]+\.js(?:\?[^\'"]*)?)\s*?[\'"]([^>]+)?\/?>#iU', $buffer_nocomments, $tags_match );
    
        if ( ! isset( $tags_match[0] ) ) {
            return $buffer;
        }
    
        $exclude_defer_js = implode( '|', $this->get_smack_exclude_defer_js() );
    
        foreach ( $tags_match[0] as $i => $tag ) {
            // Check if this file should be deferred.
            if ( preg_match( '#(' . $exclude_defer_js . ')#i', $tags_match[2][ $i ] ) ) {
                continue;
            }
    
            // Don't add defer if already async.
            if($tags_match[1][ $i ]!=='' && ($tags_match[3][ $i ])!==''){
                if ( false !== strpos( $tags_match[1][ $i ], 'async' ) || false !== strpos( $tags_match[3][ $i ], 'async' ) ) {
                    continue;
                }

    
            // Don't add defer if already defer.
            
                if ( false !== strpos( $tags_match[1][ $i ], 'defer' ) || false !== strpos( $tags_match[3][ $i ], 'defer' ) ) {
                    continue;
			    }
            }
		
            $deferred_tag = str_replace( '>', ' defer>', $tag );
            $buffer       = str_replace( $tag, $deferred_tag, $buffer );
        }
    
        return $buffer;
    }

    public function get_smack_exclude_defer_js() {
        $exclude_defer_js = [
            'gist.github.com',
            'content.jwplatform.com',
            'js.hsforms.net',
            'www.uplaunch.com',
            'google.com/recaptcha',
            'widget.reviews.co.uk',
            'verify.authorize.net/anetseal',
            'lib/admin/assets/lib/webfont/webfont.min.js',
            'app.mailerlite.com',
        ];
    
		$jquery            = site_url( wp_scripts()->registered['jquery-core']->src );
		$jetpack_jquery    = 'c0.wp.com/c/(?:.+)/wp-includes/js/jquery/jquery.js';
		$googleapis_jquery = 'ajax.googleapis.com/ajax/libs/jquery/(?:.+)/jquery(?:\.min)?.js';
		$cdnjs_jquery      = 'cdnjs.cloudflare.com/ajax/libs/jquery/(?:.+)/jquery(?:\.min)?.js';

		$exclude_defer_js[] = $this->smack_clean_exclude_file( $jquery );
		$exclude_defer_js[] = $jetpack_jquery;
		$exclude_defer_js[] = $googleapis_jquery;
		$exclude_defer_js[] = $cdnjs_jquery;
       
        $exclude_defer_js = $this->smack_exclude_defer_js_uncode($exclude_defer_js);
    
        foreach ( $exclude_defer_js as $i => $exclude ) {
            $exclude_defer_js[ $i ] = str_replace( '#', '\#', $exclude );
        }
        return $exclude_defer_js;
    }

    public function smack_clean_exclude_file( $file ) {
        if ( ! $file ) {
            return false;
        }
        return wp_parse_url( $file, PHP_URL_PATH );
    }

    public function smack_exclude_defer_js_uncode( $exclude_defer_js ) {
        $exclude_defer_js[] = $this->smack_clean_exclude_file( get_template_directory_uri() . '/library/js/init.js' );
        $exclude_defer_js[] = $this->smack_clean_exclude_file( get_template_directory_uri() . '/library/js/min/init.min.js' );
        return $exclude_defer_js;
    }
}