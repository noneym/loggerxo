<?php

namespace App\Console\Commands;

use App\Services\ClickHouse\ClickHouseClient;
use App\Services\ClickHouse\Schema;
use Illuminate\Console\Command;

class ClickHouseMigrate extends Command
{
    protected $signature = 'loggerxo:clickhouse-migrate {--fresh : Drop and recreate the tables (DESTROYS DATA)}';

    protected $description = 'Create the ClickHouse tables used by Loggerxo';

    public function handle(): int
    {
        $ch = ClickHouseClient::fromConfig();

        if (! $ch->ping()) {
            $this->error('ClickHouse is not reachable with the configured credentials.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if (! $this->confirm('This drops all event data. Continue?')) {
                return self::FAILURE;
            }
            foreach (['events', 'transactions'] as $t) {
                $ch->execute('DROP TABLE IF EXISTS '.$ch->table($t));
                $this->warn('Dropped '.$ch->table($t));
            }
        }

        foreach (Schema::statements($ch) as $name => $sql) {
            $ch->execute($sql);
            $this->info('OK  '.$name);
        }

        return self::SUCCESS;
    }
}
