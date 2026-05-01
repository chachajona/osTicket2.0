<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Models\Admin\AdminAuditLog;
use App\Services\Admin\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class FakeAdminHelperSubject extends Model
{
    protected $connection = 'osticket2';

    protected $table = 'fake_admin_helper_subjects';

    public $timestamps = false;

    protected $guarded = [];
}

beforeEach(function (): void {
    seedPermissions();

    Schema::connection('osticket2')->dropIfExists('fake_admin_helper_subjects');
    Schema::connection('osticket2')->create('fake_admin_helper_subjects', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->string('name');
    });
});

afterEach(function (): void {
    Auth::guard('staff')->logout();
    Schema::connection('osticket2')->dropIfExists('fake_admin_helper_subjects');
});

it('authenticates admin staff with admin access permission', function (): void {
    $staff = actingAsAdmin();

    expect(Auth::guard('staff')->id())->toBe($staff->staff_id)
        ->and($staff->fresh()->hasPermissionTo('admin.access'))->toBeTrue();
});

it('authenticates non-admin staff without admin access and the admin middleware rejects them', function (): void {
    $staff = actingAsAgent();
    $request = Request::create('/admin/test', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
    app()->instance('request', $request);

    $response = app(EnsureAdminAccess::class)->handle($request, fn () => response()->json(['ok' => true]));

    expect(Auth::guard('staff')->id())->toBe($staff->staff_id)
        ->and($staff->fresh()->hasPermissionTo('admin.access'))->toBeFalse()
        ->and($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(403);
});

it('asserts admin audit log entries through the shared helper', function (): void {
    $actor = actingAsAdmin();
    $request = Request::create('/admin/roles', 'POST', server: [
        'HTTP_USER_AGENT' => 'pest',
        'REMOTE_ADDR' => '127.0.0.1',
    ]);
    app()->instance('request', $request);

    $subject = FakeAdminHelperSubject::query()->create(['name' => 'Operations']);

    app(AuditLogger::class)->record(
        $actor,
        'role.create',
        $subject,
        before: null,
        after: ['name' => 'Operations'],
    );

    $log = assertAuditLogged('role.create', $subject, null, ['name' => 'Operations']);

    expect($log)->toBeInstanceOf(AdminAuditLog::class)
        ->and($log->actor_id)->toBe($actor->staff_id);
});
