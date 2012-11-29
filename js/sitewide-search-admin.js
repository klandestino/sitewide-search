jQuery( function( $ ) {

	/**
	 * Stores search result cache
	 */
	var cache = [];

	/**
	 * Executes a search by timeout
	 * @returns void
	 */
	function searchByTimeout() {
		var e = $( this );
		clearTimeout( e.data( 'search-timeout' ) );

		var timeout = setTimeout( function() {
			search.apply( e );
		}, 1000 );

		e.data( 'search-timeout', timeout );
	}

	/**
	 * Executes an ajax search for blogs
	 * @returns void
	 */
	function search() {
		var e = $( this ), val = e.val().replace( /\s+/g, ' ' ).replace( /^\s|\s$/, '' );
		if( e.data( 'search-last' ) != val && val.length > 0 ) {
			e.data( 'search-last', val );

			// Look for cached results with this query
			if( typeof( cache[ val ] ) != 'undefined' ) {
				results.apply( e, cache[ val ] );
				return;
			}

			$.ajax( ajaxurl, {
				type: 'POST',
				data: {
					action: 'get_blogs',
					cookie: encodeURIComponent( document.cookie ),
					query: val
				},
				dataType: 'json',
				success: $.proxy( results, e )
			} );
			e.addClass( 'working' );
		}
	}

	/**
	 * Taking care of results
	 * @param data array result data
	 * @returns void
	 */
	function results( data ) {
		// Don't allow more than 100 cached results
		if( cache.length > 99 ) {
			cache.shift();
		}

		// Add this search to cache array
		cache[ $( this ).data( 'search-last' ) ] = Array( data );

		$( this ).removeClass( 'working' );
		var list = $( '#blog-result' );
		list.find( '*:not( .blog-template ):has( input[ checked!="checked" ] )' ).remove();

		for( var i = 0, l = data.length; i < l; i++ ) {
			if( list.find( '#blog-' + data[ i ].blog_id ).length == 0 ) {
				var blog = list.find( '.blog-template' ).clone();
				blog.removeClass( 'blog-template' );
				blog.attr( 'id', 'blog-' + data[ i ].blog_id );

				for( var ii in data[ i ] ) {
					var re = RegExp( '%' + ii, 'g' );
					blog.html( blog.html().replace( re, data[ i ][ ii ] ) );
				}

				list.append( blog );
			}
		}
	}

	/**
	 * Sending a reset action to wordpress and listens for status
	 */
	function resetArchive() {
		var button = $( this ).find( 'input:submit' );

		if(
			confirm( $( this ).find( 'input[name=confirm]' ).val() )
			&& ! button.hasClass( 'working' )
			&& $( '#sitewide-search-populate .working' ).length == 0
		) {
			button.addClass( 'working' );
			$( window ).bind( 'beforeunload.sitewide-search-reset', function() {
				return false;
	  		} );

			$.ajax( ajaxurl, {
				type: 'post',
				data: $( this ).serialize(),
				dataType: 'json',
				success: function( data ) {
					button.removeClass( 'working' );
					$( window ).unbind( 'beforeunload.sitewide-search-reset' );

					if( data ) {
						button.val( button.val().replace( /[0-9]+/, '0' ) );
					}
				},
				error: function() {
					button.removeClass( 'working' );
					$( window ).unbind( 'beforeunload.sitewide-search-reset' );
				}
			} );
		}

		return false;
	}

	/**
	 * Sending a populate action to wordpress and listens for status
	 */
	function populateArchive() {
		var resultCount = 5;
		var button = $( this ).find( 'input:submit' );
		var results = $( this ).find( 'ul.sitewide-search-populate-results' );

		if( ! button.hasClass( 'working' ) && $( '#sitewide-search-reset .working' ).length == 0 ) {
			button.addClass( 'working' );
			$( window ).bind( 'beforeunload.sitewide-search-populate', function() {
				return false;
	  		} );

			function sendRequest( input, callback ) {
				$.ajax( ajaxurl, {
					type: 'post',
					data: input,
					dataType: 'json',
					success: function( data ) {
						if( data ) {
							if( results.find( 'li' ).length >= resultCount ) {
								results.find( 'li:first' ).remove();
							}

							if( data.status == 'ok' ) {
								results.append( '<li>' + data.message + '</li>' );
								sendRequest( data, callback );
							} else if( data.status == 'done' ) {
								results.append( '<li>' + data.message + '</li>' );
								results.append( '<li>All done</li>' );
								callback();
							} else {
								results.append( '<li>Fail</li>' );
								callback();
							}
						} else {
							results.append( '<li>Fail</li>' );
							callback();
						}
					},
					error: function() {
						button.removeClass( 'working' );
						results.append( '<li>Fail</li>' );
						callback();
					}
				} );
			}

			sendRequest( $( this ).serialize(), function() {
				button.removeClass( 'working' );
				$( window ).unbind( 'beforeunload.sitewide-search-populate' );
			} );
		}

		return false;
	}

	/**
	 * Add event listeners
	 */

	$( '#blog-search' ).keydown( searchByTimeout );
	$( '#sitewide-search-reset' ).submit( resetArchive );
	$( '#sitewide-search-populate' ).submit( populateArchive );

} );
