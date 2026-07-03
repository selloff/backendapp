<?php

namespace App\Modules\Selloff\User\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Selloff\User\Models\Follower;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function following(Request $request): JsonResponse
    {
        $following = Follower::query()
            ->with(['user.vendorProfile'])
            ->where('follower_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($following);
    }

    public function followers(Request $request): JsonResponse
    {
        $followers = Follower::query()
            ->with(['follower'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($followers);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        abort_if((int) $user->id === (int) $request->user()->id, 422, 'Cannot follow yourself.');

        $follow = Follower::query()->firstOrCreate([
            'user_id' => $user->id,
            'follower_id' => $request->user()->id,
        ]);

        return ApiResponse::success($follow, 201);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        Follower::query()
            ->where('user_id', $user->id)
            ->where('follower_id', $request->user()->id)
            ->delete();

        return ApiResponse::success(['unfollowed' => true]);
    }
}
