<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return [
            'nip' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'nip')->ignore($employeeId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role_id' => ['required', 'exists:roles,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'sub_department_id' => [
                'nullable',
                Rule::exists('sub_departments', 'id')->where(function ($query) {
                    $query->where('department_id', $this->input('department_id'));
                }),
            ],
            'gender' => ['nullable', 'in:L,P'],
            'address' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
            'user_id' => ['nullable', 'exists:users,id'],
            'create_user_account' => ['nullable', 'boolean'],
            'user_password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
