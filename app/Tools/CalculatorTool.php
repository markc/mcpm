<?php

namespace App\Tools;

class CalculatorTool extends BaseTool
{
    public function execute(array $input): array
    {
        $operation = $this->validateEnum($input, 'operation', ['add', 'subtract', 'multiply', 'divide']);
        $a = $this->validateNumeric($input, 'a');
        $b = $this->validateNumeric($input, 'b');

        $result = match ($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $this->divide($a, $b),
            default => throw new \InvalidArgumentException("Unsupported operation: {$operation}")
        };

        return ['result' => $result];
    }

    private function divide(float $a, float $b): float
    {
        if ($b == 0) {
            throw new \InvalidArgumentException('Cannot divide by zero.');
        }

        return $a / $b;
    }
}
