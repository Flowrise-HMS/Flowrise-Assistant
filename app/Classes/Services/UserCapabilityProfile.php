<?php

namespace Modules\AI\Classes\Services;

use Illuminate\Support\Collection;
use Modules\Core\Models\CoreUser;

class UserCapabilityProfile
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $pageContext
     */
    public function __construct(
        public int $userId,
        public string $name,
        public array $roles,
        public array $permissions,
        public ?string $branchId,
        public array $pageContext = [],
    ) {}

    public static function forUser(CoreUser $user, ?array $pageContext = null): self
    {
        $context = $pageContext ?? app(AssistantContextResolver::class)->resolve();

        return new self(
            userId: $user->id,
            name: (string) $user->name,
            roles: $user->getRoleNames()->values()->all(),
            permissions: $user->getAllPermissions()->pluck('name')->values()->all(),
            branchId: $context['branch_id'] ?? $user->branch_id,
            pageContext: $context,
        );
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return Collection::make($permissions)
            ->contains(fn (string $permission): bool => $this->hasPermission($permission));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function toPromptContext(): string
    {
        $roles = $this->roles !== [] ? implode(', ', $this->roles) : 'no role assigned';
        $permissionSample = array_slice($this->permissions, 0, 12);
        $permissionText = $permissionSample !== []
            ? implode(', ', $permissionSample).(count($this->permissions) > 12 ? '…' : '')
            : 'no explicit permissions';

        $page = $this->pageContext['page'] ?? 'unknown';
        $patientRef = $this->pageContext['patient_ref'] ?? null;
        $encounterRef = $this->pageContext['encounter_ref'] ?? null;

        $lines = [
            "You are assisting: {$this->name} ({$roles}, branch: ".($this->branchId ?? 'none').').',
            "Permissions include: {$permissionText}.",
            "Current page: {$page}.",
        ];

        if ($patientRef) {
            $lines[] = "Patient context: {$patientRef}.";
        }

        if ($encounterRef) {
            $lines[] = "Active encounter: {$encounterRef}.";
        }

        return implode("\n", $lines);
    }
}
