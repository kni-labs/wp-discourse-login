<?php
/**
 * Plugin Name: WP Discourse Login
 * Description: Allow SSO using Discourse as the provider
 * Version: 0.0.1
 * Author: Jake Ols
 * Author URI: https://ols.engineer
 *
 * @package WPDiscourseLogin
 * Modified code from the official WP Discourse plugin: https://github.com/discourse/wp-discourse/
 */

 // phpcs:disable Squiz.Commenting.FileComment.Missing

require_once __DIR__ . '/admin-options.php';
require_once __DIR__ . '/lib/class-nonce.php';
require_once __DIR__ . '/lib/class-utilities.php';
require_once __DIR__ . '/lib/class-sso.php';

new WPDiscourseLogin\SSO\SSO();
