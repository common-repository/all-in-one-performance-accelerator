<?php
/**
 * All-in-one Performance Accelerator plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\AIOACC;

use DOMDocument;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

require_once(__DIR__.'/../classes/bufferClass.php');

class LazyImages
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
			self::$instance->doHooks();
		}
        return self::$instance;
	}
	
	public function doHooks(){
		add_action('wp_ajax_save_media_options', array($this,'media_options'));
		add_action('wp_ajax_get_media_options', array($this,'send_media_options'));
		add_action('wp_ajax_delete_orphan_images', array($this,'delete_orphan_images'));
		add_action('wp_ajax_delete_all_orphan_images', array($this,'delete_all_orphan_images'));
		add_action('wp_ajax_get_page_count', array($this,'get_page_count'));
		add_action('wp_ajax_get_media_selected_tab', array($this,'get_media_selected_tab'));
		
	}

	public function get_media_selected_tab(){
		$tab = sanitize_text_field($_POST['tab']);
		if($tab === 'undefined'){
			$tab_value = get_option('smack_media_tab');
			if(empty($tab_value)){
				$tab_name = 'lazyLoad';
				update_option('smack_media_tab',$tab_name);
			}else{
				update_option('smack_media_tab',$tab_value);
			}
		}else{
			update_option('smack_media_tab',$tab);
		}
		$tab_address = get_option('smack_media_tab');
		$result['tab'] = $tab_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public function media_options(){
		if(isset($_POST)){
			$enable_lazyload = sanitize_text_field($_POST['enable_lazy_load']);
			$disable_emoji = sanitize_text_field($_POST['disable_emoji']);
			$disable_embeds = sanitize_text_field($_POST['disable_embeds']);
			$supportWebpImages = isset($_POST['supportWebpImages']);
			$enable_lazyload_iframe = sanitize_text_field($_POST['enable_lazyload_iframe']);
			update_option('smack_lazyload_enable',$enable_lazyload);
			update_option('smack_emoji_disable',$disable_emoji);
			update_option('smack_embeds_disable',$disable_embeds);
			update_option('smack_webp_image_enable',$supportWebpImages);
			update_option('smack_lazyload_iframe_videos', $enable_lazyload_iframe);
		}
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public function send_media_options(){
		global $wpdb;
		$enable_lazyload=get_option('smack_lazyload_enable');
        $disable_emoji=get_option('smack_emoji_disable');
		$disable_embeds=get_option('smack_embeds_disable');
		$supportWebpImages=get_option('smack_webp_image_enable');
		$enable_lazyload_iframes = get_option('smack_lazyload_iframe_videos');
        $result['enable_lazyload']=$enable_lazyload=== 'true'? true: false;
		$result['disable_emoji']=$disable_emoji=== 'true'? true: false;
		$result['disable_embeds']=$disable_embeds=== 'true'? true: false;
		$result['supportWebpImages']=$supportWebpImages=== 'true'? true: false;
		$result['enable_lazyload_iframe'] = $enable_lazyload_iframes === 'true'? true: false;
        $result['success'] = true;
		
        echo wp_json_encode($result);
        wp_die();
	}

	public function get_page_count(){
		global $wpdb;
		$totalPage=get_option('total_page_count');
		$items_per_page='10';
		$page = isset($_REQUEST['cpage']) ? abs((int) $_REQUEST['cpage']) : 1;
		$offset = $page * $items_per_page - $items_per_page;
		
		$get_orphan_images= $wpdb->get_results("SELECT
        *
      FROM
      {$wpdb->prefix}posts i
      WHERE
        i.post_type = 'attachment'
        AND
        NOT EXISTS (SELECT * FROM {$wpdb->prefix}posts p WHERE p.ID = i.post_parent)
        AND
        NOT EXISTS (SELECT * FROM {$wpdb->prefix}postmeta pm WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = i.ID)
        AND
        NOT EXISTS (SELECT * FROM {$wpdb->prefix}posts p WHERE p.post_type <> 'attachment' AND p.post_content LIKE CONCAT('%',i.guid,'%'))
        AND
        NOT EXISTS (SELECT * FROM {$wpdb->prefix}postmeta pm WHERE pm.meta_value LIKE CONCAT('%',i.guid,'%'))  ORDER BY ID DESC LIMIT $offset, $items_per_page ");
	$orphan_images = [];
	$temp = 0;

	foreach($get_orphan_images as $images){
		$orphan_images[$temp]['post_author'] = $wpdb->get_var("SELECT user_login FROM {$wpdb->prefix}users  WHERE ID = $images->post_author ");
		$orphan_images[$temp]['guid'] = $images->guid;
		$post_date = $images->post_date;
		$orphan_images[$temp]['post_date'] = date( "Y/m/d", strtotime($post_date) );
		$orphan_images[$temp]['post_title'] = $images->post_title;
		$temp++;
	}
		echo wp_json_encode([
            'response' => [
				'orphan_images'=>$orphan_images,
                'total_page' => $totalPage,
            ],
            'status' => 200,
            'success' => true,
        ]);
        wp_die();
    }

	public function EnableLazyLoad(){
		add_action( __CLASS__, 'my_admin_scripts' );
    }
    function my_admin_scripts() {
		wp_register_script('iframe-lazyload-script', plugins_url('../assets/js/Iframe.js', __FILE__), array('jquery'),'',true);
        wp_enqueue_script('iframe-lazyload-		script');
	}
    
    public function maybe_init_process() {
		global $wp;
		$home_url = home_url();
		if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'){
			$url = "https://";
		}   
		else{ 
			$url = "http://";  
		} 
		$screen = $url.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		if( $screen === $home_url.'/'){
			ob_start( [ $this, 'maybe_process_buffer' ] );
		}
		else{
			
		}
    }
    
    public function maybe_process_buffer( $buffer ) {
		if ( ! $this->buffer_instance->is_html( $buffer ) ) {
			return $buffer;
		}
		
		if(get_option('smack_lazyload_enable') == 'true'){
			$buffer = $this->smack_lazy_img($buffer);
		}
		if(get_option('smack_lazyload_iframe_videos') == 'true'){
			$buffer = $this->smack_lazy_ifra($buffer);
		}

		return $buffer;
    }
    
	public function smack_lazy_img( $buffer ) {
		$clean_buffer = preg_replace( '/<!--(.*)-->/Uis', '', $buffer );
		preg_match_all('#<img(?<atts>\s.+)\s?/?>#iUs', $clean_buffer, $images);
	
		if ( ! isset( $images[0] ) ) {
			return $buffer;
		}
	
		foreach ( $images[0] as $i => $img ) {
			// if(is_array($img)){
			// 	if (!in_array("data-src", $img)){
					$lazy = ["src", "<img"];
					$_img   = ["data-src", '<img loading="lazy"'];
					$lazy_img = str_replace( $lazy, $_img, $img );
		
					$lazy = ["<img", "/>"];
					$_img   = ["<noscript><img", '></noscript>'];
					$lazy_img .= str_replace( $lazy, $_img, $img );
					$buffer  = str_replace( $img, $lazy_img, $buffer );
			// 	}
			// }
			
		} 
		return $buffer;
	}

    public function smack_lazy_ifra( $buffer ) {
		$clean_buffer = preg_replace( '/<!--(.*)-->/Uis', '', $buffer );
		preg_match_all( '@<iframe(?<atts>\s.+)>.*</iframe>@iUs', $clean_buffer , $iframes);

		if ( ! isset( $iframes[0] ) ) {
            return $buffer;
		}
		
		foreach ( $iframes[0] as $i => $ifra ) {
            $lazy = ["src", "<iframe"];
            $_ifra   = ["data-src", '<iframe loading="lazy"'];
            $lazy_ifra = str_replace( $lazy, $_ifra, $ifra );
			
			$buffer  = str_replace( $ifra, $lazy_ifra, $buffer );
		} 
        return $buffer;
	}

	public function delete_orphan_images(){
		if(isset($_POST)){
			global $wpdb;
			$orphan_images = sanitize_text_field($_POST['orphan_guid']);
			$post_id=$wpdb->get_var("SELECT ID FROM {$wpdb->prefix}posts  WHERE guid ='$orphan_images' ");
			wp_delete_post($post_id);
			
		}
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public function delete_all_orphan_images(){
		global $wpdb;
		$get_orphan_images= $wpdb->get_results("SELECT
        *
      FROM
      {$wpdb->prefix}posts i
      WHERE
        i.post_type = 'attachment'
        AND
        NOT EXISTS (SELECT * FROM {$wpdb->prefix}posts p WHERE p.ID = i.post_parent)
        AND
        NOT EXISTS (SELECT * FROM {$wpdb->prefix}postmeta pm WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = i.ID)
        AND
        NOT EXISTS (SELECT * FROM {$wpdb->prefix}posts p WHERE p.post_type <> 'attachment' AND p.post_content LIKE CONCAT('%',i.guid,'%'))
        AND
        NOT EXISTS (SELECT * FROM {$wpdb->prefix}postmeta pm WHERE pm.meta_value LIKE CONCAT('%',i.guid,'%'))");
		foreach($get_orphan_images as $images){
			$post_id= $images->ID;
			wp_delete_post($post_id);
			
		}
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}
}
