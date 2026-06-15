<?php

namespace App\Services\Concerns;

trait BuildsProfileCompletionSummary
{
    /**
     * @param  list<array{key: string, title: string, description: string, completed: bool, url: string, required: bool}>  $steps
     * @param  array{type: 'warning'|'info', message: string}|null  $notice
     * @return array{
     *     steps: list<array{key: string, title: string, description: string, completed: bool, url: string, required: bool}>,
     *     completed_count: int,
     *     total_count: int,
     *     percent: int,
     *     is_complete: bool,
     *     intro_complete: string,
     *     intro_incomplete: string,
     *     notice: array{type: 'warning'|'info', message: string}|null
     * }
     */
    protected function buildSummary(
        array $steps,
        string $introComplete,
        string $introIncomplete,
        ?array $notice = null,
    ): array {
        $completedCount = collect($steps)->where('completed', true)->count();
        $totalCount = count($steps);
        $requiredSteps = collect($steps)->where('required', true);
        $requiredCompleted = $requiredSteps->where('completed', true)->count();

        return [
            'steps' => $steps,
            'completed_count' => $completedCount,
            'total_count' => $totalCount,
            'percent' => $totalCount > 0 ? (int) round(($completedCount / $totalCount) * 100) : 0,
            'is_complete' => $requiredSteps->isNotEmpty() && $requiredCompleted === $requiredSteps->count(),
            'intro_complete' => $introComplete,
            'intro_incomplete' => $introIncomplete,
            'notice' => $notice,
        ];
    }

    /**
     * @return array{key: string, title: string, description: string, completed: bool, url: string, required: bool}
     */
    protected function step(
        string $key,
        string $title,
        string $description,
        bool $completed,
        string $url,
        bool $required,
    ): array {
        return compact('key', 'title', 'description', 'completed', 'url', 'required');
    }
}
