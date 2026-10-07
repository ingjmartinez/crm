<?php

namespace App\Http\Requests;

use App\ViewPermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $catalogPermissions = app(ViewPermissionCatalog::class)->items()
            ->whereNull('role')->pluck('permission')->unique()->all();
        $permissions = Permission::query()->whereIn('name', $catalogPermissions)->pluck('name')->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'view_permissions' => ['sometimes', 'array'],
            'view_permissions.*.permission' => ['required', 'string', 'distinct', Rule::in($permissions)],
            'view_permissions.*.access' => ['required', Rule::in(['inherit', 'allow', 'deny'])],
        ];
    }

    public function messages(): array
    {
        return [
            'view_permissions.*.permission.in' => 'Selecciona un permiso de página válido.',
            'view_permissions.*.permission.distinct' => 'No repitas permisos de página.',
            'view_permissions.*.access.in' => 'Selecciona heredar, permitir o bloquear.',
        ];
    }
}
