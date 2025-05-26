<?php

namespace Database\Seeders;

use App\Models\Tool;
use Illuminate\Database\Seeder;

class ToolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tools = [
            [
                'name' => 'calculator',
                'display_name' => 'Calculator',
                'description' => 'Perform basic arithmetic operations including addition, subtraction, multiplication, and division.',
                'handler_class' => 'App\\Tools\\CalculatorTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['add', 'subtract', 'multiply', 'divide'],
                            'description' => 'The arithmetic operation to perform',
                        ],
                        'a' => [
                            'type' => 'number',
                            'description' => 'First operand',
                        ],
                        'b' => [
                            'type' => 'number',
                            'description' => 'Second operand',
                        ],
                    ],
                    'required' => ['operation', 'a', 'b'],
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'get_current_datetime',
                'display_name' => 'Get Current DateTime',
                'description' => 'Get the current date and time in a specified timezone with multiple format options.',
                'handler_class' => 'App\\Tools\\DateTimeTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'timezone' => [
                            'type' => 'string',
                            'description' => 'IANA timezone identifier (defaults to UTC)',
                            'default' => 'UTC',
                        ],
                    ],
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'echo',
                'display_name' => 'Echo',
                'description' => 'Echo back the input message for testing purposes with additional metadata.',
                'handler_class' => 'App\\Tools\\EchoTool',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => [
                            'type' => 'string',
                            'description' => 'Message to echo back',
                        ],
                        'delay' => [
                            'type' => 'number',
                            'description' => 'Optional delay in seconds (max 5)',
                            'minimum' => 0,
                            'maximum' => 5,
                        ],
                    ],
                    'required' => ['message'],
                ],
                'is_active' => true,
                'sort_order' => 3,
                'settings' => [
                    'max_delay' => 5,
                    'include_metadata' => true,
                ],
            ],
        ];

        foreach ($tools as $toolData) {
            Tool::updateOrCreate(
                ['name' => $toolData['name']],
                $toolData
            );
        }

        $this->command->info('Created '.count($tools).' tools');
    }
}
