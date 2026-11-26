<?php

namespace App;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoRequisicao
{
    public static function url(?string $valor, int $pedido, string $rota): ?string
    {
        $valor = trim($valor ?? '');
        if (str_starts_with($valor, 'requisicoes-pedido/')) {
            return route($rota, $pedido);
        }
        if (Str::isUrl($valor, ['http', 'https'])) {
            return $valor;
        }
        if ($valor === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)|[[:cntrl:]\\\\]~i', $valor)) {
            return null;
        }

        return url('/'.ltrim($valor, '/'));
    }

    public static function exibir(?string $arquivo, int $pedido): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless(preg_match('~^requisicoes-pedido/[a-zA-Z0-9]+\.pdf$~D', $arquivo ?? '') && $disk->exists($arquivo), 404);

        return $disk->response($arquivo, "requisicao-{$pedido}.pdf", ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
