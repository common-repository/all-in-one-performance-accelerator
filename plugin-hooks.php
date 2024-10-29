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

global $wpupe_plugin_ajax_hooks;

$wpupe_plugin_ajax_hooks = [

	'save_database_optimization_options',
	'get_database_optimization_options',
	'save_heart_beart_options',
	'get_heart_beart_options',
	'save_cdn_options',
	'get_cdn_options',
	'save_file_optimization_options',
	'get_file_optimization_options',
	'save_media_options',
	'get_media_options',
	'save_preload_options',
	'get_preload_options',
	'save_cache_options',
	'get_cache_options',
	'save_advanced_rules_options',
	'get_advanced_rules_options',
	'get_site_info_details',
	'get_site_status_details',
	'get_json_details',
	'send_json_file',
	'smack_clear_cache_dashboard',
	'smack_preload_dashboard',
	'smack_purge_opcache_dashboard',
	'save_image_optimization_options',
	'get_image_optimization_options',
	'get_processed_image_options',
	'get_drop_options',
	'save_drop_options',
	'save_gzip_options',
	'get_optimized_details',
	'get_query_informations',
	'delete_orphan_images',
	'delete_all_orphan_images',
	'get_page_count',
	'get_orphan_tables_options',
	'get_orphan_view_list',
	'delete_orphan_list',
	'get_hardware_details',
	'get_display_url',
	'stop_scaning',
	'get_latest_profile',
	'send_Mail_Report',
	'send_Mail',
	'get_modified_tables',
	'get_history_details',
	'clear_log',
	'view_scan_details',
	'delete_scan_details',
	'delete_all_scan_details',
	'save_changes',
	'save_change',
	'download_error_log',
	'save_cloudflare_cache_options',
	'save_values',
	'get_maximum_image_size',
	'set_debug_value',
	'dequeue_styles',
	'get_sacn_result',
	'delete_single_table',
	'delete_all_table',
	'get_tabs_and_page',
	'get_query_selected_tab',
	'get_advancerule_selected_tab',
	'get_tools_selected_tab',
	'get_cache_selected_tab',
	'get_asset_selected_tab',
	'get_profile_selected_tab',
	'get_data_selected_tab',
	'get_image_selected_tab',
	'get_media_selected_tab',
	'get_preload_selected_tab',
	'get_file_selected_tab',
	'get_sitestatus_selected_tab',
	'get_siteinfo_selected_tab',
	'get_orphant_selected_tab',
	'set_query_display',
];