<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\RedisService;
use App\Models\UrlModel;
use App\Models\ClickLogModel;
use Exception;

class QueueProcessor extends BaseCommand
{
    protected $group       = 'Queue';
    protected $name        = 'queue:process';
    protected $description = 'Processes click logs from the Redis queue and batch-inserts them into MySQL.';

    public function run(array $params)
    {
        $redis = new RedisService();
        if (!$redis->isEnabled()) {
            CLI::error('Redis is disabled or not running. Queue processor cannot run.');
            return;
        }

        CLI::write('Queue Processor is running. Press Ctrl+C to stop...', 'green');

        $urlModel = new UrlModel();
        $clickLogModel = new ClickLogModel();

        // Run daemon loop
        // Allow running once with --once or once parameter
        $once = in_array('--once', $params) || in_array('once', $params);

        do {
            $logsToProcess = [];
            $clicksToIncrement = []; // url_id => count

            // Pop up to 100 items from queue
            for ($i = 0; $i < 100; $i++) {
                $item = $redis->rpop('click_logs_queue');
                if (!$item) {
                    break;
                }
                
                $log = json_decode($item, true);
                if ($log) {
                    $logsToProcess[] = [
                        'url_id'     => $log['url_id'],
                        'ip_address' => $log['ip_address'],
                        'user_agent' => $log['user_agent'],
                        'referrer'   => $log['referrer'],
                        'clicked_at' => $log['clicked_at']
                    ];

                    $urlId = $log['url_id'];
                    if (!isset($clicksToIncrement[$urlId])) {
                        $clicksToIncrement[$urlId] = 0;
                    }
                    $clicksToIncrement[$urlId]++;
                }
            }

            if (!empty($logsToProcess)) {
                try {
                    // Batch insert click logs
                    $clickLogModel->insertBatch($logsToProcess);
                    CLI::write('Successfully processed ' . count($logsToProcess) . ' click logs.', 'yellow');

                    // Batch update click counts in urls table
                    foreach ($clicksToIncrement as $urlId => $count) {
                        $urlModel->where('id', $urlId)->increment('clicks_count', $count);
                    }
                } catch (Exception $e) {
                    CLI::error('Database Error: ' . $e->getMessage());
                }
            }

            if ($once) {
                break;
            }

            // Sleep for 2 seconds before checking again
            sleep(2);
        } while (true);
    }
}
