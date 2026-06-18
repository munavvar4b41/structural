<?php

namespace App\Enums;

enum JobApplicationStatus: string
{
    case Received = 'received';
    case Screening = 'screening';
    case Interview = 'interview';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Screening => 'Screening',
            self::Interview => 'Interview',
            self::Offer => 'Offer',
            self::Hired => 'Hired',
            self::Rejected => 'Rejected',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Hired, self::Rejected], true);
    }

    public function canAdvance(): bool
    {
        return ! $this->isTerminal();
    }

    public function nextStage(): ?self
    {
        return match ($this) {
            self::Received => self::Screening,
            self::Screening => self::Interview,
            self::Interview => self::Offer,
            self::Offer => self::Hired,
            self::Hired, self::Rejected => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
