<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class NewPasswordController extends Controller
{
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        $isExpired = ! $record
            || Carbon::parse($record->created_at)->addMinutes((int) config('auth.passwords.users.expire', 60))->isPast();

        if ($isExpired || ! Hash::check($validated['code'], $record->token)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['code' => '인증번호가 올바르지 않거나 만료되었습니다.']);
        }

        $user = User::query()
            ->where('email', $validated['email'])
            ->firstOrFail();

        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        event(new PasswordReset($user));

        return redirect()->route('login')->with('status', '비밀번호가 재설정되었습니다. 새 비밀번호로 로그인하세요.');
    }
}
