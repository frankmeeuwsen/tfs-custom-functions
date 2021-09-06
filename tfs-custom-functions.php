<?php

/**
 * @package tfs-custom-functions
 * @author  Frank Meeuwsen
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 * @see     https://thanksforsubscribing.app

 * @wordpress-plugin
 * Plugin Name: Thanks for Subscribing Custom Functions
 * Plugin URI: https://thanksforsubscribing.app
 * Description: This plugin contains all of my custom functions.
 * Version: 0.1
 * Author: Frank Meeuwsen
 * Author URI: https://diggingthedigital.com/
 * Text Domain: tfs
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// ============================================
// Crossed out stuff I might use someday
// ============================================
// global $post;
// $tfs_id = get_the_ID( $post->ID );
// BugFu::log($post->ID);
// do_action( 'qm/debug', $parent_post_id );
// error_log(var_export($parent_post_id,1));
// error_log(var_export($parts ,1));
// error_log(var_export($form_fileurl ,1));
// error_log(var_export($attachment ,1));
// error_log(var_export($attach_id ,1));
// error_log(var_export($attach_data ,1));
// ============================================


// // If this file is called directly, abort.
// if ( ! defined( 'WPINC' ) ) {
// 	die;
// }

add_action('admin_bar_menu', 'custom_toolbar_link', 999);
add_action('transition_post_status', 'send_emails_on_new_event', 10, 3);
// add_action( 'draft_to_publish', 'send_integromat_webhook', 10,1);
// Enable random posts
add_action('init', 'random_add_rewrite');
add_action('template_redirect', 'random_template');
//  Gravity Forms fix. After upload, move image to library
add_action('gform_after_submission', 'tfs_gf_after_submission', 10, 2);

// Check if Substack entry, then add archive to the URL
add_action('gform_after_submission', 'tfs_check_substack', 11, 2);

// Autopost to Twitter after publish
// Make this function run whenever it changes from any of these statuses to 'publish':
// add_action('auto-draft_to_publish', 'twitter_auto_post', 10, 1);
add_action('draft_to_publish', 'twitter_auto_post', 10, 1);
// add_action('future_to_publish', 'twitter_auto_post', 10, 1);
add_action('new_to_publish', 'twitter_auto_post', 10, 1);
add_action('pending_to_publish', 'twitter_auto_post', 10, 1);

// Add API keys to tools
add_action('admin_menu', 'wpdocs_register_my_api_keys_page');
add_action('admin_post_nopriv_process_form', 'submit_api_key');
add_action('admin_post_process_form', 'submit_api_key');


// Make a custom text in the searchbar
add_filter('genesis_search_text', 'custom_search_button_text');

// Enable the option show in rest
add_filter('acf/rest_api/field_settings/show_in_rest', '__return_true');

// Enable the option edit in rest
add_filter('acf/rest_api/field_settings/edit_in_rest', '__return_true');

// Doesnt this do the same?
add_filter('walker_nav_menu_start_el', 'matomo_data_tracker', 10, 2);
// Add data conversion thingis for the stats to Matomo. See if this works. 
add_filter('nav_menu_link_attributes', 'matomo_data_tracker_original', 10, 3);




function matomo_data_tracker_original($atts, $item, $args)
{
    if ('tfs-highlight-button' === $item->classes[0]) {
        $atts['data-track-content'] = '1';
        $atts['data-content-name'] = 'Random click';
        $atts['data-content-piece'] = 'Random newsletter';
        $atts['class'] .= 'matomoTrackContent';
    }

    return $atts;
}


function matomo_data_tracker($item_output, $item)
{
    if (in_array('tfs-highlight-button', $item->classes)) {
        $item_output = str_replace('data-track-content="1"', 'data-track-content', $item_output);
    }

    return $item_output;
}


function twitter_auto_post($post)
{

    // Get ID of the published post:
    $post_id = $post->ID;

    // Check to see if this has already been Tweeted
    // $posted = get_post_meta($post_id, 'social_twitter_posted', true);
    $posted = false;

    // If it hasn't previously been posted, create and post a Tweet:
    if ($posted != 'true') {

        // Include Codebird library. Ensure this matches where you have saved it:
        require_once(get_stylesheet_directory() . '/includes/codebird/src/codebird.php');
        // Set your keys. Get these from your created Twitter App:
        $consumer_key = get_option('tfs_consumer_key');
        $consumer_secret = get_option('tfs_consumer_secret');
        $access_token = get_option('tfs_access_token');
        $access_token_secret = get_option('tfs_access_token_secret');
        
        // Codebird setup:
        \Codebird\Codebird::setConsumerKey($consumer_key, $consumer_secret);
        $cb = \Codebird\Codebird::getInstance();
        $cb->setToken($access_token, $access_token_secret);

        // Get data from the published post:
        $post_title = get_the_title($post_id);
        $post_link = get_the_permalink($post_id);
        $post_twitter = get_field('twitter', $post_id);
        // error_log(var_export($post_twitter, 1));

        // Get one of the random really fun texts!
        $intros_arr = [
            'Here is the newest title on our site!',
            'We added a fresh newsletter to our collection 💌',
            'Need a cool title in your inbox? Check out this one!',
            'Welcome to a new newsletter in our collection 💌',
            'Check this fresh newsletter 💌'
        ];
        shuffle($intros_arr);

        // Compose a status using the gathered data:
        $status =  $intros_arr[0] . ' ' . $post_title . ' ';
        if ($post_twitter) {
            $status .=  'by @' . $post_twitter . ' ';
        }

        $status .= $post_link;

        // Send Tweet with image and status:
        $reply = $cb->statuses_update(array(
            'status'    => $status,
        ));

        // Check status of Tweet submission:
        if ($reply->httpstatus == 200) {
            // Add database entry showing that the Tweet was sucessful:
            add_post_meta($post_id, 'social_twitter_posted', 'true', true);
        } else {
            // Add database entry showing error details if it wasn't successful:
            add_post_meta($post_id, 'social_twitter_posted', $reply->httpstatus, true);
        }
    } else {
        // Tweet has already been posted. This will prevent it being posted again:
        return;
    }
}

function tfs_check_substack($entry, $form)
{
    $parent_post_id = get_post($entry['post_id'])->ID;
    $form_subscribeurl = $entry[5];
    $form_archiveurl = $entry[19];

    if (strpos($form_subscribeurl, 'substack') && empty($form_archiveurl)) {
        update_field('example', $form_subscribeurl . '/archive', $parent_post_id);
    }
}


function tfs_gf_after_submission($entry, $form)
{
    $parent_post_id = get_post($entry['post_id'])->ID;
    $form_fileurl = $entry[15];
    // Check the type of file. We'll use this as the 'post_mime_type'.
    $filetype = wp_check_filetype(basename($form_fileurl), null);

    // Get the path to the upload directory.
    $wp_upload_dir = wp_upload_dir();

    //Gravity forms often uses its own upload folder, so we're going to grab whatever location that is
    $parts = explode('uploads/', $form_fileurl);
    $filepath = $wp_upload_dir['basedir'] .
        '/' . $parts[1];
    $fileurl = $wp_upload_dir['baseurl'] .
        '/' . $parts[1];


    // Prepare an array of post data for the attachment.
    $attachment = array(
        'guid' => $fileurl,
        'post_mime_type' => $filetype['type'],
        'post_title' => preg_replace('/\.[^.]+$/', '', basename($fileurl)),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    // Insert the attachment.
    $attach_id = wp_insert_attachment($attachment, $filepath, $parent_post_id);

    //Image manipulations are usually an admin side function. Since Gravity Forms is a front of house solution, we need to include the image manipulations here.
    require_once(ABSPATH .
        'wp-admin/includes/image.php');

    // Generate the metadata for the attachment, and update the database record.
    if ($attach_data = wp_generate_attachment_metadata($attach_id, $filepath)) {
        wp_update_attachment_metadata($attach_id, $attach_data);
    }

    wp_update_attachment_metadata($attach_id, $attach_data);
    update_field('logo_lokaal', $attach_id, $parent_post_id);
}

// add a link to the WP Toolbar
function custom_toolbar_link($wp_admin_bar)
{
    $args = array(
        'id' => 'tfsdraft',
        'title' => 'Drafts',
        'href' => 'edit.php?post_status=draft&post_type=newsletter',
        'meta' => array(
            'class' => 'wpbeginner',
            'title' => 'Admin drafts of newsletters'
        )
    );
    $wp_admin_bar->add_node($args);
}

// Custom text in the searchbar
function custom_search_button_text($text)
{
    return ('Search...');
}
// Send an email when a submitted newsletter is published
function send_emails_on_new_event($new_status, $old_status, $post)
{
    $tfs_email = get_field('email_adres', $post->ID);
    $tfs_title = get_the_title($post->ID);
    $tfs_link = get_permalink($post->ID);
    $headers = 'From: Frank Meeuwsen <frank@thanksforsubscribing.app>';
    // $title   = wp_strip_all_tags( get_the_title( $post->ID ) );
    $message = 'Hi!

I added your newsletter "' . $tfs_title . '" to the website Thanks for Subscribing at ' . $tfs_link . '

If you\'d like to have anything changed, feel free to reply to this mail. If you want another header or avatar, no problem, just send me new images. The avatar is 100 x 100 px and the header is 600 x 400 px. 

Have a fine day and good luck with your newsletter,

Frank Meeuwsen

Thanks for Subscribing
';

    if (('publish' === $new_status && 'publish' !== $old_status) && 'newsletter' === $post->post_type) {
        wp_mail($tfs_email, 'Your newsletter is added to Thanks for Subscribing', $message, $headers);
    }
};

function random_add_rewrite()
{
    global $wp;
    $wp->add_query_var('really-random');
    add_rewrite_rule('really-random', 'index.php?really-random=1', 'top');
}

function random_template()
{
    if (get_query_var('really-random') == 1) {
        $posts = get_posts('post_type=newsletter&orderby=rand');
        foreach ($posts as $post) {
            $link = get_permalink($post);
        }
        wp_redirect($link, 307);
        exit;
    }
}


// function send_integromat_webhook($post){
// 		$response = wp_remote_post( 'https://hook.integromat.com/nzfo0mdwgiercpnmlq89gbovfz5ce442', array(
// 			'method'      => 'POST',
// 			'timeout'     => 45,
// 			'redirection' => 5,
// 			'httpversion' => '1.0',
// 			'blocking'    => true,
// 			'headers'     => array(),
// 			'body'        => array(
// 				'id' => $post->ID

// 			),
// 			'cookies'     => array()
// 			)
// 		);
// };


// Creates a subpage under the Tools section


function wpdocs_register_my_api_keys_page()
{
    add_submenu_page(
        'tools.php',
        'API Keys',
        'API Keys',
        'manage_options',
        'api-keys',
        'add_api_keys_callback'
    );
}

// The admin page containing the form
function add_api_keys_callback()
{ ?>
    <div class="wrap">
        <div id="icon-tools" class="icon32"></div>
        <h2>My Most Secret API Keys Page</h2>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST">
            <h3>Les Twitter Credentials</h3>
            <input type="text" name="tfs_consumer_key" placeholder="Enter Consumer Key" value="<?php echo get_option('tfs_consumer_key'); ?>"><br />
            <input type="text" name="tfs_consumer_secret" placeholder="Enter Consumer Secret" value="<?php echo get_option('tfs_consumer_secret'); ?>"><br />
            <input type="text" name="tfs_access_token" placeholder="Enter Access Token" value="<?php echo get_option('tfs_access_token'); ?>"><br />
            <input type="text" name="tfs_access_token_secret" placeholder="Enter Access Token Secret" value="<?php echo get_option('tfs_access_token_secret'); ?>"><br />

            <input type="hidden" name="action" value="process_form">
            <input type="submit" name="submit" id="submit" class="update-button button button-primary" value="Update Keys" />
        </form>
    </div>
<?php
}

// Submit functionality
function submit_api_key()
{
    if (isset($_POST['tfs_consumer_key'])) {
        $tfs_consumer_key = sanitize_text_field($_POST['tfs_consumer_key']);
        $tfs_consumer_secret = sanitize_text_field($_POST['tfs_consumer_secret']);
        $tfs_access_token = sanitize_text_field($_POST['tfs_access_token']);
        $tfs_access_token_secret = sanitize_text_field($_POST['tfs_access_token_secret']);

        $tfs_consumer_key_exists = get_option('tfs_consumer_key');
        if (!empty($tfs_consumer_key) && !empty($tfs_consumer_key_exists)) {
            update_option('tfs_consumer_key', $tfs_consumer_key);
            update_option('tfs_consumer_secret', $tfs_consumer_secret);
            update_option('tfs_access_token', $tfs_access_token);
            update_option('tfs_access_token_secret', $tfs_access_token_secret);
        } else {
            add_option('tfs_consumer_secret', $tfs_consumer_secret);
            add_option('tfs_consumer_key', $tfs_consumer_key);
            add_option('tfs_access_token', $tfs_access_token);
            add_option('tfs_access_token_secret', $tfs_access_token_secret);
        }
    }
    wp_redirect($_SERVER['HTTP_REFERER']);
}


?>