<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Services\AdminDatabaseBackupService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDatabaseBackupController extends Controller
{
    public function download(Request $request, AdminDatabaseBackupService $backups): StreamedResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        return $backups->download();
    }
}
