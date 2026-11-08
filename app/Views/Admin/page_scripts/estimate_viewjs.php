<script>
var baseUrl       = "<?= base_url() ?>";
var proposalId    = <?= (int)$proposal_id ?>;
var csrfTokenName = "<?= csrf_token() ?>";
var csrfHash      = "<?= csrf_hash() ?>";

function formatIndian(num) {
    if (num === null || num === undefined || num === '') return '—';
    var n       = parseFloat(num);
    var dec     = (n % 1 !== 0) ? n.toFixed(2).split('.')[1] : null;
    var intPart = Math.floor(Math.abs(n)).toString();
    if (intPart.length <= 3) {
        return (n < 0 ? '-' : '') + intPart + (dec ? '.' + dec : '');
    }
    var last3   = intPart.slice(-3);
    var rest    = intPart.slice(0, -3).replace(/(\d)(?=(\d{2})+$)/g, '$1,');
    return (n < 0 ? '-' : '') + rest + ',' + last3 + (dec ? '.' + dec : '');
}

function esc(v) {
    if (v === null || v === undefined || v === '') return '—';
    return $('<div>').text(v).html();
}

function humanizeType(v) {
    return v ? String(v).replace(/([a-z])([A-Z])/g, '$1 $2') : v;
}

function setText(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val || '—';
}

$(document).ready(function () {
    $.ajax({
        url: baseUrl + 'admin/estimates/view/' + proposalId,
        type: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { [csrfTokenName]: csrfHash },
        success: function (res) {
            if (!res.status) {
                $('#loadingState').hide();
                $('#errorState').text(res.message || 'Failed to load estimate.').show();
                return;
            }

            var p   = res.data.proposal;
            var it  = res.data.items;
            var img = res.data.images || [];

            // Customer info
            setText('cd-name',           p.name);
            setText('cd-project-name',   p.project_name);
            setText('cd-pin',            p.pin);
            setText('cd-address',        p.address);
            setText('cd-phone',          p.phone);
            setText('cd-email',          p.email);
            setText('cd-project-no',     p.project_number);
            setText('cd-date',           p.date_of_proposal);
            setText('cd-sales-manager',  p.sales_manager);
            setText('cd-service-support',p.service_support);
            setText('cd-referred-by',    p.referred_by);

            if (p.location) {
                setText('cd-location', p.location);
                $('#cd-location-row').show();
            }

            // PDF button
            if (p.pdf_url) {
                $('#pdfDownloadBtn').attr('href', p.pdf_url).removeClass('d-none');
            }

            // Paid status
            $('#paymentStatusRow').show();
            if (p.paid_status !== undefined && p.paid_status !== null && parseInt(p.paid_status) === 1) {
                $('#paidBadge').addClass('visible-el');
                $('#markPaidBtn').removeClass('visible-el');
            } else {
                $('#paidBadge').removeClass('visible-el');
                $('#markPaidBtn').addClass('visible-el');
            }

            // Items table
            var tbody = $('#itemsTbody');
            tbody.empty();
            if (!it || it.length === 0) {
                tbody.append('<tr><td colspan="12" class="text-center text-muted">No items found</td></tr>');
            } else {
                $.each(it, function (i, row) {
                    if (row.is_category == 1 || row.is_category === true) {
                        tbody.append(
                            '<tr class="table-secondary">'
                          + '<td colspan="12"><strong>' + (row.description || '') + '</strong></td>'
                          + '</tr>'
                        );
                    } else {
                        var amtRaw = row.amount !== null ? row.amount : row.amount_text;
                        var amtStr = '';
                        if (amtRaw !== null && amtRaw !== undefined && amtRaw !== '') {
                            amtStr = isNaN(parseFloat(amtRaw))
                                   ? '<strong>' + amtRaw + '</strong>'
                                   : formatIndian(amtRaw);
                        }
                        tbody.append(
                            '<tr>'
                          + '<td>' + (row.sl_no || '') + '</td>'
                          + '<td>' + (row.description || '') + '</td>'
                          + '<td>' + esc(humanizeType(row.cupboard_type)) + '</td>'
                          + '<td class="text-center">' + esc(row.size) + '</td>'
                          + '<td class="text-center">' + esc(row.design_code) + '</td>'
                          + '<td>' + esc(row.handle_type) + '</td>'
                          + '<td class="text-center">' + esc(row.door_state) + '</td>'
                          + '<td>' + esc(row.texture_code) + '</td>'
                          + '<td class="text-center">' + (row.qty !== null ? row.qty : '') + '</td>'
                          + '<td class="text-center">' + (row.unit || '') + '</td>'
                          + '<td class="text-end">' + (row.rate !== null ? formatIndian(row.rate) : '') + '</td>'
                          + '<td class="text-end">' + amtStr + '</td>'
                          + '</tr>'
                        );
                    }
                });
            }

            // Totals
            var totals = $('#totalsTbody');
            totals.empty();
            var addRow = function (label, value, bold) {
                var style = bold ? ' class="fw-bold"' : '';
                totals.append('<tr' + style + '>'
                    + '<td class="text-end">' + label + '</td>'
                    + '<td class="text-end" width="120">' + value + '</td>'
                    + '</tr>');
            };

            if (p.sub_total !== null && p.sub_total !== undefined) {
                addRow(p.sub_total_label || 'Total', '₹ ' + formatIndian(p.sub_total), false);
            }
            if (p.gst_percent !== null && p.gst_percent !== undefined && p.gst_percent > 0) {
                var gstAmt = Math.round((parseFloat(p.sub_total) || 0) * parseFloat(p.gst_percent) / 100);
                addRow('GST @' + p.gst_percent + '%', '₹ ' + formatIndian(gstAmt), false);
                if (p.sub_total !== null) {
                    addRow('Total', '₹ ' + formatIndian((parseFloat(p.sub_total) || 0) + gstAmt), false);
                }
            }
            if (p.discount !== null && p.discount !== undefined && p.discount > 0) {
                addRow('Discount', '₹ ' + formatIndian(p.discount), false);
            }
            if (p.grand_total !== null && p.grand_total !== undefined) {
                addRow('Grand Total', '₹ ' + formatIndian(p.grand_total), true);
            }

            if (p.grand_total_words) {
                $('#grandTotalWords').text(p.grand_total_words).show();
            }

            // Images gallery
            if (img.length > 0) {
                var grid = $('#imagesGrid');
                grid.empty();
                $.each(img, function (i, im) {
                    var is360  = parseInt(im.is_360) === 1;
                    // file_path is relative — use as image src; url is viewer URL for 360
                    var imgSrc = baseUrl + im.file_path;
                    var badge  = is360
                        ? '<span class="badge bg-warning text-dark position-absolute top-0 end-0 m-1" style="font-size:10px;">360°</span>'
                        : '';
                    var link = is360
                        ? '<a href="' + im.url + '" target="_blank" class="btn btn-sm btn-outline-warning mt-1 w-100"><i class="bi bi-arrows-fullscreen"></i> View 360°</a>'
                        : '<a href="' + imgSrc + '" target="_blank" class="btn btn-sm btn-outline-secondary mt-1 w-100"><i class="bi bi-zoom-in"></i> View</a>';

                    grid.append(
                        '<div class="col-6 col-sm-4 col-md-3 col-lg-2">'
                      + '  <div class="position-relative border rounded overflow-hidden" style="height:120px;background:#f5f5f5;">'
                      + badge
                      + '  <img src="' + imgSrc + '" alt="Image ' + (i+1) + '" '
                      + '       style="width:100%;height:100%;object-fit:cover;cursor:pointer;" '
                      + '       onclick="window.open(\'' + (is360 ? im.url : imgSrc) + '\',\'_blank\')" '
                      + '       onerror="this.style.display=\'none\';">'
                      + '  </div>'
                      + link
                      + '</div>'
                    );
                });
                $('#imagesCard').show();
            }

            $('#loadingState').hide();
            $('#estimateContent').show();
        },
        error: function () {
            $('#loadingState').hide();
            $('#errorState').text('Server error. Could not load estimate details.').show();
        }
    });
});

function markAsPaid() {
    Swal.fire({
        title: 'Mark as Paid?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, mark as paid',
        cancelButtonText: 'Cancel'
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $('#markPaidBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving…');

        $.ajax({
            url: baseUrl + 'admin/estimates/markPaid',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: proposalId, [csrfTokenName]: csrfHash }),
            success: function (res) {
                if (res.status) {
                    $('#markPaidBtn').removeClass('visible-el');
                    $('#paidBadge').addClass('visible-el');
                    Swal.fire({ icon: 'success', title: 'Marked as Paid', timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: res.message || 'Could not mark as paid.' });
                    $('#markPaidBtn').prop('disabled', false).html('<i class="bi bi-check-circle"></i> Mark as Paid');
                }
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Please try again.' });
                $('#markPaidBtn').prop('disabled', false).html('<i class="bi bi-check-circle"></i> Mark as Paid');
            }
        });
    });
}
</script>
