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

class DownloadSettings
{
	protected static $instance = null,$plugin;
	
	public function __construct()
	{ 
       
		//  $this->get_options();
			
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
        add_action('wp_ajax_get_json_details', array($this,'send_json_details'));
        add_action('wp_ajax_send_json_file', array($this,'get_json_details'));
        add_action('wp_ajax_save_drop_options', array($this,'drop_option_details'));
        add_action('wp_ajax_get_drop_options', array($this,'set_option_details'));
        add_action('wp_ajax_get_optimized_details', array($this,'get_optimized_details'));
        add_action('wp_ajax_get_tools_selected_tab', array($this,'get_tools_selected_tab'));
	}
    
    public function get_tools_selected_tab(){
        $tab = sanitize_text_field($_POST['tab']);
        if($tab === 'undefined'){
            $tab_value = get_option('smack_tools_tab');
            if(empty($tab_value)){
                $tab_name = 'export';
                update_option('smack_tools_tab',$tab_name);
            }else{
                update_option('smack_tools_tab',$tab_value);
            }
        }else{
            update_option('smack_tools_tab',$tab);
        }
        $tab_address = get_option('smack_tools_tab');
        $result['tab'] = $tab_address;
        $result['success'] = true;
        echo wp_json_encode($result);
        wp_die();
    }

    public function get_optimized_details(){
        $enable_lazyload=get_option('smack_lazyload_enable');
       $result['combine_css']=get_option('smack_combine_css')=== 'true'? true: false;
       $result['combine_js']=get_option('smack_combine_js')=== 'true'? true: false;
       $result['enable_lazyload']=$enable_lazyload=== 'true'? true: false;
       $result['compress_image']=get_option('smack_compress_image')=== 'true'? true: false;
       $result['activate_preloading']=get_option('smack_activate_preloading')=== 'true'? true: false;
       $result['success']='true';
        echo wp_json_encode($result);
	wp_die();
    }
 
	
   public function send_json_details(){
    @wp_mkdir_p(WP_CONTENT_DIR . '/cache/backup-json',0755);
    $enable_lazyload=get_option('smack_lazyload_enable');
    $enable_lazyload_iframes = get_option('smack_lazyload_iframe_videos');
    $disable_emoji=get_option('smack_emoji_disable');
    $disable_auto_start=get_option('disable_auto_start_heart_beat');
    $heart_beat_frequency=get_option('control_heart_beat_frequency');
    $disable_heart_beat=get_option('disable_heart_beat');
    $activate_preloading=get_option('smack_activate_preloading');
    $prefetch_dns_requests=get_option('smack_dns_prefetch');
    $reduce_life_span=get_option('smack_cache_time');
    $schedule_cleanup_timing = get_option('ENHANCER_SCHEDULE_TIME');
    $never_cache_path=get_option('smack_never_cache_path');
    $never_cache_ids=get_option('smack_never_cache_ids');
    $never_cache_slugs=get_option('smack_never_cache_slugs');
    $cache_query_strings=get_option('smack_cache_query_strings');
    $never_cache_cookies=get_option('smack_never_cache_cookies');
    $reduce_life_span_selected_option=get_option('smack_cache_life_span')=== 'true'? true: false;
    $result['minify_html']=get_option('smack_minify_html')=== 'true'? true: false;
    $result['disable_google_fonts']=get_option('smack_google_fonts')=== 'true'? true: false;
    $result['combine_google_fonts']=get_option('smack_combine_google_fonts')=== 'true'? true: false;
    $result['enable_gzip_compression']=get_option('smack_gzip_compression')=== 'true'? true: false;
    $result['remove_query_strings']=get_option('smack_remove_query_strings') === 'true' ? true : false;
    $result['minify_css']=get_option('smack_minify_css')=== 'true'? true: false;
    $result['combine_css']=get_option('smack_combine_css')=== 'true'? true: false;
    $result['blocking_css']=get_option('smack_blocking_css')=== 'true'? true: false;
    $result['blocking_js']=get_option('smack_blocking_js')=== 'true'? true: false;
    $result['minify_js']=get_option('smack_minify_js')=== 'true'? true: false;
    $result['combine_js']=get_option('smack_combine_js')=== 'true'? true: false;
    $result['defer_js']=get_option('smack_deferred_js')=== 'true'? true: false;
    $result['exclude_files_url_css'] = get_option('smack_excluded_css');
    $result['exclude_files_url_js'] = get_option('smack_excluded_js');
    $result['delay_js_script']=get_option('smack_delay_js_script');
    $result['enable_lazyload']=$enable_lazyload=== 'true'? true: false;
    $result['enable_lazyload_iframes']=$enable_lazyload_iframes=== 'true'? true: false;
	$result['disable_emoji']=$disable_emoji=== 'true'? true: false;
    $result['revision_status']=get_option('clean_revisions')=== 'true'? true: false;
    $result['trash_list']=get_option('clean_trash') === 'true'? true: false;
    $result['draft_list']=get_option('clean_drafts')=== 'true'? true: false;
    $result['spam_list']=get_option('clean_spams')=== 'true'? true: false;
    $result['trash_comments_list']=get_option('clean_trash_comments')=== 'true'? true: false;
    $result['expired_transient_list']=get_option('clean_expired_transients')=== 'true'? true: false;
    $result['all_transient_list']=get_option('clean_all_transients')=== 'true'? true: false;
    $result['optimize_database_list']=get_option('clean_optimize_database')=== 'true'? true: false;
    $result['schedule_cleanup'] = get_option('ENHANCER_SCHEDULE_STATUS')=== 'on'? true: false;
    $result['schedule_cleanup_frequency'] = !empty($schedule_cleanup_timing) ? $schedule_cleanup_timing : 'daily';
    $result['disable_heartbeat']=$disable_heart_beat=== 'true'? true: false;
    $result['heart_beat_frequency']=(int)$heart_beat_frequency;
    $result['disable_auto_start']=$disable_auto_start=== 'true'? true: false;
    $result['enable_cdn']=get_option('smack_enable_cdn')=== 'true'? true: false;
	$result['exclude_files_url_cdn']=get_option('smack_excluded_cdn_files');
    $result['domain_json']=get_option('smack_cdn_domain_input');
    $result['activate_preloading']=$activate_preloading=== 'true'? true: false;
    $result['prefetch_dns_requests']=$prefetch_dns_requests;
    $result['complete_cache']=get_option('smack_complete_cache')=== 'true'? true: false;
    $result['comment_cache']=get_option('smack_enable_comments_cache')=== 'true'? true: false;
    $result['plugin_cache']=get_option('smack_enable_plugin_cache')=== 'true'? true: false;
    $result['compress_cache']=get_option('smack_compress_cache')=== 'true'? true: false;
    $result['webp_cache']=get_option('smack_webp_cache')=== 'true'? true: false;
    $result['compress_image']=get_option('smack_compress_image')=== 'true'? true: false;
    $result['auto_compress_images']=get_option('smack_auto_compress_images')=== 'true'? true: false;
    $result['compress_different_images']=get_option('compress_different_images');
    $result['resize_image']=get_option('smack_resize_image')=== 'true'? true: false;
    $result['image_max_width']=(int)get_option('smack_image_max_width');
    $result['reduce_life_span']=(int)$reduce_life_span;
    $result['reduce_life_span_selected_option']=$reduce_life_span_selected_option;
    $result['excluded_page_paths']= !empty($never_cache_path) ? $never_cache_path : '';
    $result['excluded_post_ids']= !empty($never_cache_ids) ? $never_cache_ids : '';
    $result['excluded_post_slugs']= !empty($never_cache_slugs) ? $never_cache_slugs : '';
    $result['excluded_query_strings']= !empty($cache_query_strings) ? $cache_query_strings : '';
    $result['excluded_cookies']= !empty($never_cache_cookies) ? $never_cache_cookies : '';
    
    $result['success'] = true;
    
    $json=wp_json_encode($result);
    $json_file_name='wp-smack'.'-'.date("Y-m-d").'-'.date("h:i:s").'.json';
   
  
    $fp = fopen(WP_CONTENT_DIR . '/cache/backup-json/'.$json_file_name, 'w');
    fwrite($fp, json_encode($json));
    fclose($fp);
    $json_path=content_url(). '/cache/backup-json/'.$json_file_name;
    
    $response['json_path']=$json_path;
    echo wp_json_encode($response);
	wp_die();
   }
	
	public function get_json_details(){
       
        if(isset($_POST)){
            
            $selected_fields = str_replace("\\" , '' ,$_POST['json_info']);
            $selected_fields = json_decode($selected_fields, True); 
            $minify_html=$selected_fields['minify_html']== 1? 'true': 'false';
			$disable_google_fonts=$selected_fields['disable_google_fonts']== 1? 'true': 'false';
            $combine_google_fonts=$selected_fields['combine_google_fonts']== 1? 'true': 'false';
			$minify_css=$selected_fields['minify_css']== 1? 'true': 'false';
			$combine_css=$selected_fields['combine_css']== 1? 'true': 'false';
			$minify_js=$selected_fields['minify_js']== 1? 'true': 'false';
            $combine_js=$selected_fields['combine_js']== 1? 'true': 'false';
            $blocking_css=$selected_fields['blocking_css']== 1? 'true': 'false';
            $blocking_js=$selected_fields['blocking_js']== 1? 'true': 'false';
            $defer_js=$selected_fields['defer_js']== 1? 'true': 'false';
            $excluded_css=$selected_fields['exclude_files_url_css'];
            $excluded_js=$selected_fields['exclude_files_url_js'];
            $delay_js_script=$selected_fields['delay_js_script'];
            $gzip_compression=$selected_fields['enable_gzip_compression']== 1? 'true': 'false';  
            $enable_lazyload=$selected_fields['enable_lazyload']== 1? 'true': 'false';
            $enable_lazyload_iframes=$selected_fields['enable_lazyload_iframes']== 1? 'true': 'false';
			$disable_emoji=$selected_fields['disable_emoji']== 1? 'true': 'false';
			$disable_embeds=$selected_fields['disable_embeds']== 1? 'true': 'false';
            $revisions=$selected_fields['revision_status']== 1? 'true': 'false';
            $drafts=$selected_fields['draft_list']== 1? 'true': 'false';
            $trash_post=$selected_fields['trash_list']== 1? 'true': 'false';
            $spams=$selected_fields['spam_list']== 1? 'true': 'false';
            $trash_comments=$selected_fields['trash_comments_list']== 1? 'true': 'false';
            $expired_transients=$selected_fields['expired_transient_list']== 1? 'true': 'false';
            $all_transients=$selected_fields['all_transient_list']== 1? 'true': 'false';
            $optimize_database=$selected_fields['optimize_database_list']== 1? 'true': 'false';
            $schedule_cleanup=$selected_fields['schedule_cleanup']== 1? 'on': 'off';
            $schedule_timing=$selected_fields['schedule_cleanup_frequency'];
            $disable_heart_beat=$selected_fields['disable_heartbeat']== 1? 'true': 'false';
            $disable_auto_start=$selected_fields['disable_auto_start']== 1? 'true': 'false';
            $heart_beat_frequency=$selected_fields['heart_beat_frequency'];
            $activate_preloading=$selected_fields['activate_preloading']== 1? 'true': 'false';
            $prefetch_dns_requests=$selected_fields['prefetch_dns_requests'];
            $complete_cache=$selected_fields['complete_cache']== 1? 'true': 'false';
			$comment_cache=$selected_fields['comment_cache']== 1? 'true': 'false';
            $plugin_cache=$selected_fields['plugin_cache']== 1? 'true': 'false';
			$compress_cache=$selected_fields['compress_cache']== 1? 'true': 'false';
            $webp_cache=$selected_fields['webp_cache']== 1? 'true': 'false';
            $enable_cdn=$selected_fields['enable_cdn']== 1? 'true': 'false';
            $exclude_files_url_cdn=$selected_fields['exclude_files_url_cdn'];
            $reduce_life_span=$selected_fields['reduce_life_span'];
            $reduce_life_span_selected_option=$selected_fields['reduce_life_span_selected_option'];
            $compress_image=$selected_fields['compress_image']== 1? 'true': 'false';
            $auto_compress_images=$selected_fields['auto_compress_images']== 1? 'true': 'false';
            $compress_different_images=$selected_fields['compress_different_images'];
            $resize_image=$selected_fields['resize_image'];
            $image_max_width=$selected_fields['image_max_width'];

            $never_cache_path=$selected_fields['excluded_page_paths'];
            $never_cache_ids=$selected_fields['excluded_post_ids'];
            $never_cache_slugs=$selected_fields['excluded_post_slugs'];
            $cache_query_strings=$selected_fields['excluded_query_strings'];
            $never_cache_cookies=$selected_fields['excluded_cookies'];

            update_option('smack_minify_html',$minify_html);
			update_option('smack_google_fonts',$disable_google_fonts);
            update_option('smack_combine_google_fonts',$combine_google_fonts);
			update_option('smack_gzip_compression',$gzip_compression);
            update_option('smack_remove_query_strings',$query_string);
			update_option('smack_minify_css',$minify_css);
            update_option('smack_combine_css',$combine_css);
            update_option('smack_blocking_css',$blocking_css);
            update_option('smack_blocking_js',$blocking_js);
			update_option('smack_minify_js',$minify_js);
            update_option('smack_combine_js',$combine_js);
            update_option('smack_deferred_js',$defer_js);
            update_option('smack_excluded_css',$excluded_css);
            update_option('smack_excluded_js',$excluded_js);
            update_option('smack_delay_js_script',$delay_js_script);
            update_option('smack_lazyload_enable',$enable_lazyload);
            update_option('smack_lazyload_iframe_videos',$enable_lazyload_iframes);
            update_option('smack_emoji_disable',$disable_emoji);
			update_option('smack_embeds_disable',$disable_embeds);
            update_option('clean_revisions',$revisions);
            update_option('clean_trash',$trash_post);
            update_option('clean_drafts',$drafts);
            update_option('clean_spams',$spams);
            update_option('clean_trash_comments',$trash_comments);
            update_option('clean_expired_transients',$expired_transients);
            update_option('clean_all_transients',$all_transients);
            update_option('clean_optimize_database',$optimize_database);
            update_option('ENHANCER_SCHEDULE_STATUS', $schedule_cleanup);
            update_option('ENHANCER_SCHEDULE_TIME', $schedule_timing);
            update_option('disable_heart_beat',$disable_heart_beat);
            update_option('disable_auto_start_heart_beat',$disable_auto_start);
            update_option('control_heart_beat_frequency',$heart_beat_frequency);
            update_option('smack_activate_preloading',$activate_preloading);
            update_option('smack_dns_prefetch',$prefetch_dns_requests);	 
            update_option('smack_complete_cache',$complete_cache);
			update_option('smack_enable_comments_cache',$comment_cache);
			update_option('smack_enable_plugin_cache',$plugin_cache);
			update_option('smack_compress_cache',$compress_cache);
            update_option('smack_webp_cache',$webp_cache);
            update_option('smack_cache_time',$reduce_life_span);
            update_option('smack_cache_life_span',$reduce_life_span_selected_option);
            update_option('smack_compress_image',$compress_image);
			update_option('smack_auto_compress_images',$auto_compress_images);
			update_option('compress_different_images',$compress_different_images);
			update_option('smack_resize_image',$resize_image);
			update_option('smack_image_max_width',$image_max_width);
            update_option('smack_enable_cdn',$enable_cdn);
            update_option('smack_excluded_cdn_files',$exclude_files_url_cdn);
            update_option('smack_never_cache_path',$never_cache_path);
            update_option('smack_never_cache_ids',$never_cache_ids);
            update_option('smack_never_cache_slugs',$never_cache_slugs);
            update_option('smack_cache_query_strings',$cache_query_strings);
            update_option('smack_never_cache_cookies',$never_cache_cookies);			

             foreach($selected_fields['domain_json'] as $cdn_files){
                $cname_domain=$cdn_files['cdnCnames'];
                $cdn_files=$cdn_files['reserved_file_types'];
                if($cdn_files == 'all-files'){
                   $cdn_domain_name=$cname_domain;
                   update_option('smack_cdn_all-files',$cdn_domain_name);
                }
                elseif($cdn_files =='js'){
                   $cdn_js_url=$cname_domain;
                   update_option('smack_cdn_js',$cdn_js_url);
                }
               elseif($cdn_files =='css'){
                   $cdn_css_url=$cname_domain;
                   update_option('smack_cdn_css',$cdn_css_url);
               }
               elseif($cdn_files =='images'){
                   $cdn_image_url=$cname_domain;
                   update_option('smack_cdn_images',$cdn_image_url);
               }

           }
           update_option('smack_cdn_domain_input',$selected_fields['domain_json']);
            
        }
       
        $result['success'] = true;
        echo wp_json_encode($result);
	wp_die();
    }
	
	public function drop_option_details(){
        if(isset($_POST)){
            $drop_table_option = sanitize_text_field($_POST['drop_options_table']);
            update_option('smack_drop_options',$drop_table_option);
        }
        $result['success'] = true;
        echo wp_json_encode($result);
	wp_die();
    }

    public function set_option_details(){
        $result['drop_options_table']=get_option('smack_drop_options')=== 'true'? true: false;  
        $result['success'] = true;
        echo wp_json_encode($result);
	wp_die();
    }
    

    public function drop_all_options_table(){
        delete_option('smack_minify_html');
        delete_option('smack_google_fonts');
        delete_option('smack_combine_google_fonts');
        delete_option('smack_gzip_compression');
        delete_option('smack_remove_query_strings');
        delete_option('smack_minify_css');
        delete_option('smack_combine_css');
        delete_option('smack_minify_js');
        delete_option('smack_combine_js');
        delete_option('smack_deferred_js');
        delete_option('smack_excluded_css');
        delete_option('smack_excluded_js');
        delete_option('smack_delay_js_script');
        delete_option('smack_lazyload_enable');
        delete_option('smack_lazyload_iframe_videos');
        delete_option('smack_emoji_disable');
        delete_option('smack_embeds_disable');
        delete_option('clean_revisions');
        delete_option('clean_trash');
        delete_option('clean_drafts');
        delete_option('clean_spams');
        delete_option('clean_trash_comments');
        delete_option('clean_expired_transients');
        delete_option('clean_all_transients');
        delete_option('clean_optimize_database');
        delete_option('disable_heart_beat');
        delete_option('disable_auto_start_heart_beat');
        delete_option('control_heart_beat_frequency');
        delete_option('smack_activate_preloading');
        delete_option('smack_dns_prefetch');
        delete_option('smack_enable_cdn');
        delete_option('smack_excluded_cdn_files');
        delete_option('smack_cdn_all-files');
        delete_option('smack_cdn_js');
        delete_option('smack_cdn_css');
        delete_option('smack_cdn_images');
        delete_option('smack_complete_cache');
        delete_option('smack_enable_comments_cache');
        delete_option('smack_enable_plugin_cache');
        delete_option('smack_compress_cache');
        delete_option('smack_webp_cache');
        delete_option('smack_cache_time');
        delete_option('smack_cache_life_span');
        delete_option('smack_cdn_domain_input');   
        delete_option('smack_compress_image');
        delete_option('smack_stop_compression');
        delete_option('smack_auto_compress_images');
        delete_option('compress_different_images');
        delete_option('smack_resize_image');
        delete_option('smack_image_max_width');
        delete_option('smack_blocking_css');
        delete_option('smack_blocking_js');
        delete_option('smack_never_cache_path');
        delete_option('smack_never_cache_ids');
        delete_option('smack_never_cache_slugs');
        delete_option('smack_cache_query_strings');
        delete_option('smack_never_cache_cookies');
        delete_option('smack_cloudfare_email');
        delete_option('smack_cloudfare_api');
        delete_option('smack_cloudfare_zoneid');
        delete_option('smack_drop_options');
        
    }
}
