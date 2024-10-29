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

class Cron_status
{
	public $schedules;
	public $crons;
	public $last_missed_cron     = null;
	public $last_late_cron       = null;
	private $timeout_missed_cron = null;
	private $timeout_late_cron   = null;

	
	public function __construct() {
		$this->init();

		$this->timeout_late_cron   = 0;
		$this->timeout_missed_cron = - 5 * MINUTE_IN_SECONDS;

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$this->timeout_late_cron   = - 15 * MINUTE_IN_SECONDS;
			$this->timeout_missed_cron = - 1 * HOUR_IN_SECONDS;
		}
	}

	
	public function init() {
		$this->schedules = wp_get_schedules();
		$this->get_cron_tasks();
	}

	
	private function get_cron_tasks() {
		$cron_tasks = _get_cron_array();

		if ( empty( $cron_tasks ) ) {
			$this->crons = new WP_Error( 'no_tasks', __( 'No scheduled events exist on this site.', 'health-check' ) );
			return;
		}

		$this->crons = array();

		foreach ( $cron_tasks as $time => $cron ) {
			foreach ( $cron as $hook => $dings ) {
				foreach ( $dings as $sig => $data ) {

					$this->crons[ "$hook-$sig-$time" ] = (object) array(
						'hook'     => $hook,
						'time'     => $time,
						'sig'      => $sig,
						'args'     => $data['args'],
						'schedule' => $data['schedule'],
						'interval' => isset( $data['interval'] ) ? $data['interval'] : null,
					);

				}
			}
		}
	}

	
	public function has_missed_cron() {
		if ( is_wp_error( $this->crons ) ) {
			return $this->crons;
		}

		foreach ( $this->crons as $id => $cron ) {
			if ( ( $cron->time - time() ) < $this->timeout_missed_cron ) {
				$this->last_missed_cron = $cron->hook;
				return true;
			}
		}

		return false;
	}

	
	public function has_late_cron() {
		if ( is_wp_error( $this->crons ) ) {
			return $this->crons;
		}

		foreach ( $this->crons as $id => $cron ) {
			$cron_offset = $cron->time - time();
			if (
				$cron_offset >= $this->timeout_missed_cron &&
				$cron_offset < $this->timeout_late_cron
			) {
				$this->last_late_cron = $cron->hook;
				return true;
			}
		}

		return false;
	}
}
