<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentTypeController extends Controller
{
    public function index(Request $request): View
    {
        $query = DocumentType::query()->ordered();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        if ($request->filled('context')) {
            $query->where('context', $request->string('context')->toString());
        }

        $documentTypes = $query->paginate(25)->withQueryString();

        return view('admin.document-types.index', [
            'documentTypes' => $documentTypes,
            'contextLabels' => DocumentType::contextLabels(),
        ]);
    }

    public function create(): View
    {
        return view('admin.document-types.create', [
            'contextLabels' => DocumentType::contextLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DocumentType::query()->create($this->validated($request));

        return redirect()->route('admin.document-types.index')->with('success', 'Тип документа добавлен.');
    }

    public function edit(DocumentType $documentType): View
    {
        return view('admin.document-types.edit', [
            'documentType' => $documentType,
            'contextLabels' => DocumentType::contextLabels(),
        ]);
    }

    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        $documentType->update($this->validated($request, $documentType));

        return redirect()->route('admin.document-types.index')->with('success', 'Тип документа обновлён.');
    }

    public function destroy(DocumentType $documentType): RedirectResponse
    {
        $documentType->delete();

        return redirect()->route('admin.document-types.index')->with('success', 'Тип документа удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?DocumentType $documentType = null): array
    {
        $context = $request->string('context')->toString();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('document_types', 'slug')
                    ->where('context', $context)
                    ->ignore($documentType?->id),
            ],
            'context' => ['required', 'string', Rule::in(array_keys(DocumentType::contextLabels()))],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'slug' => 'Код (slug)',
            'context' => 'Контекст',
            'description' => 'Описание',
            'sort_order' => 'Порядок',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
