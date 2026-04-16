<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CAP_Trial_Registration_Admin_Trait {
	public function enqueue_admin_assets() {
		$page = sanitize_key( $_GET['page'] ?? '' );
		if ( ! in_array( $page, array( 'cap-trial-registrations', 'cap-trial-registration-settings' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'cap-trial-registration-admin',
			CAP_TRIAL_REG_URL . 'assets/css/cap-trial-registration-admin.css',
			array(),
			CAP_TRIAL_REG_VERSION
		);
		wp_enqueue_script(
			'cap-trial-registration-admin',
			CAP_TRIAL_REG_URL . 'assets/js/cap-trial-registration-admin.js',
			array( 'jquery' ),
			CAP_TRIAL_REG_VERSION,
			true
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
		$query      = new WP_Query( $query_args );
		$items      = $query->posts;
		$total_items = (int) $query->found_posts;
		$start_item  = $total_items > 0 ? ( ( $paged - 1 ) * $per_page ) + 1 : 0;
		$end_item    = min( $paged * $per_page, $total_items );
		?>
	<div class="cap-list-toolbar">
		<form method="get" class="cap-filter-form">
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

			<button class="button button-secondary">Filter</button>

			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cap-trial-registrations' ) ); ?>">
				Reset
			</a>

			<?php
			$export_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=cap_export_registrations_csv&payment_status=' . rawurlencode( $payment_filter ) . '&approval_status=' . rawurlencode( $approval_filter ) ),
				'cap_export_csv'
			);
			?>

			<a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>">
				Export CSV
			</a>
		</form>
	</div>
			<!-- 🔷 RESULT COUNT -->
	<div class="cap-list-meta">
		<?php
		printf(
			'Showing %1$d-%2$d of %3$d registrations',
			(int) $start_item,
			(int) $end_item,
			(int) $total_items
		);
		?>
	</div>

		<!-- 🔷 TABLE WRAP -->
	<div class="cap-table-wrap">
		<table class="widefat striped cap-acf-table">
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
					<tr class="cap-empty-row">
						<td colspan="11">No registrations found.</td>
					</tr>
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
								<?php $view_url = admin_url( 'admin.php?page=cap-trial-registrations&view_id=' . $item->ID ); ?>
								<a class="button button-small" href="<?php echo esc_url( $view_url ); ?>">View</a>
							</td>
							<td class="cap-admin-actions">
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
					'page'            => 'cap-trial-registrations',
					'payment_status'  => $payment_filter,
					'approval_status' => $approval_filter,
					'paged'           => '%#%',
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
				<div class="cap-pagination">
			<?php echo wp_kses_post( $pagination_links ); ?>
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
<div class="wrap cap-trial-registration-admin">

	<h1 class="cap-page-title">Registration Details</h1>

	<!-- 🔷 ACTION BAR (ACF STYLE) -->
	<div class="cap-list-toolbar">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cap-trial-registrations' ) ); ?>" class="button">
			&larr; Back to list
		</a>

		<?php $print_url = wp_nonce_url( admin_url( 'admin-post.php?action=cap_print_registration&reg_id=' . $reg_id ), 'cap_print_registration_' . $reg_id ); ?>

		<a href="<?php echo esc_url( $print_url ); ?>" class="button" target="_blank" rel="noopener">
			Print
		</a>

		<a href="<?php echo esc_url( $print_url . '&download=pdf' ); ?>" class="button button-primary" target="_blank" rel="noopener">
			Download PDF
		</a>
	</div>

	<!-- 🔷 ACF STYLE BOX -->
	<div class="cap-acf-box">

		<div class="cap-acf-header">
			Registration Information
		</div>

		<table class="form-table cap-form-table">
			<tbody>
				<?php foreach ( $fields as $label => $value ) : ?>
					<tr>
						<th><?php echo esc_html( $label ); ?></th>
						<td><?php echo esc_html( (string) $value ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	</div>

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
		<div class="wrap cap-trial-registration-admin">

	<h1 class="cap-page-title">CAP Trial Registration Settings</h1>

	<h2 class="nav-tab-wrapper cap-tabs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cap-trial-registration-settings&tab=trial' ) ); ?>" class="nav-tab <?php echo 'trial' === $tab ? 'nav-tab-active cap-accent-blue' : ''; ?>">1. Preferred Venue/Date/Time</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cap-trial-registration-settings&tab=payment_sms' ) ); ?>" class="nav-tab <?php echo 'payment_sms' === $tab ? 'nav-tab-active' : ''; ?>">2. Payment & SMS</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cap-trial-registration-settings&tab=email' ) ); ?>" class="nav-tab <?php echo 'email' === $tab ? 'nav-tab-active' : ''; ?>">3. Email Settings</a>
	</h2>

	<form method="post" action="options.php" class="cap-settings-form">
		<?php settings_fields( 'cap_trial_registration_settings' ); ?>

		<div class="cap-acf-box">

			<!-- 🔵 ACF STYLE HEADER -->
			<div class="cap-acf-header">
				<?php
				if ( 'trial' === $tab ) {
					echo 'Trial Configuration';
				} elseif ( 'payment_sms' === $tab ) {
					echo 'Payment & SMS Configuration';
				} else {
					echo 'Email Configuration';
				}
				?>
			</div>

			<table class="form-table cap-form-table">
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
								<p class="description"><code>One timing per line.</code></p>
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
							<td><textarea id="email_body_approval" name="<?php echo esc_attr( self::OPT_KEY ); ?>[email_body_approval]" rows="5" class="large-text"><?php echo esc_textarea( $settings['email_body_approval'] ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="email_body_rejected">Rejected Status Email Body</label></th>
							<td><textarea id="email_body_rejected" name="<?php echo esc_attr( self::OPT_KEY ); ?>[email_body_rejected]" rows="5" class="large-text"><?php echo esc_textarea( $settings['email_body_rejected'] ); ?></textarea></td>
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
		</div>

		<div class="cap-submit-wrap">
			<?php submit_button( 'Save Settings' ); ?>
		</div>

	</form>
</div>
		<?php
	}
}
