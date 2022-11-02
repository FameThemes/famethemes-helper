

jQuery(document).ready(function ($) {
    $('.ft-auto-update-link').on('click', function (e) {
        e.preventDefault();
        var id = $(this).attr('data-id') || '';
        var link = $(this);
        var tr = link.closest('.license-item');
        var act = link.attr('data-action') || 'enable';
        // tr.append(FtHelper.loading);
        tr.addClass('loading');
        
        const loadingIcon =  $( FtHelper.loading );
        tr.append(loadingIcon);
        link.html(FtHelper.loadingText);

        if (id) {
            $.ajax({
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
                success: function (r) {
                    tr.removeClass('loading');
                    loadingIcon.remove();
                    var a;
                    if (act == 'enable') {
                        link.attr('data-action', 'disable');
                        tr.find('.n-auto-update').html(FtHelper.yes);
                        if (r.success) {
                            a = r.site_count + '/' + r.license_limit;
                            tr.find('.n-activations').html(a);
                            link.html(FtHelper.disable);
                        } else {
                            link.html(FtHelper.enable);
                        }
                    } else {
                        link.attr('data-action', 'enable');
                        tr.find('.n-auto-update').html(FtHelper.no);
                        if (r.success) {
                            a = r.site_count + '/' + r.license_limit;
                            tr.find('.n-activations').html(a);

                        }
                        link.html(FtHelper.enable);
                    }


                }
            });
        }

    });
});