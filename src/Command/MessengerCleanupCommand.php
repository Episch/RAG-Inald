<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cleanup Redis Messenger Streams
 * 
 * Löscht alte Redis Streams und Consumer Groups wenn Konflikte auftreten
 */
#[AsCommand(
    name: 'app:messenger:cleanup',
    description: 'Cleanup Redis Messenger streams and consumer groups',
)]
class MessengerCleanupCommand extends Command
{
    private string $redisUrl = 'redis://localhost:6379';

    public function __construct()
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🧹 Redis Messenger Cleanup');

        try {
            // Parse Redis URL
            $parsed = parse_url($this->redisUrl);
            $host = $parsed['host'] ?? 'localhost';
            $port = $parsed['port'] ?? 6379;

            // Connect to Redis
            $redis = new \Redis();
            
            if (!$redis->connect($host, (int) $port)) {
                $io->error('Konnte nicht zu Redis verbinden: ' . $host . ':' . $port);
                return Command::FAILURE;
            }

            $io->success('✓ Redis Verbindung hergestellt');

            // List existing streams
            $io->section('Aktuelle Streams');
            $keys = $redis->keys('*');
            $messengerKeys = array_filter($keys, fn($k) => 
                str_contains($k, 'async') || 
                str_contains($k, 'failed') || 
                str_contains($k, 'messages')
            );

            if (empty($messengerKeys)) {
                $io->text('Keine Messenger-Streams gefunden.');
            } else {
                $io->listing($messengerKeys);
            }

            // Ask for confirmation
            if (!$io->confirm('Möchten Sie alle Messenger-Streams löschen?', false)) {
                $io->info('Abgebrochen.');
                return Command::SUCCESS;
            }

            // Delete streams
            $io->section('Lösche Streams');
            $deleted = 0;
            
            foreach (['messages', 'raginald_async', 'raginald_failed'] as $stream) {
                if ($redis->del($stream) > 0) {
                    $io->text("✓ Stream '{$stream}' gelöscht");
                    $deleted++;
                }
            }

            if ($deleted === 0) {
                $io->info('Keine Streams zum Löschen gefunden.');
            } else {
                $io->success("✓ {$deleted} Stream(s) gelöscht");
            }

            // Next steps
            $io->section('Nächste Schritte');
            $io->text([
                '1. Cache leeren: php bin/console cache:clear',
                '2. Worker neu starten: php bin/console messenger:consume async -vv',
                '3. Neuen Job senden und testen',
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Fehler: ' . $e->getMessage());
            $io->note('Ist Redis installiert? sudo apt-get install php-redis');
            return Command::FAILURE;
        }
    }
}

