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


class DisableEmbed
{
    
	protected static $instance = null,$plugin;
	
	public function __construct()
	{

		$disable_embeds=get_option('smack_embeds_disable');
		if($disable_embeds == 'true'){
			 $this->disable_wordpress_embeds();
		     add_action( 'init', array($this,'disable_wordpress_embeds'), 1 );
             add_action( 'init', array($this,'remove_filter_embeds'), 1 );
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

	function disable_wordpress_embeds(){
        global $wpdb;
       
		// Removes the embed query var.
		$wpdb->public_query_vars = array_diff(
			$wpdb->public_query_vars, [
				'embed',
			]
        );
        
        // Turns off oEmbed auto discovery.
        add_filter( 'embed_oembed_discover', '__return_false' );
        
        // Removes the oembed/1.0/embed REST route.
        add_filter( 'rest_endpoints',  array($this,'remove_embed_endpoint'), 1 );

        // Removes all the embeds rewrite rules.
        add_filter( 'rewrite_rules_array', array($this,'disable_embeds_rewrite_rules'), 1 );
        
        // Removes wp-embed dependency of wp-edit-post script handle.
        add_action( 'wp_default_scripts', array($this,'embeds_remove_script_dependencies'), 1 );
       
        //disables the embeds tiny_mce plugin
        add_filter( 'tiny_mce_plugins',  array($this,'disable_embeds_tiny_plugin'), 1 );

     
        
        // Disables handling of internal embeds in oembed/1.0/proxy REST route.
		add_filter( 'oembed_response_data', array($this,'filter_oembed_response_data'), 1 );

    }

    function remove_filter_embeds(){
            // Removes oEmbed JavaScript from the front-end and back-end.
            remove_action( 'wp_head', 'wp_oembed_add_host_js' );

            // Doesn't filter oEmbed results.
            remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
        
        	// Removes oEmbed result filter  before any HTTP requests are made.
		    remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );

		    // Removes oEmbed discovery links.
		    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    }

    function remove_embed_endpoint( $endpoints ) {
        
		unset( $endpoints['/oembed/1.0/embed'] );

		return $endpoints;
	}

    function disable_embeds_rewrite_rules( $rules ) {
		if ( empty( $rules ) ) {
			return $rules;
		}

		foreach ( $rules as $rule => $rewrite ) {
			if ( false !== strpos( $rewrite, 'embed=true' ) ) {
				unset( $rules[ $rule ] );
			}
		}

		return $rules;
	}

    function embeds_remove_script_dependencies( $scripts ) {
        	if ( ! empty( $scripts->registered['wp-edit-post'] ) ) {
			$scripts->registered['wp-edit-post']->deps = array_diff(
				$scripts->registered['wp-edit-post']->deps,
				[ 'wp-embed' ]
			);
		}
    }
    

    function filter_oembed_response_data( $data ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		return $data;
    }
    
    function disable_embeds_tiny_plugin( $plugins ) {
		return array_diff( $plugins, [ 'wpembed' ] );
    }
    
    

}
$new_obj = new DisableEmbed();
