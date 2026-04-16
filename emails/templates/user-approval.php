<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<p style="margin:0 0 20px 0; text-align:center; font-size:16px; line-height:1.6; color:#333333; font-weight:500;">
	<?php echo esc_html( $message ); ?>
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:15px;">
	<tr>
		<td style="padding:8px;border-bottom:1px solid #eee;"><strong>Reference ID</strong></td>
		<td style="padding:8px;border-bottom:1px solid #eee;"><?php echo esc_html( $reference_id ); ?></td>
	</tr>

	<tr>
		<td style="padding:8px;"><strong>Status</strong></td>
		<td style="padding:8px;color:<?php echo $status === 'approved' ? 'green' : 'red'; ?>;">
			<strong><?php echo ucfirst( esc_html( $status ) ); ?></strong>
		</td>
	</tr>
</table>