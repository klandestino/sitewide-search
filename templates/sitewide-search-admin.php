<?php global $wpdb; ?>
<div class="wrap">
	<h2><?php _e( 'Sitewide Search Settings', 'sitewide-search' ); ?></h2>
	<form action="" method="post">
		<?php wp_nonce_field( 'sitewide_search_admin' ); ?>

		<table class="form-table">
			<tbody>

				<tr>
					<th scope="row">
						<label for="archive-blog-id"><?php _e( 'Archive blog', 'sitewide-search' ); ?></label>
					</th>
					<td>
						<select id="archive-blog-id" name="archive-blog-id">
							<?php $blog_query = $wpdb->get_results( sprintf( 'SELECT `blog_id`, `domain` FROM `%s` ORDER BY `domain` ASC', $wpdb->blogs ) );
							foreach( $blog_query as $blog ) : ?>
								<option name="<?php echo esc_attr( $blog->blog_id ); ?>"><?php echo $blog->domain; ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="post_types"><?php _e( 'Post types', 'sitewide-search' ); ?></label>
					</th>
					<td>
						<ul>
							<?php foreach( Sitewide_Search_Admin::$post_types as $post_type => $post_type_name ) : ?>
								<li>
									<input type="checkbox" id="post_type_<?php echo esc_attr( $post_type ); ?>" name="post_types[]" value="<?php echo esc_attr( $post_type ); ?>" />
									<label for="post_type_<?php echo esc_attr( $post_type ); ?>"><?php echo $post_type_name; ?> (<?php echo $post_type; ?>)</label>
								</li>
							<?php endforeach; ?>
						</ul>
					</td>
				</tr>

			</tbody>
		</table>
	</form>
</div>
