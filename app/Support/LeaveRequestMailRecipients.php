<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use App\Settings\LeaveSettings;
use Illuminate\Support\Str;

class LeaveRequestMailRecipients
{
    public function __construct(
        private LeaveSettings $leaveSettings,
    ) {
        //
    }

    /**
     * Unique normalized email addresses for leave submission notifications.
     *
     * @return list<string>
     */
    public function forRequester(User $requester): array
    {
        $fromSettings = collect($this->leaveSettings->notification_emails ?? [])
            ->map(static fn(mixed $email): string => Str::lower(trim((string) $email)))
            ->filter(static fn(string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL));

        $teamIds = $requester->teams()->pluck('teams.id');

        $teamHeadEmails = User::query()
            ->where('role', UserRole::TeamHead)
            ->whereHas('teams', static function ($query) use ($teamIds): void {
                $query->whereIn('teams.id', $teamIds);
            })
            ->pluck('email')
            ->map(static fn(mixed $email): string => Str::lower((string) $email));

        return $fromSettings->merge($teamHeadEmails)->unique()->sort()->values()->all();
    }
}
