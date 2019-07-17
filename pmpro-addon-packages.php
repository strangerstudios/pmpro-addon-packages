<?php
/**
 * Plugin Name: Paid Memberships Pro - Addon Packages
 * Plugin URI: https://www.paidmembershipspro.com/pmpro-addon-packages/
 * Description: Allow PMPro members to purchase access to specific pages. This plugin is meant to be a temporary solution until support for multiple membership levels is added to PMPro.
 * Version: .7.8
 * Author: Stranger Studios
 * Author URI: https://www.strangerstudios.com
 */

$custom_dir = get_stylesheet_directory() . '/paid-memberships-pro/pmpro-addon-packages/';
$custom_file = $custom_dir . 'pmpro-addon-packages-shortcode.php';
if ( file_exists( $custom_file ) ) {
	require_once( $custom_file );
} else {
	require_once( dirname( __FILE__ ) . '/shortcodes/pmpro-addon-packages-shortcode.php' );
}

/**
 * Globals
 * add this to your wp-config.php to set an expiration on addon packages
 * define('PMPROAP_EXP_DAYS', 3);
 * expires in X days from purchase, 0 never expires
 */
/**
 * Add meta box to posts to set price and levels
 */
function pmproap_post_meta() {
	global $membership_levels, $post, $wpdb, $pmpro_currency_symbol, $pmpro_page_levels;

	if ( empty( $pmpro_page_levels[ $post->ID ] ) ) {
		$pmpro_page_levels[ $post->ID ] = $wpdb->get_col( "SELECT membership_id FROM {$wpdb->pmpro_memberships_pages} WHERE page_id = '{$post->ID}'" );
	}

	$pmproap_price = get_post_meta( $post->ID, '_pmproap_price', true );
?>    
	<input type="hidden" name="pmproap_noncename" id="pmproap_noncename" value="<?php echo wp_create_nonce( plugin_basename( __FILE__ ) ); ?>" />
	<input type="hidden" name="quick_edit" value="true" />
	<?php if ( $pmproap_price && empty( $pmpro_page_levels[ $post->ID ] ) ) { ?>
		<p><strong class="pmpro_red"><?php _e( 'Warning: This page is not locked down yet.', 'pmproap' ); ?></strong> <?php _e( 'You must select at least one membership level in the sidebar to the right to restrict access to this page. You can create a free membership level for this purpose if you need to.', 'pmproap' ); ?></p>
	<?php } elseif ( $pmproap_price ) { ?>
		<p><strong class="pmpro_green"><?php _e( 'This page is restricted.', 'pmproap' ); ?></strong> <?php printf( __( 'Members will have to pay %s to gain access to this page. To open access to all members, delete the price below then Save/Update this post.', 'pmproap' ), pmpro_formatPrice( $pmproap_price ) ); ?></p>
	<?php } else { ?>
		<p><?php _e( 'To charge for access to this post and any subpages, set a price below then Save/Update this post. Only members of the levels set in the "Require Membership" sidebar will be able to purchase access to this post.', 'pmproap' ); ?></p>
	<?php } ?>

	<div>
		<label><strong><?php _e( 'Price', 'pmproap' ); ?></strong></label>
		&nbsp;&nbsp;&nbsp; <?php echo $pmpro_currency_symbol; ?><input type="text" id="pmproap_price" name="pmproap_price" value="<?php echo esc_attr( $pmproap_price ); ?>" />
	</div>
<?php
}

function pmproap_post_save( $post_id ) {
	global $wpdb;

	if ( empty( $post_id ) ) {
		return false;
	}

	if ( empty( $_POST['quick_edit'] ) || ( ! empty( $_POST['pmproap_noncename'] ) && ! wp_verify_nonce( $_POST['pmproap_noncename'], plugin_basename( __FILE__ ) ) ) ) {
		return $post_id;
	}

	/**
	 * Verify if this is an auto save routine. If it is our form has not been submitted, so we dont want to do anything
	 */
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return $post_id;
	}

	// Check permissions
	if ( ! current_user_can( 'manage_options' ) ) {
		return $post_id;
	}

	// OK, we're authenticated: we need to find and save the data
	if ( isset( $_POST['pmproap_price'] ) ) {
		$mydata = preg_replace( '[^0-9\.]', '', $_POST['pmproap_price'] );
	}

	update_post_meta( $post_id, '_pmproap_price', $mydata );

	return $mydata;
}
add_action( 'save_post', 'pmproap_post_save' );

/**
 * @since v0.7 - Supports Custom Post Type (CPT) posts as add-on package/posts
 */
function pmproap_post_meta_wrapper() {

	$post_types = apply_filters( 'pmproap_supported_post_types', array( 'page', 'post' ) );

	// get extra post types from PMPro CPT, if available
	if ( function_exists( 'pmprocpt_getCPTs' ) ) {
		$post_types = array_merge( $post_types, pmprocpt_getCPTs() );
	}

	foreach ( $post_types as $type ) {
		add_meta_box( 'pmproap_post_meta', __( 'PMPro Addon Package Settings', 'pmproap' ), 'pmproap_post_meta', $type, 'normal' );
	}
}
add_action( 'admin_menu', 'pmproap_post_meta_wrapper' );

/**
 * These functions are used to add a member to a post or check if a member has been added to a post.
 */
function pmproap_addMemberToPost( $user_id, $post_id ) {
	$user_posts = get_user_meta( $user_id, '_pmproap_posts', true );
	$post_users = get_post_meta( $post_id, '_pmproap_users', true );

	// add the post to the user
	if ( is_array( $user_posts ) ) {
		if ( ! in_array( $post_id, $user_posts ) ) {
			$user_posts[] = $post_id;
		}
	} else {
		$user_posts = array( $post_id );
	}

	// add the user to the post
	if ( is_array( $post_users ) ) {
		if ( ! in_array( $user_id, $post_users ) ) {
			$post_users[] = $user_id;
		}
	} else {
		$post_users = array( $user_id );
	}

	// save the meta
	update_user_meta( $user_id, '_pmproap_posts', $user_posts );
	update_post_meta( $post_id, '_pmproap_users', $post_users );

	// Trigger that user has been added.
	do_action( 'pmproap_action_add_to_package', $user_id, $post_id );
}

/**
 * Returns true if this post is locked to at least one membership level and has a pmproap_price.
 *
 * @param  int $post_id ID of the post to check if locked.
 * @return bool         Returns true if locked, false if not.
 */
function pmproap_isPostLocked( $post_id ) {
	global $wpdb, $pmpro_page_levels;

	$pmproap_price = get_post_meta( $post_id, '_pmproap_price', true );

	// has a price?
	if ( empty( $pmproap_price ) ) {
		return false;
	}

	// has a membership level
	if ( empty( $pmpro_page_levels[ $post_id ] ) ) {
		$pmpro_page_levels[ $post_id ] = $wpdb->get_col( "SELECT membership_id FROM {$wpdb->pmpro_memberships_pages} WHERE page_id = '{$post_id}'" );
	}

	if ( empty( $pmpro_page_levels[ $post_id ] ) ) {
		return false;
	}

	// must be locked down then
	return true;
}

/**
 * Returns true if a user has access to a page.
 *
 * @param  int $user_id  ID of the user to check.
 * @param  int $post_id  ID of the post to check.
 * @return bool          Returns true if the user has access to the post, false if not.
 */
function pmproap_hasAccess( $user_id, $post_id ) {
	// does this user have a level giving them access to everything?
	$all_access_levels = apply_filters( 'pmproap_all_access_levels', array(), $user_id, $post_id );
	if ( ! empty( $all_access_levels ) && pmpro_hasMembershipLevel( $all_access_levels, $user_id ) ) {
		return true;    // user has one of the all access levels
	}

	// check for expiration date
	if ( defined( 'PMPROAP_EXP_DAYS' ) && PMPROAP_EXP_DAYS ) {
		if ( strtotime( 'now' ) >= get_user_meta( $user_id, 'pmproap_post_id_' . $post_id . '_exp_date', true ) ) {
			pmproap_removeMemberFromPost( $user_id, $post_id );
			delete_user_meta( $user_id, 'pmproap_post_id_' . $post_id . '_exp_date' );
			return false;
		}
	}

	// check if the user has access to the post
	$post_users = get_post_meta( $post_id, '_pmproap_users', true );
	if ( is_array( $post_users ) && in_array( $user_id, $post_users ) ) {
		return true;
	} else {
		return false;        // unless everyone has access
	}
}
/**
 * [pmproap_removeMemberFromPost description]
 *
 * @param  int $user_id ID of user to remove from post.
 * @param  int $post_id ID of post to remove user from.
 */
function pmproap_removeMemberFromPost( $user_id, $post_id ) {
	$user_posts = get_user_meta( $user_id, '_pmproap_posts', true );
	$post_users = get_post_meta( $post_id, '_pmproap_users', true );

	// remove the post from the user
	if ( is_array( $user_posts ) ) {
		if ( ( $key = array_search( $post_id, $user_posts ) ) !== false ) {
			unset( $user_posts[ $key ] );
		}
	}

	// remove the user from the post
	if ( is_array( $post_users ) ) {
		if ( ( $key = array_search( $user_id, $post_users ) ) !== false ) {
			unset( $post_users[ $key ] );
		}
	}

	// save the meta
	update_user_meta( $user_id, '_pmproap_posts', $user_posts );
	update_post_meta( $post_id, '_pmproap_users', $post_users );

	// Trigger that user has been removed.
	do_action( 'pmproap_action_remove_from_package', $user_id, $post_id );
}

/**
 * Filter pmpro_has_membership_access based on addon access.
 */
function pmproap_pmpro_has_membership_access_filter( $hasaccess, $mypost, $myuser, $post_membership_levels ) {
	// If the user doesn't have access already, we won't change that. So only check if they already have access.
	if ( $hasaccess ) {
		// is this post locked down via an addon?
		if ( pmproap_isPostLocked( $mypost->ID ) ) {
			// okay check if the user has access
			if ( pmproap_hasAccess( $myuser->ID, $mypost->ID ) ) {
				$hasaccess = true;
			} else {
				$hasaccess = false;
			}
		}
	}

	return $hasaccess;
}

add_filter( 'pmpro_has_membership_access_filter', 'pmproap_pmpro_has_membership_access_filter', 10, 4 );

/**
 * Filter the message for users without access.
 */
function pmproap_pmpro_text_filter( $text ) {
	global $wpdb, $current_user, $post, $pmpro_currency_symbol;

	if ( ! empty( $post ) ) {
		if ( pmproap_isPostLocked( $post->ID ) && ! pmproap_hasAccess( $current_user->ID, $post->ID ) ) {
			// which level to use for checkout link?
			$text_level_id = pmproap_getLevelIDForCheckoutLink( $post->ID, $current_user->ID );

			if ( empty( $text_level_id ) ) {
				$text = '<p>' . __( 'You must first purchase a membership level before purchasing this content. ', 'pmproap' ) . '</p>';
				$text .= '<p><a href="' . pmpro_url( 'levels' ) . '">' . __( 'Click here to choose a membership level.', 'pmproap' ) . '</a></p>';
			} else {
				// what's the price
				$pmproap_price = get_post_meta( $post->ID, '_pmproap_price', true );

				// check for all access levels
				$all_access_levels = apply_filters( 'pmproap_all_access_levels', array(), $current_user->ID, $post->ID );

				// update text
				if ( ! empty( $all_access_levels ) ) {
					$level_names = array();
					foreach ( $all_access_levels as $level_id ) {
						$level = pmpro_getLevel( $level_id );
						$level_names[] = $level->name;
					}

					$text = '<p>' . sprintf( __( 'This content requires that you purchase additional access. The price is %1$s or free for our %2$s members.', 'pmproap' ), pmpro_formatPrice( $pmproap_price ), pmpro_implodeToEnglish( $level_names ) ) . '</p>';
					$text .= '<p><a href="' . pmpro_url( 'checkout', '?level=' . $text_level_id . '&ap=' . $post->ID ) . '">' . sprintf( __( 'Purchase this Content (%s)', 'pmproap' ), pmpro_formatPrice( $pmproap_price ) ) . '</a> <a href="' . pmpro_url( 'levels' ) . '">' . __( 'Choose a Membership Level', 'pmproap' ) . '</a></p>';
				} else {
					$text = '<p>' . sprintf( __( 'This content requires that you purchase additional access. The price is %s.', 'pmproap' ), pmpro_formatPrice( $pmproap_price ) ) . '</p>';
					$text .= '<p><a href="' . pmpro_url( 'checkout', '?level=' . $text_level_id . '&ap=' . $post->ID ) . '">' . __( 'Click here to checkout', 'pmproap' ) . '</a></p>';
				}
			}
		}
	}

	return $text;
}

add_filter( 'pmpro_non_member_text_filter', 'pmproap_pmpro_text_filter' );
add_filter( 'pmpro_not_logged_in_text_filter', 'pmproap_pmpro_text_filter' );

/**
 * Figure out which PMPro level ID to use for the checkout link for an addon page.
 */
function pmproap_getLevelIDForCheckoutLink( $post_id = null, $user_id = null ) {
	global $current_user, $post;

	// default to current user
	if ( empty( $user_id ) && ! empty( $current_user ) && ! empty( $current_user->ID ) ) {
		$user_id = $current_user->ID;
	}

	// default to current post
	if ( empty( $post_id ) && ! empty( $post ) && ! empty( $post->ID ) ) {
		$post_id = $post->ID;
	}

	// no post, we bail
	if ( empty( $post_id ) ) {
		return false;
	}

	// no user id? make sure it's null
	if ( empty( $user_id ) ) {
		$user_id = null;
	}

	// use current level or offer a free level checkout
	$has_access = pmpro_has_membership_access( $post_id, $user_id, true );
	$post_levels = $has_access[1];

	// make sure membership_level obj is populated
	if ( is_user_logged_in() ) {
		$current_user->membership_level = pmpro_getMembershipLevelForUser( $current_user->ID );
	}

	$text_level_id = null;
	if ( ! empty( $current_user->membership_level ) && in_array( $current_user->membership_level->ID, $post_levels ) ) {
		$text_level_id = $current_user->membership_level->id;
	} elseif ( ! empty( $post_levels ) ) {
		// find a free level to checkout with
		foreach ( $post_levels as $post_level_id ) {
			$post_level = pmpro_getLevel( $post_level_id );
			if ( pmpro_isLevelFree( $post_level ) ) {
				$text_level_id = $post_level->id;
				break;
			}
		}
	}

	/**
	 * Filter the text_level_id
	 *
	 * @since .7.4
	 * @var  int   $text_level_id ID of level to default to when checking out for an addon package.
	 * @var  int   $post_id       ID of the post for the addon package.
	 * @var  int   $user_id       ID of the user checking out.
	 * @var  array $post_levels   Array of level ids with access to this post.
	 */
	$text_level_id = apply_filters( 'pmproap_text_level_id', $text_level_id, $post_id, $user_id, $post_levels );

	// didn't find a level id to use yet? return false and user's will be linked to the levels page.
	if ( empty( $text_level_id ) ) {
		$text_level_id = false;
	}

	return $text_level_id;
}

/**
 * Add ap to PayPal Express return url parameters
 */
function pmproap_pmpro_paypal_express_return_url_parameters( $params ) {
	if ( ! empty( $_REQUEST['ap'] ) ) {
		$params['ap'] = isset( $_REQUEST['ap'] ) ? intval( $_REQUEST['ap'] ) : null;
	}
	return $params;
}

add_filter( 'pmpro_paypal_express_return_url_parameters', 'pmproap_pmpro_paypal_express_return_url_parameters' );

/**
 * Give the user access to the page after PayPal Standard Order Success
 */
if ( ! function_exists( 'pmproap_pmpro_updated_order_paypal' ) ) {
	function pmproap_pmpro_updated_order_paypal( $order ) {

		if ( ( $order->status == 'success' ) && ( $order->gateway == 'paypalstandard' ) && ( strpos( $order->notes, 'Addon Package:' ) !== false ) ) {

			preg_match( '/Addon Package:(.*)\(#(\d+)\)/', $order->notes, $matches );
			$pmproap_ap = $matches[2];
			if ( ! empty( $pmproap_ap ) ) {
				pmproap_addMemberToPost( $order->user_id, $pmproap_ap );
			}
		}
	}
}
add_action( 'pmpro_added_order', 'pmproap_pmpro_updated_order_paypal' );
add_action( 'pmpro_updated_order', 'pmproap_pmpro_updated_order_paypal' );

/**
 * Tweak the checkout page when ap is passed in.
 * update the level cost
 */
function pmproap_pmpro_checkout_level( $level ) {
	global $current_user;

	if ( ! isset( $level->id ) ) {
		return $level;
	}

	// are we purchasing a post?
	if ( isset( $_REQUEST['ap'] ) && ! empty( $_REQUEST['ap'] ) ) {
		$ap = intval( $_REQUEST['ap'] );
		$ap_post = get_post( $ap );
		$pmproap_price = get_post_meta( $ap, '_pmproap_price', true );

		if ( ! empty( $pmproap_price ) ) {
			if ( pmpro_hasMembershipLevel( $level->id ) ) {
				// already have the membership, so price is just the ap price
				$level->initial_payment = $pmproap_price;

				// zero the rest out
				$level->billing_amount = 0;
				$level->cycle_number = 0;
				$level->trial_amount = 0;
				$level->trial_limit = 0;

				// unset expiration period and number
				$level->expiration_period = null;
				$level->expiration_number = null;

				// don't unsubscribe to the old level after checkout
				if ( ! function_exists( 'pmproap_pmpro_cancel_previous_subscriptions' ) ) {
					function pmproap_pmpro_cancel_previous_subscriptions( $cancel ) {
						return false;
					}
				}
				add_filter( 'pmpro_cancel_previous_subscriptions', 'pmproap_pmpro_cancel_previous_subscriptions' );

				// keep current enddate
				if ( ! function_exists( 'pmproap_pmpro_checkout_end_date' ) ) {
					function pmproap_pmpro_checkout_end_date( $enddate, $user_id, $pmpro_level, $startdate ) {
						$user_level = pmpro_getMembershipLevelForUser( $user_id );
						if ( ! empty( $user_level ) && ! empty( $user_level->enddate ) && $user->enddate != '0000-00-00 00:00:00' ) {
							return date_i18n( 'Y-m-d H:i:s', $user_level->enddate );
						} else {
							return $enddate;
						}
					}
				}
				add_filter( 'pmpro_checkout_end_date', 'pmproap_pmpro_checkout_end_date', 10, 4 );

			} else {
				// add the ap price to the membership
				$level->initial_payment = $level->initial_payment + $pmproap_price;
			}

			// update the name
			if ( pmpro_hasMembershipLevel( $level->id ) ) {
				$level->name = $ap_post->post_title;
			} else {
				$level->name .= sprintf( __( ' + access to %s', 'pmproap' ), $ap_post->post_title );
			}

			// don't show the discount code field
			if ( ! function_exists( 'pmproap_pmpro_show_discount_code' ) ) {
				function pmproap_pmpro_show_discount_code( $show ) {
					return false;
				}
			}
			add_filter( 'pmpro_show_discount_code', 'pmproap_pmpro_show_discount_code' );

			// add hidden input to carry ap value
			if ( ! function_exists( 'pmproap_pmpro_checkout_boxes' ) ) {
				function pmproap_pmpro_checkout_boxes() {
					if ( ! empty( $_REQUEST['ap'] ) ) {
						?>
						<input type="hidden" name="ap" value="<?php echo esc_attr( $_REQUEST['ap'] ); ?>"/>
						<?php
					}
				}
			}
			add_action( 'pmpro_checkout_boxes', 'pmproap_pmpro_checkout_boxes' );

			// give the user access to the page after checkout
			if ( ! function_exists( 'pmproap_pmpro_after_checkout' ) ) {
				function pmproap_pmpro_after_checkout( $user_id ) {
					global $pmproap_ap;
					if ( ! empty( $_SESSION['ap'] ) ) {
						$pmproap_ap = intval( $_SESSION['ap'] );
						unsset( $_SESSION['ap'] );
					} elseif ( ! empty( $_REQUEST['ap'] ) ) {
						$pmproap_ap = intval( $_REQUEST['ap'] );
					}

					if ( ! empty( $pmproap_ap ) ) {
						pmproap_addMemberToPost( $user_id, $pmproap_ap );

						// update the confirmation url
						if ( ! function_exists( 'pmproap_pmpro_confirmation_url' ) ) {
							function pmproap_pmpro_confirmation_url( $url, $user_id, $level ) {
								global $pmproap_ap;
								$url = add_query_arg( 'ap', $pmproap_ap, $url );

								return $url;
							}
						}
						add_filter( 'pmpro_confirmation_url', 'pmproap_pmpro_confirmation_url', 10, 3 );
					}
				}
			}
			add_action( 'pmpro_after_checkout', 'pmproap_pmpro_after_checkout' );
		} else {
			// woah, they passed a post id that isn't locked down
		}
	}

	return $level;
}

add_filter( 'pmpro_checkout_level', 'pmproap_pmpro_checkout_level' );

/**
 * Remove level description if checking out for level you already have.
 *
 * @param  object $level Level object to be filtered.
 * @return object $level The filtered level object.
 */
function pmproap_pmpro_checkout_level_have_it( $level ) {
	global $pmpro_pages;
	// only checkout page, with ap passed in, and have the level checking out for
	if ( is_page( $pmpro_pages['checkout'] ) &&
		! empty( $_REQUEST['ap'] ) &&
		pmpro_hasMembershipLevel( $level->id )
	) {
		$level->description = '';
	}

	return $level;
}

add_filter( 'pmpro_checkout_level', 'pmproap_pmpro_checkout_level_have_it' );

/**
 * Remove "membership level" from name if the user already has a level.
 *
 * @param  string $translated_text Translated text.
 * @param  string $text            Original text.
 * @param  string $domain          Text domain.
 * @return string                  Filtered text.
 */
function pmproap_gettext_you_have_selected( $translated_text, $text, $domain ) {
	global $pmpro_pages;
	// only checkout page, with ap passed in, and "you have selected..." string, and have the level checking out for
	if ( ! empty( $pmpro_pages ) && is_page( $pmpro_pages['checkout'] ) &&
		! empty( $_REQUEST['ap'] ) &&
		$domain == 'paid-memberships-pro' &&
		strpos( $text, 'have selected' ) !== false &&
		pmpro_hasMembershipLevel( intval( $_REQUEST['level'] ) ) ) {
		$translated_text = str_replace( __( ' membership level', 'pmproap' ), '', $translated_text );
		$translated_text = str_replace( __( 'You have selected the', 'pmproap' ), __( 'You are purchasing additional access to:', 'pmproap' ), $translated_text );
	}
	return $translated_text;
}

add_filter( 'gettext', 'pmproap_gettext_you_have_selected', 10, 3 );

/**
 * Remove "for membership" from cost text
 *
 * @param  string $text  Level cost text.
 * @param  object $level Level object to build text from.
 * @return string        Filtered text.
 */
function pmproap_pmpro_level_cost_text( $text, $level ) {
	global $pmpro_pages;
	// only checkout page, with ap passed in, and have the level checking out for
	if ( is_page( $pmpro_pages['checkout'] ) &&
		! empty( $_REQUEST['ap'] ) &&
		pmpro_hasMembershipLevel( $level->id ) ) {
		$text = str_replace( __( 'The price for membership', 'pmproap' ), __( 'The price', 'pmproap' ), $text );
		$text = str_replace( __( ' now', 'pmproap' ), '', $text );
	}

	return $text;
}

add_filter( 'pmpro_level_cost_text', 'pmproap_pmpro_level_cost_text', 10, 2 );

/**
 * Add info on addon to notes section of order.
 */
function pmproap_pmpro_added_order( $order ) {
	global $pmpro_pages;

	if ( is_page( $pmpro_pages['checkout'] ) && ! empty( $_REQUEST['ap'] ) ) {
		global $wpdb;
		$post = get_post( intval( $_REQUEST['ap'] ) );
		$order->notes .= 'Addon Package: ' . $post->post_title . ' (#' . $post->ID . ")\n";
		$sqlQuery = $wpdb->prepare(
			"UPDATE {$wpdb->pmpro_membership_orders} SET notes = %s WHERE id = %d LIMIT 1",
			$order->notes,
			$order->id
		);
		$wpdb->query( $sqlQuery );
	}

	return $order;
}

add_filter( 'pmpro_added_order', 'pmproap_pmpro_added_order' );

/**
 * Insert Addon Package column to Orders CSV export.
 *
 * @since 0.6
 */
function pmproap_pmpro_orders_csv_extra_columns( $columns ) {
	$columns['addon_package'] = 'pmproap_csv_addon_package';
	return $columns;
}
add_filter( 'pmpro_orders_csv_extra_columns', 'pmproap_pmpro_orders_csv_extra_columns' );

/**
 * Update the confirmation page to have a link to the purchased page.
 */
function pmproap_pmpro_confirmation_message( $message ) {
	if ( ! empty( $_REQUEST['ap'] ) ) {
		$ap = $_REQUEST['ap'];
		$ap_post = get_post( $ap );

		$message .= '<p class="pmproap_confirmation">' . sprintf( __( 'Continue on to %s.', 'pmproap' ), '<a href="' . get_permalink( $ap_post->ID ) . '">' . $ap_post->post_title . '</a>' ) . '</p>';
	}
	return $message;
}

add_filter( 'pmpro_confirmation_message', 'pmproap_pmpro_confirmation_message' );

/**
 * Show purchased posts on the account page
 */
function pmproap_pmpro_member_links_top( $invoice = NULL) {
	if( !empty( $invoice ) ) {
		$user_id = $invoice->user_id;
	}

	if( empty($user_id ) ) {
		global $current_user;
		$user_id = $current_user->ID;	
	}
	
	$post_ids = get_user_meta( $user_id, '_pmproap_posts', true );
	if ( is_array( $post_ids ) ) {
		foreach ( $post_ids as $post_id ) {
			$apost = get_post( $post_id );
			?>
			<li><a href="<?php echo get_permalink( $post_id ); ?>"><?php echo $apost->post_title; ?></a></li>
			<?php
		}
	}
}

add_action( 'pmpro_member_links_top', 'pmproap_pmpro_member_links_top' );
add_action( 'pmpro_invoice_bullets_top', 'pmproap_pmpro_member_links_top' );

/**
 * Show the purchased pages for each user on the edit user/profile  page of the admin
 */
function pmproap_profile_fields( $user_id ) {
	if ( is_object( $user_id ) ) {
		$user_id = $user_id->ID;
	}

	if ( ! current_user_can( 'administrator' ) ) {
		return false;
	}
?>
<h3><?php _e( 'Purchased Addon Packages', 'pmproap' ); ?></h3>
<table class="form-table">
	<?php
		$user_posts = get_user_meta( $user_id, '_pmproap_posts', true );
	if ( ! empty( $user_posts ) ) {
		foreach ( $user_posts as $upost_id ) {
			?>
			<tr>
			<th></th>
			<td>
				<?php
					$upost = get_post( $upost_id );
					?>
						<span id="pmproap_remove_span_<?php echo $upost->ID; ?>">
						<a target="_blank" href="<?php echo esc_attr( get_permalink( $upost->ID ) ); ?>"><?php echo $upost->post_title; ?></a>
						&nbsp; <a style="color: red;" id="pmproap_remove_<?php echo $upost->ID; ?>" class="pmproap_remove" href="javascript:void(0);"><?php _e( 'remove', 'pmproap' ); ?></a>
						</span>
										</td>
			</tr>
			<?php
		}
	}
	?>
	<tr>
		<th><?php _e( 'Give this User a Package', 'pmproap' ); ?></th>
		<td>
			<input type="text" id="new_pmproap_posts_1" name="new_pmproap_posts[]" size="10" value="" /> <small><?php _e( 'Enter a post/page ID', 'pmproap' ); ?></small>
		</td>
	</tr>
	<tr id="pmproap_add_tr">
		<th></th>
		<td>
			<a id="pmproap_add" href="javascript:void(0);"><?php _e( '+ Add Another', 'pmproap' ); ?></a>
		</td>
	</tr>
</table>
<input type="hidden" id="remove_pmproap_posts" name="remove_pmproap_posts" value="" />
<script>
	var npmproap_adds = 1;
	jQuery(function() {
		//too add another text input for a new package
		jQuery('#pmproap_add').click(function() {
			npmproap_adds++;
			jQuery('#pmproap_add_tr').before('<tr><th></th><td><input type="text" id="new_pmproap_posts_' + npmproap_adds + '" name="new_pmproap_posts[]" size="10" value="" /> <small><?php _e( 'Enter a post/page ID', 'pmproap' ); ?></small></td></tr>');
		});

			//removing a package
			jQuery('.pmproap_remove').click(function () {
				var thispost = jQuery(this);
				var thisid = thispost.attr('id').replace('pmproap_remove_', '');

				//strike through the post
				jQuery('#pmproap_remove_span_' + thisid).css('text-decoration', 'line-through');

				//add id to remove list
				jQuery('#remove_pmproap_posts').val(jQuery('#remove_pmproap_posts').val() + thisid + ',');
			});
		});
	</script>
	<?php
}
/**
 * Add or remove user from packages when updating via the edit user page in the dashboard.
 */
function pmproap_profile_fields_update() {
	if ( isset( $_REQUEST['new_pmproap_posts'] ) || isset( $_REQUEST['remove_pmproap_posts'] ) ) {
		// get the user id
		global $wpdb, $current_user, $user_ID;
		wp_get_current_user();

		if ( ! empty( $_REQUEST['user_id'] ) ) {
			$user_ID = intval( $_REQUEST['user_id'] );
		}

		if ( ! current_user_can( 'edit_user', $user_ID ) ) {
			return false;
		}

		// adding
		if ( is_array( $_REQUEST['new_pmproap_posts'] ) ) {
			foreach ( $_REQUEST['new_pmproap_posts'] as $post_id ) {
				$post_id = intval( $post_id );
				if ( ! empty( $post_id ) ) {
					pmproap_addMemberToPost( $user_ID, $post_id );
				}
			}
		}

		// remove
		if ( ! empty( $_REQUEST['remove_pmproap_posts'] ) ) {
			// convert to array
			$post_ids = explode( ',', $_REQUEST['remove_pmproap_posts'] );
			foreach ( $post_ids as $post_id ) {
				$post_id = intval( $post_id );
				if ( ! empty( $post_id ) ) {
					pmproap_removeMemberFromPost( $user_ID, $post_id );
				}
			}
		}
	}
}

add_action( 'show_user_profile', 'pmproap_profile_fields' );
add_action( 'edit_user_profile', 'pmproap_profile_fields' );
add_action( 'profile_update', 'pmproap_profile_fields_update' );

/**
 * Add expiration date to user meta when a user is added to a package.
 *
 * @param  int $user_id ID of user being added to a package.
 * @param  int $post_id ID of the post/package the user is being added to.
 */
function pmproap_add_exp_date( $user_id, $post_id ) {
	if ( defined( 'PMPROAP_EXP_DAYS' ) && PMPROAP_EXP_DAYS > 0 ) {
		$expdate = strtotime( '+' . PMPROAP_EXP_DAYS . ' days' );
		update_user_meta( $user_id, 'pmproap_post_id_' . $post_id . '_exp_date', $expdate );
	}
}

add_action( 'pmproap_action_add_to_package', 'pmproap_add_exp_date', 10, 2 );
/**
 * Remove expiration date in user meta when a user is removed from a package.
 *
 * @param  int $user_id ID of user being removed from a package.
 * @param  int $post_id ID of the post/package the user is being removed from.
 */
function pmproap_remove_exp_date( $user_id, $post_id ) {
	if ( get_user_meta( $user_id, 'pmproap_post_id_' . $post_id . '_exp_date' ) ) {
		delete_user_meta( $user_id, 'pmproap_post_id_' . $post_id . '_exp_date' );
	}
}

add_action( 'pmproap_action_remove_from_package', 'pmproap_remove_exp_date', 10, 2 );

/**
 * Function to print Addon Package to Orders CSV export.
 *
 * @param $order MemberOrder.
 *
 * @since 0.5.2
 */
function pmproap_csv_addon_package( $order ) {
	$ap = preg_match( '/Addon Package: (.*\))/', $order->notes, $matches );
	if ( ! empty( $ap ) ) {
		return $matches[1];
	} else {
		return '';
	}
}

/**
 * Function to add links to the plugin row meta.
 */
function pmproap_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-addon-packages.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'http://www.paidmembershipspro.com/add-ons/plus-add-ons/pmpro-purchase-access-to-a-single-page/' ) . '" title="' . esc_attr( __( 'View Documentation', 'pmproap' ) ) . '">' . __( 'Docs', 'pmproap' ) . '</a>',
			'<a href="' . esc_url( 'http://paidmembershipspro.com/support/' ) . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmproap' ) ) . '">' . __( 'Support', 'pmproap' ) . '</a>',
		);
		$links = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmproap_plugin_row_meta', 10, 2 );
