<script>
var baseUrl       = "<?= base_url() ?>";
var csrfTokenName = "<?= csrf_token() ?>";
var csrfHash      = "<?= csrf_hash() ?>";

function formatIndian(num) {
    if (num === null || num === undefined || num === '') return '—';
    var n       = parseFloat(num);
    var dec     = (n % 1 !== 0) ? n.toFixed(2).split('.')[1] : null;
    var intPart = Math.floor(Math.abs(n)).toString();
    if (intPart.length <= 3) return (n < 0 ? '-' : '') + intPart + (dec ? '.' + dec : '');
    var last3 = intPart.slice(-3);
    var rest  = intPart.slice(0, -3).replace(/(\d)(?=(\d{2})+$)/g, '$1,');
    return (n < 0 ? '-' : '') + rest + ',' + last3 + (dec ? '.' + dec : '');
}

function buildItemsTable(items) {
    if (!items || items.length === 0) {
        return '<p class="child-loading">No items found.</p>';
    }
    var html = '<table class="table table-bordered child-items-table">'
             + '<thead><tr>'
             + '<th>Sl.No</th><th>Description</th><th>Qty</th><th>Unit</th>'
             + '<th class="text-end">Rate (Rs.)</th><th class="text-end">Amount (Rs.)</th>'
             + '</tr></thead><tbody>';

    items.forEach(function (row) {
        if (parseInt(row.is_category) === 1) {
            html += '<tr class="child-row-category">'
                  + '<td colspan="6">' + (row.description || '') + '</td>'
                  + '</tr>';
        } else {
            var amtRaw = (row.amount !== null && row.amount !== '') ? row.amount : row.amount_text;
            var amtStr = '';
            if (amtRaw !== null && amtRaw !== undefined && amtRaw !== '') {
                amtStr = isNaN(parseFloat(amtRaw))
                       ? '<strong>' + amtRaw + '</strong>'
                       : formatIndian(amtRaw);
            }
            html += '<tr>'
                  + '<td>' + (row.sl_no || '') + '</td>'
                  + '<td>' + (row.description || '') + '</td>'
                  + '<td class="text-center">' + (row.qty !== null ? row.qty : '') + '</td>'
                  + '<td class="text-center">' + (row.unit || '') + '</td>'
                  + '<td class="text-end">' + (row.rate !== null ? formatIndian(row.rate) : '') + '</td>'
                  + '<td class="text-end">' + amtStr + '</td>'
                  + '</tr>';
        }
    });

    html += '</tbody></table>';
    return html;
}

var itemCache = {}; // cache loaded items per proposal id

var table = $('#estimateList').DataTable({
    processing: true,
    serverSide: true,
    scrollX: true,
    order: [[9, 'desc']],
    ajax: {
        url: baseUrl + 'admin/estimates/list',
        type: 'POST',
        data: function (d) { d[csrfTokenName] = csrfHash; }
    },
    columns: [
        // 0 — expand toggle
        {
            data: null,
            orderable: false,
            searchable: false,
            className: 'dt-center',
            defaultContent: '<span class="dt-expand-btn">+</span>'
        },
        // 1 — Sl.No
        {
            data: null,
            orderable: false,
            searchable: false,
            render: function (data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            }
        },
        // 2 — Name
        {
            data: 'name',
            render: function (data, type, row) {
                var display = data || 'N/A';
                var short   = display.length > 28 ? display.substring(0, 28) + '...' : display;
                return '<a href="' + baseUrl + 'admin/estimates/view/' + row.id
                     + '" style="text-decoration:none;">' + short + '</a>';
            }
        },
        { data: 'phone' },            // 3
        { data: 'email' },            // 4
        { data: 'project_number' },   // 5
        { data: 'date_of_proposal' }, // 6
        { data: 'grand_total' },      // 7
        // 8 — Status badge
        {
            data: 'paid_status',
            orderable: false,
            searchable: false,
            render: function (data) {
                return data == 1
                    ? '<span class="badge bg-success">Paid</span>'
                    : '<span class="badge bg-warning text-dark">Unpaid</span>';
            }
        },
        { data: 'created_at' },       // 9
        // 10 — PDF
        {
            data: 'pdf_url',
            orderable: false,
            searchable: false,
            className: 'text-center',
            render: function (data) {
                if (!data) return '<span class="text-muted">—</span>';
                var file = String(data).split('/').pop();
                return '<a href="' + data + '" target="_blank" rel="noopener" title="View PDF" class="btn btn-sm btn-outline-success d-inline-flex align-items-center me-1">'
                     + '<i class="bi bi-eye"></i></a>'
                     + '<a href="' + baseUrl + 'pdf/download/' + encodeURIComponent(file) + '" title="Download PDF" class="btn btn-sm btn-success d-inline-flex align-items-center">'
                     + '<i class="bi bi-download"></i></a>';
            }
        },
        // 11 — Action
        {
            data: 'id',
            orderable: false,
            searchable: false,
            className: 'text-center',
            render: function (data) {
                return '<a href="' + baseUrl + 'admin/estimates/view/' + data
                     + '" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"><i class="fa fa-eye"></i></a>';
            }
        }
    ]
});

// Expand / collapse child row on toggle click
$('#estimateList tbody').on('click', 'span.dt-expand-btn', function () {
    var tr  = $(this).closest('tr');
    var row = table.row(tr);
    var btn = $(this);

    if (row.child.isShown()) {
        row.child.hide();
        btn.text('+').removeClass('open');
        return;
    }

    var proposalId = row.data().id;

    if (itemCache[proposalId]) {
        row.child(buildItemsTable(itemCache[proposalId])).show();
        btn.text('−').addClass('open');
        return;
    }

    // Show loading placeholder while fetching
    row.child('<p class="child-loading">Loading items…</p>').show();
    btn.text('−').addClass('open');

    $.ajax({
        url: baseUrl + 'admin/estimates/view/' + proposalId,
        type: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        data: { [csrfTokenName]: csrfHash },
        success: function (res) {
            if (res.status && res.data && res.data.items) {
                itemCache[proposalId] = res.data.items;
                row.child(buildItemsTable(res.data.items)).show();
            } else {
                row.child('<p class="child-loading text-danger">Failed to load items.</p>').show();
            }
        },
        error: function () {
            row.child('<p class="child-loading text-danger">Server error loading items.</p>').show();
        }
    });
});
</script>
