<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ToolHandlerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $handlerDiscovery = app(\App\Services\HandlerDiscoveryService::class);

        $this->command->info('Discovering and registering built-in tool handlers...');

        $registered = $handlerDiscovery->discoverBuiltInHandlers();

        if (empty($registered)) {
            $this->command->info('All built-in handlers are already registered.');
        } else {
            $this->command->info('Registered '.count($registered).' built-in handlers:');
            foreach ($registered as $handler) {
                $this->command->line("  - {$handler->display_name} ({$handler->full_class_name})");
            }
        }

        // Display stats
        $stats = $handlerDiscovery->getHandlerStats();
        $this->command->info("\nHandler Statistics:");
        $this->command->line("  Total: {$stats['total']}");
        $this->command->line("  Active: {$stats['active']}");
        $this->command->line("  Built-in: {$stats['built_in']}");
        $this->command->line("  Custom: {$stats['custom']}");
        $this->command->line("  In use: {$stats['in_use']}");
    }
}
