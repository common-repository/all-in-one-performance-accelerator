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

class AssetView {

    protected static $instance = null,$plugin;

	public static $collection = [];

	
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

    public function __construct()
	{
        add_action('init', array($this, 'hook_setups'), 9999);
	}

    function hook_setups() {
        add_action('wp_print_scripts',array( $this ,'collect_assets'),10000);
        add_filter('script_loader_src', [$this, 'dequeue_scripts'], 10, 2);
		add_filter('style_loader_src', [$this, 'dequeue_scripts'], 10, 2);
	}

    public function collect_assets()
	{
		global $wp_scripts;
        global $wp_styles;
        $data_assets = [
			'js' => $wp_scripts,
			'css' => $wp_styles,
		];
		$denied = [
			'js' => ['admin-bar', 'wam-pnotify'],
			'css' => [
				'admin-bar',
				'dashicons',
				'wam-pnotify',
				'wbcr-clearfy-adminbar-styles',
			],
		];		
		
		foreach($data_assets as $type => $data) {
			foreach($data->queue as $el) {
				if( isset($data->registered[$el]) ) {
					if( !in_array($el, $denied[$type]) ) {
						if( isset($data->registered[$el]->src) ) {
							$url = self::prepare_url($data->registered[$el]->src);
							$url_short = str_replace(get_home_url(), '', $url);
							if( false !== strpos($url, get_theme_root_uri()) ) {
								$resource_type = 'theme';
							} else if( false !== strpos($url, plugins_url()) ) {
								$resource_type = 'plugins';
							} else {
								$resource_type = 'misc';
							}
                            
							$collection = &self::$collection[$resource_type];	

							if( 'plugins' == $resource_type ) {
								$clean_url = str_replace(WP_PLUGIN_URL . '/', '', $url);
								$url_parts = explode('/', $clean_url);
								$resource_name = !empty($url_parts[0]) ? $url_parts[0] : null;
								
								if( empty($resource_name) ) {
									continue;
								}		
								$collection =  &self::$collection[$resource_type][$resource_name];		
							}

							if( !isset($collection[$type][$el]) ) {
								
								$collection[$type][$el] = [
									'url_full' => $url,
									'url_short' => $url_short,
									'size' => self::get_asset_size($url),
									'ver' => $data->registered[$el]->ver,
									'deps' => (isset($data->registered[$el]->deps) ? $data->registered[$el]->deps : []),
								];
							}
						}
					}
				}
			}
		}	

		$assets_manager = self::$collection;
		$this->create_table($assets_manager);
		return false;
	}

	public function create_table($values){
		global $wpdb;
		$page = home_url( $_SERVER['REQUEST_URI']);
        $page_value = parse_url($page);
        $request_page = $page_value['path'].(isset($page_value['query']) ? $page_value['query'] : '');
		$asset_table = esc_sql($wpdb->prefix . "aio_asset_table_entery");    
        $wpdb->query("CREATE TABLE IF NOT EXISTS $asset_table (
			`plugin_name` VARCHAR(255) ,
			`script_name` VARCHAR(255) ,
			`url_full` VARCHAR(255) ,
			`url_short` VARCHAR(255) ,
			`size` VARCHAR(255) ,
			`type` VARCHAR(255) ,
			`version` VARCHAR(255) ,
			`plugin_active_state` VARCHAR(255) ,
            `current_page` LONGTEXT NOT NULL
				) ENGINE=InnoDB");

		$asset_table = esc_sql($wpdb->prefix . "aio_asset_table_entery");
		$display_query_column = $wpdb->get_results("SHOW COLUMNs FROM $asset_table LIKE 'display_query'");
		if(empty($display_query_column)){
		  $wpdb->query("ALTER TABLE `$asset_table` ADD COLUMN display_query VARCHAR(255);");
		}
		$id_column = $wpdb->query("SHOW COLUMNs FROM `$asset_table` LIKE 'id'");
		if(empty($id_column)){
			$wpdb->query("ALTER TABLE `$asset_table` ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY");
		}
		$asset_core_table = esc_sql($wpdb->prefix . "aio_asset_table_core_entery");
        $wpdb->query("CREATE TABLE IF NOT EXISTS $asset_core_table (
			`script_name` VARCHAR(255) ,
			`url_full` VARCHAR(255) ,
			`url_short` VARCHAR(255) ,
			`size` VARCHAR(255) ,
			`type` VARCHAR(255) ,
			`version` VARCHAR(255) ,
            `current_page` LONGTEXT NOT NULL
				) ENGINE=InnoDB");
		if(!empty($values['plugins'])){
			foreach($values['plugins'] as $plugin => $single){
				foreach($single as $type => $jsvalue){
					foreach($jsvalue as $js => $script){
						
						$in_value_check =  $wpdb->get_results( $wpdb->prepare( "select plugin_name from $asset_table where script_name = %s and type = %s ", $js,$type ) );
						$exist_page =  $wpdb->get_results( $wpdb->prepare( "select current_page from $asset_table where script_name = %s and type = %s ", $js,$type ) );
						
						if($plugin != 'all-in-one-performance-accelerator' && empty($in_value_check)){
							$url_checking = array($request_page=>'false');
							$wpdb->query("insert into {$wpdb->prefix}aio_asset_table_entery(plugin_name,script_name,url_full,url_short,size,type,version,plugin_active_state,current_page) values('".$plugin."','".$js."','".$script['url_full']."','".$script['url_short']."','".$script['size']."','".$type."','".$script['ver']."','active','".serialize($url_checking)."')");
						}else{
							if(strpos($js,'all-in-one-performance-accelerator') == false && !empty($exist_page)){
								foreach($exist_page as $ex_page){
									$add_page= unserialize($ex_page->current_page);
									$url_pages = array_keys($add_page);
									if(in_array($request_page,$url_pages)){
										foreach($add_page as $key => $value){
											$add_page[$key] = $value;
										}
									}else{
										$add_page[$request_page] = 'false';
										// array_push($add_page[$request_page]);
									}
									$update_schedule = $wpdb->update( $asset_table, array(
										'current_page' => serialize($add_page)
									), array( 'script_name' => $js ) ); 
								}  
							}                  
							$plugins_list = get_plugins();
							foreach($plugins_list as $key => $plugin_filename){
								if(is_plugin_active($key) && ($plugin_filename['TextDomain'] == $plugin) ){
									
								}elseif(!is_plugin_active($key)){
									$wpdb->delete( $asset_table, array('plugin_name' => $plugin_filename['TextDomain'] ));
								}
							}
						}
					}
				}
			}
		}

		foreach($values['misc'] as $plugin => $scripts){
			foreach($scripts as $core =>$core_value ){
				
				$in_value_core_check =  $wpdb->get_results( $wpdb->prepare( "select script_name from $asset_core_table where script_name = %sand type = %s ", $core,$plugin ) );
				$exist_page =  $wpdb->get_results( $wpdb->prepare( "select current_page from $asset_core_table where script_name = %s and type = %s ", $core,$plugin ) );
                if(empty($in_value_core_check)){
                    $url_checking = array($request_page=>'false');
					$wpdb->query("insert into {$wpdb->prefix}aio_asset_table_core_entery(script_name,url_full,url_short,size,type,version,current_page) values('".$core."','".$core_value['url_full']."','".$core_value['url_short']."','".$core_value['size']."','".$plugin."','".$core_value['ver']."','".serialize($url_checking)."')");
				}else{
                    if(!empty($exist_page)){
                        foreach($exist_page as $ex_page){
                            $add_page= unserialize($ex_page->current_page);
                            $url_pages = array_keys($add_page);
                            if(in_array($request_page,$url_pages)){
                                foreach($add_page as $key => $value){
                                    $add_page[$key] = $value;
                                }
                            }else{
                                $add_page[$request_page] = 'false';
                                array_push($add_page, $request_page);
                            }
                            $update_schedule = $wpdb->update( $asset_core_table, array(
                                'current_page' => serialize($add_page)
                            ), array( 'script_name' => $core ) ); 
                        }  
                    }          
                }
			}
		}
		$result = [];
        $valu = $wpdb->get_results( "SELECT * FROM $asset_table" );
		if(!empty($valu)){
			foreach($values['plugins'] as $plugin => $single){
				foreach($valu as $val =>$va){
					if($plugin == $va->plugin_name){
						$result[$va->plugin_name] = $wpdb->get_results($wpdb->prepare( "select * from $asset_table where plugin_name = %s", $va->plugin_name));
					}
				}
			}
		}

		foreach($values['misc'] as $plugin => $scripts){
			foreach($scripts as $core =>$core_value ){
				$core_result[$core] = $wpdb->get_results( $wpdb->prepare( "select * from $asset_core_table where script_name = %s", $core) );
			}
		}
		update_option("smack_aio_assert_plugin_table",$result);
		update_option("smack_aio_assert_core_table",$core_result);
	}

    private function get_asset_size($src)
	{
		$weight = 0;

		$home = get_theme_root() . '/../..';
		$src = explode('?', $src);

		if( !filter_var($src[0], FILTER_VALIDATE_URL) === false && strpos($src[0], get_home_url()) === false ) {
			return 0;
		}

		$src_relative = $home . str_replace(get_home_url(), '', self::prepare_url($src[0]));

		if( file_exists($src_relative) ) {
			$weight = round(filesize($src_relative) / 1024, 1);
		}

		return $weight;
	}

	private function prepare_url($url)
	{
		if( isset($url[0]) && isset($url[1]) && '/' == $url[0] && '/' == $url[1] ) {
			$out = (is_ssl() ? 'https:' : 'http:') . $url;
		} else {
			$out = $url;
		}

		return $out;
	}

    public static function dequeue_scripts($src, $handle){

        global $wpdb;
		$asset_table = esc_sql($wpdb->prefix . "aio_asset_table_entery");
		$asset_core_table = esc_sql($wpdb->prefix . "aio_asset_table_core_entery"); 

		$dequeue_plugin_script = $wpdb->get_results("SELECT current_page, url_full FROM $asset_table");
		$dequeue_core_script = $wpdb->get_results("SELECT current_page, url_full FROM $asset_core_table");

        $request_page = home_url( $_SERVER['REQUEST_URI']);
        $values = parse_url($request_page);
        $page = $values['path'].(isset($values['query']) ? $values['query'] : '');
        foreach((array)$dequeue_plugin_script as $dequeue_plugins => $dequeue_plugin){
            $dequeue_page = unserialize($dequeue_plugin->current_page);
            foreach($dequeue_page as $key => $value){
                if($value == 'true'){
                    if($page == $key){
                        $name = basename($dequeue_plugin->url_full); 
                        if(strpos($src,$name)){
                            $src = ''; 
                        }
                    }
                }
            }          
        }
        foreach((array)$dequeue_core_script as $dequeue_plugins => $dequeue_plugin){
            $dequeue_page = unserialize($dequeue_plugin->current_page);
            foreach($dequeue_page as $key => $value){
                if($value == 'true'){
                    if($page == $key){
                        $name = basename($dequeue_plugin->url_full); 
                        if(strpos($src,$name)){
                            $src = ''; 
                        }
                    }
                }
            }
        }
        return $src;
    }

}

add_action('plugins_loaded', function(){new AssetView();}, 1);