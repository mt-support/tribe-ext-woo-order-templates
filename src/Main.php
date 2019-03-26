<?php

namespace Tribe\Extensions\ET_Woo_Order_Details;

use Tribe__Events__Main;
use Tribe__Simple_Table;
use Tribe__Tickets_Plus__Commerce__WooCommerce__Main;
use Tribe__Tickets_Plus__Main;
use Tribe__Tickets_Plus__Meta;
use WC_Order;
use WC_Order_Item_Product;
use WP_Post;

/**
 * Extension's main logic. Only loaded after Bootstrap ensures we're ready.
 */
class Main {

	/**
	 * Indicates whether the attendee stylesheet has been output or not
	 *
	 * @var bool
	 */
	private $woo_attendee_styles_output = false;

	/**
	 * Echoes the attendee meta when attached to relevant WooCommerce action.
	 *
	 * @see action woocommerce_order_item_meta_start
	 * @see Tribe__Tickets_Plus__Commerce__WooCommerce__Main::get_event_for_ticket()
	 */
	public function woocommerce_echo_event_info( $item_id, $item, $order ) {
		if (
			! $item instanceof WC_Order_Item_Product
			|| ! $order instanceof WC_Order
		) {
			return;
		}

		$wootix = Tribe__Tickets_Plus__Commerce__WooCommerce__Main::get_instance();

		$item_data = $item->get_data();

		// Generate tickets early so we can get attendee meta.
		// Note, if the default order status is one that does affect stock, no tickets will be generated.
		$wootix->generate_tickets( $order->get_id() );

		// This is either true or a WP_Post, such as for any enabled post type (such as a ticket on a Page), not just for Tribe Events.
		$event = $wootix->get_event_for_ticket( $item_data['product_id'] );

		// Bail if no connected post, since it's required of a WooCommerce Ticket but not of all WooCommerce Products
		if ( empty( $event ) ) {
			return;
		}

		// Show event details if this ticket is for a tribe event.
		if (
			$event instanceof WP_Post
			&& class_exists( 'Tribe__Events__Main' )
			&& Tribe__Events__Main::POSTTYPE === $event->post_type
		) {
			$event_time       = tribe_events_event_schedule_details( $event, '<em>', '</em>' );
			$event_venue_name = tribe_get_venue( $event );
			$event_address    = tribe_get_full_address( $event );
			$event_details    = [];

			// Output event title in same format as Community Tickets.
			$event_details[] = sprintf(
				'<a href="%1$s" class="event-title">%2$s</a>',
				esc_attr( get_permalink( $event ) ),
				esc_html( get_the_title( $event ) )
			);

			if ( ! empty( $event_time ) ) {
				$event_details[] = $event_time;
			}

			if ( ! empty( $event_venue_name ) ) {
				$event_details[] = $event_venue_name;
			}

			if ( ! empty( $event_address ) ) {
				$event_details[] = $event_address;
			}

			printf(
				'<div class="tribe-event-details">%1$s</div>',
				implode( $event_details, '<br />' )
			);
		}

		$this->output_woo_attendee_styles();
		$this->echo_attendee_meta( $order->get_id(), $item_data['product_id'] );
	}

	/**
	 * Echoes the woo attendee stylesheet if it's not already included in the page
	 *
	 * This allows you to output the stylesheet after wp_head(), which simplifies
	 * this extension a decent amount.
	 */
	protected function output_woo_attendee_styles() {
		if ( $this->woo_attendee_styles_output ) {
			return;
		}

		$this->woo_attendee_styles_output = true;

		echo '<style type="text/css">';
		include( 'resources/tribe-attendee-meta-table.css' );
		echo '</style>';
	}

	/**
	 * Echoes attendee meta for every attendee in selected order
	 *
	 * @param string $order_id  Order or RSVP post ID.
	 * @param string $ticket_id The specific ticket to output attendees for.
	 */
	protected function echo_attendee_meta( $order_id, $ticket_id = null ) {
		$order_helper = new Tickets_Order_Helper( $order_id );

		$attendees = $order_helper->get_attendees();

		foreach ( $attendees as $attendee ) {
			// Skip attendees that are not for this ticket type.
			if ( ! empty( $ticket_id ) && $ticket_id != $attendee['product_id'] ) {
				continue;
			}

			$table_columns = [];

			$table_columns[] = [
				sprintf(
					'<strong class="tribe-attendee-meta-heading">%1$s</strong>',
					esc_html_x( 'Ticket ID', 'Attendee meta table.', PLUGIN_TEXT_DOMAIN )
				),
				sprintf(
					'<strong class="tribe-attendee-meta-heading">%1$s</strong>',
					esc_html( $attendee['ticket_id'] )
				),
			];

			$fields = $this->get_attendee_meta( $attendee['product_id'], $attendee['qr_ticket_id'] );
			if ( ! empty( $fields ) ) {
				foreach ( $fields as $field ) {
					$table_columns[] = [
						esc_html( $field['label'] ),
						esc_html( $field['value'] ),
					];
				}
			}

			$table_columns[] = [
				esc_html_x( 'Security Code', 'Attendee meta table.', PLUGIN_TEXT_DOMAIN ),
				esc_html( $attendee['security_code'] ),
			];

			$table                        = new Tribe__Simple_Table( $table_columns );
			$table->html_escape_td_values = false;
			$table->table_attributes      = [
				'class' => 'tribe-attendee-meta',
			];

			echo $table->output_table();
		}
	}

	/**
	 * Get attendee meta
	 *
	 * @param string $ticket_id    Ticket ID.
	 * @param string $qr_ticket_id QR Ticket ID.
	 *
	 * @return array Attendee meta array.
	 */
	protected function get_attendee_meta( $ticket_id, $qr_ticket_id ) {
		$output = [];

		$meta_fields = Tribe__Tickets_Plus__Main::instance()->meta()->get_meta_fields_by_ticket( $ticket_id );
		$meta_data   = get_post_meta( $qr_ticket_id, Tribe__Tickets_Plus__Meta::META_KEY, true );

		foreach ( $meta_fields as $field ) {
			if ( 'checkbox' === $field->type && isset( $field->extra['options'] ) ) {
				$values = [];
				foreach ( $field->extra['options'] as $option ) {
					$key = $field->slug . '_' . sanitize_title( $option );

					if ( isset( $meta_data[$key] ) ) {
						$values[] = $meta_data[$key];
					}
				}

				$value = implode( ', ', $values );
			} elseif ( isset( $meta_data[$field->slug] ) ) {
				$value = $meta_data[$field->slug];
			} else {
				continue;
			}

			if ( ! empty( $value ) ) {
				$output[$field->slug] = [
					'slug'  => $field->slug,
					'label' => $field->label,
					'value' => $value,
				];
			}
		}

		return $output;
	}
}