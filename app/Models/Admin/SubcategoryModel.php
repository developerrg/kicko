<?php
namespace App\Models\Admin;

use CodeIgniter\Model;

class SubcategoryModel extends Model
{

    protected $table = 'subcategory';
    protected $primaryKey = 'sub_Id'; // Fixed: Removed space
    protected $allowedFields = ['sub_Category_Name', 'sub_Discount_Value', 'sub_Discount_Type', 'sub_Status'];

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }
    public function getAllCategory()
    {
        return $this->db->table('category')->where('cat_Status', 1)->get()->getResult();
    }

    public function issubCategoryExists($sub_Name, $catId, $excludeId = null)
    {
        $builder = $this->db->table('subcategory');
        $builder->where('cat_Id', $catId);
        $builder->where('sub_Category_Name', $sub_Name);
        $builder->where('sub_Status !=', 3);

        if ($excludeId) {
            $builder->where('sub_Id !=', $excludeId);
        }

        return $builder->get()->getRow();
    }

    public function subcategoryInsert($data)
    {
        return $this->db->table('subcategory')->insert($data);
    }

    public function getAllSubcategories()
    {
        return $this->db->table('subcategory')
            ->select('subcategory.*, category.cat_Name')
            ->join('category', 'category.cat_Id = subcategory.cat_Id', 'left')
            ->where('subcategory.sub_Status !=', 3)
            ->get()
            ->getResult();
    }



    public function getsubCategoryByid($id)
    {
        return $this->db->table('subcategory')
            ->select('subcategory.*, category.cat_Name')
            ->join('category', 'category.cat_Id = subcategory.cat_Id', 'left')
            ->where('subcategory.sub_Id', $id)
            ->where('category.cat_Status <>', 3)
            ->get()
            ->getRow();
    }
    public function getsubCategory($subId)
    {
        return $this->db->query("select * from subcategory where sub_Id = '" . $subId . "' ")->getRow();
    }

   public function updateSubCategory($subId, $data)
    {
        $this->db->table('subcategory')
            ->where('sub_Id', $subId)
            ->update($data);

        $subcategory = $this->db->table('subcategory')
            ->select('sub_Discount_Value, sub_Discount_Type')
            ->where('sub_Id', $subId)
            ->where('sub_Status', 1)
            ->get()
            ->getRow();

        if (!$subcategory) {
            return false;
        }
        $products = $this->db->table('product')
            ->where('sub_Id', $subId)
            ->where('pr_Status', 1)
            ->groupStart()
            ->where('discount_from!=', 1)
            ->orWhere('pr_Discount_Value', 0)
            ->orWhere('pr_Discount_Value IS NULL', null, false)
            ->groupEnd()
            ->get()
            ->getResult();

        $sub_Discount_Value = $subcategory->sub_Discount_Value;
        $sub_Discount_Type = $subcategory->sub_Discount_Type;

        if ($sub_Discount_Value != null && $sub_Discount_Value != '' && !empty($sub_Discount_Type) && $sub_Discount_Value != '0') {

            foreach ($products as $pr) {
                $mrp = $pr->mrp;

                if ($sub_Discount_Type === '%') {
                    $sub_selling_price = $mrp - ($mrp * $sub_Discount_Value / 100);
                } elseif ($sub_Discount_Type === 'Rs') {
                    $sub_selling_price = $mrp - $sub_Discount_Value;
                }

                $this->db->table('product')
                    ->where('pr_Id', $pr->pr_Id)
                    ->update([
                        'pr_Selling_Price' => $sub_selling_price,
                        'pr_Discount_Value' => $sub_Discount_Value,
					    'pr_Discount_Type' => $sub_Discount_Type,
                        'discount_from' => "2",
                        'pr_modifyon' => date('Y-m-d H:i:s')
                    ]);
            }
        } else {
            foreach ($products as $pr) {
                $category = $this->db->table('category')
                    ->where('cat_Id ', $pr->cat_Id)
                    ->select('cat_Discount_Value , cat_Discount_Type')
                    ->get()
                    ->getRow();
                $cat_Discount_Value = $category->cat_Discount_Value;
                $cat_Discount_Type = $category->cat_Discount_Type;
                if ($cat_Discount_Value != null && $cat_Discount_Value != '' && $cat_Discount_Value != '0' && !empty($cat_Discount_Type)) {
                    $mrp = $pr->mrp;
                    if ($cat_Discount_Type === '%') {
                        $cat_selling_price = $mrp - ($mrp * $cat_Discount_Value / 100);
                    } elseif ($cat_Discount_Type === 'Rs') {
                        $cat_selling_price = $mrp - $cat_Discount_Value;
                    }
                    $this->db->table('product')
                        ->where('pr_Id', $pr->pr_Id)
                        ->update([
                            'pr_Selling_Price' => $cat_selling_price,
                            'pr_Discount_Value' => $cat_Discount_Value,
					        'pr_Discount_Type' => $cat_Discount_Type,
                            'discount_from' => "3",
                            'pr_modifyon' => date('Y-m-d H:i:s')
                        ]);
                } else {
                    foreach ($products as $pr) {
                        $mrp = $pr->mrp;
                        $this->db->table('product')
                            ->where('pr_Id', $pr->pr_Id)
                            ->update([
                                    'pr_Selling_Price' => $mrp,
                                    'pr_Discount_Value' => '0',
                                    'discount_from' => "0",
                                    'pr_modifyon' => date('Y-m-d H:i:s')
                                ]);
                    }
                }
            }
        }
        return true;
    }


 public function deleteSubcategoryById($sub_id, $modified_by)
    {
		$product = $this->db->table('product')
							->where('sub_Id', $sub_id)	
                            ->where('pr_Status!=',3)
							->select('pr_Id')
							->get()
							->getRow();
		if(empty($product)){
			return $this->db->table('subcategory')
					->where('sub_Id', $sub_id)
					->update([
						'sub_Status'   => 3,
						'sub_modifiyon' => date('Y-m-d H:i:s'),
						'sub_modifyby' => $modified_by
					]);
		}else{
			return false;
		}					
    }

		

    // DataTables: Get filtered subcategories
    public function getDatatables() {
    $builder = $this->db->table('subcategory s');
    $builder->select('s.*, c.cat_Name');
    $builder->join('category c', 'c.cat_Id = s.cat_Id', 'left');
    $builder->where('s.sub_Status !=', 3);

    $postData = service('request')->getPost();

    if (!empty($postData['search']['value'])) {
        // Remove all whitespace (space, tab, newline, etc.)
        $search = preg_replace('/\s+/', '', $postData['search']['value']);
        $escaped = $this->db->escapeLikeString($search);

        $builder->groupStart()
            ->where("REPLACE(REPLACE(s.sub_Category_Name, ' ', ''), CHAR(9), '') LIKE '%$escaped%'", null, false)
            ->orWhere("REPLACE(REPLACE(c.cat_Name, ' ', ''), CHAR(9), '') LIKE '%$escaped%'", null, false)
            ->groupEnd();
    }

    if (!empty($postData['length']) && $postData['length'] != -1) {
        $builder->limit($postData['length'], $postData['start']);
    }

    if (!empty($postData['order'])) {
        $columns = ['s.sub_Id', 's.sub_Category_Name', 's.sub_Discount_Value', 's.sub_Discount_Type', 's.sub_Status'];
        $orderCol = $columns[$postData['order'][0]['column']] ?? 's.sub_Id';
        $orderDir = $postData['order'][0]['dir'] ?? 'DESC';
        $builder->orderBy($orderCol, $orderDir);
    }

    return $builder->get()->getResultArray();
    }



    // Count all subcategories (excluding deleted)
    public function countAll()
    {
        return $this->db->table('subcategory')
            ->where('sub_Status !=', 3)
            ->countAllResults();
    }

    // Count filtered subcategories (DataTables support)
   public function countFiltered(){
    $builder = $this->db->table('subcategory s');
    $builder->join('category c', 'c.cat_Id = s.cat_Id', 'left');
    $builder->where('s.sub_Status !=', 3);

    $postData = service('request')->getPost();

    if (!empty($postData['search']['value'])) {
        $search = preg_replace('/\s+/', '', $postData['search']['value']);
        $escaped = $this->db->escapeLikeString($search);

        $builder->groupStart()
            ->where("REPLACE(REPLACE(s.sub_Category_Name, ' ', ''), CHAR(9), '') LIKE '%$escaped%'", null, false)
            ->orWhere("REPLACE(REPLACE(c.cat_Name, ' ', ''), CHAR(9), '') LIKE '%$escaped%'", null, false)
            ->groupEnd();
    }

    return $builder->countAllResults();
   }



}

?>