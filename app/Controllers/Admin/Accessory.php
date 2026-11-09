<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Accessory extends BaseController
{
    protected $session;
    protected $input;
    protected $accessoryModel;

    public function __construct()
    {
        $this->session        = \Config\Services::session();
        $this->input          = \Config\Services::request();
        $this->accessoryModel = new \App\Models\Admin\AccessoryModel();
    }

    private function render(string $view, array $data = [])
    {
        $template  = view('Admin/common/header');
        $template .= view('Admin/common/leftmenu');
        $template .= view($view, $data);
        $template .= view('Admin/common/footer');
        $template .= view('Admin/page_scripts/accessoryjs');
        return $template;
    }

    public function index()
    {
        if (!$this->session->get('ad_uid')) {
            return redirect()->to(base_url('admin'));
        }
        return $this->render('Admin/accessory');
    }

    public function addAccessory($acc_id = null)
    {
        if (!$this->session->get('ad_uid')) {
            return redirect()->to(base_url('admin/accessory'));
        }

        $data = ['next_code' => $this->accessoryModel->nextAccessoryCode()]; // preview only
        if ($acc_id) {
            $acc = $this->accessoryModel->getAccessoryById($acc_id);
            if (!$acc) {
                return redirect()->to('admin/accessory')->with('error', 'Accessory Not Found');
            }
            $data['accessory'] = (array) $acc;
        }
        return $this->render('Admin/accessory_add', $data);
    }

    public function saveAccessory()
    {
        if (!$this->session->get('ad_uid')) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Session expired.']);
        }

        $acc_id = $this->input->getPost('acc_id'); // the code is never read from the form
        $name   = ucwords(strtolower(trim((string)$this->input->getPost('accessory_name'))));
        $price  = trim((string)$this->input->getPost('price'));

        if ($name === '' || $price === '') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'All Fields Are Required.']);
        }
        if (!preg_match('/^[a-zA-Z0-9 _-]+$/', $name)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid Accessory Name.']);
        }
        if (!is_numeric($price) || (float)$price < 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Price must be a number of 0 or more.']);
        }
        if ($this->accessoryModel->isAccessoryExists($name, $acc_id)) {
            return $this->response->setJSON([
                'status'  => 'error',
                'field'   => 'accessory_name',
                'message' => 'Accessory Name Already Exists.',
            ]);
        }

        $uid = $this->session->get('ad_uid');

        if (empty($acc_id)) {
            // Code is generated here (AC01, AC02, ...). If two admins save at the same moment the
            // unique index rejects the second insert, so take the next number and try again.
            $row = [
                'acc_Name'      => $name,
                'acc_Price'     => round((float)$price, 2),
                'acc_Status'    => 1,
                'acc_createdon' => date('Y-m-d H:i:s'),
                'acc_createdby' => $uid,
                'acc_modifyon'  => date('Y-m-d H:i:s'),
                'acc_modifyby'  => $uid,
            ];
            $saved = false;
            for ($attempt = 0; $attempt < 5 && !$saved; $attempt++) {
                $row['acc_Code'] = $this->accessoryModel->nextAccessoryCode();
                try {
                    $saved = (bool)$this->accessoryModel->accessoryInsert($row);
                    if (!$saved) {
                        break;
                    }
                } catch (\Throwable $e) {
                    if (stripos($e->getMessage(), 'Duplicate entry') === false) {
                        throw $e;
                    }
                }
            }
            if (!$saved) {
                return $this->response->setJSON(['status' => 'error', 'message' => 'Could not save the accessory. Please try again.']);
            }
            return $this->response->setJSON([
                'status' => 1,
                'msg'    => 'Accessory Created Successfully. Code: ' . $row['acc_Code'],
                'acc_code' => $row['acc_Code'],
            ]);
        }

        if (!$this->accessoryModel->getAccessoryById($acc_id)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Accessory Not Found.']);
        }

        // The code is never changed on edit (apps reference it).
        $this->accessoryModel->updateAccessory($acc_id, [
            'acc_Name'     => $name,
            'acc_Price'    => round((float)$price, 2),
            'acc_modifyon' => date('Y-m-d H:i:s'),
            'acc_modifyby' => $uid,
        ]);
        return $this->response->setJSON(['status' => 1, 'msg' => 'Accessory Updated Successfully.']);
    }

    public function changeStatus()
    {
        $id        = $this->request->getPost('acc_Id');
        $newStatus = (int)$this->request->getPost('acc_Status');

        if (!$this->accessoryModel->getAccessoryById($id) || !in_array($newStatus, [1, 2], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Accessory Not Found']);
        }

        $this->accessoryModel->updateAccessory($id, [
            'acc_Status'   => $newStatus,
            'acc_modifyon' => date('Y-m-d H:i:s'),
            'acc_modifyby' => $this->session->get('ad_uid'),
        ]);

        return $this->response->setJSON([
            'success'    => true,
            'message'    => 'Accessory Status Updated Successfully!',
            'new_status' => $newStatus,
        ]);
    }

    public function deleteAccessory($acc_id)
    {
        if (!$acc_id || !$this->accessoryModel->getAccessoryById($acc_id)) {
            return $this->response->setJSON(['status' => 0, 'message' => 'Invalid Accessory ID.']);
        }

        $this->accessoryModel->deleteAccessoryById($acc_id, $this->session->get('ad_uid'));
        return $this->response->setJSON(['status' => 1, 'message' => 'Accessory Deleted Successfully.']);
    }

    public function ajaxList()
    {
        $data     = $this->accessoryModel->getDatatables();
        $total    = $this->accessoryModel->countAll();
        $filtered = $this->accessoryModel->countFiltered();

        foreach ($data as &$row) {
            $id = (int)$row['acc_Id'];
            $row['status_switch'] = '<div class="form-check form-switch">
              <input class="form-check-input checkactive" type="checkbox"
               id="statusSwitch-' . $id . '" value="' . $id . '" ' . ($row['acc_Status'] == 1 ? 'checked' : '') . '>
              <label class="form-check-label pl-0 label-check" for="statusSwitch-' . $id . '"></label>
              </div>';
            $row['actions'] = '<a href="' . base_url('admin/accessory/edit/' . $id) . '"><i class="bi bi-pencil-square"></i></a>&nbsp;
              <i class="bi bi-trash text-danger icon-clickable" onclick="confirmDelete(' . $id . ')"></i>';
        }

        return $this->response->setJSON([
            'draw'            => intval($this->request->getPost('draw')),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }
}
