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

					<tr>
						<th scope="row">
							<label for="meta"><?php _e( 'Metadata', 'sitewide-search' ); ?></label><br />
							<em><?php _e( 'Enable metadata to be copied', 'sitewide-search' ); ?></em>
						</th>
						<td>
							<input type="checkbox" id="meta" name="meta" <?php if( $settings[ 'meta' ] ) echo 'checked="checked"'; ?> />
						</td>
					</tr>

					<tr>
						<th scope="row" colspan="2">
							<h4><?php _e( 'Sitewide browsing functions', 'sitewide-search' ); ?></h4>
							<p><?php _e( 'Use these carefully. They may not work as perfect as you prefer and may reduce preformance.', 'sitewide-search' ); ?></p>
						</th>
					</tr>

					<?php foreach( array(
						'enable_search' => array( __( 'Sitewide search', 'sitewide-search' ), __( 'Wherever visitors are searching, results will always be fetched from the archive.', 'sitewide-search' ) ),
						'enable_archive' => array( __( 'Sitewide archive', 'sitewide-search' ), __( 'Wherever visitors are browsing archives, results will always be fetched from the archive.', 'sitewide-search' ) ),
						'enable_categories' => array( __( 'Sitewide categories', 'sitewide-search' ), __( 'Wherever visitors are browsing posts by categories, results will always be fetched from the archive.', 'sitewide-search' ) ),
						'enable_tags' => array( __( 'Sitewide tags', 'sitewide-search' ), __( 'Wherever visitors are browsing posts by tags, results will always be fetched from the archive.', 'sitewide-search' ) ),
						'enable_author' => array( __( 'Sitewide author', 'sitewide-search' ), __( 'Wherever visitors are browsing posts by author, results will always be fetched from the archive.', 'sitewide-search' ) )
					) as $override_name => $override ) : ?>
						<tr>
							<th scope="row">
								<label for="<?php echo $override_name; ?>"><?php echo $override[ 0 ]; ?></label><br />
								<em><?php echo $override[ 1 ]; ?></em>
							</th>
							<td>
								<input type="checkbox" id="<?php echo $override_name; ?>" name="<?php echo $override_name; ?>" <?php if( $settings[ $override_name ] ) echo 'checked="checked"'; ?> />
							</td>
						</tr>
					<?php endforeach; ?>

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
									<input type="hidden" name="confirm" value="<?php echo esc_attr( sprintf( __( 'You\'re about to remove all %d posts. Do you want to continue?', 'sitewide-search' ), $sitewide_search->get_post_count() ) ); ?>" />
									<p><?php _e( 'Remove all current copies from the archive blog.', 'sitewide-search' ); ?></p>
									<h4><?php _e( 'WARNING:', 'sitewide-search' ); ?></h4>
									<p><?php _e( 'Think twice before you do this, This is not undoable!', 'sitewide-search' ); ?></p>
									<p><input id="sitewide-search-reset-button" type="submit" class="button-primary" name="sitewide-search-reset" value="<?php echo esc_attr( sprintf( __( 'Remove all %d posts', 'sitewide-search' ), $sitewide_search->get_post_count() ) ); ?>" /></p>
								</form>
							</div>
						</div>
					</div>
				<?php endif; ?>
				<div class="widgets-holder-wrap">
					<div class="sidebar-name">
						<h3><span><?php _e( 'Populate archive blog', 'sitewide-search' ); ?></span></h3>
					</div>
					<div class="widgets-sortables widget-holder">
						<div class="sitewide-search-utilites">
							<form id="sitewide-search-populate" action="" method="post">
								<input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce( 'sitewide-search-populate' ) ); ?>" />
								<input type="hidden" name="action" value="populate_archive" />
								<p><?php _e( 'Populates the archive blog with posts from this network all blogs.', 'sitewide-search' ); ?></p>
								<h4><?php _e( 'WARNING:', 'sitewide-search' ); ?></h4>
								<p><?php _e( 'Depending on how many blogs and posts your site contains this can take a long time. Populate action is split into several requests of a specified amount of posts each. Choose a lower amount of posts per request if your server tend to fail during populate.', 'sitewide-search' ); ?></p>
								<p><?php _e( 'Do not reload or leave this page when doing this!', 'sitewide-search' ); ?></p>
								<p>
									<label for="sitewide-search-populate-chunk"><?php _e( 'Amount of posts per request:', 'sitewide-search' ); ?></label>
									<input id="sitewide-search-populate-chunk" name="chunk" value="100" />
								</p>
								<p><input id="sitewide-search-populate-button" type="submit" class="button-primary" name="sitewide-search-populate" value="<?php echo esc_attr( __( 'Populate', 'sitewide-search' ) ); ?>" /></p>
								<ul class="sitewide-search-populate-results"></ul>
							</form>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

</div>
