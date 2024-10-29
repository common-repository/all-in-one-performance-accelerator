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

class RewriteCDN
{
	var $baseUrl = null;
    var $cdnUrl = null;
    var $cdnImages = null;
    var $cdnJs = null;
    var $cdnCss = null;
	var $excludedPhrases = null;
	var $directories = null;
	var $disableForAdmin = null;
	
    protected static $instance = null,$plugin;

	function __construct($baseUrl, $cdnUrl, $cdnImages, $cdnJs, $cdnCss, $directories, $excludedPhrases, $disableForAdmin) 
	{
		
		$this->baseUrl = $baseUrl;
		$this->cdnUrl = $cdnUrl;
		$this->cdnJs = $cdnJs;
		$this->cdnCss = $cdnCss;
		$this->cdnImages = $cdnImages;
		
        if(!empty($this->cdnImages)){
			$this->cdnImages = (is_ssl() ? 'https://' : 'http://').$cdnImages;
			
        }else{
			$this->cdnImages = get_option('home');
		}
        if(!empty($this->cdnJs)){
			
            $this->cdnJs = (is_ssl() ? 'https://' : 'http://').$this->cdnJs;
        }else{
			$this->cdnJs = get_option('home');
		}
        if(!empty($this->cdnCss)){
            $this->cdnCss = (is_ssl() ? 'https://' : 'http://').$cdnCss;
        }else{
			$this->cdnCss = get_option('home');
		}
		$this->disableForAdmin = $disableForAdmin;
		
		// Prepare the excludes
		if(trim($excludedPhrases) != '')
		{
			$this->excludedPhrases = explode(',', $excludedPhrases);
			$this->excludedPhrases = array_map('trim', $this->excludedPhrases);
		}
		array_push($this->excludedPhrases, "]");
		array_push($this->excludedPhrases, "(");
		
		// Validate the directories
		if (trim($directories) == '') 
		{
			$directories = SMACK_CDN_DEFAULT_DIRECTORIES;
		}
		// Create the array
		$directoryArray = explode(',', $directories);
		if(count($directoryArray) > 0)
		{
			$directoryArray = array_map('trim', $directoryArray);
			$directoryArray = array_map('quotemeta', $directoryArray);
			$directoryArray = array_filter($directoryArray);
		}
		$this->directories = $directoryArray;
	}

    public static function getInstance() {
		if ( null == self::$instance ) {
			//self::$instance = new self;
			self::$plugin = Plugin::getInstance();
			//self::$instance->doHooks();
		}
        return self::$instance;
	}

    public function doHooks(){
		
	}

	
    protected function rewrite_Url($asset) 
	{
		$foundUrl = $asset[0];

		// Don't rewrite URLs in the admin preview
		if(is_admin_bar_showing() && $this->disableForAdmin)
		{
			return $asset[0];
		}

		// If the URL contains an excluded phrase don't rewrite it
		foreach($this->excludedPhrases as $exclude)
		{
           
			if($exclude == '')
				continue;

			if(stristr($foundUrl, $exclude) != false)
				return $foundUrl;
		}
		
		// If this is NOT a relative URL
		if (strstr($foundUrl, $this->baseUrl)) 
		{
            $extension = pathinfo(parse_url($foundUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            // if(strpos($extension, 'jpg') !== false){
            //    
            // }
            
            $default='false';
            if(!empty($this->cdnImages)){ 	
               if(strpos($extension, 'jpg') !== false || $extension == 'png' || $extension == 'gif' || $extension == 'svg')   {
                    return str_replace($this->baseUrl,$this->cdnImages, $foundUrl);
                    return $this->cdnImages . $foundUrl;

                }
               
            }
            if($extension == 'js' && !empty($this->cdnJs)){
				return str_replace($this->baseUrl,$this->cdnJs, $foundUrl);
                return $this->cdnJs . $foundUrl;
            }
            if($extension == 'css' && !empty($this->cdnCss)){
                return str_replace($this->baseUrl, $this->cdnCss, $foundUrl);
                return $this->cdnCss . $foundUrl;
            }
            
			return str_replace($this->baseUrl, $this->cdnUrl, $foundUrl);
			return $this->cdnUrl . $foundUrl;

		}

		
	}


	
	public function rewrite_cdn($html) 
	{
		
		// Prepare the included directories regex
        $directoriesRegex = implode('|', $this->directories);
      
		$regex = '#(?<=[(\"\'])(?:'. quotemeta($this->baseUrl) .')?/(?:((?:'.$directoriesRegex.')[^\"\')]+)|([^/\"\']+\.[^/\"\')]+))(?=[\"\')])#';
        $response=preg_replace_callback($regex, array(&$this, "rewrite_Url"), $html);
      
        return $response;
	}
	
	/**
		Begins the rewrite process with the currently configured settings
	*/
	public function Rewrite_start()
	{
		
		ob_start(array($this,'rewrite_cdn'));
	}
}
