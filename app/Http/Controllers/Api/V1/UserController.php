<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexUsersRequest;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\MeResource;
use App\Http\Resources\Api\V1\UserAdminResource;
use App\Models\User;
use App\Modules\Auth\Actions\BuildMeContextAction;
use App\Modules\Auth\Actions\LoginUserAction;
use App\Modules\Selloff\Affiliate\Services\VendorAffiliateProgramService;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipActivationService;
use App\Services\Admin\AdminUserManagementService;
use App\Services\Admin\AdminUserSummaryService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly AdminUserManagementService $userManagement,
        private readonly VendorAffiliateProgramService $affiliateProgram,
    ) {}

    public function indexMeta(): JsonResponse
    {
        $plans = MembershipPlan::query()
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('membership_plans', 'plan_order'),
                fn ($query) => $query->orderBy('plan_order'),
                fn ($query) => $query->orderBy('title'),
            )
            ->get(['id', 'title', 'price', 'duration_days', 'is_active']);

        return ApiResponse::success([
            'roles' => Role::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(['id', 'name']),
            'membership_plans' => $plans,
            'affiliate_program_enabled' => $this->affiliateProgram->programEnabled(),
        ]);
    }

    public function index(IndexUsersRequest $request): JsonResponse
    {
        $sort = $request->string('sort', 'id')->toString();
        $direction = $request->string('direction', 'desc')->toString() === 'asc' ? 'asc' : 'desc';
        $search = trim((string) ($request->input('q') ?: $request->input('search', '')));
        $perPage = (int) ($request->input('show') ?: $request->integer('per_page', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $membershipSubquery = UserMembershipPlan::query()
            ->select('membership_plans.title')
            ->join('membership_plans', 'membership_plans.id', '=', 'user_membership_plans.membership_plan_id')
            ->whereColumn('user_membership_plans.user_id', 'users.id')
            ->where('user_membership_plans.is_active', true)
            ->orderByDesc('user_membership_plans.id')
            ->limit(1);

        $query = User::query()
            ->with(['roles', 'vendorProfile'])
            ->select('users.*')
            ->selectSub($membershipSubquery, 'membership_plan_title')
            ->when($search !== '', function (Builder $q) use ($search) {
                $term = '%'.$search.'%';
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                });
            })
            ->when($request->filled('role'), fn (Builder $q) => $q->role($request->string('role')))
            ->when($request->filled('status'), function (Builder $q) use ($request) {
                $q->where('is_banned', $request->string('status') === 'banned');
            })
            ->when($request->filled('email_status'), function (Builder $q) use ($request) {
                if ($request->string('email_status') === 'confirmed') {
                    $q->whereNotNull('email_verified_at');
                } else {
                    $q->whereNull('email_verified_at');
                }
            })
            ->when($request->has('is_enable_login'), fn (Builder $q) => $q->where('is_enable_login', $request->boolean('is_enable_login')));

        if (in_array($sort, ['email', 'first_name', 'last_name', 'created_at'], true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('id', $direction);
        }

        $paginator = $query->paginate($perPage);
        $paginator->through(fn (User $user) => new UserAdminResource($user));

        return ApiResponse::success($paginator);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $roles = $data['roles'] ?? ['member'];
        unset($data['roles'], $data['password_confirmation']);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['first_name'].'-'.$data['last_name']);
        }

        $user = User::query()->create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($roles);

        return ApiResponse::success(new UserAdminResource($user->load('roles')), 201);
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(new UserAdminResource($user->load(['roles', 'vendorProfile'])));
    }

    public function summary(User $user, AdminUserSummaryService $summary): JsonResponse
    {
        return ApiResponse::success($summary->build($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updated = $this->userManagement->updateProfile($user, $request->validated(), $request->user());

        return ApiResponse::success(new UserAdminResource($updated));
    }

    public function destroy(User $user): JsonResponse
    {
        abort_if($user->hasRole('super-admin'), 422, 'Cannot delete the super-admin account.');

        $user->delete();

        return ApiResponse::success(message: 'Deleted.');
    }

    public function confirmEmail(User $user): JsonResponse
    {
        $updated = $this->userManagement->confirmEmail($user);

        return ApiResponse::success(new UserAdminResource($updated->load(['roles', 'vendorProfile'])));
    }

    public function toggleBan(Request $request, User $user): JsonResponse
    {
        $updated = $this->userManagement->toggleBan($user, $request->user());

        return ApiResponse::success(new UserAdminResource($updated->load(['roles', 'vendorProfile'])));
    }

    public function toggleAffiliate(Request $request, User $user): JsonResponse
    {
        abort_unless($this->affiliateProgram->programEnabled(), 422, 'Affiliate program is disabled.');

        $updated = $this->userManagement->toggleAffiliate($user, $request->user());

        return ApiResponse::success(new UserAdminResource($updated->load(['roles', 'vendorProfile'])));
    }

    public function changeRole(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $updated = $this->userManagement->changeRole($user, $data['role'], $request->user());

        return ApiResponse::success(new UserAdminResource($updated->load('vendorProfile')));
    }

    public function assignMembershipPlan(
        Request $request,
        User $user,
        MembershipActivationService $activation,
    ): JsonResponse {
        abort_unless($request->user()->can('membership'), 403);

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
        ]);

        $updated = $this->userManagement->assignMembershipPlan($user, (int) $data['plan_id'], $activation);

        return ApiResponse::success(new UserAdminResource($updated->load(['roles', 'vendorProfile'])));
    }

    public function impersonate(
        Request $request,
        User $user,
        LoginUserAction $login,
        BuildMeContextAction $buildMe,
    ): JsonResponse {
        $result = $this->userManagement->impersonate(
            $user,
            $request->user(),
            $login,
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'me' => new MeResource($buildMe->execute($result['user'])),
        ]);
    }
}
