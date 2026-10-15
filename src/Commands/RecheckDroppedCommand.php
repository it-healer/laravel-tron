<?php

namespace ItHealer\LaravelTron\Commands;

use Illuminate\Console\Command;
use ItHealer\LaravelTron\Enums\TronTransactionType;
use ItHealer\LaravelTron\Facades\Tron;
use ItHealer\LaravelTron\Models\TronAddress;
use ItHealer\LaravelTron\Models\TronTransaction;

class RecheckDroppedCommand extends Command
{
    protected $signature = 'tron:recheck-dropped
        {--address= : Only transfers of this Tron address}
        {--sleep=300 : Pause between node requests, in milliseconds}
        {--dry-run : Only report what would be restored}';

    protected $description = 'Look up outgoing transfers marked as dropped and restore the ones that were mined';

    public function handle(): int
    {
        /** @var class-string<TronAddress> $addressModel */
        $addressModel = config('tron.models.address');

        $transactions = TronTransaction::query()
            ->where('type', TronTransactionType::OUTGOING)
            ->whereNull('block_number')
            ->whereNotNull('dropped_at')
            ->when($this->option('address'), fn ($query, $address) => $query->where('address', $address))
            ->orderBy('time_at')
            ->get();

        $this->line('Dropped outgoing transfers to check: '.$transactions->count());

        $sleep = max((int) $this->option('sleep'), 0);
        $dryRun = (bool) $this->option('dry-run');
        $apis = [];
        $restored = $missing = $errors = 0;

        foreach ($transactions as $transaction) {
            $address = $addressModel::query()->with('wallet.node')->where('address', $transaction->address)->first();
            $node = $address?->wallet?->node ?? Tron::getNode();
            $api = $apis[$node->getKey()] ??= $node->api();

            try {
                $status = $api->getTransferStatus($transaction->txid);
                $node->increment('requests', 1);
            } catch (\Exception $e) {
                $errors++;
                $this->error($transaction->txid.': '.$e->getMessage());
                usleep($sleep * 1000);

                continue;
            }

            if ($status['blockNumber']) {
                $restored++;
                $this->info($transaction->txid.': block '.$status['blockNumber'].($status['failed'] ? ' (failed)' : ''));

                if (! $dryRun) {
                    $transaction->update([
                        'block_number' => $status['blockNumber'],
                        'failed' => $status['failed'],
                        'dropped_at' => null,
                    ]);
                }
            } else {
                $missing++;
                $this->line($transaction->txid.': not found on chain, stays dropped');
            }

            usleep($sleep * 1000);
        }

        $this->line(($dryRun ? 'Would restore: ' : 'Restored: ').$restored.', not on chain: '.$missing.', errors: '.$errors);

        return self::SUCCESS;
    }
}
