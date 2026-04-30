<?php

namespace App\Http\Middleware;

use App\Models\Scp\ScpAccessLog;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogScpAccess
{
    private const SUBJECT_KEYS = ['ticket', 'queue', 'file'];

    public function handle(Request $request, Closure $next): Response
    {
        $staff = Auth::guard('staff')->user();
        $response = $next($request);

        if ($staff) {
            $this->writeLog($request, (int) $staff->staff_id, $response);
        }

        return $response;
    }

    private function writeLog(Request $request, int $staffId, Response $response): void
    {
        ['type' => $subjectType, 'id' => $subjectId] = $this->subject($request);

        try {
            ScpAccessLog::create([
                'staff_id' => $staffId,
                'action' => $request->route()?->getName() ?? $request->method().' '.$request->path(),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'metadata' => [
                    'method' => $request->method(),
                    'path' => '/'.$request->path(),
                    'status' => $response->getStatusCode(),
                    'query' => $request->query(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (QueryException) {
            Log::warning('Unable to write SCP access log; is the osticket2 access_log table migrated?');
        }
    }

    /**
     * @return array{type: ?string, id: ?int}
     */
    private function subject(Request $request): array
    {
        foreach (self::SUBJECT_KEYS as $name) {
            $value = $request->route($name);

            if ($value === null) {
                continue;
            }

            return ['type' => $name, 'id' => $this->resolveId($value)];
        }

        return ['type' => null, 'id' => null];
    }

    private function resolveId(mixed $value): ?int
    {
        if (is_object($value)) {
            return match (true) {
                isset($value->id) => (int) $value->id,
                isset($value->ticket_id) => (int) $value->ticket_id,
                default => null,
            };
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
