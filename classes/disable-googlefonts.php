<?php
/**
 * All-in-one Performance Accelerator plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\AIOACC;
use \JMathai\PhpMultiCurl\MultiCurl;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}
require_once(__DIR__.'/../vendor/autoload.php'); 
class DisableFonts
{
    
	protected static $instance = null,$plugin;
	
	public function __construct()
	{
       
        $disable_google_fonts=get_option('smack_google_fonts');
        if($disable_google_fonts=='true'){
            
            add_action( 'wp_enqueue_scripts',  array($this,'ultimate_dequeueu_fonts'), 9999 );
            add_action( 'wp_print_styles', array($this,'ultimate_dequeueu_fonts'), 9999 );
            
            /**
             * Dequeue Google Fonts loaded by Elementor.
             */
            add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );	
        }
       
      
    
    
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
		
	}

    public function maybe_init_process() {
       
		ob_start( [ $this, 'maybe_process_buffer' ] );
    }
    
    public function maybe_process_buffer( $buffer ) {
       
		if ( ! $this->is_html( $buffer ) ) {
          
			return $buffer;
		}
			$buffer = $this->smack_combine_fonts($buffer);
	
		return $buffer;
    }

    function smack_combine_fonts($html) {
        @wp_mkdir_p(WP_CONTENT_DIR . '/cache/google-fonts/',0755);
        $clean_buffer = preg_replace( '/<!--(.*)-->/Uis', '', $html );
        preg_match_all('#(<style[^>]*>.*</style>)|(<link[^>]*stylesheet[^>]*>)#Usmi', $clean_buffer, $matches);

        foreach ( $matches[0] as $tag ) {
            if ( false !== strpos( $tag, 'https://fonts.googleapis.com/css' ) ) {


                if ( preg_match( '#<link.*href=("|\')(.*)("|\')#Usmi', $tag, $source ) ) {

                    $hash = substr(md5($source[2]), 0, 12);
                    $file_name = "$hash.google-font.css";
                    $file_path = SMACK_FONT_CACHE_DIR . $file_name;
                    $file_url = SMACK_FONT_CACHE_URL . $file_name;
         			$result=self::self_host_style_sheet($source[2], $file_path);
					    $combine_tag = str_replace($source[2],$file_url , $tag );
        			$html= str_replace( $tag, $combine_tag, $html );       
                }
            }
        }
        return $html;
    }


    private static function self_host_style_sheet($url, $file_path)
    {
      if (substr($url, 0, 2) === '//') {
        $url = 'https:' . $url;
      }
      $user_agent =
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.122 Safari/537.36';
      $css_file_response = wp_remote_get($url, [
        'user-agent' => $user_agent,
      ]);
      if (is_wp_error($css_file_response)) {
        return false;
      }
      $css = $css_file_response['body'];
      $fonts_to_download = self::get_font_urls($css);
      $downloaded_fonts = self::download_and_self_host_files($fonts_to_download);
      foreach ($downloaded_fonts as $font) {
        $css = str_replace($font['original_url'], $font['new_url'], $css);
      }
      file_put_contents($file_path, $css);
    }

    private static function get_font_urls($css)
  {
    $regex = '/url\((https:\/\/fonts\.gstatic\.com\/.*?)\)/';
    preg_match_all($regex, $css, $matches);
    return $matches[1];
  }

  private static function download_and_self_host_files($urls)
  {
    $downloader =MultiCurl::getInstance();
    $requests = [];
    foreach ($urls as $url) {
      array_push($requests, $downloader->addUrl($url));
    }
    $self_hosted_fonts = [];
    foreach ($requests as $key => $request) {
      $url = $urls[$key];
      $file_name = basename($url);
      $file_path = SMACK_FONT_CACHE_DIR . $file_name;
      file_put_contents($file_path, $request->response);
      $new_file_url = SMACK_FONT_CACHE_URL . $file_name;
      array_push($self_hosted_fonts, [
        'original_url' => $url,
        'new_url' => $new_file_url,
      ]);
    }
    return $self_hosted_fonts;
  }


	function ultimate_dequeueu_fonts() {
        global $wp_styles;
       
        // if ( ! ( $wp_styles instanceof WP_Styles ) ) {
        //     return;
        // }
    
        $allowed = apply_filters(
            'drgf_exceptions',
            [ 'olympus-google-fonts' ]
        );
      
        foreach ( $wp_styles->registered as $style ) {

            $handle = $style->handle;
            $src    = $style->src;
            $gfonts = strpos( $src, 'fonts.googleapis' );
           
            if ( false !== $gfonts ) {
                if ( ! array_key_exists( $handle, array_flip( $allowed ) ) ) {
                    wp_dequeue_style( $handle );
                }
            }
        }
        remove_action( 'wp_footer', array( 'RevSliderFront', 'load_google_fonts' ) );
    }
    
    public function is_html( $buffer ) {
        return preg_match( '/<\/html>/i', $buffer );
    }
    
   

}
$new_obj = new DisableFonts();
