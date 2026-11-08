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
        return $this->db->table('accessory')->where('acc_Status', 1)->get()->getResultArray();
    }

    /**
     * Build a unique camelCase key from the name: "Pull Out" => "pullOut".
     * The API reads the count from item field "<key>Count" (e.g. pullOutCount).
     */
    public function generateKey(string $name): string
    {
        $words = preg_split('/[^a-zA-Z0-9]+/', strtolower($name), -1, PREG_SPLIT_NO_EMPTY);
        $key   = '';
        foreach ($words as $i => $w) {
            $key .= $i === 0 ? $w : ucfirst($w);
        }
        if ($key === '' || ctype_digit($key[0])) {
            $key = 'acc' . ucfirst($key);
        }

        $base = $key;
        $n    = 2;
        while ($this->db->table('accessory')->where('acc_Key', $key)->countAllResults() > 0) {
            $key = $base . $n++;
        }
        return $key;
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
            $builder->where("REPLACE(REPLACE(a.acc_Name, ' ', ''), '\t', '') LIKE '%$escaped%'", null, false);
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

        $columns = ['a.acc_Id', 'a.acc_Name', 'a.acc_Key', 'a.acc_Price', 'a.acc_Status'];
        if (!empty($postData['order']) && isset($columns[$postData['order'][0]['column']])) {
            $dir = strtolower($postData['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';
            $builder->orderBy($columns[$postData['order'][0]['column']], $dir);
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
