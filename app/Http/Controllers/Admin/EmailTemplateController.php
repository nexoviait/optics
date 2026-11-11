<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $templates = EmailTemplate::query()->orderBy('name')->get();

        return view('admin.email.index', [
            'templates' => $templates,
            'selected' => $templates->firstWhere('slug', $request->query('preview')) ?? $templates->first(),
            'counts' => ['live' => $templates->where('status', 'Live')->count(), 'draft' => $templates->where('status', 'Draft')->count()],
        ]);
    }

    public function create(): View
    {
        return view('admin.email.edit', ['template' => new EmailTemplate([
            'name' => 'Untitled template',
            'category' => 'Transactional',
            'locale' => 'en-GB',
            'hook' => 'order.placed',
            'subject' => 'Your REFRA update — {{order.id}}',
            'preheader' => 'Your bespoke optics are progressing.',
            'status' => 'Draft',
            'blocks' => [
                ['id' => '1', 'type' => 'Logo', 'text' => ''],
                ['id' => '2', 'type' => 'Heading', 'text' => 'Hello'],
                ['id' => '3', 'type' => 'Paragraph', 'text' => 'Dear {{customer.first_name}}, thank you for order {{order.id}}.'],
                ['id' => '4', 'type' => 'Footer', 'text' => 'REFRA Atelier Optics · Genève'],
            ],
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = EmailTemplate::query()->create($this->validated($request));

        return redirect()->route('admin.email.edit', $template)->with('success', 'Template created.');
    }

    public function edit(EmailTemplate $template): View
    {
        return view('admin.email.edit', ['template' => $template]);
    }

    public function update(Request $request, EmailTemplate $template): RedirectResponse
    {
        $template->update($this->validated($request));

        return back()->with('success', $template->status === 'Live' ? 'Template published.' : 'Draft saved.');
    }

    public function destroy(EmailTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('admin.email.index')->with('success', 'Template deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'hook' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_.]+$/'],
            'category' => ['required', Rule::in(config('store.email_categories'))],
            'locale' => ['required', Rule::in(['en-GB', 'en-US', 'fr-CH', 'de-CH', 'it-IT'])],
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Draft', 'Live'])],
            'blocks' => ['required', 'json'],
        ]);

        $blocks = json_decode($data['blocks'], true);
        $types = config('store.email_block_types');
        $data['blocks'] = collect(is_array($blocks) ? $blocks : [])
            ->filter(fn ($b) => is_array($b) && in_array($b['type'] ?? null, $types, true))
            ->map(fn (array $b, int $i) => ['id' => (string) ($b['id'] ?? $i), 'type' => $b['type'], 'text' => mb_substr((string) ($b['text'] ?? ''), 0, 2000)])
            ->values()->all();

        return $data;
    }
}
