<?php
/**
 * All-in-one Performance Accelerator plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\AIOACC;
require_once(__DIR__.'/../vendor/autoload.php');

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

	
class Cloudfare
{
	protected static $instance = null,$plugin;
   
   
	public function __construct()
	{
        
	}



    function smack_cloudflare_purge() {
        $cloudflare_info=get_option('smack_cloudflare_info');
            $smack_cache = new \Cloudflare\Zone\Cache($cloudflare_info->auth );
          
            $smack_purge = $smack_cache->purge($cloudflare_info->zone_id, true );
           
            if ( ! isset( $smack_purge->success ) || empty( $smack_purge->success ) ) {
                $result_array['message']='Incorrect Cloudflare Zone ID';
                $result_array['success'] = false;
            }else{
                $result_array['success'] = true;
                $result_array['message']='Cloudflare Sucessfull';
                
            }
        echo wp_json_encode($result_array);
		wp_die();
    }
   
    function admin_smack_cloudflare_purge() {
        $cloudflare_info=get_option('smack_cloudflare_info');
            $smack_cache = new \Cloudflare\Zone\Cache($cloudflare_info->auth );
          
            $smack_purge = $smack_cache->purge($cloudflare_info->zone_id, true );
           
            if ( ! isset( $smack_purge->success ) || empty( $smack_purge->success ) ) {
                $result_array['message']='Incorrect Cloudflare Zone ID';
                $result_array['success'] = false;
            }else{
                $result_array['success'] = true;
                $result_array['message']='Cloudflare Sucessfull';
                
            }
            wp_safe_redirect( wp_get_referer() );
            die();
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
       
        add_action('wp_ajax_save_changes', array($this,'get_cloudfare_info'));
        add_action('wp_ajax_save_change', array($this,'get_cloudfare'));
        add_action('wp_ajax_save_values', array($this,'send_cloudfare_info'));
        add_action('wp_ajax_save_cloudflare_cache_options', array($this,'smack_cloudflare_purge'));
        
        
    }

    public function get_cloudfare(){
        if(isset($_POST)){
            $cloudfare_email=filter_var($_POST['emailvalues'], FILTER_SANITIZE_EMAIL);
			$cloudfare_api = sanitize_text_field($_POST['pswdValue']);
            $cloudflare_zoneid=sanitize_text_field($_POST['zoneId']);
            update_option('smack_cloudfare_email',$cloudfare_email);
            update_option('smack_cloudfare_api',$cloudfare_api);
            update_option('smack_cloudfare_zoneid',$cloudflare_zoneid);
        }
        $result_arra['success'] = false;
        $result_arra['message'] = 'Authentication Failed,Please Check Credentials';
        echo wp_json_encode($result_arra);
		wp_die();
    }

    public function get_cloudfare_info(){
       
        if(isset($_POST)){
            $cloudfare_email=filter_var($_POST['emailvalues'], FILTER_SANITIZE_EMAIL);
			$cloudfare_api = sanitize_text_field($_POST['pswdValue']);
            $cloudflare_zoneid=sanitize_text_field($_POST['zoneId']);
            update_option('smack_cloudfare_email',$cloudfare_email);
            update_option('smack_cloudfare_api',$cloudfare_api);
            update_option('smack_cloudfare_zoneid',$cloudflare_zoneid);
        }
       
       $api=new \Cloudflare\Api( $cloudfare_email, $cloudfare_api);
      
       $cf_zone         = $api->get( 'zones/' . $cloudflare_zoneid );
      
       if ( true === $cf_zone->success ) {
        $zone_found = false;
        $site_url   = get_site_url();
        if ( ! empty( $cf_zone->result ) ) {
        
            $parsed_url = wp_parse_url( $site_url );
         
            if ( false === strpos( strtolower( $parsed_url['host'] ), $cf_zone->result->name ) ) {
                $zone_found = true;
            }
            
        }
        if($zone_found == "true"){
            $cloudflare_info = (object) [
                'auth'    => $api,
                'zone_id' => $cloudflare_zoneid,
            ];
          
            update_option('smack_cloudflare_info',$cloudflare_info);
            $result_array['success'] = true;
            $result_array['message'] = 'Authentication Successfully';
        }else{
            $result_array['success'] = false;
            $result_array['message'] = 'Domain Name not matched to credentials';
        }
        
        
       
       }else{
        delete_option('smack_cloudflare_info');
        $result_array['success'] = false;
        $result_array['message'] = 'Authentication Failed,Please Check Credentials';
       }
       
       
		echo wp_json_encode($result_array);
		wp_die();
    }

    public function send_cloudfare_info(){
        if(empty(get_option('smack_cloudfare_email'))||get_option('smack_cloudfare_email')=== 'false'){
			$result['email_value']='';
		}else{
			$result['email_value']= get_option('smack_cloudfare_email');;
		}
        if(empty(get_option('smack_cloudfare_api'))||get_option('smack_cloudfare_api')=== 'false'){
			$result['pswd_value']='';
		}else{
			$result['pswd_value']= get_option('smack_cloudfare_api');;
		}
        if(empty(get_option('smack_cloudfare_zoneid'))||get_option('smack_cloudfare_zoneid')=== 'false'){
			$result['cache_value']='';
		}else{
			$result['cache_value']= get_option('smack_cloudfare_zoneid');;
		}
        $result['success']=true;
			echo wp_json_encode($result);
        wp_die(); 
    }
   

}