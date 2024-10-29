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

class HearbeatControl
{
    
	protected static $instance = null,$plugin;
	
	public function __construct()
	{
		
   // $this->disable_heartbeat();
    add_action( 'heartbeat_settings', array($this,'control_heartbeat_settings'), 1 );
    //add_action( 'init', array($this,'stop_heartbeat'), 1 );
    add_filter( 'heartbeat_settings', array($this,'auto_start_heartbeat_settings'),1 );
    
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
        add_action('wp_ajax_save_heart_beart_options', array($this,'disable_heartbeat'));
        add_action('wp_ajax_get_heart_beart_options', array($this,'heartbeat_options'));
	}

	public function disable_heartbeat(){

        if(isset($_POST)){
            $disable_heart_beat=sanitize_text_field($_POST['disable_heart_beat']);
            $disable_auto_start=sanitize_text_field($_POST['disable_auto_start_heart_beat']);
            $heart_beat_frequency=sanitize_text_field($_POST['control_heart_beat_frequency']);
            update_option('disable_heart_beat',$disable_heart_beat);
            update_option('disable_auto_start_heart_beat',$disable_auto_start);
            update_option('control_heart_beat_frequency',$heart_beat_frequency);
        }
        
       
          
        $result['success'] = true;
            echo wp_json_encode($result);
            wp_die();
	}

    function auto_start_heartbeat_settings( $settings ) {
        $disable_auto_start=get_option('disable_auto_start_heart_beat');
        if($disable_auto_start != 'false'){
        $settings['autostart'] = false;
        return $settings;
        }
    }

    function control_heartbeat_settings( $settings ) {
       
        $heart_beat_frequency=get_option('control_heart_beat_frequency');
            $settings['interval'] = $heart_beat_frequency; 
            return $settings;
    }
    
   
    
    function heartbeat_options(){
        $disable_auto_start=get_option('disable_auto_start_heart_beat');
        $heart_beat_frequency=get_option('control_heart_beat_frequency');
        $disable_heart_beat=get_option('disable_heart_beat');
        $result['disable_heartbeat']=$disable_heart_beat=== 'true'? true: false;
        $result['heart_beat_frequency']=(int)$heart_beat_frequency;
        $result['disable_auto_start']=$disable_auto_start=== 'true'? true: false;
        $result['success'] = true;
        echo wp_json_encode($result);
        wp_die();
    }

}
$new_obj = new HearbeatControl();
