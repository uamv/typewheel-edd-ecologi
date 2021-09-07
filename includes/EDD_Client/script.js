(function( $ ) {
    $(document).ready( function() {

        let $body = $('body');
        $body.on('click', '.edd-client-cred-link',function () {
            $(this).closest('tr').next('.edd-client-row').toggle('slow', 'linear');
            $(this).closest('p').next('.edd-client-row').toggle('slow', 'linear');
        });

        $body.on('click','.edd-client-button', function (event) {
            event.preventDefault();

            $('.dashicons', this).removeClass( 'dashicons-yes-alt' ).addClass('dashicons-update');
            $('.dashicons', this).addClass( 'spin' );

            var edd_button = $(this);

            wp.ajax.post( edd_button.attr('data-action'), {
               headers: 'Content-type: application/json',
               data: {
                  nonce : edd_button.attr('data-nonce'),
                  action : edd_button.attr('data-action'),
                  operation: edd_button.attr('data-operation'),
                  license: edd_button.prev('.edd-client-license-key').val(),
               }
            } )
            .done( function( response ) {

               $('.edd-client-msg').remove();
               edd_button.parent().append('<div class="edd-client-msg">'+response.data+'</div>');

               if( response.success === true ){
                   edd_button.find('.dashicons').removeClass( 'dashicons-update spin' ).addClass('dashicons-yes-alt');
                   if(response.data === 'License deactivated for this site' || response.data === 'License successfully activated'){
                       location.reload(false);
                   }
               }else{
                   edd_button.find('.dashicons').removeClass( 'dashicons-update spin' ).addClass('dashicons-dismiss');
               }

            } )
            .fail( function(response) {

               console.log(response);

            });


        });
    });
})(jQuery);
