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
require_once(__DIR__.'/scheduleDBCleanup.php');
	
class OptimizeDB
{
	protected static $instance = null,$plugin;
	
	public function __construct()
	{
		   
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
        add_action('wp_ajax_save_database_optimization_options', array($this,'get_db_options'));
        add_action('wp_ajax_get_database_optimization_options', array($this,'get_count')); 
        add_action('wp_ajax_get_data_selected_tab', array($this,'get_data_selected_tab'));   
    }

    public function get_data_selected_tab(){
		$tab = sanitize_text_field($_POST['tab']);
		if($tab === 'undefined'){
			$tab_value = get_option('smack_data_tab');
			if(empty($tab_value)){
				$tab_name = 'postclenup';
				update_option('smack_data_tab',$tab_name);
			}else{
				update_option('smack_data_tab',$tab_value);
			}
		}else{
			update_option('smack_data_tab',$tab);
		}
		$tab_address = get_option('smack_data_tab');
		$result['tab'] = $tab_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}
    
	public function get_count(){

        global $wpdb;
        $get_revisions   = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'revision'" ) );
        $get_trash   = $wpdb->get_var($wpdb->prepare ("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_status=%s", 'trash'));
        $get_draft   = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_status=%s", 'draft'));
        $get_spam_comments  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}comments WHERE comment_approved=%s", 'spam') );
        $get_trash_comments  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}comments WHERE comment_approved=%s", 'trash'));
        $server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
        $time  = isset( $server_array['REQUEST_TIME'] ) ? (int) $server_array['REQUEST_TIME'] : time();
        $expired_transient = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(option_name) FROM $wpdb->options WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout' ) . '%', $time ) );
        $all_transient = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(option_id) FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_site_transient_' ) . '%' ) );
        $optimize_database = $wpdb->get_var("SELECT COUNT(table_name) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "' and Engine <> 'InnoDB' and data_free > 0");
        $result['revision']=(int)$get_revisions;
        $result['trash']=(int)$get_trash;
        $result['draft']=(int)$get_draft;
        $result['spam']=(int)$get_spam_comments;
        $result['trash_comments']=(int)$get_trash_comments;
        $result['expired_transient']=(int)$expired_transient;
        $result['all_transient']=(int)$all_transient;
        $result['optimize_database']=(int)$optimize_database;
        $result['revision_status']=get_option('clean_revisions')=== 'true'? true: false;
        $result['trash_list']=get_option('clean_trash') === 'true'? true: false;
        $result['draft_list']=get_option('clean_drafts')=== 'true'? true: false;
        $result['spam_list']=get_option('clean_spams')=== 'true'? true: false;
        $result['trash_comments_list']=get_option('clean_trash_comments')=== 'true'? true: false;
        $result['expired_transient_list']=get_option('clean_expired_transients')=== 'true'? true: false;
        $result['all_transient_list']=get_option('clean_all_transients')=== 'true'? true: false;
        $result['optimize_database_list']=get_option('clean_optimize_database')=== 'true'? true: false;
        $result['schedule_cleanup'] = get_option('ENHANCER_SCHEDULE_STATUS')=== 'on'? true: false;
        $result['revision_limit']=get_option('smack_revision_limit');
        $result['draft_limit']=get_option('smack_draft_limit');
        $result['revisions_start_date'] = empty(get_option('revisionStartDate')) ? '' : get_option('revisionStartDate');
        $result['revisions_end_date'] = empty(get_option('revisionEndDate')) ? '' : get_option('revisionEndDate');
        $result['draft_start_date'] = empty(get_option('draftStartDate')) ? '' : get_option('draftStartDate');
        $result['draft_end_date'] = empty(get_option('draftEndDate')) ? '' : get_option('draftEndDate');
        $result['trash_start_date'] = empty(get_option('trashStartDate')) ? '' : get_option('trashStartDate');
        $result['trash_end_date'] = empty(get_option('trashEndDate')) ? '' : get_option('trashEndDate');
        $result['spam_start_date'] = empty(get_option('spamStartDate')) ? '' : get_option('spamStartDate');
        $result['spam_end_date'] = empty(get_option('spamEndDate')) ? '' : get_option('spamEndDate');
        $result['comments_start_date'] = empty(get_option('commentsStartDate')) ? '' : get_option('commentsStartDate');
        $result['comments_end_date'] = empty(get_option('commentsEndDate')) ? '' : get_option('commentsEndDate');
        $schedule_cleanup_timing = get_option('ENHANCER_SCHEDULE_TIME');
        $result['schedule_cleanup_frequency'] = !empty($schedule_cleanup_timing) ? $schedule_cleanup_timing : 'daily';
        
        echo wp_json_encode($result);
        wp_die();     
	}
    
    public function get_db_options(){
            if(isset($_POST)){
                $revisions = sanitize_text_field($_POST['revisions']);
                $drafts = sanitize_text_field($_POST['auto_draft']);
                $trash_post = sanitize_text_field($_POST['trashed_post']);
                $revisionlimit=intval($_POST['revision_limit']);
                $draft_limit=intval($_POST['draft_limit']);
                $spams = sanitize_text_field($_POST['spam_comments']);
                $trash_comments = sanitize_text_field($_POST['trashed_comments']);
                $expired_transients = sanitize_text_field($_POST['expired_transients']);
                $all_transients = sanitize_text_field($_POST['all_transients']);
                $optimize_database = sanitize_text_field($_POST['optimize_tables']);
                $schedule_status = sanitize_text_field($_POST['schedule_cleanup']);           
                $schedule_timing = sanitize_text_field($_POST['schedule_cleanup_frequency']);
                $revision_start_date=sanitize_text_field($_POST['revision_start_date']);
                $revision_end_date=sanitize_text_field($_POST['revision_end_date']);
                $draft_start_date=sanitize_text_field($_POST['draft_start_date']);
                $draft_end_date=sanitize_text_field($_POST['draft_end_date']);
                $trash_start_date=sanitize_text_field($_POST['trash_start_date']);
                $trash_end_date=sanitize_text_field($_POST['trash_end_date']);
                $spam_start_date=sanitize_text_field($_POST['spam_start_date']);
                $spam_end_date=sanitize_text_field($_POST['spam_end_date']);
                $comments_start_date=sanitize_text_field($_POST['comments_start_date']);
                $comments_end_date=sanitize_text_field($_POST['comments_end_date']);
                $revisionStartDate = sanitize_text_field($_POST['revisionStartDate']);
                $revisionEndDate = sanitize_text_field($_POST['revisionEndDate']);
                $draftStartDate = sanitize_text_field($_POST['draftStartDate']);
                $draftEndDate = sanitize_text_field($_POST['draftEndDate']);
                $trashStartDate = sanitize_text_field($_POST['trashStartDate']); 
                $trashEndDate = sanitize_text_field($_POST['trashEndDate']);
                $spamStartDate = sanitize_text_field($_POST['spamStartDate']);
                $spamEndDate = sanitize_text_field($_POST['spamEndDate']);
                $commentsStartDate = sanitize_text_field($_POST['commentsStartDate']);
                $commentsEndDate = sanitize_text_field($_POST['commentsEndDate']);
                update_option('revisionStartDate',$revisionStartDate);
                update_option('revisionEndDate',$revisionEndDate);
                update_option('draftStartDate',$draftStartDate);
                update_option('draftEndDate',$draftEndDate);
                update_option('trashStartDate',$trashStartDate);
                update_option('trashEndDate',$trashEndDate);
                update_option('spamStartDate',$spamStartDate);
                update_option('spamEndDate',$spamEndDate);
                update_option('commentsStartDate',$commentsStartDate);
                update_option('commentsEndDate',$commentsEndDate);
                update_option('clean_revisions',$revisions);
                update_option('clean_trash',$trash_post);
                update_option('clean_drafts',$drafts);
                update_option('smack_revision_limit',$revisionlimit);
                update_option('smack_draft_limit',$draft_limit);
                update_option('clean_spams',$spams);
                update_option('clean_trash_comments',$trash_comments);
                update_option('clean_expired_transients',$expired_transients);
                update_option('clean_all_transients',$all_transients);
                update_option('clean_optimize_database',$optimize_database);
                if(!empty($revisionlimit)||$revisionlimit=='0'){
  
                    $this->limit_post_revisions();
                }
                if(!empty($draft_limit)||$draft_limit=='0'){
                    $this->limit_auto_save();
                }
                if($revision_start_date!='Invalid date'){
                    update_option('revisions_start_date',$revision_start_date);
                }else{
                    delete_option('revisions_start_date');
                }
                if($revision_end_date!='Invalid date'){
                    update_option('revisions_end_date',$revision_end_date);
                }else{
                    delete_option('revisions_end_date');
                }
                if($draft_start_date!='Invalid date'){
                    update_option('draft_start_date',$draft_start_date);
                }else{
                    delete_option('draft_start_date');
                }
                if($draft_end_date!='Invalid date'){
                    update_option('draft_end_date',$draft_end_date);
                }else{
                    delete_option('draft_end_date');
                }
                if($trash_start_date!='Invalid date'){
                    update_option('trash_start_date',$trash_start_date);
                }else{
                    delete_option('trash_start_date');
                }
                if($trash_end_date!='Invalid date'){
                    update_option('trash_end_date',$trash_end_date);
                }else{
                    delete_option('trash_end_date');
                }
                if($spam_start_date!='Invalid date'){
                    update_option('spam_start_date',$spam_start_date);
                }else{
                    delete_option('spam_start_date');
                }
                if($spam_end_date!='Invalid date'){
                    update_option('spam_end_date',$spam_end_date);
                }else{
                    delete_option('spam_end_date');
                }
                if($comments_start_date!='Invalid date'){
                    update_option('comments_start_date',$comments_start_date);
                }else{
                    delete_option('comments_start_date');
                }
                if($comments_end_date!='Invalid date'){
                    update_option('comments_end_date',$comments_end_date);
                }else{
                    delete_option('comments_end_date');
                }
              
                
            }
            $post_cleanup=$this->post_cleanup();

            $schedule_instance = scheduleDBCleanup::getInstance();
            $schedule_instance->getScheduleStatus($schedule_status, $schedule_timing);
    
            $result['success'] = true;
            echo wp_json_encode($result);
            wp_die();
    }

    public function post_cleanup(){
        global $wpdb;
        $revision=get_option('clean_revisions');
        $drafts = get_option('clean_drafts');
        $trash_post = get_option('clean_trash');
        $spams = get_option('clean_spams');
        $trash_comments = get_option('clean_trash_comments');
        $expired_transients = get_option('clean_expired_transients');
        $all_transients = get_option('clean_all_transients');
        $optimize_database = get_option('clean_optimize_database');
        $revision_start_date=get_option('revisions_start_date');
        $revision_end_date=get_option('revisions_end_date');
        $draft_start_date=get_option('draft_start_date');
        $draft_end_date=get_option('draft_end_date');
        $trash_start_date=get_option('trash_start_date');
        $trash_end_date=get_option('trash_end_date');
        $spam_start_date=get_option('spam_start_date');
        $spam_end_date=get_option('spam_end_date');
        $comments_start_date=get_option('comments_start_date');
        $comments_end_date=get_option('comments_end_date');
        if($revision=="true"){
            if(!empty($revision_start_date)&&($revision_end_date)){              
                $get_revision_ids= $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type='revision' AND (post_modified BETWEEN '$revision_start_date%' AND '$revision_end_date%')");            
            }else{
                $get_revision_ids= $wpdb->get_results($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type=%s", 'revision'));
            }
            
            foreach($get_revision_ids as $revision_id){
                $deleted_revision=$revision_id->ID;
                wp_delete_post_revision($deleted_revision);
            }
        }
        if($drafts =="true"){
            if(!empty($draft_start_date)&&($draft_end_date)){     
                $get_draft_ids= $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_status='draft' AND (post_modified BETWEEN '$draft_start_date%' AND '$draft_end_date%')");
            }else{
                $get_draft_ids= $wpdb->get_results($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE post_status=%s", 'draft'));
            }
           
        foreach($get_draft_ids as $draft_id){
            $deleted_draft=$draft_id->ID;
            wp_delete_post($deleted_draft);
        }
    }
    if($trash_post =="true"){
        if(!empty($trash_start_date)&&($trash_end_date)){
            $get_trash_ids= $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_status='trash' AND (post_modified BETWEEN '$trash_start_date%' AND  '$trash_end_date%')");
        }else{
            $get_trash_ids= $wpdb->get_results($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE post_status=%s", 'trash'));
        }
        foreach($get_trash_ids as $trash_id){
            $deleted_trash=$trash_id->ID;
            wp_delete_post($deleted_trash);
        }
    }
    if($spams =="true"){
        if(!empty($spam_start_date)&&($spam_end_date)){
            $get_spam_ids= $wpdb->get_results("SELECT comment_ID FROM {$wpdb->prefix}comments WHERE comment_approved='spam' AND (comment_date BETWEEN '$spam_start_date%' AND '$spam_end_date%')");
        }else{
            $get_spam_ids= $wpdb->get_results($wpdb->prepare( "SELECT comment_ID FROM {$wpdb->prefix}comments WHERE comment_approved= %s",'spam' ));
        } 
        foreach($get_spam_ids as $spam_id){
            $deleted_spam=$spam_id->comment_ID;
            wp_delete_comment($deleted_spam);
        }
    }
    if($trash_comments=="true"){
        if(!empty($comments_start_date)&&($comments_end_date)){
            $get_trash_comment_ids= $wpdb->get_results("SELECT comment_ID FROM {$wpdb->prefix}comments WHERE comment_approved='trash' AND (comment_date BETWEEN '$comments_start_date%' AND '$comments_end_date%')");
        }else{
            $get_trash_comment_ids= $wpdb->get_results($wpdb->prepare( "SELECT comment_ID FROM {$wpdb->prefix}comments WHERE comment_approved= %s",'trash' ));
        } 
        foreach($get_trash_comment_ids as $trash_comment_id){
            $deleted_trash_comments=$trash_comment_id->comment_ID;
            wp_delete_comment($deleted_trash_comments);
        }
    }
    if($expired_transients=="true"){
        $time  = isset( $server_array['REQUEST_TIME'] ) ? (int) $server_array['REQUEST_TIME'] : time();
        $expired_transient_name = $wpdb->get_results($wpdb->prepare( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout' ) . '%', $time )) );
        foreach($expired_transient_name as $expired_transient_name){
            $deleted_expired_transient=$expired_transient_name->option_name;
            delete_expired_transients();
        }
    }
    if($all_transients =="true"){
       $all_transient_name = $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_site_transient_' ) . '%' ) );
       foreach($all_transient_name as $transient_name){
        $deleted_transient_name=$transient_name->option_name;
       delete_transient( str_replace( '_transient_', '',$deleted_transient_name ) );
       }
    }
    if($optimize_database=="true"){
       $get_optimize_table_name=$wpdb->get_results("SELECT table_name, data_free FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "' and Engine <> 'InnoDB' and data_free > 0" );
       foreach($get_optimize_table_name as $optimize_table_name){
        $deleted_tables=$wpdb->query( "OPTIMIZE TABLE $optimize_table_name->table_name" );
       }
    }
       
    }

    public function limit_post_revisions(){
        
         if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
            // config file resides in ABSPATH
            $wp_config_file = ABSPATH . 'wp-config.php';
        } elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
            // config file resides one level above ABSPATH but is not part of another installation
            $wp_config_file = dirname( ABSPATH ) . '/wp-config.php';
        }
        // check if config file can be written to
        if ( ! is_writable( $wp_config_file ) ) {
            return;
        }

        // get config file contents
        $wp_config_file_contents = file_get_contents( $wp_config_file );

              // validate config file
              if ( ! is_string( $wp_config_file_contents ) ) {
                return;
            }
    
              $revision_limit=get_option('smack_revision_limit');
              if($revision_limit=='1'){
                $set = false;
              }else{
                if($revision_limit=='0'){
                    $revision_limit='false';
                }
                $set = true;
              }
              $update_post_revision=preg_match("/Enables post revision for AIO Performance Accelerator./", $wp_config_file_contents);
              if($update_post_revision=='1'){
                
                $wp_config_file_contents = preg_replace( '/\/\*\* Enables post revision for AIO Performance Accelerator\. \*\/' . PHP_EOL . '.+' . PHP_EOL . '.+' . PHP_EOL . '\}' . PHP_EOL . PHP_EOL . '/', '', $wp_config_file_contents );
                file_put_contents( $wp_config_file, $wp_config_file_contents ); 
            }
            $found_post_revision_constant = preg_match( '/define\s*\(\s*[\'\"]WP_POST_REVISIONS[\'\"]\s*,.+\);/', $wp_config_file_contents );
        if ( $set && ! $found_post_revision_constant ) {
            $ce_wp_config_lines  = '/** Enables post revision for AIO Performance Accelerator. */' . PHP_EOL;
            $ce_wp_config_lines .= "if ( ! defined( 'WP_POST_REVISIONS' ) ) {" . PHP_EOL;
            $ce_wp_config_lines .= "\tdefine( 'WP_POST_REVISIONS', $revision_limit );" . PHP_EOL;
            $ce_wp_config_lines .= '}' . PHP_EOL;
            $ce_wp_config_lines .= PHP_EOL;
            $wp_config_file_contents = preg_replace( '/(\/\*\* Sets up WordPress vars and included files\. \*\/)/', $ce_wp_config_lines . '$1', $wp_config_file_contents );
        }
        if ( ! $set ) {
            $wp_config_file_contents = preg_replace( '/\/\*\* Enables post revision for AIO Performance Accelerator\. \*\/' . PHP_EOL . '.+' . PHP_EOL . '.+' . PHP_EOL . '\}' . PHP_EOL . PHP_EOL . '/', '', $wp_config_file_contents );
        }

        // update config file
        file_put_contents( $wp_config_file, $wp_config_file_contents );    
       
    }

        public function limit_auto_save(){
            if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
                // config file resides in ABSPATH
                $wp_config_file = ABSPATH . 'wp-config.php';
            } elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
                // config file resides one level above ABSPATH but is not part of another installation
                $wp_config_file = dirname( ABSPATH ) . '/wp-config.php';
            }
            // check if config file can be written to
            if ( ! is_writable( $wp_config_file ) ) {
                return;
            }
    
            // get config file contents
            $wp_config_file_contents = file_get_contents( $wp_config_file );
    
             if ( ! is_string( $wp_config_file_contents ) ) {
                    return;
                }
                $draft_limit=get_option('smack_draft_limit');
                $draft = true;
                switch($draft_limit){
                    case 0:
                         $draft_limit ='false';
                         break;
                    case 1:
                        $draft = false;
                        break;
                    case 2:
                         $draft_limit ='120';
                         break;
                    case 3:
                         $draft_limit ='180';
                         break;
                    case 4:
                         $draft_limit ='240';
                         break;
                    case 5:
                         $draft_limit ='300';  
                         break;
                    
                }
                $update_auto_save=preg_match("/Enables Auto Save for AIO Performance Accelerator./", $wp_config_file_contents);
                  if($update_auto_save=='1'){
                    $wp_config_file_contents = preg_replace( '/\/\*\* Enables Auto Save for AIO Performance Accelerator\. \*\/' . PHP_EOL . '.+' . PHP_EOL . '.+' . PHP_EOL . '\}' . PHP_EOL . PHP_EOL . '/', '', $wp_config_file_contents );
                    file_put_contents( $wp_config_file, $wp_config_file_contents ); 
                }
                $found_draft_constant = preg_match( '/define\s*\(\s*[\'\"]AUTOSAVE_INTERVAL[\'\"]\s*,.+\);/', $wp_config_file_contents );
            if ( $draft && ! $found_draft_constant ) {
                $ce_wp_config_lines  = '/** Enables Auto Save for AIO Performance Accelerator. */' . PHP_EOL;
            $ce_wp_config_lines .= "if ( ! defined( 'AUTOSAVE_INTERVAL' ) ) {" . PHP_EOL;
            $ce_wp_config_lines .= "\tdefine( 'AUTOSAVE_INTERVAL', $draft_limit );" . PHP_EOL;
            $ce_wp_config_lines .= '}' . PHP_EOL;
            $ce_wp_config_lines .= PHP_EOL;
            $wp_config_file_contents = preg_replace( '/(\/\*\* Sets up WordPress vars and included files\. \*\/)/', $ce_wp_config_lines . '$1', $wp_config_file_contents );
            }
            if ( ! $draft ) {
                $wp_config_file_contents = preg_replace( '/\/\*\* Enables Auto Save for AIO Performance Accelerator.\. \*\/' . PHP_EOL . '.+' . PHP_EOL . '.+' . PHP_EOL . '\}' . PHP_EOL . PHP_EOL . '/', '', $wp_config_file_contents );
            }
    
            // update config file
            file_put_contents( $wp_config_file, $wp_config_file_contents );    
       

    }
	

}
$new_obj = new OptimizeDB();