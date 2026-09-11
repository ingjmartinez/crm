<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('role') ? 'roles.edit' : 'roles.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('roles', 'name')->ignore($this->route('role'))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', \Illuminate\Validation\Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del rol.',
            'name.unique' => 'Ya existe un rol con ese nombre.',
            'permissions.array' => 'Selecciona permisos válidos.',
            'permissions.*.exists' => 'Uno de los permisos seleccionados no existe.',
            'permissions.*.distinct' => 'No repitas el mismo permiso.',
        ];
    }

    protected function passedValidation(): void
    {
        $role = $this->route('role');
        abort_if($role?->name === 'superadmin' && $this->input('name') !== 'superadmin', 422, 'No se puede cambiar el nombre del rol superadmin.');
    }
}
