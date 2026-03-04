<?php

namespace App\Console\Commands;

use App\Messaging\RabbitMq\RabbitMqDeadLetterReplayer;
use Illuminate\Console\Command;

class ReplayDeadLetterQueue extends Command
{
    protected $signature = 'events:replay-dead-letter {--limit=10 : Number of messages to replay from the dead-letter queue}';

    protected $description = 'Replay messages from the RabbitMQ dead-letter queue back onto the primary exchange';

    public function __construct(protected RabbitMqDeadLetterReplayer $replayer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $this->info(sprintf('Replaying up to %d message%s from the dead-letter queue...', $limit, $limit === 1 ? '' : 's'));

        $replayed = $this->replayer->replay($limit);

        if ($replayed === 0) {
            $this->warn('No messages were replayed. Dead-letter queue may be empty.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Replayed %d message%s from the dead-letter queue.', $replayed, $replayed === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
