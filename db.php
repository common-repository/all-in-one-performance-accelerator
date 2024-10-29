<?php

defined( 'ABSPATH' ) || die();

$backtrace = ABSPATH."wp-content/plugins/all-in-one-performance-accelerator/Query-moniter/Backtrace.php";

if ( ! is_readable( $backtrace ) ) {
	return;
}
require_once $backtrace;

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

class DB extends wpdb {

	public $qm_php_vars = array(
		'max_execution_time'  => null,
		'memory_limit'        => null,
		'upload_max_filesize' => null,
		'post_max_size'       => null,
		'display_errors'      => null,
		'log_errors'          => null,
	);

	/**
	 * Class constructor
	 */
	public function __construct( $dbuser, $dbpassword, $dbname, $dbhost ) {

		foreach ( $this->qm_php_vars as $setting => &$val ) {
			$val = ini_get( $setting );
		}

		parent::__construct( $dbuser, $dbpassword, $dbname, $dbhost );

	}

	/**
	 * Performs a MySQL database query, using current database connection.
	 *
	 * @see wpdb::query()
	 *
	 * @param string $query Database query
	 * @return int|bool Boolean true for CREATE, ALTER, TRUNCATE and DROP queries. Number of rows
	 *                  affected/selected for all other queries. Boolean false on error.
	 */
	public function query( $query ) {
		if ( ! $this->ready ) {
			if ( isset( $this->check_current_query ) ) {
				// This property was introduced in WP 4.2
				$this->check_current_query = true;
			}
			return false;
		}

		if ( $this->show_errors ) {
			$this->hide_errors();
		}

		$result = parent::query( $query );
		$i      = $this->num_queries - 1;

		if ( ! isset( $this->queries[ $i ] ) ) {
			return $result;
		}
		
		$this->queries[ $i ]['trace'] = new \Backtrace( array(
			'ignore_frames' => 1,
		) );

		if ( ! isset( $this->queries[ $i ][3] ) ) {
			$this->queries[ $i ][3] = $this->time_start;
		}

		if ( $this->last_error ) {
			$code = 'qmdb';
			if ( $this->use_mysqli ) {
				if ( $this->dbh instanceof mysqli ) {
					$code = mysqli_errno( $this->dbh );
				}
			} else {
				if ( is_resource( $this->dbh ) ) {
					// Please do not report this code as a PHP 7 incompatibility. Observe the surrounding logic.
					// phpcs:ignore
					$code = mysql_errno( $this->dbh );
				}
			}
			$this->queries[ $i ]['result'] = new WP_Error( $code, $this->last_error );
		} else {
			$this->queries[ $i ]['result'] = $result;
		}

		return $result;
	}

}

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$wpdb = new DB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
