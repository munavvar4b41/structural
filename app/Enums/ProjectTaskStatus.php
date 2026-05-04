<?php

namespace App\Enums;

enum ProjectTaskStatus: string
{
    case Backlog = 'backlog';
    case ToDo = 'to_do';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Review = 'review';
    case Done = 'done';
    case Cancelled = 'cancelled';

    /**
     * Default status for newly created tasks.
     */
    public static function defaultForNew(): self
    {
        return self::ToDo;
    }

    /**
     * Ordered statuses for board columns (left to right).
     *
     * @return list<self>
     */
    public static function boardOrder(): array
    {
        return [
            self::Backlog,
            self::ToDo,
            self::InProgress,
            self::Blocked,
            self::Review,
            self::Done,
            self::Cancelled,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Backlog => __('Backlog'),
            self::ToDo => __('To do'),
            self::InProgress => __('In progress'),
            self::Blocked => __('Blocked'),
            self::Review => __('Review'),
            self::Done => __('Done'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
