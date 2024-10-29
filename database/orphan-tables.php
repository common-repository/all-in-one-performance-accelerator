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

	
class OrphanTables
{
	protected static $instance = null,$plugin;
	public $limit_details = 500;

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
       
        add_action('wp_ajax_get_orphan_tables_options', array($this,'get_orphan_count'));
        add_action('wp_ajax_get_orphan_view_list', array($this,'get_orphan_view_list'));  
        add_action('wp_ajax_delete_orphan_list', array($this,'delete_orphan_list'));  
		add_action('wp_ajax_get_modified_tables', array($this,'get_modified_tables'));  
		add_action('wp_ajax_get_orphant_selected_tab', array($this,'get_orphant_selected_tab'));  
    }

	public function get_orphant_selected_tab(){
		$tab = sanitize_text_field($_POST['tab']);
		if($tab === 'undefined'){
			$tab_value = get_option('smack_orphant_tab');
			if(empty($tab_value)){
				$tab_name = 'post';
				update_option('smack_orphant_tab',$tab_name);
			}else{
				update_option('smack_orphant_tab',$tab_value);
			}
		}else{
			update_option('smack_orphant_tab',$tab);
		}
		$tab_address = get_option('smack_orphant_tab');
		$result['tab'] = $tab_address;
		$result['success'] = true;
		echo wp_json_encode($result);
		wp_die();
	}

	public function get_orphan_count(){

        global $wpdb;
        $result['orphan_postmeta'] = $wpdb->get_var( "SELECT COUNT(meta_id) FROM $wpdb->postmeta WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts)" );
        $result['orphan_commentmeta']=$wpdb->get_var( "SELECT COUNT(meta_id) FROM $wpdb->commentmeta WHERE comment_id NOT IN (SELECT comment_ID FROM $wpdb->comments)" );
        $result['orphan_usermeta']=$wpdb->get_var( "SELECT COUNT(umeta_id) FROM $wpdb->usermeta WHERE user_id NOT IN (SELECT ID FROM $wpdb->users)" );
        $result['orphan_termmeta']=$wpdb->get_var( "SELECT COUNT(meta_id) FROM $wpdb->termmeta WHERE term_id NOT IN (SELECT term_id FROM $wpdb->terms)" );
        $orphan_term_relationships_sql = implode( "','", array_map( 'esc_sql', $this->get_excluded_taxonomies() ) );
       $result['orphan_term_relationships'] = $wpdb->get_var( "SELECT COUNT(object_id) FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy NOT IN ('$orphan_term_relationships_sql') AND tr.object_id NOT IN (SELECT ID FROM $wpdb->posts)" ); // phpcs:ignore
        $result['unused_terms']=$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(t.term_id) FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.count = %d AND t.term_id NOT IN (" . implode( ',', $this->get_excluded_termids() ) . ')', 0 ) ); // phpcs:ignore
        $postmeta_query = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT(meta_id) AS count FROM $wpdb->postmeta GROUP BY post_id, meta_key, meta_value HAVING count > %d", 1 ) );
        if ( is_array( $postmeta_query ) ) {
            $result['duplicated_postmeta']=array_sum( array_map( 'intval', $postmeta_query ) );
        }
        $commentsmeta_query = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT(meta_id) AS count FROM $wpdb->commentmeta GROUP BY comment_id, meta_key, meta_value HAVING count > %d", 1 ) );
		if ( is_array( $commentsmeta_query ) ) {
             $result['duplicated_commentmeta']=array_sum( array_map( 'intval', $commentsmeta_query ) );
		}
        $usermeta_query = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT(umeta_id) AS count FROM $wpdb->usermeta GROUP BY user_id, meta_key, meta_value HAVING count > %d", 1 ) );
        if ( is_array( $usermeta_query ) ) {
            $result['duplicated_usermeta'] = array_sum( array_map( 'intval', $usermeta_query ) );
        }
        $termmeta_query = $wpdb->get_col( $wpdb->prepare( "SELECT COUNT(meta_id) AS count FROM $wpdb->termmeta GROUP BY term_id, meta_key, meta_value HAVING count > %d", 1 ) );
		if ( is_array( $termmeta_query ) ) {
			$result['duplicated_termmeta'] = array_sum( array_map( 'intval', $termmeta_query ) );
		}
        $result['oembed_postmeta']=$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(meta_id) FROM $wpdb->postmeta WHERE meta_key LIKE(%s)", '%_oembed_%' ) );
        $result['sucess']='true';
    
        echo wp_json_encode($result);
        wp_die();     
	}
    
    public function get_orphan_view_list(){
        global $wpdb;

		$details = array();
        $list=$_POST['list'];
		switch ( $list ) {
			case 'orphan_postmeta':
				$orphan_post_details = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM $wpdb->postmeta WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts) LIMIT %d", $this->limit_details ) );
                $result['orphan_postmeta']=$orphan_post_details;
                break;
			case 'orphan_commentmeta':
				$orphan_comments_details = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM $wpdb->commentmeta WHERE comment_id NOT IN (SELECT comment_ID FROM $wpdb->comments) LIMIT %d", $this->limit_details ) );
                $result['orphan_commentmeta']=$orphan_comments_details;
                break;
			case 'orphan_usermeta':
				$orphan_user_details = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM $wpdb->usermeta WHERE user_id NOT IN (SELECT ID FROM $wpdb->users) LIMIT %d", $this->limit_details ) );
                $result['orphan_usermeta']=$orphan_user_details;
                break;
			case 'orphan_termmeta':
				$orphan_term_details = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM $wpdb->termmeta WHERE term_id NOT IN (SELECT term_id FROM $wpdb->terms) LIMIT %d", $this->limit_details ) );
                $result['orphan_termmeta']=$orphan_term_details;
                break;
			case 'orphan_term_relationships':
				$orphan_term_relationships_sql = implode( "','", array_map( 'esc_sql', $this->get_excluded_taxonomies() ) );
				$orphan_relationship_details = $wpdb->get_col( $wpdb->prepare( "SELECT tt.taxonomy FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy NOT IN ('$orphan_term_relationships_sql') AND tr.object_id NOT IN (SELECT ID FROM $wpdb->posts) LIMIT %d", $this->limit_details ) ); // phpcs:ignore
				$result['orphan_term_relationships']=$orphan_relationship_details;
                break;
			case 'unused_terms':
				$unused_details = $wpdb->get_col( $wpdb->prepare( "SELECT t.name FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.count = %d AND t.term_id NOT IN (" . implode( ',', $this->get_excluded_termids() ) . ') LIMIT %d', 0, $this->limit_details ) ); // phpcs:ignore
                $result['unused_terms']=$unused_details;
                break;
			case 'duplicated_postmeta':
				$query   = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(meta_id) AS count, meta_key FROM $wpdb->postmeta GROUP BY post_id, meta_key, meta_value HAVING count > %d LIMIT %d", 1, $this->limit_details ) );
				$details = array();
				if ( $query ) {
					foreach ( $query as $meta ) {
						$duplicate_post_details[] = $meta->meta_key;
					}
				}
                $result['duplicated_postmeta']=$duplicate_post_details;
				break;
			case 'duplicated_commentmeta':
				$query   = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(meta_id) AS count, meta_key FROM $wpdb->commentmeta GROUP BY comment_id, meta_key, meta_value HAVING count > %d LIMIT %d", 1, $this->limit_details ) );
				$details = array();
				if ( $query ) {
					foreach ( $query as $meta ) {
						$duplicate_comment_details[] = $meta->meta_key;
					}
				}
                $result['duplicated_commentmeta']=$duplicate_comment_details;
				break;
			case 'duplicated_usermeta':
				$query   = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(umeta_id) AS count, meta_key FROM $wpdb->usermeta GROUP BY user_id, meta_key, meta_value HAVING count > %d LIMIT %d", 1, $this->limit_details ) );
				$details = array();
				if ( $query ) {
					foreach ( $query as $meta ) {
						$duplicate_user_details[] = $meta->meta_key;
					}
				}
                $result['duplicated_usermeta']=$duplicate_user_details;
				break;
			case 'duplicated_termmeta':
				$query   = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(meta_id) AS count, meta_key FROM $wpdb->termmeta GROUP BY term_id, meta_key, meta_value HAVING count > %d LIMIT %d", 1, $this->limit_details ) );
				$details = array();
				if ( $query ) {
					foreach ( $query as $meta ) {
						$duplicate_term_details[] = $meta->meta_key;
					}
				}
                $result['duplicated_termmeta']=$duplicate_term_details;
				break;
			case 'oembed_postmeta':
				$oembed_post_details = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM $wpdb->postmeta WHERE meta_key LIKE(%s) LIMIT %d", '%_oembed_%', $this->limit_details ) );
                $result['oembed_postmeta']=$oembed_post_details;
                break;
		}
        $result['sucess']='true';
        echo wp_json_encode($result);
        wp_die(); 
    }

	public function delete_orphan_list(){
		global $wpdb;
		$list=$_POST['delete_list'];
		switch ( $list ) {
			case 'orphan_postmeta':
				$query = $wpdb->get_results( "SELECT post_id, meta_key FROM $wpdb->postmeta WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts)" );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$post_id = (int) $meta->post_id;
						if ( 0 === $post_id ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $post_id, $meta->meta_key ) );
						} else {
							delete_post_meta( $post_id, $meta->meta_key );
						}
					}

				}
				break;
			case 'orphan_commentmeta':
				$query = $wpdb->get_results( "SELECT comment_id, meta_key FROM $wpdb->commentmeta WHERE comment_id NOT IN (SELECT comment_ID FROM $wpdb->comments)" );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$comment_id = (int) $meta->comment_id;
						if ( 0 === $comment_id ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->commentmeta WHERE comment_id = %d AND meta_key = %s", $comment_id, $meta->meta_key ) );
						} else {
							delete_comment_meta( $comment_id, $meta->meta_key );
						}
					}
				}
				break;
			case 'orphan_usermeta':
				$query = $wpdb->get_results( "SELECT user_id, meta_key FROM $wpdb->usermeta WHERE user_id NOT IN (SELECT ID FROM $wpdb->users)" );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$user_id = (int) $meta->user_id;
						if ( 0 === $user_id ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id = %d AND meta_key = %s", $user_id, $meta->meta_key ) );
						} else {
							delete_user_meta( $user_id, $meta->meta_key );
						}
					}

				}
				break;
			case 'orphan_termmeta':
				$query = $wpdb->get_results( "SELECT term_id, meta_key FROM $wpdb->termmeta WHERE term_id NOT IN (SELECT term_id FROM $wpdb->terms)" );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$term_id = (int) $meta->term_id;
						if ( 0 === $term_id ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->termmeta WHERE term_id = %d AND meta_key = %s", $term_id, $meta->meta_key ) );
						} else {
							delete_term_meta( $term_id, $meta->meta_key );
						}
					}

				}
				break;
			case 'orphan_term_relationships':
				$query = $wpdb->get_results( "SELECT tr.object_id, tr.term_taxonomy_id, tt.term_id, tt.taxonomy FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy NOT IN ('" . implode( '\',\'', $this->get_excluded_taxonomies() ) . "') AND tr.object_id NOT IN (SELECT ID FROM $wpdb->posts)" ); // phpcs:ignore
				if ( $query ) {
					foreach ( $query as $tax ) {
						$wp_remove_object_terms = wp_remove_object_terms( (int) $tax->object_id, (int) $tax->term_id, $tax->taxonomy );
						if ( true !== $wp_remove_object_terms ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id = %d", $tax->object_id, $tax->term_taxonomy_id ) );
						}
					}

				}
				break;
			case 'unused_terms':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT tt.term_taxonomy_id, t.term_id, tt.taxonomy FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.count = %d AND t.term_id NOT IN (" . implode( ',', $this->get_excluded_termids() ) . ')', 0 ) ); // phpcs:ignore
				if ( $query ) {
					$check_wp_terms = false;
					foreach ( $query as $tax ) {
						if ( taxonomy_exists( $tax->taxonomy ) ) {
							wp_delete_term( (int) $tax->term_id, $tax->taxonomy );
						} else {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d", (int) $tax->term_taxonomy_id ) );
							$check_wp_terms = true;
						}
					}
					// We need this for invalid taxonomies.
					if ( $check_wp_terms ) {
						$wpdb->get_results( "DELETE FROM $wpdb->terms WHERE term_id NOT IN (SELECT term_id FROM $wpdb->term_taxonomy)" );
					}

				}
				break;
			case 'duplicated_postmeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT GROUP_CONCAT(meta_id ORDER BY meta_id DESC) AS ids, post_id, COUNT(*) AS count FROM $wpdb->postmeta GROUP BY post_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$ids = array_map( 'intval', explode( ',', $meta->ids ) );
						array_pop( $ids );
						$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_id IN (" . implode( ',', $ids ) . ') AND post_id = %d', (int) $meta->post_id ) ); // phpcs:ignore
					}

				}
				break;
			case 'duplicated_commentmeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT GROUP_CONCAT(meta_id ORDER BY meta_id DESC) AS ids, comment_id, COUNT(*) AS count FROM $wpdb->commentmeta GROUP BY comment_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$ids = array_map( 'intval', explode( ',', $meta->ids ) );
						array_pop( $ids );
						$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->commentmeta WHERE meta_id IN (" . implode( ',', $ids ) . ') AND comment_id = %d', (int) $meta->comment_id ) ); // phpcs:ignore
					}

				}
				break;
			case 'duplicated_usermeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT GROUP_CONCAT(umeta_id ORDER BY umeta_id DESC) AS ids, user_id, COUNT(*) AS count FROM $wpdb->usermeta GROUP BY user_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$ids = array_map( 'intval', explode( ',', $meta->ids ) );
						array_pop( $ids );
						$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE umeta_id IN (" . implode( ',', $ids ) . ') AND user_id = %d', (int) $meta->user_id ) ); // phpcs:ignore
					}

				}
				break;
			case 'duplicated_termmeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT GROUP_CONCAT(meta_id ORDER BY meta_id DESC) AS ids, term_id, COUNT(*) AS count FROM $wpdb->termmeta GROUP BY term_id, meta_key, meta_value HAVING count > %d", 1 ) );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$ids = array_map( 'intval', explode( ',', $meta->ids ) );
						array_pop( $ids );
						$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->termmeta WHERE meta_id IN (" . implode( ',', $ids ) . ') AND term_id = %d', (int) $meta->term_id ) ); // phpcs:ignore
					}

				}
				break;
			case 'oembed_postmeta':
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key FROM $wpdb->postmeta WHERE meta_key LIKE(%s)", '%_oembed_%' ) );
				if ( $query ) {
					foreach ( $query as $meta ) {
						$post_id = (int) $meta->post_id;
						if ( 0 === $post_id ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $post_id, $meta->meta_key ) );
						} else {
							delete_post_meta( $post_id, $meta->meta_key );
						}
					}
				}
				break;
		}
		$result['sucess']='true';
        echo wp_json_encode($result);
        wp_die(); 
	}
    private function get_excluded_taxonomies() {
		$excluded_taxonomies   = array();
		$excluded_taxonomies[] = 'link_category';

		return apply_filters( 'wp_sweep_excluded_taxonomies', $excluded_taxonomies );
	}
	
    private function get_excluded_termids() {
		$default_term_ids = $this->get_default_taxonomy_termids();
		if ( ! is_array( $default_term_ids ) ) {
			$default_term_ids = array();
		}
		$parent_term_ids = $this->get_parent_termids();
		if ( ! is_array( $parent_term_ids ) ) {
			$parent_term_ids = array();
		}
		$excluded_termids = array_merge( $default_term_ids, $parent_term_ids );
		return apply_filters( 'wp_sweep_excluded_termids', $excluded_termids );
	}

    private function get_default_taxonomy_termids() {
		$taxonomies       = get_taxonomies();
		$default_term_ids = array();
		if ( $taxonomies ) {
			$tax = array_keys( $taxonomies );
			if ( $tax ) {
				foreach ( $tax as $t ) {
					$term_id = (int) get_option( 'default_' . $t );
					if ( $term_id > 0 ) {
						$default_term_ids[] = $term_id;
					}
				}
			}
		}
		return $default_term_ids;
	}

    private function get_parent_termids() {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare( "SELECT tt.parent FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.parent > %d", 0 ) );
	}

	public function get_modified_tables(){

		global $wpdb;
		
		require_once(ABSPATH.'/wp-admin/includes/schema.php');
	
		$wp_db_schema = wp_get_db_schema('all');
		
	// Separate individual queries into an array
	  $queries = explode(';', $wp_db_schema);
	  if (''==$queries[count($queries)-1]) {
		array_pop($queries);
	  }
	
	  $wpTablesList = array();
	
	  foreach ($queries as $query) {
		if (preg_match("|CREATE TABLE ([^ ]*)|", $query, $matches)) {
		  $wpTablesList[trim( strtolower($matches[1]), '`' )] = $query;
		}
	  }
	
	  $changedTables = array();
	  $i = 1;
	  foreach ($wpTablesList as $table=>$createQuery) {
		update_option('pgc_scanprogress_current', $i);
		update_option('pgc_scanprogress_status', $table);
	
		// orginal structure columns list
		$origColumns = pgc_extract_field_names($createQuery);
	
		// fact structrue columns list
		$query = "describe $table";
		$factColumns = $wpdb->get_results($query);
		
		foreach ($factColumns as $factColumn) {
		  if (!isset($origColumns[strtolower($factColumn->Field)])) {
			if (!isset($changedTables[$table])) {
			  $changedTables[$table] = array();
			}        
		
			$changedTables[$table][$factColumn->Field] = new \stdClass();
			$changedTables[$table][$factColumn->Field]->plugin_name = '';
			$changedTables[$table][$factColumn->Field]->plugin_state = '';
		  }
		}
	  } 
	  if (count($changedTables)>0) {
		$modified_tables = [];
		$temp = 0;
		foreach ($changedTables as $tableName=>$columnData) {
			foreach ($columnData as $column=>$plugin) {
				$modified_tables[$temp]['table_name']=$tableName;
				$modified_tables[$temp]['Extra_table']=$column;
				$temp++;
				
			}
			
		}
	  }
	  echo wp_json_encode([
		'response' => [
			'modified_tables'=>$modified_tables,
		],
		'status' => 200,
		'success' => true,
	]);
	wp_die();
	  
	}
}
