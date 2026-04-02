<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function index(): View
    {
        $settings = SystemSetting::query()
            ->orderBy('group_key')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->groupBy('group_key');

        return view('admin.system-settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $rows = SystemSetting::query()->get()->keyBy('id');

        foreach ($rows as $id => $row) {
            if ($row->value_type === 'boolean') {
                $row->value = $request->boolean("settings.$id.value") ? '1' : '0';
            } else {
                $value = $request->input("settings.$id.value");
                $row->value = $value !== null ? (string) $value : null;
            }

            $row->is_active = $request->boolean("settings.$id.is_active");
            $row->save();
        }

        return redirect()->route('admin.system-settings.index')->with('success', 'Системные настройки обновлены.');
    }
}
