<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Agent listing delegates to users with agent-facing roles.
 */
final class Agent
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function allForDashboard(): array
    {
        return User::agentsForSelect();
    }
}
