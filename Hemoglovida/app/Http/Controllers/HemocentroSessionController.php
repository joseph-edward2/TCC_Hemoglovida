<?php

namespace App\Http\Controllers;

use App\Rules\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class HemocentroSessionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'regex:/\A(?:[0-9]{7}|[0-9]{11}|[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2})\z/'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'login.*' => 'Informe um CNES de 7 dígitos ou um CPF válido.',
            'password.*' => 'Informe uma senha válida.',
        ]);

        $document = str_replace(['.', '-'], '', $credentials['login']);
        $isCnes = strlen($document) === 7;

        if ($isCnes ? $document === '0000000' : ! Cpf::isValid($document)) {
            throw ValidationException::withMessages(['login' => 'Informe um CNES de 7 dígitos ou um CPF válido.']);
        }

        $guard = Auth::guard($isCnes ? 'hemocentro' : 'funcionario');
        $column = $isCnes ? 'cnes_hem' : 'cpf_fun';

        if (! $guard->attempt([$column => $document, 'password' => $credentials['password']])) {
            if ($isCnes && $guard->getLastAttempted() !== null) {
                throw ValidationException::withMessages(['password' => 'Senha inválida']);
            }

            throw ValidationException::withMessages(['login' => 'CNES, CPF ou senha inválidos.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('hemocentro.inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('hemocentro')->logout();
        Auth::guard('funcionario')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('hemocentro.login');
    }
}
