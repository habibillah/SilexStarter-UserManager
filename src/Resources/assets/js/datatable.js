$(document).ready(function(){
    var $datatable = $(datatableId).DataTable({
        "processing": true,
        "serverSide": true,
        "dom" : "<'row'<'col-md-12'<'widget'<'widget-title'<'row'<'col-md-5'<'#icon-wrapper'>><'col-md-3'l><'col-md-4'f>><'clearfix'>><'widget-body flow no-padding'tr><'widget-title'<'col-sm-5'i><'col-sm-7'p><'clearfix'>>>>",
        "columnDefs": [
            {
                "targets": -1,
                "className": 'text-center',
                "orderable": false,
                "searchable": false
            }
        ],
        "language": {
            "paginate": {
                "previous": "&laquo;",
                "next": "&raquo;"
            }
        },
        "ajax": {
            "url": datatableUrl,
            "type": "POST",
            "error": function(resp){
                if(resp.responseJSON.error.code == 401){
                    alert('Your session is expired!\n\nYou will be redirected to the login page shortly.');
                    window.location.reload();
                }
            }
        }
    }).on('click', '.btn-edit', function(e){
        e.preventDefault();

        var url = $(this).attr('href');
        $(formId)[0].reset();

        $.ajax({
            'method' : 'GET',
            'url' : url,
            'success' : function(fields){

                var elem;

                for(var a in fields){
                    elem = $('#'+a);

                    if( elem.length > 0 && elem.prop('tagName').toLowerCase() == 'select') {
                        if (a == 'activated') {
                            elem.val(fields[a] ? 1 : 0);
                        }

                        if (a == 'permissions') {
                            var perms = [];

                            for(var perm in fields[a]){
                                perms.push(perm);
                            }

                            elem.val(perms);
                        }

                        if (a == 'groups') {
                            var groups = [];

                            for(var group in fields[a]){
                                groups.push(fields[a][group].id);
                            }

                            elem.val(groups);
                        }

                    } else {
                        elem.val(fields[a]);
                    }
                }

                $('#_method').val('PUT');
                $('.selectpicker').selectpicker('render');

                $(modalId).modal('show');
            },
            'error' : function(resp){
                if(resp.status == 401){
                    alert('Your session is expired!\n\nYou will be redirected to the login page shortly.');
                    window.location.reload();
                } else {
                    alert('Error occured while trying to get user info!');
                }
            }
        });

    }).on('click', '.btn-delete', function(e){
        e.preventDefault();

        if(confirm('Are you sure want to delete this user?')){
            var $link = $(this);
            var url = $link.attr('href');

            $link.prop('disabled', true).text('deleting...');

            $.ajax({
                'method' : 'DELETE',
                'url' : url,
                'success' : function(){
                    $datatable.ajax.reload(null, false);
                },
                'error' : function(resp){

                    if(resp.status == 401){
                        alert('Your session is expired!\n\nYou will be redirected to the login page shortly.');
                        window.location.reload();
                    } else {
                        alert('Error occured when deleting data');
                        $link.prop('disabled', false).text('delete');
                    }
                }
            });
        }
    });

    $('#btn-save').click(function(e) {
        e.preventDefault();

        $.ajax({
            'method' : $('#_method').val(),
            'data' : $(formId).serialize(),
            'url' : $('#_method').val() == 'PUT' ? $(formId).attr('action') + $('#id').val() : $(formId).attr('action'),
            'success' : function(resp){
                alert(resp.data);
                $(modalId).modal('hide');
                $datatable.ajax.reload(null, false);
            },
            'error': function(resp){
                if(resp.status == 401){
                    alert('Your session is expired!\n\nYou will be redirected to the login page shortly.');
                    window.location.reload();
                } else {
                    alert(resp.responseJSON.errors.message);
                }
            }
        });
    });
});