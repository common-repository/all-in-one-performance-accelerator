<?php
/**
 * All-in-one Performance Accelerator plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\AIOACC;
require_once(ABSPATH . 'wp-admin/includes/file.php');
if ( ! defined( 'ABSPATH' ) ) {
	die;
}
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/misc.php');
require_once(ABSPATH . 'wp-includes/class-wp-locale-switcher.php');
require_once(ABSPATH . 'wp-includes/l10n.php');

class Compression
{
	protected static $instance = null,$plugin;
    
    const HTACCESS_FILENAME = '.htaccess';

     	
	

	public function __construct()
	{
		
			add_action('admin_init', array($this,'add_gzip_compression'));
      
      
       
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
		
	}

	public function add_gzip_compression() {
		$add_gzip_compression=get_option('smack_gzip_compression');
		if($add_gzip_compression == 'true'){
			
			$result=$this->enable_gzip_compression_to_htaccess();
			$response=$this->test_gzip_compression_working();
			if($response == 'true'){
				$messages='Gzip compression is now enabled and working.';
			}else{
				$messages='Gzip compression seems not to be working. Perhaps mod_deflate module is not active.';
			}
		}if($add_gzip_compression == 'false'){
			$response=$this->disable_gzip_compression_from_htaccess();
			if($response == 'true'){
				$message='Gzip compression is now disabled';
			}
		}

	}
    public function test_gzip_compression_working() {
		$header = array(
			'headers' => array(
				'Content-Encoding' => 'gzip'
			)
		);
		
		$result = wp_remote_get(get_site_url(), $header);
		if (!is_array($result)) {
			return FALSE;
		}
		
		return strpos($result['headers']['content-encoding'], 'gzip') !== FALSE;
    }
    
    public function enable_gzip_compression_to_htaccess() {
		$file = get_home_path() . self::HTACCESS_FILENAME;
	
		$rules = '# Gzip compression' . PHP_EOL;
	$rules .= '<IfModule mod_deflate.c>' . PHP_EOL;
		$rules .= '# Active compression' . PHP_EOL;
		$rules .= 'SetOutputFilter DEFLATE' . PHP_EOL;
		$rules .= '# Force deflate for mangled headers' . PHP_EOL;
		$rules .= '<IfModule mod_setenvif.c>' . PHP_EOL;
			$rules .= '<IfModule mod_headers.c>' . PHP_EOL;
			$rules .= 'SetEnvIfNoCase ^(Accept-EncodXng|X-cept-Encoding|X{15}|~{15}|-{15})$ ^((gzip|deflate)\s*,?\s*)+|[X~-]{4,13}$ HAVE_Accept-Encoding' . PHP_EOL;
			$rules .= 'RequestHeader append Accept-Encoding "gzip,deflate" env=HAVE_Accept-Encoding' . PHP_EOL;
			$rules .= '# Don’t compress images and other uncompressible content' . PHP_EOL;
			$rules .= 'SetEnvIfNoCase Request_URI \\' . PHP_EOL;
			$rules .= '\\.(?:gif|jpe?g|png|rar|zip|exe|flv|mov|wma|mp3|avi|swf|mp?g|mp4|webm|webp|pdf)$ no-gzip dont-vary' . PHP_EOL;
			$rules .= '</IfModule>' . PHP_EOL;
		$rules .= '</IfModule>' . PHP_EOL . PHP_EOL;
		$rules .= '# Compress all output labeled with one of the following MIME-types' . PHP_EOL;
		$rules .= '<IfModule mod_filter.c>' . PHP_EOL;
		$rules .= 'AddOutputFilterByType DEFLATE application/atom+xml \
		                          application/javascript \
		                          application/json \
		                          application/rss+xml \
		                          application/vnd.ms-fontobject \
		                          application/x-font-ttf \
		                          application/xhtml+xml \
		                          application/xml \
		                          font/opentype \
		                          image/svg+xml \
		                          image/x-icon \
		                          text/css \
		                          text/html \
		                          text/plain \
		                          text/x-component \
		                          text/xml' . PHP_EOL;
		$rules .= '</IfModule>' . PHP_EOL;
		$rules .= '<IfModule mod_headers.c>' . PHP_EOL;
			 $rules .= 'Header append Vary: Accept-Encoding' . PHP_EOL;
	   $rules .= '</IfModule>' . PHP_EOL;
	$rules .= '</IfModule>' . PHP_EOL . PHP_EOL;
		$result=insert_with_markers($file, 'Smack_Gzip_Compression', $rules);
		return $result;
	}
    
    

	public function disable_gzip_compression_from_htaccess() {
		
		$file = get_home_path() . self::HTACCESS_FILENAME;
		return insert_with_markers($file, 'Smack_Gzip_Compression', array());
	}
	
}
$new_obj = new Compression();