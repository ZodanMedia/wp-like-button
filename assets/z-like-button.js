(function ($) {

    $(function() {
        if ($('.zLikeButton').length > 0) {
            $('.likeCheck').click(function () {
                const $this = $(this);
                const $these = $('.likeCheck');
                // in case there are more instances active, get them all
                $these.each(function() {
                    console.log($(this));
                    $(this).attr("disabled", "disabled");
                })
                
                data = {
                    'action': 'like_button',
                    'nonce': z_like_button.nonce,
                    'post': $this.attr('id')
                };

                $.ajax({
                    type: "post",
                    data: data,
                    url: z_like_button.url,
                    dataType: "json",
                    success: function (results) {
                        $these.each(function() {
                            console.log($(this));
                            $(this).parent().toggleClass('liked');
                            let checked = $this.prop("checked");
                            console.log(checked);
                            $(this).prop("checked", checked);
                            $(this).parent().find('.likeCount').text(results.likes);
                            $(this).parent().find('.intitule').text(results.text);
                            $(this).removeAttr("disabled");
                        })
                    },
                    error: function () {
                    }
                });
            });
        }

        if ($('.zlb-my-likes-list > li > .remove-from-list').length > 0) {
            $('.zlb-my-likes-list > li > .remove-from-list').click(function () {
                const $this = $(this);
                $(this).attr("disabled", "disabled");
                
                data = {
                    'action': 'like_button_remove',
                    'nonce': $this.attr('data-post-nonce'),
                    'post': $this.attr('data-post-id')
                };

                $.ajax({
                    type: "post",
                    data: data,
                    url: z_like_button.url,
                    dataType: "json",
                    success: function (results) {
                        if(results.removed == $this.attr('data-post-id')) {
                            $this.closest('li').remove();
                            console.log($(this));
                        } else {
                            alert(results.message);
                        }
                    },
                    error: function () {
                    }
                });
            });
        }

    });
    
})(jQuery);