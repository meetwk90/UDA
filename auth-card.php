<?php
/*
Plugin Name: Authorization Card 
Description: 
Version: 0.1.0
Author: Walter Wan
Author URI: http://meetwk90.github.io
*/

register_activation_hook(__FILE__, function() {
	flush_rewrite_rules();
	wp_insert_post(array('post_type'=>'page', 'post_title'=>'Authorization Card', 'post_status'=>'publish', 'post_content'=>'[auth-card]'));
	wp_insert_post(array('post_type'=>'page', 'post_title'=>'Change Request', 'post_status'=>'publish', 'post_content'=>'[change-request]'));
	wp_insert_post(array('post_type'=>'page', 'post_title'=>'Auth Card Approval', 'post_status'=>'publish', 'post_content'=>'[auth-card-approval]'));
});

register_deactivation_hook(__FILE__, function() {
	flush_rewrite_rules();
	$page = get_posts(array('name'=>'Authorization Card', 'post_type'=>'page'))[0];
	wp_delete_post($page->ID, true);
	$page = get_posts(array('name'=>'Change Request', 'post_type'=>'page'))[0];
	wp_delete_post($page->ID, true);
	$page = get_posts(array('name'=>'Auth Card Approval', 'post_type'=>'page'))[0];
    wp_delete_post($page->ID, true);
});

add_action('init', function() {
	register_post_type('auth_card', array(
		'label'=>'Auth Card',
		'show_ui'=>true,
		'show_in_menu'=>true,
		'supports'=>array('title'),
		'menu_icon'=>'dashicons-networking'
	));
	add_shortcode('auth-card', function() {
		require plugin_dir_path(__FILE__) . '/front.php';
	});
	add_shortcode('change-request', function() {
		require plugin_dir_path(__FILE__) . '/change-request.php';
	});
	add_shortcode('auth-card-approval', function() {
		require plugin_dir_path(__FILE__) . '/auth-card-approval.php';
	});
});

add_action('parse_query', function($wp_query) {
	add_action('wp_enqueue_scripts', function() {
		wp_enqueue_style('listbox');
		wp_enqueue_script('listbox');
		wp_enqueue_style('datetimepicker');
		wp_enqueue_script('datetimepicker');
	});
});

function get_email_address($full_address) {
	is_email(trim($full_address)) && $email = trim($full_address);
    preg_match('/\<(.*?)\>/', $full_address, $matches);
    $matches && $email = $matches[1];
    return $email;
}