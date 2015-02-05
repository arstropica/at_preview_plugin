<?php
    /**
    * ArsTropica Responsive Framework at-preview-guestlogin.php
    * 
    * PHP version 5
    * 
    * @category   Theme WordPress Plugin Include 
    * @package    WordPress
    * @author     ArsTropica <info@arstropica.com> 
    * @copyright  2014 ArsTropica 
    * @license    http://opensource.org/licenses/gpl-license.php GNU Public License 
    * @version    1.0 
    * @link       http://pear.php.net/package/ArsTropica Reponsive Framework
    * @subpackage ArsTropica Responsive Framework
    * @see        References to other sections (if any)...
    */
?>
<?php
    /**
    * Get current URI.
    *
    * @since 1.0
    * 
    * @return string   Return 
    */
    function at_preview_get_current_uri() {
        $s = empty($_SERVER["HTTPS"]) ? '': ($_SERVER["HTTPS"] == "on") ? "s" : "";
        $protocol = strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s;
        $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
        return $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI'];
    }
?>
<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (isset($_GET['redirect'], $_GET['guestlogin'])) {

    //Climb dirs till we find wp-blog-header (Varies depending on wordpress install)
    while (! file_exists('wp-blog-header.php') )
        chdir('..'); 

    //Needed for user functions
    require ("wp-blog-header.php");

    global $current_user;
    $at_preview_enable_auth = get_option('at_preview_enable_auth', false);

    //The username of the "guest" or "test" account we are going to manage
    $at_preview_user_login = 'guest';

    if (! $at_preview_enable_auth) {
        //If username already exists jump to the customizer  
        if ( username_exists( $at_preview_user_login ) ) {
            //Now  Login as the new test user
            $user = get_user_by('login', $at_preview_user_login);
            $at_preview_user_role = get_role('theme_options_preview');
            $at_preview_user_role->add_cap('edit_theme_options', true);
            $user->set_role('theme_options_preview'); // Assign that role to the new user
            $user_id = $user->ID;
            wp_set_current_user($user_id, $at_preview_user_login);
            wp_set_auth_cookie($user_id);
            do_action('wp_login', $at_preview_user_login);
            //Username Exists. But are they a user of this blog on multisite? Lets check first...
            global $blog_id;
            if( !is_user_member_of_blog() ) {
                add_user_to_blog($blog_id, $current_user->ID, 'theme_options_preview');
            }
            //Ok, we're all done making sure everything works, let's take the Guest to their customizer
            wp_redirect(rawurldecode($_GET['redirect']));
            exit; 
        } else {
            // else create the account and give them permission to theme_options
            wp_create_user( $at_preview_user_login, 'SomeReallyLongForgettablePassworderp1234567654321', 'Guest@' . preg_replace('/^www\./','',$_SERVER['SERVER_NAME']) );
            $at_preview_user= new WP_User( null, $at_preview_user_login );
            //Let's create a new role for this type of user to manage permissions more adequately 
            $at_preview_user_role = add_role('theme_options_preview', 'Theme Options Preview', array(
            'read' => true, 
            'edit_posts' => false,
            'delete_posts' => false, // Use false to explicitly deny
            'edit_theme_options' => true, //This is the magic
            ));
            $at_preview_user->set_role('theme_options_preview'); // Assign that role to the new user
            wp_redirect(at_preview_get_current_uri());
            exit; 
        }                              
    } elseif ($current_user->user_login != 'guest') {
        wp_logout();
        include(dirname(__FILE__) . '/at-preview-login.phtml');
    } elseif ($current_user->user_login == 'guest') {
        wp_redirect(rawurldecode($_GET['redirect']));
        exit; 
    } else {
        wp_redirect(rawurldecode($_GET['redirect']));
        exit; 
    }
} elseif (isset($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
} else {
    header('Location: ' . $_SERVER['HTTP_HOST']);
    exit;
}
    exit;
