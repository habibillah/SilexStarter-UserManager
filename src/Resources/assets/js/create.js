$(document).ready(function(){
    $('#icon-wrapper').html('<a href="'+window.location+'create" class="btn btn-sm btn-primary" id="btn-create"><i class="fa fa-fw fa-plus"></i>'+createLabel+'</a>');

    $('#btn-create').click(function(e){
        e.preventDefault();
        $(formId)[0].reset();

        $('.selectpicker').selectpicker('render');
        $('#_method').val('POST');
        $('#id').val('');
        $(modalId).modal('show');
    });
});