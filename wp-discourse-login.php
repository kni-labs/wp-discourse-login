<?php
/**
 * Plugin Name: WP Discourse Login
 * Description: Allow SSO using Discourse as the provider
 * Version: 0.0.1
 * Author: Jake Ols
 * Author URI: https://ols.engineer
 *
 * Modified code from the official WP Discourse plugin: https://github.com/discourse/wp-discourse/
 */

require_once plugin_dir_path( __FILE__ ) . 'lib/admin-options.php';
require_once plugin_dir_path( __FILE__ ) . 'lib/nonce.php';
require_once plugin_dir_path( __FILE__ ) . 'lib/utilities.php';
require_once plugin_dir_path( __FILE__ ) . 'lib/sso.php';
add_filter( 'query_vars', 'discourse_sso_custom_query_vars' );
add_action( 'parse_query', 'discourse_sso_url_redirect' );
new WPDiscourseLogin\SSO();
