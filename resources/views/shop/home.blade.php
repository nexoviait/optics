<x-layouts.app>
    @foreach ($sections as $section)
        @includeIf('shop.home.'.$section->key, ['section' => $section, 'c' => $section->content ?? []])
    @endforeach
</x-layouts.app>
