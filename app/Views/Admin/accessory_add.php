<div class="pcoded-content">
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="page-header-title">
                        <h5 class="m-b-10"><?= isset($accessory) ? 'Update Accessory' : 'Add Accessory'; ?></h5>
                        <p class="m-b-0">Welcome to Kico Admin Control Panel</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <ul class="breadcrumb-title">
                        <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard'); ?>"> <i class="fa fa-home"></i> </a></li>
                        <li class="breadcrumb-item"><a href="#"><?= isset($accessory) ? 'Update Accessory' : 'Add Accessory'; ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="pcoded-inner-content">
        <div class="main-body">
            <div class="page-wrapper">
                <div class="page-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="row"><div id="messageBox" class="alert alert-success" style="display: none;"></div></div>
                                </div>
                                <div class="card-block">
                                    <form name="createAccessory" id="createAccessory" method="post">
                                        <div class="form-group row">
                                            <label class="col-sm-2 col-form-label">Accessory Name <span style="color: red;">*</span></label>
                                            <div class="col-sm-6">
                                                <input type="text" name="accessory_name" id="accessoryName" class="form-control"
                                                    value="<?= isset($accessory) ? esc($accessory['acc_Name']) : '' ?>"
                                                    placeholder="e.g. Door, Drawer">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-sm-2 col-form-label">Price <span style="color: red;">*</span></label>
                                            <div class="col-sm-6">
                                                <input type="number" min="0" step="0.01" class="form-control" name="price" id="accessoryPrice"
                                                    value="<?= isset($accessory) ? esc($accessory['acc_Price']) : '' ?>"
                                                    placeholder="Price per unit">
                                            </div>
                                        </div>
                                        <div class="row justify-content-center">
                                            <input type="hidden" name="acc_id" value="<?= isset($accessory['acc_Id']) ? esc($accessory['acc_Id']) : '' ?>">
                                            <div class="button-group">
                                                <button type="button" class="btn btn-secondary"
                                                    onclick="window.location.href='<?= base_url('admin/accessory'); ?>'">
                                                    <i class="bi bi-x-circle"></i> Discard
                                                </button>
                                                <button type="button" class="btn btn-primary" id="accessorySubmit" name="accessorySubmit">
                                                    <i class="bi bi-check-circle"></i> <?= isset($accessory) ? 'Update' : 'Save'; ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="styleSelector"> </div>
        </div>
    </div>
</div>
