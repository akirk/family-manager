<?php
/**
 * Plugin Name: Family Manager
 * Description: A WpApp household dashboard for family tasks, appointments, and rewards.
 * Version: 1.0.0
 * Author: Alex Kirk
 * Text Domain: family-manager
 * Tested up to: 7.1
 * Requires PHP: 7.4
 */

namespace FamilyManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Autoloader for plugin classes.
spl_autoload_register( function( $class ) {
    $prefix = 'FamilyManager\\';
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
