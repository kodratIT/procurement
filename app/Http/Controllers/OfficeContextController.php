<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Services\ActiveOfficeContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OfficeContextController extends Controller
{
    public function __construct(private readonly ActiveOfficeContext $context) {}

    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate(['office_id' => ['required', 'integer', 'exists:offices,id']]);
        $this->context->set(Office::findOrFail($validated['office_id']));

        return redirect()->back()->with('status', 'Active office changed.');
    }
}
