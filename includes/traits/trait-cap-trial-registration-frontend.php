<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CAP_Trial_Registration_Frontend_Trait {
	public function enqueue_assets() {
		wp_register_style(
			'cap-trial-registration-frontend',
			CAP_TRIAL_REG_URL . 'assets/css/cap-trial-registration-frontend.css',
			array(),
			CAP_TRIAL_REG_VERSION
		);
		wp_register_script(
			'cap-trial-registration-razorpay-checkout',
			'https://checkout.razorpay.com/v1/checkout.js',
			array(),
			null,
			true
		);
		wp_register_script(
			'cap-trial-registration-frontend',
			CAP_TRIAL_REG_URL . 'assets/js/cap-trial-registration-frontend.js',
			array( 'jquery', 'cap-trial-registration-razorpay-checkout' ),
			CAP_TRIAL_REG_VERSION,
			true
		);
	}

	public function render_shortcode() {
		wp_enqueue_style( 'cap-trial-registration-frontend' );
		wp_enqueue_script( 'cap-trial-registration-frontend' );

		$settings    = $this->get_settings();
		$is_thank_you = isset( $_GET['cap_thank_you'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cap_thank_you'] ) );

		wp_localize_script(
			'cap-trial-registration-frontend',
			'capTrialReg',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'prepareCheckoutNonce' => wp_create_nonce( 'cap_prepare_checkout' ),
				'createOrderNonce'     => wp_create_nonce( 'cap_create_order' ),
				'verifyPaymentNonce'   => wp_create_nonce( 'cap_verify_payment' ),
				'razorpayKey'          => $settings['razorpay_key_id'],
				'checkoutSession'      => '',
				'venueDateMap'         => $settings['trial_venue_date_map'],
				'successMessage'       => $settings['email_body_confirmation'],
				'thankYouUrl'          => $this->get_thank_you_redirect_url( $settings ),
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
				<div id="cap-fullscreen-loader" class="cap-fullscreen-loader" style="display:none;" aria-live="polite" aria-busy="true">
					<div class="cap-loader-content">
						<div class="cap-loader-spinner" aria-hidden="true"></div>
						<p id="cap-loader-message">Payment successful. Redirecting…</p>
					</div>
				</div>
				<div id="cap-checkout-form-panel">
					<?php $this->render_form_panel( $settings ); ?>
				</div>
				<!-- <div id="cap-checkout-payment-panel" style="display:none;">
					<?php $this->render_payment_panel(); ?>
				</div> -->
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
				<div class="cap-step-indicator" data-step="4"><span>4</span> Payment</div>
			</div>

			<div class="cap-form-step" data-step="1">
				<h3>Personal Details</h3>
				<div class="cap-grid">
					<div class="cap-field"><label for="full_name">Full Name of Player <span class="required">*</span></label><input type="text" id="full_name" name="full_name" required></div>
					<div class="cap-field"><label for="date_of_birth">Date of Birth <span class="required">*</span></label><input type="date" id="date_of_birth" name="date_of_birth" required></div>
					<div class="cap-field"><label for="place_of_birth">Place of Birth<span class="required">*</span></label><input type="text" id="place_of_birth" name="place_of_birth" required></div>
					<div class="cap-field"><label for="residential_address">Residential Address<span class="required">*</span></label><textarea id="residential_address" name="residential_address" required></textarea></div>
					<div class="cap-field"><label for="guardian_name">Guardian's Name<span class="required">*</span></label><input type="text" id="guardian_name" name="guardian_name" required></div>
					<div class="cap-field"><label for="relationship_with_player">Relationship with Player<span class="required">*</span></label><input type="text" id="relationship_with_player" name="relationship_with_player" required></div>
					<div class="cap-field"><label for="mobile_number">Mobile Number<span class="required">*</span></label><input type="tel" id="mobile_number" name="mobile_number" pattern="[0-9]{10,15}" required></div>
					<div class="cap-field"><label for="email_id">Email ID<span class="required">*</span></label><input type="email" id="email_id" name="email_id" required></div>
				</div>
				<div class="cap-step-actions"><button type="button" class="cap-btn cap-step-next" data-current-step="1">Next Step</button></div>
			</div>

			<div class="cap-form-step" data-step="2" style="display:none;">
				<h3>Cricketer Details</h3>
				<div class="cap-grid">
					<div class="cap-field"><label for="playing_role">Playing Role<span class="required">*</span></label><select id="playing_role" name="playing_role" required><option value="">Select Playing Role</option><option value="Batter">Batter</option><option value="Bowler">Bowler</option><option value="Wicketkeeper">Wicketkeeper</option></select></div>
					<div class="cap-field"><label for="batting_style">Batting Style</label><select id="batting_style" name="batting_style" required><option value="">Select Batting Style</option><option value="Right-hand bat">Right-hand bat</option><option value="Left-hand bat">Left-hand bat</option></select></div>
					<div class="cap-field"><label for="bowling_style">Bowling Style</label><select id="bowling_style" name="bowling_style"><option value="">Select Bowling Style</option><option value="Right-arm fast">Right-arm fast</option><option value="Right-arm off spin">Right-arm off spin</option><option value="Right-arm leg spin">Right-arm leg spin</option><option value="Left-arm fast">Left-arm fast</option><option value="Left-arm orthodox spin">Left-arm orthodox spin</option><option value="Left-arm chinaman">Left-arm chinaman</option><option value="Others">Others</option></select></div>
				</div>
				<div class="cap-step-actions"><button type="button" class="cap-btn cap-btn-secondary cap-step-prev" data-current-step="2">Previous</button><button type="button" class="cap-btn cap-step-next" data-current-step="2">Next Step</button></div>
			</div>

			<div class="cap-form-step" data-step="3" style="display:none;">
				<h3>Trial Preference</h3>
				<div class="cap-grid">
					<div class="cap-field"><label for="cap_preferred_trial_venue">Preferred Trial Venue<span class="required">*</span></label><select id="cap_preferred_trial_venue" name="preferred_trial_venue" required><option value="">Select Venue</option><?php foreach ( $settings['trial_venue_date_map'] as $venue_name => $dates ) : ?><option value="<?php echo esc_attr( $venue_name ); ?>"><?php echo esc_html( $venue_name ); ?></option><?php endforeach; ?></select></div>
					<div class="cap-field"><label for="cap_preferred_trial_date">Preferred Trial Date<span class="required">*</span></label><select id="cap_preferred_trial_date" name="preferred_trial_date" required><option value="">Select Date<span class="required">*</span></option></select></div>
					<div class="cap-field"><label for="preferred_batch_timing">Preferred Batch Timing<span class="required">*</span></label><select id="preferred_batch_timing" name="preferred_batch_timing" required><option value="">Select Batch</option><?php foreach ( $settings['batch_timings'] as $batch_timing ) : ?><option value="<?php echo esc_attr( $batch_timing ); ?>"><?php echo esc_html( $batch_timing ); ?></option><?php endforeach; ?></select></div>
				</div>
				<h3>Mandatory Declarations<span class="required">*</span></h3>
				<div class="cap-checkbox-group">
					<label><input type="checkbox" name="declaration_correct_details" required> I confirm that all details submitted are correct.</label>
					<label><input type="checkbox" name="declaration_registration_only" required> I understand that payment confirms registration only and does not guarantee scholarship selection.</label>
					<label><input type="checkbox" name="declaration_terms" required> I agree to the programme terms and conditions.</label>
					<label><input type="checkbox" name="declaration_media_consent" required> I consent to the use of event photos/videos for CAP communication and promotional purposes.</label>
					<label><input type="checkbox" name="declaration_age_proof" required> I understand that original age-proof must be physically produced at the trial venue for verification.</label>
				</div>
				<div class="cap-terms-box">
					<h4>Terms & Conditions</h4>
					<ul><li>The registration fee is non-refundable once paid.</li><li>In the event of any dispute, the matter shall be subject to <b>Delhi jurisdiction only</b>.</li><li>Participation in the scholarship trials will be entirely at the participant’s own risk. CAP shall not be responsible for any injury, accident, loss, or damage arising during participation in the trials.</li></ul>
					<label><input type="checkbox" name="accept_terms_conditions" required> I accept the <a href="<?php echo esc_url( $terms_url ); ?>" target="_blank">Terms & Conditions</a></label>
				</div>
				<div class="cap-step-actions"><button type="button" class="cap-btn cap-btn-secondary cap-step-prev" data-current-step="3">Previous</button><button type="button" class="cap-btn cap-step-next" data-current-step="3">Proceed to Payment</button></div>
			</div>
			<div class="cap-form-step" data-step="4" style="display:none;">
	<h3>Payment</h3>

	<div class="cap-payment-summary">
		<?php
		$settings      = $this->get_settings();
		$base_amount   = (int) $settings['base_amount'];
		$tax_percent   = (float) $settings['tax_percent'];
		$tax_amount    = round( ( $base_amount * $tax_percent ) / 100, 2 );
		$total_amount  = round( $base_amount + $tax_amount, 2 );
		?>

		<p>Registration Fee: INR <?php echo esc_html( number_format( $base_amount, 2 ) ); ?></p>
		<p>Tax (<?php echo esc_html( $tax_percent ); ?>%): INR <?php echo esc_html( number_format( $tax_amount, 2 ) ); ?></p>
		<p><strong>Total: INR <?php echo esc_html( number_format( $total_amount, 2 ) ); ?></strong></p>
	</div>

	<div class="cap-step-actions">
		<button type="button" class="cap-btn cap-btn-secondary cap-step-prev" data-current-step="4">
			Previous
		</button>

<button type="button" id="cap-pay-now" class="cap-btn">
	Pay Now
</button>
	</div>

	<div id="cap-payment-result"></div>

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
		$errors          = array();
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

		$data        = $this->collect_clean_form_data();
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
		<?php
	}
}
