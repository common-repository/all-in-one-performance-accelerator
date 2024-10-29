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


require_once(__DIR__.'/../vendor/autoload.php');
use MatthiasMullie\Minify;

class MinifyCss
{
	protected static $instance = null,$plugin;
	
	public function __construct()
	{ 
		   $combine_css=get_option('smack_combine_css');
		   $blocking_css=get_option('smack_blocking_css');
		   $blocking_js=get_option('smack_blocking_js');
		   $minify_css=get_option('smack_minify_css');
		   $query_strings=get_option('smack_remove_query_strings');
		   if($combine_css == 'false' && $minify_css=='true'){
			add_filter( 'style_loader_tag', array($this,'replace_style_tag'), 10, 3);
			
		   }
		   if($blocking_css == 'true'){
			add_filter( 'style_loader_tag', array($this,'blocking_style_sheet'), 10, 4 ); 
		   }
		   if($blocking_js == 'true'){
			add_filter( 'script_loader_tag', array($this,'blocking_java_script'), 10, 2 ); 
		   
		   }
		   if($query_strings == 'true'){
		   add_filter( 'script_loader_src',array($this,'_remove_script_version') , 15, 1 );
           add_filter( 'style_loader_src', array($this,'_remove_script_version'), 15, 1 );
		   }
		}

	

	public static function replace_style_tag( $tag, $handle, $src ) {
		
		$theme  = wp_get_theme();
		$active_theme  = get_stylesheet_directory_uri();
		$themes = explode($active_theme, $src);
		if(!empty($themes[1])){
			$theme_url = $themes[1];
			if($theme_url){
				$themes = explode('wp-content',$src);
				$theme_url = $themes[1];
				$theme_path = substr($theme_url, 1);
				if(strpos($theme_path, 'assets') !== false) {
					$excluded = explode('css',$theme_path);
				$css_path=$excluded[0].'css';
				}elseif(strpos($theme_path, 'css/') !== false){
					if(strpos($theme_path, 'assets') == false) {
					$excluded = explode('css',$theme_path);
				$css_path=$excluded[0].'css';
					}
				}else{
					$excluded = explode($theme->stylesheet,$theme_path);
					$css_path=$excluded[0].$theme->stylesheet;
					
				}
				$excluded_css=get_option('smack_excluded_css');
			$exclude_urls = explode(',',$excluded_css);
			foreach($exclude_urls as $exclude_url){
				
				$exclude[]=site_url().'/'.$exclude_url;
			}
			$without_versions = explode('?',$src);
				if (!in_array($without_versions[0],$exclude)){
			   $modified="cache/smack-minify/themes/css";
			   $stylesheet=str_replace($css_path,$modified,$src);
			 $tag=str_replace($src, $stylesheet, $tag);
				}
			}
		}
		

		
		return $tag;
	
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
		add_action('wp_ajax_save_file_optimization_options', array($this,'minify_options'));
        add_action('wp_ajax_get_file_optimization_options', array($this,'send_minify_options'));
		add_action('wp_ajax_save_gzip_options', array($this,'check_gzip_enabled'));
		add_action('wp_ajax_get_file_selected_tab', array($this,'get_file_selected_tab'));
		
		
	}

	public function get_file_selected_tab(){
		$tab = sanitize_text_field($_POST['tab']);
		if($tab === 'undefined'){
			$tab_value = get_option('smack_file_tab');
			if(empty($tab_value)){
				$tab_name = 'htmlfile';
				update_option('smack_file_tab',$tab_name);
			}else{
				update_option('smack_file_tab',$tab_value);
			}
		}else{
			update_option('smack_file_tab',$tab);
		}
		$tab_address = get_option('smack_file_tab');
		$result['tab'] = $tab_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}


	public function blocking_java_script($tag, $handle){
		if ( !is_user_logged_in() ) {
			global $wp_scripts;
		if ( 'jquery-core' === $handle ) {
			 return str_replace(' src',  ' async src', $tag);
		}else{
			$async_src="async";
			return str_replace(' src',  ' '.$async_src.' src', $tag); 
		}
	}
		return $tag;

	}

	public function blocking_style_sheet($html, $handle, $href, $media){
			global $wp_styles;
			$onload = 'this.onload=null;this.rel="stylesheet"';
			if( in_array( $handle, $wp_styles->queue ) ){
				$html = str_replace("rel='stylesheet'", " rel='stylesheet' rel='preload'  as='style' onload='$onload'  ", $html);
			}	
		return $html;
	}
	
	public function minify_options(){
		if($_POST){
			$need_to_preload = false;
			$existing_defer_js = get_option('smack_deferred_js');
			$preloading_enabled = get_option('smack_activate_preloading');

			if($existing_defer_js != sanitize_text_field($_POST['defer_js'])){
				$need_to_preload = true;
			}

			$minify_html = sanitize_text_field($_POST['minify_html']);
			$disable_google_fonts = sanitize_text_field($_POST['disable_google_fonts']);
			$combine_google_fonts = sanitize_text_field($_POST['combine_google_fonts']);
			$query_string=sanitize_text_field($_POST['remove_query_strings']);
			$minify_css = sanitize_text_field($_POST['minify_css']);
			$combine_css = sanitize_text_field($_POST['combine_css']);
			$blocking_css = sanitize_text_field($_POST['blocking_css']);
			$minify_js = sanitize_text_field($_POST['minify_js']);
			$combine_js = sanitize_text_field($_POST['combine_js']);
			$blocking_js = sanitize_text_field($_POST['blocking_js']);
			$gzip_compression = sanitize_text_field($_POST['enable_gzip_compression']);
			$defer_js = sanitize_text_field($_POST['defer_js']);
			$excluded_css = esc_url_raw($_POST['exclude_files_url_css']);
			$excluded_js = esc_url_raw($_POST['exclude_files_url_js']);  
			$delay_js_script = esc_url_raw($_POST['delay_js_script']);
			update_option('smack_minify_html',$minify_html);
			update_option('smack_google_fonts',$disable_google_fonts);
			update_option('smack_combine_google_fonts',$combine_google_fonts);
			update_option('smack_remove_query_strings',$query_string);
			update_option('smack_gzip_compression',$gzip_compression);
			update_option('smack_minify_css',$minify_css);
			update_option('smack_combine_css',$combine_css);
			update_option('smack_blocking_css',$blocking_css);
			update_option('smack_blocking_js',$blocking_js);
			update_option('smack_minify_js',$minify_js);
			update_option('smack_combine_js',$combine_js);
			update_option('smack_deferred_js', $defer_js);
			update_option('smack_excluded_css',$excluded_css);
			update_option('smack_excluded_js',$excluded_js);
			update_option('smack_delay_js_script',$delay_js_script);
		}
		
        if($minify_html=='true'){
			$this->optimize_html();
		}
		if($minify_css=='true'){
			$this->optimize_css();
		}
		if($minify_js=='true'){
			$js_minify=new MinifyJs;
			$js_minify->optimize_js();
		}

		if($preloading_enabled == 'true'){
			if($need_to_preload){
				$adminbar_instance = adminbarFunction::getInstance();
				$adminbar_instance->smack_clean_domain('');
				$adminbar_instance->run_smack_bot( 'cache-preload', '' );
			}
		}
        $result['deferjs_error_message']=get_option('smack_deferjs_error');
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public function send_minify_options(){
		
		$result['minify_html']=get_option('smack_minify_html')=== 'true'? true: false;
		$result['disable_google_fonts']=get_option('smack_google_fonts')=== 'true'? true: false;
		$result['combine_google_fonts']=get_option('smack_combine_google_fonts')=== 'true'? true: false;
		$result['remove_query_strings']=get_option('smack_remove_query_strings')=== 'true'? true: false;
		$result['enable_gzip_compression']=get_option('smack_gzip_compression')=== 'true'? true: false;
		$result['minify_css']=get_option('smack_minify_css')=== 'true'? true: false;
		$result['combine_css']=get_option('smack_combine_css')=== 'true'? true: false;
		$result['blocking_css']=get_option('smack_blocking_css')=== 'true'? true: false;
		$result['blocking_js']=get_option('smack_blocking_js')=== 'true'? true: false;
        $result['minify_js']=get_option('smack_minify_js')=== 'true'? true: false;
		$result['combine_js']=get_option('smack_combine_js')=== 'true'? true: false;
		$result['defer_js'] = get_option('smack_deferred_js') === 'true'? true : false;
		if(empty(get_option('smack_excluded_js'))||get_option('smack_excluded_js')=== 'false'){
			$result['exclude_files_url_js']='';
		}else{
			$excluded_js = get_option('smack_excluded_js');
			$excluded_js = str_replace('http://',"",$excluded_js);
			$result['exclude_files_url_js']= $excluded_js;
		}
		if(empty(get_option('smack_excluded_css'))||get_option('smack_excluded_css')=== 'false'){
			$result['exclude_files_url_css']='';
		}else{
			$excluded_css = get_option('smack_excluded_css');
			$excluded_css = str_replace('http://',"",$excluded_css);
			$result['exclude_files_url_css']= $excluded_css;
		}
		if(empty(get_option('smack_delay_js_script'))||get_option('smack_delay_js_script')=== 'false'){
			$result['delay_js_script']='';
		}else{
			$delay_value=get_option('smack_delay_js_script');
			$delayed_script = str_replace('http://',"",$delay_value);
			$result['delay_js_script']=$delayed_script;
		}
		$result['success'] = true;
        echo wp_json_encode($result);
        wp_die();

	}

	public function check_gzip_enabled(){
		if($_POST){
			
			if($_POST['gzip_table_enabled']=='true'){
				$response=$this->site_gzip_enabled();
				if($response=='true'){
					$result['success'] = true;
					
				}
				else{
					$result['success'] = false;
				}
				
			}else{
				$result['success'] = true;
			}
			
		
        echo wp_json_encode($result);
        wp_die();
		}

	}

	public function site_gzip_enabled(){
		$url = home_url();
		
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // follow redirects
		curl_setopt($ch, CURLOPT_HEADER, 1); // include headers in curl response
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Encoding: gzip, deflate', // request gzip
			'Accept-Language: en-US,en;q=0.5',
			'Connection: keep-alive',
			'SomeBull: BeingIgnored',
			'User-Agent: Mozilla/5.0 (Windows NT 5.1; rv:16.0) Gecko/20100101 Firefox/16.0'
		  )
		);
		$response = curl_exec($ch);
	
		if ($response === false) {
			
			die('Error fetching page: ' . curl_error($ch));
		}
		
		$info = curl_getinfo($ch);
	
		for ($i = 0; $i <= $info['redirect_count']; ++$i) {
			// split request and headers into separate vars for as many times 
			// as there were redirects
			list($headers, $response) = explode("\r\n\r\n", $response, 2);
		}
		
		curl_close($ch);
		
		$headers = explode("\r\n", $headers); // split headers into one per line
		$hasGzip = false;
		
		foreach($headers as $header) { // loop over each header
			if (stripos($header, 'Content-Encoding') !== false) { // look for a Content-Encoding header
				if (strpos($header, 'gzip') !== false) { // see if it contains gzip
					$hasGzip = true;
				}
			}
		}
		
		return $hasGzip;
		// var_dump($hasGzip);
		// echo $hasGzip;
	}

	public function optimize_html(){
		$root_dir=scandir(ABSPATH);
        foreach($root_dir as $root_files){
			$root_extension = pathinfo($root_files, PATHINFO_EXTENSION);
			if($root_extension == 'html'){
				$html_path=ABSPATH.$root_files;
				$this->minify_html($html_path);
			}
		}
	}


	public function optimize_css(){
		@wp_mkdir_p(WP_CONTENT_DIR . '/cache/smack-minify/themes/css',0755);
		// @wp_mkdir_p(WP_CONTENT_DIR . '/cache/smack-minify/plugins/woocommerce/packages/woocommerce-blocks/build/',0755);
		$sheet= get_stylesheet_uri();
		$theme_path=get_theme_file_path();
		$theme_files = scandir($theme_path);
		$smack_minify_path=WP_CONTENT_DIR.'/'.'cache'.'/'.'smack-minify'.'/'.'themes'.'/'.'css';
		foreach($theme_files as $theme_file){
			
		   $css_extension = pathinfo($theme_file, PATHINFO_EXTENSION);

		   if($css_extension == 'css'){
			   $css_path= get_theme_file_path().'/'.$theme_file;
				// $fileGet_content=file_get_contents($css_path);
			   $minify_path=$smack_minify_path.'/'.$theme_file;
			   
			  $this->minify_css($css_path,$minify_path);
		   }
		 
		   if($theme_file == 'assets'){
			   $css_files=scandir($theme_path.'/'.$theme_file);
			   foreach($css_files as $css_file){
				   if($css_file == 'css'){
						 $assets_css=scandir($theme_path.'/'.$theme_file.'/'.'css');
					   foreach($assets_css as $asset_css){
						   $css_ext = pathinfo($asset_css, PATHINFO_EXTENSION);
						   if($css_ext == 'css'){
							   $css_path= $theme_path.'/'.$theme_file.'/'.'css'.'/'.$asset_css;
							   $minified_path=$smack_minify_path.'/'.$asset_css;
							
							  $this->minify_css($css_path,$minified_path);
						   }
					   }
				   }		
			   }
			   
		   }
		   if($theme_file == 'css'){
				$css_files=scandir($theme_path.'/'.$theme_file);
				foreach($css_files as $css_file){
					$css_ext = pathinfo($css_file, PATHINFO_EXTENSION);
					if($css_ext == 'css'){
						$css_path= $theme_path.'/'.$theme_file.'/'.$css_file;
						$minified_path=$smack_minify_path.'/'.$css_file;
						
						$this->minify_css($css_path,$minified_path);
					}
							
				}
			
			}
		}
		$active_plugins = get_option('active_plugins');
		foreach ($active_plugins as $plugin_list) {
			$plugin_list = explode("/", $plugin_list);
			$plugin_name = $plugin_list[0];
			$dir = WP_PLUGIN_DIR . '/' . $plugin_name; // Assuming your plugins are in the standard WordPress plugin directory
			$plugin_files = scandir($dir);	
			foreach ($plugin_files as $plugin_file) {
				// Check if the file is an "assets" folder
				if ($plugin_file == 'assets') {
					$assets_dir = WP_PLUGIN_DIR . '/' . $plugin_name . '/' . $plugin_file;
					$assets_css = scandir($assets_dir);
					foreach ($assets_css as $asset_css) {
						$css_ext = pathinfo($asset_css, PATHINFO_EXTENSION);
						if ($css_ext == 'css') {
							$css_path = $assets_dir . '/' . $asset_css;
							$minified_path = WP_CONTENT_DIR . '/cache/smack-minify/plugins/' . $plugin_name . '/assets/' . $asset_css;
							$minify_dir = dirname($minified_path);
							if (!is_dir($minify_dir)) {
								wp_mkdir_p($minify_dir, 0755, true);
							}
							$minifier = new Minify\CSS($css_path);
							$minifier->minify($minified_path);
						}
						else if($asset_css=='css'){
							$assets_dir_sub_folder = WP_PLUGIN_DIR . '/' . $plugin_name . '/' . $plugin_file .'/' . $asset_css;
							$assets_css_sub_folder_files = scandir($assets_dir_sub_folder);
							foreach($assets_css_sub_folder_files as $assets_sub_folder_list){
								$css_ext_sub_files = pathinfo($assets_sub_folder_list, PATHINFO_EXTENSION);
								if($css_ext_sub_files=='css'){
									$css_path_sub = $assets_dir . '/' . $asset_css .'/'.$assets_sub_folder_list;
									$minified_path_sub = WP_CONTENT_DIR . '/cache/smack-minify/plugins/' . $plugin_name . '/assets/' . $asset_css .'/'.$assets_sub_folder_list ;
									$minify_dir_sub = dirname($minified_path_sub);
									if (!is_dir($minify_dir_sub)) {
										wp_mkdir_p($minify_dir_sub, 0755, true);
									}
									$minifier = new Minify\CSS($css_path_sub);
									$minifier->minify($minified_path_sub);
								}
							}
						}
					}
				}
			}
		}

	}

	public function minify_css($minify_path, $destination_path) {
		try {
			$minifier = new Minify\CSS($minify_path);
			$minifiedContent = $minifier->minify($destination_path);
			if ($minifiedContent !== false) {
				file_put_contents($destination_path, $minifiedContent);
			} else {
				error_log("Minification failed for $minify_path");
			}
		} catch (Exception $e) {
			error_log("Error during CSS minification: " . $e->getMessage());
		}
	}
	
	public function minify_html($minify_path){
	
		$minifier = new Minify\CSS($minify_path);
		$minifier->minify($minify_path);
	}

	public function _remove_script_version($src){
		$parts = explode( '?ver', $src );
		return $parts[0];
	}
	

}
$new_obj = new MinifyCss();