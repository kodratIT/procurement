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

        $context = [];

        if (isset($validated['assignment_id']) && $validated['assignment_id'] !== null) {
            $context['assignment_id'] = (int) $validated['assignment_id'];
        }

        if (isset($validated['office_id']) && $validated['office_id'] !== null) {
            $context['office_id'] = (int) $validated['office_id'];
        }

        foreach (['branch_id', 'department_id', 'role_id'] as $column) {
            if (array_key_exists($column, $validated)) {
                $context[$column] = $validated[$column] !== null ? (int) $validated[$column] : null;
            }
        }

        $this->context->setContext($context);

        return redirect()->back()->with('status', 'Active access context changed.');
    }

    public function confirmMutation(Request $request): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']]);
        $this->context->confirmMutation();

        return redirect()->back()->with('status', 'Non-default office mutations confirmed for this context.');
    }
}
