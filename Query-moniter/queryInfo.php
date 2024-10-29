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
if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

if ( SAVEQUERIES && property_exists( $GLOBALS['wpdb'], 'save_queries' ) ) {
	$GLOBALS['wpdb']->save_queries = true;
}

class SmackQuery1 {
    function __construct() {
		

        $data = $this->query_usage();
        return $data;
    }
    function query_usage() {
		global $wpdb;
		$data = array();
        $precision = 0;
        $memory_usage = memory_get_peak_usage() / 1048576;
        if ($memory_usage < 10) {
            $precision = 2;
        } else if ($memory_usage < 100) {
            $precision = 1;
        }

        $memory_usage = round($memory_usage, $precision);
        $time_usage = (empty($this->starttime)) ? '' : $this->servertime . round(microtime(true) - $this->starttime, 2);

        $data['memory'] = $memory_usage;
        $data['time'] = $time_usage;
        $data['queries'] = $wpdb->num_queries;
			

		return $data;
		
	}
}