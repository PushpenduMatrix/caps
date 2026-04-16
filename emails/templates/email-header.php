<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<!doctype html>
<html>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:20px 0;">
<tr>
<td align="center">

<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">

<tr>
	<td align="center" style="padding:20px;background:#24295f;">
		<img src="<?php echo esc_url( $logo_url ?? '' ); ?>" style="max-width:180px;">
	</td>
</tr>

<tr>
	<td style="background:#f91c3d;color:#ffffff;padding:15px;text-align:center;font-size:18px;font-weight:bold;">
		<?php echo esc_html( $email_heading ?? 'Notification' ); ?>
	</td>
</tr>

<tr>
<td style="padding:20px;color:#333333;font-size:14px;line-height:1.6;">