<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RoleSwitchController extends Controller
{
    public function switch(Request $request)
    {
        $request->validate([
            "role" => ["required", "string", "in:admin,coordinator,lecturer,student"],
        ]);

        $tenant = app("current_tenant");
        $user = $request->user();
        $role = $request->input("role");

        // Verify the user actually has this role in this tenant
        $hasRole = $user->is_super_admin
            || $user->tenantUsers()
                ->where("tenant_id", $tenant->id)
                ->where("is_active", true)
                ->where("role", $role)
                ->exists();

        if (! $hasRole) {
            return back()->with("error", "You do not have the {$role} role.");
        }

        session()->put("tenant_{$tenant->id}_role", $role);

        return redirect("/{$tenant->slug}/dashboard")->with("success", "Switched to " . ucfirst($role) . " view.");
    }
}
