<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<p>A new registration has been submitted with payment.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
	<?php foreach ( $fields as $label => $value ) : 

		$value = esc_html( $value );

		if ( $label === 'Email' ) {
			$value = '<a href="mailto:' . esc_attr( $value ) . '" style="color:#0073e6;text-decoration:none;">' . esc_html( $value ) . '</a>';
		}

		$style = '';
		if ( $label === 'Payment Status' && strtolower( $value ) === 'paid' ) {
			$style = 'color:green;font-weight:bold;';
		}
		if ( $label === 'Approval Status' ) {
			$style = 'color:#ff9900;font-weight:bold;';
		}
	?>

	<tr>
		<td style="padding:8px;border-bottom:1px solid #eee;">
			<strong><?php echo esc_html( $label ); ?></strong>
		</td>
		<td style="padding:8px;border-bottom:1px solid #eee;<?php echo esc_attr( $style ); ?>">
			<?php echo $value; ?>
		</td>
	</tr>

	<?php endforeach; ?>
</table>