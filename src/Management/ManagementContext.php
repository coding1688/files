<?php

namespace Coding1688\Files\Management;

class ManagementContext
{
    public function actorId(): int|string|null
    {
        return auth()->guard(config('files.management.guard'))->id();
    }

    public function authorize(string $action): void {}

    public function audit(string $action, ?string $subject = null, array $changes = []): void
    {
        event('files.management.audit', [$action, $subject, $changes, $this->actorId()]);
    }
}
