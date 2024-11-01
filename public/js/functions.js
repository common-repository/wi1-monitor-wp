(function($){
    $(document).ready(function(){
        //Code Here
        var messageUpdate = $('.updated,.error');
        messageUpdate.each(function(){
            $this = $(this);
            not_type = "error";
            if($this.hasClass('updated')){
                var not_type = "update";
            }
            var data = {
                action: 'wi_send_notice',
                type : not_type,
                message: $this.text()
            };
            $.post(ajaxurl,data);
        });
        //Code End
    });
})(jQuery);