<?php

/*
	Plugin Name: WooCommerce Piwik integration
	Plugin URI: https://wordpress.org/plugins/woocommerce-piwik-integration
	Description: Allows Piwik and Piwik PRO tracking code to be inserted into WooCommerce store pages.
	Version: 2.1.4
	Author: piwikpro
	Author URI: https://piwik.pro
	Text Domain: woocommerce-piwik-integration
	Domain Path: /languages/
	License: GPLv3
	License URI: http://www.gnu.org/licenses/gpl-3.0.txt

	Copyright (C) 2017 by Piwik PRO <https://piwik.pro>
	and associates (see AUTHORS.txt file).

	This file is part of WooCommerce Piwik integration plugin.

	WooCommerce Piwik integration plugin is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	WooCommerce Piwik integration plugin is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with WooCommerce Piwik integration plugin; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wc_piwik_add_integration( $integrations ) {
	global $woocommerce;

	if ( is_object( $woocommerce ) && version_compare( $woocommerce->version, '2.1-beta-1', '>=' ) ) {
		include_once( 'includes/class-wc-piwik.php' );
		$integrations[] = 'WC_Piwik';
	}

	return $integrations;
}

add_filter( 'woocommerce_integrations', 'wc_piwik_add_integration' );
