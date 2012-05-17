<?php
/*
Plugin Name: PMPro Addon Packages
Plugin URI: http://www.paidmembershipspro.com/pmpro-addon-packages/
Description: Allow PMPro members to purchase access to specific pages. This plugin is meant to be a temporary solution until support for multiple membership levels is added to PMPro.
Version: .1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	Story
	* Admin designates a post as an "addon package".
	* Sets a price for access to the post. (pmproap_price)
	* Selects which membership levels can purchase the package. (pmproap_levels)
	* For users without access, the page will show a link to purchase at the bottom of the page.
	* Purchase goes to either a new checkout page or the PMPro checkout page with some parameters passed in.
	* After checking out, they are taken to a new confirmation page or the PMPro confirmation page with extra info.
	* After purchasing, the user ID is added to post meta (pmproap_users). The post ID is also added to user meta (pmproap_posts).
	
	Limitations
	* Only one time charges.
	* No tax.
	* No discount codes.
*/

/*
	add meta box to posts to set price and levels
*/
function pmproap_post_meta()
{
	global $membership_levels, $post, $wpdb, $pmpro_currency_symbol, $pmpro_page_levels;
	
	if(empty($pmpro_page_levels[$post->ID]))
		$pmpro_page_levels[$post->ID] = $wpdb->get_col("SELECT membership_id FROM {$wpdb->pmpro_memberships_pages} WHERE page_id = '{$post->ID}'");
	
	$pmproap_price = get_post_meta($post->ID, "_pmproap_price", true);
?>    
    <input type="hidden" name="pmproap_noncename" id="pmproap_noncename" value="<?php echo wp_create_nonce( plugin_basename(__FILE__) )?>" />
	
	<?php if($pmproap_price && empty($pmpro_page_levels[$post->ID])) { ?>
		<p><strong class="pmpro_red">Warning: This page is not locked down yet.</strong> You must select at least one membership level in the sidebar to the right to restrict access to this page. You can create a free membership level for this purpose if you need to.</p>
	<?php } elseif($pmproap_price) { ?>
		<p><strong class="pmpro_green">This page is restricted.</strong> Members will have to pay <?php echo $pmpro_currency_symbol . $pmproap_price; ?> to gain access to this page. To open access to all members, delete the price below then Save/Update this post.</p>
	<?php } else { ?>
		<p>To charge for access to this post and any subpages, set a price below then Save/Update this post. Only members of the levels set in the "Require Membership" sidebar will be able to purchase access to this post.</p>		
	<?php } ?>
	
	<div>
		<label><strong>Price</strong></label>
		&nbsp;&nbsp;&nbsp; <?php echo $pmpro_currency_symbol; ?><input type="text" id="pmproap_price" name="pmproap_price" value="<?php echo esc_attr($pmproap_price); ?>" />
	</div>
<?php
}

function pmproap_post_save($post_id)
{
	global $wpdb;

	if(empty($post_id))
		return false;
	
	if (!empty($_POST['pmproap_noncename']) && !wp_verify_nonce( $_POST['pmproap_noncename'], plugin_basename(__FILE__) )) {
		return $post_id;
	}

	// verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
	// to do anything
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		return $post_id;

	// Check permissions
	if ( !current_user_can( 'manage_options') )
		return $post_id;	

	// OK, we're authenticated: we need to find and save the data	
	if(!empty($_POST['pmproap_price']))
		$mydata = preg_replace("[^0-9\.]", "", $_POST['pmproap_price']);
	else
		$mydata = "";	

	update_post_meta($post_id, "_pmproap_price", $mydata);

	return $mydata;
}

function pmproap_post_meta_wrapper()
{
	add_meta_box('pmproap_post_meta', 'PMPro Addon Package Settings', 'pmproap_post_meta', 'page', 'normal');
	add_meta_box('pmproap_post_meta', 'PMPro Addon Package Settings', 'pmproap_post_meta', 'post', 'normal');
}
if (is_admin())
{
	add_action('admin_menu', 'pmproap_post_meta_wrapper');
	add_action('save_post', 'pmproap_post_save');

	require_once(ABSPATH . "wp-content/plugins/paid-memberships-pro/adminpages/dashboard.php");
}

/*
	These functions are used to add a member to a post or check if a member has been added to a post.
*/
function pmproap_addMemberToPost($user_id, $post_id)
{
	$user_posts = get_user_meta($user_id, "_pmproap_posts", true);
	$post_users = get_post_meta($post_id, "_pmproap_users", true);
		
	//add the post to the user
	if(is_array($user_posts))
	{
		if(!in_array($post_id, $user_posts))
		{
			$user_posts[] = $post_id;
		}
	}
	else
	{
		$user_posts = array($post_id);
	}	
	
	//add the user to the post
	if(is_array($post_users))
	{
		if(!in_array($user_id, $post_users))
		{
			$post_users[] = $user_id;
		}
	}
	else
	{
		$post_users = array($user_id);
	}	
		
	//save the meta
	update_user_meta($user_id, "_pmproap_posts", $user_posts);
	update_post_meta($post_id, "_pmproap_users", $post_users);
}

//returns true if this post is locked to at least one membership level and has a pmproap_price
function pmproap_isPostLocked($post_id)
{
	global $wpdb, $pmpro_page_levels;
	
	$pmproap_price = get_post_meta($post_id, "_pmproap_price", true);
	
	//has a price?
	if(empty($pmproap_price))
		return false;
		
	//has a membership level
	if(empty($pmpro_page_levels[$post_id]))
		$pmpro_page_levels[$post_id] = $wpdb->get_col("SELECT membership_id FROM {$wpdb->pmpro_memberships_pages} WHERE page_id = '{$post_id}'");	
	
	if(empty($pmpro_page_levels[$post_id]))
		return false;
		
	//must be locked down then
	return true;
}

//returns true if a user has access to a page
function pmproap_hasAccess($user_id, $post_id)
{
	$post_users = get_post_meta($post_id, "_pmproap_users", true);
	if(is_array($post_users) && in_array($user_id, $post_users))
		return true;
	else
		return false;		//unless everyone has access
}

/*
	Filter pmpro_has_membership_access based on addon access.
*/
function pmproap_pmpro_has_membership_access_filter($hasaccess, $mypost, $myuser, $post_membership_levels)
{
	//If the user doesn't have access already, we won't change that. So only check if they already have access.
	if($hasaccess)
	{	
		//is this post locked down via an addon?
		if(pmproap_isPostLocked($mypost->ID))
		{
			//okay check if the user has access
			if(pmproap_hasAccess($myuser->ID, $mypost->ID))
				$hasaccess = true;
			else
				$hasaccess = false;
		}
	}
	
	return $hasaccess;
}
add_filter("pmpro_has_membership_access_filter", "pmproap_pmpro_has_membership_access_filter", 10, 4);

/*
	Filter the message for users without access.
*/
function pmproap_pmpro_text_filter($text)
{
	global $current_user, $post;
	
	if(!empty($current_user) && !empty($post))
	{
		if(pmproap_isPostLocked($post->ID) && !pmproap_hasAccess($current_user->ID, $post->ID))
		{
			$pmproap_price = get_post_meta($post->ID, "_pmproap_price", true);
			global $pmpro_currency_symbol;
			
			$text .= " This content requires that you purchase additional access. The price is " . $pmpro_currency_symbol . $pmproap_price . ".";
			
			if(pmpro_hasMembershipLevel())
				$text .= "<a href=\"" . pmpro_url("checkout", "?level=" . $current_user->membership_level->id . "&ap=" . $post->ID) . "\">Click here to checkout</a>.";
		}
	}
	
	return $text;
}
add_filter("pmpro_non_member_text_filter", "pmproap_pmpro_text_filter");
add_filter("pmpro_not_logged_in_text_filter", "pmproap_pmpro_text_filter");

/*
	Tweak the checkout page when ap is passed in.
*/
//update the level cost
function pmproap_pmpro_checkout_level($level)
{	
	//are we purchasing a post?
	if(!empty($_REQUEST['ap']))
	{
		$ap = intval($_REQUEST['ap']);
		$ap_post = get_post($ap);
		$pmproap_price = get_post_meta($ap, "_pmproap_price", true);
		if(!empty($pmproap_price))
		{
			if(pmpro_hasMembershipLevel($level->id))
			{
				//already have the membership, so price is just the ap price
				$level->initial_payment = $pmproap_price;
				
				//zero the rest out
				$level->billing_amount = 0;
				$level->cycle_number = 0;
				$level->trial_amount = 0;
				$level->trial_limit = 0;
				
				//don't unsubscribe to the old level after checkout
				function pmproap_pmpro_cancel_previous_subscriptions($cancel)
				{
					return false;
				}
				add_filter("pmpro_cancel_previous_subscriptions", "pmproap_pmpro_cancel_previous_subscriptions");
			}
			else
			{
				//add the ap price to the membership
				$level->initial_payment = $level->initial_payment + $pmproap_price;
			}
			
			//update the name
			$level->name .= " + access to " . $ap_post->post_title;
			
			//don't show the discount code field
			function pmproap_pmpro_show_discount_code($show)
			{
				return false;
			}			
			add_filter("pmpro_show_discount_code", "pmproap_pmpro_show_discount_code");
			
			//add hidden input to carry ap value
			function pmproap_pmpro_checkout_boxes()
			{
				if(!empty($_REQUEST['ap']))
				{
				?>
					<input type="hidden" name="ap" value="<?php echo esc_attr($_REQUEST['ap']); ?>" />
				<?php
				}
			}
			add_action("pmpro_checkout_boxes", "pmproap_pmpro_checkout_boxes");
			
			//give the user access to the page after checkout
			function pmproap_pmpro_after_checkout($user_id)
			{
				global $pmproap_ap;
				if(!empty($_SESSION['ap']))
				{
					$pmproap_ap = $_SESSION['ap'];
					unsset($_SESSION['ap']);
				}
				elseif(!empty($_REQUEST['ap']))
				{
					$pmproap_ap = $_REQUEST['ap'];
				}
				
				if(!empty($pmproap_ap))
				{					
					pmproap_addMemberToPost($user_id, $pmproap_ap);
					
					//update the confirmation url
					function pmproap_pmpro_confirmation_url($url, $user_id, $level)
					{
						global $pmproap_ap;
						$url .= "?ap=" . $pmproap_ap;
					
						return $url;
					}
					add_filter("pmpro_confirmation_url", "pmproap_pmpro_confirmation_url", 10, 3);
				}
			}
			add_action("pmpro_after_checkout", "pmproap_pmpro_after_checkout");
		}
		else
		{
			//woah, they passed a post id that isn't locked down			
		}
	}

	return $level;
}
add_filter("pmpro_checkout_level", "pmproap_pmpro_checkout_level");

/*
	Update the confirmation page to have a link to the purchased page.
*/
function pmproap_pmpro_confirmation_message($message)
{
	if(!empty($_REQUEST['ap']))
	{
		$ap = $_REQUEST['ap'];
		$ap_post = get_post($ap);
		
		$message .= "<p class=\"pmproap_confirmation\">Continue on to <a href=\"" . get_permalink($ap_post->ID) . "\">" . $ap_post->post_title . "</a>.</p>";
	}
	return $message;
}
add_filter("pmpro_confirmation_message", "pmproap_pmpro_confirmation_message");

/*
	Show purchased posts on the account page
*/
function pmproap_pmpro_member_links_top()
{
	global $current_user;
	$post_ids = get_user_meta($current_user->ID, "_pmproap_posts", true);
	if(is_array($post_ids))
	{
		foreach($post_ids as $post_id)
		{
			$apost = get_post($post_id);
		?>
			<li><a href="<?php echo get_permalink($post_id); ?>"><?php echo $apost->post_title; ?></a></li>
		<?php
		}
	}
}
add_action("pmpro_member_links_top", "pmproap_pmpro_member_links_top");