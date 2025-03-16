
jQuery(document).ready(function(){

    jQuery("a#create-staging").click( function( event ) {

        jQuery('#response').html('');

        jQuery( "a#create-staging" ).css( { "pointer-events":"none", "color":"lightgrey" } );

        jQuery( "span.spinner-create-staging" ).addClass("is-active")
            .css( { "float":"left", "visibility":"" } );

        const data = {
            'action': 'create_staging',
            nonce: stl.nonce
        };

        // Ajax-Anfrage an den WordPress-Server senden
        jQuery.post(ajaxurl, data, function(response) {
            if (response.error) {
                jQuery('#response').html('<p style="color: red;">' + response.data.message + '</p>');
            } else {
                jQuery('#response').html('<p style="color: green;">' + response.data.message + '</p>');


                jQuery( "span.spinner-create-staging" ).removeClass("is-active")
                    .css( { "float":"right", "visibility":"none" } );

                jQuery( "a#create-staging" ).css( { "pointer-events":"unset", "color":"#fff" } );
            }
        });
    });

});