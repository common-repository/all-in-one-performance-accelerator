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


class SmackQuery {

	private $display = true;
	private $starttime, $servertime, $css, $ajax,$enqueued_styles='',$data;
	protected static $instance = null,$plugin;
	public $query_row = 0;
    public $total_time = 0;
    public $total_qs = 0;
	public static function getInstance() {
      
		if ( null == self::$instance ) {
			self::$instance = new self;
			self::$plugin = Plugin::getInstance();
			self::$instance->doHooks();
		}
		return self::$instance;
	}

	public function doHooks(){
		add_action('wp_ajax_get_query_informations', array($this,'get_query_info'));
		
	}

	function __construct() {
		$css='.query-dd{display:block;line-height:24px;background-color:#c4c4c4;color:#000;font-family:Helvetica,Arial,sans-serif;font-size:18px;text-align:center}.query-bs{margin:0 45px}.query-ss{margin:0 4px;border-left:5px solid #999}#query_dd{position:fixed;left:50%;bottom:0;z-index:9999998;margin-left:-160px;width:475px;height:26px;white-space:nowrap;overflow:hidden;background-color:rgb(159 188 253 / 50%);border-radius:6px;margin-bottom:21px;padding:2px}';
		$this->css = '<style type="text/css" scoped>' . str_replace(array("\r","\n","\t"), '', $css);
		$this->ajax = 'false';

		// give plugins/themes lots of time to set NO_SmackQuery_DISPLAY constant
		add_action('init', array($this, 'hook_setup'), 9999);

		// hooks for things where usage display should be suppressed
		add_filter('wp_xmlrpc_server_class', array($this, 'no_query_display'));
		add_filter('rest_send_nocache_headers', array($this, 'no_query_display'));
	}
	
	

	function hook_setup() {
		// allow a theme/plugin to disable SmackQuery display
		global $wpdb;
		// error_log(print_r(["hook_setup"],true),3,'/var/www/html/ram.log');
		$id_to_check = 1; // Replace this with the actual id value you want to check
		$displayQueryConditions='';
		$in_value_check = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT display_query FROM {$wpdb->prefix}aio_asset_table_entery WHERE id = %d",
				$id_to_check
			)
		);

		// Check if the query was successful
		if ($in_value_check !== false) {
			// Display or process the result
			foreach ($in_value_check as $result) {
				// Assuming display_query is a column in the result set
				$displayQueryConditions=$result->display_query;
			}
		} else {
			// Handle the case where the query failed
			$wpdb_last_error = $wpdb->last_error;
			error_log("Database error: $wpdb_last_error");
		}



		if (!defined('NO_SmackQuery_DISPLAY')) {
			
			if (defined('WP_ADMIN')) {
				add_action('admin_init', array($this, 'smack_time_to_first_byte'), 9999);
				if($displayQueryConditions=='displayAdmindisplayStatic' || $displayQueryConditions=='displayAdmin' || $displayQueryConditions=='' || $displayQueryConditions==NULL || $displayQueryConditions=='displayAdminno' )
					add_action('admin_footer', function(){register_shutdown_function(array($this, 'query_usage'));}, 9999);
			}
			else {
				add_action('wp_loaded', array($this, 'smack_time_to_first_byte'), 9999);
				if($displayQueryConditions=='displayAdmindisplayStatic' || $displayQueryConditions=='displayStatic' || $displayQueryConditions=='' || $displayQueryConditions==NULL  || $displayQueryConditions=='nodisplayStatic')
					add_action('wp_footer', function(){register_shutdown_function(array($this, 'query_usage'));}, 9999);
			}			
		}
	}

	// function works for filter or action hooks
	function no_query_display($val) {
		$this->display = false;
		return $val;
	}

	function smack_time_to_first_byte() {

		// theme display/customizer does some, um, unusual things...
		if (is_admin()) {
			$this->css .= '.wrap .theme-overlay .theme-wrap,.wrap .theme-overlay .theme-backdrop,.wrap .wp-full-overlay-sidebar-content,#customize-preview iframe{bottom:24px}';
		}
		//$server_array = filter_input_array(INPUT_SERVER, FILTER_SANITIZE_STRING);
		// PHP 5.4+ provides this value
		if (!empty($_SERVER['REQUEST_TIME_FLOAT'])) {
			$this->starttime = $_SERVER['REQUEST_TIME_FLOAT'];
			$this->servertime = strval(round(microtime(true) - $this->starttime, 2)) . '<span class="query-ss"></span>';
		}
	}

	function query_usage() {
		if ($this->display && !defined('WP_INSTALLING') && (!defined('DOING_AJAX') || (defined('DOING_AJAX') && $this->ajax))) {
			global $wpdb;
			
			$precision = 0;
			$memory_usage = memory_get_peak_usage() / 1048576;
			if ($memory_usage < 10) {
				$precision = 2;
			}
			else if ($memory_usage < 100) {
				$precision = 1;
			}

			$memory_usage = round($memory_usage, $precision);
			$time_usage = (empty($this->starttime)) ? '' : $this->servertime . round(microtime(true) - $this->starttime, 2);
			
            // echo ((defined('DOING_AJAX')) ? '' : $this->css . '</style><div id="query_dd_spacer"></div>') . '<div class="query-dd"' . ((defined('DOING_AJAX') && $this->ajax) ? '>' : ' id="query_dd">') . "Memory:{$memory_usage}M<span class=\"query-bs\">Time:{$time_usage}s</span>Queries:{$wpdb->num_queries}Q</div>";
            // echo ( $this->css . '</style><div id="query_dd_spacer"></div>') . '<div class="query-dd"' . ((defined('DOING_AJAX') && $this->ajax) ? '>' : ' id="query_dd">') . "Memory:{$memory_usage}M<span class=\"query-bs\">Time:{$time_usage}s</span>Queries:{$wpdb->num_queries}Q</div>";
			
			$class = array(
				'smack-no-js',
			);
	
			if ( did_action( 'wp_head' ) ) {
				$class[] = sprintf( 'smack-theme-%s', get_template() );
				$class[] = sprintf( 'smack-theme-%s', get_stylesheet() );
			}
	
			if ( ! is_admin_bar_showing() ) {
				$class[] = 'smack-peek';
			}
			if (is_user_logged_in()) {
				

				echo '<div id="query-view" class="' . implode( ' ', array_map( 'esc_attr', $class ) ) . '" dir="ltr">';
					echo '<div id="smack-side-resizer" class="smack-resizer"></div>';
					echo '<div id="smack-title" class="smack-resizer">';
						echo '<h1 class="smack-title-heading">' . esc_html__( 'Smack Query / Asset View', 'all-in-one' ) . '</h1>';
						echo '<button class="smack-title-button smack-button-container-position" aria-label="' . esc_html__( 'Toggle panel position', 'all-in-one' ) . '"><span class="dashicons dashicons-image-rotate-left" aria-hidden="true"></span></button>';
						echo '<button class="smack-title-button smack-button-container-close" aria-label="' . esc_attr__( 'Close Panel', 'all-in-one' ) . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>';
					echo '</div>';
					$this->query_output_view();
					$this->asset_output_view();
				echo '</div>';
			}
		}
	}
	public function smack_query_view($queryss){
        $unserialize=$queryss;
        foreach ( $unserialize as $name => $db ) {
			if ( is_a( $db, 'wpdb' ) ) {
				$response=$this->process_db( $name, $db );
            }
        }
    }
	public function get_query_info(){
       
        $this->data['total_qs']   = 0;
		$this->data['total_time'] = 0;
		$this->data['errors']     = array();
        // $this->db_objects = get_option('smack_db_queries');
        $this->db_objects = apply_filters( 'qm/collect/db_objects', array(
			'$wpdb' => $GLOBALS['wpdb'],
		) );
        $unserialize = $this->db_objects; //$this->custom_unserialize($this->db_objects);
        foreach ( $unserialize as $name => $db ) {
			if ( is_a( $db, 'wpdb' ) ) {
				$response=$this->process_db( $name, $db );
                $component_times=$this->query_status($response['component_times']);
                $caller_times=$this->caller_status($response['times']);
                $select_chart_report= $this->caller_report_status($response['times']);
                $time_chart_report= $this->chart_report_status($response['component_times']);
			} else {
				unset( $unserialize[ $name ] );
			}
		}
        global $wpdb;
		$id_to_check = 1; // Replace this with the actual id value you want to check
		$displayQueryConditions='';
		$in_value_check = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT display_query FROM {$wpdb->prefix}aio_asset_table_entery WHERE id = %d",
				$id_to_check
			)
		);

		// Check if the query was successful
		if ($in_value_check !== false) {
			// Display or process the result
			foreach ($in_value_check as $result) {
				// Assuming display_query is a column in the result set
				$displayQueryConditions=$result->display_query;
			}
		} else {
			// Handle the case where the query failed
			$wpdb_last_error = $wpdb->last_error;
			error_log("Database error: $wpdb_last_error");
		}
        echo wp_json_encode(['response' => ['time_chart_report' => $time_chart_report, 'plugin_query_details' => $component_times,'caller_details' => $caller_times,'caller_query_details'=>$select_chart_report ],'displayQueryConditions'=>$displayQueryConditions,'status' => 200, 'success' => true]);
        wp_die();  
    }
	public function chart_report_status($component){
		if(isset($component)){
			$name = array_column($component, 'component');
			$time = array_column($component, 'ltime');
			$result = array_combine($name, $time);
			return $result;
		}
        
    }

    public function caller_report_status($select){
        $name = array_column($select, 'caller');
        $time = array_column($select, 'ltime');
        $result = array_combine($name, $time);
        return $result;
    }

    public function query_status($plugins){
        $result_array = [];
        $temp = 0;
            foreach($plugins as $plugin){
            $result_array[$temp]['plugin'] = $plugin['component'];
            $result_array[$temp]['ltime'] = $plugin['ltime'];
            $result_array[$temp]['SELECT'] = isset($plugin['types']['SELECT']) ? $plugin['types']['SELECT'] : null ;
            $result_array[$temp]['INSERT'] = isset($plugin['types']['CREATE']) ? $plugin['types']['CREATE'] : null ;
            $result_array[$temp]['UPDATE'] = isset($plugin['types']['ALTER']) ? $plugin['types']['ALTER'] : null ;
            $result_array[$temp]['SHOW'] = isset($plugin['types']['SHOW']) ? $plugin['types']['SHOW'] : null ;
    
            $temp++;
            }
            return $result_array;  
    }

    public function caller_status($callers){
        $caller_array = [];
        $temp = 0;
            foreach($callers as $caller){
            $caller_array[$temp]['caller'] = $caller['caller'];
            $caller_array[$temp]['ltime'] = $caller['ltime'];
            $caller_array[$temp]['SELECT'] = isset($caller['types']['SELECT']) ? $caller['types']['SELECT'] : null ;
            $caller_array[$temp]['INSERT'] = isset($caller['types']['CREATE']) ? $caller['types']['CREATE'] : null ;
            $caller_array[$temp]['UPDATE'] = isset($caller['types']['ALTER']) ? $caller['types']['ALTER'] : null ;
            $caller_array[$temp]['SHOW'] = isset($caller['types']['SHOW']) ? $caller['types']['SHOW'] : null ;
    
            $temp++;
            }
        
            return $caller_array;  
    }
    public function process_db($id,$db){
        global $EZSQL_ERROR, $wp_the_query;
      
              //  With SAVEQUERIES defined as false, `wpdb::queries` is empty but `wpdb::num_queries` is not.
                if ( empty( $db->queries ) ) {
                    $this->data['total_qs'] += $db->num_queries;
                    return;
                }
        
                $rows       = array();
                $types      = array();
                $has_result = false;
                $has_trace  = false;
				$total_time = $this->total_time;
				$total_qs   = $this->total_qs;
                $i          = 0;
                $request    = isset($wp_the_query->request) ? trim( $wp_the_query->request ) :'';
               
                if ( method_exists( $db, 'remove_placeholder_escape' ) ) {
                    $request = $db->remove_placeholder_escape( $request );
                }
                foreach ( (array) $db->queries as $query ) {
                    # @TODO: decide what I want to do with this:
                    if ( false !== strpos( $query[2], 'wp_admin_bar' ) and !isset( $_REQUEST['qm_display_admin_bar'] ) ) { // phpcs:ignore
                        continue;
                    }
                    
                    $sql        = $query[0];
                    $ltime      = $query[1];
                    $stack      = $query[2];
                    $has_start  = isset( $query[3] );
                    $has_trace  = isset( $query['trace'] );
                    $has_result = isset( $query['result'] );
        
                    if ( $has_result ) {
                        $result = $query['result'];
                    } else {
                        $result = null;
                    }
        
                    $this->total_time += $ltime;
                  
                    if ( $has_trace ) {
        
                        $trace       = $query['trace'];
                       
                        $component   = $query['trace']->get_component();
                      
                        $caller      = $query['trace']->get_caller();
                        $caller_name = $caller['display'];
                        $caller      = $caller['display'];
        
                    } else {
        
                        $trace     = null;
                        $component = null;
                        $callers   = explode( ',', $stack );
                        $caller    = trim( end( $callers ) );
        
                        if ( false !== strpos( $caller, '(' ) ) {
                            $caller_name = substr( $caller, 0, strpos( $caller, '(' ) ) . '()';
                        } else {
                            $caller_name = $caller;
                        }
                    }
        
                    $sql  = trim( $sql );
                    $type = self::getting_queries_type( $sql );
        
                    $this->type_log( $type );
                    $this->caller_log( $caller_name, $ltime, $type );
        
                    $this->dupe_log( $sql, $i );
        
                    if ( $component ) {
                        $this->component_log( $component, $ltime, $type );
                    }
        
                    if ( ! isset( $types[ $type ]['total'] ) ) {
                        $types[ $type ]['total'] = 1;
                    } else {
                        $types[ $type ]['total']++;
                    }
        
                    if ( ! isset( $types[ $type ]['callers'][ $caller ] ) ) {
                        $types[ $type ]['callers'][ $caller ] = 1;
                    } else {
                        $types[ $type ]['callers'][ $caller ]++;
                    }
        
                    $is_main_query = ( $request === $sql && ( false !== strpos( $stack, ' WP->main,' ) ) );
        
                    $row = compact( 'caller', 'caller_name', 'sql', 'ltime', 'result', 'type', 'component', 'trace', 'is_main_query' );
        
                    if ( ! isset( $trace ) ) {
                        $row['stack'] = $stack;
                    }
        
                    if ( is_wp_error( $result ) ) {
                        $this->data['errors'][] = $row;
                    }
        
                    if ( self::is_expensive( $row ) ) {
                        $this->data['expensive'][] = $row;
                    }
        
                    $rows[ $i ] = $row;
                    $i++;
        
                }
        
                if ( '$wpdb' === $id && ! $has_result && ! empty( $EZSQL_ERROR ) && is_array( $EZSQL_ERROR ) ) {
                    // Fallback for displaying database errors when wp-content/db.php isn't in place

                    foreach ( $EZSQL_ERROR as $error ) {
                        $row = array(
                            'caller'      => null,
                            'caller_name' => null,
                            'stack'       => '',
                            'sql'         => $error['query'],
                            'ltime'       => 0,
                            'result'      => new \WP_Error( 'qmdb', $error['error_str'] ),
                            'type'        => '',
                            'component'   => false,
                            'trace'       => null,
                            'is_main_query' => false,
                        );
                        $this->data['errors'][] = $row;
                    }
                }
        
                $this->total_qs = count( $rows );
				if (!isset($this->data['total_qs'])) {
					$this->data['total_qs'] = 0;
				}
				
				if (!isset($this->data['total_time'])) {
					$this->data['total_time'] = 0;
				}
                $this->data['total_qs']   += $this->total_qs;
                $this->data['total_time'] += $this->total_time;
				// error_log(print_r(["total_qs"=>$this->data],true),3,'/var/www/html/ram.log');
              
                $has_main_query = wp_list_filter( $rows, array(
                    'is_main_query' => true,
                ) );

                $this->data['dbs'][ $id ] = (object) compact( 'rows', 'types', 'has_result', 'has_trace', 'total_time', 'total_qs', 'has_main_query' );
                // $serialized_data = $this->custom_serialize($this->data);
                // error_log(print_r(["thisdata"=>$this->data],true),3,'/var/www/html/ram.log');
                
                update_option('data_db','');
                return $this->data;
            
    }
	
	protected function type_log( $type ) {

		if ( isset( $this->data['types'][ $type ] ) ) {
			$this->data['types'][ $type ]++;
		} else {
			$this->data['types'][ $type ] = 1;
		}

	}	
	public static function is_expensive( array $row ) {
		return $row['ltime'] > 0.05;
	}
    protected function caller_log( $caller, $ltime, $type ) {

		if ( ! isset( $this->data['times'][ $caller ] ) ) {
			$this->data['times'][ $caller ] = array(
				'caller' => $caller,
				'ltime'  => 0,
				'types'  => array(),
			);
		}

		$this->data['times'][ $caller ]['ltime'] += $ltime;

		if ( isset( $this->data['times'][ $caller ]['types'][ $type ] ) ) {
			$this->data['times'][ $caller ]['types'][ $type ]++;
		} else {
			$this->data['times'][ $caller ]['types'][ $type ] = 1;
		}

	}

    protected function dupe_log( $sql, $i ) {

		$sql = str_replace( array( "\r\n", "\r", "\n" ), ' ', $sql );
		$sql = str_replace( array( "\t", '`' ), '', $sql );
		$sql = preg_replace( '/ +/', ' ', $sql );
		$sql = trim( $sql );
		$sql = rtrim( $sql, ';' );
		$this->data['dupes'][ $sql ][] = $i;

	}

    protected function component_log( $component, $ltime, $type ) {
        if ( ! isset( $this->data['component_times'][ $component->name ] ) ) {
            $this->data['component_times'][ $component->name ] = array(
                'component' => $component->name,
                'ltime'     => 0,
                'types'     => array(),
            );
        }
        $this->data['component_times'][ $component->name ]['ltime'] += $ltime;
        if ( isset( $this->data['component_times'][ $component->name ]['types'][ $type ] ) ) {
            $this->data['component_times'][ $component->name ]['types'][ $type ]++;
        } else {
            $this->data['component_times'][ $component->name ]['types'][ $type ] = 1;
        }

    }
	public static function getting_queries_type( $sql ) {
		// Trim leading whitespace and brackets
		$sql = ltrim( $sql, ' \t\n\r\0\x0B(' );

		if ( 0 === strpos( $sql, '/*' ) ) {
			// Strip out leading comments such as `/*NO_SELECT_FOUND_ROWS*/` before calculating the query type
			$sql = preg_replace( '|^/\*[^\*/]+\*/|', '', $sql );
		}

		$words = preg_split( '/\b/', trim( $sql ), 2, PREG_SPLIT_NO_EMPTY );
		$type = strtoupper( $words[0] );

		return $type;
	}
	public function query_output_view(){
		// $db = get_option('data_db');
		$overAllQuerys = apply_filters( 'qm/collect/db_objects', array(
			'$wpdb' => $GLOBALS['wpdb'],
		) ); 
		$this->smack_query_view($overAllQuerys);
		$db=$this->data;
		$this->query_row = 0;
		echo '<div id="smack-panels" class="smack_query_table_view" style="display:none">';
			echo '<div class="smack smack-panel-show">';
				echo '<table class="smack-sortable">';
					echo '<thead>';
						echo '<tr>';
							echo '<th scope="col" class="smack-sorted-asc smack-sortable-column" role="columnheader" aria-sort="ascending">';
								echo esc_html__( 'no', 'all-in-one' ); // WPCS: XSS ok;
							echo '</th>';
							echo '<th scope="col" class="smack-filterable-column">'; 
								echo '<div class="smack-filter-container">';
									echo '<label for="smack-query-view">'.esc_html__( 'Query') . '</label>';
									echo '<select id="smack-query-view" class="smack-filter"  data-filter="type">';
										echo '<option value="">' . esc_html_x( 'All', '"All" option for filters', 'all-in-one' ) . '</option>';
										echo '<option value="' . esc_attr( 'SHOW' ) . '">' . esc_html( 'SHOW' ) . '</option>';
										echo '<option value="' . esc_attr( 'SELECT' ) . '">' . esc_html( 'SELECT' ) . '</option>';
										echo '<option value="' . esc_attr( 'DELETE' ) . '">' . esc_html( 'DELETE' ) . '</option>';
										echo '<option value="' . esc_attr( 'CREATE' ) . '">' . esc_html( 'CREATE' ) . '</option>';
									echo '</select>';
								echo '</div>';
							echo '</th>';
							echo '<th scope="col">' . esc_html__( 'Callers', 'all-in-one' ) . '</th>';
							echo '<th scope="col">' . esc_html__( 'Components', 'all-in-one' ) . '</th>';
							echo '<th scope="col">' . esc_html__( 'rows', 'all-in-one' ) . '</th>';
							echo '<th scope="col">' . esc_html__( 'time', 'all-in-one' ) . '</th>';
						echo '</tr>';
					echo '</thead>';
					echo '<tbody>';
						foreach ( $db['dbs']['$wpdb']->rows as $row ) {
							$type    = $this->get_query_type( $row['sql'] );
							$sql_out = $this->format_sql( $row['sql'] );
							if ( 'SELECT' !== $type ) {
								$sql_out = "<span class='smack-nonselectsql'>{$sql_out}</span>";
							}
							echo '<tr data-smack-type="'.esc_attr($type).'">';
								echo '<td class="smack-row-num smack-num">';
									echo intval( ++$this->query_row );
								echo'</td>';
								echo '<td class="smack-row-sql smack-ltr smack-wrap">';
									echo $sql_out; // WPCS: XSS ok;
								echo '</td>';
								echo '<td>';
									echo $row['caller_name']; 
								echo '</td>';
								echo '<td>';
									echo isset($row['component']) ? $row['component']->name : 'Default Name';
								echo'</td>';
								echo '<td>';
									echo $row['result'];
								echo'</td>';
								echo '<td>';
									$stime = number_format_i18n( $row['ltime'], 4 );
									echo $stime;
								echo'</td>';
							echo '</tr>';
						}
					echo '</tbody>';
				echo '</table>';
			echo '</div>';
		echo '</div>';
	}

	public static function get_query_type( $sql ) {
		
		$sql = ltrim( $sql, ' \t\n\r\0\x0B(' );

		if ( 0 === strpos( $sql, '/*' ) ) {
			
			$sql = preg_replace( '|^/\*[^\*/]+\*/|', '', $sql );
		}

		$words = preg_split( '/\b/', trim( $sql ), 2, PREG_SPLIT_NO_EMPTY );
		$type = strtoupper( $words[0] );

		return $type;
	}
	
	public static function format_sql( $sql ) {

		$sql = str_replace( array( "\r\n", "\r", "\n", "\t" ), ' ', $sql );
		$sql = esc_html( $sql );
		$sql = trim( $sql );

		$regex = 'ADD|AFTER|ALTER|AND|BEGIN|COMMIT|CREATE|DELETE|DESCRIBE|DO|DROP|ELSE|END|EXCEPT|EXPLAIN|FROM|GROUP|HAVING|INNER|INSERT|INTERSECT|LEFT|LIMIT|ON|OR|ORDER|OUTER|RENAME|REPLACE|RIGHT|ROLLBACK|SELECT|SET|SHOW|START|THEN|TRUNCATE|UNION|UPDATE|USE|USING|VALUES|WHEN|WHERE|XOR';
		$sql   = preg_replace( '# (' . $regex . ') #', '<br> $1 ', $sql );

		$keywords = '\b(?:ACTION|ADD|AFTER|ALTER|AND|ASC|AS|AUTO_INCREMENT|BEGIN|BETWEEN|BIGINT|BINARY|BIT|BLOB|BOOLEAN|BOOL|BREAK|BY|CASE|COLLATE|COLUMNS?|COMMIT|CONTINUE|CREATE|DATA(?:BASES?)?|DATE(?:TIME)?|DECIMAL|DECLARE|DEC|DEFAULT|DELAYED|DELETE|DESCRIBE|DESC|DISTINCT|DOUBLE|DO|DROP|DUPLICATE|ELSE|END|ENUM|EXCEPT|EXISTS|EXPLAIN|FIELDS|FLOAT|FOREIGN|FOR|FROM|FULL|FUNCTION|GROUP|HAVING|IF|IGNORE|INDEX|INNER|INSERT|INTEGER|INTERSECT|INTERVAL|INTO|INT|IN|IS|JOIN|KEYS?|LEFT|LIKE|LIMIT|LONG(?:BLOB|TEXT)|MEDIUM(?:BLOB|INT|TEXT)|MERGE|MIDDLEINT|NOT|NO|NULLIF|ON|ORDER|OR|OUTER|PRIMARY|PROC(?:EDURE)?|REGEXP|RENAME|REPLACE|RIGHT|RLIKE|ROLLBACK|SCHEMA|SELECT|SET|SHOW|SMALLINT|START|TABLES?|TEXT(?:SIZE)?|THEN|TIME(?:STAMP)?|TINY(?:BLOB|INT|TEXT)|TRUNCATE|UNION|UNIQUE|UNSIGNED|UPDATE|USE|USING|VALUES?|VAR(?:BINARY|CHAR)|WHEN|WHERE|WHILE|XOR)\b';
		$sql      = preg_replace( '#' . $keywords . '#', '<b>$0</b>', $sql );

		return '<code>' . $sql . '</code>';

	}

	public function asset_output_view()
	{
		$page = home_url($_SERVER['REQUEST_URI']);
		$page_value = parse_url($page);
		// $current_page = $page_value['path'] . isset($page_value['query']) ?  $page_value['query'] :'';

		// Step 1: Get enqueued scripts and styles for the current page
		$enqueued_styles = wp_styles()->queue;
		$enqueued_scripts = wp_scripts()->queue;
		echo '<div id="smack-wrapper">';
		echo '<nav id="smack-panel-menu" class="smack_asset_view" style="display:none;width:15%" aria-labelledby="smack-panel-menu-caption" style="width:15%">';
		echo '<ul role="tablist">';

		$ids = []; // Initialize the $ids array
		$scriptandcss=[];
		$scriptandcss_versions=[];
		echo '<li role="presentation"><button class="smack_button" role="tab" id="styles" data-smack-href="styles" value="styles"> styles</button></li>';
		echo '<li role="presentation"><button class="smack_button" role="tab" id="scripts" data-smack-href="scripts" value="scripts">scripts</button></li>';
		echo '</ul>';
		echo '</nav>';
		echo '<div id="smack-panels" class="smack_asset_view" style="display:none">';
		echo '<div id="smack-panels-styles" class="smack-panels" style="display:none">';	
		echo '<div class="smack">';
		echo '<table class="smack-sortable">';
		echo '<thead>';
		echo '<tr>';
		echo '<th scope="col">' . esc_html__('source', 'all-in-one') . '</th>';
		echo '<th scope="col">' . esc_html__('type', 'all-in-one') . '</th>';
		echo '<th scope="col">' . esc_html__('version', 'all-in-one') . '</th>';
		// echo '<th scope="col">' . esc_html__('size', 'all-in-one') . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';
		// Add your logic to display information for the current asset
		foreach ($enqueued_styles as $style_handle) {
			$style = wp_styles()->registered[$style_handle];
			echo '<tr>';
			echo '<td>' . esc_html($style->handle) . '</td>';
			echo '<td>' . esc_html($style->src) . '</td>';
			echo '<td>' . esc_html($style->ver) . '</td>'; // Access the 'type' information
			// echo '<td>' . esc_html($script->extra['data']->ver) . '</td>'; // Access the 'version' information
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';
		echo '</div>';
		echo '</div>';
		echo '<div id="smack-panels-scripts" class="smack-panels" style="display:none">';	
		echo '<div class="smack">';
		echo '<table class="smack-sortable">';
		echo '<thead>';
		echo '<tr>';
		echo '<th scope="col">' . esc_html__('source', 'all-in-one') . '</th>';
		echo '<th scope="col">' . esc_html__('type', 'all-in-one') . '</th>';
		echo '<th scope="col">' . esc_html__('version', 'all-in-one') . '</th>';
		// echo '<th scope="col">' . esc_html__('size', 'all-in-one') . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';
		// Add your logic to display information for the current asset
		foreach ($enqueued_scripts as $script_handle) {
			if(!empty(wp_scripts()->registered[$script_handle])){
				$script = wp_scripts()->registered[$script_handle];
				echo '<tr>';
				echo '<td>' . esc_html($script->handle) . '</td>';
				echo '<td>' . esc_html($script->src) . '</td>';
				echo '<td>' . esc_html($script->ver) . '</td>'; // Access the 'type' information
				// echo '<td>' . esc_html($script->extra['data']->ver) . '</td>'; // Access the 'version' information
				echo '</tr>';
			}
		}
		echo '</tbody>';
		echo '</table>';
		echo '</div>';
		echo '</div>';
		
		echo "</div>";
		echo '</div>';
		
		echo '</div>';
	}

}

add_action('plugins_loaded', function(){new SmackQuery();}, 1);
