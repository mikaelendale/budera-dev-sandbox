<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DocsController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('docs.show', ['page' => 'overview']);
    }

    public function show(string $page): Response|SymfonyResponse
    {
        $allowed = config('docs.api_pages', []);
        if (! in_array($page, $allowed, true)) {
            abort(404);
        }

        $path = resource_path('docs/api/'.$page.'.md');
        if (! is_readable($path)) {
            abort(404);
        }

        $markdown = file_get_contents($path);
        if (! is_string($markdown)) {
            abort(404);
        }

        $html = Str::markdown($markdown);

        return Inertia::render('Docs/Show', [
            'title' => $this->titleForPage($page),
            'page' => $page,
            'html' => $html,
            'nav' => config('docs.api_nav', []),
        ]);
    }

    private function titleForPage(string $page): string
    {
        foreach (config('docs.api_nav', []) as $item) {
            if (($item['slug'] ?? null) === $page) {
                return (string) ($item['label'] ?? ucfirst($page));
            }
        }

        return ucfirst($page);
    }
}
