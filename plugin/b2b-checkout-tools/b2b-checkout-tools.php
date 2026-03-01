<?php
/**
 * Plugin Name: B2B Checkout Tools
 * Plugin URI: https://example.com/
 * Description: Adds a business organization number field to WooCommerce checkout, looks up company name, and stores both on the order.
 * Version: 1.1.0
 * Author: Luka
 * Text Domain: b2b-checkout-tools
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Add organization number field to checkout.
 */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
$fields['billing']['billing_orgnr'] = array(
'type'        => 'text',
'label'       => __( 'Organization Number', 'b2b-checkout-tools' ),
'placeholder' => __( '9 digits', 'b2b-checkout-tools' ),
'required'    => true,
'class'       => array( 'form-row-wide' ),
'priority'    => 120,
'clear'       => true,
);

return $fields;
} );

/**
 * Validate organization number on checkout.
 */
add_action( 'woocommerce_checkout_process', function () {
if ( ! isset( $_POST['billing_orgnr'] ) ) {
wc_add_notice( __( 'Organization Number is required.', 'b2b-checkout-tools' ), 'error' );
return;
}

$orgnr = sanitize_text_field( wp_unslash( $_POST['billing_orgnr'] ) );

if ( ! preg_match( '/^\d{9}$/', $orgnr ) ) {
wc_add_notice( __( 'Organization Number must be exactly 9 digits.', 'b2b-checkout-tools' ), 'error' );
}
} );

/**
 * Look up company name from Enhetsregisteret and cache for 24 hours.
 */
function b2b_checkout_tools_lookup_company_name( $orgnr ) {
$orgnr = sanitize_text_field( $orgnr );

if ( ! preg_match( '/^\d{9}$/', $orgnr ) ) {
return '';
}

$transient_key = 'b2b_org_lookup_' . $orgnr;
$cached        = get_transient( $transient_key );

if ( false !== $cached && is_array( $cached ) && isset( $cached['name'] ) ) {
return sanitize_text_field( $cached['name'] );
}

$url      = 'https://data.brreg.no/enhetsregisteret/api/enheter/' . rawurlencode( $orgnr );
$response = wp_remote_get(
$url,
array(
'timeout' => 10,
'headers' => array(
'Accept' => 'application/json',
),
)
);

if ( is_wp_error( $response ) ) {
return '';
}

$status_code = (int) wp_remote_retrieve_response_code( $response );

if ( 200 !== $status_code ) {
set_transient(
$transient_key,
array(
'name' => '',
),
DAY_IN_SECONDS
);

return '';
}

$body = json_decode( wp_remote_retrieve_body( $response ), true );

if ( ! is_array( $body ) || empty( $body['navn'] ) || ! is_string( $body['navn'] ) ) {
set_transient(
$transient_key,
array(
'name' => '',
),
DAY_IN_SECONDS
);

return '';
}

$company_name = sanitize_text_field( $body['navn'] );

set_transient(
$transient_key,
array(
'name' => $company_name,
),
DAY_IN_SECONDS
);

return $company_name;
}

/**
 * Save organization number and looked-up company name to the order.
 */
add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
if ( empty( $_POST['billing_orgnr'] ) ) {
return;
}

$orgnr = sanitize_text_field( wp_unslash( $_POST['billing_orgnr'] ) );

if ( ! preg_match( '/^\d{9}$/', $orgnr ) ) {
return;
}

$order->update_meta_data( '_billing_orgnr', $orgnr );

$company_name = b2b_checkout_tools_lookup_company_name( $orgnr );

if ( ! empty( $company_name ) ) {
$order->update_meta_data( '_billing_company_name_lookup', $company_name );
}
}, 10, 2 );

/**
 * Show organization number and looked-up company name in admin order page.
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
$orgnr        = $order->get_meta( '_billing_orgnr' );
$company_name = $order->get_meta( '_billing_company_name_lookup' );

if ( ! empty( $orgnr ) ) {
echo '<p><strong>' . esc_html__( 'Organization Number:', 'b2b-checkout-tools' ) . '</strong> ' . esc_html( $orgnr ) . '</p>';
}

if ( ! empty( $company_name ) ) {
echo '<p><strong>' . esc_html__( 'Registered Company Name:', 'b2b-checkout-tools' ) . '</strong> ' . esc_html( $company_name ) . '</p>';
}
} );