<?php
/**
 * Plugin Name: Households
 * Description: A WpApp dashboard for a household, or for several: who is at which home, shared tasks and appointments, and what each house needs people to know.
 * Version: 1.1.0+0e9c54616b2d
 * Author: Alex Kirk
 * Text Domain: households
 * Tested up to: 7.1
 * Requires PHP: 7.4
 */

namespace Households;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Autoloader for plugin classes.
spl_autoload_register( function( $class ) {
    $prefix = 'Households\\';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, $len ) ) . '.php';
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

add_action( 'plugins_loaded', function() {
    $app = new App();
    $app->init();
} );

register_activation_hook( __FILE__, function() {
    $app = new App();
    $app->activate();
} );

register_deactivation_hook( __FILE__, function() {
    $app = new App();
    $app->deactivate();
} );
