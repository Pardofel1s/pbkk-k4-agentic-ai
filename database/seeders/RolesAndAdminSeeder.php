<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles, permissions and the single admin account (see docs/rbac.md).
 * Idempotent: safe to run on every NativePHP boot.
 */
class RolesAndAdminSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    public const ROLES = [
        'admin' => [
            'users.manage',
            'targets.create',
            'targets.verify',
            'targets.view-any',
            'chat.view-any',
            'reports.view',
            'certificates.issue',
        ],
        'analyst' => [
            'targets.view-any',
            'scans.run',
            'chat.use',
            'findings.manage',
            'reports.view',
            'comments.create',
        ],
        'client' => [
            'targets.create',
            'reports.view',
            'comments.create',
        ],
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (collect(self::ROLES)->flatten()->unique() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (array_keys(self::ROLES) as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Spatie flushes its cache on model events, which WithoutModelEvents seeders switch off.
        $registrar->forgetCachedPermissions();

        foreach (self::ROLES as $role => $permissions) {
            Role::findByName($role, 'web')->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();

        $this->seedAdmin();
    }

    private function seedAdmin(): void
    {
        $config = config('auth.admin');
        $admin = User::query()->firstWhere('email', $config['email']);

        if ($admin === null) {
            if (blank($config['password'])) {
                throw new RuntimeException('ADMIN_PASSWORD is empty. Set it in .env before seeding or building the app.');
            }

            $admin = User::query()->create([
                'name' => $config['name'],
                'email' => $config['email'],
                'password' => $config['password'],
            ]);
            $admin->forceFill(['email_verified_at' => now()])->save();
        }

        $admin->syncRoles('admin');
    }
}
