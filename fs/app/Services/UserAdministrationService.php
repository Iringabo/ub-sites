<?php

namespace App\Services;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

class UserAdministrationService
{
    /**
     * @return array<string, array{title: string, description: string}>
     */
    public function groups(): array
    {
        return config('AuthGroups')->groups;
    }

    /**
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return config('AuthGroups')->permissions;
    }

    public function isValidGroup(string $group): bool
    {
        return array_key_exists($group, $this->groups());
    }

    public function isValidPermission(string $permission): bool
    {
        return array_key_exists($permission, $this->permissions());
    }

    /**
     * @param list<string> $groups
     * @param list<string> $permissions
     */
    public function validateSelections(array $groups, array $permissions): array
    {
        $errors = [];

        foreach ($groups as $group) {
            if (! $this->isValidGroup($group)) {
                $errors['groups'] = 'Le groupe sélectionné est invalide.';
                break;
            }
        }

        foreach ($permissions as $permission) {
            if (! $this->isValidPermission($permission)) {
                $errors['permissions'] = 'La permission sélectionnée est invalide.';
                break;
            }
        }

        return $errors;
    }

    /**
     * @param list<string> $groups
     * @param list<string> $permissions
     */
    public function wouldLockOutSelf(User $actor, array $groups, array $permissions, bool $active): bool
    {
        $userId = (int) $actor->id;

        if ($userId <= 0) {
            return false;
        }

        return ! $active
            || ! $this->selectionHasPermission($groups, $permissions, 'admin.access')
            || ! $this->selectionHasPermission($groups, $permissions, 'users.manage');
    }

    /**
     * @param list<string> $groups
     */
    public function wouldRemoveLastSuperAdmin(User $user, array $groups, bool $active): bool
    {
        $currentGroups = $user->getGroups() ?? [];

        if (! in_array('superadmin', $currentGroups, true)) {
            return false;
        }

        if ($active && in_array('superadmin', $groups, true)) {
            return false;
        }

        return $this->countActiveSuperAdmins((int) $user->id) <= 0;
    }

    public function countActiveSuperAdmins(?int $excludeId = null): int
    {
        $model = model(UserModel::class);
        $groupsTable = config('Auth')->tables['groups_users'];
        $usersTable  = config('Auth')->tables['users'];

        $model->select($usersTable . '.id')
            ->join($groupsTable, $groupsTable . '.user_id = ' . $usersTable . '.id')
            ->where($usersTable . '.active', 1)
            ->where($groupsTable . '.group', 'superadmin')
            ->groupBy($usersTable . '.id');

        if ($excludeId !== null) {
            $model->where($usersTable . '.id !=', $excludeId);
        }

        return $model->countAllResults();
    }

    /**
     * @param list<string> $groups
     * @param list<string> $permissions
     */
    private function selectionHasPermission(array $groups, array $permissions, string $permission): bool
    {
        if (in_array($permission, $permissions, true)) {
            return true;
        }

        $config = config('AuthGroups');

        foreach ($groups as $group) {
            foreach ($config->matrix[$group] ?? [] as $rule) {
                if ($this->permissionMatchesRule($permission, $rule)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function permissionMatchesRule(string $permission, string $rule): bool
    {
        if ($rule === $permission) {
            return true;
        }

        if (! str_ends_with($rule, '*')) {
            return false;
        }

        $prefix = rtrim($rule, '*');

        return str_starts_with($permission, $prefix);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    public function normalizeSelections(array $values): array
    {
        $values = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $values,
        ), static fn (string $value): bool => $value !== ''));

        return array_values(array_unique($values));
    }
}
