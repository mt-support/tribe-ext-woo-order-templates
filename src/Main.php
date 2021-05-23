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

		/** @var Tribe__Tickets_Plus__Commerce__WooCommerce__Main $woo_provider */
		$wootix = tribe( 'tickets-plus.commerce.woo' );

		$item_data = $item->get_data();

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

		/** @var Tribe__Tickets_Plus__Commerce__WooCommerce__Main $woo_provider */
		$woo_provider = tribe( 'tickets-plus.commerce.woo' );
		$attendees    = $woo_provider->get_attendees_by_id( $order_id );

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

	/**
	 * Adds the Event Title column header on WooCommerce Order Items table.
	 *
	 * @since TBD
	 *
	 * @param WC_Order $order Order Object.
	 */
	public function add_event_title_header( $order ) {

		if ( ! $this->should_render_event_column( $order ) ) {
			return;
		}

		?>
		<th class="item_event sortable" data-sort="string-ins">
			<?php esc_html_e( 'Event', PLUGIN_TEXT_DOMAIN ); ?>
		</th>
		<?php
	}

	/**
	 * Add Event Link for Order Items.
	 *
	 * @since TBD
	 *
	 * @param \WC_Product $product
	 * @param \WC_Order_Item_Product $item
	 * @param string $item_id
	 */
	public function add_event_title_for_order_item( $product, $item, $item_id ) {

		if ( ! is_object( $product ) ) {
			return;
		}

		if ( ! $this->should_render_event_column( $item->get_order() ) ) {
			return;
		}

		$event_id   = $product->get_meta( '_tribe_wooticket_for_event' );
		$event_post = ! empty( $event_id ) ? get_post( $event_id ) : '' ;
		$event      = ! empty( $event_post ) ? $event_post->post_title : '';
		$schedule   = function_exists( 'tribe_events_event_schedule_details' ) ? tribe_events_event_schedule_details( $event_post ) : '';
		$link       = sprintf( '<a target="_blank" rel="noopener nofollow" href="%s">%s</a> (%s)', get_permalink( $event_post ), esc_html( $event ), $schedule );

		?>
		<td class="item_event" width="15%" data-sort-value="<?php echo esc_attr( $event ) ?>">
			<?php echo $link; ?>
		</td>
		<?php
	}

	/**
	 * Add attendee data to Order Item view.
	 *
	 * @since TBD
	 *
	 * @param string $item_id
	 * @param \WC_Order_Item $item
	 * @param \WC_Product $product
	 */
	public function add_attendee_data_for_order_item( $item_id, $item, $product ) {

		if ( ! is_object( $product ) ) {
			return;
		}

		if ( ! $this->should_render_event_column( $item->get_order() ) ) {
			return;
		}

		/** @var Tribe__Tickets_Plus__Commerce__WooCommerce__Main $woo_provider */
		$woo_provider = tribe( 'tickets-plus.commerce.woo' );

		$ticket_id = $product->get_id();
		$attendees = $woo_provider->get_attendees_by_id( $item->get_order_id() );

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

			$table_columns[] = [
				esc_html_x( 'Name', 'Attendee meta table.', PLUGIN_TEXT_DOMAIN ),
				esc_html( $attendee[ 'holder_name' ] ),
			];

			$table_columns[] = [
				esc_html_x( 'Email', 'Attendee meta table.', PLUGIN_TEXT_DOMAIN ),
				esc_html( $attendee[ 'holder_email' ] ),
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
	 * Add inline styles for Attendee Table for Order Items.
	 *
	 * @since TBD
	 */
	function admin_order_table_styles() {

		$custom_css = '
                table.tribe-attendee-meta td:first-child {
	                padding-left: 0 !important;
                }
                table.tribe-attendee-meta td {
	                padding: 5px 10px !important;
                }
                ';
		wp_add_inline_style( 'event-tickets-admin-css', $custom_css );
	}

	/**
	 * Check if we have Tickets in Order.
	 *
	 * @param WC_Order $order
	 */
	public function should_render_event_column( $order ) {
		return (bool) $order->get_meta( '_tribe_has_tickets' );
	}

}