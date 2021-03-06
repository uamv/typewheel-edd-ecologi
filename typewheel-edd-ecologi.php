<?php
/**
* Plugin Name: EDD Ecologi
* Plugin URI: http://typewheel.xyz
* Description: Plant trees and/or offset carbon via ecologi for every EDD purchase and/or renewal
* Version: 2.0.2
* Author: Typewheel
* Author URI: http://typewheel.xyz
* Text Domain: typewheel-edde
*
* @package Typewheel
* @version 2.0.2
* @author uamv
* @copyright Copyright (c) 2021, Typewheel
* @link http://typewheel.xyz
* @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
* Define constants.
*/

define( 'TYPEWHEEL_EDDE_VERSION', '2.0.2' );
define( 'TYPEWHEEL_EDDE_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'TYPEWHEEL_EDDE_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'TYPEWHEEL_EDDE_STORE_URL', 'https://responsiblewp.com' );

/**
* The core plugin class that is used to define internationalization,
* dashboard-specific hooks, and public-facing site hooks.
*/
require TYPEWHEEL_EDDE_DIR_PATH . 'class-typewheel-edd-ecologi.php';

/**
* Begins execution of the plugin.
*
* Since everything within the plugin is registered via hooks,
* then kicking off the plugin from this point in the file does
* not affect the page life cycle.
*
* @since    0.1
*/
function run_edd_ecologi() {

    $plugin = new Typewheel_EDD_Ecologi();
    $plugin->run();

    // include class for managing EDD licensing
    // see https://github.com/wpstars/EDD_Client

    require_once( 'includes/EDD_Client/EDD_Client_Init.php' );
    $licensed = new EDD_Client_Init( __FILE__, TYPEWHEEL_EDDE_STORE_URL );

}
run_edd_ecologi();

/**
 *  Clean up the cron event
 */
register_deactivation_hook( __FILE__, function() {

    $timestamp = wp_next_scheduled( 'typewheel_edde_do_every_eight_hours' );
    wp_unschedule_event( $timestamp, 'typewheel_edde_do_every_eight_hours' );

} );
