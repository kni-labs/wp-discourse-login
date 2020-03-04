<?php
/**
 * Admin options for WP Discourse Login
 */

function wpdlg_register_settings() {
	add_option( 'wpdlg_discourse_url', 'Discourse URL' );
	register_setting( 'wpdlg_discourse_settings', 'wpdlg_discourse_url' );
	add_option( 'wpdlg_discourse_secret', 'Discourse Secret' );
	register_setting( 'wpdlg_discourse_settings', 'wpdlg_discourse_secret' );
	add_option( 'wpdlg_discourse_meta', 'Discourse Meta Key' );
	register_setting( 'wpdlg_discourse_settings', 'wpdlg_discourse_meta' );
	add_option( 'wpdlg_discourse_meta', 'Discourse API Key' );
	register_setting( 'wpdlg_discourse_settings', 'wpdlg_discourse_api', array('sanitize_callback' => 'sanitize_key') );
}
add_action( 'admin_init', 'wpdlg_register_settings' );

function wpdlg_register_options_page() {
	add_options_page( 'WP Discourse Login Settings', 'WP Discourse Login', 'manage_network_options', 'wp-discourse-login', 'wpdlg_option_page' );
}
add_action( 'admin_menu', 'wpdlg_register_options_page' );

function wpdlg_option_page() { ?>
  <div>
	<?php screen_icon(); ?>
  <h2>WP Discourse Login Options</h2>
  <form method="post" action="options.php">
	<?php settings_fields( 'wpdlg_discourse_settings' ); ?>
  <h3>Discourse URL</h3>
  <p>The URL of where your Discourse Instance is hosted.</p>
  <input type="text" id="wpdlg_discourse_url" name="wpdlg_discourse_url" value="<?php echo esc_attr( get_option( 'wpdlg_discourse_url' ) ); ?>" />
  <h3>SSO Secret</h3>
  <p>Your SSO secret. Should match secret on discourse instance</p>
  <input type="text" id="wpdlg_discourse_secret" name="wpdlg_discourse_secret" value="<?php echo esc_attr( get_option( 'wpdlg_discourse_secret' ) ); ?>" />
  <h3>Meta Key</h3>
  <p>The meta name by which this user ID will be associated to the Discourse forum</p>
  <input type="text" id="wpdlg_discourse_meta" name="wpdlg_discourse_meta" value="<?php echo esc_attr( get_option( 'wpdlg_discourse_meta' ) ); ?>" />
  <h3>API Key</h3>
  <p>The API Key of the Discourse forum</p>
  <input type="text" id="wpdlg_discourse_api" name="wpdlg_discourse_api" value="<?php echo esc_attr( get_option( 'wpdlg_discourse_api' ) ); ?>" />
	<?php submit_button(); ?>
  </form>
  </div>
	<?php
}

function wpdlg_sanitize_url(){

}
