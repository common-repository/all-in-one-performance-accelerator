<?php
/**
 * All-in-one Performance Accelerator plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\AIOACC;
require_once(__DIR__.'/../vendor/autoload.php');

use ArtisansWeb\Optimizer;


class ImageOptimization
{
	protected static $instance = null,$plugin;
	public $is_image;
	protected $editors = array();
	public function __construct()
	{
		if ( ! defined( 'SMACK_FS_CHMOD_FILE' ) ) {
			define( 'SMACK_FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
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
        add_action('wp_ajax_save_image_optimization_options', array($this,'compress_image'));
		add_action('wp_ajax_get_image_optimization_options', array($this,'get_image_options'));
		add_action('wp_ajax_get_image_selected_tab', array($this,'get_image_selected_tab'));
		add_action('wp_ajax_get_processed_image_options', array($this,'get_processing_records'));
		add_action('wp_ajax_get_maximum_image_size',array($this,'maximum_image_width'));
		add_filter('wp_get_attachment_url',array(__CLASS__, 'do_media_url_modification'), 10, 5);
	}
	
	public function get_image_selected_tab(){
		$tab = sanitize_text_field($_POST['tab']);
		if($tab === 'undefined'){
			$tab_value = get_option('smack_image_tab');
			if(empty($tab_value)){
				$tab_name = 'compressimages';
				update_option('smack_image_tab',$tab_name);
			}else{
				update_option('smack_image_tab',$tab_value);
			}
		}else{
			update_option('smack_image_tab',$tab);
		}
		$tab_address = get_option('smack_image_tab');
		$result['tab'] = $tab_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public static function get_image_options(){
		$result['compress_image']=get_option('smack_compress_image')=== 'true'? true: false;
		$result['auto_compress_images']=get_option('smack_auto_compress_images')=== 'true'? true: false;
		$result['compress_different_images']=get_option('compress_different_images');
		$result['resize_image']=get_option('smack_resize_image')=== 'true'? true: false;
		$result['image_max_width']=(int)get_option('smack_image_max_width');
		$result['percentage_saved']=(int)get_option('smack_percentage_saved');
		$result['size_saved']=get_option('smack_size_saved');
		$result['total_size_before']=get_option('smack_total_size_before');
		$result['total_size_after']=get_option('smack_total_size_after');
		$result['max_width_sizes']=(int)get_option('smack_image_maxwidth_size');
		$result['image_sizes'] = get_option('smack_image_sizes') === 'true' ? true : false;
		echo wp_json_encode($result);
        wp_die();  

	}

	public static function get_processing_records(){
		$stop_processing=get_option('smack_stop_compression');
		$total_records=(int)get_option('smack_total_images');
		$processing_records=(int)get_option('smack_image_processed');
		$skipped_images=(int)get_option('smack_skipped_images');
		$skipped_error_message=get_option('smack_skipped_image_error');
		$result['total_records']=$total_records;
		$result['processing_records']=$processing_records;
		$result['skipped_records']=$skipped_images;
		$result['skipped_error_message']=$skipped_error_message;
		if($stop_processing =='true'){
			delete_option('smack_image_processed');
			$result['success']=true;
			echo wp_json_encode($result);
        wp_die(); 
			
		}
		if($total_records == $processing_records){
			$result['success']=true;
			delete_option('smack_image_processed');
			delete_option('smack_skipped_images');
		}else{
			$result['success']=false;
		}
		echo wp_json_encode($result);
        wp_die(); 
	}



	public static function compress_image(){	
		if(isset($_POST)){
			$stop_processing = sanitize_text_field($_POST['stop_processing_records']);	
			$compress_image = sanitize_text_field($_POST['compress_images']);
			$auto_compress_images = sanitize_text_field($_POST['auto_compress_images']);
			$compress_different_images=str_replace( "\\", "", sanitize_text_field($_POST['compress_different_images']));
			$compress_different_images=json_decode($compress_different_images,true);
			$sizes=array_column($compress_different_images,'value');
			$resize_image = sanitize_text_field($_POST['resize_image']);
			$image_max_width = intval($_POST['image_max_width']);
			$image_sizes = sanitize_text_field($_POST['image_sizes']);
			update_option('smack_compress_image',$compress_image);
			update_option('smack_stop_compression',$stop_processing);
			update_option('smack_auto_compress_images',$auto_compress_images);
			update_option('compress_different_images',$compress_different_images);
			update_option('smack_resize_image',$resize_image);
			update_option('smack_image_max_width',$image_max_width);
			update_option('smack_image_sizes',$image_sizes);
		}
		if($compress_image=='true'){
			$query_images_args = array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => - 1,
			);
			$query_images = new \WP_Query( $query_images_args );
			$uploads=wp_get_upload_dir();
			$images = array();
			$custom_image = [];
			foreach ( $query_images->posts as $image ) {
				$images[] = get_attached_file( $image->ID,$unfiltered = false );		
				
				//self::image_resize($images);
				$attachment_metadata = wp_get_attachment_metadata( $image->ID );
				if(!empty($attachment_metadata['sizes'])){
					foreach($attachment_metadata['sizes'] as $size_key=>$size_values){
						
						if($resize_image === 'true' && $image_max_width > 150){
							$_width = $size_values['width'];
							if($_width < $image_max_width ){
								$sizes=self::get_image_maximum_intermediate_image_size();
								$size_names=array_keys($sizes['width']);					
								if(in_array($size_key,$size_names)){
									
									if(!empty($attachment_metadata['sizes'][$size_key]['file'])){
										$images_path = get_attached_file( $image->ID,$unfiltered = false );
										$dir = pathinfo( $images_path, PATHINFO_DIRNAME );
										$custom_image[]=$dir. '/' . $attachment_metadata['sizes'][$size_key]['file'];
									}
								}
							}
						}
						else{
							if(in_array($size_key,$sizes)){
								
								if(!empty($attachment_metadata['sizes'][$size_key]['file'])){
									$images_path = get_attached_file( $image->ID,$unfiltered = false );
									$dir = pathinfo( $images_path, PATHINFO_DIRNAME );
									$custom_image[]=$dir. '/' . $attachment_metadata['sizes'][$size_key]['file'];
								}
							}
						}
					}
				}	
			}
			$image_source=array_merge($images,$custom_image);
			$image_count=count($image_source);
			update_option('smack_total_images',$image_count);
			$total_size_before = 0;
			$total_size_after= 0;
			$total_size_diff= 0;
			$i=0;
			foreach($image_source as $image_path){
				$stop_processing=get_option('smack_stop_compression');
				$file_name=basename($image_path);
				$suffix='-smack_optimize';
				$dir = pathinfo( $image_path, PATHINFO_DIRNAME );
				$ext = pathinfo( $image_path, PATHINFO_EXTENSION );
				$name    = wp_basename($image_path, ".$ext" );
				$optimized_file=$name.$suffix.'.'.$ext;
				$img = new Optimizer();
				if (file_exists($image_path)){
					$size_before = filesize($image_path);
				}
				else{
					$size_before = 0;
				}
				$total_size_before += $size_before;
				// $uploads=wp_get_upload_dir();
				// $optimized_dir=$uploads['path'];
				$target_path=$dir.'/'.$optimized_file;
				if (!file_exists($target_path)){
					
					$img->optimize($image_path,$target_path);
				}
				if(file_exists($target_path)){
					$size_after = filesize($target_path);
				}else{
					$size_after = 0;
				}
				$total_size_after += $size_after;
				$size_diff = $size_before - $size_after;
				$total_size_diff += $size_diff;
				$i++;
				update_option('smack_image_processed',$i);
				if($stop_processing=='true'){
		 			break;
				update_option('smack_image_processed','0');	
		
				}
			
			}
			$size_percent = 100 * $total_size_diff / ($total_size_before + 0.000001);
			$html = '<p>Saved: <b>' . size_format($total_size_diff) . '</b> ( ' . round($size_percent) . '% )<br>';
			$html .= 'Size Before: <i>' . size_format($total_size_before, 2) . '</i><br>';
			$html .= 'Size now: <i>' . size_format($total_size_after, 2) . '</i><br>';
			$html .= count($images) . ' images reduced<br></p>';
			$sizes=self::get_image_maximum_intermediate_image_size();
			update_option('smack_percentage_saved',round($size_percent));
			update_option('smack_size_saved',size_format($total_size_diff));
			update_option('smack_total_size_before',size_format($total_size_before, 2));
			update_option('smack_total_size_after',size_format($total_size_after, 2));
			update_option('smack_image_maxwidth_size',$sizes['height']);
		}else{
			delete_option('smack_total_images');
			delete_option('smack_skipped_images');
			delete_option('smack_image_processed');
			delete_option('smack_percentage_saved');
			delete_option('smack_size_saved');
			delete_option('smack_total_size_before');
			delete_option('smack_total_size_after');
			delete_option('smack_image_maxwidth_size');
		}
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public static function maximum_image_width (){
		$query_images_args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => - 1,
		);
		$query_images = new \WP_Query( $query_images_args );
		$images = array();
		$maximum_width = 0;
		foreach ( $query_images->posts as $image ) {
			$images[] = get_attached_file( $image->ID,$unfiltered = false );		
			$attachment_metadata = wp_get_attachment_metadata( $image->ID );
			$maxi_width[] = $attachment_metadata['width'];
			$maximum_width = max($maxi_width);
		}
		$result['maximum_image_width'] = $maximum_width;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public static function get_image_maximum_intermediate_image_size() {
		$width  = 0;
		$height = 0;
		$limit  = 9999;
		$width= self::get_image_thumbnail_sizes();
		foreach ($width  as $_size ) {
			if ( $_size['width'] > $width && $_size['width'] < $limit ) {
				$width = $_size['width'];
			}
	
			if ( $_size['height'] > $height && $_size['height'] < $limit ) {
				$height = $_size['height'];
			}
		}
	
		return array(
			'width'  => $width,
			'height' => $height,
		);
	}

	public static function get_image_thumbnail_sizes() {
		// All image size names.
		$intermediate_image_sizes = get_intermediate_image_sizes();
		$intermediate_image_sizes = array_flip( $intermediate_image_sizes );
		// Additional image size attributes.
		$additional_image_sizes   = wp_get_additional_image_sizes();
	
		// Create the full array with sizes and crop info.
		foreach ( $intermediate_image_sizes as $size_name => $s ) {
			$intermediate_image_sizes[ $size_name ] = array(
				'width'  => '',
				'height' => '',
				'crop'   => false,
				'name'   => $size_name,
			);
	
			if ( isset( $additional_image_sizes[ $size_name ]['width'] ) ) {
				// For theme-added sizes.
				$intermediate_image_sizes[ $size_name ]['width'] = (int) $additional_image_sizes[ $size_name ]['width'];
			} else {
				// For default sizes set in options.
				$intermediate_image_sizes[ $size_name ]['width'] = (int) get_option( "{$size_name}_size_w" );
			}
	
			if ( isset( $additional_image_sizes[ $size_name ]['height'] ) ) {
				// For theme-added sizes.
				$intermediate_image_sizes[ $size_name ]['height'] = (int) $additional_image_sizes[ $size_name ]['height'];
			} else {
				// For default sizes set in options.
				$intermediate_image_sizes[ $size_name ]['height'] = (int) get_option( "{$size_name}_size_h" );
			}
	
			if ( isset( $additional_image_sizes[ $size_name ]['crop'] ) ) {
				// For theme-added sizes.
				$intermediate_image_sizes[ $size_name ]['crop'] = (int) $additional_image_sizes[ $size_name ]['crop'];
			} else {
				// For default sizes set in options.
				$intermediate_image_sizes[ $size_name ]['crop'] = (int) get_option( "{$size_name}_crop" );
			}
		}
	
		return $intermediate_image_sizes;
	}

	public static function do_media_url_modification($attachment_url){
		$compress_image=get_option('smack_compress_image');
		if($compress_image == 'true'){
	    $id = self::get_attachment_id_from_url($attachment_url);
		$mime_type = get_post_mime_type($id);
		$mime_type_explode = explode('/', $mime_type);
		$extension = $mime_type_explode[0];
		if ($extension == 'image') {
			$upload_directory = wp_upload_dir();
			$file_name=basename($attachment_url);
			$path=wp_get_original_image_path($id,$unfiltered = false );
			$suffix='-smack_optimize';
			$dir = pathinfo( $path, PATHINFO_DIRNAME );
			$ext = pathinfo( $path, PATHINFO_EXTENSION );
			$name    = wp_basename($path, ".$ext" );
			$optimized_file=$name.$suffix.'.'.$ext;
			$sub_dir=$upload_directory['subdir'];
			$optimized='/smack_optimized_images';
			$attachment_path = str_replace($file_name, $optimized_file, $path);
			if(file_exists($attachment_path)){
				$attachment_url = str_replace($file_name, $optimized_file, $attachment_url);
				
			}
			
			
		
		}
	}
	return $attachment_url;
	}

	public static function get_attachment_id_from_url($attachment_url) {
		global $wpdb;
		$attachment_id = false;
		if ( '' == $attachment_url )
		return;
		$upload_dir_paths = wp_upload_dir();
		if ( false !== strpos( $attachment_url, $upload_dir_paths['baseurl'] ) ) {
		$attachment_url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $attachment_url );
		$attachment_url = str_replace( $upload_dir_paths['baseurl'] . '/', '', $attachment_url );
		$attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = '_wp_attached_file' AND wpostmeta.meta_value = '$attachment_url' AND wposts.post_type = 'attachment'" ) );
		}
		return $attachment_id;
  	}

  	public static function image_resize( $attachment_path ) {
	
		$is_image='true';
		if ( ! $is_image ) {
			return false;
		}

		$do_resize       =get_option('smack_resize_image');
		$resize_width    = get_option('smack_image_max_width');
		foreach($attachment_path as $path){
			$attachment_size[] = self::get_image_size($path);
			
		}
		foreach($attachment_size as $sizes){
			if ( ! $do_resize || ! $attachment_size || $resize_width >= $sizes['width'] ) {
				
				return false;
			}
			
			$resized_attachment_path = self::resize( $attachment_path, $sizes, $resize_width );
			if ( is_wp_error( $resized_attachment_path ) ) {
				return false;
			}
			//self::$instance->move( $resized_attachment_path, $attachment_path, true );
		}
	}

	public static function get_image_size( $file_path ) {
	
		if ( ! $file_path ) {
			return array();
		}
		
		$size = @getimagesize($file_path);
		if ( ! $size || ! isset( $size[0], $size[1] ) ) {
			return array();
		}

		return array(
			0          => (int) $size[0],
			1          => (int) $size[1],
			'width'    => (int) $size[0],
			'height'   => (int) $size[1],
			'type'     => (int) $size[2],
			'attr'     => $size[3],
			'channels' => isset( $size['channels'] ) ? (int) $size['channels'] : null,
			'bits'     => isset( $size['bits'] )     ? (int) $size['bits']     : null,
			'mime'     => $size['mime'],
		);
	
	}

	public static function compress_image_on_upload($file_path) {
		$compress_image=get_option('smack_auto_compress_images');
		
		if($compress_image == 'true'){
			$file_name=basename($file_path);
		$suffix='-smack_optimize';
		$dir = pathinfo( $file_path, PATHINFO_DIRNAME );
		$ext = pathinfo( $file_path, PATHINFO_EXTENSION );
		$name    = wp_basename($file_path, ".$ext" );
		$optimized_file=$name.$suffix.'.'.$ext;
		$img = new Optimizer();
		$uploads=wp_get_upload_dir();
		$optimized_dir=$uploads['path'];
		$target_path=$optimized_dir.'/'.$optimized_file;
	
		$img->optimize($file_path,$target_path);
		}
		
	}

	public static function resize( $attachment_path, $attachment_sizes, $max_width ) {
		foreach($attachment_path as $paths){
			$editor = self::get_editor_type( $paths );
			
			if ( is_wp_error( $editor ) ) {
				return $editor;
			}
			$new_sizes  = wp_constrain_dimensions( $attachment_sizes['width'], $attachment_sizes['height'], $max_width );
			$image_type = strtolower( (string) self::$instance->path_info( $paths, 'extension' ) );
			if (self::$instance->can_get_exif() && ( 'jpg' === $image_type || 'jpe' === $image_type || 'jpeg' === $image_type ) ) {
				$exif        = self::$instance->get_image_exif( $paths );
				$orientation = isset( $exif['Orientation'] ) ? (int) $exif['Orientation'] : 1;
		
				switch ( $orientation ) {
					case 3:
						$editor->rotate( 180 );
						break;
					case 6:
						$editor->rotate( -90 );
						break;
					case 8:
						$editor->rotate( 90 );
				}
			}
			add_filter( 'image_strip_meta', '__return_false', 789 );
			
		$resized = $editor->resize( $new_sizes[0], $new_sizes[1], false );
		
		// Remove the filter when we're done to prevent any conflict.
		remove_filter( 'image_strip_meta', '__return_false', 789 );
		$resized_image_path  = $editor->generate_filename( 'smack_optimize' );
		if ( is_wp_error( $resized ) ) {
			return $resized;
		}
			
		$resized_image_saved = $editor->save( $resized_image_path );

		if ( is_wp_error( $resized_image_saved ) ) {
			return $resized_image_saved;
		}
		
		}
		
		return $resized_image_path;
	}

	public static function get_editor_type( $path ) {
	
		if ( isset(self::$instance->editors[ $path ] ) ) {
			
			return self::$instance->editors[ $path ];
		}
		
		self::$instance->editors[ $path ] = wp_get_image_editor($path, array(
			'methods' => self::get_editor_methods(),
		) );
		return self::$instance->editors[ $path ];
	}

	public static function get_editor_methods() {

		static $methods;

		if ( isset( $methods ) ) {
			return $methods;
		}

		$methods = array(
			'resize',
			'multi_resize',
			'generate_filename',
			'save',
		);

		if (self::$instance->can_get_exif() ) {
			$methods[] = 'rotate';
		}
		
		return $methods;
	}

	public function can_get_exif() {
		static $callable;

		if ( ! isset( $callable ) ) {
			$callable = is_callable( 'exif_read_data' );
		}

		return $callable;
	}

	public function path_info( $file_path, $option = null ) {
		if ( ! $file_path ) {
			if ( isset( $option ) ) {
				return '';
			}

			return array(
				'dir_path'  => '',
				'file_name' => '',
				'extension' => null,
				'file_base' => '',
			);
		}

		if ( isset( $option ) ) {
			$options = array(
				'dir_path'  => PATHINFO_DIRNAME,
				'file_name' => PATHINFO_BASENAME,
				'extension' => PATHINFO_EXTENSION,
				'file_base' => PATHINFO_FILENAME,
			);

			if ( ! isset( $options[ $option ] ) ) {
				return '';
			}

			$output = pathinfo( $file_path, $options[ $option ] );

			if ( 'dir_path' !== $option ) {
				return $output;
			}

			return self::$instance->is_root( $output ) ? self::$instance->get_root() : trailingslashit( $output );
		}

		$output = pathinfo( $file_path );

		$output['dirname']   = self::$instance->is_root( $output['dirname'] ) ? self::$instance->get_root()    : trailingslashit( $output['dirname'] );
		$output['extension'] = isset( $output['extension'] )        ? $output['extension'] : null;

		// '/www/htdocs/inc/lib.inc.php'
		return array(
			'dir_path'  => $output['dirname'],   // '/www/htdocs/inc/'
			'file_name' => $output['basename'],  // 'lib.inc.php'
			'extension' => $output['extension'], // 'php'
			'file_base' => $output['filename'],  // 'lib.inc'
		);
	}

	public function is_root( $path ) {
		$path = rtrim( $path, '/\\' );
		return '.' === $path || '' === $path || preg_match( '@^.:$@', $path );
	}

	public function get_root() {
		static $groot;

		if ( isset( $groot ) ) {
			return $groot;
		}

		$groot = preg_replace( '@^((?:.:)?/+).*@', '$1', self::$instance->get_site_root() );

		return $groot;
	}

	public function get_site_root() {
			static $root_path;

			if ( isset( $root_path ) ) {
				return $root_path;
			}
		$root_path = apply_filters( 'imagify_site_root', null );

		if ( is_string( $root_path ) ) {
			$root_path = trailingslashit( wp_normalize_path( $root_path ) );

			return $root_path;
		}

		$home    = set_url_scheme( untrailingslashit( get_option( 'home' ) ), 'http' );
		$siteurl = set_url_scheme( untrailingslashit( get_option( 'siteurl' ) ), 'http' );

		if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) ) {
			$wp_path_rel_to_home = str_ireplace( $home, '', $siteurl ); /* $siteurl - $home */
			$pos                 = strripos( str_replace( '\\', '/', ABSPATH ), trailingslashit( $wp_path_rel_to_home ) );
			$root_path           = substr( ABSPATH, 0, $pos );
			$root_path           = trailingslashit( wp_normalize_path( $root_path ) );
			return $root_path;
		}

		if ( ! defined( 'PATH_CURRENT_SITE' ) || ! is_multisite() || is_main_site() ) {
			$root_path = self::$instance->get_abspath();
			return $root_path;
		}

		
		$document_root     = realpath( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ); // `realpath()` is needed for those cases where $_SERVER['DOCUMENT_ROOT'] is totally different from ABSPATH.
		$document_root     = trailingslashit( str_replace( '\\', '/', $document_root ) );
		$path_current_site = trim( str_replace( '\\', '/', PATH_CURRENT_SITE ), '/' );
		$root_path         = trailingslashit( wp_normalize_path( $document_root . $path_current_site ) );

		return $root_path;
	}

	public function get_abspath() {
		static $abspath;

		if ( isset( $abspath ) ) {
			return $abspath;
		}

		$abspath = wp_normalize_path( ABSPATH );

		// Make sure ABSPATH is not messed up: it could be defined as a relative path for example (yeah, I know, but we've seen it).
		$test_file = wp_normalize_path( IMAGIFY_FILE );
		$pos       = strpos( $test_file, $abspath );

		if ( $pos > 0 ) {
			// ABSPATH has a wrong value.
			$abspath = substr( $test_file, 0, $pos ) . $abspath;

		} elseif ( false === $pos && class_exists( 'ReflectionClass' ) ) {
			// Imagify is symlinked (dude, you look for trouble).
			$reflector = new ReflectionClass( 'WP' );
			$test_file = $reflector->getFileName();
			$pos       =  strpos( $test_file, $abspath );

			if ( 0 < $pos ) {
				// ABSPATH has a wrong value.
				$abspath = substr( $test_file, 0, $pos ) . $abspath;
			}
		}

		$abspath = trailingslashit( $abspath );

		if ( '/' !== substr( $abspath, 0, 1 ) && ':' !== substr( $abspath, 1, 1 ) ) {
			$abspath = '/' . $abspath;
		}

		return $abspath;
	}

	public function get_image_exif( $file_path, $sections = null, $arrays = false, $thumbnail = false ) {
		if ( ! $file_path || ! $this->can_get_exif() ) {
			return array();
		}

		$exif = @exif_read_data( $file_path, $sections, $arrays, $thumbnail );

		return is_array( $exif ) ? $exif : array();
	}

	public static function move( $source, $destination, $overwrite = false ) {
		if ( ImageOptimization::move( $source, $destination, $overwrite ) ) {
			return self::$instance->chmod_file( $destination );
		}

		if ( ! self::$instance->chmod_file( $destination ) ) {
			return false;
		}

		if ( ImageOptimization::move( $source, $destination, $overwrite ) ) {
			return self::$instance->chmod_file( $destination );
		}

		return false;
	}

	public function chmod_file( $file_path ) {
		if ( ! $file_path ) {
			return false;
		}

		return chmod( $file_path, FS_CHMOD_FILE );
	}

	
}
