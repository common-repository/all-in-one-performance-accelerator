<?php
/**
 * All-in-one Performance Accelerator plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */
namespace Smackcoders\AIOACC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class scheduleDBCleanup
{
    protected static $instance = null, $plugin;

    public function __construct() {

    }

    public static function getInstance() {   
        if ( null == self::$instance ) {
            self::$instance = new self;
            self::$plugin = Plugin::getInstance();
        }
        return self::$instance;
    }

    public static function start_scheduler($timing){
		if (!wp_next_scheduled('profile_enhancer_schedule_hook')) {
            wp_schedule_event(time(), $timing , 'profile_enhancer_schedule_hook');
        }
	}

    public function getScheduleStatus($schedule_status, $schedule_timing){
        if($schedule_status == 'true' && isset($schedule_timing)){
			update_option('ENHANCER_SCHEDULE_STATUS', 'on');
            update_option('ENHANCER_SCHEDULE_TIME', $schedule_timing);	
            
            $schedule_db_optimize_timing = array(
                'daily' => 'smack_enhancer_daily',
                'weekly' => 'smack_enhancer_weekly',
                'monthly' => 'smack_enhancer_monthly'
            );
			self::$instance->start_scheduler($schedule_db_optimize_timing[$schedule_timing]);
        }
        elseif($schedule_status == 'false'){
			update_option('ENHANCER_SCHEDULE_STATUS', 'off');
			delete_option('ENHANCER_SCHEDULE_TIME');
			$timestamp = wp_next_scheduled( 'profile_enhancer_schedule_hook' );
    		wp_unschedule_event( $timestamp, 'profile_enhancer_schedule_hook' );
		}	
	}
    
    
}