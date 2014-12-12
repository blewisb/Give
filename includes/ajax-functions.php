<?php
/**
 * AJAX Functions
 *
 * Process the front-end AJAX actions.
 *
 * @package     Give
 * @subpackage  Functions/AJAX
 * @copyright   Copyright (c) 2014, WordImpress
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether AJAX is enabled.
 *
 * This will be deprecated soon in favor of give_is_ajax_disabled()
 *
 * @since 1.0
 * @return bool
 */
function give_is_ajax_enabled() {
	$retval = ! give_is_ajax_disabled();

	return apply_filters( 'give_is_ajax_enabled', $retval );
}

/**
 * Check if AJAX works as expected
 *
 * @since 2.2
 * @return bool True if AJAX works, false otherwise
 */
function give_test_ajax_works() {

	$params = array(
		'sslverify' => false,
		'timeout'   => 60,
	);

	$ajax  = wp_remote_get( add_query_arg( 'action', 'give_test_ajax', give_get_ajax_url() ), $params );
	$works = true;

	if ( is_wp_error( $ajax ) ) {

		$works = false;

	} else {

		if ( empty( $ajax['response'] ) ) {
			$works = false;
		}

		if ( empty( $ajax['response']['code'] ) || 200 !== (int) $ajax['response']['code'] ) {
			$works = false;
		}

		if ( empty( $ajax['response']['message'] ) || 'OK' !== $ajax['response']['message'] ) {
			$works = false;
		}

		if ( ! isset( $ajax['body'] ) || 0 !== (int) $ajax['body'] ) {
			$works = false;
		}

	}

	return $works;
}

/**
 * Checks whether AJAX is disabled.
 *
 * @since 1.0
 * @return bool
 */
function give_is_ajax_disabled() {
	$retval = ! give_get_option( 'enable_ajax_cart' );

	return apply_filters( 'give_is_ajax_disabled', $retval );
}


/**
 * Get AJAX URL
 *
 * @since 1.3
 * @return string
 */
function give_get_ajax_url() {
	$scheme = defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN ? 'https' : 'admin';

	$current_url = give_get_current_page_url();
	$ajax_url    = admin_url( 'admin-ajax.php', $scheme );

	if ( preg_match( '/^https/', $current_url ) && ! preg_match( '/^https/', $ajax_url ) ) {
		$ajax_url = preg_replace( '/^http/', 'https', $ajax_url );
	}

	return apply_filters( 'give_ajax_url', $ajax_url );
}

/**
 * Loads Checkout Login Fields the via AJAX
 *
 * @since 1.0
 * @return void
 */
function give_load_checkout_login_fields() {
	do_action( 'give_purchase_form_login_fields' );
	give_die();
}

add_action( 'wp_ajax_nopriv_checkout_login', 'give_load_checkout_login_fields' );

/**
 * Load Checkout Register Fields via AJAX
 *
 * @since 1.0
 * @return void
 */
function give_load_checkout_register_fields() {
	do_action( 'give_purchase_form_register_fields' );
	give_die();
}

add_action( 'wp_ajax_nopriv_checkout_register', 'give_load_checkout_register_fields' );

/**
 * Get Download Title via AJAX (used only in WordPress Admin)
 *
 * @since 1.0
 * @return void
 */
function give_ajax_get_download_title() {
	if ( isset( $_POST['download_id'] ) ) {
		$title = get_the_title( $_POST['download_id'] );
		if ( $title ) {
			echo $title;
		} else {
			echo 'fail';
		}
	}
	give_die();
}

add_action( 'wp_ajax_give_get_download_title', 'give_ajax_get_download_title' );
add_action( 'wp_ajax_nopriv_give_get_download_title', 'give_ajax_get_download_title' );

/**
 * Recalculate cart taxes
 *
 * @since 1.6
 * @return void
 */
function give_ajax_recalculate_taxes() {
	if ( ! give_get_cart_contents() ) {
		return false;
	}

	if ( empty( $_POST['billing_country'] ) ) {
		$_POST['billing_country'] = give_get_shop_country();
	}

	ob_start();
	give_checkout_cart();
	$cart     = ob_get_clean();
	$response = array(
		'html'  => $cart,
		'total' => html_entity_decode( give_cart_total( false ), ENT_COMPAT, 'UTF-8' ),
	);

	echo json_encode( $response );

	give_die();
}

add_action( 'wp_ajax_give_recalculate_taxes', 'give_ajax_recalculate_taxes' );
add_action( 'wp_ajax_nopriv_give_recalculate_taxes', 'give_ajax_recalculate_taxes' );

/**
 * Retrieve a states drop down
 *
 * @since 1.6
 * @return void
 */
function give_ajax_get_states_field() {
	if ( empty( $_POST['country'] ) ) {
		$_POST['country'] = give_get_shop_country();
	}
	$states = give_get_shop_states( $_POST['country'] );

	if ( ! empty( $states ) ) {

		$args = array(
			'name'             => $_POST['field_name'],
			'id'               => $_POST['field_name'],
			'class'            => $_POST['field_name'] . '  edd-select',
			'options'          => give_get_shop_states( $_POST['country'] ),
			'show_option_all'  => false,
			'show_option_none' => false
		);

		$response = EDD()->html->select( $args );

	} else {

		$response = 'nostates';
	}

	echo $response;

	give_die();
}

add_action( 'wp_ajax_give_get_shop_states', 'give_ajax_get_states_field' );
add_action( 'wp_ajax_nopriv_give_get_shop_states', 'give_ajax_get_states_field' );

/**
 * Retrieve a states drop down
 *
 * @since 1.6
 * @return void
 */
function give_ajax_download_search() {
	global $wpdb;

	$search  = esc_sql( sanitize_text_field( $_GET['s'] ) );
	$results = array();
	if ( current_user_can( 'edit_products' ) ) {
		$items = $wpdb->get_results( "SELECT ID,post_title FROM $wpdb->posts WHERE `post_type` = 'download' AND `post_title` LIKE '%$search%' LIMIT 50" );
	} else {
		$items = $wpdb->get_results( "SELECT ID,post_title FROM $wpdb->posts WHERE `post_type` = 'download' AND `post_status` = 'publish' AND `post_title` LIKE '%$search%' LIMIT 50" );
	}

	if ( $items ) {

		foreach ( $items as $item ) {

			$results[] = array(
				'id'   => $item->ID,
				'name' => $item->post_title
			);
		}

	} else {

		$items[] = array(
			'id'   => 0,
			'name' => __( 'No results found', 'edd' )
		);

	}

	echo json_encode( $results );

	give_die();
}

add_action( 'wp_ajax_give_download_search', 'give_ajax_download_search' );
add_action( 'wp_ajax_nopriv_give_download_search', 'give_ajax_download_search' );

/**
 * Search the customers database via Ajax
 *
 * @since 2.2
 * @return void
 */
function give_ajax_customer_search() {
	global $wpdb;

	$search  = esc_sql( sanitize_text_field( $_GET['s'] ) );
	$results = array();
	if ( ! current_user_can( 'view_shop_reports' ) ) {
		$customers = array();
	} else {
		$customers = $wpdb->get_results( "SELECT id,name,email FROM {$wpdb->prefix}give_customers WHERE `name` LIKE '%$search%' OR `email` LIKE '%$search%' LIMIT 50" );
	}

	if ( $customers ) {

		foreach ( $customers as $customer ) {

			$results[] = array(
				'id'   => $customer->id,
				'name' => $customer->name . '(' . $customer->email . ')'
			);
		}

	} else {

		$customers[] = array(
			'id'   => 0,
			'name' => __( 'No results found', 'edd' )
		);

	}

	echo json_encode( $results );

	give_die();
}

add_action( 'wp_ajax_give_customer_search', 'give_ajax_customer_search' );

/**
 * Check for Download Price Variations via AJAX (this function can only be used
 * in WordPress Admin). This function is used for the Edit Payment screen when downloads
 * are added to the purchase. When each download is chosen, an AJAX call is fired
 * to this function which will check if variable prices exist for that download.
 * If they do, it will output a dropdown of all the variable prices available for
 * that download.
 *
 * @author Sunny Ratilal
 * @since  1.5
 * @return void
 */
function give_check_for_download_price_variations() {
	if ( ! current_user_can( 'edit_products' ) ) {
		die( '-1' );
	}

	$download_id = intval( $_POST['download_id'] );
	$download    = get_post( $download_id );

	if ( 'download' != $download->post_type ) {
		die( '-2' );
	}

	if ( give_has_variable_prices( $download_id ) ) {
		$variable_prices = give_get_variable_prices( $download_id );

		if ( $variable_prices ) {
			$ajax_response = '<select class="give_price_options_select edd-select edd-select" name="give_price_option">';
			foreach ( $variable_prices as $key => $price ) {
				$ajax_response .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $price['name'] ) . '</option>';
			}
			$ajax_response .= '</select>';
			echo $ajax_response;
		}

	}

	give_die();
}

add_action( 'wp_ajax_give_check_for_download_price_variations', 'give_check_for_download_price_variations' );


/**
 * Searches for users via ajax and returns a list of results
 *
 * @since 2.0
 * @return void
 */
function give_ajax_search_users() {

	if ( current_user_can( 'manage_shop_settings' ) ) {

		$search_query = trim( $_POST['user_name'] );

		$found_users = get_users( array(
				'number' => 9999,
				'search' => $search_query . '*'
			)
		);

		$user_list = '<ul>';
		if ( $found_users ) {
			foreach ( $found_users as $user ) {
				$user_list .= '<li><a href="#" data-login="' . esc_attr( $user->user_login ) . '">' . esc_html( $user->user_login ) . '</a></li>';
			}
		} else {
			$user_list .= '<li>' . __( 'No users found', 'edd' ) . '</li>';
		}
		$user_list .= '</ul>';

		echo json_encode( array( 'results' => $user_list ) );

	}
	die();
}

add_action( 'wp_ajax_give_search_users', 'give_ajax_search_users' );
