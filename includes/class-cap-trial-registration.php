<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/traits/trait-cap-trial-registration-settings.php';
require_once __DIR__ . '/traits/trait-cap-trial-registration-frontend.php';
require_once __DIR__ . '/traits/trait-cap-trial-registration-payments.php';
require_once __DIR__ . '/traits/trait-cap-trial-registration-notifications.php';
require_once __DIR__ . '/traits/trait-cap-trial-registration-admin.php';

class CAP_Trial_Registration {
	use CAP_Trial_Registration_Settings_Trait;
	use CAP_Trial_Registration_Frontend_Trait;
	use CAP_Trial_Registration_Payments_Trait;
	use CAP_Trial_Registration_Notifications_Trait;
	use CAP_Trial_Registration_Admin_Trait;

	const POST_TYPE = 'cap_trial_reg';
	const OPT_KEY   = 'cap_trial_reg_settings';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		self::register_post_type();
		flush_rewrite_rules();
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_shortcode( 'cap_trial_registration_form', array( $this, 'render_shortcode' ) );

		add_action( 'wp_ajax_cap_create_razorpay_order', array( $this, 'ajax_create_razorpay_order' ) );
		add_action( 'wp_ajax_nopriv_cap_create_razorpay_order', array( $this, 'ajax_create_razorpay_order' ) );
		add_action( 'wp_ajax_cap_prepare_checkout', array( $this, 'ajax_prepare_checkout' ) );
		add_action( 'wp_ajax_nopriv_cap_prepare_checkout', array( $this, 'ajax_prepare_checkout' ) );
		add_action( 'wp_ajax_cap_verify_payment', array( $this, 'ajax_verify_payment' ) );
		add_action( 'wp_ajax_nopriv_cap_verify_payment', array( $this, 'ajax_verify_payment' ) );

		add_action( 'admin_post_cap_update_registration_status', array( $this, 'handle_admin_status_update' ) );
		add_action( 'admin_post_cap_export_registrations_csv', array( $this, 'handle_export_csv' ) );
		add_action( 'admin_post_cap_print_registration', array( $this, 'handle_print_registration' ) );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => 'CAP Registrations',
				'public'              => false,
				'show_ui'             => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'exclude_from_search' => true,
			)
		);
	}
}
