(function ($) {
    $(function() {

		$('#z-like-button-update-wrapper').hide();

		$('#z-like-button-manual-update').change(function() {
			if($(this).is(':checked')) {
				$('#z-like-button-update-wrapper').slideDown();
			} else {
				$('#z-like-button-update-wrapper').slideUp();
			}
		});

		// Add Color Picker  in admin to all inputs that have 'z-mini-menu-color-field' class
    	$('.z-like-button-color-field').wpColorPicker();
		
		// After 500 ms, try to move the labels so we can properly display them
		setTimeout(function() {
			$('.color-label-faux').each(function(){
				let label_to_move = $(this);
				console.log(label_to_move);
				let target = $(this).next(); // .wp-picker-container
				console.log(target);
				label_to_move.prependTo(target);
			});

		}, 500 );




		// Copy the shortcode code on click of the <code> el
		var zlb_code_els = document.querySelectorAll('.like-button-options code');

		zlb_code_els.forEach((code_el) => code_el.addEventListener('click', () => {
			let code_shortcode = code_el.innerHTML;
			console.log(code_shortcode);
			zlb_setClipboard(code_shortcode).then( zlb_showCopiedMsg( code_shortcode, code_el ) );
		}));

		async function zlb_setClipboard(text) {
			const type = "text/plain";
			const clipboardItemData = {
				[type]: text,
			};
			const clipboardItem = new ClipboardItem(clipboardItemData);
			await navigator.clipboard.write([clipboardItem]);
		}
		async function zlb_showCopiedMsg( text, el ) {
			console.log( text );
			console.log( el );

			const msgDiv = document.createElement( 'div' );
			msgDiv.classList.add('zlb-msg');
			msgDiv.innerHTML = z_like_button_admin.copiedText;
			el.after(msgDiv);
			setTimeout( function() {
				msgDiv.classList.add('fadeOut');
				setTimeout( function() {
					msgDiv.remove();
				}, 1500 );
			}, 3000 );
		}

    });
})(jQuery);