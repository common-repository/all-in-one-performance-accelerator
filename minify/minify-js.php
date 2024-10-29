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

class MinifyJs
{
	protected static $instance = null,$plugin;
	
	

    
	public function __construct()
	{
		$combine_js=get_option('smack_combine_js');
		$minify_js=get_option('smack_minify_js');
		if($combine_js == 'false' && $minify_js=='true' ){
			add_filter( 'script_loader_tag', array($this,'add_id_to_script'), 10, 3);
		}
		
		
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

	public function optimize_js(){
		@wp_mkdir_p(WP_CONTENT_DIR . '/cache/smack-minify/themes/js',0755);
		@wp_mkdir_p(WP_CONTENT_DIR . '/cache/smack-minify/plugins/woocommerce/js',0755);
		$theme_path=get_theme_file_path();
		$files = scandir($theme_path);
		
		
		foreach($files as $file){

		

			if($file == 'js' || $file == 'assets' || $file == 'includes' ){
			   $js_files=scandir($theme_path.'/'.$file);

		   		foreach($js_files as $js_file){
				
					$ext = pathinfo($js_file, PATHINFO_EXTENSION);
					if($ext == 'js'){

					   $js_path= get_theme_file_path().'/'.$file.'/'.$js_file;
					   $smack_minify_path=WP_CONTENT_DIR.'/'.'cache'.'/'.'smack-minify'.'/'.'themes'.'/'.'js/'.$js_file;
					   $this->minify_js($js_path,$smack_minify_path);
				   	}else{
						
					   if($js_file == 'js' ){
						   $assets_js=scandir($theme_path.'/'.$file.'/'.'js');
						   foreach($assets_js as $asset_js){
								$js_ext = pathinfo($asset_js, PATHINFO_EXTENSION);
						   		if($js_ext == 'js'){
									   $js_path= $theme_path.'/'.$file.'/'.'js'.'/'.$asset_js;
									   $smack_minify_path=WP_CONTENT_DIR.'/'.'cache'.'/'.'smack-minify'.'/'.'themes'.'/'.'js/'.$asset_js;
									   $this->minify_js($js_path,$smack_minify_path);
						   		}
					  		 }
						   }
						   elseif($js_file == 'custom' ){
							$Custom_path=scandir(get_theme_file_path().'/'.'js'.'/'.'custom') ;
							@wp_mkdir_p(WP_CONTENT_DIR . '/cache/smack-minify/themes/js/custom');
							foreach($Custom_path as $custom_js){
								
								$js_ext = pathinfo($custom_js, PATHINFO_EXTENSION);
						   		 if($js_ext == 'js'){
									 $js_path= $theme_path.'/'.'js'.'/'.'custom'.'/'.$custom_js;
									 $smack_minify_path=WP_CONTENT_DIR.'/'.'cache'.'/'.'smack-minify'.'/'.'themes'.'/'.'js/'.'custom'.'/'.$custom_js;
								 	$this->minify_js($js_path,$smack_minify_path);
								    }
							}	
						   }
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
						if ($css_ext == 'js') {
							$css_path = $assets_dir . '/' . $asset_css;
							$minified_path = WP_CONTENT_DIR . '/cache/smack-minify/plugins/' . $plugin_name . '/assets/' . $asset_css;
							$minify_dir = dirname($minified_path);
							if (!is_dir($minify_dir)) {
								wp_mkdir_p($minify_dir, 0755, true);
							}
							$minifier = new Minify\CSS($css_path);
							$minifier->minify($minified_path);
						}
						else if($asset_css=='js'){
							$assets_dir_sub_folder = WP_PLUGIN_DIR . '/' . $plugin_name . '/' . $plugin_file .'/' . $asset_css;
							$assets_css_sub_folder_files = scandir($assets_dir_sub_folder);
							foreach($assets_css_sub_folder_files as $assets_sub_folder_list){
								$css_ext_sub_files = pathinfo($assets_sub_folder_list, PATHINFO_EXTENSION);
								if($css_ext_sub_files=='js'){
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

	public static function add_id_to_script( $tag, $handle, $src ) {
		
		if(strpos($src, 'woocommerce') !== false) {
		if(strpos($src, 'frontend') !== false) {
			$plugins = explode('wp-content',$src);
			$plugin_url = $plugins[1];
			$plugin_path = substr($plugin_url, 1);
			$excluded = explode('frontend',$plugin_path);
			if (strpos($src, '.min') == false) {
				$frontend_path=$excluded[0].'frontend';
				$modified="cache/smack-minify/plugins/woocommerce/js";
		   $stylesheet=str_replace($frontend_path,$modified,$src);
		 $tag=str_replace($src, $stylesheet, $tag);
			}
		}
	}
		$theme  = wp_get_theme();
		$active_theme  = get_stylesheet_directory_uri();
		$themes = explode($active_theme, $src);
        if(!empty($themes[1])){
		$theme_url = $themes[1];
		if($theme_url){
			$themes = explode('wp-content',$src);
			$theme_url = $themes[1];
			$theme_path = substr($theme_url, 1);
			
			if(strpos($theme_path, 'build') !== false) {
				return $tag;
			}
			if(strpos($theme_path, 'assets') !== false) {
				$excluded = explode('js',$theme_path);
				$js_path=$excluded[0].'js';
			}elseif(strpos($theme_path, 'js/') !== false){
				if(strpos($theme_path, 'assets') == false) {
				$excluded = explode('js',$theme_path);
				$js_path=$excluded[0].'js';
				}
			}else{
				$excluded = explode($theme->stylesheet,$theme_path);
				$js_path=$excluded[0].$theme->stylesheet;
				
			}
			
			$excluded_js=get_option('smack_excluded_js');
		$exclude_urls = explode(',',$excluded_js);
		foreach($exclude_urls as $exclude_url){
			
		
			$exclude[]=site_url().'/'.$exclude_url;
			
		}
		$without_versions = explode('?',$src);	
			if (!in_array($without_versions[0],$exclude)){	
				
				if (strpos($src, '.min') == false) {
					
				$src=str_replace('defer','',$src);
				$modified="cache/smack-minify/themes/js";
				$stylesheet=str_replace($js_path,$modified,$src);
			    $tag=str_replace($src, $stylesheet, $tag);
				}
			}
		}
		}
		
		   
		
		
		return $tag;
	}


	public function minify_js($minify_path,$destinated_path){
		$jsfilegetcontent=file_get_contents($minify_path);
		if (strpos($minify_path, '.min') == false) {
			
			$minifier = new Minify\CSS($minify_path);
			$minifier->minify($destinated_path);
		}
		
	}
	


}
$new_obj = new MinifyJs();