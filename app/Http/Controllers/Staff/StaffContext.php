<?php

namespace App\Http\Controllers\Staff;

use App\Models\Location;
use App\Models\MarketDay;
use Illuminate\Http\Request;

/** Remembers which market a staff member is working at today. */
class StaffContext
{
    public static function location(Request $request): ?Location
    {
        $id = $request->session()->get('staff_location_id');
        if ($id && ($location = Location::active()->find($id))) {
            return $location;
        }

        // Default: the first market trading today, if any.
        $today = MarketDay::whereDate('date', today())->where('status', 'scheduled')->orderBy('id')->first();

        return $today?->location;
    }
}
