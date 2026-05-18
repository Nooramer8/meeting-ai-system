<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssigneeController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $assignees = User::query()
            ->select('id', 'name', 'email', 'phone', 'position', 'role', 'created_at')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'ilike', $search)
                        ->orWhere('email', 'ilike', $search)
                        ->orWhere('phone', 'ilike', $search)
                        ->orWhere('position', 'ilike', $search);
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return $this->success($assignees);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::create([
            ...$validated,
            'role' => 'member',
            'password' => Hash::make(Str::random(32)),
        ]);

        return $this->success(
            $user->only(['id', 'name', 'email', 'phone', 'position', 'role', 'created_at']),
            'Assignee created.',
            201
        );
    }
}
