<?php
    /**
    * ArsTropica Responsive Framework at-preview.php
    * 
    * PHP version 5
    * 
    * @category   Theme WordPress Plugins 
    * @package    WordPress
    * @author     ArsTropica <info@arstropica.com> 
    * @copyright  2014 ArsTropica 
    * @license    http://opensource.org/licenses/gpl-license.php GNU Public License 
    * @version    1.0 
    * @link       http://pear.php.net/package/ArsTropica Reponsive Framework
    * @subpackage ArsTropica Responsive Framework
    * @see        References to other sections (if any)...
    */

    /*
    Plugin Name: ArsTropica Guest Theme Preview Plugin
    Plugin URI: http://arstropica.com/
    Description: Allows guests to preview theme options.  Based on GreenThe.me plugin.
    Version: 1.0
    Author: ArsTropica
    Author URI: http://arstropica.com/
    License: GPLv2 or later
    Text Domain: at-preview
    */


    /**
    * Loads plugin, if enabled in Theme Settings.
    * 
    * @since 1.0
    * @return void 
    */
    function at_preview_init() {
        global $at_theme_custom, $at_preview;
        $theme = wp_get_theme();
        $template = $theme->get_template();
        $theme_mod = trailingslashit($theme->get_template_directory()) . 'lib/theme_mod.php';
        if (($template == 'arstropica') && (file_exists($theme_mod))) {
            require_once($theme_mod);
            if (class_exists('at_responsive_theme_mod')) {
                if (!is_object($at_theme_custom)) {
                    $at_theme_custom = new at_responsive_theme_mod();
                }
                $enable_plugin = $at_theme_custom->get_option('settings/enabledemo', true);
                if ($enable_plugin) {
                    if (get_transient('at_preview_initialized') === false) {
                        set_transient('at_preview_initialized', 'off'); 
                    }
                    $at_preview = new at_preview_plugin();
                } else {
                    delete_transient('at_preview_initialized');
                }
            } else {
                delete_transient('at_preview_initialized');
            }                                
        }
    }

    /**
    * Removes Login and Role upon Deactivation.
    * 
    * @since 1.0
    * @return void 
    */
    function at_preview_deactivation() {

        if (is_plugin_active_for_network(plugin_basename( __FILE__ ))) {
            $blogs = wp_get_sites();
            foreach ( $blogs as $blog ) {
                switch_to_blog( $blog[ 'blog_id' ] );
                at_remove_guest();
                restore_current_blog();
            }
        } else {
            at_remove_guest();
        }

    }

    /*
    * Remove Guest User and Preview Role 
    * @since 1.0
    * @return bool $success 
    */
    function at_remove_guest()
    {
        global $wp_roles;
        
        $success = false;

        if ( username_exists( 'guest' ) ) {

            $guest = get_user_by( 'login', 'guest' );

            if ($guest && is_a($guest, 'WP_User')) {
                $success = true;
                wp_delete_user( $guest->ID );
            }
        }

        if ($wp_roles->is_role('theme_options_preview')) {
            remove_role('theme_options_preview');
        } else {
            $success = false;
        }
        
        return $success;
    }

    at_preview_init();

    /*Register Deactivation Hook*/
    register_deactivation_hook( __FILE__, 'at_preview_deactivation' );



    /**
    * Front End Demo Previewer
    * 
    * @category   Theme WordPress Plugins
    * @package    WordPress
    * @author     ArsTropica <info@arstropica.com>
    * @copyright  2014 ArsTropica
    * @license    http://opensource.org/licenses/gpl-license.php GNU Public License 
    * @version    Release: @package_version@
    * @link       http://pear.php.net/package/ArsTropica Reponsive Framework
    * @subpackage ArsTropica Responsive Framework
    * @see        References to other sections (if any)...
    */
    class at_preview_plugin {


        public $page_hook;

        private $setting_errors = array();

        private $setting_msgs = array();

        /**
        * Constructor.
        *
        * @since 1.0
        * 
        * @return void 
        */
        public function __construct() {

            // Definitions
            define('AT_PREVIEW_VERSION', '4.2.2');
            define('AT_PREVIEW_DIR', dirname(__FILE__));
            define('AT_PREVIEW_URL', plugins_url('',__FILE__));

            // Actions
            add_action( 'plugins_loaded', array($this, '_at_preview_wp_customize_include') );
            add_action( 'init', array($this, 'at_preview_add_rewrite_rules') );
            add_action( 'init', array($this, 'at_preview_restrict_admin_with_redirect') );
            add_action( 'template_redirect', array($this, 'at_preview_guest_login_iframe') );
            add_action( 'admin_enqueue_scripts', array($this, '_at_preview_wp_customize_loader_settings' ));
            add_action( 'admin_bar_menu', array($this, 'at_preview_customize_menu' ));
            add_action( 'wp_before_admin_bar_render', array($this, 'at_preview_simplify_menu' ));  
            add_action( 'admin_menu', array($this, 'at_preview_register_theme_page') );
            add_action( 'admin_init', array($this, 'at_preview_add_options_metaboxes') );
            add_action( 'wp_head', array($this, 'if_guest_redirect') );
            add_action( 'admin_head', array($this, 'if_guest_redirect') );
            add_action( 'admin_head', array($this, 'at_preview_theme_page_js') );

            // Filters
            add_filter( 'query_vars', array($this, 'at_prevew_query_vars' ) );
            add_filter( 'request', array($this, 'at_preview_set_ep_vars' ) );
            add_filter( 'template_include', array($this, 'at_preview_handle_demo'), 99 );
            add_filter( 'get_user_metadata', array($this, 'at_preview_remove_admin_bar'), 10, 3 );

            // Shortcodes
            add_shortcode('guestpreview' , array($this, 'at_preview_autologin_link'));

            /* Loads the plugin's translated strings. */
            // load_plugin_textdomain('at-preview', false, plugin_basename(AT_PREVIEW_DIR).'/lang');            

        }

        /**
        * Add rewrite rules.
        *
        * @since 1.0
        * 
        * @return void
        */
        public function at_preview_add_rewrite_rules() {
            global $wp_rewrite;

            add_rewrite_rule('guestpreview([^/]*)/?', 'index.php?guestpreview=1&$matches[1]', 'top');
            add_rewrite_rule('demo([^/]*)/?', 'index.php?demo=1&$matches[1]', 'top');
            if (get_transient('at_preview_initialized') == 'off') {
                set_transient('at_preview_initialized', 'on');
                $wp_rewrite->flush_rules(true);  // This should really be done in a plugin activation, but we'll do it with transients.
            }
        }


        /**
        * Redirect calls to guestpreview to login script.
        *
        * @since 1.0
        * 
        * @return void 
        */
        public function at_preview_guest_login_redirect($redirect = false)
        {
            global $wp_query;

            if ( get_query_var('guestpreview') === false || strpos($_SERVER['REQUEST_URI'], '/guestpreview') === false)
            {
                return;
            }

            // $redirect_uri = rawurlencode(home_url( '/demo' ));
            // $redirect_uri = rawurlencode(wp_nonce_url(home_url('/demo'), 'preview-customize_' . basename(get_stylesheet_directory()), 'nonce'));
            // $guestlogin_url = plugins_url('/includes/at-preview-customize.php', __FILE__);
            $redirect_uri = rawurlencode(plugins_url('/includes/at-preview-customize.php', __FILE__));
            $guestlogin_url = plugins_url('/includes/at-preview-guestlogin.php?guestlogin=1&redirect='.$redirect_uri, __FILE__);
            if ($redirect) {
                wp_redirect($guestlogin_url);
                exit;          
            } else {
                return $guestlogin_url;
            }
        }

        /**
        * Display IFrame to hold Guest Previewer.
        *
        * @since 1.0
        * 
        * @return void 
        */
        public function at_preview_guest_login_iframe()
        {
            global $wp_query;

            if ( get_query_var('guestpreview') === false || (strpos($_SERVER['REQUEST_URI'], '/guestpreview') === false) )
            {
                return;
            }
            $guestlogin_url = $this->at_preview_guest_login_redirect(false);
            $iframe_html = <<<HTML
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
        <HTML id="at-guest-top" xmlns="http://www.w3.org/1999/xhtml" lang="EN"> 
        <HEAD> 
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> 
        <title>Guest Preview</title>        
        <style type="text/css">
        HTML#at-guest-top
        {
            overflow: auto;
        }
        IFRAME#at-guest-demo, #at-guest-demo > HTML, IFRAME#at-guest-demo > HTML > BODY, HTML#at-guest-top, HTML#at-guest-top > BODY
        {
            margin: 0px;
            padding: 0px;
            height: 100%;
            border: none;
        }
        IFRAME#at-guest-demo, IFRAME#at-guest-top
        {
            display: block;
            width: 100%;
            border: none;
            overflow-y: auto;
            overflow-x: hidden;
        }
        </style>
        </HEAD> 
        <BODY> 
        <IFRAME id="at-guest-demo" name="demo" src="{$guestlogin_url}" frameborder="0" marginheight="0" marginwidth="0" width="100%" height="100%" scrolling="auto"></IFRAME>
        </BODY> 
        </html>

HTML;
            echo $iframe_html;        
            exit; 
        }

        /**
        * Add guestpreivew and demo query vars.
        *
        * @since 1.0
        * 
        * @return array   Return 
        */
        public function at_prevew_query_vars( $query_vars ){
            // add guestpreview & demo to the array of recognized query vars
            $query_vars[] = 'guestpreview';
            $query_vars[] = 'demo';
            return $query_vars;
        }

        /**
        * Make sure that 'get_query_var( 'xxxx' )' will not return just an empty string if it is set.
        *
        * @param  array $vars
        * @return array
        */
        public function at_preview_set_ep_vars( $vars )
        {
            foreach( array('guestpreview', 'demo') as $_var)
                isset( $vars[$_var] ) and $vars[$_var] = true;

            return $vars;
        }

        /**
        * Includes customizer template for demo endpoint.
        *
        * @since 1.0
        * 
        * @return string   Return 
        */
        public function at_preview_handle_demo($template) {

            if ( get_query_var( 'demo' ) )
            {
                $template =  dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'at-preview-customize.php';
            }
            return $template;
        }

        /**
        * Includes and instantiates the WP_Customize_Manager class.
        *
        * Fires when ?wp_customize=on or on wp-admin/customize.php.
        *
        * @since 1.0
        * 
        * @return void 
        */
        public function _at_preview_wp_customize_include() {
            if ( ! ( ( isset( $_REQUEST['demo'] ) && '1' == $_REQUEST['demo'] )
                || ( 'at-preview-customize.php' == basename( $_SERVER['PHP_SELF'] ) )
                || ( strpos($_SERVER['REQUEST_URI'], '/demo') !== false )
                ) )
                return;

            require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
            // Init Customize class
            $GLOBALS['wp_customize'] = new WP_Customize_Manager;
        }

        /**
        * Substitutes WP_Customize_Manager's wp_loaded method.
        *
        * @since 1.0
        * 
        * @return void 
        */
        public function at_preview_wp_loaded() {
            global $wp_customize;

            do_action( 'customize_register', $wp_customize );

            if ( $wp_customize->is_preview() && ! is_admin() )
                $this->at_preview_customize_preview_init();
        }

        /**
        * Print javascript settings.
        *
        * @since 1.0
        */
        public function at_preview_customize_preview_init() {
            global $wp_customize;

            $wp_customize->prepare_controls();

            wp_enqueue_script( 'customize-preview' );
            add_action( 'wp_head', array( $wp_customize, 'customize_preview_base' ) );
            add_action( 'wp_head', array( $wp_customize, 'customize_preview_html5' ) );
            add_action( 'wp_footer', array( $wp_customize, 'customize_preview_settings' ), 20 );
            add_action( 'shutdown', array( $wp_customize, 'customize_preview_signature' ), 1000 );
            add_filter( 'wp_die_handler', array( $wp_customize, 'remove_preview_signature' ) );

            foreach ( $wp_customize->settings as $setting ) {
                $setting->preview();
            }

            /**
            * Fires once the Customizer preview has initialized and JavaScript
            * settings have been printed.
            *
            * @since 3.4.0
            *
            * @param WP_Customize_Manager $this WP_Customize_Manager instance.
            */
            do_action( 'customize_preview_init', $wp_customize );
        }

        /**
        * Adds settings for the customize-loader script.
        *
        * @since 1.0
        * 
        * @return void 
        */
        public function _at_preview_wp_customize_loader_settings() {
            global $wp_scripts;

            $admin_origin = parse_url( admin_url() );
            $home_origin  = parse_url( home_url() );
            $cross_domain = ( strtolower( $admin_origin[ 'host' ] ) != strtolower( $home_origin[ 'host' ] ) );

            $browser = array(
                'mobile' => wp_is_mobile(),
                'ios'    => wp_is_mobile() && preg_match( '/iPad|iPod|iPhone/', $_SERVER['HTTP_USER_AGENT'] ),
            );

            $settings = array(
                'url'           => esc_url( home_url( 'guestpreview' )  ),
                'isCrossDomain' => $cross_domain,
                'browser'       => $browser,
            );

            $script = 'var _wpCustomizeLoaderSettings = ' . json_encode( $settings ) . ';';

            $data = $wp_scripts->get_data( 'customize-loader', 'data' );
            if ( $data )
                $script = "$data\n$script";

            $wp_scripts->add_data( 'customize-loader', 'data', $script );
        }

        /**
        * Returns a URL to load the theme customizer.
        *
        * @since 1.0
        *
        * @param string $stylesheet Optional. Theme to customize. Defaults to current theme. The theme's stylesheet will be urlencoded if necessary.
        * 
        * @return string    Return 
        */
        public function at_preview_wp_customize_url( $stylesheet = null ) {
            $url = home( '/guestpreview' );
            if ( $stylesheet )
                $url .= '?theme=' . urlencode( $stylesheet );
            return esc_url( $url );
        }

        /**
        *    Expose the Customizer Preview by adding a link in the admin bar.
        *
        * @since 1.0
        * 
        * @return void 
        *
        */
        public function at_preview_customize_menu($admin_bar) {
            global $current_user;
            get_currentuserinfo();
            if ($current_user->user_login == 'guest') {
                $admin_bar->add_menu( array (
                    'id' => 'customizer-preview',
                    'title' => 'Guest Preview',
                    'href' => home_url( '/guestpreview' ),
                    'meta' => array(
                        'title' => __('ArsTropica Responsive Theme Customizer Preview'),
                    ),
                ));                                       
                $admin_bar->add_menu( array (
                    'id' => 'customizer-site-name',
                    'title' => __(ucwords(get_current_theme()) . " Theme"),
                    'href' => home_url( '/guestpreview' ),
                    'meta' => array(
                        'title' => __(ucwords(get_current_theme()) . ' Theme Customizer Preview'),
                    ),
                ));                                       
            }
        }

        /**
        *    Simplify the admin bar.
        *
        * @since 1.0
        * 
        * @return void 
        *
        */
        public function at_preview_simplify_menu() {
            global $current_user, $wp_admin_bar;
            get_currentuserinfo();
            if ($current_user->user_login == 'guest') {
                $wp_admin_bar->remove_menu('site-name');  
                $wp_admin_bar->remove_menu('updates');  
                $wp_admin_bar->remove_menu('comments');  
                $wp_admin_bar->remove_menu('my-sites');  
            }
        }

        /** 
        * IF the test user tries to view admin, take them back home
        * 
        *
        * @since 1.0
        * 
        * @return void 
        *
        */
        public function at_preview_restrict_admin_with_redirect() {

            function endswith($string, $test) {
                $strlen = strlen($string);
                $testlen = strlen($test);
                if ($testlen > $strlen) return false;
                return substr_compare($string, $test, -$testlen) === 0;
            }
            //Get current user's role
            global $current_user;
            $user_roles = $current_user->roles;
            $user_role = array_shift($user_roles);

            if( is_admin() &&
            $user_role == 'theme_options_preview' 
            && !endswith($_SERVER['PHP_SELF'], '/wp-admin/admin-ajax.php')
            && !endswith($_SERVER['PHP_SELF'], '/demo') 
            && !endswith($_SERVER['PHP_SELF'], '/includes/at-preview-customize.php') 
            && !stristr($_SERVER['REQUEST_URI'], 'theme_export_options=safe_download') 
            && !stristr($_SERVER['REQUEST_URI'], 'theme_export_options=safe_email') 
            ) {
                wp_redirect(site_url() ); exit;        
            }
        }

        /** 
        * Create Shortcode to drop the login and redirect link
        *
        * Usage: [guestpreview]Preview Theme[/guestpreview]
        * 
        * @since 1.0
        *
        * @return string    Return 
        * 
        */
        public function at_preview_autologin_link($atts, $content = null) {
            extract(shortcode_atts(array('link' => home_url('/guestpreview')), $atts));
            return '<a class="button" href="'.$link.'"><span>' . do_shortcode($content) . '</span></a>';
        }

        /** 
        * Remove WPBar if the user's role is Theme_Options_Preview
        *
        * @param null $retval
        * @param int $object_id ID of the user.
        * @param string $meta_key Meta key being fetched.
        * @since 1.0
        *
        * @return string    Return 
        * 
        */
        public function at_preview_remove_admin_bar( $null, $user_id, $key )
        {
            global $current_user;
            if ($current_user != null) {
                $user_roles = $current_user->roles;
                $user_role = array_shift($user_roles); 
            }
            if( 'show_admin_bar_front' != $key ) return null;
            if( $user_role == 'theme_options_preview' ) return 0;
            return null;
        }

        /** 
        * Register Guest Preview Page Link
        *
        * @since 1.0
        *
        * @return void
        * 
        */
        public function at_preview_register_theme_page()
        {
            /*global $submenu;
            $submenu['themes.php'][49.975] = array( 
            '<div id="at-guest-preview-link">Guest Preview</div>'
            ,   'theme_options_preview' 
            ,   home_url('/guestpreview') 
            );*/
            $this->page_hook = add_theme_page('Guest Preview Options', 'Guest Preview', 'edit_theme_options', 'at-guest-preview', array($this, 'at_preview_settings_page') );
            add_action('admin_print_scripts-' . $this->page_hook, array($this, 'load_settings_scripts'));
        }

        /** 
        * Guest Preview Wrapper Function
        *
        * @since 1.0
        *
        * @return void
        * 
        */
        public function at_preview_settings_page()
        {
            $this->at_preview_process_options();
        ?>
        <div class="wrap">
            <h2>Guest Preview Options</h2>
            <?php
                if ($this->setting_msgs) {
                    foreach ($this->setting_msgs as $message) {
                        echo '<div id="message" class="updated"><p>' . $message . '</p></div>';
                        echo "\n";
                    }
                }
                if ($this->setting_errors) {
                    foreach ($this->setting_errors as $error) {
                        echo '<div class="error"><p>' . $error . '</p></div>';
                        echo "\n";
                    }
                }
            ?>
            <form method="post">
                <div id="poststuff" class="metabox-holder">
                    <!-- Main Content -->
                    <div id="post-body-content">
                        <?php do_meta_boxes('settings_page_at_preview', 'normal', array()) ?>
                    </div>

                    <div>
                        <input type="submit" class='button-primary' name="update_at_preview" value="<?php _e('Save Changes')?>" />
                    </div>

                </div><!--#poststuff-->

            </form>
        </div>
        <?php
        }

        /** 
        * Add Metaboxes to Options Page
        *
        * @since 1.0
        *
        * @return void
        * 
        */
        public function at_preview_add_options_metaboxes()
        {
            add_meta_box(
                'at_preview_password',
                __( 'Authentication Settings', 'at-preview' ),
                array($this, 'do_auth_metabox'),
                'settings_page_at_preview',
                'normal',
                'core'
            );

            add_meta_box(
                'at_preview_preview',
                __( 'Preview', 'at-preview' ),
                array($this, 'do_preview_metabox'),
                'settings_page_at_preview',
                'normal',
                'core'
            );

        }

        /** 
        * Display Authorization Metabox
        *
        * @since 1.0
        *
        * @return void
        * 
        */
        public function do_auth_metabox()
        {
            // Add an nonce field so we can check for it later.
            wp_nonce_field( 'at_preview_auth_meta_box', 'at_preview_auth_meta_box_nonce' );
            $at_preview_enable_auth = get_option('at_preview_enable_auth', false);
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="at_preview_enable_auth"><?php _e("Enable Authentication", 'at-preview'); ?></label></th>
                    <td>
                        <fieldset>
                            <input type="checkbox" id="at_preview_enable_auth" name="at_preview_enable_auth" value="1" <?php checked($at_preview_enable_auth, '1', true); ?>/>
                            <p class="description"><?php _e( 'It is recommended that you enable authentication to prevent <strong>bots and hackers</strong> from accessing the preview page.', 'at-preview' ); ?></p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="at_preview_auth_password"><?php _e("Enter / Change Guest Password", 'at-preview'); ?></label></th>
                    <td>
                        <fieldset>
                            <input class="hidden" value=" " /><!-- #24364 workaround -->
                            <input type="password" name="pass1" id="pass1" value="" class="at_preview_auth_password regular-text" size="16" value="" autocomplete="off" <?php echo $at_preview_enable_auth ? '' : ' disabled="disabled"'; ?> />
                            <span class="description"><?php _e( 'If you would like to change the password type a new one. Otherwise leave this blank.', 'at-preview' ); ?></span>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pass2"><?php _e("Repeat New Password", 'at-preview'); ?></label></th>
                    <td>
                        <fieldset>
                            <input type="password" name="pass2" id="pass2" value="" class="at_preview_auth_password regular-text" size="16" value="" autocomplete="off" <?php echo $at_preview_enable_auth ? '' : ' disabled="disabled"'; ?> />
                            <span class="description" for="pass2"><?php _e( 'Type your new password again.' ); ?></span>
                            <br />
                            <div id="pass-strength-result"><?php _e( 'Strength indicator' ); ?></div>
                            <p class="description indicator-hint"><?php _e( 'Hint: The password should be at least seven characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ &amp; ).', 'at-preview' ); ?></p>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>
        <input name="update_at_preview" value="1" type="hidden" />

        <script type="text/javascript">
            jQuery(document).ready( function($) {   
                $('#at_preview_enable_auth').on('click', function(e){
                    if ($(this).is(':checked')) {
                        $('.at_preview_auth_password').removeClass('disabled').prop('disabled', false).removeProp('disabled');
                    } else {
                        $('.at_preview_auth_password').addClass('disabled').prop('disabled', true);
                    }
                });
            });
        </script>
        <?php
        }

        /** 
        * Display Preview Metabox
        *
        * @since 1.0
        *
        * @return void
        * 
        */
        public function do_preview_metabox()
        {
        ?>
        <div>
            <h4>Guest Preview</h4>
            <p><a class="button button-secondary button-hero" href="<?php echo site_url('/guestpreview'); ?>/" target="_blank">Preview Site</a></p>
            <p><strong>Note</strong>: You may need to <a href="<?php echo admin_url( 'options-permalink.php?settings-updated=true' ); ?>">refresh your permalinks</a> before the page can display.</p>
        </div>        
        <?php
        }

        /** 
        * Process Settings Values
        *
        * @since 1.0
        *
        * @return void
        * 
        */
        public function at_preview_process_options()
        {
            if ( ! empty( $_POST ) && check_admin_referer( 'at_preview_auth_meta_box', 'at_preview_auth_meta_box_nonce' ) ) {
                if (isset($_POST['update_at_preview'])) {
                    $at_preview_enable_auth = isset($_POST['at_preview_enable_auth']) ? $_POST['at_preview_enable_auth'] : 0;
                    if ($at_preview_enable_auth) {
                        //The username of the "guest" or "test" account we are going to manage
                        $at_preview_guest_login = 'guest';
                        $pass1 = $pass2 = '';
                        $at_preview_user_exists = username_exists( $at_preview_guest_login );
                        if ( isset( $_POST['pass1'] ) )
                            $pass1 = $_POST['pass1'];
                        if ( isset( $_POST['pass2'] ) )
                            $pass2 = $_POST['pass2'];

                        if ( empty($pass1) && !empty($pass2) ) {
                            $this->setting_errors['pass'] = __( '<strong>ERROR</strong>: You entered your new password only once.' );
                        } elseif ( !empty($pass1) && empty($pass2) ) {
                            $this->setting_errors['pass'] = __( '<strong>ERROR</strong>: You entered your new password only once.' );
                        } elseif ( empty($pass1) && ! $at_preview_user_exists ) {
                            $this->setting_errors['pass'] = __( '<strong>ERROR</strong>: You did not enter a password.' );
                        } else {
                            //If username already exists jump to the customizer  
                            if ( $at_preview_user_exists ) {
                                //Now  Login as the new test user
                                $at_preview_user = get_user_by('login', $at_preview_guest_login);
                                $at_preview_user->user_pass = $pass1;
                                $user_id = wp_update_user( $at_preview_user );
                                if (! $this->check_role_caps($at_preview_user)) {
                                    $this->setting_errors['caps'] = __( 'The user <strong>' . $at_preview_guest_login . '</strong> could not be updated.' );
                                }
                                $this->setting_msgs['pass'] = __( 'Password for <strong>' . $at_preview_guest_login . '</strong> has been updated.' );
                            } else {
                                // else create the account and give them permission to theme_options
                                wp_create_user( $at_preview_guest_login, $pass1, 'Guest@' . preg_replace('/^www\./','',$_SERVER['SERVER_NAME']) );
                                $at_preview_user= new WP_User( null, $at_preview_guest_login );
                                //Let's create a new role for this type of user to manage permissions more adequately 
                                if (! $this->check_role_caps($at_preview_user)) {
                                    $this->setting_errors['caps'] = __( 'The user <strong>' . $at_preview_guest_login . '</strong> could not be created.' );
                                }
                                $at_preview_user->set_role('theme_options_preview'); // Assign that role to the new user
                                $this->setting_msgs['user'] = __( 'New user <strong>' . $at_preview_guest_login . '</strong> has been created.' );
                                $this->setting_msgs['pass'] = __( 'Password for <strong>' . $at_preview_guest_login . '</strong> has been created.' );
                            }
                        }                            
                    }
                    if (! $this->setting_errors) {
                        update_option('at_preview_enable_auth', $at_preview_enable_auth);
                        $this->setting_msgs['auth'] = __( 'Settings Updated.' );
                    }
                }
            }
        }

        /** 
        * Check Capabilities
        *
        * @param WP_User $user Optional. WordPress User Object
        * @since 1.0
        *
        * @return void
        * 
        */
        public function check_role_caps($at_preview_user = null)
        {
            if (! $at_preview_user) $at_preview_user = get_user_by('login', 'guest');

            $at_preview_user_role = get_role('theme_options_preview');
            if (! $at_preview_user_role) {
                $at_preview_user_role = add_role('theme_options_preview', 'Theme Options Preview', array(
                    'delete_posts' => false, // Use false to explicitly deny
                    'read' => false, // true allows this capability
                    'edit_posts' => false, // Allows user to edit their own posts
                    'edit_pages' => false, // Allows user to edit pages
                    'edit_others_posts' => false, // Allows user to edit others posts not just their own
                    'create_posts' => false, // Allows user to create new posts
                    'manage_categories' => false, // Allows user to manage post categories
                    'publish_posts' => false, // Allows the user to publish, otherwise posts stays in draft mode
                    'edit_themes' => false, // false denies this capability. User can’t edit your theme
                    'install_plugins' => false, // User cant add new plugins
                    'update_plugin' => false, // User can’t update any plugins
                    'update_core' => false, // user cant perform core updates
                    'edit_theme_options' => true, //This is the magic
                ));
            }
            if ($at_preview_user_role) {
                $at_preview_user_role->add_cap('edit_theme_options', true);
                $at_preview_user_role->add_cap('read', true);
                $at_preview_user_role->add_cap('edit_posts', false);
                $at_preview_user_role->add_cap('delete_posts', false);
            } else {
                return false;
            }
            if ($at_preview_user) {
                $at_preview_user->set_role('theme_options_preview'); 
            } else {
                return false;
            }
            return true;
        }

        /** 
        * Enqueue Scripts for Settings Page
        *
        * @since 1.0
        *
        * @return void
        * 
        */
        public function load_settings_scripts ()
        {
            wp_enqueue_script('wp-auth-check');
            wp_enqueue_script('wp-pointer');
            wp_enqueue_script('user-profile');
            wp_enqueue_script('user-suggest');
            wp_enqueue_script('password-strength-meter');
        }



        /** 
        * Open Guest Preview Page in new window
        *
        * @since 1.0
        *
        * @return void
        * 
        */
        public function at_preview_theme_page_js( )
        {
        ?>
        <script type="text/javascript">
            jQuery(document).ready( function($) {   
                $('#at-guest-preview-link').parent().attr('target','_blank');  
            });
        </script>
        <?php
        }

        /**
        * Add theme mod / option filters
        * 
        * @since 1.0
        * 
        * @return void 
        */
        public function maybe_filter_theme_mod() {
            if (stristr($_SERVER['HTTP_REFERER'], 'at-preview-customize.php')) {
                global $wp_customize;
                $settings = $wp_customize->settings();
                if ($settings) {
                    foreach ($settings as $setting) {
                        $_name = $setting->id;
                        $_type = $setting->type;
                        switch ($_type) {
                            case 'get_theme_mod' : {
                                add_filter( "theme_mod_{$_name}", array($this, "do_filter_theme_mod"), 10, 1 );
                                break;
                            }
                            case 'get_option' : {
                                add_filter( "pre_option_{$_name}", array($this, "do_filter_theme_mod"), 10, 1 );
                                break;
                            }
                        }
                        // echo "<pre>" . print_r("theme_mod_{$_name}", true) . "</pre>\n";
                    }
                }
            }
        }

        /**
        * Add global JS variables
        * 
        * @since 1.0
        * 
        * @return void 
        */
        function global_js_vars() {
            global $current_user;
            if (is_user_logged_in()) {
                get_currentuserinfo();
                $uname = '"' . $current_user->user_login . '"';
            } else {
                $uname = 'false';
            }

            echo '<script type="text/javascript">
            /* <![CDATA[ */
            var at_preview_vars = {"current_user": ' . $uname . '};
            /* ]]> */
            </script>';
        }

        /**
        * Redirect IFRAME if guest preview is off
        * 
        * @since 1.0
        * 
        * @return void 
        */
        function if_guest_redirect() {
            global $current_user, $wp_query;
            $guest_logged_in = false;
            if (is_user_logged_in()) {
                get_currentuserinfo();
                if (strtolower($current_user->user_login) == 'guest') {
                    $guest_logged_in = true;
                }
            }

            if (! $guest_logged_in) {
                echo '<script type="text/javascript">
                /* <![CDATA[ */
                if (window.top.location.pathname.indexOf("/guestpreview") >= 0) {
                window.top.location.href = window.location.href;
                }
                /* ]]> */
                </script>';             
            }
        }
    }
