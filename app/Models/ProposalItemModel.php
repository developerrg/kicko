<?php

namespace App\Models;

use CodeIgniter\Model;

class ProposalItemModel extends Model
{
    protected $table         = 'kico_proposal_item';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'proposal_id', 'is_category',
        'sl_no', 'description',
        'qty', 'unit', 'texture_code', 'handle_type',
        'cupboard_type', 'size', 'design_code', 'door_state', 'rate', 'amount', 'amount_text',
        'sort_order', 'status', 'created_at',
    ];

    public function getItemsByProposal(int $proposalId): array
    {
        return $this->where('proposal_id', $proposalId)
                    ->where('status', 1)
                    ->orderBy('sort_order', 'ASC')
                    ->findAll();
    }

    public function insertItems(int $proposalId, array $items): bool
    {
        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach ($items as $i => $item) {
            $isCat     = !empty($item['isCategory']);
            $rawAmount = $item['amount'] ?? null;

            $rows[] = [
                'proposal_id' => $proposalId,
                'is_category' => $isCat ? 1 : 0,
                'sl_no'       => $isCat ? null : ($item['slNo'] ?? null),
                'description' => $item['description'] ?? null,
                'qty'          => $isCat ? null : ($item['qty']  ?? null),
                'unit'         => $isCat ? null : ($item['unit'] ?? null),
                'texture_code' => $isCat ? null : ($item['textureCode'] ?? null),
                'handle_type'  => $isCat ? null : ($item['handleType']  ?? null),
                'cupboard_type' => $isCat ? null : ($item['cupboardType'] ?? null),
                'size'          => $isCat ? null : ($item['size']         ?? null),
                'design_code'   => $isCat ? null : ($item['designCode']   ?? null),
                'door_state'    => $isCat ? null : ($item['doorState']    ?? null),
                'rate'         => $isCat ? null : (isset($item['rate']) && is_numeric($item['rate']) ? $item['rate'] : null),
                'amount'      => (!$isCat && is_numeric($rawAmount)) ? (float)$rawAmount : null,
                'amount_text' => (!$isCat && !is_numeric($rawAmount) && $rawAmount !== null) ? (string)$rawAmount : null,
                'sort_order'  => $i,
                'status'      => 1,
                'created_at'  => $now,
            ];
        }

        return $this->insertBatch($rows) !== false;
    }
}
