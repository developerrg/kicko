<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Assigns sequential product codes (CB01, CB02, ...) to every ACTIVE product (pr_Status = 1),
 * ordered by pr_Id. Old codes are saved in the product_code_backup table first.
 *
 *   php spark products:assign-codes                 dry run - shows the plan only
 *   php spark products:assign-codes --apply         writes the codes
 *   php spark products:assign-codes --prefix CB --start 1 --width 2 --apply
 *   php spark products:assign-codes --restore       puts the backed-up old codes back
 */
class AssignProductCodes extends BaseCommand
{
    protected $group       = 'Kico';
    protected $name        = 'products:assign-codes';
    protected $description = 'Assign sequential codes (CB01, CB02, ...) to all active products.';
    protected $usage       = 'products:assign-codes [--apply] [--prefix CB] [--start 1] [--width 2] [--restore]';
    protected $options     = [
        '--apply'   => 'Write the changes (without it, only a dry run is printed).',
        '--prefix'  => 'Code prefix, letters/numbers only. Default: CB',
        '--start'   => 'First number. Default: 1',
        '--width'   => 'Minimum digits, zero padded. Default: 2 (CB01 ... CB99, CB100)',
        '--restore' => 'Restore the old codes saved in product_code_backup.',
    ];

    private const BACKUP_TABLE = 'product_code_backup';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        if (CLI::getOption('restore') !== null) {
            return $this->restore($db);
        }

        $prefix = (string)(CLI::getOption('prefix') ?? 'CB');
        $start  = (int)(CLI::getOption('start') ?? 1);
        $width  = (int)(CLI::getOption('width') ?? 2);
        $apply  = CLI::getOption('apply') !== null;

        if (!preg_match('/^[A-Za-z0-9]+$/', $prefix) || $start < 0 || $width < 1) {
            CLI::error('Invalid options: prefix must be letters/numbers only, start >= 0, width >= 1.');
            return EXIT_USER_INPUT;
        }

        $products = $db->table('product')
            ->select('pr_Id, pr_Name, pr_Code')
            ->where('pr_Status', 1)
            ->orderBy('pr_Id', 'ASC')
            ->get()->getResultArray();

        if (!$products) {
            CLI::write('No active products found.', 'yellow');
            return;
        }

        // Build the plan
        $plan = [];
        $n    = $start;
        foreach ($products as $p) {
            $plan[] = [
                'id'   => (int)$p['pr_Id'],
                'name' => $p['pr_Name'],
                'old'  => $p['pr_Code'],
                'new'  => $prefix . str_pad((string)$n, $width, '0', STR_PAD_LEFT),
            ];
            $n++;
        }

        // A target code must not already belong to a product that is NOT being re-coded
        $activeIds = array_column($plan, 'id');
        $newCodes  = array_column($plan, 'new');
        $clash = $db->table('product')
            ->select('pr_Id, pr_Name, pr_Code, pr_Status')
            ->whereIn('pr_Code', $newCodes)
            ->whereNotIn('pr_Id', $activeIds)
            ->get()->getResultArray();
        if ($clash) {
            CLI::error('Aborted - these codes are already used by non-active products:');
            foreach ($clash as $c) {
                CLI::write("  {$c['pr_Code']}  (product #{$c['pr_Id']} {$c['pr_Name']}, status {$c['pr_Status']})");
            }
            CLI::write('Use --prefix / --start to pick a different range.');
            return EXIT_ERROR;
        }

        CLI::write(($apply ? 'APPLYING' : 'DRY RUN') . ' - ' . count($plan) . ' active product(s):', 'cyan');
        CLI::table(
            array_map(static fn($r) => [$r['id'], $r['name'], $r['old'] ?? '(none)', $r['new'], $r['old'] === $r['new'] ? 'same' : 'change'], $plan),
            ['ID', 'Name', 'Old code', 'New code', '']
        );

        if (!$apply) {
            CLI::write('Nothing written. Re-run with --apply to save these codes.', 'yellow');
            return;
        }

        $db->query('CREATE TABLE IF NOT EXISTS ' . self::BACKUP_TABLE . ' (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            pr_Id INT NOT NULL,
            old_code VARCHAR(256) NULL,
            new_code VARCHAR(256) NOT NULL,
            batch VARCHAR(32) NOT NULL,
            changed_on DATETIME NOT NULL,
            INDEX (pr_Id), INDEX (batch)
        )');

        $batch = date('YmdHis');
        $now   = date('Y-m-d H:i:s');
        $db->transStart();
        $changed = 0;
        foreach ($plan as $r) {
            if ($r['old'] === $r['new']) {
                continue;
            }
            $db->table(self::BACKUP_TABLE)->insert([
                'pr_Id' => $r['id'], 'old_code' => $r['old'], 'new_code' => $r['new'], 'batch' => $batch, 'changed_on' => $now,
            ]);
            // Two-step safety is not needed: targets cannot collide with any other product (checked above),
            // and old codes of the active products are replaced one by one in id order.
            $db->table('product')->where('pr_Id', $r['id'])->update([
                'pr_Code' => $r['new'], 'pr_modifyon' => $now,
            ]);
            $changed++;
        }
        $db->transComplete();

        if (!$db->transStatus()) {
            CLI::error('Failed - nothing was changed (rolled back). ' . json_encode($db->error()));
            return EXIT_ERROR;
        }

        CLI::write("Done: {$changed} product code(s) updated. Old codes saved in " . self::BACKUP_TABLE . " (batch {$batch}).", 'green');
        CLI::write('Undo with: php spark products:assign-codes --restore', 'light_gray');
    }

    private function restore($db)
    {
        if (!$db->tableExists(self::BACKUP_TABLE)) {
            CLI::error('No backup table found - nothing to restore.');
            return EXIT_ERROR;
        }
        $last = $db->table(self::BACKUP_TABLE)->selectMax('batch')->get()->getRow()->batch ?? null;
        if (!$last) {
            CLI::error('Backup table is empty.');
            return EXIT_ERROR;
        }
        $rows = $db->table(self::BACKUP_TABLE)->where('batch', $last)->get()->getResultArray();

        $db->transStart();
        // clear the new codes first so restored old codes can never collide with them
        foreach ($rows as $r) {
            $db->table('product')->where('pr_Id', $r['pr_Id'])->update(['pr_Code' => null]);
        }
        foreach ($rows as $r) {
            $db->table('product')->where('pr_Id', $r['pr_Id'])->update(['pr_Code' => $r['old_code'], 'pr_modifyon' => date('Y-m-d H:i:s')]);
        }
        $db->transComplete();

        if (!$db->transStatus()) {
            CLI::error('Restore failed - rolled back. ' . json_encode($db->error()));
            return EXIT_ERROR;
        }
        $db->table(self::BACKUP_TABLE)->where('batch', $last)->delete();
        CLI::write(count($rows) . " product code(s) restored from batch {$last}.", 'green');
    }
}
