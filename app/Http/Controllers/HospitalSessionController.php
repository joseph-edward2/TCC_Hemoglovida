<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class HospitalSessionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'regex:/\A(?:[0-9]{14}|[0-9]{2}\.[0-9]{3}\.[0-9]{3}\/[0-9]{4}-[0-9]{2})\z/'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'login.*' => 'Informe um CNPJ de 14 dígitos.',
            'password.*' => 'Informe uma senha válida.',
        ]);

        $cnpj = str_replace(['.', '/', '-'], '', $credentials['login']);
        if (! Auth::guard('hospital')->attempt(['cnpj_hosp' => $cnpj, 'password' => $credentials['password']])) {
            throw ValidationException::withMessages(['login' => 'CNPJ ou senha inválidos.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('hospital.estoque'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('hospital')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('hospital.login');
    }
}
