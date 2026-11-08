<div class="pcoded-content">
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Estimate Details</h5>
                        <p class="m-b-0">Proposal / Estimate Detail View</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <ul class="breadcrumb-title">
                        <li class="breadcrumb-item">
                            <a href="<?= base_url('admin/dashboard') ?>"><i class="fa fa-home"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="<?= base_url('admin/estimates') ?>">Estimates</a></li>
                        <li class="breadcrumb-item"><a href="#!">Detail</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="pcoded-inner-content">
        <div class="main-body">
            <div class="page-wrapper">
                <div class="page-body">

                    <!-- Back + PDF download -->
                    <div class="row mb-3">
                        <div class="col d-flex gap-2 justify-content-end align-items-center">
                            <a id="pdfDownloadBtn" href="#" target="_blank" class="btn btn-success d-none">
                                <i class="bi bi-file-earmark-pdf"></i> Download PDF
                            </a>
                            <a href="<?= base_url('admin/estimates') ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to list
                            </a>
                        </div>
                    </div>

                    <!-- Loading state -->
                    <div id="loadingState" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Loading estimate...</p>
                    </div>

                    <!-- Content (hidden until loaded) -->
                    <div id="estimateContent" style="display:none;">

                        <!-- Customer details -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header"><h6 class="mb-0">Customer Info</h6></div>
                                    <div class="card-block">
                                        <table class="table table-sm table-borderless mb-0" style="table-layout:fixed;width:100%;">
                                            <colgroup><col style="width:140px"><col></colgroup>
                                            <tr><th>Name</th><td id="cd-name" style="word-break:break-word;overflow-wrap:break-word;">—</td></tr>
                                            <tr><th>Project Name</th><td id="cd-project-name" style="word-break:break-word;overflow-wrap:break-word;">—</td></tr>
                                            <tr><th>Address</th><td id="cd-address" style="white-space:pre-wrap;word-break:break-word;overflow-wrap:break-word;">—</td></tr>
                                            <tr><th>Phone</th><td id="cd-phone">—</td></tr>
                                            <tr><th>Email</th><td id="cd-email" style="word-break:break-all;overflow-wrap:break-word;">—</td></tr>
                                            <tr><th>PIN</th><td id="cd-pin">—</td></tr>
                                            <tr id="cd-location-row" style="display:none;"><th>Location</th><td id="cd-location" style="white-space:pre-wrap;word-break:break-word;overflow-wrap:break-word;">—</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header"><h6 class="mb-0">Proposal Info</h6></div>
                                    <div class="card-block">
                                        <table class="table table-sm table-borderless mb-0" style="table-layout:fixed;width:100%;">
                                            <colgroup><col style="width:160px"><col></colgroup>
                                            <tr><th>Project Number</th><td id="cd-project-no" style="word-break:break-word;overflow-wrap:break-word;">—</td></tr>
                                            <tr><th>Date of Proposal</th><td id="cd-date">—</td></tr>
                                            <tr><th>Sales Manager</th><td id="cd-sales-manager" style="word-break:break-word;overflow-wrap:break-word;">—</td></tr>
                                            <tr><th>Service Support</th><td id="cd-service-support" style="word-break:break-word;overflow-wrap:break-word;">—</td></tr>
                                            <tr><th>Referred By</th><td id="cd-referred-by" style="word-break:break-word;overflow-wrap:break-word;">—</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items table -->
                        <div class="card mb-3">
                            <div class="card-header"><h6 class="mb-0">Line Items</h6></div>
                            <div class="card-block">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" id="itemsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Sl.No</th>
                                                <th>Product Name</th>
                                                <th>Cupboard Type</th>
                                                <th>Size (cm)</th>
                                                <th>Design</th>
                                                <th>Handle</th>
                                                <th>Door</th>
                                                <th>Texture</th>
                                                <th>Accessories</th>
                                                <th>Qty</th>
                                                <th>Unit</th>
                                                <th class="text-end">Rate (Rs.)</th>
                                                <th class="text-end">Amount (Rs.)</th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsTbody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Images -->
                        <div class="card mb-3" id="imagesCard" style="display:none;">
                            <div class="card-header"><h6 class="mb-0">Images</h6></div>
                            <div class="card-block">
                                <div class="row g-2" id="imagesGrid"></div>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="row justify-content-end mb-4">
                            <div class="col-md-5">
                                <table class="table table-bordered table-sm">
                                    <tbody id="totalsTbody"></tbody>
                                </table>
                                <div id="grandTotalWords" class="text-center fw-bold fst-italic text-muted mb-3" style="display:none;"></div>

                                <!-- Payment status -->
                                <div class="d-flex justify-content-end align-items-center gap-2 mt-2" id="paymentStatusRow" style="display:none;">
                                    <span id="paidBadge" class="badge bg-success px-3 py-2 fs-6">&#10003; Paid</span>
                                    <button id="markPaidBtn" class="btn btn-success" onclick="markAsPaid()">
                                        <i class="bi bi-check-circle"></i> Mark as Paid
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div><!-- /estimateContent -->

                    <!-- Error state -->
                    <div id="errorState" class="alert alert-danger" style="display:none;"></div>

<style>
/* Override any theme !important on badge/button inside payment row */
#paidBadge   { display: none !important; }
#markPaidBtn { display: none !important; }
#paidBadge.visible-el   { display: inline-block !important; }
#markPaidBtn.visible-el { display: inline-block !important; }
/* PDF download button */
#pdfDownloadBtn.d-none { display: none !important; }
#pdfDownloadBtn:not(.d-none) { display: inline-flex !important; align-items: center; gap: 6px; }
</style>

                </div>
            </div>
        </div>
    </div>
</div>
