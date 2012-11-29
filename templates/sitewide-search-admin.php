<?php

global $wpdb, $sitewide_search;
$settings = Sitewide_Search_Admin::get_settings();

?>
<div class="wrap sitewide-search-admin">
	<h2><?php _e( 'Sitewide Search Settings', 'sitewide-search' ); ?></h2>	

	<div class="widget-liquid-left">
		<form action="" method="post">
			<?php wp_nonce_field( 'sitewide_search_admin' ); ?>

			<table class="form-table">
				<tbody>

					<tr>
						<th scope="row">
							<label for="blog-search"><?php _e( 'Archive blog', 'sitewide-search' ); ?></label><br />
							<em><?php _e( 'Search for blogs by domain in the search field to the right. Select one blog to be the archive blog. All posts with choosen post types (see below) will be copied into this blog and all searches and archive browsing will be done from this blog.', 'sitewide-search' ); ?></em>
						</th>
						<td>
							<input id="blog-search" type="text" name="blog-search" autocomplete="off" />
							<ul id="blog-result">
								<li class="blog-template">
									<input id="blog-id-%blog_id" name="archive_blog_id" type="radio" value="%blog_id" />
									<label for="blog-id-%blog_id">%blogname — <em>%domain</em></label>
									— <a href="%siteurl" title="%blogname"><?php _e( 'View blog', 'sitewide-search' ); ?></a>
								</li>
								<?php if(
									$settings[ 'archive_blog_id' ]
									&& $archive_blog = Sitewide_Search_Admin::get_blogs( array( $settings[ 'archive_blog_id' ] ), false )
								) : $archive_blog = reset( $archive_blog ); ?>
									<li>
										<input id="blog-id-<?php echo esc_attr( $archive_blog[ 'blog_id' ] ); ?>" name="archive_blog_id" type="radio" value="<?php echo esc_attr( $archive_blog[ 'blog_id' ] ); ?>" checked="checked" />
										<label for="blog-id-<?php echo esc_attr( $archive_blog[ 'blog_id' ] ); ?>">
											<?php echo $archive_blog[ 'blogname' ]; ?> — <em><?php echo $archive_blog[ 'domain' ]; ?></em>
										</label>
										— <a href="<?php echo esc_attr( $archive_blog[ 'siteurl' ] ); ?>" title="<?php echo esc_attr( $archive_blog[ 'blogname' ] ); ?>"><?php _e( 'View blog', 'sitewide-search' ); ?></a>
									</li>
								<?php endif; ?>

							</ul>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="post_types"><?php _e( 'Post types', 'sitewide-search' ); ?></label><br />
							<em><?php _e( 'Choose post types that will be copied into the archive blog', 'sitewide-search' ); ?></em>
						</th>
						<td>
							<ul>
								<?php foreach( Sitewide_Search_Admin::$post_types as $post_type => $post_type_name ) : ?>
									<li>
										<input type="checkbox" id="post_type_<?php echo esc_attr( $post_type ); ?>" name="post_types[]" value="<?php echo esc_attr( $post_type ); ?>" <?php if( in_array( $post_type, $settings[ 'post_types' ] ) ) echo 'checked="checked"'; ?> />
										<label for="post_type_<?php echo esc_attr( $post_type ); ?>"><?php echo $post_type_name; ?> — <em><?php echo $post_type; ?></em></label>
									</li>
								<?php endforeach; ?>
							</ul>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="post_types"><?php _e( 'Taxonomies', 'sitewide-search' ); ?></label><br />
							<em><?php _e( 'Choose taxonomies that will be copied into the archive blog', 'sitewide-search' ); ?></em>
						</th>
						<td>
							<ul>
								<?php foreach( Sitewide_Search_Admin::$taxonomies as $tax => $tax_name ) : ?>
									<li>
										<input type="checkbox" id="taxonomies_<?php echo esc_attr( $tax ); ?>" name="taxonomies[]" value="<?php echo esc_attr( $tax ); ?>" <?php if( in_array( $tax, $settings[ 'taxonomies' ] ) ) echo 'checked="checked"'; ?> />
										<label for="post_type_<?php echo esc_attr( $tax ); ?>"><?php echo $tax_name; ?> — <em><?php echo $tax; ?></em></label>
									</li>
								<?php endforeach; ?>
							</ul>
						</td>
					</tr>

				</tbody>
			</table>

			<input type="submit" class="button-primary" name="sitewide-search-save" value="<?php echo esc_attr( __( 'Save' ) ); ?>" />
		</form>
	</div>

	<div class="widget-liquid-right">
		<div id="widgets-right">
			<?php if( $settings[ 'archive_blog_id' ] ) : ?>
				<?php if( $sitewide_search->get_post_count() ) : ?>
					<div class="widgets-holder-wrap">
						<div class="sidebar-name">
							<h3><span><?php _e( 'Reset archive blog', 'sitewide-search' ); ?></span></h3>
						</div>
						<div class="widgets-sortables widget-holder">
							<div class="sitewide-search-utilites">
								<form id="sitewide-search-reset" action="" method="post">
									<input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce( 'sitewide-search-reset' ) ); ?>" />
									<input type="hidden" name="action" value="reset_archive" />
									<p class="description"><?php _e( 'Remove all current copies from the archive blog. WARNING: this is not undoable!', 'sitewide-search' ); ?></p>
									<input id="sitewide-search-reset-button" type="submit" class="button-primary" name="sitewide-search-reset" value="<?php echo esc_attr( sprintf( __( 'Reset all %d posts', 'sitewide-search' ), $sitewide_search->get_post_count() ) ); ?>" />
								</form>
							</div>
						</div>
					</div>
				<?php endif; ?>
				<div class="widgets-holder-wrap">
					<div class="sidebar-name">
						<h3><span><?php _e( 'Repopulate archive blog', 'sitewide-search' ); ?></span></h3>
					</div>
					<div class="widgets-sortables widget-holder">
						<div class="sitewide-search-utilites">
							<form id="sitewide-search-repopulate" action="" method="post">
								<input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce( 'sitewide-search-repopulate' ) ); ?>" />
									<input type="hidden" name="action" value="repopulate_archive" />
								<p class="description"><?php _e( 'Removes and repopulates the archive blog with posts from this network all blogs. WARNING: this is not undoable!', 'sitewide-search' ); ?></p>
								<input id="sitewide-search-repopulate-button" type="submit" class="button-primary" name="sitewide-search-repopulate" value="<?php echo esc_attr( __( 'Repopulate', 'sitewide-search' ) ); ?>" />
								<ul class="sitewide-search-repopulate-results"></ul>
							</form>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

</div>
