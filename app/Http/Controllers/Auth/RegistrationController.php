<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterAccountRequest;
use App\Models\CertificationBody;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class RegistrationController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', [
            'selectedRole' => $request->query('role') === User::ROLE_INSTITUTION ? User::ROLE_INSTITUTION : User::ROLE_APPLICANT,
        ]);
    }

    public function store(RegisterAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
                'is_active' => true,
                'email_verified_at' => null,
            ]);
            if ($data['role'] === User::ROLE_INSTITUTION) {
                $user->certificationBody()->create([
                    'name' => $data['body_name'],
                    'contact' => $data['contact'] ?? null,
                    'status' => CertificationBody::STATUS_PENDING,
                    'verified_at' => null,
                ]);
            }

            return $user;
        });

        $status = 'Akun berhasil dibuat. Periksa email Anda untuk membuka tautan verifikasi.';
        try {
            event(new Registered($user));
            RateLimiter::hit(EmailVerificationController::rateLimitKey($user->email), 60);
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Pengiriman email verifikasi belum berhasil.', ['user_id' => $user->id]);
            $status = 'Akun berhasil dibuat, tetapi email belum berhasil dikirim. Silakan gunakan kirim ulang tautan.';
        }

        return redirect()->route('verification.notice')
            ->with('registration_email', $user->email)
            ->with('status', $status);
    }
}
