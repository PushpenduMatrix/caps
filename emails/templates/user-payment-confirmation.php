<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<!-- Main Message -->
<div style="margin-bottom:20px; text-align:center;">
	<p style="display:inline-block; margin:0; padding:12px 18px; background:#f9f9f9; border-radius:6px; font-size:16px; line-height:1.6; color:#333; font-weight:500;">
		<?php echo esc_html( $message ); ?>
	</p>
</div>

<!-- Details -->
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
	
	<tr>
		<td style="padding:8px;border-bottom:1px solid #eee;"><strong>Email</strong></td>
		<td style="padding:8px;border-bottom:1px solid #eee;">
			<a href="mailto:<?php echo esc_attr( $email ); ?>" style="color:#0073e6;text-decoration:none;">
				<?php echo esc_html( $email ); ?>
			</a>
		</td>
	</tr>

	<tr>
		<td style="padding:8px;border-bottom:1px solid #eee;"><strong>Mobile</strong></td>
		<td style="padding:8px;border-bottom:1px solid #eee;">
			<?php echo esc_html( $mobile ); ?>
		</td>
	</tr>

	<tr>
		<td style="padding:8px;"><strong>Payment Status</strong></td>
		<td style="padding:8px; color:green;">
			<strong><?php echo esc_html( ucfirst( $payment_status ) ); ?></strong>
		</td>
	</tr>

</table>