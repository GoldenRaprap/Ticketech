<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Services\AuditActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminDepartmentCategoryController extends Controller
{
    public function storeDepartment(Request $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'department_name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')],
        ]);

        $department = Department::create(['name' => $validated['department_name']]);
        $auditLogger->record(
            $request,
            'department_created',
            "Added department {$department->name}.",
            $department,
            ['is_active' => true],
        );

        return to_route('admin.sla-settings')->with('status', 'Department added.');
    }

    public function storeCategory(Request $request, AuditActivityLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $category = Category::create([
            'name' => $validated['category_name'],
            'description' => $validated['description'] ?? null,
        ]);
        $auditLogger->record(
            $request,
            'category_created',
            "Added category {$category->name}.",
            $category,
            ['is_active' => true],
        );

        return to_route('admin.sla-settings')->with('status', 'Category added.');
    }

    public function toggleDepartment(
        Request $request,
        Department $department,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $wasActive = $department->is_active;
        $department->update(['is_active' => ! $wasActive]);
        $status = $department->is_active ? 'activated' : 'deactivated';
        $auditLogger->record(
            $request,
            'department_'.$status,
            ucfirst($status)." department {$department->name}.",
            $department,
            ['before' => ['is_active' => $wasActive], 'after' => ['is_active' => $department->is_active]],
        );

        return to_route('admin.sla-settings')->with('status', "Department {$status}.");
    }

    public function toggleCategory(
        Request $request,
        Category $category,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $wasActive = $category->is_active;
        $category->update(['is_active' => ! $wasActive]);
        $status = $category->is_active ? 'activated' : 'deactivated';
        $auditLogger->record(
            $request,
            'category_'.$status,
            ucfirst($status)." category {$category->name}.",
            $category,
            ['before' => ['is_active' => $wasActive], 'after' => ['is_active' => $category->is_active]],
        );

        return to_route('admin.sla-settings')->with('status', "Category {$status}.");
    }
}
