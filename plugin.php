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

//A singleton class
class Plugin
{
	protected static $instance = null;
	static $wpupe_slug = 'all-in-one-performance-accelerator';
	static $wpupe_plugin_slug = 'all-in-one-performance-accelerator';
	protected $wpupe_pluginSlug = 'all-in-one-performance-accelerator';
	protected $pluginVersion = 1.0;

	public static function getInstance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	//Getters
	public function getPluginSlug() {
		return $this->wpupe_pluginSlug;
	}

	public function getPluginVersion() {
		return $this->pluginVersion;
	}

	public static function activate() {
		copy( SMACK_DIR . '/db.php', WP_CONTENT_DIR . '/db.php' );
	}

	/**
	 * The code that runs during plugin deactivation.
	 */
	public static function deactivate() {
		if ( file_exists( WP_CONTENT_DIR . '/db.php' ) ) {
			unlink( WP_CONTENT_DIR . '/db.php' ); // phpcs:ignore
		}
		$remove_compression=new Compression();
		$remove_compression->disable_gzip_compression_from_htaccess();
		$enable_drop_option=get_option('smack_drop_options');
		if($enable_drop_option=='true'){
			$drop_table=new DownloadSettings();
			$drop_table->drop_all_options_table();
		}
		delete_option('disable_auto_start_heart_beat');
		$remove_cache_folder=WP_CONTENT_DIR . '/cache/smack_cache';
		$remove_preload_folder=WP_CONTENT_DIR . '/cache/smack-preload';
		$remove_minify_folder=WP_CONTENT_DIR . '/cache/smack-minify';
		$remove_optimize_folder=WP_CONTENT_DIR . '/cache/smack_optimize';
if (is_dir($remove_cache_folder)) {
	$dir = new \RecursiveDirectoryIterator($remove_cache_folder, \RecursiveDirectoryIterator::SKIP_DOTS);
	foreach (new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::CHILD_FIRST ) as $filename => $file) {
		if (is_file($filename))
			unlink($filename);
		else
			rmdir($filename);
	}
	rmdir($remove_cache_folder); // Now remove myfolder
}
if (is_dir($remove_preload_folder)) {
	$dir = new \RecursiveDirectoryIterator($remove_preload_folder, \RecursiveDirectoryIterator::SKIP_DOTS);
	foreach (new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::CHILD_FIRST ) as $filename => $file) {
		if (is_file($filename))
			unlink($filename);
		else
			rmdir($filename);
	}
	rmdir($remove_preload_folder); // Now remove myfolder
}
if (is_dir($remove_minify_folder)) {
	$dir = new \RecursiveDirectoryIterator($remove_minify_folder, \RecursiveDirectoryIterator::SKIP_DOTS);
	foreach (new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::CHILD_FIRST ) as $filename => $file) {
		if (is_file($filename))
			unlink($filename);
		else
			rmdir($filename);
	}
	rmdir($remove_minify_folder); // Now remove myfolder
}
if (is_dir($remove_optimize_folder)) {
	$dir = new \RecursiveDirectoryIterator($remove_optimize_folder, \RecursiveDirectoryIterator::SKIP_DOTS);
	foreach (new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::CHILD_FIRST ) as $filename => $file) {
		if (is_file($filename))
			unlink($filename);
		else
			rmdir($filename);
	}
	rmdir($remove_optimize_folder); // Now remove myfolder
}		
		
	
	}

	
}
