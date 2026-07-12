<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MeResource;
use App\Modules\Auth\Actions\BuildMeContextAction;
use App\Modules\Selloff\User\Models\VendorProfile;
use App\Modules\Auth\Http\Requests\Api\V1\UpdateMeRequest;
use App\Modules\Auth\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MeController extends Controller
{
    public function show(Request $request, BuildMeContextAction $buildMe): JsonResponse
    {
        return ApiResponse::success(new MeResource($buildMe->execute($request->user()->load('vendorProfile'))));
    }

    public function update(UpdateMeRequest $request, BuildMeContextAction $buildMe): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $socialMediaData = $validated['social_media_data'] ?? null;
        $coverPath = $validated['cover_path'] ?? null;
        unset($validated['social_media_data'], $validated['cover_path']);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($request->has('social_media_data')) {
            if ($user->can('vendor')) {
                VendorProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['social_media_data' => $socialMediaData],
                );
            } else {
                $user->update(['social_media_data' => $socialMediaData]);
            }
        }

        if ($request->exists('cover_path')) {
            VendorProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['cover_path' => $coverPath],
            );
        }

        return ApiResponse::success(new MeResource($buildMe->execute($user->fresh()->load('vendorProfile'))));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return ApiResponse::success(message: 'Password updated.');
    }
}
