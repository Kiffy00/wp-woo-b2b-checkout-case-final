<?php
/**
 * B2B Child Theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', function () {
    $parent_style = 'parent-style';

    wp_enqueue_style(
        $parent_style,
        get_template_directory_uri() . '/style.css',
        array(),
        wp_get_theme( get_template() )->get( 'Version' )
    );

    wp_enqueue_style(
        'child-style',
        get_stylesheet_uri(),
        array( $parent_style ),
        wp_get_theme()->get( 'Version' )
    );
}, 20 );
