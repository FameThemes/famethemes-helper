

jQuery( document ).ready( function( $ ){
    $( '.ft-auto-update-link').on( 'click', function( e ) {
        e.preventDefault();
        var id = $( this).attr( 'data-id' ) || '';
        var link = $( this);
        var act = link.attr( 'data-action' ) || 'enable';
        if ( id ) {
            $.ajax( {
                url: FtHelper.ajax,
                data: {
                    action: 'fame_helper_api',
                    fame_helper: act,
                    id: id,
                    nonce: FtHelper.nonce,
                },
                dataType: 'json',
                type: 'get',
                cache: false,
                success: function( r ){
                    var tr = link.closest( 'tr' );
                    var a;
                    if ( act == 'enable' ) {
                        link.attr( 'data-action', 'disable' );
                        if (r.success) {
                            a = r.site_count + '/' + r.license_limit;
                            tr.find('.n-activations').html(a);
                            tr.find('.n-auto-update').html('<span class="dashicons dashicons-yes"></span>');
                            tr.find('.ft-auto-update-link').html(FtHelper.disable).attr('title', FtHelper.disable);
                        } else {
                            tr.find('.ft-auto-update-link').html(FtHelper.enable).attr('title', FtHelper.enable);
                            tr.find('.n-auto-update').html('<span class="dashicons dashicons-no-alt"></span>');
                        }
                    } else {
                        link.attr( 'data-action', 'enable' );
                        if (r.success) {
                            a = r.site_count + '/' + r.license_limit;
                            tr.find('.n-activations').html(a);

                        }

                        tr.find('.n-auto-update').html('<span class="dashicons dashicons-no-alt"></span>');
                        tr.find('.ft-auto-update-link').html(FtHelper.enable).attr('title', FtHelper.enable);
                    }


                }
            } );
        }

    } );
} );