<?php

namespace App\Enums;

enum TaskPriorityShade: string
{
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::Light => __('Light'),
            self::Dark => __('Dark'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(static fn (self $shade): array => [
                'value' => $shade->value,
                'label' => $shade->label(),
            ])
            ->all();
    }
}
