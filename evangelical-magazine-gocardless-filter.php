<?php
/**
Plugin Name: Evangelical Magazine GoCardless Filter
Description: Modifications for the Fat Beehive GoCardless Gravity Forms Plugin
Plugin URI: http://www.evangelicalmagazine.com/
Version: 0.1
Author: Mark Barnes
Author URI: http://www.markbarnes.net/
*/

add_filter ('fb_gf_gocardless_action', 'mb_em_filter_gocardless_action', 10, 3);
add_filter ('fb_gf_gocardless_subscription_name', 'mb_em_filter_gocardless_subscription_name', 10, 3);
add_filter ('fb_gf_gocardless_subscription_interval', 'mb_em_filter_gocardless_subscription_interval', 10, 3);
add_filter ('fb_gf_gocardless_subscription_interval_unit', 'mb_em_filter_gocardless_subscription_interval_unit', 10, 3);
add_filter ('gform_addon_pre_process_feeds', 'mb_em_redirect_to_correct_payment_processor', 10, 3);


/**
* Filters the GoCardless action
*
* Changes the payment type to 'subscription' if one of two hidden fields are found.
*
* @param string $action - the existing action
* @param array $entry - the entry submitted by the user
* @param array $form - the form used for this submission
* @return string - the revised action
*/
function mb_em_filter_gocardless_action ($action, $entry, $form) {
	if (isset ($form['fields'])) {
		foreach ($form ['fields'] as $f) {
			if ($f->label == 'gocardless_subscription_interval' || $f->label == 'gocardless_subscription_interval_unit') {
				return 'subscription';
			}
		}
	}
	return $action;
}

/**
* Filters the GoCardless subscription name
*
* Supplies the GoCardless subscription name for clarity.
*
* @param string $name - the existing name
* @param array $entry - the entry submitted by the user
* @param array $form - the form used for this submission
* @return string - the revised name
*/
function mb_em_filter_gocardless_subscription_name ($name, $entry, $form) {
	if (isset ($form['fields'])) {
		$name = '';
		foreach ($form ['fields'] as $f) {
			if (strtolower($f->label) == 'quantity' ) {
				$name = $entry[$f->id].' '.($entry[$f->id] > 1 ? 'copies' : 'copy').$name;
			}
			if (strtolower($f->label) == 'subscription type' ) {
				$name = $name.', '.trim(substr($entry[$f->id],0,strcspn ($entry[$f->id], '-–—')));
			}
		}
	}
	return 'Evangelical Magazine ('.$name.')';
}

/**
* Filters the GoCardless subscription interval
*
* @param int $interval - the existing interval
* @param array $entry - the entry submitted by the user
* @param array $form - the form used for this submission
* @return int - the revised interval
*/
function mb_em_filter_gocardless_subscription_interval ($interval, $entry, $form) {
	if (isset ($form['fields'])) {
		foreach ($form ['fields'] as $f) {
			if ($f->label == 'gocardless_subscription_interval' ) {
				return $entry[$f->id];
			}
		}
	}
	return $interval;
}

/**
* Filters the GoCardless subscription interval unit
*
* Will be one of 'yearly', 'monthly', or 'weekly'
*
* @param string $interval_unit - the existing interval unit
* @param array $entry - the entry submitted by the user
* @param array $form - the form used for this submission
* @return string - the revised interval unit
*/
function mb_em_filter_gocardless_subscription_interval_unit ($interval_unit, $entry, $form) {
	if (isset ($form['fields'])) {
		foreach ($form ['fields'] as $f) {
			if ($f->label == 'gocardless_subscription_interval_unit' ) {
				return $entry[$f->id];
			}
		}
	}
	return $interval_unit;
}

/**
* Ensures the correct payment processor is used
*
* Checks the form fields to see whether direct debit or credit card is selected as the payment type, then redirects as appropriate.
* Filters gform_addon_pre_process_feeds
*
* @param array $feeds - an array of payment feeds assigned to this form
* @param array $entry - the entry submitted by the user
* @param array $form - the form used for this submission
* @return array - the revised feed list
*/
function mb_em_redirect_to_correct_payment_processor ($feeds, $entry, $form) {
	if (isset ($form['fields'])) {
		// First, check the entry to determine which payment type has been chosen
		foreach ($form ['fields'] as $f) {
			if (strtolower($f->label) == 'subscription type' ) {
				$payment_type = strpos(strtolower($entry[$f->id]), 'credit card') !== FALSE ? 'credit card' : 'direct debit';
				break;
			}
		}
		// If necessary, remove the other payment types from the feed
		if (isset($payment_type)) {
			foreach ($feeds as $k => $feed) {
				if ($payment_type == 'credit card' && $feed['addon_slug'] == 'gravityformsfbgfgocardlesshosted') {
					unset($feeds[$k]);
				} elseif ($payment_type == 'direct debit' && in_array($feed['addon_slug'], array ('gravityformspaypal', 'gravityformspaypalpaymentspro', 'gravityformsstripe', 'gravityformsauthorizenet'))) {
					unset($feeds[$k]);
				}
			}
			// The PayPalPro plugin doesn't use the standard methods, so instead we have to remove the filters it has added.
			if ($payment_type == 'direct debit') {
            	remove_filter('gform_validation',array("GFPayPalPro", "paypalpro_validation"), 1000, 4);
                remove_filter("gform_confirmation", array("GFPayPalPro", "start_express_checkout"), 1000, 4);
            	remove_action('gform_after_submission',array("GFPayPalPro", "paypalpro_after_submission"), 10, 2);
            	remove_filter("gform_get_form_filter", array("GFPayPalPro", "maybe_confirm_express_checkout"));
			}
		}
	}
	return $feeds;
}