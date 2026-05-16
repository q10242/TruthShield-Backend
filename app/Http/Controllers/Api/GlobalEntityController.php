<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GlobalEntity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GlobalEntityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = GlobalEntity::query()->withCount('eventEntities');

        if ($request->filled('q')) {
            $q = '%'.$request->get('q').'%';
            $query->where(fn ($b) => $b->where('name', 'like', $q)->orWhere('description', 'like', $q));
        }

        if ($request->filled('type')) {
            $query->where('entity_type', $request->get('type'));
        }

        $entities = $query->orderBy('name')->limit((int) $request->get('limit', 30))->get();

        return response()->json(['data' => $entities]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'entity_type' => ['required', 'string', Rule::in(['person', 'organization'])],
            'aliases' => ['nullable', 'array', 'max:10'],
            'aliases.*' => ['string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'wikipedia_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $entity = GlobalEntity::create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['data' => $entity->loadCount('eventEntities')], 201);
    }

    public function show(GlobalEntity $globalEntity): JsonResponse
    {
        $globalEntity->loadCount('eventEntities');
        $appearances = $globalEntity->eventEntities()
            ->with('event:id,name,slug')
            ->select(['id', 'news_event_id', 'global_entity_id', 'name', 'entity_type', 'description'])
            ->get();

        return response()->json([
            'data' => $globalEntity,
            'appearances' => $appearances,
        ]);
    }
}
