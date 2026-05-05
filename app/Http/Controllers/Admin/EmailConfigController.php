<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmailConfig\StoreEmailConfigRequest;
use App\Http\Requests\Admin\EmailConfig\UpdateEmailConfigRequest;
use App\Models\EmailAccount;
use App\Models\Staff;
use App\Services\Admin\EmailConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailConfigController extends Controller
{
    public function __construct(
        private readonly EmailConfigService $emailConfigs,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', EmailAccount::class);

        return Inertia::render('Admin/EmailConfig/Index', [
            'items' => $this->emailConfigs->indexItems(),
            'summary' => $this->emailConfigs->summary(),
            'createUrls' => $this->createUrls(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', EmailAccount::class);

        return Inertia::render('Admin/EmailConfig/Edit', [
            'mode' => 'create',
            'config' => null,
            'type' => $this->emailConfigs->normalizeType((string) $request->query('type', 'account')),
            'templateGroups' => $this->emailConfigs->templateGroupOptions(),
        ]);
    }

    public function store(StoreEmailConfigRequest $request): RedirectResponse
    {
        $this->authorize('create', EmailAccount::class);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $created = $this->emailConfigs->create($request->validated(), $actor);

        return redirect()
            ->route('admin.email-config.edit', $created['key'])
            ->with('status', 'Email configuration created.');
    }

    public function edit(string $emailConfig): Response
    {
        $resolved = $this->emailConfigs->resolveForRoute($emailConfig);
        $this->authorize('update', $resolved['subject']);

        return Inertia::render('Admin/EmailConfig/Edit', [
            'mode' => 'edit',
            'config' => $resolved['config'],
            'type' => $resolved['type'],
            'templateGroups' => $this->emailConfigs->templateGroupOptions(),
        ]);
    }

    public function update(UpdateEmailConfigRequest $request, string $emailConfig): RedirectResponse
    {
        $resolved = $this->emailConfigs->resolveForRoute($emailConfig);
        $this->authorize('update', $resolved['subject']);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $updated = $this->emailConfigs->update($emailConfig, $request->validated(), $actor);

        return redirect()
            ->route('admin.email-config.edit', $updated['key'])
            ->with('status', 'Email configuration updated.');
    }

    public function destroy(Request $request, string $emailConfig): RedirectResponse
    {
        $resolved = $this->emailConfigs->resolveForRoute($emailConfig);
        $this->authorize('delete', $resolved['subject']);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->emailConfigs->delete($emailConfig, $actor);

        return redirect()
            ->route('admin.email-config.index')
            ->with('status', 'Email configuration deleted.');
    }

    /**
     * @return array<string, string>
     */
    private function createUrls(): array
    {
        $base = route('admin.email-config.create');

        return [
            'account' => $base.'?type=account',
            'template' => $base.'?type=template',
            'group' => $base.'?type=group',
        ];
    }
}
