<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Models\Admin\ProductModel;

class Product extends BaseController
{

    public function __construct()
    {
        $this->session = \Config\Services::session();
        $this->input = \Config\Services::request();
        $this->productModel = new \App\Models\Admin\ProductModel();
    }

    public function index()
    {
        if (!$this->session->get('ad_uid')) {
            return redirect()->to(base_url('admin'));
        }

        $allproducts = $this->productModel->getAllProducts();
        $data['product'] = $allproducts;



        $template = view('Admin/common/header');
        $template .= view('Admin/common/leftmenu');
        $template .= view('Admin/product', $data);
        $template .= view('Admin/product_add_modal');
        $template .= view('Admin/product_video_modal');
        $template .= view('Admin/common/footer');
        $template .= view('Admin/page_scripts/productjs');
        return $template;

    }
    //Product Data List ajax // Listing table data
    public function ajaxList()
    {
        $model = new \App\Models\Admin\ProductModel();
        $data = $model->getDatatables();
        $total = $model->countAll();
        $filtered = $model->countFiltered();

        foreach ($data as &$row) {
            // Default fallbacks
            $row['pr_Name'] = $row['pr_Name'] ?? 'N/A';
            $row['mrp'] = isset($row['mrp']) ? number_format($row['mrp'], 2) : 'N/A';
            $row['pr_Selling_Price'] = isset($row['pr_Selling_Price']) ? number_format($row['pr_Selling_Price'], 2) : 'N/A';

            $row['pr_Discount_Value'] = $row['pr_Discount_Value'] ?? 'N/A';
            $row['pr_Stock'] = $row['pr_Stock'] ?? 'N/A';


            $imageArray = json_decode($row['product_images'], true);
            $firstImage = (!empty($imageArray[0]['name'][0])) ? $imageArray[0]['name'][0] : null;


            if ($firstImage) {
                $imagePath = base_url('uploads/productmedia/' . $firstImage);
                $row['image'] = '
        <a href="#" class="view-large-image" data-image="' . $imagePath . '" data-bs-toggle="modal" data-bs-target="#imageModal">
            <img src="' . $imagePath . '" alt="Product Image" class="img-thumbnail" width="60">
        </a>';
            } else {
                //$row['image'] = '<span class="text-muted">No image</span>';
                $defaultImage = base_url('public/Admin/assets/images/default.jpg');
                $row['image'] = '
        <a href="#" onclick="showImageToastAtCursor(event); return false;">
            <img src="' . $defaultImage . '" alt="No Image" class="img-thumbnail" width="60">
        </a>';
            }


            // Status toggle switch
            $row['status_switch'] = '<div class="form-check form-switch">
			<input class="form-check-input checkactive"
				   type="checkbox"
				   id="statusSwitch-' . $row['pr_Id'] . '"
				   value="' . $row['pr_Id'] . '" ' . ($row['pr_Status'] == 1 ? 'checked' : '') . '>
			<label class="form-check-label pl-0 label-check"
				   for="statusSwitch-' . $row['pr_Id'] . '"></label>
		</div>';

            // Action buttons
            $row['actions'] = '
        <img class="img-size open-image-modal"
			 src="' . base_url(ASSET_PATH . 'Admin/assets/images/image_add.ico') . '"
			 alt="Image-add"
			 data-toggle="modal"
			 data-target="#exampleModal"
			 data-product-id="' . $row['pr_Id'] . '"
			 data-product-name="' . htmlspecialchars($row['pr_Name'], ENT_QUOTES) . '"
			 onclick="openProductModal(' . $row['pr_Id'] . ', \'' . addslashes($row['pr_Name']) . '\')"
			 style="cursor: pointer;">&nbsp;

      			 
		<img class="img-size open-video-modal"
			 src="' . base_url(ASSET_PATH . 'Admin/assets/images/video_add.ico') . '"
			 alt="Video-add"
			 data-toggle="modal"
			 data-target="#videoModal"
			 data-product-id="' . $row['pr_Id'] . '"
			 data-product-name="' . htmlspecialchars($row['pr_Name'], ENT_QUOTES) . '"
			 onclick="openvideoModal(' . $row['pr_Id'] . ', \'' . addslashes($row['pr_Name']) . '\')"
			 style="cursor: pointer;">     
             
		<a href="' . base_url('admin/product/edit/' . $row['pr_Id']) . '">
			<i class="bi bi-pencil-square"></i>
		</a>&nbsp;

           	<a href="">
			<i class="bi bi-card-list text-info icon-clickable"></i>
		</a>&nbsp;
        
        <i class="bi bi-trash text-danger icon-clickable" style="cursor: pointer;" 
		   onclick="confirmDelete(' . $row['pr_Id'] . ')"></i>&nbsp;';

        }

        return $this->response->setJSON([
            'draw' => intval($this->request->getPost('draw')),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data
        ]);
    }


    //Product Add
    public function addProduct($pr_id = null)
    {
        if (!$this->session->get('ad_uid')) {
            return redirect()->to(base_url('/admin/'));
        }

        $data = [];
        $data['categories'] = $this->productModel->getAllCategories();
        $data['next_code']  = $this->productModel->nextProductCode(); // preview only; final code is assigned on save

        if ($pr_id) {
            $product = $this->productModel->getProductByid($pr_id);



            if (!$pr_id) {
                return redirect()->to('admin/product')->with('error', 'product not found');
            }

            $data['product'] = (array) $product;
        }

        $template = view('Admin/common/header');
        $template .= view('Admin/common/leftmenu');
        $template .= view('Admin/product_add', $data);
        $template .= view('Admin/common/footer');
        $template .= view('Admin/page_scripts/productjs');
        return $template;
    }

    public function getSubcategories()
    {
        $cat_id = $this->request->getPost('cat_id');
        $subcategories = $this->productModel->getSubcategoriesByCatId($cat_id);
        return $this->response->setJSON($subcategories);
    }


    //Product save

    // Product save
    public function saveProduct()
    {
       
        $pr_id = $this->input->getPost('pr_id');
        $sub_id = $this->input->getPost('sub_id');
        $cat_id = $this->input->getPost('cat_id');
        $product_name = trim($this->input->getPost('product_name'));
        $product_name = ucwords(strtolower(trim($product_name)));
        // Product code is system-generated (CB01, CB02, ...) and is never taken from the form:
        // new product -> next free code at save time; existing product -> keeps its stored code.
        $existingProduct = !empty($pr_id) ? $this->productModel->getProductByid($pr_id) : null;
        if (!empty($pr_id) && !$existingProduct) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Product Not Found.']);
        }
        $product_code = $existingProduct ? trim((string)$existingProduct->pr_Code) : '';

        $product_description = $this->input->getPost('product_description');

        $product_description = preg_replace_callback('/([.!?]\s*)([a-z])/',
                                            fn($m) => $m[1] . strtoupper($m[2]), 
                                            ucfirst(strtolower(trim($product_description))));

        $mrp = $this->input->getPost('mrp');
        $selling_price = $this->input->getPost('selling_price');
        $product_stock = $this->input->getPost('product_stock');
        $reset_stock = $this->input->getPost('reset_stock');
        $discount_value = $this->input->getPost('discount_value');
        $discount_type = $this->input->getPost('discount_type');
        $available_color = $this->input->getPost('aval_colors');
        $size = $this->input->getPost('size');

        $sleeve_style   = ucwords(strtolower(trim($this->request->getPost('sleeve_style'))));
        $fabric   = ucwords(strtolower(trim($this->request->getPost('fabric'))));
        $stitching   = ucwords(strtolower(trim($this->request->getPost('stitching'))));
        
        $DisCountFrom = 0;     
        
        if (empty($cat_id) || empty($product_name) || empty($product_stock) || empty($reset_stock) || empty($mrp)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'All Required Fields Must Be Filled.'
            ]);
        }
        if (!preg_match('/^[a-zA-Z0-9 _-]+$/', $product_name)) {
    return $this->response->setJSON([
        'status' => 'error',
        'field' => 'product_name',
        'message' => 'Invalid Product Name.'
    ]);
}
if(!empty($product_description)) {
if (!preg_match('/^[a-zA-Z0-9\s.,;:()\'"\/&_@+-]+$/', $product_description)) {
    return $this->response->setJSON([
        'status' => 'error',
        'field' => 'product_description',
        'message' => 'Description contains invalid characters. Allowed: letters, numbers, spaces, and basic punctuation.'
    ]);
}
}
if (!ctype_digit($product_stock)) {
    return $this->response->setJSON([
        'status' => 'error',
        'field' => 'product_stock',
        'message' => 'Product Stock Must be a Number.'
    ]);
}

// Validate reset_stock
if (!ctype_digit($reset_stock)) {
    return $this->response->setJSON([
        'status' => 'error',
        'field' => 'reset_stock',
        'message' => 'Reset Stock Must be a Number.'
    ]);
}
if(!empty($discount_value)){
if (!preg_match('/^[0-9]+$/', $discount_value)) {
    return $this->response->setJSON([
        'status' => 'error',
        'field' => 'discount_value',
        'message' => 'Please Enter a Number Value.'
    ]);
}
}

        // Check if product name already exists (excluding current ID)
        if ($this->productModel->isProductExists($product_name, $pr_id)) {
            return $this->response->setJSON([
                'status' => 'error',
                'field' => 'product_name',
                'message' => 'Product Name Already Exists.'
            ]);
        }


        if (($discount_type == "%" && $discount_value > 100) || ($discount_type == "Rs" && $discount_value >= $mrp) || ($discount_value >= $mrp)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Enter A Valid Discount'
            ]);
        }

        if (empty($discount_value) || empty($discount_type) || $discount_value == '0') {
            $sub_DiscountExist = false;
            if (!empty($sub_id)) {
                $subcategory = $this->productModel->isDiscountInSub($sub_id);

                if (($subcategory->sub_Discount_Value !== null && $subcategory->sub_Discount_Value !== '') && !empty($subcategory->sub_Discount_Type) && $subcategory->sub_Discount_Value > 0) {
                    $discount_value = $subcategory->sub_Discount_Value;
                    $discount_type = $subcategory->sub_Discount_Type;
                    if ($discount_type === '%') {
                        $selling_price = $mrp - ($mrp * $discount_value / 100);
                    } elseif ($discount_type === 'Rs') {
                        $selling_price = $mrp - $discount_value;
                    }
                    $sub_DiscountExist = true;
                    $DisCountFrom = 2;
                }
            }

            if (!$sub_DiscountExist) {
                $category = $this->productModel->isDiscountInCat($cat_id);

                if (($category->cat_Discount_Value !== null && $category->cat_Discount_Value !== '') && !empty($category->cat_Discount_Type)) {
                    $discount_value = $category->cat_Discount_Value;
                    $discount_type = $category->cat_Discount_Type;
                    if ($discount_type === '%') {
                        $selling_price = $mrp - ($mrp * $discount_value / 100);
                    } elseif ($discount_type === 'Rs') {
                        $selling_price = $mrp - $discount_value;
                    }
                    $DisCountFrom = 3;
                }
            }
        } else {
            $DisCountFrom = 1;
        }


        $data = [
            'pr_Name' => $product_name,
            'pr_Code' => $product_code,
            'pr_Description' => $product_description,
            'mrp' => $mrp,
            'pr_Selling_Price' => isset($selling_price) ? $selling_price : null,
            'pr_Discount_Value' => isset($discount_value) ? $discount_value : null,
            'pr_Discount_Type' => isset($discount_type) ? $discount_type : null,
            'cat_Id' => $cat_id,
            'sub_Id' => $sub_id,
            'pr_Stock' => $product_stock,
            'pr_Reset_Stock' => $reset_stock,
            'pr_modifyby' => $this->session->get('ad_uid'),
            'pr_modifyon' => date("Y-m-d H:i:s"),
            'discount_from' => $DisCountFrom,
        ];

        if (empty($pr_id)) {
            $data['pr_Status'] = 1; 
            $data['pr_createdon'] = date("Y-m-d H:i:s");
            $data['pr_createdby'] = $this->session->get('ad_uid');

            // Assign the next free code; if two admins save at the same moment the unique index
            // rejects the second insert, so take the next number and try again.
            $inserted = false;
            for ($attempt = 0; $attempt < 5 && !$inserted; $attempt++) {
                $data['pr_Code'] = $this->productModel->nextProductCode();
                try {
                    $inserted = (bool)$this->productModel->productInsert($data);
                    if (!$inserted) {
                        break;
                    }
                } catch (\Throwable $e) {
                    if (stripos($e->getMessage(), 'Duplicate entry') === false) {
                        throw $e;
                    }
                }
            }

            if ($inserted) {

                return $this->response->setJSON([
                    'status' => 1,
                    'msg' => 'Product Created Successfully. Code: ' . $data['pr_Code'],
                    'product_code' => $data['pr_Code'],
                    'redirect' => base_url('admin/product')
                ]);
            } else {
                return $this->response->setJSON([
                    'status' => 0,
                    'msg' => 'Product not created.',
                ]);
            }
        } else {
            if ($product_code === '') {
                $data['pr_Code'] = $this->productModel->nextProductCode(); // older product that never had a code
            }
            if ($this->productModel->updateProduct($pr_id, $data)) {

                return $this->response->setJSON([
                    'status' => 1,
                    'msg' => 'Product Updated Successfully.',
                ]);
            } else {
                return $this->response->setJSON([
                    'status' => 0,
                    'msg' => 'Product Not Updated Successfully.',
                ]);
            }
        }
    }



    //Media upload
    public function uploadMedia()
    {
        $productId = $this->request->getPost('product_id');
        $files = $this->request->getFileMultiple('files'); // Corrected

        $newFileNames = [];

        if ($files) {
            foreach ($files as $file) {
                if ($file->isValid() && !$file->hasMoved()) {
                    $name = $file->getRandomName();
                    $file->move(FCPATH . 'uploads/productmedia/', $name);
                    $newFileNames[] = $name;
                }
            }

            $productModel = new \App\Models\Admin\ProductModel();

            $existingMediaJson = $productModel->getProductImages($productId);


            $existingMedia = json_decode($existingMediaJson, true);

            $allNames = [];

            if (!empty($existingMedia) && isset($existingMedia[0]['name'])) {
                $allNames = $existingMedia[0]['name'];
            }

            $allNames = array_merge($allNames, $newFileNames);

            $updatedJson = [
                ['name' => $allNames]
            ];

            $productModel->updateProductImages($productId, json_encode($updatedJson));

            return $this->response->setJSON(['success' => true]);
        }

        return $this->response->setJSON(['success' => false]);
    }

    //get product images
    public function getProductImages($productId)
    {
        $productModel = new \App\Models\Admin\ProductModel();
        $imagesJson = $productModel->getProductImages($productId);
        $images = json_decode($imagesJson, true);

        // Extract image names
        $imageList = $images[0]['name'] ?? [];

        return $this->response->setJSON($imageList);
    }

    //Delte the whole product
    public function deleteProduct($pr_id)
    {
        if ($pr_id) {
            $modified_by = $this->session->get('ad_uid');
            $pr_status = $this->productModel->deleteProductById(3, $pr_id, $modified_by);
            if ($pr_status) {
                echo json_encode([
                    'success' => true,
                    'msg' => 'Product Deleted Successfully.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'msg' => 'Failed To Delete Product.'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'msg' => 'Invalid Request.'
            ]);
        }
    }

    //Delete the single product


    public function deleteProductImage()
    {
        $request = $this->request->getJSON(true);

        $productId = $request['product_id'] ?? null;
        $image = $request['image'] ?? null;

        if (!$productId || !$image) {
            return $this->response->setJSON(['success' => false, 'message' => 'Missing Product id Or Image.']);
        }

        // Delete from folder
        $imagePath = FCPATH . 'uploads/productmedia/' . $image;

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }

        // Delete from database
        $productModel = new \App\Models\Admin\ProductModel();
        $deleted = $productModel->delete_image($productId, $image);

        return $this->response->setJSON([
            'success' => $deleted,
            'message' => $deleted ? 'Image deleted successfully.' : 'Image Not Deleted From DB.'
        ]);
    }


    public function ProductuploadVideo()
    {
        $productId = $this->request->getPost('product_id');
        $videoFile = $this->request->getFile('video');

        // Check if video file is uploaded, valid, and not moved
        if ($videoFile && $videoFile->isValid() && !$videoFile->hasMoved()) {

            // Check file size (max 4MB = 4 * 1024 * 1024 = 4194304 bytes)
            if ($videoFile->getSize() > 10485760) { // 10 * 1024 * 1024
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Your video size is too large. Please upload a video within 10MB.'
                ]);
            }

            // Check MIME type (allow only video formats)
            $allowedMimeTypes = ['video/mp4', 'video/avi', 'video/mpeg', 'video/quicktime', 'video/x-matroska'];
            if (!in_array($videoFile->getMimeType(), $allowedMimeTypes)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Only video files are allowed. Please upload a valid video format.'
                ]);
            }

            // Proceed with storing the file
            $newName = $videoFile->getRandomName();
            $videoFile->move('uploads/productmedia', $newName);

            $productModel = new \App\Models\Admin\ProductModel();
            $productModel->updateProductVideo($productId, $newName);

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Video Uploaded Successfully.',
                'video' => $newName
            ]);
        } else {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'message' => 'Invalid Video File.'
            ]);
        }
    }

    public function deleteVideo()
    {
        $request = service('request');
        $productId = $request->getPost('product_id');
        $videoName = $request->getPost('video_name');

        if (!$productId || !$videoName) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Missing product_id or video_name'
            ]);
        }

        // Build the full path
        $filePath = FCPATH . 'uploads/productmedia/' . $videoName;

        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Failed To Delete File. Check Permissions.'
                ]);
            }
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'File Not Found On Server.'
            ]);
        }

        // Update DB to null the video field
        $productModel = new \App\Models\Admin\ProductModel();
        $updateResult = $productModel->deleteProductVideo($productId);

        if ($updateResult) {
            return $this->response->setJSON(['status' => 'success']);
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed To Update Database.'
            ]);
        }
    }


    public function getVideo()
    {
        $request = service('request');
        $productId = $request->getPost('product_id');

        $productModel = new \App\Models\Admin\ProductModel();
        $product = $productModel->getVideo($productId);

        if ($product && $product->product_video) {
            return $this->response->setJSON([
                'status' => 'success',
                'video' => $product->product_video
            ]);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => 'No Video Found']);
    }

    //ChangeStatus

    public function changeStatus()
    {
        $prId = $this->request->getPost('pr_Id');
        $newStatus = $this->request->getPost('pr_Status');

        $productModel = new \App\Models\Admin\ProductModel();
        $product = $productModel->getProductByid($prId);

        if (!$product) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Product Not Found'
            ]);
        }

        $update = $productModel->updateProduct($prId, ['pr_Status' => $newStatus]);

        if ($update) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Product Status Updated Successfully!',
                'new_status' => $newStatus
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed To Update Status'
            ]);
        }
    }

    //View Product

    public function viewProduct($id)
    {
        if (!$this->session->get('ad_uid')) {
            return redirect()->to(base_url('admin'));
        }

        $product = $this->productModel->getProductByid($id);
        $data['product'] = $product;
        // print_r($data['product']);
        // exit;

        $template = view('Admin/common/header');
        $template .= view('Admin/common/leftmenu');
        $template .= view('Admin/product_view', $data);
        $template .= view('Admin/common/footer');

        return $template;

    }

}