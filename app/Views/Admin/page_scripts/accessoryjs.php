<script>
var baseUrl   = "<?= base_url() ?>";
var csrfToken = "<?= csrf_token() ?>";
var csrfHash  = "<?= csrf_hash() ?>";

$(document).ready(function () {
    if ($('#accessoryList').length) {
        $('#accessoryList').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: baseUrl + "admin/accessory/List",
                type: "POST",
                data: function (d) { d[csrfToken] = csrfHash; }
            },
            columns: [
                { data: null, render: function (d, t, r, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
                { data: 'acc_Code', render: function (data) { return data ? $('<div>').text(data).html() : '-'; } },
                { data: 'acc_Name', render: function (data) {
                    var s = $('<div>').text(data).html();
                    return data.length > 30 ? '<span title="' + s + '">' + $('<div>').text(data.substring(0, 30)).html() + '...</span>' : s;
                } },
                { data: 'acc_Price' },
                { data: 'status_switch' },
                { data: 'actions' }
            ],
            columnDefs: [{ targets: [0, 5], orderable: false, searchable: false }, { targets: 4, orderable: true, searchable: false }]
        });
    }
});

// Add / update
$('#accessorySubmit').click(function (e) {
    e.preventDefault();
    $.post(baseUrl + "admin/accessory/save", $('#createAccessory').serialize(), function (response) {
        if (response.status == 1) {
            $('#messageBox').removeClass('alert-danger').addClass('alert-success')
                .text(response.msg || 'Saved Successfully!').show();
            setTimeout(function () { window.location.href = baseUrl + "admin/accessory"; }, 1500);
        } else {
            $('#messageBox').removeClass('alert-success').addClass('alert-danger')
                .text(response.message || 'Please Fill all the Data').show();
            setTimeout(function () { $('#messageBox').empty().hide(); }, 3000);
        }
    }, 'json');
});

// Active / inactive
$(document).on('change', '.checkactive', function () {
    var accId  = $(this).attr('id').split('-')[1];
    var status = $(this).is(':checked') ? 1 : 2;
    $.ajax({
        url: baseUrl + 'admin/accessory/status',
        type: 'POST',
        dataType: 'json',
        data: { acc_Id: accId, acc_Status: status },
        success: function (response) {
            $('#messageBox').removeClass('alert-success alert-danger')
                .addClass(response.success ? 'alert-success' : 'alert-danger')
                .text(response.message).show();
            $('html, body').animate({ scrollTop: 0 }, 'fast');
            setTimeout(function () { $('#messageBox').fadeOut(); }, 2000);
        },
        error: function (xhr, status, error) {
            $('#messageBox').removeClass('alert-success').addClass('alert-danger').text('AJAX error: ' + error).show();
        }
    });
});

// Delete
function confirmDelete(accId) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'You want to delete this Accessory?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: baseUrl + "admin/accessory/delete/" + accId,
            type: "POST",
            dataType: "json",
            success: function (response) {
                if (response.status == 1) {
                    Swal.fire('Deleted!', response.message, 'success');
                    $('#accessoryList').DataTable().ajax.reload(null, false);
                } else {
                    Swal.fire('Error!', response.message, 'error');
                }
            },
            error: function () { Swal.fire('Error!', 'Something went wrong.', 'error'); }
        });
    });
}
</script>
