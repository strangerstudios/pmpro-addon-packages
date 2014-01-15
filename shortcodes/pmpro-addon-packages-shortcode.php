<?php 
	//archives page for add on packages using [pmpro_addon_packages] shortcode
	function pmpro_addon_packages_shortcode($atts, $content=null, $code="")
	{
		// $atts    ::= array of attributes
		// $content ::= text within enclosing form of shortcode element
		// $code    ::= the shortcode found, when == callback name
		// examples: [pmpro_addon_packages show="none" include="subpages"] table of addone packages that are subpages of the page with shortcode and showing no description excerpt
		
		global $wpdb, $post, $current_user, $pmpro_currency_symbol;
		
		extract(shortcode_atts(array(
			'show' => 'excerpt',
			'include' => 'all',
			'orderby'	=> 'menu_order',
			'order'	=>	'ASC'
		), $atts));
		
		$count = 0;
		
		// get posts
		if($include == 'subpages')
		{
			$query = new WP_Query(array('post_type' => 'page', 'post_status'=>'publish', 'posts_per_page'=>-1, 'orderby'=>$orderby, 'order'=>$order, 'meta_key'=>'_pmproap_price', 'meta_compare'=>'>', 'meta_value'=>'0', 'post_parent'=>$post->ID));
		}
		else
		{
			$query = new WP_Query(array('post_type' => array('post', 'page'), 'post_status'=>'publish', 'posts_per_page'=>-1, 'orderby'=>$orderby, 'order'=>$order, 'meta_key'=>'_pmproap_price', 'meta_compare'=>'>', 'meta_value'=>'0'));
		}
		ob_start();
		
		if ($query->have_posts() ) :
		?>
		<table id="pmpro_addon_packages" cellpadding="0" cellspacing="0" border="0">
			<thead>
				<tr>
					<th><?php _e('Name', 'pmpro');?></th>
					<?php if($show == 'excerpt') { ?><th><?php _e('Description', 'pmpro');?></th><?php } ?>
					<th><?php _e('Price', 'pmpro');?></th>        
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
			<?php
				global $more;
				remove_action('the_content','pmpro_membership_content_filter',5);
				while ($query->have_posts() ) : $query->the_post();	
				$pmproap_price = get_post_meta($post->ID, "_pmproap_price", true);
				?>
				<tr id="pmpro_addon_package-<?php echo $post->ID; ?>" class="pmpro_addon_package">
					<td class="pmpro_addon_package-title"><?php the_title(); ?><br /><?php echo $post->post_type; ?>
					<?php if($show == 'excerpt') { ?>
						<td class="pmpro_addon_package-excerpt">
						<?php 
							$more = 0;
							the_content('');
						?></td>
					<?php } ?>
					<td class="pmpro_addon_package-price"><?php echo $pmpro_currency_symbol . $pmproap_price; ?></td>
					<?php 
						if(pmproap_hasAccess($current_user->ID,$post->ID))
						{
							?>
							<td class="pmpro_addon_package-view"><a class="pmpro_btn" href="<?php echo the_permalink(); ?>"><?php _e('View&nbsp;Now', 'pmpro');?></a></td>
							<?php
						}
						else
						{
							//use current level or offer a free level checkout
							$has_access = pmpro_has_membership_access($post->ID, $current_user->ID, true);			
							$post_levels = $has_access[1];
							if(in_array($current_user->membership_level->ID, $post_levels))
							{
								$text_level_id = $current_user->membership_level->id;				
							}
							else
							{
								//find a free level to checkout with
								foreach($post_levels as $post_level_id)
								{
									$post_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = '" . $post_level_id . "' LIMIT 1");
									if(pmpro_isLevelFree($post_level))
									{
										$text_level_id = $post_level->id;
										break;
									}
								}
							}
							?>
							<td class="pmpro_addon_package-buy"><a class="pmpro_btn" href="<?php echo pmpro_url("checkout", "?level=" . $text_level_id . "&ap=" . $post->ID); ?>"><?php _e('Buy&nbsp;Now', 'pmpro');?></a></td>
							<?php
						}
					?>
				</tr> <!-- end pmpro_addon_package-->
				<?php
				$count++;
				endwhile;
				add_action('the_content','pmpro_membership_content_filter',5);
			?>
			</table> <!-- end #pmpro_addon_packages -->
			<?php
			endif;
		wp_reset_query();
		$temp_content = ob_get_contents();
		ob_end_clean();
		return $temp_content;
	}
	add_shortcode("pmpro_addon_packages", "pmpro_addon_packages_shortcode");