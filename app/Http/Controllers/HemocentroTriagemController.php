<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class HemocentroTriagemController extends Controller
{
    public function index(Request $request): View
    {
        $bags = $this->pendingBags($request)->orderBy('isbt')->get(['isbt', 'tipo_bolsa', 'laudo_path']);

        return view('hemocentro.Hem_Triagem', compact('bags'));
    }

    public function anexar(Request $request, string $isbt): RedirectResponse
    {
        $bag = $this->pendingBags($request)->where('isbt', $isbt)->whereNull('laudo_path');
        abort_unless($bag->exists(), 404);

        $request->validate(['laudo' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240']], [
            'laudo.required' => 'Selecione um laudo.',
            'laudo.mimes' => 'Envie um laudo em PDF, PNG ou JPG.',
            'laudo.max' => 'O laudo deve ter até 10 MB.',
            'laudo.uploaded' => 'Não foi possível enviar o laudo.',
        ]);

        $path = $request->file('laudo')->store('laudos', 'local');
        abort_if($path === false, 500, 'Não foi possível salvar o laudo.');

        try {
            abort_unless($bag->update(['laudo_path' => $path]) === 1, 409, 'A bolsa já foi atualizada.');
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return to_route('hemocentro.triagem')->with('status', "Laudo anexado à bolsa {$isbt}.");
    }

    public function laudo(Request $request, string $isbt): StreamedResponse
    {
        $path = $this->pendingBags($request)->where('isbt', $isbt)->value('laudo_path');
        $disk = Storage::disk('local');
        abort_unless($path !== null && $disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function concluir(Request $request, string $isbt): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', 'string', 'in:disponivel,recusada']])['status'];
        $bag = $this->pendingBags($request)->where('isbt', $isbt);
        $updated = (clone $bag)->whereNotNull('laudo_path')->update(['disponibilidade_bolsa' => $status]);

        if ($updated === 0) {
            abort_unless($bag->exists(), 404);

            return to_route('hemocentro.triagem')->withErrors(['laudo' => 'Anexe o laudo antes de concluir a triagem.']);
        }

        $result = $status === 'recusada' ? 'recusada' : 'liberada para o estoque';

        return to_route('hemocentro.triagem')->with('status', "Bolsa {$isbt} {$result}.");
    }

    private function pendingBags(Request $request): Builder
    {
        return DB::table('bolsa_sangue')
            ->where('cnes_hem', $request->user()->cnes_hem)
            ->whereRaw("LOWER(TRIM(REPLACE(disponibilidade_bolsa, '_', ' '))) IN (?, ?)", ['em analise', 'em análise']);
    }
}
