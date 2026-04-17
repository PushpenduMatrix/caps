<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CAP_Trial_Registration_Notifications_Trait {
		private function send_post_payment_notifications( $reg_id ) {
		$email          = get_post_meta( $reg_id, '_email_id', true );
		$mobile         = get_post_meta( $reg_id, '_mobile_number', true );
		$settings       = $this->get_settings();
		$subject        = $settings['email_subject_confirmation'];
		$body           = $settings['email_body_confirmation'];
		$payment_status = get_post_meta( $reg_id, '_payment_status', true );

		if ( 'paid' !== $payment_status ) {
			return;
		}

			// ✅ EMAIL (only existing fields passed)
		if ( is_email( $email ) ) {

		CAP_Email::send(
			$email,
			$subject,
			'user-payment-confirmation.php',
			array(
				'message'        => $body,
				'payment_status' => $payment_status,
				'email'          => $email,
				'mobile'         => $mobile,
				'email_heading'  => 'Payment Confirmation',
				'logo_url'       => 'https://cricketacademyofpathans.com/wp-content/uploads/2021/06/wl2-2.png',
			)
		);
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

		$base_amount  = (int) $settings['base_amount'];
		$tax_percent  = (float) $settings['tax_percent'];
		$tax_amount   = round( ( $base_amount * $tax_percent ) / 100, 2 );
		$total_amount = round( $base_amount + $tax_amount, 2 );

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

		CAP_Email::send(
		$admin_email,
		'New CAP Registration Received',
		'admin-registration.php',
		array(
			'fields'        => $fields,
			'email_heading' => 'New Registration Submitted',
			'logo_url'      => 'https://cricketacademyofpathans.com/wp-content/uploads/2021/06/wl2-2.png',
		) );
	}

	private function send_approval_notifications( $reg_id, $status ) {
		// print_r($status); exit;
		
		$email    = get_post_meta( $reg_id, '_email_id', true );
		$mobile   = get_post_meta( $reg_id, '_mobile_number', true );
		$settings = $this->get_settings();
		$subject  = $settings['email_subject_approval'];
		$status_key = strtolower( $status ); // approved / disapproved
		$message     = $this->get_email_body_by_status( $settings, $status_key, $reg_id );
		$reference_id = get_post_meta( $reg_id, '_reference_id', true );
		// $message = $this->get_email_body_by_status( $settings, $status_key, $reg_id );

	// ✅ EMAIL (HTML TEMPLATE)
	if ( is_email( $email ) ) {

		CAP_Email::send(
			$email,
			$settings['email_subject_approval'] ?? 'Registration Status Update',
			'user-approval.php',
			array(
				'message'       => $message,
				'reference_id'  => $reference_id,
				'status'        => $status_key,
				'email_heading' => 'Registration ' . ucfirst( $status_key ),
				'logo_url'      => 'https://cricketacademyofpathans.com/wp-content/uploads/2021/06/wl2-2.png',
			)
		);
	}

		$this->send_fast2sms( $mobile, $body, 'sms' );
		$this->send_fast2sms( $mobile, $body, 'whatsapp' );
	}

	private function get_email_body_by_status( $settings, $status, $reg_id ) {

	switch ( $status ) {
		case 'approved':
			$body = $settings['email_body_approved'] ?? '';
			break;

		case 'disapproved':
			$body = $settings['email_body_rejected'] ?? '';
			break;

		default:
			$body = $settings['email_body_approval'] ?? '';
	}

	return $body;
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
}
