<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Services\AuditActivityLogger;
use App\Services\TicketCategoryRecommendations;
use App\Services\TicketNewCategoryRecommendations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'classification_keywords' => ['nullable', 'string', 'max:2000'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        $category = Category::create([
            'name' => $validated['category_name'],
            'description' => $validated['description'] ?? null,
            'classification_keywords' => $validated['classification_keywords'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
        ]);
        $auditLogger->record(
            $request,
            'category_created',
            "Added category {$category->name}.",
            $category,
            ['is_active' => true, 'department_id' => $category->department_id],
        );

        return to_route('admin.sla-settings')->with('status', 'Category added.');
    }

    public function createCategoryFromSuggestion(
        Request $request,
        TicketNewCategoryRecommendations $categoryRecommendations,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:45'],
            'category_name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);
        $keyword = mb_strtolower(trim($validated['keyword']));

        [$category, $ticketCount, $departmentName] = DB::transaction(function () use (
            $request,
            $validated,
            $keyword,
            $categoryRecommendations,
        ): array {
            $suggestion = $categoryRecommendations->findByKeyword($keyword);

            if ($suggestion === null) {
                throw ValidationException::withMessages([
                    'keyword' => 'This new-category suggestion is no longer available. Refresh the page and review current suggestions.',
                ]);
            }

            $department = Department::query()
                ->whereKey($validated['department_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($department === null) {
                throw ValidationException::withMessages([
                    'department_id' => 'Select an active department for this category.',
                ]);
            }

            $tickets = Ticket::query()
                ->whereIn('id', $suggestion['ticket_ids'])
                ->whereNull('department_id')
                ->whereNull('category_id')
                ->whereNotIn('status', ['Resolved', 'Closed'])
                ->lockForUpdate()
                ->get();

            if ($tickets->count() < 3) {
                throw ValidationException::withMessages([
                    'keyword' => 'At least 3 unmatched tickets are required to create a suggested category.',
                ]);
            }

            $category = Category::create([
                'name' => $validated['category_name'],
                'classification_keywords' => $keyword,
                'department_id' => $department->id,
                'is_active' => true,
            ]);

            foreach ($tickets as $ticket) {
                $ticket->update([
                    'department_id' => $department->id,
                    'category_id' => $category->id,
                ]);
                $ticket->events()->create([
                    'actor_id' => $request->user()->id,
                    'event' => 'category_suggestion_applied',
                    'description' => "Admin routed this ticket to category {$category->name}.",
                    'properties' => [
                        'category_id' => $category->id,
                        'department_id' => $department->id,
                        'keyword' => $keyword,
                    ],
                ]);
            }

            return [$category, $tickets->count(), $department->name];
        });

        $auditLogger->record(
            $request,
            'category_suggestion_approved',
            "Created category {$category->name} and routed {$ticketCount} unmatched tickets to {$departmentName}.",
            $category,
            [
                'keyword' => $keyword,
                'department_id' => $category->department_id,
                'ticket_count' => $ticketCount,
            ],
        );

        return to_route('admin.sla-settings')
            ->with('status', "Created category {$category->name} and routed {$ticketCount} unmatched tickets to {$departmentName}.");
    }

    public function approveCategoryKeyword(
        Request $request,
        Category $category,
        TicketCategoryRecommendations $categoryRecommendations,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:45'],
        ]);
        $keyword = mb_strtolower(trim($validated['keyword']));
        $recommendations = $categoryRecommendations->recommendations()[$category->id] ?? [];

        if (! in_array($keyword, array_column($recommendations, 'keyword'), true)) {
            throw ValidationException::withMessages([
                'keyword' => 'This suggestion is no longer available. Refresh the page and review current suggestions.',
            ]);
        }

        $wasAdded = DB::transaction(function () use ($category, $keyword): bool {
            $lockedCategory = Category::query()->lockForUpdate()->findOrFail($category->id);
            $existingKeywords = preg_split(
                '/[\r\n,]+/',
                mb_strtolower($lockedCategory->classification_keywords ?? ''),
                -1,
                PREG_SPLIT_NO_EMPTY,
            ) ?: [];
            $existingKeywords = array_values(array_filter(array_map('trim', $existingKeywords)));

            if (in_array($keyword, $existingKeywords, true)) {
                return false;
            }

            $updatedKeywords = implode(', ', [...$existingKeywords, $keyword]);

            if (mb_strlen($updatedKeywords) > 2000) {
                throw ValidationException::withMessages([
                    'keyword' => 'This category has reached its matching phrase limit. Remove an existing phrase before approving another.',
                ]);
            }

            $lockedCategory->update(['classification_keywords' => $updatedKeywords]);

            return true;
        });

        if (! $wasAdded) {
            return to_route('admin.sla-settings')->with('status', 'That matching phrase was already approved.');
        }

        $auditLogger->record(
            $request,
            'category_keyword_approved',
            "Approved the matching phrase [{$keyword}] for category {$category->name}.",
            $category,
            ['keyword' => $keyword],
        );

        return to_route('admin.sla-settings')->with('status', 'Category matching phrase approved.');
    }

    public function updateCategoryDepartment(
        Request $request,
        Category $category,
        AuditActivityLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);
        $previousDepartmentId = $category->department_id;
        $category->update(['department_id' => $validated['department_id'] ?? null]);
        $departmentName = $category->department_id === null
            ? null
            : Department::query()->whereKey($category->department_id)->value('name');

        $auditLogger->record(
            $request,
            'category_department_updated',
            $category->department_id === null
                ? "Removed the department route for category {$category->name}."
                : "Routed category {$category->name} to department {$departmentName}.",
            $category,
            [
                'before' => ['department_id' => $previousDepartmentId],
                'after' => ['department_id' => $category->department_id],
            ],
        );

        return to_route('admin.sla-settings')->with('status', 'Category routing updated.');
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
