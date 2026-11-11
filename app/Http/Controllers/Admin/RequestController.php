<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RequestController extends Controller
{
    public function index(Request $request): View
    {
        $type = array_key_exists((string) $request->query('type'), ServiceRequest::TYPES) ? $request->query('type') : null;

        return view('admin.requests', [
            'requests' => ServiceRequest::query()->with('user')->latest()
                ->when($type, fn ($q) => $q->where('type', $type))
                ->paginate(20)->withQueryString(),
            'subscribers' => NewsletterSubscriber::query()->latest()->take(20)->get(),
            'subscriberCount' => NewsletterSubscriber::query()->count(),
            'type' => $type,
        ]);
    }

    public function update(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $serviceRequest->update($request->validate([
            'status' => ['required', Rule::in(ServiceRequest::STATUSES)],
        ]));

        return back()->with('success', 'Request updated.');
    }
}
