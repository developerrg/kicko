<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Models\Admin\SubcategoryModel;

class Subcategory extends BaseController
{

    public function __construct()
    {
        $this->session = \Config\Services::session();
        $this->input = \Config\Services::request();
        $this->subcategoryModel = new \App\Models\Admin\SubcategoryModel();
    }

    public function index()
    {
		if (!$this->session->get('ad_uid')) {
				return redirect()->to(base_url('admin'));
			}
			
		$allsubcategory = $this->subcategoryModel->getAllSubcategories();
        $data['subcategory'] =  $allsubcategory;
		

        $template = view('Admin/common/header');
		$template.= view('Admin/common/leftmenu');
		$template.= view('Admin/subcategory', $data);
        $template.= view('Admin/common/footer');
        $template.= view('Admin/page_scripts/subcategoryjs');
        return $template;

}
//Listing Datatable

	public function ajaxList()
	{
	$model = new \App\Models\Admin\SubcategoryModel();
	$data = $model->getDatatables();
	$total = $model->countAll();
	$filtered = $model->countFiltered();

	foreach ($data as &$row) {
		// Default fallbacks
		$row['cat_Name'] = $row['cat_Name'] ?? 'N/A';
		$row['sub_Category_Name'] = $row['sub_Category_Name'] ?? 'N/A';
		$row['sub_Discount_Value'] = $row['sub_Discount_Value'] ?? 'N/A';
		$row['sub_Discount_Type'] = $row['sub_Discount_Type'] ?? 'N/A';
	
		
		// Status toggle switch
		$row['status_switch'] = '<div class="form-check form-switch">
			<input class="form-check-input checkactive"
				   type="checkbox"
				   id="statusSwitch-' . $row['sub_Id'] . '"
				   value="' . $row['sub_Id'] . '" ' . ($row['sub_Status'] == 1 ? 'checked' : '') . '>
			<label class="form-check-label pl-0 label-check"
				   for="statusSwitch-' . $row['sub_Id'] . '"></label>
		</div>';

		// Action buttons
		$row['actions'] = '<a href="' . base_url('admin/subcategory/edit/' . $row['sub_Id']) . '">
				<i class="bi bi-pencil-square"></i>
			</a>&nbsp;
			<i class="bi bi-trash text-danger icon-clickable"
			   onclick="confirmDelete(' . $row['sub_Id'] . ')"></i>';
	}
	
	return $this->response->setJSON([
		'draw' => intval($this->request->getPost('draw')),
		'recordsTotal' => $total,
		'recordsFiltered' => $filtered,
		'data' => $data
	]);
	}

//Add subcategory

public function addSubcategory($sub_id = null)
{
    if (!$this->session->get('ad_uid')) {
		return redirect()->to(base_url('admin'));
	}


    $data = [];
    $data['category'] = $this->subcategoryModel->getAllCategory();

    if ($sub_id) {
        $subcat = $this->subcategoryModel->getSubcategoryByid($sub_id);
		

        if (!$subcat) {
            return redirect()->to('subcategory')->with('error', 'subcategory not found');
        }

        $data['subcategory'] = (array) $subcat;
    }

    $template = view('Admin/common/header');
    $template .= view('Admin/common/leftmenu');
    $template .= view('Admin/subcategory_add', $data); 
    $template .= view('Admin/common/footer');
    $template .= view('Admin/page_scripts/subcategoryjs');
    return $template;
}

public function saveSubcategory() {
    $sub_id = $this->input->getPost('sub_id');
    $cat_id = $this->input->getPost('cat_id');
    $subcategory_name = $this->input->getPost('subcategory_name');
	$subcategory_name = ucwords(strtolower(trim($subcategory_name)));
    $discount_value = $this->input->getPost('discount_value');
    $discount_type = $this->input->getPost('discount_type');

    if ($cat_id && $subcategory_name) {
		 if (!preg_match('/^[a-zA-Z0-9 _-]+$/', $subcategory_name)) {
        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Invalid SubCategory Name.'
        ]);
    }

        // Check if subcategory name already exists under the same category
        $exists = $this->subcategoryModel->issubCategoryExists($subcategory_name, $cat_id, $sub_id);
        if ($exists) {
            return $this->response->setJSON([
                'status' => 'error',
                'field' => 'subcategory_name',
                'message' => 'Subcategory name already exists in this category.'
            ]);
        }

        $data = [
            'cat_Id' => $cat_id,
            'sub_Category_Name' => $subcategory_name,
            'sub_Discount_Value' => $discount_value,
            'sub_Discount_Type' => $discount_type,
            'sub_Status' => 1,
            'sub_createdon' => date("Y-m-d H:i:s"),
            'sub_createdby' => $this->session->get('ad_uid'),
            'sub_modifyby' => $this->session->get('ad_uid'),
        ];

        if (empty($sub_id)) {
            $this->subcategoryModel->subcategoryInsert($data);
            return $this->response->setJSON([
                "status" => 1,
                "msg" => "Subcategory Created Successfully.",
                "redirect" => base_url('admin/subcategory')
            ]);
        } else {
            $this->subcategoryModel->updateSubcategory($sub_id, $data);
            return $this->response->setJSON([
                "status" => 1,
                "msg" => "Subcategory Updated Successfully.",
                "redirect" => base_url('admin/subcategory')
            ]);
        }
    } else {
        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'All fields are required.'
        ]);
    }
}

public function changeStatus()
{
	$subId = $this->request->getPost('sub_Id');
	$newStatus = $this->request->getPost('sub_Status');

	$subcategoryModel = new \App\Models\Admin\SubcategoryModel();
	$subcategory = $subcategoryModel->getsubCategory($subId);

	if (!$subcategory) {
		return $this->response->setJSON([
			'success' => false,
			'message' => 'Subcategory not found'
		]);
	}

	$update = $subcategoryModel->updateSubCategory($subId, ['sub_Status' => $newStatus]);

	if ($update) {
		return $this->response->setJSON([
			'success' => true,
			'message' => 'Status Updated Successfully!',
			'new_status' => $newStatus
		]);
	} else {
		return $this->response->setJSON([
			'success' => false,
			'message' => 'Status Updated Successfully!'
		]);
	}
}
//Category Delete

	public function deleteSubcategory($sub_id)
	{
		if ($sub_id) {
			$modified_by = $this->session->get('ad_uid');
			$sub_delete = $this->subcategoryModel->deleteSubcategoryById($sub_id, $modified_by);

			if ($sub_delete) {
				echo json_encode([
					'success' => true,
					'msg' => 'Subcategory Deleted Successfully.'
				]);
			} else {
				echo json_encode([
					'success' => false,
					'msg' => 'There Exists A Product Under This Subcategory.'
				]);
			}
		} else {
			echo json_encode([
				'success' => false,
				'msg' => 'Invalid request.'
			]);
		}
	}

}
?>