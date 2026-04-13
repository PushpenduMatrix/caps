<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAP_Trial_Registration {
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

	public function admin_menu() {
		add_menu_page(
			'CAP Registrations',
			'CAP Registrations',
			'manage_options',
			'cap-trial-registrations',
			array( $this, 'render_admin_list_page' ),
			'dashicons-id'
		);

		add_submenu_page(
			'cap-trial-registrations',
			'Settings',
			'Settings',
			'manage_options',
			'cap-trial-registration-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'cap_trial_registration_settings',
			self::OPT_KEY,
			array( $this, 'sanitize_settings' )
		);
	}

	public function sanitize_settings( $input ) {
		$existing = $this->get_settings();
		$venue_date_map = $this->parse_venue_date_map_input( $input['trial_venue_date_map'] ?? '' );
		$batch_timings  = $this->parse_batch_timings_input( $input['batch_timings'] ?? '' );
		$output = $existing;

		if ( array_key_exists( 'razorpay_key_id', $input ) ) {
			$output['razorpay_key_id'] = sanitize_text_field( $input['razorpay_key_id'] );
		}
		if ( array_key_exists( 'razorpay_key_secret', $input ) ) {
			$output['razorpay_key_secret'] = sanitize_text_field( $input['razorpay_key_secret'] );
		}
		if ( array_key_exists( 'base_amount', $input ) ) {
			$output['base_amount'] = absint( $input['base_amount'] );
		}
		if ( array_key_exists( 'tax_percent', $input ) ) {
			$output['tax_percent'] = floatval( $input['tax_percent'] );
		}
		if ( array_key_exists( 'terms_url', $input ) ) {
			$output['terms_url'] = esc_url_raw( $input['terms_url'] );
		}
		if ( array_key_exists( 'trial_venue_date_map', $input ) ) {
			$output['trial_venue_date_map'] = $venue_date_map;
		}
		if ( array_key_exists( 'batch_timings', $input ) ) {
			$output['batch_timings'] = $batch_timings;
		}
		if ( array_key_exists( 'email_subject_confirmation', $input ) ) {
			$output['email_subject_confirmation'] = sanitize_text_field( $input['email_subject_confirmation'] );
		}
		if ( array_key_exists( 'email_body_confirmation', $input ) ) {
			$output['email_body_confirmation'] = sanitize_textarea_field( $input['email_body_confirmation'] );
		}
		if ( array_key_exists( 'email_subject_approval', $input ) ) {
			$output['email_subject_approval'] = sanitize_text_field( $input['email_subject_approval'] );
		}
		if ( array_key_exists( 'email_body_approval', $input ) ) {
			$output['email_body_approval'] = sanitize_textarea_field( $input['email_body_approval'] );
		}
		if ( array_key_exists( 'admin_notification_email', $input ) ) {
			$output['admin_notification_email'] = sanitize_email( $input['admin_notification_email'] );
		}
		if ( array_key_exists( 'thank_you_content', $input ) ) {
			$output['thank_you_content'] = wp_kses_post( $input['thank_you_content'] );
		}
		if ( array_key_exists( 'thank_you_page_url', $input ) ) {
			$output['thank_you_page_url'] = esc_url_raw( $input['thank_you_page_url'] );
		}
		if ( array_key_exists( 'thank_you_page_id', $input ) ) {
			$output['thank_you_page_id'] = absint( $input['thank_you_page_id'] );
		}
		if ( array_key_exists( 'registrations_per_page', $input ) ) {
			$output['registrations_per_page'] = max( 1, absint( $input['registrations_per_page'] ) );
		}
		if ( array_key_exists( 'fast2sms_sms_api_url', $input ) ) {
			$output['fast2sms_sms_api_url'] = esc_url_raw( $input['fast2sms_sms_api_url'] );
		}
		if ( array_key_exists( 'fast2sms_whatsapp_api_url', $input ) ) {
			$output['fast2sms_whatsapp_api_url'] = esc_url_raw( $input['fast2sms_whatsapp_api_url'] );
		}
		if ( array_key_exists( 'fast2sms_api_key', $input ) ) {
			$output['fast2sms_api_key'] = sanitize_text_field( $input['fast2sms_api_key'] );
		}
		if ( array_key_exists( 'fast2sms_sender_id', $input ) ) {
			$output['fast2sms_sender_id'] = sanitize_text_field( $input['fast2sms_sender_id'] );
		}
		$output['fast2sms_whatsapp_enabled'] = ! empty( $input['fast2sms_whatsapp_enabled'] ) ? '1' : ( $output['fast2sms_whatsapp_enabled'] ?? '0' );

		return $output;
	}

	private function get_settings() {
		$defaults = array(
			'razorpay_key_id'     => '',
			'razorpay_key_secret' => '',
			'base_amount'         => 1299,
			'tax_percent'         => 18,
			'terms_url'           => '',
			'trial_venue_date_map'=> $this->default_venue_date_map(),
			'batch_timings'       => $this->default_batch_timings(),
			'email_subject_confirmation' => 'CAP National Scholarship 2026 - Registration Confirmation',
			'email_body_confirmation'    => "Thank you for registering for CAP National Scholarship 2026.\n\nYour final trial date, batch timing, reporting details and venue instructions will be shared after final slot allocation via Email / SMS.\nPlease check your email and registered mobile number for further updates.",
			'email_subject_approval'     => 'CAP National Scholarship 2026 - Application Status',
			'email_body_approval'        => 'Your application status has been updated: {{status}}.',
			'admin_notification_email'   => get_option( 'admin_email' ),
			'thank_you_content'          => '<h2>Thank you for your payment.</h2><p>Your registration has been confirmed successfully.</p>',
			'thank_you_page_url'         => '',
			'thank_you_page_id'          => 0,
			'registrations_per_page'     => 20,
			'fast2sms_sms_api_url'       => 'https://www.fast2sms.com/dev/bulkV2',
			'fast2sms_whatsapp_api_url'  => '',
			'fast2sms_api_key'           => '',
			'fast2sms_sender_id'         => '',
			'fast2sms_whatsapp_enabled'  => '0',
		);

		$settings = wp_parse_args( get_option( self::OPT_KEY, array() ), $defaults );
		if ( empty( $settings['fast2sms_sms_api_url'] ) && ! empty( $settings['free2sms_api_url'] ) ) {
			$settings['fast2sms_sms_api_url'] = $settings['free2sms_api_url'];
		}
		if ( empty( $settings['fast2sms_api_key'] ) && ! empty( $settings['free2sms_api_key'] ) ) {
			$settings['fast2sms_api_key'] = $settings['free2sms_api_key'];
		}
		if ( empty( $settings['fast2sms_sender_id'] ) && ! empty( $settings['free2sms_sender_id'] ) ) {
			$settings['fast2sms_sender_id'] = $settings['free2sms_sender_id'];
		}
		if ( empty( $settings['fast2sms_whatsapp_enabled'] ) && isset( $settings['free2sms_whatsapp_enabled'] ) ) {
			$settings['fast2sms_whatsapp_enabled'] = $settings['free2sms_whatsapp_enabled'];
		}
		return $settings;
	}

	private function default_venue_date_map() {
		return array(
			'CAP Panvel'      => array( '16 May 2026', '17 May 2026' ),
			'CAP Jaipur'      => array( '16 May 2026', '17 May 2026' ),
			'CAP Gopalganj'   => array( '23 May 2026', '24 May 2026' ),
			'CAP Lucknow'     => array( '23 May 2026', '24 May 2026' ),
			'CAP Bhubaneswar' => array( '23 May 2026', '24 May 2026' ),
		);
	}

	private function default_batch_timings() {
		return array(
			'Morning Batch (9:00 AM - 1:00 PM)',
			'Afternoon Batch (2:00 PM - 6:00 PM)',
		);
	}

	private function parse_venue_date_map_input( $raw_input ) {
		$raw_input = is_string( $raw_input ) ? wp_unslash( $raw_input ) : '';
		$raw_input = trim( $raw_input );

		if ( '' === $raw_input ) {
			return $this->default_venue_date_map();
		}

		$json_map = json_decode( $raw_input, true );
		if ( is_array( $json_map ) ) {
			$clean_map = array();
			foreach ( $json_map as $venue => $dates ) {
				$venue = sanitize_text_field( (string) $venue );
				if ( '' === $venue || ! is_array( $dates ) ) {
					continue;
				}
				$clean_dates = array_values(
					array_filter(
						array_map(
							static function ( $date ) {
								return sanitize_text_field( (string) $date );
							},
							$dates
						)
					)
				);
				if ( ! empty( $clean_dates ) ) {
					$clean_map[ $venue ] = $clean_dates;
				}
			}
			if ( ! empty( $clean_map ) ) {
				return $clean_map;
			}
		}

		$clean_map = array();
		$lines     = preg_split( '/\r\n|\r|\n/', $raw_input );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '|' ) ) {
				continue;
			}
			list( $venue_part, $dates_part ) = array_pad( explode( '|', $line, 2 ), 2, '' );
			$venue = sanitize_text_field( trim( $venue_part ) );
			if ( '' === $venue ) {
				continue;
			}
			$date_items = array_values(
				array_filter(
					array_map(
						'sanitize_text_field',
						array_map( 'trim', explode( ',', $dates_part ) )
					)
				)
			);
			if ( ! empty( $date_items ) ) {
				$clean_map[ $venue ] = $date_items;
			}
		}

		return ! empty( $clean_map ) ? $clean_map : $this->default_venue_date_map();
	}

	private function parse_batch_timings_input( $raw_input ) {
		$raw_input = is_string( $raw_input ) ? wp_unslash( $raw_input ) : '';
		$raw_input = trim( $raw_input );
		if ( '' === $raw_input ) {
			return $this->default_batch_timings();
		}

		$json_items = json_decode( $raw_input, true );
		if ( is_array( $json_items ) ) {
			$timings = array_values(
				array_filter(
					array_map(
						static function ( $item ) {
							return sanitize_text_field( (string) $item );
						},
						$json_items
					)
				)
			);
			return ! empty( $timings ) ? $timings : $this->default_batch_timings();
		}

		$lines = preg_split( '/\r\n|\r|\n/', $raw_input );
		$lines = array_values(
			array_filter(
				array_map(
					static function ( $line ) {
						return sanitize_text_field( trim( $line ) );
					},
					$lines
				)
			)
		);

		return ! empty( $lines ) ? $lines : $this->default_batch_timings();
	}

	private function venue_map_to_lines( $map ) {
		$lines = array();
		foreach ( (array) $map as $venue => $dates ) {
			$lines[] = $venue . '|' . implode( ',', (array) $dates );
		}
		return implode( "\n", $lines );
	}

	public function enqueue_assets() {
		wp_register_style(
			'cap-trial-registration',
			CAP_TRIAL_REG_URL . 'assets/css/cap-trial-registration.css',
			array(),
			CAP_TRIAL_REG_VERSION
		);
		wp_register_script(
			'cap-trial-registration',
			CAP_TRIAL_REG_URL . 'assets/js/cap-trial-registration.js',
			array( 'jquery' ),
			CAP_TRIAL_REG_VERSION,
			true
		);
	}

	public function render_shortcode() {
		wp_enqueue_style( 'cap-trial-registration' );
		wp_enqueue_script( 'cap-trial-registration' );

		$settings = $this->get_settings();
		$is_thank_you = isset( $_GET['cap_thank_you'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cap_thank_you'] ) );

		wp_localize_script(
			'cap-trial-registration',
			'capTrialReg',
			array(
				'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
				'prepareCheckoutNonce'=> wp_create_nonce( 'cap_prepare_checkout' ),
				'createOrderNonce'    => wp_create_nonce( 'cap_create_order' ),
				'verifyPaymentNonce'  => wp_create_nonce( 'cap_verify_payment' ),
				'razorpayKey'         => $settings['razorpay_key_id'],
				'checkoutSession'     => '',
				'venueDateMap'        => $settings['trial_venue_date_map'],
				'successMessage'      => $settings['email_body_confirmation'],
				'thankYouUrl'         => $this->get_thank_you_redirect_url( $settings ),
			)
		);

		ob_start();
		?>
		<div class="cap-reg-wrap">
			<?php if ( $is_thank_you ) : ?>
				<div class="cap-reg-thank-you">
					<?php echo wp_kses_post( $settings['thank_you_content'] ); ?>
				</div>
			<?php else : ?>
				<div id="cap-checkout-form-panel">
					<?php $this->render_form_panel( $settings ); ?>
				</div>
				<div id="cap-checkout-payment-panel" style="display:none;">
					<?php $this->render_payment_panel(); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

private function render_form_panel( $settings ) {
	$terms_url = ! empty( $settings['terms_url'] ) ? $settings['terms_url'] : home_url( '/terms-and-conditions/' );
	?>
	<form method="post" class="cap-reg-form" novalidate>
		<?php wp_nonce_field( 'cap_trial_registration_submit', 'cap_trial_nonce' ); ?>
		<div class="cap-reg-errors" style="display:none;"></div>
		<h2 class="cap-form-title">Application Form</h2>
		<div class="cap-progress-bar"><div class="cap-progress-bar-fill"></div></div>
		<div class="cap-stepper">
			<div class="cap-step-indicator active" data-step="1"><span>1</span> Personal Details</div>
			<div class="cap-step-indicator" data-step="2"><span>2</span> Cricketer Details</div>
			<div class="cap-step-indicator" data-step="3"><span>3</span> Trial Preference</div>
		</div>

		<div class="cap-form-step" data-step="1">
			<h3>Personal Details</h3>
			<div class="cap-grid">
				<div class="cap-field"><label for="full_name">Full Name of Player</label><input type="text" id="full_name" name="full_name" required></div>
				<div class="cap-field"><label for="date_of_birth">Date of Birth</label><input type="date" id="date_of_birth" name="date_of_birth" required></div>
				<div class="cap-field"><label for="place_of_birth">Place of Birth</label><input type="text" id="place_of_birth" name="place_of_birth" required></div>
				<div class="cap-field"><label for="residential_address">Residential Address</label><textarea id="residential_address" name="residential_address" required></textarea></div>
				<div class="cap-field"><label for="guardian_name">Guardian's Name</label><input type="text" id="guardian_name" name="guardian_name" required></div>
				<div class="cap-field"><label for="relationship_with_player">Relationship with Player</label><input type="text" id="relationship_with_player" name="relationship_with_player" required></div>
				<div class="cap-field"><label for="mobile_number">Mobile Number</label><input type="tel" id="mobile_number" name="mobile_number" pattern="[0-9]{10,15}" required></div>
				<div class="cap-field"><label for="email_id">Email ID</label><input type="email" id="email_id" name="email_id" required></div>
			</div>
			<div class="cap-step-actions"><button type="button" class="cap-btn cap-step-next" data-current-step="1">Next Step</button></div>
		</div>

		<div class="cap-form-step" data-step="2" style="display:none;">
			<h3>Cricketer Details</h3>
			<div class="cap-grid">
				<div class="cap-field"><label for="playing_role">Playing Role</label><select id="playing_role" name="playing_role" required><option value="">Select Playing Role</option><option value="Batter">Batter</option><option value="Bowler">Bowler</option><option value="Wicketkeeper">Wicketkeeper</option></select></div>
				<div class="cap-field"><label for="batting_style">Batting Style</label><select id="batting_style" name="batting_style" required><option value="">Select Batting Style</option><option value="Right-hand bat">Right-hand bat</option><option value="Left-hand bat">Left-hand bat</option></select></div>
				<div class="cap-field"><label for="bowling_style">Bowling Style</label><select id="bowling_style" name="bowling_style" required><option value="">Select Bowling Style</option><option value="Right-arm fast">Right-arm fast</option><option value="Right-arm off spin">Right-arm off spin</option><option value="Right-arm leg spin">Right-arm leg spin</option><option value="Left-arm fast">Left-arm fast</option><option value="Left-arm orthodox spin">Left-arm orthodox spin</option><option value="Left-arm chinaman">Left-arm chinaman</option><option value="Others">Others</option></select></div>
			</div>
			<div class="cap-step-actions"><button type="button" class="cap-btn cap-btn-secondary cap-step-prev" data-current-step="2">Previous</button><button type="button" class="cap-btn cap-step-next" data-current-step="2">Next Step</button></div>
		</div>

		<div class="cap-form-step" data-step="3" style="display:none;">
			<h3>Trial Preference</h3>
			<div class="cap-grid">
				<div class="cap-field"><label for="cap_preferred_trial_venue">Preferred Trial Venue</label><select id="cap_preferred_trial_venue" name="preferred_trial_venue" required><option value="">Select Venue</option><?php foreach ( $settings['trial_venue_date_map'] as $venue_name => $dates ) : ?><option value="<?php echo esc_attr( $venue_name ); ?>"><?php echo esc_html( $venue_name ); ?></option><?php endforeach; ?></select></div>
				<div class="cap-field"><label for="cap_preferred_trial_date">Preferred Trial Date</label><select id="cap_preferred_trial_date" name="preferred_trial_date" required><option value="">Select Date</option></select></div>
				<div class="cap-field"><label for="preferred_batch_timing">Preferred Batch Timing</label><select id="preferred_batch_timing" name="preferred_batch_timing" required><option value="">Select Batch</option><?php foreach ( $settings['batch_timings'] as $batch_timing ) : ?><option value="<?php echo esc_attr( $batch_timing ); ?>"><?php echo esc_html( $batch_timing ); ?></option><?php endforeach; ?></select></div>
			</div>
			<h3>Mandatory Declarations</h3>
			<div class="cap-checkbox-group">
				<label><input type="checkbox" name="declaration_correct_details" required> I confirm that all details submitted are correct.</label>
				<label><input type="checkbox" name="declaration_registration_only" required> I understand that payment confirms registration only and does not guarantee scholarship selection.</label>
				<label><input type="checkbox" name="declaration_terms" required> I agree to the programme terms and conditions.</label>
				<label><input type="checkbox" name="declaration_media_consent" required> I consent to media usage.</label>
				<label><input type="checkbox" name="declaration_age_proof" required> I will provide age-proof at venue.</label>
			</div>
			<div class="cap-terms-box">
				<h4>Terms & Conditions</h4>
				<ul><li>The registration fee is non-refundable once paid.</li><li>Disputes subject to Delhi jurisdiction.</li><li>Participation is at own risk.</li></ul>
				<label><input type="checkbox" name="accept_terms_conditions" required> I accept the <a href="<?php echo esc_url( $terms_url ); ?>" target="_blank">Terms & Conditions</a></label>
			</div>
			<div class="cap-step-actions"><button type="button" class="cap-btn cap-btn-secondary cap-step-prev" data-current-step="3">Previous</button><button type="submit" class="cap-btn">Proceed to Payment</button></div>
		</div>
	</form>
	<?php
}

	private function process_registration_form() {
		$errors = $this->validate_form_input();
		if ( ! empty( $errors ) ) {
			return array(
				'errors'  => $errors,
				'reg_id'  => 0,
				'message' => '',
			);
		}
		return array(
			'errors'  => array(),
			'reg_id'  => 0,
			'message' => 'Registration details validated. Complete payment to confirm your slot.',
		);
	}

	private function validate_form_input() {
		$errors = array();
		$required_fields = array(
			'full_name',
			'date_of_birth',
			'place_of_birth',
			'residential_address',
			'guardian_name',
			'relationship_with_player',
			'mobile_number',
			'email_id',
			'playing_role',
			'batting_style',
			'bowling_style',
			'preferred_trial_venue',
			'preferred_trial_date',
			'preferred_batch_timing',
			'declaration_correct_details',
			'declaration_registration_only',
			'declaration_terms',
			'declaration_media_consent',
			'declaration_age_proof',
			'accept_terms_conditions',
		);

		foreach ( $required_fields as $required_field ) {
			if ( empty( $_POST[ $required_field ] ) ) {
				$errors[] = 'Please complete all mandatory fields and declarations.';
				break;
			}
		}

		$dob = sanitize_text_field( wp_unslash( $_POST['date_of_birth'] ?? '' ) );
		if ( ! $this->is_valid_age_range( $dob, 10, 21 ) ) {
			$errors[] = 'Date of Birth is invalid. Age must be between 10 and 21 years.';
		}

		$venue = sanitize_text_field( wp_unslash( $_POST['preferred_trial_venue'] ?? '' ) );
		$date  = sanitize_text_field( wp_unslash( $_POST['preferred_trial_date'] ?? '' ) );
		if ( ! $this->is_valid_venue_date( $venue, $date ) ) {
			$errors[] = 'Selected trial date is not valid for the selected venue.';
		}

		return $errors;
	}

	private function is_valid_age_range( $date_string, $min, $max ) {
		if ( empty( $date_string ) ) {
			return false;
		}
		try {
			$dob = new DateTimeImmutable( $date_string );
			$now = new DateTimeImmutable( current_time( 'Y-m-d' ) );
		} catch ( Exception $e ) {
			return false;
		}

		if ( $dob > $now ) {
			return false;
		}

		$age = (int) $dob->diff( $now )->y;
		return $age >= $min && $age <= $max;
	}

	public function ajax_prepare_checkout() {
		check_ajax_referer( 'cap_prepare_checkout', 'nonce' );

		$errors = $this->validate_form_input();
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		$data = $this->collect_clean_form_data();
		$session_key = $this->create_checkout_session( $data );
		if ( empty( $session_key ) ) {
			wp_send_json_error( array( 'errors' => array( 'Unable to prepare checkout. Please try again.' ) ) );
		}

		wp_send_json_success(
			array(
				'checkout_session' => $session_key,
			)
		);
	}

	private function create_checkout_session( $data ) {
		$session_key = wp_generate_uuid4();
		$payload = array(
			'data' => $data,
			'created_at' => time(),
		);
		return set_transient( 'cap_checkout_' . $session_key, $payload, HOUR_IN_SECONDS ) ? $session_key : '';
	}

	private function get_checkout_session( $session_key ) {
		return get_transient( 'cap_checkout_' . $session_key );
	}

	private function save_checkout_session( $session_key, $payload ) {
		return set_transient( 'cap_checkout_' . $session_key, $payload, HOUR_IN_SECONDS );
	}

	private function clear_checkout_session( $session_key ) {
		delete_transient( 'cap_checkout_' . $session_key );
	}

	private function create_registration_from_data( $data, $payment_data ) {
		$full_name = $data['full_name'];
		$email     = $data['email_id'];
		$mobile    = $data['mobile_number'];
		$reference = 'CAP' . time() . wp_rand( 100, 999 );

		$reg_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $full_name . ' - ' . $reference,
			),
			true
		);
		if ( is_wp_error( $reg_id ) || ! $reg_id ) {
			return 0;
		}

		foreach ( $data as $key => $value ) {
			update_post_meta( $reg_id, '_' . $key, $value );
		}

		update_post_meta( $reg_id, '_reference_id', $reference );
		update_post_meta( $reg_id, '_registration_status', 'Registered - Awaiting Admin Review' );
		update_post_meta( $reg_id, '_approval_status', 'Pending' );
		update_post_meta( $reg_id, '_payment_status', 'paid' );
		update_post_meta( $reg_id, '_email_id', $email );
		update_post_meta( $reg_id, '_mobile_number', $mobile );
		update_post_meta( $reg_id, '_razorpay_payment_id', $payment_data['payment_id'] );
		update_post_meta( $reg_id, '_razorpay_order_id', $payment_data['order_id'] );
		update_post_meta( $reg_id, '_razorpay_signature', $payment_data['signature'] );
		update_post_meta( $reg_id, '_payment_timestamp', current_time( 'mysql' ) );

		return $reg_id;
	}

	private function collect_clean_form_data() {
		return array(
			'full_name'                    => sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) ),
			'date_of_birth'                => sanitize_text_field( wp_unslash( $_POST['date_of_birth'] ?? '' ) ),
			'place_of_birth'               => sanitize_text_field( wp_unslash( $_POST['place_of_birth'] ?? '' ) ),
			'residential_address'          => sanitize_textarea_field( wp_unslash( $_POST['residential_address'] ?? '' ) ),
			'guardian_name'                => sanitize_text_field( wp_unslash( $_POST['guardian_name'] ?? '' ) ),
			'relationship_with_player'     => sanitize_text_field( wp_unslash( $_POST['relationship_with_player'] ?? '' ) ),
			'mobile_number'                => sanitize_text_field( wp_unslash( $_POST['mobile_number'] ?? '' ) ),
			'email_id'                     => sanitize_email( wp_unslash( $_POST['email_id'] ?? '' ) ),
			'playing_role'                 => sanitize_text_field( wp_unslash( $_POST['playing_role'] ?? '' ) ),
			'batting_style'                => sanitize_text_field( wp_unslash( $_POST['batting_style'] ?? '' ) ),
			'bowling_style'                => sanitize_text_field( wp_unslash( $_POST['bowling_style'] ?? '' ) ),
			'preferred_trial_venue'        => sanitize_text_field( wp_unslash( $_POST['preferred_trial_venue'] ?? '' ) ),
			'preferred_trial_date'         => sanitize_text_field( wp_unslash( $_POST['preferred_trial_date'] ?? '' ) ),
			'preferred_batch_timing'       => sanitize_text_field( wp_unslash( $_POST['preferred_batch_timing'] ?? '' ) ),
			'declaration_correct_details'  => ! empty( $_POST['declaration_correct_details'] ) ? 'Yes' : 'No',
			'declaration_registration_only'=> ! empty( $_POST['declaration_registration_only'] ) ? 'Yes' : 'No',
			'declaration_terms'            => ! empty( $_POST['declaration_terms'] ) ? 'Yes' : 'No',
			'declaration_media_consent'    => ! empty( $_POST['declaration_media_consent'] ) ? 'Yes' : 'No',
			'declaration_age_proof'        => ! empty( $_POST['declaration_age_proof'] ) ? 'Yes' : 'No',
			'accept_terms_conditions'      => ! empty( $_POST['accept_terms_conditions'] ) ? 'Yes' : 'No',
		);
	}

	private function render_payment_panel() {
		$settings      = $this->get_settings();
		$base_amount   = (int) $settings['base_amount'];
		$tax_percent   = (float) $settings['tax_percent'];
		$tax_amount    = round( ( $base_amount * $tax_percent ) / 100, 2 );
		$total_amount  = round( $base_amount + $tax_amount, 2 );
		$display_total = number_format( $total_amount, 2 );
		?>
		<div class="cap-payment-panel">
			<h3>Payment</h3>
			<p>Registration Fee: INR <?php echo esc_html( number_format( $base_amount, 2 ) ); ?></p>
			<p>Applicable Tax (<?php echo esc_html( $tax_percent ); ?>%): INR <?php echo esc_html( number_format( $tax_amount, 2 ) ); ?></p>
			<p><strong>Total Payable: INR <?php echo esc_html( $display_total ); ?></strong></p>
			<p class="cap-payment-help">Click below to pay securely with Razorpay.</p>
			<button type="button" id="cap-pay-now" class="cap-btn">Pay INR <?php echo esc_html( $display_total ); ?></button>
			<div id="cap-payment-result"></div>
		</div>
		<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
		<?php
	}

	public function ajax_create_razorpay_order() {
		check_ajax_referer( 'cap_create_order', 'nonce' );

		$session_key = sanitize_text_field( wp_unslash( $_POST['checkout_session'] ?? '' ) );
		$session     = $this->get_checkout_session( $session_key );
		if ( empty( $session ) || empty( $session['data'] ) ) {
			wp_send_json_error( array( 'message' => 'Session expired. Please submit the form again.' ) );
		}

		$settings = $this->get_settings();
		if ( empty( $settings['razorpay_key_id'] ) || empty( $settings['razorpay_key_secret'] ) ) {
			wp_send_json_error( array( 'message' => 'Razorpay is not configured. Please contact support.' ) );
		}

		$base_amount  = (int) $settings['base_amount'];
		$tax_percent  = (float) $settings['tax_percent'];
		$total_amount = round( $base_amount + ( $base_amount * $tax_percent / 100 ), 2 );
		$amount_paise = (int) round( $total_amount * 100 );

		$payload = wp_json_encode(
			array(
				'amount'          => $amount_paise,
				'currency'        => 'INR',
				'receipt'         => 'cap_reg_' . substr( md5( $session_key ), 0, 12 ),
				'payment_capture' => 1,
			)
		);

		$response = wp_remote_post(
			'https://api.razorpay.com/v1/orders',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $settings['razorpay_key_id'] . ':' . $settings['razorpay_key_secret'] ),
					'Content-Type'  => 'application/json',
				),
				'body'    => $payload,
				'timeout' => 25,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Unable to connect to payment gateway.' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['id'] ) ) {
			wp_send_json_error( array( 'message' => 'Could not create payment order.' ) );
		}

		$session['razorpay_order_id'] = sanitize_text_field( $body['id'] );
		$this->save_checkout_session( $session_key, $session );

		wp_send_json_success(
			array(
				'order_id'      => sanitize_text_field( $body['id'] ),
				'amount'        => $amount_paise,
				'currency'      => 'INR',
				'key'           => $settings['razorpay_key_id'],
				'full_name'     => $session['data']['full_name'] ?? '',
				'email'         => $session['data']['email_id'] ?? '',
				'contact'       => $session['data']['mobile_number'] ?? '',
				'reference_id'  => '',
			)
		);
	}

	public function ajax_verify_payment() {
		check_ajax_referer( 'cap_verify_payment', 'nonce' );

		$session_key        = sanitize_text_field( wp_unslash( $_POST['checkout_session'] ?? '' ) );
		$session            = $this->get_checkout_session( $session_key );
		$razorpay_payment   = sanitize_text_field( wp_unslash( $_POST['razorpay_payment_id'] ?? '' ) );
		$razorpay_order     = sanitize_text_field( wp_unslash( $_POST['razorpay_order_id'] ?? '' ) );
		$razorpay_signature = sanitize_text_field( wp_unslash( $_POST['razorpay_signature'] ?? '' ) );

		if ( empty( $session ) || empty( $session['data'] ) ) {
			wp_send_json_error( array( 'message' => 'Session expired. Please submit the form again.' ) );
		}
		if ( empty( $session['razorpay_order_id'] ) || $session['razorpay_order_id'] !== $razorpay_order ) {
			wp_send_json_error( array( 'message' => 'Order mismatch. Please retry payment.' ) );
		}

		$settings = $this->get_settings();
		$secret   = $settings['razorpay_key_secret'];
		$hash     = hash_hmac( 'sha256', $razorpay_order . '|' . $razorpay_payment, $secret );

		if ( ! hash_equals( $hash, $razorpay_signature ) ) {
			wp_send_json_error( array( 'message' => 'Payment verification failed.' ) );
		}

		$reg_id = $this->create_registration_from_data(
			$session['data'],
			array(
				'payment_id' => $razorpay_payment,
				'order_id'   => $razorpay_order,
				'signature'  => $razorpay_signature,
			)
		);
		if ( ! $reg_id ) {
			wp_send_json_error( array( 'message' => 'Payment captured but registration save failed. Please contact support.' ) );
		}

		$this->send_post_payment_notifications( $reg_id );
		$this->clear_checkout_session( $session_key );

		$redirect_url = esc_url_raw( wp_unslash( $_POST['thank_you_url'] ?? '' ) );
		if ( empty( $redirect_url ) ) {
			$redirect_url = $this->get_thank_you_redirect_url( $settings );
		}

		wp_send_json_success(
			array(
				'message' => 'Payment successful. Confirmation has been sent.',
				'redirect_url' => esc_url_raw( $redirect_url ),
			)
		);
	}

	private function send_post_payment_notifications( $reg_id ) {
		$email   = get_post_meta( $reg_id, '_email_id', true );
		$mobile  = get_post_meta( $reg_id, '_mobile_number', true );
		$settings = $this->get_settings();
		$subject  = $settings['email_subject_confirmation'];
		$body     = $settings['email_body_confirmation'];
		$payment_status = get_post_meta( $reg_id, '_payment_status', true );

		if ( 'paid' !== $payment_status ) {
			return;
		}

		if ( is_email( $email ) ) {
			wp_mail( $email, $subject, $body );
		}

		$this->send_admin_registration_email( $reg_id );

		$this->send_fast2sms( $mobile, $body, 'sms' );
		$this->send_fast2sms( $mobile, $body, 'whatsapp' );
	}

	private function send_admin_registration_email( $reg_id ) {
		$settings    = $this->get_settings();
		$admin_email = $settings['admin_notification_email'];
		if ( ! is_email( $admin_email ) ) {
			return;
		}

		$settings      = $this->get_settings();
		$base_amount   = (int) $settings['base_amount'];
		$tax_percent   = (float) $settings['tax_percent'];
		$tax_amount    = round( ( $base_amount * $tax_percent ) / 100, 2 );
		$total_amount  = round( $base_amount + $tax_amount, 2 );

		$subject = 'New CAP Registration Received';
		$fields  = array(
			'Reference ID'             => get_post_meta( $reg_id, '_reference_id', true ),
			'Full Name of Player'      => get_post_meta( $reg_id, '_full_name', true ),
			'Date of Birth'            => get_post_meta( $reg_id, '_date_of_birth', true ),
			'Place of Birth'           => get_post_meta( $reg_id, '_place_of_birth', true ),
			'Residential Address'      => get_post_meta( $reg_id, '_residential_address', true ),
			"Guardian's Name"          => get_post_meta( $reg_id, '_guardian_name', true ),
			'Relationship with Player' => get_post_meta( $reg_id, '_relationship_with_player', true ),
			'Mobile Number'            => get_post_meta( $reg_id, '_mobile_number', true ),
			'Email ID'                 => get_post_meta( $reg_id, '_email_id', true ),
			'Playing Role'             => get_post_meta( $reg_id, '_playing_role', true ),
			'Batting Style'            => get_post_meta( $reg_id, '_batting_style', true ),
			'Bowling Style'            => get_post_meta( $reg_id, '_bowling_style', true ),
			'Preferred Trial Venue'    => get_post_meta( $reg_id, '_preferred_trial_venue', true ),
			'Preferred Trial Date'     => get_post_meta( $reg_id, '_preferred_trial_date', true ),
			'Preferred Batch Timing'   => get_post_meta( $reg_id, '_preferred_batch_timing', true ),
			'Payment Status'           => get_post_meta( $reg_id, '_payment_status', true ),
			'Razorpay Payment ID'      => get_post_meta( $reg_id, '_razorpay_payment_id', true ),
			'Razorpay Order ID'        => get_post_meta( $reg_id, '_razorpay_order_id', true ),
			'Razorpay Signature'       => get_post_meta( $reg_id, '_razorpay_signature', true ),
			'Payment Timestamp'        => get_post_meta( $reg_id, '_payment_timestamp', true ),
			'Base Amount (INR)'        => number_format( $base_amount, 2 ),
			'Tax Percent'              => $tax_percent . '%',
			'Tax Amount (INR)'         => number_format( $tax_amount, 2 ),
			'Total Amount Paid (INR)'  => number_format( $total_amount, 2 ),
			'Approval Status'          => get_post_meta( $reg_id, '_approval_status', true ),
		);

		$body_lines = array( 'A new registration has been submitted with payment.', '' );
		foreach ( $fields as $label => $value ) {
			$body_lines[] = $label . ': ' . $value;
		}

		wp_mail( $admin_email, $subject, implode( "\n", $body_lines ) );
	}

	private function send_approval_notifications( $reg_id, $status ) {
		$email   = get_post_meta( $reg_id, '_email_id', true );
		$mobile  = get_post_meta( $reg_id, '_mobile_number', true );
		$settings = $this->get_settings();
		$subject  = $settings['email_subject_approval'];
		$body     = str_replace( '{{status}}', $status, $settings['email_body_approval'] );

		if ( is_email( $email ) ) {
			wp_mail( $email, $subject, $body );
		}

		$this->send_fast2sms( $mobile, $body, 'sms' );
		$this->send_fast2sms( $mobile, $body, 'whatsapp' );
	}

	private function send_fast2sms( $mobile, $message, $channel = 'sms' ) {
		$settings = $this->get_settings();
		$api_url  = 'whatsapp' === $channel ? $settings['fast2sms_whatsapp_api_url'] : $settings['fast2sms_sms_api_url'];
		$api_key  = $settings['fast2sms_api_key'];
		$sender   = $settings['fast2sms_sender_id'];

		if ( 'whatsapp' === $channel && '1' !== $settings['fast2sms_whatsapp_enabled'] ) {
			return;
		}

		if ( empty( $api_url ) || empty( $api_key ) || empty( $mobile ) ) {
			return;
		}

		wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'authorization' => $api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 20,
				'body'    => wp_json_encode(
					array(
						'to'      => $mobile,
						'message' => $message,
						'sender'  => $sender,
						'channel' => $channel,
					)
				),
			)
		);
	}

	public function render_admin_list_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$view_id = absint( $_GET['view_id'] ?? 0 );
		if ( $view_id && self::POST_TYPE === get_post_type( $view_id ) ) {
			$this->render_admin_single_view( $view_id );
			return;
		}

		$payment_filter  = sanitize_text_field( wp_unslash( $_GET['payment_status'] ?? '' ) );
		$approval_filter = sanitize_text_field( wp_unslash( $_GET['approval_status'] ?? '' ) );
		$paged           = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$settings        = $this->get_settings();
		$per_page        = max( 1, absint( $settings['registrations_per_page'] ?? 20 ) );
		$meta_query      = array( 'relation' => 'AND' );
		if ( in_array( $payment_filter, array( 'paid', 'unpaid' ), true ) ) {
			$meta_query[] = array(
				'key'   => '_payment_status',
				'value' => $payment_filter,
			);
		}
		if ( in_array( $approval_filter, array( 'Pending', 'Approved', 'Disapproved' ), true ) ) {
			$meta_query[] = array(
				'key'   => '_approval_status',
				'value' => $approval_filter,
			);
		}

		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( count( $meta_query ) > 1 ) {
			$query_args['meta_query'] = $meta_query;
		}
		$query = new WP_Query( $query_args );
		$items = $query->posts;
		$total_items = (int) $query->found_posts;
		$start_item  = $total_items > 0 ? ( ( $paged - 1 ) * $per_page ) + 1 : 0;
		$end_item    = min( $paged * $per_page, $total_items );
		?>
		<div class="wrap">
			<h1>CAP Trial Registrations</h1>
			<form method="get" style="margin: 12px 0;">
				<input type="hidden" name="page" value="cap-trial-registrations">
				<select name="payment_status">
					<option value="">All Payment</option>
					<option value="paid" <?php selected( $payment_filter, 'paid' ); ?>>Paid</option>
					<option value="unpaid" <?php selected( $payment_filter, 'unpaid' ); ?>>Pending Payment</option>
				</select>
				<select name="approval_status">
					<option value="">All Approval</option>
					<option value="Pending" <?php selected( $approval_filter, 'Pending' ); ?>>Pending</option>
					<option value="Approved" <?php selected( $approval_filter, 'Approved' ); ?>>Approved</option>
					<option value="Disapproved" <?php selected( $approval_filter, 'Disapproved' ); ?>>Disapproved</option>
				</select>
				<button class="button">Filter</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cap-trial-registrations' ) ); ?>">Reset</a>
				<?php
				$export_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=cap_export_registrations_csv&payment_status=' . rawurlencode( $payment_filter ) . '&approval_status=' . rawurlencode( $approval_filter ) ),
					'cap_export_csv'
				);
				?>
				<a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>">Export CSV</a>
			</form>
			<p style="margin: 8px 0 12px; color: #50575e;">
				<?php
				printf(
					'Showing %1$d-%2$d of %3$d registrations',
					(int) $start_item,
					(int) $end_item,
					(int) $total_items
				);
				?>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Reference</th>
						<th>Name</th>
						<th>Email</th>
						<th>Mobile</th>
						<th>Venue</th>
						<th>Date</th>
						<th>Date Applied</th>
						<th>Payment</th>
						<th>Approval</th>
						<th>View</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr><td colspan="11">No registrations found.</td></tr>
					<?php else : ?>
						<?php foreach ( $items as $item ) : ?>
							<tr>
								<td><?php echo esc_html( get_post_meta( $item->ID, '_reference_id', true ) ); ?></td>
								<td><?php echo esc_html( get_post_meta( $item->ID, '_full_name', true ) ); ?></td>
								<td><?php echo esc_html( get_post_meta( $item->ID, '_email_id', true ) ); ?></td>
								<td><?php echo esc_html( get_post_meta( $item->ID, '_mobile_number', true ) ); ?></td>
								<td><?php echo esc_html( get_post_meta( $item->ID, '_preferred_trial_venue', true ) ); ?></td>
								<td><?php echo esc_html( get_post_meta( $item->ID, '_preferred_trial_date', true ) ); ?></td>
								<td><?php echo esc_html( get_the_date( 'd M Y h:i A', $item ) ); ?></td>
								<td><?php echo esc_html( get_post_meta( $item->ID, '_payment_status', true ) ); ?></td>
								<td><?php echo esc_html( get_post_meta( $item->ID, '_approval_status', true ) ); ?></td>
								<td>
									<?php
									$view_url = admin_url( 'admin.php?page=cap-trial-registrations&view_id=' . $item->ID );
									?>
									<a class="button" href="<?php echo esc_url( $view_url ); ?>">View</a>
								</td>
								<td>
									<?php echo wp_kses_post( $this->admin_action_buttons( $item->ID ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php
			$pagination_base = add_query_arg(
				array(
					'page'           => 'cap-trial-registrations',
					'payment_status' => $payment_filter,
					'approval_status'=> $approval_filter,
					'paged'          => '%#%',
				),
				admin_url( 'admin.php' )
			);
			$pagination_links = paginate_links(
				array(
					'base'      => $pagination_base,
					'format'    => '',
					'current'   => $paged,
					'total'     => max( 1, (int) $query->max_num_pages ),
					'prev_text' => '&laquo; Prev',
					'next_text' => 'Next &raquo;',
				)
			);
			if ( $pagination_links ) :
				?>
				<div class="tablenav" style="margin-top:12px;">
					<div class="tablenav-pages"><?php echo wp_kses_post( $pagination_links ); ?></div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_admin_single_view( $reg_id ) {
		$settings     = $this->get_settings();
		$base_amount  = (int) $settings['base_amount'];
		$tax_percent  = (float) $settings['tax_percent'];
		$tax_amount   = round( ( $base_amount * $tax_percent ) / 100, 2 );
		$total_amount = round( $base_amount + $tax_amount, 2 );
		$fields       = array(
			'Reference ID'             => get_post_meta( $reg_id, '_reference_id', true ),
			'Full Name of Player'      => get_post_meta( $reg_id, '_full_name', true ),
			'Date of Birth'            => get_post_meta( $reg_id, '_date_of_birth', true ),
			'Place of Birth'           => get_post_meta( $reg_id, '_place_of_birth', true ),
			'Residential Address'      => get_post_meta( $reg_id, '_residential_address', true ),
			"Guardian's Name"          => get_post_meta( $reg_id, '_guardian_name', true ),
			'Relationship with Player' => get_post_meta( $reg_id, '_relationship_with_player', true ),
			'Mobile Number'            => get_post_meta( $reg_id, '_mobile_number', true ),
			'Email ID'                 => get_post_meta( $reg_id, '_email_id', true ),
			'Playing Role'             => get_post_meta( $reg_id, '_playing_role', true ),
			'Batting Style'            => get_post_meta( $reg_id, '_batting_style', true ),
			'Bowling Style'            => get_post_meta( $reg_id, '_bowling_style', true ),
			'Preferred Trial Venue'    => get_post_meta( $reg_id, '_preferred_trial_venue', true ),
			'Preferred Trial Date'     => get_post_meta( $reg_id, '_preferred_trial_date', true ),
			'Preferred Batch Timing'   => get_post_meta( $reg_id, '_preferred_batch_timing', true ),
			'Payment Status'           => get_post_meta( $reg_id, '_payment_status', true ),
			'Razorpay Payment ID'      => get_post_meta( $reg_id, '_razorpay_payment_id', true ),
			'Razorpay Order ID'        => get_post_meta( $reg_id, '_razorpay_order_id', true ),
			'Razorpay Signature'       => get_post_meta( $reg_id, '_razorpay_signature', true ),
			'Payment Timestamp'        => get_post_meta( $reg_id, '_payment_timestamp', true ),
			'Base Amount (INR)'        => number_format( $base_amount, 2 ),
			'Tax Percent'              => $tax_percent . '%',
			'Tax Amount (INR)'         => number_format( $tax_amount, 2 ),
			'Total Amount (INR)'       => number_format( $total_amount, 2 ),
			'Approval Status'          => get_post_meta( $reg_id, '_approval_status', true ),
		);
		?>
		<div class="wrap">
			<h1>Registration Details</h1>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cap-trial-registrations' ) ); ?>" class="button">&larr; Back to list</a>
				<?php
				$print_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=cap_print_registration&reg_id=' . $reg_id ),
					'cap_print_registration_' . $reg_id
				);
				?>
				<a href="<?php echo esc_url( $print_url ); ?>" class="button" target="_blank" rel="noopener">Print</a>
				<a href="<?php echo esc_url( $print_url . '&download=pdf' ); ?>" class="button button-primary" target="_blank" rel="noopener">Download PDF</a>
			</p>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( $fields as $label => $value ) : ?>
						<tr>
							<th style="width: 300px;"><?php echo esc_html( $label ); ?></th>
							<td><?php echo esc_html( (string) $value ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function handle_print_registration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}

		$reg_id = absint( $_GET['reg_id'] ?? 0 );
		check_admin_referer( 'cap_print_registration_' . $reg_id );
		if ( ! $reg_id || self::POST_TYPE !== get_post_type( $reg_id ) ) {
			wp_die( 'Invalid registration' );
		}

		$settings     = $this->get_settings();
		$base_amount  = (int) $settings['base_amount'];
		$tax_percent  = (float) $settings['tax_percent'];
		$tax_amount   = round( ( $base_amount * $tax_percent ) / 100, 2 );
		$total_amount = round( $base_amount + $tax_amount, 2 );
		$fields       = array(
			'Reference ID'             => get_post_meta( $reg_id, '_reference_id', true ),
			'Full Name of Player'      => get_post_meta( $reg_id, '_full_name', true ),
			'Date of Birth'            => get_post_meta( $reg_id, '_date_of_birth', true ),
			'Place of Birth'           => get_post_meta( $reg_id, '_place_of_birth', true ),
			'Residential Address'      => get_post_meta( $reg_id, '_residential_address', true ),
			"Guardian's Name"          => get_post_meta( $reg_id, '_guardian_name', true ),
			'Relationship with Player' => get_post_meta( $reg_id, '_relationship_with_player', true ),
			'Mobile Number'            => get_post_meta( $reg_id, '_mobile_number', true ),
			'Email ID'                 => get_post_meta( $reg_id, '_email_id', true ),
			'Playing Role'             => get_post_meta( $reg_id, '_playing_role', true ),
			'Batting Style'            => get_post_meta( $reg_id, '_batting_style', true ),
			'Bowling Style'            => get_post_meta( $reg_id, '_bowling_style', true ),
			'Preferred Trial Venue'    => get_post_meta( $reg_id, '_preferred_trial_venue', true ),
			'Preferred Trial Date'     => get_post_meta( $reg_id, '_preferred_trial_date', true ),
			'Preferred Batch Timing'   => get_post_meta( $reg_id, '_preferred_batch_timing', true ),
			'Payment Status'           => get_post_meta( $reg_id, '_payment_status', true ),
			'Razorpay Payment ID'      => get_post_meta( $reg_id, '_razorpay_payment_id', true ),
			'Razorpay Order ID'        => get_post_meta( $reg_id, '_razorpay_order_id', true ),
			'Razorpay Signature'       => get_post_meta( $reg_id, '_razorpay_signature', true ),
			'Payment Timestamp'        => get_post_meta( $reg_id, '_payment_timestamp', true ),
			'Base Amount (INR)'        => number_format( $base_amount, 2 ),
			'Tax Percent'              => $tax_percent . '%',
			'Tax Amount (INR)'         => number_format( $tax_amount, 2 ),
			'Total Amount (INR)'       => number_format( $total_amount, 2 ),
			'Approval Status'          => get_post_meta( $reg_id, '_approval_status', true ),
		);

		$download_mode = sanitize_text_field( wp_unslash( $_GET['download'] ?? '' ) );
		if ( 'pdf' === $download_mode ) {
			header( 'Content-Disposition: inline; filename="registration-' . $reg_id . '.html"' );
		}
		?>
		<!doctype html>
		<html>
		<head>
			<meta charset="utf-8">
			<title>CAP Registration <?php echo esc_html( (string) $reg_id ); ?></title>
			<style>
				body { font-family: Arial, sans-serif; margin: 24px; }
				h1 { margin: 0 0 16px; }
				table { width: 100%; border-collapse: collapse; }
				th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
				th { width: 280px; background: #f8f8f8; }
				.noprint { margin-bottom: 16px; }
				@media print { .noprint { display: none; } }
			</style>
		</head>
		<body>
			<div class="noprint">
				<button onclick="window.print()">Print / Save as PDF</button>
			</div>
			<h1>CAP Registration Details</h1>
			<table>
				<tbody>
					<?php foreach ( $fields as $label => $value ) : ?>
						<tr>
							<th><?php echo esc_html( $label ); ?></th>
							<td><?php echo esc_html( (string) $value ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<script>
				<?php if ( 'pdf' === $download_mode ) : ?>
				window.print();
				<?php endif; ?>
			</script>
		</body>
		</html>
		<?php
		exit;
	}

	private function admin_action_buttons( $reg_id ) {
		$current_status = get_post_meta( $reg_id, '_approval_status', true );
		if ( in_array( $current_status, array( 'Approved', 'Disapproved' ), true ) ) {
			return '<em>No actions</em>';
		}

		$approve_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=cap_update_registration_status&reg_id=' . $reg_id . '&status=Approved' ),
			'cap_update_status_' . $reg_id
		);
		$reject_url  = wp_nonce_url(
			admin_url( 'admin-post.php?action=cap_update_registration_status&reg_id=' . $reg_id . '&status=Disapproved' ),
			'cap_update_status_' . $reg_id
		);

		return sprintf(
			'<a class="button button-primary" href="%1$s">Approve</a> <a class="button" href="%2$s">Disapprove</a>',
			esc_url( $approve_url ),
			esc_url( $reject_url )
		);
	}

	public function handle_export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'cap_export_csv' );

		$payment_filter  = sanitize_text_field( wp_unslash( $_GET['payment_status'] ?? '' ) );
		$approval_filter = sanitize_text_field( wp_unslash( $_GET['approval_status'] ?? '' ) );
		$meta_query      = array( 'relation' => 'AND' );
		if ( in_array( $payment_filter, array( 'paid', 'unpaid' ), true ) ) {
			$meta_query[] = array(
				'key'   => '_payment_status',
				'value' => $payment_filter,
			);
		}
		if ( in_array( $approval_filter, array( 'Pending', 'Approved', 'Disapproved' ), true ) ) {
			$meta_query[] = array(
				'key'   => '_approval_status',
				'value' => $approval_filter,
			);
		}

		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query;
		}
		$items = get_posts( $args );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cap-registrations-' . gmdate( 'Ymd-His' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Reference ID', 'Full Name', 'DOB', 'Place of Birth', 'Address', 'Guardian Name', 'Relationship', 'Mobile', 'Email', 'Role', 'Batting', 'Bowling', 'Venue', 'Trial Date', 'Batch', 'Payment', 'Approval', 'Created At' ) );
		foreach ( $items as $item ) {
			fputcsv(
				$output,
				array(
					get_post_meta( $item->ID, '_reference_id', true ),
					get_post_meta( $item->ID, '_full_name', true ),
					get_post_meta( $item->ID, '_date_of_birth', true ),
					get_post_meta( $item->ID, '_place_of_birth', true ),
					get_post_meta( $item->ID, '_residential_address', true ),
					get_post_meta( $item->ID, '_guardian_name', true ),
					get_post_meta( $item->ID, '_relationship_with_player', true ),
					get_post_meta( $item->ID, '_mobile_number', true ),
					get_post_meta( $item->ID, '_email_id', true ),
					get_post_meta( $item->ID, '_playing_role', true ),
					get_post_meta( $item->ID, '_batting_style', true ),
					get_post_meta( $item->ID, '_bowling_style', true ),
					get_post_meta( $item->ID, '_preferred_trial_venue', true ),
					get_post_meta( $item->ID, '_preferred_trial_date', true ),
					get_post_meta( $item->ID, '_preferred_batch_timing', true ),
					get_post_meta( $item->ID, '_payment_status', true ),
					get_post_meta( $item->ID, '_approval_status', true ),
					$item->post_date,
				)
			);
		}
		fclose( $output );
		exit;
	}

	public function handle_admin_status_update() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed' );
		}

		$reg_id = absint( $_GET['reg_id'] ?? 0 );
		$status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );

		check_admin_referer( 'cap_update_status_' . $reg_id );

		if ( ! $reg_id || ! in_array( $status, array( 'Approved', 'Disapproved' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=cap-trial-registrations' ) );
			exit;
		}

		update_post_meta( $reg_id, '_approval_status', $status );
		update_post_meta( $reg_id, '_registration_status', 'Approved' === $status ? 'Approved by Admin' : 'Disapproved by Admin' );
		$this->send_approval_notifications( $reg_id, $status );

		wp_safe_redirect( admin_url( 'admin.php?page=cap-trial-registrations' ) );
		exit;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = $this->get_settings();
		$tab      = sanitize_key( $_GET['tab'] ?? 'trial' );
		$allowed  = array( 'trial', 'payment_sms', 'email' );
		if ( ! in_array( $tab, $allowed, true ) ) {
			$tab = 'trial';
		}
		?>
		<div class="wrap">
			<h1>CAP Trial Registration Settings</h1>
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cap-trial-registration-settings&tab=trial' ) ); ?>" class="nav-tab <?php echo 'trial' === $tab ? 'nav-tab-active' : ''; ?>">1. Preferred Venue/Date/Time</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cap-trial-registration-settings&tab=payment_sms' ) ); ?>" class="nav-tab <?php echo 'payment_sms' === $tab ? 'nav-tab-active' : ''; ?>">2. Payment & SMS</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cap-trial-registration-settings&tab=email' ) ); ?>" class="nav-tab <?php echo 'email' === $tab ? 'nav-tab-active' : ''; ?>">3. Email Settings</a>
			</h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'cap_trial_registration_settings' ); ?>
				<table class="form-table">
					<?php if ( 'trial' === $tab ) : ?>
						<tr>
							<th scope="row"><label for="trial_venue_date_map">Venue-Date Mapping</label></th>
							<td>
								<textarea id="trial_venue_date_map" name="<?php echo esc_attr( self::OPT_KEY ); ?>[trial_venue_date_map]" rows="8" class="large-text code"><?php echo esc_textarea( $this->venue_map_to_lines( $settings['trial_venue_date_map'] ) ); ?></textarea>
								<p class="description">One line per venue. Format: <code>Venue Name|Date 1,Date 2</code></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="batch_timings">Batch Timings</label></th>
							<td>
								<textarea id="batch_timings" name="<?php echo esc_attr( self::OPT_KEY ); ?>[batch_timings]" rows="6" class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $settings['batch_timings'] ) ); ?></textarea>
								<p class="description">One timing per line.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="terms_url">Terms URL</label></th>
							<td><input type="url" id="terms_url" name="<?php echo esc_attr( self::OPT_KEY ); ?>[terms_url]" value="<?php echo esc_attr( $settings['terms_url'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="registrations_per_page">Registrations Per Page</label></th>
							<td>
								<input type="number" id="registrations_per_page" name="<?php echo esc_attr( self::OPT_KEY ); ?>[registrations_per_page]" value="<?php echo esc_attr( (int) $settings['registrations_per_page'] ); ?>" min="1" max="500">
								<p class="description">Controls pagination size on CAP Registrations admin listing.</p>
							</td>
						</tr>
					<?php elseif ( 'payment_sms' === $tab ) : ?>
						<tr>
							<th scope="row"><label for="razorpay_key_id">Razorpay Key ID</label></th>
							<td><input type="text" id="razorpay_key_id" name="<?php echo esc_attr( self::OPT_KEY ); ?>[razorpay_key_id]" value="<?php echo esc_attr( $settings['razorpay_key_id'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="razorpay_key_secret">Razorpay Key Secret</label></th>
							<td><input type="text" id="razorpay_key_secret" name="<?php echo esc_attr( self::OPT_KEY ); ?>[razorpay_key_secret]" value="<?php echo esc_attr( $settings['razorpay_key_secret'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="base_amount">Base Amount (INR)</label></th>
							<td><input type="number" id="base_amount" name="<?php echo esc_attr( self::OPT_KEY ); ?>[base_amount]" value="<?php echo esc_attr( $settings['base_amount'] ); ?>" min="1"></td>
						</tr>
						<tr>
							<th scope="row"><label for="tax_percent">Tax Percent</label></th>
							<td><input type="number" step="0.01" id="tax_percent" name="<?php echo esc_attr( self::OPT_KEY ); ?>[tax_percent]" value="<?php echo esc_attr( $settings['tax_percent'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="fast2sms_sms_api_url">Fast2SMS SMS API URL</label></th>
							<td><input type="url" id="fast2sms_sms_api_url" name="<?php echo esc_attr( self::OPT_KEY ); ?>[fast2sms_sms_api_url]" value="<?php echo esc_attr( $settings['fast2sms_sms_api_url'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="fast2sms_whatsapp_api_url">Fast2SMS WhatsApp API URL</label></th>
							<td><input type="url" id="fast2sms_whatsapp_api_url" name="<?php echo esc_attr( self::OPT_KEY ); ?>[fast2sms_whatsapp_api_url]" value="<?php echo esc_attr( $settings['fast2sms_whatsapp_api_url'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="fast2sms_api_key">Fast2SMS API Key</label></th>
							<td><input type="text" id="fast2sms_api_key" name="<?php echo esc_attr( self::OPT_KEY ); ?>[fast2sms_api_key]" value="<?php echo esc_attr( $settings['fast2sms_api_key'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="fast2sms_sender_id">Fast2SMS Sender ID</label></th>
							<td><input type="text" id="fast2sms_sender_id" name="<?php echo esc_attr( self::OPT_KEY ); ?>[fast2sms_sender_id]" value="<?php echo esc_attr( $settings['fast2sms_sender_id'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row">Fast2SMS WhatsApp</th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPT_KEY ); ?>[fast2sms_whatsapp_enabled]" value="1" <?php checked( '1', $settings['fast2sms_whatsapp_enabled'] ); ?>> Enable WhatsApp notifications</label></td>
						</tr>
					<?php elseif ( 'email' === $tab ) : ?>
						<tr>
							<th scope="row"><label for="admin_notification_email">Admin Notification Email</label></th>
							<td><input type="email" id="admin_notification_email" name="<?php echo esc_attr( self::OPT_KEY ); ?>[admin_notification_email]" value="<?php echo esc_attr( $settings['admin_notification_email'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="email_subject_confirmation">Confirmation Email Subject</label></th>
							<td><input type="text" id="email_subject_confirmation" name="<?php echo esc_attr( self::OPT_KEY ); ?>[email_subject_confirmation]" value="<?php echo esc_attr( $settings['email_subject_confirmation'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="email_body_confirmation">Confirmation Email Body</label></th>
							<td><textarea id="email_body_confirmation" name="<?php echo esc_attr( self::OPT_KEY ); ?>[email_body_confirmation]" rows="6" class="large-text"><?php echo esc_textarea( $settings['email_body_confirmation'] ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="email_subject_approval">Approval Status Email Subject</label></th>
							<td><input type="text" id="email_subject_approval" name="<?php echo esc_attr( self::OPT_KEY ); ?>[email_subject_approval]" value="<?php echo esc_attr( $settings['email_subject_approval'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th scope="row"><label for="email_body_approval">Approval Status Email Body</label></th>
							<td><textarea id="email_body_approval" name="<?php echo esc_attr( self::OPT_KEY ); ?>[email_body_approval]" rows="5" class="large-text"><?php echo esc_textarea( $settings['email_body_approval'] ); ?></textarea><p class="description">Use <code>{{status}}</code> placeholder for Approved/Disapproved.</p></td>
						</tr>
						<tr>
							<th scope="row"><label for="thank_you_content">Thank You Page Content</label></th>
							<td><textarea id="thank_you_content" name="<?php echo esc_attr( self::OPT_KEY ); ?>[thank_you_content]" rows="6" class="large-text"><?php echo esc_textarea( $settings['thank_you_content'] ); ?></textarea><p class="description">Shown after successful payment. Basic HTML is allowed.</p></td>
						</tr>
						<tr>
							<th scope="row"><label for="thank_you_page_url">Dedicated Thank You Page URL</label></th>
							<td><input type="url" id="thank_you_page_url" name="<?php echo esc_attr( self::OPT_KEY ); ?>[thank_you_page_url]" value="<?php echo esc_attr( $settings['thank_you_page_url'] ); ?>" class="regular-text"><p class="description">Optional. Example: <?php echo esc_html( home_url( '/thank-you/' ) ); ?>. If empty, plugin uses inline thank-you mode.</p></td>
						</tr>
						<tr>
							<th scope="row"><label for="thank_you_page_id">Assign Thank You Page</label></th>
							<td>
								<?php
								wp_dropdown_pages(
									array(
										'name'              => self::OPT_KEY . '[thank_you_page_id]',
										'id'                => 'thank_you_page_id',
										'selected'          => (int) $settings['thank_you_page_id'],
										'show_option_none'  => '-- Select a Page --',
										'option_none_value' => '0',
									)
								);
								?>
								<p class="description">Preferred method. If selected, payment redirects to this page.</p>
							</td>
						</tr>
					<?php endif; ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function is_valid_venue_date( $venue, $date ) {
		$settings = $this->get_settings();
		$map      = $settings['trial_venue_date_map'];

		return isset( $map[ $venue ] ) && in_array( $date, $map[ $venue ], true );
	}

	private function get_thank_you_redirect_url( $settings ) {
		if ( ! empty( $settings['thank_you_page_id'] ) ) {
			$page_url = get_permalink( (int) $settings['thank_you_page_id'] );
			if ( $page_url ) {
				return esc_url_raw( $page_url );
			}
		}
		if ( ! empty( $settings['thank_you_page_url'] ) ) {
			return esc_url_raw( $settings['thank_you_page_url'] );
		}
		return esc_url_raw( add_query_arg( array( 'cap_thank_you' => '1' ), get_permalink() ) );
	}
}
