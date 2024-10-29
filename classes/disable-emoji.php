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

class DisableEmoji
{
    
	protected static $instance = null,$plugin;
	
	public function __construct()
	{
		$disable_emoji=get_option('smack_emoji_disable');
		if($disable_emoji=='true'){
			
			add_action( 'init', array($this,'ultimate_disable_emoji'), 1 );
			add_filter( 'tiny_mce_plugins',array($this,'ultimate_disable_emoji_plugin'));
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

	function ultimate_disable_emoji() {
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
       	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		add_filter( 'emoji_svg_url', '__return_false' );
		//remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	
	}

    function ultimate_disable_emoji_plugin( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, array( 'wpemoji' ) );
		}

		return array();
	}

}
$new_obj = new DisableEmoji();
