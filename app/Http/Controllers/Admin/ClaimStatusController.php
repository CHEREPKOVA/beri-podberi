<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClaimStatusController extends Controller
{
    public function index(Request $request): View
    {
        $query = ClaimStatus::query()->ordered();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        $statuses = $query->paginate(25)->withQueryString();

        return view('admin.claim-statuses.index', compact('statuses'));
    }

    public function create(): View
    {
        return view('admin.claim-statuses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        ClaimStatus::query()->create($this->validated($request));

        return redirect()->route('admin.claim-statuses.index')->with('success', 'Статус претензии добавлен.');
    }

    public function edit(ClaimStatus $claimStatus): View
    {
        return view('admin.claim-statuses.edit', ['status' => $claimStatus]);
    }

    public function update(Request $request, ClaimStatus $claimStatus): RedirectResponse
    {
        $claimStatus->update($this->validated($request, $claimStatus));

        return redirect()->route('admin.claim-statuses.index')->with('success', 'Статус претензии обновлён.');
    }

    public function destroy(ClaimStatus $claimStatus): RedirectResponse
    {
        $claimStatus->delete();

        return redirect()->route('admin.claim-statuses.index')->with('success', 'Статус претензии удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ClaimStatus $status = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('claim_statuses', 'slug')->ignore($status?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_terminal' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'slug' => 'Код (slug)',
            'description' => 'Описание',
            'sort_order' => 'Порядок',
            'is_terminal' => 'Финальный статус',
        ]);

        $validated['is_terminal'] = $request->boolean('is_terminal');
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
