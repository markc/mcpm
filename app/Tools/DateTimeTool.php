<?php

namespace App\Tools;

class DateTimeTool extends BaseTool
{
    public function execute(array $input): array
    {
        $timezone = $input['timezone'] ?? 'UTC';

        try {
            $timezoneObj = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                "Invalid timezone: {$timezone}. Please use a valid IANA timezone identifier."
            );
        }

        $now = new \DateTime('now', $timezoneObj);

        return [
            'datetime_iso8601' => $now->format(\DateTime::ATOM),
            'timezone' => $timezoneObj->getName(),
            'timestamp' => $now->getTimestamp(),
            'formatted' => $now->format('Y-m-d H:i:s T'),
        ];
    }
}
