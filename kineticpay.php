<?php
/**
Plugin Name: Kineticpay for GravityForms
Plugin URI: https://wordpress.org/plugins/kineticpay-for-gravityforms/
Description: Kineticpay. Fair payment platform.
Version: 1.0.3
Author: Kinetic Innovative Technologies Sdn Bhd
Author URI: https://www.kitsb.com.my/
License: GPL-2.0+
Text Domain: gravityformskineticpay
Domain Path: /languages
*/

defined( 'ABSPATH' ) || die();

define('GF_KINETICPAY_VERSION', '1.0.3');
define('GF_KINETICPAY_URL', plugin_dir_url(__FILE__));
define('GF_KINETICPAY_PATH', dirname(__FILE__));

add_action('gform_loaded', array( 'GF_Kineticpay_Bootstrap', 'load' ), 5);

class GF_Kineticpay_Bootstrap
{

  public static function load()
  {

    if ( ! method_exists('GFForms', 'include_payment_addon_framework')) {
      return;
    }

    require_once 'helpers/kineticpay_wpconnect.php';
    require_once 'class-gf-kineticpay.php';

    GFAddOn::register('GFKineticpay');
  }
}

function gf_kineticpay()
{
  return GFKineticpay::get_instance();
}

add_action('admin_print_styles', 'set_kinetic_log');
function set_kinetic_log()
{
  $html = "<style>.gform-icon--kineticpay2 {background-image: url('".GF_KINETICPAY_URL . "assets/images/kineticpay.png');background-repeat: no-repeat;background-size: cover;}.gform-icon--kineticpay3 {background-image: url('".GF_KINETICPAY_URL . "assets/images/kineticpay.png');background-repeat: no-repeat;background-size: contain;background-position: bottom;}.kineticpay4{align-items: normal!important}.gform-icon--kineticpay5 {background-image: url('".GF_KINETICPAY_URL . "assets/images/kineticpay.png');background-repeat: no-repeat;background-size: 30px;background-position: 20px 20px;
}</style>";
echo wp_kses( $html, array(
				'style' => array(),
            ) );
}
add_action('admin_print_scripts', 'set_kinetic_js', 9999);
function set_kinetic_js()
{
$js = "<script>
		jQuery( document ).ready( function ($) {
			$( '.gform-icon--kineticpay' ).closest('.icon').addClass('gform-icon--kineticpay2');
			$( '.gform-icon--kineticpay' ).closest('.button-icon').addClass('gform-icon--kineticpay3');
			$( '.gform-icon--kineticpay' ).closest('.gform-form-toolbar__icon').addClass('gform-icon--kineticpay3');
			$( '.gform-icon--kineticpay' ).closest('a').addClass('kineticpay4');
			setInterval(function () {
				if ($( '#sidebar_field_icon').hasClass('gform-icon--kineticpay')) {
					$( '.gform-icon--kineticpay' ).closest('#sidebar_field_info').addClass('gform-icon--kineticpay5');
				} else {
					$('#sidebar_field_info').removeClass('gform-icon--kineticpay5');
				}
			}, 1);
		});	
		</script>";
	echo wp_kses( $js, array(
				'script' => array(),
            ) );
}