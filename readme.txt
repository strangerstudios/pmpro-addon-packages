=== Paid Memberships Pro - Addon Packages ===
Contributors: strangerstudios
Tags: pmpro, paid memberships pro, ecommerce
Requires at least: 3.6
Tested up to: 4.0
Stable tag: .4.5

Allow PMPro members to purchase access to specific pages. This plugin is meant to be a temporary solution until support for multiple membership levels is added to PMPro.

== Description ==
You must have the latest version of Paid Memberships Pro installed (currently 1.8.5).

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

== Installation ==

1. Upload the `pmpro-addon-packages` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Edit a post or page to set a price for it.

== Using the Addon Packages Shortcode ==
The [pmpro_addon_packages] shortcode allows you to display a "shop" like page of available addon packages (all pages and posts with a defined pmproap_price). Add the shortcode to a page with your desired attributes. 

Shortcode attributes include:
* checkout_button: The text displayed on the button linking to checkout. (default: "Buy Now").
* exclude: A comma-separated list of the page IDs to exclude from display (default: none).
* include: Optionally set this attribute to only show subpages of the active page. Accepts: "subpages". (default: shows all pages and posts with an addon package price).
* layout: The layout of the output. (default: table). Accepts "div", "table", "2col", "3col", "4col" (column-type layouts will work with the Memberlite Theme or any theme based on the Foundation 5 grid system).
* link: Hyperlink the post/page title to the single view; accepts “true” or “false” (default: true).
* orderby: Accepts any orderby parameter as defined in the codex. (default: menu_order).
* order: Accepts ASC or DESC as defined in the codex. (default: ASC).
* thumbnail: Optionally hide or show the subpage’s featured image; accepts “thumbnail”, “medium”, “large” or “false”. (default: thumbnail).
* view_button: The text displayed on the button linking to view the single page. (default: "View Now").

If the user has already purchased the addon package, they will see a link to "View". If the user has a valid membership for the addon package, they can purchase without modifying their membership level. If the current user does not have a membership level or is not logged in, they can click the "buy" button to purchase membership and the addon package in one step.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-addon-packages/issues

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at http://www.paidmembershipspro.com for more documentation and our support forums.

Please Note: This plugin is meant as a temporary solution. Most updates and fixes will be reserved for when this functionality is built into Paid Memberships Pro. We may not fix the pmpro-addon-packages plugin itself unless it is critical.

== Changelog ==
= .5 =
* FEATURE: Added [pmpro_addon_packages] shortcode which can be used to show a list of all pages marked as addon packages. See the description section above for instructions on how to use.

= .4.5 =
* If no free level is available for an addon package, the checkout link will now default to the first required level when non-members view the page.

= .4.4 =
* Adding an order note like "Addon Package: Sample Page (#2)" when checking out for an addon package.

= .4.3 =
* Changes to pmproap_pmpro_text_filter() function to avoid warnings and also show a special message when all access levels are set. (Thanks, Merwan.)

= .4.2 =
* Added optional "expires in X days" global
* Fixed bug in pmpro_addon_packages shortcode.
* Supporting non-default wp-content directories. (Thanks, Adam-Moss on GitHub)

= .4.1 =
* Added pmproap_action_add_to_package and pmproap_action_remove_from_package hooks. (Thanks, DanHarrison)

= .4 =
* Added shortcode to show addon packages.
* Fixed confirmation URL to properly add the ap parameter. The "continue on to ..." link now appears in the membership confirmation. (Thanks, jons7)

= .3 =
* Updating text a bit for cases where you are checking out for a level you already have to purchase a package.

= .2 =
* Wrapped some functions in if(!function_exists("...")) for PMPro 1.7.5 support.

= .1.3 =
* The pmproap_profile_fields function can now take either a user_id or user object. This will fix some bugs.
* Using the pmpro_paypal_express_return_url_parameters filter to add the 'ap' param to the PayPal Express return URL to make this plugin more compatible with PayPal Express.

= .1.2 =
* Added the pmproap_all_access_levels filter to allow developers to designate certain levels as "all access levels", which will have access to all addon packages without being charged. Some code to use this: https://gist.github.com/3845777
* Added purchased packages to the edit user/profile page with the ability to add/remove packages.

= .1.1 =
* Changed what text is shown when a page is locked.
* If you don't have a required level, when viewing a locked page, the checkout link will choose a free membership level to switch to if one is available.

= .1 =
* Initial release.
