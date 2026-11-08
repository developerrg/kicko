<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Models\ProposalItemModel;

class ProposalModel extends Model
{
    protected $table         = 'kico_proposal';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'name', 'project_name', 'pin', 'address', 'phone', 'email',
        'project_number', 'date_of_proposal',
        'sales_manager', 'service_support', 'referred_by',
        'location', 'latitude', 'longitude',
        'sub_total_label', 'sub_total', 'gst_percent',
        'discount', 'grand_total', 'grand_total_words',
        'pdf_file_name', 'pdf_url',
        'status', 'paid_status', 'created_at', 'created_by', 'updated_at', 'updated_by',
    ];

    public function getAllProposals(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        $this->where('status', 1);
        $this->applyFilters($filters);
        return $this->orderBy('created_at', 'DESC')->findAll($limit, $offset);
    }

    public function countFiltered(array $filters = []): int
    {
        $this->where('status', 1);
        $this->applyFilters($filters);
        return $this->countAllResults();
    }

    private function applyFilters(array $filters): void
    {
        if (!empty($filters['name'])) {
            $this->like('name', $filters['name']);
        }
        if (!empty($filters['phone'])) {
            $this->like('phone', $filters['phone']);
        }
        if (!empty($filters['email'])) {
            $this->like('email', $filters['email']);
        }
        if (!empty($filters['project_number'])) {
            $this->like('project_number', $filters['project_number']);
        }
    }

    public function getDatatables(string $search = '', int $start = 0, int $length = 10, string $orderBy = 'created_at', string $dir = 'desc'): array
    {
        $allowedCols = ['name', 'phone', 'email', 'project_number', 'date_of_proposal', 'grand_total', 'created_at'];
        if (!in_array($orderBy, $allowedCols, true)) {
            $orderBy = 'created_at';
        }
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        // Fresh builder for each count/fetch — never reuse accumulated state
        $db = $this->db;

        // Total (no search filter)
        $total = (int)$db->table($this->table)->where('status', 1)->countAllResults();

        // Filtered count
        $qFiltered = $db->table($this->table)->where('status', 1);
        if ($search !== '') {
            $qFiltered->groupStart()
                      ->like('name', $search)
                      ->orLike('phone', $search)
                      ->orLike('email', $search)
                      ->orLike('project_number', $search)
                      ->groupEnd();
        }
        $filtered = (int)$qFiltered->countAllResults();

        // Data
        $qData = $db->table($this->table)->where('status', 1);
        if ($search !== '') {
            $qData->groupStart()
                  ->like('name', $search)
                  ->orLike('phone', $search)
                  ->orLike('email', $search)
                  ->orLike('project_number', $search)
                  ->groupEnd();
        }
        $data = $qData->orderBy($orderBy, $dir)->limit($length, $start)->get()->getResultArray();

        return ['total' => $total, 'filtered' => $filtered, 'data' => $data];
    }

    public function markAsPaid(int $id): bool
    {
        return $this->update($id, [
            'paid_status' => 1,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function getProposalWithItems(int $id): ?array
    {
        $proposal = $this->find($id);
        if (!$proposal) return null;

        $proposal['items'] = (new ProposalItemModel())->getItemsByProposal($id);
        return $proposal;
    }
}
