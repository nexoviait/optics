<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Prescription;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrescriptionController extends Controller
{
    /** Open a customer's uploaded prescription from private storage. Access is logged: it is medical data. */
    public function file(Prescription $prescription): StreamedResponse
    {
        abort_unless($prescription->file_path && Storage::disk('local')->exists($prescription->file_path), 404);

        ActivityLog::record('Prescription file opened', $prescription);

        return Storage::disk('local')->response($prescription->file_path, $prescription->file_name);
    }
}
