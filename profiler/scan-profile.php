<?php
namespace Smackcoders\AIOACC; 

// If profiling hasn't started, start it
if ( function_exists( 'get_option' ) && !isset( $GLOBALS['smack_profiler'] ) && basename( __FILE__ ) !=  basename( $_SERVER['SCRIPT_FILENAME'] ) ) {    
	$opts = get_option( 'profiler_details' );	
	//delete_option('profiler_details');
	if ( !empty( $opts['profiling_enabled'] ) ) {
	$file = realpath( dirname( __FILE__ ) ) . '/scan.php';
		if ( !file_exists( $file ) ) {  
			return;
		}
		@include_once $file;
		declare( ticks = 1 ); 
			$GLOBALS['smack_profiler'] = new Scan(); 
	}
	unset( $opts );
}
