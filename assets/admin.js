/*!
 * @package PB_Formats
 * @since 1.0
 */
(function($) {
	"use strict";

	// Show/Hide Boxes as needed
	$('#post-formats-select input').change(pbformatsBoxChecked);

	function pbformatsBoxChecked() {
		var _currentBox = $('#post-formats-select input:checked').attr('value');
		if( typeof _currentBox != 'undefined' ) {
			$('#pb-formatsdiv div[id^=pb_formats_box_]').hide();
			$('#pb-formatsdiv #pb_formats_box_'+ _currentBox + '').stop(true, true).fadeIn(400);
		}
	}

	$(window).load(function() {
		pbformatsBoxChecked();
	});

	// Media Manager for Galleries
	$(function() {
		var frame,
		    images = $('#_pb_formats_gallery').val(),
		    selection = pbformatsLoadImages(images);

		$('#pb_formats_gallery_upload').on('click', function(e) {
			e.preventDefault();

			// Set options for 1st frame render
			var options = {
				title: PB_Formats_Localize.createText,
				state: 'gallery-edit',
				frame: 'post',
				selection: selection
			};

			// Check if frame or gallery already exist
			if( frame || selection ) {
				options['title'] = PB_Formats_Localize.editText;
			}

			frame = wp.media(options).open();
			
			// Tweak views
			frame.menu.get('view').unset('cancel');
			frame.menu.get('view').unset('separateCancel');
			frame.menu.get('view').get('gallery-edit').el.innerHTML = PB_Formats_Localize.editText;
			frame.content.get('view').sidebar.unset('gallery'); // Hide Gallery Settings in sidebar

			// When we are editing a gallery
			overrideGalleryInsert();
			frame.on( 'toolbar:render:gallery-edit', function() {
				overrideGalleryInsert();
			});
			
			frame.on( 'content:render:browse', function( browser ) {
		    if ( !browser ) return;
		    // Hide Gallery Settings in sidebar
		    browser.sidebar.on('ready', function(){
	        browser.sidebar.unset('gallery');
		    });
		    // Hide filter/search as they don't work
		    browser.toolbar.on('ready', function(){
			    if(browser.toolbar.controller._state == 'gallery-library'){
		        browser.toolbar.$el.hide();
			    }
		    });
			});
			
			// All images removed
			frame.state().get('library').on( 'remove', function() {
		    var models = frame.state().get('library');
				if(models.length == 0){
			    selection = false;
					$.post( PB_Formats_Localize.ajax_url, { ids: '', action: 'pb_formats_ajax', nonce: PB_Formats_Localize.nonce });
				}
			});
			
			// Override insert button
			function overrideGalleryInsert() {
				frame.toolbar.get('view').set({
					insert: {
						style: 'primary',
						text: PB_Formats_Localize.saveText,
						click: function() {
							var models = frame.state().get('library'),
						    ids = '';

							models.each( function( attachment ) {
						    ids += attachment.id + ','
							});

							this.el.innerHTML = PB_Formats_Localize.savingText;
								
							$.ajax({
								type: 'POST',
								url: PB_Formats_Localize.ajax_url,
								data: { 
									ids: ids, 
									action: 'pb_formats_ajax',
									nonce: PB_Formats_Localize.nonce 
								},
								success: function() {
									selection = pbformatsLoadImages(ids);
									$('#_pb_formats_gallery').val( ids );
									frame.close();
								},
								dataType: 'html'
							}).done( function( data ) {
								$('#pb_formats_gallery_input').html( data );
							}); 
						}
					}
				});
			}
		});
		
		// Load images
		function pbformatsLoadImages(images) {
			if( images ){
		    var shortcode = new wp.shortcode({
  					tag:    'gallery',
  					attrs:   { ids: images },
  					type:   'single'
  			});

		    var attachments = wp.media.gallery.attachments( shortcode );

				var selection = new wp.media.model.Selection( attachments.models, {
  					props:    attachments.props.toJSON(),
  					multiple: true
  				}
  			);
      
				selection.gallery = attachments.gallery;
      
				// Fetch the query's attachments, and then break ties from the
				// query to allow for sorting.
				selection.more().done( function() {
					// Break ties with the query.
					selection.props.set({ query: false });
					selection.unmirror();
					selection.props.unset('orderby');
				});
      				
				return selection;
			}	
			return false;
		}
	});

})(jQuery);
