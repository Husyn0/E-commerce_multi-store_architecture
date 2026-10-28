<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends TenantApiController
{
    public function index()
    {
        return CategoryResource::collection(
            Category::query()
                ->forTenant($this->tenantId())
                ->orderBy('position')
                ->orderBy('name')
                ->paginate(100),
        );
    }

    public function store(Request $request, AuditLogger $auditLogger)
    {
        $data = $this->validatedCategory($request);
        $this->assertParentBelongsToTenant($data['parent_id'] ?? null);

        $category = DB::transaction(function () use ($data, $auditLogger, $request): Category {
            $category = new Category;
            $category->tenant_id = $this->tenantId();
            $category->fill($data);
            $category->save();

            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'category.created',
                'category',
                $category->id,
                $request,
            );

            return $category;
        });

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(string $categoryId): CategoryResource
    {
        return new CategoryResource(
            Category::query()->forTenant($this->tenantId())->findOrFail($categoryId),
        );
    }

    public function update(Request $request, string $categoryId, AuditLogger $auditLogger): CategoryResource|JsonResponse
    {
        $category = Category::query()->forTenant($this->tenantId())->findOrFail($categoryId);
        $data = $this->validatedCategory($request, $category->id);
        $this->assertParentBelongsToTenant($data['parent_id'] ?? null);

        $parentId = $data['parent_id'] ?? $category->parent_id;
        if ($parentId !== null) {
            $ancestor = Category::query()->forTenant($this->tenantId())->findOrFail($parentId);
            while ($ancestor !== null) {
                if ($ancestor->id === $category->id) {
                    return response()->json(['message' => 'A category cannot be its own ancestor.'], 422);
                }

                $ancestor = $ancestor->parent_id === null
                    ? null
                    : Category::query()->forTenant($this->tenantId())->find($ancestor->parent_id);
            }
        }

        DB::transaction(function () use ($data, $auditLogger, $category, $request): void {
            $category->fill($data);
            $category->save();

            if ($category->wasChanged()) {
                $auditLogger->web(
                    $this->tenantContext(),
                    $request->user(),
                    'category.updated',
                    'category',
                    $category->id,
                    $request,
                );
            }
        });

        return new CategoryResource($category->refresh());
    }

    public function destroy(string $categoryId, Request $request, AuditLogger $auditLogger)
    {
        $category = Category::query()->forTenant($this->tenantId())->findOrFail($categoryId);
        if ($category->children()->exists() || $category->products()->exists()) {
            return response()->json([
                'message' => 'This category has dependent categories or products.',
            ], 409);
        }

        DB::transaction(function () use ($auditLogger, $category, $request): void {
            $id = $category->id;
            $category->delete();

            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'category.deleted',
                'category',
                $id,
                $request,
            );
        });

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCategory(Request $request, ?string $ignoreId = null): array
    {
        if (array_diff(
            array_keys($request->all()),
            ['parent_id', 'name', 'slug', 'position', 'is_active'],
        ) !== []) {
            abort(422, 'The request contains fields that cannot be edited.');
        }

        $slugRule = Rule::unique('categories', 'slug')->where('tenant_id', $this->tenantId());
        if ($ignoreId !== null) {
            $slugRule->ignore($ignoreId);
        }
        $required = $ignoreId === null ? 'required' : 'sometimes';

        return $request->validate([
            'parent_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('categories', 'id')->where('tenant_id', $this->tenantId())],
            'name' => [$required, 'string', 'max:160'],
            'slug' => [
                $required,
                'string',
                'max:180',
                $slugRule,
            ],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function assertParentBelongsToTenant(?string $parentId): void
    {
        if ($parentId !== null) {
            Category::query()->forTenant($this->tenantId())->findOrFail($parentId);
        }
    }
}
