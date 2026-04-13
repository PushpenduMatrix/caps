<?php
/**
 * Plugin Name: CAP Trial Registration
 * Description: Player registration and Razorpay payment flow for CAP National Scholarship 2026.
 * Version: 1.0.0
 * Author: Pushpendu Mondal
 * Author URI: https://matrixnmedia.com
 * Text Domain: cap-trial-registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CAP_TRIAL_REG_VERSION', '1.0.0' );
define( 'CAP_TRIAL_REG_PATH', plugin_dir_path( __FILE__ ) );
define( 'CAP_TRIAL_REG_URL', plugin_dir_url( __FILE__ ) );

require_once CAP_TRIAL_REG_PATH . 'includes/class-cap-trial-registration.php';

register_activation_hook( __FILE__, array( 'CAP_Trial_Registration', 'activate' ) );

CAP_Trial_Registration::get_instance();
