jQuery('document').ready(function () {
	/* Left menu tabs */
	jQuery( '.instafx-menu li a' ).on( 'click', function( e ) {
	
		jQuery( '.instafx-menu li a' ).removeClass( 'active' );
		jQuery( this ).addClass( 'active' );
		
		e.preventDefault();
	});
	
	/* Twitter Stream ticker
	----------------------------------------------------------------- */
	var $t_stream = jQuery('.instafx-twitter-stream'),
			$t_stream_list = $t_stream.find('ul');

	// Only run this script when twitter feed fetched
	if( $t_stream_list.length > 0 ) {
		var $item = $t_stream_list.find('li'),
				item_length = $item.length,
				current_visible = $item.filter(':visible').index();

		// Hide all list except the first one
		$t_stream_list.find('li:not(:first)').hide();
		setInterval(function(){
			var next_visible = current_visible + 1;
			if( next_visible > item_length - 1 ) {
				next_visible = 0;
			}
			current_visible = next_visible;
			$item.hide();
			$item.eq(next_visible).fadeTo(250, 1);
		}, 5000);
	}

});