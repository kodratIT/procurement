<?php

namespace App\Http\Controllers;

use App\Services\AccessContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OfficeContextController extends Controller
{
    public function __construct(private readonly AccessContextService $context) {}

    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assignment_id' => ['nullable', 'integer'],
            'office_id' => ['nullable', 'integer', 'required_without:assignment_id'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'role_id' => ['nullable', 'integer'],
        ]);
        $this->context->setContext([
            'assignment_id' => isset($validated['assignment_id']) ? (int) $validated['assignment_id'] : null,
            'office_id' => isset($validated['office_id']) ? (int) $validated['office_id'] : null,
            'branch_id' => array_key_exists('branch_id', $validated) ? $validated['branch_id'] : null,
            'department_id' => array_key_exists('department_id', $validated) ? $validated['department_id'] : null,
            'role_id' => array_key_exists('role_id', $validated) ? $validated['role_id'] : null,
        ]);

        return redirect()->back()->with('status', 'Active access context changed.');
    }

    public function confirmMutation(Request $request): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']]);
        $this->context->confirmMutation();

        return redirect()->back()->with('status', 'Non-default office mutations confirmed for this context.');
    }
}
