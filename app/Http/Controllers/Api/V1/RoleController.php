<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function createMeta(): JsonResponse
    {
        $legacyOrder = config('selloff.legacy_role_permissions', []);
        $fromDatabase = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        $ordered = array_values(array_unique([
            ...$legacyOrder,
            ...array_diff($fromDatabase, $legacyOrder),
        ]));

        return ApiResponse::success([
            'permissions' => $ordered,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = Role::query()
            ->where('guard_name', 'web')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->with('permissions:id,name')
            ->withCount('permissions')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        $paginator->through(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'permissions_count' => $role->permissions_count,
            'permissions' => $role->name === 'super-admin'
                ? config('selloff.legacy_role_permissions', [])
                : $role->permissions->pluck('name')->values()->all(),
        ]);

        return ApiResponse::success($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        abort_if(in_array($validated['name'], ['super-admin'], true), 422, 'Reserved role name.');

        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return ApiResponse::success($role->load('permissions'), 201);
    }

    public function show(Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'web', 404);

        $role->loadMissing('permissions:id,name');

        return ApiResponse::success([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
            ],
            'permissions' => $role->name === 'super-admin'
                ? config('selloff.legacy_role_permissions', [])
                : $role->permissions->pluck('name')->values(),
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        abort_if($role->name === 'super-admin', 422, 'Cannot modify the super-admin role.');

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        abort_if(in_array($role->name, ['admin', 'member'], true) && array_key_exists('permissions', $validated), 422, 'Cannot modify system role permissions.');

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }

        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        return ApiResponse::success($role->fresh()->load('permissions'));
    }

    public function destroy(Role $role): JsonResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        abort_if(in_array($role->name, ['super-admin', 'admin', 'vendor', 'member'], true), 422, 'Cannot delete a system role.');

        $role->delete();

        return ApiResponse::success(message: 'Deleted.');
    }
}
