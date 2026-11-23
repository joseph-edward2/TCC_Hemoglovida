<?php

namespace App\Http\Controllers;

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

        if ($isCnes ? $document === '0000000' : ! $this->validCpf($document)) {
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
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('hemocentro.login');
    }

    private function validCpf(string $cpf): bool
    {
        if ($cpf === str_repeat($cpf[0], 11)) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $cpf[$index] * ($position + 1 - $index);
            }

            $remainder = $sum % 11;
            if ((int) $cpf[$position] !== ($remainder < 2 ? 0 : 11 - $remainder)) {
                return false;
            }
        }

        return true;
    }
}
