<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransportCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransportCompanyController extends Controller
{
    public function index(Request $request): View
    {
        $query = TransportCompany::query()->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        $companies = $query->paginate(25)->withQueryString();

        return view('admin.transport-companies.index', compact('companies'));
    }

    public function create(): View
    {
        return view('admin.transport-companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', 'unique:transport_companies,slug'],
            'website' => ['nullable', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'slug' => 'Код (slug)',
            'website' => 'Сайт',
            'tracking_url' => 'URL отслеживания',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        TransportCompany::query()->create($validated);

        return redirect()->route('admin.transport-companies.index')->with('success', 'Транспортная компания добавлена.');
    }

    public function edit(TransportCompany $transportCompany): View
    {
        return view('admin.transport-companies.edit', ['company' => $transportCompany]);
    }

    public function update(Request $request, TransportCompany $transportCompany): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('transport_companies', 'slug')->ignore($transportCompany->id)],
            'website' => ['nullable', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Название',
            'slug' => 'Код (slug)',
            'website' => 'Сайт',
            'tracking_url' => 'URL отслеживания',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $transportCompany->update($validated);

        return redirect()->route('admin.transport-companies.index')->with('success', 'Данные транспортной компании обновлены.');
    }

    public function destroy(TransportCompany $transportCompany): RedirectResponse
    {
        if ($transportCompany->manufacturerProfiles()->exists()) {
            return redirect()->route('admin.transport-companies.index')
                ->with('error', 'Нельзя удалить транспортную компанию: она указана у производителей. Деактивируйте запись.');
        }

        $transportCompany->delete();

        return redirect()->route('admin.transport-companies.index')->with('success', 'Транспортная компания удалена.');
    }
}
