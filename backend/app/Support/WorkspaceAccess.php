<?php

namespace App\Support;

use App\Models\User;

class WorkspaceAccess
{
    public static function assertAssignment(User $user, array $values): void
    {
        if ($user->isCeo()) return;
        if (!empty($values['team_id'])) abort_unless($user->teams()->where('teams.id', $values['team_id'])->exists(), 403, 'Choose one of your teams.');
        $owners = collect($values['assignee_ids'] ?? [])->merge(!empty($values['assigned_to']) ? [$values['assigned_to']] : [])->unique();
        $allowed = User::where('id', $user->id)->orWhereHas('teams', fn ($teams) => $teams->whereIn('teams.id', $user->teams()->pluck('teams.id')))->pluck('id');
        abort_unless($owners->diff($allowed)->isEmpty(), 403, 'Choose an owner from your teams.');
    }
}
