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

class DelayJS
{
    
	protected static $instance = null,$plugin;
	
	public function __construct()
	{
       
        ob_start( [ $this, 'delay_js_buffer' ] );
        add_action('wp_footer',array($this,'smack_add_delay_js') , PHP_INT_MAX);
		
    
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
     
    public function delay_js_buffer($html){
        //strip comments before search
	$html_no_comments = preg_replace('/<!--(.*)-->/Uis', '', $html);

	//match all script tags
	preg_match_all('#(<script\s?([^>]+)?\/?>)(.*?)<\/script>#is', $html_no_comments, $matches);
	//no script tags found
	if(!isset($matches[0])) {
		return $html;
    }
    
    foreach($matches[0] as $i => $tag) {

		$atts_source = !empty($matches[2][$i]) ? $this->getting_atts_array($matches[2][$i]) : array();
	
		//skip if type is not javascript
		if(isset($atts_source['type']) && stripos($atts_source['type'], 'javascript') == false) {
			continue;
        }
       
        $delay_js=get_option('smack_delay_js_script');
        $delayed_js = explode(",", $delay_js);
        $url = home_url();
        if(!empty($delayed_js)) {
            foreach($delayed_js as $delayed_script) { 
                if(!empty($delayed_script)) {
                    $delayed_script = str_replace('http://',"",$delayed_script); 
                    $delayed_script = $url.'/'.$delayed_script;
                    if(!empty($tag) && !empty($delayed_script)){
                        if(strpos($tag, $delayed_script) !== false) {
                        
                            if(!empty($atts_source['src'])) {
                                $atts_source['data-delayedjsscript'] = $atts_source['src'];
                                unset($atts_source['src']);
                            }
                            // else {
                            //     $atts_source['data-delayedjsscript'] = "data:text/javascript;base64," . base64_encode($matches[3][$i]);
                            // }
                        
                            $delayed_atts =$this->getting_atts_string($atts_source);
                            $delayed_tag = sprintf('<script %1$s></script>', $delayed_atts);  
                            $html = str_replace($tag, $delayed_tag, $html);

                            continue 2;
                        }
                    }
                }   
            }
        }
        
    }
       return $html;
       
    }

    function getting_atts_array($atts_string) {
	
        if(!empty($atts_string)) {
            $atts_array = array_map(
                function(array $attribute) {
                    return $attribute['value'];
                },
                wp_kses_hair($atts_string, wp_allowed_protocols())
            );
    
            return $atts_array;
        }
    
        return false;
    }

    function getting_atts_string($atts_array) {

        if(!empty($atts_array)) {
            $assigned_atts_array = array_map(
            function($name, $value) {
                    if($value === '') {
                        return $name;
                    }
                    return sprintf('%s="%s"', $name, esc_attr($value));
                },
                array_keys($atts_array),
                $atts_array
            );
            $atts_string = implode(' ', $assigned_atts_array);
    
            return $atts_string;
        }
    
        return false;
    }


    function smack_add_delay_js() {

        $smack_delay_js_script = get_option('smack_delay_js_script');
    
          if(!empty($smack_delay_js_script)||get_option('smack_combine_js') == 'true') {
             
              echo '<script type="text/javascript" id="smack-delayed-js-scripts">
              const enhancerUserInteractions=["keydown","mouseover","touchmove","touchstart"];
              enhancerUserInteractions.forEach(function(event){window.addEventListener(event,smackTriggerDelayedScripts,{passive:!0})});
              function smackTriggerDelayedScripts(){smackLoadDelayedScripts();enhancerUserInteractions.forEach(function(event){window.removeEventListener(event,smackTriggerDelayedScripts,{passive:!0})})}
                  function smackLoadDelayedScripts(){document.querySelectorAll("script[data-delayedjsscript]").forEach(function(elem){elem.setAttribute("src",elem.getAttribute("data-delayedjsscript"))})}</script>';
    
          }
    }



}
$new_obj = new DelayJS();