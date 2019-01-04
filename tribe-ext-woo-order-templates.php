<?php
/**
 * Plugin Name:       Event Tickets Plus Extension: Enhance Woo Order Templates
 * Plugin URI:        https://theeventscalendar.com/extensions/add-event-and-attendee-information-to-woocommerce-order-details/
 * GitHub Plugin URI: https://github.com/mt-support/tribe-ext-woo-order-templates
 * Description:       Adds event and attendee information to the WooCommerce order pages, including emails and the checkout screen.
 * Version:           1.0.1
 * Extension Class:   Tribe\Extensions\ETWooOrderDetails\Main
 * Author:            Modern Tribe, Inc.
 * Author URI:        http://m.tri.be/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tribe-ext-extension-template
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

namespace Tribe\Extensions\ETWooOrderDetails;

use Tribe__Dependency;
use Tribe__Extension;

/**
 * Define Constants
 */

if ( ! defined( __NAMESPACE__ . '\NS' ) ) {
	define( __NAMESPACE__ . '\NS', __NAMESPACE__ . '\\' );
}

if ( ! defined( NS . 'PLUGIN_TEXT_DOMAIN' ) ) {
	// `Tribe\Extensions\ETWooOrderDetails\PLUGIN_TEXT_DOMAIN` is defined
	define( NS . 'PLUGIN_TEXT_DOMAIN', 'tribe-ext-extension-template' );
}

// Do not load unless Tribe Common is fully loaded and our class does not yet exist.
if (
	class_exists( 'Tribe__Extension' )
	&& ! class_exists( NS . 'Main' )
) {
	/**
	 * Extension main class, class begins loading on init() function.
	 */
	class Main extends Tribe__Extension {

		/**
		 * Is Events Calendar PRO active. If yes, we will add some extra functionality.
		 *
		 * @return bool
		 */
		public $ecp_active = false;

		/**
		 * Setup the Extension's properties.
		 *
		 * This always executes even if the required plugins are not present.
		 */
		public function construct() {
			// Requirements and other properties such as the extension homepage can be defined here.

			/**
			 * Examples:
			 * All these version numbers are the ones on or after November 16, 2016, but you could remove the version
			 * number, as it's an optional parameter. Know that your extension code will not run at all (we won't even
			 * get this far) if you are not running The Events Calendar 4.3.3+ or Event Tickets 4.3.3+, as that is where
			 * the Tribe__Extension class exists, which is what we are extending.
			 */
			// $this->add_required_plugin( 'Tribe__Tickets__Main', '4.3.3' );
			// $this->add_required_plugin( 'Tribe__Tickets_Plus__Main', '4.3.3' );
			// $this->add_required_plugin( 'Tribe__Events__Main', '4.3.3' );
			// $this->add_required_plugin( 'Tribe__Events__Pro__Main', '4.3.3' );
			// $this->add_required_plugin( 'Tribe__Events__Community__Main', '4.3.2' );
			// $this->add_required_plugin( 'Tribe__Events__Community__Tickets__Main', '4.3.2' );
			// $this->add_required_plugin( 'Tribe__Events__Filterbar__View', '4.3.3' );
			// $this->add_required_plugin( 'Tribe__Events__Tickets__Eventbrite__Main', '4.3.2' );
			// $this->add_required_plugin( 'Tribe_APM', '4.4' );

			// Conditionally-require Events Calendar PRO. If it is active, run an extra bit of code.
			add_action( 'tribe_plugins_loaded', [ $this, 'detect_tec_pro' ], 0 );
		}

		/**
		 * Check required plugins after all Tribe plugins have loaded.
		 *
		 * Useful for conditionally-requiring a Tribe plugin, whether to add extra functionality
		 * or require a certain version but only if it is active.
		 */
		public function detect_tec_pro() {
			if ( Tribe__Dependency::instance()->is_plugin_active( 'Tribe__Events__Pro__Main' ) ) {
				$this->add_required_plugin( 'Tribe__Events__Pro__Main', '4.3.3' );
				$this->ecp_active = true;
			}
		}

		/**
		 * Extension initialization and hooks.
		 */
		public function init() {
			// Load plugin textdomain
			load_plugin_textdomain( PLUGIN_TEXT_DOMAIN, false, basename( dirname( __FILE__ ) ) . '/languages/' );

			if ( ! $this->php_version_check() ) {
				return;
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

	} // end class
} // end if class_exists check
