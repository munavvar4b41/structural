<?php

namespace App\Enums;

enum TaskPriorityColor: string
{
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Yellow = 'yellow';
    case Lime = 'lime';
    case Green = 'green';
    case Teal = 'teal';
    case Cyan = 'cyan';
    case Blue = 'blue';
    case Indigo = 'indigo';
    case Purple = 'purple';
    case Pink = 'pink';
    case Rose = 'rose';
    case Gray = 'gray';

    public function label(): string
    {
        return match ($this) {
            self::Red => __('Red'),
            self::Orange => __('Orange'),
            self::Amber => __('Amber'),
            self::Yellow => __('Yellow'),
            self::Lime => __('Lime'),
            self::Green => __('Green'),
            self::Teal => __('Teal'),
            self::Cyan => __('Cyan'),
            self::Blue => __('Blue'),
            self::Indigo => __('Indigo'),
            self::Purple => __('Purple'),
            self::Pink => __('Pink'),
            self::Rose => __('Rose'),
            self::Gray => __('Gray'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(static fn (self $color): array => [
                'value' => $color->value,
                'label' => $color->label(),
            ])
            ->all();
    }
}
