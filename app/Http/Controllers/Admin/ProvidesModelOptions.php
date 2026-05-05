<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use App\Models\EmailModel;
use App\Models\EmailTemplateGroup;
use App\Models\Sla;
use App\Models\Staff;
use App\Models\Team;

trait ProvidesModelOptions
{
    /**
     * @return list<array{id:int,name:string}>
     */
    protected function departmentOptions(?int $excludeId = null): array
    {
        return Department::query()
            ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'id' => (int) $department->getKey(),
                'name' => (string) $department->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    protected function staffOptions(): array
    {
        return Staff::query()
            ->where('isactive', 1)
            ->orderBy('firstname')
            ->orderBy('lastname')
            ->orderBy('username')
            ->get()
            ->map(fn (Staff $staff): array => [
                'id' => (int) $staff->getKey(),
                'name' => $staff->displayName(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    protected function slaOptions(): array
    {
        return Sla::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Sla $sla): array => [
                'id' => (int) $sla->getKey(),
                'name' => (string) $sla->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    protected function teamOptions(): array
    {
        return Team::query()
            ->orderBy('name')
            ->get(['team_id', 'name'])
            ->map(fn (Team $team): array => [
                'id' => (int) $team->getKey(),
                'name' => (string) $team->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    protected function emailOptions(): array
    {
        return EmailModel::query()
            ->orderBy('name')
            ->orderBy('email')
            ->get(['email_id', 'name', 'email'])
            ->map(fn (EmailModel $email): array => [
                'id' => (int) $email->getKey(),
                'name' => trim((string) $email->name) !== ''
                    ? sprintf('%s <%s>', (string) $email->name, (string) $email->email)
                    : (string) $email->email,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    protected function templateOptions(): array
    {
        return EmailTemplateGroup::query()
            ->orderBy('name')
            ->get(['tpl_id', 'name'])
            ->map(fn (EmailTemplateGroup $template): array => [
                'id' => (int) $template->getKey(),
                'name' => (string) $template->name,
            ])
            ->values()
            ->all();
    }
}
