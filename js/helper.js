

jQuery( document ).ready( function( $ ){
    $( '.enable-auto-update').on( 'click', function( e ) {
        e.preventDefault();

        var id = $( this).attr( 'data-id' ) || '';
        if ( id ) {
            $.ajax( {
                url: FtHelper.ajax,
                data: {
                    action: 'fame_helper_api',
                    fame_helper: 'enable_auto_update',
                    id: id,
                    nonce: FtHelper.nonce,
                },
                dataType: 'html',
                type: 'get',
                cache: false,
                success: function( r ){

                }
            } );
        }

    } );
} );