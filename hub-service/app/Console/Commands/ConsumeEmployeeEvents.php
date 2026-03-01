<?php

namespace App\Console\Commands;

use App\Messaging\RabbitMq\RabbitMqConsumer;
use Illuminate\Console\Command;

class ConsumeEmployeeEvents extends Command
{
    protected $signature = 'events:consume-employee';

    protected $description = 'Consume employee events from RabbitMQ and dispatch them to handlers';

    public function __construct(
        protected RabbitMqConsumer $consumer
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting employee event consumer...');

        $this->consumer->consume();

        return self::SUCCESS;
    }
}
