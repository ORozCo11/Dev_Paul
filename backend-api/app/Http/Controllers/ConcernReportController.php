<?php

namespace App\Http\Controllers;

use App\Models\ConcernReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConcernReportController extends Controller
{
    private const TYPES = ['Barangay Inactive', 'Suspected Fake Staff', 'Other'];

    /**
     * Public — no account required. The reporter may be a resident with no
     * login at all, flagging a barangay whose Admin has gone unresponsive,
     * or a fraud concern about someone claiming to be barangay staff.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'concern_type' => ['required', Rule::in(self::TYPES)],
            'province_name' => ['nullable', 'string', 'max:255'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'barangay_name' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_contact' => ['nullable', 'string', 'max:255'],
        ]);

        ConcernReport::create($data);

        return response()->json([
            'message' => 'Thank you — your concern has been submitted and will be reviewed.',
        ], 201);
    }
}
