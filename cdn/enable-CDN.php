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


define('SMACK_CDN_DEFAULT_DIRECTORIES', "wp-content,wp-includes");
define('SMACK_CDN_DEFAULT_EXCLUDED', ".php");

class EnableCDN
{
    
    protected static $instance = null,$plugin;
    
	
	public function __construct()
	{
		$prefetch_dns_requests=get_option('smack_dns_prefetch');
		if(! empty($prefetch_dns_requests)){
			add_action( 'init', array( $this, 'remove_dns_prefetch' ), 99 );
			add_action("wp_head",  array(__CLASS__,'cdn_dns_Prefetch'), 0 );
		}else {
			add_action( 'init', array( $this, 'remove_dns_prefetch' ), 99 );
		}
		add_action("template_redirect", array(__CLASS__,'CDN_Rewrite'));
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
		add_action('wp_ajax_save_cdn_options', array($this,'cdn_Options'));
       add_action('wp_ajax_get_cdn_options', array($this,'send_cdn_options'));
	}

    public static function cdn_Options() {
		
		if(isset($_POST)){
			
			$enable_cdn=sanitize_text_field($_POST['enable_cdn']);
			$exclude_files_url_cdn=sanitize_text_field($_POST['exclude_files_url_cdn']);
			$cdn_details=str_replace("\\" , '' , sanitize_text_field($_POST['cdn_details']));
			$cdn_decode=json_decode($cdn_details, True);  
			foreach($cdn_decode as $cdn_names){
				if(empty( $cdn_names['cdnCnames'])){
					update_option('smack_cdn_domain_input',$cdn_decode);
					return;
				}
				
			}
			
			$file_format = array('all-files','js','css','images');
			
			$cdn_decode_cdnnames = array_column($cdn_decode, 'cdnCnames');
			
			$cdn_decode_filetypes = array_column($cdn_decode, 'reserved_file_types');

			$cdn_decode_array = array_combine($cdn_decode_cdnnames, $cdn_decode_filetypes);

			$cdn_disabled = array_diff($file_format, $cdn_decode_array);

			$cdn_enabled = array_intersect($file_format, $cdn_decode_array);	
			foreach($cdn_enabled as $cdn_enabled_value){
				$cdn_intersect_key = array_search($cdn_enabled_value, $cdn_decode_array);
				if(!empty($cdn_intersect_key)){
					update_option("smack_cdn_". $cdn_enabled_value, $cdn_intersect_key);
				}
			}

			foreach($cdn_disabled as $cdn_disabled_value){
				delete_option("smack_cdn_". $cdn_disabled_value);
			}
			
			if($enable_cdn!=='true'){
				
				delete_option('smack_cdn_all-files');
				delete_option('smack_cdn_js');
				delete_option('smack_cdn_css');
				delete_option('smack_cdn_images');
			}
			
		
			update_option('smack_enable_cdn',$enable_cdn);
			update_option('smack_excluded_cdn_files',$exclude_files_url_cdn);
			update_option('smack_cdn_domain_input',$cdn_decode);
		
		}
		
				$result['success'] = true;
            echo wp_json_encode($result);
            wp_die();
		
    }	

	public static function getOptions() {
		
		$cdn_domain_name=get_option('smack_cdn_all-files');
		$cdn_image_url=get_option('smack_cdn_images');
		$cdn_js_url=get_option('smack_cdn_js');
		$cdn_css_url=get_option('smack_cdn_css');
		$excluded_cdn_url=get_option('smack_excluded_cdn_files');
		
		$explode_excluded_cdn = explode(",", $excluded_cdn_url);
		$implode_excluded_cdn = implode(',', $explode_excluded_cdn);
		  $excluded_cdn=$implode_excluded_cdn.',.php';
		
		$options =
			array(
				"advanced_edit" => 		0,
				"pull_zone" => 			"",
                "cdn_domain_name" => 	$cdn_domain_name,
                "cdn_image_url"  =>    $cdn_image_url,
                "cdn_js_url"  =>     $cdn_js_url,
                "cdn_css_url"  =>     $cdn_css_url,
				"excluded" => 			$excluded_cdn,
				"directories" => 		SMACK_CDN_DEFAULT_DIRECTORIES,
				"site_url" =>			get_option('home'),
				"disable_admin" => 		0,
				"api_key" => 			""
			);

			return $options;
	}

	public static function send_cdn_options(){
		$result['enable_cdn']=get_option('smack_enable_cdn')=== 'true'? true: false;
		$result['domain_json']=get_option('smack_cdn_domain_input');
		if(empty(get_option('smack_excluded_cdn_files'))||get_option('smack_excluded_cdn_files')=== 'false'){
			$result['exclude_files_url_cdn']='';
		}else{
			$result['exclude_files_url_cdn']=get_option('smack_excluded_cdn_files');
		}
		$result['success'] = true;
            echo wp_json_encode($result);
            wp_die();
	}
    
    public static function CDN_Rewrite() 
	{
		$enable_cdn=get_option('smack_enable_cdn');
		
		if($enable_cdn=='true'){
			
			$options = self::getOptions();
			
			if(strlen(trim($options["cdn_domain_name"])) > 0 ||strlen(trim($options["cdn_css_url"])) > 0 ||strlen(trim($options["cdn_js_url"])) > 0||strlen(trim($options["cdn_image_url"])) > 0 )
			{
				
				$rewriter = new RewriteCDN($options["site_url"],(is_ssl() ? 'https://' : 'http://') .  $options["cdn_domain_name"],$options["cdn_image_url"],$options["cdn_js_url"],$options["cdn_css_url"], $options["directories"], $options["excluded"], $options["disable_admin"]);
			
			$result=$rewriter->Rewrite_start();
			
			}	
		}
		
	}

	function  remove_dns_prefetch () {
        remove_action( 'wp_head', 'wp_resource_hints', 2, 99 );
    }

	public static function cdn_dns_Prefetch() 
	{
		
		$prefetch_dns_requests=get_option('smack_dns_prefetch');

		if(!empty($prefetch_dns_requests)){

		
			$result = '';
			
		
				// https://developer.mozilla.org/en-US/docs/Controlling_DNS_prefetching
				// $result = '<meta http-equiv="x-dns-prefetch-control" content="on">';
				$prefetch_dns_requests=explode(',',$prefetch_dns_requests);
			
				
				if (!empty($prefetch_dns_requests)) {
					$prefetch_dns_requests = array_map('esc_url', $prefetch_dns_requests); // Chapter 6 Pro WordPress Plugin Development
					
					foreach ($prefetch_dns_requests as $dpfdomain) {
						$result .= '<link rel="dns-prefetch" href="' . $dpfdomain . '" />';
					}
				}
				
				echo $result;
			}
			// en
	}
 

}
$new_obj = new EnableCDN();
