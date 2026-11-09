<?php
namespace App\Models\Admin;

use CodeIgniter\Model;

class AccessoryModel extends Model
{
    protected $table      = 'accessory';
    protected $primaryKey = 'acc_Id';

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function isAccessoryExists($name, $excludeId = null)
    {
        $builder = $this->db->table('accessory');
        $builder->where('acc_Name', $name);
        $builder->where('acc_Status !=', 3);
        if ($excludeId) {
            $builder->where('acc_Id !=', $excludeId);
        }
        return $builder->get()->getRow();
    }

    public function getAccessoryById($id)
    {
        return $this->db->table('accessory')->where('acc_Id', $id)->where('acc_Status !=', 3)->get()->getRow();
    }

    /** Active accessories used for pricing in generatePDF. */
    public function getActiveAccessories(): array
    {
        return $this->db->table('accessory')->where('acc_Status', 1)->orderBy('acc_Id', 'ASC')->get()->getResultArray();
    }

    /** Next auto accessory code: AC01, AC02, ... (deleted accessories keep their number reserved). */
    public function nextAccessoryCode(string $prefix = 'AC', int $width = 2): string
    {
        $row = $this->db->query(
            'SELECT MAX(CAST(SUBSTRING(acc_Code, ?) AS UNSIGNED)) AS n FROM accessory WHERE acc_Code REGEXP ?',
            [strlen($prefix) + 1, '^' . $prefix . '[0-9]+$']
        )->getRow();
        return $prefix . str_pad((string)(((int)($row->n ?? 0)) + 1), $width, '0', STR_PAD_LEFT);
    }

    public function accessoryInsert($data)
    {
        return $this->db->table('accessory')->insert($data);
    }

    public function updateAccessory($id, $data)
    {
        return $this->db->table('accessory')->where('acc_Id', $id)->update($data);
    }

    public function deleteAccessoryById($id, $modifiedBy)
    {
        return $this->db->table('accessory')->where('acc_Id', $id)->update([
            'acc_Status'   => 3,
            'acc_modifyon' => date('Y-m-d H:i:s'),
            'acc_modifyby' => $modifiedBy,
        ]);
    }

    // ── DataTables ────────────────────────────────────────────────────────
    private function searchValue(): string
    {
        $postData = service('request')->getPost();
        return empty($postData['search']['value'])
            ? ''
            : preg_replace('/\s+/', '', $postData['search']['value']);
    }

    private function applySearch($builder, string $searchValue): void
    {
        if ($searchValue !== '') {
            $escaped = $this->db->escapeLikeString($searchValue);
            $builder->groupStart()
                ->where("REPLACE(REPLACE(a.acc_Name, ' ', ''), '\t', '') LIKE '%$escaped%'", null, false)
                ->orWhere("a.acc_Code LIKE '%$escaped%'", null, false)
                ->groupEnd();
        }
    }

    public function getDatatables()
    {
        $postData = service('request')->getPost();

        $builder = $this->db->table('accessory a');
        $builder->select('a.*');
        $builder->where('a.acc_Status !=', 3);
        $this->applySearch($builder, $this->searchValue());

        if (!empty($postData['length']) && $postData['length'] != -1) {
            $builder->limit((int)$postData['length'], (int)($postData['start'] ?? 0));
        }

        // DataTables column index => sortable column (0 Slno and 5 Action are not sortable)
        $columns = [1 => 'a.acc_Code', 2 => 'a.acc_Name', 3 => 'a.acc_Price', 4 => 'a.acc_Status'];
        $col = (int)($postData['order'][0]['column'] ?? -1);
        if (!empty($postData['order']) && isset($columns[$col])) {
            $dir = strtolower($postData['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';
            $builder->orderBy($columns[$col], $dir);
        } else {
            $builder->orderBy('a.acc_Id', 'DESC');
        }

        return $builder->get()->getResultArray();
    }

    public function countAll()
    {
        return $this->db->table('accessory')->where('acc_Status !=', 3)->countAllResults();
    }

    public function countFiltered()
    {
        $builder = $this->db->table('accessory a');
        $builder->where('a.acc_Status !=', 3);
        $this->applySearch($builder, $this->searchValue());
        return $builder->countAllResults();
    }
}
