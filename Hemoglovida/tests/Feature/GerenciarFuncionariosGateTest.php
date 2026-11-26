<?php

use App\Enums\CargoFuncionario;
use App\Models\Funcionario;
use App\Models\Hemocentro;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('o gate de gestão considera o guard, o cargo e o vínculo do funcionário', function (callable $criarUsuario, bool $esperadoHospital, bool $esperadoHemocentro) {
    $usuario = $criarUsuario();

    match (true) {
        $usuario instanceof Hospital => Auth::guard('hospital')->setUser($usuario),
        $usuario instanceof Hemocentro => Auth::guard('hemocentro')->setUser($usuario),
        default => Auth::guard('funcionario')->setUser($usuario),
    };

    expect(Gate::forUser($usuario)->allows('gerenciar-funcionarios', 'hospital'))->toBe($esperadoHospital)
        ->and(Gate::forUser($usuario)->allows('gerenciar-funcionarios', 'hemocentro'))->toBe($esperadoHemocentro);
})->with([
    'hospital' => [
        fn () => Hospital::factory()->create(),
        true,
        false,
    ],
    'hemocentro' => [
        fn () => Hemocentro::factory()->create(),
        false,
        true,
    ],
    'responsável do hospital' => [
        function () {
            $hospital = Hospital::factory()->create();

            return Funcionario::factory()->paraHospital($hospital)->create([
                'cpf_fun' => '52998224725',
                'tipo_fun' => CargoFuncionario::Responsavel->value,
            ]);
        },
        true,
        false,
    ],
    'responsável do hemocentro' => [
        function () {
            $hemocentro = Hemocentro::factory()->create();

            return Funcionario::factory()->paraHemocentro($hemocentro)->create([
                'cpf_fun' => '28001238938',
                'tipo_fun' => CargoFuncionario::Responsavel->value,
            ]);
        },
        false,
        true,
    ],
    'administrativo do hospital' => [
        function () {
            $hospital = Hospital::factory()->create();

            return Funcionario::factory()->paraHospital($hospital)->create([
                'cpf_fun' => '12345678909',
                'tipo_fun' => CargoFuncionario::Administrativo->value,
            ]);
        },
        false,
        false,
    ],
    'administrativo do hemocentro' => [
        function () {
            $hemocentro = Hemocentro::factory()->create();

            return Funcionario::factory()->paraHemocentro($hemocentro)->create([
                'cpf_fun' => '11144477735',
                'tipo_fun' => CargoFuncionario::Administrativo->value,
            ]);
        },
        false,
        false,
    ],
]);

test('o alias cadastrar-funcionario segue a mesma regra do gate de gestão', function () {
    $hospital = Hospital::factory()->create();
    Auth::guard('hospital')->setUser($hospital);

    expect(Gate::forUser($hospital)->allows('cadastrar-funcionario', 'hospital'))->toBeTrue()
        ->and(Gate::forUser($hospital)->allows('cadastrar-funcionario', 'hemocentro'))->toBeFalse()
        ->and(Gate::forUser($hospital)->allows('gerenciar-funcionarios', 'instituicao-invalida'))->toBeFalse();
});
