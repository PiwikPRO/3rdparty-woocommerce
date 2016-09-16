<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

?>

<link rel="stylesheet" href="<?php echo plugin_dir_url(realpath(__DIR__)) . 'assets/css/admin.css'; ?>" type="text/css" media="all" />
<p>
	<?php echo isset( $this->method_description ) ? wpautop( $this->method_description ) : ''; ?>
</p>

<div class="clear tab-margin"></div>
<p class="tab-container clear">
	<a class="<?php echo $cloudClass; ?>tab-custom" href="<?php echo $cloudSubtab; ?>">Piwik Cloud</a>
	<a class="<?php echo $selfHostedClass; ?>tab-custom last" href="<?php echo $selfHostedSubtab; ?>">Self-hosted
		Piwik</a>
</p>
<div class="clear"></div>
<?php
if ( $_GET['subtab'] == 'piwik-self-hosted' ) {
	?>
	<h3 class="title title-custom">Self-hosted Piwik</h3>
	<table class="form-table">
		<?php $this->generate_settings_html( $this->selfHostedFields ); ?>
	</table>
<?php
} else {
	?>
	<h3 class="title title-custom">Piwik Cloud integration</h3>
	<hr>
	<?php
	if ( get_option( 'woocommerce_piwik_integrated' ) ) {
		?>
		<p>
			Click the button below to stop collecting the data to your Piwik Cloud instance.
		</p>
		<p>
			<span style="color: #75a204; font-size: 18px; margin-right: 10px;"><strong>Connected</strong></span>
			<a href="<?php echo "http://{$this->settings['piwik_domain_name']}" ?>">View Dashboard</a>
		</p>
		<p>
			<a href="<?php echo "$url&disconnect-piwik-cloud=1"; ?>">
				<input class="button-primary" type="button" value="Disconnect"/>
			</a>
		</p>
	<?php
	} else {
		?>
		<p>
			Click the button below to seamlessly integrate with <a href="http://piwik.pro">Piwik Cloud</a>.
		</p>
		<p>
			<a href="<?php echo "$url&integrate-piwik-cloud=1"; ?>">
				<input class="button-primary" type="button" value="Connect"/>
			</a>
		</p>
	<?php
	}
}
?>

<h3 class="title title-custom">Common settings</h3>
<hr>
<table class="form-table">
	<?php $this->generate_settings_html( $this->commonFields ); ?>
</table>

<!-- Section -->
<div><input type="hidden" name="section" value="<?php echo $this->id; ?>"/></div>