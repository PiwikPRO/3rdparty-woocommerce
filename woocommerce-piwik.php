<?php

/**
Plugin Name: WooCommerce Piwik
Plugin URI: http://wordpress.org/plugins/woocommerce-piwik/
Description: Allows Piwik and Piwik PRO tracking code to be inserted into WooCommerce store pages.
Author: Piwik PRO
Author URI: http://www.piwik.pro
Version: 1.0.0
*/

function wc_piwik_add_integration($integrations)
{
	global $woocommerce;

	if (is_object($woocommerce) && version_compare($woocommerce->version, '2.1-beta-1', '>=')) {
		include_once('includes/class-wc-piwik.php');
		$integrations[] = 'WC_Piwik';
	}

	return $integrations;
}

add_filter('woocommerce_integrations', 'wc_piwik_add_integration');