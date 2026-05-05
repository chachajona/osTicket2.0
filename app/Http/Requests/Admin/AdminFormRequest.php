<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;

class AdminFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->staff()?->canAccessAdminPanel() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    protected function staff(): ?Staff
    {
        $staff = $this->user('staff');

        return $staff instanceof Staff ? $staff : null;
    }
}
