<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', ['pages' => Page::query()->orderBy('footer_group')->orderBy('sort_order')->get()]);
    }

    public function create(): View
    {
        return view('admin.pages.form', ['page' => new Page(['is_published' => true, 'footer_group' => 'help'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $page = Page::query()->create($this->validated($request));
        ActivityLog::record("Page created: {$page->title}", $page);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page created.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.form', ['page' => $page]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $page->update($this->validated($request, $page));
        ActivityLog::record("Page updated: {$page->title}", $page);

        return back()->with('success', 'Page saved.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', 'Page deleted.');
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'alpha_dash', 'max:160', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'footer_group' => ['nullable', Rule::in(array_keys(config('store.footer_groups')))],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
