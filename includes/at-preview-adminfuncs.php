<?php
    /**
    * ArsTropica Responsive Framework at-preview-adminfuncs.php
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
if ( ! defined('WP_ADMIN') )
    define('WP_ADMIN', true);

if ( ! defined('WP_NETWORK_ADMIN') )
    define('WP_NETWORK_ADMIN', false);

if ( ! defined('WP_USER_ADMIN') )
    define('WP_USER_ADMIN', false);

if ( ! WP_NETWORK_ADMIN && ! WP_USER_ADMIN ) {
    define('WP_BLOG_ADMIN', true);
}

if ( isset($_GET['import']) && !defined('WP_LOAD_IMPORTERS') )
    define('WP_LOAD_IMPORTERS', true);

$wordpress_root = dirname(__FILE__);
while (! file_exists($wordpress_root . '/wp-admin') && (strlen($wordpress_root) > 5)) {
    $wordpress_root = @dirname($wordpress_root);                                      
}

require_once($wordpress_root . '/wp-load.php');

nocache_headers();

require_once(ABSPATH . 'wp-admin/includes/admin.php');
