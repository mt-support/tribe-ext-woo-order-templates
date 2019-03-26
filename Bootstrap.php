<?php
/**
 * Plugin Name:       Event Tickets Plus Extension: Enhance Woo Order Templates
 * Plugin URI:        https://theeventscalendar.com/extensions/add-event-and-attendee-information-to-woocommerce-order-details/
 * GitHub Plugin URI: https://github.com/mt-support/tribe-ext-woo-order-templates
 * Description:       Adds event and attendee information to the WooCommerce order pages, including emails and displaying order details.
 * Version:           1.0.2
 * Extension Class:   Tribe\Extensions\ET_Woo_Order_Details\Bootstrap
 * Author:            Modern Tribe, Inc.
 * Author URI:        http://m.tri.be/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tribe-ext-woo-order-templates
 *
 *     This plugin is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     any later version.
 *
 *     This plugin is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *     GNU General Public License for more details.
 */

namespace Tribe\Extensions\ET_Woo_Order_Details;

use Tribe__Autoloader;
use Tribe__Events__Community__Tickets__Main;
use Tribe__Extension;

/**
 * Define Constants
 */

if ( ! defined( __NAMESPACE__ . '\NS' ) ) {
	define( __NAMESPACE__ . '\NS', __NAMESPACE__ . '\\' );
}

if ( ! defined( NS . 'PLUGIN_TEXT_DOMAIN' ) ) {
	// `Tribe\Extensions\ET_Woo_Order_Details\PLUGIN_TEXT_DOMAIN` is defined
	define( NS . 'PLUGIN_TEXT_DOMAIN', 'tribe-ext-woo-order-templates' );
}

// Do not load unless Tribe Common is fully loaded and our class does not yet exist.
if (
	class_exists( 'Tribe__Extension' )
	&& ! class_exists( NS . 'Bootstrap' )
) {
	/**
	 * Extension main class. Will utilize other classes after sanity checks. Class begins loading on init().
	 */
	class Bootstrap extends Tribe__Extension {

		/**
		 * @var Tribe__Autoloader
		 */
		private $class_loader;

		/**
		 * Setup the Extension's properties.
		 *
		 * This always executes even if the required plugins are not present.
		 */
		public function construct() {
			$this->add_required_plugin( 'Tribe__Tickets__Main' );
			// ET+ 4.5.6 was latest mention of changes to accommodate WooCommerce 3.x
			$this->add_required_plugin( 'Tribe__Tickets_Plus__Main', '4.5.6' );
		}

		/**
		 * Extension initialization and hooks.
		 */
		public function init() {
			load_plugin_textdomain( PLUGIN_TEXT_DOMAIN, false, basename( dirname( __FILE__ ) ) . '/languages/' );

			if ( ! $this->php_version_check() ) {
				return;
			}

			if ( ! $this->is_woocommerce_active() ) {
				return;
			}

			$this->class_loader();

			add_action( 'woocommerce_order_item_meta_start', [ new Main(), 'woocommerce_echo_event_info' ], 100, 3 );

			// Hide the event title that gets added by Community Tickets, to prevent duplicates
			if ( class_exists( 'Tribe__Events__Community__Tickets__Main' ) ) {
				remove_action( 'woocommerce_order_item_meta_start', [ Tribe__Events__Community__Tickets__Main::instance(), 'add_order_item_details' ], 10 );
			}
		}

		/**
		 * Check if we have a sufficient version of PHP. Admin notice if we don't and user should see it.
		 *
		 * @link https://theeventscalendar.com/knowledgebase/php-version-requirement-changes/ All extensions require PHP 5.6+.
		 *
		 * @return bool
		 */
		private function php_version_check() {
			$php_required_version = '5.6';

			if ( version_compare( PHP_VERSION, $php_required_version, '<' ) ) {
				if (
					is_admin()
					&& current_user_can( 'activate_plugins' )
				) {
					$message = '<p>';

					$message .= sprintf( __( '%s requires PHP version %s or newer to work. Please contact your website host and inquire about updating PHP.', PLUGIN_TEXT_DOMAIN ), $this->get_name(), $php_required_version );

					$message .= sprintf( ' <a href="%1$s">%1$s</a>', 'https://wordpress.org/about/requirements/' );

					$message .= '</p>';

					tribe_notice( PLUGIN_TEXT_DOMAIN . '-php-version', $message, [ 'type' => 'error' ] );
				}

				return false;
			}

			return true;
		}

		/**
		 * Check if WooCommerce is activated.
		 *
		 * @link https://docs.woocommerce.com/document/query-whether-woocommerce-is-activated/
		 *
		 * @return bool
		 */
		private function is_woocommerce_active() {
			return class_exists( 'WooCommerce' );
		}

		/**
		 * Use Tribe Autoloader for all class files within this namespace in the 'src' directory.
		 *
		 * @return Tribe__Autoloader
		 */
		public function class_loader() {
			if ( empty( $this->class_loader ) ) {
				$this->class_loader = new Tribe__Autoloader;
				$this->class_loader->set_dir_separator( '\\' );
				$this->class_loader->register_prefix(
					NS,
					__DIR__ . DIRECTORY_SEPARATOR . 'src'
				);
			}

			$this->class_loader->register_autoloader();

			return $this->class_loader;
		}
	}
}
