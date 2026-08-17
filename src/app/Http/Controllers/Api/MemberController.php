<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(
        private readonly MemberService $memberService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Member::class);

        $members = Member::query()
            ->when($request->search, fn ($q, $search) =>
                $q->where(fn ($q) =>
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('member_number', 'like', "%{$search}%")
                )
            )
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->category, fn ($q, $category) => $q->where('category', $category))
            ->orderBy('last_name')
            ->paginate($request->per_page ?? 25);

        return response()->json($members);
    }

    public function show(Member $member): JsonResponse
    {
        $this->authorize('view', $member);

        return response()->json($member->load('groups'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Member::class);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'unique:members,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
            'status' => ['required', 'in:aktiv,ruhend,ausgetreten,ausgeschlossen'],
            'category' => ['required', 'in:vollmitglied,foerdermitglied,ehrenmitglied,jugend'],
            'membership_start' => ['nullable', 'date'],
            'gdpr_consent' => ['boolean'],
        ]);

        $member = $this->memberService->create($validated, $request->user()->id);

        return response()->json($member, 201);
    }

    public function update(Request $request, Member $member): JsonResponse
    {
        $this->authorize('update', $member);

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['nullable', 'email', "unique:members,email,{$member->id}"],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'in:aktiv,ruhend,ausgetreten,ausgeschlossen'],
        ]);

        $member = $this->memberService->update($member, $validated, $request->user()->id);

        return response()->json($member);
    }

    public function destroy(Member $member): JsonResponse
    {
        $this->authorize('delete', $member);

        $member->delete();

        return response()->json(null, 204);
    }

    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', Member::class);

        return response()->json($this->memberService->getStatistics());
    }
}
