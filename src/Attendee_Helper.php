<?php

namespace Tribe\Extensions\ET_Woo_Order_Details;

use ReflectionClass;
use Tribe__Tickets__Tickets;

/**
 * Extend class from Event Tickets to expose non-public methods to this extension.
 */
class Attendee_Helper extends Tribe__Tickets__Tickets {

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_filter( 'tribe_tickets_get_modules', [ $this, 'remove_as_active_module' ] );
	}

	/**
	 * @param $active_modules
	 *
	 * @return mixed
	 */
	public function remove_as_active_module( $active_modules ) {

		unset( $active_modules[ $this->class_name ] );

		return $active_modules;
	}

	/**
	 * Returns the meta key used to link attendees with orders.
	 *
	 * @see Tribe__Tickets__Tickets::get_attendee_order_key()
	 *
	 * @param  ReflectionClass $provider_class The ticket provider's class.
	 *
	 * @return string
	 */
	public function safely_get_attendee_order_key( $provider_class ) {
		return $this->get_attendee_order_key( $provider_class );
	}

	/**
	 * Returns the meta key used to link attendees with the base event.
	 *
	 * @see Tribe__Tickets__Tickets::get_attendee_event_key()
	 *
	 * @param  ReflectionClass $provider_class The ticket provider's class.
	 *
	 * @return string
	 */
	public function safely_get_attendee_event_key( $provider_class ) {
		return $this->get_attendee_event_key( $provider_class );
	}

	/**
	 * Returns the attendee object post type.
	 *
	 * @see Tribe__Tickets__Tickets::get_attendee_object()
	 *
	 * @param  ReflectionClass $provider_class The ticket provider's class.
	 *
	 * @return string
	 */
	public function safely_get_attendee_object( $provider_class ) {
		return $this->get_attendee_object( $provider_class );
	}
}