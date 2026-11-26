<?php

namespace App\Http\Controllers;

use App\Models\Funcionario;
use App\Rules\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class HospitalSessionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'regex:/\A(?:[0-9]{11}|[0-9]{14}|[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}|[0-9]{2}\.[0-9]{3}\.[0-9]{3}\/[0-9]{4}-[0-9]{2})\z/'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'login.*' => 'Informe um CNPJ de 14 dígitos ou um CPF válido.',
            'password.*' => 'Informe uma senha válida.',
        ]);

        $document = str_replace(['.', '-', '/'], '', $credentials['login']);
        $isCnpj = strlen($document) === 14;

        $documentValido = $isCnpj ? $this->validCnpj($document) : Cpf::isValid($document);

        if (! $documentValido) {
            throw ValidationException::withMessages(['login' => 'Informe um CNPJ de 14 dígitos ou um CPF válido.']);
        }

        $guard = Auth::guard($isCnpj ? 'hospital' : 'funcionario');
        $column = $isCnpj ? 'cnpj_hosp' : 'cpf_fun';

        if (! $guard->attempt([$column => $document, 'password' => $credentials['password']])) {
            if ($guard->getLastAttempted() !== null) {
                throw ValidationException::withMessages(['password' => 'Senha inválida']);
            }

            throw ValidationException::withMessages(['login' => 'CNPJ, CPF ou senha inválidos.']);
        }

        if (! $isCnpj) {
            $funcionario = $guard->user();

            if (! $funcionario instanceof Funcionario || blank($funcionario->cnpj_hosp)) {
                $guard->logout();

                throw ValidationException::withMessages([
                    'login' => 'Este CPF não está vinculado a um hospital.',
                ]);
            }
        }

        $request->session()->regenerate();

        return redirect()->intended(route('hospital.estoque'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('hospital')->logout();
        Auth::guard('funcionario')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('hospital.login');
    }

    private function validCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weightsOne = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weightsTwo = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([[$weightsOne, 12], [$weightsTwo, 13]] as [$weights, $position]) {
            $sum = 0;

            foreach ($weights as $index => $weight) {
                $sum += ((int) $cnpj[$index]) * $weight;
            }

            $remainder = $sum % 11;
            $expected = $remainder < 2 ? 0 : 11 - $remainder;

            if ((int) $cnpj[$position] !== $expected) {
                return false;
            }
        }

        return true;
    }
}
