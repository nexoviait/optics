<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PageSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(): View
    {
        return view('admin.content.index', [
            'sections' => PageSection::query()->forPage('home')->get(),
            'activity' => ActivityLog::query()->with('user')->where('subject_type', (new PageSection)->getMorphClass())->latest()->take(8)->get(),
        ]);
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => ['required', 'integer', 'exists:page_sections,id'],
            'sections.*.is_enabled' => ['boolean'],
        ]);

        foreach (array_values($data['sections']) as $i => $section) {
            PageSection::query()->whereKey($section['id'])->update([
                'sort_order' => $i,
                'is_enabled' => (bool) ($section['is_enabled'] ?? false),
            ]);
        }
        ActivityLog::record('Homepage sections reordered', PageSection::query()->find($data['sections'][0]['id']));

        return back()->with('success', 'Homepage published.');
    }

    public function edit(PageSection $section): View
    {
        return view('admin.content.edit', ['section' => $section]);
    }

    /**
     * Scalar fields are edited as inputs; repeatable lists (cards, stats,
     * quotes…) as validated JSON so every section shares one form.
     */
    public function update(Request $request, PageSection $section): RedirectResponse
    {
        $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'fields' => ['array'],
            'fields.*' => ['nullable', 'string', 'max:2000'],
            'lists' => ['array'],
            'lists.*' => ['nullable', 'string', 'max:20000'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $content = $section->content ?? [];
        foreach ($request->input('fields', []) as $key => $value) {
            if (array_key_exists($key, $content) && ! is_array($content[$key])) {
                $content[$key] = (string) $value;
            }
        }

        foreach ($request->input('lists', []) as $key => $json) {
            if (! array_key_exists($key, $content) || ! is_array($content[$key])) {
                continue;
            }
            $decoded = json_decode((string) $json, true);
            if (! is_array($decoded) || ! array_is_list($decoded)) {
                throw ValidationException::withMessages(["lists.{$key}" => "“{$key}” must be a valid JSON list."]);
            }
            $content[$key] = $decoded;
        }

        if ($request->hasFile('image') && array_key_exists('image', $content)) {
            if (! str_starts_with((string) $content['image'], 'images/')) {
                Storage::disk('public')->delete((string) $content['image']);
            }
            $content['image'] = $request->file('image')->store('content', 'public');
        }

        $section->update(['label' => $request->input('label'), 'content' => $content]);
        ActivityLog::record("Section updated: {$section->label}", $section);

        return redirect()->route('admin.content.index')->with('success', "{$section->label} saved.");
    }
}
