<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EngagementController extends Controller
{
    public function newsletter(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        NewsletterSubscriber::query()->firstOrCreate(['email' => strtolower($data['email'])]);

        return $this->respond($request, "You're on the list. Welcome to the Dispatch.");
    }

    public function consultation(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'name' => [$request->user() ? 'nullable' : 'required', 'string', 'max:80'],
            'email' => [$request->user() ? 'nullable' : 'required', 'email', 'max:255'],
        ]);

        ServiceRequest::query()->create([
            'type' => 'consultation',
            'user_id' => $request->user()?->id,
            'name' => $data['name'] ?? $request->user()?->name,
            'email' => $data['email'] ?? $request->user()?->email,
            'preferred_date' => $data['preferred_date'],
        ]);

        return $this->respond($request, 'Consultation requested for '.date('j M Y', strtotime($data['preferred_date'])).'. Our concierge will confirm by email.');
    }

    public function homeTrial(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:4'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'name' => [$request->user() ? 'nullable' : 'required', 'string', 'max:80'],
            'email' => [$request->user() ? 'nullable' : 'required', 'email', 'max:255'],
        ], ['product_ids.required' => 'Select at least one frame for your box.']);

        $names = Product::query()->whereIn('id', $data['product_ids'])->pluck('name');

        ServiceRequest::query()->create([
            'type' => 'home_trial',
            'user_id' => $request->user()?->id,
            'name' => $data['name'] ?? $request->user()?->name,
            'email' => $data['email'] ?? $request->user()?->email,
            'product_ids' => $data['product_ids'],
            'notes' => $names->implode(', '),
        ]);

        $count = count($data['product_ids']);

        return $this->respond($request, "Home Trial Box booked with {$count} frame".($count > 1 ? 's' : '').'.');
    }

    private function respond(Request $request, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }
}
