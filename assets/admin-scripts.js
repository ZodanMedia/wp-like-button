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