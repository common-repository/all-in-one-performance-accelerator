<?php

namespace Smackcoders\AIOACC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class enhancerBase
{
    protected $content = '';
    public $debug_log = false;
    public $cdn_url = '';

    public function __construct( $content )
    {     
        $this->content = $content;
        add_action( 'wp_loaded', array( $this, 'load_toolbar' ) );
    }
  
    //Reads the page and collects tags.  
    abstract public function read( $options );

    //Joins and optimizes collected things.
    abstract public function minify();

    //Caches the things.
    abstract public function cache();

    //Returns the content
    abstract public function getcontent();

    /**
     * Tranfsorms a given URL to a full local filepath if possible.
     * Returns local filepath or false.
     */
    public function getpath( $url )
    {
        $url = apply_filters( 'smack_optimize_filter_cssjs_alter_url', $url );

        if ( false !== strpos( $url, '%' ) ) {
            $url = urldecode( $url );
        }

        $site_host    = parse_url( SMACK_OPTIMIZE_WP_SITE_URL, PHP_URL_HOST );
        $content_host = parse_url( SMACK_OPTIMIZE_WP_ROOT_URL, PHP_URL_HOST );

        // Normalizing attempts...
        $double_slash_position = strpos( $url, '//' );
        if ( 0 === $double_slash_position ) {
            if ( is_ssl() ) {
                $url = 'https:' . $url;
            } else {
                $url = 'http:' . $url;
            }
        } elseif ( ( false === $double_slash_position ) && ( false === strpos( $url, $site_host ) ) ) {
            if ( SMACK_OPTIMIZE_WP_SITE_URL === $site_host ) {
                $url = SMACK_OPTIMIZE_WP_SITE_URL . $url;
            } else {
                $url = SMACK_OPTIMIZE_WP_SITE_URL . self::path_canonicalize( $url );
            }
        }

        if ( $site_host !== $content_host ) {
            $url = str_replace( SMACK_OPTIMIZE_WP_CONTENT_URL, SMACK_OPTIMIZE_WP_SITE_URL . SMACK_OPTIMIZE_WP_CONTENT_NAME, $url );
        }

        // First check; hostname wp site should be hostname of url!
        $url_host = @parse_url( $url, PHP_URL_HOST ); // @codingStandardsIgnoreLine
        if ( $url_host !== $site_host ) {
            /**
             * First try to get all domains from WPML (if available)
             * then explicitely declare $this->cdn_url as OK as well
             * then apply own filter smack_optimize_filter_cssjs_multidomain takes an array of hostnames
             * each item in that array will be considered part of the same WP multisite installation
             */
            $multidomains = array();

            $multidomains_wpml = apply_filters( 'wpml_setting', array(), 'language_domains' );
            if ( ! empty( $multidomains_wpml ) ) {
                $multidomains = array_map( array( $this, 'get_url_hostname' ), $multidomains_wpml );
            }

            if ( ! empty( $this->cdn_url ) ) {
                $multidomains[] = parse_url( $this->cdn_url, PHP_URL_HOST );
            }

            $multidomains = apply_filters( 'smack_optimize_filter_cssjs_multidomain', $multidomains );

            if ( ! empty( $multidomains ) ) {
                if ( in_array( $url_host, $multidomains ) ) {
                    $url = str_replace( $url_host, $site_host, $url );
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Try to remove "wp root url" from url while not minding http<>https.
        $tmp_ao_root = preg_replace( '/https?:/', '', SMACK_OPTIMIZE_WP_ROOT_URL );

        if ( $site_host !== $content_host ) {
            // As we replaced the content-domain with the site-domain, we should match against that.
            $tmp_ao_root = preg_replace( '/https?:/', '', SMACK_OPTIMIZE_WP_SITE_URL );
        }

        $tmp_url = preg_replace( '/https?:/', '', $url );
        $path    = str_replace( $tmp_ao_root, '', $tmp_url );

        // If path starts with :// or //, this is not a URL in the WP context and
        // we have to assume we can't aggregate.
        if ( preg_match( '#^:?//#', $path ) ) {
            // External script/css (adsense, etc).
            return false;
        }

        // Prepend with WP_ROOT_DIR to have full path to file.
        $path = str_replace( '//', '/', SMACK_WP_ROOT_DIR . $path );

        // Final check: does file exist and is it readable?
        if ( file_exists( $path ) && is_file( $path ) && is_readable( $path ) ) {
            return $path;
        } else {
            return false;
        }
    }

    public function load_toolbar()
    {
      
        // Check permissions and that toolbar is not hidden via filter.
        if ( current_user_can( 'manage_options' ) && apply_filters( 'smack_optimize_filter_toolbar_show', true ) ) {
        
            // Create a handler for the AJAX toolbar requests.
           // add_action( 'wp_ajax_smack_optimize_delete_cache', array( $this, 'delete_cache' ) );

            // Load custom styles, scripts and menu only when needed.
            if ( is_admin_bar_showing() ) {
             
                if ( is_admin() ) {
                   add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
                } else {
                    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
                }

                // Add the Autoptimize Toolbar to the Admin bar.
                add_action( 'admin_bar_menu', array( $this, 'add_toolbar' ), 100 );
            }
        }
    }

    public function enqueue_scripts()
    {
      
        // Autoptimize Toolbar Styles.
        wp_enqueue_style( 'autoptimize-toolbar', plugins_url( '/static/toolbar.css', __FILE__ ), array(), AUTOPTIMIZE_PLUGIN_VERSION, 'all' );

        // Autoptimize Toolbar Javascript.
        wp_enqueue_script( 'autoptimize-toolbar', plugins_url( '/static/toolbar.js', __FILE__ ), array( 'jquery' ), AUTOPTIMIZE_PLUGIN_VERSION, true );
      
        // Localizes a registered script with data for a JavaScript variable.
        // Needed for the AJAX to work properly on the frontend.
        wp_localize_script( 'autoptimize-toolbar', 'autoptimize_ajax_object', array(
            'ajaxurl'     => admin_url( 'admin-ajax.php' ),
            // translators: links to the Autoptimize settings page.
            'error_msg'   => sprintf( __( 'Your Autoptimize cache might not have been purged successfully, please check on the <a href=%s>Autoptimize settings page</a>.', 'autoptimize' ), admin_url( 'options-general.php?page=autoptimize' ) . ' style="white-space:nowrap;"' ),
            'dismiss_msg' => __( 'Dismiss this notice.' ),
            'nonce'       => wp_create_nonce( 'ao_delcache_nonce' ),
        ) );
    }

    public function delete_cache()
    {
    
        check_ajax_referer( 'ao_delcache_nonce', 'nonce' );

        $result = false;
        if ( current_user_can( 'manage_options' ) ) {
            // We call the function for cleaning the Autoptimize cache.
            $result = enhancerCache::clearall();
        }

        wp_send_json( $result );
    }

    public function add_toolbar()
    {
        global $wp_admin_bar;
      
        // Retrieve the Autoptimize Cache Stats information.
        $stats = enhancerCache::stats();

        // Set the Max Size recommended for cache files.
        $max_size = apply_filters( 'smack_optimize_filter_cachecheck_maxsize', 512 * 1024 * 1024 );

        // Retrieve the current Total Files in cache.
        $files = $stats[0];
        // Retrieve the current Total Size of the cache.
        $bytes = $stats[1];
        $size  = $this->format_filesize( $bytes );

        // Calculate the percentage of cache used.
        $percentage = ceil( $bytes / $max_size * 100 );
        if ( $percentage > 100 ) {
            $percentage = 100;
        }

        /**
         * We define the type of color indicator for the current state of cache size:
         * - "green" if the size is less than 80% of the total recommended.
         * - "orange" if over 80%.
         * - "red" if over 100%.
         */
        $color = ( 100 == $percentage ) ? 'red' : ( ( $percentage > 80 ) ? 'orange' : 'green' );

        // Create or add new items into the Admin Toolbar.
        // Main "Autoptimize" node.
        $wp_admin_bar->add_node( array(
            'id'    => 'autoptimize',
            'title' => '<span class="ab-icon"></span><span class="ab-label">' . __( 'Autoptimize', 'autoptimize' ) . '</span>',
            'href'  => admin_url( 'options-general.php?page=autoptimize' ),
            'meta'  => array( 'class' => 'bullet-' . $color ),
        ));

        // "Cache Info" node.
        $wp_admin_bar->add_node( array(
            'id'     => 'autoptimize-cache-info',
            'title'  => '<p>' . __( 'Cache Info', 'autoptimize' ) . '</p>' .
                        '<div class="autoptimize-radial-bar" percentage="' . $percentage . '">' .
                        '<div class="autoptimize-circle">' .
                        '<div class="mask full"><div class="fill bg-' . $color . '"></div></div>' .
                        '<div class="mask half"><div class="fill bg-' . $color . '"></div></div>' .
                        '<div class="shadow"></div>' .
                        '</div>' .
                        '<div class="inset"><div class="percentage"><div class="numbers ' . $color . '">' . $percentage . '%</div></div></div>' .
                        '</div>' .
                        '<table>' .
                        '<tr><td>' . __( 'Size', 'autoptimize' ) . ':</td><td class="size ' . $color . '">' . $size . '</td></tr>' .
                        '<tr><td>' . __( 'Files', 'autoptimize' ) . ':</td><td class="files white">' . $files . '</td></tr>' .
                        '</table>',
            'parent' => 'autoptimize',
        ));

        // "Delete Cache" node.
        $wp_admin_bar->add_node( array(
            'id'     => 'autoptimize-delete-cache',
            'title'  => __( 'Delete Cache', 'autoptimize' ),
            'parent' => 'autoptimize',
        ));
    }

    public function format_filesize( $bytes, $decimals = 2 )
    {
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' );

        for ( $i = 0; ( $bytes / 1024) > 0.9; $i++, $bytes /= 1024 ) {} // @codingStandardsIgnoreLine

        return sprintf( "%1.{$decimals}f %s", round( $bytes, $decimals ), $units[ $i ] );
    }

    /**
     * Canonicalizes the given path regardless of it existing or not.
     *
     * @param string $path Path to normalize.
     *
     * @return string
     */
    public static function path_canonicalize( $path )
    {
        $patterns     = array(
            '~/{2,}~',
            '~/(\./)+~',
            '~([^/\.]+/(?R)*\.{2,}/)~',
            '~\.\./~',
        );
        $replacements = array(
            '/',
            '/',
            '',
            '',
        );

        return preg_replace( $patterns, $replacements, $path );
    }

    /**
     * Returns the hostname part of a given $url if we're able to parse it.
     * If not, it returns the original url (prefixed with http:// scheme in case
     * it was missing).
     * Used as callback for WPML multidomains filter.
     */
    protected function get_url_hostname( $url )
    {
        // Checking that the url starts with something vaguely resembling a protocol.
        if ( ( 0 !== strpos( $url, 'http' ) ) && ( 0 !== strpos( $url, '//' ) ) ) {
            $url = 'http://' . $url;
        }

        // Grab the hostname.
        $hostname = parse_url( $url, PHP_URL_HOST );

        // Fallback when parse_url() fails.
        if ( empty( $hostname ) ) {
            $hostname = $url;
        }

        return $hostname;
    }

    /**
     * Hides everything between noptimize-comment tags.
     */
    protected function hide_noptimize( $markup )
    {
        return $this->replace_contents_with_marker_if_exists(
            'NOPTIMIZE',
            '/<!--\s?noptimize\s?-->/',
            '#<!--\s?noptimize\s?-->.*?<!--\s?/\s?noptimize\s?-->#is',
            $markup
        );
    }

    /**
     * Unhide noptimize-tags.
     */
    protected function restore_noptimize( $markup )
    {
        return $this->restore_marked_content( 'NOPTIMIZE', $markup );
    }

    /**
     * Hides "iehacks" content.
     */
    protected function hide_iehacks( $markup )
    {
        return $this->replace_contents_with_marker_if_exists(
            'IEHACK', // Marker name...
            '<!--[if', // Invalid regex, will fallback to search using strpos()...
            '#<!--\[if.*?\[endif\]-->#is', // Replacement regex...
            $markup
        );
    }

    /**
     * Restores "hidden" iehacks content.
     */
    protected function restore_iehacks( $markup )
    {
        return $this->restore_marked_content( 'IEHACK', $markup );
    }

    /**
     * "Hides" content within HTML comments using a regex-based replacement
     * if HTML comment markers are found.
     * `<!--example-->` becomes `%%COMMENTS%%ZXhhbXBsZQ==%%COMMENTS%%`
     */
    protected function hide_comments( $markup )
    {
        return $this->replace_contents_with_marker_if_exists(
            'COMMENTS',
            '<!--',
            '#<!--.*?-->#is',
            $markup
        );
    }

    /**
     * Restores original HTML comment markers inside a string whose HTML
     * comments have been "hidden" by using `hide_comments()`.
     */
    protected function restore_comments( $markup )
    {
        return $this->restore_marked_content( 'COMMENTS', $markup );
    }

    /**
     * Replaces the given URL with the CDN-version of it when CDN replacement
     * is supposed to be done.
     */
    public function url_replace_cdn( $url )
    {
        // For 2.3 back-compat in which cdn-ing appeared to be automatically
        // including WP subfolder/subdirectory into account as part of cdn-ing,
        // even though it might've caused serious troubles in certain edge-cases.
        $cdn_url = self::tweak_cdn_url_if_needed( $this->cdn_url );

        // Allows API/filter to further tweak the cdn url...
        $cdn_url = apply_filters( 'smack_optimize_filter_base_cdnurl', $cdn_url );
        if ( ! empty( $cdn_url ) ) {
            $this->debug_log( 'before=' . $url );

            // Simple str_replace-based approach fails when $url is protocol-or-host-relative.
            $is_protocol_relative = self::is_protocol_relative( $url );
            $is_host_relative     = ( ! $is_protocol_relative && ( '/' === $url[0] ) );
            $cdn_url              = rtrim( $cdn_url, '/' );

            if ( $is_host_relative ) {
                // Prepending host-relative urls with the cdn url.
                $url = $cdn_url . $url;
            } else {
                // Either a protocol-relative or "regular" url, replacing it either way.
                if ( $is_protocol_relative ) {
                    // Massage $site_url so that simple str_replace() still "works" by
                    // searching for the protocol-relative version of SMACK_OPTIMIZE_WP_SITE_URL.
                    $site_url = str_replace( array( 'http:', 'https:' ), '', SMACK_OPTIMIZE_WP_SITE_URL );
                } else {
                    $site_url = SMACK_OPTIMIZE_WP_SITE_URL;
                }
                $this->debug_log( '`' . $site_url . '` -> `' . $cdn_url . '` in `' . $url . '`' );
                $url = str_replace( $site_url, $cdn_url, $url );
            }

            $this->debug_log( 'after=' . $url );
        }

        // Allow API filter to take further care of CDN replacement.
        $url = apply_filters( 'smack_optimize_filter_base_replace_cdn', $url );

        return $url;
    }

     /**
     * Modify given $cdn_url to include the site path when needed.
     *
     * @param string $cdn_url          CDN URL to tweak.
     * @param bool   $force_cache_miss Force a cache miss in order to be able
     *                                 to re-run the filter.
     *
     * @return string
     */
    public static function tweak_cdn_url_if_needed( $cdn_url, $force_cache_miss = false )
    {
        static $results = array();

        if ( ! isset( $results[ $cdn_url ] ) || $force_cache_miss ) {

            // In order to return unmodified input when there's no need to tweak.
            $results[ $cdn_url ] = $cdn_url;

            // Behind a default true filter for backcompat, and only for sites
            // in a subfolder/subdirectory, but still easily turned off if
            // not wanted/needed...
            if ( self::siteurl_not_root() ) {
                $check = apply_filters( 'smack_optimize_filter_cdn_magic_path_check', true, $cdn_url );
                if ( $check ) {
                    $site_url_parts = self::get_ao_wp_site_url_parts();
                    $cdn_url_parts  = \parse_url( $cdn_url );
                    $schemeless     = self::is_protocol_relative( $cdn_url );
                    $cdn_url_parts  = self::maybe_replace_cdn_path( $site_url_parts, $cdn_url_parts );
                    if ( false !== $cdn_url_parts ) {
                        $results[ $cdn_url ] = self::assemble_parsed_url( $cdn_url_parts, $schemeless );
                    }
                }
            }
        }

        return $results[ $cdn_url ];
    }

     /**
     * Given an array or components returned from \parse_url(), assembles back
     * the complete URL.
     * If optional
     *
     * @param array $parsed_url URL components array.
     * @param bool  $schemeless Whether the assembled URL should be
     *                          protocol-relative (schemeless) or not.
     *
     * @return string
     */
    public static function assemble_parsed_url( array $parsed_url, $schemeless = false )
    {
        $scheme = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
        if ( $schemeless ) {
            $scheme = '//';
        }
        $host     = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
        $port     = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
        $user     = isset( $parsed_url['user'] ) ? $parsed_url['user'] : '';
        $pass     = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
        $pass     = ( $user || $pass ) ? "$pass@" : '';
        $path     = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
        $query    = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
        $fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }


    /**
     * When siteurl contains a path other than '/' and the CDN URL does not have
     * a path or it's path is '/', this will modify the CDN URL's path component
     * to match that of the siteurl.
     * This is to support "magic" CDN urls that worked that way before v2.4...
     *
     * @param array $site_url_parts Site URL components array.
     * @param array $cdn_url_parts  CDN URL components array.
     *
     * @return array|false
     */
    public static function maybe_replace_cdn_path( array $site_url_parts, array $cdn_url_parts )
    {
        if ( isset( $site_url_parts['path'] ) && '/' !== $site_url_parts['path'] ) {
            if ( ! isset( $cdn_url_parts['path'] ) || '/' === $cdn_url_parts['path'] ) {
                $cdn_url_parts['path'] = $site_url_parts['path'];
                return $cdn_url_parts;
            }
        }

        return false;
    }

    /**
     * Returns true if given $url is protocol-relative.
     *
     * @param string $url URL to check.
     *
     * @return bool
     */
    public static function is_protocol_relative( $url )
    {
        $result = false;

        if ( ! empty( $url ) ) {
            $result = ( 0 === strpos( $url, '//' ) );
        }

        return $result;
    }

     /**
     * Decides whether this is a "subdirectory site" or not.
     *
     * @param bool $override Allows overriding the decision when needed.
     *
     * @return bool
     */
    public static function siteurl_not_root( $override = null )
    {
        static $subdir = null;

        if ( null === $subdir ) {
            $parts  = self::get_ao_wp_site_url_parts();
            $subdir = ( isset( $parts['path'] ) && ( '/' !== $parts['path'] ) );
        }

        if ( null !== $override ) {
            $subdir = $override;
        }

        return $subdir;
    }

    /**
     * Parse SMACK_OPTIMIZE_WP_SITE_URL into components using \parse_url(), but do
     * so only once per request/lifecycle.
     *
     * @return array
     */
    public static function get_ao_wp_site_url_parts()
    {
        static $parts = array();

        if ( empty( $parts ) ) {
            $parts = \parse_url( SMACK_OPTIMIZE_WP_SITE_URL );
        }

        return $parts;
    }

    /**
     * Injects/replaces the given payload markup into `$this->content`
     * at the specified location.
     * If the specified tag cannot be found, the payload is appended into
     * $this->content along with a warning wrapped inside <!--noptimize--> tags.
     *
     * @param string $payload Markup to inject.
     * @param array  $where   Array specifying the tag name and method of injection.
     *                        Index 0 is the tag name (i.e., `</body>`).
     *                        Index 1 specifies ˛'before', 'after' or 'replace'. Defaults to 'before'.
     *
     * @return void
     */
    protected function inject_in_html( $payload, $where )
    {
        $warned   = false;
        $position = self::strpos( $this->content, $where[0] );
        if ( false !== $position ) {
            // Found the tag, setup content/injection as specified.
            if ( 'after' === $where[1] ) {
                $content = $where[0] . $payload;
            } elseif ( 'replace' === $where[1] ) {
                $content = $payload;
            } else {
                $content = $payload . $where[0];
            }
            // Place where specified.
            $this->content = self::substr_replace(
                $this->content,
                $content,
                $position,
                // Using plain strlen() should be safe here for now, since
                // we're not searching for multibyte chars here still...
                strlen( $where[0] )
            );
        } else {
            // Couldn't find what was specified, just append and add a warning.
            $this->content .= $payload;
            if ( ! $warned ) {
                $tag_display    = str_replace( array( '<', '>' ), '', $where[0] );
                $this->content .= '<!--noptimize--><!-- Enhancer found a problem with the HTML in your Theme, tag `' . $tag_display . '` missing --><!--/noptimize-->';
                $warned         = true;
            }
        }
    }

     /**
     * Attempts to return the number of characters in the given $string if
     * mbstring is available. Returns the number of bytes
     * (instead of characters) as fallback.
     *
     * @param string      $string   String.
     * @param string|null $encoding Encoding.
     *
     * @return int Number of characters or bytes in given $string
     *             (characters if/when supported, bytes otherwise).
     */
    public static function strlen( $string, $encoding = null )
    {
        if ( self::mbstring_available() ) {
            return ( null === $encoding ) ? \mb_strlen( $string ) : \mb_strlen( $string, $encoding );
        } else {
            return \strlen( $string );
        }
    }


     /**
     * Our wrapper around implementations of \substr_replace()
     * that attempts to not break things horribly if at all possible.
     * Uses mbstring if available, before falling back to regular
     * substr_replace() (which works just fine in the majority of cases).
     *
     * @param string      $string      String.
     * @param string      $replacement Replacement.
     * @param int         $start       Start offset.
     * @param int|null    $length      Length.
     * @param string|null $encoding    Encoding.
     *
     * @return string
     */
    public static function substr_replace( $string, $replacement, $start, $length = null, $encoding = null )
    {
        if ( self::mbstring_available() ) {
            $strlen = self::strlen( $string, $encoding );

            if ( $start < 0 ) {
                if ( -$start < $strlen ) {
                    $start = $strlen + $start;
                } else {
                    $start = 0;
                }
            } elseif ( $start > $strlen ) {
                $start = $strlen;
            }

            if ( null === $length || '' === $length ) {
                $start2 = $strlen;
            } elseif ( $length < 0 ) {
                $start2 = $strlen + $length;
                if ( $start2 < $start ) {
                    $start2 = $start;
                }
            } else {
                $start2 = $start + $length;
            }

            if ( null === $encoding ) {
                $leader  = $start ? \mb_substr( $string, 0, $start ) : '';
                $trailer = ( $start2 < $strlen ) ? \mb_substr( $string, $start2, null ) : '';
            } else {
                $leader  = $start ? \mb_substr( $string, 0, $start, $encoding ) : '';
                $trailer = ( $start2 < $strlen ) ? \mb_substr( $string, $start2, null, $encoding ) : '';
            }

            return "{$leader}{$replacement}{$trailer}";
        }

        return ( null === $length ) ? \substr_replace( $string, $replacement, $start ) : \substr_replace( $string, $replacement, $start, $length );
    }


    /**
     * Multibyte-capable strpos() if support is available on the server.
     * If not, it falls back to using \strpos().
     *
     * @param string      $haystack Haystack.
     * @param string      $needle   Needle.
     * @param int         $offset   Offset.
     * @param string|null $encoding Encoding. Default null.
     *
     * @return int|false
     */
    public static function strpos( $haystack, $needle, $offset = 0, $encoding = null )
    {
        if ( self::mbstring_available() ) {
            return ( null === $encoding ) ? \mb_strpos( $haystack, $needle, $offset ) : \mb_strpos( $haystack, $needle, $offset, $encoding );
        } else {
            return \strpos( $haystack, $needle, $offset );
        }
    }

    public static function mbstring_available( $override = null )
    {
        static $available = null;

        if ( null === $available ) {
            $available = \extension_loaded( 'mbstring' );
        }

        if ( null !== $override ) {
            $available = $override;
        }

        return $available;
    }

    /**
     * Returns true if given `$tag` is found in the list of `$removables`.
     *
     * @param string $tag Tag to search for.
     * @param array  $removables List of things considered completely removable.
     *
     * @return bool
     */
    protected function isremovable( $tag, $removables )
    {
        foreach ( $removables as $match ) {
            if ( false !== strpos( $tag, $match ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Callback used in `self::inject_minified()`.
     *
     * @param array $matches Regex matches.
     *
     * @return string
     */
    public function inject_minified_callback( $matches )
    {
        static $conf = null;
        if ( null === $conf ) {
            $conf = autoptimizeConfig::instance();
        }

        /**
         * $matches[1] holds the whole match caught by regex in self::inject_minified(),
         * so we take that and split the string on `|`.
         * First element is the filepath, second is the md5 hash of contents
         * the filepath had when it was being processed.
         * If we don't have those, we'll bail out early.
        */
        $filepath = null;
        $filehash = null;

        // Grab the parts we need.
        $parts = explode( '|', $matches[1] );
        if ( ! empty( $parts ) ) {
            $filepath = isset( $parts[0] ) ? base64_decode( $parts[0] ) : null;
            $filehash = isset( $parts[1] ) ? $parts[1] : null;
        }

        // Bail early if something's not right...
        if ( ! $filepath || ! $filehash ) {
            return "\n";
        }

        $filecontent = file_get_contents( $filepath );

        // Some things are differently handled for css/js...
        $is_js_file = ( '.js' === substr( $filepath, -3, 3 ) );

        $is_css_file = false;
        if ( ! $is_js_file ) {
            $is_css_file = ( '.css' === substr( $filepath, -4, 4 ) );
        }

        // BOMs being nuked here unconditionally (regardless of where they are)!
        $filecontent = preg_replace( "#\x{EF}\x{BB}\x{BF}#", '', $filecontent );

        // Remove comments and blank lines.
        if ( $is_js_file ) {
            $filecontent = preg_replace( '#^\s*\/\/.*$#Um', '', $filecontent );
        }

        // Nuke un-important comments.
        $filecontent = preg_replace( '#^\s*\/\*[^!].*\*\/\s?#Um', '', $filecontent );

        // Normalize newlines.
        $filecontent = preg_replace( '#(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+#', "\n", $filecontent );

        // JS specifics.
        if ( $is_js_file ) {
            // Append a semicolon at the end of js files if it's missing.
            $last_char = substr( $filecontent, -1, 1 );
            if ( ';' !== $last_char && '}' !== $last_char ) {
                $filecontent .= ';';
            }
            // Check if try/catch should be used.
            $opt_js_try_catch = $conf->get( 'smack_optimize_js_trycatch' );
            if ( 'on' === $opt_js_try_catch ) {
                // It should, wrap in try/catch.
                $filecontent = 'try{' . $filecontent . '}catch(e){}';
            }
        } elseif ( $is_css_file ) {
            $filecontent = combineCSS::fixurls( $filepath, $filecontent );
        } else {
            $filecontent = '';
        }

        // Return modified (or empty!) code/content.
        return "\n" . $filecontent;
    }

    /**
     * Inject already minified code in optimized JS/CSS.
     *
     * @param string $in Markup.
     *
     * @return string
     */
    protected function inject_minified( $in )
    {
        $out = $in;
        if ( false !== strpos( $in, '%%INJECTLATER%%' ) ) {
            $out = preg_replace_callback(
                '#\/\*\!%%INJECTLATER' . SMACK_OPTIMIZE_HASH . '%%(.*?)%%INJECTLATER%%\*\/#is',
                array( $this, 'inject_minified_callback' ),
                $in
            );
        }

        return $out;
    }

    /**
     * Specialized method to create the INJECTLATER marker.
     * These are somewhat "special", in the sense that they're additionally wrapped
     * within an "exclamation mark style" comment, so that they're not stripped
     * out by minifiers.
     * They also currently contain the hash of the file's contents too (unlike other markers).
     *
     * @param string $filepath Filepath.
     * @param string $hash Hash.
     *
     * @return string
     */
    public static function build_injectlater_marker( $filepath, $hash )
    {
        $contents = '/*!' . self::build_marker( 'INJECTLATER', $filepath, $hash ) . '*/';

        return $contents;
    }

    /**
     * Creates and returns a `%%`-style named marker which holds
     * the base64 encoded `$data`.
     * If `$hash` is provided, it's appended to the base64 encoded string
     * using `|` as the separator (in order to support building the
     * somewhat special/different INJECTLATER marker).
     *
     * @param string      $name Marker name.
     * @param string      $data Marker data which will be base64-encoded.
     * @param string|null $hash Optional.
     *
     * @return string
     */
    public static function build_marker( $name, $data, $hash = null )
    {
        // Start the marker, add the data.
        $marker = '%%' . $name . SMACK_OPTIMIZE_HASH . '%%' . base64_encode( $data );

        // Add the hash if provided.
        if ( null !== $hash ) {
            $marker .= '|' . $hash;
        }

        // Close the marker.
        $marker .= '%%' . $name . '%%';

        return $marker;
    }

    /**
     * Searches for `$search` in `$content` (using either `preg_match()`
     * or `strpos()`, depending on whether `$search` is a valid regex pattern or not).
     * If something is found, it replaces `$content` using `$re_replace_pattern`,
     * effectively creating our named markers (`%%{$marker}%%`.
     * These are then at some point replaced back to their actual/original/modified
     * contents using `autoptimizeBase::restore_marked_content()`.
     *
     * @param string $marker Marker name (without percent characters).
     * @param string $search A string or full blown regex pattern to search for in $content. Uses `strpos()` or `preg_match()`.
     * @param string $re_replace_pattern Regex pattern to use when replacing contents.
     * @param string $content Content to work on.
     *
     * @return string
     */
    public static function replace_contents_with_marker_if_exists( $marker, $search, $re_replace_pattern, $content )
    {
        $found = false;

        $is_regex = self::str_is_valid_regex( $search );
        if ( $is_regex ) {
            $found = preg_match( $search, $content );
        } else {
            $found = ( false !== strpos( $content, $search ) );
        }

        if ( $found ) {
            $content = preg_replace_callback(
                $re_replace_pattern,
                function( $matches ) use ( $marker ) {
                    return self::build_marker( $marker, $matches[0] );
                },
                $content
            );
        }

        return $content;
    }

    /**
     * Returns true if the string is a valid regex.
     *
     * @param string $string String, duh.
     *
     * @return bool
     */
    public static function str_is_valid_regex( $string )
    {
        set_error_handler( function() {}, E_WARNING );
        $is_regex = ( false !== preg_match( $string, '' ) );
        restore_error_handler();
        return $is_regex;
    }

    /**
     * Complements `autoptimizeBase::replace_contents_with_marker_if_exists()`.
     *
     * @param string $marker Marker.
     * @param string $content Markup.
     *
     * @return string
     */
    public static function restore_marked_content( $marker, $content )
    {
        if ( false !== strpos( $content, $marker ) ) {
            $content = preg_replace_callback(
                '#%%' . $marker . SMACK_OPTIMIZE_HASH . '%%(.*?)%%' . $marker . '%%#is',
                function ( $matches ) {
                    return base64_decode( $matches[1] );
                },
                $content
            );
        }

        return $content;
    }

    /**
     * Logs given `$data` for debugging purposes (when debug logging is on).
     *
     * @param mixed $data Data to log.
     *
     * @return void
     */
    protected function debug_log( $data )
    {
        if ( ! isset( $this->debug_log ) || ! $this->debug_log ) {
            return;
        }

        if ( ! is_string( $data ) && ! is_resource( $data ) ) {
            $data = var_export( $data, true );
        }

        error_log( $data );
    }

    /**
     * Checks if a single local css/js file can be minified and returns source if so.
     *
     * @param string $filepath Filepath.
     *
     * @return bool|string to be minified code or false.
     */
    protected function prepare_minify_single( $filepath )
    {
      
        // Decide what we're dealing with, return false if we don't know.
        if ( self::str_ends_in( $filepath, '.js' ) ) {
            $type = 'js';
        } elseif ( self::str_ends_in( $filepath, '.css' ) ) {
            $type = 'css';
        } else {
            return false;
        }

        // Bail if it looks like its already minifed (by having -min or .min
        // in filename) or if it looks like WP jquery.js (which is minified).
        $minified_variants = array(
            '-min.' . $type,
            '.min.' . $type,
            'js/jquery/jquery.js',
        );
        foreach ( $minified_variants as $ending ) {
            if ( self::str_ends_in( $filepath, $ending ) ) {
                return false;
            }
        }

        // Get file contents, bail if empty.
        $contents = file_get_contents( $filepath );

        return $contents;
    }

    /**
     * Returns true if given $str ends with given $test.
     *
     * @param string $str String to check.
     * @param string $test Ending to match.
     *
     * @return bool
     */
    public static function str_ends_in( $str, $test )
    {
        // @codingStandardsIgnoreStart
        // substr_compare() is bugged on 5.5.11: https://3v4l.org/qGYBH
        // return ( 0 === substr_compare( $str, $test, -strlen( $test ) ) );
        // @codingStandardsIgnoreEnd

        $length = strlen( $test );

        return ( substr( $str, -$length, $length ) === $test );
    }

    /**
     * Given an enhancerCache instance returns the (maybe cdn-ed) url of
     * the cached file.
     *
     * @param enhancerCache $cache enhancerCache instance.
     *
     * @return string
     */
    protected function build_minify_single_url( enhancerCache $cache )
    {
        $url = SMACK_OPTIMIZE_CACHE_URL . $cache->getname();

        // CDN-replace the resulting URL if needed...
        $url = $this->url_replace_cdn( $url );

        return $url;
    }
}