<?php
/**
 * All-in-one Performance Accelerator plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plug_dir = ( ( defined( 'WP_PLUGIN_DIR' ) ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins' ) . '/all-in-one-performance-accelerator';

require_once $plug_dir . '/includes/smack_cache_engine.php';
require_once $plug_dir . '/includes/smack_cache_disk.php';

Smack_Cache_Engine::start();
Smack_Cache_Engine::deliver_cache();
