<?php 
	//archives page for add on packages using [pmpro_addon_packages] shortcode
	function pmpro_addon_packages_shortcode($atts, $content=null, $code="")
	{
		// $atts    ::= array of attributes
		// $content ::= text within enclosing form of shortcode element
		// $code    ::= the shortcode found, when == callback name
		// examples: [pmpro_addon_packages show="none" include="subpages"] 
		// table of addone packages that are subpages of the page with shortcode and showing no description excerpt
		
		global $wpdb, $post, $current_user;
		
		extract(shortcode_atts(array(
			'checkout_button' => __('Buy Now', 'pmpro-addon-packages'),
			'levels_button' => __('Choose a Level', 'pmpro-addon-packages'),
			'exclude' => NULL,
			'layout' => 'table',
			'link' => true,
			'include' => NULL,
			'orderby'	=> 'menu_order',
			'order'	=>	'ASC',
			'thumbnail' => 'thumbnail',
			'view_button' => __('View Now', 'pmpro-addon-packages'),
		), $atts));					
		
		// prep exclude array
		$exclude = str_replace(" ", "", $exclude);
		$exclude = explode(",", $exclude);
	
		//turn 0's into falses
		if($include == "subpages")
		{
			$post_type = "page";
			$post_parent = $post->ID;

			$include = NULL;	//so it doesn't affect the query below
		}
		else
		{
			$post_type = array('post', 'page');
			$post_parent = NULL;

			//including post IDs
			if(!empty($include)) {
				// prep exclude array
				$include = str_replace(" ", "", $include);
				$include = explode(",", $include);
			}
		}
		
		if($link === "0" || $link === "false" || $link === "no")
			$link = false;
		else
			$link = true;
	
		if($thumbnail && strtolower($thumbnail) != "false")
		{
			if(strtolower($thumbnail) == "medium")
				$thumbnail = "medium";
			elseif(strtolower($thumbnail) == "large")
				$thumbnail = "large";
			else
				$thumbnail = "thumbnail";
		}
		else
			$thumbnail = false;
				
		// get posts
		$args = array(
			'meta_key'=>'_pmproap_price',
			'meta_compare'=>'>',
			'meta_value'=>'0',
			"order"=>$order,
			"orderby"=>$orderby,
			'posts_per_page'=>-1,
			'post_status'=>'publish',
			"post_type"=>$post_type,
			"post_parent"=>$post_parent,
			"post__not_in"=>$exclude,
			"post__in"=>$include,
		);
		$pmproap_posts = get_posts($args);
		
		$layout_cols = preg_replace('/[^0-9]/', '', $layout);
		if(!empty($layout_cols))
			$pmproap_posts_chunks = array_chunk($pmproap_posts, $layout_cols);
		else
			$pmproap_posts_chunks = array_chunk($pmproap_posts, 1);
			
		ob_start();
		
		if(!empty($pmproap_posts)) 
		{	
			?>
			<style>
				.pmpro_addon_package td {vertical-align: middle; }
				tr.pmpro_addon_package td.pmpro_addon_package-title h2 {margin: 0; }
				#pmpro_addon_packages h2.pmpro_addon_package-title {margin: 0 0 2rem 0; }
				.pmpro_addon_package td.pmpro_addon_package-thumbnail img {max-width: 100%; height: auto; }
				.pmpro_addon_package td.pmpro_addon_package-buy .pmpro_btn, .pmpro_addon_package td.pmpro_addon_package-view .pmpro_btn {display: block; }
			</style>
		<?php		
			if($layout == 'table')
			{
				?>
				<table id="pmpro_addon_packages" cellpadding="0" cellspacing="0" border="0">					
					<tbody>
					<?php
						foreach($pmproap_posts as $post)
						{
							$pmproap_price = get_post_meta($post->ID, "_pmproap_price", true);																				
							?>
							<tr id="pmpro_addon_package-<?php echo $post->ID; ?>" class="pmpro_addon_package">
							<?php 
								if ( has_post_thumbnail() && !empty($thumbnail))
								{					
									?>
									<td width="15%" class="pmpro_addon_package-thumbnail">
									<?php	
										if($link)
											echo '<a href="' . get_permalink() . '">' . get_the_post_thumbnail($post->ID, $thumbnail) . '</a>';
										else
											echo get_the_post_thumbnail($post->ID, $thumbnail);
									?>
									</td>
									<?php
								}
								?>
								<td class="pmpro_addon_package-title">
									<h2>
									<?php 
										if(!empty($link))
											echo '<a href="' . get_permalink() . '">' . get_the_title() . '</a>';
										else
											echo get_the_title();
									?>
									</h2>									
								</td>
								<?php
									if(!empty($current_user->ID) && pmproap_hasAccess($current_user->ID,$post->ID))
									{
										?>
										<td width="25%" class="pmpro_addon_package-view"><a class="pmpro_btn" href="<?php echo the_permalink(); ?>"><?php echo $view_button; ?></a></td>
										<?php
									}
									else
									{
										//which level to use for checkout link?
										$text_level_id = pmproap_getLevelIDForCheckoutLink($post->ID, $current_user->ID);
										
										?>
										<td width="25%" class="pmpro_addon_package-buy">
										<?php

										if(empty($text_level_id)) {																		
											?>
												<a class="pmpro_btn" href="<?php echo pmpro_url( "levels" ); ?>">
													<?php echo $levels_button;?>
												</a>
											<?php
										} else {										
											//what's the price
											$pmproap_price = get_post_meta($post->ID, "_pmproap_price", true);
											?>
												<a class="pmpro_btn" href="<?php echo pmpro_url( "checkout", "?level=" . $text_level_id . "&ap=" . $post->ID ); ?>">
													<?php echo $checkout_button; ?> &mdash; <span class="pmpro_addon_package-price"><?php echo pmpro_formatPrice( $pmproap_price ); ?></span>
												</a>
											<?php
										}
										?>
										</td>
										<?php
									}
								?>
							</tr> <!-- end pmpro_addon_package-->
							<?php
						}
					?>
					</tbody>
				</table> <!-- end #pmpro_addon_packages -->
				<?php
			}
			else
			{
				?>
				<div id="pmpro_addon_packages">
					<?php
						foreach($pmproap_posts_chunks as $row): ?>
							<div class="row">
						<?php
							foreach($row as $post): 
								$pmproap_price = get_post_meta($post->ID, "_pmproap_price", true); ?>
								<div class="medium-<?php
									if($layout == '2col')
										echo '6 ';
									elseif($layout == '3col')
										echo '4 text-center ';
									elseif($layout == '4col')
										echo '3 text-center ';
									else
										echo '12 ';?>
								columns">
									<article id="pmpro_addon_package-<?php echo $post->ID; ?>" class="<?php echo implode(" ", get_post_class()); ?> pmpro_addon_package">							
										<header class="entry-header"><h2 class="entry-title pmpro_addon_package-title">
										<?php 
											if ( has_post_thumbnail() && !empty($thumbnail))
											{					
												if($layout == '3col' || $layout == '4col')
													$thumbnail_class = "aligncenter";
												else
													$thumbnail_class = "alignright";
												if($link)
													echo '<a href="' . get_permalink() . '">' . get_the_post_thumbnail($post->ID, $thumbnail, array('class' => $thumbnail_class)) . '</a>';
												else
													echo get_the_post_thumbnail($post->ID, $thumbnail, array('class' => $thumbnail_class));
											}
											if(!empty($link))
												echo '<a href="' . get_permalink() . '">' . get_the_title() . '</a>';
											else
												echo get_the_title();
										?>									
										</h2></header>
										<div class="entry-content">																		
											<?php
												if(!empty($current_user->ID) && pmproap_hasAccess($current_user->ID,$post->ID))
												{
													?>
													<p class="pmpro_addon_package-view"><a class="pmpro_btn" href="<?php echo the_permalink(); ?>"><?php echo $view_button; ?></a></p>
													<?php
												}
												else
												{
													//which level to use for checkout link?
													$text_level_id = pmproap_getLevelIDForCheckoutLink($post->ID, $current_user->ID);
													
													if(empty($text_level_id)) {																	
														?>															
														<p class="pmpro_addon_package-buy"><a class="pmpro_btn" href="<?php echo pmpro_url("levels"); ?>"><?php echo $levels_button; ?></a></p>
														<?php
													} else {													
														//what's the price
														$pmproap_price = get_post_meta($post->ID, "_pmproap_price", true);														
														?>
														<p class="pmpro_addon_package-buy"><a class="pmpro_btn" href="<?php echo pmpro_url("checkout", "?level=" . $text_level_id . "&ap=" . $post->ID); ?>"><?php echo $checkout_button; ?> &mdash; <span class="pmpro_addon_package-price"><?php echo pmpro_formatPrice($pmproap_price); ?></span></a></p>
														<?php
													}
												}
											?>
										</div>
									</article> <!-- end pmpro_addon_package-->
								</div>
							<?php endforeach; ?>
						</div> <!-- end row -->
						<?php if($layout == '3col' || $layout == '4col') { echo "<hr />"; } ?>						
					<?php endforeach; ?>
				</div> <!-- end #pmpro_addon_packages -->
				<?php
			}
			//Reset Query
			wp_reset_query();
		}
	else
	{
		_e('No add on packages found.','pmpro-addon-packages');
	}		
	$temp_content = ob_get_contents();
	ob_end_clean();
	return $temp_content;
}
add_shortcode("pmpro_addon_packages", "pmpro_addon_packages_shortcode");
