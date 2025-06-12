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

    });
})(jQuery);