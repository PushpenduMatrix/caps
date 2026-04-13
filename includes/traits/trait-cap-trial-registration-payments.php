<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CAP_Trial_Registration_Payments_Trait {
	private function create_checkout_session( $data ) {
		$session_key = wp_generate_uuid4();
		$payload     = array(
			'data'       => $data,
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
				'order_id'     => sanitize_text_field( $body['id'] ),
				'amount'       => $amount_paise,
				'currency'     => 'INR',
				'key'          => $settings['razorpay_key_id'],
				'full_name'    => $session['data']['full_name'] ?? '',
				'email'        => $session['data']['email_id'] ?? '',
				'contact'      => $session['data']['mobile_number'] ?? '',
				'reference_id' => '',
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
				'message'      => 'Payment successful. Confirmation has been sent.',
				'redirect_url' => esc_url_raw( $redirect_url ),
			)
		);
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
			'full_name'                     => sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) ),
			'date_of_birth'                 => sanitize_text_field( wp_unslash( $_POST['date_of_birth'] ?? '' ) ),
			'place_of_birth'                => sanitize_text_field( wp_unslash( $_POST['place_of_birth'] ?? '' ) ),
			'residential_address'           => sanitize_textarea_field( wp_unslash( $_POST['residential_address'] ?? '' ) ),
			'guardian_name'                 => sanitize_text_field( wp_unslash( $_POST['guardian_name'] ?? '' ) ),
			'relationship_with_player'      => sanitize_text_field( wp_unslash( $_POST['relationship_with_player'] ?? '' ) ),
			'mobile_number'                 => sanitize_text_field( wp_unslash( $_POST['mobile_number'] ?? '' ) ),
			'email_id'                      => sanitize_email( wp_unslash( $_POST['email_id'] ?? '' ) ),
			'playing_role'                  => sanitize_text_field( wp_unslash( $_POST['playing_role'] ?? '' ) ),
			'batting_style'                 => sanitize_text_field( wp_unslash( $_POST['batting_style'] ?? '' ) ),
			'bowling_style'                 => sanitize_text_field( wp_unslash( $_POST['bowling_style'] ?? '' ) ),
			'preferred_trial_venue'         => sanitize_text_field( wp_unslash( $_POST['preferred_trial_venue'] ?? '' ) ),
			'preferred_trial_date'          => sanitize_text_field( wp_unslash( $_POST['preferred_trial_date'] ?? '' ) ),
			'preferred_batch_timing'        => sanitize_text_field( wp_unslash( $_POST['preferred_batch_timing'] ?? '' ) ),
			'declaration_correct_details'   => ! empty( $_POST['declaration_correct_details'] ) ? 'Yes' : 'No',
			'declaration_registration_only' => ! empty( $_POST['declaration_registration_only'] ) ? 'Yes' : 'No',
			'declaration_terms'             => ! empty( $_POST['declaration_terms'] ) ? 'Yes' : 'No',
			'declaration_media_consent'     => ! empty( $_POST['declaration_media_consent'] ) ? 'Yes' : 'No',
			'declaration_age_proof'         => ! empty( $_POST['declaration_age_proof'] ) ? 'Yes' : 'No',
			'accept_terms_conditions'       => ! empty( $_POST['accept_terms_conditions'] ) ? 'Yes' : 'No',
		);
	}
}
